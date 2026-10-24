<?php
/**
 * API: Search Product
 * Used for: Fast checkout product search and barcode scanning
 */

session_start();
require_once('../../_init.php');

header('Content-Type: application/json');

try {
    $query = $_POST['q'] ?? '';
    
    if (empty($query)) {
        echo json_encode(['success' => false, 'message' => 'Query tidak boleh kosong']);
        exit;
    }

    // Search by barcode or product name
    $sql = "SELECT id, barang_nama, harga_jual, stok, barcode 
            FROM barang 
            WHERE (barcode = ? OR barang_nama LIKE ? OR barang_kode LIKE ?)
            AND is_deleted = 0
            LIMIT 1";
    
    $stmt = $db->prepare($sql);
    $searchTerm = "%$query%";
    $stmt->bind_param('sss', $query, $searchTerm, $searchTerm);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $product = $result->fetch_assoc();
        echo json_encode([
            'success' => true,
            'data' => [
                'id' => $product['id'],
                'name' => $product['barang_nama'],
                'price' => $product['harga_jual'],
                'stock' => $product['stok'],
                'barcode' => $product['barcode']
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Produk tidak ditemukan']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
