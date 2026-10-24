<?php
require_once __DIR__ . '/_init.php';
kasir_require_login();

$saleId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($saleId <= 0) {
    header('Location: ' . kasir_url_for('dashboard.php'));
    exit();
}

$posConn = pos_db_conn();
$sale = null;
$items = [];

if ($posConn instanceof mysqli && !$posConn->connect_error) {
    $stmt = $posConn->prepare("SELECT * FROM pos_sales WHERE id = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('i', $saleId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result && $result->num_rows > 0) {
            $sale = $result->fetch_assoc();
        }
        $stmt->close();
    }

    if ($sale) {
        $stmtItems = $posConn->prepare("SELECT * FROM pos_sale_items WHERE sale_id = ? ORDER BY id ASC");
        if ($stmtItems) {
            $stmtItems->bind_param('i', $saleId);
            $stmtItems->execute();
            $resultItems = $stmtItems->get_result();
            if ($resultItems) {
                while ($row = $resultItems->fetch_assoc()) {
                    $items[] = $row;
                }
            }
            $stmtItems->close();
        }
    }
}

if (!$sale) {
    header('Location: ' . kasir_url_for('dashboard.php'));
    exit();
}

function format_rupiah($nilai) {
    return 'Rp ' . number_format((float)$nilai, 0, ',', '.');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Transaksi POS - MINVEN</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        body { background: #f8fafc; }
        .page-wrap { max-width: 900px; margin: 0 auto; padding: 28px 16px 48px; }
        .card-soft { border: 0; border-radius: 20px; box-shadow: 0 12px 35px rgba(15,23,42,.08); }
        .summary-box { background: #eff6ff; border-radius: 18px; padding: 18px; }
    </style>
</head>
<body>
    <div class="page-wrap">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <div class="text-muted small">Kasir POS</div>
                <h1 class="h3 mb-1">Detail Transaksi</h1>
                <div class="text-muted">Nomor: <?= htmlspecialchars($sale['sale_number']) ?></div>
            </div>
            <div class="d-flex gap-2">
                <a class="btn btn-outline-secondary" href="<?= htmlspecialchars(kasir_url_for('dashboard.php')) ?>">Dashboard</a>
                <a class="btn btn-outline-dark" href="<?= htmlspecialchars(kasir_url_for('create.php')) ?>">Transaksi Baru</a>
            </div>
        </div>

        <div class="card card-soft mb-4">
            <div class="card-body">
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <div class="text-muted small mb-1">Tanggal Transaksi</div>
                        <div class="fw-semibold"><?= htmlspecialchars(date('d/m/Y H:i', strtotime($sale['sale_date']))) ?></div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted small mb-1">Kasir</div>
                        <div class="fw-semibold"><?= htmlspecialchars($sale['cashier_username']) ?></div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted small mb-1">Nama Pelanggan</div>
                        <div class="fw-semibold"><?= htmlspecialchars($sale['customer_name'] ?: '-') ?></div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted small mb-1">Metode Pembayaran</div>
                        <div class="fw-semibold"><?= htmlspecialchars(strtoupper($sale['payment_method'])) ?></div>
                    </div>
                </div>

                <hr class="my-4">

                <h5 class="fw-bold mb-3">Item Penjualan</h5>
                <div class="table-responsive mb-4">
                    <table class="table align-middle">
                        <thead class="table-light text-uppercase fs-7 fw-bold text-muted">
                            <tr>
                                <th>Produk</th>
                                <th class="text-end">Qty</th>
                                <th class="text-end">Harga Satuan</th>
                                <th class="text-end">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $item): ?>
                            <tr>
                                <td class="fw-semibold"><?= htmlspecialchars($item['nama_product']) ?></td>
                                <td class="text-end"><?= (int)$item['qty'] ?></td>
                                <td class="text-end"><?= htmlspecialchars(format_rupiah($item['unit_price'])) ?></td>
                                <td class="text-end"><?= htmlspecialchars(format_rupiah($item['line_total'])) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="summary-box">
                    <div class="d-flex justify-content-between mb-2">
                        <span>Subtotal</span>
                        <strong><?= htmlspecialchars(format_rupiah($sale['subtotal'])) ?></strong>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Diskon</span>
                        <strong><?= htmlspecialchars(format_rupiah($sale['discount_amount'])) ?></strong>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Pajak</span>
                        <strong><?= htmlspecialchars(format_rupiah($sale['tax_amount'])) ?></strong>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="fw-semibold">Grand Total</span>
                        <strong class="fs-4"><?= htmlspecialchars(format_rupiah($sale['grand_total'])) ?></strong>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Dibayar</span>
                        <strong><?= htmlspecialchars(format_rupiah($sale['amount_paid'])) ?></strong>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span>Kembalian</span>
                        <strong><?= htmlspecialchars(format_rupiah($sale['change_amount'])) ?></strong>
                    </div>
                </div>

                <?php if (trim((string)($sale['notes'] ?? '')) !== ''): ?>
                <div class="alert alert-secondary mt-3 mb-0">
                    <div class="text-muted small mb-1">Catatan</div>
                    <div><?= htmlspecialchars(trim((string)$sale['notes'])) ?></div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>