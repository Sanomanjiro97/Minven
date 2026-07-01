<?php
session_start();
require_once '../config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'User not logged in']);
    exit();
}

// Clear unauthorized access from session
if (isset($_SESSION['unauthorized_access'])) {
    unset($_SESSION['unauthorized_access']);
}

echo json_encode(['success' => true]);
?> 