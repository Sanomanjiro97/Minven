<?php
session_start();
require_once '../config.php';
require_once '../includes/page_access_check.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

// Check access to view menu access
if (!hasAccess('menu_access', 'view')) {
    $_SESSION['error'] = "Akses tidak diizinkan untuk melihat Menu Access";
    header("Location: index.php");
    exit();
}

$id = $_GET['id'] ?? 0;

if (!$id) {
    $_SESSION['error'] = "ID Menu Access tidak valid!";
    header("Location: index.php");
    exit();
}

// Get menu access details
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
    'reset_stok' => 'Reset Stok Gudang',
    'edit_nama_gudang' => 'Edit Nama Gudang'
];

$menu_display_name = $menu_names[$menu_access['menu_name']] ?? $menu_access['menu_name'];
$has_can_complete = db_has_column($conn, 'menu_access', 'can_complete');
$has_can_setup_split = db_has_column($conn, 'menu_access', 'can_setup_split');
$menu_access['can_complete'] = $menu_access['can_complete'] ?? 0;
$menu_access['can_setup_split'] = $menu_access['can_setup_split'] ?? 0;
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Menu Access - MINVEN</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .permission-badge {
            font-size: 0.9rem;
            padding: 0.5rem 1rem;
        }
        .permission-yes {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .permission-no {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .info-card {
            background-color: #f8f9fa;
            border-left: 4px solid #007bff;
        }
    </style>
</head>
<body>
    <?php include '../templates/navbar.php'; ?>

    <div class="container-fluid mt-4">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow">
                    <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="bi bi-eye me-2"></i>
                            Detail Menu Access
                        </h5>
                        <div>
                            <?php if (hasAccess('menu_access', 'edit')): ?>
                            <a href="edit.php?id=<?= $menu_access['id'] ?>" class="btn btn-warning btn-sm">
                                <i class="bi bi-pencil me-1"></i>
                                Edit
                            </a>
                            <?php endif; ?>
                            <a href="index.php" class="btn btn-secondary btn-sm">
                                <i class="bi bi-arrow-left me-1"></i>
                                Kembali
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="info-card p-3 mb-3">
                                    <h6 class="text-primary mb-2">
                                        <i class="bi bi-shield me-2"></i>
                                        Informasi Role
                                    </h6>
                                    <p class="mb-1"><strong>Role ID:</strong> <?= $menu_access['role_id'] ?></p>
                                    <p class="mb-0"><strong>Nama Role:</strong> 
                                        <span class="badge bg-primary"><?= htmlspecialchars($menu_access['nama_role']) ?></span>
                                    </p>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="info-card p-3 mb-3">
                                    <h6 class="text-primary mb-2">
                                        <i class="bi bi-list me-2"></i>
                                        Informasi Menu
                                    </h6>
                                    <p class="mb-1"><strong>Menu ID:</strong> <?= $menu_access['id'] ?></p>
                                    <p class="mb-0"><strong>Nama Menu:</strong> 
                                        <span class="badge bg-info"><?= htmlspecialchars($menu_display_name) ?></span>
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-12">
                                <div class="info-card p-3 mb-3">
                                    <h6 class="text-primary mb-3">
                                        <i class="bi bi-key me-2"></i>
                                        Permissions
                                    </h6>
                                    <div class="row">
                                        <div class="col-md-2">
                                            <div class="text-center">
                                                <span class="badge permission-badge <?= $menu_access['can_view'] ? 'permission-yes' : 'permission-no' ?>">
                                                    <i class="bi bi-eye me-1"></i>
                                                    View
                                                </span>
                                                <p class="mt-2 mb-0">
                                                    <strong><?= $menu_access['can_view'] ? 'Diizinkan' : 'Tidak Diizinkan' ?></strong>
                                                </p>
                                            </div>
                                        </div>
                                        <div class="col-md-2">
                                            <div class="text-center">
                                                <span class="badge permission-badge <?= $menu_access['can_add'] ? 'permission-yes' : 'permission-no' ?>">
                                                    <i class="bi bi-plus-circle me-1"></i>
                                                    Add
                                                </span>
                                                <p class="mt-2 mb-0">
                                                    <strong><?= $menu_access['can_add'] ? 'Diizinkan' : 'Tidak Diizinkan' ?></strong>
                                                </p>
                                            </div>
                                        </div>
                                        <div class="col-md-2">
                                            <div class="text-center">
                                                <span class="badge permission-badge <?= $menu_access['can_edit'] ? 'permission-yes' : 'permission-no' ?>">
                                                    <i class="bi bi-pencil me-1"></i>
                                                    Edit
                                                </span>
                                                <p class="mt-2 mb-0">
                                                    <strong><?= $menu_access['can_edit'] ? 'Diizinkan' : 'Tidak Diizinkan' ?></strong>
                                                </p>
                                            </div>
                                        </div>
                                        <div class="col-md-2">
                                            <div class="text-center">
                                                <span class="badge permission-badge <?= $menu_access['can_delete'] ? 'permission-yes' : 'permission-no' ?>">
                                                    <i class="bi bi-trash me-1"></i>
                                                    Delete
                                                </span>
                                                <p class="mt-2 mb-0">
                                                    <strong><?= $menu_access['can_delete'] ? 'Diizinkan' : 'Tidak Diizinkan' ?></strong>
                                                </p>
                                            </div>
                                        </div>
                                        <?php if ($has_can_setup_split): ?>
                                        <div class="col-md-2">
                                            <div class="text-center">
                                                <span class="badge permission-badge <?= $menu_access['can_setup_split'] ? 'permission-yes' : 'permission-no' ?>">
                                                    <i class="bi bi-diagram-3 me-1"></i>
                                                    Setup Split
                                                </span>
                                                <p class="mt-2 mb-0">
                                                    <strong><?= $menu_access['can_setup_split'] ? 'Diizinkan' : 'Tidak Diizinkan' ?></strong>
                                                </p>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                        <?php if ($has_can_complete): ?>
                                        <div class="col-md-2">
                                            <div class="text-center">
                                                <span class="badge permission-badge <?= $menu_access['can_complete'] ? 'permission-yes' : 'permission-no' ?>">
                                                    <i class="bi bi-check-circle me-1"></i>
                                                    Complete
                                                </span>
                                                <p class="mt-2 mb-0">
                                                    <strong><?= $menu_access['can_complete'] ? 'Diizinkan' : 'Tidak Diizinkan' ?></strong>
                                                </p>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="info-card p-3 mb-3">
                                    <h6 class="text-primary mb-2">
                                        <i class="bi bi-calendar-plus me-2"></i>
                                        Informasi Timestamp
                                    </h6>
                                    <p class="mb-1"><strong>Dibuat pada:</strong> <?= date('d/m/Y H:i:s', strtotime($menu_access['created_at'])) ?></p>
                                    <p class="mb-0"><strong>Diupdate pada:</strong> <?= date('d/m/Y H:i:s', strtotime($menu_access['updated_at'])) ?></p>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="info-card p-3 mb-3">
                                    <h6 class="text-primary mb-2">
                                        <i class="bi bi-info-circle me-2"></i>
                                        Informasi Tambahan
                                    </h6>
                                    <p class="mb-1"><strong>Menu Key:</strong> <code><?= htmlspecialchars($menu_access['menu_name']) ?></code></p>
                                    <p class="mb-0"><strong>Status:</strong> 
                                        <span class="badge bg-success">Aktif</span>
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="row mt-4">
                            <div class="col-12">
                                <div class="d-flex justify-content-between">
                                    <a href="index.php" class="btn btn-secondary">
                                        <i class="bi bi-arrow-left me-1"></i>
                                        Kembali ke Daftar
                                    </a>
                                    <?php if (hasAccess('menu_access', 'edit')): ?>
                                    <a href="edit.php?id=<?= $menu_access['id'] ?>" class="btn btn-warning">
                                        <i class="bi bi-pencil me-1"></i>
                                        Edit Menu Access
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 
