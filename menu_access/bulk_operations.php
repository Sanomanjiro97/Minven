<?php
session_start();
require_once '../config.php';
require_once '../includes/page_access_check.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

// Check access to menu access management
if (!hasAccess('menu_access', 'view')) {
    $_SESSION['error'] = "Akses tidak diizinkan untuk mengelola Menu Access";
    header("Location: ../dashboard.php");
    exit();
}

$error = null;
$success = null;

// Get all roles
$roles_sql = "SELECT id, nama_role FROM roles ORDER BY nama_role";
$roles_result = $conn->query($roles_sql);
$roles = [];
while ($role = $roles_result->fetch_assoc()) {
    $roles[] = $role;
}

// Get all available menus
$available_menus = [
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
    'reset_stok' => 'Reset Stok Gudang',
    'edit_nama_gudang' => 'Edit Nama Gudang'
];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'bulk_create') {
        // Bulk create menu access
        $selected_roles = $_POST['selected_roles'] ?? [];
        $selected_menus = $_POST['selected_menus'] ?? [];
        $can_view = isset($_POST['can_view']) ? 1 : 0;
        $can_add = isset($_POST['can_add']) ? 1 : 0;
        $can_edit = isset($_POST['can_edit']) ? 1 : 0;
        $can_delete = isset($_POST['can_delete']) ? 1 : 0;
        
        if (empty($selected_roles) || empty($selected_menus)) {
            $error = "Pilih minimal satu role dan satu menu!";
        } else {
            $success_count = 0;
            $error_count = 0;
            
            foreach ($selected_roles as $role_id) {
                foreach ($selected_menus as $menu_name) {
                    // Check if already exists
                    $check_sql = "SELECT id FROM menu_access WHERE role_id = ? AND menu_name = ?";
                    $check_stmt = $conn->prepare($check_sql);
                    $check_stmt->bind_param('is', $role_id, $menu_name);
                    $check_stmt->execute();
                    
                    if ($check_stmt->get_result()->num_rows === 0) {
                        // Insert new menu access
                        $sql = "INSERT INTO menu_access (role_id, menu_name, can_view, can_add, can_edit, can_delete) 
                                VALUES (?, ?, ?, ?, ?, ?)";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param('isiiii', $role_id, $menu_name, $can_view, $can_add, $can_edit, $can_delete);
                        
                        if ($stmt->execute()) {
                            $success_count++;
                        } else {
                            $error_count++;
                        }
                    } else {
                        $error_count++;
                    }
                }
            }
            
            if ($success_count > 0) {
                $success = "Berhasil membuat $success_count menu access baru!";
                if ($error_count > 0) {
                    $success .= " $error_count menu access sudah ada atau gagal dibuat.";
                }
            } else {
                $error = "Tidak ada menu access baru yang dibuat. $error_count menu access sudah ada atau gagal dibuat.";
            }
        }
    } elseif ($action === 'bulk_update') {
        // Bulk update menu access
        $selected_ids = $_POST['selected_ids'] ?? [];
        $can_view = isset($_POST['can_view']) ? 1 : 0;
        $can_add = isset($_POST['can_add']) ? 1 : 0;
        $can_edit = isset($_POST['can_edit']) ? 1 : 0;
        $can_delete = isset($_POST['can_delete']) ? 1 : 0;
        
        if (empty($selected_ids)) {
            $error = "Pilih minimal satu menu access untuk diupdate!";
        } else {
            $success_count = 0;
            $error_count = 0;
            
            foreach ($selected_ids as $id) {
                $sql = "UPDATE menu_access SET can_view = ?, can_add = ?, can_edit = ?, can_delete = ? WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param('iiiii', $can_view, $can_add, $can_edit, $can_delete, $id);
                
                if ($stmt->execute()) {
                    $success_count++;
                } else {
                    $error_count++;
                }
            }
            
            if ($success_count > 0) {
                $success = "Berhasil mengupdate $success_count menu access!";
                if ($error_count > 0) {
                    $success .= " $error_count menu access gagal diupdate.";
                }
            } else {
                $error = "Gagal mengupdate menu access. $error_count menu access gagal diupdate.";
            }
        }
    } elseif ($action === 'bulk_delete') {
        // Bulk delete menu access
        $selected_ids = $_POST['selected_ids'] ?? [];
        
        if (empty($selected_ids)) {
            $error = "Pilih minimal satu menu access untuk dihapus!";
        } else {
            $success_count = 0;
            $error_count = 0;
            
            foreach ($selected_ids as $id) {
                $sql = "DELETE FROM menu_access WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param('i', $id);
                
                if ($stmt->execute()) {
                    $success_count++;
                } else {
                    $error_count++;
                }
            }
            
            if ($success_count > 0) {
                $success = "Berhasil menghapus $success_count menu access!";
                if ($error_count > 0) {
                    $success .= " $error_count menu access gagal dihapus.";
                }
            } else {
                $error = "Gagal menghapus menu access. $error_count menu access gagal dihapus.";
            }
        }
    }
}

