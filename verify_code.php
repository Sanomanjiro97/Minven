<?php
session_start();
require_once 'config.php';

$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $verification_code = isset($_POST['verification_code']) ? $_POST['verification_code'] : '';
    $email = isset($_POST['email']) ? $_POST['email'] : '';
    
    // Validasi kode verifikasi
    if (empty($verification_code)) {
        $error = "Kode verifikasi tidak boleh kosong.";
    } else {
        // Cek kode verifikasi di database
        $sql = "SELECT email, token, expiry FROM password_resets 
                WHERE token = ? AND email = ? AND expiry > NOW()";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ss', $verification_code, $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            // Kode verifikasi valid, simpan email di session untuk proses reset password
            $_SESSION['reset_email'] = $email;
            $_SESSION['reset_code'] = $verification_code;
            
            // Redirect ke halaman reset password
            header("Location: reset_password.php");
            exit();
        } else {
            $error = "Kode verifikasi tidak valid atau telah kadaluarsa.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verifikasi Kode - MINVEN</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(to bottom, rgb(0, 21, 246), rgb(0, 0, 0));
            height: 100vh;
            margin: 0;
            font-family: 'Poppins', sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 30px; 
            border-radius: 10px; 
        }

        .verify-container {
            background: #ffffff;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            padding: 40px;
            max-width: 450px;
            width: 100%;
            text-align: center;
        }

        .verify-container h2 {
            color: #333;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .verify-container p {
            color: #666;
            margin-bottom: 30px;
        }

        .form-control {
            border-radius: 10px;
            border: 2px solid #e3e6f0;
            padding: 12px 20px;
            font-size: 1rem;
            transition: all 0.3s ease;
            text-align: center;
            letter-spacing: 5px;
            font-size: 1.5rem;
            font-weight: bold;
        }

        .form-control:focus {
            border-color: #007bff;
            box-shadow: 0 0 0 0.2rem rgba(0,123,255,.25);
        }

        .btn-primary {
            background-color: #007bff;
            border: none;
            border-radius: 10px;
            padding: 12px 30px;
            font-weight: 600;
            width: 100%;
            transition: background-color 0.2s ease;
        }

        .btn-primary:hover {
            background-color: #0056b3;
        }

        .btn-secondary {
            border-radius: 10px;
            padding: 8px 20px;
            font-weight: 500;
            text-decoration: none;
        }

        .logo-img {
            max-width: 80px;
            height: auto;
            margin-bottom: 20px;
        }

        .alert {
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .form-text {
            text-align: center;
            margin-top: 5px;
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
            <img class="minven-logo" src="/minven_pro/asset/LOGO1.png" alt="">
        </div>
    </div>
    <div class="verify-container">
        <img src="asset/LOGO1.png" alt="MINVEN Logo" class="logo-img">
        <h2>Verifikasi Kode</h2>
        <p>Masukkan kode verifikasi 6 digit yang telah dikirim ke email Anda.</p>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($success)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($success) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="mb-3">
                <input type="text" class="form-control" name="verification_code" 
                       placeholder="123456" required autofocus maxlength="6" pattern="[0-9]{6}">
                <div class="form-text">Masukkan 6 digit kode verifikasi.</div>
            </div>
            
            <input type="hidden" name="email" value="<?= htmlspecialchars($_GET['email'] ?? '') ?>">
            
            <button type="submit" class="btn btn-primary">Verifikasi Kode</button>
        </form>
        
        <div class="mt-4">
            <a href="forget_password.php" class="btn btn-secondary btn-sm">Kembali</a>
        </div>
        
        <div class="mt-3">
            <small class="text-muted">&copy; <?php echo date("Y"); ?> MINVEN. All Rights Reserved.</small>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
