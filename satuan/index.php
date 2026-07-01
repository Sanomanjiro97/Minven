<?php
session_start();
require_once '../config.php';
require_once '../includes/access_check.php';
require_once '../includes/menu_access_helper.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

// Check access untuk menu satuan
if (!checkAccess('satuan', 'view')) {
    $_SESSION['error'] = 'Anda tidak memiliki akses untuk melihat menu satuan!';
    header('Location: ../dashboard.php');
    exit();
}

$filter_session_key = 'filter_satuan_index';
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
    $where[] = "s.kode_satuan LIKE ?";
    $params[] = "%$filter_id%";
    $types .= 's';
}

if ($filter_nama !== '') {
    $where[] = "s.nama_satuan LIKE ?";
    $params[] = "%$filter_nama%";
    $types .= 's';
}

// Query untuk mengambil data satuan
$sql = "SELECT s.*, u.nama as created_by_name FROM satuan s
        LEFT JOIN users u ON s.created_by = u.id 
";
if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}
$sql .= " ORDER BY s.nama_satuan";

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

$page_title = "Manajemen Satuan";
include '../templates/header.php';
include '../templates/navbar.php';
?>

<style>
    .dashboard-container {
        margin-left: 260px;
        padding: 2rem;
        transition: all 0.3s ease;
    }
    
    @media (max-width: 991.98px) {
        .dashboard-container {
            margin-left: 0;
            padding: 1rem;
        }
    }
</style>

<div class="dashboard-container">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mb-0">Manajemen Satuan</h2>
            <div class="d-flex gap-2">
                <a href="import.php" class="btn btn-warning">
                    <i class='bx bx-import'></i> Import Data
                </a>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSatuanModal">
                    <i class='bx bx-plus'></i> Tambah Satuan
                </button>
            </div>
        </div>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= $_SESSION['success']; unset($_SESSION['success']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= $_SESSION['error']; unset($_SESSION['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="card mb-4">
            <div class="card-body">
                <form method="get" class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label for="filter_id" class="form-label">Cari Kode/ID</label>
                        <input type="text" class="form-control" id="filter_id" name="filter_id" value="<?= htmlspecialchars($filter_id) ?>" placeholder="Kode satuan...">
                    </div>
                    <div class="col-md-4">
                        <label for="filter_nama" class="form-label">Cari Nama</label>
                        <input type="text" class="form-control" id="filter_nama" name="filter_nama" value="<?= htmlspecialchars($filter_nama) ?>" placeholder="Nama satuan...">
                    </div>
                    <div class="col-md-4 d-flex gap-2">
                        <button type="submit" class="btn btn-primary">Filter</button>
                        <a href="index.php?reset=1" class="btn btn-secondary">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped table-hover mb-0">
                        <thead class="table-dark">
                            <tr>
                                <th class="ps-3">No</th>
                                <th>Kode Satuan</th>
                                <th>Nama Satuan</th>
                                <th>Dibuat Oleh</th>
                                <th>Tanggal Dibuat</th>
                                <th class="text-center pe-3">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result->num_rows > 0): ?>
                                <?php $no = 1; ?>
                                <?php while($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td class="ps-3"><?= $no++ ?></td>
                                    <td><?= htmlspecialchars($row['kode_satuan']) ?></td>
                                    <td><?= htmlspecialchars($row['nama_satuan']) ?></td>
                                    <td><?= htmlspecialchars($row['created_by_name'] ?? 'System') ?></td>
                                    <td><?= date('d-m-Y H:i', strtotime($row['created_at'])) ?></td>
                                    <td class="text-center pe-3">
                                        <div class="btn-group" role="group">
                                            <button class="btn btn-sm btn-warning edit-btn" 
                                                data-id="<?= $row['id'] ?>"
                                                data-kode="<?= htmlspecialchars($row['kode_satuan']) ?>"
                                                data-nama="<?= htmlspecialchars($row['nama_satuan']) ?>">
                                                <i class='bx bx-edit'></i>
                                            </button>
                                            <button class="btn btn-sm btn-danger delete-btn" 
                                                data-id="<?= $row['id'] ?>"
                                                data-nama="<?= htmlspecialchars($row['nama_satuan']) ?>">
                                                <i class='bx bx-trash'></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4">Tidak ada data ditemukan.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Modal -->
<div class="modal fade" id="addSatuanModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="process.php" method="post">
                <input type="hidden" name="action" value="add">
                <div class="modal-header">
                    <h5 class="modal-title">Tambah Satuan Baru</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="kode_satuan" class="form-label">Kode Satuan</label>
                        <input type="text" class="form-control" id="kode_satuan" name="kode_satuan" required>
                    </div>
                    <div class="mb-3">
                        <label for="nama_satuan" class="form-label">Nama Satuan</label>
                        <input type="text" class="form-control" id="nama_satuan" name="nama_satuan" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editSatuanModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="process.php" method="post">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Satuan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit_kode" class="form-label">Kode Satuan</label>
                        <input type="text" class="form-control" id="edit_kode" name="kode_satuan" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_nama" class="form-label">Nama Satuan</label>
                        <input type="text" class="form-control" id="edit_nama" name="nama_satuan" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Update</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('.edit-btn').click(function() {
        const id = $(this).data('id');
        const kode = $(this).data('kode');
        const nama = $(this).data('nama');
        
        $('#edit_id').val(id);
        $('#edit_kode').val(kode);
        $('#edit_nama').val(nama);
        
        const modal = new bootstrap.Modal(document.getElementById('editSatuanModal'));
        modal.show();
    });

    $('.delete-btn').click(function() {
        const id = $(this).data('id');
        const nama = $(this).data('nama');
        
        if (confirm(`Apakah Anda yakin ingin menghapus satuan "${nama}"?`)) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'process.php';
            
            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'delete';
            
            const idInput = document.createElement('input');
            idInput.type = 'hidden';
            idInput.name = 'id';
            idInput.value = id;
            
            form.appendChild(actionInput);
            form.appendChild(idInput);
            document.body.appendChild(form);
            form.submit();
        }
    });
});
</script>

<?php include '../templates/footer.php'; ?>
