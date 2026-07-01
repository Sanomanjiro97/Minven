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

$poId = (int)($_GET['id'] ?? 0);
if ($poId <= 0) {
    if ($wantsJson) {
        $jsonOut(['success' => false, 'message' => 'id wajib'], 400);
    }
    header('Location: ' . bo_url_for('reports/po.php'));
    exit();
}

$header = null;
$items = [];
$total = 0.0;
$totalItem = 0;

if ($boMainConn) {
    $detailStatusExists = function_exists('db_has_column') ? db_has_column($boMainConn, 'detail_purchase_order', 'status') : true;

    $stmt = $boMainConn->prepare("
        SELECT po.id, po.no_po, po.tanggal, po.total_item, po.total_harga, po.status, po.keterangan,
               s.nama_supplier, s.kode_supplier, s.telepon, s.email
        FROM purchase_order po
        LEFT JOIN supplier s ON s.id = po.supplier_id
        WHERE po.id = ? AND po.status != 'menunggu'
        LIMIT 1
    ");
    if ($stmt) {
        $stmt->bind_param('i', $poId);
        $stmt->execute();
        $header = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }

    $isRejectedPo = strtolower(trim((string)($header['status'] ?? ''))) === 'rejected';

    $sqlItems = "
        SELECT d.jumlah, d.harga_satuan, d.total_harga, d.keterangan_detail,
               b.kode_barang, b.nama_barang
        FROM detail_purchase_order d
        LEFT JOIN barang b ON b.id = d.barang_id
        WHERE d.purchase_order_id = ?
    ";
    if ($detailStatusExists && !$isRejectedPo) {
        $sqlItems .= " AND (d.status IS NULL OR d.status != 'rejected')";
    }
    $sqlItems .= " ORDER BY b.nama_barang ASC, d.id ASC";

    $stmt = $boMainConn->prepare($sqlItems);
    if ($stmt) {
        $stmt->bind_param('i', $poId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($res && ($r = $res->fetch_assoc())) {
            $items[] = $r;
            $total += (float)($r['total_harga'] ?? ((float)$r['harga_satuan'] * (int)$r['jumlah']));
            $totalItem += (int)($r['jumlah'] ?? 0);
        }
        $stmt->close();
    }
}

if (!$header) {
    if ($wantsJson) {
        $jsonOut(['success' => false, 'message' => 'PO tidak ditemukan'], 404);
    }
    header('Location: ' . bo_url_for('reports/po.php'));
    exit();
}

$header['total_harga'] = $total;
$header['total_item'] = $totalItem;

if ($wantsJson) {
    $jsonOut([
        'success' => true,
        'data' => [
            'header' => $header,
            'items' => $items,
        ],
    ]);
}
?>
<?php
$headerActions = '<a class="btn btn-outline-secondary" href="' . htmlspecialchars(bo_url_for('reports/po.php')) . '"><i class="bi bi-arrow-left me-1"></i>Kembali</a>';
bo_render_shell_start([
    'title' => 'Detail PO - Backoffice',
    'page_title' => 'Detail PO',
    'page_subtitle' => 'Lihat informasi header dan item purchase order dalam tampilan yang sama dengan halaman laporan.',
    'active' => 'po-detail',
    'header_actions' => $headerActions,
]);
?>
<div class="bo-card p-4 mb-4">
            <div class="row g-2">
                <div class="col-md-3">
                    <div class="text-muted small">No PO</div>
                    <div class="fw-bold"><?= htmlspecialchars($header['no_po']) ?></div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">Tanggal</div>
                    <div class="fw-bold"><?= htmlspecialchars($header['tanggal']) ?></div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">Supplier</div>
                    <div class="fw-bold"><?= htmlspecialchars($header['nama_supplier'] ?? '-') ?></div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">Status</div>
                    <div class="fw-bold"><?= htmlspecialchars($header['status'] ?? '-') ?></div>
                </div>
                <div class="col-md-6">
                    <div class="text-muted small">Kontak Supplier</div>
                    <div class="fw-bold">
                        <?= htmlspecialchars(trim((string)($header['telepon'] ?? ''))) !== '' ? htmlspecialchars($header['telepon']) : '-' ?>
                        <?php if (trim((string)($header['email'] ?? '')) !== ''): ?>
                            <span class="text-muted fw-normal">•</span> <?= htmlspecialchars($header['email']) ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-md-6 text-md-end">
                    <div class="text-muted small">Total PO</div>
                    <div class="fw-bold">Rp <?= number_format((float)($header['total_harga'] ?? $total), 0, ',', '.') ?></div>
                </div>
                <?php if (trim((string)($header['keterangan'] ?? '')) !== ''): ?>
                    <div class="col-12">
                        <div class="text-muted small">Keterangan</div>
                        <div class="fw-bold"><?= nl2br(htmlspecialchars((string)$header['keterangan'])) ?></div>
                    </div>
                <?php endif; ?>
            </div>
</div>

<div class="bo-card p-4">
    <div class="bo-card-header">
        <div>
            <h3 class="bo-card-title">Detail Item</h3>
            <div class="bo-card-subtitle">Daftar item dan keterangan yang termasuk dalam purchase order ini.</div>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Kode</th>
                    <th>Nama Item</th>
                    <th>Keterangan</th>
                    <th class="text-end">Qty</th>
                    <th class="text-end">Harga</th>
                    <th class="text-end">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($items)): ?>
                    <tr><td colspan="6" class="text-center text-muted py-4">Detail PO kosong.</td></tr>
                <?php else: ?>
                    <?php foreach ($items as $it): ?>
                        <?php
                            $qty = (int)($it['jumlah'] ?? 0);
                            $harga = (float)($it['harga_satuan'] ?? 0);
                            $sub = (float)($it['total_harga'] ?? ($qty * $harga));
                            $namaItem = trim((string)($it['nama_barang'] ?? ''));
                            if ($namaItem === '') $namaItem = trim((string)($it['keterangan_detail'] ?? ''));
                        ?>
                        <tr>
                            <td><?= htmlspecialchars((string)($it['kode_barang'] ?? '-')) ?></td>
                            <td><?= htmlspecialchars($namaItem !== '' ? $namaItem : '-') ?></td>
                            <td><?= htmlspecialchars((string)($it['keterangan_detail'] ?? '')) ?></td>
                            <td class="text-end"><?= number_format($qty) ?></td>
                            <td class="text-end">Rp <?= number_format($harga, 0, ',', '.') ?></td>
                            <td class="text-end">Rp <?= number_format($sub, 0, ',', '.') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr>
                        <td colspan="5" class="text-end fw-bold">Total</td>
                        <td class="text-end fw-bold">Rp <?= number_format((float)($header['total_harga'] ?? $total), 0, ',', '.') ?></td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php bo_render_shell_end(); ?>
