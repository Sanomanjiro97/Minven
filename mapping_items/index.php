<?php
session_start();
require_once '../config.php'; // koneksi $conn
require_once '../includes/access_check.php';
require_once '../includes/menu_access_helper.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

// Check access untuk menu mapping_items
if (!checkAccess('mapping_items', 'view')) {
    $_SESSION['error'] = 'Anda tidak memiliki akses untuk melihat menu mapping items!';
    header('Location: ../dashboard.php');
    exit();
}

$filter_session_key = 'filter_mapping_items_index';
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

// Handle submit mapping
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $barang_id = $_POST['barang_id'];
    $lokasi_id = $_POST['lokasi_id'];
    $created_by = $_SESSION['user_id'];

    // Cek apakah mapping sudah ada
    $stmt = $conn->prepare("SELECT id FROM item_mapping WHERE barang_id = ? AND lokasi_id = ?");
    $stmt->bind_param("ii", $barang_id, $lokasi_id);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        // Update jika sudah ada
        $stmt_update = $conn->prepare("UPDATE item_mapping 
            SET aktif = 1, updated_by = ?, updated_at = NOW() 
            WHERE barang_id = ? AND lokasi_id = ?");
        $stmt_update->bind_param("iii", $created_by, $barang_id, $lokasi_id);
        $stmt_update->execute();
        $stmt_update->close();
        echo "<script>alert('Mapping diperbarui!'); window.location.href='index.php';</script>";
    } else {
        // Insert jika belum ada
        $stmt_insert = $conn->prepare("INSERT INTO item_mapping (barang_id, lokasi_id, aktif, created_by) 
            VALUES (?, ?, 1, ?)");
        $stmt_insert->bind_param("iii", $barang_id, $lokasi_id, $created_by);
        if ($stmt_insert->execute()) {
            echo "<script>alert('Mapping berhasil disimpan!'); window.location.href='index.php';</script>";
        } else {
            echo "Gagal insert: " . $conn->error;
        }
        $stmt_insert->close();
    }
    $stmt->close();
    exit;
}

include_once __DIR__ . '/../templates/header.php';
include_once __DIR__ . '/../templates/navbar.php';

// Get barang data
$sql_barang = "SELECT * FROM barang ORDER BY nama_barang";
$barang = $conn->query($sql_barang);
if (!$barang) {
    die("Error fetching barang: " . $conn->error);
}

// Get lokasi data
$sql_lokasi = "SELECT * FROM lokasi_mapping WHERE aktif = 1 ORDER BY nama_lokasi";
$lokasi = $conn->query($sql_lokasi);
if (!$lokasi) {
    die("Error fetching lokasi: " . $conn->error);
}

// Get existing mappings
$filter_id = isset($_GET['filter_id']) ? trim((string)$_GET['filter_id']) : trim((string)($saved_filters['filter_id'] ?? ''));
$filter_nama = isset($_GET['filter_nama']) ? trim((string)$_GET['filter_nama']) : trim((string)($saved_filters['filter_nama'] ?? ''));

$_SESSION[$filter_session_key] = [
    'filter_id' => $filter_id,
    'filter_nama' => $filter_nama,
];

$mapping_where = [];
$mapping_params = [];
$mapping_types = '';

if ($filter_id !== '') {
    $mapping_where[] = "(CAST(b.id AS CHAR) LIKE ? OR b.kode_barang LIKE ?)";
    $mapping_params[] = "%$filter_id%";
    $mapping_params[] = "%$filter_id%";
    $mapping_types .= 'ss';
}

if ($filter_nama !== '') {
    $mapping_where[] = "b.nama_barang LIKE ?";
    $mapping_params[] = "%$filter_nama%";
    $mapping_types .= 's';
}

$sql_mapping = "SELECT im.*, b.nama_barang, l.nama_lokasi, l.kode_lokasi 
                FROM item_mapping im 
                JOIN barang b ON im.barang_id = b.id 
                JOIN lokasi_mapping l ON im.lokasi_id = l.id";
