<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

// Validate role_id parameter
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: roles.php");
    exit();
}

$role_id = (int)$_GET['id'];

// Get role info
$sql = "SELECT * FROM roles WHERE id = ?";
if (!isset($conn) || !$conn) {
    throw new Exception("Database connection not established");
}
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $role_id);
$stmt->execute();
$role = $stmt->get_result()->fetch_assoc();

if (!$role) {
    $_SESSION['error'] = "Role dengan ID $role_id tidak ditemukan";
    header("Location: roles.php");
    exit();
}

// Daftar menu yang tersedia
$available_menus = [
    'dashboard' => [
        'name' => 'Dashboard',
        'icon' => 'bx-home-alt',
        'submenus' => []
    ],
    'master' => [
        'name' => 'Master Data',
        'icon' => 'bx-data',
        'submenus' => [
            'barang' => 'Master Barang',
            'kategori' => 'Master Kategori',
            'supplier' => 'Master Supplier',
            'satuan' => 'Master Satuan',
            'mapping_items' => 'Mapping Items',
            'konversi_masukan' => 'Konversi',
        ]

    ],
    'gudang' => [
        'name' => 'Master Gudang',
        'icon' => 'bx-store',
        'submenus' => [
            'master_gudang' => 'Master Gudang',
            'gudang_central' => 'Gudang Central',
            'gudang_antapani' => 'Gudang Antapani',
            'tambah_gudang' => 'Tambah Gudang',
        ]
    ],
    'transaksi' => [
        'name' => 'Transaksi',
        'icon' => 'bx-transfer',
        'submenus' => [
            'stok_masuk' => 'Stok Masuk',
            'stok_keluar' => 'Stok Keluar',
            'stok_transfer' => 'Stok Transfer',
            'adjustment_in' => 'Adjustment In',
            'adjustment_out' => 'Adjustment Out',
        ]
    ],
    'pembelian' => [
        'name' => 'Pembelian',
        'icon' => 'bx-shopping-bag',
        'submenus' => [
            'purchase_order' => 'Purchase Order',
            'approve' => 'Approve PO',
            'pembelian_direct' => 'Pembelian Direct',
            'payment' => 'Payment',
            'vendor_refund' => 'Refund Vendor',
            'manufacture' => 'Manufaktur',
            'surat_jalan' => 'Surat Jalan'
        ]
    ],
    'laporan' => [
        'name' => 'Laporan',
        'icon' => 'bx-file',
        'submenus' => [
            'laporan_stok' => 'Laporan Stok',
            'laporan_po' => 'Laporan PO',
            'laporan_pembelian' => 'Laporan Pembelian',
            'laporan_transfer' => 'Laporan Transfer',
            'laporan_adjustment_in' => 'Laporan Adjustment In',
            'laporan_adjustment_out' => 'Laporan Adjustment Out',
        ]
    ],
    'backoffice' => [
        'name' => 'Backoffice',
        'icon' => 'bx-buildings',
        'submenus' => [
            'backoffice_dashboard' => 'Dashboard Backoffice',
            'backoffice_reports_inventory' => 'Laporan Inventory',
            'backoffice_reports_finance' => 'Laporan Keuangan',
            'backoffice_reports_po' => 'Laporan PO',
            'backoffice_reports_direct' => 'Laporan Pemberian Direct',
            'backoffice_reports_inventory_price' => 'Laporan Inventory - Item Price',
            'backoffice_reports_item_movement' => 'Laporan Inventory - Item Movement',
            'backoffice_users' => 'User Backoffice',
            'backoffice_roles' => 'Role Backoffice'
        ]
    ],
    'user' => [
        'name' => 'Manajemen User',
        'icon' => 'bx-user',
        'submenus' => []
    ],
    'setup' => [
        'name' => 'Setup',
        'icon' => 'bx-cog',
        'submenus' => [
            'reset_stok' => 'Reset Stok Gudang',
            'edit_nama_gudang' => 'Edit Nama Gudang',
            'template_po' => 'Template PO',
            'barcode' => 'Setup Barcode',
            'get_wa' => 'Get WA',
        ]
    ]
];

