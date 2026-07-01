<?php
/**
 * MINVEN PRO - AUTO-FIX Menu Access on Initialization
 * 
 * This script automatically fixes missing menus when the application starts.
 * It should be included in config.php or index.php
 */

function ensure_menu_access_integrity($conn) {
    if (!$conn) return;
    
    // Check if menu_access table has any records
    $result = $conn->query("SELECT COUNT(*) as cnt FROM menu_access");
    if (!$result) return; // table doesn't exist
    
    $row = $result->fetch_assoc();
    $count = $row['cnt'];
    
    // If table is empty or nearly empty, restore it
    if ($count < 10) {
        restore_default_menu_access($conn);
    }
}

function restore_default_menu_access($conn) {
    // Get all roles
    $roles_result = $conn->query("SELECT id FROM roles");
    $roles = [];
    while ($r = $roles_result->fetch_assoc()) {
        $roles[] = $r['id'];
    }
    
    if (empty($roles)) return; // No roles found
    
    // Define all menus
    $menus = [
        'dashboard', 'gudang', 'gudang_central', 'gudang_antapani', 'master',
        'barang', 'supplier', 'satuan', 'kategori', 'mapping_items', 'konversi_masukan',
        'transaksi', 'stok_masuk', 'stok_keluar', 'stok_transfer',
        'adjustment_in', 'adjustment_out', 'pembelian', 'purchase_order',
        'approve', 'pembelian_direct', 'payment', 'vendor_refund', 'manufacture',
        'laporan', 'laporan_pembelian', 'laporan_transfer', 'laporan_adjustment_in',
        'laporan_adjustment_out', 'order_management', 'cashier', 'setup', 'user',
        'reset_stok', 'template_po', 'barcode', 'get_wa', 'menu_access', 'absensi',
        'tambah_gudang'
    ];
    
    // Clear and rebuild menu_access
    $conn->query("TRUNCATE TABLE menu_access");
    
    foreach ($roles as $role_id) {
        $is_admin = ($role_id == 1);
        
        foreach ($menus as $menu) {
            $can_view = 1;
            $can_add = $is_admin ? 1 : 0;
            $can_edit = $is_admin ? 1 : 0;
            $can_delete = $is_admin ? 1 : 0;
            
            // Basic view access for all roles
            if (in_array($menu, ['dashboard', 'gudang', 'gudang_central', 'gudang_antapani', 'laporan', 'absensi'])) {
                $can_view = 1;
            }
            
            $sql = "INSERT INTO menu_access (role_id, menu_name, can_view, can_add, can_edit, can_delete, can_complete, can_setup_split)
                    VALUES ($role_id, '$menu', $can_view, $can_add, $can_edit, $can_delete, 0, 0)";
            
            $conn->query($sql);
        }
    }
    
    // Ensure admin has full access
    $conn->query("UPDATE menu_access SET can_view = 1, can_add = 1, can_edit = 1, can_delete = 1 WHERE role_id = 1");
}

// This function should be called in config.php after database connection:
// if (isset($conn) && $conn) {
//     ensure_menu_access_integrity($conn);
// }
?>
