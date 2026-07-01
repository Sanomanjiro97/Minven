<?php
session_start();
require_once '../config.php';
require_once '../includes/page_access_check.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

// Check access to edit menu access
if (!hasAccess('menu_access', 'edit')) {
    $_SESSION['error'] = "Akses tidak diizinkan untuk mengedit Menu Access";
    header("Location: index.php");
    exit();
}

$id = $_GET['id'] ?? 0;

if (!$id) {
    $_SESSION['error'] = "ID Menu Access tidak valid!";
    header("Location: index.php");
    exit();
}

$error = null;
$success = null;

// Get menu access details
$sql = "SELECT ma.*, r.nama_role 
        FROM menu_access ma 
        INNER JOIN roles r ON ma.role_id = r.id 
        WHERE ma.id = ?";
if (!isset($conn) || !$conn) {
    $_SESSION['error'] = "Koneksi database gagal!";
    header("Location: index.php");
    exit();
}
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
    'edit_nama_gudang' => 'Edit Nama Gudang',
    'tambah_gudang' => 'Tambah Gudang'
];

$has_can_complete = db_has_column($conn, 'menu_access', 'can_complete');
$has_can_send_wa = db_has_column($conn, 'menu_access', 'can_send_wa');
$has_can_setup_split = db_has_column($conn, 'menu_access', 'can_setup_split');
if (!$has_can_setup_split) {
    $conn->query("ALTER TABLE menu_access ADD COLUMN can_setup_split TINYINT(1) NOT NULL DEFAULT 0 AFTER can_delete");
    $has_can_setup_split = db_has_column($conn, 'menu_access', 'can_setup_split');
}

