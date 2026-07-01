<?php
session_start();

// Handle database connection manually to avoid HTML output on error
$conn = null;
try {
    require_once 'config.php';
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// Check access manually to avoid redirects
function checkAccessDirect($menu_name, $action = 'view') {
    global $conn;
    
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role_id'])) {
        return false;
    }
    
    // Simplified access check for AJAX requests
    $role_id = $_SESSION['role_id'];
    $sql = "SELECT * FROM menu_access WHERE role_id = ? AND menu_name = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('is', $role_id, $menu_name);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $access = $result->fetch_assoc();
        return $access['can_edit'] == 1; // Allow if can edit
    }
    
    return false;
}

header('Content-Type: application/json');

// Cek apakah request adalah POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Cek apakah user sudah login dan memiliki akses
if (!isset($_SESSION['user_id']) || !checkAccessDirect('barang', 'edit')) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Ambil data dari POST
$barang_id = isset($_POST['barang_id']) ? intval($_POST['barang_id']) : 0;
$baku_non_baku = isset($_POST['baku_non_baku']) ? $_POST['baku_non_baku'] : 'non_baku';
$action = isset($_POST['action']) ? $_POST['action'] : '';

// Validasi data
if ($barang_id <= 0 || !in_array($baku_non_baku, ['baku', 'non_baku']) || $action !== 'update_jenis') {
    echo json_encode(['success' => false, 'message' => 'Invalid data']);
    exit;
}

// Update database
try {
    $sql = "UPDATE barang SET baku_non_baku = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $baku_non_baku, $barang_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Jenis barang berhasil diupdate']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal mengupdate jenis barang']);
    }
    
    $stmt->close();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

$conn->close();
?>