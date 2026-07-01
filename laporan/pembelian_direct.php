<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

require_once '../includes/page_access_check.php';

$filter_session_key = 'filter_laporan_pembelian_direct';
if (isset($_GET['reset']) && (string)$_GET['reset'] === '1') {
    unset($_SESSION[$filter_session_key]);
    header('Location: pembelian_direct.php');
    exit();
}

$saved_filters = [];
if (isset($_SESSION[$filter_session_key]) && is_array($_SESSION[$filter_session_key])) {
    $saved_filters = $_SESSION[$filter_session_key];
}

$filter_keys = ['start_date', 'end_date', 'period'];
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
        header('Location: pembelian_direct.php?' . http_build_query($redirect_params));
        exit();
    }
}

// Default filter values
$start_date = isset($_GET['start_date']) ? (string)$_GET['start_date'] : (string)($saved_filters['start_date'] ?? date('Y-m-01'));
$end_date = isset($_GET['end_date']) ? (string)$_GET['end_date'] : (string)($saved_filters['end_date'] ?? date('Y-m-d'));
$period = isset($_GET['period']) ? (string)$_GET['period'] : (string)($saved_filters['period'] ?? 'daily');

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
];

// Query untuk mengambil data pembelian direct
$sql = "SELECT 
            COALESCE(b.nama_barang, dp.keterangan) as nama_barang,
            p.tanggal,
            SUM(dp.jumlah) as total_item,
            SUM(dp.jumlah * dp.harga_satuan) as total_harga,
            GROUP_CONCAT(DISTINCT p.nama_toko) as nama_toko_list,
            GROUP_CONCAT(DISTINCT p.keterangan) as keterangan_list,
            GROUP_CONCAT(DISTINCT u.nama) as created_by_names
        FROM direct_purchase p
        LEFT JOIN detail_direct_purchase dp ON dp.direct_purchase_id = p.id AND dp.barang_id IS NOT NULL
        LEFT JOIN barang b ON dp.barang_id = b.id
        LEFT JOIN users u ON p.created_by = u.id
        WHERE p.tanggal BETWEEN ? AND ?
            AND p.status = 'stok_masuk'
        GROUP BY COALESCE(b.nama_barang, dp.keterangan), p.tanggal
        ORDER BY p.tanggal DESC, COALESCE(b.nama_barang, dp.keterangan)";


$stmt = $conn->prepare($sql);
if ($stmt === false) {
    die("Error preparing query: " . $conn->error);
}
$stmt->bind_param('ss', $start_date, $end_date);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Pembelian Direct - Sistem Inventory</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.0.7/css/boxicons.min.css" rel="stylesheet">
</head>
<body>
    <?php include '../templates/navbar.php'; ?>

    <div class="container-fluid mt-4">
        <div class="row mb-3">
            <div class="col">
                <h2>Laporan Pembelian Direct</h2>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Periode</label>
                        <select name="period" class="form-select">
                            <option value="daily" <?= $period == 'daily' ? 'selected' : '' ?>>Harian</option>
                            <option value="weekly" <?= $period == 'weekly' ? 'selected' : '' ?>>Mingguan</option>
                            <option value="monthly" <?= $period == 'monthly' ? 'selected' : '' ?>>Bulanan</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Tanggal Awal</label>
                        <input type="date" class="form-control" name="start_date" value="<?= $start_date ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Tanggal Akhir</label>
                        <input type="date" class="form-control" name="end_date" value="<?= $end_date ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">&nbsp;</label>
                        <div>
                            <button type="submit" class="btn btn-primary">
                                <i class='bx bx-filter-alt'></i> Filter
                            </button>
                            <a href="pembelian_direct_print.php?start_date=<?= $start_date ?>&end_date=<?= $end_date ?>&period=<?= $period ?>" 
                               class="btn btn-info" target="_blank"> <!-- Ubah warna tombol Cetak jika ingin -->
                                <i class='bx bx-printer'></i> Cetak
                            </a>
                            <a href="pembelian_direct_excel.php?start_date=<?= $start_date ?>&end_date=<?= $end_date ?>&period=<?= $period ?>" 
                               class="btn btn-success"> <!-- Ubah warna tombol Excel jika ingin -->
                                <i class='bx bx-download'></i> Excel
                            </a>
                            <a id="btnSavePDF" onclick="generatePDF()" class="btn btn-danger">
                                <i class='bx bx-save as pdf'></i> PDF
                            </a>
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
                                <th>Nama Barang</th>
                                <th>Total Item</th>
                                <th>Total Harga</th>
                                <th>Nama Toko</th>
                                <th>Keterangan</th>
                                <th>Created by</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $no = 1;
                            $total_item = 0;
                            $total_harga = 0;
                            while($row = $result->fetch_assoc()): 
                                $total_item += $row['total_item'];
                                $total_harga += $row['total_harga'];
                            ?>
                            <tr>
                                <td><?= $no++ ?></td>
                                <td><?= date('d/m/Y', strtotime($row['tanggal'])) ?></td>
                                <td><?= htmlspecialchars($row['nama_barang']) ?></td>
                                <td class="text-end"><?= number_format($row['total_item']) ?></td>
                                <td class="text-end"><?= number_format($row['total_harga']) ?></td>
                                <td><?= htmlspecialchars($row['nama_toko_list']) ?></td>
                                <td><?= htmlspecialchars($row['keterangan_list']) ?></td>
                                <td><?= htmlspecialchars($row['created_by_names']) ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="3" class="text-end">Total:</th>
                                <th class="text-end"><?= number_format($total_item) ?></th>
                                <th class="text-end"><?= number_format($total_harga) ?></th>
                                <th colspan="3"></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>

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
            LAPORAN PEMBELIAN DIRECT<br>
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
        
        doc.save('Laporan_Pembelian_Direct.pdf');
        document.body.removeChild(pdfContainer);
    });
}
</script>
</div>
</body>
</html>

  
