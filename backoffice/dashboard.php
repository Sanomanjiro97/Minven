<?php
require_once __DIR__ . '/_init.php';

$wantsJson = ((string)($_GET['format'] ?? '') === 'json')
    || ((string)($_GET['api'] ?? '') === '1')
    || (!empty($_SERVER['HTTP_ACCEPT']) && strpos((string)$_SERVER['HTTP_ACCEPT'], 'application/json') !== false);
$jsonSection = strtolower(trim((string)($_GET['section'] ?? '')));
$jsonOut = function (array $payload, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit();
};

if ($wantsJson) {
    if (!bo_is_logged_in()) {
        $jsonOut(['success' => false, 'message' => 'Unauthorized'], 401);
    }
    if (!checkAccess('backoffice_dashboard', 'view')) {
        $jsonOut(['success' => false, 'message' => 'Forbidden'], 403);
    }
} else {
    bo_require_login();
    $authOk = checkAccess('backoffice_dashboard', 'view');
    if (!$authOk) {
        header('Location: ' . url_for('unauthorized.php'));
        exit();
    }
}

$monthNames = [
    1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'Mei', 6 => 'Jun',
    7 => 'Jul', 8 => 'Agu', 9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des'
];

$now = new DateTime('now');
$dateLabel = $now->format('d') . ' ' . ($monthNames[(int)$now->format('n')] ?? $now->format('M')) . ' ' . $now->format('Y');
$dayNames = [
    'Mon' => 'Senin', 'Tue' => 'Selasa', 'Wed' => 'Rabu', 'Thu' => 'Kamis', 'Fri' => 'Jumat', 'Sat' => 'Sabtu', 'Sun' => 'Minggu',
];
$dayLabel = $dayNames[$now->format('D')] ?? $now->format('l');

$username = (string)($_SESSION['username'] ?? '');
$greeting = $username !== '' ? "Hello, " . $username . " 👋" : "Hello 👋";

$currentYear = (int)$now->format('Y');
$currentQuarter = 'q' . (string)ceil(((int)$now->format('n')) / 3);
$selectedYear = (int)($_GET['year'] ?? $currentYear);
$selectedQuarter = strtolower(trim((string)($_GET['quarter'] ?? $currentQuarter)));
$quarterOptions = [
    'all' => ['label' => 'Semua Triwulan', 'months' => [1, 12]],
    'q1' => ['label' => 'Q1 (Jan - Mar)', 'months' => [1, 3]],
    'q2' => ['label' => 'Q2 (Apr - Jun)', 'months' => [4, 6]],
    'q3' => ['label' => 'Q3 (Jul - Sep)', 'months' => [7, 9]],
    'q4' => ['label' => 'Q4 (Okt - Des)', 'months' => [10, 12]],
];
if (!isset($quarterOptions[$selectedQuarter])) {
    $selectedQuarter = $currentQuarter;
}
if ($selectedYear < 2000 || $selectedYear > ($currentYear + 5)) {
    $selectedYear = $currentYear;
}

$monthLabel = static function (DateTime $date) use ($monthNames): string {
    return ($monthNames[(int)$date->format('n')] ?? $date->format('M')) . ' ' . $date->format('Y');
};

$periodStart = new DateTime(sprintf('%04d-01-01', $selectedYear));
$periodEnd = new DateTime(sprintf('%04d-12-31', $selectedYear));
if ($selectedQuarter !== 'all') {
    [$startMonth, $endMonth] = $quarterOptions[$selectedQuarter]['months'];
    $periodStart = new DateTime(sprintf('%04d-%02d-01', $selectedYear, $startMonth));
    $periodEnd = new DateTime(sprintf('%04d-%02d-01', $selectedYear, $endMonth));
    $periodEnd->modify('last day of this month');
}

$comparisonStart = clone $periodStart;
$comparisonEnd = clone $periodEnd;
$comparisonShortLabel = 'tahun lalu';
if ($selectedQuarter === 'all') {
    $comparisonStart->modify('-1 year');
    $comparisonEnd->modify('-1 year');
} else {
    $quarterNumber = (int)substr($selectedQuarter, 1);
    $comparisonYear = $quarterNumber === 1 ? ($selectedYear - 1) : $selectedYear;
    $comparisonQuarter = $quarterNumber === 1 ? 4 : ($quarterNumber - 1);
    [$compareStartMonth, $compareEndMonth] = $quarterOptions['q' . $comparisonQuarter]['months'];
    $comparisonStart = new DateTime(sprintf('%04d-%02d-01', $comparisonYear, $compareStartMonth));
    $comparisonEnd = new DateTime(sprintf('%04d-%02d-01', $comparisonYear, $compareEndMonth));
    $comparisonEnd->modify('last day of this month');
    $comparisonShortLabel = 'triwulan sebelumnya';
}

$periodLabel = $monthLabel($periodStart) . ' - ' . $monthLabel($periodEnd);
$comparisonLabel = $monthLabel($comparisonStart) . ' - ' . $monthLabel($comparisonEnd);
$availableYears = [];

