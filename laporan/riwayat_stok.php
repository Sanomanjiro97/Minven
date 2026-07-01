<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

require_once '../includes/page_access_check.php';

$filter_session_key = 'filter_laporan_riwayat_stok';
if (isset($_GET['reset']) && (string)$_GET['reset'] === '1') {
    unset($_SESSION[$filter_session_key]);
    header('Location: riwayat_stok.php');
    exit();
}

$saved_filters = [];
if (isset($_SESSION[$filter_session_key]) && is_array($_SESSION[$filter_session_key])) {
    $saved_filters = $_SESSION[$filter_session_key];
}

$filter_keys = ['start_date', 'end_date', 'gudang_id'];
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
        header('Location: riwayat_stok.php?' . http_build_query($redirect_params));
        exit();
    }
}

// Default filter values
$start_date = isset($_GET['start_date']) ? (string)$_GET['start_date'] : (string)($saved_filters['start_date'] ?? date('Y-m-01'));
$end_date = isset($_GET['end_date']) ? (string)$_GET['end_date'] : (string)($saved_filters['end_date'] ?? date('Y-m-d'));
$gudang_id = isset($_GET['gudang_id']) ? (string)$_GET['gudang_id'] : (string)($saved_filters['gudang_id'] ?? '');

$_SESSION[$filter_session_key] = [
    'start_date' => $start_date,
    'end_date' => $end_date,
    'gudang_id' => $gudang_id,
];

// Query untuk dropdown gudang
$gudang_sql = "SELECT id, nama_gudang FROM gudang ORDER BY nama_gudang";
$gudang_options = $conn->query($gudang_sql);

// Query untuk mengambil data riwayat stok
$sql = "SELECT 
            rs.*, 
            b.kode_barang, 
            b.nama_barang,
            g.nama_gudang,
            u.nama as user_reset,
            gs.stok_awal as stok_awal_sebelum,
            gs.stok_terpakai as stok_terpakai_sebelum,
            gs.stok_awal - gs.stok_terpakai as stok_akhir_sebelum
        FROM riwayat_stok rs
        LEFT JOIN barang b ON rs.barang_id = b.id
        LEFT JOIN gudang g ON rs.gudang_id = g.id
        LEFT JOIN users u ON rs.user_id = u.id
        LEFT JOIN gudang_stok gs ON rs.barang_id = gs.barang_id AND rs.gudang_id = gs.gudang_id
        WHERE rs.tanggal_reset BETWEEN ? AND ?
        ".($gudang_id ? "AND rs.gudang_id = ?" : "")."
        ORDER BY rs.tanggal_reset DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param('ss'.($gudang_id ? 'i' : ''), $start_date, $end_date, ...($gudang_id ? [$gudang_id] : []));
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Reset Stok - Sistem Inventory</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.0.7/css/boxicons.min.css" rel="stylesheet">
    <!-- Tambahkan di bagian head -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <?php include '../templates/navbar.php'; ?>

    <div class="container mt-4">
        <div class="row mb-3">
            <div class="col">
                <h2>Riwayat Reset Stok</h2>
            </div>
        </div>

        <form method="GET" class="row g-3">
            <div class="col-md-2">
                <label class="form-label">Tanggal Awal</label>
                <input type="date" class="form-control" name="start_date" value="<?= $start_date ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">Tanggal Akhir</label>
                <input type="date" class="form-control" name="end_date" value="<?= $end_date ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">Gudang</label>
                <select name="gudang_id" class="form-select">
                    <option value="">Semua Gudang</option>
                    <?php while($gudang = $gudang_options->fetch_assoc()): ?>
                        <option value="<?= $gudang['id'] ?>" <?= $gudang_id == $gudang['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($gudang['nama_gudang']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">&nbsp;</label>
                <button type="submit" class="btn btn-primary">
                    <i class='bx bx-filter-alt'></i> Filter
                </button>
            </div>
            
            <div class="col-md-2">
                <label class="form-label">&nbsp;</label>
                <a href="riwayat_stok_print.php?start_date=<?= $start_date ?>&end_date=<?= $end_date ?>&gudang_id=<?= $gudang_id ?>" 
                   class="btn btn-success" target="_blank">
                    <i class='bx bx-printer'></i> Cetak
                </a>
            </div>
            <div class="col-md-2">
                <label class="form-label">&nbsp;</label>
                <a href="riwayat_stok_excel.php?start_date=<?= $start_date ?>&end_date=<?= $end_date ?>&gudang_id=<?= $gudang_id ?>" 
                   class="btn btn-warning">
                    <i class='bx bx-download'></i> Excel
                </a>
            </div>
        </form>

        <div class="card mt-3">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Tanggal Reset</th>
                                <th>Gudang</th>
                                <th>Kode Barang</th>
                                <th>Nama Barang</th>
                                <th>Stok Awal Sebelum</th>
                                <th>Stok Terpakai Sebelum</th>
                                <th>Stok Akhir Sebelum</th>
                                <th>User</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $no = 1;
                            while($row = $result->fetch_assoc()): 
                            ?>
                            <tr>
                                <td><?= $no++ ?></td>
                                <td><?= date('d/m/Y H:i', strtotime($row['tanggal_reset'])) ?></td>
                                <td><?= htmlspecialchars($row['nama_gudang']) ?></td>
                                <td><?= htmlspecialchars($row['kode_barang']) ?></td>
                                <td><?= htmlspecialchars($row['nama_barang']) ?></td>
                                <td class="text-end"><?= number_format($row['stok_awal_sebelum']) ?></td>
                                <td class="text-end"><?= number_format($row['stok_terpakai_sebelum']) ?></td>
                                <td class="text-end"><?= number_format($row['stok_akhir_sebelum']) ?></td>
                                <td><?= htmlspecialchars($row['user_reset']) ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<div class="card mt-3">
    <div class="card-header">
        <h5>Grafik Perubahan Stok</h5>
    </div>
    <div class="card-body">
        <canvas id="stokChart" height="100"></canvas>
    </div>
</div>

<script>
// Ambil data untuk chart
const ctx = document.getElementById('stokChart');
new Chart(ctx, {
    type: 'line',
    data: {
        labels: [<?php echo implode(',', array_map(function($r) { return "'" . date('d/m', strtotime($r['tanggal_reset'])) . "'"; }, $result->fetch_all(MYSQLI_ASSOC))); ?>],
        datasets: [{
            label: 'Stok Awal',
            data: [<?php echo implode(',', array_column($result->fetch_all(MYSQLI_ASSOC), 'stok_awal_sebelum')); ?>],
            borderColor: 'rgb(75, 192, 192)',
            tension: 0.1
        },{
            label: 'Stok Akhir',
            data: [<?php echo implode(',', array_column($result->fetch_all(MYSQLI_ASSOC), 'stok_akhir_sebelum')); ?>],
            borderColor: 'rgb(255, 99, 132)',
            tension: 0.1
        }]
    },
    options: {
        responsive: true,
        plugins: {
            title: {
                display: true,
                text: 'Perubahan Stok'
            }
        }
    }
});
</script>
