<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

// Cek apakah ada parameter id
if (!isset($_GET['id'])) {
    header("Location: index.php");
    exit();
}

$user_id = $_GET['id'];

// Ambil data user yang akan diedit
$sql = "SELECT * FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Ambil role user
$role_sql = "SELECT role_id FROM user_roles WHERE user_id = ?";
$role_stmt = $conn->prepare($role_sql);
$role_stmt->bind_param("i", $user_id);
$role_stmt->execute();
$role_result = $role_stmt->get_result();
$user_roles = [];
while ($row = $role_result->fetch_assoc()) {
    $user_roles[] = $row['role_id'];
}

// Proses form jika ada POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama = $_POST['nama'];
    $email = $_POST['email'];
    $kode_pegawai = $_POST['kode_pegawai'];
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $roles = isset($_POST['roles']) ? $_POST['roles'] : [];
    
    try {
        $conn->begin_transaction();
        
        // Update data user dengan kode pegawai dan status
        $update_sql = "UPDATE users SET nama = ?, email = ?, kode_pegawai = ?, is_active = ? WHERE id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("sssii", $nama, $email, $kode_pegawai, $is_active, $user_id);
        $update_stmt->execute();
        
        // Hapus role lama
        $delete_sql = "DELETE FROM user_roles WHERE user_id = ?";
        $delete_stmt = $conn->prepare($delete_sql);
        $delete_stmt->bind_param("i", $user_id);
        $delete_stmt->execute();
        
        // Tambah role baru
        $insert_sql = "INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)";
        $insert_stmt = $conn->prepare($insert_sql);
        foreach ($roles as $role_id) {
            $insert_stmt->bind_param("ii", $user_id, $role_id);
            $insert_stmt->execute();
        }
        
        $conn->commit();
        $_SESSION['success'] = "Data user berhasil diperbarui";
        header("Location: index.php");
        exit();
        
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = "Gagal memperbarui data user: " . $e->getMessage();
    }
}

