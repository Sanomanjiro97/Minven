<?php
function ensureMenuAccessSetupSplitColumn() {
    $conn = auth_db_conn();
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    if (!$conn) {
        return;
    }

    $exists = false;
    if (function_exists('db_has_column')) {
        $exists = db_has_column($conn, 'menu_access', 'can_setup_split');
    } else {
        $stmt = $conn->prepare("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'menu_access' AND COLUMN_NAME = 'can_setup_split' LIMIT 1");
        if ($stmt) {
            $stmt->execute();
            $res = $stmt->get_result();
            $exists = $res && $res->num_rows > 0;
            $stmt->close();
        }
    }
    if ($exists) {
        return;
    }

    $conn->query("ALTER TABLE menu_access ADD COLUMN can_setup_split TINYINT(1) NOT NULL DEFAULT 0 AFTER can_delete");
}

function ensureMenuAccessWASendColumn() {
    $conn = auth_db_conn();
    static $done = false;
    if ($done) return;
    $done = true;
    if (!$conn) return;

    $result = $conn->query("SHOW COLUMNS FROM menu_access LIKE 'can_send_wa'");
    if ($result->num_rows == 0) {
        $conn->query("ALTER TABLE menu_access ADD COLUMN can_send_wa TINYINT(1) NOT NULL DEFAULT 0");
    }
}

function checkAccess($menu_name, $action = 'view') {
    $conn = auth_db_conn();
    
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role_id'])) {
        return false;
    }

    if ((int)$_SESSION['role_id'] === 1) {
        return true;
    }
    
    // Get user roles from user_roles table
    $sql = "SELECT role_id FROM user_roles WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $role_ids = [];
    while ($row = $result->fetch_assoc()) {
        $role_ids[] = $row['role_id'];
    }
    
    // If no roles found, check single role_id from session
    if (empty($role_ids)) {
        $role_ids = [$_SESSION['role_id']];
    }
    
    // Check access for any of the user's roles
    foreach ($role_ids as $role_id) {
        if ((int)$role_id === 1) {
            return true;
        }
        $sql = "SELECT * FROM menu_access WHERE role_id = ? AND menu_name = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('is', $role_id, $menu_name);
        $stmt->execute();
        $access_result = $stmt->get_result();
        
        if ($access_result->num_rows > 0) {
            $access = $access_result->fetch_assoc();
            
            switch ($action) {
                case 'view':
                    if ($access['can_view'] == 1) return true;
                    break;
                case 'add':
                    if ($access['can_add'] == 1) return true;
                    break;
                case 'edit':
                    if ($access['can_edit'] == 1) return true;
                    break;
                case 'delete':
                    if ($access['can_delete'] == 1) return true;
                    break;
                case 'setup_split':
                    ensureMenuAccessSetupSplitColumn();
                    if (isset($access['can_setup_split']) && $access['can_setup_split'] == 1) return true;
                    break;
                case 'complete':
                    if (isset($access['can_complete']) && $access['can_complete'] == 1) return true;
                    break;
                case 'send_wa':
                    ensureMenuAccessWASendColumn();
                    if (isset($access['can_send_wa']) && $access['can_send_wa'] == 1) return true;
                    if (checkAccess('get_wa', 'view')) return true;
                    break;
                default:
                    return false;
            }
        }
    }
    
    return false;
}

function redirectIfNoAccess($menu_name, $action = 'view', $redirect_url = '../dashboard.php') {
    if (!checkAccess($menu_name, $action)) {
        $_SESSION['error'] = "Akses tidak diizinkan untuk melakukan aksi '$action' pada menu '$menu_name'";
        header("Location: $redirect_url");
        exit();
    }
}

function hasAccess($menu_name, $action = 'view') {
    return checkAccess($menu_name, $action);
}

