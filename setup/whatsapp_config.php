<?php
session_start();
require_once '../config.php';
require_once '../includes/access_check.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

if (!checkAccess('get_wa', 'view')) {
    $_SESSION['error'] = 'Anda tidak memiliki akses ke halaman Setup WhatsApp!';
    header('Location: /minven_pro/dashboard.php');
    exit();
}

// Create table if not exists
$conn->query("CREATE TABLE IF NOT EXISTS setup_whatsapp (
    id INT AUTO_INCREMENT PRIMARY KEY,
    wa_number VARCHAR(20) NOT NULL,
    template_message TEXT,
    is_active TINYINT(1) DEFAULT 1,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by INT
)");

// Check if there is data
$check = $conn->query("SELECT * FROM setup_whatsapp LIMIT 1");
if ($check->num_rows == 0) {
    $conn->query("INSERT INTO setup_whatsapp (wa_number, template_message) VALUES ('628123456789', 'Halo, ada Purchase Order baru dengan nomor {no_po} untuk supplier {supplier}. Mohon diproses. Terima kasih.')");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $wa_number = $_POST['wa_number'];
    $template_message = $_POST['template_message'];
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    $updateSql = "UPDATE setup_whatsapp SET wa_number = ?, template_message = ?, is_active = ?, updated_by = ? WHERE id = 1";
    $stmt = $conn->prepare($updateSql);
    $stmt->bind_param("ssii", $wa_number, $template_message, $is_active, $_SESSION['user_id']);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Pengaturan WhatsApp berhasil disimpan!";
    } else {
        $_SESSION['error'] = "Gagal menyimpan pengaturan: " . $conn->error;
    }
    
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

$config = $conn->query("SELECT * FROM setup_whatsapp WHERE id = 1")->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup WhatsApp - MINVEN PRO</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background: #f8f9fa; min-height: 100vh; font-family: 'Segoe UI', sans-serif; }
        .card { border-radius: 1rem; border: none; box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.1); }
        .card-header { background: #004aad; color: white; border-radius: 1rem 1rem 0 0 !important; padding: 1.25rem; }
        .btn-primary { background: #004aad; border: none; padding: 0.6rem 1.5rem; border-radius: 0.5rem; }
        .btn-primary:hover { background: #003a8c; }
        .info-box { background: #e7f1ff; border-left: 4px solid #004aad; padding: 1rem; border-radius: 0.5rem; margin-bottom: 1.5rem; }
    </style>
</head>
<body>
    <?php include '../templates/navbar.php'; ?>

    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-whatsapp me-2"></i> Pengaturan WhatsApp</h5>
                    </div>
                    <div class="card-body p-4">
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

                        <div class="info-box">
                            <i class="bi bi-info-circle me-2"></i>
                            Gunakan variabel berikut dalam template pesan:
                            <ul class="mb-0 mt-2">
                                <li><code>{no_po}</code> - Nomor Purchase Order</li>
                                <li><code>{supplier}</code> - Nama Supplier</li>
                                <li><code>{tanggal}</code> - Tanggal PO</li>
                                <li><code>{total}</code> - Total Harga PO</li>
                                <li><code>{items}</code> - Daftar Nama Barang dalam PO</li>
                                <li><code>{total_item}</code> - Total Jumlah Item dalam PO</li>
                            </ul>
                        </div>

                        <form method="POST">
                            <div class="mb-4">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="is_active" value="1" <?= $config['is_active'] ? 'checked' : '' ?>>
                                    <label class="form-check-label fw-bold">Aktifkan Fitur WhatsApp</label>
                                </div>
                                <div class="form-text mt-2 text-info">
                                    <i class="bi bi-info-circle me-1"></i>
                                    Nomor tujuan akan diambil otomatis dari data <strong>Telepon Supplier</strong> yang terdaftar.
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold">Template Pesan</label>
                                <textarea name="template_message" class="form-control" rows="5" required><?= htmlspecialchars($config['template_message']) ?></textarea>
                            </div>

                            <input type="hidden" name="wa_number" value="DYNAMIC">

                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="bi bi-save me-2"></i> Simpan Pengaturan
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