<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/product_catalog.php';
require_once __DIR__ . '/../_init.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

$success = false;
$data = [];
$message = '';

try {
    kasir_require_login();

    $conn = main_db_conn();
    if (!$conn instanceof mysqli || $conn->connect_error) {
        throw new Exception('Koneksi database gagal.');
    }

    $gudangId = isset($_GET['gudang_id']) ? (int)($_GET['gudang_id']) : 0;
    $products = product_catalog_fetch_active_products_for_sync($conn);

    if ($gudangId > 0) {
        $products = array_values(array_filter($products, static function ($product) use ($gudangId) {
            return (int)($product['gudang_id'] ?? 0) === $gudangId;
        }));
    }

    $data = [
        'products' => $products,
        'total' => count($products),
    ];
    $success = true;
    $message = count($products) > 0 ? 'Produk berhasil dimuat.' : 'Belum ada produk aktif untuk gudang ini.';
} catch (Throwable $e) {
    http_response_code(500);
    $message = $e->getMessage();
}

echo json_encode([
    'success' => $success,
    'message' => $message,
    'data' => $data,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
