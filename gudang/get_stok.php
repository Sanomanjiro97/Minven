<?php
session_start();
require_once '../config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

if (!isset($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'ID tidak ditemukan']);
    exit();
}

$id = intval($_GET['id']);

$query = "SELECT 
    gs.id, 
    b.kode_barang, 
    b.nama_barang, 
    gs.stok_awal, 
    gs.stok_terpakai,
    gs.stok_minimum,
    gs.expire_date
FROM gudang_stok gs
JOIN barang b ON gs.barang_id = b.id
WHERE gs.id = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $data = $result->fetch_assoc();
    
    // Handle expire_date
    if ($data['expire_date'] && $data['expire_date'] != '0000-00-00') {
        $data['expire_date'] = $data['expire_date'];
    } else {
        $data['expire_date'] = '';
    }
    
    echo json_encode($data);
} else {
    echo json_encode(['success' => false, 'message' => 'Data tidak ditemukan']);
}
?>