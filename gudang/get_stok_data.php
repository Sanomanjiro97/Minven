<?php
session_start();
require_once '../config.php';

// Pastikan tidak ada output sebelum header
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

if (!isset($_GET['gudang_id'])) {
    echo json_encode(['success' => false, 'message' => 'ID gudang tidak ditemukan']);
    exit();
}

$gudang_id = intval($_GET['gudang_id']);

// Query untuk data stok gudang
$sql = "SELECT
            gs.id,
            gs.barang_id,
            b.kode_barang,
            b.nama_barang,
            k.nama_kategori,
            s.nama_satuan,
            gs.stok_awal,
            gs.stok_terpakai,
            (gs.stok_awal - gs.stok_terpakai) as stok_akhir,
            gs.stok_minimum,
            gs.expire_date,
            gs.updated_at,
            u.nama as updated_by
        FROM gudang_stok gs
        LEFT JOIN barang b ON gs.barang_id = b.id
        LEFT JOIN kategori k ON b.kategori_id = k.id
        LEFT JOIN satuan s ON b.satuan_id = s.id
        LEFT JOIN users u ON gs.modified_by = u.id
        WHERE gs.gudang_id = ?
        ORDER BY b.nama_barang";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $gudang_id);
$stmt->execute();
$result = $stmt->get_result();

$items = [];
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        // Handle expire_date
        if ($row['expire_date'] && $row['expire_date'] != '0000-00-00') {
            $row['expire_date'] = date('d/m/Y', strtotime($row['expire_date']));
        } else {
            $row['expire_date'] = '-';
        }
        $items[] = $row;
    }
    echo json_encode(['success' => true, 'items' => $items]);
} else {
    echo json_encode(['success' => true, 'items' => []]);
}
?>