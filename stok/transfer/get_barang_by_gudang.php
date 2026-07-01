<?php
session_start();
require_once '../../config.php';

if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if (!isset($_GET['gudang_id']) || empty($_GET['gudang_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'ID Gudang tidak valid']);
    exit();
}

$gudang_id = $_GET['gudang_id'];

try {
    // Query untuk mendapatkan semua barang yang ada di gudang tertentu beserta stok yang tersedia
    // Untuk transfer, hanya barang yang ada stok tersedia di gudang asal yang bisa ditransfer
    $sql = "SELECT b.id, b.kode_barang, b.nama_barang, s.nama_satuan, 
                   COALESCE(gs.stok_awal, 0) as stok_awal,
                   COALESCE(gs.stok_terpakai, 0) as stok_terpakai,
                   COALESCE((gs.stok_awal - gs.stok_terpakai), 0) as stok_tersedia
            FROM barang b
            LEFT JOIN gudang_stok gs ON b.id = gs.barang_id AND gs.gudang_id = ?
            LEFT JOIN satuan s ON b.satuan_id = s.id
            ORDER BY b.nama_barang";
    
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        throw new Exception("Prepare statement failed: " . $conn->error);
    }
    
    $stmt->bind_param("i", $gudang_id);
    if (!$stmt->execute()) {
        throw new Exception("Execute statement failed: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    $barang = [];
    
    while ($row = $result->fetch_assoc()) {
        $barang[] = [
            'id' => $row['id'],
            'kode_barang' => $row['kode_barang'],
            'nama_barang' => $row['nama_barang'],
            'nama_satuan' => $row['nama_satuan'],
            'stok_awal' => $row['stok_awal'],
            'stok_terpakai' => $row['stok_terpakai'],
            'stok_tersedia' => $row['stok_tersedia']
        ];
    }
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'barang' => $barang]);
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?> 