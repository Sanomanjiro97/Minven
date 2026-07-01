<?php
session_start();
require_once '../config.php';
require_once '../includes/access_check.php';
require_once '../includes/menu_access_helper.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

// Check access untuk menu supplier
if (!checkAccess('supplier', 'view')) {
    $_SESSION['error'] = 'Anda tidak memiliki akses untuk melihat menu supplier!';
    header('Location: ../dashboard.php');
    exit();
}

// Check access untuk view supplier
if (!hasAccess('supplier', 'view')) {
    $_SESSION['error'] = "Akses tidak diizinkan untuk melihat data supplier";
    header("Location: ../dashboard.php");
    exit();
}

$filter_session_key = 'filter_supplier_index';
if (isset($_GET['reset']) && (string)$_GET['reset'] === '1') {
    unset($_SESSION[$filter_session_key]);
    header('Location: index.php');
    exit();
}

$saved_filters = [];
if (isset($_SESSION[$filter_session_key]) && is_array($_SESSION[$filter_session_key])) {
    $saved_filters = $_SESSION[$filter_session_key];
}

$filter_keys = ['filter_id', 'filter_nama'];
$has_any_filter_param = false;
foreach ($filter_keys as $k) {
    if (array_key_exists($k, $_GET)) {
        $has_any_filter_param = true;
        break;
    }
}

if (!$has_any_filter_param && !empty($saved_filters)) {
    $redirect_params = [];
    foreach ($filter_keys as $k) {
        if (array_key_exists($k, $saved_filters) && (string)$saved_filters[$k] !== '') {
            $redirect_params[$k] = (string)$saved_filters[$k];
        }
    }
    if (!empty($redirect_params)) {
        header('Location: index.php?' . http_build_query($redirect_params));
        exit();
    }
}

$filter_id = isset($_GET['filter_id']) ? trim((string)$_GET['filter_id']) : trim((string)($saved_filters['filter_id'] ?? ''));
$filter_nama = isset($_GET['filter_nama']) ? trim((string)$_GET['filter_nama']) : trim((string)($saved_filters['filter_nama'] ?? ''));

$_SESSION[$filter_session_key] = [
    'filter_id' => $filter_id,
    'filter_nama' => $filter_nama,
];

$where = [];
$params = [];
$types = '';

if ($filter_id !== '') {
    $where[] = "kode_supplier LIKE ?";
    $params[] = "%$filter_id%";
    $types .= 's';
}

if ($filter_nama !== '') {
    $where[] = "nama_supplier LIKE ?";
    $params[] = "%$filter_nama%";
    $types .= 's';
}

$sql = "SELECT * FROM supplier";
if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}
$sql .= " ORDER BY nama_supplier";

