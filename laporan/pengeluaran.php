<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

require_once '../includes/page_access_check.php';

$filter_session_key = 'filter_laporan_pengeluaran';
if (isset($_GET['reset']) && (string)$_GET['reset'] === '1') {
    unset($_SESSION[$filter_session_key]);
    header('Location: pengeluaran.php');
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
        header('Location: pengeluaran.php?' . http_build_query($redirect_params));
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

// Query untuk mengambil data pengeluaran, pembelian direct dan PO
$sql = "SELECT 
            'pengeluaran' as jenis,
            p.id,
            p.tanggal,
            p.no_pengeluaran as nomor,
            p.total_harga as total,
            p.keterangan,
            u.nama as created_by_name,
            NULL as nama_supplier
        FROM pengeluaran p
        LEFT JOIN users u ON p.created_by = u.id
        WHERE p.tanggal BETWEEN ? AND ?
        
        UNION ALL
        
        SELECT 
            'direct' as jenis,
            d.id,
            d.tanggal,
            d.no_pembelian as nomor,
            d.total_harga as total,  // Changed from p.total_harga to d.total_harga
            d.keterangan,
            u.nama as created_by_name,
            s.nama_supplier
        FROM pembelian_direct d
        LEFT JOIN users u ON d.created_by = u.id
        LEFT JOIN supplier s ON d.supplier_id = s.id
        WHERE d.tanggal BETWEEN ? AND ?
        
        UNION ALL
        
        SELECT 
            'po' as jenis,
            po.id,
            po.tanggal,
            po.no_po as nomor,
            po.total_harga as total,
            po.keterangan,
            u.nama as created_by_name,
            s.nama_supplier
        FROM purchase_order po
        LEFT JOIN users u ON po.created_by = u.id
        LEFT JOIN supplier s ON po.supplier_id = s.id
        WHERE po.tanggal BETWEEN ? AND ?
        
        ORDER BY tanggal DESC, id";

$stmt = $conn->prepare($sql);
if ($stmt === false) {
    die("Error preparing statement: " . $conn->error);
}
$stmt->bind_param('ssssss', $start_date, $end_date, $start_date, $end_date, $start_date, $end_date);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Pengeluaran - Sistem Inventory</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.0.7/css/boxicons.min.css" rel="stylesheet">
</head>
<body>
    <?php include '../templates/navbar.php'; ?>

    <div class="container mt-4">
        <div class="row mb-3">
            <div class="col">
                <h2>Laporan Pengeluaran</h2>
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
                    <div class="col-md-3">
                        <label class="form-label">&nbsp;</label>
                        <div>
                            <button type="submit" class="btn btn-primary">
                                <i class='bx bx-filter-alt'></i> Filter
                            </button>
                            <a href="pengeluaran_print.php?start_date=<?= $start_date ?>&end_date=<?= $end_date ?>&period=<?= $period ?>" 
                               class="btn btn-success" target="_blank">
                                <i class='bx bx-printer'></i> Cetak
                            </a>
                            <a href="pengeluaran_pdf.php?start_date=<?= $start_date ?>&end_date=<?= $end_date ?>&period=<?= $period ?>" 
                               class="btn btn-danger" target="_blank">
                                <i class='bx bx-file'></i> PDF
                            </a>
                            <a href="pengeluaran_excel.php?start_date=<?= $start_date ?>&end_date=<?= $end_date ?>&period=<?= $period ?>" 
                               class="btn btn-success">
                                <i class='bx bx-spreadsheet'></i> Excel
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
                                <th>No Pengeluaran</th>
                                <th>Total Harga</th>
                                <th>Keterangan</th>
                                <th>Dibuat Oleh</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $no = 1;
                            $total_harga = 0;
                            while($row = $result->fetch_assoc()): 
                                $total_harga += $row['total'];
                            ?>
                            <tr>
                                <td><?= $no++ ?></td>
                                <td><?= date('d/m/Y', strtotime($row['tanggal'])) ?></td>
                                <td>
                                    <?= htmlspecialchars($row['jenis'] == 'pengeluaran' ? $row['nomor'] : 
                                        ($row['jenis'] == 'direct' ? $row['nomor'] : $row['nomor'])) ?>
                                </td>
                                <td class="text-end">Rp <?= number_format($row['total'], 0, ',', '.') ?></td>
                                <td><?= htmlspecialchars($row['keterangan']) ?></td>
                                <td><?= htmlspecialchars($row['created_by_name']) ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="3" class="text-end">Total Harga:</th>
                                <th class="text-end">Rp <?= number_format($total_harga, 0, ',', '.') ?></th>
                                <th colspan="2"></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