$stats = [
    'barang' => 0,
    'gudang' => 0,
    'stok' => 0,
    'po_total' => 0,
    'po_pending' => 0,
    'po_completed' => 0,
    'direct_total' => 0,
];

$delta = [
    'po_total' => 0.0,
    'po_pending' => 0.0,
    'po_completed' => 0.0,
    'direct_total' => 0.0,
];

$quarterChartLabels = ['Q1', 'Q2', 'Q3', 'Q4'];
$quarterPoValues = [0, 0, 0, 0];
$quarterDirectValues = [0, 0, 0, 0];
$yearChartLabels = [];
$yearPoValues = [];
$yearDirectValues = [];
$liveRefreshSeconds = 30;
$topSuppliers = [];
$topItems = [];
$recentActivity = [];

$loadTopItems = static function (mysqli $conn, string $periodStartSql, string $periodEndSql, string $detailFilterJoin): array {
    $items = [];
    $res = $conn->query("
        SELECT item_code, item_name, SUM(total_qty) AS total_qty, SUM(total_value) AS total_value
        FROM (
            SELECT
                COALESCE(b.kode_barang, '-') AS item_code,
                COALESCE(b.nama_barang, 'Item tidak diketahui') AS item_name,
                SUM(d.jumlah) AS total_qty,
                SUM(COALESCE(d.total_harga, (d.jumlah * d.harga_satuan))) AS total_value
            FROM detail_purchase_order d
            INNER JOIN purchase_order po ON po.id = d.purchase_order_id
            LEFT JOIN barang b ON b.id = d.barang_id
            WHERE po.tanggal BETWEEN '$periodStartSql' AND '$periodEndSql' $detailFilterJoin
            GROUP BY COALESCE(b.kode_barang, '-'), COALESCE(b.nama_barang, 'Item tidak diketahui')

            UNION ALL

            SELECT
                COALESCE(b.kode_barang, '-') AS item_code,
                COALESCE(b.nama_barang, 'Item tidak diketahui') AS item_name,
                SUM(d.jumlah) AS total_qty,
                SUM(COALESCE(d.total_harga, (d.jumlah * d.harga_satuan))) AS total_value
            FROM detail_direct_purchase d
            INNER JOIN direct_purchase dp ON dp.id = d.direct_purchase_id
            LEFT JOIN barang b ON b.id = d.barang_id
            WHERE dp.tanggal BETWEEN '$periodStartSql' AND '$periodEndSql'
            GROUP BY COALESCE(b.kode_barang, '-'), COALESCE(b.nama_barang, 'Item tidak diketahui')
        ) item_stats
        GROUP BY item_code, item_name
        ORDER BY total_qty DESC, total_value DESC, item_name ASC
        LIMIT 5
    ");
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $items[] = [
                'code' => (string)($r['item_code'] ?? '-'),
                'name' => (string)($r['item_name'] ?? 'Item tidak diketahui'),
                'qty' => (int)($r['total_qty'] ?? 0),
                'value' => (float)($r['total_value'] ?? 0),
            ];
        }
    }
    return $items;
};

