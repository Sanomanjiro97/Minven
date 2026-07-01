<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    require_once __DIR__ . '/../config.php';
    header("Location: " . url_for('index.php'));
    exit();
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/access_check.php';
require_once __DIR__ . '/../includes/menu_access_helper.php';
require_once __DIR__ . '/../templates/header.php';
require_once __DIR__ . '/../templates/navbar.php';

// Check access untuk menu gudang
if (!checkAccess('gudang', 'view')) {
    $_SESSION['error'] = 'Anda tidak memiliki akses untuk melihat menu gudang!';
    header('Location: ' . url_for('dashboard.php'));
    exit();
}

// Ambil gudang
$gudangList = $conn->query("SELECT id, nama_gudang FROM gudang");

$filter_session_key = 'filter_master_gudang';
if (isset($_GET['reset']) && (string)$_GET['reset'] === '1') {
    unset($_SESSION[$filter_session_key]);
    header('Location: master_gudang.php');
    exit();
}

$saved_filters = [];
if (isset($_SESSION[$filter_session_key]) && is_array($_SESSION[$filter_session_key])) {
    $saved_filters = $_SESSION[$filter_session_key];
}

$filter_gudang = isset($_GET['gudang_id'])
    ? (int) $_GET['gudang_id']
    : (int) ($saved_filters['gudang_id'] ?? 0);
$start_date = isset($_GET['start_date'])
    ? (string) $_GET['start_date']
    : (string) ($saved_filters['start_date'] ?? date('Y-m-d', strtotime('-30 days')));
$end_date = isset($_GET['end_date'])
    ? (string) $_GET['end_date']
    : (string) ($saved_filters['end_date'] ?? date('Y-m-d'));
$period = isset($_GET['period'])
    ? (string) $_GET['period']
    : (string) ($saved_filters['period'] ?? 'daily');

$allowed_periods = ['daily', 'weekly', 'monthly'];
if (!in_array($period, $allowed_periods, true)) {
    $period = 'daily';
}

$start_dt = DateTime::createFromFormat('Y-m-d', $start_date) ?: new DateTime('-30 days');
$end_dt = DateTime::createFromFormat('Y-m-d', $end_date) ?: new DateTime();
if ($start_dt > $end_dt) {
    $tmp = $start_dt;
    $start_dt = $end_dt;
    $end_dt = $tmp;
    $start_date = $start_dt->format('Y-m-d');
    $end_date = $end_dt->format('Y-m-d');
}

$_SESSION[$filter_session_key] = [
    'gudang_id' => $filter_gudang,
    'start_date' => $start_date,
    'end_date' => $end_date,
    'period' => $period,
];

$period_key_expr_stok = "DATE(ts.tanggal)";
$period_key_expr_transfer = "DATE(tt.tanggal)";
if ($period === 'weekly') {
    $period_key_expr_stok = "DATE_SUB(DATE(ts.tanggal), INTERVAL WEEKDAY(DATE(ts.tanggal)) DAY)";
    $period_key_expr_transfer = "DATE_SUB(DATE(tt.tanggal), INTERVAL WEEKDAY(DATE(tt.tanggal)) DAY)";
} elseif ($period === 'monthly') {
    $period_key_expr_stok = "DATE_FORMAT(ts.tanggal, '%Y-%m')";
    $period_key_expr_transfer = "DATE_FORMAT(tt.tanggal, '%Y-%m')";
}

$range_start = $start_date . " 00:00:00";
$range_end = $end_date . " 23:59:59";

$params = [$range_start, $range_end];
$types = "ss";
$stok_gudang_filter_sql = "";
if ($filter_gudang > 0) {
    $stok_gudang_filter_sql = " AND ts.gudang_id = ? ";
    $params[] = $filter_gudang;
    $types .= "i";
}

$transfer_params = [$range_start, $range_end];
$transfer_types = "ss";
$transfer_gudang_filter_sql = "";
if ($filter_gudang > 0) {
    $transfer_gudang_filter_sql = " AND (tt.gudang_asal_id = ? OR tt.gudang_tujuan_id = ?) ";
    $transfer_params[] = $filter_gudang;
    $transfer_params[] = $filter_gudang;
    $transfer_types .= "ii";
}

