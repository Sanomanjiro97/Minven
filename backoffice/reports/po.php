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
    if (!checkAccess('backoffice', 'view') || !checkAccess('backoffice_reports_po', 'view')) {
        $jsonOut(['success' => false, 'message' => 'Forbidden'], 403);
    }
} else {
    bo_require_login();
    if (!checkAccess('backoffice_reports_po', 'view')) {
        header('Location: ' . url_for('unauthorized.php'));
        exit();
    }
}

$start = (string)($_GET['start'] ?? '');
$end = (string)($_GET['end'] ?? '');
$supplierId = (int)($_GET['supplier_id'] ?? 0);
$q = trim((string)($_GET['q'] ?? ''));

if ($start === '' || $end === '') {
    $start = date('Y-m-01');
    $end = date('Y-m-t');
}

$suppliers = [];
if ($boMainConn) {
    $res = $boMainConn->query("SELECT id, nama_supplier FROM supplier ORDER BY nama_supplier");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $suppliers[] = $row;
        }
    }
}

$pos = [];
$totalPo = 0.0;

if ($boMainConn) {
    $detailStatusExists = function_exists('db_has_column') ? db_has_column($boMainConn, 'detail_purchase_order', 'status') : true;
    $detailFilter = $detailStatusExists ? "AND (d2.status IS NULL OR d2.status != 'rejected')" : "";

    $params = [];
    $types = '';
    $where = "po.tanggal BETWEEN ? AND ?";
    $types .= 'ss';
    $params[] = $start;
    $params[] = $end;

    $where .= " AND po.status != 'menunggu'";

    if ($supplierId > 0) {
        $where .= " AND po.supplier_id = ?";
        $types .= 'i';
        $params[] = $supplierId;
    }

    $joinDetail = '';
    if ($q !== '') {
        $joinDetail = "LEFT JOIN detail_purchase_order d ON d.purchase_order_id = po.id
                       LEFT JOIN barang b ON b.id = d.barang_id";
        $where .= " AND (b.kode_barang LIKE ? OR b.nama_barang LIKE ?)";
        $types .= 'ss';
        $like = '%' . $q . '%';
        $params[] = $like;
        $params[] = $like;
    }

    $sql = "SELECT po.id, po.no_po, po.tanggal,
                   (
                       SELECT COALESCE(SUM(d2.jumlah), 0)
                       FROM detail_purchase_order d2
                       WHERE d2.purchase_order_id = po.id
                       $detailFilter
                   ) AS total_item,
                   (
                       SELECT COALESCE(SUM(COALESCE(d2.total_harga, (d2.jumlah * d2.harga_satuan))), 0)
                       FROM detail_purchase_order d2
                       WHERE d2.purchase_order_id = po.id
                       $detailFilter
                   ) AS total_harga,
                   po.status,
                   s.nama_supplier
            FROM purchase_order po
            LEFT JOIN supplier s ON s.id = po.supplier_id
            $joinDetail
            WHERE $where
            GROUP BY po.id
            ORDER BY po.tanggal DESC, po.id DESC";

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
        while ($res && ($row = $res->fetch_assoc())) {
            $pos[] = $row;
            $totalPo += (float)($row['total_harga'] ?? 0);
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
                'supplier_id' => $supplierId,
                'q' => $q,
            ],
            'suppliers' => $suppliers,
            'items' => $pos,
            'total_po' => $totalPo,
        ],
    ]);
}
?>
<?php
$headerActions = '<a class="btn btn-outline-secondary" href="' . htmlspecialchars(bo_url_for('dashboard.php')) . '"><i class="bi bi-grid-1x2 me-1"></i>Dashboard</a>';
bo_render_shell_start([
    'title' => 'Laporan PO - Backoffice',
    'page_title' => 'Laporan PO',
    'page_subtitle' => 'Monitor purchase order dengan filter periode, supplier, dan pencarian item.',
    'active' => 'reports-po',
    'header_actions' => $headerActions,
]);
?>
<div class="bo-card p-4 mb-4">
    <div class="bo-card-header">
        <div>
            <h3 class="bo-card-title">Filter Laporan</h3>
            <div class="bo-card-subtitle">Tampilkan data PO sesuai periode dan supplier yang dibutuhkan.</div>
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
                    <label class="form-label">Supplier</label>
                    <select name="supplier_id" class="form-select">
                        <option value="0">Semua Supplier</option>
                        <?php foreach ($suppliers as $s): ?>
                            <option value="<?= (int)$s['id'] ?>" <?= ((int)$s['id'] === $supplierId) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($s['nama_supplier']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Cari Item (kode/nama)</label>
                    <input type="text" name="q" class="form-control" value="<?= htmlspecialchars($q) ?>" placeholder="contoh: BB- / Gula / Susu">
                </div>
                <div class="col-12 d-flex gap-2 mt-2">
                    <button class="btn btn-primary"><i class="bi bi-funnel me-1"></i>Terapkan</button>
                    <a class="btn btn-outline-secondary" href="<?= htmlspecialchars(bo_url_for('reports/po.php')) ?>">Reset</a>
                    <div class="ms-auto fw-bold align-self-center">Total PO: Rp <?= number_format($totalPo, 0, ',', '.') ?></div>
                </div>
    </form>
</div>

<div class="bo-card p-4">
    <div class="bo-card-header">
        <div>
            <h3 class="bo-card-title">Daftar Purchase Order</h3>
            <div class="bo-card-subtitle">Hasil filter laporan PO yang siap ditinjau lebih detail.</div>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Tanggal</th>
                    <th>No PO</th>
                    <th>Supplier</th>
                    <th>Status</th>
                    <th class="text-end">Total Item</th>
                    <th class="text-end">Total PO</th>
                    <th class="text-end">Detail</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($pos)): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">Data tidak ada.</td></tr>
                <?php else: ?>
                    <?php foreach ($pos as $po): ?>
                        <tr>
                            <td><?= htmlspecialchars($po['tanggal']) ?></td>
                            <td><?= htmlspecialchars($po['no_po']) ?></td>
                            <td><?= htmlspecialchars($po['nama_supplier'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($po['status'] ?? '-') ?></td>
                            <td class="text-end"><?= number_format((int)($po['total_item'] ?? 0)) ?></td>
                            <td class="text-end">Rp <?= number_format((float)($po['total_harga'] ?? 0), 0, ',', '.') ?></td>
                            <td class="text-end">
                                <a class="btn btn-sm btn-outline-primary" href="<?= htmlspecialchars(bo_url_for('reports/po_detail.php?id=' . (int)$po['id'])) ?>">Lihat</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php bo_render_shell_end(); ?>
