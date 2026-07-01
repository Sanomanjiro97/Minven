<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

require_once '../includes/page_access_check.php';

$filter_session_key = 'filter_laporan_stok_gudang_simple';
if (isset($_GET['reset']) && (string)$_GET['reset'] === '1') {
    unset($_SESSION[$filter_session_key]);
    header('Location: stok_gudang_simple.php');
    exit();
}

$saved_filters = [];
if (isset($_SESSION[$filter_session_key]) && is_array($_SESSION[$filter_session_key])) {
    $saved_filters = $_SESSION[$filter_session_key];
}

$filter_keys = ['gudang_id', 'kategori_id', 'stok_minimum'];
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
        header('Location: stok_gudang_simple.php?' . http_build_query($redirect_params));
        exit();
    }
}

// Default filter values
$gudang_id = isset($_GET['gudang_id']) ? (string)$_GET['gudang_id'] : (string)($saved_filters['gudang_id'] ?? '');
$kategori_id = isset($_GET['kategori_id']) ? (string)$_GET['kategori_id'] : (string)($saved_filters['kategori_id'] ?? '');
$stok_minimum = isset($_GET['stok_minimum']) ? (string)$_GET['stok_minimum'] : (string)($saved_filters['stok_minimum'] ?? '');

$_SESSION[$filter_session_key] = [
    'gudang_id' => $gudang_id,
    'kategori_id' => $kategori_id,
    'stok_minimum' => $stok_minimum,
];

// Query untuk dropdown gudang
$gudang_sql = "SELECT id, nama_gudang FROM gudang WHERE status = 'aktif' ORDER BY nama_gudang";
$gudang_options = $conn->query($gudang_sql);

// Query untuk dropdown kategori
$kategori_sql = "SELECT id, nama_kategori FROM kategori ORDER BY nama_kategori";
$kategori_options = $conn->query($kategori_sql);

// Query untuk mengambil data stok gudang
$sql = "SELECT
            g.nama_gudang,
            b.kode_barang,
            b.nama_barang,
            k.nama_kategori,
            s.nama_satuan,
            gs.stok_awal,
            gs.stok_terpakai,
            gs.stok_sisa,
            b.stok_minimum,
            gs.expire_date,
            gs.batch_number,
            gs.updated_at
        FROM gudang_stok gs
        JOIN gudang g ON gs.gudang_id = g.id
        JOIN barang b ON gs.barang_id = b.id
        LEFT JOIN kategori k ON b.kategori_id = k.id
        LEFT JOIN satuan s ON b.satuan_id = s.id
        WHERE g.status = 'aktif'
        ".($gudang_id ? "AND gs.gudang_id = ?" : "")."
        ".($kategori_id ? "AND b.kategori_id = ?" : "")."
        ".($stok_minimum === 'low' ? "AND gs.stok_sisa <= b.stok_minimum" : "")."
        ".($stok_minimum === 'critical' ? "AND gs.stok_sisa <= (b.stok_minimum * 0.5)" : "")."
        ORDER BY g.nama_gudang, b.kode_barang";

$stmt = $conn->prepare($sql);
if ($stmt === false) {
    die("Error preparing query: " . $conn->error);
}

// Bind parameters berdasarkan filter yang dipilih
$param_types = '';
$param_values = [];

if ($gudang_id) {
    $param_types .= 'i';
    $param_values[] = $gudang_id;
}

if ($kategori_id) {
    $param_types .= 'i';
    $param_values[] = $kategori_id;
}

if (!empty($param_values)) {
    $stmt->bind_param($param_types, ...$param_values);
}

$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Stok Gudang - Sistem Inventory</title>
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
        .text-end {
            text-align: right !important;
        }
        .text-begin {
            text-align: left !important;
        }
        .low-stock {
            background-color: #fff3cd !important;
        }
        .critical-stock {
            background-color: #f8d7da !important;
        }
    </style>