$chart_sql = "
    SELECT period_key,
           SUM(masuk_qty) AS masuk_qty,
           SUM(keluar_qty) AS keluar_qty,
           SUM(transfer_qty) AS transfer_qty
    FROM (
        SELECT
            {$period_key_expr_stok} AS period_key,
            SUM(CASE WHEN ts.jenis_transaksi = 'masuk' THEN dts.jumlah ELSE 0 END) AS masuk_qty,
            SUM(CASE WHEN ts.jenis_transaksi = 'keluar' THEN dts.jumlah ELSE 0 END) AS keluar_qty,
            0 AS transfer_qty
        FROM transaksi_stok ts
        JOIN detail_transaksi_stok dts ON ts.id = dts.transaksi_stok_id
        WHERE ts.tanggal BETWEEN ? AND ?
        {$stok_gudang_filter_sql}
        GROUP BY period_key

        UNION ALL

        SELECT
            {$period_key_expr_transfer} AS period_key,
            0 AS masuk_qty,
            0 AS keluar_qty,
            SUM(dtt.jumlah) AS transfer_qty
        FROM transaksi_transfer tt
        JOIN detail_transaksi_transfer dtt ON tt.id = dtt.transaksi_transfer_id
        WHERE tt.tanggal BETWEEN ? AND ?
        {$transfer_gudang_filter_sql}
        GROUP BY period_key
    ) x
    GROUP BY period_key
    ORDER BY period_key ASC
";

$chart_stmt = $conn->prepare($chart_sql);
if ($chart_stmt === false) die("Query error: " . $conn->error);

$bind_types = $types . $transfer_types;
$bind_params = array_merge($params, $transfer_params);
$chart_stmt->bind_param($bind_types, ...$bind_params);
$chart_stmt->execute();
$chart_result = $chart_stmt->get_result();

$series_map = [];
while ($r = $chart_result->fetch_assoc()) {
    $series_map[$r['period_key']] = [
        'masuk' => (int) $r['masuk_qty'],
        'keluar' => (int) $r['keluar_qty'],
        'transfer' => (int) $r['transfer_qty']
    ];
}

$labels = [];
$stok_masuk = [];
$stok_keluar = [];
$stok_transfer = [];

$cursor = clone $start_dt;
$end_cursor = clone $end_dt;
if ($period === 'weekly') {
    $cursor->modify('monday this week');
    $end_cursor->modify('monday this week');
} elseif ($period === 'monthly') {
    $cursor->modify('first day of this month');
    $end_cursor->modify('first day of this month');
}

