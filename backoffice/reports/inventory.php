<?php
require_once __DIR__ . '/../_init.php';

$wantsJson = ((string)($_GET['format'] ?? '') === 'json')
    || ((string)($_GET['api'] ?? '') === '1')
    || (!empty($_SERVER['HTTP_ACCEPT']) && strpos((string)$_SERVER['HTTP_ACCEPT'], 'application/json') !== false);

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
    if (!checkAccess('backoffice', 'view') || !checkAccess('backoffice_reports_inventory', 'view')) {
        $jsonOut(['success' => false, 'message' => 'Forbidden'], 403);
    }
} else {
    bo_require_login();
    if (!checkAccess('backoffice_reports_inventory', 'view')) {
        header('Location: ' . url_for('unauthorized.php'));
        exit();
    }
}

$gudangId = (int)($_GET['gudang_id'] ?? 0);
$start = (string)($_GET['start'] ?? '');
$end = (string)($_GET['end'] ?? '');
$group = (string)($_GET['group'] ?? 'day');
$qText = trim((string)($_GET['q'] ?? ''));

if ($start === '' || $end === '') {
    $start = date('Y-m-01');
    $end = date('Y-m-t');
}

if (!in_array($group, ['day', 'month', 'year'], true)) {
    $group = 'day';
}

$gudangOptions = [];
if ($boMainConn) {
    $q = $boMainConn->query("SELECT id, nama_gudang FROM gudang ORDER BY nama_gudang");
    if ($q) {
        while ($r = $q->fetch_assoc()) {
            $gudangOptions[] = $r;
        }
    }
}

$stockRows = [];
$totalQty = 0;
$totalValue = 0.0;

if ($boMainConn) {
    $types = '';
    $params = [];
    $where = "1=1";
    if ($gudangId > 0) {
        $where .= " AND gs.gudang_id = ?";
        $types .= 'i';
        $params[] = $gudangId;
    }
    if ($qText !== '') {
        $where .= " AND (b.kode_barang LIKE ? OR b.nama_barang LIKE ?)";
        $types .= 'ss';
        $like = '%' . $qText . '%';
        $params[] = $like;
        $params[] = $like;
    }

    $sql = "SELECT g.nama_gudang, b.kode_barang, b.nama_barang,
                   SUM(gs.stok_awal) AS stok_awal,
                   SUM(gs.stok_terpakai) AS stok_terpakai,
                   SUM(gs.stok_awal - gs.stok_terpakai) AS stok_sisa,
                   b.harga_beli
            FROM gudang_stok gs
            JOIN barang b ON b.id = gs.barang_id
            JOIN gudang g ON g.id = gs.gudang_id
            WHERE $where
            GROUP BY gs.gudang_id, gs.barang_id
            ORDER BY g.nama_gudang ASC, b.nama_barang ASC";

    $stmt = $boMainConn->prepare($sql);
    if ($stmt) {
        if ($types !== '') {
            $bindArgs = [];
            $bindArgs[] = $types;
            foreach ($params as $k => $v) {
                $bindArgs[] = $params[$k];
            }
            $refs = [];
            foreach ($bindArgs as $k => $v) {
                $refs[$k] = &$bindArgs[$k];
            }
            call_user_func_array([$stmt, 'bind_param'], $refs);
        }
        $stmt->execute();
        $res = $stmt->get_result();
        while ($res && ($r = $res->fetch_assoc())) {
            $stockRows[] = $r;
            $qty = (int)($r['stok_sisa'] ?? 0);
            $totalQty += $qty;
            $totalValue += ((float)($r['harga_beli'] ?? 0)) * $qty;
        }
        $stmt->close();
    }
}

$changeRows = [];
if ($boMainConn) {
    $bucket = "DATE(h.created_at)";
    if ($group === 'month') {
        $bucket = "DATE_FORMAT(h.created_at, '%Y-%m')";
    } elseif ($group === 'year') {
        $bucket = "DATE_FORMAT(h.created_at, '%Y')";
    }

    $types = 'ss';
    $params = [$start, $end];
    $where = "DATE(h.created_at) BETWEEN ? AND ?";
    $groupBy = "$bucket, b.id";
    $selectGudang = '';
    if ($gudangId > 0) {
        $where .= " AND h.gudang_id = ?";
        $types .= 'i';
        $params[] = $gudangId;
        $groupBy .= ", g.id";
        $selectGudang = ", g.nama_gudang";
    }

    if ($qText !== '') {
        $where .= " AND (b.kode_barang LIKE ? OR b.nama_barang LIKE ?)";
        $types .= 'ss';
        $like = '%' . $qText . '%';
        $params[] = $like;
        $params[] = $like;
    }

    $sql = "SELECT $bucket AS periode,
                   b.kode_barang, b.nama_barang
                   $selectGudang,
                   SUM(CASE WHEN h.jenis_perubahan IN ('masuk','transfer_in') THEN h.jumlah_perubahan ELSE 0 END) AS qty_masuk,
                   SUM(CASE WHEN h.jenis_perubahan IN ('keluar','transfer_out') THEN h.jumlah_perubahan ELSE 0 END) AS qty_keluar,
                   SUM(CASE WHEN h.jenis_perubahan = 'reset' THEN 1 ELSE 0 END) AS jumlah_reset
            FROM gudang_stok_history h
            JOIN barang b ON b.id = h.barang_id
            JOIN gudang g ON g.id = h.gudang_id
            WHERE $where
            GROUP BY $groupBy
            ORDER BY periode DESC, b.nama_barang ASC";

    $stmt = $boMainConn->prepare($sql);
    if ($stmt) {
        $bindArgs = [];
        $bindArgs[] = $types;
        foreach ($params as $k => $v) {
            $bindArgs[] = $params[$k];
        }
        $refs = [];
        foreach ($bindArgs as $k => $v) {
            $refs[$k] = &$bindArgs[$k];
        }
        call_user_func_array([$stmt, 'bind_param'], $refs);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($res && ($r = $res->fetch_assoc())) {
            $changeRows[] = $r;
        }
        $stmt->close();
    }
}

