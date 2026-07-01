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
    if (!checkAccess('backoffice', 'view') || !checkAccess('backoffice_reports_direct', 'view')) {
        $jsonOut(['success' => false, 'message' => 'Forbidden'], 403);
    }
} else {
    bo_require_login();
    if (!checkAccess('backoffice_reports_direct', 'view')) {
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

$directs = [];
$totalDirect = 0.0;

if ($boMainConn) {
    $params = [];
    $types = '';
    $where = "dp.tanggal BETWEEN ? AND ?";
    $types .= 'ss';
    $params[] = $start;
    $params[] = $end;

    if ($supplierId > 0) {
        $where .= " AND dp.supplier_id = ?";
        $types .= 'i';
        $params[] = $supplierId;
    }

    $joinDetail = '';
    if ($q !== '') {
        $joinDetail = "LEFT JOIN detail_direct_purchase d ON d.direct_purchase_id = dp.id
                       LEFT JOIN barang b ON b.id = d.barang_id";
        $where .= " AND (b.kode_barang LIKE ? OR b.nama_barang LIKE ? OR d.keterangan LIKE ?)";
        $types .= 'sss';
        $like = '%' . $q . '%';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }

    $sql = "SELECT dp.id, dp.no_transaksi, dp.tanggal, dp.total_item, dp.total_harga, dp.status, dp.nama_toko,
                   s.nama_supplier
            FROM direct_purchase dp
            LEFT JOIN supplier s ON s.id = dp.supplier_id
            $joinDetail
            WHERE $where
            GROUP BY dp.id
            ORDER BY dp.tanggal DESC, dp.id DESC";

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
            $directs[] = $row;
            $totalDirect += (float)($row['total_harga'] ?? 0);
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
            'items' => $directs,
            'total_direct' => $totalDirect,
        ],
    ]);
}
?>
<?php
$headerActions = '<a class="btn btn-outline-secondary" href="' . htmlspecialchars(bo_url_for('dashboard.php')) . '"><i class="bi bi-grid-1x2 me-1"></i>Dashboard</a>';
bo_render_shell_start([
    'title' => 'Laporan Pemberian Direct - Backoffice',
    'page_title' => 'Laporan Direct',
    'page_subtitle' => 'Pantau pembelian direct dengan filter periode, supplier, dan pencarian item.',
    'active' => 'reports-direct',
    'header_actions' => $headerActions,
]);
?>
<div class="bo-card p-4 mb-4">
    <div class="bo-card-header">
        <div>
            <h3 class="bo-card-title">Filter Laporan</h3>
            <div class="bo-card-subtitle">Gunakan filter untuk mempersempit data direct yang ditampilkan.</div>
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
                    <label class="form-label">Cari Item (kode/nama/keterangan)</label>
                    <input type="text" name="q" class="form-control" value="<?= htmlspecialchars($q) ?>" placeholder="contoh: BB- / Gula / Keterangan">
                </div>
                <div class="col-12 d-flex gap-2 mt-2">
                    <button class="btn btn-primary"><i class="bi bi-funnel me-1"></i>Terapkan</button>
                    <a class="btn btn-outline-secondary" href="<?= htmlspecialchars(bo_url_for('reports/direct.php')) ?>">Reset</a>
                    <div class="ms-auto fw-bold align-self-center">Total Direct: Rp <?= number_format($totalDirect, 0, ',', '.') ?></div>
                </div>
    </form>
</div>

<div class="bo-card p-4">
    <div class="bo-card-header">
        <div>
            <h3 class="bo-card-title">Daftar Direct</h3>
            <div class="bo-card-subtitle">Hasil filter pembelian direct yang siap dilihat detailnya.</div>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Tanggal</th>
                    <th>No Transaksi</th>
                    <th>Supplier</th>
                    <th>Nama Toko</th>
                    <th class="text-end">Total Item</th>
                    <th class="text-end">Total Harga</th>
                    <th>Status</th>
                    <th class="text-end">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($directs)): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4">Data tidak ada.</td></tr>
                <?php else: ?>
                    <?php foreach ($directs as $d): ?>
                        <tr>
                            <td><?= htmlspecialchars((string)($d['tanggal'] ?? '')) ?></td>
                            <td><?= htmlspecialchars((string)($d['no_transaksi'] ?? '')) ?></td>
                            <td><?= htmlspecialchars((string)($d['nama_supplier'] ?? '-')) ?></td>
                            <td><?= htmlspecialchars((string)($d['nama_toko'] ?? '-')) ?></td>
                            <td class="text-end"><?= number_format((int)($d['total_item'] ?? 0)) ?></td>
                            <td class="text-end">Rp <?= number_format((float)($d['total_harga'] ?? 0), 0, ',', '.') ?></td>
                            <td><?= htmlspecialchars((string)($d['status'] ?? '-')) ?></td>
                            <td class="text-end">
                                <a class="btn btn-sm btn-outline-primary" href="<?= htmlspecialchars(bo_url_for('reports/direct_detail.php?id=' . (int)($d['id'] ?? 0))) ?>">Lihat</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php bo_render_shell_end(); ?>