if (!empty($where)) {
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        die("Query error: " . $conn->error);
    }
    $bind = [$types];
    foreach ($params as $i => $val) {
        $bind[] = &$params[$i];
    }
    call_user_func_array([$stmt, 'bind_param'], $bind);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query($sql);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Supplier - Sistem Inventory</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.0.7/css/boxicons.min.css" rel="stylesheet">
    <style>
        body {
            background: #ffffff;
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .card {
            border-radius: 0.75rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            background: #ffffff;
            border: 1px solid #e2e8f0;
        }
        
        .supplier-table thead th {
            background: #007bff;
            color: white;
            border: none;
            padding: 0.75rem 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.8rem;
            letter-spacing: 0.05em;
        }

        .supplier-table {
            width: 100%;
        }

        .supplier-table td {
            vertical-align: middle;
        }

        .supplier-table .nowrap {
            white-space: nowrap;
        }

        .supplier-table .col-kode { width: 140px; }
        .supplier-table .col-nama { width: 220px; }
        .supplier-table .col-alamat { width: 280px; max-width: 280px; word-break: break-word; }
        .supplier-table .col-telepon { width: 140px; }
        .supplier-table .col-email { width: 240px; max-width: 240px; word-break: break-word; }
        .supplier-table .col-norek { width: 180px; }
        .supplier-table .col-top { width: 90px; }
        .supplier-table .col-gambar { width: 120px; }
        .supplier-table .col-aksi { width: 110px; }
    </style>
</head>
<body>
    <?php include '../templates/navbar.php'; ?>

    <div class="container mt-4">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2 class="mb-0 text-dark">
                        <i class='bx bx-store-alt'></i> Manajemen Supplier
                    </h2>
                    <div class="d-flex gap-2">
                        <?php if (hasAccess('supplier', 'add')): ?>
                            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addSupplierModal">
                                <i class='bx bx-plus'></i> Tambah Supplier
                            </button>
                            <div class="btn-group" role="group">
                                <a href="import_supplier.php" class="btn btn-success btn-sm">
                                    <i class='bx bx-import'></i> Import
                                </a>
                                <a href="download_template.php" class="btn btn-info btn-sm text-white">
                                    <i class='bx bx-download'></i> Template
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?= $_SESSION['success']; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['success']); ?>
                <?php endif; ?>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?= $_SESSION['error']; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['error']); ?>
                <?php endif; ?>

                <form method="get" class="row g-2 align-items-end mb-3">
                    <div class="col-md-3">
                        <label for="filter_id" class="form-label mb-1">Cari Kode/ID</label>
                        <input type="text" class="form-control" id="filter_id" name="filter_id" value="<?= htmlspecialchars($filter_id) ?>" placeholder="Kode supplier...">
                    </div>
                    <div class="col-md-4">
                        <label for="filter_nama" class="form-label mb-1">Cari Nama</label>
                        <input type="text" class="form-control" id="filter_nama" name="filter_nama" value="<?= htmlspecialchars($filter_nama) ?>" placeholder="Nama supplier...">
                    </div>
                    <div class="col-md-5 d-flex gap-2">
                        <button type="submit" class="btn btn-primary">Filter</button>
                        <a href="index.php?reset=1" class="btn btn-secondary">Reset</a>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="table table-striped table-sm align-middle supplier-table">
                        <thead>
                            <tr>
                                <th class="nowrap text-center" style="width: 60px;">No</th>
                                <th class="col-kode nowrap">Kode Supplier</th>
                                <th class="col-nama">Nama Supplier</th>
                                <th class="col-alamat">Alamat</th>
                                <th class="col-telepon nowrap">Telepon</th>
                                <th class="col-email">Email</th>
                                <th class="col-norek nowrap">No Rekening</th>
                                <th class="col-top nowrap">TOP (hari)</th>
                                <th class="col-gambar nowrap">Gambar</th>
                                <th class="col-aksi nowrap">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $no = 1; ?>
                            <?php while($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td class="nowrap text-center"><?= $no++ ?></td>
                                    <td class="col-kode nowrap"><?= htmlspecialchars($row['kode_supplier']) ?></td>
                                    <td class="col-nama"><?= htmlspecialchars($row['nama_supplier']) ?></td>
                                    <td class="col-alamat"><?= htmlspecialchars($row['alamat']) ?></td>
                                    <td class="col-telepon nowrap"><?= htmlspecialchars($row['telepon']) ?></td>
                                    <td class="col-email"><?= htmlspecialchars($row['email']) ?></td>
                                    <td class="col-norek nowrap"><?= htmlspecialchars($row['no_rekening']) ?></td>
                                    <td class="col-top nowrap"><?= htmlspecialchars($row['terms_of_payment']) ?></td>
                                    <td class="col-gambar nowrap">
                                        <?php if (!empty($row['gambar'])): ?>
                                            <a href="#" class="gambar-preview" data-bs-toggle="modal" data-bs-target="#gambarModal" data-image="../uploads/supplier/<?= htmlspecialchars($row['gambar']) ?>" data-nama="<?= htmlspecialchars($row['nama_supplier']) ?>">
                                                <img src="../uploads/supplier/<?= htmlspecialchars($row['gambar']) ?>" alt="Gambar Supplier" style="max-width: 80px; max-height: 80px; object-fit: cover; border-radius: 4px; cursor: pointer;">
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted">Tidak ada gambar</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="col-aksi nowrap">
                                        <div class="btn-group" role="group">
                                            <?php if (hasAccess('supplier', 'edit')): ?>
                                                <button class="btn btn-sm btn-warning edit-btn" 
                                                        data-id="<?= $row['id'] ?>"
                                                        data-kode="<?= htmlspecialchars($row['kode_supplier']) ?>"
                                                        data-nama="<?= htmlspecialchars($row['nama_supplier']) ?>"
                                                        data-alamat="<?= htmlspecialchars($row['alamat']) ?>"
                                                        data-telepon="<?= htmlspecialchars($row['telepon']) ?>"
                                                        data-email="<?= htmlspecialchars($row['email']) ?>"
                                                        data-no_rekening="<?= htmlspecialchars($row['no_rekening']) ?>"
                                                        data-top="<?= htmlspecialchars($row['terms_of_payment']) ?>">
                                                    <i class='bx bx-edit'></i>
                                                </button>
                                            <?php endif; ?>
                                            <?php if (hasAccess('supplier', 'delete')): ?>
                                                <button class="btn btn-sm btn-danger delete-btn" 
                                                        data-id="<?= $row['id'] ?>"
                                                        data-nama="<?= htmlspecialchars($row['nama_supplier']) ?>">
                                                    <i class='bx bx-trash'></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Tambah Supplier -->
    <div class="modal fade" id="addSupplierModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="process.php" method="post" enctype="multipart/form-data">
                    <div class="modal-header">
                        <h5 class="modal-title">Tambah Supplier</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <?php include 'form_supplier_fields.php'; ?>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="action" value="add" class="btn btn-primary">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Edit Supplier -->
    <div class="modal fade" id="editSupplierModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="process.php" method="post" enctype="multipart/form-data">
                    <input type="hidden" name="id" id="edit_id">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Supplier</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <?php include 'form_supplier_fields_edit.php'; ?>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="action" value="edit" class="btn btn-primary">Update</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Handle edit
        document.querySelectorAll('.edit-btn').forEach(button => {
            button.addEventListener('click', function() {
                document.getElementById('edit_id').value = this.dataset.id;
                document.getElementById('edit_kode_supplier').value = this.dataset.kode;
                document.getElementById('edit_nama_supplier').value = this.dataset.nama;
                document.getElementById('edit_alamat').value = this.dataset.alamat;
                document.getElementById('edit_telepon').value = this.dataset.telepon;
                document.getElementById('edit_email').value = this.dataset.email;
                document.getElementById('edit_no_rekening').value = this.dataset.no_rekening;
                document.getElementById('edit_terms_of_payment').value = this.dataset.top;
                new bootstrap.Modal(document.getElementById('editSupplierModal')).show();
            });
        });

        // Handle delete
        document.querySelectorAll('.delete-btn').forEach(button => {
            button.addEventListener('click', function() {
                if(confirm('Apakah Anda yakin ingin menghapus supplier ini?')) {
                    const id = this.dataset.id;
                    window.location.href = `process.php?action=delete&id=${id}`;
                }
            });
        });

        // Handle gambar preview
        document.querySelectorAll('.gambar-preview').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const imageUrl = this.dataset.image;
                const namaSupplier = this.dataset.nama;
                
                document.getElementById('gambarModalLabel').textContent = 'Gambar Supplier: ' + namaSupplier;
                document.getElementById('gambarModalImage').src = imageUrl;
                document.getElementById('gambarModalImage').alt = 'Gambar Supplier ' + namaSupplier;
            });
        });
    </script>

    <!-- Modal Gambar -->
    <div class="modal fade" id="gambarModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="gambarModalLabel">Gambar Supplier</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <img id="gambarModalImage" src="" alt="" class="img-fluid" style="max-height: 70vh; object-fit: contain;">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
