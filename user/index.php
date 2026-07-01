<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

// Handle tambah user baru
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['username'])) {
    $username = $_POST['username'];
    $nama = $_POST['nama'];
    $nama_lengkap = $_POST['nama_lengkap'];
    $email = $_POST['email'];
    $kode_pegawai = $_POST['kode_pegawai'] ?? null;
    $telepon = $_POST['telepon'] ?? null;
    $alamat = $_POST['alamat'] ?? null;
    $keterangan = $_POST['keterangan'] ?? null;
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $is_active = ($_POST['status'] == 'active') ? 1 : 0;
    $role_id = isset($_POST['role_id']) ? (int)$_POST['role_id'] : 0;
    $created_by = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
    $profile_picture = null;
    $uploaded_file_full_path = null;

    if (isset($_FILES['profile_picture']) && is_array($_FILES['profile_picture'])) {
        if ($_FILES['profile_picture']['error'] !== UPLOAD_ERR_NO_FILE) {
            if ($_FILES['profile_picture']['error'] !== UPLOAD_ERR_OK) {
                $_SESSION['error'] = "Gagal mengunggah foto.";
                header("Location: index.php");
                exit();
            }

            $tmp_name = (string)$_FILES['profile_picture']['tmp_name'];
            $file_size = (int)$_FILES['profile_picture']['size'];
            $original_name = (string)$_FILES['profile_picture']['name'];

            if ($file_size > 2 * 1024 * 1024) {
                $_SESSION['error'] = "Ukuran foto maksimal 2MB.";
                header("Location: index.php");
                exit();
            }

            $image_info = @getimagesize($tmp_name);
            if ($image_info === false || !isset($image_info['mime'])) {
                $_SESSION['error'] = "File foto tidak valid.";
                header("Location: index.php");
                exit();
            }

            $allowed_mime_to_ext = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
                'image/gif' => 'gif',
            ];

            $mime = (string)$image_info['mime'];
            if (!isset($allowed_mime_to_ext[$mime])) {
                $_SESSION['error'] = "Format foto harus JPG, PNG, WEBP, atau GIF.";
                header("Location: index.php");
                exit();
            }

            $ext = $allowed_mime_to_ext[$mime];
            $safe_base = preg_replace('/[^a-zA-Z0-9._-]+/', '_', pathinfo($original_name, PATHINFO_FILENAME));
            $safe_base = $safe_base !== '' ? $safe_base : 'profile';

            try {
                $random = bin2hex(random_bytes(4));
            } catch (Exception $e) {
                $random = (string)mt_rand(10000000, 99999999);
            }

            $upload_dir = __DIR__ . '/../uploads/user';
            if (!is_dir($upload_dir)) {
                if (!mkdir($upload_dir, 0755, true) && !is_dir($upload_dir)) {
                    $_SESSION['error'] = "Folder upload tidak tersedia.";
                    header("Location: index.php");
                    exit();
                }
            }

            $filename = 'user_' . time() . '_' . $random . '_' . $safe_base . '.' . $ext;
            $target_path = $upload_dir . '/' . $filename;

            if (!move_uploaded_file($tmp_name, $target_path)) {
                $_SESSION['error'] = "Gagal menyimpan foto.";
                header("Location: index.php");
                exit();
            }

            $uploaded_file_full_path = $target_path;
            $profile_picture = '../uploads/user/' . $filename;
        }
    }

    $role = '';
    if ($role_id > 0) {
        $role_stmt = $conn->prepare("SELECT nama_role FROM roles WHERE id = ?");
        if ($role_stmt) {
            $role_stmt->bind_param('i', $role_id);
            $role_stmt->execute();
            $role_row = $role_stmt->get_result()->fetch_assoc();
            $role = $role_row ? (string)$role_row['nama_role'] : '';
            $role_stmt->close();
        }
    }
    
    $sql = "INSERT INTO users (username, nama, nama_lengkap, email, kode_pegawai, telepon, alamat, keterangan, password, is_active, role, role_id, created_by, profile_picture) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    
    if ($stmt === false) {
        $_SESSION['error'] = "Gagal menyiapkan query: " . $conn->error;
    } else {
        if ($role_id <= 0 || $role === '') {
            $_SESSION['error'] = "Role harus dipilih.";
            $stmt->close();
            header("Location: index.php");
            exit();
        }

        $stmt->bind_param('sssssssssisiis', $username, $nama, $nama_lengkap, $email, $kode_pegawai, $telepon, $alamat, $keterangan, $password, $is_active, $role, $role_id, $created_by, $profile_picture);

        $insert_ok = false;
        $new_user_id = null;

        try {
            $conn->begin_transaction();

            if (!$stmt->execute()) {
                throw new Exception($stmt->error);
            }

            $new_user_id = (int)$conn->insert_id;

            $ur_stmt = $conn->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)");
            if ($ur_stmt === false) {
                throw new Exception($conn->error);
            }
            $ur_stmt->bind_param('ii', $new_user_id, $role_id);
            if (!$ur_stmt->execute()) {
                throw new Exception($ur_stmt->error);
            }
            $ur_stmt->close();

            $conn->commit();
            $insert_ok = true;
        } catch (Exception $e) {
            $conn->rollback();
            if ($uploaded_file_full_path && is_file($uploaded_file_full_path)) {
                @unlink($uploaded_file_full_path);
            }
            $_SESSION['error'] = "Gagal menambahkan user: " . $e->getMessage();
        }

        if ($insert_ok) {
            // Kirim email selamat datang
            require_once '../libs/PHPMailer/src/Exception.php';
            require_once '../libs/PHPMailer/src/PHPMailer.php';
            require_once '../libs/PHPMailer/src/SMTP.php';

            $mail = new PHPMailer\PHPMailer\PHPMailer(true);

            try {
                // Server settings
                $mail->isSMTP();
                $mail->Host       = SMTP_HOST;
                $mail->SMTPAuth   = true;
                $mail->Username   = SMTP_USER;
                $mail->Password   = SMTP_PASS;
                $mail->SMTPSecure = SMTP_SECURE;
                $mail->Port       = SMTP_PORT;

                // Recipients
                $mail->setFrom(FROM_EMAIL, FROM_NAME);
                $mail->addAddress($email, $nama_lengkap);

                // Content
                $mail->isHTML(true);
                $mail->Subject = 'Selamat Datang di MINVEN - Detail Akun Anda';
                $plain_password = $_POST['password'];
                $mail->Body    = "
                    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e0e0e0; border-radius: 10px;'>
                        <h2 style='color: #4e73df; text-align: center;'>Selamat Datang di MINVEN</h2>
                        <p>Halo <b>$nama_lengkap</b>,</p>
                        <p>Akun Anda telah berhasil dibuat. Berikut adalah detail login Anda:</p>
                        <div style='background-color: #f8f9fc; padding: 15px; border-radius: 5px; margin: 20px 0;'>
                            <p style='margin: 5px 0;'><b>Username:</b> $username</p>
                            <p style='margin: 5px 0;'><b>Password:</b> $plain_password</p>
                            <p style='margin: 5px 0;'><b>Role:</b> $role</p>
                        </div>
                        <p>Silakan login di <a href='http://{$_SERVER['HTTP_HOST']}" . dirname($_SERVER['PHP_SELF'], 2) . "/index.php'>halaman login</a>.</p>
                        <p style='color: #666; font-size: 0.9em;'>Harap segera ganti password Anda setelah login pertama kali.</p>
                        <hr style='border: 0; border-top: 1px solid #eee; margin: 20px 0;'>
                        <p style='text-align: center; color: #999; font-size: 0.8em;'>&copy; " . date("Y") . " MINVEN. All Rights Reserved.</p>
                    </div>
                ";
                
                $mail->send();
                $_SESSION['success'] = "User baru berhasil ditambahkan dan email notifikasi telah dikirim.";
            } catch (Exception $e) {
                $_SESSION['success'] = "User berhasil ditambahkan, namun gagal mengirim email notifikasi. Error: {$mail->ErrorInfo}";
            }
        } else {
            if (!isset($_SESSION['error'])) {
                if ($uploaded_file_full_path && is_file($uploaded_file_full_path)) {
                    @unlink($uploaded_file_full_path);
                }
                $_SESSION['error'] = "Gagal menambahkan user: " . $stmt->error;
            }
        }
        $stmt->close();
    }
    
    header("Location: index.php");
    exit();
}

