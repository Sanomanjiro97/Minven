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

// Handle payment method update
$successMessage = '';
$errorMessage = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['payment_method'])) {
    $paymentMethod = $_POST['payment_method'];
    if ($paymentMethod === 'cash' || $paymentMethod === 'saldo') {
        if ($boMainConn) {
            $stmt = $boMainConn->prepare("UPDATE purchase_order SET payment_method = ? WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param('si', $paymentMethod, $poId);
                if ($stmt->execute()) {
                    $successMessage = 'Metode pembayaran berhasil diperbarui!';
                } else {
                    $errorMessage = 'Gagal memperbarui metode pembayaran: ' . $stmt->error;
                }
                $stmt->close();
            }
        }
    }
}

$header = null;
$items = [];
$total = 0.0;
$totalItem = 0;

if ($boMainConn) {
    $detailStatusExists = function_exists('db_has_column') ? db_has_column($boMainConn, 'detail_purchase_order', 'status') : true;

    $stmt = $boMainConn->prepare("
        SELECT po.id, po.no_po, po.tanggal, po.total_item, po.total_harga, po.status, po.keterangan,
               po.payment_method,
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

    // Query Detail PO diselaraskan dengan view.php (join Satuan, Konversi, & Gambar)
    $sqlItems = "
        SELECT d.id, d.jumlah, d.harga_satuan, d.total_harga, d.keterangan_detail, d.status,
               b.kode_barang, b.nama_barang, b.gambar,
               COALESCE(s_konv.nama_satuan, s.nama_satuan) as nama_satuan
        FROM detail_purchase_order d
        LEFT JOIN barang b ON b.id = d.barang_id
        LEFT JOIN satuan s ON b.satuan_id = s.id
        LEFT JOIN conversi_po_detail cpd ON d.id = cpd.detail_purchase_order_id
        LEFT JOIN satuan s_konv ON cpd.satuan_asal_id = s_konv.id
        WHERE d.purchase_order_id = ?
    ";
    if ($detailStatusExists && !$isRejectedPo) {
        $sqlItems .= " AND (d.status IS NULL OR d.status != 'rejected')";
    }
    $sqlItems .= " ORDER BY b.kode_barang ASC, d.id ASC";

    $stmt = $boMainConn->prepare($sqlItems);
    if ($stmt) {
        $stmt->bind_param('i', $poId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($res && ($r = $res->fetch_assoc())) {
            $qty = (int)($r['jumlah'] ?? 0);
            $hargaSatuan = (float)($r['harga_satuan'] ?? 0);
            
            // Prioritaskan kalkulasi manual jika database total_harga kosong/0
            $totalHarga = (float)($r['total_harga'] ?? 0);
            if ($totalHarga <= 0) {
                $totalHarga = $qty * $hargaSatuan;
            }
            if ($hargaSatuan <= 0 && $totalHarga > 0) {
                $hargaSatuan = $totalHarga / $qty;
            }

            $r['harga_satuan'] = $hargaSatuan;
            $r['total_harga'] = $totalHarga;

            $items[] = $r;
            
            if (($r['status'] ?? '') !== 'rejected') {
                $total += $totalHarga;
                $totalItem += $qty;
            }
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
            <?php if ($successMessage): ?>
                <div class="alert alert-success mb-4"><?= htmlspecialchars($successMessage) ?></div>
            <?php endif; ?>
            <?php if ($errorMessage): ?>
                <div class="alert alert-danger mb-4"><?= htmlspecialchars($errorMessage) ?></div>
            <?php endif; ?>
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
                    <div class="fw-bold text-primary fs-5">Rp <?= number_format((float)($header['total_harga']), 0, ',', '.') ?></div>
                </div>
                <div class="col-12">
                    <form method="POST" class="row g-3 align-items-end">
                        <div class="col-md-6">
                            <label class="text-muted small">Metode Pembayaran</label>
                            <select name="payment_method" class="form-select" required>
                                <option value="cash" <?= $header['payment_method'] === 'cash' ? 'selected' : '' ?>>Cash</option>
                                <option value="saldo" <?= $header['payment_method'] === 'saldo' ? 'selected' : '' ?>>Saldo</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Simpan</button>
                        </div>
                    </form>
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
                    <th width="5%">No</th>
                    <th width="15%">Kode</th>
                    <th width="20%">Nama Item</th>
                    <th width="10%">Foto</th>
                    <th width="10%" class="text-end">Qty</th>
                    <th width="10%">Satuan</th>
                    <th width="15%" class="text-end">Harga</th>
                    <th width="15%" class="text-end">Total</th>
                    <th width="20%">Keterangan</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($items)): ?>
                    <tr><td colspan="9" class="text-center text-muted py-4">Detail PO kosong.</td></tr>
                <?php else: ?>
                    <?php 
                    $no = 1;
                    foreach ($items as $it): 
                        $qty = (int)($it['jumlah'] ?? 0);
                        $harga = (float)($it['harga_satuan'] ?? 0);
                        $sub = (float)($it['total_harga'] ?? ($qty * $harga));
                        
                        $namaItem = trim((string)($it['nama_barang'] ?? ''));
                        if ($namaItem === '') {
                            $namaItem = trim((string)($it['keterangan_detail'] ?? ''));
                        }

                        $isRejected = ($it['status'] ?? '') === 'rejected';
                    ?>
                        <tr class="<?= $isRejected ? 'table-danger text-decoration-line-through text-muted' : '' ?>">
                            <td><?= $no++ ?></td>
                            <td><?= htmlspecialchars((string)($it['kode_barang'] ?? '-')) ?></td>
                            <td><?= htmlspecialchars($namaItem !== '' ? $namaItem : '-') ?></td>
                            <td>
                                <?php
                                    $gambarFile = !empty($it['gambar']) ? basename($it['gambar']) : '';
                                    $gambarFsPath = $gambarFile ? (__DIR__ . '/../uploads/barang/' . $gambarFile) : '';
                                    $gambarUrl = $gambarFile ? ('../uploads/barang/' . rawurlencode($gambarFile)) : '';
                                ?>
                                <?php if ($gambarFile && file_exists($gambarFsPath)): ?>
                                    <a href="<?= htmlspecialchars($gambarUrl) ?>" target="_blank" rel="noopener">
                                        <img src="<?= htmlspecialchars($gambarUrl) ?>" alt="Foto" style="width:40px; height:40px; object-fit:cover; border-radius:6px; border:1px solid #e9ecef;">
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end"><?= number_format($qty) ?></td>
                            <td><?= htmlspecialchars($it['nama_satuan'] ?? '-') ?></td>
                            <td class="text-end">Rp <?= number_format($harga, 0, ',', '.') ?></td>
                            <td class="text-end">Rp <?= number_format($sub, 0, ',', '.') ?></td>
                            <td><?= htmlspecialchars((string)($it['keterangan_detail'] ?? '')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr>
                        <td colspan="7" class="text-end fw-bold text-uppercase">Grand Total (Non-Rejected)</td>
                        <td class="text-end fw-bold text-primary">Rp <?= number_format((float)$header['total_harga'], 0, ',', '.') ?></td>
                        <td></td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php bo_render_shell_end(); ?>