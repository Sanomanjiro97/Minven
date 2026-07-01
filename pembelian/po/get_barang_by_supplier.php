<?php
session_start();
require_once '../../config.php';

if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized', 'data' => []]);
    exit();
}

$supplier_id = isset($_GET['supplier_id']) ? (int)$_GET['supplier_id'] : 0;

$has_harga_po = db_has_column($conn, 'barang', 'harga_po');

$sql = "SELECT b.id, b.kode_barang, b.nama_barang, b.satuan_id, s.nama_satuan, b.harga_beli" .
        ($has_harga_po ? ", b.harga_po, COALESCE(NULLIF(b.harga_po, 0), b.harga_beli) AS harga_po_default" : ", b.harga_beli AS harga_po_default") . ",
               ksb.satuan_asal_id as konversi_satuan_id,
               s2.nama_satuan as konversi_nama_satuan,
               ksb.nilai_konversi
        FROM barang b
        LEFT JOIN satuan s ON b.satuan_id = s.id
        LEFT JOIN konversi_satuan_barang ksb ON b.id = ksb.barang_id AND b.satuan_id = ksb.satuan_tujuan_id
        LEFT JOIN satuan s2 ON ksb.satuan_asal_id = s2.id
        WHERE b.supplier_id = ?
        ORDER BY b.kode_barang";

$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $supplier_id);
$stmt->execute();
$result = $stmt->get_result();

$items = [];
while ($row = $result->fetch_assoc()) {
    $items[] = $row;
}

header('Content-Type: application/json');
echo json_encode(['success' => true, 'message' => 'Data loaded successfully', 'data' => $items]);
?>