$topItems = [];
if ($boMainConn) {
    $types = 'ss';
    $params = [$start, $end];
    $where = "DATE(h.created_at) BETWEEN ? AND ?";
    if ($gudangId > 0) {
        $where .= " AND h.gudang_id = ?";
        $types .= 'i';
        $params[] = $gudangId;
    }
    if ($qText !== '') {
        $where .= " AND (b.kode_barang LIKE ? OR b.nama_barang LIKE ?)";
        $types .= 'ss';
        $like = '%' . $qText . '%';
        $params[] = $like;
        $params[] = $like;
    }

    $sql = "SELECT b.kode_barang, b.nama_barang,
                   SUM(CASE WHEN h.jenis_perubahan IN ('masuk','transfer_in') THEN ABS(h.jumlah_perubahan) ELSE 0 END) AS total_masuk,
                   SUM(CASE WHEN h.jenis_perubahan IN ('keluar','transfer_out') THEN ABS(h.jumlah_perubahan) ELSE 0 END) AS total_keluar,
                   COUNT(*) AS jumlah_transaksi
            FROM gudang_stok_history h
            JOIN barang b ON b.id = h.barang_id
            WHERE $where
            GROUP BY b.id, b.kode_barang, b.nama_barang
            HAVING total_masuk > 0 OR total_keluar > 0
            ORDER BY total_keluar DESC, total_masuk DESC, b.nama_barang ASC
            LIMIT 5";

    $stmt = $boMainConn->prepare($sql);
    if ($stmt) {
        $bindArgs = [];
        $bindArgs[] = $types;
        foreach ($params as $k => $v) {
            $bindArgs[] = $params[$k];
        }
        $refs = [];
        foreach ($bindArgs as $k => $v) {
            $refs[$k] = &$bindArgs[$k];
        }
        call_user_func_array([$stmt, 'bind_param'], $refs);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($res && ($r = $res->fetch_assoc())) {
            $topItems[] = $r;
        }
        $stmt->close();
    }
}

$liveRefreshSeconds = 30;
$monthlyHistoryMonths = 6;
$topItemsMonthly = [];
$topItemsMonthlyRange = [
    'start' => '',
    'end' => '',
];
try {
    $monthlyHistoryEndDate = new DateTimeImmutable($end !== '' ? $end : date('Y-m-d'));
} catch (Throwable $e) {
    $monthlyHistoryEndDate = new DateTimeImmutable(date('Y-m-d'));
}
$monthlyHistoryEndDate = $monthlyHistoryEndDate->modify('last day of this month');
$monthlyHistoryStartDate = $monthlyHistoryEndDate->modify('first day of this month')->modify('-' . ($monthlyHistoryMonths - 1) . ' months');
$topItemsMonthlyRange['start'] = $monthlyHistoryStartDate->format('Y-m-d');
$topItemsMonthlyRange['end'] = $monthlyHistoryEndDate->format('Y-m-d');

