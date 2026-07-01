<?php
session_start();
require_once 'config.php';

$error = null;
$success = null;
$authConn = auth_db_conn();
if (!$authConn) {
    $authConn = $conn;
}

if (!isset($_SESSION['reset_user_id'])) {
    header("Location: forget_password.php");
    exit();
}

$user_id = $_SESSION['reset_user_id'];
$user_name = isset($_SESSION['reset_user_name']) ? $_SESSION['reset_user_name'] : '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $old_password = isset($_POST['old_password']) ? $_POST['old_password'] : '';
    $new_password = isset($_POST['new_password']) ? $_POST['new_password'] : '';
    $confirm_password = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';

    if (empty($old_password) || empty($new_password) || empty($confirm_password)) {
        $error = "Semua field harus diisi.";
    } elseif (strlen($new_password) < 6) {
        $error = "Password baru minimal 6 karakter.";
    } elseif ($new_password !== $confirm_password) {
        $error = "Password baru dan konfirmasi password tidak cocok.";
    } else {
        try {
            $sql = "SELECT password FROM users WHERE id = ?";
            $stmt = $authConn->prepare($sql);
            $stmt->bind_param('i', $user_id);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $user = $result->fetch_assoc();

                if (!password_verify($old_password, $user['password'])) {
                    $error = "Password lama salah.";
                } else {
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

                    $sql = "UPDATE users SET password = ? WHERE id = ?";
                    $stmt = $authConn->prepare($sql);
                    $stmt->bind_param('si', $hashed_password, $user_id);

                    if ($stmt->execute()) {
                        unset($_SESSION['reset_user_id']);
                        unset($_SESSION['reset_user_name']);

                        $success = "Password berhasil diubah. Silakan login dengan password baru Anda.";
                    } else {
                        $error = "Gagal mengubah password. Silakan coba lagi.";
                    }
                }
            } else {
                $error = "User tidak ditemukan.";
            }
        } catch (Exception $e) {
            $error = "Terjadi kesalahan: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="format-detection" content="telephone=no">
    <title>Reset Password - MINVEN</title>
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

        .reset-container {
            display: flex;
            width: 100%;
            max-width: 900px;
            min-height: 550px;
            background: #ffffff;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .reset-form-section {
            flex: 1;
            padding: 50px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .reset-welcome-section {
            flex: 1;
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: #ffffff;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 20px;
            text-align: center;
        }

        .reset-welcome-section .logo-img {
            max-width: 200px;
            width: 40%;
            height: auto;
            margin-bottom: 20px;
        }

        .reset-welcome-section h1 {
            font-size: 2rem;
            font-weight: 700;
            margin: 0;
            text-transform: uppercase;
            letter-spacing: 3px;
        }

        .reset-welcome-section p {
            font-size: 1rem;
            font-weight: 300;
            margin-top: 5px;
            letter-spacing: 1px;
        }

        .minven-brand-pro {
            display: inline-block;
            padding: 2px 6px;
            border: 1px solid #C9A227;
            border-radius: 8px;
            background-image:
                linear-gradient(130deg, rgba(255, 255, 255, 0) 35%, rgba(255, 255, 255, 0.55) 50%, rgba(255, 255, 255, 0) 65%),
                linear-gradient(135deg, #B88900 0%, #D4AF37 45%, #F1D593 60%, #B88900 100%);
            background-size: 220% 220%, 100% 100%;
            background-position: -120% -120%, center;
            color: #ffffff !important;
            line-height: 1;
            box-shadow: 0 0 0 1px rgba(201, 162, 39, 0.35), 0 6px 14px rgba(0, 0, 0, 0.25);
            animation: proShine 3.5s linear infinite;
        }

        @keyframes proShine {
            0% {
                background-position: -120% -120%, center;
            }
            50% {
                background-position: 120% 120%, center;
            }
            100% {
                background-position: 220% 220%, center;
            }
        }

        .reset-form-section h2 {
            font-size: 1.8rem;
            font-weight: 700;
            color: #333;
            margin-bottom: 5px;
            text-align: left;
        }

        .reset-form-section .subtitle {
            font-size: 0.95rem;
            color: #666;
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
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, .25);
        }

        .btn-primary {
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

        .btn-primary:hover {
            background-color: #0056b3;
        }

        .btn-secondary {
            background-color: #6c757d;
            border: none;
            border-radius: 8px;
            padding: 10px 20px;
            font-weight: 500;
            transition: background-color 0.2s ease;
        }

        .btn-secondary:hover {
            background-color: #5a6268;
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

        .success-message {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
        }

        .success-message h4 {
            margin-bottom: 10px;
        }

        .success-message .btn {
            margin-top: 15px;
        }

        @media (max-width: 992px) {
            body {
                padding: 20px;
                height: auto;
                min-height: 100vh;
                display: block;
                overflow-y: auto;
            }

            .reset-container {
                flex-direction: column-reverse;
                max-width: 600px;
                margin: 0 auto;
                height: auto;
                min-height: auto;
            }

            .reset-welcome-section {
                padding: 40px 20px;
                min-height: 200px;
            }

            .reset-welcome-section .logo-img {
                width: 100px;
                max-width: 100%;
            }

            .reset-form-section {
                padding: 40px 30px;
            }
        }

        @media (max-width: 576px) {
            body {
                padding: 10px;
            }

            .reset-container {
                max-width: 100%;
                margin-top: 10px;
            }

            .reset-welcome-section h1 {
                font-size: 1.5rem;
            }

            .reset-welcome-section p {
                font-size: 0.9rem;
            }

            .reset-form-section {
                padding: 30px 20px;
            }

            .reset-form-section h2 {
                font-size: 1.5rem;
            }
        }
    </style>

    <style id="minven-page-loader-style">
        #minven-page-loader {
            position: fixed;
            inset: 0;
            z-index: 20000;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #004AAD;
            opacity: 1;
            visibility: visible;
            pointer-events: auto;
            transition: opacity .18s ease, visibility .18s ease;
        }

        #minven-page-loader.minven-hidden {
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
        }

        #minven-page-loader .minven-inner {
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        #minven-page-loader .minven-logo {
            display: block;
            width: min(70vw, 260px);
            height: auto;
            object-fit: contain;
            filter: drop-shadow(0 14px 26px rgba(0, 0, 0, .35));
        }
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

    <div class="reset-container">
        <div class="reset-form-section">
            <?php if ($success): ?>
                <div class="success-message">
                    <h4>Berhasil!</h4>
                    <p><?= htmlspecialchars($success) ?></p>
                    <a href="index.php" class="btn btn-primary">Login Sekarang</a>
                </div>
            <?php else: ?>
                <h2>Reset Password</h2>
                <p class="subtitle">Halo, <?= htmlspecialchars($user_name); ?>. Buat password baru untuk akun Anda.</p>

                <?php if (isset($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?= htmlspecialchars($error) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <div class="mb-3">
                        <label for="old_password" class="form-label">Password Lama</label>
                        <input type="password" id="old_password" name="old_password" class="form-control" autocomplete="current-password" required autofocus>
                    </div>

                    <div class="mb-3">
                        <label for="new_password" class="form-label">Password Baru</label>
                        <input type="password" id="new_password" name="new_password" class="form-control" autocomplete="new-password" required>
                        <small class="form-text text-muted">Password minimal 6 karakter.</small>
                    </div>

                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Konfirmasi Password Baru</label>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control" autocomplete="new-password" required>
                    </div>

                    <button type="submit" class="btn btn-primary">Ubah Password</button>
                </form>

                <div class="copyright-footer">
                    <a href="forget_password.php">Kembali</a>
                </div>
            <?php endif; ?>
        </div>

        <div class="reset-welcome-section">
            <img src="<?= htmlspecialchars(url_for('asset/LOGO1.png')) ?>" alt="MINVEN Logo" class="logo-img">
            <h1>MINVEN <span class="minven-brand-pro">PRO</span></h1>
            <p>Mobile Inventory</p>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