while ($cursor <= $end_cursor) {
    if ($period === 'daily') {
        $key = $cursor->format('Y-m-d');
        $labels[] = $cursor->format('d/m');
        $cursor->modify('+1 day');
    } elseif ($period === 'weekly') {
        $key = $cursor->format('Y-m-d');
        $labels[] = 'Minggu ' . $cursor->format('d/m');
        $cursor->modify('+7 days');
    } else {
        $key = $cursor->format('Y-m');
        $labels[] = $cursor->format('m/Y');
        $cursor->modify('first day of next month');
    }

    $stok_masuk[] = $series_map[$key]['masuk'] ?? 0;
    $stok_keluar[] = $series_map[$key]['keluar'] ?? 0;
    $stok_transfer[] = $series_map[$key]['transfer'] ?? 0;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Grafik & Transaksi Gudang</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Add Boxicons and Animate.css links -->
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/animate.css@4.1.1/animate.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <style>
        :root {
            --primary-color: #0d6efd;
            --secondary-color: #6c757d;
            --success-color: #198754;
            --danger-color: #dc3545;
            --warning-color: #ffc107;
            --info-color: #0dcaf0;
        }

        body {
            background-color: #f8f9fa; /* Fallback color */
            /* Add a subtle gradient background */
            background-image: linear-gradient(to bottom right, #e0f2ff, #f8f9fa);
            background-attachment: fixed; /* Keep background fixed when scrolling */
        }

        /* 3D Card Effect */
        .card-3d {
            border: none;
            border-radius: 10px;
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            transform-style: preserve-3d;
        }

        .card-3d:hover {
            transform: translateY(-5px) rotateX(5deg);
            box-shadow: 0 15px 30px rgba(0,0,0,0.2);
        }

        /* Add 3D effect to icons */
        .card-body i.bi, .card-header i.bi { /* Target Bootstrap Icons */
            text-shadow:
                1px 1px 0 rgba(0,0,0,0.1),
                2px 2px 0 rgba(0,0,0,0.08),
                3px 3px 0 rgba(0,0,0,0.06);
            transition: text-shadow 0.3s ease;
        }

        .card-3d:hover .card-body i.bi {
             text-shadow:
                1px 1px 0 rgba(0,0,0,0.15),
                2px 2px 0 rgba(0,0,0,0.12),
                3px 3px 0 rgba(0,0,0,0.09),
                4px 4px 0 rgba(0,0,0,0.06),
                5px 5px 0 rgba(0,0,0,0.03);
        }


        /* Table styling */
        .table-container {
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            transform: translateZ(0);
        }

        .table thead {
            background: #0008f9;
            color: white;
        }

        .table thead th {
            border: none;
            padding: 15px;
            font-weight: 500;
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
        }

        .table tbody tr {
            transition: all 0.3s ease;
            transform-style: preserve-3d;
        }

        .table tbody tr:hover {
            transform: translateX(5px);
            box-shadow: 5px 0 15px rgba(0,0,0,0.05);
        }

        /* Status colors (if applicable, though not used in this specific table) */
        .stok-aman {
            background-color: rgba(25, 135, 84, 0.05);
            border-left: 4px solid var(--success-color);
            transition: all 0.3s ease;
        }

        .stok-minimum {
            background-color: rgba(255, 193, 7, 0.05);
            border-left: 4px solid var(--warning-color);
            animation: pulse 2s infinite;
        }

        .stok-habis {
            background-color: rgba(220, 53, 69, 0.05);
            border-left: 4px solid var(--danger-color);
            animation: shake 0.5s ease-in-out infinite;
        }

        /* Animations */
        @keyframes pulse {
            0% { background-color: rgba(255, 193, 7, 0.05); }
            50% { background-color: rgba(255, 193, 7, 0.15); }
            100% { background-color: rgba(255, 193, 7, 0.05); }
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-3px); }
            75% { transform: translateX(3px); }
        }

        /* Button styling */
        .btn {
            transition: all 0.3s ease;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            border-radius: 8px;
            padding: 8px 16px;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 10px rgba(0,0,0,0.15);
        }

        /* Alert styling */
        .alert {
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
        }

        .alert:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 10px rgba(0,0,0,0.1);
        }

        /* Form controls */
        .form-control, .form-select {
            border-radius: 8px;
            border: 1px solid #dee2e6;
            padding: 10px 15px;
            transition: all 0.3s ease;
            box-shadow: inset 0 1px 2px rgba(0,0,0,0.05);
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25),
                        inset 0 1px 2px rgba(0,0,0,0.1);
            transform: translateY(-1px);
        }

        /* Toast notification (if used) */
        .toast {
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transform: translateZ(0);
        }

        /* Specific styles for this page */
        .chart-container { width: 100%; max-width: 100%; margin: 30px 0; }
        .canvas-wrapper { height: 400px; }
        canvas {
            /* Change background from solid white to a subtle gradient */
            background: linear-gradient(to bottom right, #f0f8ff, #ffffff); /* Subtle light blue to white gradient */
            border-radius: 10px;
            /* Add subtle box-shadow for 3D effect */
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: box-shadow 0.3s ease;
        }

        canvas:hover {
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
        }
    </style>
</head>
<body>
<!-- Remove this include -->
<?php // include_once $_SERVER['DOCUMENT_ROOT']. '/minven_pro/templates/navbar.php';?>
<div class="container-fluid py-4 animate__animated animate__fadeIn"> <!-- Fullscreen layout -->
    <div class="card shadow-lg chart-container card-3d animate__animated animate__zoomIn"> <!-- Added card-3d and animation classes -->
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <div><i class="bi bi-bar-chart-line-fill"></i> Grafik Transaksi Gudang</div>
            <div>
                <a href="?<?= htmlspecialchars(http_build_query(['gudang_id' => 0, 'start_date' => $start_date, 'end_date' => $end_date, 'period' => $period])) ?>" class="btn btn-sm btn-light me-2"><i class="bi bi-arrow-clockwise"></i> Semua</a>
                <button id="downloadPNG" class="btn btn-sm btn-outline-light me-1"><i class="bi bi-image"></i> PNG</button>
                <button id="downloadPDF" class="btn btn-sm btn-outline-light"><i class="bi bi-file-earmark-pdf"></i> PDF</button>
            </div>
        </div>
        <div class="card-body">

            <div class="mb-4">
                <h5><i class="bi bi-building"></i> Pilih Gudang</h5>
                <div class="d-flex flex-wrap gap-3">
                    <?php
                    $gudangList->data_seek(0);
                    while ($g = $gudangList->fetch_assoc()):
                    ?>
                        <a href="?<?= htmlspecialchars(http_build_query(['gudang_id' => (int) $g['id'], 'start_date' => $start_date, 'end_date' => $end_date, 'period' => $period])) ?>" class="btn <?= $g['id'] == $filter_gudang ? 'btn-primary' : 'btn-outline-primary' ?> d-flex align-items-center">
                            <i class="bi bi-box-seam me-2 fs-5"></i> <?= $g['nama_gudang'] ?>
                        </a>
                    <?php endwhile; ?>
                </div>
            </div>

            <form class="row g-3 align-items-end mb-4" method="GET">
                <input type="hidden" name="gudang_id" value="<?= (int) $filter_gudang ?>">
                <div class="col-12 col-md-3">
                    <label class="form-label">Periode</label>
                    <select class="form-select" name="period">
                        <option value="daily" <?= $period === 'daily' ? 'selected' : '' ?>>Per Hari</option>
                        <option value="weekly" <?= $period === 'weekly' ? 'selected' : '' ?>>Per Minggu</option>
                        <option value="monthly" <?= $period === 'monthly' ? 'selected' : '' ?>>Per Bulan</option>
                    </select>
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label">Tanggal Awal</label>
                    <input type="date" class="form-control" name="start_date" value="<?= htmlspecialchars($start_date) ?>">
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label">Tanggal Akhir</label>
                    <input type="date" class="form-control" name="end_date" value="<?= htmlspecialchars($end_date) ?>">
                </div>
                <div class="col-12 col-md-3">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-funnel-fill me-2"></i> Terapkan
                    </button>
                </div>
            </form>

            <div id="chartsContainer" class="mb-5">
                <div class="card card-3d">
                    <div class="card-header bg-light d-flex justify-content-between align-items-center">
                        <div class="fw-semibold">
                            <i class="bi bi-graph-up"></i> Diagram Transaksi (Masuk/Keluar/Transfer)
                        </div>
                        <div class="text-muted small">
                            Hover bar/legend untuk sorot detail
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="canvas-wrapper">
                            <canvas id="stockChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <h5><i class="bi bi-truck"></i> Riwayat Transaksi Gudang</h5>
            <?php
if ($filter_gudang > 0) {
    $range_start_sql = $conn->real_escape_string($range_start);
    $range_end_sql = $conn->real_escape_string($range_end);
    $transaksi = $conn->query("(
        SELECT
            ts.tanggal,
            b.nama_barang,
            dts.jumlah AS jumlah,
            'masuk' AS jenis_transaksi
        FROM
            transaksi_stok ts
        JOIN
            detail_transaksi_stok dts ON ts.id = dts.transaksi_stok_id
        JOIN
            barang b ON dts.barang_id = b.id
        WHERE
            ts.jenis_transaksi = 'masuk' AND ts.gudang_id = $filter_gudang
            AND ts.tanggal BETWEEN '{$range_start_sql}' AND '{$range_end_sql}'

        UNION

        SELECT
            ts.tanggal,
            b.nama_barang,
            dts.jumlah AS jumlah,
            'keluar' AS jenis_transaksi
        FROM
            transaksi_stok ts
        JOIN
            detail_transaksi_stok dts ON ts.id = dts.transaksi_stok_id
        JOIN
            barang b ON dts.barang_id = b.id
        WHERE
            ts.jenis_transaksi = 'keluar' AND ts.gudang_id = $filter_gudang
            AND ts.tanggal BETWEEN '{$range_start_sql}' AND '{$range_end_sql}'

        UNION

        SELECT
            tt.tanggal,
            b.nama_barang,
            dtt.jumlah,
            'transfer_keluar' AS jenis_transaksi
        FROM
            transaksi_transfer tt
        JOIN
            detail_transaksi_transfer dtt ON tt.id = dtt.transaksi_transfer_id
        JOIN
            barang b ON dtt.barang_id = b.id
        WHERE
            tt.gudang_asal_id = $filter_gudang
            AND tt.tanggal BETWEEN '{$range_start_sql}' AND '{$range_end_sql}'

        UNION

        SELECT
            tt.tanggal,
            b.nama_barang,
            dtt.jumlah,
            'transfer_masuk' AS jenis_transaksi
        FROM
            transaksi_transfer tt
        JOIN
            detail_transaksi_transfer dtt ON tt.id = dtt.transaksi_transfer_id
        JOIN
            barang b ON dtt.barang_id = b.id
        WHERE
            tt.gudang_tujuan_id = $filter_gudang
            AND tt.tanggal BETWEEN '{$range_start_sql}' AND '{$range_end_sql}'
    ) ORDER BY tanggal DESC LIMIT 50");

    if ($transaksi) {
        // Mapping label warna
        $jenisLabel = [
            'masuk' => 'success',
            'keluar' => 'danger',
            'transfer_masuk' => 'warning',
            'transfer_keluar' => 'warning'
        ];
        echo '<div class="table-responsive table-container"><table class="table table-bordered table-hover">'; // Added table-container
        echo '<thead class="table-light"><tr><th>Tanggal</th><th>Barang</th><th>Jumlah</th><th>Jenis Transaksi</th></tr></thead><tbody>';
        while ($t = $transaksi->fetch_assoc()) {
            $jenis = $t['jenis_transaksi'];
            echo "<tr>
                    <td>" . date("d-m-Y", strtotime($t['tanggal'])) . "</td>
                    <td>{$t['nama_barang']}</td>
                    <td>{$t['jumlah']}</td>
                    <td><span class='badge bg-{$jenisLabel[$jenis]}'>{$jenis}</span></td>
                  </tr>";
        }
        echo '</tbody></table></div>';
    } else {
        echo '<div class="alert alert-danger"><i class="bi bi-exclamation-circle"></i> Gagal mengambil data transaksi: ' . $conn->error . '</div>';
    }
} else {
    echo '<div class="alert alert-info"><i class="bi bi-info-circle"></i> Silakan pilih gudang untuk melihat riwayat transaksinya.</div>';
}
?>

        </div>
        <div class="card-footer text-end text-muted small">
            <i class="bi bi-clock-history"></i> Terakhir dimuat: <?= date("d-m-Y H:i") ?>
        </div>
    </div>
</div>

<script>
const labels = <?= json_encode($labels); ?>;
const dataMasuk = <?= json_encode($stok_masuk); ?>;
const dataKeluar = <?= json_encode($stok_keluar); ?>;
const dataTransfer = <?= json_encode($stok_transfer); ?>;

const chartCanvas = document.getElementById('stockChart');
const chartCtx = chartCanvas ? chartCanvas.getContext('2d') : null;

const baseColors = {
    masuk: { bg: 'rgba(25, 135, 84, 0.75)', border: 'rgba(25, 135, 84, 1)' },
    keluar: { bg: 'rgba(220, 53, 69, 0.75)', border: 'rgba(220, 53, 69, 1)' },
    transfer: { bg: 'rgba(255, 193, 7, 0.85)', border: 'rgba(255, 193, 7, 1)' }
};

function setDatasetOpacity(chart, activeIndex) {
    chart.data.datasets.forEach((ds, idx) => {
        const isActive = activeIndex === null || idx === activeIndex;
        const base = ds._baseColor;
        const dimAlpha = 0.12;
        const fullBg = base.bg;
        const dimBg = fullBg.replace(/rgba\(([^,]+),([^,]+),([^,]+),\s*([^)]+)\)/, `rgba($1,$2,$3,${dimAlpha})`);
        ds.backgroundColor = isActive ? fullBg : dimBg;
        ds.borderColor = isActive ? base.border : 'rgba(0,0,0,0.08)';
        ds.borderWidth = isActive ? 1 : 1;
    });
    chart.update('none');
}