</head>
<body>
    <?php include '../templates/navbar.php'; ?>

    <div class="container-fluid mt-4">
        <h2 class="page-title">Laporan Stok Gudang</h2>

        <div class="card mb-4">
            <div class="card-header">Filter & Aksi</div>
            <div class="card-body">
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label">Gudang</label>
                        <select name="gudang_id" class="form-select">
                            <option value="">Semua Gudang</option>
                            <?php 
                            $gudang_options->data_seek(0);
                            while($gudang = $gudang_options->fetch_assoc()): ?>
                                <option value="<?= $gudang['id'] ?>" <?= $gudang_id == $gudang['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($gudang['nama_gudang']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Kategori</label>
                        <select name="kategori_id" class="form-select">
                            <option value="">Semua Kategori</option>
                            <?php 
                            $kategori_options->data_seek(0);
                            while($kategori = $kategori_options->fetch_assoc()): ?>
                                <option value="<?= $kategori['id'] ?>" <?= $kategori_id == $kategori['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($kategori['nama_kategori']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Status Stok</label>
                        <select name="stok_minimum" class="form-select">
                            <option value="">Semua Stok</option>
                            <option value="low" <?= $stok_minimum == 'low' ? 'selected' : '' ?>>Stok Rendah</option>
                            <option value="critical" <?= $stok_minimum == 'critical' ? 'selected' : '' ?>>Stok Kritis</option>
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
                    <a href="stok_gudang_print.php?gudang_id=<?= $gudang_id ?>&kategori_id=<?= $kategori_id ?>&stok_minimum=<?= $stok_minimum ?>"
                       class="btn btn-outline-secondary" target="_blank">
                        <i class='bx bx-printer'></i> Cetak
                    </a>
                    <a href="stok_gudang_excel.php?gudang_id=<?= $gudang_id ?>&kategori_id=<?= $kategori_id ?>&stok_minimum=<?= $stok_minimum ?>"
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
            <div class="card-header">
                Data Stok Gudang - Total: <?= $result->num_rows ?> Barang
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Gudang</th>
                                <th>Kode Barang</th>
                                <th>Nama Barang</th>
                                <th>Kategori</th>
                                <th>Satuan</th>
                                <th>Stok Awal</th>
                                <th>Stok Terpakai</th>
                                <th>Stok Sisa</th>
                                <th>Stok Minimum</th>
                                <th>Status</th>
                                <th>Update Terakhir</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $no = 1;
                            $total_stok_awal = 0;
                            $total_stok_terpakai = 0;
                            $total_stok_sisa = 0;
                            
                            while($row = $result->fetch_assoc()):
                                $total_stok_awal += $row['stok_awal'];
                                $total_stok_terpakai += $row['stok_terpakai'];
                                $total_stok_sisa += $row['stok_sisa'];
                                
                                // Tentukan status stok
                                $status_class = '';
                                $status_text = 'Normal';
                                
                                if ($row['stok_sisa'] <= ($row['stok_minimum'] * 0.5)) {
                                    $status_class = 'critical-stock';
                                    $status_text = 'Kritis';
                                } elseif ($row['stok_sisa'] <= $row['stok_minimum']) {
                                    $status_class = 'low-stock';
                                    $status_text = 'Rendah';
                                }
                            ?>
                            <tr class="<?= $status_class ?>">
                                <td><?= $no++ ?></td>
                                <td><?= htmlspecialchars($row['nama_gudang'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($row['kode_barang'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($row['nama_barang'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($row['nama_kategori'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($row['nama_satuan'] ?? '-') ?></td>
                                <td class="text-end"><?= number_format($row['stok_awal'] ?? 0) ?></td>
                                <td class="text-end"><?= number_format($row['stok_terpakai'] ?? 0) ?></td>
                                <td class="text-end"><?= number_format($row['stok_sisa'] ?? 0) ?></td>
                                <td class="text-end"><?= number_format($row['stok_minimum'] ?? 0) ?></td>
                                <td><?= $status_text ?></td>
                                <td><?= !empty($row['updated_at']) && $row['updated_at'] != '0000-00-00 00:00:00' ? date('d/m/Y H:i', strtotime($row['updated_at'])) : '-' ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                        <tfoot>
                            <tr class="table-primary">
                                <th colspan="6" class="text-end">Total:</th>
                                <th class="text-end"><?= number_format($total_stok_awal) ?></th>
                                <th class="text-end"><?= number_format($total_stok_terpakai) ?></th>
                                <th class="text-end"><?= number_format($total_stok_sisa) ?></th>
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
                LAPORAN STOK GUDANG<br>
                <span style="font-size: 30px; font-weight: 30px;">
                    Total: <?= $result->num_rows ?> Barang
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
            
            doc.save('Laporan_Stok_Gudang.pdf');
            document.body.removeChild(pdfContainer);
        });
    }
    </script>
</body>
</html>
