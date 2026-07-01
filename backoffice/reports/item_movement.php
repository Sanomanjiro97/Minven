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
    if (!checkAccess('backoffice', 'view') || !checkAccess('backoffice_reports_item_movement', 'view')) {
        $jsonOut(['success' => false, 'message' => 'Forbidden'], 403);
    }
} else {
    bo_require_login();
    if (!checkAccess('backoffice_reports_item_movement', 'view')) {
        header('Location: ' . url_for('unauthorized.php'));
        exit();
    }
}

$start = (string)($_GET['start'] ?? '');
$end = (string)($_GET['end'] ?? '');
$mode = (string)($_GET['mode'] ?? 'frequent');
$limit = (int)($_GET['limit'] ?? 20);
$qText = trim((string)($_GET['q'] ?? ''));
$gudangId = (int)($_GET['gudang_id'] ?? 0);

if ($start === '' || $end === '') {
    $start = date('Y-m-01');
    $end = date('Y-m-t');
}
if (!in_array($mode, ['frequent', 'slow'], true)) {
    $mode = 'frequent';
}
if ($limit <= 0 || $limit > 500) {
    $limit = 20;
}

$gudangOptions = [];
if ($boMainConn) {
    $res = $boMainConn->query("SELECT id, nama_gudang FROM gudang ORDER BY nama_gudang");
    if ($res) {
        while ($r = $res->fetch_assoc()) $gudangOptions[] = $r;
    }
}

$rows = [];

if ($boMainConn) {
    $types = 'ss';
    $params = [$start, $end];
    $whereMovement = "DATE(h.created_at) BETWEEN ? AND ?";

    if ($gudangId > 0) {
        $whereMovement .= " AND h.gudang_id = ?";
        $types .= 'i';
        $params[] = $gudangId;
    }

    $whereItem = "1=1";
    if ($qText !== '') {
        $whereItem .= " AND (b.kode_barang LIKE ? OR b.nama_barang LIKE ?)";
        $types .= 'ss';
        $like = '%' . $qText . '%';
        $params[] = $like;
        $params[] = $like;
    }

    $order = $mode === 'slow' ? 'qty_keluar ASC' : 'qty_keluar DESC';

    $sql = "
        SELECT b.kode_barang, b.nama_barang,
               COALESCE(m.qty_keluar, 0) AS qty_keluar,
               COALESCE(m.qty_masuk, 0) AS qty_masuk,
               COALESCE(s.qty_stok, 0) AS qty_stok
        FROM barang b
        LEFT JOIN (
            SELECT h.barang_id,
                   SUM(CASE WHEN h.jenis_perubahan IN ('keluar','transfer_out') THEN h.jumlah_perubahan ELSE 0 END) AS qty_keluar,
                   SUM(CASE WHEN h.jenis_perubahan IN ('masuk','transfer_in') THEN h.jumlah_perubahan ELSE 0 END) AS qty_masuk
            FROM gudang_stok_history h
            WHERE $whereMovement
            GROUP BY h.barang_id
        ) m ON m.barang_id = b.id
        LEFT JOIN (
            SELECT gs.barang_id, SUM(gs.stok_awal - gs.stok_terpakai) AS qty_stok
            FROM gudang_stok gs
            " . ($gudangId > 0 ? "WHERE gs.gudang_id = " . (int)$gudangId : "") . "
            GROUP BY gs.barang_id
        ) s ON s.barang_id = b.id
        WHERE $whereItem
        ORDER BY $order, b.nama_barang ASC
        LIMIT " . (int)$limit . "
    ";

    $stmt = $boMainConn->prepare($sql);
    if ($stmt) {
        $bindArgs = [];
        $bindArgs[] = $types;
        foreach ($params as $k => $v) $bindArgs[] = $params[$k];
        $refs = [];
        foreach ($bindArgs as $k => $v) $refs[$k] = &$bindArgs[$k];
        call_user_func_array([$stmt, 'bind_param'], $refs);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($res && ($r = $res->fetch_assoc())) {
            $rows[] = $r;
        }
        $stmt->close();
    }
}

if ($wantsJson) {
    $jsonOut([
        'success' => true,
        'data' => [
            'filters' => [
                'start' => $start,
                'end' => $end,
                'mode' => $mode,
                'limit' => $limit,
                'q' => $qText,
                'gudang_id' => $gudangId,
            ],
            'gudang_options' => $gudangOptions,
            'items' => $rows,
        ],
    ]);
}

