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
    if (!checkAccess('backoffice', 'view') || !checkAccess('backoffice_reports_inventory_price', 'view')) {
        $jsonOut(['success' => false, 'message' => 'Forbidden'], 403);
    }
} else {
    bo_require_login();
    if (!checkAccess('backoffice_reports_inventory_price', 'view')) {
        header('Location: ' . url_for('unauthorized.php'));
        exit();
    }
}

$qText = trim((string)($_GET['q'] ?? ''));
$start = (string)($_GET['start'] ?? '');
$end = (string)($_GET['end'] ?? '');

if ($start === '' || $end === '') {
    $start = date('Y-m-01');
    $end = date('Y-m-t');
}

$rows = [];
$totalQty = 0;
$totalValue = 0.0;
$totalValuePo = 0.0;
$totalHargaBeliSatuan = 0.0;
$totalHargaPoSatuan = 0.0;
$totalKeluar = 0;

if ($boMainConn) {
    $types = 'ss';
    $params = [$start, $end];
    $where = "1=1";

    if ($qText !== '') {
        $where .= " AND (b.kode_barang LIKE ? OR b.nama_barang LIKE ?)";
        $types .= 'ss';
        $like = '%' . $qText . '%';
        $params[] = $like;
        $params[] = $like;
    }

    $sql = "SELECT b.kode_barang, b.nama_barang, b.harga_beli, b.harga_po,
                   COALESCE(SUM(gs.stok_awal - gs.stok_terpakai), 0) AS stok_sisa,
                   COALESCE(m.qty_keluar, 0) AS qty_keluar,
                   COALESCE(m.qty_masuk, 0) AS qty_masuk
            FROM barang b
            LEFT JOIN gudang_stok gs ON gs.barang_id = b.id
            LEFT JOIN (
                SELECT h.barang_id,
                       SUM(CASE WHEN h.jenis_perubahan IN ('keluar','transfer_out') THEN h.jumlah_perubahan ELSE 0 END) AS qty_keluar,
                       SUM(CASE WHEN h.jenis_perubahan IN ('masuk','transfer_in') THEN h.jumlah_perubahan ELSE 0 END) AS qty_masuk
                FROM gudang_stok_history h
                WHERE DATE(h.created_at) BETWEEN ? AND ?
                GROUP BY h.barang_id
            ) m ON m.barang_id = b.id
            WHERE $where
            GROUP BY b.id
            ORDER BY b.nama_barang ASC";

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
            $rows[] = $r;
            $qty = (int)($r['stok_sisa'] ?? 0);
            $totalQty += $qty;
            $hb = (float)($r['harga_beli'] ?? 0);
            $hp = (float)($r['harga_po'] ?? 0);
            $totalValue += $qty * $hb;
            $totalValuePo += $qty * $hp;
            $totalHargaBeliSatuan += $hb;
            $totalHargaPoSatuan += $hp;
            $totalKeluar += (int)($r['qty_keluar'] ?? 0);
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
                'q' => $qText,
            ],
            'items' => $rows,
            'totals' => [
                'total_qty' => $totalQty,
                'total_keluar' => $totalKeluar,
                'total_nilai_hpp' => $totalValue,
                'total_nilai_po' => $totalValuePo,
                'total_harga_beli_satuan' => $totalHargaBeliSatuan,
                'total_harga_po_satuan' => $totalHargaPoSatuan,
            ],
        ],
    ]);
}

