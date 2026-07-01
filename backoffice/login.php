<?php
require_once __DIR__ . '/_init.php';

header('Location: ' . url_for('index.php'));
exit();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Backoffice - MINVEN</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link rel="icon" type="image/png" href="<?= htmlspecialchars(url_for('asset/LOGO1.png')) ?>">
    <style>
        body{
            min-height:100vh;
            background: linear-gradient(to bottom, rgb(0, 21, 246), rgb(0, 0, 0));
            display:flex;
            align-items:center;
            justify-content:center;
            padding:24px;
        }
        .card{
            border-radius:14px;
            box-shadow: 0 18px 40px rgba(0,0,0,.25);
            overflow:hidden;
            max-width: 420px;
            width: 100%;
        }
        .card-header{
            background: linear-gradient(135deg, #000000 0%, #0800f9 100%);
            color:#fff;
            border:0;
        }
        .btn-primary{
            border-radius:10px;
            font-weight:700;
            padding:12px 14px;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="card-header py-3 px-4">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-shield-lock"></i>
                <div>
                    <div class="fw-bold">Backoffice</div>
                    <div class="small opacity-75">Laporan Inventory & Keuangan</div>
                </div>
            </div>
        </div>
        <div class="card-body p-4">
            <?php if ($error): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <form method="post" autocomplete="off">
                <div class="mb-3">
                    <label class="form-label">Username</label>
                    <input type="text" name="username" class="form-control" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-box-arrow-in-right me-1"></i> Login
                </button>
            </form>
            <div class="mt-3 text-center">
                <a href="<?= htmlspecialchars(url_for('index.php')) ?>" class="text-decoration-none text-white-50" style="font-size: 0.9rem">Kembali ke aplikasi utama</a>
            </div>
        </div>
    </div>
</body>
</html>
