<?php
session_start();
require_once '../config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'User not logged in']);
    exit();
}

// Check if there's an unauthorized access attempt
if (isset($_SESSION['unauthorized_access'])) {
    $unauthorized_access = $_SESSION['unauthorized_access'];
    
    // Clear it from session after sending
    unset($_SESSION['unauthorized_access']);
    
    echo json_encode([
        'unauthorized_access' => $unauthorized_access
    ]);
} else {
    echo json_encode([
        'unauthorized_access' => null
    ]);
}
?> 