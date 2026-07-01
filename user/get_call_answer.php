<?php
session_start();
require_once '../config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
} 

$user_id = (int) $_SESSION['user_id'];
$receiver_id = isset($_GET['receiver_id']) ? intval($_GET['receiver_id']) : 0;

try {
    $sql = "SELECT answer, status, call_type FROM calls
            WHERE caller_id = ? AND receiver_id = ? AND status IN ('active', 'pending')
            ORDER BY created_at DESC LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ii', $user_id, $receiver_id);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($row = $res->fetch_assoc()) {
        if ($row['answer']) {
            echo json_encode([
                'success' => true,
                'answer' => json_decode($row['answer'], true),
                'call_type' => $row['call_type']
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Waiting for answer...']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'No call found']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