if ($boMainConn) {
    $q = $boMainConn->query("SELECT COUNT(*) AS c FROM barang");
    if ($q && ($r = $q->fetch_assoc())) $stats['barang'] = (int)$r['c'];

    $q = $boMainConn->query("SELECT COUNT(*) AS c FROM gudang");
    if ($q && ($r = $q->fetch_assoc())) $stats['gudang'] = (int)$r['c'];

    $q = $boMainConn->query("SELECT COALESCE(SUM(jumlah),0) AS s FROM stok");
    if ($q && ($r = $q->fetch_assoc())) $stats['stok'] = (int)$r['s'];

    $yearRange = $boMainConn->query("
        SELECT MIN(y) AS min_year, MAX(y) AS max_year
        FROM (
            SELECT YEAR(tanggal) AS y FROM purchase_order
            UNION ALL
            SELECT YEAR(tanggal) AS y FROM direct_purchase
        ) AS yearly_data
    ");
    $minYear = $currentYear - 4;
    $maxYear = $currentYear;
    if ($yearRange && ($yearRow = $yearRange->fetch_assoc())) {
        if (!empty($yearRow['min_year'])) {
            $minYear = min($minYear, (int)$yearRow['min_year'], $selectedYear);
        }
        if (!empty($yearRow['max_year'])) {
            $maxYear = max($maxYear, (int)$yearRow['max_year'], $selectedYear);
        }
    }
    for ($year = $maxYear; $year >= $minYear; $year--) {
        $availableYears[] = $year;
    }

    $detailStatusExists = function_exists('db_has_column') ? db_has_column($boMainConn, 'detail_purchase_order', 'status') : false;
    $detailFilterJoin = $detailStatusExists ? " AND (d.status IS NULL OR d.status != 'rejected')" : "";
    $detailFilterSub = $detailStatusExists ? " AND (d2.status IS NULL OR d2.status != 'rejected')" : "";
    $periodStartSql = $boMainConn->real_escape_string($periodStart->format('Y-m-d'));
    $periodEndSql = $boMainConn->real_escape_string($periodEnd->format('Y-m-d'));
    $comparisonStartSql = $boMainConn->real_escape_string($comparisonStart->format('Y-m-d'));
    $comparisonEndSql = $boMainConn->real_escape_string($comparisonEnd->format('Y-m-d'));
    $selectedYearSql = (int)$selectedYear;

    $q = $boMainConn->query("SELECT COUNT(*) AS c FROM purchase_order WHERE tanggal BETWEEN '$periodStartSql' AND '$periodEndSql'");
    if ($q && ($r = $q->fetch_assoc())) $stats['po_total'] = (int)$r['c'];

    $q = $boMainConn->query("
        SELECT
            SUM(CASE WHEN LOWER(COALESCE(status,'')) LIKE '%pending%' OR LOWER(COALESCE(status,'')) IN ('pending','proses','process','draft','open') THEN 1 ELSE 0 END) AS pending_count,
            SUM(CASE WHEN LOWER(COALESCE(status,'')) LIKE '%selesai%' OR LOWER(COALESCE(status,'')) LIKE '%complete%' OR LOWER(COALESCE(status,'')) IN ('selesai','complete','completed','done','finish') THEN 1 ELSE 0 END) AS completed_count
        FROM purchase_order
        WHERE tanggal BETWEEN '$periodStartSql' AND '$periodEndSql'
    ");
    if ($q && ($r = $q->fetch_assoc())) {
        $stats['po_pending'] = (int)($r['pending_count'] ?? 0);
        $stats['po_completed'] = (int)($r['completed_count'] ?? 0);
    }

    $q = $boMainConn->query("SELECT COUNT(*) AS c FROM direct_purchase WHERE tanggal BETWEEN '$periodStartSql' AND '$periodEndSql'");
    if ($q && ($r = $q->fetch_assoc())) $stats['direct_total'] = (int)$r['c'];

    $prev = [
        'po_total' => 0,
        'po_pending' => 0,
        'po_completed' => 0,
        'direct_total' => 0,
    ];

    $q = $boMainConn->query("SELECT COUNT(*) AS c FROM purchase_order WHERE tanggal BETWEEN '$comparisonStartSql' AND '$comparisonEndSql'");
    if ($q && ($r = $q->fetch_assoc())) $prev['po_total'] = (int)$r['c'];

    $q = $boMainConn->query("
        SELECT
            SUM(CASE WHEN LOWER(COALESCE(status,'')) LIKE '%pending%' OR LOWER(COALESCE(status,'')) IN ('pending','proses','process','draft','open') THEN 1 ELSE 0 END) AS pending_count,
            SUM(CASE WHEN LOWER(COALESCE(status,'')) LIKE '%selesai%' OR LOWER(COALESCE(status,'')) LIKE '%complete%' OR LOWER(COALESCE(status,'')) IN ('selesai','complete','completed','done','finish') THEN 1 ELSE 0 END) AS completed_count
        FROM purchase_order
        WHERE tanggal BETWEEN '$comparisonStartSql' AND '$comparisonEndSql'
    ");
    if ($q && ($r = $q->fetch_assoc())) {
        $prev['po_pending'] = (int)($r['pending_count'] ?? 0);
        $prev['po_completed'] = (int)($r['completed_count'] ?? 0);
    }

    $q = $boMainConn->query("SELECT COUNT(*) AS c FROM direct_purchase WHERE tanggal BETWEEN '$comparisonStartSql' AND '$comparisonEndSql'");
    if ($q && ($r = $q->fetch_assoc())) $prev['direct_total'] = (int)$r['c'];

    $pct = function (int $current, int $previous): float {
        if ($previous <= 0) return $current > 0 ? 100.0 : 0.0;
        return (($current - $previous) / $previous) * 100.0;
    };

    $delta['po_total'] = $pct($stats['po_total'], $prev['po_total']);
    $delta['po_pending'] = $pct($stats['po_pending'], $prev['po_pending']);
    $delta['po_completed'] = $pct($stats['po_completed'], $prev['po_completed']);
    $delta['direct_total'] = $pct($stats['direct_total'], $prev['direct_total']);

    $quarterPoMap = [];
    $res = $boMainConn->query("
        SELECT QUARTER(tanggal) AS q, COUNT(*) AS c
        FROM purchase_order
        WHERE YEAR(tanggal) = $selectedYearSql
        GROUP BY QUARTER(tanggal)
        ORDER BY QUARTER(tanggal)
    ");
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $quarterPoMap[(int)($r['q'] ?? 0)] = (int)($r['c'] ?? 0);
        }
    }
    $quarterDirectMap = [];
    $res = $boMainConn->query("
        SELECT QUARTER(tanggal) AS q, COUNT(*) AS c
        FROM direct_purchase
        WHERE YEAR(tanggal) = $selectedYearSql
        GROUP BY QUARTER(tanggal)
        ORDER BY QUARTER(tanggal)
    ");
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $quarterDirectMap[(int)($r['q'] ?? 0)] = (int)($r['c'] ?? 0);
        }
    }
    for ($i = 1; $i <= 4; $i++) {
        $quarterPoValues[$i - 1] = (int)($quarterPoMap[$i] ?? 0);
        $quarterDirectValues[$i - 1] = (int)($quarterDirectMap[$i] ?? 0);
    }

    $yearStart = $selectedYear - 4;
    $yearPoMap = [];
    $res = $boMainConn->query("
        SELECT YEAR(tanggal) AS y, COUNT(*) AS c
        FROM purchase_order
        WHERE YEAR(tanggal) BETWEEN $yearStart AND $selectedYearSql
        GROUP BY YEAR(tanggal)
        ORDER BY YEAR(tanggal)
    ");
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $yearPoMap[(int)($r['y'] ?? 0)] = (int)($r['c'] ?? 0);
        }
    }
    $yearDirectMap = [];
    $res = $boMainConn->query("
        SELECT YEAR(tanggal) AS y, COUNT(*) AS c
        FROM direct_purchase
        WHERE YEAR(tanggal) BETWEEN $yearStart AND $selectedYearSql
        GROUP BY YEAR(tanggal)
        ORDER BY YEAR(tanggal)
    ");
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $yearDirectMap[(int)($r['y'] ?? 0)] = (int)($r['c'] ?? 0);
        }
    }
    for ($year = $yearStart; $year <= $selectedYear; $year++) {
        $yearChartLabels[] = (string)$year;
        $yearPoValues[] = (int)($yearPoMap[$year] ?? 0);
        $yearDirectValues[] = (int)($yearDirectMap[$year] ?? 0);
    }

    $res = $boMainConn->query("
        SELECT supplier_name, SUM(total_transaksi) AS total_transaksi, SUM(total_nilai) AS total_nilai
        FROM (
            SELECT
                COALESCE(s.nama_supplier, 'Tanpa Supplier') AS supplier_name,
                COUNT(DISTINCT po.id) AS total_transaksi,
                COALESCE(SUM(COALESCE(d.total_harga, (d.jumlah * d.harga_satuan))), 0) AS total_nilai
            FROM purchase_order po
            LEFT JOIN supplier s ON s.id = po.supplier_id
            LEFT JOIN detail_purchase_order d ON d.purchase_order_id = po.id $detailFilterJoin
            WHERE po.tanggal BETWEEN '$periodStartSql' AND '$periodEndSql'
            GROUP BY COALESCE(s.nama_supplier, 'Tanpa Supplier')

            UNION ALL

            SELECT
                COALESCE(s.nama_supplier, NULLIF(dp.nama_toko, ''), 'Tanpa Supplier') AS supplier_name,
                COUNT(DISTINCT dp.id) AS total_transaksi,
                COALESCE(SUM(COALESCE(d.total_harga, (d.jumlah * d.harga_satuan))), 0) AS total_nilai
            FROM direct_purchase dp
            LEFT JOIN supplier s ON s.id = dp.supplier_id
            LEFT JOIN detail_direct_purchase d ON d.direct_purchase_id = dp.id
            WHERE dp.tanggal BETWEEN '$periodStartSql' AND '$periodEndSql'
            GROUP BY COALESCE(s.nama_supplier, NULLIF(dp.nama_toko, ''), 'Tanpa Supplier')
        ) supplier_stats
        GROUP BY supplier_name
        ORDER BY total_nilai DESC, total_transaksi DESC, supplier_name ASC
        LIMIT 5
    ");
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $topSuppliers[] = [
                'name' => (string)($r['supplier_name'] ?? 'Tanpa Supplier'),
                'transactions' => (int)($r['total_transaksi'] ?? 0),
                'value' => (float)($r['total_nilai'] ?? 0),
            ];
        }
    }

    $topItems = $loadTopItems($boMainConn, $periodStartSql, $periodEndSql, $detailFilterJoin);

    $res = $boMainConn->query("
        SELECT po.id, po.no_po, po.tanggal, COALESCE(NULLIF(u.nama, ''), NULLIF(u.username, ''), 'User') AS created_by_name
        FROM purchase_order po
        LEFT JOIN users u ON u.id = po.created_by
        WHERE po.tanggal BETWEEN '$periodStartSql' AND '$periodEndSql'
        ORDER BY po.tanggal DESC, po.id DESC
        LIMIT 4
    ");
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $recentActivity[] = [
                'type' => 'PO',
                'title' => (string)($r['no_po'] ?? ''),
                'meta' => 'Dibuat oleh ' . (string)($r['created_by_name'] ?? 'User'),
                'time' => (string)($r['tanggal'] ?? ''),
                'icon' => 'bi bi-clipboard-check',
                'badge' => 'primary',
            ];
        }
    }

    $res = $boMainConn->query("
        SELECT dp.id, dp.no_transaksi, dp.tanggal, COALESCE(NULLIF(u.nama, ''), NULLIF(u.username, ''), 'User') AS created_by_name
        FROM direct_purchase dp
        LEFT JOIN users u ON u.id = dp.created_by
        WHERE dp.tanggal BETWEEN '$periodStartSql' AND '$periodEndSql'
        ORDER BY dp.tanggal DESC, dp.id DESC
        LIMIT 4
    ");
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $recentActivity[] = [
                'type' => 'Direct',
                'title' => (string)($r['no_transaksi'] ?? ''),
                'meta' => 'Dibuat oleh ' . (string)($r['created_by_name'] ?? 'User'),
                'time' => (string)($r['tanggal'] ?? ''),
                'icon' => 'bi bi-send',
                'badge' => 'info',
            ];
        }
    }

    usort($recentActivity, function ($a, $b) {
        return strcmp((string)($b['time'] ?? ''), (string)($a['time'] ?? ''));
    });
    $recentActivity = array_slice($recentActivity, 0, 6);
}

