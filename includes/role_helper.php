<?php
/**
 * Role Access Helper Functions
 * File ini berisi fungsi-fungsi helper untuk memudahkan penggunaan role akses
 */

require_once __DIR__ . '/access_check.php';

/**
 * Menampilkan menu berdasarkan akses yang dimiliki user
 * @param string $menu_name Nama menu
 * @param string $action Aksi yang diizinkan (view, add, edit, delete)
 * @param string $url URL menu
 * @param string $icon Icon menu
 * @param string $text Text menu
 * @param string $class Class tambahan untuk styling
 */
function displayMenuIfAccess($menu_name, $action = 'view', $url = '#', $icon = '', $text = '', $class = '') {
    if (hasAccess($menu_name, $action)) {
        $icon_html = $icon ? "<i class=\"$icon\"></i>" : '';
        echo "<a href=\"$url\" class=\"$class\">$icon_html $text</a>";
        return true;
    }
    return false;
}

/**
 * Menampilkan tombol berdasarkan akses yang dimiliki user
 * @param string $menu_name Nama menu
 * @param string $action Aksi yang diizinkan
 * @param string $url URL tombol
 * @param string $text Text tombol
 * @param string $class Class tombol (btn-primary, btn-danger, dll)
 * @param string $icon Icon tombol
 */
function displayButtonIfAccess($menu_name, $action = 'view', $url = '#', $text = '', $class = 'btn btn-primary', $icon = '') {
    if (hasAccess($menu_name, $action)) {
        $icon_html = $icon ? "<i class=\"$icon me-1\"></i>" : '';
        echo "<a href=\"$url\" class=\"$class\">$icon_html $text</a>";
        return true;
    }
    return false;
}

/**
 * Menampilkan dropdown menu berdasarkan akses
 * @param string $parent_menu Nama menu parent
 * @param array $submenus Array submenu dengan format ['menu_name' => ['url' => '', 'text' => '', 'icon' => '']]
 */
function displayDropdownIfAccess($parent_menu, $submenus = []) {
    if (hasAccess($parent_menu, 'view')) {
        echo '<li class="nav-item dropdown">';
        echo '<a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">';
        echo '<i class="bi bi-archive me-1"></i> ' . ucfirst($parent_menu);
        echo '</a>';
        echo '<ul class="dropdown-menu">';
        
        foreach ($submenus as $menu_name => $menu_data) {
            if (hasAccess($menu_name, 'view')) {
                $icon = isset($menu_data['icon']) ? "<i class=\"{$menu_data['icon']} me-1\"></i>" : '';
                echo "<li><a class=\"dropdown-item\" href=\"{$menu_data['url']}\">$icon {$menu_data['text']}</a></li>";
            }
        }
        
        echo '</ul>';
        echo '</li>';
        return true;
    }
    return false;
}

/**
 * Menampilkan alert jika user tidak memiliki akses
 * @param string $menu_name Nama menu
 * @param string $action Aksi yang dibutuhkan
 */
function showNoAccessAlert($menu_name, $action = 'view') {
    if (!hasAccess($menu_name, $action)) {
        echo '<div class="alert alert-warning" role="alert">';
        echo '<i class="bi bi-exclamation-triangle me-2"></i>';
        echo "Anda tidak memiliki akses untuk melakukan aksi '$action' pada menu '$menu_name'";
        echo '</div>';
        return true;
    }
    return false;
}

/**
 * Menampilkan atau menyembunyikan elemen berdasarkan akses
 * @param string $menu_name Nama menu
 * @param string $action Aksi yang diizinkan
 * @param bool $show_if_no_access Jika true, tampilkan elemen jika tidak ada akses
 */
function displayElementIfAccess($menu_name, $action = 'view', $show_if_no_access = false) {
    $has_access = hasAccess($menu_name, $action);
    if ($show_if_no_access) {
        return !$has_access;
    }
    return $has_access;
}

/**
 * Mendapatkan daftar menu yang dapat diakses user
 * @param int $user_id ID user
 * @return array Array menu yang dapat diakses
 */
