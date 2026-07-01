<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

require_once '../includes/page_access_check.php';

$filter_session_key = 'filter_laporan_stok_keluar';
if (isset($_GET['reset']) && (string)$_GET['reset'] === '1') {
    unset($_SESSION[$filter_session_key]);
    header('Location: stok_keluar.php');
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
        header('Location: stok_keluar.php?' . http_build_query($redirect_params));
        exit();
    }
}

// Default filter values
$start_date = isset($_GET['start_date']) ? (string)$_GET['start_date'] : (string)($saved_filters['start_date'] ?? date('Y-m-01'));
$end_date = isset($_GET['end_date']) ? (string)$_GET['end_date'] : (string)($saved_filters['end_date'] ?? date('Y-m-d'));
$period = isset($_GET['period']) ? (string)$_GET['period'] : (string)($saved_filters['period'] ?? 'daily');
$gudang_id = isset($_GET['gudang_id']) ? (string)$_GET['gudang_id'] : (string)($saved_filters['gudang_id'] ?? '');

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

// Query untuk dropdown gudang
$gudang_sql = "SELECT id, nama_gudang FROM gudang ORDER BY nama_gudang";
$gudang_options = $conn->query($gudang_sql);

// Query untuk mengambil data stok keluar dari transaksi_stok dan detail_transaksi_stok
$sql = "SELECT
            ts.tanggal,
            b.kode_barang,
            b.nama_barang,
            dts.jumlah, -- Mengambil jumlah dari detail_transaksi_stok
            g.nama_gudang,
            u.username as created_by_username -- Tambahkan created_by_username
        FROM detail_transaksi_stok dts -- Mulai dari detail
        JOIN transaksi_stok ts ON dts.transaksi_stok_id = ts.id -- Bergabung ke transaksi header
        LEFT JOIN barang b ON dts.barang_id = b.id -- Bergabung ke barang
        JOIN gudang g ON ts.gudang_id = g.id -- Bergabung ke gudang
        LEFT JOIN users u ON ts.created_by = u.id -- Join dengan tabel users
        WHERE ts.jenis_transaksi = 'keluar' -- Filter hanya transaksi keluar
        AND ts.tanggal BETWEEN ? AND ? -- Filter berdasarkan tanggal
        ".($gudang_id ? "AND ts.gudang_id = ?" : "")." -- Filter berdasarkan gudang jika dipilih
        ORDER BY ts.tanggal DESC, b.kode_barang";


        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            die("Error preparing query: " . $conn->error);
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
            <title>Laporan Stok Keluar - Sistem Inventory</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
            <link href="https://cdn.jsdelivr.net/npm/boxicons@2.0.7/css/boxicons.min.css" rel="stylesheet">
            <style>
                body {
                    background-color: #f8f9fa;
                }
                .page-title {
                    margin-bottom: 2rem;
                    color: #343a40;
                    font-weight: 600;
                }
                .card {
                    border: none;
                    border-radius: 0.75rem;
                    box-shadow: 0 4px 25px rgba(0, 0, 0, 0.1);
                    margin-bottom: 2rem;
                }
                .card-header {
                    background-color: #fff;
                    border-bottom: 1px solid #dee2e6;
                    font-weight: 600;
                    padding: 1rem 1.5rem;
                }
                .form-label {
                    font-size: 0.875rem;
                    font-weight: 500;
                }
                .btn {
                    border-radius: 0.5rem;
                }
                .btn i {
                    margin-right: 0.5rem;
                }
                .table thead {
                    background-color: #0008f9;
                    color: white;
                }
            </style>
        </head>
        <body>
            <?php include '../templates/navbar.php'; ?>
            <div class="container-fluid mt-4">
                <h2 class="page-title">Laporan Stok Keluar</h2>
        
                <div class="card mb-4">
                    <div class="card-header">Filter & Aksi</div>
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
                                <label class="form-label">Tanggal Awal</label>
                                <input type="date" class="form-control" name="start_date" value="<?= $start_date ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Tanggal Akhir</label>
                                <input type="date" class="form-control" name="end_date" value="<?= $end_date ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Gudang</label>
                                <select name="gudang_id" class="form-select">
                                    <option value="">Semua Gudang</option>
                                    <?php 
                                    // Reset pointer gudang_options
                                    $gudang_options->data_seek(0);
                                    while($gudang = $gudang_options->fetch_assoc()): ?>
                                        <option value="<?= $gudang['id'] ?>" <?= $gudang_id == $gudang['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($gudang['nama_gudang']) ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class='bx bx-filter-alt'></i> Filter
                                </button>
                            </div>
                        </form>
                        <hr>
                        <div class="d-flex justify-content-end gap-2">
                            <a href="stok_keluar_print.php?start_date=<?= $start_date ?>&end_date=<?= $end_date ?>&period=<?= $period ?>&gudang_id=<?= $gudang_id ?>"
                               class="btn btn-outline-secondary" target="_blank">
                                <i class='bx bx-printer'></i> Cetak
                            </a>
                            <a href="stok_keluar_excel.php?start_date=<?= $start_date ?>&end_date=<?= $end_date ?>&period=<?= $period ?>&gudang_id=<?= $gudang_id ?>"
                               class="btn btn-outline-success">
                                <i class='bx bxs-spreadsheet'></i> Excel
                            </a>
                            <button onclick="generatePDF()" class="btn btn-outline-danger">
                                <i class='bx bxs-file-pdf'></i> PDF
                            </button>
                        </div>
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
                                <!-- Menghapus kolom Stok Awal dan Stok Akhir -->
                                <th>Jumlah Keluar</th>
                                <th>Gudang Asal</th>
                                <th>Created By</th> <!-- Tambah kolom Created By -->
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $no = 1;
                            $total_keluar = 0;
                            while($row = $result->fetch_assoc()):
                                $total_keluar += $row['jumlah'];
                            ?>
                            <tr>
                                <td><?= $no++ ?></td>
                                <td><?= date('d/m/Y', strtotime($row['tanggal'])) ?></td>
                                <td><?= htmlspecialchars($row['kode_barang'] ?? '-') ?></td> <!-- Tambahkan null coalescing operator -->
                                <td><?= htmlspecialchars($row['nama_barang'] ?? '-') ?></td> <!-- Tambahkan null coalescing operator -->
                                <td class="text-end"><?= number_format($row['jumlah'] ?? 0) ?></td> <!-- Tambahkan null coalescing operator -->
                                <td><?= htmlspecialchars($row['nama_gudang'] ?? '-') ?></td> <!-- Tambahkan null coalescing operator -->
                                <td><?= htmlspecialchars($row['created_by_username'] ?? 'N/A') ?></td> <!-- Tampilkan username -->
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="4" class="text-end">Total Keluar:</th> <!-- Sesuaikan colspan -->
                                <th class="text-end"><?= number_format($total_keluar) ?></th>
                                <th colspan="3"></th> <!-- Sesuaikan colspan -->
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
            LAPORAN STOK KELUAR<br>
            <span style="font-size: 30px; font-weight: 30px;">
                Periode: ${document.querySelector('[name="start_date"]').value} s/d ${document.querySelector('[name="end_date"]').value}
            </span>
        </div>
         <div class="header">
       <img src="../asset/cjawilnew.png" alt="Logo" style="height: 300px; display: block; margin: 0 auto;"/>
          <span style="font-size: 10px; font-weight: 10px;">  
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
        
        doc.save('Laporan_Stok_Keluar.pdf');
        document.body.removeChild(pdfContainer);
    });
}
</script>
</div>
</body>
</html>
