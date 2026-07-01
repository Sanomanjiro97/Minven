<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

require_once '../includes/page_access_check.php';

$filter_session_key = 'filter_laporan_adjustment_in';
if (isset($_GET['reset']) && (string)$_GET['reset'] === '1') {
    unset($_SESSION[$filter_session_key]);
    header('Location: adjustment_in.php');
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
        header('Location: adjustment_in.php?' . http_build_query($redirect_params));
        exit();
    }
}

$start_date = isset($_GET['start_date']) ? (string)$_GET['start_date'] : (string)($saved_filters['start_date'] ?? date('Y-m-01'));
$end_date = isset($_GET['end_date']) ? (string)$_GET['end_date'] : (string)($saved_filters['end_date'] ?? date('Y-m-d'));
$period = isset($_GET['period']) ? (string)$_GET['period'] : (string)($saved_filters['period'] ?? 'daily');
$gudang_id = isset($_GET['gudang_id']) ? (string)$_GET['gudang_id'] : (string)($saved_filters['gudang_id'] ?? '');

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

$gudang_sql = "SELECT id, nama_gudang FROM gudang ORDER BY nama_gudang";
$gudang_options = $conn->query($gudang_sql);

$sql = "SELECT
            ts.tanggal,
            b.kode_barang,
            b.nama_barang,
            dts.jumlah,
            ts.keterangan,
            ts.no_transaksi,
            g.nama_gudang,
            u.username as created_by_username
        FROM detail_transaksi_stok dts
        JOIN transaksi_stok ts ON dts.transaksi_stok_id = ts.id
        LEFT JOIN barang b ON dts.barang_id = b.id
        JOIN gudang g ON ts.gudang_id = g.id
        LEFT JOIN users u ON ts.created_by = u.id
        WHERE ts.jenis_transaksi = 'adjustment_in'
            AND ts.tanggal BETWEEN ? AND ?
            " . ($gudang_id ? "AND ts.gudang_id = ?" : "") . "
        ORDER BY ts.tanggal DESC, ts.no_transaksi DESC, dts.id ASC";

if ($gudang_id) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ssi', $start_date, $end_date, $gudang_id);
} else {
    $stmt = $conn->prepare($sql);
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
    <title>Laporan Adjustment In - Sistem Inventory</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .page-title {
            color: #0008f9;
            font-weight: 700;
        }
        .card {
            border-radius: 0.75rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            border: 1px solid #e2e8f0;
        }
        .badge-success {
            background-color: #28a745;
        }
    </style>
</head>
<body>
    <?php include '../templates/navbar.php'; ?>

    <div class="container-fluid mt-4">
        <div class="row mb-3">
            <div class="col-12">
                <h2 class="page-title"><i class="bx bx-plus-circle me-2"></i>Laporan Adjustment In</h2>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-2">
                        <label for="start_date" class="form-label">Tanggal Mulai</label>
                        <input type="date" class="form-control" id="start_date" name="start_date" value="<?= htmlspecialchars($start_date) ?>">
                    </div>
                    <div class="col-md-2">
                        <label for="end_date" class="form-label">Tanggal Selesai</label>
                        <input type="date" class="form-control" id="end_date" name="end_date" value="<?= htmlspecialchars($end_date) ?>">
                    </div>
                    <div class="col-md-2">
                        <label for="period" class="form-label">Periode</label>
                        <select class="form-select" id="period" name="period">
                            <option value="daily" <?= $period === 'daily' ? 'selected' : '' ?>>Harian</option>
                            <option value="weekly" <?= $period === 'weekly' ? 'selected' : '' ?>>Mingguan</option>
                            <option value="monthly" <?= $period === 'monthly' ? 'selected' : '' ?>>Bulanan</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="gudang_id" class="form-label">Gudang</label>
                        <select class="form-select" id="gudang_id" name="gudang_id">
                            <option value="">Semua Gudang</option>
                            <?php while($gudang = $gudang_options->fetch_assoc()): ?>
                                <option value="<?= $gudang['id'] ?>" <?= $gudang_id == $gudang['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($gudang['nama_gudang']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex align-items-end gap-2">
                        <button type="submit" class="btn btn-primary">Filter</button>
                        <a href="adjustment_in.php?reset=1" class="btn btn-secondary">Reset</a>
                        <a href="adjustment_in_excel.php?start_date=<?= urlencode($start_date) ?>&end_date=<?= urlencode($end_date) ?>&gudang_id=<?= urlencode($gudang_id) ?>" class="btn btn-success">
                            <i class="bx bx-download"></i> Excel
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover table-bordered align-middle mb-0">
                        <thead class="table-success">
                            <tr>
                                <th class="text-center text-nowrap">No</th>
                                <th class="text-center text-nowrap">Tanggal</th>
                                <th class="text-center text-nowrap">No Transaksi</th>
                                <th class="text-center text-nowrap">Kode Barang</th>
                                <th>Nama Barang</th>
                                <th class="text-end text-nowrap">Jumlah</th>
                                <th class="text-nowrap">Gudang</th>
                                <th>Keterangan</th>
                                <th class="text-center text-nowrap">User</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $no = 1;
                            $total_jumlah = 0;
                            while($row = $result->fetch_assoc()):
                                $total_jumlah += $row['jumlah'];
                            ?>
                            <tr>
                                <td class="text-center"><?= $no++ ?></td>
                                <td class="text-center text-nowrap"><?= date('d/m/Y', strtotime($row['tanggal'])) ?></td>
                                <td class="text-center text-nowrap"><?= htmlspecialchars($row['no_transaksi']) ?></td>
                                <td class="text-center text-nowrap"><?= htmlspecialchars($row['kode_barang']) ?></td>
                                <td><?= htmlspecialchars($row['nama_barang']) ?></td>
                                <td class="text-end"><?= number_format($row['jumlah']) ?></td>
                                <td class="text-nowrap"><?= htmlspecialchars($row['nama_gudang']) ?></td>
                                <td><?= htmlspecialchars($row['keterangan'] ?? '-') ?></td>
                                <td class="text-center text-nowrap"><?= htmlspecialchars($row['created_by_username'] ?? '-') ?></td>
                            </tr>
                            <?php endwhile; ?>
                            <?php if ($no === 1): ?>
                            <tr>
                                <td colspan="9" class="text-center text-muted">Tidak ada data untuk filter ini.</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <th colspan="5" class="text-end fw-bold">Total:</th>
                                <th class="text-end fw-bold"><?= number_format($total_jumlah) ?></th>
                                <th colspan="3"></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
        flatpickr("#start_date", { dateFormat: "Y-m-d", maxDate: "today" });
        flatpickr("#end_date", { dateFormat: "Y-m-d", maxDate: "today" });

        document.getElementById('period').addEventListener('change', function() {
            const startDateInput = document.getElementById('start_date');
            const endDateInput = document.getElementById('end_date');

            if (this.value === 'weekly') {
                const today = new Date();
                const monday = new Date(today);
                monday.setDate(today.getDate() - today.getDay() + 1);
                const sunday = new Date(monday);
                sunday.setDate(monday.getDate() + 6);

                startDateInput.value = monday.toISOString().split('T')[0];
                endDateInput.value = sunday.toISOString().split('T')[0];
            } else if (this.value === 'monthly') {
                const now = new Date();
                startDateInput.value = now.getFullYear() + '-' + String(now.getMonth() + 1).padStart(2, '0') + '-01';
                const lastDay = new Date(now.getFullYear(), now.getMonth() + 1, 0);
                endDateInput.value = lastDay.toISOString().split('T')[0];
            }
        });
    </script>
</body>
</html>