if ($wantsJson && $jsonSection === 'top-items') {
    $liveTopItems = [];
    if ($boMainConn) {
        $detailStatusExists = function_exists('db_has_column') ? db_has_column($boMainConn, 'detail_purchase_order', 'status') : false;
        $detailFilterJoin = $detailStatusExists ? " AND (d.status IS NULL OR d.status != 'rejected')" : "";
        $periodStartSql = $boMainConn->real_escape_string($periodStart->format('Y-m-d'));
        $periodEndSql = $boMainConn->real_escape_string($periodEnd->format('Y-m-d'));
        $liveTopItems = $loadTopItems($boMainConn, $periodStartSql, $periodEndSql, $detailFilterJoin);
    }

    $jsonOut([
        'success' => true,
        'data' => [
            'filters' => [
                'year' => $selectedYear,
                'quarter' => $selectedQuarter,
                'period_label' => $periodLabel,
            ],
            'top_items' => $liveTopItems,
            'live_refresh_seconds' => $liveRefreshSeconds,
            'generated_at' => date('Y-m-d H:i:s'),
        ],
    ]);
}

if (empty($availableYears)) {
    for ($year = $currentYear; $year >= ($currentYear - 4); $year--) {
        $availableYears[] = $year;
    }
}
$headerActions = '<a class="btn btn-primary" href="' . htmlspecialchars(bo_url_for('reports/finance.php')) . '"><i class="bi bi-graph-up-arrow me-1"></i>Lihat Ringkasan</a>';
bo_render_shell_start([
    'title' => 'Dashboard Backoffice - MINVEN',
    'page_title' => 'Dashboard',
    'page_subtitle' => 'Ringkasan operasional backoffice dengan gaya tampilan baru yang tetap memakai warna utama MINVEN.',
    'active' => 'dashboard',
    'header_actions' => $headerActions,
    'extra_head' => '<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>',
]);

