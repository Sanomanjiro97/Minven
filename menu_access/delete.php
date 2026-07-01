<?php
session_start();
require_once '../config.php';
require_once '../includes/page_access_check.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

// Check access to delete menu access
if (!hasAccess('menu_access', 'delete')) {
    $_SESSION['error'] = "Akses tidak diizinkan untuk menghapus Menu Access";
    header("Location: index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error'] = "Metode request tidak valid!";
    header("Location: index.php");
    exit();
}

$id = $_POST['id'] ?? 0;

if (!$id) {
    $_SESSION['error'] = "ID Menu Access tidak valid!";
    header("Location: index.php");
    exit();
}

// Get menu access details for confirmation
$sql = "SELECT ma.*, r.nama_role 
        FROM menu_access ma 
        INNER JOIN roles r ON ma.role_id = r.id 
        WHERE ma.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error'] = "Menu Access tidak ditemukan!";
    header("Location: index.php");
    exit();
}

$menu_access = $result->fetch_assoc();

// Get menu display name
$menu_names = [
    'dashboard' => 'Dashboard',
    'master' => 'Master Data',
    'barang' => 'Master Barang',
    'kategori' => 'Master Kategori',
    'supplier' => 'Master Supplier',
    'satuan' => 'Master Satuan',
    'mapping_items' => 'Mapping Items',
    'gudang' => 'Master Gudang',
    'transaksi' => 'Transaksi',
    'stok_masuk' => 'Stok Masuk',
    'stok_keluar' => 'Stok Keluar',
    'stok_transfer' => 'Stok Transfer',
    'pembelian' => 'Pembelian',
    'purchase_order' => 'Purchase Order',
    'pembelian_direct' => 'Pembelian Direct',
    'vendor_refund' => 'Refund Vendor',
    'manufacture' => 'Manufaktur',
    'surat_jalan' => 'Surat Jalan',
    'laporan' => 'Laporan',
    'laporan_stok' => 'Laporan Stok',
    'laporan_pembelian' => 'Laporan Pembelian',
    'laporan_transfer' => 'Laporan Transfer',
    'user' => 'Manajemen User',
    'menu_access' => 'Menu Access Management',
    'setup' => 'Setup',
    'reset_stok' => 'Reset Stok Gudang'
];

$menu_display_name = $menu_names[$menu_access['menu_name']] ?? $menu_access['menu_name'];

// Check if this is a critical menu access (admin role or important menu)
$is_critical = false;
$critical_roles = ['admin', 'administrator', 'super admin'];
$critical_menus = ['dashboard', 'user', 'menu_access'];

if (in_array(strtolower($menu_access['nama_role']), $critical_roles) || 
    in_array($menu_access['menu_name'], $critical_menus)) {
    $is_critical = true;
}

// Delete the menu access
$delete_sql = "DELETE FROM menu_access WHERE id = ?";
$delete_stmt = $conn->prepare($delete_sql);
$delete_stmt->bind_param('i', $id);

if ($delete_stmt->execute()) {
    // Log the deletion
    $log_sql = "INSERT INTO activity_log (user_id, action, table_name, record_id, description, ip_address) 
                VALUES (?, ?, ?, ?, ?, ?)";
    $log_stmt = $conn->prepare($log_sql);
    $description = "Menghapus menu access untuk role '{$menu_access['nama_role']}' pada menu '{$menu_display_name}'";
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $log_stmt->bind_param('ississ', $_SESSION['user_id'], 'DELETE', 'menu_access', $id, $description, $ip_address);
    $log_stmt->execute();

    $_SESSION['success'] = "Menu access berhasil dihapus!";
    
    if ($is_critical) {
        $_SESSION['warning'] = "Perhatian: Anda telah menghapus akses untuk role atau menu yang penting. Pastikan ini adalah tindakan yang diinginkan.";
    }
} else {
    $_SESSION['error'] = "Gagal menghapus menu access: " . $conn->error;
}

header("Location: index.php");
exit();
?> 