if (!empty($mapping_where)) {
    $sql_mapping .= " WHERE " . implode(" AND ", $mapping_where);
}
$sql_mapping .= " ORDER BY b.nama_barang";

$mapping = null;
if (!empty($mapping_where)) {
    $stmt_mapping = $conn->prepare($sql_mapping);
    if ($stmt_mapping) {
        $bind = [$mapping_types];
        foreach ($mapping_params as $i => $val) {
            $bind[] = &$mapping_params[$i];
        }
        call_user_func_array([$stmt_mapping, 'bind_param'], $bind);
        $stmt_mapping->execute();
        $mapping = $stmt_mapping->get_result();
    }
} else {
    $mapping = $conn->query($sql_mapping);
}
if (!$mapping) {
    // If table doesn't exist, create it
    $create_table_sql = "CREATE TABLE IF NOT EXISTS item_mapping (
        id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
        barang_id INT(11) NOT NULL,
        lokasi_id INT(11) NOT NULL,
        aktif TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP NOT NULL DEFAULT current_timestamp(),
        updated_at TIMESTAMP NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
        FOREIGN KEY (barang_id) REFERENCES barang(id) ON DELETE RESTRICT,
        FOREIGN KEY (lokasi_id) REFERENCES lokasi_mapping(id) ON DELETE RESTRICT,
        UNIQUE KEY unique_mapping (barang_id, lokasi_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";
    
    if ($conn->query($create_table_sql)) {
        if (!empty($mapping_where)) {
            $stmt_mapping2 = $conn->prepare($sql_mapping);
            if (!$stmt_mapping2) {
                die("Error fetching mapping: " . $conn->error);
            }
            $bind2 = [$mapping_types];
            foreach ($mapping_params as $i => $val) {
                $bind2[] = &$mapping_params[$i];
            }
            call_user_func_array([$stmt_mapping2, 'bind_param'], $bind2);
            $stmt_mapping2->execute();
            $mapping = $stmt_mapping2->get_result();
        } else {
            $mapping = $conn->query($sql_mapping);
        }
    } else {
        die("Error creating table: " . $conn->error);
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Mapping Item ke Lokasi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<div class="container-fluid mt-4">
    <div class="card hover-lift scale-in mb-4">
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
                <div class="d-flex align-items-center gap-3 flex-wrap">
                    <h3 class="mb-0 fw-bold" style="font-size:1.3rem;">Form Mapping Item ke Lokasi</h3>
                    <a href="download_template.php" class="btn btn-success">
                        <i class="fas fa-download"></i> Download Template
                    </a>
                    <a href="import.php" class="btn btn-warning">
                        <i class="fas fa-file-import"></i> Import
                    </a>
                    <a href="lokasi.php" class="btn btn-primary">
                        <i class="fas fa-cog"></i> Manajemen Lokasi
                </a>
        </div>
    </div>

    <?php
    if (isset($_SESSION['success'])) {
        echo '<div class="alert alert-success">' . $_SESSION['success'] . '</div>';
        unset($_SESSION['success']);
    }
    if (isset($_SESSION['error'])) {
        echo '<div class="alert alert-danger">' . $_SESSION['error'] . '</div>';
        unset($_SESSION['error']);
    }
    ?>

    <div class="card p-4 shadow-sm mb-5">
        <form action="process.php" method="POST">
            <input type="hidden" name="action" value="add">
            <div class="mb-3">
                <label for="barang_id" class="form-label">Barang</label>
                <select name="barang_id" class="form-select" id="barang_id" required>
                    <option value="">-- Pilih Barang --</option>
                    <?php while ($b = $barang->fetch_assoc()) { ?>
                        <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['nama_barang']) ?></option>
                    <?php } ?>
                </select>
            </div>
            <div class="mb-3">
                <label for="lokasi_id" class="form-label">Lokasi</label>
                <select name="lokasi_id" class="form-select" id="lokasi_id" required>
                    <option value="">-- Pilih Lokasi --</option>
                    <?php while ($l = $lokasi->fetch_assoc()) { ?>
                        <option value="<?= $l['id'] ?>"><?= htmlspecialchars($l['nama_lokasi']) ?> (<?= htmlspecialchars($l['kode_lokasi']) ?>)</option>
                    <?php } ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">Simpan Mapping</button>
        </form>
    </div>

    <h4>Data Mapping Saat Ini</h4>
    <div class="row g-2 align-items-end mb-3">
        <div class="col-lg-8">
            <form method="get" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label for="filter_id" class="form-label mb-1">Cari ID/Kode Barang</label>
                    <input type="text" class="form-control" id="filter_id" name="filter_id" value="<?= htmlspecialchars($filter_id) ?>" placeholder="ID/Kode barang...">
                </div>
                <div class="col-md-5">
                    <label for="filter_nama" class="form-label mb-1">Cari Nama Barang</label>
                    <input type="text" class="form-control" id="filter_nama" name="filter_nama" value="<?= htmlspecialchars($filter_nama) ?>" placeholder="Nama barang...">
                </div>
                <div class="col-md-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="index.php?reset=1" class="btn btn-secondary">Reset</a>
                </div>
            </form>
        </div>
        <div class="col-lg-4">
            <label for="statusFilter" class="form-label mb-1">Status</label>
            <select id="statusFilter" class="form-select">
                <option value="">Semua Status</option>
                <option value="1">Aktif</option>
                <option value="0">Tidak Aktif</option>
            </select>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-bordered table-hover table-striped">
            <thead class="table-dark">
                <tr>
                    <th>No</th>
                    <th>Barang</th>
                    <th>Lokasi</th>
                    <th>Kode Lokasi</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $no = 1;
                if (is_object($mapping)) {
                    while ($row = $mapping->fetch_assoc()) { 
                ?>
                    <tr data-status="<?= $row['aktif'] ?>">
                        <td><?= $no++ ?></td>
                        <td><?= htmlspecialchars($row['nama_barang']) ?></td>
                        <td><?= htmlspecialchars($row['nama_lokasi']) ?></td>
                        <td><?= htmlspecialchars($row['kode_lokasi']) ?></td>
                        <td><?= $row['aktif'] ? '<span class="badge bg-success">Aktif</span>' : '<span class="badge bg-danger">Tidak Aktif</span>' ?></td>
                        <td>
                            <form action="process.php" method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="toggle">
                                <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                <button type="submit" class="btn btn-sm <?= $row['aktif'] ? 'btn-danger' : 'btn-success' ?>" 
                                        onclick="return confirm('<?= $row['aktif'] ? 'Nonaktifkan' : 'Aktifkan' ?> mapping ini?')"
                                        title="<?= $row['aktif'] ? 'Nonaktifkan' : 'Aktifkan' ?> mapping">
                                    <i class="fas <?= $row['aktif'] ? 'fa-times' : 'fa-check' ?>"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php 
                    }
                }
                ?>
            </tbody>
        </table>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<style>
.badge {
    font-size: 0.8em;
}

.table td {
    vertical-align: middle;
}

.btn-sm {
    margin: 0 2px;
    transition: all 0.3s ease;
}

.btn-sm:hover {
    transform: scale(1.1);
}

.form-select.w-auto {
    width: auto !important;
}

@media (max-width: 768px) {
    .form-select.w-auto {
        width: 100% !important;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Status filter functionality
    const statusFilter = document.getElementById('statusFilter');
    const tableRows = document.querySelectorAll('table tbody tr');
    
    statusFilter.addEventListener('change', function() {
        const selectedStatus = this.value;
        
        tableRows.forEach(row => {
            const status = row.getAttribute('data-status');
            
            if (selectedStatus === '') {
                row.style.display = '';
            } else if (selectedStatus === status) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    });
});
</script>
</body>
</html>
