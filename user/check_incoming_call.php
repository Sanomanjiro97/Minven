<?php
session_start();
require_once '../config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['hasIncoming' => false]);
    exit();
}

$user_id = (int) $_SESSION['user_id'];

try {
    $sql = "SELECT c.id, c.caller_id, c.call_type, u.nama AS caller_name
            FROM calls c
            JOIN users u ON u.id = c.caller_id
            WHERE c.receiver_id = ? AND c.status = 'pending'
            ORDER BY c.created_at DESC
            LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($row = $res->fetch_assoc()) {
        echo json_encode([
            'hasIncoming' => true,
            'call_id' => (int)$row['id'],
            'caller_id' => (int)$row['caller_id'],
            'caller_name' => $row['caller_name'],
            'call_type' => $row['call_type']
        ]);
    } else {
        echo json_encode(['hasIncoming' => false]);
    }
} catch (Exception $e) {
    echo json_encode(['hasIncoming' => false, 'error' => $e->getMessage()]);
}
