<?php
// Aktifkan error reporting untuk debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start output buffering to catch any unwanted output
ob_start();

session_start();
require_once '../../config.php';

// Clear any output that might have been generated
ob_clean();

// Set header JSON di awal
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'status' => 'error',
        'message' => 'Sesi tidak valid. Silakan login kembali.',
        'redirect' => '../../index.php'
    ]);
    exit();
}

// Validasi input
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo json_encode(['status' => 'error', 'message' => 'ID pembelian tidak valid']);
    exit();
}

$purchase_id = (int)$_GET['id'];

try {
    // Query untuk mengambil detail pembelian
    $sql = "SELECT 
            ddp.*,
            b.nama_barang,
            b.kode_barang,
            s.nama_satuan as satuan,
            CASE 
                WHEN ddp.barang_id IS NULL THEN ddp.keterangan
                WHEN ddp.keterangan IS NOT NULL AND ddp.keterangan != '' THEN CONCAT(b.nama_barang, ' (', ddp.keterangan, ')')
                ELSE b.nama_barang 
            END as display_name
            FROM detail_direct_purchase ddp 
            LEFT JOIN barang b ON ddp.barang_id = b.id 
            LEFT JOIN satuan s ON b.satuan_id = s.id
            WHERE ddp.direct_purchase_id = ?";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Database prepare error: " . $conn->error);
    }
    
    $stmt->bind_param('i', $purchase_id);
    if (!$stmt->execute()) {
        throw new Exception("Database execute error: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    if (!$result) {
        throw new Exception("Database result error: " . $stmt->error);
    }
    
    $items = [];
    while ($row = $result->fetch_assoc()) {
        $items[] = [
            'id' => (int)$row['id'],
            'barang_id' => $row['barang_id'] ? (int)$row['barang_id'] : null,
            'kode_barang' => $row['kode_barang'] ?: '',
            'nama_barang' => $row['display_name'] ?: '',
            'jumlah' => (float)$row['jumlah'],
            'satuan' => $row['satuan'] ?: 'pcs',
            'keterangan' => $row['keterangan'] ?: '',
            'foto' => $row['foto'] ?: ''
        ];
    }
    
    echo json_encode([
        'status' => 'success',
        'items' => $items
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
} catch (Error $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'System error: ' . $e->getMessage()
    ]);
}

// Flush output buffer
ob_end_flush();
?>