function check_menu_access($conn, $role_ids, $menu_name, $permission = 'can_view') {
    if (empty($role_ids)) return false;
    $allowed_permissions = ['can_view', 'can_add', 'can_edit', 'can_delete', 'can_complete'];
    if (!in_array($permission, $allowed_permissions, true)) return false;
    $role_ids = array_map('intval', $role_ids);
    $role_ids_str = implode(',', $role_ids);
    $sql = "SELECT COUNT(*) as cnt FROM menu_access WHERE role_id IN ($role_ids_str) AND menu_name = ? AND $permission = 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $menu_name);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    return $result['cnt'] > 0;
}

// Function to get user roles
function getUserRoles($user_id) {
    $conn = auth_db_conn();
    
    $sql = "SELECT r.id, r.nama_role FROM roles r 
            INNER JOIN user_roles ur ON r.id = ur.role_id 
            WHERE ur.user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $roles = [];
    while ($row = $result->fetch_assoc()) {
        $roles[] = $row;
    }
    
    return $roles;
}

// Function to check if user has any access to a menu
function hasAnyAccess($menu_name) {
    return checkAccess($menu_name, 'view') || 
           checkAccess($menu_name, 'add') || 
           checkAccess($menu_name, 'edit') || 
           checkAccess($menu_name, 'delete') ||
           checkAccess($menu_name, 'setup_split') ||
           checkAccess($menu_name, 'complete');
}

// New function to handle unauthorized access with popup
function handleUnauthorizedAccess($menu_name, $action = 'view') {
    if (!checkAccess($menu_name, $action)) {
        // Store unauthorized access attempt in session
        $_SESSION['unauthorized_access'] = [
            'menu' => $menu_name,
            'action' => $action,
            'timestamp' => time()
        ];
        
        // Return false to indicate unauthorized access
        return false;
    }
    return true;
}

// Function to get menu display name
function getMenuDisplayName($menu_name) {
    $menu_names = [
        'dashboard' => 'Dashboard',
        'master' => 'Master Data',
        'barang' => 'Master Barang',
        'kategori' => 'Master Kategori',
        'supplier' => 'Master Supplier',
        'satuan' => 'Master Satuan',
        'mapping_items' => 'Mapping Items',
        'gudang' => 'Master Gudang',
        'gudang_central' => 'Gudang Central',
        'gudang_antapani' => 'Gudang Antapani',
        'transaksi' => 'Transaksi',
        'stok_masuk' => 'Stok Masuk',
        'stok_keluar' => 'Stok Keluar',
        'stok_transfer' => 'Stok Transfer',
        'pembelian' => 'Pembelian',
        'purchase_order' => 'Purchase Order',
        'approve' => 'Approve',
        'pembelian_direct' => 'Pembelian Direct',
        'vendor_refund' => 'Refund Vendor',
        'manufacture' => 'Manufaktur',
        'surat_jalan' => 'Surat Jalan',
        'laporan' => 'Laporan',
        'laporan_stok' => 'Laporan Stok',
        'po' => 'Laporan PO',
        'laporan_pembelian' => 'Laporan Pembelian',
        'laporan_transfer' => 'Laporan Transfer',
        'user' => 'Manajemen User',
        'setup' => 'Setup',
        'reset_stok' => 'Reset Stok Gudang',
        'edit_nama_gudang' => 'Edit Nama Gudang',
        'setup_upload_template' => 'Upload Template Laporan & Logo',
        'tambah_gudang' => 'Tambah Gudang'
    ];
    
    return $menu_names[$menu_name] ?? ucfirst(str_replace('_', ' ', $menu_name));
}

// Function to get action display name
function getActionDisplayName($action) {
    $action_names = [
        'view' => 'Melihat',
        'add' => 'Menambah',
        'edit' => 'Mengedit',
        'delete' => 'Menghapus',
        'complete' => 'Menyelesaikan'
    ];
    
    return $action_names[$action] ?? $action;
}