// Handle hapus user
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $user_id = (int)$_GET['id'];
    
    // Prevent deleting own account
    if ($user_id == $_SESSION['user_id']) {
        $_SESSION['error'] = "Tidak dapat menghapus akun sendiri";
    } else {
        // Delete user roles first
        $delete_roles_sql = "DELETE FROM user_roles WHERE user_id = ?";
        $delete_roles_stmt = $conn->prepare($delete_roles_sql);
        
        if ($delete_roles_stmt === false) {
            $_SESSION['error'] = "Gagal menyiapkan query hapus roles: " . $conn->error;
        } else {
            $delete_roles_stmt->bind_param('i', $user_id);
            $delete_roles_stmt->execute();
            $delete_roles_stmt->close();
        }
        
        // Delete user
        $delete_user_sql = "DELETE FROM users WHERE id = ?";
        $delete_user_stmt = $conn->prepare($delete_user_sql);
        
        if ($delete_user_stmt === false) {
            $_SESSION['error'] = "Gagal menyiapkan query hapus user: " . $conn->error;
        } else {
            $delete_user_stmt->bind_param('i', $user_id);
            
            if ($delete_user_stmt->execute()) {
                $_SESSION['success'] = "User berhasil dihapus";
            } else {
                $_SESSION['error'] = "Gagal menghapus user: " . $delete_user_stmt->error;
            }
            $delete_user_stmt->close();
        }
    }
    
    header("Location: index.php");
    exit();
}

