<?php
session_start();
require_once '../config.php';
require_once '../includes/access_check.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'User not logged in']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Invalid request method']);
    exit();
}

$menu_name = $_POST['menu_name'] ?? '';
$action = $_POST['action'] ?? 'view';

if (empty($menu_name)) {
    echo json_encode(['error' => 'Menu name is required']);
    exit();
}

// Check access
$has_access = checkAccess($menu_name, $action);

echo json_encode([
    'has_access' => $has_access,
    'menu_name' => $menu_name,
    'action' => $action
]);
?> 