$menu_access['can_setup_split'] = $menu_access['can_setup_split'] ?? 0;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $role_id = $_POST['role_id'];
    $menu_name = $_POST['menu_name'];
    $can_view = isset($_POST['can_view']) ? 1 : 0;
    $can_add = isset($_POST['can_add']) ? 1 : 0;
    $can_edit = isset($_POST['can_edit']) ? 1 : 0;
    $can_delete = isset($_POST['can_delete']) ? 1 : 0;
    $can_approve = isset($_POST['can_approve']) ? 1 : 0;
    $can_export = isset($_POST['can_export']) ? 1 : 0;
    $can_import = isset($_POST['can_import']) ? 1 : 0;
    $can_setup_split = ($has_can_setup_split && $menu_name === 'barang' && isset($_POST['can_setup_split'])) ? 1 : 0;
    $can_complete = ($has_can_complete && ($menu_name === 'purchase_order' || $menu_name === 'payment') && isset($_POST['can_complete'])) ? 1 : 0;
    $can_send_wa = ($has_can_send_wa && ($menu_name === 'purchase_order' || $menu_name === 'vendor_refund') && isset($_POST['can_send_wa'])) ? 1 : 0;

    // Validation
    if (empty($role_id) || empty($menu_name)) {
        $error = "Role dan Menu harus dipilih!";
    } else {
        // Check if menu access already exists for this role and menu (excluding current record)
        $check_sql = "SELECT id FROM menu_access WHERE role_id = ? AND menu_name = ? AND id != ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param('isi', $role_id, $menu_name, $id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result->num_rows > 0) {
            $error = "Menu access untuk role dan menu ini sudah ada!";
        } else {
            // Update menu access
            $sql = "UPDATE menu_access SET role_id = ?, menu_name = ?, can_view = ?, can_add = ?, can_edit = ?, can_delete = ?, can_approve = ?, can_export = ?, can_import = ?, can_setup_split = ?, can_complete = ?, can_send_wa = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('isiiiiiiiiiii', $role_id, $menu_name, $can_view, $can_add, $can_edit, $can_delete, $can_approve, $can_export, $can_import, $can_setup_split, $can_complete, $can_send_wa, $id);
            
            if ($stmt->execute()) {
                $success = "Menu access berhasil diupdate!";
                // Refresh menu access data
                $menu_access['role_id'] = $role_id;
                $menu_access['menu_name'] = $menu_name;
                $menu_access['can_view'] = $can_view;
                $menu_access['can_add'] = $can_add;
                $menu_access['can_edit'] = $can_edit;
                $menu_access['can_delete'] = $can_delete;
                $menu_access['can_approve'] = $can_approve;
                $menu_access['can_export'] = $can_export;
                $menu_access['can_import'] = $can_import;
                $menu_access['can_setup_split'] = $can_setup_split;
                if ($has_can_complete) {
                    $menu_access['can_complete'] = $can_complete;
                }
                if ($has_can_send_wa) {
                    $menu_access['can_send_wa'] = $can_send_wa;
                }
            } else {
                $error = "Gagal mengupdate menu access: " . $conn->error;
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Menu Access - MINVEN</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
    <?php include '../templates/navbar.php'; ?>

    <div class="container-fluid mt-4">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow">
                    <div class="card-header bg-warning text-dark d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="bi bi-pencil me-2"></i>
                            Edit Menu Access
                        </h5>
                        <a href="view.php?id=<?= $id ?>" class="btn btn-info btn-sm">
                            <i class="bi bi-eye me-1"></i>
                            Lihat Detail
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

                        <form method="POST">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="role_id" class="form-label">Role <span class="text-danger">*</span></label>
                                        <select id="role_id" name="role_id" class="form-select" required>
                                            <option value="">Pilih Role</option>
                                            <?php foreach ($roles as $role): ?>
                                                <option value="<?= $role['id'] ?>" <?= $menu_access['role_id'] == $role['id'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($role['nama_role']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="menu_name" class="form-label">Menu <span class="text-danger">*</span></label>
                                        <select id="menu_name" name="menu_name" class="form-select" required>
                                            <option value="">Pilih Menu</option>
                                            <?php foreach ($available_menus as $key => $name): ?>
                                                <option value="<?= $key ?>" <?= $menu_access['menu_name'] == $key ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($name) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-12">
                                    <label class="form-label">Permissions:</label>
                                    <div class="row">
                                        <div class="col-md-2">
                                            <div class="form-check">
                                                <input type="checkbox" id="can_view" name="can_view" class="form-check-input" 
                                                       <?= $menu_access['can_view'] ? 'checked' : '' ?>>
                                                <label for="can_view" class="form-check-label">
                                                    <i class="bi bi-eye text-info me-1"></i>
                                                    View
                                                </label>
                                            </div>
                                        </div>
                                        <div class="col-md-2">
                                            <div class="form-check">
                                                <input type="checkbox" id="can_add" name="can_add" class="form-check-input"
                                                       <?= $menu_access['can_add'] ? 'checked' : '' ?>>
                                                <label for="can_add" class="form-check-label">
                                                    <i class="bi bi-plus-circle text-success me-1"></i>
                                                    Add
                                                </label>
                                            </div>
                                        </div>
                                        <div class="col-md-2">
                                            <div class="form-check">
                                                <input type="checkbox" id="can_edit" name="can_edit" class="form-check-input"
                                                       <?= $menu_access['can_edit'] ? 'checked' : '' ?>>
                                                <label for="can_edit" class="form-check-label">
                                                    <i class="bi bi-pencil text-warning me-1"></i>
                                                    Edit
                                                </label>
                                            </div>
                                        </div>
                                        <div class="col-md-2">
                                            <div class="form-check">
                                                <input type="checkbox" id="can_delete" name="can_delete" class="form-check-input"
                                                       <?= $menu_access['can_delete'] ? 'checked' : '' ?>>
                                                <label for="can_delete" class="form-check-label">
                                                    <i class="bi bi-trash text-danger me-1"></i>
                                                    Delete
                                                </label>
                                            </div>
                                        </div>
                                        <div class="col-md-2">
                                            <div class="form-check">
                                                <input type="checkbox" id="can_approve" name="can_approve" class="form-check-input"
                                                       <?= !empty($menu_access['can_approve']) ? 'checked' : '' ?>>
                                                <label for="can_approve" class="form-check-label">
                                                    <i class="bi bi-check-all text-primary me-1"></i>
                                                    Approve
                                                </label>
                                            </div>
                                        </div>
                                        <div class="col-md-2">
                                            <div class="form-check">
                                                <input type="checkbox" id="can_export" name="can_export" class="form-check-input"
                                                       <?= !empty($menu_access['can_export']) ? 'checked' : '' ?>>
                                                <label for="can_export" class="form-check-label">
                                                    <i class="bi bi-file-earmark-excel text-success me-1"></i>
                                                    Export
                                                </label>
                                            </div>
                                        </div>
                                        <div class="col-md-2">
                                            <div class="form-check">
                                                <input type="checkbox" id="can_import" name="can_import" class="form-check-input"
                                                       <?= !empty($menu_access['can_import']) ? 'checked' : '' ?>>
                                                <label for="can_import" class="form-check-label">
                                                    <i class="bi bi-file-earmark-arrow-up text-info me-1"></i>
                                                    Import
                                                </label>
                                            </div>
                                        </div>
                                        <?php if ($has_can_setup_split): ?>
                                        <div class="col-md-3" id="setup_split_permission_div" style="display: none;">
                                            <div class="form-check">
                                                <input type="checkbox" id="can_setup_split" name="can_setup_split" class="form-check-input"
                                                       <?= !empty($menu_access['can_setup_split']) ? 'checked' : '' ?>>
                                                <label for="can_setup_split" class="form-check-label">
                                                    <i class="bi bi-diagram-3 text-info me-1"></i>
                                                    Setup Split
                                                </label>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                        <?php if ($has_can_complete): ?>
                                        <div class="col-md-2" id="complete_permission_div" style="display: none;">
                                            <div class="form-check">
                                                <input type="checkbox" id="can_complete" name="can_complete" class="form-check-input"
                                                       <?= !empty($menu_access['can_complete']) ? 'checked' : '' ?>>
                                                <label for="can_complete" class="form-check-label">
                                                    <i class="bi bi-check-circle text-primary me-1"></i>
                                                    Complete
                                                </label>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                        <?php if ($has_can_send_wa): ?>
                                        <div class="col-md-2" id="send_wa_permission_div" style="display: none;">
                                            <div class="form-check">
                                                <input type="checkbox" id="can_send_wa" name="can_send_wa" class="form-check-input"
                                                       <?= !empty($menu_access['can_send_wa']) ? 'checked' : '' ?>>
                                                <label for="can_send_wa" class="form-check-label">
                                                    <i class="bi bi-whatsapp text-success me-1"></i>
                                                    Kirim WA
                                                </label>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="row mt-4">
                                <div class="col-12">
                                    <div class="d-flex justify-content-between">
                                        <a href="index.php" class="btn btn-secondary">
                                            <i class="bi bi-arrow-left me-1"></i>
                                            Kembali
                                        </a>
                                        <div>
                                            <a href="view.php?id=<?= $id ?>" class="btn btn-info me-2">
                                                <i class="bi bi-eye me-1"></i>
                                                Lihat Detail
                                            </a>
                                            <button type="submit" class="btn btn-warning">
                                                <i class="bi bi-save me-1"></i>
                                                Update
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-check view permission if other permissions are checked
        document.addEventListener('DOMContentLoaded', function() {
            const viewCheckbox = document.getElementById('can_view');
            const addCheckbox = document.getElementById('can_add');
            const editCheckbox = document.getElementById('can_edit');
            const deleteCheckbox = document.getElementById('can_delete');
            const approveCheckbox = document.getElementById('can_approve');
            const exportCheckbox = document.getElementById('can_export');
            const importCheckbox = document.getElementById('can_import');
            const setupSplitCheckbox = document.getElementById('can_setup_split');
            const completeCheckbox = document.getElementById('can_complete');
            const sendWaCheckbox = document.getElementById('can_send_wa');
            const menuSelect = document.getElementById('menu_name');
            const completeDiv = document.getElementById('complete_permission_div');
            const setupSplitDiv = document.getElementById('setup_split_permission_div');
            const sendWaDiv = document.getElementById('send_wa_permission_div');

            function updateViewPermission() {
                const completeChecked = completeCheckbox ? completeCheckbox.checked : false;
                const setupSplitChecked = setupSplitCheckbox ? setupSplitCheckbox.checked : false;
                const sendWaChecked = sendWaCheckbox ? sendWaCheckbox.checked : false;
                if (addCheckbox.checked || editCheckbox.checked || deleteCheckbox.checked || 
                    approveCheckbox.checked || exportCheckbox.checked || importCheckbox.checked || 
                    setupSplitChecked || completeChecked || sendWaChecked) {
                    viewCheckbox.checked = true;
                }
            }

            function toggleSetupSplitPermission() {
                if (!setupSplitDiv || !setupSplitCheckbox) return;
                if (menuSelect.value === 'barang') {
                    setupSplitDiv.style.display = 'block';
                } else {
                    setupSplitDiv.style.display = 'none';
                    setupSplitCheckbox.checked = false;
                }
            }

            function toggleCompletePermission() {
                if (!completeDiv || !completeCheckbox) return;

                if (menuSelect.value === 'purchase_order' || menuSelect.value === 'payment') {
                    completeDiv.style.display = 'block';
                } else {
                    completeDiv.style.display = 'none';
                    completeCheckbox.checked = false;
                }
            }

            function toggleSendWAPermission() {
                if (!sendWaDiv || !sendWaCheckbox) return;

                if (menuSelect.value === 'purchase_order' || menuSelect.value === 'vendor_refund') {
                    sendWaDiv.style.display = 'block';
                } else {
                    sendWaDiv.style.display = 'none';
                    sendWaCheckbox.checked = false;
                }
            }

            // Initial check
            toggleSetupSplitPermission();
            toggleCompletePermission();
            toggleSendWAPermission();

            // Add event listeners
            menuSelect.addEventListener('change', toggleCompletePermission);
            menuSelect.addEventListener('change', toggleSetupSplitPermission);
            menuSelect.addEventListener('change', toggleSendWAPermission);
            addCheckbox.addEventListener('change', updateViewPermission);
            editCheckbox.addEventListener('change', updateViewPermission);
            deleteCheckbox.addEventListener('change', updateViewPermission);
            approveCheckbox.addEventListener('change', updateViewPermission);
            exportCheckbox.addEventListener('change', updateViewPermission);
            importCheckbox.addEventListener('change', updateViewPermission);
            if (setupSplitCheckbox) {
                setupSplitCheckbox.addEventListener('change', updateViewPermission);
            }
            if (completeCheckbox) {
                completeCheckbox.addEventListener('change', updateViewPermission);
            }
            if (sendWaCheckbox) {
                sendWaCheckbox.addEventListener('change', updateViewPermission);
            }
        });
    </script>
</body>
</html> 
