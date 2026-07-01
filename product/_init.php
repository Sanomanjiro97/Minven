<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/access_check.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: " . url_for('index.php'));
    exit();
}

function h($str) {
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

function ensure_product_tables($conn) {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $tables = [
        'product' => "CREATE TABLE IF NOT EXISTS `product` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `nama_product` varchar(150) NOT NULL,
            `gudang_id` int(11) NOT NULL,
            `created_by` int(11) DEFAULT NULL,
            `created_at` datetime DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_product_gudang_id` (`gudang_id`),
            KEY `idx_product_created_by` (`created_by`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
        'product_detail' => "CREATE TABLE IF NOT EXISTS `product_detail` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `product_id` int(11) NOT NULL,
            `barang_id` int(11) NOT NULL,
            `qty` int(11) NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`),
            KEY `idx_product_detail_product_id` (`product_id`),
            KEY `idx_product_detail_barang_id` (`barang_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
    ];

    foreach ($tables as $name => $createSql) {
        $stmt = $conn->prepare("SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1");
        $exists = false;
        if ($stmt) {
            $stmt->bind_param('s', $name);
            $stmt->execute();
            $res = $stmt->get_result();
            $exists = $res && $res->num_rows > 0;
            $stmt->close();
        }
        if (!$exists) {
            $conn->query($createSql);
        }
    }
}

ensure_product_tables($conn);
