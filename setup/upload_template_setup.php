<?php
session_start();
require_once '../config.php';
require_once '../includes/access_check.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

if (!checkAccess('setup_upload_template', 'view')) {
    $_SESSION['error'] = 'Anda tidak memiliki akses ke halaman Upload Template Setup!';
    header('Location: /minven_pro/dashboard.php');
    exit();
}

$createTableSql = "CREATE TABLE IF NOT EXISTS setup_file_template (
    id INT AUTO_INCREMENT PRIMARY KEY,
    kategori ENUM('laporan', 'cetakan', 'logo') NOT NULL,
    nama_file_asli VARCHAR(255) NOT NULL,
    nama_file_simpan VARCHAR(255) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    file_ext VARCHAR(20) NOT NULL,
    mime_type VARCHAR(100) DEFAULT NULL,
    file_size INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    uploaded_by INT DEFAULT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
$conn->query($createTableSql);

$projectRoot = realpath(__DIR__ . '/..');
$allowedTemplateExt = ['pdf', 'xls', 'xlsx'];
$allowedLogoExt = ['jpg', 'jpeg', 'png', 'ico'];

function ensureDirectory($path)
{
    if (!is_dir($path)) {
        mkdir($path, 0775, true);
    }
}

function buildUploadPath($projectRoot, $relativePath)
{
    return $projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
}

