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

$successMessage = '';
$errorMessage = '';

// Check apakah kolom location_id ada di tabel purchase_order
$hasLocationColumn = false;
if ($boMainConn) {
    $checkCol = $boMainConn->query("SHOW COLUMNS FROM purchase_order LIKE 'location_id'");
    if ($checkCol && $checkCol->num_rows > 0) {
        $hasLocationColumn = true;
    }
}

// Handle inline payment method & location update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['po_id'])) {
    $poId = (int)$_POST['po_id'];

    // Update Payment Method
    if (isset($_POST['payment_method'])) {
        $paymentMethod = $_POST['payment_method'];
        if ($paymentMethod === 'cash' || $paymentMethod === 'saldo') {
            if ($boMainConn) {
                $stmt = $boMainConn->prepare("UPDATE purchase_order SET payment_method = ? WHERE id = ?");
                if ($stmt) {
                    $stmt->bind_param('si', $paymentMethod, $poId);
                    if ($stmt->execute()) {
                        $successMessage = 'Metode pembayaran PO berhasil diperbarui!';
                    } else {
                        $errorMessage = 'Gagal memperbarui metode pembayaran: ' . $stmt->error;
                    }
                    $stmt->close();
                }
            }
        }
    }

    // Update Lokasi Simpan
    if (isset($_POST['location_id'])) {
        $locationId = (int)$_POST['location_id'];
        if ($boMainConn) {
            if ($hasLocationColumn) {
                $stmt = $boMainConn->prepare("UPDATE purchase_order SET location_id = ? WHERE id = ?");
                if ($stmt) {
                    $stmt->bind_param('ii', $locationId, $poId);
                    if ($stmt->execute()) {
                        $successMessage = 'Lokasi simpan PO berhasil diperbarui!';
                    } else {
                        $errorMessage = 'Gagal memperbarui lokasi simpan: ' . $stmt->error;
                    }
                    $stmt->close();
                }
            } else {
                $errorMessage = 'Gagal menyimpan: Kolom "location_id" belum ditambahkan ke tabel purchase_order!';
            }
        }
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

// Ambil master daftar lokasi dari lokasi_mapping
$locations = [];
if ($boMainConn) {
    $resLoc = @$boMainConn->query("SELECT id, nama_lokasi FROM lokasi_mapping WHERE aktif = 1 ORDER BY nama_lokasi");
    if ($resLoc) {
        while ($rowLoc = $resLoc->fetch_assoc()) {
            $locations[$rowLoc['id']] = $rowLoc['nama_lokasi'];
        }
    }
}

$pos = [];
$totalPo = 0.0;

if ($boMainConn) {
    $detailStatusExists = function_exists('db_has_column') ? db_has_column($boMainConn, 'detail_purchase_order', 'status') : true;

    $params = [];
    $types = '';
    $where = "po.tanggal BETWEEN ? AND ?";
    $types .= 'ss';
    $params[] = $start;
    $params[] = $end;

    $where .= " AND po.status NOT IN ('menunggu', 'draft')";

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

    $locationSelect = $hasLocationColumn ? "po.location_id," : "0 AS location_id,";

    $sql = "SELECT po.id, po.no_po, po.tanggal, $locationSelect
                   CASE 
                       WHEN po.status = 'rejected' THEN 0 
                       ELSE (
                           SELECT COALESCE(SUM(
                               CASE WHEN " . ($detailStatusExists ? "d2.status = 'rejected'" : "1=0") . " THEN 0 
                               ELSE d2.jumlah END
                           ), 0)
                           FROM detail_purchase_order d2
                           WHERE d2.purchase_order_id = po.id
                       ) 
                   END AS total_item,
                   CASE 
                       WHEN po.status = 'rejected' THEN 0 
                       ELSE (
                           SELECT COALESCE(SUM(
                               CASE WHEN " . ($detailStatusExists ? "d2.status = 'rejected'" : "1=0") . " THEN 0 
                               ELSE COALESCE(NULLIF(d2.total_harga, 0), (d2.jumlah * d2.harga_satuan)) END
                           ), 0)
                           FROM detail_purchase_order d2
                           WHERE d2.purchase_order_id = po.id
                       ) 
                   END AS total_harga,
                   po.status,
                   po.payment_method,
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

<!-- Style tambahan agar dropdown ringkas & tidak keluar layar -->
<style>
    .dropdown-menu-scrollable {
        max-height: 200px;
        overflow-y: auto;
    }
    .table-responsive {
        min-height: 350px; /* Menjaga kontainer cukup tinggi agar dropdown tidak terpotong */
    }
</style>

<?php if ($successMessage): ?>
    <div class="alert alert-success mb-4"><?= htmlspecialchars($successMessage) ?></div>
<?php endif; ?>
<?php if ($errorMessage): ?>
    <div class="alert alert-danger mb-4"><?= htmlspecialchars($errorMessage) ?></div>
<?php endif; ?>
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
                    <th>Metode</th>
                    <th>Lokasi Simpan</th>
                    <th class="text-end">Total Item</th>
                    <th class="text-end">Total PO</th>
                    <th class="text-end">Detail</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($pos)): ?>
                    <tr><td colspan="9" class="text-center text-muted py-4">Data tidak ada.</td></tr>
                <?php else: ?>
                    <?php foreach ($pos as $po): ?>
                        <?php 
                            $currentLocId = (int)($po['location_id'] ?? 0);
                            $currentLocLabel = $locations[$currentLocId] ?? 'Pilih Lokasi';
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($po['tanggal']) ?></td>
                            <td><?= htmlspecialchars($po['no_po']) ?></td>
                            <td><?= htmlspecialchars($po['nama_supplier'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($po['status'] ?? '-') ?></td>
                            
                            <!-- Kolom 1: Metode Pembayaran -->
                            <td>
                                <div class="dropdown d-inline-block">
                                    <button class="btn btn-sm dropdown-toggle <?= $po['payment_method'] === 'cash' ? 'btn-success' : ($po['payment_method'] === 'saldo' ? 'btn-primary' : 'btn-outline-secondary') ?>" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                        <?= $po['payment_method'] ? strtoupper($po['payment_method']) : 'Pilih Metode' ?>
                                    </button>
                                    <ul class="dropdown-menu shadow-sm">
                                        <li>
                                            <form method="POST">
                                                <input type="hidden" name="po_id" value="<?= (int)$po['id'] ?>">
                                                <button type="submit" name="payment_method" value="cash" class="dropdown-item <?= $po['payment_method'] === 'cash' ? 'active' : '' ?>">Cash</button>
                                            </form>
                                        </li>
                                        <li>
                                            <form method="POST">
                                                <input type="hidden" name="po_id" value="<?= (int)$po['id'] ?>">
                                                <button type="submit" name="payment_method" value="saldo" class="dropdown-item <?= $po['payment_method'] === 'saldo' ? 'active' : '' ?>">Saldo</button>
                                            </form>
                                        </li>
                                    </ul>
                                </div>
                            </td>

                            <!-- Kolom 2: Lokasi Simpan (Dilengkapi Scroll internal & Max-Height) -->
                            <td>
                                <div class="dropdown d-inline-block">
                                    <button class="btn btn-sm dropdown-toggle <?= $currentLocId > 0 ? 'btn-info text-white' : 'btn-outline-secondary' ?>" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                        <?= htmlspecialchars($currentLocLabel) ?>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-scrollable shadow-sm">
                                        <?php if (!empty($locations)): ?>
                                            <?php foreach ($locations as $locId => $locName): ?>
                                                <li>
                                                    <form method="POST" class="m-0">
                                                        <input type="hidden" name="po_id" value="<?= (int)$po['id'] ?>">
                                                        <input type="hidden" name="location_id" value="<?= (int)$locId ?>">
                                                        <button type="submit" class="dropdown-item <?= $currentLocId === (int)$locId ? 'active' : '' ?>">
                                                            <?= htmlspecialchars($locName) ?>
                                                        </button>
                                                    </form>
                                                </li>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <li><span class="dropdown-item disabled">Tidak ada lokasi</span></li>
                                        <?php endif; ?>
                                    </ul>
                                </div>
                            </td>

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