// Function to check if current page requires access check
function shouldCheckAccess($current_url) {
    $base = BASE_PATH;
    $protected_pages = [
        $base . 'dashboard.php',
        $base . 'barang/',
        $base . 'supplier/',
        $base . 'satuan/',
        $base . 'kategori/',
        $base . 'mapping_items/',
        $base . 'gudang/',
        $base . 'stok/',
        $base . 'pembelian/',
        $base . 'vendor_refund/',
        $base . 'manufacture/',
        $base . 'laporan/',
        $base . 'user/'
    ];
    $excluded_pages = [
        $base . 'logout.php',
        $base . 'index.php',
        $base . 'config.php',
        $base . 'ajax/',
        $base . 'asset/',
        $base . 'uploads/',
        $base . 'examples/',
        $base . 'docs/'
    ];
    
    // First check if page is in excluded list
    foreach ($excluded_pages as $excluded_page) {
        if (strpos($current_url, $excluded_page) !== false) {
            return false; // Don't check access for excluded pages
        }
    }
    
    // Then check if page is in protected list
    foreach ($protected_pages as $page) {
        if (strpos($current_url, $page) !== false) {
            return true;
        }
    }
    
    return false;
}

// Function to get menu name from URL
function getMenuNameFromUrl($url) {
    $base = BASE_PATH;
    if (strpos($url, $base . 'gudang/gudang_central.php') !== false) {
        return 'gudang_central';
    }
    if (strpos($url, $base . 'gudang/gudang_antapani.php') !== false) {
        return 'gudang_antapani';
    }
    if (strpos($url, $base . 'gudang/edit.php') !== false) {
        if (preg_match('/[?&]gudang=(central|antapani)\b/i', $url, $matches)) {
            return 'gudang_' . strtolower($matches[1]);
        }
        return 'gudang';
    }

    $baseTrim = rtrim($base, '/');
    if (strpos($url, $baseTrim) !== 0) {
        return 'dashboard';
    }
    
    $relative = substr($url, strlen($baseTrim));
    $relative = ltrim($relative, '/');
    $url_parts = explode('/', $relative);
    if (isset($url_parts[0]) && $url_parts[0] !== '') {
        $menu_part = $url_parts[0];
        
        if ($menu_part === 'dashboard.php') {
            return 'dashboard';
        }
        
        $menu_mapping = [
            'barang' => 'barang',
            'supplier' => 'supplier',
            'satuan' => 'satuan',
            'kategori' => 'kategori',
            'mapping_items' => 'mapping_items',
            'gudang' => 'gudang',
            'stok' => 'transaksi',
            'pembelian' => 'pembelian',
            'laporan' => 'laporan',
            'user' => 'user'
        ];
        
        return $menu_mapping[$menu_part] ?? $menu_part;
    }
    
    return 'dashboard';
}

