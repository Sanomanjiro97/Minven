<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

require_once '../includes/page_access_check.php';

$filter_session_key = 'filter_laporan_stok_transfer';
if (isset($_GET['reset']) && (string)$_GET['reset'] === '1') {
    unset($_SESSION[$filter_session_key]);
    header('Location: stok_transfer.php');
    exit();
}

$saved_filters = [];
if (isset($_SESSION[$filter_session_key]) && is_array($_SESSION[$filter_session_key])) {
    $saved_filters = $_SESSION[$filter_session_key];
}

$filter_keys = ['start_date', 'end_date', 'period', 'gudang_id'];
$has_any_filter_param = false;
foreach ($filter_keys as $k) {
    if (array_key_exists($k, $_GET)) {
        $has_any_filter_param = true;
        break;
    }
}

if (!$has_any_filter_param && !empty($saved_filters)) {
    $redirect_params = [];
    foreach ($filter_keys as $k) {
        if (array_key_exists($k, $saved_filters) && (string)$saved_filters[$k] !== '') {
            $redirect_params[$k] = (string)$saved_filters[$k];
        }
    }
    if (!empty($redirect_params)) {
        header('Location: stok_transfer.php?' . http_build_query($redirect_params));
        exit();
    }
}

// Default filter values
$start_date = isset($_GET['start_date']) ? (string)$_GET['start_date'] : (string)($saved_filters['start_date'] ?? date('Y-m-01'));
$end_date = isset($_GET['end_date']) ? (string)$_GET['end_date'] : (string)($saved_filters['end_date'] ?? date('Y-m-d'));
$period = isset($_GET['period']) ? (string)$_GET['period'] : (string)($saved_filters['period'] ?? 'daily');
$gudang_id = isset($_GET['gudang_id']) ? (string)$_GET['gudang_id'] : (string)($saved_filters['gudang_id'] ?? '');

// Query untuk dropdown gudang
$gudang_sql = "SELECT id, nama_gudang FROM gudang ORDER BY nama_gudang";
$gudang_options = $conn->query($gudang_sql);

// Adjust dates based on period
if ($period == 'weekly') {
    $start_date = date('Y-m-d', strtotime('monday this week'));
    $end_date = date('Y-m-d', strtotime('sunday this week'));
} elseif ($period == 'monthly') {
    $start_date = date('Y-m-01');
    $end_date = date('Y-m-t');
}

$_SESSION[$filter_session_key] = [
    'start_date' => $start_date,
    'end_date' => $end_date,
    'period' => $period,
    'gudang_id' => $gudang_id,
];