function getUserAccessibleMenus($user_id = null) {
    $conn = auth_db_conn();
    
    if (!$user_id) {
        $user_id = $_SESSION['user_id'] ?? 0;
    }
    
    if (!$user_id) return [];
    
    $sql = "SELECT DISTINCT ma.menu_name, ma.can_view, ma.can_add, ma.can_edit, ma.can_delete 
            FROM menu_access ma 
            INNER JOIN user_roles ur ON ma.role_id = ur.role_id 
            WHERE ur.user_id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $menus = [];
    while ($row = $result->fetch_assoc()) {
        $menus[$row['menu_name']] = [
            'can_view' => $row['can_view'],
            'can_add' => $row['can_add'],
            'can_edit' => $row['can_edit'],
            'can_delete' => $row['can_delete']
        ];
    }
    
    return $menus;
}

/**
 * Menampilkan menu navbar berdasarkan akses user
 */
function displayNavbarMenus() {
    $accessible_menus = getUserAccessibleMenus();
    
    // Dashboard
    if (isset($accessible_menus['dashboard'])) {
        echo '<li class="nav-item">';
        echo '<a class="nav-link" href="' . htmlspecialchars(url_for('dashboard.php')) . '">';
        echo '<i class="bi bi-grid-fill me-1"></i> Dashboard';
        echo '</a>';
        echo '</li>';
    }
    
    // Master Data
    if (isset($accessible_menus['master'])) {
        echo '<li class="nav-item dropdown">';
        echo '<a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">';
        echo '<i class="bi bi-archive me-1"></i> Master Data';
        echo '</a>';
        echo '<ul class="dropdown-menu">';
        
        if (isset($accessible_menus['barang'])) {
            echo '<li><a class="dropdown-item" href="' . htmlspecialchars(url_for('barang/index.php')) . '"><i class="bi bi-box me-1"></i> Master Barang</a></li>';
        }
        if (isset($accessible_menus['supplier'])) {
            echo '<li><a class="dropdown-item" href="' . htmlspecialchars(url_for('supplier/index.php')) . '"><i class="bi bi-truck me-1"></i> Master Supplier</a></li>';
        }
        if (isset($accessible_menus['satuan'])) {
            echo '<li><a class="dropdown-item" href="' . htmlspecialchars(url_for('satuan/index.php')) . '"><i class="bi bi-rulers me-1"></i> Master Satuan</a></li>';
        }
        if (isset($accessible_menus['kategori'])) {
            echo '<li><a class="dropdown-item" href="' . htmlspecialchars(url_for('kategori/index.php')) . '"><i class="bi bi-tags me-1"></i> Master Kategori</a></li>';
        }
        if (isset($accessible_menus['mapping_items'])) {
            echo '<li><a class="dropdown-item" href="' . htmlspecialchars(url_for('mapping_items/index.php')) . '"><i class="bi bi-diagram-2 me-1"></i> Mapping Items</a></li>';
        }
        
        echo '</ul>';
        echo '</li>';
    }
    
    // Transaksi
    if (isset($accessible_menus['transaksi'])) {
        echo '<li class="nav-item dropdown">';
        echo '<a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">';
        echo '<i class="bi bi-arrow-left-right me-1"></i> Transaksi';
        echo '</a>';
        echo '<ul class="dropdown-menu">';
        
        if (isset($accessible_menus['stok_masuk'])) {
            echo '<li><a class="dropdown-item" href="' . htmlspecialchars(url_for('stok/masuk/index.php')) . '"><i class="bi bi-box-arrow-in-down me-1"></i> Stok Masuk</a></li>';
        }
        if (isset($accessible_menus['stok_keluar'])) {
            echo '<li><a class="dropdown-item" href="' . htmlspecialchars(url_for('stok/keluar/index.php')) . '"><i class="bi bi-box-arrow-up me-1"></i> Stok Keluar</a></li>';
        }
        if (isset($accessible_menus['stok_transfer'])) {
            echo '<li><a class="dropdown-item" href="' . htmlspecialchars(url_for('stok/transfer/index.php')) . '"><i class="bi bi-arrow-repeat me-1"></i> Stok Transfer</a></li>';
        }
        
        echo '</ul>';
        echo '</li>';
    }
    
    // Pembelian
    if (isset($accessible_menus['pembelian'])) {
        echo '<li class="nav-item dropdown">';
        echo '<a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">';
        echo '<i class="bi bi-cart-check me-1"></i> Pembelian';
        echo '</a>';
        echo '<ul class="dropdown-menu">';
        
        if (isset($accessible_menus['purchase_order'])) {
            echo '<li><a class="dropdown-item" href="' . htmlspecialchars(url_for('pembelian/po/index.php')) . '"><i class="bi bi-file-earmark-text me-1"></i> Purchase Order</a></li>';
        }
        if (isset($accessible_menus['pembelian_direct'])) {
            echo '<li><a class="dropdown-item" href="' . htmlspecialchars(url_for('pembelian/direct/index.php')) . '"><i class="bi bi-bag-check me-1"></i> Pembelian Direct</a></li>';
        }
        if (isset($accessible_menus['surat_jalan'])) {
            echo '<li><a class="dropdown-item" href="' . htmlspecialchars(url_for('pembelian/surat jalan/index.php')) . '"><i class="bi bi-truck me-1"></i> Surat Jalan</a></li>';
        }
        
        echo '</ul>';
        echo '</li>';
    }
    
    // Laporan
    if (isset($accessible_menus['laporan'])) {
        echo '<li class="nav-item dropdown">';
        echo '<a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">';
        echo '<i class="bi bi-file-bar-graph me-1"></i> Laporan';
        echo '</a>';
        echo '<ul class="dropdown-menu">';
        
        if (isset($accessible_menus['laporan_stok'])) {
            echo '<li><a class="dropdown-item" href="' . htmlspecialchars(url_for('laporan/stok_gudang.php')) . '"><i class="bi bi-stack me-1"></i> Laporan Stok</a></li>';
        }
        if (isset($accessible_menus['laporan_pembelian'])) {
            echo '<li><a class="dropdown-item" href="' . htmlspecialchars(url_for('laporan/pembelian_direct.php')) . '"><i class="bi bi-cash-stack me-1"></i> Laporan Pembelian</a></li>';
        }
        if (isset($accessible_menus['laporan_transfer'])) {
            echo '<li><a class="dropdown-item" href="' . htmlspecialchars(url_for('laporan/stok_transfer.php')) . '"><i class="bi bi-arrow-repeat me-1"></i> Laporan Transfer</a></li>';
        }
        
        echo '</ul>';
        echo '</li>';
    }
    
    // User Management
    if (isset($accessible_menus['user'])) {
        echo '<li class="nav-item">';
        echo '<a class="nav-link" href="' . htmlspecialchars(url_for('user/index.php')) . '">';
        echo '<i class="bi bi-people me-1"></i> Manajemen User';
        echo '</a>';
        echo '</li>';
    }
}

/**
 * Menampilkan tombol aksi berdasarkan akses user
 * @param string $menu_name Nama menu
 * @param int $id ID item
 * @param string $base_url Base URL untuk aksi
 */
function displayActionButtons($menu_name, $id, $base_url = '') {
    echo '<div class="btn-group" role="group">';
    
    if (hasAccess($menu_name, 'view')) {
        echo "<a href=\"{$base_url}view.php?id=$id\" class=\"btn btn-sm btn-info\">";
        echo '<i class="bi bi-eye me-1"></i>View';
        echo '</a>';
    }
    
    if (hasAccess($menu_name, 'edit')) {
        echo "<a href=\"{$base_url}edit.php?id=$id\" class=\"btn btn-sm btn-warning\">";
        echo '<i class="bi bi-pencil me-1"></i>Edit';
        echo '</a>';
    }
    
    if (hasAccess($menu_name, 'delete')) {
        echo "<a href=\"{$base_url}delete.php?id=$id\" class=\"btn btn-sm btn-danger\" onclick=\"return confirm('Yakin ingin menghapus data ini?')\">";
        echo '<i class="bi bi-trash me-1"></i>Delete';
        echo '</a>';
    }
    
    echo '</div>';
}
?> 
