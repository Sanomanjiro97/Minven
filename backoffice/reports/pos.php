<?php
require_once __DIR__ . '/../_init.php';
require_once __DIR__ . '/../../includes/pos_bootstrap.php';

bo_require_login();

if (!function_exists('bo_pos_currency')) {
    function bo_pos_currency($amount) {
        return 'Rp ' . number_format((float)$amount, 0, ',', '.');
    }
}

$posConn = pos_bootstrap();
$start = (string)($_GET['start'] ?? date('Y-m-01'));
$end = (string)($_GET['end'] ?? date('Y-m-t'));

$summary = [
    'total_sales' => 0.0,
    'total_transactions' => 0,
    'total_items' => 0,
];
$rows = [];

if ($posConn instanceof mysqli && !$posConn->connect_error) {
    $stmt = $posConn->prepare("SELECT
            COALESCE(SUM(grand_total), 0) AS total_sales,
            COUNT(*) AS total_transactions,
            COALESCE(SUM((SELECT COALESCE(SUM(qty),0) FROM pos_sale_items psi WHERE psi.sale_id = ps.id)), 0) AS total_items
        FROM pos_sales ps
        WHERE DATE(sale_date) BETWEEN ? AND ?");
    if ($stmt) {
        $stmt->bind_param('ss', $start, $end);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        if ($row) {
            $summary['total_sales'] = (float)($row['total_sales'] ?? 0);
            $summary['total_transactions'] = (int)($row['total_transactions'] ?? 0);
            $summary['total_items'] = (int)($row['total_items'] ?? 0);
        }
        $stmt->close();
    }

    $stmt = $posConn->prepare("SELECT sale_number, sale_date, cashier_username, customer_name, payment_method, payment_status, grand_total
        FROM pos_sales
        WHERE DATE(sale_date) BETWEEN ? AND ?
        ORDER BY sale_date DESC, id DESC
        LIMIT 100");
    if ($stmt) {
        $stmt->bind_param('ss', $start, $end);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($res && ($row = $res->fetch_assoc())) {
            $rows[] = $row;
        }
        $stmt->close();
    }
}

bo_render_shell_start([
    'title' => 'Backoffice - Laporan POS',
    'page_title' => 'Laporan POS',
    'page_subtitle' => 'Monitoring transaksi dari aplikasi kasir terpisah',
    'active' => 'reports-pos',
]);
?>
<div class="container-fluid px-0">
    <div class="card shadow-sm border-0 rounded-4 mb-4">
        <div class="card-body">
            <form class="row g-3" method="get">
                <div class="col-md-4">
                    <label class="form-label">Mulai</label>
                    <input class="form-control" type="date" name="start" value="<?= htmlspecialchars($start) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Sampai</label>
                    <input class="form-control" type="date" name="end" value="<?= htmlspecialchars($end) ?>">
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <button class="btn btn-primary w-100" type="submit">Terapkan Filter</button>
                </div>
            </form>
        </div>
    </div>

    <?php if (!$posConn instanceof mysqli || $posConn->connect_error): ?>
        <div class="alert alert-warning">Database POS belum tersedia. Buka aplikasi `kasir` sekali untuk inisialisasi otomatis.</div>
    <?php endif; ?>

    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="card shadow-sm border-0 rounded-4 h-100">
                <div class="card-body">
                    <div class="text-muted small mb-2">Omzet POS</div>
                    <div class="fs-3 fw-bold"><?= htmlspecialchars(bo_pos_currency($summary['total_sales'])) ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-sm border-0 rounded-4 h-100">
                <div class="card-body">
                    <div class="text-muted small mb-2">Jumlah Transaksi</div>
                    <div class="fs-3 fw-bold"><?= (int)$summary['total_transactions'] ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-sm border-0 rounded-4 h-100">
                <div class="card-body">
                    <div class="text-muted small mb-2">Item Terjual</div>
                    <div class="fs-3 fw-bold"><?= (int)$summary['total_items'] ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0 rounded-4">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="h5 mb-0">Transaksi POS</h2>
                <span class="text-muted small"><?= count($rows) ?> data</span>
            </div>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>No. Transaksi</th>
                            <th>Tanggal</th>
                            <th>Kasir</th>
                            <th>Pelanggan</th>
                            <th>Pembayaran</th>
                            <th>Status</th>
                            <th class="text-end">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$rows): ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">Belum ada transaksi POS pada periode ini.</td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach ($rows as $row): ?>
                            <tr>
                                <td><?= htmlspecialchars((string)$row['sale_number']) ?></td>
                                <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime((string)$row['sale_date']))) ?></td>
                                <td><?= htmlspecialchars((string)$row['cashier_username']) ?></td>
                                <td><?= htmlspecialchars((string)($row['customer_name'] ?: '-')) ?></td>
                                <td><?= htmlspecialchars(strtoupper((string)$row['payment_method'])) ?></td>
                                <td><?= htmlspecialchars(strtoupper((string)$row['payment_status'])) ?></td>
                                <td class="text-end"><?= htmlspecialchars(bo_pos_currency((float)$row['grand_total'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php bo_render_shell_end(); ?>
