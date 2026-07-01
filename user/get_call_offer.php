<?php
session_start();
require_once '../config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$user_id = (int) $_SESSION['user_id'];
$caller_id = isset($_GET['caller_id']) ? intval($_GET['caller_id']) : 0;

$stmt = $conn->prepare("SELECT * FROM calls WHERE caller_id = ? AND receiver_id = ? AND status = 'pending' ORDER BY created_at DESC LIMIT 1");
$stmt->bind_param('ii', $caller_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    echo json_encode([
        'success' => true,
        'offer' => json_decode($row['offer'], true),
        'call_type' => $row['call_type'],
        'call_id' => $row['id']
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'No pending call found']);
}
$stmt->close();