// Query untuk mengambil data user dengan role
$sql = "SELECT u.*, GROUP_CONCAT(r.nama_role SEPARATOR ', ') as roles 
        FROM users u 
        LEFT JOIN user_roles ur ON u.id = ur.user_id 
        LEFT JOIN roles r ON ur.role_id = r.id 
        GROUP BY u.id 
        ORDER BY u.nama_lengkap";
$result = $conn->query($sql);
if (!$result) {
    die("Query failed: " . $conn->error);
}

// Get roles for dropdown
$roles_sql = "SELECT * FROM roles ORDER BY nama_role";
$roles_result = $conn->query($roles_sql);
if (!$roles_result) {
    die("Roles query failed: " . $conn->error);
}
$roles = [];
while ($role_row = $roles_result->fetch_assoc()) {
    $roles[] = $role_row;
}

// Calculate statistics
$total_users = $result->num_rows;
$roles_count = count($roles);

// Count active and inactive users
$active_users_sql = "SELECT COUNT(*) as active_count FROM users WHERE is_active = 1";
$active_result = $conn->query($active_users_sql);
$active_users = $active_result ? $active_result->fetch_assoc()['active_count'] : 0;

$inactive_users_sql = "SELECT COUNT(*) as inactive_count FROM users WHERE is_active = 0";
$inactive_result = $conn->query($inactive_users_sql);
$inactive_users = $inactive_result ? $inactive_result->fetch_assoc()['inactive_count'] : 0;
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen User - Sistem Inventory</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.0.7/css/boxicons.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #0800f9;
            --secondary-color: #111827;
            --success-color: #1cc88a;
            --info-color: #36b9cc;
            --warning-color: #f6c23e;
            --danger-color: #e74a3b;
            --dark-color: #111827;
            --light-color: #f8f9fc;
        }

        body {
            background: #ffffff;
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .main-container {
            background: #ffffff;
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            margin: 20px;
            padding: 30px;
            border: 1px solid #e2e8f0;
        }

        .page-header {
            background: linear-gradient(135deg, #000000 0%, #0800f9 100%);
            color: white;
            padding: 25px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .page-title {
            font-size: 2.5rem;
            font-weight: 700;
            margin: 0;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
        }

        .btn-elegant {
            border-radius: 25px;
            padding: 12px 25px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: all 0.3s ease;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        .btn-elegant:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
        }

        .card-elegant {
            background: white;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            border: none;
            overflow: hidden;
        }

        .card-header-elegant {
            background: linear-gradient(135deg, #f8f9fc, #e3e6f0);
            border-bottom: 2px solid #e3e6f0;
            padding: 20px 30px;
            font-weight: 700;
            color: var(--dark-color);
        }

        .table-elegant {
            margin: 0;
        }

        .table-elegant thead th {
            background: #0800f9;
            color: white;
            border: none;
            padding: 15px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .table-elegant tbody tr {
            transition: all 0.3s ease;
        }

        .table-elegant tbody tr:hover {
            background: linear-gradient(135deg, #f8f9fc, #e3e6f0);
            transform: scale(1.01);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .table-elegant tbody td {
            padding: 15px;
            border: none;
            border-bottom: 1px solid #e3e6f0;
            vertical-align: middle;
        }

        .badge-role {
            background: linear-gradient(135deg, var(--success-color), #17a673);
            color: white;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .btn-action {
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin: 0 3px;
            transition: all 0.3s ease;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.2);
        }

        .btn-action:hover {
            transform: translateY(-2px) scale(1.1);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color), #6f42c1);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 1.2rem;
            overflow: hidden;
        }

        .user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .stats-card {
            background: linear-gradient(135deg, var(--success-color), #17a673);
            color: white;
            padding: 20px;
            border-radius: 15px;
            text-align: center;
            margin-bottom: 20px;
        }

        .stats-number {
            font-size: 2.5rem;
            font-weight: 700;
            margin: 0;
        }

        .stats-label {
            font-size: 1rem;
            opacity: 0.9;
            margin: 0;
        }

        .search-box {
            background: white;
            border-radius: 25px;
            padding: 10px 20px;
            border: 2px solid #e3e6f0;
            transition: all 0.3s ease;
        }

        .search-box:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(8, 0, 249, 0.18);
        }

        .table-responsive {
            overflow-x: auto;
        }

        .table-elegant {
            min-width: 1400px;
        }

        .user-info {
            display: flex;
            flex-direction: column;
        }

        .user-name {
            font-weight: 600;
            color: var(--dark-color);
        }

        .user-fullname {
            font-size: 0.85rem;
            color: var(--secondary-color);
        }

        .contact-link {
            color: var(--primary-color);
            text-decoration: none;
        }

        .contact-link:hover {
            color: #6f42c1;
            text-decoration: underline;
        }

        .keterangan-cell {
            max-width: 200px;
            word-wrap: break-word;
            white-space: normal;
        }

        .keterangan-text {
            font-size: 0.85rem;
            color: var(--secondary-color);
            line-height: 1.4;
        }

        .main-container .btn-primary {
            background-color: #0800f9;
            border-color: #0800f9;
        }

        .main-container .btn-primary:hover,
        .main-container .btn-primary:focus {
            background-color: #0600c8;
            border-color: #0600c8;
        }

        .main-container .btn-outline-primary {
            border-color: #0800f9;
            color: #0800f9;
        }

        .main-container .btn-outline-primary:hover,
        .main-container .btn-outline-primary:focus {
            background-color: #0800f9;
            border-color: #0800f9;
            color: #ffffff;
        }

        .modal-content {
            border-radius: 18px;
            border: 1px solid rgba(0, 0, 0, 0.08);
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.25);
        }

        .modal-header {
            background: linear-gradient(135deg, #000000 0%, #0800f9 100%);
            color: #ffffff;
            border-bottom: none;
            padding: 18px 24px;
        }

        .modal-header .btn-close {
            filter: invert(1) grayscale(1) brightness(1.4);
            opacity: 0.9;
        }

        .modal-body {
            padding: 24px;
            background: linear-gradient(180deg, #ffffff 0%, #f6f8ff 100%);
        }

        .modal-footer {
            border-top: none;
            padding: 16px 24px 22px;
            background: #ffffff;
        }

        .form-label {
            font-weight: 600;
            color: #111827;
        }

        .form-control,
        .form-select {
            border-radius: 14px;
            border: 1px solid #e3e6f0;
            padding: 12px 14px;
            background: #ffffff;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: #0800f9;
            box-shadow: 0 0 0 0.2rem rgba(8, 0, 249, 0.18);
        }

        .input-group-text {
            border-radius: 14px;
            border: 1px solid #e3e6f0;
            background: rgba(8, 0, 249, 0.06);
            color: #0800f9;
            padding: 0 14px;
        }

        .input-group > .form-control,
        .input-group > .form-select {
            border-left: 0;
        }

        .avatar-preview {
            width: 52px;
            height: 52px;
            border-radius: 16px;
            background: rgba(8, 0, 249, 0.08);
            border: 1px solid rgba(8, 0, 249, 0.18);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            flex: 0 0 auto;
        }

        .avatar-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .help-text {
            color: #6b7280;
            font-size: 0.85rem;
        }
    </style>
</head>
<body>
    <?php include '../templates/navbar.php'; ?>

    <div class="main-container animate__animated animate__fadeIn">
        <!-- Page Header -->
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h1 class="page-title">
                        <i class='bx bx-user-circle me-3'></i>
                        Manajemen User
                    </h1>
                    <p class="mb-0 mt-2 opacity-75">Kelola data pengguna sistem inventory</p>
                </div>
                <div class="col-md-6 text-end">
                    <button type="button" class="btn btn-light btn-elegant me-2" data-bs-toggle="modal" data-bs-target="#addUserModal">
                        <i class='bx bx-plus me-2'></i>Tambah User
                    </button>
                    <a href="roles.php" class="btn btn-outline-light btn-elegant">
                        <i class='bx bx-user-check me-2'></i>Kelola Role
                    </a>
                </div>
            </div>
        </div>

        <!-- Success/Error Messages -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class='bx bx-check-circle me-2'></i>
                <?= $_SESSION['success'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class='bx bx-error-circle me-2'></i>
                <?= $_SESSION['error'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stats-card">
                    <h3 class="stats-number"><?= $total_users ?></h3>
                    <p class="stats-label">Total Users</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card" style="background: linear-gradient(135deg, var(--info-color), #2a96a5);">
                    <h3 class="stats-number"><?= $active_users ?></h3>
                    <p class="stats-label">Active Users</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card" style="background: linear-gradient(135deg, var(--warning-color), #e0a800);">
                    <h3 class="stats-number"><?= $roles_count ?></h3>
                    <p class="stats-label">Roles Available</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card" style="background: linear-gradient(135deg, var(--danger-color), #be2617);">
                    <h3 class="stats-number"><?= $inactive_users ?></h3>
                    <p class="stats-label">Inactive Users</p>
                </div>
            </div>
        </div>

        <!-- Search and Filter -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0">
                        <i class='bx bx-search text-muted'></i>
                    </span>
                    <input type="text" class="form-control search-box border-start-0" id="searchInput" placeholder="Cari user...">
                </div>
            </div>
            <div class="col-md-6 text-end">
                <button class="btn btn-outline-primary btn-elegant" onclick="exportData()">
                    <i class='bx bx-download me-2'></i>Export Data
                </button>
            </div>
        </div>

        <!-- User Table -->
        <div class="card-elegant">
            <div class="card-header-elegant">
                <h5 class="mb-0">
                    <i class='bx bx-table me-2'></i>
                    Daftar Pengguna
                </h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-elegant mb-0" id="userTable">
                        <thead>
                            <tr>
                                <th width="50">#</th>
                                <th width="80">Avatar</th>
                                <th>Kode Pegawai</th>
                                <th>Username</th>
                                <th>Nama</th>
                                <th>Email</th>
                                <th>Telepon</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Keterangan</th>
                                <th width="200">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $no = 1;
                            while($row = $result->fetch_assoc()): 
                                $initial = strtoupper(substr($row['nama_lengkap'], 0, 1));
                                $profile_picture_row = trim((string)($row['profile_picture'] ?? ''));
                            ?>
                            <tr class="animate__animated animate__fadeInUp" style="animation-delay: <?= $no * 0.1 ?>s">
                                <td class="text-center fw-bold"><?= $no++ ?></td>
                                <td class="text-center">
                                    <div class="user-avatar">
                                        <?php if ($profile_picture_row !== ''): ?>
                                            <img src="<?= htmlspecialchars($profile_picture_row) ?>" alt="<?= htmlspecialchars($row['nama_lengkap']) ?>">
                                        <?php else: ?>
                                            <?= $initial ?>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if($row['kode_pegawai']): ?>
                                        <span class="badge bg-primary rounded-pill">
                                            <?= htmlspecialchars($row['kode_pegawai']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?= htmlspecialchars($row['username']) ?></strong>
                                </td>
                                <td>
                                    <div class="user-info">
                                        <span class="user-name"><?= htmlspecialchars($row['nama']) ?></span>
                                        <span class="user-fullname"><?= htmlspecialchars($row['nama_lengkap']) ?></span>
                                    </div>
                                </td>
                                <td>
                                    <a href="mailto:<?= htmlspecialchars($row['email']) ?>" class="contact-link">
                                        <?= htmlspecialchars($row['email']) ?>
                                    </a>
                                </td>
                                <td>
                                    <?php if($row['telepon']): ?>
                                        <a href="tel:<?= htmlspecialchars($row['telepon']) ?>" class="contact-link">
                                            <?= htmlspecialchars($row['telepon']) ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if($row['roles']): ?>
                                        <span class="badge-role me-1"><?= htmlspecialchars($row['roles']) ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary"><?= htmlspecialchars($row['role']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if($row['is_active']): ?>
                                        <span class="badge bg-success">Aktif</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Nonaktif</span>
                                    <?php endif; ?>
                                </td>
                                <td class="keterangan-cell">
                                    <?php if($row['keterangan']): ?>
                                        <span class="keterangan-text"><?= htmlspecialchars($row['keterangan']) ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <a href="edit.php?id=<?= $row['id'] ?>" class="btn btn-warning btn-action" title="Edit">
                                        <i class='bx bx-edit'></i>
                                    </a>
                                    <a href="role_access.php?user_id=<?= $row['id'] ?>" class="btn btn-info btn-action" title="Role Access">
                                        <i class='bx bx-key'></i>
                                    </a>
                                    <?php if($row['id'] != $_SESSION['user_id']): ?>
                                    <button onclick="deleteUser(<?= $row['id'] ?>)" class="btn btn-danger btn-action" title="Delete">
                                        <i class='bx bx-trash'></i>
                                    </button>
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

    <!-- Modal Tambah User -->
    <div class="modal fade" id="addUserModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" enctype="multipart/form-data" autocomplete="off">
                    <div class="modal-header">
                        <h5 class="modal-title">Tambah User Baru</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Username <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class='bx bx-user'></i></span>
                                        <input type="text" class="form-control" name="username" required>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Nama <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class='bx bx-id-card'></i></span>
                                        <input type="text" class="form-control" name="nama" required>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class='bx bx-user-voice'></i></span>
                                        <input type="text" class="form-control" name="nama_lengkap" required>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Email <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class='bx bx-envelope'></i></span>
                                        <input type="email" class="form-control" name="email" required>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Kode Pegawai</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class='bx bx-hash'></i></span>
                                        <input type="text" class="form-control" name="kode_pegawai" placeholder="Contoh: TK-001">
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Telepon</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class='bx bx-phone'></i></span>
                                        <input type="text" class="form-control" name="telepon" placeholder="08xxxxxxxxxx">
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Alamat</label>
                                    <textarea class="form-control" name="alamat" rows="3" placeholder="Masukkan alamat lengkap"></textarea>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Keterangan</label>
                                    <textarea class="form-control" name="keterangan" rows="3" placeholder="Masukkan keterangan tambahan tentang user"></textarea>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Password <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class='bx bx-lock-alt'></i></span>
                                        <input type="password" class="form-control" name="password" required>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Foto Profil</label>
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="avatar-preview" id="avatarPreview">
                                            <i class='bx bx-image'></i>
                                        </div>
                                        <div class="flex-grow-1">
                                            <input type="file" class="form-control" name="profile_picture" id="profilePictureInput" accept="image/*">
                                            <div class="help-text mt-1">Opsional. Maks 2MB. Format: JPG/PNG/WEBP/GIF.</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Role <span class="text-danger">*</span></label>
                                    <select class="form-select" name="role_id" required>
                                        <option value="">Pilih Role</option>
                                        <?php foreach ($roles as $role_row): ?>
                                            <option value="<?= (int)$role_row['id'] ?>">
                                                <?= htmlspecialchars($role_row['nama_role']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Status <span class="text-danger">*</span></label>
                                    <select class="form-select" name="status" required>
                                        <option value="active">Aktif</option>
                                        <option value="inactive">Nonaktif</option>
                                    </select>
                                </div>
                            </div>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Search functionality
    document.getElementById('searchInput').addEventListener('keyup', function() {
        const searchTerm = this.value.toLowerCase();
        const table = document.getElementById('userTable');
        const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');

        for (let i = 0; i < rows.length; i++) {
            const row = rows[i];
            const cells = row.getElementsByTagName('td');
            let found = false;

            for (let j = 0; j < cells.length; j++) {
                const cellText = cells[j].textContent.toLowerCase();
                if (cellText.includes(searchTerm)) {
                    found = true;
                    break;
                }
            }

            if (found) {
                row.style.display = '';
                row.classList.add('animate__fadeIn');
            } else {
                row.style.display = 'none';
            }
        }
    });

    function deleteUser(id) {
        Swal.fire({
            title: 'Konfirmasi Hapus',
            text: 'Apakah Anda yakin ingin menghapus user ini?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Ya, Hapus!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'index.php?action=delete&id=' + id;
            }
        });
    }

    function exportData() {
        // Implement export functionality
        alert('Fitur export akan segera tersedia!');
    }

    const profileInput = document.getElementById('profilePictureInput');
    const avatarPreview = document.getElementById('avatarPreview');
    if (profileInput && avatarPreview) {
        profileInput.addEventListener('change', function() {
            const file = this.files && this.files[0] ? this.files[0] : null;
            if (!file) {
                avatarPreview.innerHTML = "<i class='bx bx-image'></i>";
                return;
            }
            const url = URL.createObjectURL(file);
            avatarPreview.innerHTML = "";
            const img = document.createElement('img');
            img.src = url;
            img.onload = function() {
                URL.revokeObjectURL(url);
            };
            avatarPreview.appendChild(img);
        });
    }

    // Add SweetAlert2 for better alerts
    const script = document.createElement('script');
    script.src = 'https://cdn.jsdelivr.net/npm/sweetalert2@11';
    document.head.appendChild(script);
    </script>
</body>
</html>
