<?php
require_once __DIR__ . '/_init.php';
kasir_require_login();

$user = kasir_current_user();
$todayStart = date('Y-m-d 00:00:00');
$todayEnd = date('Y-m-d 23:59:59');

$summary = [
    'today_total' => 0.0,
    'today_count' => 0,
    'items_sold' => 0,
    'cash_total' => 0.0,
];
$recentSales = [];
$currentShift = null;

if ($kasirPosConn instanceof mysqli && !$kasirPosConn->connect_error) {
    $stmt = $kasirPosConn->prepare("SELECT
            COALESCE(SUM(grand_total), 0) AS today_total,
            COUNT(*) AS today_count,
            COALESCE(SUM((SELECT COALESCE(SUM(qty),0) FROM pos_sale_items psi WHERE psi.sale_id = ps.id)), 0) AS items_sold,
            COALESCE(SUM(CASE WHEN payment_method = 'cash' THEN grand_total ELSE 0 END), 0) AS cash_total
        FROM pos_sales ps
        WHERE sale_date BETWEEN ? AND ?");
    if ($stmt) {
        $stmt->bind_param('ss', $todayStart, $todayEnd);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        if ($row) {
            $summary['today_total'] = (float)($row['today_total'] ?? 0);
            $summary['today_count'] = (int)($row['today_count'] ?? 0);
            $summary['items_sold'] = (int)($row['items_sold'] ?? 0);
            $summary['cash_total'] = (float)($row['cash_total'] ?? 0);
        }
        $stmt->close();
    }

    $stmtShift = $kasirPosConn->prepare("SELECT * FROM pos_shifts WHERE status = 'open' AND opened_by = ? ORDER BY id DESC LIMIT 1");
    if ($stmtShift) {
        $stmtShift->bind_param('i', $user['id']);
        $stmtShift->execute();
        $resShift = $stmtShift->get_result();
        $currentShift = $resShift ? $resShift->fetch_assoc() : null;
        $stmtShift->close();
    }

    $result = $kasirPosConn->query("SELECT id, sale_number, sale_date, cashier_username, customer_name, payment_method, grand_total
        FROM pos_sales
        ORDER BY id DESC
        LIMIT 12");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $recentSales[] = $row;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Kasir</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        body { margin: 0; background: linear-gradient(180deg, #eef4ff 0%, #f8fafc 35%, #f8fafc 100%); color: #0f172a; }
        .app-shell { min-height: 100vh; display: flex; }
        .sidebar { width: 290px; background: linear-gradient(180deg, #0f172a 0%, #172554 48%, #1e3a8a 100%); color: #fff; padding: 26px 20px; display: flex; flex-direction: column; }
        .brand-mark { width: 48px; height: 48px; border-radius: 16px; display: inline-flex; align-items: center; justify-content: center; background: rgba(255, 255, 255, 0.12); font-size: 1.2rem; }
        .nav-link-pos { display: flex; align-items: center; gap: 12px; padding: 14px 16px; border-radius: 18px; color: rgba(255, 255, 255, 0.86); text-decoration: none; margin-bottom: 10px; background: rgba(255, 255, 255, 0.05); }
        .nav-link-pos.active, .nav-link-pos:hover { color: #fff; background: linear-gradient(90deg, rgba(59, 130, 246, .95), rgba(14, 165, 233, .95)); }
        .content { flex: 1; padding: 28px; }
        .hero-card, .card-soft { border: 0; border-radius: 24px; box-shadow: 0 18px 45px rgba(15, 23, 42, 0.08); }
        .hero-card { background: linear-gradient(135deg, #0f172a 0%, #1d4ed8 55%, #38bdf8 100%); color: #fff; }
        .hero-card .text-muted-soft { color: rgba(255, 255, 255, 0.78); }
        .stat-card { padding: 22px; height: 100%; }
        .stat-icon { width: 52px; height: 52px; border-radius: 18px; display: inline-flex; align-items: center; justify-content: center; font-size: 1.15rem; }
        .soft-blue { background: #dbeafe; color: #1d4ed8; }
        .soft-green { background: #dcfce7; color: #16a34a; }
        .soft-orange { background: #ffedd5; color: #ea580c; }
        .soft-violet { background: #ede9fe; color: #7c3aed; }
        .section-title { font-size: 1.02rem; font-weight: 700; }
        .quick-action { display: flex; align-items: center; gap: 14px; padding: 18px; border-radius: 20px; text-decoration: none; color: #0f172a; background: #fff; border: 1px solid #e2e8f0; box-shadow: 0 10px 24px rgba(15, 23, 42, 0.04); }
        .quick-action:hover { color: #0f172a; border-color: #bfdbfe; box-shadow: 0 16px 32px rgba(59, 130, 246, 0.10); }
        .quick-action i { width: 48px; height: 48px; border-radius: 16px; display: inline-flex; align-items: center; justify-content: center; background: #eff6ff; color: #2563eb; font-size: 1.15rem; }
        .table thead th { border-bottom: 0; color: #64748b; font-size: .78rem; text-transform: uppercase; letter-spacing: .04em; }
        .shift-badge { display: inline-flex; align-items: center; gap: 8px; padding: 10px 14px; border-radius: 999px; background: rgba(255, 255, 255, 0.12); }
        @media (max-width: 991.98px) { .app-shell { display: block; } .sidebar { width: 100%; } .content { padding: 18px; } }
    </style>
</head>
<body>
    <div class="app-shell">
        <aside class="sidebar">
            <div class="d-flex align-items-center gap-3 mb-4">
                <div class="brand-mark"><i class="bi bi-shop"></i></div>
                <div>
                    <div class="text-uppercase small text-white-50">Minven POS</div>
                    <div class="fw-bold fs-5">Dashboard Kasir</div>
                </div>
            </div>

            <div class="rounded-4 p-3 mb-4" style="background: rgba(255,255,255,.08);">
                <div class="small text-white-50 mb-1">Login sebagai</div>
                <div class="fw-semibold"><?= htmlspecialchars($user['nama'] !== '' ? $user['nama'] : $user['username']) ?></div>
                <div class="small text-white-50 mt-1"><?= $currentShift ? 'Shift sedang aktif' : 'Belum ada shift aktif' ?></div>
            </div>

            <a class="nav-link-pos active" href="<?= htmlspecialchars(kasir_url_for('dashboard.php')) ?>">
                <i class="bi bi-grid"></i><span>Dashboard</span>
            </a>
            <a class="nav-link-pos" href="<?= htmlspecialchars(kasir_url_for('create.php')) ?>">
                <i class="bi bi-cart-plus"></i><span>Transaksi Baru</span>
            </a>
            <a class="nav-link-pos" href="<?= htmlspecialchars(kasir_url_for('tables.php')) ?>">
                <i class="bi bi-border-all"></i><span>Management Meja</span>
            </a>
            <a class="nav-link-pos" href="<?= htmlspecialchars(kasir_url_for('shift.php')) ?>">
                <i class="bi bi-clock-history"></i><span>Shift Kasir</span>
            </a>
            <a class="nav-link-pos" href="<?= htmlspecialchars(kasir_url_for('settlement.php')) ?>">
                <i class="bi bi-file-earmark-check"></i><span>Settlement Harian</span>
            </a>
            <a class="nav-link-pos" href="<?= htmlspecialchars(kasir_url_for('orders/index.php')) ?>">
                <i class="bi bi-receipt"></i><span>Pesanan</span>
            </a>
            <a class="nav-link-pos" href="<?= htmlspecialchars(kasir_url_for('payments/index.php')) ?>">
                <i class="bi bi-credit-card"></i><span>Pembayaran</span>
            </a>
            <a class="nav-link-pos" href="<?= htmlspecialchars(kasir_url_for('customers/index.php')) ?>">
                <i class="bi bi-people"></i><span>Pelanggan</span>
            </a>
            <a class="nav-link-pos" href="<?= htmlspecialchars(kasir_url_for('inventory/index.php')) ?>">
                <i class="bi bi-box-seam"></i><span>Stok Kasir</span>
            </a>
            <a class="nav-link-pos" href="<?= htmlspecialchars(kasir_url_for('analytics/index.php')) ?>">
                <i class="bi bi-bar-chart-line"></i><span>Analitik</span>
            </a>
            <a class="nav-link-pos" href="<?= htmlspecialchars(kasir_url_for('settings/index.php')) ?>">
                <i class="bi bi-gear"></i><span>Pengaturan</span>
            </a>

            <div class="mt-auto pt-4">
                <a class="nav-link-pos" href="<?= htmlspecialchars(kasir_url_for('logout.php')) ?>">
                    <i class="bi bi-box-arrow-right"></i><span>Logout</span>
                </a>
            </div>
        </aside>

        <main class="content">
            <div class="hero-card p-4 p-lg-5 mb-4">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                    <div>
                        <div class="text-uppercase small text-muted-soft mb-2">Ringkasan Hari Ini</div>
                        <h1 class="h3 fw-bold mb-2">Kasir siap untuk transaksi cepat dan rapi</h1>
                        <p class="text-muted-soft mb-0">Tampilan dibuat lebih dekat ke pola POS modern: fokus ke omzet, status shift, aksi cepat, dan transaksi terbaru.</p>
                    </div>
                    <div class="d-flex flex-column align-items-lg-end gap-3">
                        <div class="shift-badge">
                            <i class="bi bi-circle-fill" style="font-size:.55rem;"></i>
                            <span><?= $currentShift ? 'Shift aktif: ' . htmlspecialchars($currentShift['shift_number']) : 'Belum ada shift aktif' ?></span>
                        </div>
                        <a class="btn btn-light btn-lg rounded-4 px-4" href="<?= htmlspecialchars(kasir_url_for('create.php')) ?>">
                            <i class="bi bi-plus-circle me-2"></i> Buat Transaksi
                        </a>
                    </div>
                </div>
            </div>

            <?php if (isset($_SESSION['kasir_success'])): ?>
                <div class="alert alert-success border-0 shadow-sm"><?= htmlspecialchars((string)$_SESSION['kasir_success']) ?></div>
                <?php unset($_SESSION['kasir_success']); ?>
            <?php endif; ?>
            <?php if (isset($_SESSION['kasir_error'])): ?>
                <div class="alert alert-danger border-0 shadow-sm"><?= htmlspecialchars((string)$_SESSION['kasir_error']) ?></div>
                <?php unset($_SESSION['kasir_error']); ?>
            <?php endif; ?>

            <div class="row g-4 mb-4">
                <div class="col-md-6 col-xl-3">
                    <div class="card card-soft stat-card">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="text-muted small mb-2">Omzet Hari Ini</div>
                                <div class="h4 fw-bold mb-0"><?= htmlspecialchars(kasir_format_currency($summary['today_total'])) ?></div>
                            </div>
                            <div class="stat-icon soft-blue"><i class="bi bi-cash-stack"></i></div>
                        </div>
                        <div class="mt-3 pt-3 border-top">
                            <form method="POST" action="sync_omset.php" class="d-grid">
                                <button type="submit" name="sync_omset" value="1" class="btn btn-sm btn-outline-primary rounded-3">
                                    <i class="bi bi-arrow-repeat me-1"></i> Sync ke Backoffice
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-xl-3">
                    <div class="card card-soft stat-card">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="text-muted small mb-2">Transaksi</div>
                                <div class="h4 fw-bold mb-0"><?= (int)$summary['today_count'] ?></div>
                            </div>
                            <div class="stat-icon soft-green"><i class="bi bi-receipt"></i></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-xl-3">
                    <div class="card card-soft stat-card">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="text-muted small mb-2">Item Terjual</div>
                                <div class="h4 fw-bold mb-0"><?= (int)$summary['items_sold'] ?></div>
                            </div>
                            <div class="stat-icon soft-orange"><i class="bi bi-bag-check"></i></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-xl-3">
                    <div class="card card-soft stat-card">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="text-muted small mb-2">Cash Masuk</div>
                                <div class="h4 fw-bold mb-0"><?= htmlspecialchars(kasir_format_currency($summary['cash_total'])) ?></div>
                            </div>
                            <div class="stat-icon soft-violet"><i class="bi bi-wallet2"></i></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4 mb-4">
                <div class="col-lg-8">
                    <div class="card card-soft">
                        <div class="card-body p-4">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div class="section-title mb-0">Aksi Cepat</div>
                                <span class="text-muted small">Workflow kasir harian</span>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <a class="quick-action" href="<?= htmlspecialchars(kasir_url_for('create.php')) ?>">
                                        <i class="bi bi-cart-plus"></i>
                                        <div>
                                            <div class="fw-semibold">Transaksi Baru</div>
                                            <div class="text-muted small">Buat order dan simpan pembayaran</div>
                                        </div>
                                    </a>
                                </div>
                                <div class="col-md-6">
                                    <a class="quick-action" href="<?= htmlspecialchars(kasir_url_for('tables.php')) ?>">
                                        <i class="bi bi-border-all"></i>
                                        <div>
                                            <div class="fw-semibold">Management Meja</div>
                                            <div class="text-muted small">Atur ketersediaan dan status meja</div>
                                        </div>
                                    </a>
                                </div>
                                <div class="col-md-6">
                                    <a class="quick-action" href="<?= htmlspecialchars(kasir_url_for('shift.php')) ?>">
                                        <i class="bi bi-clock-history"></i>
                                        <div>
                                            <div class="fw-semibold">Kelola Shift</div>
                                            <div class="text-muted small">Buka atau tutup shift kasir</div>
                                        </div>
                                    </a>
                                </div>
                                <div class="col-md-6">
                                    <a class="quick-action" href="<?= htmlspecialchars(kasir_url_for('settlement.php')) ?>">
                                        <i class="bi bi-file-earmark-check"></i>
                                        <div>
                                            <div class="fw-semibold">Settlement</div>
                                            <div class="text-muted small">Tutup penjualan harian dan sinkron omset</div>
                                        </div>
                                    </a>
                                </div>
                                <div class="col-md-6">
                                    <a class="quick-action" href="<?= htmlspecialchars(url_for('backoffice/dashboard.php')) ?>">
                                        <i class="bi bi-bar-chart-line"></i>
                                        <div>
                                            <div class="fw-semibold">Backoffice</div>
                                            <div class="text-muted small">Cek laporan dan produk dari pusat</div>
                                        </div>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card card-soft h-100">
                        <div class="card-body p-4">
                            <div class="section-title mb-3">Status Shift</div>
                            <?php if ($currentShift): ?>
                                <div class="rounded-4 p-3 mb-3" style="background:#eff6ff;border:1px solid #bfdbfe;">
                                    <div class="text-muted small mb-1">Nomor Shift</div>
                                    <div class="fw-semibold"><?= htmlspecialchars($currentShift['shift_number']) ?></div>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Dibuka</span>
                                    <strong><?= htmlspecialchars(date('d/m/Y H:i', strtotime($currentShift['opened_at']))) ?></strong>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Kas Awal</span>
                                    <strong><?= htmlspecialchars(kasir_format_currency((float)$currentShift['opening_cash'])) ?></strong>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span class="text-muted">Status</span>
                                    <span class="badge bg-primary">Aktif</span>
                                </div>
                            <?php else: ?>
                                <div class="text-muted mb-3">Belum ada shift aktif untuk user ini.</div>
                                <a class="btn btn-primary w-100 rounded-4" href="<?= htmlspecialchars(kasir_url_for('shift.php')) ?>">
                                    <i class="bi bi-play-circle me-2"></i> Buka Shift
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card card-soft">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div class="section-title mb-0">Transaksi Terbaru</div>
                        <span class="text-muted small"><?= count($recentSales) ?> transaksi</span>
                    </div>
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead>
                                <tr>
                                    <th>No. Transaksi</th>
                                    <th>Tanggal</th>
                                    <th>Pelanggan</th>
                                    <th>Pembayaran</th>
                                    <th>Total</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!$recentSales): ?>
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-5">Belum ada transaksi POS.</td>
                                    </tr>
                                <?php endif; ?>
                                <?php foreach ($recentSales as $sale): ?>
                                    <tr>
                                        <td class="fw-semibold"><?= htmlspecialchars((string)$sale['sale_number']) ?></td>
                                        <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime((string)$sale['sale_date']))) ?></td>
                                        <td><?= htmlspecialchars((string)($sale['customer_name'] ?: '-')) ?></td>
                                        <td><?= htmlspecialchars(strtoupper((string)$sale['payment_method'])) ?></td>
                                        <td class="fw-semibold"><?= htmlspecialchars(kasir_format_currency((float)$sale['grand_total'])) ?></td>
                                        <td class="text-end">
                                            <a class="btn btn-sm btn-outline-primary rounded-3" href="<?= htmlspecialchars(kasir_url_for('view.php?id=' . (int)$sale['id'])) ?>">
                                                Detail
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>