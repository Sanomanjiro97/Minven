<?php
/**
 * Auto-fix untuk memastikan menu_access terisi untuk user yang sedang login
 * File ini di-include otomatis dari config.php atau dashboard
 */

function ensureUserMenuAccess() {
    // Do not auto-insert menus. A missing row means the role has no access.
    return;

    $authConn = auth_db_conn();
    $mainConn = main_db_conn();
    
    if (!isset($_SESSION['user_id'])) {
        return;
    }
    
    $user_id = $_SESSION['user_id'];
    
    // Get user role(s)
    $sql = "SELECT role_id FROM user_roles WHERE user_id = ?";
    if (!$authConn || !($authConn instanceof mysqli) || $authConn->connect_error) return;
    $stmt = $authConn->prepare($sql);
    if (!$stmt) return;
    
    $stmt->bind_param('i', $user_id);
    if (!$stmt->execute()) {
        $stmt->close();
        return;
    }
    $result = $stmt->get_result();
    
    $role_ids = [];
    if ($result && $result instanceof mysqli_result) {
        while ($row = $result->fetch_assoc()) {
            $role_ids[] = $row['role_id'];
        }
    }
    $stmt->close();
    
    if (empty($role_ids) && isset($_SESSION['role_id'])) {
        $role_ids = [$_SESSION['role_id']];
    }
    
    if (empty($role_ids)) {
        return;
    }
    
    // Define all available menus
    $available_menus = [
        'dashboard' => ['submenus' => []],
        'master' => ['submenus' => ['barang', 'kategori', 'supplier', 'satuan', 'mapping_items', 'konversi_masukan']],
        'gudang' => ['submenus' => ['gudang', 'gudang_central', 'gudang_antapani', 'tambah_gudang']],
        'transaksi' => ['submenus' => ['stok_masuk', 'stok_keluar', 'stok_transfer', 'adjustment_in', 'adjustment_out']],
        'pembelian' => ['submenus' => ['purchase_order', 'approve', 'pembelian_direct', 'payment', 'vendor_refund', 'manufacture', 'surat_jalan']],
        'laporan' => ['submenus' => ['laporan_stok', 'laporan_po', 'laporan_pembelian', 'laporan_transfer', 'laporan_adjustment_in', 'laporan_adjustment_out']],
        'backoffice' => ['submenus' => ['backoffice', 'backoffice_dashboard', 'backoffice_reports_inventory', 'backoffice_reports_finance', 'backoffice_reports_po', 'backoffice_reports_direct', 'backoffice_reports_inventory_price', 'backoffice_reports_item_movement', 'backoffice_users', 'backoffice_roles']],
        'cashier' => ['submenus' => []],
        'order_management' => ['submenus' => ['orders', 'delivery', 'mobile']],
        'user' => ['submenus' => []],
        'setup' => ['submenus' => ['reset_stok', 'edit_nama_gudang', 'template_po', 'barcode', 'get_wa', 'menu_access']]
    ];
    
    // Flatten menu list
    $all_menus = [];
    foreach ($available_menus as $main_menu => $data) {
        $all_menus[$main_menu] = 1;
        foreach ($data['submenus'] as $submenu) {
            $all_menus[$submenu] = 1;
        }
    }
    
    // Add gudang menus from database
    if ($mainConn && $mainConn instanceof mysqli && !$mainConn->connect_error) {
        $gudang_result = @$mainConn->query("SELECT DISTINCT kode_gudang FROM gudang WHERE kode_gudang IS NOT NULL AND kode_gudang != ''");
    } else {
        $gudang_result = null;
    }
    if ($gudang_result && $gudang_result instanceof mysqli_result) {
        while ($g = $gudang_result->fetch_assoc()) {
            $kode = $g['kode_gudang'];
            if ($kode) {
                $all_menus['gudang_' . strtolower($kode)] = 1;
            }
        }
    }
    
    // For each role, check and add missing menus
    foreach ($role_ids as $role_id) {
        // Get existing menu access
        $sql = "SELECT menu_name FROM menu_access WHERE role_id = ?";
        $stmt = $authConn->prepare($sql);
        if (!$stmt) continue;
        
        $stmt->bind_param('i', $role_id);
        if (!$stmt->execute()) {
            $stmt->close();
            continue;
        }
        $result = $stmt->get_result();
        
        $existing_menus = [];
        if ($result && $result instanceof mysqli_result) {
            while ($row = $result->fetch_assoc()) {
                $existing_menus[$row['menu_name']] = 1;
            }
        }
        $stmt->close();
        
        // Tambahkan menu yang masih hilang, walau role sudah punya beberapa akses.
        // Ini penting agar menu seperti cashier tetap muncul meski role sudah ada record lama.
        $missing_menus = [];
        foreach ($all_menus as $menu_name => $val) {
            if (!isset($existing_menus[$menu_name])) {
                $missing_menus[$menu_name] = 1;
            }
        }

        // Add missing menus
        if (!empty($missing_menus)) {
            $sql = "INSERT IGNORE INTO menu_access (role_id, menu_name, can_view, can_add, can_edit, can_delete, can_complete, can_setup_split) 
                    VALUES (?, ?, 1, 1, 1, 1, 0, 0)";
            $stmt = $authConn->prepare($sql);
            if (!$stmt) continue;

            foreach ($missing_menus as $menu_name => $val) {
                $stmt->bind_param('is', $role_id, $menu_name);
                @$stmt->execute();
            }
            $stmt->close();
        }
    }
}

// Auto-run on every page load if session exists
// Skip if auto-fix is disabled
if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['user_id'])) {
    if (!isset($GLOBALS['DISABLE_MENU_ACCESS_AUTO_FIX']) || !$GLOBALS['DISABLE_MENU_ACCESS_AUTO_FIX']) {
        ensureUserMenuAccess();
    }
}
?>
