<?php
ob_start();
session_start();

require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /minven_pro/index.php');
    exit();
}

$page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="format-detection" content="telephone=no">
    <title>Sistem Inventori Mobile</title>
    <link rel="icon" type="image/png" href="/minven_pro/asset/LOGO1.png">
    <link rel="shortcut icon" type="image/png" href="/minven_pro/asset/LOGO1.png">
    <link rel="apple-touch-icon" href="/minven_pro/asset/LOGO1.png">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #0d6efd;
            --secondary-color: #6c757d;
            --success-color: #198754;
            --danger-color: #dc3545;
            --warning-color: #ffc107;
            --info-color: #0dcaf0;
        }

        body {
            background-color: #f8f9fa;
            -webkit-text-size-adjust: 100%;
        }

        .card-3d {
            border: none;
            border-radius: 10px;
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            transform-style: preserve-3d;
        }

        .card-3d:hover {
            transform: translateY(-5px) rotateX(5deg);
            box-shadow: 0 15px 30px rgba(0,0,0,0.2);
        }

        .table-container {
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            transform: translateZ(0);
        }

        .table thead {
            background: #0008f9;
            color: white;
        }

        .table thead th {
            border: none;
            padding: 15px;
            font-weight: 500;
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
        }

        .table tbody tr:hover {
            transform: translateX(5px);
            box-shadow: 5px 0 15px rgba(0,0,0,0.05);
        }

        .stok-aman {
            background-color: rgba(25, 135, 84, 0.05);
            border-left: 4px solid var(--success-color);
            transition: all 0.3s ease;
        }

        .stok-minimum {
            background-color: rgba(255, 193, 7, 0.05);
            border-left: 4px solid var(--warning-color);
            animation: pulse 2s infinite;
        }

        .stok-habis {
            background-color: rgba(220, 53, 69, 0.05);
            border-left: 4px solid var(--danger-color);
            animation: shake 0.5s ease-in-out infinite;
        }

        @keyframes pulse {
            0% { background-color: rgba(255, 193, 7, 0.05); }
            50% { background-color: rgba(255, 193, 7, 0.15); }
            100% { background-color: rgba(255, 193, 7, 0.05); }
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-3px); }
            75% { transform: translateX(3px); }
        }

        .btn, .form-control, .form-select {
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 10px rgba(0,0,0,0.15);
        }

        @media (hover: none) {
            .card-3d:hover {
                transform: none;
                box-shadow: 0 10px 20px rgba(0,0,0,0.1);
            }

            .table tbody tr:hover {
                transform: none;
                box-shadow: none;
            }

            .btn:hover {
                transform: none;
                box-shadow: none;
            }
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
        }

        .main-content {
            padding: 15px;
        }

        @media (max-width: 768px) {
            .navbar-brand {
                font-size: 0.9rem;
            }
            .nav-link {
                font-size: 0.85rem;
                padding: 0.4rem;
            }
            .sisa-stok-input {
                width: 100%;
                margin: 10px 0;
            }
            td:nth-child(8) {
                min-width: unset;
                white-space: nowrap;
            }
            .table {
                font-size: 0.85rem;
            }
        }

        @media (max-width: 576px) {
            .form-control, .form-select {
                font-size: 0.9rem;
                padding: 8px 12px;
            }
            .btn {
                width: 100%;
                margin-bottom: 10px;
            }
            .main-content {
                padding: 10px 5px;
            }
        }
    </style>
</head>
<body>
<?php include __DIR__ . '/templates/navbar.php'; ?>

<div class="container-fluid main-content">
    <?php
    // Load the appropriate page content
    if ($page === 'dashboard') {
        include 'dashboard_mobile.php';
    } else {
        $page_path = $page . '.php';
        if (file_exists($page_path)) {
            include $page_path;
        } else {
            echo '<div class="alert alert-danger">Halaman tidak ditemukan</div>';
        }
    }
    ?>
</div>

<!-- Bootstrap Bundle with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
