<?php
require_once '../config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['supplier_id'])) {
    try {
        $supplier_id = (int)$_GET['supplier_id'];
        
        $sql = "SELECT b.id, b.kode_barang, b.nama_barang, s.nama_satuan, b.harga_beli 
                FROM barang b
                LEFT JOIN satuan s ON b.satuan_id = s.id
                WHERE b.supplier_id = ?
                ORDER BY b.kode_barang";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $supplier_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $barang = [];
        while ($row = $result->fetch_assoc()) {
            $barang[] = $row;
        }
        
        echo json_encode($barang);
        exit();
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
        exit();
    }
}

echo json_encode([]);
exit();