// Get current menu access for bulk update/delete
$current_access_sql = "SELECT ma.*, r.nama_role 
                       FROM menu_access ma 
                       INNER JOIN roles r ON ma.role_id = r.id 
                       ORDER BY r.nama_role, ma.menu_name";
$current_access_result = $conn->query($current_access_sql);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bulk Operations Menu Access - MINVEN</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .operation-section {
            border: 1px solid #dee2e6;
            border-radius: 0.375rem;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        .operation-section h6 {
            color: #495057;
            border-bottom: 2px solid #007bff;
            padding-bottom: 0.5rem;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <?php include '../templates/navbar.php'; ?>

    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="bi bi-gear-wide-connected me-2"></i>
                            Bulk Operations Menu Access
                        </h5>
                        <a href="index.php" class="btn btn-light btn-sm">
                            <i class="bi bi-arrow-left me-1"></i>
                            Kembali ke Daftar
                        </a>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="bi bi-exclamation-triangle me-2"></i>
                                <?= htmlspecialchars($error) ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <?php if ($success): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="bi bi-check-circle me-2"></i>
                                <?= htmlspecialchars($success) ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <!-- Bulk Create Section -->
                        <div class="operation-section">
                            <h6>
                                <i class="bi bi-plus-circle text-success me-2"></i>
                                Bulk Create Menu Access
                            </h6>
                            <form method="POST">
                                <input type="hidden" name="action" value="bulk_create">
                                <div class="row">
                                    <div class="col-md-6">
                                        <label class="form-label">Pilih Roles:</label>
                                        <div class="border p-3" style="max-height: 200px; overflow-y: auto;">
                                            <?php foreach ($roles as $role): ?>
                                            <div class="form-check">
                                                <input type="checkbox" name="selected_roles[]" value="<?= $role['id'] ?>" 
                                                       class="form-check-input" id="role_<?= $role['id'] ?>">
                                                <label for="role_<?= $role['id'] ?>" class="form-check-label">
                                                    <?= htmlspecialchars($role['nama_role']) ?>
                                                </label>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Pilih Menus:</label>
                                        <div class="border p-3" style="max-height: 200px; overflow-y: auto;">
                                            <?php foreach ($available_menus as $key => $name): ?>
                                            <div class="form-check">
                                                <input type="checkbox" name="selected_menus[]" value="<?= $key ?>" 
                                                       class="form-check-input" id="menu_<?= $key ?>">
                                                <label for="menu_<?= $key ?>" class="form-check-label">
                                                    <?= htmlspecialchars($name) ?>
                                                </label>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="row mt-3">
                                    <div class="col-12">
                                        <label class="form-label">Permissions:</label>
                                        <div class="row">
                                            <div class="col-md-3">
                                                <div class="form-check">
                                                    <input type="checkbox" name="can_view" class="form-check-input" id="create_view">
                                                    <label for="create_view" class="form-check-label">View</label>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="form-check">
                                                    <input type="checkbox" name="can_add" class="form-check-input" id="create_add">
                                                    <label for="create_add" class="form-check-label">Add</label>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="form-check">
                                                    <input type="checkbox" name="can_edit" class="form-check-input" id="create_edit">
                                                    <label for="create_edit" class="form-check-label">Edit</label>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="form-check">
                                                    <input type="checkbox" name="can_delete" class="form-check-input" id="create_delete">
                                                    <label for="create_delete" class="form-check-label">Delete</label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="mt-3">
                                    <button type="submit" class="btn btn-success">
                                        <i class="bi bi-plus-circle me-1"></i>
                                        Bulk Create
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- Bulk Update Section -->
                        <div class="operation-section">
                            <h6>
                                <i class="bi bi-pencil text-warning me-2"></i>
                                Bulk Update Menu Access
                            </h6>
                            <form method="POST">
                                <input type="hidden" name="action" value="bulk_update">
                                <div class="row">
                                    <div class="col-md-8">
                                        <label class="form-label">Pilih Menu Access untuk Update:</label>
                                        <div class="border p-3" style="max-height: 300px; overflow-y: auto;">
                                            <?php while ($access = $current_access_result->fetch_assoc()): ?>
                                            <div class="form-check">
                                                <input type="checkbox" name="selected_ids[]" value="<?= $access['id'] ?>" 
                                                       class="form-check-input" id="update_<?= $access['id'] ?>">
                                                <label for="update_<?= $access['id'] ?>" class="form-check-label">
                                                    <strong><?= htmlspecialchars($access['nama_role']) ?></strong> - 
                                                    <?= htmlspecialchars($access['menu_name']) ?>
                                                    <small class="text-muted">(ID: <?= $access['id'] ?>)</small>
                                                </label>
                                            </div>
                                            <?php endwhile; ?>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Set Permissions:</label>
                                        <div class="border p-3">
                                            <div class="form-check">
                                                <input type="checkbox" name="can_view" class="form-check-input" id="update_view">
                                                <label for="update_view" class="form-check-label">View</label>
                                            </div>
                                            <div class="form-check">
                                                <input type="checkbox" name="can_add" class="form-check-input" id="update_add">
                                                <label for="update_add" class="form-check-label">Add</label>
                                            </div>
                                            <div class="form-check">
                                                <input type="checkbox" name="can_edit" class="form-check-input" id="update_edit">
                                                <label for="update_edit" class="form-check-label">Edit</label>
                                            </div>
                                            <div class="form-check">
                                                <input type="checkbox" name="can_delete" class="form-check-input" id="update_delete">
                                                <label for="update_delete" class="form-check-label">Delete</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="mt-3">
                                    <button type="submit" class="btn btn-warning">
                                        <i class="bi bi-pencil me-1"></i>
                                        Bulk Update
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- Bulk Delete Section -->
                        <div class="operation-section">
                            <h6>
                                <i class="bi bi-trash text-danger me-2"></i>
                                Bulk Delete Menu Access
                            </h6>
                            <form method="POST" onsubmit="return confirm('Apakah Anda yakin ingin menghapus menu access yang dipilih? Tindakan ini tidak dapat dibatalkan!')">
                                <input type="hidden" name="action" value="bulk_delete">
                                <div class="row">
                                    <div class="col-12">
                                        <label class="form-label">Pilih Menu Access untuk Hapus:</label>
                                        <div class="border p-3" style="max-height: 300px; overflow-y: auto;">
                                            <?php 
                                            $current_access_result->data_seek(0);
                                            while ($access = $current_access_result->fetch_assoc()): 
                                            ?>
                                            <div class="form-check">
                                                <input type="checkbox" name="selected_ids[]" value="<?= $access['id'] ?>" 
                                                       class="form-check-input" id="delete_<?= $access['id'] ?>">
                                                <label for="delete_<?= $access['id'] ?>" class="form-check-label">
                                                    <strong><?= htmlspecialchars($access['nama_role']) ?></strong> - 
                                                    <?= htmlspecialchars($access['menu_name']) ?>
                                                    <small class="text-muted">(ID: <?= $access['id'] ?>)</small>
                                                </label>
                                            </div>
                                            <?php endwhile; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="mt-3">
                                    <button type="submit" class="btn btn-danger">
                                        <i class="bi bi-trash me-1"></i>
                                        Bulk Delete
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-check view permission if other permissions are checked
        document.addEventListener('DOMContentLoaded', function() {
            const createView = document.getElementById('create_view');
            const createAdd = document.getElementById('create_add');
            const createEdit = document.getElementById('create_edit');
            const createDelete = document.getElementById('create_delete');

            const updateView = document.getElementById('update_view');
            const updateAdd = document.getElementById('update_add');
            const updateEdit = document.getElementById('update_edit');
            const updateDelete = document.getElementById('update_delete');

            function updateViewPermission(viewCheckbox, addCheckbox, editCheckbox, deleteCheckbox) {
                if (addCheckbox.checked || editCheckbox.checked || deleteCheckbox.checked) {
                    viewCheckbox.checked = true;
                }
            }

            // Create form
            createAdd.addEventListener('change', () => updateViewPermission(createView, createAdd, createEdit, createDelete));
            createEdit.addEventListener('change', () => updateViewPermission(createView, createAdd, createEdit, createDelete));
            createDelete.addEventListener('change', () => updateViewPermission(createView, createAdd, createEdit, createDelete));

            // Update form
            updateAdd.addEventListener('change', () => updateViewPermission(updateView, updateAdd, updateEdit, updateDelete));
            updateEdit.addEventListener('change', () => updateViewPermission(updateView, updateAdd, updateEdit, updateDelete));
            updateDelete.addEventListener('change', () => updateViewPermission(updateView, updateAdd, updateEdit, updateDelete));
        });
    </script>
</body>
</html> 
