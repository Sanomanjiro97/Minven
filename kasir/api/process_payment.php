<?php
/**
 * API: Process Payment
 * Used for: Processing transactions and payments
 */

session_start();
require_once('../../_init.php');

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        exit;
    }

    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    if (empty($data['items']) || empty($data['payment_method']) || empty($data['amount'])) {
        echo json_encode(['success' => false, 'message' => 'Data tidak lengkap']);
        exit;
    }

    // Start transaction
    $db->begin_transaction();

    try {
        // 1. Create POS transaction
        $transactionNo = 'TRX' . date('YmdHis') . rand(1000, 9999);
        $sql = "INSERT INTO pos_transactions 
                (transaction_no, outlet_id, cashier_id, customer_id, order_type, 
                 subtotal, discount_amount, total_amount, payment_status, transaction_status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'active')";
        
        $stmt = $db->prepare($sql);
        $stmt->bind_param('siiiiddd', 
            $transactionNo,
            $_SESSION['outlet_id'],
            $_SESSION['user_id'],
            $data['customer_id'] ?? null,
            $data['order_type'] ?? 'dine_in',
            $data['subtotal'],
            $data['discount_amount'] ?? 0,
            $data['total_amount']
        );
        $stmt->execute();
        $transactionId = $db->insert_id();

        // 2. Add transaction items
        foreach ($data['items'] as $item) {
            $sql = "INSERT INTO pos_transaction_items 
                    (transaction_id, barang_id, quantity, unit_price, subtotal) 
                    VALUES (?, ?, ?, ?, ?)";
            
            $stmt = $db->prepare($sql);
            $stmt->bind_param('iiidi',
                $transactionId,
                $item['id'],
                $item['qty'],
                $item['price'],
                $item['subtotal']
            );
            $stmt->execute();

            // Update inventory
            $sql = "UPDATE barang SET stok = stok - ? WHERE id = ?";
            $stmt = $db->prepare($sql);
            $stmt->bind_param('ii', $item['qty'], $item['id']);
            $stmt->execute();
        }

        // 3. Record payment
        $sql = "INSERT INTO pos_payments 
                (transaction_id, payment_method, amount, payment_status) 
                VALUES (?, ?, ?, 'success')";
        
        $stmt = $db->prepare($sql);
        $stmt->bind_param('isd',
            $transactionId,
            $data['payment_method'],
            $data['amount']
        );
        $stmt->execute();

        // 4. Add loyalty points if customer
        if (!empty($data['customer_id'])) {
            $points = floor($data['total_amount'] / 1000); // 1 point per 1000 rupiah
            $sql = "INSERT INTO pos_loyalty_points 
                    (customer_id, transaction_id, points, point_type) 
                    VALUES (?, ?, ?, 'earn')";
            
            $stmt = $db->prepare($sql);
            $stmt->bind_param('iii',
                $data['customer_id'],
                $transactionId,
                $points
            );
            $stmt->execute();

            // Update customer total purchases
            $sql = "UPDATE pos_customers 
                    SET loyalty_points = loyalty_points + ?,
                        total_purchases = total_purchases + ?,
                        last_purchase_date = NOW()
                    WHERE id = ?";
            
            $stmt = $db->prepare($sql);
            $stmt->bind_param('idi', $points, $data['total_amount'], $data['customer_id']);
            $stmt->execute();
        }

        // Update transaction status
        $sql = "UPDATE pos_transactions SET payment_status = 'paid', transaction_status = 'completed' WHERE id = ?";
        $stmt = $db->prepare($sql);
        $stmt->bind_param('i', $transactionId);
        $stmt->execute();

        // Log audit
        logAudit($_SESSION['user_id'], 'PAYMENT_PROCESSED', 'pos_transactions', $transactionId, 
                 ['payment_method' => $data['payment_method'], 'amount' => $data['amount']]);

        $db->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Pembayaran berhasil diproses',
            'transaction_id' => $transactionId,
            'transaction_no' => $transactionNo,
            'receipt_url' => '../receipts/index.php?id=' . $transactionId
        ]);

    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function logAudit($userId, $action, $table, $recordId, $details) {
    global $db;
    $sql = "INSERT INTO pos_audit_log (user_id, action_type, table_name, record_id, action_details) 
            VALUES (?, ?, ?, ?, ?)";
    $stmt = $db->prepare($sql);
    $jsonDetails = json_encode($details);
    $stmt->bind_param('issjs', $userId, $action, $table, $recordId, $jsonDetails);
    $stmt->execute();
}
?>
