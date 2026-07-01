<?php
session_start();
require_once '../../config.php';
require_once '../../includes/access_check.php';
require_once '../../includes/menu_access_helper.php';

if (!isset($_SESSION['user_id']) || !isset($_GET['id'])) {
    $_SESSION['error'] = "Akses ditolak";
    header("Location: index.php");
    exit();
}

// Check access untuk menu purchase_order delete
if (!checkAccess('purchase_order', 'delete')) {
    $_SESSION['error'] = "Anda tidak memiliki akses untuk menghapus PO";
    header("Location: index.php");
    exit();
}

$id = $_GET['id'];

// Hapus detail PO terlebih dahulu
$sql_detail = "DELETE FROM detail_purchase_order WHERE purchase_order_id = ?";
$stmt_detail = $conn->prepare($sql_detail);
$stmt_detail->bind_param("i", $id);
$stmt_detail->execute();
$stmt_detail->close();

// Hapus header PO
$sql = "DELETE FROM purchase_order WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    $_SESSION['success'] = "PO berhasil dihapus";
} else {
    $_SESSION['error'] = "Gagal menghapus PO: " . $stmt->error;
}

$stmt->close();
$conn->close();
header("Location: index.php");
exit();
?>