if ($boMainConn) {
    $types = 'ss';
    $params = [$topItemsMonthlyRange['start'], $topItemsMonthlyRange['end']];
    $where = "DATE(h.created_at) BETWEEN ? AND ?";
    if ($gudangId > 0) {
        $where .= " AND h.gudang_id = ?";
        $types .= 'i';
        $params[] = $gudangId;
    }
    if ($qText !== '') {
        $where .= " AND (b.kode_barang LIKE ? OR b.nama_barang LIKE ?)";
        $types .= 'ss';
        $like = '%' . $qText . '%';
        $params[] = $like;
        $params[] = $like;
    }

    $sql = "SELECT DATE_FORMAT(h.created_at, '%Y-%m') AS periode_bulan,
                   b.id AS barang_id,
                   b.kode_barang,
                   b.nama_barang,
                   SUM(CASE WHEN h.jenis_perubahan IN ('masuk','transfer_in') THEN ABS(h.jumlah_perubahan) ELSE 0 END) AS total_masuk,
                   SUM(CASE WHEN h.jenis_perubahan IN ('keluar','transfer_out') THEN ABS(h.jumlah_perubahan) ELSE 0 END) AS total_keluar,
                   COUNT(*) AS jumlah_transaksi
            FROM gudang_stok_history h
            JOIN barang b ON b.id = h.barang_id
            WHERE $where
            GROUP BY periode_bulan, b.id, b.kode_barang, b.nama_barang
            HAVING total_masuk > 0 OR total_keluar > 0
            ORDER BY periode_bulan DESC, total_keluar DESC, total_masuk DESC, b.nama_barang ASC";

    $stmt = $boMainConn->prepare($sql);
    if ($stmt) {
        $bindArgs = [];
        $bindArgs[] = $types;
        foreach ($params as $k => $v) {
            $bindArgs[] = $params[$k];
        }
        $refs = [];
        foreach ($bindArgs as $k => $v) {
            $refs[$k] = &$bindArgs[$k];
        }
        call_user_func_array([$stmt, 'bind_param'], $refs);
        $stmt->execute();
        $res = $stmt->get_result();
        $bucketCounts = [];
        while ($res && ($r = $res->fetch_assoc())) {
            $periodKey = (string)($r['periode_bulan'] ?? '');
            if ($periodKey === '') {
                continue;
            }
            if (!isset($bucketCounts[$periodKey])) {
                $bucketCounts[$periodKey] = 0;
            }
            if ($bucketCounts[$periodKey] >= 5) {
                continue;
            }
            $topItemsMonthly[] = $r;
            $bucketCounts[$periodKey]++;
        }
        $stmt->close();
    }
}

if ($wantsJson) {
    $jsonOut([
        'success' => true,
        'data' => [
            'filters' => [
                'gudang_id' => $gudangId,
                'start' => $start,
                'end' => $end,
                'group' => $group,
                'q' => $qText,
            ],
            'gudang_options' => $gudangOptions,
            'stock_rows' => $stockRows,
            'change_rows' => $changeRows,
            'top_items' => $topItems,
            'top_items_monthly' => $topItemsMonthly,
            'top_items_monthly_range' => $topItemsMonthlyRange,
            'total_qty' => $totalQty,
            'total_value_hpp' => $totalValue,
            'live_refresh_seconds' => $liveRefreshSeconds,
        ],
    ]);
}

