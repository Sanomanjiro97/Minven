<?php
session_start();
require_once '../config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['unread' => 0]);
    exit();
}

$user_id = $_SESSION['user_id'];

try {
    // Count unread messages
    $sql = "SELECT COUNT(*) as unread, sender_id FROM messages WHERE receiver_id = ? AND is_read = 0 GROUP BY sender_id";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $notifications = [];
    $total_unread = 0;
    
    while ($row = $result->fetch_assoc()) {
        $sender_id = $row['sender_id'];
        $unread = $row['unread'];
        $total_unread += $unread;
        
        // Get sender name
        $name_sql = "SELECT nama FROM users WHERE id = ?";
        $name_stmt = $conn->prepare($name_sql);
        $name_stmt->bind_param('i', $sender_id);
        $name_stmt->execute();
        $name_result = $name_stmt->get_result();
        $sender_name = $name_result->fetch_assoc()['nama'];
        
        $notifications[] = [
            'sender_id' => $sender_id,
            'sender_name' => $sender_name,
            'unread' => $unread
        ];
    }
    
    echo json_encode([
        'unread' => $total_unread,
        'notifications' => $notifications
    ]);
} catch (Exception $e) {
    echo json_encode(['unread' => 0, 'error' => $e->getMessage()]);
}