<?php
/**
 * API: Get Transaction Receipt
 * Used for: Displaying and printing receipts
 */

session_start();
require_once('../../_init.php');

header('Content-Type: application/json');

try {
    $transactionId = $_GET['id'] ?? null;
    
    if (empty($transactionId)) {
        echo json_encode(['success' => false, 'message' => 'Transaction ID tidak ditemukan']);
        exit;
    }

    // Get transaction details
    $sql = "SELECT t.*, u.user_name as cashier_name, c.name as customer_name 
            FROM pos_transactions t
            LEFT JOIN users u ON t.cashier_id = u.id
            LEFT JOIN pos_customers c ON t.customer_id = c.id
            WHERE t.id = ?";
    
    $stmt = $db->prepare($sql);
    $stmt->bind_param('i', $transactionId);
    $stmt->execute();
    $transaction = $stmt->get_result()->fetch_assoc();

    if (!$transaction) {
        echo json_encode(['success' => false, 'message' => 'Transaksi tidak ditemukan']);
        exit;
    }

    // Get transaction items
    $sql = "SELECT ti.*, b.barang_nama 
            FROM pos_transaction_items ti
            JOIN barang b ON ti.barang_id = b.id
            WHERE ti.transaction_id = ?";
    
    $stmt = $db->prepare($sql);
    $stmt->bind_param('i', $transactionId);
    $stmt->execute();
    $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Get payments
    $sql = "SELECT * FROM pos_payments WHERE transaction_id = ?";
    $stmt = $db->prepare($sql);
    $stmt->bind_param('i', $transactionId);
    $stmt->execute();
    $payments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    echo json_encode([
        'success' => true,
        'transaction' => $transaction,
        'items' => $items,
        'payments' => $payments
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