$export = (string)($_GET['export'] ?? '');
if ($export === 'xlsx') {
    $sheet = [];
    $sheet[] = ['Laporan Inventory'];
    $sheet[] = ['Periode', $start, $end];
    $sheet[] = ['Gudang', $gudangId > 0 ? (string)$gudangId : 'Semua'];
    $sheet[] = ['Cari', $qText !== '' ? $qText : '-'];
    $sheet[] = ['Rekap', $group];
    $sheet[] = [];
    $sheet[] = ['Stok Saat Ini'];
    $sheet[] = ['Gudang', 'Kode', 'Barang', 'Stok Awal', 'Terpakai', 'Stok Sisa'];
    foreach ($stockRows as $r) {
        $sheet[] = [
            (string)($r['nama_gudang'] ?? ''),
            (string)($r['kode_barang'] ?? ''),
            (string)($r['nama_barang'] ?? ''),
            (int)($r['stok_awal'] ?? 0),
            (int)($r['stok_terpakai'] ?? 0),
            (int)($r['stok_sisa'] ?? 0),
        ];
    }
    $sheet[] = ['', '', 'TOTAL QTY', '', '', (int)$totalQty];
    $sheet[] = ['', '', 'NILAI STOK (HPP)', '', '', (float)$totalValue];
    $sheet[] = [];
    $sheet[] = ['Perubahan Stok'];
    $head = ['Periode'];
    if ($gudangId > 0) $head[] = 'Gudang';
    $head[] = 'Kode';
    $head[] = 'Barang';
    $head[] = 'Masuk';
    $head[] = 'Keluar';
    $head[] = 'Reset';
    $sheet[] = $head;
    foreach ($changeRows as $r) {
        $row = [(string)($r['periode'] ?? '')];
        if ($gudangId > 0) $row[] = (string)($r['nama_gudang'] ?? '-');
        $row[] = (string)($r['kode_barang'] ?? '');
        $row[] = (string)($r['nama_barang'] ?? '');
        $row[] = (int)($r['qty_masuk'] ?? 0);
        $row[] = (int)($r['qty_keluar'] ?? 0);
        $row[] = (int)($r['jumlah_reset'] ?? 0);
        $sheet[] = $row;
    }
    $sheet[] = [];
    $sheet[] = ['Top 5 Item - Riwayat Per Bulan'];
    $sheet[] = ['Periode Bulan', 'Rank', 'Kode', 'Barang', 'Masuk', 'Keluar', 'Jumlah Transaksi'];
    $monthlyRanks = [];
    foreach ($topItemsMonthly as $r) {
        $periodKey = (string)($r['periode_bulan'] ?? '');
        if (!isset($monthlyRanks[$periodKey])) {
            $monthlyRanks[$periodKey] = 0;
        }
        $monthlyRanks[$periodKey]++;
        $sheet[] = [
            $periodKey,
            $monthlyRanks[$periodKey],
            (string)($r['kode_barang'] ?? ''),
            (string)($r['nama_barang'] ?? ''),
            (int)($r['total_masuk'] ?? 0),
            (int)($r['total_keluar'] ?? 0),
            (int)($r['jumlah_transaksi'] ?? 0),
        ];
    }
    bo_export_xlsx_download(bo_export_filename('Laporan_Inventory', 'xlsx'), 'Laporan Inventory', $sheet);
}
if ($export === 'pdf') {
    $sub = [
        'Periode: ' . $start . ' s/d ' . $end,
        'Gudang: ' . ($gudangId > 0 ? (string)$gudangId : 'Semua') . ' • Cari: ' . ($qText !== '' ? $qText : '-') . ' • Rekap: ' . $group,
    ];

    $colsStock = [
        ['key' => 'nama_gudang', 'label' => 'Gudang', 'w' => 55, 'align' => 'L'],
        ['key' => 'kode_barang', 'label' => 'Kode', 'w' => 25, 'align' => 'L'],
        ['key' => 'nama_barang', 'label' => 'Barang', 'w' => 95, 'align' => 'L'],
        ['key' => 'stok_awal', 'label' => 'Awal', 'w' => 25, 'align' => 'R'],
        ['key' => 'stok_terpakai', 'label' => 'Terpakai', 'w' => 25, 'align' => 'R'],
        ['key' => 'stok_sisa', 'label' => 'Sisa', 'w' => 25, 'align' => 'R'],
    ];

    $pdf = bo_export_pdf_begin('L', 'Laporan Inventory - Stok Saat Ini', $sub, $colsStock);
    $rowsStock = [];
    foreach ($stockRows as $r) {
        $rowsStock[] = [
            'nama_gudang' => (string)($r['nama_gudang'] ?? ''),
            'kode_barang' => (string)($r['kode_barang'] ?? ''),
            'nama_barang' => (string)($r['nama_barang'] ?? ''),
            'stok_awal' => number_format((int)($r['stok_awal'] ?? 0)),
            'stok_terpakai' => number_format((int)($r['stok_terpakai'] ?? 0)),
            'stok_sisa' => number_format((int)($r['stok_sisa'] ?? 0)),
        ];
    }
    bo_pdf_draw_rows($pdf, $colsStock, $rowsStock);
    $pdf->Ln(3);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(0, 6, bo_pdf_text('Total Qty: ' . number_format($totalQty) . ' • Nilai Stok (HPP): Rp ' . number_format($totalValue, 0, ',', '.')), 0, 1, 'L');

    $colsChange = [
        ['key' => 'periode', 'label' => 'Periode', 'w' => 30, 'align' => 'L'],
    ];
    if ($gudangId > 0) {
        $colsChange[] = ['key' => 'nama_gudang', 'label' => 'Gudang', 'w' => 45, 'align' => 'L'];
    }
    $colsChange[] = ['key' => 'kode_barang', 'label' => 'Kode', 'w' => 25, 'align' => 'L'];
    $colsChange[] = ['key' => 'nama_barang', 'label' => 'Barang', 'w' => 95, 'align' => 'L'];
    $colsChange[] = ['key' => 'qty_masuk', 'label' => 'Masuk', 'w' => 25, 'align' => 'R'];
    $colsChange[] = ['key' => 'qty_keluar', 'label' => 'Keluar', 'w' => 25, 'align' => 'R'];
    $colsChange[] = ['key' => 'jumlah_reset', 'label' => 'Reset', 'w' => 22, 'align' => 'R'];

    $pdf->boTitle = 'Laporan Inventory - Perubahan Stok';
    bo_export_pdf_set_table($pdf, $colsChange, true);
    $rowsChange = [];
    foreach ($changeRows as $r) {
        $rr = [
            'periode' => (string)($r['periode'] ?? ''),
            'kode_barang' => (string)($r['kode_barang'] ?? ''),
            'nama_barang' => (string)($r['nama_barang'] ?? ''),
            'qty_masuk' => number_format((int)($r['qty_masuk'] ?? 0)),
            'qty_keluar' => number_format((int)($r['qty_keluar'] ?? 0)),
            'jumlah_reset' => number_format((int)($r['jumlah_reset'] ?? 0)),
        ];
        if ($gudangId > 0) $rr['nama_gudang'] = (string)($r['nama_gudang'] ?? '-');
        $rowsChange[] = $rr;
    }
    bo_pdf_draw_rows($pdf, $colsChange, $rowsChange);

    $colsTopMonthly = [
        ['key' => 'periode_bulan', 'label' => 'Bulan', 'w' => 28, 'align' => 'L'],
        ['key' => 'rank', 'label' => 'Rank', 'w' => 18, 'align' => 'C'],
        ['key' => 'kode_barang', 'label' => 'Kode', 'w' => 25, 'align' => 'L'],
        ['key' => 'nama_barang', 'label' => 'Barang', 'w' => 95, 'align' => 'L'],
        ['key' => 'total_masuk', 'label' => 'Masuk', 'w' => 25, 'align' => 'R'],
        ['key' => 'total_keluar', 'label' => 'Keluar', 'w' => 25, 'align' => 'R'],
        ['key' => 'jumlah_transaksi', 'label' => 'Trx', 'w' => 20, 'align' => 'R'],
    ];
    $pdf->boTitle = 'Laporan Inventory - Top 5 Item Per Bulan';
    bo_export_pdf_set_table($pdf, $colsTopMonthly, true);
    $rowsTopMonthly = [];
    $monthlyRanks = [];
    foreach ($topItemsMonthly as $r) {
        $periodKey = (string)($r['periode_bulan'] ?? '');
        if (!isset($monthlyRanks[$periodKey])) {
            $monthlyRanks[$periodKey] = 0;
        }
        $monthlyRanks[$periodKey]++;
        $rowsTopMonthly[] = [
            'periode_bulan' => $periodKey,
            'rank' => '#' . $monthlyRanks[$periodKey],
            'kode_barang' => (string)($r['kode_barang'] ?? ''),
            'nama_barang' => (string)($r['nama_barang'] ?? ''),
            'total_masuk' => number_format((int)($r['total_masuk'] ?? 0)),
            'total_keluar' => number_format((int)($r['total_keluar'] ?? 0)),
            'jumlah_transaksi' => number_format((int)($r['jumlah_transaksi'] ?? 0)),
        ];
    }
    bo_pdf_draw_rows($pdf, $colsTopMonthly, $rowsTopMonthly);
    bo_export_pdf_download($pdf, bo_export_filename('Laporan_Inventory', 'pdf'));
}
$headerActions = '<a class="btn btn-outline-secondary" href="' . htmlspecialchars(bo_url_for('dashboard.php')) . '"><i class="bi bi-grid-1x2 me-1"></i>Dashboard</a>';
bo_render_shell_start([
    'title' => 'Laporan Inventory - Backoffice',
    'page_title' => 'Laporan Inventory',
    'page_subtitle' => 'Pantau stok saat ini, perubahan stok, dan item teratas dalam satu halaman.',
    'active' => 'reports-inventory',
    'header_actions' => $headerActions,
]);
?>
<div class="bo-card p-4 mb-4">
    <div class="bo-card-header">
        <div>
            <h3 class="bo-card-title">Filter Inventory</h3>
            <div class="bo-card-subtitle">Gunakan filter gudang, item, dan periode rekap untuk menyesuaikan tampilan dashboard inventory.</div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <button class="btn btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#inventoryFilters" aria-expanded="true" aria-controls="inventoryFilters">
                <i class="bi bi-sliders me-1"></i>Toggle Filter
            </button>
            <a class="btn btn-outline-secondary" href="<?= htmlspecialchars(bo_url_for('reports/inventory_price.php')) ?>"><i class="bi bi-tags me-1"></i>Inventory Price</a>
            <a class="btn btn-outline-secondary" href="<?= htmlspecialchars(bo_url_for('reports/item_movement.php')) ?>"><i class="bi bi-graph-up-arrow me-1"></i>Item Movement</a>
        </div>
    </div>
    <div class="collapse show" id="inventoryFilters">
    <form class="row g-3 align-items-end" method="get">
                <div class="col-md-3">
                    <label class="form-label">Tanggal Mulai</label>
                    <input type="date" name="start" class="form-control" value="<?= htmlspecialchars($start) ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Tanggal Akhir</label>
                    <input type="date" name="end" class="form-control" value="<?= htmlspecialchars($end) ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Filter Gudang</label>
                    <select name="gudang_id" class="form-select">
                        <option value="0">Semua Gudang</option>
                        <?php foreach ($gudangOptions as $g): ?>
                            <option value="<?= (int)$g['id'] ?>" <?= ((int)$g['id'] === $gudangId) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($g['nama_gudang']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Cari Item (kode/nama)</label>
                    <input type="text" name="q" class="form-control" value="<?= htmlspecialchars($qText) ?>" placeholder="contoh: BB- / Gula / Susu">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Periode Rekap Perubahan</label>
                    <select name="group" class="form-select">
                        <option value="day" <?= $group === 'day' ? 'selected' : '' ?>>Per Hari</option>
                        <option value="month" <?= $group === 'month' ? 'selected' : '' ?>>Per Bulan</option>
                        <option value="year" <?= $group === 'year' ? 'selected' : '' ?>>Per Tahun</option>
                    </select>
                </div>
                <div class="col-md-3 d-grid">
                    <button class="btn btn-primary" type="submit"><i class="bi bi-funnel me-1"></i>Terapkan</button>
                </div>
                <div class="col-md-3 d-grid">
                    <a class="btn btn-success" href="<?= htmlspecialchars(bo_url_for('reports/inventory.php?' . http_build_query(array_merge($_GET, ['export' => 'xlsx'])))) ?>"><i class="bi bi-file-earmark-excel me-1"></i>Export XLSX</a>
                </div>
                <div class="col-md-3 d-grid">
                    <a class="btn btn-danger" href="<?= htmlspecialchars(bo_url_for('reports/inventory.php?' . http_build_query(array_merge($_GET, ['export' => 'pdf'])))) ?>"><i class="bi bi-file-earmark-pdf me-1"></i>Export PDF</a>
                </div>
                <div class="col-md-6 text-md-end align-self-end">
                    <div class="fw-bold">Total Qty: <?= number_format($totalQty) ?> • Nilai Stok (HPP): Rp <?= number_format($totalValue, 0, ',', '.') ?></div>
                </div>
    </form>
    </div>
</div>

<ul class="nav nav-tabs mb-3" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-stock" type="button" role="tab">Stok Saat Ini</button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-changes" type="button" role="tab">Perubahan Stok</button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-top-items" type="button" role="tab">Top 5 Item</button>
    </li>
</ul>

<div class="tab-content">
    <div class="tab-pane fade show active" id="tab-stock" role="tabpanel">
        <div class="bo-card p-4">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Gudang</th>
                            <th>Kode</th>
                            <th>Barang</th>
                            <th class="text-end">Stok Awal</th>
                            <th class="text-end">Terpakai</th>
                            <th class="text-end">Stok Sisa</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($stockRows)): ?>
                            <tr><td colspan="6" class="text-center text-muted py-4">Data tidak ada.</td></tr>
                        <?php else: ?>
                            <?php foreach ($stockRows as $r): ?>
                                <tr>
                                    <td><?= htmlspecialchars($r['nama_gudang']) ?></td>
                                    <td><?= htmlspecialchars($r['kode_barang']) ?></td>
                                    <td><?= htmlspecialchars($r['nama_barang']) ?></td>
                                    <td class="text-end"><?= number_format((int)($r['stok_awal'] ?? 0)) ?></td>
                                    <td class="text-end"><?= number_format((int)($r['stok_terpakai'] ?? 0)) ?></td>
                                    <td class="text-end"><?= number_format((int)($r['stok_sisa'] ?? 0)) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="tab-pane fade" id="tab-changes" role="tabpanel">
        <div class="bo-card p-4">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Periode</th>
                            <?php if ($gudangId > 0): ?>
                                <th>Gudang</th>
                            <?php endif; ?>
                            <th>Kode</th>
                            <th>Barang</th>
                            <th class="text-end">Masuk</th>
                            <th class="text-end">Keluar</th>
                            <th class="text-end">Reset</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($changeRows)): ?>
                            <tr><td colspan="<?= $gudangId > 0 ? 7 : 6 ?>" class="text-center text-muted py-4">Data tidak ada.</td></tr>
                        <?php else: ?>
                            <?php foreach ($changeRows as $r): ?>
                                <tr>
                                    <td><?= htmlspecialchars((string)$r['periode']) ?></td>
                                    <?php if ($gudangId > 0): ?>
                                        <td><?= htmlspecialchars((string)($r['nama_gudang'] ?? '-')) ?></td>
                                    <?php endif; ?>
                                    <td><?= htmlspecialchars((string)$r['kode_barang']) ?></td>
                                    <td><?= htmlspecialchars((string)$r['nama_barang']) ?></td>
                                    <td class="text-end"><?= number_format((int)($r['qty_masuk'] ?? 0)) ?></td>
                                    <td class="text-end"><?= number_format((int)($r['qty_keluar'] ?? 0)) ?></td>
                                    <td class="text-end"><?= number_format((int)($r['jumlah_reset'] ?? 0)) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="tab-pane fade" id="tab-top-items" role="tabpanel">
        <div class="bo-card p-4">
            <div class="bo-card-header">
                <div>
                    <h3 class="bo-card-title">Top 5 Item</h3>
                    <div class="bo-card-subtitle">Item dengan qty keluar atau terpakai tertinggi pada periode aktif, lengkap dengan live update dan riwayat per bulan.</div>
                </div>
                <div class="d-flex flex-column align-items-md-end gap-2">
                    <div class="text-muted small" id="topItemsPeriodLabel"><?= htmlspecialchars($start) ?> s/d <?= htmlspecialchars($end) ?></div>
                    <div class="d-flex align-items-center gap-3 flex-wrap">
                        <div class="form-check form-switch m-0">
                            <input class="form-check-input" type="checkbox" role="switch" id="topItemsLiveToggle" <?= $end === date('Y-m-d') ? 'checked' : '' ?>>
                            <label class="form-check-label small" for="topItemsLiveToggle">Live time</label>
                        </div>
                        <span class="badge text-bg-light" id="topItemsLiveStatus">Auto refresh <?= (int)$liveRefreshSeconds ?> detik</span>
                        <span class="text-muted small" id="topItemsLastUpdated">Belum diperbarui</span>
                    </div>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th style="width: 80px;">Rank</th>
                            <th>Kode</th>
                            <th>Barang</th>
                            <th class="text-end">Total Masuk</th>
                            <th class="text-end">Total Keluar</th>
                            <th class="text-end">Jumlah Transaksi</th>
                        </tr>
                    </thead>
                    <tbody id="topItemsTableBody">
                        <?php if (empty($topItems)): ?>
                            <tr><td colspan="6" class="text-center text-muted py-4">Data top item tidak ada untuk filter ini.</td></tr>
                        <?php else: ?>
                            <?php foreach ($topItems as $i => $r): ?>
                                <tr>
                                    <td><span class="badge bg-primary">#<?= $i + 1 ?></span></td>
                                    <td><?= htmlspecialchars((string)$r['kode_barang']) ?></td>
                                    <td><?= htmlspecialchars((string)$r['nama_barang']) ?></td>
                                    <td class="text-end"><?= number_format((int)($r['total_masuk'] ?? 0)) ?></td>
                                    <td class="text-end fw-bold"><?= number_format((int)($r['total_keluar'] ?? 0)) ?></td>
                                    <td class="text-end"><?= number_format((int)($r['jumlah_transaksi'] ?? 0)) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="bo-card p-4 mt-4">
            <div class="bo-card-header">
                <div>
                    <h3 class="bo-card-title">Riwayat Per Bulan</h3>
                    <div class="bo-card-subtitle">Top 5 item per bulan untuk 6 bulan terakhir berdasarkan filter gudang dan pencarian saat ini.</div>
                </div>
                <div class="text-muted small" id="topItemsMonthlyRangeLabel"><?= htmlspecialchars($topItemsMonthlyRange['start']) ?> s/d <?= htmlspecialchars($topItemsMonthlyRange['end']) ?></div>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Bulan</th>
                            <th style="width: 80px;">Rank</th>
                            <th>Kode</th>
                            <th>Barang</th>
                            <th class="text-end">Total Masuk</th>
                            <th class="text-end">Total Keluar</th>
                            <th class="text-end">Jumlah Transaksi</th>
                        </tr>
                    </thead>
                    <tbody id="topItemsMonthlyTableBody">
                        <?php if (empty($topItemsMonthly)): ?>
                            <tr><td colspan="7" class="text-center text-muted py-4">Riwayat bulanan belum tersedia.</td></tr>
                        <?php else: ?>
                            <?php $monthlyRanks = []; ?>
                            <?php foreach ($topItemsMonthly as $r): ?>
                                <?php
                                $periodKey = (string)($r['periode_bulan'] ?? '');
                                if (!isset($monthlyRanks[$periodKey])) {
                                    $monthlyRanks[$periodKey] = 0;
                                }
                                $monthlyRanks[$periodKey]++;
                                ?>
                                <tr>
                                    <td><?= htmlspecialchars($periodKey) ?></td>
                                    <td><span class="badge bg-primary">#<?= $monthlyRanks[$periodKey] ?></span></td>
                                    <td><?= htmlspecialchars((string)($r['kode_barang'] ?? '')) ?></td>
                                    <td><?= htmlspecialchars((string)($r['nama_barang'] ?? '')) ?></td>
                                    <td class="text-end"><?= number_format((int)($r['total_masuk'] ?? 0)) ?></td>
                                    <td class="text-end fw-bold"><?= number_format((int)($r['total_keluar'] ?? 0)) ?></td>
                                    <td class="text-end"><?= number_format((int)($r['jumlah_transaksi'] ?? 0)) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const topItemsTabButton = document.querySelector('[data-bs-target="#tab-top-items"]');
    const liveToggle = document.getElementById('topItemsLiveToggle');
    const liveStatus = document.getElementById('topItemsLiveStatus');
    const lastUpdated = document.getElementById('topItemsLastUpdated');
    const periodLabel = document.getElementById('topItemsPeriodLabel');
    const monthlyRangeLabel = document.getElementById('topItemsMonthlyRangeLabel');
    const topItemsTableBody = document.getElementById('topItemsTableBody');
    const topItemsMonthlyTableBody = document.getElementById('topItemsMonthlyTableBody');
    if (!topItemsTabButton || !liveToggle || !liveStatus || !lastUpdated || !periodLabel || !monthlyRangeLabel || !topItemsTableBody || !topItemsMonthlyTableBody) {
        return;
    }

    const refreshSeconds = <?= (int)$liveRefreshSeconds ?>;
    const queryString = <?= json_encode(http_build_query([
        'start' => $start,
        'end' => $end,
        'gudang_id' => $gudangId,
        'group' => $group,
        'q' => $qText,
    ]), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    const endpoint = <?= json_encode(bo_url_for('reports/inventory.php'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?> + '?' + queryString + '&format=json';
    let refreshTimer = null;
    let clockTimer = null;
    let liveNow = new Date();
    let isFetching = false;

    const escapeHtml = function (value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    };

    const formatNumber = function (value) {
        const number = Number(value || 0);
        return number.toLocaleString('id-ID');
    };

    const formatDateTime = function (value) {
        return value.toLocaleString('id-ID', {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit'
        });
    };

    const renderTopItems = function (items) {
        if (!Array.isArray(items) || items.length === 0) {
            topItemsTableBody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">Data top item tidak ada untuk filter ini.</td></tr>';
            return;
        }
        topItemsTableBody.innerHTML = items.map(function (item, index) {
            return '<tr>'
                + '<td><span class="badge bg-primary">#' + (index + 1) + '</span></td>'
                + '<td>' + escapeHtml(item.kode_barang || '') + '</td>'
                + '<td>' + escapeHtml(item.nama_barang || '') + '</td>'
                + '<td class="text-end">' + formatNumber(item.total_masuk) + '</td>'
                + '<td class="text-end fw-bold">' + formatNumber(item.total_keluar) + '</td>'
                + '<td class="text-end">' + formatNumber(item.jumlah_transaksi) + '</td>'
                + '</tr>';
        }).join('');
    };

    const renderTopItemsMonthly = function (items) {
        if (!Array.isArray(items) || items.length === 0) {
            topItemsMonthlyTableBody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">Riwayat bulanan belum tersedia.</td></tr>';
            return;
        }
        const ranks = {};
        topItemsMonthlyTableBody.innerHTML = items.map(function (item) {
            const periodKey = item.periode_bulan || '-';
            ranks[periodKey] = (ranks[periodKey] || 0) + 1;
            return '<tr>'
                + '<td>' + escapeHtml(periodKey) + '</td>'
                + '<td><span class="badge bg-primary">#' + ranks[periodKey] + '</span></td>'
                + '<td>' + escapeHtml(item.kode_barang || '') + '</td>'
                + '<td>' + escapeHtml(item.nama_barang || '') + '</td>'
                + '<td class="text-end">' + formatNumber(item.total_masuk) + '</td>'
                + '<td class="text-end fw-bold">' + formatNumber(item.total_keluar) + '</td>'
                + '<td class="text-end">' + formatNumber(item.jumlah_transaksi) + '</td>'
                + '</tr>';
        }).join('');
    };

    const tickClock = function () {
        liveNow = new Date(liveNow.getTime() + 1000);
        const suffix = liveToggle.checked ? ' | live ' + formatDateTime(liveNow) : '';
        liveStatus.textContent = 'Auto refresh ' + refreshSeconds + ' detik' + suffix;
    };

    const setLastUpdated = function () {
        liveNow = new Date();
        lastUpdated.textContent = 'Update terakhir: ' + formatDateTime(liveNow);
        tickClock();
    };

    const stopLiveRefresh = function () {
        if (refreshTimer) {
            clearInterval(refreshTimer);
            refreshTimer = null;
        }
        if (clockTimer) {
            clearInterval(clockTimer);
            clockTimer = null;
        }
        liveStatus.textContent = 'Live time nonaktif';
    };

    const fetchTopItems = function () {
        if (!liveToggle.checked || isFetching) {
            return;
        }
        isFetching = true;
        fetch(endpoint, {
            headers: {
                'Accept': 'application/json'
            },
            cache: 'no-store'
        })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('Gagal memuat data');
                }
                return response.json();
            })
            .then(function (payload) {
                if (!payload || !payload.success || !payload.data) {
                    throw new Error('Payload tidak valid');
                }
                renderTopItems(payload.data.top_items || []);
                renderTopItemsMonthly(payload.data.top_items_monthly || []);
                if (payload.data.filters) {
                    periodLabel.textContent = (payload.data.filters.start || '-') + ' s/d ' + (payload.data.filters.end || '-');
                }
                if (payload.data.top_items_monthly_range) {
                    monthlyRangeLabel.textContent = (payload.data.top_items_monthly_range.start || '-') + ' s/d ' + (payload.data.top_items_monthly_range.end || '-');
                }
                setLastUpdated();
            })
            .catch(function () {
                lastUpdated.textContent = 'Gagal memperbarui data live.';
            })
            .finally(function () {
                isFetching = false;
            });
    };

    const startLiveRefresh = function () {
        stopLiveRefresh();
        if (!liveToggle.checked) {
            return;
        }
        setLastUpdated();
        clockTimer = window.setInterval(tickClock, 1000);
        refreshTimer = window.setInterval(fetchTopItems, refreshSeconds * 1000);
        fetchTopItems();
    };

    liveToggle.addEventListener('change', startLiveRefresh);
    topItemsTabButton.addEventListener('shown.bs.tab', function () {
        if (liveToggle.checked) {
            startLiveRefresh();
        } else {
            tickClock();
        }
    });
    if (document.getElementById('tab-top-items') && document.getElementById('tab-top-items').classList.contains('show') && liveToggle.checked) {
        startLiveRefresh();
    } else if (!liveToggle.checked) {
        stopLiveRefresh();
        lastUpdated.textContent = 'Aktifkan live time untuk auto refresh.';
    } else {
        setLastUpdated();
        clockTimer = window.setInterval(tickClock, 1000);
    }
});
</script>
<?php bo_render_shell_end(); ?>
