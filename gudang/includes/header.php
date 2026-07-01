<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

$header_gudang_id = isset($gudang_id) ? (int)$gudang_id : 0;
$header_gudang_nama = 'Gudang';
if ($header_gudang_id > 0) {
    $stmt_gudang = $conn->prepare("SELECT nama_gudang FROM gudang WHERE id = ?");
    if ($stmt_gudang) {
        $stmt_gudang->bind_param('i', $header_gudang_id);
        $stmt_gudang->execute();
        $row_gudang = $stmt_gudang->get_result()->fetch_assoc();
        if ($row_gudang && isset($row_gudang['nama_gudang']) && trim((string)$row_gudang['nama_gudang']) !== '') {
            $header_gudang_nama = (string)$row_gudang['nama_gudang'];
        }
        $stmt_gudang->close();
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($header_gudang_nama) ?> - Sistem Inventory</title>
    <link rel="icon" type="image/png" href="/minven_pro/asset/LOGO1.png">
    <link rel="shortcut icon" type="image/png" href="/minven_pro/asset/LOGO1.png">
    <link rel="apple-touch-icon" href="/minven_pro/asset/LOGO1.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <audio id="notificationSound" src="../asset/masuk.mp3" preload="auto"></audio>
    <audio id="warningSound" src="../asset/warning.mp3" preload="auto"></audio>
    <audio id="stokhabisSound" src="../asset/stokhabis.mp3" preload="auto"></audio>
    <style>
        .stok-aman { background-color: #d4edda; }
        .stok-minimum { background-color: #fff3cd; }
        .stok-habis { background-color: #f8d7da; }
    </style>
</head>
<body>