$export = (string)($_GET['export'] ?? '');
if ($export === 'xlsx') {
    $sheet = [];
    $sheet[] = ['Inventory - Item Price'];
    $sheet[] = ['Periode', $start, $end];
    $sheet[] = ['Cari', $qText !== '' ? $qText : '-'];
    $sheet[] = [];
    $sheet[] = ['Kode', 'Nama Item', 'Stok Sisa', 'Keluar (periode)', 'Harga Beli', 'Harga PO', 'Nilai (Qty x Harga Beli)', 'Nilai (Qty x Harga PO)'];
    foreach ($rows as $r) {
        $qty = (int)($r['stok_sisa'] ?? 0);
        $keluar = (int)($r['qty_keluar'] ?? 0);
        $hb = (float)($r['harga_beli'] ?? 0);
        $hp = (float)($r['harga_po'] ?? 0);
        $sheet[] = [
            (string)($r['kode_barang'] ?? ''),
            (string)($r['nama_barang'] ?? ''),
            $qty,
            $keluar,
            $hb,
            $hp,
            $qty * $hb,
            $qty * $hp,
        ];
    }
    $sheet[] = [];
    $sheet[] = ['TOTAL', '', (int)$totalQty, (int)$totalKeluar, (float)$totalHargaBeliSatuan, (float)$totalHargaPoSatuan, (float)$totalValue, (float)$totalValuePo];
    bo_export_xlsx_download(bo_export_filename('Inventory_Item_Price', 'xlsx'), 'Inventory - Item Price', $sheet);
}
if ($export === 'pdf') {
    $sub = [
        'Periode: ' . $start . ' s/d ' . $end,
        'Cari: ' . ($qText !== '' ? $qText : '-'),
        'Total Qty: ' . number_format($totalQty) . ' • Total Nilai (HPP): Rp ' . number_format($totalValue, 0, ',', '.') . ' • Total Nilai (PO): Rp ' . number_format($totalValuePo, 0, ',', '.'),
    ];
    $cols = [
        ['key' => 'kode_barang', 'label' => 'Kode', 'w' => 25, 'align' => 'L'],
        ['key' => 'nama_barang', 'label' => 'Nama Item', 'w' => 95, 'align' => 'L'],
        ['key' => 'stok_sisa', 'label' => 'Stok', 'w' => 20, 'align' => 'R'],
        ['key' => 'qty_keluar', 'label' => 'Keluar', 'w' => 22, 'align' => 'R'],
        ['key' => 'harga_beli', 'label' => 'Harga Beli', 'w' => 35, 'align' => 'R'],
        ['key' => 'harga_po', 'label' => 'Harga PO', 'w' => 35, 'align' => 'R'],
        ['key' => 'nilai_hpp', 'label' => 'Nilai HPP', 'w' => 35, 'align' => 'R'],
        ['key' => 'nilai_po', 'label' => 'Nilai PO', 'w' => 35, 'align' => 'R'],
    ];
    $pdf = bo_export_pdf_begin('L', 'Inventory - Item Price', $sub, $cols);
    $out = [];
    foreach ($rows as $r) {
        $qty = (int)($r['stok_sisa'] ?? 0);
        $hb = (float)($r['harga_beli'] ?? 0);
        $hp = (float)($r['harga_po'] ?? 0);
        $out[] = [
            'kode_barang' => (string)($r['kode_barang'] ?? ''),
            'nama_barang' => (string)($r['nama_barang'] ?? ''),
            'stok_sisa' => number_format($qty),
            'qty_keluar' => number_format((int)($r['qty_keluar'] ?? 0)),
            'harga_beli' => 'Rp ' . number_format($hb, 0, ',', '.'),
            'harga_po' => 'Rp ' . number_format($hp, 0, ',', '.'),
            'nilai_hpp' => 'Rp ' . number_format($qty * $hb, 0, ',', '.'),
            'nilai_po' => 'Rp ' . number_format($qty * $hp, 0, ',', '.'),
        ];
    }
    bo_pdf_draw_rows($pdf, $cols, $out);
    bo_export_pdf_download($pdf, bo_export_filename('Inventory_Item_Price', 'pdf'));
}
$headerActions = '<a class="btn btn-outline-secondary" href="' . htmlspecialchars(bo_url_for('dashboard.php')) . '"><i class="bi bi-grid-1x2 me-1"></i>Dashboard</a>';
bo_render_shell_start([
    'title' => 'Inventory Price - Backoffice',
    'page_title' => 'Inventory Price',
    'page_subtitle' => 'Pantau nilai stok per item dengan layout sidebar yang konsisten.',
    'active' => 'inventory-price',
    'header_actions' => $headerActions,
]);
?>
<div class="bo-card p-4 mb-4">
    <div class="bo-card-header">
        <div>
            <h3 class="bo-card-title">Filter Inventory Price</h3>
            <div class="bo-card-subtitle">Gunakan periode dan pencarian item untuk melihat nilai stok per item.</div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a class="btn btn-outline-secondary" href="<?= htmlspecialchars(bo_url_for('reports/inventory.php')) ?>"><i class="bi bi-box-seam me-1"></i>Inventory</a>
            <a class="btn btn-outline-secondary" href="<?= htmlspecialchars(bo_url_for('reports/item_movement.php')) ?>"><i class="bi bi-graph-up-arrow me-1"></i>Item Movement</a>
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
        <div class="col-md-6">
            <label class="form-label">Cari Item (kode/nama)</label>
            <input type="text" name="q" class="form-control" value="<?= htmlspecialchars($qText) ?>" placeholder="contoh: BB- / Gula / Susu">
        </div>
        <div class="col-md-3 d-grid">
            <button class="btn btn-primary" type="submit"><i class="bi bi-funnel me-1"></i>Terapkan</button>
        </div>
        <div class="col-md-3 d-grid">
            <a class="btn btn-success" href="<?= htmlspecialchars(bo_url_for('reports/inventory_price.php?' . http_build_query(array_merge($_GET, ['export' => 'xlsx'])))) ?>"><i class="bi bi-file-earmark-excel me-1"></i>Export XLSX</a>
        </div>
        <div class="col-md-3 d-grid">
            <a class="btn btn-danger" href="<?= htmlspecialchars(bo_url_for('reports/inventory_price.php?' . http_build_query(array_merge($_GET, ['export' => 'pdf'])))) ?>"><i class="bi bi-file-earmark-pdf me-1"></i>Export PDF</a>
        </div>
        <div class="col-md-3 text-md-end align-self-end">
            <div class="fw-bold">Total Qty: <?= number_format($totalQty) ?></div>
            <div class="text-muted small">Total Nilai (HPP): Rp <?= number_format($totalValue, 0, ',', '.') ?></div>
            <div class="text-muted small">Total Nilai (PO): Rp <?= number_format($totalValuePo, 0, ',', '.') ?></div>
            <div class="text-muted small">Total Harga Satuan (Beli/PO): Rp <?= number_format($totalHargaBeliSatuan, 0, ',', '.') ?> / Rp <?= number_format($totalHargaPoSatuan, 0, ',', '.') ?></div>
            <div class="text-muted small">Total Keluar (periode): <?= number_format($totalKeluar) ?></div>
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
                    <th class="text-end">Stok Sisa</th>
                    <th class="text-end">Keluar (periode)</th>
                    <th class="text-end">Harga Beli</th>
                    <th class="text-end">Harga PO</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rows)): ?>
                    <tr><td colspan="6" class="text-center text-muted py-4">Data tidak ada.</td></tr>
                <?php else: ?>
                    <?php foreach ($rows as $r): ?>
                        <?php
                            $qty = (int)($r['stok_sisa'] ?? 0);
                            $keluar = (int)($r['qty_keluar'] ?? 0);
                            $hb = (float)($r['harga_beli'] ?? 0);
                            $hj = (float)($r['harga_po'] ?? 0);
                        ?>
                        <tr>
                            <td><?= htmlspecialchars((string)$r['kode_barang']) ?></td>
                            <td><?= htmlspecialchars((string)$r['nama_barang']) ?></td>
                            <td class="text-end"><?= number_format($qty) ?></td>
                            <td class="text-end"><?= number_format($keluar) ?></td>
                            <td class="text-end">Rp <?= number_format($hb, 0, ',', '.') ?></td>
                            <td class="text-end">Rp <?= number_format($hj, 0, ',', '.') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr>
                        <td colspan="4" class="text-end fw-bold">Total Nilai (Qty x Harga)</td>
                        <td class="text-end fw-bold">Rp <?= number_format($totalValue, 0, ',', '.') ?></td>
                        <td class="text-end fw-bold">Rp <?= number_format($totalValuePo, 0, ',', '.') ?></td>
                    </tr>
                    <tr>
                        <td colspan="4" class="text-end fw-bold">Total Harga Satuan</td>
                        <td class="text-end fw-bold">Rp <?= number_format($totalHargaBeliSatuan, 0, ',', '.') ?></td>
                        <td class="text-end fw-bold">Rp <?= number_format($totalHargaPoSatuan, 0, ',', '.') ?></td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php bo_render_shell_end(); ?>
