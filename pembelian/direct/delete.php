<?php
session_start();
require_once '../../config.php';

if (!isset($_SESSION['user_id'])) {
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json');
        http_response_code(401);
        echo json_encode([
            'status' => 'error',
            'message' => 'Sesi tidak valid. Silakan login kembali.',
            'redirect' => '../../index.php'
        ]);
    } else {
        header("Location: ../../index.php");
    }
    exit();
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    $_SESSION['error'] = "ID pembelian tidak valid!";
    header("Location: index.php");
    exit();
}

// Cek apakah pembelian masih menunggu
$sql = "SELECT status, no_transaksi FROM direct_purchase WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error'] = "Data pembelian tidak ditemukan!";
    header("Location: index.php");
    exit();
}

$purchase = $result->fetch_assoc();

if ($purchase['status'] !== 'menunggu') {
    $_SESSION['error'] = "Hanya pembelian dengan status 'Menunggu' yang dapat dihapus!";
    header("Location: index.php");
    exit();
}

try {
    // Mulai transaksi
    $conn->begin_transaction();

    // Hapus detail pembelian
    $sql = "DELETE FROM detail_direct_purchase WHERE direct_purchase_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $id);
    if (!$stmt->execute()) {
        throw new Exception("Gagal menghapus detail pembelian: " . $conn->error);
    }

    // Hapus header pembelian
    $sql = "DELETE FROM direct_purchase WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $id);
    if (!$stmt->execute()) {
        throw new Exception("Gagal menghapus data pembelian: " . $conn->error);
    }

    // Commit transaksi
    $conn->commit();
    
    $_SESSION['success'] = "Pembelian dengan No. Transaksi " . htmlspecialchars($purchase['no_transaksi']) . " berhasil dihapus!";

} catch (Exception $e) {
    // Rollback jika terjadi error
    $conn->rollback();
    $_SESSION['error'] = $e->getMessage();
}

header("Location: index.php");
exit();