$gudang_q = @$conn->query("SELECT kode_gudang, nama_gudang FROM gudang ORDER BY nama_gudang");
if ($gudang_q) {
    while ($g = $gudang_q->fetch_assoc()) {
        $kode = (string)($g['kode_gudang'] ?? '');
        $nama = (string)($g['nama_gudang'] ?? 'Gudang');
        if ($kode !== '') {
            $menu_key = 'gudang_' . $kode;
            $norm_all = strtolower($kode . ' ' . $nama);
            $norm_all = preg_replace('/[^a-z]/', '', $norm_all);
            $norm_all = str_replace('gudang', '', $norm_all);
            if (strpos($norm_all, 'central') !== false || strpos($norm_all, 'antapani') !== false) {
                continue;
            }
            $available_menus['gudang']['submenus'][$menu_key] = 'Stok ' . str_replace('_', ' ', $nama);
        }
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        // Start transaction for data integrity
        $conn->begin_transaction();
        
        // Delete existing access
        $sql = "DELETE FROM menu_access WHERE role_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $role_id);
        $stmt->execute();
        
        $has_can_complete = db_has_column($conn, 'menu_access', 'can_complete');
        $has_can_setup_split = db_has_column($conn, 'menu_access', 'can_setup_split');
        if (!$has_can_setup_split) {
            $conn->query("ALTER TABLE menu_access ADD COLUMN can_setup_split TINYINT(1) NOT NULL DEFAULT 0 AFTER can_delete");
            $has_can_setup_split = db_has_column($conn, 'menu_access', 'can_setup_split');
        }
        if (!$has_can_complete) {
            $conn->query("ALTER TABLE menu_access ADD COLUMN can_complete TINYINT(1) NOT NULL DEFAULT 0 AFTER can_delete");
            $has_can_complete = db_has_column($conn, 'menu_access', 'can_complete');
        }

        if ($has_can_complete && $has_can_setup_split) {
            $sql = "INSERT INTO menu_access (role_id, menu_name, can_view, can_add, can_edit, can_delete, can_setup_split, can_complete) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                        can_view = VALUES(can_view),
                        can_add = VALUES(can_add),
                        can_edit = VALUES(can_edit),
                        can_delete = VALUES(can_delete),
                        can_setup_split = VALUES(can_setup_split),
                        can_complete = VALUES(can_complete)";
        } elseif ($has_can_complete) {
            $sql = "INSERT INTO menu_access (role_id, menu_name, can_view, can_add, can_edit, can_delete, can_complete) VALUES (?, ?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                        can_view = VALUES(can_view),
                        can_add = VALUES(can_add),
                        can_edit = VALUES(can_edit),
                        can_delete = VALUES(can_delete),
                        can_complete = VALUES(can_complete)";
        } elseif ($has_can_setup_split) {
            $sql = "INSERT INTO menu_access (role_id, menu_name, can_view, can_add, can_edit, can_delete, can_setup_split) VALUES (?, ?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                        can_view = VALUES(can_view),
                        can_add = VALUES(can_add),
                        can_edit = VALUES(can_edit),
                        can_delete = VALUES(can_delete),
                        can_setup_split = VALUES(can_setup_split)";
        } else {
            $sql = "INSERT INTO menu_access (role_id, menu_name, can_view, can_add, can_edit, can_delete) VALUES (?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                        can_view = VALUES(can_view),
                        can_add = VALUES(can_add),
                        can_edit = VALUES(can_edit),
                        can_delete = VALUES(can_delete)";
        }

        $stmt = $conn->prepare($sql);
        
        if (!$stmt) {
            throw new Exception("Failed to prepare SQL statement: " . $conn->error);
        }
        
        $inserted_count = 0;
        
        foreach ($available_menus as $menu_key => $menu) {
            // Handle main menu
            if (isset($_POST['menu_' . $menu_key]) && $_POST['menu_' . $menu_key] === 'on') {
                $can_view = 1;
                $can_add = isset($_POST['add_' . $menu_key]) && $_POST['add_' . $menu_key] === 'on' ? 1 : 0;
                $can_edit = isset($_POST['edit_' . $menu_key]) && $_POST['edit_' . $menu_key] === 'on' ? 1 : 0;
                $can_delete = isset($_POST['delete_' . $menu_key]) && $_POST['delete_' . $menu_key] === 'on' ? 1 : 0;
                $can_setup_split = 0;
                if ($has_can_setup_split && $menu_key === 'barang' && isset($_POST['setup_split_' . $menu_key]) && $_POST['setup_split_' . $menu_key] === 'on') {
                    $can_setup_split = 1;
                }
                $can_complete = 0;
                if ($has_can_complete && ($menu_key === 'purchase_order' || $menu_key === 'payment') && isset($_POST['complete_' . $menu_key]) && $_POST['complete_' . $menu_key] === 'on') {
                    $can_complete = 1;
                }

                if ($has_can_complete && $has_can_setup_split) {
                    $stmt->bind_param('isiiiiii', $role_id, $menu_key, $can_view, $can_add, $can_edit, $can_delete, $can_setup_split, $can_complete);
                } elseif ($has_can_complete) {
                    $stmt->bind_param('isiiiii', $role_id, $menu_key, $can_view, $can_add, $can_edit, $can_delete, $can_complete);
                } elseif ($has_can_setup_split) {
                    $stmt->bind_param('isiiiii', $role_id, $menu_key, $can_view, $can_add, $can_edit, $can_delete, $can_setup_split);
                } else {
                    $stmt->bind_param('isiiii', $role_id, $menu_key, $can_view, $can_add, $can_edit, $can_delete);
                }

                if (!$stmt->execute()) {
                    throw new Exception($stmt->error);
                }
                $inserted_count++;
            }
            
            // Handle submenus
            if (!empty($menu['submenus'])) {
                foreach ($menu['submenus'] as $submenu_key => $submenu_name) {
                    if (isset($_POST['menu_' . $submenu_key]) && $_POST['menu_' . $submenu_key] === 'on') {
                        $can_view = 1;
                        $can_add = isset($_POST['add_' . $submenu_key]) && $_POST['add_' . $submenu_key] === 'on' ? 1 : 0;
                        $can_edit = isset($_POST['edit_' . $submenu_key]) && $_POST['edit_' . $submenu_key] === 'on' ? 1 : 0;
                        $can_delete = isset($_POST['delete_' . $submenu_key]) && $_POST['delete_' . $submenu_key] === 'on' ? 1 : 0;
                        $can_setup_split = 0;
                        if ($has_can_setup_split && $submenu_key === 'barang' && isset($_POST['setup_split_' . $submenu_key]) && $_POST['setup_split_' . $submenu_key] === 'on') {
                            $can_setup_split = 1;
                        }
                        $can_complete = 0;
                        if ($has_can_complete && ($submenu_key === 'purchase_order' || $submenu_key === 'payment') && isset($_POST['complete_' . $submenu_key]) && $_POST['complete_' . $submenu_key] === 'on') {
                            $can_complete = 1;
                        }

                        if ($has_can_complete && $has_can_setup_split) {
                            $stmt->bind_param('isiiiiii', $role_id, $submenu_key, $can_view, $can_add, $can_edit, $can_delete, $can_setup_split, $can_complete);
                        } elseif ($has_can_complete) {
                            $stmt->bind_param('isiiiii', $role_id, $submenu_key, $can_view, $can_add, $can_edit, $can_delete, $can_complete);
                        } elseif ($has_can_setup_split) {
                            $stmt->bind_param('isiiiii', $role_id, $submenu_key, $can_view, $can_add, $can_edit, $can_delete, $can_setup_split);
                        } else {
                            $stmt->bind_param('isiiii', $role_id, $submenu_key, $can_view, $can_add, $can_edit, $can_delete);
                        }

                        if (!$stmt->execute()) {
                            throw new Exception($stmt->error);
                        }
                        $inserted_count++;
                    }
                }
            }
        }
        
        // Commit transaction
        $conn->commit();
        
        $_SESSION['success'] = "Hak akses berhasil diperbarui. Total $inserted_count menu/submenu disimpan.";
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        $_SESSION['error'] = "Error: " . $e->getMessage();
    }
    
    // Redirect to prevent form resubmission
    header("Location: role_access.php?id=" . $role_id);
    exit();
}

