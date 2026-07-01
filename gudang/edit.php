<?php
session_start();
require_once '../config.php';
require_once '../includes/access_check.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

// Ambil ID dari parameter URL
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
// Ambil parameter gudang dari URL (default: central)
$gudang = isset($_GET['gudang']) ? $_GET['gudang'] : 'central';

// Set gudang_id berdasarkan parameter gudang
if ($gudang == 'antapani') {
    $gudang_id = 13;
    $redirect_page = 'gudang_antapani.php';
    $process_file = 'process_antapani.php';
    $gudang_name = 'Antapani';
    $menu_key = 'gudang_antapani';
} else {
    $gudang_id = 23;
    $redirect_page = 'gudang_central.php';
    $process_file = 'process_central.php';
    $gudang_name = 'Central';
    $menu_key = 'gudang_central';
}

$stmt_gudang = $conn->prepare("SELECT nama_gudang FROM gudang WHERE id = ?");
if ($stmt_gudang) {
    $stmt_gudang->bind_param('i', $gudang_id);
    $stmt_gudang->execute();
    $row_gudang = $stmt_gudang->get_result()->fetch_assoc();
    if ($row_gudang && isset($row_gudang['nama_gudang']) && trim((string)$row_gudang['nama_gudang']) !== '') {
        $gudang_name = (string)$row_gudang['nama_gudang'];
    }
    $stmt_gudang->close();
}

if (!checkAccess($menu_key, 'edit')) {
    $_SESSION['error'] = "Akses tidak diizinkan untuk mengedit stok Gudang " . trim(strip_tags((string)$gudang_name)) . "!";
    header("Location: $redirect_page");
    exit();
}

// Jika ID tidak valid, redirect ke halaman utama
if ($id <= 0) {
    header("Location: $redirect_page");
    exit;
}

// Ambil data stok dari database
$stmt = $conn->prepare("SELECT 
    gs.id, 
    b.kode_barang, 
    b.nama_barang, 
    gs.stok_awal, 
    gs.stok_terpakai,
    gs.stok_sisa,
    gs.stok_minimum,
    gs.expire_date
FROM gudang_stok gs
JOIN barang b ON gs.barang_id = b.id
WHERE gs.id = ? AND gs.gudang_id = ?");
$stmt->bind_param("ii", $id, $gudang_id);
$stmt->execute();
$result = $stmt->get_result();

// Jika data tidak ditemukan, redirect ke halaman utama
if ($result->num_rows == 0) {
    header("Location: $redirect_page");
    exit;
}

$stok = $result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Stok - Gudang <?= htmlspecialchars($gudang_name) ?></title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Boxicons CSS -->
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="shortcut icon" type="image/png" href="/minven_pro/asset/LOGO1.png">
    <link rel="apple-touch-icon" href="/minven_pro/asset/LOGO1.png">
    <style>
        body {
            background-color: #f8f9fa;
            padding-top: 20px;
        }
        .card {
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            border-radius: 10px;
        }
        .card-header {
            background-color: #4e73df;
            color: white;
            border-radius: 10px 10px 0 0 !important;
        }
        .btn-primary {
            background-color: #4e73df;
            border-color: #4e73df;
        }
        .btn-primary:hover {
            background-color: #2e59d9;
            border-color: #2e59d9;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class='bx bx-edit me-2'></i>Edit Stok Barang - Gudang <?= htmlspecialchars($gudang_name) ?></h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-4">
                            <h6 class="fw-bold">Detail Barang:</h6>
                            <p class="mb-1"><strong>Kode:</strong> <?= htmlspecialchars($stok['kode_barang']) ?></p>
                            <p class="mb-0"><strong>Nama:</strong> <?= htmlspecialchars($stok['nama_barang']) ?></p>
                        </div>
                        
                        <form method="POST" action="<?= $process_file ?>" id="editForm">
                            <input type="hidden" name="action" value="update_stok">
                            <input type="hidden" name="edit_stok_id" value="<?= $stok['id'] ?>">
                            <input type="hidden" name="gudang_id" value="<?= $gudang_id ?>">
                            <input type="hidden" name="redirect" value="<?= $redirect_page ?>">
                            
                            <div class="mb-3">
                                <label for="edit_stok_awal" class="form-label">Stok Awal</label>
                                <input type="number" name="edit_stok_awal" id="edit_stok_awal" class="form-control" 
                                       value="<?= $stok['stok_awal'] ?>" readonly>
                            </div>
                            
                            <div class="mb-3">
                                <label for="edit_stok_terpakai" class="form-label">Stok Terpakai</label>
                                <input type="number" name="edit_stok_terpakai" id="edit_stok_terpakai" class="form-control"
                                       value="<?= $stok['stok_terpakai'] ?>" required min="0">
                            </div>
                            
                            <div class="mb-3">
                                <label for="edit_stok_minimum" class="form-label">Par Stok</label>
                                <input type="number" name="edit_stok_minimum" id="edit_stok_minimum" class="form-control"
                                       value="<?= $stok['stok_minimum'] ?>" required min="0">
                            </div>
                            
                            <div class="mb-3">
                                <label for="edit_stok_sisa" class="form-label">Stok Sisa</label>
                                <input type="number" name="edit_stok_sisa" id="edit_stok_sisa" class="form-control"
                                       value="<?= $stok['stok_sisa'] ?>" required min="0">
                            </div>
                            
                            <div class="mb-3">
                                <label for="edit_expire_date" class="form-label">Tanggal Kadaluarsa</label>
                                <?php
                                // Saat menampilkan form edit
                                $expire_date_value = ($stok['expire_date'] && $stok['expire_date'] != '0000-00-00') ? $stok['expire_date'] : '';
                                ?>
                                <input type="date" class="form-control" id="edit_expire_date" name="edit_expire_date" value="<?= $expire_date_value ?>">
                            </div>
                            
                            <div class="d-flex justify-content-between">
                                <a href="<?= $redirect_page ?>" class="btn btn-secondary">
                                    <i class='bx bx-arrow-back me-1'></i>Kembali
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class='bx bx-save me-1'></i>Simpan Perubahan
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
