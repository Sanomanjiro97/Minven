<?php
session_start();
require_once '../config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode([]);
    exit();
}

if (!isset($_GET['receiver_id'])) {
    echo json_encode([]);
    exit();
}

$user_id = $_SESSION['user_id'];
$receiver_id = intval($_GET['receiver_id']);

try {
    // Mark messages as read
    $update_sql = "UPDATE messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ? AND is_read = 0";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param('ii', $receiver_id, $user_id);
    $update_stmt->execute();
    
    // Get messages
    $sql = "SELECT m.*, 
            CASE WHEN m.sender_id = ? THEN 'You' ELSE u.nama END as sender
            FROM messages m
            JOIN users u ON m.sender_id = u.id
            WHERE (m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?)
            ORDER BY m.created_at ASC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('iiiii', $user_id, $user_id, $receiver_id, $receiver_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $messages = [];
    while ($row = $result->fetch_assoc()) {
        $messages[] = $row;
    }
    
    echo json_encode($messages);
} catch (Exception $e) {
    echo json_encode([]);
}