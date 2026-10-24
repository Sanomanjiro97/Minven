<?php
require_once __DIR__ . '/_init.php';
require_once __DIR__ . '/../includes/product_catalog.php';
kasir_require_login();

$posConn = pos_db_conn();
$user = kasir_current_user();
$message = '';
$messageType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    if ($posConn instanceof mysqli && !$posConn->connect_error) {

        // ==========================================
        // 1. ACTION: CREATE SETTLEMENT
        // ==========================================
        if ($action === 'create') {
            $settlementDate = isset($_POST['settlement_date']) ? trim($_POST['settlement_date']) : '';

            if ($settlementDate !== '') {
                $totalSales = 0.0;
                $totalCash = 0.0;
                $totalTransfer = 0.0;
                $totalQris = 0.0;
                $totalDebit = 0.0;
                $totalEwallet = 0.0;
                $totalDiscount = 0.0;
                $totalTax = 0.0;

                // Membaca penjualan murni berdasarkan DATE(sale_date)
                $queryCalc = "SELECT 
                                LOWER(payment_method) AS pm, 
                                SUM(grand_total) AS total, 
                                SUM(discount_amount) AS discount, 
                                SUM(tax_amount) AS tax 
                              FROM pos_sales 
                              WHERE DATE(sale_date) = ?
                              GROUP BY LOWER(payment_method)";

                $stmtSales = $posConn->prepare($queryCalc);
                if ($stmtSales) {
                    $stmtSales->bind_param('s', $settlementDate);
                    $stmtSales->execute();
                    $salesResult = $stmtSales->get_result();

                    if ($salesResult) {
                        while ($row = $salesResult->fetch_assoc()) {
                            $subTotal = (float)$row['total'];
                            $totalSales += $subTotal;
                            $totalDiscount += (float)$row['discount'];
                            $totalTax += (float)$row['tax'];

                            $pm = strtolower(trim($row['pm']));

                            if (strpos($pm, 'cash') !== false || strpos($pm, 'tunai') !== false) {
                                $totalCash += $subTotal;
                            } elseif (strpos($pm, 'transfer') !== false || strpos($pm, 'bank') !== false) {
                                $totalTransfer += $subTotal;
                            } elseif (strpos($pm, 'qris') !== false || strpos($pm, 'qr') !== false) {
                                $totalQris += $subTotal;
                            } elseif (strpos($pm, 'debit') !== false || strpos($pm, 'edc') !== false) {
                                $totalDebit += $subTotal;
                            } elseif (strpos($pm, 'ewallet') !== false) {
                                $totalEwallet += $subTotal;
                            } else {
                                $totalCash += $subTotal; // Default jika jenis pembayaran tak terdefinisi
                            }
                        }
                    }
                    $stmtSales->close();
                }

                $settlementNumber = 'STL-' . date('YmdHis');
                // Gudang ID aman jika dikosongkan (diisi NULL)
                $gudangId = !empty($user['gudang_id']) ? (int)$user['gudang_id'] : null;

                $stmt = $posConn->prepare("INSERT INTO pos_settlements 
                    (settlement_number, settlement_date, gudang_id, total_sales, total_cash, total_transfer, total_qris, total_debit, total_ewallet, total_discount, total_tax, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                
                if ($stmt) {
                    $stmt->bind_param('ssidddddddd', 
                        $settlementNumber, 
                        $settlementDate, 
                        $gudangId, 
                        $totalSales, 
                        $totalCash, 
                        $totalTransfer, 
                        $totalQris, 
                        $totalDebit, 
                        $totalEwallet, 
                        $totalDiscount, 
                        $totalTax
                    );
                    
                    if ($stmt->execute()) {
                        $message = 'Settlement berhasil dibuat dengan total ' . format_rupiah($totalSales);
                        $messageType = 'success';
                    } else {
                        $message = 'Gagal menyimpan settlement: ' . $stmt->error;
                        $messageType = 'danger';
                    }
                    $stmt->close();
                } else {
                    $message = 'Gagal mempersiapkan query: ' . $posConn->error;
                    $messageType = 'danger';
                }
            }
        } 
        
        // ==========================================
        // 2. ACTION: CLOSE & SYNC SETTLEMENT
        // ==========================================
        elseif ($action === 'close') {
            $settlementId = isset($_POST['settlement_id']) ? (int)$_POST['settlement_id'] : 0;
            $notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';

            if ($settlementId > 0) {
                $stmt = $posConn->prepare("SELECT * FROM pos_settlements WHERE id = ? LIMIT 1");
                $stmt->bind_param('i', $settlementId);
                $stmt->execute();
                $settlement = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if ($settlement) {
                    $targetDate = $settlement['settlement_date'];

                    // Update status settlement
                    $stmtUpdateSettlement = $posConn->prepare("UPDATE pos_settlements SET notes = ?, synced_to_omset = '1' WHERE id = ?");
                    if ($stmtUpdateSettlement) {
                        $stmtUpdateSettlement->bind_param('si', $notes, $settlementId);
                        $stmtUpdateSettlement->execute();
                        $stmtUpdateSettlement->close();

                        // Link transaksi pos_sales ke settlement_id ini
                        $stmtLinkSales = $posConn->prepare("UPDATE pos_sales SET settlement_id = ? WHERE DATE(sale_date) = ?");
                        $stmtLinkSales->bind_param('is', $settlementId, $targetDate);
                        $stmtLinkSales->execute();
                        $stmtLinkSales->close();

                        // Sync ke database utama (pendapatan_manual)
                        $mainConn = main_db_conn();
                        if ($mainConn instanceof mysqli && !$mainConn->connect_error) {
                            $stmtSync = $mainConn->prepare("INSERT INTO pendapatan_manual 
                                (tanggal, total_omset_hari, total_cash_sales, total_transfers, total_qr_gopay, total_edc, total_online_payment) 
                                VALUES (?, ?, ?, ?, ?, ?, ?)
                                ON DUPLICATE KEY UPDATE 
                                total_omset_hari = VALUES(total_omset_hari),
                                total_cash_sales = VALUES(total_cash_sales),
                                total_transfers = VALUES(total_transfers),
                                total_qr_gopay = VALUES(total_qr_gopay),
                                total_edc = VALUES(total_edc),
                                total_online_payment = VALUES(total_online_payment),
                                updated_at = NOW()");

                            if ($stmtSync) {
                                $stmtSync->bind_param('sdddddd', 
                                    $targetDate, 
                                    $settlement['total_sales'], 
                                    $settlement['total_cash'], 
                                    $settlement['total_transfer'], 
                                    $settlement['total_qris'], 
                                    $settlement['total_debit'], 
                                    $settlement['total_ewallet']
                                );
                                $stmtSync->execute();
                                $stmtSync->close();
                            }
                        }

                        $message = 'Settlement berhasil ditutup dan disinkronkan ke pendapatan.';
                        $messageType = 'success';
                    }
                }
            }
        }
    }
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
    <title>Settlement Kasir - MINVEN</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        body { background: #f8fafc; }
        .page-wrap { max-width: 1200px; margin: 0 auto; padding: 28px 16px 48px; }
        .card-soft { border: 0; border-radius: 20px; box-shadow: 0 12px 35px rgba(15,23,42,.08); }
    </style>
</head>
<body>
    <div class="page-wrap">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <div class="text-muted small">Kasir POS</div>
                <h1 class="h3 mb-1">Settlement Harian</h1>
            </div>
            <div class="d-flex gap-2">
                <a class="btn btn-outline-secondary" href="<?= htmlspecialchars(kasir_url_for('dashboard.php')) ?>">Dashboard</a>
            </div>
        </div>

        <?php if ($message !== ''): ?>
        <div class="alert alert-<?= $messageType ?> mb-4">
            <div class="fw-semibold">Informasi</div>
            <div><?= htmlspecialchars($message) ?></div>
        </div>
        <?php endif; ?>

        <div class="card card-soft mb-4">
            <div class="card-body">
                <h5 class="fw-bold mb-3">Buat Settlement Baru</h5>
                <form method="post" class="needs-validation">
                    <input type="hidden" name="action" value="create">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label fw-semibold">Tanggal Settlement</label>
                            <input type="date" name="settlement_date" class="form-control" value="<?= htmlspecialchars(date('Y-m-d')) ?>" required>
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-file-earmark-plus"></i> Buat Settlement
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <?php if ($posConn instanceof mysqli && !$posConn->connect_error): ?>
            <div class="card card-soft">
                <div class="card-body">
                    <h5 class="fw-bold mb-3">Riwayat Settlement</h5>
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead class="table-light text-uppercase fs-7 fw-bold text-muted">
                                <tr>
                                    <th>Nomor</th>
                                    <th>Tanggal</th>
                                    <th class="text-end">Total Penjualan</th>
                                    <th class="text-end">Cash</th>
                                    <th class="text-end">Transfer</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $stmtHistory = $posConn->prepare("SELECT * FROM pos_settlements ORDER BY id DESC LIMIT 20");
                                if ($stmtHistory) {
                                    $stmtHistory->execute();
                                    $historyResult = $stmtHistory->get_result();
                                    if ($historyResult):
                                        while ($row = $historyResult->fetch_assoc()):
                                    ?>
                                    <tr>
                                        <td class="fw-semibold"><?= htmlspecialchars($row['settlement_number']) ?></td>
                                        <td><?= htmlspecialchars(date('d/m/Y', strtotime($row['settlement_date']))) ?></td>
                                        <td class="text-end fw-bold"><?= htmlspecialchars(format_rupiah($row['total_sales'])) ?></td>
                                        <td class="text-end"><?= htmlspecialchars(format_rupiah($row['total_cash'])) ?></td>
                                        <td class="text-end"><?= htmlspecialchars(format_rupiah($row['total_transfer'])) ?></td>
                                        <td>
                                            <?php if ((string)($row['synced_to_omset'] ?? '0') === '0'): ?>
                                                <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#closeModal<?= (int)$row['id'] ?>">
                                                    Tutup & Sync
                                                </button>
                                            <?php else: ?>
                                                <span class="badge bg-success">Synced</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php if ((string)($row['synced_to_omset'] ?? '0') === '0'): ?>
                                    <div class="modal fade" id="closeModal<?= (int)$row['id'] ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <form method="post">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Tutup Settlement</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <input type="hidden" name="action" value="close">
                                                        <input type="hidden" name="settlement_id" value="<?= (int)$row['id'] ?>">
                                                        <div class="mb-3">
                                                            <label class="form-label fw-semibold">Catatan</label>
                                                            <textarea name="notes" class="form-control" rows="3"></textarea>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                                        <button type="submit" class="btn btn-primary">Tutup & Sinkronkan</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    <?php
                                        endwhile;
                                    endif;
                                    $stmtHistory->close();
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>