<?php
require_once __DIR__ . '/_init.php';

if (kasir_is_logged_in()) {
    header('Location: ' . kasir_url_for('dashboard.php'));
    exit();
}

header('Location: ' . url_for('index.php'));
exit();

$user = kasir_current_user();
if ($user['id'] > 0) {
    kasir_logout();
}

$error = $_SESSION['kasir_error'] ?? null;
unset($_SESSION['kasir_error']);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    if ($username === '' || $password === '') {
        $error = 'Username dan password wajib diisi.';
    } elseif (!$kasirAuthConn instanceof mysqli || $kasirAuthConn->connect_error) {
        $error = 'Koneksi database login belum tersedia.';
    } else {
        $stmt = $kasirAuthConn->prepare("SELECT u.*, ur.role_id, r.nama_role FROM users u INNER JOIN user_roles ur ON ur.user_id = u.id INNER JOIN roles r ON r.id = ur.role_id WHERE u.username = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('s', $username);
            $stmt->execute();
            $result = $stmt->get_result();
            $loginUser = $result ? $result->fetch_assoc() : null;
            $stmt->close();
            if ($loginUser && password_verify($password, (string)$loginUser['password'])) {
                if ((int)$loginUser['is_active'] !== 1) {
                    $error = 'User inaktif. Silakan hubungi administrator.';
                } elseif (!kasir_user_can_login($kasirAuthConn, (int)$loginUser['id'])) {
                    $error = 'Akun ini tidak memiliki akses ke aplikasi kasir.';
                } else {
                    session_regenerate_id(true);
                    $_SESSION['kasir_user_id'] = (int)$loginUser['id'];
                    $_SESSION['kasir_username'] = (string)$loginUser['username'];
                    $_SESSION['kasir_nama'] = (string)($loginUser['nama'] ?? '');
                    $_SESSION['kasir_role_id'] = (int)$loginUser['role_id'];
                    $_SESSION['kasir_role_name'] = (string)($loginUser['nama_role'] ?? 'Kasir');
                    header('Location: ' . kasir_url_for('dashboard.php'));
                    exit();
                }
            } else {
                $error = 'Username atau password salah.';
            }
        } else {
            $error = 'Login gagal diproses. Silakan coba lagi.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Kasir - MINVEN POS</title>
    <link rel="icon" type="image/png" href="<?= htmlspecialchars(url_for('asset/LOGO1.png')) ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { min-height: 100vh; margin: 0; display: grid; place-items: center; padding: 24px; background: radial-gradient(circle at top left, #38bdf8 0, transparent 42%), linear-gradient(135deg, #07152e, #0f766e); }
        .login-card { width: min(100%, 440px); overflow: hidden; border: 0; border-radius: 24px; box-shadow: 0 24px 70px rgba(2, 12, 27, .35); }
        .login-head { padding: 34px; color: #fff; background: linear-gradient(135deg, #0f172a, #0e7490); }
        .brand-icon { width: 54px; height: 54px; display: grid; place-items: center; border-radius: 18px; background: rgba(255,255,255,.15); font-size: 1.5rem; }
        .login-body { padding: 34px; background: #fff; }
        .form-control { padding: 12px 14px; border-radius: 12px; }
        .btn-login { padding: 12px; border: 0; border-radius: 12px; background: #0e7490; }
        .btn-login:hover { background: #155e75; }
    </style>
</head>
<body>
    <main class="login-card">
        <header class="login-head d-flex align-items-center gap-3"><div class="brand-icon"><i class="bi bi-shop"></i></div><div><div class="small text-uppercase opacity-75">MINVEN POS</div><h1 class="h3 mb-0 fw-bold">Login Kasir</h1></div></header>
        <section class="login-body">
            <?php if ($error): ?><div class="alert alert-danger" role="alert"><?= htmlspecialchars($error) ?></div><?php endif; ?>
            <form method="post" autocomplete="off">
                <div class="mb-3"><label class="form-label fw-semibold" for="username">Username</label><input class="form-control" id="username" name="username" type="text" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required autofocus></div>
                <div class="mb-4"><label class="form-label fw-semibold" for="password">Password</label><input class="form-control" id="password" name="password" type="password" required></div>
                <button class="btn btn-login btn-primary w-100" type="submit"><i class="bi bi-box-arrow-in-right me-2"></i>Masuk ke Kasir</button>
            </form>
        </section>
    </main>
</body>
</html>