function saveUploadedFile($conn, $kategori, $file, $allowedExt, $targetRelativeDir)
{
    global $projectRoot;

    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload file gagal. Coba ulangi lagi.');
    }

    $originalName = trim((string)$file['name']);
    $fileExt = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if (!in_array($fileExt, $allowedExt, true)) {
        throw new RuntimeException('Format file tidak sesuai ketentuan.');
    }

    $targetAbsoluteDir = buildUploadPath($projectRoot, $targetRelativeDir);
    ensureDirectory($targetAbsoluteDir);

    $safeKategori = preg_replace('/[^a-z0-9_]/i', '', $kategori);
    $generatedName = $safeKategori . '_' . date('YmdHis') . '_' . substr(bin2hex(random_bytes(6)), 0, 8) . '.' . $fileExt;
    $relativePath = rtrim($targetRelativeDir, '/') . '/' . $generatedName;
    $targetFile = buildUploadPath($projectRoot, $relativePath);

    if (!move_uploaded_file($file['tmp_name'], $targetFile)) {
        throw new RuntimeException('Gagal menyimpan file ke server.');
    }

    $mimeType = function_exists('mime_content_type') ? mime_content_type($targetFile) : ($file['type'] ?? '');
    $size = isset($file['size']) ? (int)$file['size'] : 0;

    $deactivateStmt = $conn->prepare("UPDATE setup_file_template SET is_active = 0 WHERE kategori = ?");
    $deactivateStmt->bind_param('s', $kategori);
    $deactivateStmt->execute();
    $deactivateStmt->close();

    $insertStmt = $conn->prepare("
        INSERT INTO setup_file_template 
        (kategori, nama_file_asli, nama_file_simpan, file_path, file_ext, mime_type, file_size, is_active, uploaded_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?)
    ");
    $insertStmt->bind_param(
        'ssssssii',
        $kategori,
        $originalName,
        $generatedName,
        $relativePath,
        $fileExt,
        $mimeType,
        $size,
        $_SESSION['user_id']
    );
    $insertStmt->execute();
    $insertStmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!checkAccess('setup_upload_template', 'edit')) {
        $_SESSION['error'] = 'Anda tidak memiliki akses untuk upload template.';
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }

    try {
        if (isset($_POST['upload_template_laporan'])) {
            if (!isset($_FILES['template_laporan'])) {
                throw new RuntimeException('File template laporan belum dipilih.');
            }
            saveUploadedFile($conn, 'laporan', $_FILES['template_laporan'], $allowedTemplateExt, 'uploads/setup/templates/laporan');
            $_SESSION['success'] = 'Template laporan berhasil diupload. Format yang didukung: PDF, XLS, XLSX.';
        } elseif (isset($_POST['upload_template_cetakan'])) {
            if (!isset($_FILES['template_cetakan'])) {
                throw new RuntimeException('File template cetakan belum dipilih.');
            }
            saveUploadedFile($conn, 'cetakan', $_FILES['template_cetakan'], $allowedTemplateExt, 'uploads/setup/templates/cetakan');
            $_SESSION['success'] = 'Template cetakan berhasil diupload. Format yang didukung: PDF, XLS, XLSX.';
        } elseif (isset($_POST['upload_logo_template'])) {
            if (!isset($_FILES['template_logo'])) {
                throw new RuntimeException('File logo belum dipilih.');
            }
            saveUploadedFile($conn, 'logo', $_FILES['template_logo'], $allowedLogoExt, 'uploads/setup/logo');
            $_SESSION['success'] = 'Template logo berhasil diupload. Format yang didukung: JPG, PNG, ICO.';
        }
    } catch (Throwable $e) {
        $_SESSION['error'] = $e->getMessage();
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

$activeFiles = [
    'laporan' => null,
    'cetakan' => null,
    'logo' => null
];
$result = $conn->query("SELECT * FROM setup_file_template WHERE is_active = 1 ORDER BY uploaded_at DESC");
while ($row = $result->fetch_assoc()) {
    if (isset($activeFiles[$row['kategori']]) && $activeFiles[$row['kategori']] === null) {
        $activeFiles[$row['kategori']] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup Upload Template - MINVEN PRO</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
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
            color: black;
            border-radius: 0.75rem 0.75rem 0 0 !important;
            padding: 15px 20px;
        }
        .card .card .card-header {
            background: #f8f9fa;
            color: #212529;
            border-bottom: 1px solid #e2e8f0;
        }
        .btn-primary {
            background: #0008f9;
            border: none;
        }
        .btn-primary:hover {
            background: #0006d4;
        }
        .info-box {
            background: #f8f9fa;
            border-left: 4px solid #0008f9;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
        }
        .active-file-box {
            border: 1px dashed #ced4da;
            border-radius: 8px;
            padding: 0.75rem;
            background: #f8f9fa;
        }
        .card-body,
        .card-body label,
        .card-body small,
        .card-body strong,
        .active-file-box,
        .active-file-box span,
        .active-file-box a {
            color: #212529 !important;
        }
        .card-body .text-muted {
            color: #6c757d !important;
        }
        .card-body .form-control {
            color: #212529 !important;
            background-color: #ffffff;
            border-color: #ced4da;
        }
        .card-body .form-control::file-selector-button {
            color: #212529;
            background-color: #f1f3f5;
        }
    </style>
</head>
<body>
    <?php include '../templates/navbar.php'; ?>

    <div class="container-fluid mt-4 mb-4">
        <div class="row">
            <div class="col-12">
                <div class="card shadow">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="bi bi-upload me-2"></i>
                            Upload Template Laporan, Cetakan, dan Logo
                        </h5>
                        <a href="index.php" class="btn btn-sm btn-light">
                            <i class="bi bi-arrow-left me-1"></i> Kembali
                        </a>
                    </div>
                    <div class="card-body">
                        <?php if (isset($_SESSION['success'])): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="bi bi-check-circle me-1"></i> <?= htmlspecialchars($_SESSION['success']) ?>
                                <?php unset($_SESSION['success']); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <?php if (isset($_SESSION['error'])): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="bi bi-exclamation-circle me-1"></i> <?= htmlspecialchars($_SESSION['error']) ?>
                                <?php unset($_SESSION['error']); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <div class="info-box">
                            <small class="text-muted">
                                <i class="bi bi-info-circle me-1"></i>
                                Format upload template laporan/cetakan: <strong>PDF, XLS, XLSX</strong>. 
                                Format upload logo: <strong>JPG, PNG, ICO</strong>.
                                File terbaru akan menjadi file aktif.
                            </small>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-4">
                                <div class="card h-100">
                                    <div class="card-header"><h6 class="mb-0">Template Laporan</h6></div>
                                    <div class="card-body">
                                        <form method="POST" enctype="multipart/form-data">
                                            <div class="mb-3">
                                                <label class="form-label">Pilih File</label>
                                                <input type="file" name="template_laporan" class="form-control" accept=".pdf,.xls,.xlsx" required>
                                                <small class="text-muted">Format: PDF, XLS, XLSX</small>
                                            </div>
                                            <button type="submit" name="upload_template_laporan" class="btn btn-primary w-100">
                                                <i class="bi bi-cloud-upload me-1"></i>Upload Template Laporan
                                            </button>
                                        </form>
                                        <div class="active-file-box mt-3">
                                            <small class="text-muted d-block">File Aktif:</small>
                                            <?php if ($activeFiles['laporan']): ?>
                                                <strong><?= htmlspecialchars($activeFiles['laporan']['nama_file_asli']) ?></strong><br>
                                                <a href="<?= htmlspecialchars(url_for($activeFiles['laporan']['file_path'])) ?>" target="_blank" class="small">Lihat / Download</a>
                                            <?php else: ?>
                                                <span class="text-muted">Belum ada file.</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <div class="card h-100">
                                    <div class="card-header"><h6 class="mb-0">Template Cetakan</h6></div>
                                    <div class="card-body">
                                        <form method="POST" enctype="multipart/form-data">
                                            <div class="mb-3">
                                                <label class="form-label">Pilih File</label>
                                                <input type="file" name="template_cetakan" class="form-control" accept=".pdf,.xls,.xlsx" required>
                                                <small class="text-muted">Format: PDF, XLS, XLSX</small>
                                            </div>
                                            <button type="submit" name="upload_template_cetakan" class="btn btn-primary w-100">
                                                <i class="bi bi-cloud-upload me-1"></i>Upload Template Cetakan
                                            </button>
                                        </form>
                                        <div class="active-file-box mt-3">
                                            <small class="text-muted d-block">File Aktif:</small>
                                            <?php if ($activeFiles['cetakan']): ?>
                                                <strong><?= htmlspecialchars($activeFiles['cetakan']['nama_file_asli']) ?></strong><br>
                                                <a href="<?= htmlspecialchars(url_for($activeFiles['cetakan']['file_path'])) ?>" target="_blank" class="small">Lihat / Download</a>
                                            <?php else: ?>
                                                <span class="text-muted">Belum ada file.</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <div class="card h-100">
                                    <div class="card-header"><h6 class="mb-0">Template Logo</h6></div>
                                    <div class="card-body">
                                        <form method="POST" enctype="multipart/form-data">
                                            <div class="mb-3">
                                                <label class="form-label">Pilih File</label>
                                                <input type="file" name="template_logo" class="form-control" accept=".jpg,.jpeg,.png,.ico" required>
                                                <small class="text-muted">Format: JPG, PNG, ICO</small>
                                            </div>
                                            <button type="submit" name="upload_logo_template" class="btn btn-primary w-100">
                                                <i class="bi bi-cloud-upload me-1"></i>Upload Template Logo
                                            </button>
                                        </form>
                                        <div class="active-file-box mt-3">
                                            <small class="text-muted d-block">File Aktif:</small>
                                            <?php if ($activeFiles['logo']): ?>
                                                <strong><?= htmlspecialchars($activeFiles['logo']['nama_file_asli']) ?></strong><br>
                                                <a href="<?= htmlspecialchars(url_for($activeFiles['logo']['file_path'])) ?>" target="_blank" class="small">Lihat / Download</a>
                                            <?php else: ?>
                                                <span class="text-muted">Belum ada file.</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
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