$cards = [
    [
        'label' => 'Total PO',
        'value' => $stats['po_total'],
        'delta' => $delta['po_total'],
        'icon' => 'bi bi-clipboard-check',
    ],
    [
        'label' => 'PO Pending',
        'value' => $stats['po_pending'],
        'delta' => $delta['po_pending'],
        'icon' => 'bi bi-clock-history',
    ],
    [
        'label' => 'PO Selesai',
        'value' => $stats['po_completed'],
        'delta' => $delta['po_completed'],
        'icon' => 'bi bi-check-circle',
    ],
    [
        'label' => 'Total Direct',
        'value' => $stats['direct_total'],
        'delta' => $delta['direct_total'],
        'icon' => 'bi bi-bag-check',
    ],
];

$heroTransactions = $stats['po_total'] + $stats['direct_total'];
?>
<div class="bo-card bo-hero mb-4">
    <div class="bo-hero-grid">
        <div>
            <div class="bo-hero-eyebrow"><?= htmlspecialchars($greeting) ?></div>
            <h2 class="bo-hero-title">Welcome to Minven Backoffice</h2>
            <p class="bo-hero-text">Tampilan dashboard disusun ulang agar lebih dekat dengan referensi MaterialPro, tetapi tetap memakai identitas warna biru MINVEN dan data yang relevan untuk operasional backoffice.</p>
            <div class="bo-hero-actions">
                <a class="btn btn-light" href="<?= htmlspecialchars(bo_url_for('reports/po.php')) ?>"><i class="bi bi-clipboard-data me-1"></i>Laporan PO</a>
                <a class="btn btn-outline-light" href="<?= htmlspecialchars(bo_url_for('reports/inventory.php')) ?>"><i class="bi bi-box-seam me-1"></i>Inventory</a>
            </div>
        </div>
        <div class="bo-highlight-panel">
            <div class="bo-highlight-label">Periode Aktif</div>
            <div class="bo-highlight-value"><?= number_format($heroTransactions) ?></div>
            <div class="bo-highlight-meta">Total transaksi PO + Direct untuk <?= htmlspecialchars($periodLabel) ?></div>
            <div class="bo-highlight-meta">Perbandingan acuan: <?= htmlspecialchars($comparisonLabel) ?></div>
        </div>
    </div>
</div>

