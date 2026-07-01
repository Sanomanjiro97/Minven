<?php
session_start();
require_once '../../config.php';

if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if (!isset($_GET['gudang_id']) || empty($_GET['gudang_id']) || !isset($_GET['barang_id']) || empty($_GET['barang_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Parameter tidak lengkap']);
    exit();
}

$gudang_id = $_GET['gudang_id'];
$barang_id = $_GET['barang_id'];
$detail_barang = $_GET['detail_barang'] ?? '';

try {
    // Query untuk mendapatkan stok tersedia
    $sql = "SELECT jumlah FROM gudang_stok 
            WHERE gudang_id = ? AND barang_id = ? AND detail_barang = ?";
    
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        throw new Exception("Prepare statement failed: " . $conn->error);
    }
    
    $stmt->bind_param("iis", $gudang_id, $barang_id, $detail_barang);
    if (!$stmt->execute()) {
        throw new Exception("Execute statement failed: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $stok = $row['jumlah'];
        
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'stok' => $stok]);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Stok tidak ditemukan', 'stok' => 0]);
    }
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>