function get_accessible_gudang_list($conn) {
    $rows = [];
    // Kumpulkan menu akses gudang yang diizinkan untuk user ini
    $role_ids = [];
    if (isset($_SESSION['user_id'])) {
        $stmt = $conn->prepare("SELECT role_id FROM user_roles WHERE user_id = ?");
        if ($stmt) {
            $stmt->bind_param('i', $_SESSION['user_id']);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($res && ($r = $res->fetch_assoc())) {
                $role_ids[] = (int)$r['role_id'];
            }
            $stmt->close();
        }
    }
    if (empty($role_ids) && isset($_SESSION['role_id'])) {
        $role_ids[] = (int)$_SESSION['role_id'];
    }
    $allowed_menus = [];
    if (!empty($role_ids)) {
        $placeholders = implode(',', array_fill(0, count($role_ids), '?'));
        $types = str_repeat('i', count($role_ids));
        // Ambil semua menu yang diizinkan, normalisasi nama menu agar warisan lama (dengan spasi/kapital) tetap terbaca
        $sqlAcc = "SELECT DISTINCT menu_name FROM menu_access WHERE can_view = 1 AND role_id IN ($placeholders)";
        $stmt = $conn->prepare($sqlAcc);
        if ($stmt) {
            $stmt->bind_param($types, ...$role_ids);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($res && ($m = $res->fetch_assoc())) {
                $raw = (string)$m['menu_name'];
                $norm = strtolower(trim($raw));
                $norm = str_replace([' ', '-'], '_', $norm);
                $norm = preg_replace('/[^a-z0-9_]/', '', $norm);
                $norm = preg_replace('/_+/', '_', $norm);
                if (strpos($norm, 'gudang') === 0) {
                    $allowed_menus[$norm] = true;
                }
            }
            $stmt->close();
        }
    }
    if (empty($allowed_menus)) {
        return []; // tidak ada akses gudang
    }
    // Ambil semua gudang aktif (longgar: null/kosong/'aktif')
    $sql = "SELECT id, kode_gudang, nama_gudang FROM gudang";
    if (function_exists('db_has_column') && db_has_column($conn, 'gudang', 'status')) {
        $sql .= " WHERE (status IS NULL OR status = '' OR LOWER(status) = 'aktif')";
    }
    $sql .= " ORDER BY nama_gudang";
    $res = $conn->query($sql);
    while ($res && ($g = $res->fetch_assoc())) {
        $id = (int)($g['id'] ?? 0);
        $kode = (string)($g['kode_gudang'] ?? '');
        $nama = (string)($g['nama_gudang'] ?? '');
        $normKode = strtolower(preg_replace('/[^a-z0-9]/', '', $kode));
        $normNama = strtolower(preg_replace('/[^a-z0-9]/', '', $nama));
        $normAll = strtolower(preg_replace('/[^a-z0-9]/', '', $kode . ' ' . $nama));
        $normAll = str_replace('gudang', '', $normAll);
        $include = false;
        // Pemetaan eksplisit berdasarkan menu yang diizinkan
        if ($kode !== '' && isset($allowed_menus[strtolower('gudang_' . $kode)])) {
            $include = true;
        } elseif ($normKode !== '' && isset($allowed_menus['gudang_' . $normKode])) {
            $include = true;
        } elseif ($normNama !== '' && isset($allowed_menus['gudang_' . $normNama])) {
            $include = true;
        } elseif ((strpos($normAll, 'central') !== false || strpos($normAll, 'pusat') !== false) && isset($allowed_menus['gudang_central'])) {
            $include = true;
        } elseif (strpos($normAll, 'antapani') !== false && isset($allowed_menus['gudang_antapani'])) {
            $include = true;
        }
        if ($include) {
            $rows[] = ['id' => $id, 'kode_gudang' => $kode, 'nama_gudang' => $nama];
        }
    }
    // Second-pass safeguard: jika akses central/antapani ada tapi belum terjaring
    if (!empty($allowed_menus)) {
        $haveIds = array_column($rows, 'id');
        $needCentral = isset($allowed_menus['gudang_central']);
        $needAntapani = isset($allowed_menus['gudang_antapani']);
        if ($needCentral || $needAntapani) {
            $conds = [];
            if ($needCentral) {
                $conds[] = "(LOWER(REPLACE(nama_gudang,' ','')) LIKE '%central%' OR LOWER(REPLACE(nama_gudang,' ','')) LIKE '%pusat%' OR LOWER(REPLACE(IFNULL(kode_gudang,''),' ','')) LIKE '%central%' OR LOWER(REPLACE(IFNULL(kode_gudang,''),' ','')) LIKE '%pusat%')";
            }
            if ($needAntapani) {
                $conds[] = "(LOWER(REPLACE(nama_gudang,' ','')) LIKE '%antapani%' OR LOWER(REPLACE(IFNULL(kode_gudang,''),' ','')) LIKE '%antapani%')";
            }
            $where = implode(' OR ', $conds);
            $sql2 = "SELECT id, kode_gudang, nama_gudang FROM gudang WHERE $where";
            $r2 = $conn->query($sql2);
            while ($r2 && ($g = $r2->fetch_assoc())) {
                $gid = (int)($g['id'] ?? 0);
                if ($gid && !in_array($gid, $haveIds, true)) {
                    $rows[] = [
                        'id' => $gid,
                        'kode_gudang' => (string)($g['kode_gudang'] ?? ''),
                        'nama_gudang' => (string)($g['nama_gudang'] ?? '')
                    ];
                }
            }
        }
    }
    return $rows;
}
