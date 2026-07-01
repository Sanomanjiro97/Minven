<?php
session_start();
require_once '../config.php';
require_once '../includes/access_check.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

$gudang_id = 33;
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

if (!checkAccess('gudang_87', 'view')) {
    $_SESSION['error'] = 'Anda tidak memiliki akses untuk melihat ' . $gudang_nama_plain . '!';
    header('Location: /minven_pro/dashboard.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_stok'])) {
    if (!checkAccess('gudang_87', 'delete')) {
        $_SESSION['error'] = 'Anda tidak memiliki akses untuk reset stok ' . $gudang_nama_plain . '!';
        header('Location: gudang_antapani.php');
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
    
    if ((time() - $_SESSION['reset_notification']['time']) > 60) {
        unset($_SESSION['reset_notification']);
    }
}
// ... existing code ...

// Add this query before the HTML section
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

// Debug information
if (!$result) {
    error_log("Database error in gudang_antapani.php: " . $conn->error);
    $_SESSION['error'] = "Error executing query: " . $conn->error;
} else {
    error_log("Query executed successfully. Found " . $result->num_rows . " rows");
}

// ... rest of the existing code ...
// Query untuk data stok gudang Antapani (ID 13)
// Modify the SQL query to properly join with item_mapping and lokasi_mapping
// ... existing code ...
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
        --success-bg: #d1e7dd;
        --danger-color: #dc3545;
        --danger-bg: #f8d7da;
        --warning-color: #ffc107;
        --warning-bg: #fff3cd;
        --info-color: #0dcaf0;
        --dark-color: #212529;
        --light-bg: #f8f9fa;
    }

    body {
        font-size: 0.8rem;
        padding-bottom: 50px;
        background: linear-gradient(135deg, #e9ecef 0%, #dee2e6 100%);
        min-height: 100vh;
    }

    .container, .container-fluid {
        padding-left: 0.5rem;
        padding-right: 0.5rem;
    }

    .page-header {
        background: linear-gradient(135deg, #0d6efd 0%, #0b5ed7 100%);
        color: white;
        padding: 0.75rem 1rem;
        border-radius: 10px;
        margin-bottom: 0.75rem;
        box-shadow: 0 4px 12px rgba(13, 110, 253, 0.25);
    }

    .page-header h1 {
        font-weight: 700;
        margin: 0;
        font-size: 1.1rem;
    }

    .page-header p {
        margin: 0;
        opacity: 0.85;
        font-size: 0.75rem;
    }

    .card {
        border: none;
        border-radius: 10px;
        background: #fff;
        margin-bottom: 0.75rem;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
        overflow: hidden;
    }

    .card-header {
        border-bottom: 1px solid #f1f5f9;
        background: #fff;
        padding: 0.75rem 1rem;
    }

    .card-header h5 {
        font-weight: 600;
        color: var(--dark-color);
        margin: 0;
        font-size: 0.9rem;
    }

    .card-body {
        padding: 0.75rem 1rem;
    }

    .form-control,
    .form-select {
        border-radius: 6px;
        border: 1px solid #e2e8f0;
        padding: 6px 10px;
        font-size: 0.8rem;
        transition: all 0.2s ease;
        background-color: #fff;
    }

    .form-control:hover,
    .form-select:hover {
        border-color: #cbd5e1;
    }

    .form-control:focus,
    .form-select:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 2px rgba(13, 110, 253, 0.1);
        background-color: #fff;
    }

    .input-group-text {
        border-radius: 6px 0 0 6px;
        border: 1px solid #e2e8f0;
        border-right: none;
        background-color: #f8fafc;
        font-size: 0.8rem;
        padding: 6px 10px;
    }

    .input-group .form-control {
        border-radius: 0 6px 6px 0;
    }

    .btn {
        border-radius: 6px;
        font-weight: 500;
        padding: 5px 12px;
        transition: all 0.2s ease;
        font-size: 0.8rem;
        border: 1px solid transparent;
    }

    .btn:hover {
        transform: translateY(-1px);
        box-shadow: 0 3px 8px rgba(0, 0, 0, 0.1);
    }

    .btn:active {
        transform: translateY(0);
    }

    .btn-primary {
        background: linear-gradient(135deg, #0d6efd 0%, #0b5ed7 100%);
        border-color: #0b5ed7;
    }

    .btn-success {
        background: linear-gradient(135deg, #198754 0%, #157347 100%);
        border-color: #157347;
    }

    .btn-danger {
        background: linear-gradient(135deg, #dc3545 0%, #bb2d3a 100%);
        border-color: #bb2d3a;
    }

    .btn-outline-light {
        border-color: rgba(255,255,255,0.5);
        color: white;
    }

    .btn-outline-light:hover {
        background: rgba(255,255,255,0.2);
        border-color: white;
        color: white;
    }

    .btn-outline-secondary {
        border-color: #6c757d;
        color: #6c757d;
    }

    .btn-outline-primary {
        border-color: #0d6efd;
        color: #0d6efd;
    }

    .btn-outline-danger {
        border-color: #dc3545;
        color: #dc3545;
    }

    .btn-sm {
        padding: 3px 8px;
        font-size: 0.75rem;
    }

    .summary-card {
        cursor: pointer;
        user-select: none;
        border-radius: 10px;
        transition: all 0.25s ease;
        border: none;
        overflow: hidden;
        position: relative;
        margin-bottom: 0;
    }

    .summary-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(0, 0, 0, 0.1);
    }

    .summary-card.summary-active {
        outline: 2px solid rgba(13, 110, 253, 0.4);
        outline-offset: -2px;
    }

    .summary-card .card-body {
        padding: 0.75rem;
    }

    .summary-icon {
        width: 36px;
        height: 36px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.1rem;
        flex-shrink: 0;
    }

    .summary-icon.total {
        background: linear-gradient(135deg, #0d6efd 0%, #0b5ed7 100%);
        color: white;
    }

    .summary-icon.aman {
        background: linear-gradient(135deg, #198754 0%, #157347 100%);
        color: white;
    }

    .summary-icon.minimum {
        background: linear-gradient(135deg, #ffc107 0%, #ffca2c 100%);
        color: var(--dark-color);
    }

    .summary-icon.habis {
        background: linear-gradient(135deg, #dc3545 0%, #bb2d3a 100%);
        color: white;
    }

    .summary-value {
        font-size: 1.25rem;
        font-weight: 700;
        line-height: 1;
    }

    .summary-label {
        font-size: 0.7rem;
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.3px;
        opacity: 0.7;
    }

    .table-responsive {
        border-radius: 8px;
        overflow: hidden;
        border: 1px solid #e9ecef;
    }

    .table {
        margin-bottom: 0;
        font-size: 0.8rem;
    }

    .table thead {
        background: linear-gradient(135deg, #0d6efd 0%, #0b5ed7 100%);
        color: white;
    }

    .table thead th {
        font-weight: 600;
        padding: 10px 8px;
        font-size: 0.7rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border: none;
        white-space: nowrap;
    }

    .table tbody td {
        padding: 10px 8px;
        vertical-align: middle;
        border-bottom: 1px solid #f1f5f9;
        font-size: 0.8rem;
    }

    .table tbody tr {
        transition: all 0.15s ease;
    }

    .table tbody tr:hover {
        background-color: #f8fafc;
    }

    .table tbody tr:last-child td {
        border-bottom: none;
    }

    .table-success {
        background: linear-gradient(135deg, rgba(25, 135, 84, 0.06) 0%, rgba(25, 135, 84, 0.02) 100%) !important;
        border-left: 3px solid var(--success-color);
    }

    .table-warning {
        background: linear-gradient(135deg, rgba(255, 193, 7, 0.08) 0%, rgba(255, 193, 7, 0.03) 100%) !important;
        border-left: 3px solid var(--warning-color);
    }

    .table-danger {
        background: linear-gradient(135deg, rgba(220, 53, 69, 0.06) 0%, rgba(220, 53, 69, 0.02) 100%) !important;
        border-left: 3px solid var(--danger-color);
    }

    .badge {
        font-weight: 600;
        padding: 3px 8px;
        border-radius: 5px;
        font-size: 0.65rem;
        text-transform: uppercase;
        letter-spacing: 0.3px;
    }

    .badge.bg-success {
        background: var(--success-color) !important;
    }

    .badge.bg-warning {
        background: var(--warning-color) !important;
        color: var(--dark-color) !important;
    }

    .badge.bg-danger {
        background: var(--danger-color) !important;
    }

    .badge.bg-secondary {
        background: #6c757d !important;
    }

    .modal-content {
        border-radius: 12px;
        border: none;
        overflow: hidden;
        box-shadow: 0 15px 40px rgba(0, 0, 0, 0.15);
    }

    .modal-header {
        padding: 1rem 1.25rem;
        border-bottom: 1px solid #f1f5f9;
    }

    .modal-header.bg-primary {
        background: linear-gradient(135deg, #0d6efd 0%, #0b5ed7 100%);
        border-bottom: none;
    }

    .modal-header.bg-success {
        background: linear-gradient(135deg, #198754 0%, #157347 100%);
        border-bottom: none;
    }

    .modal-title {
        font-weight: 600;
        font-size: 0.95rem;
    }

    .modal-body {
        padding: 1.25rem;
    }

    .modal-footer {
        padding: 0.75rem 1.25rem;
        border-top: 1px solid #f1f5f9;
    }

    .toast {
        border-radius: 10px;
        overflow: hidden;
        border: none;
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
    }

    .toast-header {
        padding: 10px 14px;
        border: none;
        font-weight: 600;
        font-size: 0.85rem;
    }

    .toast-body {
        padding: 12px 14px;
        font-size: 0.85rem;
    }

    .alert {
        border-radius: 8px;
        border: none;
        padding: 10px 14px;
        font-weight: 500;
        font-size: 0.85rem;
    }

    .alert-success {
        background: linear-gradient(135deg, #d1e7dd 0%, #badbcc 100%);
        color: #0f5132;
    }

    .alert-danger {
        background: linear-gradient(135deg, #f8d7da 0%, #f5c2c7 100%);
        color: #842029;
    }

    .form-label {
        font-weight: 500;
        font-size: 0.8rem;
        margin-bottom: 4px;
        color: var(--dark-color);
    }

    .text-primary {
        color: var(--primary-color) !important;
    }

    @media (max-width: 767.98px) {
        .page-header {
            padding: 0.6rem 0.75rem;
            border-radius: 8px;
        }

        .page-header h1 {
            font-size: 1rem;
        }

        .summary-card {
            margin-bottom: 0.5rem;
        }

        .summary-icon {
            width: 32px;
            height: 32px;
            font-size: 1rem;
        }

        .summary-value {
            font-size: 1.1rem;
        }

        .table {
            font-size: 0.75rem;
        }

        .table thead th,
        .table tbody td {
            padding: 8px 6px;
        }
    }

    .select2-container--bootstrap-5 .select2-selection--single {
        min-height: calc(1.8rem + 2px);
        padding: 0.3rem 2rem 0.3rem 0.6rem;
        font-size: 0.8rem;
        border-radius: 6px;
        border: 1px solid #e2e8f0;
        background-color: #fff;
    }

    .select2-container--bootstrap-5 .select2-selection--single:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 2px rgba(13, 110, 253, 0.1);
    }

    .action-buttons .btn {
        padding: 4px 8px;
        border-radius: 5px;
    }

    .action-buttons .btn i {
        font-size: 0.9rem;
    }

    .kode-barang {
        background: linear-gradient(135deg, #e7f1ff 0%, #d4e4ff 100%);
        padding: 2px 6px;
        border-radius: 4px;
        font-weight: 600;
        color: var(--primary-color);
        font-size: 0.7rem;
    }

    @keyframes fadeInUp {
        from { 
            opacity: 0; 
            transform: translateY(10px); 
        }
        to { 
            opacity: 1; 
            transform: translateY(0); 
        }
    }

    .animate-fade-in {
        animation: fadeInUp 0.3s ease forwards;
    }

    .card:nth-child(1) { animation-delay: 0.03s; }
    .card:nth-child(2) { animation-delay: 0.06s; }
    .card:nth-child(3) { animation-delay: 0.09s; }
    .card:nth-child(4) { animation-delay: 0.12s; }

    .stok-aman { background-color: rgba(25, 135, 84, 0.06) !important; }
    .stok-minimum { background-color: rgba(255, 193, 7, 0.08) !important; }
    .stok-habis { background-color: rgba(220, 53, 69, 0.06) !important; }
    </style>

 
    <!-- Add Bootstrap JS bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</head>
<body>
    <?php include '../templates/navbar.php'; ?>

    <div class="container-fluid mt-4 animate__animated animate__fadeIn">
        <!-- Page Header -->
        <div class="page-header animate__animated animate__fadeIn">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <h1 class="mb-1">
                        <i class='bx bx-package me-2'></i><?= htmlspecialchars($gudang_nama) ?>
                    </h1>
                    <p class="mb-0 opacity-75">Sistem Manajemen Inventory Terintegrasi</p>
                </div>
                <div class="d-flex gap-2">
                    <?php $canAdd = checkAccess('gudang_87', 'add'); $canDelete = checkAccess('gudang_87', 'delete'); ?>
                    <?php if ($canAdd): ?>
                    <button class="btn btn-light" data-bs-toggle="modal" data-bs-target="#addStokModal">
                        <i class='bx bx-plus me-1'></i>Tambah Barang
                    </button>
                    <?php endif; ?>
                    <button class="btn btn-outline-light" onclick="exportToExcel()">
                        <i class='bx bx-export me-1'></i>Export
                    </button>
                    <?php if ($canDelete): ?>
                    <form method="POST" class="d-inline">
                        <button type="submit" name="reset_stok" class="btn btn-outline-light" 
                                onclick="return confirm('Apakah Anda yakin ingin mereset semua stok?')">
                            <i class='bx bx-reset me-1'></i>Reset
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4 g-3">
            <div class="col-xl-3 col-md-6">
                <div class="card border-0 h-100 summary-card animate-fade-in" data-summary-filter="all" role="button" tabindex="0">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <div class="summary-icon total">
                                    <i class='bx bx-package'></i>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <div class="summary-label text-muted mb-1">Total Barang</div>
                                <div class="summary-value text-dark" id="totalBarang">0</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card border-0 h-100 summary-card animate-fade-in" data-summary-filter="aman" role="button" tabindex="0">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <div class="summary-icon aman">
                                    <i class='bx bx-check-circle'></i>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <div class="summary-label text-muted mb-1">Stok Aman</div>
                                <div class="summary-value text-success" id="stokAman">0</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card border-0 h-100 summary-card animate-fade-in" data-summary-filter="minimum" role="button" tabindex="0">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <div class="summary-icon minimum">
                                    <i class='bx bx-error'></i>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <div class="summary-label text-muted mb-1">Stok Minimum</div>
                                <div class="summary-value text-warning" id="stokMinimum">0</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card border-0 h-100 summary-card animate-fade-in" data-summary-filter="habis" role="button" tabindex="0">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <div class="summary-icon habis">
                                    <i class='bx bx-x-circle'></i>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <div class="summary-label text-muted mb-1">Stok Habis</div>
                                <div class="summary-value text-danger" id="stokHabis">0</div>
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
                    <div class="col-lg-3 col-md-6">
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0">
                                <i class='bx bx-search text-muted'></i>
                            </span>
                            <input type="text" class="form-control border-start-0" id="searchInput" 
                                   placeholder="Cari nama barang, kode, barcode, atau kategori...">
                        </div>
                    </div>
                    <div class="col-lg-2 col-md-6">
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
                    <div class="col-lg-2 col-md-6">
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
                                <th style="width: 140px;">Barcode</th>
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
                                $canEditGlobal = checkAccess('gudang_87', 'edit');
                                $canDeleteGlobal = checkAccess('gudang_87', 'delete');
                                
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
                                    <span class="text-dark"><?= htmlspecialchars($row['barcode'] ?? '-') ?></span>
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
                                        <button type="button" class="btn btn-sm btn-outline-success d-inline-flex align-items-center justify-content-center gap-1" title="Stok Masuk" data-bs-toggle="modal" data-bs-target="#stokMasukModal" data-stok-id="<?= (int)($row['id'] ?? 0) ?>" data-barang-id="<?= (int)($row['barang_id'] ?? 0) ?>" data-barang-nama="<?= htmlspecialchars($row['nama_barang'] ?? '') ?>">
                                            <i class='bx bx-log-in-circle'></i><span class="d-none d-xxl-inline">Masuk</span>
                                        </button>
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
                                    echo "<tr><td colspan='16' class='text-center text-danger'>Error executing query: " . $conn->error . "</td></tr>";
                                } else {
                                    echo "<tr><td colspan='16' class='text-center text-muted'>Tidak ada data stok untuk gudang ini.</td></tr>";
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
                <form id="addStokForm" action="process_operasional_stok_gudang87.php" method="POST">
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

    <div class="modal fade" id="stokMasukModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class='bx bx-log-in-circle me-2'></i>Input Stok Masuk</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="stokMasukModalForm" action="process_operasional_stok_gudang87.php" method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="quick_stok_masuk">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label for="sm_tanggal" class="form-label">Tanggal</label>
                                <input type="date" class="form-control" id="sm_tanggal" name="tanggal" value="<?= date('Y-m-d') ?>" required>
                            </div>
                            <div class="col-md-8">
                                <label for="sm_gudang_tujuan_id_display" class="form-label">Pilih Gudang Tujuan</label>
                                <select class="form-select" id="sm_gudang_tujuan_id_display" disabled>
                                    <option value="<?= (int)$gudang_id ?>" selected><?= htmlspecialchars($gudang_nama) ?></option>
                                </select>
                                <input type="hidden" name="gudang_tujuan_id" value="<?= (int)$gudang_id ?>">
                            </div>
                            <div class="col-12">
                                <label for="sm_keterangan" class="form-label">Keterangan Transaksi</label>
                                <textarea class="form-control" id="sm_keterangan" name="keterangan" rows="2"></textarea>
                            </div>
                        </div>

                        <hr class="my-3">

                        <h5 class="mb-3">Detail Barang Masuk</h5>
                        <div class="row g-3 mb-3 align-items-end">
                            <div class="col-md-4">
                                <label for="sm_select_barang_id" class="form-label">Barang</label>
                                <select class="form-select select2" id="sm_select_barang_id">
                                    <option value="">-- Pilih Barang --</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="sm_display_stok_tersedia" class="form-label">Stok Tersedia</label>
                                <input type="text" class="form-control" id="sm_display_stok_tersedia" value="" readonly>
                            </div>
                            <div class="col-md-2">
                                <label for="sm_display_satuan" class="form-label">Satuan</label>
                                <input type="text" class="form-control" id="sm_display_satuan" value="" readonly>
                            </div>
                            <div class="col-md-2">
                                <label for="sm_input_jumlah" class="form-label">Jumlah</label>
                                <input type="number" class="form-control" id="sm_input_jumlah" min="1" value="1">
                            </div>
                            <div class="col-md-2">
                                <button type="button" class="btn btn-success w-100" id="sm_addItemBtn">Tambah Item</button>
                            </div>
                            <div class="col-12">
                                <label for="sm_input_keterangan_item" class="form-label">Keterangan</label>
                                <input type="text" class="form-control" id="sm_input_keterangan_item" placeholder="Contoh: Ukuran M, Warna Biru">
                            </div>
                        </div>

                        <div class="table-responsive mb-3">
                            <table class="table table-bordered" id="sm_itemsTable">
                                <thead>
                                    <tr>
                                        <th>Kode Barang</th>
                                        <th>Nama Barang</th>
                                        <th>Keterangan</th>
                                        <th class="text-center">Jumlah</th>
                                        <th>Satuan</th>
                                        <th class="text-center">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>

                        <input type="hidden" name="items_data" id="sm_items_data">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-success" id="sm_submitBtn" disabled>Simpan Stok Masuk</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Edit Stok -->
    <div class="modal fade" id="editStokModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Stok Barang</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="editStokForm" action="process_operasional_stok_gudang87.php?action=edit" method="POST">
                    <div class="modal-body">
                        <input type="hidden" id="edit_stok_id" name="edit_stok_id">

                        <!-- In the Edit Stok Modal -->
                        <div class="mb-3">
                            <label class="form-label" for="edit_kode_barang">Kode Barang</label>
                            <input type="text" class="form-control" id="edit_kode_barang" readonly>
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="edit_nama_barang">Nama Barang</label>
                            <input type="text" class="form-control" id="edit_nama_barang" readonly>
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="edit_stok_awal">Stok Awal</label>
                            <input type="number" class="form-control" id="edit_stok_awal" name="edit_stok_awal" min="1" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="edit_stok_minimum">Par Stok</label>
                            <input type="number" class="form-control" id="edit_stok_minimum" name="edit_stok_minimum" min="0">
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="edit_expire_date">Tanggal Kadaluarsa</label>
                            <input type="date" class="form-control" id="edit_expire_date" name="edit_expire_date">
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

    <!-- Modal Scan Barcode untuk Stok Final -->
    <div class="modal fade" id="scanBarcodeStokModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class='bx bx-barcode-reader me-2'></i>Input Stok Final (Scan Barcode)</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="scanBarcodeStokForm">
                    <div class="modal-body">
                        <div class="mb-2">
                            <label class="form-label mb-1">Kode Barang</label>
                            <input type="text" class="form-control" id="sb_kode_barang" readonly>
                        </div>
                        <div class="mb-2">
                            <label class="form-label mb-1">Barcode</label>
                            <input type="text" class="form-control" id="sb_barcode" readonly>
                        </div>
                        <div class="mb-2">
                            <label class="form-label mb-1">Nama Barang</label>
                            <input type="text" class="form-control" id="sb_nama_barang" readonly>
                        </div>
                        <div class="mb-2">
                            <label class="form-label mb-1">Stok Awal</label>
                            <input type="text" class="form-control" id="sb_stok_awal" readonly>
                        </div>
                        <div class="mb-2">
                            <label for="sb_stok_final" class="form-label mb-1">Stok Final</label>
                            <input type="number" class="form-control" id="sb_stok_final" min="0" required>
                        </div>
                        <div class="mb-2">
                            <label for="sb_qty_scan" class="form-label mb-1">Qty Scan</label>
                            <input type="number" class="form-control" id="sb_qty_scan" min="0" value="0" readonly>
                            <small class="text-muted">Setiap scan barcode item yang sama akan menambah 1 qty.</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">Simpan Stok Final</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

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

        $('#sm_select_barang_id').select2({
            theme: 'bootstrap-5',
            width: '100%',
            placeholder: '-- Pilih Barang --',
            allowClear: true,
            minimumResultsForSearch: 0,
            dropdownParent: $('#stokMasukModal')
        });
    }

    (function initStokMasukModal() {
        const modalEl = document.getElementById('stokMasukModal');
        const selectBarang = document.getElementById('sm_select_barang_id');
        const displaySatuan = document.getElementById('sm_display_satuan');
        const displayStokTersedia = document.getElementById('sm_display_stok_tersedia');
        const inputJumlah = document.getElementById('sm_input_jumlah');
        const inputKeteranganItem = document.getElementById('sm_input_keterangan_item');
        const addItemBtn = document.getElementById('sm_addItemBtn');
        const itemsTableBody = document.querySelector('#sm_itemsTable tbody');
        const itemsDataInput = document.getElementById('sm_items_data');
        const submitBtn = document.getElementById('sm_submitBtn');

        if (!modalEl || !selectBarang || !itemsTableBody || !itemsDataInput || !submitBtn) return;

        let items = [];
        let barangLoaded = false;
        let lastTargetStokId = '';
        let lastTargetBarangId = '';
        let lastTargetBarangNama = '';
        let quickMode = false;
        const itemsTableContainer = document.getElementById('sm_itemsTable')?.closest('.table-responsive');

        function syncBarangFields() {
            const selectedOption = selectBarang.options[selectBarang.selectedIndex];
            if (selectedOption && selectedOption.value) {
                displaySatuan.value = selectedOption.getAttribute('data-satuan') || '';
                displayStokTersedia.value = selectedOption.getAttribute('data-stok-tersedia') || '0';
            } else {
                displaySatuan.value = '';
                displayStokTersedia.value = '';
            }
        }

        function renderItems() {
            itemsTableBody.innerHTML = '';
            items.forEach((item, index) => {
                const row = itemsTableBody.insertRow();
                row.innerHTML = `
                    <td>${item.kode_barang}</td>
                    <td>${item.nama_barang}</td>
                    <td>${item.detail_barang || '-'}</td>
                    <td class="text-center">${item.jumlah}</td>
                    <td>${item.satuan || '-'}</td>
                    <td class="text-center">
                        <button type="button" class="btn btn-danger btn-sm sm-remove-item-btn" data-index="${index}">Hapus</button>
                    </td>
                `;
            });
            itemsDataInput.value = JSON.stringify(items);
            submitBtn.disabled = items.length === 0;
        }

        function setQuickItems() {
            const selectedOption = selectBarang.options[selectBarang.selectedIndex];
            const barangId = String(selectBarang.value || '');
            const jumlah = parseInt(inputJumlah.value, 10);
            const detailBarang = (inputKeteranganItem?.value || '').trim();

            if (!barangId || isNaN(jumlah) || jumlah <= 0) {
                items = [];
                renderItems();
                return;
            }

            items = [
                {
                    stok_id: String(lastTargetStokId || ''),
                    barang_id: barangId,
                    kode_barang: selectedOption?.getAttribute('data-kode') || '',
                    nama_barang: selectedOption?.getAttribute('data-nama') || '',
                    detail_barang: detailBarang,
                    jumlah: jumlah,
                    satuan: selectedOption?.getAttribute('data-satuan') || ''
                }
            ];
            renderItems();
        }

        function enableQuickMode() {
            quickMode = true;
            if (addItemBtn) addItemBtn.style.display = 'none';
            if (displaySatuan) {
                const satuanWrapper = displaySatuan.closest('.col-md-2') || displaySatuan.closest('[class*="col-"]');
                if (satuanWrapper) satuanWrapper.style.display = 'none';
            }
            if (displayStokTersedia) {
                const stokWrapper = displayStokTersedia.closest('.col-md-2') || displayStokTersedia.closest('[class*="col-"]');
                if (stokWrapper) stokWrapper.style.display = 'none';
            }
            if (itemsTableContainer) itemsTableContainer.style.display = 'none';
            if (window.jQuery) {
                $('#sm_select_barang_id').prop('disabled', true);
            } else {
                selectBarang.disabled = true;
            }
        }

        function disableQuickMode() {
            quickMode = false;
            if (addItemBtn) addItemBtn.style.display = '';
            if (inputKeteranganItem) {
                const keteranganWrapper = inputKeteranganItem.closest('.col-12') || inputKeteranganItem.closest('[class*="col-"]');
                if (keteranganWrapper) keteranganWrapper.style.display = '';
            }
            if (displaySatuan) {
                const satuanWrapper = displaySatuan.closest('.col-md-2') || displaySatuan.closest('[class*="col-"]');
                if (satuanWrapper) satuanWrapper.style.display = '';
            }
            if (displayStokTersedia) {
                const stokWrapper = displayStokTersedia.closest('.col-md-2') || displayStokTersedia.closest('[class*="col-"]');
                if (stokWrapper) stokWrapper.style.display = '';
            }
            if (itemsTableContainer) itemsTableContainer.style.display = '';
            if (window.jQuery) {
                $('#sm_select_barang_id').prop('disabled', false);
            } else {
                selectBarang.disabled = false;
            }
        }

        function loadBarang() {
            if (barangLoaded) return Promise.resolve();
            return fetch(`../stok/masuk/get_barang_by_gudang.php?gudang_id=<?= (int)$gudang_id ?>`)
                .then(response => response.json())
                .then(data => {
                    if (!data?.success) return;
                    const currentValue = selectBarang.value;
                    if (window.jQuery) {
                        const $select = $('#sm_select_barang_id');
                        $select.empty().append(new Option('-- Pilih Barang --', ''));
                        (data.barang || []).forEach(item => {
                            const option = new Option(
                                `${item.kode_barang} - ${item.nama_barang} (Tersedia: ${item.stok_tersedia || 0})`,
                                item.id
                            );
                            option.setAttribute('data-stok-id', item.stok_id || 0);
                            option.setAttribute('data-kode', item.kode_barang || '');
                            option.setAttribute('data-nama', item.nama_barang || '');
                            option.setAttribute('data-satuan', item.nama_satuan || '');
                            option.setAttribute('data-stok-tersedia', item.stok_tersedia || 0);
                            $select.append(option);
                        });
                        $select.trigger('change.select2');
                    } else {
                        selectBarang.innerHTML = '';
                        selectBarang.appendChild(new Option('-- Pilih Barang --', ''));
                        (data.barang || []).forEach(item => {
                            const option = document.createElement('option');
                            option.value = item.id;
                            option.setAttribute('data-stok-id', item.stok_id || 0);
                            option.setAttribute('data-kode', item.kode_barang || '');
                            option.setAttribute('data-nama', item.nama_barang || '');
                            option.setAttribute('data-satuan', item.nama_satuan || '');
                            option.setAttribute('data-stok-tersedia', item.stok_tersedia || 0);
                            option.textContent = `${item.kode_barang} - ${item.nama_barang} (Tersedia: ${item.stok_tersedia || 0})`;
                            selectBarang.appendChild(option);
                        });
                    }
                    barangLoaded = true;
                    if (window.jQuery) {
                        $('#sm_select_barang_id').val(currentValue || null).trigger('change.select2');
                    }
                })
                .catch(() => {});
        }

        function resetFormState(options = {}) {
            const clearTarget = options && options.clearTarget === true;
            items = [];
            disableQuickMode();
            renderItems();
            inputJumlah.value = '1';
            inputKeteranganItem.value = '';
            if (window.jQuery) {
                $('#sm_select_barang_id').val(null).trigger('change');
            } else {
                selectBarang.value = '';
                syncBarangFields();
            }
            if (clearTarget) {
                lastTargetStokId = '';
                lastTargetBarangId = '';
                lastTargetBarangNama = '';
            }
        }

        selectBarang.addEventListener('change', syncBarangFields);
        if (window.jQuery) {
            $('#sm_select_barang_id').on('select2:select select2:clear', syncBarangFields);
        }

        inputJumlah.addEventListener('input', function() {
            if (quickMode) setQuickItems();
        });

        inputKeteranganItem.addEventListener('input', function() {
            if (quickMode) setQuickItems();
        });

        selectBarang.addEventListener('change', function() {
            if (quickMode) setQuickItems();
        });
        if (window.jQuery) {
            $('#sm_select_barang_id').on('select2:select select2:clear', function() {
                if (quickMode) setQuickItems();
            });
        }

        addItemBtn.addEventListener('click', function() {
            const selectedOption = selectBarang.options[selectBarang.selectedIndex];
            if (!selectedOption || !selectBarang.value) {
                alert('Pilih barang terlebih dahulu.');
                return;
            }

            const barangId = String(selectBarang.value);
            const stokId = selectedOption.getAttribute('data-stok-id') || '';
            const kodeBarang = selectedOption.getAttribute('data-kode') || '';
            const namaBarang = selectedOption.getAttribute('data-nama') || '';
            const satuan = selectedOption.getAttribute('data-satuan') || '';
            const detailBarang = (inputKeteranganItem.value || '').trim();
            const jumlah = parseInt(inputJumlah.value, 10);

            if (isNaN(jumlah) || jumlah <= 0) {
                alert('Jumlah harus angka positif.');
                return;
            }

            const existingItemIndex = items.findIndex(item => String(item.barang_id) === barangId && (item.detail_barang || '') === detailBarang);
            if (existingItemIndex > -1) {
                items[existingItemIndex].jumlah += jumlah;
            } else {
                items.push({
                    stok_id: stokId,
                    barang_id: barangId,
                    kode_barang: kodeBarang,
                    nama_barang: namaBarang,
                    detail_barang: detailBarang,
                    jumlah: jumlah,
                    satuan: satuan
                });
            }

            inputKeteranganItem.value = '';
            inputJumlah.value = '1';
            if (window.jQuery) {
                $('#sm_select_barang_id').val(null).trigger('change');
            } else {
                selectBarang.value = '';
                syncBarangFields();
            }
            renderItems();
        });

        itemsTableBody.addEventListener('click', function(e) {
            const target = e.target;
            if (!(target instanceof HTMLElement)) return;
            if (!target.classList.contains('sm-remove-item-btn')) return;
            const index = parseInt(target.getAttribute('data-index') || '-1', 10);
            if (isNaN(index) || index < 0) return;
            items.splice(index, 1);
            renderItems();
        });

        modalEl.addEventListener('show.bs.modal', function(event) {
            const trigger = event.relatedTarget;
            lastTargetStokId = trigger && trigger.getAttribute ? (trigger.getAttribute('data-stok-id') || '') : '';
            lastTargetBarangId = trigger && trigger.getAttribute ? (trigger.getAttribute('data-barang-id') || '') : '';
            lastTargetBarangNama = trigger && trigger.getAttribute ? (trigger.getAttribute('data-barang-nama') || '') : '';

            resetFormState({ clearTarget: false });
            loadBarang().then(() => {
                if (lastTargetBarangId && window.jQuery) {
                    $('#sm_select_barang_id').val(String(lastTargetBarangId)).trigger('change');
                } else if (lastTargetBarangId) {
                    selectBarang.value = String(lastTargetBarangId);
                    syncBarangFields();
                }
                if (lastTargetBarangId) {
                    if (lastTargetBarangNama) {
                        inputKeteranganItem.value = lastTargetBarangNama;
                    } else {
                        const selectedOption = selectBarang.options[selectBarang.selectedIndex];
                        inputKeteranganItem.value = selectedOption?.getAttribute('data-nama') || '';
                    }
                    enableQuickMode();
                    setQuickItems();
                }
            });
        });

        modalEl.addEventListener('shown.bs.modal', function() {
            if (lastTargetBarangId) {
                const current = String(selectBarang.value || '');
                if (current !== String(lastTargetBarangId)) {
                    if (window.jQuery) {
                        $('#sm_select_barang_id').val(String(lastTargetBarangId)).trigger('change');
                    } else {
                        selectBarang.value = String(lastTargetBarangId);
                        syncBarangFields();
                    }
                }
                if (!inputKeteranganItem.value) {
                    if (lastTargetBarangNama) {
                        inputKeteranganItem.value = lastTargetBarangNama;
                    } else {
                        const selectedOption = selectBarang.options[selectBarang.selectedIndex];
                        inputKeteranganItem.value = selectedOption?.getAttribute('data-nama') || '';
                    }
                }
                if (quickMode) setQuickItems();
            }
            inputJumlah.focus();
            inputJumlah.select();
        });

        modalEl.addEventListener('hidden.bs.modal', function() {
            resetFormState({ clearTarget: true });
        });
    })();
    
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
            input.value = stokAwal - parseFloat(input.closest('tr').cells[9].textContent.replace(/,/g, ''));
            return;
        }

        // Show loading state
        input.disabled = true;
        input.style.backgroundColor = '#f8f9fa';

        // Debug log
        console.log('Sending update request:', { id: id, sisa_stok: sisaStok, stokAwal: stokAwal });
        
        fetch('process_operasional_stok_gudang87.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: 'action=update_sisa_stok&id=' + id + '&sisa_stok=' + sisaStok
        })
        .then(async (response) => {
            const text = await response.text();
            console.log('Raw response:', text);
            let data;
            try {
                data = JSON.parse(text);
                console.log('Parsed response:', data);
            } catch (e) {
                console.error('JSON parse error:', e);
                throw new Error(`Respons bukan JSON (HTTP ${response.status}): ${text.slice(0, 200)}`);
            }
            if (!response.ok) {
                throw new Error(data?.message || `HTTP ${response.status}`);
            }
            return data;
        })
        .then(data => {
            if(data.success) {
                const row = input.closest('tr');
                const stokTerpakai = data.data.stok_terpakai;
                const stokAkhir = data.data.sisa_stok;
                const stokMin = parseFloat(row.cells[11].textContent);

                if (finalOnly) {
                    input.value = String(stokAkhir);
                    input.setAttribute('data-prev-final', String(stokAkhir));
                    return;
                }

                // Update terpakai column
                row.cells[9].textContent = number_format(stokTerpakai);

                // Update status badge
                const statusCell = row.cells[10];
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
            console.error('Error details:', {
                message: error.message,
                stack: error.stack,
                name: error.name
            });
            showToast('Terjadi kesalahan: ' + error.message, 'error');
            // Reset input value
            input.value = stokAwal - parseFloat(input.closest('tr').cells[9].textContent.replace(/,/g, ''));
        })
        .finally(() => {
            input.disabled = false;
            input.style.backgroundColor = '';
            input.removeAttribute('data-update-scope');
        });
    };

    // Edit stock function
    window.editStok = function(id) {
        window.location.href = `edit.php?id=${id}&gudang=antapani`;
    };

    // Delete stock function
    window.deleteStok = function(id) {
        if (confirm('Apakah Anda yakin ingin menghapus stok ini?')) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'process_operasional_stok_gudang87.php';
            
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

   window.exportToExcel = function() {
    const url = 'export_gudang_excel%20.php?gudang_id=<?= (int)$gudang_id ?>';
    window.location.href = url;
};

    // Update statistics function
    function updateStatistics() {
        const rows = document.querySelectorAll('#inventoryTable tbody tr');
        let total = 0, aman = 0, minimum = 0, habis = 0;

        rows.forEach(row => {
            total++;
            const status = row.cells[10].textContent;
            if (status.includes('Aman')) aman++;
            else if (status.includes('Minimum')) minimum++;
            else if (status.includes('Habis')) habis++;
        });

        document.getElementById('totalBarang').textContent = total;
        document.getElementById('stokAman').textContent = aman;
        document.getElementById('stokMinimum').textContent = minimum;
        document.getElementById('stokHabis').textContent = habis;
        
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
<?php if (isset($_SESSION['notification'])): ?>
<script>
    Swal.fire({
        icon: '<?php echo $_SESSION['notification']['type']; ?>',
        title: '<?php echo $_SESSION['notification']['title']; ?>',
        text: '<?php echo $_SESSION['notification']['message']; ?>',
        showConfirmButton: true,
        timer: 3000
    });
    <?php unset($_SESSION['notification']); ?>
</script>
<?php endif; ?>
