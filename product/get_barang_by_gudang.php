<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/access_check.php';

$conn = $conn ?? null;
if (!($conn instanceof mysqli)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Koneksi database tidak tersedia.']);
    exit();
}

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if (!checkAccess('product', 'add')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit();
}

if (!isset($_GET['gudang_id']) || (string)$_GET['gudang_id'] === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID Gudang tidak valid']);
    exit();
}

$gudang_id = (int)$_GET['gudang_id'];
if ($gudang_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID Gudang tidak valid']);
    exit();
}

$allowed = false;
$rows = get_accessible_gudang_list($conn);
foreach ($rows as $r) {
    if ((int)($r['id'] ?? 0) === $gudang_id) {
        $allowed = true;
        break;
    }
}
if (!$allowed) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit();
}

try {
    $sql = "SELECT b.id, b.kode_barang, b.nama_barang, s.nama_satuan,
                   COALESCE(gs.stok_awal, 0) as stok_awal,
                   COALESCE(gs.stok_terpakai, 0) as stok_terpakai,
                   COALESCE((gs.stok_awal - gs.stok_terpakai), 0) as stok_tersedia
            FROM gudang_stok gs
            INNER JOIN barang b ON b.id = gs.barang_id
            LEFT JOIN satuan s ON b.satuan_id = s.id
            WHERE gs.gudang_id = ?
              AND (gs.stok_awal - gs.stok_terpakai) > 0
            ORDER BY b.nama_barang";

    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        throw new Exception($conn->error);
    }
    $stmt->bind_param("i", $gudang_id);
    if (!$stmt->execute()) {
        throw new Exception($stmt->error);
    }
    $result = $stmt->get_result();
    $barang = [];
    while ($row = $result->fetch_assoc()) {
        $barang[] = [
            'id' => (int)$row['id'],
            'kode_barang' => (string)($row['kode_barang'] ?? ''),
            'nama_barang' => (string)($row['nama_barang'] ?? ''),
            'nama_satuan' => (string)($row['nama_satuan'] ?? ''),
            'stok_awal' => (int)($row['stok_awal'] ?? 0),
            'stok_terpakai' => (int)($row['stok_terpakai'] ?? 0),
            'stok_tersedia' => (int)($row['stok_tersedia'] ?? 0),
        ];
    }
    $stmt->close();

    echo json_encode(['success' => true, 'barang' => $barang]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan saat memuat data']);
}
