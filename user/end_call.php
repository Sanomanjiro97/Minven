<?php
session_start();
require_once '../config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$user_id = (int) $_SESSION['user_id'];
$receiver_id = isset($_POST['receiver_id']) ? intval($_POST['receiver_id']) : 0;

$stmt = $conn->prepare("UPDATE calls SET status = 'ended' WHERE ((caller_id = ? AND receiver_id = ?) OR (caller_id = ? AND receiver_id = ?)) AND status IN ('pending', 'active')");
$stmt->bind_param('iiii', $user_id, $receiver_id, $receiver_id, $user_id);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Call ended']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to end call']);
}
$stmt->close();
