<?php
/**
 * Auto-fix untuk memastikan menu_access terisi untuk user yang sedang login
 * File ini di-include otomatis dari config.php atau dashboard
 */

function ensureUserMenuAccess() {
    $authConn = auth_db_conn();
    $mainConn = main_db_conn();
    
    if (!isset($_SESSION['user_id'])) {
        return;
    }
    
    $user_id = $_SESSION['user_id'];
    
    // Get user role(s)
    $sql = "SELECT role_id FROM user_roles WHERE user_id = ?";
    if (!$authConn) return;
    $stmt = $authConn->prepare($sql);
    if (!$stmt) return;
    
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $role_ids = [];
    while ($row = $result->fetch_assoc()) {
        $role_ids[] = $row['role_id'];
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
    if ($mainConn) {
        $gudang_result = $mainConn->query("SELECT DISTINCT kode_gudang FROM gudang WHERE kode_gudang IS NOT NULL AND kode_gudang != ''");
    } else {
        $gudang_result = null;
    }
    if ($gudang_result) {
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
        $stmt->execute();
        $result = $stmt->get_result();
        
        $existing_menus = [];
        while ($row = $result->fetch_assoc()) {
            $existing_menus[$row['menu_name']] = 1;
        }
        $stmt->close();
        
        // Hanya seed role yang belum punya satupun hak akses (role baru).
        // Jangan tambah menu yang sengaja dinonaktifkan di role_access.php.
        if (!empty($existing_menus)) {
            continue;
        }

        $missing_menus = $all_menus;
        
        // Add missing menus
        if (!empty($missing_menus)) {
            $sql = "INSERT IGNORE INTO menu_access (role_id, menu_name, can_view, can_add, can_edit, can_delete) 
                    VALUES (?, ?, 1, 1, 1, 1)";
            $stmt = $authConn->prepare($sql);
            if (!$stmt) continue;
            
            foreach ($missing_menus as $menu_name => $val) {
                $stmt->bind_param('is', $role_id, $menu_name);
                $stmt->execute();
            }
            $stmt->close();
        }
    }
}

// Auto-run on every page load if session exists
if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['user_id'])) {
    ensureUserMenuAccess();
}
?>
