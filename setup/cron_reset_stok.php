<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/reset_stok_harian.php';

date_default_timezone_set('Asia/Jakarta');

$out = run_reset_stok_harian($conn);

if (PHP_SAPI !== 'cli') {
    header('Content-Type: application/json; charset=utf-8');
}
echo json_encode($out, JSON_UNESCAPED_UNICODE);
?>
