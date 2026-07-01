<?php
session_start();
require_once '../config.php';
require_once '../includes/access_check.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

if (!checkAccess('reset_stok', 'view') && !checkAccess('setup', 'view')) {
    $_SESSION['error'] = 'Anda tidak memiliki akses ke halaman Setup Reset Stok!';
    header('Location: /minven_pro/dashboard.php');
    exit();
}

$gudang_list = $conn->query("SELECT id, kode_gudang, nama_gudang FROM gudang ORDER BY nama_gudang");
$all_gudang = [];
while ($g = $gudang_list->fetch_assoc()) {
    $all_gudang[] = $g;
}

$checkTable = $conn->query("SHOW TABLES LIKE 'setup_reset_stok'");
if ($checkTable->num_rows == 0) {
    $createTable = "CREATE TABLE IF NOT EXISTS setup_reset_stok (
        id INT AUTO_INCREMENT PRIMARY KEY,
        jam_reset TIME NOT NULL DEFAULT '00:00:00',
        gudang_id INT NOT NULL,
        is_active TINYINT(1) DEFAULT 1,
        last_reset DATETIME DEFAULT NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        updated_by INT,
        UNIQUE KEY unique_gudang (gudang_id)
    )";
    $conn->query($createTable);
    
    foreach ($all_gudang as $g) {
        $conn->query("INSERT INTO setup_reset_stok (jam_reset, gudang_id) VALUES ('20:10', {$g['id']})");
    }
} else {
    $checkColumn = $conn->query("SHOW COLUMNS FROM setup_reset_stok LIKE 'last_reset'");
    if ($checkColumn->num_rows == 0) {
        $conn->query("ALTER TABLE setup_reset_stok ADD COLUMN last_reset DATETIME DEFAULT NULL AFTER is_active");
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_all'])) {
        if (!checkAccess('reset_stok', 'edit') && !checkAccess('setup', 'edit')) {
            $_SESSION['error'] = 'Anda tidak memiliki akses untuk menyimpan pengaturan reset stok!';
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        }

        $jam_reset = $_POST['jam_reset_all'] ?? '20:10';

        $updateSql = "UPDATE setup_reset_stok SET jam_reset = ?, is_active = 1, updated_by = ?";
        $stmt = $conn->prepare($updateSql);
        $stmt->bind_param("si", $jam_reset, $_SESSION['user_id']);
        $stmt->execute();
        
        $_SESSION['success'] = "Pengaturan reset stok berhasil disimpan untuk semua gudang!";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
}

$jam_reset_all = '20:10';
$last_reset_all = '-';

$summary = $conn->query("SELECT 
        COUNT(*) as total,
        MIN(TIME_FORMAT(jam_reset, '%H:%i')) as any_time,
        MAX(last_reset) as max_last_reset
    FROM setup_reset_stok");
if ($summary && ($s = $summary->fetch_assoc())) {
    if (!empty($s['any_time'])) {
        $jam_reset_all = (string)$s['any_time'];
    }
    if (!empty($s['max_last_reset'])) {
        $last_reset_all = date('d/m/Y H:i', strtotime((string)$s['max_last_reset']));
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup Reset Stok - MINVEN PRO</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
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
        
        .card-header {
            background: #0008f9;
            color: white;
            border-radius: 0.75rem 0.75rem 0 0 !important;
            padding: 15px 20px;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #0008f9;
            box-shadow: 0 0 0 0.2rem rgba(0, 8, 249, 0.25);
        }
        
        .btn-primary {
            background: #0008f9;
            border: none;
            padding: 0.6rem 1.5rem;
            border-radius: 8px;
        }
        
        .btn-primary:hover {
            background: #0006d4;
        }
        
        .btn-success {
            background: #28a745;
            border: none;
        }
        
        .btn-success:hover {
            background: #218838;
        }
        
        .info-box {
            background: #f8f9fa;
            border-left: 4px solid #0008f9;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
        }
        
        .table thead th {
            background: #0008f9;
            color: white;
            border: none;
            padding: 1rem;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.875rem;
            letter-spacing: 0.05em;
        }
        
        .form-switch .form-check-input:checked {
            background-color: #0008f9;
            border-color: #0008f9;
        }
    </style>
</head>
<body>
    <?php include '../templates/navbar.php'; ?>

    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <div class="card shadow">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="bi bi-gear me-2"></i>
                            Setup Jam Reset Stok
                        </h5>
                        <?php if (checkAccess('setup_upload_template', 'view')): ?>
                            <a href="upload_template_setup.php" class="btn btn-sm btn-light">
                                <i class="bi bi-upload me-1"></i> Upload Template Laporan & Logo
                            </a>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <?php if (isset($_SESSION['success'])): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="bi bi-check-circle me-1"></i> <?= $_SESSION['success']; unset($_SESSION['success']); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (isset($_SESSION['error'])): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="bi bi-exclamation-circle me-1"></i> <?= $_SESSION['error']; unset($_SESSION['error']); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <div class="info-box">
                            <small class="text-muted">
                                <i class="bi bi-info-circle me-1"></i>
                                Masukkan jam reset stok harian. Setiap hari pada jam ini, stok akhir otomatis dijadikan stok awal dan stok terpakai direset ke 0.
                            </small>
                        </div>
                        
                        <form method="POST" action="">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Jam Reset Harian</label>
                                    <input type="time" class="form-control" name="jam_reset_all" value="<?= htmlspecialchars($jam_reset_all) ?>" step="60">
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2 mt-3">
                                <button type="submit" name="save_all" class="btn btn-primary btn-lg">
                                    <i class="bi bi-save me-2"></i>Simpan Semua Pengaturan
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
