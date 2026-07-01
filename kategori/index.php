<?php
session_start();
require_once '../config.php';
require_once '../includes/access_check.php';
require_once '../includes/menu_access_helper.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

// Check access untuk menu kategori
if (!checkAccess('kategori', 'view')) {
    $_SESSION['error'] = 'Anda tidak memiliki akses untuk melihat menu kategori!';
    header('Location: ../dashboard.php');
    exit();
}

$filter_session_key = 'filter_kategori_index';
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
    $where[] = "k.kode_kategori LIKE ?";
    $params[] = "%$filter_id%";
    $types .= 's';
}

if ($filter_nama !== '') {
    $where[] = "k.nama_kategori LIKE ?";
    $params[] = "%$filter_nama%";
    $types .= 's';
}

// Ambil data kategori dengan parent
$sql = "SELECT k.*, pk.nama_kategori as parent_name 
        FROM kategori k 
        LEFT JOIN kategori pk ON k.parent_id = pk.id 
";
if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}
$sql .= " ORDER BY k.nama_kategori";

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
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Manajemen Kategori - Sistem Inventory</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.0.7/css/boxicons.min.css" rel="stylesheet" />
</head>
<body>
<?php include '../templates/navbar.php'; ?>

<div class="container-fluid mt-4">
    <div class="card hover-lift scale-in mb-4">
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
                <div class="d-flex align-items-center gap-3 flex-wrap">
                    <h2 class="mb-0 fw-bold" style="font-size:1.3rem;">Manajemen Kategori</h2>
                    <?php if (checkAccess('kategori', 'view')): ?>
                        <button class="btn btn-success hover-lift" onclick="downloadTemplate()">
                            <i class='bx bx-download'></i> Download Template
                        </button>
                        <button class="btn btn-info hover-lift" data-bs-toggle="modal" data-bs-target="#importKategoriModal">
                            <i class='bx bx-import'></i> Import
                        </button>
                    <?php endif; ?>
                    <?php if (checkAccess('kategori', 'add')): ?>
                        <button class="btn btn-primary hover-lift" data-bs-toggle="modal" data-bs-target="#addKategoriModal">
                            <i class='bx bx-plus'></i> Tambah Kategori
                        </button>
                    <?php endif; ?>
                </div>
            </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show slide-up" role="alert">
            <?= $_SESSION['success']; unset($_SESSION['success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show slide-up" role="alert">
            <?= $_SESSION['error']; unset($_SESSION['error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

            <form method="get" class="row g-2 align-items-end mb-3">
                <div class="col-md-3">
                    <label for="filter_id" class="form-label mb-1">Cari Kode/ID</label>
                    <input type="text" class="form-control" id="filter_id" name="filter_id" value="<?= htmlspecialchars($filter_id) ?>" placeholder="Kode kategori...">
                </div>
                <div class="col-md-4">
                    <label for="filter_nama" class="form-label mb-1">Cari Nama</label>
                    <input type="text" class="form-control" id="filter_nama" name="filter_nama" value="<?= htmlspecialchars($filter_nama) ?>" placeholder="Nama kategori...">
                </div>
                <div class="col-md-5 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="index.php?reset=1" class="btn btn-secondary">Reset</a>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Kode</th>
                            <th>Nama Kategori</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $no = 1; ?>
                        <?php while($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td><?= htmlspecialchars($row['kode_kategori']) ?></td>
                            <td><?= htmlspecialchars($row['nama_kategori']) ?></td>
                            <td>
                                <!-- Tombol edit dan hapus -->
                                <?php if (checkAccess('kategori', 'edit')): ?>
                                <button class="btn btn-sm btn-warning hover-lift btn-edit" 
                                        data-id="<?= $row['id'] ?>" 
                                        data-kode="<?= htmlspecialchars($row['kode_kategori']) ?>" 
                                        data-nama="<?= htmlspecialchars($row['nama_kategori']) ?>" 
                                        data-parent="<?= $row['parent_id'] ?>">
                                    <i class="bx bx-edit"></i> Edit
                                </button>
                                <?php endif; ?>
                                <?php if (checkAccess('kategori', 'delete')): ?>
                                <a href="process.php?action=delete&id=<?= $row['id'] ?>" class="btn btn-sm btn-danger hover-lift" 
                                   onclick="return confirm('Apakah Anda yakin ingin menghapus kategori ini?')">
                                    <i class="bx bx-trash"></i> Hapus
                                </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add Kategori Modal -->
<div class="modal fade" id="addKategoriModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class='bx bx-plus-circle me-2'></i>
                    Tambah Kategori
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="process.php" method="post">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Kode Kategori</label>
                        <input type="text" class="form-control" name="kode_kategori" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nama Kategori</label>
                        <input type="text" class="form-control" name="nama_kategori" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary hover-lift" data-bs-dismiss="modal">
                        <i class='bx bx-x me-1'></i> Batal
                    </button>
                    <button type="submit" class="btn btn-primary hover-lift" name="action" value="add">
                        <i class='bx bx-save me-1'></i> Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Kategori Modal -->
<div class="modal fade" id="editKategoriModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class='bx bx-edit me-2'></i>
                    Edit Kategori
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="process.php" method="post" id="editKategoriForm">
                <div class="modal-body">
                    <input type="hidden" name="id" id="edit-id" required>
                    <div class="mb-3">
                        <label class="form-label">Kode Kategori</label>
                        <input type="text" class="form-control" name="kode_kategori" id="edit-kode" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nama Kategori</label>
                        <input type="text" class="form-control" name="nama_kategori" id="edit-nama" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary hover-lift" data-bs-dismiss="modal">
                        <i class='bx bx-x me-1'></i> Batal
                    </button>
                    <button type="submit" class="btn btn-primary hover-lift" name="action" value="edit">
                        <i class='bx bx-save me-1'></i> Update
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Import Kategori Modal -->
<div class="modal fade" id="importKategoriModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Import Kategori</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="import_kategori.php" method="post" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">File Excel (XLSX)</label>
                        <input type="file" class="form-control" name="xlsx_file" accept=".xlsx" required>
                        <div class="form-text">
                            <a href="import_kategori.php?action=download">Download template</a>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Import</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
// Check if Bootstrap is loaded properly
if (typeof bootstrap === 'undefined') {
    console.error('Bootstrap is not loaded properly');
    alert('Bootstrap tidak dimuat dengan benar. Silakan refresh halaman.');
}

document.addEventListener('DOMContentLoaded', function() {
    const editButtons = document.querySelectorAll('.btn-edit');
    const editModalElement = document.getElementById('editKategoriModal');
    
    // Check if modal element exists before initializing
    if (editModalElement) {
        const editModal = new bootstrap.Modal(editModalElement);

        editButtons.forEach(btn => {
            btn.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                const kode = this.getAttribute('data-kode');
                const nama = this.getAttribute('data-nama');
               

                document.getElementById('edit-id').value = id;
                document.getElementById('edit-kode').value = kode;
                document.getElementById('edit-nama').value = nama;
               

                editModal.show();
            });
        });
    }
    
    // Add error handling for all modals
    const modalTriggers = document.querySelectorAll('[data-bs-toggle="modal"]');
    modalTriggers.forEach(trigger => {
        trigger.addEventListener('click', function(e) {
            const targetId = this.getAttribute('data-bs-target');
            const targetModal = document.querySelector(targetId);
            
            if (!targetModal) {
                e.preventDefault();
                console.error('Modal target not found:', targetId);
                alert('Modal tidak ditemukan. Silakan refresh halaman.');
                return;
            }
            
            // Fallback: manually show modal if Bootstrap fails
            try {
                const bsModal = bootstrap.Modal.getInstance(targetModal);
                if (bsModal) {
                    bsModal.show();
                } else {
                    // Manual fallback
                    targetModal.style.display = 'block';
                    targetModal.classList.add('show');
                    document.body.classList.add('modal-open');
                    
                    // Add backdrop
                    const backdrop = document.createElement('div');
                    backdrop.className = 'modal-backdrop fade show';
                    document.body.appendChild(backdrop);
                    
                    // Close modal on backdrop click
                    backdrop.addEventListener('click', function() {
                        targetModal.style.display = 'none';
                        targetModal.classList.remove('show');
                        document.body.classList.remove('modal-open');
                        backdrop.remove();
                    });
                    
                    // Close modal on close button click
                    const closeButtons = targetModal.querySelectorAll('[data-bs-dismiss="modal"]');
                    closeButtons.forEach(btn => {
                        btn.addEventListener('click', function() {
                            targetModal.style.display = 'none';
                            targetModal.classList.remove('show');
                            document.body.classList.remove('modal-open');
                            backdrop.remove();
                        });
                    });
                }
            } catch (error) {
                console.error('Error showing modal:', error);
                alert('Terjadi kesalahan saat membuka modal. Silakan refresh halaman.');
            }
        });
    });
});

// Function to download template
function downloadTemplate() {
    // Redirect to download template page
    window.location.href = 'download_template.php';
}
</script>
</body>
</html>
