<?php
require_once __DIR__ . '/_init.php';
kasir_require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sync_omset'])) {
    $today = date('Y-m-d');
    $todayStart = $today . ' 00:00:00';
    $todayEnd = $today . ' 23:59:59';
    
    // Get backoffice connection
    $boMainConn = main_db_conn();
    if (!$boMainConn instanceof mysqli || $boMainConn->connect_error) {
        $_SESSION['kasir_error'] = 'Koneksi database backoffice tidak tersedia.';
        header('Location: ' . kasir_url_for('dashboard.php'));
        exit();
    }
    
    // Ensure pendapatan_manual table exists
    $boMainConn->query("CREATE TABLE IF NOT EXISTS pendapatan_manual (
        id INT AUTO_INCREMENT PRIMARY KEY,
        tanggal DATE UNIQUE NOT NULL,
        total_omset_hari DOUBLE DEFAULT 0,
        total_dine_in DOUBLE DEFAULT 0,
        total_take_away DOUBLE DEFAULT 0,
        total_online_order DOUBLE DEFAULT 0,
        total_cash_sales DOUBLE DEFAULT 0,
        total_qr_gopay DOUBLE DEFAULT 0,
        total_edc DOUBLE DEFAULT 0,
        total_online_payment DOUBLE DEFAULT 0,
        total_transfers DOUBLE DEFAULT 0,
        total_shopeefood DOUBLE DEFAULT 0,
        total_gofood_gopay DOUBLE DEFAULT 0,
        total_ovo DOUBLE DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    // Aggregate POS sales data for today
    $totalOmset = 0.0;
    $totalCash = 0.0;
    $totalQrGopay = 0.0;
    $totalEdc = 0.0;
    $totalOnlinePayment = 0.0;
    $totalTransfers = 0.0;
    $totalShopeefood = 0.0;
    $totalGofood = 0.0;
    $totalOvo = 0.0;
    $totalDineIn = 0.0;
    $totalTakeAway = 0.0;
    $totalOnlineOrder = 0.0;
    
    if ($kasirPosConn instanceof mysqli && !$kasirPosConn->connect_error) {
        // Get total omset and breakdown by payment method
        $stmt = $kasirPosConn->prepare("SELECT 
            COALESCE(SUM(grand_total), 0) AS total_omset,
            COALESCE(SUM(CASE WHEN payment_method = 'cash' THEN grand_total ELSE 0 END), 0) AS total_cash,
            COALESCE(SUM(CASE WHEN payment_method = 'qris' THEN grand_total ELSE 0 END), 0) AS total_qris,
            COALESCE(SUM(CASE WHEN payment_method = 'debit' THEN grand_total ELSE 0 END), 0) AS total_debit,
            COALESCE(SUM(CASE WHEN payment_method = 'ewallet' THEN grand_total ELSE 0 END), 0) AS total_ewallet,
            COALESCE(SUM(CASE WHEN payment_method = 'transfer' THEN grand_total ELSE 0 END), 0) AS total_transfer,
            COALESCE(SUM(CASE WHEN payment_method = 'shopeefood' THEN grand_total ELSE 0 END), 0) AS total_shopeefood,
            COALESCE(SUM(CASE WHEN payment_method = 'gofood' THEN grand_total ELSE 0 END), 0) AS total_gofood,
            COALESCE(SUM(CASE WHEN payment_method = 'ovo' THEN grand_total ELSE 0 END), 0) AS total_ovo
            FROM pos_sales 
            WHERE sale_date BETWEEN ? AND ?");
        
        if ($stmt) {
            $stmt->bind_param('ss', $todayStart, $todayEnd);
            $stmt->execute();
            $res = $stmt->get_result();
            $row = $res ? $res->fetch_assoc() : null;
            
            if ($row) {
                $totalOmset = (float)($row['total_omset'] ?? 0);
                $totalCash = (float)($row['total_cash'] ?? 0);
                $totalQrGopay = (float)($row['total_qris'] ?? 0);
                $totalEdc = (float)($row['total_debit'] ?? 0);
                $totalOnlinePayment = (float)($row['total_ewallet'] ?? 0);
                $totalTransfers = (float)($row['total_transfer'] ?? 0);
                $totalShopeefood = (float)($row['total_shopeefood'] ?? 0);
                $totalGofood = (float)($row['total_gofood'] ?? 0);
                $totalOvo = (float)($row['total_ovo'] ?? 0);
            }
            $stmt->close();
        }
        
        // For now, set order types to 0 as we don't have that data in pos_sales
        // This could be enhanced if order type is tracked in the future
        $totalDineIn = 0.0;
        $totalTakeAway = 0.0;
        $totalOnlineOrder = 0.0;
    }
    
    // Insert or update pendapatan_manual
    $stmtCheck = $boMainConn->prepare("SELECT id FROM pendapatan_manual WHERE tanggal = ? LIMIT 1");
    if ($stmtCheck) {
        $stmtCheck->bind_param('s', $today);
        $stmtCheck->execute();
        $resCheck = $stmtCheck->get_result();
        $existingRow = $resCheck ? $resCheck->fetch_assoc() : null;
        $stmtCheck->close();
        
        if ($existingRow) {
            // Update existing record
            $stmtUpdate = $boMainConn->prepare("UPDATE pendapatan_manual SET 
                total_omset_hari = ?,
                total_dine_in = ?,
                total_take_away = ?,
                total_online_order = ?,
                total_cash_sales = ?,
                total_qr_gopay = ?,
                total_edc = ?,
                total_online_payment = ?,
                total_transfers = ?,
                total_shopeefood = ?,
                total_gofood_gopay = ?,
                total_ovo = ?
                WHERE tanggal = ?");
            
            if ($stmtUpdate) {
                $stmtUpdate->bind_param('dddddddddddds', 
                    $totalOmset, $totalDineIn, $totalTakeAway, $totalOnlineOrder,
                    $totalCash, $totalQrGopay, $totalEdc, $totalOnlinePayment,
                    $totalTransfers, $totalShopeefood, $totalGofood, $totalOvo, $today);
                
                if ($stmtUpdate->execute()) {
                    $_SESSION['kasir_success'] = 'Data omset hari ini berhasil diperbarui di backoffice.';
                } else {
                    $_SESSION['kasir_error'] = 'Gagal memperbarui data omset: ' . $stmtUpdate->error;
                }
                $stmtUpdate->close();
            } else {
                $_SESSION['kasir_error'] = 'Gagal menyiapkan query update: ' . $boMainConn->error;
            }
        } else {
            // Insert new record
            $stmtInsert = $boMainConn->prepare("INSERT INTO pendapatan_manual (
                tanggal, total_omset_hari, total_dine_in, total_take_away, total_online_order,
                total_cash_sales, total_qr_gopay, total_edc, total_online_payment,
                total_transfers, total_shopeefood, total_gofood_gopay, total_ovo
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            if ($stmtInsert) {
                $stmtInsert->bind_param('sdddddddddddd', 
                    $today, $totalOmset, $totalDineIn, $totalTakeAway, $totalOnlineOrder,
                    $totalCash, $totalQrGopay, $totalEdc, $totalOnlinePayment,
                    $totalTransfers, $totalShopeefood, $totalGofood, $totalOvo);
                
                if ($stmtInsert->execute()) {
                    $_SESSION['kasir_success'] = 'Data omset hari ini berhasil disinkronkan ke backoffice.';
                } else {
                    $_SESSION['kasir_error'] = 'Gagal menyimpan data omset: ' . $stmtInsert->error;
                }
                $stmtInsert->close();
            } else {
                $_SESSION['kasir_error'] = 'Gagal menyiapkan query insert: ' . $boMainConn->error;
            }
        }
    } else {
        $_SESSION['kasir_error'] = 'Gagal menyiapkan query cek: ' . $boMainConn->error;
    }
} else {
    $_SESSION['kasir_error'] = 'Metode request tidak valid.';
}

header('Location: ' . kasir_url_for('dashboard.php'));
exit();
