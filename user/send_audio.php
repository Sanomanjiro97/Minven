<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$sender_id = (int) $_SESSION['user_id'];
$receiver_id = isset($_POST['receiver_id']) ? intval($_POST['receiver_id']) : 0;

if ($receiver_id <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid receiver']);
    exit();
}

// Check if audio file was uploaded
if (isset($_FILES['audio']) && $_FILES['audio']['error'] == 0) {
    // Create uploads directory if it doesn't exist
    $upload_dir = '../uploads/audio/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    $original_name = isset($_FILES['audio']['name']) ? $_FILES['audio']['name'] : '';
    $file_extension = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));

    $allowed_extensions = ['wav', 'webm', 'ogg', 'mp4', 'm4a'];
    if (!in_array($file_extension, $allowed_extensions, true)) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Invalid audio type']);
        exit();
    }

    $mimeType = '';
    if (class_exists('finfo')) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($_FILES['audio']['tmp_name']) ?: '';
    }
    if (!$mimeType && isset($_FILES['audio']['type'])) {
        $mimeType = (string) $_FILES['audio']['type'];
    }
    $mimeType = strtolower(trim($mimeType));

    // Generate unique filename
    $filename = 'audio_' . time() . '_' . uniqid() . '.' . $file_extension;
    $filepath = $upload_dir . $filename;
    
    // Move uploaded file
    if (move_uploaded_file($_FILES['audio']['tmp_name'], $filepath)) {
        // Create HTML for audio player
        $type_attr = $mimeType ? ' type="' . htmlspecialchars($mimeType, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"' : '';
        $audio_html = '<audio controls preload="metadata"><source src="../uploads/audio/' . $filename . '"' . $type_attr . '>Browser Anda tidak mendukung tag audio.</audio>';
        
        // Insert message into database
        $stmt = $conn->prepare("INSERT INTO messages (sender_id, receiver_id, message) VALUES (?, ?, ?)");
        $stmt->bind_param("iis", $sender_id, $receiver_id, $audio_html);
        
        if ($stmt->execute()) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
        } else {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Database error']);
        }
        $stmt->close();
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Failed to save audio file']);
    }
} else {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'No audio file received']);
}
?>
