<?php
require_once '../../config.php';
/** @var mysqli|null $conn */
$conn = $conn ?? null;

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$supplier_id = isset($_GET['supplier_id']) ? (int)$_GET['supplier_id'] : 0;

if ($supplier_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Supplier tidak valid']);
    exit();
}

if (!$conn instanceof mysqli) {
    echo json_encode(['success' => false, 'message' => 'Koneksi database tidak tersedia']);
    exit();
}

$has_harga_po = db_has_column($conn, 'barang', 'harga_po');
$sql = "SELECT pt.*, b.kode_barang, b.nama_barang, b.harga_beli" .
        ($has_harga_po ? ", b.harga_po, COALESCE(NULLIF(b.harga_po, 0), b.harga_beli) AS harga_po_default" : ", b.harga_beli AS harga_po_default") . ",
        s.nama_satuan
        FROM po_template pt
        LEFT JOIN barang b ON pt.barang_id = b.id
        LEFT JOIN satuan s ON b.satuan_id = s.id
        WHERE pt.supplier_id = ? AND pt.is_active = 1
        ORDER BY b.kode_barang";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $supplier_id);
$stmt->execute();
$result = $stmt->get_result();

$items = [];
while ($row = $result->fetch_assoc()) {
    $items[] = [
        'id' => $row['barang_id'],
        'kode_barang' => $row['kode_barang'],
        'nama_barang' => $row['nama_barang'],
        'nama_satuan' => $row['nama_satuan'] ?? '',
        'jumlah' => $row['jumlah'],
        'harga_beli' => $row['harga_beli'],
        'harga_po_default' => $row['harga_po_default'],
        'keterangan' => $row['keterangan'] ?? ''
    ];
}

if (empty($items)) {
    echo json_encode(['success' => false, 'message' => 'Tidak ada template untuk supplier ini']);
    exit();
}

echo json_encode(['success' => true, 'data' => $items]);
exit();