// Query untuk mengambil data stok transfer
$sql = "SELECT 
            tt.tanggal,
            b.kode_barang,
            b.nama_barang,
            s.nama_satuan,
            dtt.jumlah,
            dtt.keterangan,
            u.nama as created_by_name,
            g_asal.nama_gudang as gudang_asal,
            g_tujuan.nama_gudang as gudang_tujuan,
            gs_asal.stok_awal as stok_awal_asal,
            gs_asal.jumlah as stok_akhir_asal,
            gs_tujuan.stok_awal as stok_awal_tujuan,
            gs_tujuan.jumlah as stok_akhir_tujuan
        FROM transaksi_transfer tt
        JOIN detail_transaksi_transfer dtt ON tt.id = dtt.transaksi_transfer_id
        JOIN barang b ON dtt.barang_id = b.id
        LEFT JOIN satuan s ON b.satuan_id = s.id
        LEFT JOIN users u ON tt.created_by = u.id
        LEFT JOIN gudang g_asal ON tt.gudang_asal_id = g_asal.id
        LEFT JOIN gudang g_tujuan ON tt.gudang_tujuan_id = g_tujuan.id
        LEFT JOIN gudang_stok gs_asal ON (gs_asal.barang_id = b.id AND gs_asal.gudang_id = tt.gudang_asal_id AND gs_asal.detail_barang = dtt.keterangan)
        LEFT JOIN gudang_stok gs_tujuan ON (gs_tujuan.barang_id = b.id AND gs_tujuan.gudang_id = tt.gudang_tujuan_id AND gs_tujuan.detail_barang = dtt.keterangan)
        WHERE tt.tanggal BETWEEN ? AND ?
        ".($gudang_id ? "AND tt.gudang_asal_id = ?" : "")."
        ORDER BY tt.tanggal DESC, b.kode_barang";

        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            die("Error preparing statement: " . $conn->error);
        }
        if ($gudang_id) {
            $stmt->bind_param('ssi', $start_date, $end_date, $gudang_id);
        } else {
            $stmt->bind_param('ss', $start_date, $end_date);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        ?>
        
        <!DOCTYPE html>
        <html lang="id">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Laporan Stok Transfer - Sistem Inventory</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
            <link href="https://cdn.jsdelivr.net/npm/boxicons@2.0.7/css/boxicons.min.css" rel="stylesheet">
            <style>
                body {
                    background-color: #f8f9fa;
                }
                .card {
                    border-radius: 0.75rem;
                    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
                }
                .card-header {
                    background-color: #fff;
                    border-bottom: 1px solid #dee2e6;
                    font-weight: 600;
                }
                .form-label {
                    font-size: 0.875rem;
                    font-weight: 500;
                }
                .btn i {
                    margin-right: 0.5rem;
                }
                .table thead {
                    background-color: #0008f9;
                    color: white;
                }
                .table tbody tr:hover {
                    background-color: #f1f3f5;
                }
                .status-aman {
                    color: #198754;
                    font-weight: 500;
                }
                .status-minimum {
                    color: #ffc107;
                    font-weight: 500;
                }
                .badge.bg-success-light {
                    background-color: rgba(25, 135, 84, 0.15);
                    color: #198754;
                }
                .badge.bg-warning-light {
                    background-color: rgba(255, 193, 7, 0.15);
                    color: #ffc107;
                }
                .page-header {
                    display: flex;
                    align-items: center;
                    margin-bottom: 1.5rem;
                }
                .page-header img {
                    height: 40px;
                    margin-right: 1rem;
                }
                .page-header h2 {
                    font-size: 1.75rem;
                    font-weight: 600;
                    margin: 0;
                }
            </style>
        </head>
        <body>
            <?php include '../templates/navbar.php'; ?>
        
            <div class="container-fluid mt-4">
                <div class="page-header">
                    <img src="../asset/cjawilnew.png" alt="Logo">
                    <h2>Laporan Stok Transfer</h2>
                </div>
                
                <?php if ($gudang_id): 
                    $gudang_name_sql = "SELECT nama_gudang FROM gudang WHERE id = ?";
                    $gudang_name_stmt = $conn->prepare($gudang_name_sql);
                    $gudang_name_stmt->bind_param('i', $gudang_id);
                    $gudang_name_stmt->execute();
                    $gudang_name_result = $gudang_name_stmt->get_result();
                    $gudang_name = $gudang_name_result->fetch_assoc()['nama_gudang'];
                ?>
                <div class="alert alert-info">
                    <i class='bx bx-info-circle'></i> Menampilkan data transfer dari gudang: <strong><?= htmlspecialchars($gudang_name) ?></strong>
                </div>
                <?php endif; ?>
        
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3 align-items-end">
                            <div class="col-md-2">
                                <label class="form-label">Periode</label>
                                <select name="period" class="form-select">
                                    <option value="daily" <?= $period == 'daily' ? 'selected' : '' ?>>Harian</option>
                                    <option value="weekly" <?= $period == 'weekly' ? 'selected' : '' ?>>Mingguan</option>
                                    <option value="monthly" <?= $period == 'monthly' ? 'selected' : '' ?>>Bulanan</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Gudang</label>
                                <select name="gudang_id" class="form-select">
                                    <option value="">Semua Gudang</option>
                                    <?php 
                                    mysqli_data_seek($gudang_options, 0);
                                    while($gudang = $gudang_options->fetch_assoc()): 
                                    ?>
                                        <option value="<?= $gudang['id'] ?>" <?= $gudang_id == $gudang['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($gudang['nama_gudang']) ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Tanggal Awal</label>
                                <input type="date" class="form-control" name="start_date" value="<?= $start_date ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Tanggal Akhir</label>
                                <input type="date" class="form-control" name="end_date" value="<?= $end_date ?>">
                            </div>
                            <div class="col-md-4">
                                <div class="d-flex align-items-center gap-2">
                                    <button type="submit" class="btn btn-primary"><i class='bx bx-filter-alt'></i>Filter</button>
                                    <a href="stok_transfer_print.php?start_date=<?= $start_date ?>&end_date=<?= $end_date ?>&period=<?= $period ?>&gudang_id=<?= $gudang_id ?>" class="btn btn-secondary" target="_blank"><i class='bx bx-printer'></i>Cetak</a>
                                    <a href="stok_transfer_excel.php?start_date=<?= $start_date ?>&end_date=<?= $end_date ?>&period=<?= $period ?>&gudang_id=<?= $gudang_id ?>" class="btn btn-success"><i class='bx bxs-spreadsheet'></i>Excel</a>
                                    <a id="btnSavePDF" onclick="generatePDF()" class="btn btn-danger"><i class='bx bxs-file-pdf'></i>PDF</a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
        
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped">
                                <thead>
                            <tr>
                                <th>No</th>
                                <th>Tanggal</th>
                                <th>Kode Barang</th>
                                <th>Nama Barang</th>
                                <th>Gudang Asal</th>
                                <th>Gudang Tujuan</th>
                                <th>Jumlah Transfer</th>
                                <th>Satuan</th>
                                <th>Keterangan</th>
                                <th>Updated by</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $no = 1;
                            $total_transfer = 0;
                            while($row = $result->fetch_assoc()): 
                                $total_transfer += $row['jumlah'];
                            ?>
                            <tr>
                                <td><?= $no++ ?></td>
                                <td><?= date('d/m/Y', strtotime($row['tanggal'])) ?></td>
                                <td><?= htmlspecialchars($row['kode_barang']) ?></td>
                                <td><?= htmlspecialchars($row['nama_barang']) ?></td>
                                <td><?= htmlspecialchars($row['gudang_asal']) ?></td>
                                <td><?= htmlspecialchars($row['gudang_tujuan']) ?></td>
                                <td class="text-end"><?= number_format($row['jumlah']) ?></td>
                                <td><?= htmlspecialchars($row['nama_satuan']) ?></td>
                                <td><?= htmlspecialchars($row['keterangan']) ?></td>
                                <td><?= htmlspecialchars($row['created_by_name']) ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="6" class="text-end">Total Transfer:</th>
                                <th class="text-end"><?= number_format($total_transfer) ?></th>
                                <th colspan="3"></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
// Wait for DOM to be fully loaded
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('savePdf').addEventListener('click', function() {
        // Show loading indicator
        this.innerHTML = '<i class="bx bx-loader bx-spin"></i> Processing...';
        
        // Capture the report container
        html2canvas(document.querySelector('#report-container'), {
            scale: 2, // Higher quality
            logging: false,
            useCORS: true
        }).then(canvas => {
            const pdf = new window.jspdf.jsPDF('p', 'mm', 'a4');
            const imgData = canvas.toDataURL('image/png');
            const imgWidth = 210; // A4 width in mm
            const imgHeight = canvas.height * imgWidth / canvas.width;
            
            pdf.addImage(imgData, 'PNG', 0, 0, imgWidth, imgHeight);
            pdf.save('stok_report_' + new Date().toISOString().slice(0, 10) + '.pdf');
            
            // Restore button text
            this.innerHTML = '<i class="bx bxs-file-pdf"></i> Save as PDF';
        }).catch(err => {
            console.error('Error generating PDF:', err);
            alert('Failed to generate PDF. Please try again.');
            this.innerHTML = '<i class="bx bxs-file-pdf"></i> Save as PDF';
        });
    });
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script>
function generatePDF() {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF('p', 'mm', 'a4');
    
    // Header styling
    const header = `
        <div style="
            text-align: center;
            margin-bottom: 20px;
            padding: 10px;
            font-size: 30px;
            font-weight: bold;
        ">
            <img src='../asset/cjawilnew.png' alt='Logo' style='height:60px; display:block; margin:0 auto 10px auto;'/><br>
            LAPORAN TRANSFER<br>
            <span style="font-size: 30px; font-weight: 30px;">
                Periode: ${document.querySelector('[name="start_date"]').value} s/d ${document.querySelector('[name="end_date"]').value}
            </span>
        </div>
    `;
    
    // Clone table with its container
    const tableContainer = document.querySelector('.table-responsive').parentNode.cloneNode(true);
    
    // Create PDF container
    const pdfContainer = document.createElement('div');
    pdfContainer.style.width = '100%';
    pdfContainer.style.padding = '10px';
    pdfContainer.innerHTML = header;
    pdfContainer.appendChild(tableContainer);
    document.body.appendChild(pdfContainer);
    
    // Generate PDF with auto-pagination
    html2canvas(pdfContainer, {
        scale: 0.8,
        logging: false,
        useCORS: true,
        windowWidth: pdfContainer.scrollWidth,
        windowHeight: pdfContainer.scrollHeight
    }).then(canvas => {
        const imgData = canvas.toDataURL('image/png');
        const imgWidth = 190; // A4 width - margins
        const imgHeight = (canvas.height * imgWidth) / canvas.width;
        
        let heightLeft = imgHeight;
        let position = 10; // Top margin
        const pageHeight = 277; // A4 height - margins
        
        // First page
        doc.addImage(imgData, 'PNG', 10, position, imgWidth, imgHeight);
        heightLeft -= pageHeight;
        
        // Add new pages if needed
        while (heightLeft >= 0) {
            position = heightLeft - imgHeight + 10;
            doc.addPage();
            doc.addImage(imgData, 'PNG', 10, position, imgWidth, imgHeight);
            heightLeft -= pageHeight;
        }
        
        doc.save('Laporan_Stok_transfer.pdf');
        document.body.removeChild(pdfContainer);
    });
}
</script>
</div>
</body>
</html>
