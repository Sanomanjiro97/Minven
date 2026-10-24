<?php
require_once __DIR__ . '/../_init.php';
require_once __DIR__ . '/../../includes/update_stok_helper.php';

// Background API untuk update stok terpakai
// Tidak perlu session check karena ini background process
header('Content-Type: application/json; charset=utf-8');

$saleId = isset($_GET['sale_id']) ? (int)$_GET['sale_id'] : 0;
$success = false;
$message = '';

try {
    // Get sale info to get gudang_id
    $posConn = pos_db_conn();
    if (!$posConn instanceof mysqli || $posConn->connect_error) {
        throw new Exception('POS database connection failed');
    }

    $stmt = $posConn->prepare("SELECT gudang_id FROM pos_sales WHERE id = ? LIMIT 1");
    if (!$stmt) {
        throw new Exception('Failed to prepare query');
    }
    $stmt->bind_param('i', $saleId);
    $stmt->execute();
    $result = $stmt->get_result();
    $sale = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if (!$sale) {
        throw new Exception('Sale not found');
    }

    $gudangId = (int)($sale['gudang_id'] ?? 0);
    if ($gudangId <= 0) {
        throw new Exception('Invalid gudang_id');
    }

    // Get main connection
    $mainConn = main_db_conn();
    if (!$mainConn instanceof mysqli || $mainConn->connect_error) {
        throw new Exception('Main database connection failed');
    }

    // Call the helper function to update stock
    $updateResult = updateStokTerpakaiHelper($mainConn, $gudangId);
    
    if ($updateResult['success']) {
        $success = true;
        $message = 'Stock updated successfully for ' . $updateResult['updated_count'] . ' items';
    } else {
        $message = $updateResult['message'];
    }

} catch (Exception $e) {
    $message = $e->getMessage();
}

echo json_encode([
    'success' => $success,
    'message' => $message,
    'sale_id' => $saleId
], JSON_UNESCAPED_UNICODE);
