<?php
session_start();
require_once '../config.php';
require_once '../includes/page_access_check.php';
require_once '../includes/access_check.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

$target = '';
if (hasAccess('laporan_stok', 'view')) {
    $target = 'stok_gudang.php';
} elseif (hasAccess('laporan_pembelian', 'view')) {
    $target = 'pembelian_direct.php';
} elseif (hasAccess('laporan_transfer', 'view')) {
    $target = 'stok_transfer.php';
} elseif (hasAccess('laporan', 'view')) {
    $target = 'stok_gudang.php';
}

if ($target === '') {
    $_SESSION['error'] = 'Akses laporan tidak tersedia untuk akun Anda';
    header('Location: ../dashboard.php');
    exit();
}

header("Location: " . $target);
exit();
?> 
