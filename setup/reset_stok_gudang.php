<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/access_check.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

if (!checkAccess('reset_stok', 'view') && !checkAccess('setup', 'view')) {
    $_SESSION['error'] = 'Anda tidak memiliki akses ke halaman Setup Reset Stok!';
    header('Location: /minven_pro/dashboard.php');
    exit();
}

header('Location: index.php');
exit();