// Get existing access - MOVED HERE to get updated data after POST
$sql = "SELECT * FROM menu_access WHERE role_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $role_id);
$stmt->execute();
$result = $stmt->get_result();

$current_access = [];
while ($row = $result->fetch_assoc()) {
    $current_access[$row['menu_name']] = $row;
}

$page_title = "Pengaturan Hak Akses";
require_once '../templates/header.php';
require_once '../templates/navbar.php';
?>

<style>
    :root {
        --primary-blue: #004aad;
        --secondary-blue: #003a8c;
        --accent-gold: #d4af37;
        --light-bg: #f8f9fc;
        --border-radius: 12px;
        --box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
    }

    .dashboard-container {
        margin-left: 260px; /* Standard sidebar width */
        padding: 30px;
        background: #f4f7fe;
        min-height: 100vh;
        transition: all 0.3s ease;
    }

    @media (max-width: 992px) {
        .dashboard-container {
            margin-left: 0;
            padding: 15px;
        }
    }

    .access-card {
        background: white;
        border-radius: var(--border-radius);
        box-shadow: var(--box-shadow);
        border: none;
        overflow: hidden;
        margin-bottom: 30px;
    }

    .card-header-elegant {
        background: var(--primary-blue);
        color: white;
        padding: 20px 25px;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .card-header-elegant h4 {
        margin: 0;
        font-weight: 700;
        font-size: 1.25rem;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .menu-group {
        border: 1px solid #edf2f9;
        border-radius: var(--border-radius);
        margin-bottom: 20px;
        overflow: hidden;
        transition: all 0.3s ease;
    }

    .menu-group:hover {
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        border-color: var(--primary-blue);
    }

    .menu-header {
        background: #f8fafd;
        padding: 15px 20px;
        display: flex;
        align-items: center;
        gap: 15px;
        border-bottom: 1px solid #edf2f9;
    }

    .menu-icon-circle {
        width: 38px;
        height: 38px;
        background: white;
        color: var(--primary-blue);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
    }

    .menu-title {
        font-weight: 700;
        color: #2d3748;
        margin: 0;
        flex-grow: 1;
    }

    .submenu-section {
        padding: 15px 20px;
        background: white;
    }

    .submenu-item {
        padding: 12px 15px;
        border-radius: 8px;
        margin-bottom: 10px;
        background: #fff;
        border: 1px solid #f0f4f8;
        transition: all 0.2s ease;
    }

    .submenu-item:hover {
        background: #f8fafd;
        border-color: #e2e8f0;
    }

    .submenu-title {
        font-weight: 600;
        color: #4a5568;
        font-size: 0.95rem;
        margin-bottom: 8px;
    }

    .permission-grid {
        display: flex;
        flex-wrap: wrap;
        gap: 15px;
        align-items: center;
    }

    .form-check-input {
        width: 1.1em;
        height: 1.1em;
        margin-top: 0.25em;
        cursor: pointer;
    }

    .form-check-input:checked {
        background-color: var(--primary-blue);
        border-color: var(--primary-blue);
    }

    .form-check-label {
        font-size: 0.85rem;
        font-weight: 500;
        color: #4a5568;
        cursor: pointer;
    }

    .btn-save-elegant {
        background: var(--primary-blue);
        color: white;
        border: none;
        padding: 12px 30px;
        border-radius: 8px;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 10px;
        transition: all 0.3s ease;
    }

    .btn-save-elegant:hover {
        background: var(--secondary-blue);
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(0, 74, 173, 0.3);
        color: white;
    }

    .btn-back-elegant {
        background: #edf2f9;
        color: #4a5568;
        border: none;
        padding: 12px 25px;
        border-radius: 8px;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 10px;
        transition: all 0.3s ease;
    }

    .btn-back-elegant:hover {
        background: #e2e8f0;
        color: #2d3748;
    }

    .badge-role {
        background: rgba(255, 255, 255, 0.2);
        padding: 6px 15px;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 600;
    }

    .loading-overlay {
        position: fixed;
        top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(255, 255, 255, 0.8);
        display: none;
        justify-content: center;
        align-items: center;
        z-index: 9999;
    }
</style>

<div class="loading-overlay">
    <div class="spinner-border text-primary" role="status">
        <span class="visually-hidden">Loading...</span>
    </div>
</div>

<div class="dashboard-container">
    <div class="access-card">
        <div class="card-header-elegant">
            <h4><i class='bx bx-shield-quarter'></i> Pengaturan Hak Akses</h4>
            <div class="badge-role">
                <i class='bx bx-user-circle'></i> 
                <?= isset($role['nama_role']) ? htmlspecialchars($role['nama_role']) : 'Role Tidak Dikenal' ?>
            </div>
        </div>

        <div class="card-body p-4">
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm" role="alert">
                    <i class='bx bx-check-circle me-2'></i>
                    <?= $_SESSION['success'] ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>

            <form method="POST" action="role_access.php?id=<?= $role_id ?>" id="accessForm">
                <?php foreach ($available_menus as $menu_key => $menu): ?>
                <div class="menu-group">
                    <div class="menu-header">
                        <div class="menu-icon-circle">
                            <i class='<?= $menu['icon'] ?>'></i>
                        </div>
                        <h5 class="menu-title"><?= $menu['name'] ?></h5>
                        
                        <div class="d-flex align-items-center gap-4">
                            <div class="form-check form-switch mb-0">
                                <input type="checkbox" class="form-check-input menu-checkbox" 
                                       id="main_menu_<?= $menu_key ?>_<?= $role_id ?>" 
                                       name="menu_<?= $menu_key ?>"
                                       <?= isset($current_access[$menu_key]) ? 'checked' : '' ?>>
                                <label class="form-check-label fw-bold" for="main_menu_<?= $menu_key ?>_<?= $role_id ?>">
                                    Aktif
                                </label>
                            </div>

                            <div class="permission-grid">
                                <?php 
                                // Logic for simplified checkboxes
                                $is_laporan_main = strpos($menu_key, 'laporan') === 0;
                                $is_aktif_only_main = in_array($menu_key, ['barcode', 'approve', 'payment']);
                                ?>

                                <?php if ($is_aktif_only_main): ?>
                                    <!-- Only 1 checkbox "Aktif" already shown by the switch above -->
                                    <input type="hidden" name="view_<?= $menu_key ?>" value="on">
                                <?php else: ?>
                                    <div class="form-check mb-0">
                                        <input type="checkbox" class="form-check-input permission-checkbox" 
                                               id="main_view_<?= $menu_key ?>_<?= $role_id ?>" 
                                               name="view_<?= $menu_key ?>"
                                               <?= isset($current_access[$menu_key]) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="main_view_<?= $menu_key ?>_<?= $role_id ?>">View</label>
                                    </div>

                                    <?php if (!$is_laporan_main): ?>
                                        <div class="form-check mb-0">
                                            <input type="checkbox" class="form-check-input permission-checkbox" 
                                                   id="main_add_<?= $menu_key ?>_<?= $role_id ?>" 
                                                   name="add_<?= $menu_key ?>"
                                                   <?= isset($current_access[$menu_key]) && $current_access[$menu_key]['can_add'] ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="main_add_<?= $menu_key ?>_<?= $role_id ?>">Add</label>
                                        </div>
                                        <div class="form-check mb-0">
                                            <input type="checkbox" class="form-check-input permission-checkbox" 
                                                   id="main_edit_<?= $menu_key ?>_<?= $role_id ?>" 
                                                   name="edit_<?= $menu_key ?>"
                                                   <?= isset($current_access[$menu_key]) && $current_access[$menu_key]['can_edit'] ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="main_edit_<?= $menu_key ?>_<?= $role_id ?>">Edit</label>
                                        </div>
                                        <div class="form-check mb-0">
                                            <input type="checkbox" class="form-check-input permission-checkbox" 
                                                   id="main_delete_<?= $menu_key ?>_<?= $role_id ?>" 
                                                   name="delete_<?= $menu_key ?>"
                                                   <?= isset($current_access[$menu_key]) && $current_access[$menu_key]['can_delete'] ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="main_delete_<?= $menu_key ?>_<?= $role_id ?>">Delete</label>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <?php if (!empty($menu['submenus'])): ?>
                    <div class="submenu-section">
                        <div class="row g-3">
                            <?php foreach ($menu['submenus'] as $submenu_key => $submenu_name): ?>
                            <div class="col-md-6">
                                <div class="submenu-item h-100">
                                    <div class="d-flex justify-content-between align-items-start mb-3">
                                        <div class="submenu-title mb-0"><?= $submenu_name ?></div>
                                        <div class="form-check form-switch">
                                            <input type="checkbox" class="form-check-input submenu-checkbox" 
                                                   id="menu_<?= $submenu_key ?>_<?= $role_id ?>" 
                                                   name="menu_<?= $submenu_key ?>"
                                                   data-parent="<?= $menu_key ?>"
                                                   <?= isset($current_access[$submenu_key]) ? 'checked' : '' ?>>
                                            <label class="form-check-label fw-bold" for="menu_<?= $submenu_key ?>_<?= $role_id ?>">Aktif</label>
                                        </div>
                                    </div>
                                    
                                    <div class="permission-grid">
                                        <?php 
                                        $is_sub_laporan = strpos($submenu_key, 'laporan') === 0;
                                        $is_sub_aktif_only = in_array($submenu_key, ['barcode', 'approve', 'payment']);
                                        ?>

                                        <?php if ($is_sub_aktif_only): ?>
                                            <!-- Simplified: Only "Aktif" switch above is needed -->
                                            <input type="hidden" name="view_<?= $submenu_key ?>" value="on">
                                        <?php else: ?>
                                            <div class="form-check mb-0">
                                                <input type="checkbox" class="form-check-input permission-checkbox" 
                                                       id="submenu_view_<?= $submenu_key ?>_<?= $role_id ?>" 
                                                       name="view_<?= $submenu_key ?>"
                                                       <?= isset($current_access[$submenu_key]) ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="submenu_view_<?= $submenu_key ?>_<?= $role_id ?>">View</label>
                                            </div>

                                            <?php if (!$is_sub_laporan): ?>
                                                <div class="form-check mb-0">
                                                    <input type="checkbox" class="form-check-input permission-checkbox" 
                                                           id="submenu_add_<?= $submenu_key ?>_<?= $role_id ?>" 
                                                           name="add_<?= $submenu_key ?>"
                                                           <?= (isset($current_access[$submenu_key]) && $current_access[$submenu_key]['can_add']) ? 'checked' : '' ?>>
                                                    <label class="form-check-label" for="submenu_add_<?= $submenu_key ?>_<?= $role_id ?>">Add</label>
                                                </div>
                                                <div class="form-check mb-0">
                                                    <input type="checkbox" class="form-check-input permission-checkbox" 
                                                           id="submenu_edit_<?= $submenu_key ?>_<?= $role_id ?>" 
                                                           name="edit_<?= $submenu_key ?>"
                                                           <?= (isset($current_access[$submenu_key]) && $current_access[$submenu_key]['can_edit']) ? 'checked' : '' ?>>
                                                    <label class="form-check-label" for="submenu_edit_<?= $submenu_key ?>_<?= $role_id ?>">Edit</label>
                                                </div>
                                                <div class="form-check mb-0">
                                                    <input type="checkbox" class="form-check-input permission-checkbox" 
                                                           id="submenu_delete_<?= $submenu_key ?>_<?= $role_id ?>" 
                                                           name="delete_<?= $submenu_key ?>"
                                                           <?= (isset($current_access[$submenu_key]) && $current_access[$submenu_key]['can_delete']) ? 'checked' : '' ?>>
                                                    <label class="form-check-label" for="submenu_delete_<?= $submenu_key ?>_<?= $role_id ?>">Delete</label>
                                                </div>
                                                <?php if ($submenu_key === 'barang'): ?>
                                                    <div class="form-check mb-0">
                                                        <input type="checkbox" class="form-check-input permission-checkbox" 
                                                               id="submenu_setup_split_<?= $submenu_key ?>_<?= $role_id ?>" 
                                                               name="setup_split_<?= $submenu_key ?>"
                                                               <?= (isset($current_access[$submenu_key]) && isset($current_access[$submenu_key]['can_setup_split']) && $current_access[$submenu_key]['can_setup_split']) ? 'checked' : '' ?>>
                                                        <label class="form-check-label" for="submenu_setup_split_<?= $submenu_key ?>_<?= $role_id ?>">Setup Split</label>
                                                    </div>
                                                <?php endif; ?>
                                                <?php if ($submenu_key === 'purchase_order' || $submenu_key === 'payment'): ?>
                                                    <div class="form-check mb-0">
                                                        <input type="checkbox" class="form-check-input permission-checkbox" 
                                                               id="submenu_complete_<?= $submenu_key ?>_<?= $role_id ?>" 
                                                               name="complete_<?= $submenu_key ?>"
                                                               <?= (isset($current_access[$submenu_key]) && isset($current_access[$submenu_key]['can_complete']) && $current_access[$submenu_key]['can_complete']) ? 'checked' : '' ?>>
                                                        <label class="form-check-label" for="submenu_complete_<?= $submenu_key ?>_<?= $role_id ?>">Complete</label>
                                                    </div>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>

                <div class="d-flex justify-content-end gap-3 mt-4 mb-5">
                    <a href="roles.php" class="btn-back-elegant text-decoration-none">
                        <i class='bx bx-arrow-back'></i> Kembali
                    </a>
                    <button type="submit" class="btn-save-elegant">
                        <i class='bx bx-save'></i> Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const loadingOverlay = document.querySelector('.loading-overlay');
    const form = document.getElementById('accessForm');

    form.addEventListener('submit', function(e) {
        loadingOverlay.style.display = 'flex';

        // Checkbox disabled tidak ikut POST; pastikan tidak terkirim sebagai aktif
        form.querySelectorAll('.menu-checkbox:disabled, .submenu-checkbox:disabled').forEach(function(cb) {
            cb.disabled = false;
            cb.checked = false;
        });

        // Hidden view_* hanya untuk menu yang masih aktif
        form.querySelectorAll('input[type="hidden"][name^="view_"]').forEach(function(hidden) {
            const menuKey = hidden.name.replace(/^view_/, '');
            const menuCb = form.querySelector('input[name="menu_' + menuKey + '"]');
            if (!menuCb || !menuCb.checked) {
                hidden.remove();
            }
        });
        
        const formData = new FormData(form);
        let hasData = false;
        for (let [key, value] of formData.entries()) {
            if (key.startsWith('menu_') && value === 'on') {
                hasData = true;
                break;
            }
        }
        
        if (!hasData) {
            e.preventDefault();
            alert('Pilih setidaknya satu menu untuk disimpan!');
            loadingOverlay.style.display = 'none';
            return false;
        }
    });

    function togglePermissionCheckboxes(container, enabled) {
        const checkboxes = container.querySelectorAll('.permission-checkbox');
        checkboxes.forEach(checkbox => {
            checkbox.disabled = !enabled;
            if (!enabled) checkbox.checked = false;
        });
    }

    function toggleSubmenuItems(menuGroup, enabled) {
        const submenuCheckboxes = menuGroup.querySelectorAll('.submenu-checkbox');
        submenuCheckboxes.forEach(checkbox => {
            checkbox.disabled = !enabled;
            if (!enabled) {
                checkbox.checked = false;
                const item = checkbox.closest('.submenu-item');
                togglePermissionCheckboxes(item, false);
            } else {
                const item = checkbox.closest('.submenu-item');
                togglePermissionCheckboxes(item, checkbox.checked);
            }
        });
    }

    // Main menu toggles
    document.querySelectorAll('.menu-checkbox').forEach(checkbox => {
        const menuGroup = checkbox.closest('.menu-group');
        const header = checkbox.closest('.menu-header');
        
        togglePermissionCheckboxes(header, checkbox.checked);
        toggleSubmenuItems(menuGroup, checkbox.checked);
        
        checkbox.addEventListener('change', function() {
            togglePermissionCheckboxes(header, this.checked);
            toggleSubmenuItems(menuGroup, this.checked);
        });
    });

    // Submenu toggles
    document.querySelectorAll('.submenu-checkbox').forEach(checkbox => {
        const item = checkbox.closest('.submenu-item');
        const parentKey = checkbox.getAttribute('data-parent');
        const parentCheckbox = document.getElementById(`main_menu_${parentKey}_<?= $role_id ?>`);
        
        togglePermissionCheckboxes(item, checkbox.checked && parentCheckbox.checked);
        
        checkbox.addEventListener('change', function() {
            togglePermissionCheckboxes(item, this.checked && parentCheckbox.checked);
        });
    });

    // View auto-toggle logic
    document.querySelectorAll('input[name^="view_"]').forEach(viewCb => {
        viewCb.addEventListener('change', function() {
            if (!this.checked) {
                const container = this.closest('.permission-grid');
                container.querySelectorAll('.permission-checkbox:not([name^="view_"])').forEach(cb => cb.checked = false);
            }
        });
    });

    document.querySelectorAll('.permission-checkbox:not([name^="view_"])').forEach(permCb => {
        permCb.addEventListener('change', function() {
            if (this.checked) {
                const container = this.closest('.permission-grid');
                const viewCb = container.querySelector('input[name^="view_"]');
                if (viewCb) viewCb.checked = true;
            }
        });
    });
});
</script>

<?php require_once '../templates/footer.php'; ?>
</body>
</html>