// Ambil semua role yang tersedia
$all_roles_sql = "SELECT * FROM roles";
$all_roles_result = $conn->query($all_roles_sql);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit User - Sistem Inventory</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4e73df;
            --secondary-color: #858796;
            --success-color: #1cc88a;
            --info-color: #36b9cc;
            --warning-color: #f6c23e;
            --danger-color: #e74a3b;
            --dark-color: #5a5c69;
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
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
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

        .card-elegant {
            background: white;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            border: none;
            overflow: hidden;
        }

        .card-header-elegant {
            background: #0008f9;
            border-bottom: 2px solid #e3e6f0;
            padding: 20px 30px;
            font-weight: 700;
            color: white;
        }

        .form-control-elegant {
            border-radius: 15px;
            border: 2px solid #e3e6f0;
            padding: 15px 20px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: #f8f9fc;
        }

        .form-control-elegant:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(78, 115, 223, 0.25);
            background: white;
            transform: translateY(-2px);
        }

        .form-control-elegant:read-only {
            background: #e9ecef;
            color: #6c757d;
        }

        .form-label-elegant {
            font-weight: 600;
            color: var(--dark-color);
            margin-bottom: 10px;
            font-size: 1rem;
        }

        .btn-elegant {
            border-radius: 25px;
            padding: 12px 30px;
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

        .form-section {
            background: linear-gradient(135deg, #f8f9fc, #e3e6f0);
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 25px;
            border-left: 5px solid var(--primary-color);
        }

        .section-title {
            color: var(--primary-color);
            font-weight: 700;
            font-size: 1.2rem;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
        }

        .section-title i {
            margin-right: 10px;
            font-size: 1.5rem;
        }

        .role-checkbox {
            background: white;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 10px;
            border: 2px solid #e3e6f0;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .role-checkbox:hover {
            border-color: var(--primary-color);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .role-checkbox input[type="checkbox"] {
            margin-right: 10px;
            transform: scale(1.2);
        }

        .user-info-card {
            background: linear-gradient(135deg, var(--info-color), #2a96a5);
            color: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .user-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            font-weight: 700;
            margin: 0 auto 15px;
        }
    </style>
</head>
<body>
    <?php include '../templates/navbar.php'; ?>

    <div class="main-container animate__animated animate__fadeIn">
        <!-- Page Header -->
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="page-title">
                        <i class='bx bx-edit me-3'></i>
                        Edit User
                    </h1>
                    <p class="mb-0 mt-2 opacity-75">Perbarui data pengguna sistem inventory</p>
                </div>
                <div class="col-md-4 text-end">
                    <a href="index.php" class="btn btn-outline-light btn-elegant">
                        <i class='bx bx-arrow-back me-2'></i>Kembali
                    </a>
                </div>
            </div>
        </div>

        <!-- User Info Card -->
        <div class="user-info-card">
            <div class="row align-items-center">
                <div class="col-md-2 text-center">
                    <div class="user-avatar">
                        <?= strtoupper(substr($user['nama'], 0, 1)) ?>
                    </div>
                </div>
                <div class="col-md-10">
                    <h4 class="mb-1"><?= htmlspecialchars($user['nama']) ?></h4>
                    <p class="mb-1 opacity-75">
                        <i class='bx bx-user me-2'></i><?= htmlspecialchars($user['username']) ?>
                    </p>
                    <p class="mb-0 opacity-75">
                        <i class='bx bx-envelope me-2'></i><?= htmlspecialchars($user['email']) ?>
                    </p>
                </div>
            </div>
        </div>

        <!-- Form Card -->
        <div class="card-elegant">
            <div class="card-header-elegant">
                <h5 class="mb-0">
                    <i class='bx bx-edit me-2'></i>
                    Form Edit Data User
                </h5>
            </div>
            <div class="card-body p-4">
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class='bx bx-error-circle me-2'></i>
                        <?= $_SESSION['error'] ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['error']); ?>
                <?php endif; ?>

                <form method="POST" class="needs-validation" novalidate>
                    <!-- Personal Information Section -->
                    <div class="form-section">
                        <h6 class="section-title">
                            <i class='bx bx-user'></i>
                            Informasi Pribadi
                        </h6>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label-elegant">
                                    <i class='bx bx-user me-2'></i>Username
                                </label>
                                <input type="text" class="form-control form-control-elegant" 
                                       value="<?= htmlspecialchars($user['username']) ?>" readonly>
                                <div class="form-text">Username tidak dapat diubah</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label-elegant">
                                    <i class='bx bx-id-card me-2'></i>Kode Pegawai
                                </label>
                                <input type="text" class="form-control form-control-elegant" name="kode_pegawai" 
                                       value="<?= htmlspecialchars($user['kode_pegawai'] ?? '') ?>" 
                                       placeholder="Masukkan kode pegawai">
                                <div class="form-text">Contoh: EMP001, PGW2024001</div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label-elegant">
                                    <i class='bx bx-user-detail me-2'></i>Nama Lengkap
                                </label>
                                <input type="text" class="form-control form-control-elegant" name="nama" 
                                       value="<?= htmlspecialchars($user['nama']) ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label-elegant">
                                    <i class='bx bx-envelope me-2'></i>Email
                                </label>
                                <input type="email" class="form-control form-control-elegant" name="email" 
                                       value="<?= htmlspecialchars($user['email']) ?>" required>
                            </div>
                        </div>
                    </div>

                    <!-- Role Assignment Section -->
                    <div class="form-section">
                        <h6 class="section-title">
                            <i class='bx bx-user-check'></i>
                            Penugasan Role
                        </h6>
                        <div class="row">
                            <div class="col-12">
                                <p class="text-muted mb-3">Pilih role yang akan diberikan kepada user:</p>
                                <div class="row">
                                    <?php while ($role = $all_roles_result->fetch_assoc()): ?>
                                    <div class="col-md-4 mb-2">
                                        <label class="role-checkbox">
                                            <input type="checkbox" name="roles[]" 
                                                   value="<?= $role['id'] ?>" 
                                                   <?= in_array($role['id'], $user_roles) ? 'checked' : '' ?>>
                                            <strong><?= htmlspecialchars($role['nama_role']) ?></strong>
                                        </label>
                                    </div>
                                    <?php endwhile; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Status User Section -->
                    <div class="form-section">
                        <h6 class="section-title">
                            <i class='bx bx-toggle-left'></i>
                            Status User
                        </h6>
                        <div class="row">
                            <div class="col-12">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="is_active" id="is_active" 
                                           <?= ($user['is_active'] == 1) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="is_active">
                                        <strong>User Aktif</strong>
                                    </label>
                                </div>
                                <div class="form-text">Nonaktifkan jika user tidak diizinkan login ke sistem</div>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="row mt-4">
                        <div class="col-12 text-center">
                            <button type="submit" class="btn btn-primary btn-elegant me-3">
                                <i class='bx bx-save me-2'></i>Simpan Perubahan
                            </button>
                            <a href="index.php" class="btn btn-secondary btn-elegant">
                                <i class='bx bx-x me-2'></i>Batal
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Form validation
    (function() {
        'use strict';
        window.addEventListener('load', function() {
            var forms = document.getElementsByClassName('needs-validation');
            var validation = Array.prototype.filter.call(forms, function(form) {
                form.addEventListener('submit', function(event) {
                    if (form.checkValidity() === false) {
                        event.preventDefault();
                        event.stopPropagation();
                    }
                    form.classList.add('was-validated');
                }, false);
            });
        }, false);
    })();

    // Auto-dismiss alerts
    setTimeout(function() {
        var alerts = document.querySelectorAll('.alert');
        alerts.forEach(function(alert) {
            var bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        });
    }, 5000);
    </script>
</body>
</html>