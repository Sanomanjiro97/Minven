<?php
session_start();
require_once '../../config.php';
require_once '../../includes/access_check.php';
require_once '../../includes/menu_access_helper.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit();
}

// Check access untuk menu approve - user harus memiliki akses edit untuk purchase_order atau akses view untuk approve
if (!checkAccess('purchase_order', 'edit') && !checkAccess('approve', 'view')) {
    $_SESSION['error'] = "Anda tidak memiliki akses untuk melakukan approval PO";
    header("Location: index.php");
    exit();
}

// Validasi parameter
if (!isset($_GET['id']) || !isset($_GET['status'])) {
    $_SESSION['error'] = "Data tidak valid";
    header("Location: index.php");
    exit();
}

$id = $_GET['id'];
$status = $_GET['status'];

// Validasi status
if (!in_array($status, ['approved', 'rejected'])) {
    $_SESSION['error'] = "Status tidak valid";
    header("Location: index.php");
    exit();
}

// Debug untuk memeriksa nilai yang akan diupdate
error_log("Debug - ID: $id, Status: $status, User: " . $_SESSION['user_id']);

// Update status PO di tabel purchase_order
$sql = "UPDATE purchase_order SET status = ? WHERE id = ?";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    $_SESSION['error'] = "Database error: " . $conn->error;
    header("Location: index.php?" . time());
    exit();
}
$stmt->bind_param("si", $status, $id);

if ($stmt->execute()) {
    // Debug untuk konfirmasi update berhasil
    error_log("Update berhasil - Rows affected: " . $stmt->affected_rows);
    
    if ($stmt->affected_rows > 0) {
        $_SESSION['success'] = "Status PO berhasil diupdate ke " . strtoupper($status);
    } else {
        $_SESSION['error'] = "Tidak ada perubahan status PO";
    }
} else {
    error_log("Execute failed: " . $stmt->error);
    $_SESSION['error'] = "Gagal mengupdate status PO: " . $stmt->error;
}

$stmt->close();
$conn->close();

// Redirect tanpa cache
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Location: index.php?" . time());
exit();
?>