<div class="bo-card p-4 mb-4">
    <div class="bo-card-header">
        <div>
            <h3 class="bo-card-title">Filter Dashboard</h3>
            <div class="bo-card-subtitle">Atur tahun dan triwulan untuk mengubah kartu KPI, chart, dan daftar insight yang tampil.</div>
        </div>
    </div>
    <form method="get" class="row g-3 align-items-end">
        <div class="col-12 col-md-3 col-xl-2">
            <label class="bo-form-label">Tahun</label>
            <select class="form-select" name="year">
                <?php foreach ($availableYears as $year): ?>
                    <option value="<?= (int)$year ?>" <?= (int)$year === $selectedYear ? 'selected' : '' ?>><?= (int)$year ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-md-4 col-xl-3">
            <label class="bo-form-label">Filter Triwulan</label>
            <select class="form-select" name="quarter">
                <?php foreach ($quarterOptions as $quarterKey => $quarterMeta): ?>
                    <option value="<?= htmlspecialchars($quarterKey) ?>" <?= $quarterKey === $selectedQuarter ? 'selected' : '' ?>>
                        <?= htmlspecialchars($quarterMeta['label']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-md-5 col-xl-4">
            <label class="bo-form-label">Periode Aktif</label>
            <div class="fw-bold"><?= htmlspecialchars($periodLabel) ?></div>
            <div class="bo-card-subtitle">Perbandingan: <?= htmlspecialchars($comparisonLabel) ?></div>
        </div>
        <div class="col-12 col-xl-3 d-flex gap-2 justify-content-xl-end">
            <button type="submit" class="btn btn-primary"><i class="bi bi-funnel me-1"></i>Terapkan</button>
            <a class="btn btn-outline-secondary" href="<?= htmlspecialchars(bo_url_for('dashboard.php')) ?>">Reset</a>
        </div>
    </form>
</div>

<div class="row g-3 mb-4 align-items-stretch">
    <?php foreach ($cards as $c): ?>
        <?php
        $d = (float)($c['delta'] ?? 0);
        $isNeg = $d < 0;
        $prefix = $isNeg ? '' : '+';
        $deltaText = $prefix . number_format($d, 1) . '%';
        ?>
        <div class="col-12 col-md-6 col-xl-3 d-flex">
            <div class="bo-card bo-stat w-100">
                <div class="bo-stat-icon"><i class="<?= htmlspecialchars($c['icon']) ?>"></i></div>
                <div class="flex-grow-1">
                    <div class="bo-stat-label"><?= htmlspecialchars($c['label']) ?></div>
                    <div class="bo-stat-value"><?= number_format((int)($c['value'] ?? 0)) ?></div>
                    <div class="bo-delta<?= $isNeg ? ' neg' : '' ?>"><?= htmlspecialchars($deltaText) ?></div>
                    <div class="bo-stat-note">vs <?= htmlspecialchars($comparisonShortLabel) ?></div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-3 mb-4 align-items-stretch">
    <div class="col-12 col-xl-6 d-flex">
        <div class="bo-card p-4 w-100">
            <div class="bo-card-header">
                <div>
                    <h3 class="bo-card-title">Diagram Triwulan</h3>
                    <div class="bo-card-subtitle">Perbandingan jumlah transaksi PO dan Direct per triwulan pada tahun <?= (int)$selectedYear ?></div>
                </div>
            </div>
            <div style="height: 300px;">
                <canvas id="quarterTrend"></canvas>
            </div>
        </div>
    </div>
    <div class="col-12 col-xl-6 d-flex">
        <div class="bo-card p-4 w-100">
            <div class="bo-card-header">
                <div>
                    <h3 class="bo-card-title">Diagram Tahunan</h3>
                    <div class="bo-card-subtitle">Ringkasan transaksi 5 tahun terakhir sampai <?= (int)$selectedYear ?></div>
                </div>
            </div>
            <div style="height: 300px;">
                <canvas id="yearTrend"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 align-items-stretch">
    <div class="col-12 col-lg-6 col-xl-4 d-flex">
        <div class="bo-card p-4 w-100">
            <div class="bo-card-header">
                <div>
                    <h3 class="bo-card-title">Recent Activity</h3>
                    <div class="bo-card-subtitle">Aktivitas terbaru yang tercatat pada periode aktif.</div>
                </div>
                <a class="bo-link small" href="<?= htmlspecialchars(bo_url_for('reports/po.php')) ?>">Lihat Semua</a>
            </div>
            <?php if (empty($recentActivity)): ?>
                <div class="bo-empty">Belum ada aktivitas pada periode ini.</div>
            <?php else: ?>
                <?php foreach ($recentActivity as $it): ?>
                    <div class="bo-activity-item">
                        <div class="bo-activity-icon"><i class="<?= htmlspecialchars((string)($it['icon'] ?? 'bi bi-dot')) ?>"></i></div>
                        <div class="flex-grow-1">
                            <div class="fw-bold"><?= htmlspecialchars((string)($it['type'] ?? '')) ?> #<?= htmlspecialchars((string)($it['title'] ?? '')) ?></div>
                            <div class="bo-card-subtitle"><?= htmlspecialchars((string)($it['meta'] ?? '')) ?></div>
                        </div>
                        <div class="small text-muted"><?= htmlspecialchars((string)($it['time'] ?? '')) ?></div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-12 col-lg-6 col-xl-4 d-flex">
        <div class="bo-card p-4 w-100">
            <div class="bo-card-header">
                <div>
                    <h3 class="bo-card-title">Top 5 Supplier</h3>
                    <div class="bo-card-subtitle">Diurutkan berdasarkan nilai transaksi pada periode aktif.</div>
                </div>
            </div>
            <?php if (empty($topSuppliers)): ?>
                <div class="bo-empty">Belum ada data supplier pada periode ini.</div>
            <?php else: ?>
                <?php foreach ($topSuppliers as $index => $supplier): ?>
                    <div class="bo-rank-item">
                        <div class="bo-rank-badge"><?= (int)($index + 1) ?></div>
                        <div class="flex-grow-1">
                            <div class="fw-bold"><?= htmlspecialchars((string)($supplier['name'] ?? 'Tanpa Supplier')) ?></div>
                            <div class="bo-card-subtitle"><?= number_format((int)($supplier['transactions'] ?? 0)) ?> transaksi</div>
                        </div>
                        <div class="text-end fw-bold">Rp <?= number_format((float)($supplier['value'] ?? 0), 0, ',', '.') ?></div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-12 col-lg-12 col-xl-4 d-flex">
        <div class="bo-card p-4 w-100">
            <div class="bo-card-header">
                <div>
                    <h3 class="bo-card-title">Top 5 Item</h3>
                    <div class="bo-card-subtitle">Diurutkan berdasarkan total kuantitas pembelian pada periode aktif.</div>
                </div>
                <div class="d-flex flex-column align-items-xl-end gap-2">
                    <div class="d-flex align-items-center gap-3 flex-wrap">
                        <div class="form-check form-switch m-0">
                            <input class="form-check-input" type="checkbox" role="switch" id="dashboardTopItemsLiveToggle" checked>
                            <label class="form-check-label small" for="dashboardTopItemsLiveToggle">Live time</label>
                        </div>
                        <span class="badge text-bg-light" id="dashboardTopItemsLiveStatus">Auto refresh <?= (int)$liveRefreshSeconds ?> detik</span>
                    </div>
                    <div class="text-muted small" id="dashboardTopItemsUpdatedAt">Belum diperbarui</div>
                </div>
            </div>
            <div id="dashboardTopItemsContainer">
                <?php if (empty($topItems)): ?>
                    <div class="bo-empty">Belum ada data item pada periode ini.</div>
                <?php else: ?>
                    <?php foreach ($topItems as $index => $item): ?>
                        <div class="bo-rank-item">
                            <div class="bo-rank-badge"><?= (int)($index + 1) ?></div>
                            <div class="flex-grow-1">
                                <div class="fw-bold"><?= htmlspecialchars((string)($item['name'] ?? 'Item tidak diketahui')) ?></div>
                                <div class="bo-card-subtitle"><?= htmlspecialchars((string)($item['code'] ?? '-')) ?> · <?= number_format((int)($item['qty'] ?? 0)) ?> qty</div>
                            </div>
                            <div class="text-end fw-bold">Rp <?= number_format((float)($item['value'] ?? 0), 0, ',', '.') ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php ob_start(); ?>
<script>
    const quarterChartLabels = <?= json_encode($quarterChartLabels, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    const quarterPoValues = <?= json_encode($quarterPoValues, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    const quarterDirectValues = <?= json_encode($quarterDirectValues, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    const yearChartLabels = <?= json_encode($yearChartLabels, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    const yearPoValues = <?= json_encode($yearPoValues, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    const yearDirectValues = <?= json_encode($yearDirectValues, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    const topItemsLiveRefreshSeconds = <?= (int)$liveRefreshSeconds ?>;

    const commonOptions = {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            x: { grid: { display: false } },
            y: { beginAtZero: true, ticks: { precision: 0 } }
        }
    };

    const quarterCtx = document.getElementById('quarterTrend');
    if (quarterCtx) {
        new Chart(quarterCtx, {
            type: 'bar',
            data: {
                labels: quarterChartLabels,
                datasets: [
                    {
                        label: 'PO',
                        data: quarterPoValues,
                        borderRadius: 10,
                        backgroundColor: '#114BB8'
                    },
                    {
                        label: 'Direct',
                        data: quarterDirectValues,
                        borderRadius: 10,
                        backgroundColor: 'rgba(17,75,184,.30)'
                    }
                ]
            },
            options: {
                ...commonOptions,
                plugins: { legend: { position: 'top' } }
            }
        });
    }

    const yearCtx = document.getElementById('yearTrend');
    if (yearCtx) {
        new Chart(yearCtx, {
            type: 'line',
            data: {
                labels: yearChartLabels,
                datasets: [
                    {
                        label: 'PO',
                        data: yearPoValues,
                        borderColor: '#114BB8',
                        backgroundColor: 'rgba(17,75,184,.12)',
                        fill: true,
                        tension: 0.35,
                        pointRadius: 3,
                        pointBackgroundColor: '#114BB8'
                    },
                    {
                        label: 'Direct',
                        data: yearDirectValues,
                        borderColor: '#0F766E',
                        backgroundColor: 'rgba(15,118,110,.10)',
                        fill: true,
                        tension: 0.35,
                        pointRadius: 3,
                        pointBackgroundColor: '#0F766E'
                    }
                ]
            },
            options: {
                ...commonOptions,
                plugins: { legend: { position: 'top' } }
            }
        });
    }

    const topItemsContainer = document.getElementById('dashboardTopItemsContainer');
    const topItemsLiveToggle = document.getElementById('dashboardTopItemsLiveToggle');
    const topItemsLiveStatus = document.getElementById('dashboardTopItemsLiveStatus');
    const topItemsUpdatedAt = document.getElementById('dashboardTopItemsUpdatedAt');
    const topItemsEndpoint = <?= json_encode(bo_url_for('dashboard.php'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
        + '?format=json&section=top-items&year=<?= (int)$selectedYear ?>&quarter=<?= rawurlencode($selectedQuarter) ?>';
    let topItemsRefreshTimer = null;
    let topItemsClockTimer = null;
    let topItemsNow = new Date();
    let topItemsFetching = false;

    const escapeHtml = (value) => String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');

    const formatNumberId = (value) => Number(value || 0).toLocaleString('id-ID');

    const formatDateTimeId = (value) => value.toLocaleString('id-ID', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit'
    });

    const renderDashboardTopItems = (items) => {
        if (!topItemsContainer) {
            return;
        }
        if (!Array.isArray(items) || items.length === 0) {
            topItemsContainer.innerHTML = '<div class="bo-empty">Belum ada data item pada periode ini.</div>';
            return;
        }
        topItemsContainer.innerHTML = items.map((item, index) => `
            <div class="bo-rank-item">
                <div class="bo-rank-badge">${index + 1}</div>
                <div class="flex-grow-1">
                    <div class="fw-bold">${escapeHtml(item.name || 'Item tidak diketahui')}</div>
                    <div class="bo-card-subtitle">${escapeHtml(item.code || '-')} · ${formatNumberId(item.qty)} qty</div>
                </div>
                <div class="text-end fw-bold">Rp ${formatNumberId(item.value)}</div>
            </div>
        `).join('');
    };

    const tickTopItemsClock = () => {
        topItemsNow = new Date(topItemsNow.getTime() + 1000);
        const suffix = topItemsLiveToggle && topItemsLiveToggle.checked ? ' | live ' + formatDateTimeId(topItemsNow) : '';
        if (topItemsLiveStatus) {
            topItemsLiveStatus.textContent = 'Auto refresh ' + topItemsLiveRefreshSeconds + ' detik' + suffix;
        }
    };

    const setTopItemsUpdated = () => {
        topItemsNow = new Date();
        if (topItemsUpdatedAt) {
            topItemsUpdatedAt.textContent = 'Update terakhir: ' + formatDateTimeId(topItemsNow);
        }
        tickTopItemsClock();
    };

    const stopTopItemsLive = () => {
        if (topItemsRefreshTimer) {
            clearInterval(topItemsRefreshTimer);
            topItemsRefreshTimer = null;
        }
        if (topItemsClockTimer) {
            clearInterval(topItemsClockTimer);
            topItemsClockTimer = null;
        }
        if (topItemsLiveStatus) {
            topItemsLiveStatus.textContent = 'Live time nonaktif';
        }
    };

    const fetchDashboardTopItems = () => {
        if (!topItemsLiveToggle || !topItemsLiveToggle.checked || topItemsFetching) {
            return;
        }
        topItemsFetching = true;
        fetch(topItemsEndpoint, {
            headers: { Accept: 'application/json' },
            cache: 'no-store'
        })
            .then((response) => {
                if (!response.ok) {
                    throw new Error('Gagal memuat top item');
                }
                return response.json();
            })
            .then((payload) => {
                if (!payload || !payload.success || !payload.data) {
                    throw new Error('Payload tidak valid');
                }
                renderDashboardTopItems(payload.data.top_items || []);
                setTopItemsUpdated();
            })
            .catch(() => {
                if (topItemsUpdatedAt) {
                    topItemsUpdatedAt.textContent = 'Gagal memperbarui data live.';
                }
            })
            .finally(() => {
                topItemsFetching = false;
            });
    };

    const startTopItemsLive = () => {
        stopTopItemsLive();
        if (!topItemsLiveToggle || !topItemsLiveToggle.checked) {
            return;
        }
        setTopItemsUpdated();
        topItemsClockTimer = window.setInterval(tickTopItemsClock, 1000);
        topItemsRefreshTimer = window.setInterval(fetchDashboardTopItems, topItemsLiveRefreshSeconds * 1000);
        fetchDashboardTopItems();
    };

    if (topItemsLiveToggle) {
        topItemsLiveToggle.addEventListener('change', () => {
            if (topItemsLiveToggle.checked) {
                startTopItemsLive();
            } else {
                stopTopItemsLive();
                if (topItemsUpdatedAt) {
                    topItemsUpdatedAt.textContent = 'Aktifkan live time untuk auto refresh.';
                }
            }
        });
        startTopItemsLive();
    }
</script>
<?php $pageScripts = ob_get_clean(); ?>
<?php bo_render_shell_end($pageScripts); ?>
