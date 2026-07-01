<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Not authenticated']);
    exit();
}

// Check if the request is a file upload
if (!isset($_FILES['file']) || $_FILES['file']['error'] != 0) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'No file uploaded or upload error']);
    exit();
}

$upload_dir = '../uploads/chat/';

// Create directory if it doesn't exist
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

$file_info = pathinfo($_FILES['file']['name']);
$file_extension = strtolower($file_info['extension']);

// Validate file type
$allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'zip', 'rar', 'mp3', 'wav', 'ogg'];

if (!in_array($file_extension, $allowed_extensions)) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'File type not allowed']);
    exit();
}

// Generate unique filename
$new_filename = uniqid() . '.' . $file_extension;
$file_destination = $upload_dir . $new_filename;

if (move_uploaded_file($_FILES['file']['tmp_name'], $file_destination)) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true, 
        'file_path' => $file_destination,
        'file_type' => $file_extension,
        'file_name' => $file_info['basename']
    ]);
} else {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Failed to move uploaded file']);
}
