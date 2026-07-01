<?php
session_start();
require_once '../../config.php';
require_once '../../includes/access_check.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

if (!isset($_GET['gudang_id'])) {
    echo json_encode(['success' => false, 'message' => 'ID gudang tidak ditemukan']);
    exit();
}

$gudang_id = (int)$_GET['gudang_id'];
if ($gudang_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID gudang tidak valid']);
    exit();
}

$sql = "SELECT
            COALESCE(gs.id, 0) as stok_id,
            b.id as id,
            b.kode_barang,
            b.barcode,
            b.barcode_dus,
            b.nama_barang,
            b.satuan_id,
            s.nama_satuan,
            COALESCE(gs.stok_awal, 0) as stok_awal,
            COALESCE(gs.stok_terpakai, 0) as stok_terpakai,
            COALESCE((gs.stok_awal - gs.stok_terpakai), 0) as stok_tersedia
        FROM barang b
        LEFT JOIN gudang_stok gs ON gs.barang_id = b.id AND gs.gudang_id = ?
        LEFT JOIN satuan s ON b.satuan_id = s.id
        ORDER BY b.nama_barang";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Database prepare error']);
    exit();
}

$stmt->bind_param('i', $gudang_id);
if (!$stmt->execute()) {
    echo json_encode(['success' => false, 'message' => 'Database execute error']);
    exit();
}

$result = $stmt->get_result();
$barang = [];
while ($row = $result->fetch_assoc()) {
    $barang[] = [
        'stok_id' => (int)($row['stok_id'] ?? 0),
        'id' => (int)($row['id'] ?? 0),
        'kode_barang' => $row['kode_barang'] ?? '',
        'barcode' => $row['barcode'] ?? '',
        'barcode_dus' => $row['barcode_dus'] ?? '',
        'nama_barang' => $row['nama_barang'] ?? '',
        'satuan_id' => (int)($row['satuan_id'] ?? 0),
        'nama_satuan' => $row['nama_satuan'] ?? '',
        'stok_awal' => (int)($row['stok_awal'] ?? 0),
        'stok_terpakai' => (int)($row['stok_terpakai'] ?? 0),
        'stok_tersedia' => (int)($row['stok_tersedia'] ?? 0)
    ];
}

echo json_encode(['success' => true, 'barang' => $barang]);
