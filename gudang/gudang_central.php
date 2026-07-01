<?php
session_start();
require_once '../config.php';
require_once '../includes/access_check.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

$gudang_id = 23;
$gudang_nama = '';
$stmt_gudang = $conn->prepare("SELECT nama_gudang FROM gudang WHERE id = ?");
if ($stmt_gudang) {
    $stmt_gudang->bind_param('i', $gudang_id);
    $stmt_gudang->execute();
    $gudang_row = $stmt_gudang->get_result()->fetch_assoc();
    if ($gudang_row && isset($gudang_row['nama_gudang'])) {
        $gudang_nama = (string)$gudang_row['nama_gudang'];
    }
    $stmt_gudang->close();
}
$gudang_nama = $gudang_nama !== '' ? $gudang_nama : 'Gudang';
$gudang_nama_plain = trim(strip_tags($gudang_nama));

if (!checkAccess('gudang_central', 'view')) {
    $_SESSION['error'] = 'Anda tidak memiliki akses untuk melihat ' . $gudang_nama_plain . '!';
    header('Location: /minven_pro/dashboard.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_stok'])) {
    if (!checkAccess('gudang_central', 'delete')) {
        $_SESSION['error'] = 'Anda tidak memiliki akses untuk reset stok ' . $gudang_nama_plain . '!';
        header('Location: gudang_central.php');
        exit();
    }

    $nama_tabel = 'gudang_stok';
    $reset = $conn->query("UPDATE $nama_tabel SET jumlah = 0, stok_awal = 0, stok_terpakai = 0, stok_sisa = 0 WHERE gudang_id = $gudang_id");

    if ($reset) {
        $_SESSION['success'] = "Semua stok di " . $gudang_nama_plain . " berhasil direset menjadi 0.";
    } else {
        $_SESSION['error'] = "Gagal mereset stok: " . $conn->error;
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Tampilkan notifikasi jika ada
if (isset($_SESSION['reset_notification']) && (time() - $_SESSION['reset_notification']['time']) < 3600) {
    $notification = $_SESSION['reset_notification'];
    $notifType = isset($notification['type']) ? $notification['type'] : 'success';
    $notifMessage = $notification['message'];
    
    echo "<script>
        document.addEventListener('DOMContentLoaded', function() {
            showToast('{$notifMessage}', '{$notifType}');
            " . ($notifType == 'success' ? "playSound('notificationSound');" : "playSound('warningSound');") . "
        });
    </script>";
    
    // Hapus notifikasi setelah ditampilkan sekali
    if ((time() - $_SESSION['reset_notification']['time']) > 60) {
        unset($_SESSION['reset_notification']);
    }
}
// Query untuk data stok gudang Central (ID 23)
$sql = "SELECT
    gs.id,
    gs.barang_id,
    b.kode_barang,
    b.barcode,
    b.nama_barang,
    k.nama_kategori,
    s.nama_satuan,
    gs.stok_awal,
    gs.stok_terpakai,
    (gs.stok_awal - gs.stok_terpakai) as stok_akhir,
    gs.stok_minimum,
    gs.expire_date,
    gs.updated_at,
    gs.last_reset,
    u.nama as updated_by,
    GROUP_CONCAT(DISTINCT lm.nama_lokasi) as nama_lokasi
FROM gudang_stok gs
LEFT JOIN barang b ON gs.barang_id = b.id
LEFT JOIN kategori k ON b.kategori_id = k.id
LEFT JOIN satuan s ON b.satuan_id = s.id
LEFT JOIN users u ON gs.modified_by = u.id
LEFT JOIN item_mapping im ON im.barang_id = gs.barang_id AND im.aktif = 1
LEFT JOIN lokasi_mapping lm ON lm.id = im.lokasi_id AND lm.aktif = 1
WHERE gs.gudang_id = $gudang_id
GROUP BY gs.id";

$result = $conn->query($sql);
function resetStokHarian($conn) {
    date_default_timezone_set('Asia/Jakarta');
    $current_time = date('H:i');
    
    // Cek jika sudah jam 00:00
    if ($current_time == '22:54') {
        try {
            $conn->begin_transaction();
            
            // Update stok_awal dengan stok_akhir dan reset stok_terpakai
            $queryReset = "UPDATE gudang_stok 
                          SET stok_awal = (stok_awal - stok_terpakai),
                              stok_terpakai = 0,
                              updated_at = NOW(),
                              last_reset = NOW()
                          WHERE gudang_id = 23";
            
            $stmt = $conn->prepare($queryReset);
            if ($stmt === false) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            
            $executed = $stmt->execute();
            if ($executed === false) {
                throw new Exception("Execute failed: " . $stmt->error);
            }
            
            $conn->commit();
            
            // Catat log
            error_log("Reset stok harian berhasil pada " . date('Y-m-d H:i:s'));
            
        } catch (Exception $e) {
            $conn->rollback();
            error_log("Gagal reset stok harian: " . $e->getMessage());
            // Re-throw the exception to show it to the user
            throw $e;
        }
    }
}

// Panggil fungsi reset stok
resetStokHarian($conn);
// Debug information
if (!$result) {
    error_log("Database error in gudang_central.php: " . $conn->error);
    $_SESSION['error'] = "Error executing query: " . $conn->error;
} else {
    error_log("Query executed successfully. Found " . $result->num_rows . " rows");
}

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($gudang_nama) ?> - Sistem Inventory</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/animate.css@4.1.1/animate.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
    <link rel="shortcut icon" type="image/png" href="/minven_pro/asset/LOGO1.png">
    <link rel="apple-touch-icon" href="/minven_pro/asset/LOGO1.png">
    <audio id="notificationSound" src="../asset/masuk.mp3" preload="auto"></audio>
    <audio id="warningSound" src="../asset/warning.mp3" preload="auto"></audio>
    <audio id="stokhabisSound" src="../asset/stokhabis.mp3" preload="auto"></audio>
    
    <style>
    :root {
        --primary-color: #0d6efd;
        --secondary-color: #6c757d;
        --success-color: #198754;
        --danger-color: #dc3545;
        --warning-color: #ffc107;
        --info-color: #0dcaf0;
    }

    /* === Status Stok === */
    .stok-aman {
        background-color: rgba(25, 135, 84, 0.05) !important;
        border-left: 4px solid var(--success-color);
    }

    .stok-minimum {
        background-color: rgba(255, 193, 7, 0.05) !important;
        border-left: 4px solid var(--warning-color);
        animation: pulse 2s infinite;
    }

    .stok-habis {
        background-color: rgba(220, 53, 69, 0.05) !important;
        border-left: 4px solid var(--danger-color);
    }

    .stok-aman td:first-child,
    .stok-minimum td:first-child,
    .stok-habis td:first-child {
        background-color: #ffffff !important;
    }

    /* === Table Status Colors === */
    .table-success {
        background-color: rgba(25, 135, 84, 0.05) !important;
        border-left: 4px solid var(--success-color);
    }

    .table-warning {
        background-color: rgba(255, 193, 7, 0.05) !important;
        border-left: 4px solid var(--warning-color);
        animation: pulse 2s infinite;
    }

    .table-danger {
        background-color: rgba(220, 53, 69, 0.05) !important;
        border-left: 4px solid var(--danger-color);
    }

    .table-success td:first-child,
    .table-warning td:first-child,
    .table-danger td:first-child {
        background-color: #ffffff !important;
    }

    /* === Umum & Layout === */
    body {
        font-size: 0.875rem;
        padding-bottom: 70px;
        background-color: #f8f9fa;
    }

    .container, .container-fluid {
        padding-left: 0.75rem;
        padding-right: 0.75rem;
    }

    .card {
        border: none;
        border-radius: 10px;
        box-shadow: 0 10px 20px rgba(0, 0, 0, 0.05);
        margin-bottom: 1rem;
        transition: all 0.3s ease;
    }

    .card:hover {
        transform: translateY(-3px);
        box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
    }

    .alert h4,
    .card-header h5 {
        font-size: 1.1rem;
    }

    .card-header .btn {
        font-size: 0.8rem;
        padding: 0.25rem 0.5rem;
    }

    .card-header .btn .bx {
        font-size: 1rem;
    }

    /* === Tabel === */
    .table-responsive-custom {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        margin: 0 -0.75rem 1rem;
        border: 1px solid #dee2e6;
        border-radius: 0.25rem;
    }

    .table {
        width: 100% !important;
        margin-bottom: 0;
    }

    .table th, .table td {
        vertical-align: middle;
        text-align: center;
    }

    .table thead {
        background: linear-gradient(135deg, var(--primary-color), #0b5ed7);
        color: white;
    }

    .table thead th {
        font-size: 0.7rem;
        font-weight: 600;
        padding: 0.4rem 0.25rem;
        position: sticky;
        top: 0;
        z-index: 10;
        border: none;
        text-transform: uppercase;
        letter-spacing: 0.3px;
    }

    .table thead th:first-child,
    .table tbody td:first-child {
        position: sticky;
        left: 0;
        z-index: 5;
    }

    .table thead th:first-child {
        background-color: #0b5ed7;
        z-index: 11;
    }

    .table tbody td:first-child {
        background-color: #ffffff;
    }

    .table td {
        word-break: normal;
    }

    .table .stok-terpakai {
        max-width: 55px;
        min-width: 45px;
        font-size: 0.75rem;
        padding: 0.1rem 0.2rem;
    }

    .table .action-buttons .btn {
        font-size: 0.7rem;
        padding: 0.15rem 0.3rem;
    }

    .table .action-buttons .btn .bx {
        font-size: 0.9rem;
    }

    /* === Input Kustom === */
    .sisa-stok-input {
        width: 150px;
        min-width: 150px;
        padding: 8px 12px;
        font-size: 1.05em;
        text-align: center;
        border: 2px solid #dee2e6;
        border-radius: 8px;
        transition: all 0.3s ease;
        margin: 0 auto;
        display: block;
    }

    /* === Form Elements === */
    .form-control,
    .form-select {
        border-radius: 8px;
        border: 1px solid #dee2e6;
        padding: 10px 15px;
        transition: all 0.3s ease;
    }

    .form-control:focus,
    .form-select:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
        transform: translateY(-1px);
    }

    /* === Button === */
    .btn {
        transition: all 0.3s ease;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        border-radius: 8px;
        padding: 8px 16px;
    }

    .btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 10px rgba(0, 0, 0, 0.1);
    }

    /* === Alerts === */
    .alert {
        border-radius: 8px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        transition: all 0.3s ease;
    }

    .alert:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 10px rgba(0, 0, 0, 0.1);
    }

    /* === Animasi === */
    @keyframes pulse {
        0% { background-color: rgba(255, 193, 7, 0.05); }
        50% { background-color: rgba(255, 193, 7, 0.15); }
        100% { background-color: rgba(255, 193, 7, 0.05); }
    }

    @keyframes shake {
        0%, 100% { transform: translateX(0); }
        25% { transform: translateX(-3px); }
        75% { transform: translateX(3px); }
    }

    /* === Mobile Responsive === */
    @media (max-width: 767.98px) {
        .page-title {
            font-size: 1.2rem;
            margin-bottom: 0.75rem;
        }

        .btn {
            font-size: 0.8rem;
            padding: 0.3rem 0.6rem;
        }

        .btn-sm {
            font-size: 0.7rem;
            padding: 0.2rem 0.4rem;
        }

        .form-control,
        .form-select {
            font-size: 0.8rem;
        }

        .modal-body {
            font-size: 0.8rem;
        }

        .modal-header .btn-close {
            padding: 0.2rem 0.4rem;
        }

        .table-controls .form-control,
        .table-controls .btn,
        .table-search-filter .form-control,
        .table-search-filter .btn {
            width: 100%;
            margin-bottom: 0.5rem;
        }

        .table-search-filter .input-group .btn {
            width: auto;
        }
    }

    .badge.bg-success { background: #198754; }
    .badge.bg-warning { background: #ffc107; color: #212529; }
    .badge.bg-danger { background: #dc3545; }
    .stok-aman { background: #e9fbe5; }
    .stok-minimum { background: #fffbe6; }
    .stok-habis { background: #fdeaea; }

    .select2-container--bootstrap-5 .select2-selection--single {
        min-height: calc(2.25rem + 2px);
        padding: 0.375rem 2.25rem 0.375rem 0.75rem;
        font-size: 0.875rem;
        display: flex;
        align-items: center;
    }

    .summary-card {
        cursor: pointer;
        user-select: none;
    }

    .summary-card.summary-active {
        outline: 2px solid rgba(13, 110, 253, 0.35);
        outline-offset: -2px;
    }
    </style>

    <!-- Add Bootstrap JS bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</head>
<body>
    <?php include '../templates/navbar.php'; ?>

    <div class="container-fluid mt-4 animate__animated animate__fadeIn">
        <!-- Header Section -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="h3 mb-1 fw-bold text-primary">
                            <i class='bx bx-package me-2'></i><?= htmlspecialchars($gudang_nama) ?>
                        </h1>
                        <p class="text-muted mb-0">Sistem Manajemen Inventory Terintegrasi</p>
                    </div>
                    <div class="d-flex gap-2">
                        <?php $canAdd = checkAccess('gudang_central', 'add'); $canDelete = checkAccess('gudang_central', 'delete'); ?>
                        <?php if ($canAdd): ?>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addStokModal">
                            <i class='bx bx-plus me-1'></i>Tambah Barang
                        </button>
                        <?php endif; ?>
                        <button class="btn btn-outline-secondary" onclick="exportToExcel()">
                            <i class='bx bx-export me-1'></i>Export
                        </button>
                        <?php if ($canDelete): ?>
                        <form method="POST" class="d-inline">
                            <button type="submit" name="reset_stok" class="btn btn-outline-danger" 
                                    onclick="return confirm('Apakah Anda yakin ingin mereset semua stok?')">
                                <i class='bx bx-reset me-1'></i>Reset
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="card border-0 shadow-sm h-100 summary-card" data-summary-filter="all" role="button" tabindex="0">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <div class="bg-primary bg-opacity-10 rounded-circle p-3">
                                    <i class='bx bx-package text-primary fs-4'></i>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h6 class="card-title text-muted mb-1">Total Barang</h6>
                                <h4 class="mb-0 fw-bold" id="totalBarang">0</h4>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="card border-0 shadow-sm h-100 summary-card" data-summary-filter="aman" role="button" tabindex="0">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <div class="bg-success bg-opacity-10 rounded-circle p-3">
                                    <i class='bx bx-check-circle text-success fs-4'></i>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h6 class="card-title text-muted mb-1">Stok Aman</h6>
                                <h4 class="mb-0 fw-bold text-success" id="stokAman">0</h4>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="card border-0 shadow-sm h-100 summary-card" data-summary-filter="minimum" role="button" tabindex="0">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <div class="bg-warning bg-opacity-10 rounded-circle p-3">
                                    <i class='bx bx-error text-warning fs-4'></i>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h6 class="card-title text-muted mb-1">Stok Minimum</h6>
                                <h4 class="mb-0 fw-bold text-warning" id="stokMinimum">0</h4>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="card border-0 shadow-sm h-100 summary-card" data-summary-filter="habis" role="button" tabindex="0">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <div class="bg-danger bg-opacity-10 rounded-circle p-3">
                                    <i class='bx bx-x-circle text-danger fs-4'></i>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h6 class="card-title text-muted mb-1">Stok Habis</h6>
                                <h4 class="mb-0 fw-bold text-danger" id="stokHabis">0</h4>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php if(isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show animate__animated animate__bounceIn" role="alert">
            <i class='bx bx-check-circle me-2'></i>
            <?= $_SESSION['success'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['success']); endif; ?>
        
        <?php if(isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show animate__animated animate__shakeX" role="alert">
            <i class='bx bx-error-circle me-2'></i>
            <?= $_SESSION['error'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['error']); endif; ?>
        
        <!-- Search and Filter Section -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-lg-4 col-md-6">
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0">
                                <i class='bx bx-search text-muted'></i>
                            </span>
                            <input type="text" class="form-control border-start-0" id="searchInput" 
                                   placeholder="Cari nama barang, kode, barcode, atau kategori...">
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <select class="form-select" id="filter_lokasi" name="filter_lokasi">
                            <option value="all">Semua Lokasi</option>
                            <?php
                            $lokasi_query = "SELECT DISTINCT nama_lokasi FROM lokasi_mapping WHERE aktif = 1 ORDER BY nama_lokasi";
                            $lokasi_result = $conn->query($lokasi_query);
                            if ($lokasi_result) {
                                while($lokasi = $lokasi_result->fetch_assoc()):
                            ?>
                            <option value="<?= htmlspecialchars($lokasi['nama_lokasi']) ?>" 
                                <?= (isset($_GET['filter_lokasi']) && $_GET['filter_lokasi'] == $lokasi['nama_lokasi']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($lokasi['nama_lokasi']) ?>
                            </option>
                            <?php endwhile; } ?>
                        </select>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <select class="form-select" id="filter_status">
                            <option value="all">Semua Status</option>
                            <option value="aman">Stok Aman</option>
                            <option value="minimum">Stok Minimum</option>
                            <option value="habis">Stok Habis</option>
                        </select>
                    </div>
                    <div class="col-lg-2 col-md-6">
                        <button type="button" class="btn btn-primary w-100" onclick="applyFilters()">
                            <i class='bx bx-filter me-1'></i>Filter
                        </button>
                    </div>
                    <?php require_once __DIR__ . '/includes/barcode_scan_field.php'; ?>
                </div>
            </div>
        </div>

        <!-- Main Table Section -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 py-3">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-semibold text-dark">
                        <i class='bx bx-list-ul me-2 text-primary'></i>Daftar Inventory
                    </h5>
                    <div class="d-flex gap-2">
                        <span class="badge bg-light text-dark border" id="totalRecords">0 item</span>
                        <button class="btn btn-sm btn-outline-primary" onclick="refreshTable()">
                            <i class='bx bx-refresh'></i>
                        </button>
                    </div>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="inventoryTable">
                        <thead>
                            <tr>
                                <th class="text-center" style="width: 50px;">No</th>
                                <th style="width: 120px;">Kode Barang</th>
                                <th style="width: 120px;">Barcode</th>
                                <th style="min-width: 200px;">Nama Barang</th>
                                <th style="width: 120px;">Kategori</th>
                                <th style="width: 80px;">Satuan</th>
                                <th style="width: 120px;">Lokasi</th>
                                <th class="text-center" style="width: 100px;">Stok Awal</th>
                                <th class="text-center" style="width: 120px;">Stok Final</th>
                                <th class="text-center" style="width: 100px;">Terpakai</th>
                                <th class="text-center" style="width: 100px;">Stok Akhir</th>
                                <th class="text-center" style="width: 80px;">Par</th>
                                <th class="text-center" style="width: 100px;">Expire</th>
                                <th class="text-center" style="width: 100px;">Tanggal Reset Stok Harian</th>
                                <th style="width: 150px;">Update Terakhir</th>
                                <th class="text-center" style="width: 120px;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if ($result && $result->num_rows > 0) {
                                $no = 1;
                                $totalBarang = 0;
                                $stokAman = 0;
                                $stokMinimum = 0;
                                $stokHabis = 0;
                                $canEditGlobal = checkAccess('gudang_central', 'edit');
                                $canDeleteGlobal = checkAccess('gudang_central', 'delete');
                                
                                while($row = $result->fetch_assoc()):
                                    $stok_awal = $row['stok_awal'];
                                    $stok_terpakai = $row['stok_terpakai'];
                                    $stok_akhir = $stok_awal - $stok_terpakai;
                                    $stok_min = $row['stok_minimum'];
                                    $totalBarang++;

                                    // Tentukan kelas warna stok
                                    $stok_class = '';
                                    $status_badge = '';
                                    if($stok_akhir <= 0) {
                                        $stok_class = 'table-danger';
                                        $status_badge = '<span class="badge bg-danger">Habis</span>';
                                        $stokHabis++;
                                    } elseif($stok_akhir <= $stok_min) {
                                        $stok_class = 'table-warning';
                                        $status_badge = '<span class="badge bg-warning text-dark">Minimum</span>';
                                        $stokMinimum++;
                                    } else {
                                        $stok_class = 'table-success';
                                        $status_badge = '<span class="badge bg-success">Aman</span>';
                                        $stokAman++;
                                    }
                            ?>
                            <tr class="<?= $stok_class ?>" data-barcode="<?= htmlspecialchars(trim((string)($row['barcode'] ?? ''))) ?>">
                                <td class="text-center fw-semibold"><?= $no++ ?></td>
                                <td>
                                    <span class="fw-semibold text-primary"><?= htmlspecialchars($row['kode_barang'] ?? 'N/A') ?></span>
                                </td>
                                <td>
                                    <span class="text-muted"><?= htmlspecialchars($row['barcode'] ?? '-') ?></span>
                                </td>
                                <td>
                                    <div class="d-flex flex-column">
                                        <span class="fw-semibold"><?= htmlspecialchars($row['nama_barang'] ?? 'Barang Tidak Ditemukan') ?></span>
                                    </div>
                                </td>
                                <td><?= htmlspecialchars($row['nama_kategori'] ?? 'N/A') ?></td>
                                <td class="text-center">
                                    <span class="text-dark"><?= htmlspecialchars($row['nama_satuan'] ?? 'N/A') ?></span>
                                </td>
                                <td>
                                    <span class="text-dark">
                                        </i><?= htmlspecialchars($row['nama_lokasi'] ?? 'N/A') ?>
                                    </span>
                                </td>
                                <td class="text-center fw-bold"><?= number_format($stok_awal) ?></td>
                                <td class="text-center">
                                    <input type="number" class="form-control form-control-sm text-center border-0 bg-transparent"
                                        value="<?= $stok_akhir ?>"
                                        data-id="<?= $row['id'] ?>"
                                        data-stok-awal="<?= $stok_awal ?>"
                                        min="0" max="<?= $stok_awal ?>"
                                        style="width: 80px;"
                                        <?= $canEditGlobal ? '' : 'disabled' ?>
                                        onchange="updateStok(this)">
                                </td>
                                <td class="text-center text-muted"><?= number_format($stok_terpakai) ?></td>
                                <td class="text-center">
                                    <?= $status_badge ?>
                                    <div class="fw-bold"><?= number_format($stok_akhir) ?></div>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-secondary"><?= $stok_min ?></span>
                                </td>
                                <td class="text-center">
                                    <?php if($row['expire_date'] && $row['expire_date'] != '0000-00-00'): ?>
                                        <span class="badge bg-light text-dark">
                                            <?= date('d/m/Y', strtotime($row['expire_date'])) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if($row['last_reset'] && $row['last_reset'] != '0000-00-00 00:00:00'): ?>
                                        <span class="badge bg-light text-dark">
                                            <?= date('d/m/Y H:i', strtotime($row['last_reset'])) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="d-flex flex-column">
                                        <small class="fw-semibold"><?= date('d/m/Y', strtotime($row['updated_at'])) ?></small>
                                        <small class="text-muted"><?= date('H:i', strtotime($row['updated_at'])) ?> by <?= isset($row['updated_by']) ? htmlspecialchars($row['updated_by']) : 'N/A' ?></small>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <?php $canEdit = $canEditGlobal; $canDelete = $canDeleteGlobal; ?>
                                    <div class="d-grid gap-2">
                                        <a class="btn btn-sm btn-outline-success d-inline-flex align-items-center justify-content-center gap-1" title="Stok Masuk" href="../stok/masuk/create.php?quick=1&gudang_tujuan_id=23&barang_id=<?= (int)($row['barang_id'] ?? 0) ?>">
                                            <i class='bx bx-log-in-circle'></i><span class="d-none d-xxl-inline">Masuk</span>
                                        </a>
                                        <?php if ($canEdit || $canDelete): ?>
                                        <div class="d-inline-flex justify-content-center gap-2">
                                            <?php if ($canEdit): ?>
                                            <button class="btn btn-sm btn-outline-warning" title="Edit" onclick="editStok(<?= $row['id'] ?>)">
                                                <i class='bx bx-edit-alt'></i>
                                            </button>
                                            <?php endif; ?>
                                            <?php if ($canDelete): ?>
                                            <button class="btn btn-sm btn-outline-danger" title="Hapus" onclick="deleteStok(<?= $row['id'] ?>)">
                                                <i class='bx bx-trash'></i>
                                            </button>
                                            <?php endif; ?>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile;
                            } else {
                                if (!$result) {
                                    echo "<tr><td colspan='15' class='text-center text-danger'>Error executing query: " . $conn->error . "</td></tr>";
                                } else {
                                    echo "<tr><td colspan='15' class='text-center text-muted'>Tidak ada data stok untuk gudang ini.</td></tr>";
                                }
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Tambah Stok -->
    <div class="modal fade" id="addStokModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class='bx bx-package me-2'></i>Tambah Stok Barang</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="addStokForm" action="process_central.php" method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_stok">
                        <input type="hidden" name="gudang_id" value="<?= (int)$gudang_id ?>">
                        
                        <div class="mb-3">
                            <label for="barang_id" class="form-label">Barang <span class="text-danger">*</span></label>
                            <select class="form-select select2" id="barang_id" name="barang_id" required>
                                <option value="">Pilih Barang</option>
                                <?php
                                $barang_query = "SELECT id, kode_barang, nama_barang FROM barang ORDER BY nama_barang";
                                $barang_result = $conn->query($barang_query);
                                if ($barang_result) {
                                    while($barang = $barang_result->fetch_assoc()):
                                ?>
                                <option value="<?= $barang['id'] ?>">
                                    <?= htmlspecialchars($barang['kode_barang']) ?> - <?= htmlspecialchars($barang['nama_barang']) ?>
                                </option>
                                <?php endwhile;
                                }
                                ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="stok_minimum" class="form-label">Par Stok <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="stok_minimum" name="stok_minimum" min="0" required>
                        </div>

                        <div class="mb-3">
                            <label for="expire_date" class="form-label">Tanggal Kadaluarsa</label>
                            <input type="date" class="form-control" id="expire_date" name="expire_date">
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

    <!-- Toast Notification -->
    <div class="toast-container position-fixed bottom-0 end-0 p-3">
        <div id="liveToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="toast-header bg-primary text-white">
                <i class='bx bx-bell me-2'></i>
                <strong class="me-auto">Notifikasi Sistem</strong>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
            <div class="toast-body bg-light">
                <div class="d-flex align-items-center">
                    <span class="notification-message">Stok berhasil diupdate!</span>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        // Show loading state initially
        document.addEventListener('DOMContentLoaded', function() {
            // Hide any existing loading indicators
            const loadingIndicators = document.querySelectorAll('.loading, .spinner-border');
            loadingIndicators.forEach(indicator => {
                indicator.style.display = 'none';
            });
            
            console.log('DOM loaded, initializing inventory system...');
            
            // Initialize statistics
            updateStatistics();

            if (window.jQuery) {
                $('#barang_id').select2({
                    theme: 'bootstrap-5',
                    width: '100%',
                    placeholder: 'Pilih Barang',
                    allowClear: true,
                    minimumResultsForSearch: 0,
                    dropdownParent: $('#addStokModal')
                });
            }
            
            function applyAllFilters() {
                const searchTerm = (document.getElementById('searchInput')?.value || '').toLowerCase();
                const locationFilter = document.getElementById('filter_lokasi')?.value || 'all';
                const statusFilter = document.getElementById('filter_status')?.value || 'all';
                const rows = document.querySelectorAll('#inventoryTable tbody tr');
                let visibleCount = 0;

                rows.forEach(row => {
                    const kodeBarang = (row.cells[1]?.textContent || '').toLowerCase();
                    const barcode = (row.cells[2]?.textContent || '').toLowerCase();
                    const namaBarang = (row.cells[3]?.textContent || '').toLowerCase();
                    const kategori = (row.cells[4]?.textContent || '').toLowerCase();
                    const location = (row.cells[6]?.textContent || '').trim();
                    const status = (row.cells[10]?.textContent || '').trim();

                    const matchSearch = !searchTerm || kodeBarang.includes(searchTerm) || barcode.includes(searchTerm) || namaBarang.includes(searchTerm) || kategori.includes(searchTerm);
                    const matchLocation = locationFilter === 'all' || location.includes(locationFilter);

                    let matchStatus = true;
                    if (statusFilter !== 'all') {
                        if (statusFilter === 'aman') matchStatus = status.includes('Aman');
                        if (statusFilter === 'minimum') matchStatus = status.includes('Minimum');
                        if (statusFilter === 'habis') matchStatus = status.includes('Habis');
                    }

                    const showRow = matchSearch && matchLocation && matchStatus;
                    row.style.display = showRow ? '' : 'none';
                    if (showRow) visibleCount++;
                });

                const totalRecords = document.getElementById('totalRecords');
                if (totalRecords) totalRecords.textContent = visibleCount + ' item';
            }

            function updateSummaryActive() {
                const statusFilter = document.getElementById('filter_status')?.value || 'all';
                document.querySelectorAll('.summary-card').forEach(card => {
                    const key = card.getAttribute('data-summary-filter') || 'all';
                    const isActive = (key === 'all' && statusFilter === 'all') || key === statusFilter;
                    card.classList.toggle('summary-active', isActive);
                });
            }

            window.applyFilters = function() {
                applyAllFilters();
                updateSummaryActive();
            };

            document.getElementById('searchInput').addEventListener('input', function() {
                applyAllFilters();
            });

            document.querySelectorAll('.summary-card').forEach(card => {
                const activate = () => {
                    const key = card.getAttribute('data-summary-filter') || 'all';
                    const filterStatus = document.getElementById('filter_status');
                    if (filterStatus) filterStatus.value = key;
                    applyAllFilters();
                    updateSummaryActive();
                };

                card.addEventListener('click', activate);
                card.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        activate();
                    }
                });
            });

            applyAllFilters();
            updateSummaryActive();

            // Update stock function
            window.updateStok = function(input) {
                const id = input.getAttribute('data-id');
                const stokAwal = parseFloat(input.getAttribute('data-stok-awal'));
                const sisaStok = parseFloat(input.value);
                const finalOnly = input.getAttribute('data-update-scope') === 'final-only';

                if (isNaN(sisaStok) || sisaStok < 0 || sisaStok > stokAwal) {
                    showToast('Nilai stok tidak valid', 'error');
                    input.value = stokAwal - parseFloat(input.closest('tr').cells[8].textContent.replace(/,/g, ''));
                    return;
                }

                // Show loading state
                input.disabled = true;
                input.style.backgroundColor = '#f8f9fa';

                fetch('process_central.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=update_sisa_stok&id=' + id + '&sisa_stok=' + sisaStok
                })
                .then(response => response.json())
                .then(data => {
                    if(data.success) {
                        const row = input.closest('tr');
                        const stokTerpakai = data.data.stok_terpakai;
                        const stokAkhir = data.data.sisa_stok;
                        const stokMin = parseFloat(row.cells[10].textContent);

                        if (finalOnly) {
                            input.value = String(stokAkhir);
                            input.setAttribute('data-prev-final', String(stokAkhir));
                            return;
                        }

                        // Update terpakai column
                        row.cells[8].textContent = number_format(stokTerpakai);

                        // Update status badge
                        const statusCell = row.cells[9];
                        let newStatusBadge = '';
                        let newRowClass = '';

                        if (stokAkhir <= 0) {
                            newStatusBadge = '<span class="badge bg-danger">Habis</span>';
                            newRowClass = 'table-danger';
                            playSound('stokhabisSound');
                            showToast('Perhatian! Stok telah habis', 'error');
                        } else if (stokAkhir <= stokMin) {
                            newStatusBadge = '<span class="badge bg-warning text-dark">Minimum</span>';
                            newRowClass = 'table-warning';
                            playSound('warningSound');
                            showToast('Perhatian! Stok mencapai batas minimum', 'warning');
                        } else {
                            newStatusBadge = '<span class="badge bg-success">Aman</span>';
                            newRowClass = 'table-success';
                            playSound('notificationSound');
                            showToast('Stok berhasil diupdate', 'success');
                        }

                        statusCell.innerHTML = newStatusBadge + '<div class="fw-bold">' + number_format(stokAkhir) + '</div>';

                        // Update row class
                        row.className = newRowClass;

                        // Update statistics
                        updateStatistics();
                    } else {
                        throw new Error(data.message || 'Gagal update stok');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showToast('Terjadi kesalahan: ' + error.message, 'error');
                    // Reset input value
                    input.value = stokAwal - parseFloat(input.closest('tr').cells[8].textContent.replace(/,/g, ''));
                })
                .finally(() => {
                    input.disabled = false;
                    input.style.backgroundColor = '';
                    input.removeAttribute('data-update-scope');
                });
            };

            // Edit stock function
            window.editStok = function(id) {
                window.location.href = `edit_central.php?id=${id}&gudang=central`;
            };

            // Delete stock function
            window.deleteStok = function(id) {
                if (confirm('Apakah Anda yakin ingin menghapus stok ini?')) {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = 'process_central.php';
                    
                    const actionInput = document.createElement('input');
                    actionInput.type = 'hidden';
                    actionInput.name = 'action';
                    actionInput.value = 'delete_stok';
                    
                    const idInput = document.createElement('input');
                    idInput.type = 'hidden';
                    idInput.name = 'id';
                    idInput.value = id;
                    
                    form.appendChild(actionInput);
                    form.appendChild(idInput);
                    document.body.appendChild(form);
                    form.submit();
                }
            };

            // Refresh table function
            window.refreshTable = function() {
                location.reload();
            };

            // Export to Excel function
            window.exportToExcel = function() {
                showToast('Fitur export akan segera tersedia', 'info');
            };

            // Update statistics function
            function updateStatistics() {
                const rows = document.querySelectorAll('#inventoryTable tbody tr:not([style*="display: none"])');
                let total = 0, aman = 0, minimum = 0, habis = 0;

                rows.forEach(row => {
                    total++;
                    const status = row.cells[9].textContent;
                    if (status.includes('Aman')) aman++;
                    else if (status.includes('Minimum')) minimum++;
                    else if (status.includes('Habis')) habis++;
                });

                document.getElementById('totalBarang').textContent = total;
                document.getElementById('stokAman').textContent = aman;
                document.getElementById('stokMinimum').textContent = minimum;
                document.getElementById('stokHabis').textContent = habis;
                document.getElementById('totalRecords').textContent = total + ' item';
                
                console.log('Statistics updated:', { total, aman, minimum, habis });
            }

            // Number format helper
            function number_format(num) {
                return new Intl.NumberFormat('id-ID').format(num);
            }

            // Enhanced toast function
            window.showToast = function(message, type = 'success') {
                const toast = document.getElementById('liveToast');
                const toastBody = toast.querySelector('.toast-body .notification-message');
                const toastHeader = toast.querySelector('.toast-header');
                
                toastBody.textContent = message;
                toastHeader.className = 'toast-header';
                
                if (type === 'error') {
                    toastHeader.classList.add('bg-danger');
                } else if (type === 'warning') {
                    toastHeader.classList.add('bg-warning');
                } else if (type === 'info') {
                    toastHeader.classList.add('bg-info');
                } else {
                    toastHeader.classList.add('bg-primary');
                }
                
                const bsToast = new bootstrap.Toast(toast);
                bsToast.show();
            };

            // Sound functions
            window.playSound = function(soundId, muted = false) {
                const sound = document.getElementById(soundId);
                if (sound) {
                    sound.volume = 0.5;
                    sound.currentTime = 0;
                    sound.muted = muted;
                    sound.play().catch(e => {
                        console.error('Error playing sound:', e);
                    });
                }
            };

            // Add event listeners for filters
            document.getElementById('filter_lokasi').addEventListener('change', applyFilters);
            document.getElementById('filter_status').addEventListener('change', applyFilters);
            
            console.log('Inventory system initialized successfully');
        });
    </script>
    <script src="includes/barcode_scan_stok_final.js"></script>
</body>
</html>
