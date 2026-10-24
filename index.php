<?php
session_start();
require_once 'config.php';

$error = null;
$authConn = resolve_user_db_conn();
if (!$authConn) {
    $authConn = $conn;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // Query mengambil data user beserta role-nya berdasarkan username
    $sql = "SELECT u.*, ur.role_id, r.nama_role
            FROM users u 
            JOIN user_roles ur ON u.id = ur.user_id 
            JOIN roles r ON ur.role_id = r.id
            WHERE u.username = ? LIMIT 1";
            
    $stmt = $authConn->prepare($sql);
    if ($stmt === false) {
        die("Error preparing statement: " . $authConn->error);
    }
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            if ($user['is_active'] == 1) {
                session_regenerate_id(true);
                unset(
                    $_SESSION['kasir_user_id'],
                    $_SESSION['kasir_username'],
                    $_SESSION['kasir_nama'],
                    $_SESSION['kasir_role_id'],
                    $_SESSION['kasir_role_name']
                );
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['nama'] = $user['nama'];
                $_SESSION['role_id'] = $user['role_id'];
                $_SESSION['nama_role'] = $user['nama_role'] ?? '';
                $kasirRole = null;
                $kasirAccessStmt = $authConn->prepare("SELECT ur.role_id, kasir_role.nama_role
                    FROM user_roles ur
                    INNER JOIN roles kasir_role ON kasir_role.id = ur.role_id
                    WHERE ur.user_id = ?
                      AND LOWER(TRIM(kasir_role.nama_role)) = 'kasir'
                    LIMIT 1");
                if ($kasirAccessStmt) {
                    $kasirAccessStmt->bind_param('i', $user['id']);
                    $kasirAccessStmt->execute();
                    $kasirAccessResult = $kasirAccessStmt->get_result();
                    $kasirRole = $kasirAccessResult ? $kasirAccessResult->fetch_assoc() : null;
                    $kasirAccessStmt->close();
                }

                if ($kasirRole) {
                    $_SESSION['kasir_user_id'] = (int)$user['id'];
                    $_SESSION['kasir_username'] = (string)$user['username'];
                    $_SESSION['kasir_nama'] = (string)($user['nama'] ?? '');
                    $_SESSION['kasir_role_id'] = (int)$kasirRole['role_id'];
                    $_SESSION['kasir_role_name'] = (string)($kasirRole['nama_role'] ?? 'Kasir');
                    $redirectTarget = url_for('kasir/dashboard.php');
                } else {
                    $redirectTarget = url_for('dashboard.php');
                }
                header('Location: ' . $redirectTarget);
                exit();
            } else {
                $error = "User inaktif harap hubungi administrator!";
            }
        } else {
            $error = "Username atau password salah!";
        }
    } else {
        $error = "Username atau password salah!";
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
            max-width: 900px;
            height: 550px; /* Diubah dari min-height ke height statis agar ukuran box luar mengunci kaku */
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
            justify-content: space-between; /* Membuat konten terdistribusi rata atas-bawah */
            height: 100%;
        }

        .login-form-inner {
            display: flex;
            flex-direction: column;
            justify-content: center;
            flex-grow: 1; /* Mengisi ruang kosong agar form tetap presisi di tengah */
        }

        .login-welcome-section {
            flex: 1;
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: #ffffff;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 20px;
            text-align: center;
            height: 100%;
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

        .form-control {
            border: 1px solid #ced4da;
            border-radius: 8px;
            padding: 10px 15px;
            font-size: 0.95rem;
            margin-bottom: 15px;
            transition: border-color 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
        }

        @supports (-webkit-touch-callout: none) {
            input, .form-control {
                font-size: 16px;
            }
        }
        
        .form-control:focus {
            border-color: #007bff;
            box-shadow: 0 0 0 0.2rem rgba(0,123,255,.25);
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
            margin-top: auto; /* Memastikan footer copyright selalu terdorong paling bawah form section */
            text-align: center;
            font-size: 0.85rem;
            color: #555;
            width: 100%;
            padding-top: 20px;
        }

        @media (max-width: 992px) {
            body {
                padding: max(16px, env(safe-area-inset-top)) 16px max(16px, env(safe-area-inset-bottom));
                height: auto;
                min-height: 100vh;
                min-height: 100svh;
                display: flex;
                align-items: center;
                justify-content: center;
                overflow-y: auto;
                background: linear-gradient(145deg, #eaf2fb 0%, #f7f9fc 58%, #e8eef7 100%);
            }

            .login-split-container {
                flex-direction: column-reverse;
                max-width: 520px;
                margin: 0 auto;
                height: auto;
                background: #ffffff;
                border: 1px solid rgba(23, 49, 84, 0.08);
                border-radius: 22px;
                box-shadow: 0 22px 60px rgba(26, 48, 78, 0.14);
            }

            .login-welcome-section {
                padding: 28px 20px;
                min-height: 190px;
                height: auto;
                background: linear-gradient(140deg, #12345a 0%, #0758a5 100%);
                border-bottom: 0;
            }

            .login-welcome-section .logo-img {
                width: 82px;
                margin-bottom: 12px;
            }

            .login-form-section {
                padding: 32px 34px 22px;
                background: #ffffff;
                color: #26364a;
                height: auto;
            }

            .login-form-section h2 {
                color: #173154;
                margin-bottom: 22px;
            }

            .form-label {
                color: #53657b;
            }

            .form-control {
                background: #ffffff;
                border-color: #d8e0e9;
                color: #26364a;
            }

            .form-control::placeholder {
                color: #8795a7;
            }

            .form-control:focus {
                border-color: #1768b2;
                box-shadow: 0 0 0 0.2rem rgba(23, 104, 178, 0.14);
            }

            .btn-login {
                background: linear-gradient(135deg, #1768b2 0%, #124a80 100%);
                box-shadow: 0 8px 20px rgba(23, 104, 178, 0.2);
            }

            .btn-login:hover {
                background: linear-gradient(135deg, #145d9f 0%, #103f6d 100%);
            }

            .copyright-footer {
                color: #7b8795;
                margin-top: 1.25rem;
            }

            .login-form-section footer {
                color: #7b8795 !important;
                font-size: 0.78rem;
                padding-bottom: 0 !important;
            }
        }

        @media (max-width: 576px) {
            body {
                padding: max(12px, env(safe-area-inset-top)) 12px max(12px, env(safe-area-inset-bottom));
            }

            .login-split-container {
                max-width: 100%;
                margin: 0 auto;
                border-radius: 18px;
            }

            .login-welcome-section h1 {
                font-size: 1.35rem;
            }
            
            .login-welcome-section p {
                font-size: 0.9rem;
            }

            .login-form-section {
                padding: 26px 22px 20px;
            }

            .login-form-section h2 {
                font-size: 1.45rem;
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
            document.addEventListener('click', (e) => {
                if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
                const a = e.target && e.target.closest ? e.target.closest('a') : null;
                if (!a || a.hasAttribute('download') || (a.getAttribute('target') || '').toLowerCase() === '_blank' || a.getAttribute('data-no-loader') !== null) return;
                const href = (a.getAttribute('href') || '').trim();
                if (!href || href === '#' || href.startsWith('#') || href.toLowerCase().startsWith('javascript:')) return;
                let url;
                try { url = new URL(href, window.location.href); } catch { return; }
                if (url.origin !== window.location.origin) return;
                show();
            }, true);
            document.addEventListener('submit', (e) => {
                if (e.defaultPrevented) return;
                show();
            }, true);
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
            <div class="login-form-inner">
                <h2>Log in</h2>
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?= htmlspecialchars($error) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <form method="POST">
                    <div class="mb-3">
                        <label for="username" class="form-label">Username</label>
                        <input type="text" id="username" name="username" class="form-control" autocomplete="username" required autofocus>
                    </div>
                    
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" id="password" name="password" class="form-control" autocomplete="current-password" required>
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-login w-100">Log in</button>
                    <div class="text-center mt-3">
                        <a href="forget_password.php" class="text-decoration-none" style="color: #007bff; font-size: 0.9rem;">
                            Lupa Password?
                        </a>
                    </div>
                </form>
            </div>
            
           <footer class="text-center text-muted py-3">
    &copy; <?= date('Y'); ?> MINVEN <strong>PRO</strong>. All rights reserved.
</footer>
        </div>
        
        <div class="login-welcome-section">
            <img src="<?= htmlspecialchars(url_for('asset/LOGO1.png')) ?>" alt="MINVEN Logo" class="logo-img">
            <h1>MINVEN <span class="minven-brand-pro">PRO</span></h1>
            <p>Minven APPS</p>
        </div> 
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
