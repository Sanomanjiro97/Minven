<?php
// Enable error reporting for debugging, but don't display to prevent HTML output
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Start output buffering to catch any unexpected output
ob_start();

session_start();
require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
    header('HTTP/1.1 401 Unauthorized');
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'update_jenis') {
    $barang_id = intval($_POST['barang_id']);
    $baku_non_baku = $_POST['baku_non_baku'];
    
    // Validasi input
    if ($barang_id <= 0) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'ID barang tidak valid']);
        exit();
    }
    
    if (!in_array($baku_non_baku, ['baku', 'non_baku'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Nilai jenis tidak valid']);
        exit();
    }
    
    // Update database - first check if column exists
    $sql = "UPDATE barang SET baku_non_baku = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        // If prepare fails, the column might not exist
        // Try alternative approach or inform user to add the column
        error_log("SQL prepare failed: Column baku_non_baku mungkin tidak ada di tabel barang - " . $conn->error);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Kolom baku_non_baku tidak ditemukan di database. Silakan tambahkan kolom ini ke tabel barang.']);
        exit();
    }
    
    $stmt->bind_param("si", $baku_non_baku, $barang_id);
    
    if ($stmt->execute()) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'Jenis barang berhasil diupdate']);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Gagal mengupdate jenis barang']);
    }
    
    $stmt->close();
} else {
    header('HTTP/1.1 400 Bad Request');
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Permintaan tidak valid']);
}

// Clean any output buffer - but only handle actual unexpected output
$output = ob_get_clean();
if ($output && trim($output) !== '' && !str_contains($output, '{"success":')) {
    // If there was unexpected output (not JSON), log it and return error
    error_log("Unexpected output in process_jenis.php: " . $output);
    // Don't send the unexpected output, send proper JSON error instead
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Server error occurred']);
} else if ($output && trim($output) !== '') {
    // If the output is JSON, just send it as is
    echo $output;
}

// Only close connection if it exists and is valid
if (isset($conn) && $conn instanceof mysqli && !$conn->connect_error) {
    $conn->close();
}