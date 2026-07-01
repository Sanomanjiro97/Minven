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

$directId = (int)($_GET['id'] ?? 0);
if ($directId <= 0) {
    if ($wantsJson) {
        $jsonOut(['success' => false, 'message' => 'id wajib'], 400);
    }
    header('Location: ' . bo_url_for('reports/direct.php'));
    exit();
}

$header = null;
$items = [];
$total = 0.0;

if ($boMainConn) {
    $stmt = $boMainConn->prepare("
        SELECT dp.id, dp.no_transaksi, dp.tanggal, dp.total_item, dp.total_harga, dp.status, dp.keterangan, dp.nama_toko,
               s.nama_supplier, s.kode_supplier, s.telepon, s.email
        FROM direct_purchase dp
        LEFT JOIN supplier s ON s.id = dp.supplier_id
        WHERE dp.id = ?
        LIMIT 1
    ");
    if ($stmt) {
        $stmt->bind_param('i', $directId);
        $stmt->execute();
        $header = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }

    $stmt = $boMainConn->prepare("
        SELECT d.jumlah, d.harga_satuan, (d.jumlah * d.harga_satuan) AS total_harga, d.keterangan AS keterangan_detail,
               b.kode_barang, b.nama_barang
        FROM detail_direct_purchase d
        LEFT JOIN barang b ON b.id = d.barang_id
        WHERE d.direct_purchase_id = ?
        ORDER BY b.nama_barang ASC, d.id ASC
    ");
    if ($stmt) {
        $stmt->bind_param('i', $directId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($res && ($r = $res->fetch_assoc())) {
            $items[] = $r;
            $total += (float)($r['total_harga'] ?? ((float)($r['harga_satuan'] ?? 0) * (int)($r['jumlah'] ?? 0)));
        }
        $stmt->close();
    }
}

if (!$header) {
    if ($wantsJson) {
        $jsonOut(['success' => false, 'message' => 'Direct tidak ditemukan'], 404);
    }
    header('Location: ' . bo_url_for('reports/direct.php'));
    exit();
}

$header['total_harga'] = $total;

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
$headerActions = '<a class="btn btn-outline-secondary" href="' . htmlspecialchars(bo_url_for('reports/direct.php')) . '"><i class="bi bi-arrow-left me-1"></i>Kembali</a>';
bo_render_shell_start([
    'title' => 'Detail Direct - Backoffice',
    'page_title' => 'Detail Direct',
    'page_subtitle' => 'Lihat detail transaksi direct dengan shell yang sama seperti menu laporan lainnya.',
    'active' => 'direct-detail',
    'header_actions' => $headerActions,
]);
?>
<div class="bo-card p-4 mb-4">
            <div class="row g-2">
                <div class="col-md-3">
                    <div class="text-muted small">No Transaksi</div>
                    <div class="fw-bold"><?= htmlspecialchars((string)($header['no_transaksi'] ?? '')) ?></div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">Tanggal</div>
                    <div class="fw-bold"><?= htmlspecialchars((string)($header['tanggal'] ?? '')) ?></div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">Supplier</div>
                    <div class="fw-bold"><?= htmlspecialchars((string)($header['nama_supplier'] ?? '-')) ?></div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">Status</div>
                    <div class="fw-bold"><?= htmlspecialchars((string)($header['status'] ?? '-')) ?></div>
                </div>
                <div class="col-md-6">
                    <div class="text-muted small">Nama Toko</div>
                    <div class="fw-bold"><?= htmlspecialchars((string)($header['nama_toko'] ?? '-')) ?></div>
                </div>
                <div class="col-md-6">
                    <div class="text-muted small">Kontak Supplier</div>
                    <div class="fw-bold">
                        <?= htmlspecialchars(trim((string)($header['telepon'] ?? ''))) !== '' ? htmlspecialchars((string)$header['telepon']) : '-' ?>
                        <?php if (trim((string)($header['email'] ?? '')) !== ''): ?>
                            <span class="text-muted fw-normal">•</span> <?= htmlspecialchars((string)$header['email']) ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="text-muted small">Keterangan</div>
                    <div class="fw-bold"><?= htmlspecialchars((string)($header['keterangan'] ?? '-')) ?></div>
                </div>
                <div class="col-md-6 text-md-end">
                    <div class="text-muted small">Total Direct</div>
                    <div class="fw-bold">Rp <?= number_format((float)($header['total_harga'] ?? $total), 0, ',', '.') ?></div>
                </div>
            </div>
</div>

<div class="bo-card p-4">
    <div class="bo-card-header">
        <div>
            <h3 class="bo-card-title">Detail Item</h3>
            <div class="bo-card-subtitle">Daftar item dan keterangan pada transaksi direct ini.</div>
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
                    <tr><td colspan="6" class="text-center text-muted py-4">Detail direct kosong.</td></tr>
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
