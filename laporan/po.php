<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

require_once '../includes/page_access_check.php';

$filter_session_key = 'filter_laporan_po';
if (isset($_GET['reset']) && (string)$_GET['reset'] === '1') {
    unset($_SESSION[$filter_session_key]);
    header('Location: po.php');
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
        header('Location: po.php?' . http_build_query($redirect_params));
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

// Query untuk mengambil data PO
$sql = "SELECT 
            po.id,
            po.tanggal,
            po.status,
            s.nama_supplier,
            GROUP_CONCAT(DISTINCT CASE WHEN (dpo.status IS NULL OR dpo.status != 'rejected') THEN b.nama_barang END ORDER BY b.nama_barang SEPARATOR ', ') AS nama_barang,
            SUM(CASE WHEN (dpo.status IS NULL OR dpo.status != 'rejected') THEN dpo.jumlah ELSE 0 END) AS total_item,
            u.nama AS created_by_name,
            po.keterangan
        FROM purchase_order po
        LEFT JOIN detail_purchase_order dpo ON po.id = dpo.purchase_order_id
        LEFT JOIN barang b ON dpo.barang_id = b.id
        LEFT JOIN supplier s ON po.supplier_id = s.id
        LEFT JOIN users u ON po.created_by = u.id
        WHERE po.tanggal BETWEEN ? AND ?
        GROUP BY po.id
        ORDER BY po.tanggal DESC, po.id";


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
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
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
        .table tbody tr:hover {
            background-color: #f1f3f5;
        }
        .page-header {
            text-align: center;
            margin-bottom: 1.5rem;
            display: none;
        }
        .page-header img {
            height: 40px;
            margin-bottom: 0.5rem;
        }
        .page-header h2 {
            font-size: 1.75rem;
            font-weight: 600;
            margin: 0;
        }
        .page-header .periode {
            font-size: 1rem;
            color: #6c757d;
        }
         @media print {
            body {
                background-color: #fff;
            }
            .container {
                max-width: 100% !important;
            }
            .card {
                box-shadow: none;
                border: 1px solid #dee2e6;
            }
            .no-print {
                display: none;
            }
            .page-header {
                display: block;
            }
            #report-content {
                margin-top: -5rem;
            }
        }
    </style>
</head>
<body>
    <?php include '../templates/navbar.php'; ?>

    <div class="container-fluid my-4">
        <h2 class="page-title no-print">Laporan PO</h2>

        <div class="card no-print">
            <div class= "card-header">
                Filter & Aksi
            </div>
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
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100"><i class='bx bx-filter-alt'></i>Filter</button>
                    </div>
                </form>
                <hr>
                <div class="d-flex justify-content-end gap-2">
                     <a href="po_print.php?start_date=<?= $start_date ?>&end_date=<?= $end_date ?>&period=<?= $period ?>" class="btn btn-outline-secondary" target="_blank"><i class='bx bx-printer'></i>Cetak</a>
                     <a href="po_excel.php?start_date=<?= $start_date ?>&end_date=<?= $end_date ?>&period=<?= $period ?>" class="btn btn-outline-success"><i class='bx bxs-file-excel'></i>Excel</a>
                     <button onclick="generatePDF()" class="btn btn-outline-danger"><i class='bx bxs-file-pdf'></i>PDF</button>
                </div>
            </div>
        </div>

        <div class="card" id="report-content-wrapper">
             <div class="card-header no-print">
                Hasil Laporan
            </div>
            <div class="page-header">
                <img src="../asset/cjawilnew.png" alt="Logo">
                <h2>Laporan PO</h2>
                <div class="periode">Periode: <?= date('d/m/Y', strtotime($start_date)) ?> - <?= date('d/m/Y', strtotime($end_date)) ?></div>
            </div>

            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Tanggal</th>
                                <th>Supplier</th>
                                <th>Nama Barang</th>
                                <th>Total Item</th>
                                <th>Status</th>
                                <th>Keterangan</th>
                                <th>Created By</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $no = 1;
                            $total_po = 0;
                            while($row = $result->fetch_assoc()): 
                                $total_po += $row['total_item'];
                                $status_class = '';
                                switch($row['status']) {
                                    case 'draft':
                                        $status_class = 'text-secondary';
                                        break;
                                    case 'approved':
                                        $status_class = 'text-success';
                                        break;
                                    case 'rejected':
                                        $status_class = 'text-danger';
                                        break;
                                }
                            ?>
                            <tr>
                                <td><?= $no++ ?></td>
                                <td><?= date('d/m/Y', strtotime($row['tanggal'])) ?></td>
                                <td><?= htmlspecialchars($row['nama_supplier']) ?></td>
                                <td><?= htmlspecialchars($row['nama_barang']) ?></td>
                                <td class="text-end"><?= number_format($row['total_item']) ?></td>
                                <td class="<?= $status_class ?>"><?= ucfirst($row['status']) ?></td>
                                <td><?= htmlspecialchars($row['keterangan']) ?></td>
                                <td><?= htmlspecialchars($row['created_by_name']) ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="4" class="text-end">Total Item:</th>
                                <th class="text-end"><?= number_format($total_po) ?></th>
                                <th colspan="3"></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>

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
        
        doc.save('Laporan_Purchase_Order.pdf');
        document.body.removeChild(pdfContainer);
    });
}
</script>
</div>
</body>
</html>
