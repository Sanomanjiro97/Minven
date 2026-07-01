<?php
session_start();
require_once 'config.php';

$error = null;
$authConn = auth_db_conn();
if (!$authConn) {
    $authConn = $conn;
}

// Ambil daftar role dari database
$roles = [];
$sql = "SELECT id, nama_role FROM roles";
$result = $authConn->query($sql);
while ($row = $result->fetch_assoc()) {
    $roles[$row['id']] = $row['nama_role'];
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $role_id = (int)($_POST['role_id'] ?? 0);
    
    // Query yang diperbaiki untuk mengecek user dan role
    $sql = "SELECT u.*, ur.role_id, r.nama_role
            FROM users u 
            JOIN user_roles ur ON u.id = ur.user_id 
            JOIN roles r ON ur.role_id = r.id
            WHERE u.username = ? AND ur.role_id = ?";
    $stmt = $authConn->prepare($sql);
    if ($stmt === false) {
        die("Error preparing statement: " . $authConn->error);
    }
    $stmt->bind_param('si', $username, $role_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            // Cek status user aktif atau tidak
            if ($user['is_active'] == 1) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['nama'] = $user['nama'];
                $_SESSION['role_id'] = $user['role_id'];
                $_SESSION['nama_role'] = $user['nama_role'] ?? '';
                header("Location: dashboard.php");
                exit();
            } else {
                // User tidak aktif
                $error = "User inaktif harap hubungi administrator!";
            }
        } else {
            $error = "Username, password atau role salah!";
        }
    } else {
        $error = "Username, password atau role salah!";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="format-detection" content="telephone=no">
    <title>MINVEN PRO</title>
    <link rel="icon" type="image/png" href="<?= htmlspecialchars(url_for('asset/LOGO1.png')) ?>">
    <link rel="shortcut icon" type="image/png" href="<?= htmlspecialchars(url_for('asset/LOGO1.png')) ?>">
    <link rel="apple-touch-icon" href="<?= htmlspecialchars(url_for('asset/LOGO1.png')) ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">    
    <style>
        body {
            background: linear-gradient(to bottom, rgb(0, 21, 246), rgb(0, 0, 0));
            min-height: 100vh;
            margin: 0;
            font-family: 'Poppins', sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .login-split-container {
            display: flex;
            width: 100%;
            max-width: 900px; /* Lebar total container */
            min-height: 550px; /* Tinggi minimum container */
            background: #ffffff;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .login-form-section {
            flex: 1;
            padding: 50px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .login-welcome-section {
            flex: 1;
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%); /* Gradien biru tua */
            color: #ffffff;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 20px;
            text-align: center;
        }

        .login-welcome-section .logo-img {
            max-width: 200px;
            width: 40%;
            height: auto;
            margin-bottom: 20px;
        }

        .login-welcome-section h1 {
            font-size: 2rem; 
            font-weight: 700;
            margin: 0;
            text-transform: uppercase; 
            letter-spacing: 3px; 
        }

        .login-welcome-section h1 .minven-brand-pro {
            font-size: 0.62em;
            font-weight: 600;
            text-transform: lowercase;
            letter-spacing: 1px;
            opacity: 0.92;
            margin-left: 6px;
        }

        .copyright-footer .minven-brand-pro {
            font-size: 0.82em;
            font-weight: 600;
            text-transform: lowercase;
            letter-spacing: 0.5px;
            opacity: 0.9;
            margin-left: 3px;
        }

        .minven-brand-pro{
            display:inline-block;
            padding:2px 6px;
            border:1px solid #C9A227;
            border-radius:8px;
            background-image:
                linear-gradient(130deg, rgba(255,255,255,0) 35%, rgba(255,255,255,0.55) 50%, rgba(255,255,255,0) 65%),
                linear-gradient(135deg, #B88900 0%, #D4AF37 45%, #F1D593 60%, #B88900 100%);
            background-size: 220% 220%, 100% 100%;
            background-position: -120% -120%, center;
            color:#ffffff !important;
            line-height:1;
            box-shadow: 0 0 0 1px rgba(201,162,39,0.35), 0 6px 14px rgba(0,0,0,0.25);
            animation: proShine 3.5s linear infinite;
        }
        @keyframes proShine{
            0% { background-position: -120% -120%, center; }
            50% { background-position: 120% 120%, center; }
            100% { background-position: 220% 220%, center; }
        }

        .login-welcome-section p {
            font-size: 1rem;
            font-weight: 300;
            margin-top: 5px;
            letter-spacing: 1px;
        }

        .login-form-section h2 {
            font-size: 1.8rem;
            font-weight: 700;
            color: #333;
            margin-bottom: 25px;
            text-align: left;
        }

        .form-label {
            font-size: 0.9rem;
            color: #555;
            margin-bottom: 5px;
        }

        .form-control, .form-select {
            border: 1px solid #ced4da;
            border-radius: 8px;
            padding: 10px 15px;
            font-size: 0.95rem;
            margin-bottom: 15px;
            transition: border-color 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
        }

        @supports (-webkit-touch-callout: none) {
            input, select, textarea, .form-control, .form-select {
                font-size: 16px;
            }
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #007bff;
            box-shadow: 0 0 0 0.2rem rgba(0,123,255,.25);
        }

        .input-group .form-control {
            margin-bottom: 0;
        }
        
        .btn-login {
            background-color: #007bff;
            border: none;
            border-radius: 8px;
            padding: 12px;
            font-weight: 500;
            font-size: 1rem;
            width: 100%;
            margin-top: 10px;
            transition: background-color 0.2s ease;
        }
        
        .btn-login:hover {
            background-color: #0056b3;
        }

        .copyright-footer {
            margin-top: 2rem;
            text-align: center;
            font-size: 0.85rem;
            color: #555;
            width: 100%;
        }

        .copyright-footer a {
            color: #007bff;
            text-decoration: none;
            transition: color 0.3s;
        }

        .copyright-footer a:hover {
            text-decoration: underline;
        }

        /* Responsive adjustments for Tablets and Mobile */
        @media (max-width: 992px) {
            body {
                padding: 20px;
                height: auto;
                min-height: 100vh;
                display: block;
                overflow-y: auto;
            }

            .login-split-container {
                flex-direction: column-reverse; /* Welcome section on top */
                max-width: 600px; /* Wider for tablets */
                margin: 0 auto;
                height: auto;
                min-height: auto;
                background: rgba(10, 15, 26, 0.72);
                border: 1px solid rgba(255, 255, 255, 0.12);
                box-shadow: 0 18px 55px rgba(0, 0, 0, 0.42);
                backdrop-filter: blur(20px);
                -webkit-backdrop-filter: blur(20px);
            }

            .login-welcome-section {
                padding: 40px 20px;
                min-height: 200px;
                background: radial-gradient(circle at 20% 10%, rgba(11, 102, 255, 0.42) 0%, rgba(11, 102, 255, 0.0) 52%),
                            radial-gradient(circle at 80% 0%, rgba(139, 92, 246, 0.34) 0%, rgba(139, 92, 246, 0.0) 58%),
                            linear-gradient(135deg, rgba(15, 23, 42, 0.42) 0%, rgba(10, 15, 26, 0.1) 100%);
                border-bottom: 1px solid rgba(255, 255, 255, 0.12);
            }

            .login-welcome-section .logo-img {
                width: 100px; /* Smaller logo on mobile/tablet */
                max-width: 100%;
            }

            .login-form-section {
                padding: 40px 30px;
                background: transparent;
                color: rgba(255, 255, 255, 0.92);
            }

            .login-form-section h2 {
                color: #ffffff;
            }

            .form-label {
                color: rgba(255, 255, 255, 0.78);
            }

            .form-control, .form-select {
                background: rgba(255, 255, 255, 0.06);
                border-color: rgba(255, 255, 255, 0.14);
                color: rgba(255, 255, 255, 0.92);
            }

            .form-control::placeholder {
                color: rgba(255, 255, 255, 0.55);
            }

            .form-control:focus, .form-select:focus {
                border-color: rgba(11, 102, 255, 0.85);
                box-shadow: 0 0 0 0.2rem rgba(11, 102, 255, 0.25);
            }

            .btn-login {
                background: linear-gradient(135deg, #0b66ff 0%, #8b5cf6 100%);
                box-shadow: 0 12px 28px rgba(11, 102, 255, 0.28);
            }

            .btn-login:hover {
                background: linear-gradient(135deg, #0a5cf0 0%, #7c4cf0 100%);
            }

            .copyright-footer {
                color: rgba(255, 255, 255, 0.72);
            }

            .copyright-footer a {
                color: rgba(255, 255, 255, 0.9);
            }
        }

        /* Specific Mobile adjustments */
        @media (max-width: 576px) {
            body {
                padding: 10px;
            }

            .login-split-container {
                max-width: 100%;
                margin-top: 10px;
            }

            .login-welcome-section h1 {
                font-size: 1.5rem;
            }
            
            .login-welcome-section p {
                font-size: 0.9rem;
            }

            .login-form-section {
                padding: 30px 20px;
            }

            .login-form-section h2 {
                font-size: 1.5rem;
            }
        }
    </style>

    <style id="minven-page-loader-style">
        #minven-page-loader{
            position:fixed;inset:0;z-index:20000;display:flex;align-items:center;justify-content:center;
            background:#004AAD;
            opacity:1;visibility:visible;pointer-events:auto;
            transition:opacity .18s ease,visibility .18s ease
        }
        #minven-page-loader.minven-hidden{opacity:0;visibility:hidden;pointer-events:none}
        #minven-page-loader .minven-inner{position:relative;display:flex;align-items:center;justify-content:center}
        #minven-page-loader .minven-logo{display:block;width:min(70vw,260px);height:auto;object-fit:contain;filter:drop-shadow(0 14px 26px rgba(0,0,0,.35))}
    </style>

    <script>
        (function minvenPageLoaderInit() {
            const LOADER_ID = 'minven-page-loader';

            const show = () => {
                const el = document.getElementById(LOADER_ID);
                if (!el) return;
                el.classList.remove('minven-hidden');
            };

            const hide = () => {
                const el = document.getElementById(LOADER_ID);
                if (!el) return;
                el.classList.add('minven-hidden');
            };

            window.addEventListener('load', () => setTimeout(hide, 120));
            window.addEventListener('beforeunload', show);
            document.addEventListener(
                'click',
                (e) => {
                    if (e.defaultPrevented) return;
                    if (e.button !== 0) return;
                    if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;

                    const a = e.target && e.target.closest ? e.target.closest('a') : null;
                    if (!a) return;
                    if (a.hasAttribute('download')) return;
                    if ((a.getAttribute('target') || '').toLowerCase() === '_blank') return;
                    if (a.getAttribute('data-no-loader') !== null) return;

                    const href = (a.getAttribute('href') || '').trim();
                    if (!href || href === '#' || href.startsWith('#')) return;
                    if (href.toLowerCase().startsWith('javascript:')) return;

                    let url;
                    try {
                        url = new URL(href, window.location.href);
                    } catch {
                        return;
                    }
                    if (url.origin !== window.location.origin) return;

                    show();
                },
                true
            );
            document.addEventListener(
                'submit',
                (e) => {
                    if (e.defaultPrevented) return;
                    show();
                },
                true
            );
        })();
    </script>
</head>
<body>
    <div id="minven-page-loader" aria-hidden="true">
        <div class="minven-inner">
            <img class="minven-logo" src="<?= htmlspecialchars(url_for('asset/LOGO1.png')) ?>" alt="">
        </div>
    </div>
    <div class="login-split-container">
        <div class="login-form-section">
            <h2>Log in</h2>
            <?php if (isset($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="mb-3"> <!-- Menggunakan mb-3 untuk konsistensi spacing Bootstrap -->
                    <label for="username" class="form-label">Username</label>
                    <input type="text" id="username" name="username" class="form-control" autocomplete="username" required autofocus>
                </div>
                
                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" id="password" name="password" class="form-control" autocomplete="current-password" required>
                </div>
    
                <div class="mb-3">
                    <label for="role_id" class="form-label">Role</label>
                    <select id="role_id" name="role_id" class="form-select" required>
                        <option value="">Pilih Role</option>
                        <?php foreach ($roles as $id => $nama): ?>
                            <option value="<?= $id ?>"><?= htmlspecialchars($nama) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <button type="submit" class="btn btn-primary btn-login w-100">Log in</button>
                <div class="text-center mt-3">
                    <a href="forget_password.php" class="text-decoration-none" style="color: #007bff; font-size: 0.9rem;">
                        Lupa Password?
                    </a>
                </div>
            </form>
            
            <!-- Pindahkan copyright-footer ke sini -->
            <div class="copyright-footer">
                <span>© 2025 — MINVEN <span class="minven-brand-pro">PRO</span>® | All Rights Reserved.</span>
            </div>
        </div>
        <div class="login-welcome-section">
            <img src="<?= htmlspecialchars(url_for('asset/LOGO1.png')) ?>" alt="MINVEN Logo" class="logo-img"> <!-- Tambahkan tag img di sini -->
            <h1>MINVEN <span class="minven-brand-pro">PRO</span></h1>
            <p>Mobile Inventory</p>
            <!-- Anda bisa menambahkan gambar atau elemen grafis lain di sini jika diinginkan -->
        </div> 
    </div>

    <!-- Hapus copyright-footer dari sini jika masih ada -->

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