let stockChart = null;
if (chartCtx) {
    stockChart = new Chart(chartCtx, {
        type: 'bar',
        data: {
            labels,
            datasets: [
                {
                    label: 'Stok Masuk',
                    data: dataMasuk,
                    backgroundColor: baseColors.masuk.bg,
                    borderColor: baseColors.masuk.border,
                    borderWidth: 1,
                    _baseColor: baseColors.masuk
                },
                {
                    label: 'Stok Keluar',
                    data: dataKeluar,
                    backgroundColor: baseColors.keluar.bg,
                    borderColor: baseColors.keluar.border,
                    borderWidth: 1,
                    _baseColor: baseColors.keluar
                },
                {
                    label: 'Stok Transfer',
                    data: dataTransfer,
                    backgroundColor: baseColors.transfer.bg,
                    borderColor: baseColors.transfer.border,
                    borderWidth: 1,
                    _baseColor: baseColors.transfer
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            scales: {
                x: {
                    stacked: false
                },
                y: {
                    beginAtZero: true,
                    ticks: { precision: 0 }
                }
            },
            plugins: {
                legend: {
                    position: 'top',
                    onHover: (event, legendItem, legend) => {
                        const idx = legendItem.datasetIndex;
                        if (legend.chart) setDatasetOpacity(legend.chart, idx);
                        if (event?.native?.target) event.native.target.style.cursor = 'pointer';
                    },
                    onLeave: (event, legend) => {
                        if (legend?.chart) setDatasetOpacity(legend.chart, null);
                        if (event?.native?.target) event.native.target.style.cursor = 'default';
                    }
                },
                tooltip: {
                    mode: 'index',
                    intersect: false,
                    callbacks: {
                        label: (ctx) => {
                            const v = Number(ctx.parsed?.y ?? 0);
                            return `${ctx.dataset.label}: ${v.toLocaleString('id-ID')}`;
                        }
                    }
                }
            }
        }
    });
}

document.getElementById('downloadPNG').addEventListener('click', () => {
    html2canvas(document.querySelector('#chartsContainer')).then(canvas => {
        const link = document.createElement('a');
        link.download = 'grafik_transaksi_gudang.png';
        link.href = canvas.toDataURL();
        link.click();
    });
});

document.getElementById('downloadPDF').addEventListener('click', () => {
    html2canvas(document.querySelector('#chartsContainer')).then(canvas => {
        const imgData = canvas.toDataURL('image/png');
        const { jsPDF } = window.jspdf;
        const pdf = new jsPDF('landscape');
        const imgProps = pdf.getImageProperties(imgData);
        const pdfWidth = pdf.internal.pageSize.getWidth() - 20;
        const pdfHeight = (imgProps.height * pdfWidth) / imgProps.width;
        pdf.addImage(imgData, 'PNG', 10, 20, pdfWidth, pdfHeight);
        pdf.save('grafik_transaksi_gudang.pdf');
    });
});

// Add this at the end of your existing script section
document.addEventListener('DOMContentLoaded', function() {
    // Disable all nav links except current page
    const navLinks = document.querySelectorAll('.nav-link:not(.active)');
    navLinks.forEach(link => {
        link.style.pointerEvents = 'none';
        link.style.cursor = 'default';
        link.style.opacity = '0.6';
    });
});
</script>
</body>
</html>
