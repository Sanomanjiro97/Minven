<?php
session_start();
require_once '../config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$user_id = (int) $_SESSION['user_id'];
$caller_id = isset($_POST['caller_id']) ? intval($_POST['caller_id']) : 0;

if ($caller_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid caller']);
    exit();
}

$stmt = $conn->prepare("UPDATE calls SET status = 'rejected' WHERE caller_id = ? AND receiver_id = ? AND status = 'pending'");
$stmt->bind_param('ii', $caller_id, $user_id);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Call rejected']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to reject call']);
}
$stmt->close();
