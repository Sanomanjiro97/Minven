<?php
require_once __DIR__ . '/_init.php';
kasir_require_login();

$posConn = pos_db_conn();
$user = kasir_current_user();
$currentShift = null;
$message = '';
$error = '';
$shiftHistory = [];

if ($posConn instanceof mysqli && !$posConn->connect_error) {
    $stmtCurrent = $posConn->prepare("SELECT * FROM pos_shifts WHERE status = 'open' AND opened_by = ? ORDER BY id DESC LIMIT 1");
    if ($stmtCurrent) {
        $stmtCurrent->bind_param('i', $user['id']);
        $stmtCurrent->execute();
        $resultCurrent = $stmtCurrent->get_result();
        $currentShift = $resultCurrent ? $resultCurrent->fetch_assoc() : null;
        $stmtCurrent->close();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $posConn instanceof mysqli && !$posConn->connect_error) {
    $action = trim((string)($_POST['action'] ?? ''));

    if ($action === 'open') {
        $openingCash = (float)($_POST['opening_cash'] ?? 0);
        if ($currentShift) {
            $error = 'Masih ada shift aktif. Tutup shift aktif terlebih dahulu.';
        } else {
            $shiftNumber = 'SHIFT-' . date('YmdHis');
            $stmt = $posConn->prepare("INSERT INTO pos_shifts (shift_number, shift_date, shift_label, gudang_id, opened_by, opened_by_username, opened_at, opening_cash, status) VALUES (?, CURDATE(), 'Shift Kasir', 0, ?, ?, NOW(), ?, 'open')");
            if ($stmt) {
                $stmt->bind_param('sisd', $shiftNumber, $user['id'], $user['username'], $openingCash);
                if ($stmt->execute()) {
                    $message = 'Shift berhasil dibuka.';
                } else {
                    $error = 'Gagal membuka shift.';
                }
                $stmt->close();
            } else {
                $error = 'Query buka shift tidak valid.';
            }
        }
    } elseif ($action === 'close') {
        $shiftId = (int)($_POST['shift_id'] ?? 0);
        $closingCash = (float)($_POST['closing_cash'] ?? 0);
        $notes = trim((string)($_POST['notes'] ?? ''));

        if ($shiftId <= 0) {
            $error = 'Shift tidak valid.';
        } else {
            $stmt = $posConn->prepare("SELECT * FROM pos_shifts WHERE id = ? AND opened_by = ? LIMIT 1");
            if ($stmt) {
                $stmt->bind_param('ii', $shiftId, $user['id']);
                $stmt->execute();
                $shift = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if (!$shift) {
                    $error = 'Data shift tidak ditemukan.';
                } else {
                    $totalSalesCash = 0.0;
                    $stmtSales = $posConn->prepare("SELECT COALESCE(SUM(grand_total), 0) AS total_cash FROM pos_sales WHERE shift_id = ? AND payment_method = 'cash'");
                    if ($stmtSales) {
                        $stmtSales->bind_param('i', $shiftId);
                        $stmtSales->execute();
                        $salesRow = $stmtSales->get_result()->fetch_assoc();
                        $totalSalesCash = (float)($salesRow['total_cash'] ?? 0);
                        $stmtSales->close();
                    }

                    $expectedCash = (float)($shift['opening_cash'] ?? 0) + $totalSalesCash;
                    $cashDifference = $closingCash - $expectedCash;

                    $stmtUpdate = $posConn->prepare("UPDATE pos_shifts SET closed_by = ?, closed_by_username = ?, closed_at = NOW(), closing_cash = ?, expected_cash = ?, cash_difference = ?, status = 'closed', notes = ? WHERE id = ?");
                    if ($stmtUpdate) {
                        $stmtUpdate->bind_param('isdddsi', $user['id'], $user['username'], $closingCash, $expectedCash, $cashDifference, $notes, $shiftId);
                        if ($stmtUpdate->execute()) {
                            $message = 'Shift berhasil ditutup.';
                        } else {
                            $error = 'Gagal menutup shift.';
                        }
                        $stmtUpdate->close();
                    } else {
                        $error = 'Query tutup shift tidak valid.';
                    }
                }
            }
        }
    }

    $stmtCurrent = $posConn->prepare("SELECT * FROM pos_shifts WHERE status = 'open' AND opened_by = ? ORDER BY id DESC LIMIT 1");
    if ($stmtCurrent) {
        $stmtCurrent->bind_param('i', $user['id']);
        $stmtCurrent->execute();
        $resultCurrent = $stmtCurrent->get_result();
        $currentShift = $resultCurrent ? $resultCurrent->fetch_assoc() : null;
        $stmtCurrent->close();
    }
}

if ($posConn instanceof mysqli && !$posConn->connect_error) {
    $stmtHistory = $posConn->prepare("SELECT * FROM pos_shifts WHERE opened_by = ? ORDER BY id DESC LIMIT 20");
    if ($stmtHistory) {
        $stmtHistory->bind_param('i', $user['id']);
        $stmtHistory->execute();
        $historyResult = $stmtHistory->get_result();
        while ($historyResult && ($row = $historyResult->fetch_assoc())) {
            $shiftHistory[] = $row;
        }
        $stmtHistory->close();
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
    <title>Shift Kasir - MINVEN</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        body { background: linear-gradient(180deg, #eef4ff 0%, #f8fafc 44%, #f8fafc 100%); color: #0f172a; }
        .page-wrap { max-width: 1160px; margin: 0 auto; padding: 28px 16px 48px; }
        .hero-card, .card-soft { border: 0; border-radius: 24px; box-shadow: 0 18px 45px rgba(15, 23, 42, 0.08); }
        .hero-card { background: linear-gradient(135deg, #0f172a 0%, #1d4ed8 60%, #38bdf8 100%); color: #fff; }
        .hero-card .muted { color: rgba(255, 255, 255, 0.78); }
        .stat-pill { padding: 14px 16px; border-radius: 18px; background: #f8fafc; border: 1px solid #e2e8f0; }
        .section-title { font-size: 1.05rem; font-weight: 700; }
        .btn-soft { border-radius: 14px; padding: 12px 18px; }
        .table thead th { border-bottom: 0; color: #64748b; font-size: .78rem; text-transform: uppercase; letter-spacing: .04em; }
    </style>
</head>
<body>
    <div class="page-wrap">
        <div class="hero-card p-4 p-lg-5 mb-4">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                <div>
                    <div class="muted small text-uppercase mb-2">Manajemen Shift</div>
                    <h1 class="h3 fw-bold mb-2">Buka dan tutup shift kasir lebih cepat</h1>
                    <p class="muted mb-0">Shift tidak lagi perlu memilih gudang. Cukup buka shift, lalu transaksi bisa berjalan seperti kasir pada umumnya.</p>
                </div>
                <div class="d-flex gap-2">
                    <a class="btn btn-light btn-soft" href="<?= htmlspecialchars(kasir_url_for('dashboard.php')) ?>">
                        <i class="bi bi-grid me-2"></i> Dashboard
                    </a>
                    <a class="btn btn-outline-light btn-soft" href="<?= htmlspecialchars(kasir_url_for('create.php')) ?>">
                        <i class="bi bi-cart-plus me-2"></i> Kasir
                    </a>
                </div>
            </div>
        </div>

        <?php if ($message !== ''): ?>
            <div class="alert alert-success border-0 shadow-sm"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        <?php if ($error !== ''): ?>
            <div class="alert alert-danger border-0 shadow-sm"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="row g-4">
            <div class="col-lg-5">
                <div class="card card-soft h-100">
                    <div class="card-body p-4">
                        <div class="section-title mb-3"><?= $currentShift ? 'Shift Aktif' : 'Buka Shift Baru' ?></div>

                        <?php if (!$currentShift): ?>
                            <div class="stat-pill mb-4">
                                <div class="text-muted small mb-1">Kasir</div>
                                <div class="fw-semibold"><?= htmlspecialchars($user['nama'] !== '' ? $user['nama'] : $user['username']) ?></div>
                            </div>
                            <form method="post">
                                <input type="hidden" name="action" value="open">
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Uang Awal Kas</label>
                                    <input type="number" name="opening_cash" class="form-control form-control-lg" step="0.01" value="0" required>
                                </div>
                                <div class="text-muted small mb-4">Shift akan dibuka untuk user yang sedang login tanpa mengikat gudang tertentu.</div>
                                <button type="submit" class="btn btn-primary btn-soft w-100">
                                    <i class="bi bi-play-circle me-2"></i> Buka Shift Sekarang
                                </button>
                            </form>
                        <?php else: ?>
                            <div class="row g-3 mb-4">
                                <div class="col-6">
                                    <div class="stat-pill">
                                        <div class="text-muted small mb-1">Nomor Shift</div>
                                        <div class="fw-semibold"><?= htmlspecialchars($currentShift['shift_number']) ?></div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="stat-pill">
                                        <div class="text-muted small mb-1">Tanggal</div>
                                        <div class="fw-semibold"><?= htmlspecialchars(date('d/m/Y', strtotime($currentShift['shift_date']))) ?></div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="stat-pill">
                                        <div class="text-muted small mb-1">Dibuka</div>
                                        <div class="fw-semibold"><?= htmlspecialchars(date('H:i', strtotime($currentShift['opened_at']))) ?></div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="stat-pill">
                                        <div class="text-muted small mb-1">Kas Awal</div>
                                        <div class="fw-semibold"><?= htmlspecialchars(format_rupiah($currentShift['opening_cash'])) ?></div>
                                    </div>
                                </div>
                            </div>
                            <form method="post">
                                <input type="hidden" name="action" value="close">
                                <input type="hidden" name="shift_id" value="<?= (int)$currentShift['id'] ?>">
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Kas Akhir</label>
                                    <input type="number" name="closing_cash" class="form-control form-control-lg" step="0.01" value="0" required>
                                </div>
                                <div class="mb-4">
                                    <label class="form-label fw-semibold">Catatan Shift</label>
                                    <textarea name="notes" class="form-control" rows="4" placeholder="Tulis catatan penutupan shift jika perlu"></textarea>
                                </div>
                                <button type="submit" class="btn btn-danger btn-soft w-100">
                                    <i class="bi bi-stop-circle me-2"></i> Tutup Shift
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-lg-7">
                <div class="card card-soft h-100">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div class="section-title mb-0">Riwayat Shift</div>
                            <span class="text-muted small"><?= count($shiftHistory) ?> shift</span>
                        </div>
                        <div class="table-responsive">
                            <table class="table align-middle">
                                <thead>
                                    <tr>
                                        <th>Nomor Shift</th>
                                        <th>Tanggal</th>
                                        <th>Status</th>
                                        <th class="text-end">Kas Awal</th>
                                        <th class="text-end">Kas Akhir</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!$shiftHistory): ?>
                                        <tr>
                                            <td colspan="5" class="text-center text-muted py-5">Belum ada riwayat shift.</td>
                                        </tr>
                                    <?php endif; ?>
                                    <?php foreach ($shiftHistory as $row): ?>
                                        <tr>
                                            <td class="fw-semibold"><?= htmlspecialchars($row['shift_number']) ?></td>
                                            <td><?= htmlspecialchars(date('d/m/Y', strtotime($row['shift_date']))) ?></td>
                                            <td>
                                                <span class="badge bg-<?= $row['status'] === 'open' ? 'primary' : 'secondary' ?>">
                                                    <?= $row['status'] === 'open' ? 'Open' : 'Closed' ?>
                                                </span>
                                            </td>
                                            <td class="text-end"><?= htmlspecialchars(format_rupiah($row['opening_cash'])) ?></td>
                                            <td class="text-end"><?= $row['closing_cash'] !== null ? htmlspecialchars(format_rupiah($row['closing_cash'])) : '-' ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>