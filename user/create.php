<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

// Query untuk mengambil data role
$sql = "SELECT id, nama_role FROM roles ORDER BY nama_role";
$roles = $conn->query($sql);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $nama = $_POST['nama'];
    $email = $_POST['email'];
    $kode_pegawai = $_POST['kode_pegawai'];
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $selected_roles = isset($_POST['roles']) ? $_POST['roles'] : [];

    // Insert user baru dengan kode pegawai dan status
    $sql = "INSERT INTO users (username, password, nama, email, kode_pegawai, is_active) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('sssssi', $username, $password, $nama, $email, $kode_pegawai, $is_active);
    
    if ($stmt->execute()) {
        $user_id = $conn->insert_id;
        
        // Insert role untuk user
        if (!empty($selected_roles)) {
            $sql = "INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)";
            $stmt = $conn->prepare($sql);
            
            foreach ($selected_roles as $role_id) {
                $stmt->bind_param('ii', $user_id, $role_id);
                $stmt->execute();
            }
        }

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
            $mail->addAddress($email, $nama);

            // Content
            $mail->isHTML(true);
            $mail->Subject = 'Selamat Datang di MINVEN - Detail Akun Anda';
            $plain_password = $_POST['password'];
            $mail->Body    = "
                <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e0e0e0; border-radius: 10px;'>
                    <h2 style='color: #4e73df; text-align: center;'>Selamat Datang di MINVEN</h2>
                    <p>Halo <b>$nama</b>,</p>
                    <p>Akun Anda telah berhasil dibuat. Berikut adalah detail login Anda:</p>
                    <div style='background-color: #f8f9fc; padding: 15px; border-radius: 5px; margin: 20px 0;'>
                        <p style='margin: 5px 0;'><b>Username:</b> $username</p>
                        <p style='margin: 5px 0;'><b>Password:</b> $plain_password</p>
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
        
        header("Location: index.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah User - Sistem Inventory</title>
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

        .password-strength {
            height: 5px;
            border-radius: 3px;
            margin-top: 5px;
            transition: all 0.3s ease;
        }

        .strength-weak { background: var(--danger-color); }
        .strength-medium { background: var(--warning-color); }
        .strength-strong { background: var(--success-color); }
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
                        <i class='bx bx-user-plus me-3'></i>
                        Tambah User Baru
                    </h1>
                    <p class="mb-0 mt-2 opacity-75">Buat akun pengguna baru untuk sistem inventory</p>
                </div>
                <div class="col-md-4 text-end">
                    <a href="index.php" class="btn btn-outline-light btn-elegant">
                        <i class='bx bx-arrow-back me-2'></i>Kembali
                    </a>
                </div>
            </div>
        </div>

        <!-- Form Card -->
        <div class="card-elegant">
            <div class="card-header-elegant">
                <h5 class="mb-0">
                    <i class='bx bx-edit me-2'></i>
                    Form Data User
                </h5>
            </div>
            <div class="card-body p-4">
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
                                    <i class='bx bx-id-card me-2'></i>Kode Pegawai
                                </label>
                                <input type="text" class="form-control form-control-elegant" name="kode_pegawai" 
                                       placeholder="Masukkan kode pegawai" required>
                                <div class="form-text">Contoh: EMP001, PGW2024001</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label-elegant">
                                    <i class='bx bx-user me-2'></i>Username
                                </label>
                                <input type="text" class="form-control form-control-elegant" name="username" 
                                       placeholder="Masukkan username" required>
                                <div class="form-text">Username untuk login ke sistem</div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label-elegant">
                                    <i class='bx bx-user-detail me-2'></i>Nama Lengkap
                                </label>
                                <input type="text" class="form-control form-control-elegant" name="nama" 
                                       placeholder="Masukkan nama lengkap" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label-elegant">
                                    <i class='bx bx-envelope me-2'></i>Email
                                </label>
                                <input type="email" class="form-control form-control-elegant" name="email" 
                                       placeholder="Masukkan email" required>
                            </div>
                        </div>
                    </div>

                    <!-- Security Section -->
                    <div class="form-section">
                        <h6 class="section-title">
                            <i class='bx bx-lock-alt'></i>
                            Keamanan Akun
                        </h6>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label-elegant">
                                    <i class='bx bx-lock me-2'></i>Password
                                </label>
                                <input type="password" class="form-control form-control-elegant" name="password" 
                                       id="password" placeholder="Masukkan password" required>
                                <div class="password-strength" id="passwordStrength"></div>
                                <div class="form-text">Minimal 8 karakter dengan kombinasi huruf dan angka</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label-elegant">
                                    <i class='bx bx-lock me-2'></i>Konfirmasi Password
                                </label>
                                <input type="password" class="form-control form-control-elegant" name="confirm_password" 
                                       id="confirmPassword" placeholder="Konfirmasi password" required>
                                <div class="form-text" id="passwordMatch"></div>
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
                                    <?php while($role = $roles->fetch_assoc()): ?>
                                    <div class="col-md-4 mb-2">
                                        <label class="role-checkbox">
                                            <input type="checkbox" name="roles[]" value="<?= $role['id'] ?>">
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
                                    <input class="form-check-input" type="checkbox" name="is_active" id="is_active" checked>
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
                                <i class='bx bx-save me-2'></i>Simpan User
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
    // Password strength checker
    document.getElementById('password').addEventListener('input', function() {
        const password = this.value;
        const strengthBar = document.getElementById('passwordStrength');
        let strength = 0;
        
        if (password.length >= 8) strength++;
        if (password.match(/[a-z]/)) strength++;
        if (password.match(/[A-Z]/)) strength++;
        if (password.match(/[0-9]/)) strength++;
        if (password.match(/[^a-zA-Z0-9]/)) strength++;
        
        strengthBar.className = 'password-strength';
        if (strength < 3) {
            strengthBar.classList.add('strength-weak');
        } else if (strength < 4) {
            strengthBar.classList.add('strength-medium');
        } else {
            strengthBar.classList.add('strength-strong');
        }
    });

    // Password confirmation checker
    document.getElementById('confirmPassword').addEventListener('input', function() {
        const password = document.getElementById('password').value;
        const confirmPassword = this.value;
        const matchText = document.getElementById('passwordMatch');
        
        if (confirmPassword === '') {
            matchText.textContent = '';
            matchText.className = 'form-text';
        } else if (password === confirmPassword) {
            matchText.textContent = 'Password cocok!';
            matchText.className = 'form-text text-success';
        } else {
            matchText.textContent = 'Password tidak cocok!';
            matchText.className = 'form-text text-danger';
        }
    });

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
    </script>
</body>
</html>