$export = (string)($_GET['export'] ?? '');
if ($export === 'xlsx') {
    $sheet = [];
    $sheet[] = ['Inventory - Item Movement'];
    $sheet[] = ['Periode', $start, $end];
    $sheet[] = ['Gudang', $gudangId > 0 ? (int)$gudangId : 'Semua'];
    $sheet[] = ['Mode', $mode];
    $sheet[] = ['Cari', $qText !== '' ? $qText : '-'];
    $sheet[] = ['Limit', (int)$limit];
    $sheet[] = [];
    $sheet[] = ['Kode', 'Nama Item', 'Keluar', 'Masuk', 'Stok Sisa'];
    foreach ($rows as $r) {
        $sheet[] = [
            (string)($r['kode_barang'] ?? ''),
            (string)($r['nama_barang'] ?? ''),
            (int)($r['qty_keluar'] ?? 0),
            (int)($r['qty_masuk'] ?? 0),
            (int)($r['qty_stok'] ?? 0),
        ];
    }
    bo_export_xlsx_download(bo_export_filename('Inventory_Item_Movement', 'xlsx'), 'Item Movement', $sheet);
}
if ($export === 'pdf') {
    $sub = [
        'Periode: ' . $start . ' s/d ' . $end,
        'Gudang: ' . ($gudangId > 0 ? (string)$gudangId : 'Semua') . ' • Mode: ' . $mode . ' • Limit: ' . (int)$limit,
        'Cari: ' . ($qText !== '' ? $qText : '-'),
    ];
    $cols = [
        ['key' => 'kode_barang', 'label' => 'Kode', 'w' => 25, 'align' => 'L'],
        ['key' => 'nama_barang', 'label' => 'Nama Item', 'w' => 90, 'align' => 'L'],
        ['key' => 'qty_keluar', 'label' => 'Keluar', 'w' => 25, 'align' => 'R'],
        ['key' => 'qty_masuk', 'label' => 'Masuk', 'w' => 25, 'align' => 'R'],
        ['key' => 'qty_stok', 'label' => 'Stok', 'w' => 25, 'align' => 'R'],
    ];
    $pdf = bo_export_pdf_begin('P', 'Inventory - Item Movement', $sub, $cols);
    $out = [];
    foreach ($rows as $r) {
        $out[] = [
            'kode_barang' => (string)($r['kode_barang'] ?? ''),
            'nama_barang' => (string)($r['nama_barang'] ?? ''),
            'qty_keluar' => number_format((int)($r['qty_keluar'] ?? 0)),
            'qty_masuk' => number_format((int)($r['qty_masuk'] ?? 0)),
            'qty_stok' => number_format((int)($r['qty_stok'] ?? 0)),
        ];
    }
    bo_pdf_draw_rows($pdf, $cols, $out);
    bo_export_pdf_download($pdf, bo_export_filename('Inventory_Item_Movement', 'pdf'));
}
$headerActions = '<a class="btn btn-outline-secondary" href="' . htmlspecialchars(bo_url_for('dashboard.php')) . '"><i class="bi bi-grid-1x2 me-1"></i>Dashboard</a>';
bo_render_shell_start([
    'title' => 'Item Movement - Backoffice',
    'page_title' => 'Item Movement',
    'page_subtitle' => 'Pantau item yang sering atau jarang bergerak dengan layout sidebar yang konsisten.',
    'active' => 'item-movement',
    'header_actions' => $headerActions,
]);
?>
<div class="bo-card p-4 mb-4">
    <div class="bo-card-header">
        <div>
            <h3 class="bo-card-title">Filter Item Movement</h3>
            <div class="bo-card-subtitle">Gunakan periode, gudang, mode, pencarian item, dan limit untuk melihat pergerakan item.</div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a class="btn btn-outline-secondary" href="<?= htmlspecialchars(bo_url_for('reports/inventory.php')) ?>"><i class="bi bi-box-seam me-1"></i>Inventory</a>
            <a class="btn btn-outline-secondary" href="<?= htmlspecialchars(bo_url_for('reports/inventory_price.php')) ?>"><i class="bi bi-tags me-1"></i>Inventory Price</a>
        </div>
    </div>
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
            <label class="form-label">Mode</label>
            <select name="mode" class="form-select">
                <option value="frequent" <?= $mode === 'frequent' ? 'selected' : '' ?>>Sering Keluar</option>
                <option value="slow" <?= $mode === 'slow' ? 'selected' : '' ?>>Tidak Sering Keluar</option>
            </select>
        </div>
        <div class="col-md-6">
            <label class="form-label">Cari Item (kode/nama)</label>
            <input type="text" name="q" class="form-control" value="<?= htmlspecialchars($qText) ?>" placeholder="contoh: BB- / Gula / Susu">
        </div>
        <div class="col-md-3">
            <label class="form-label">Limit</label>
            <input type="number" name="limit" class="form-control" value="<?= (int)$limit ?>" min="1" max="500">
        </div>
        <div class="col-md-3 d-grid">
            <button class="btn btn-primary" type="submit"><i class="bi bi-funnel me-1"></i>Terapkan</button>
        </div>
        <div class="col-md-3 d-grid">
            <a class="btn btn-success" href="<?= htmlspecialchars(bo_url_for('reports/item_movement.php?' . http_build_query(array_merge($_GET, ['export' => 'xlsx'])))) ?>"><i class="bi bi-file-earmark-excel me-1"></i>Export XLSX</a>
        </div>
        <div class="col-md-3 d-grid">
            <a class="btn btn-danger" href="<?= htmlspecialchars(bo_url_for('reports/item_movement.php?' . http_build_query(array_merge($_GET, ['export' => 'pdf'])))) ?>"><i class="bi bi-file-earmark-pdf me-1"></i>Export PDF</a>
        </div>
    </form>
</div>

<div class="bo-card p-4">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Kode</th>
                    <th>Nama Item</th>
                    <th class="text-end">Keluar</th>
                    <th class="text-end">Masuk</th>
                    <th class="text-end">Stok Sisa</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rows)): ?>
                    <tr><td colspan="5" class="text-center text-muted py-4">Data tidak ada.</td></tr>
                <?php else: ?>
                    <?php foreach ($rows as $r): ?>
                        <tr>
                            <td><?= htmlspecialchars((string)$r['kode_barang']) ?></td>
                            <td><?= htmlspecialchars((string)$r['nama_barang']) ?></td>
                            <td class="text-end"><?= number_format((int)($r['qty_keluar'] ?? 0)) ?></td>
                            <td class="text-end"><?= number_format((int)($r['qty_masuk'] ?? 0)) ?></td>
                            <td class="text-end"><?= number_format((int)($r['qty_stok'] ?? 0)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php bo_render_shell_end(); ?>
