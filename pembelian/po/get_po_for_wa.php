<?php
session_start();
require_once '../../config.php';

if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid PO ID']);
    exit();
}

$po_id = (int)$_GET['id'];

$sql = "SELECT po.*, s.nama_supplier, s.alamat as alamat_supplier, s.telepon as telepon_supplier, u.nama as created_by_name
        FROM purchase_order po
        LEFT JOIN supplier s ON po.supplier_id = s.id
        LEFT JOIN users u ON po.created_by = u.id
        WHERE po.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $po_id);
$stmt->execute();
$po = $stmt->get_result()->fetch_assoc();

if (!$po) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'PO not found']);
    exit();
}

$sql_items = "SELECT dpo.*, b.kode_barang, b.nama_barang, s.nama_satuan
              FROM detail_purchase_order dpo
              LEFT JOIN barang b ON dpo.barang_id = b.id
              LEFT JOIN satuan s ON b.satuan_id = s.id
              WHERE dpo.purchase_order_id = ? AND (dpo.status IS NULL OR dpo.status != 'rejected')
              ORDER BY b.kode_barang";
$stmt_items = $conn->prepare($sql_items);
$stmt_items->bind_param('i', $po_id);
$stmt_items->execute();
$items = $stmt_items->get_result()->fetch_all(MYSQLI_ASSOC);

header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'no_po' => $po['no_po'],
    'tanggal' => date('d/m/Y', strtotime($po['tanggal'])),
    'supplier' => $po['nama_supplier'],
    'telepon' => $po['telepon_supplier'],
    'items' => $items
]);
exit();
?>