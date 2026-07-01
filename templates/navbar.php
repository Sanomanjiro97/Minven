<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/access_check.php';

if (!isset($GLOBALS['__minven_auto_reset_stok_done'])) {
    $GLOBALS['__minven_auto_reset_stok_done'] = true;
    if (isset($GLOBALS['conn']) && $GLOBALS['conn'] instanceof mysqli && !$GLOBALS['conn']->connect_error) {
        require_once __DIR__ . '/../setup/reset_stok_harian.php';
        if (function_exists('run_reset_stok_harian')) {
            @run_reset_stok_harian($GLOBALS['conn']);
        }
    }
}

// Get current URI for active menu detection
$current_uri = $_SERVER['REQUEST_URI'] ?? '';

// Define active page variables
$basePathForDetect = BASE_PATH;
$isDashboardPage = (strpos($current_uri, $basePathForDetect . 'dashboard.php') !== false);
$isGudangPage = (strpos($current_uri, $basePathForDetect . 'gudang/') !== false);
$isMasterDataPage = (
    strpos($current_uri, $basePathForDetect . 'barang/') !== false ||
    strpos($current_uri, $basePathForDetect . 'supplier/') !== false ||
    strpos($current_uri, $basePathForDetect . 'satuan/') !== false ||
    strpos($current_uri, $basePathForDetect . 'kategori/') !== false ||
    strpos($current_uri, $basePathForDetect . 'mapping_items/') !== false
);
$isTransaksiPage = (
    strpos($current_uri, $basePathForDetect . 'stok/masuk/') !== false ||
    strpos($current_uri, $basePathForDetect . 'stok/keluar/') !== false ||
    strpos($current_uri, $basePathForDetect . 'stok/transfer/') !== false ||
    strpos($current_uri, $basePathForDetect . 'stok/adjustment_in/') !== false ||
    strpos($current_uri, $basePathForDetect . 'stok/adjustment_out/') !== false
);
$isPembelianPage = (
    strpos($current_uri, $basePathForDetect . 'pembelian/po/') !== false ||
    strpos($current_uri, $basePathForDetect . 'pembelian/direct/') !== false ||
    strpos($current_uri, $basePathForDetect . 'pembelian/surat jalan/') !== false
);
$isVendorRefundPage = (strpos($current_uri, $basePathForDetect . 'vendor_refund/') !== false);
$isManufacturePage = (strpos($current_uri, $basePathForDetect . 'manufacture/') !== false);
$isLaporanPage = (strpos($current_uri, $basePathForDetect . 'laporan/') !== false);
$isUserPage = (strpos($current_uri, $basePathForDetect . 'user/') !== false || strpos($current_uri, $basePathForDetect . 'setup/') !== false);

$mobileGudangHref = '#';
if (hasAccess('gudang_central')) {
    $mobileGudangHref = url_for('gudang/gudang_central.php');
} elseif (hasAccess('gudang_antapani')) {
    $mobileGudangHref = url_for('gudang/gudang_antapani.php');
} elseif (hasAccess('gudang')) {
    $mobileGudangHref = url_for('gudang/master_gudang.php');
}

$mobileTransaksiHref = '#';
if (hasAccess('stok_masuk')) {
    $mobileTransaksiHref = url_for('stok/masuk/index.php');
} elseif (hasAccess('stok_keluar')) {
    $mobileTransaksiHref = url_for('stok/keluar/index.php');
} elseif (hasAccess('stok_transfer')) {
    $mobileTransaksiHref = url_for('stok/transfer/index.php');
} elseif (hasAccess('adjustment_in')) {
    $mobileTransaksiHref = url_for('stok/adjustment_in/index.php');
} elseif (hasAccess('adjustment_out')) {
    $mobileTransaksiHref = url_for('stok/adjustment_out/index.php');
}

$mobileLaporanHref = '#';
if (hasAccess('laporan')) {
    $mobileLaporanHref = url_for('laporan/stok_gudang.php');
}

if (!isset($inventoryMobileOnly)) {
    $inventoryMobileOnly = false;
}

$gudang_central_nama = 'Gudang Central';
$gudang_antapani_nama = 'Gudang Antapani';
if (isset($GLOBALS['conn']) && $GLOBALS['conn']) {
    $stmt_nav_gudang = $GLOBALS['conn']->prepare("SELECT id, nama_gudang FROM gudang WHERE id IN (23, 13)");
    if ($stmt_nav_gudang) {
        $stmt_nav_gudang->execute();
        $res_nav_gudang = $stmt_nav_gudang->get_result();
        while ($row = $res_nav_gudang->fetch_assoc()) {
            $id = (int)($row['id'] ?? 0);
            $nama = trim((string)($row['nama_gudang'] ?? ''));
            if ($nama === '') continue;
            if ($id === 23) $gudang_central_nama = $nama;
            if ($id === 13) $gudang_antapani_nama = $nama;
        }
        $stmt_nav_gudang->close();
    }
}

$sidebar_user_name = trim((string)($_SESSION['nama_lengkap'] ?? $_SESSION['nama'] ?? $_SESSION['username'] ?? 'User'));
$sidebar_user_role = trim((string)($_SESSION['nama_role'] ?? ''));
$sidebar_user_initial = strtoupper(substr($sidebar_user_name !== '' ? $sidebar_user_name : 'U', 0, 1));
$sidebar_user_id = (int)($_SESSION['user_id'] ?? 0);
$sidebar_profile_picture = trim((string)($_SESSION['profile_picture'] ?? ''));
$sidebar_project_root = str_replace('\\', '/', realpath(__DIR__ . '/..') ?: (__DIR__ . '/..'));
$sidebar_upload_user_dir = $sidebar_project_root . '/uploads/user';
$sidebar_main_conn = (($conn ?? null) instanceof mysqli ? $conn : (function_exists('main_db_conn') ? main_db_conn() : ($GLOBALS['conn'] ?? null)));
$sidebar_temp_conn = null;

if (!($sidebar_main_conn instanceof mysqli) && defined('DB_HOST') && defined('DB_USER') && defined('DB_PASS') && defined('DB_NAME')) {
    $sidebar_temp_conn = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($sidebar_temp_conn instanceof mysqli && !$sidebar_temp_conn->connect_error) {
        $sidebar_main_conn = $sidebar_temp_conn;
    }
}

if ($sidebar_main_conn instanceof mysqli && !$sidebar_main_conn->connect_error) {
    if ($sidebar_user_id > 0) {
        $stmt_sidebar_user = $sidebar_main_conn->prepare("SELECT profile_picture FROM users WHERE id = ? LIMIT 1");
        if ($stmt_sidebar_user) {
            $stmt_sidebar_user->bind_param('i', $sidebar_user_id);
            $stmt_sidebar_user->execute();
            $res_sidebar_user = $stmt_sidebar_user->get_result();
            if ($res_sidebar_user instanceof mysqli_result) {
                $sidebar_user_row = $res_sidebar_user->fetch_assoc();
                $db_sidebar_profile_picture = trim((string)($sidebar_user_row['profile_picture'] ?? ''));
                if ($db_sidebar_profile_picture !== '') {
                    $sidebar_profile_picture = $db_sidebar_profile_picture;
                }
            }
            $stmt_sidebar_user->close();
        }
    }
}

if ($sidebar_temp_conn instanceof mysqli) {
    $sidebar_temp_conn->close();
}

if ($sidebar_profile_picture === '' && $sidebar_user_id > 0 && is_dir($sidebar_upload_user_dir)) {
    $sidebar_uploaded_files = glob($sidebar_upload_user_dir . '/' . $sidebar_user_id . '_*');
    if (is_array($sidebar_uploaded_files) && $sidebar_uploaded_files !== []) {
        usort($sidebar_uploaded_files, static function ($a, $b) {
            return filemtime($b) <=> filemtime($a);
        });
        $sidebar_profile_picture = $sidebar_uploaded_files[0];
    }
}

$sidebar_profile_picture_url = '';
if ($sidebar_profile_picture !== '') {
    $sidebar_profile_picture = str_replace('\\', '/', $sidebar_profile_picture);
    $sidebar_profile_picture_file = basename($sidebar_profile_picture);
    $sidebar_profile_picture_fs = $sidebar_upload_user_dir . '/' . $sidebar_profile_picture_file;

    if ($sidebar_profile_picture_file !== '' && is_file($sidebar_profile_picture_fs)) {
        $sidebar_profile_picture_url = url_for('uploads/user/' . rawurlencode($sidebar_profile_picture_file)) . '?v=' . filemtime($sidebar_profile_picture_fs);
    } elseif (
        strpos($sidebar_profile_picture, 'http://') === 0 ||
        strpos($sidebar_profile_picture, 'https://') === 0 ||
        strpos($sidebar_profile_picture, 'data:') === 0
    ) {
        $sidebar_profile_picture_url = $sidebar_profile_picture;
    } else {
        if (preg_match('#^[A-Za-z]:/#', $sidebar_profile_picture) && strpos($sidebar_profile_picture, $sidebar_project_root . '/') === 0) {
            $sidebar_profile_picture = substr($sidebar_profile_picture, strlen($sidebar_project_root) + 1);
        }

        if (strpos($sidebar_profile_picture, BASE_PATH) === 0) {
            $sidebar_profile_picture_url = $sidebar_profile_picture;
        } else {
            $sidebar_profile_picture = preg_replace('#^(\.\./)+#', '', $sidebar_profile_picture);
            $sidebar_profile_picture = ltrim($sidebar_profile_picture, '/');

            if (strpos($sidebar_profile_picture, trim(BASE_PATH, '/')) === 0) {
                $sidebar_profile_picture_url = '/' . $sidebar_profile_picture;
            } else {
                $sidebar_profile_picture_url = url_for($sidebar_profile_picture);
            }
        }
    }
}
?>
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">

<link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600&family=Montserrat:wght@700&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Nunito+Sans:wght@400;600&display=swap" rel="stylesheet">
<link rel="shortcut icon" type="image/png" href="<?= htmlspecialchars(url_for('asset/LOGO1.png')) ?>">
<link rel="apple-touch-icon" href="<?= htmlspecialchars(url_for('asset/LOGO1.png')) ?>">

<style>
        @media (min-width: 1200px) {
            body {
                padding-left: calc(300px + env(safe-area-inset-left));
            }

            body.minven-sidebar-collapsed {
                padding-left: env(safe-area-inset-left);
            }

            body.minven-sidebar-collapsed .minven-sidebar {
                transform: translateX(calc(-100% - 18px));
                opacity: 0;
                pointer-events: none;
            }

            .minven-sidebar-toggle {
                position: fixed;
                top: 26px;
                left: calc(280px - 21px + env(safe-area-inset-left));
                width: 42px;
                height: 42px;
                padding: 0;
                border-radius: 14px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                border: 1px solid rgba(255, 255, 255, 0.14);
                background: rgba(15, 23, 42, 0.70);
                color: #ffffff;
                z-index: 1036;
                backdrop-filter: blur(14px);
                -webkit-backdrop-filter: blur(14px);
                box-shadow: 0 14px 30px rgba(0, 0, 0, 0.22);
            }

            body.minven-sidebar-collapsed .minven-sidebar-toggle {
                left: 16px;
            }

            .minven-sidebar-toggle:hover,
            .minven-sidebar-toggle:focus-visible {
                background: rgba(0, 86, 179, 0.24);
                border-color: rgba(0, 86, 179, 0.35);
                color: #ffffff;
            }

            .minven-sidebar .dropdown,
            .minven-sidebar .dropend {
                width: 100%;
                position: relative;
            }

            .minven-sidebar .dropend .dropdown-menu {
                position: static !important;
                inset: auto !important;
                transform: none !important;
                width: 100%;
                min-width: 0;
                margin: 8px 0 0;
            }


            .minven-navbar,
            .minven-mobile-nav,
            .minven-mobile-sheet {
                display: none !important;
            }
        }

        .minven-sidebar {
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            width: 280px;
            padding: 24px 18px 20px;
            background: linear-gradient(180deg, #0056b3 0%, #004a99 45%, #003f85 100%);
            box-shadow: 12px 0 40px rgba(2, 12, 40, 0.25);
            z-index: 1035;
            display: flex;
            flex-direction: column;
            align-items: stretch;
            gap: 12px;
            border-right: 1px solid rgba(255, 255, 255, 0.10);
            transition: transform 0.18s ease, opacity 0.18s ease;
            overflow-y: auto;
        }

        .minven-sidebar__logo {
            width: 100%;
            min-height: 72px;
            border-radius: 22px;
            background: rgba(255, 255, 255, 0.18);
            border: 1px solid rgba(255, 255, 255, 0.18);
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 14px 16px;
            color: #ffffff;
            text-decoration: none;
            box-shadow: 0 14px 32px rgba(0, 0, 0, 0.18);
            user-select: none;
        }

        .minven-sidebar__brand-mark {
            width: 42px;
            height: 42px;
            border-radius: 16px;
            display: grid;
            place-items: center;
            background: rgba(255, 255, 255, 0.18);
            border: 1px solid rgba(255, 255, 255, 0.24);
            font-size: 20px;
            font-weight: 900;
            letter-spacing: 0.8px;
        }

        .minven-sidebar__brand-text {
            display: flex;
            flex-direction: column;
            min-width: 0;
        }

        .minven-sidebar__brand-title {
            font-size: 16px;
            font-weight: 800;
            letter-spacing: 0.4px;
            line-height: 1.1;
        }

        .minven-sidebar__brand-subtitle {
            margin-top: 3px;
            font-size: 12px;
            font-weight: 600;
            color: rgba(255, 255, 255, 0.82);
        }

        .minven-sidebar__profile-card {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 14px;
            border-radius: 20px;
            background: rgba(255, 255, 255, 0.14);
            border: 1px solid rgba(255, 255, 255, 0.14);
            box-shadow: 0 10px 24px rgba(0, 0, 0, 0.14);
            text-decoration: none;
            transition: background 0.18s ease, transform 0.18s ease, border-color 0.18s ease;
        }

        .minven-sidebar__profile-card:hover,
        .minven-sidebar__profile-card:focus {
            background: rgba(255, 255, 255, 0.18);
            border-color: rgba(255, 255, 255, 0.20);
            transform: translateY(-1px);
        }

        .minven-sidebar__avatar {
            width: 52px;
            height: 52px;
            flex: 0 0 52px;
            border-radius: 16px;
            overflow: hidden;
            display: grid;
            place-items: center;
            background: rgba(255, 255, 255, 0.18);
            border: 1px solid rgba(255, 255, 255, 0.22);
            color: #ffffff;
            font-size: 18px;
            font-weight: 800;
            line-height: 1;
            text-transform: uppercase;
        }

        .minven-sidebar__avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .minven-sidebar__profile-meta {
            min-width: 0;
            display: flex;
            flex-direction: column;
        }

        .minven-sidebar__profile-name {
            font-size: 14px;
            font-weight: 800;
            color: #ffffff;
            line-height: 1.2;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .minven-sidebar__profile-role {
            margin-top: 4px;
            font-size: 12px;
            font-weight: 600;
            color: rgba(255, 255, 255, 0.78);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .minven-sidebar__nav {
            width: 100%;
            display: flex;
            flex-direction: column;
            align-items: stretch;
            gap: 8px;
            margin-top: 2px;
            flex: 1 1 auto;
        }

        .minven-sidebar__item {
            width: 100%;
            min-height: 52px;
            border-radius: 18px;
            display: inline-flex;
            align-items: center;
            justify-content: flex-start;
            gap: 12px;
            padding: 12px 14px;
            text-decoration: none;
            color: rgba(255, 255, 255, 0.88);
            background: transparent;
            border: 1px solid rgba(255, 255, 255, 0.0);
            transition: background 0.18s ease, border-color 0.18s ease, transform 0.18s ease, color 0.18s ease;
        }

        .minven-sidebar__item i {
            width: 24px;
            text-align: center;
            font-size: 20px;
            line-height: 1;
            color: rgba(255, 255, 255, 0.9);
        }

        .minven-sidebar__item-label {
            font-size: 14px;
            font-weight: 700;
            line-height: 1.2;
        }

        .minven-sidebar__item:hover,
        .minven-sidebar__item:focus {
            color: #ffffff;
            background: rgba(255, 255, 255, 0.16);
            border-color: rgba(255, 255, 255, 0.16);
            transform: translateY(-1px);
            box-shadow: none;
        }

        .minven-sidebar__item.active {
            color: #ffffff;
            background: rgba(255, 255, 255, 0.22);
            border-color: rgba(255, 255, 255, 0.18);
            box-shadow: 0 12px 28px rgba(0, 0, 0, 0.16);
        }

        .minven-sidebar__item.active i,
        .minven-sidebar__item.active .minven-sidebar__item-label {
            color: #ffffff;
        }

        .minven-sidebar .dropdown-menu {
            min-width: 280px;
            border-radius: 18px;
            background: rgba(0, 48, 102, 0.96);
            border: 1px solid rgba(255, 255, 255, 0.12);
            box-shadow: 0 18px 40px rgba(0, 0, 0, 0.35);
            padding: 10px;
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
        }

        .minven-sidebar .dropdown-item {
            border-radius: 14px;
            padding: 12px 14px;
            color: rgba(255, 255, 255, 0.9);
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
        }

        .minven-sidebar .dropdown-item i {
            width: 24px;
            text-align: center;
            color: rgba(255, 255, 255, 0.9);
        }

        .minven-sidebar .dropdown-item:hover,
        .minven-sidebar .dropdown-item:focus {
            color: #ffffff;
            background: rgba(255, 255, 255, 0.12);
        }

        .minven-sidebar__bottom {
            width: 100%;
            display: flex;
            flex-direction: column;
            align-items: stretch;
            gap: 8px;
            padding-top: 10px;
        }

        .minven-sidebar__section-label {
            padding: 8px 8px 4px;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0.8px;
            text-transform: uppercase;
            color: rgba(255, 255, 255, 0.62);
        }

        .minven-sidebar__user {
            width: 100%;
            min-height: 56px;
            border-radius: 18px;
            display: inline-flex;
            align-items: center;
            justify-content: flex-start;
            gap: 12px;
            padding: 12px 14px;
            background: rgba(255, 255, 255, 0.16);
            border: 1px solid rgba(255, 255, 255, 0.16);
            color: #ffffff;
            text-decoration: none;
            box-shadow: none;
        }

        .minven-sidebar__user:hover,
        .minven-sidebar__user:focus {
            background: rgba(255, 255, 255, 0.22);
            border-color: rgba(255, 255, 255, 0.18);
            color: #ffffff;
        }

        @media (max-width: 1199.98px) {
            .minven-mobile-sheet.offcanvas-end {
                width: min(88vw, 380px);
                background: linear-gradient(180deg, #0056b3 0%, #004a99 45%, #003f85 100%);
                color: #ffffff;
                border-left: 1px solid rgba(255, 255, 255, 0.12);
            }

            .minven-mobile-sheet .minven-sheet-handle {
                display: none;
            }

            .minven-mobile-sheet .offcanvas-header {
                background: transparent;
                border-bottom: 1px solid rgba(255, 255, 255, 0.14);
            }

            .minven-mobile-sheet .offcanvas-body {
                background: transparent;
            }

            .minven-mobile-sheet .list-group-item {
                background: rgba(255, 255, 255, 0.10);
                border-color: rgba(255, 255, 255, 0.16);
                color: #ffffff !important;
            }

            .minven-mobile-sheet .list-group-item i {
                color: rgba(255, 255, 255, 0.9);
            }
        }

        .minven-navbar {
            background: linear-gradient(135deg, #0a0f1a 0%, #0d1f3c 40%, #0a1628 100%);
            padding: 0;
            box-shadow: 
                0 4px 30px rgba(0, 0, 0, 0.4),
                0 0 0 1px rgba(255, 255, 255, 0.05),
                inset 0 1px 0 rgba(255, 255, 255, 0.08);
            position: relative;
            z-index: 1030;
            display: flex;
            align-items: center;
            min-height: 68px;
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
        }
        
        .minven-navbar::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: 
                radial-gradient(ellipse at 0% 0%, rgba(0, 86, 179, 0.15) 0%, transparent 50%),
                radial-gradient(ellipse at 100% 0%, rgba(0, 64, 133, 0.12) 0%, transparent 50%);
            pointer-events: none;
        }
        
        .minven-navbar::after {
            content: '';
            position: absolute;
            left: 0;
            right: 0;
            bottom: 0;
            height: 2px;
            background: linear-gradient(90deg, 
                transparent 0%, 
                rgba(212, 175, 55, 0.3) 20%, 
                rgba(212, 175, 55, 0.6) 50%, 
                rgba(212, 175, 55, 0.3) 80%, 
                transparent 100%
            );
            box-shadow: 0 0 20px rgba(212, 175, 55, 0.3);
        }

        .minven-navbar .minven-navbar__container {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: nowrap;
            width: 100%;
            max-width: 1280px;
            margin-left: auto;
            margin-right: auto;
            padding-left: max(24px, env(safe-area-inset-left));
            padding-right: max(24px, env(safe-area-inset-right));
            position: relative;
            z-index: 2;
        }

        .minven-navbar .navbar-nav {
            display: flex;
            flex-direction: row;
            align-items: center;
            gap: 2px;
            margin-bottom: 0;
            margin-left: 0 !important;
            position: relative;
            z-index: 2;
        }

        .minven-navbar .navbar-brand {
            color: #ffffff !important;
            font-family: 'Inter', 'Montserrat', system-ui, -apple-system, 'Segoe UI', Roboto, Arial, sans-serif;
            font-weight: 800;
            font-size: 1.25rem !important;
            margin-right: 20px;
            padding-right: 12px;
            flex-shrink: 0;
            white-space: nowrap;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            text-shadow: 0 2px 20px rgba(0, 86, 179, 0.5);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            z-index: 5;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .minven-navbar .navbar-brand .brand-pro {
            display: inline-block;
            margin-left: 6px;
            padding: 2px 6px;
            border: 1px solid #C9A227;
            border-radius: 8px;
            background-image:
                linear-gradient(130deg, rgba(255,255,255,0) 35%, rgba(255,255,255,0.55) 50%, rgba(255,255,255,0) 65%),
                linear-gradient(135deg, #B88900 0%, #D4AF37 45%, #F1D593 60%, #B88900 100%);
            background-size: 220% 220%, 100% 100%;
            background-position: -120% -120%, center;
            color: #ffffff !important;
            font-size: 0.70em;
            font-weight: 800;
            letter-spacing: 0.6px;
            line-height: 1;
            position: relative;
            overflow: hidden;
            box-shadow: 0 0 0 1px rgba(201,162,39,0.35), 0 6px 14px rgba(0,0,0,0.25);
            animation: proShine 3.5s linear infinite;
        }

        @keyframes proShine {
            0% { background-position: -120% -120%, center; }
            50% { background-position: 120% 120%, center; }
            100% { background-position: 220% 220%, center; }
        }

        .minven-navbar .navbar-brand::after {
            content: ' PRO';
            display: inline-block;
            margin-left: 6px;
            padding: 2px 6px;
            border: 1px solid #C9A227;
            border-radius: 8px;
            background-image:
                linear-gradient(130deg, rgba(255,255,255,0) 35%, rgba(255,255,255,0.55) 50%, rgba(255,255,255,0) 65%),
                linear-gradient(135deg, #B88900 0%, #D4AF37 45%, #F1D593 60%, #B88900 100%);
            background-size: 220% 220%, 100% 100%;
            background-position: -120% -120%, center;
            color: #ffffff !important;
            font-size: 0.70em;
            font-weight: 800;
            letter-spacing: 0.7px;
            line-height: 1;
            position: relative;
            overflow: hidden;
            box-shadow: 0 0 0 1px rgba(201,162,39,0.35), 0 6px 14px rgba(0,0,0,0.25);
            animation: proShine 3.5s linear infinite;
        }

        .minven-navbar .navbar-brand.brand-has-pro::after {
            content: '' !important;
            display: none !important;
        }

        @keyframes navLineShine {
            0% { background-position: -120% 0, 0 0; }
            50% { background-position: 120% 0, 0 0; }
            100% { background-position: 220% 0, 0 0; }
        }

        .minven-navbar .navbar-nav .nav-link {
            color: rgba(255, 255, 255, 0.85) !important;
            font-family: 'Inter', 'Nunito Sans', system-ui, -apple-system, 'Segoe UI', Roboto, Arial, sans-serif;
            font-weight: 500;
            font-size: 0.875rem;
            border-radius: 10px;
            margin: 0 2px;
            padding: 10px 14px;
            min-height: 44px;
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            display: flex;
            align-items: center;
            position: relative;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid transparent;
            background: transparent;
            touch-action: manipulation;
        }

        .minven-navbar .navbar-nav .nav-link::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(0, 86, 179, 0.15) 0%, rgba(0, 64, 133, 0.10) 100%);
            border-radius: 10px;
            opacity: 0;
            transform: scale(0.9);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .minven-navbar .navbar-nav .nav-link::after {
            content: '';
            position: absolute;
            left: 50%;
            transform: translateX(-50%);
            bottom: 6px;
            width: 0;
            height: 2px;
            background: linear-gradient(90deg, #0056b3, #004a99, #d4af37);
            border-radius: 999px;
            transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 0 10px rgba(0, 86, 179, 0.35);
        }

        .minven-navbar .navbar-nav .nav-link:hover::before,
        .minven-navbar .navbar-nav .nav-link:focus::before {
            opacity: 1;
            transform: scale(1);
        }

        .minven-navbar .navbar-nav .nav-link:hover::after,
        .minven-navbar .navbar-nav .nav-link:focus::after,
        .minven-navbar .navbar-nav .nav-link.active::after {
            width: calc(100% - 28px);
        }

        .minven-navbar .navbar-nav .nav-link:hover,
        .minven-navbar .navbar-nav .nav-link:focus {
            color: #ffffff !important;
            transform: translateY(-2px);
            border-color: rgba(0, 86, 179, 0.2);
        }

        .minven-navbar .navbar-nav .nav-link.active {
            color: #ffffff !important;
            font-weight: 600;
            border-color: rgba(139, 92, 246, 0.3);
            background: linear-gradient(135deg, rgba(0, 86, 179, 0.10) 0%, rgba(0, 64, 133, 0.08) 100%);
        }

        .minven-navbar .dropdown-menu {
            border: none;
            box-shadow: 
                0 25px 50px -12px rgba(0, 0, 0, 0.5),
                0 0 0 1px rgba(255, 255, 255, 0.05);
            border-radius: 16px;
            padding: 10px 0;
            min-width: 240px;
            max-width: 320px;
            background: rgba(15, 23, 42, 0.98);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            margin-top: 8px;
            animation: dropdownFadeIn 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        @keyframes dropdownFadeIn {
            from {
                opacity: 0;
                transform: translateY(-10px) scale(0.95);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .minven-navbar .dropdown-item {
            color: rgba(255, 255, 255, 0.8) !important;
            padding: 12px 18px;
            font-size: 0.875rem;
            min-height: 44px;
            display: flex;
            align-items: center;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            border-left: 2px solid transparent;
        }

        .minven-navbar .dropdown-item:hover,
        .minven-navbar .dropdown-item:focus {
            background: linear-gradient(90deg, rgba(0, 86, 179, 0.15) 0%, transparent 100%) !important;
            color: #ffffff !important;
            transform: translateX(4px);
            border-left-color: #0056b3;
        }

        .minven-navbar .dropdown-item i {
            width: 20px;
            text-align: center;
            color: rgba(139, 92, 246, 0.8) !important;
            transition: color 0.2s ease;
        }
        
        .minven-navbar .dropdown-item:hover i {
            color: #d4af37 !important;
        }

        .minven-navbar .dropdown-divider {
            border-top: none;
            height: 1px;
            background: linear-gradient(90deg, transparent 0%, rgba(212, 175, 55, 0.3) 50%, transparent 100%);
            margin: 8px 0;
        }

        .minven-navbar .navbar-toggler {
            border: none;
            padding: 10px 14px;
            min-height: 48px;
            min-width: 48px;
            border-radius: 12px;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.05);
        }

        .minven-navbar .navbar-toggler:focus {
            box-shadow: none;
        }

        .minven-navbar .navbar-toggler:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        .minven-navbar.navbar-dark .navbar-toggler {
            border-color: rgba(255, 255, 255, 0.1) !important;
        }

        .minven-navbar.navbar-dark .navbar-toggler-icon {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3e%3cpath stroke='rgba%28255, 255, 255, 0.9%29' stroke-linecap='round' stroke-miterlimit='10' stroke-width='2' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e") !important;
        }

        .minven-navbar .notification-badge {
            position: absolute;
            top: 4px;
            right: 4px;
            transform: translate(30%, -30%);
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
            border-radius: 50%;
            padding: 0.2rem 0.45rem;
            font-size: 0.65rem;
            min-width: 1.3rem;
            text-align: center;
            font-weight: 700;
            box-shadow: 0 2px 8px rgba(239, 68, 68, 0.5);
            animation: badgePulse 2s ease-in-out infinite;
        }
        
        @keyframes badgePulse {
            0%, 100% { transform: translate(30%, -30%) scale(1); }
            50% { transform: translate(30%, -30%) scale(1.1); }
        }

        @media (max-width: 1199.98px) {
            .minven-navbar .minven-navbar__collapse {
                margin-top: 8px;
                padding: 10px;
                background: rgba(255, 255, 255, 0.98);
                border-radius: 12px;
                box-shadow: 0 10px 25px rgba(0, 0, 0, 0.25);
            }

            .minven-navbar .minven-navbar__collapse.show {
                max-height: calc(100vh - 88px);
                overflow-y: auto;
                overscroll-behavior: contain;
            }

            .minven-navbar .navbar-nav {
                flex-direction: column;
                gap: 0;
                align-items: stretch;
            }

            .minven-navbar .navbar-nav .nav-link {
                color: rgba(255, 255, 255, 0.9) !important;
                padding: 14px 16px;
                border-radius: 12px;
                white-space: normal;
                overflow: visible;
                text-overflow: initial;
                transform: none;
                min-height: 52px;
            }

            .minven-navbar .navbar-nav .nav-link:hover,
            .minven-navbar .navbar-nav .nav-link:focus {
                background: linear-gradient(135deg, rgba(0, 86, 179, 0.15) 0%, rgba(0, 64, 133, 0.10) 100%) !important;
                color: #ffffff !important;
                transform: none;
                border-color: rgba(0, 86, 179, 0.3);
            }

            .minven-navbar .navbar-nav .nav-link.active {
                background: linear-gradient(135deg, rgba(0, 86, 179, 0.2) 0%, rgba(0, 64, 133, 0.15) 100%) !important;
                color: #ffffff !important;
            }

            .minven-navbar .dropdown-menu {
                position: static;
                box-shadow: none;
                margin: 4px 0 8px 12px;
                border-radius: 14px;
                min-width: 0;
                max-width: none;
                background: rgba(30, 41, 59, 0.8);
                backdrop-filter: blur(10px);
                -webkit-backdrop-filter: blur(10px);
            }

            .minven-navbar .dropdown-item {
                min-height: 48px;
                padding: 14px 16px;
                border-radius: 10px;
                transform: none;
                color: rgba(255, 255, 255, 0.85) !important;
                border-left: none;
            }

            .minven-navbar .dropdown-item:hover,
            .minven-navbar .dropdown-item:focus {
                transform: none;
                background: rgba(0, 86, 179, 0.15) !important;
                border-left: none;
            }
        }

        @media (hover: none) and (pointer: coarse) {
            .minven-navbar .navbar-nav .nav-link {
                min-height: 52px;
                padding: 14px 18px;
            }
            
            .minven-navbar .dropdown-item {
                min-height: 52px;
                padding: 14px 20px;
            }
            
            .minven-navbar .navbar-toggler {
                min-height: 52px;
                min-width: 52px;
            }
        }

        @media (max-width: 1199.98px) {
            .minven-navbar {
                position: sticky;
                top: 0;
            }

            body {
                padding-bottom: calc(96px + env(safe-area-inset-bottom)) !important;
            }
        }

       <?php if ($inventoryMobileOnly): ?>
        @media (max-width: 1199.98px) {
            .minven-navbar {
                display: none !important;
            }
        }
        <?php endif; ?>

        .minven-mobile-nav {
            display: none;
        }

        @media (max-width: 1199.98px) {
            .minven-mobile-nav {
                display: block;
                position: fixed;
                left: 0;
                right: 0;
                bottom: 0;
                z-index: 1040;
                padding: 12px 16px calc(12px + env(safe-area-inset-bottom));
                background: rgba(15, 23, 42, 0.9);
                backdrop-filter: blur(20px);
                -webkit-backdrop-filter: blur(20px);
                border-top: 1px solid rgba(255, 255, 255, 0.08);
                box-shadow: 0 -10px 40px rgba(0, 0, 0, 0.3);
            }

            .minven-mobile-nav__inner {
                max-width: 600px;
                margin: 0 auto;
                display: flex;
                align-items: stretch;
                justify-content: space-around;
                gap: 8px;
            }

            .minven-mobile-nav__item {
                flex: 1 1 0;
                min-width: 0;
                text-decoration: none;
                color: rgba(255, 255, 255, 0.6);
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                gap: 6px;
                padding: 10px 8px;
                border-radius: 16px;
                border: 1px solid rgba(255, 255, 255, 0.06);
                background: rgba(255, 255, 255, 0.03);
                transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                min-height: 60px;
                touch-action: manipulation;
            }

            .minven-mobile-nav__item:active {
                transform: scale(0.95);
            }

            .minven-navbar .nav-link:focus-visible,
            .minven-navbar .dropdown-item:focus-visible,
            .minven-mobile-nav__item:focus-visible {
                outline: 2px solid rgba(0, 86, 179, 0.85);
                outline-offset: 2px;
            }

            .minven-mobile-nav__icon {
                font-size: 22px;
                line-height: 1;
            }

            .minven-mobile-nav__label {
                font-size: 10px;
                line-height: 1;
                font-weight: 600;
                letter-spacing: 0.5px;
                text-transform: uppercase;
                max-width: 100%;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }

            .minven-mobile-nav__item.active {
                color: #ffffff;
                border-color: rgba(0, 86, 179, 0.4);
                background: linear-gradient(135deg, rgba(0, 86, 179, 0.2) 0%, rgba(0, 64, 133, 0.15) 100%);
                box-shadow: 0 4px 20px rgba(0, 86, 179, 0.3);
            }

            .minven-mobile-nav__item.active .minven-mobile-nav__icon {
                filter: drop-shadow(0 0 8px rgba(0, 86, 179, 0.6));
            }

            .minven-navbar-backdrop {
                position: fixed;
                inset: 0;
                background: rgba(2, 6, 23, 0.58);
                z-index: 1035;
                backdrop-filter: blur(6px);
                -webkit-backdrop-filter: blur(6px);
            }

            body.minven-offcanvas-open {
                overflow: hidden;
            }

            .minven-mobile-sheet .offcanvas-header {
                padding: 16px 20px 12px 20px;
                background: rgba(15, 23, 42, 0.5);
                border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            }

            .minven-mobile-sheet .offcanvas-body {
                padding: 12px 20px 24px 20px;
                background: rgba(15, 23, 42, 0.98);
            }

            .minven-mobile-sheet .minven-sheet-handle {
                width: 48px;
                height: 4px;
                border-radius: 999px;
                background: rgba(255, 255, 255, 0.15);
                margin: 10px auto 0 auto;
            }

            .minven-mobile-sheet .list-group-item {
                border: 1px solid rgba(255, 255, 255, 0.08);
                border-radius: 14px !important;
                margin-bottom: 10px;
                padding: 14px 16px;
                background: rgba(255, 255, 255, 0.03);
                color: rgba(255, 255, 255, 0.9);
                transition: all 0.2s ease;
            }
            
            .minven-mobile-sheet .list-group-item:hover {
                background: rgba(0, 86, 179, 0.1);
                border-color: rgba(0, 86, 179, 0.3);
            }

            .minven-mobile-sheet .list-group-item i {
                width: 24px;
                text-align: center;
                color: #0056b3;
            }
        }

        @media print {
            .minven-mobile-nav {
                display: none !important;
            }
            .minven-sidebar-toggle {
                display: none !important;
            }
            body {
                padding-bottom: 0 !important;
            }
        }
    </style>

<button id="minven-sidebar-toggle" class="btn minven-sidebar-toggle d-none d-xl-inline-flex" type="button" aria-label="Toggle sidebar" aria-controls="minven-sidebar" aria-expanded="true" data-no-loader="true">
    <i class="bi bi-list"></i>
</button>
<script>
(function minvenSidebarToggleInit() {
    const KEY = 'minven_sidebar_collapsed';

    const getCollapsed = () => {
        try {
            return localStorage.getItem(KEY) === '1';
        } catch (e) {
            return false;
        }
    };

    const setCollapsed = (collapsed) => {
        try {
            localStorage.setItem(KEY, collapsed ? '1' : '0');
        } catch (e) {}
    };

    const apply = (collapsed) => {
        if (!document.body) return;
        document.body.classList.toggle('minven-sidebar-collapsed', collapsed);

        const btn = document.getElementById('minven-sidebar-toggle');
        if (!btn) return;

        btn.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
        const icon = btn.querySelector('i');
        if (icon) {
            icon.className = collapsed ? 'bi bi-chevron-right' : 'bi bi-list';
        }
    };

    const init = () => {
        apply(getCollapsed());

        const btn = document.getElementById('minven-sidebar-toggle');
        if (!btn) return;

        btn.addEventListener('click', () => {
            const collapsed = !document.body.classList.contains('minven-sidebar-collapsed');
            setCollapsed(collapsed);
            apply(collapsed);
        });
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, { once: true });
    } else {
        init();
    }
})();
</script>

<aside id="minven-sidebar" class="minven-sidebar d-none d-xl-flex" aria-label="Navigasi utama">
    <a class="minven-sidebar__logo" href="<?= url_for('dashboard.php') ?>" aria-label="Beranda">
        <span class="minven-sidebar__brand-mark">M</span>
        <span class="minven-sidebar__brand-text">
            <span class="minven-sidebar__brand-title">MINVEN</span>
            <span class="minven-sidebar__brand-subtitle">Inventory Dashboard</span>
        </span>
    </a>

    <div class="minven-sidebar__profile-card">
        <div class="minven-sidebar__avatar" aria-hidden="true">
            <?php if ($sidebar_profile_picture_url !== ''): ?>
                <img src="<?= htmlspecialchars($sidebar_profile_picture_url) ?>" alt="<?= htmlspecialchars($sidebar_user_name) ?>">
            <?php else: ?>
                <?= htmlspecialchars($sidebar_user_initial) ?>
            <?php endif; ?>
        </div>
        <div class="minven-sidebar__profile-meta">
            <div class="minven-sidebar__profile-name"><?= htmlspecialchars($sidebar_user_name) ?></div>
            <div class="minven-sidebar__profile-role"><?= htmlspecialchars($sidebar_user_role !== '' ? $sidebar_user_role : ($_SESSION['username'] ?? 'Pengguna')) ?></div>
        </div>
    </div>

    <div class="minven-sidebar__section-label">Menu Utama</div>
    <div class="minven-sidebar__nav">
        <?php if (hasAccess('dashboard')): ?>
            <a class="minven-sidebar__item <?= $isDashboardPage ? 'active' : '' ?>" href="<?= url_for('dashboard.php') ?>" title="Dashboard" aria-label="Dashboard">
                <i class="bi bi-house"></i>
                <span class="minven-sidebar__item-label">Dashboard</span>
            </a>
        <?php endif; ?>

        <?php if (hasAccess('gudang') || hasAccess('gudang_central') || hasAccess('gudang_antapani')): ?>
            <div class="dropdown dropend">
                <button class="minven-sidebar__item <?= $isGudangPage ? 'active' : '' ?>" type="button" data-sidebar-dropdown="true" aria-expanded="false" title="Gudang" aria-label="Gudang" data-no-loader="true">
                    <i class="bi bi-building"></i>
                    <span class="minven-sidebar__item-label">Gudang</span>
                </button>
                <ul class="dropdown-menu">
                    <?php if (hasAccess('gudang')): ?>
                        <li><a class="dropdown-item" href="<?= url_for('gudang/master_gudang.php') ?>"><i class="bi bi-building"></i> Master Gudang</a></li>
                    <?php endif; ?>
                    <?php if (hasAccess('tambah_gudang', 'add')): ?>
                        <li><a class="dropdown-item" href="<?= url_for('gudang/tambah_gudang.php') ?>"><i class="bi bi-plus-square"></i> Tambah Gudang</a></li>
                    <?php endif; ?>
                    <?php if (hasAccess('gudang_central')): ?>
                        <li><a class="dropdown-item" href="<?= url_for('gudang/gudang_central.php') ?>"><i class="bi bi-building"></i> <?= htmlspecialchars($gudang_central_nama) ?></a></li>
                    <?php endif; ?>
                    <?php if (hasAccess('gudang_antapani')): ?>
                        <li><a class="dropdown-item" href="<?= url_for('gudang/gudang_antapani.php') ?>"><i class="bi bi-building"></i> <?= htmlspecialchars($gudang_antapani_nama) ?></a></li>
                    <?php endif; ?>
                    <?php
                    if (hasAccess('gudang')) {
                        $gudang_sql = "SELECT id, kode_gudang, nama_gudang FROM gudang ORDER BY nama_gudang";
                        $gudang_result = $GLOBALS['conn']->query($gudang_sql);
                        if ($gudang_result) {
                            while ($gudang = $gudang_result->fetch_assoc()) {
                                $filename = "operasional_stok_gudang" . $gudang['kode_gudang'] . ".php";
                                $menu_key = 'gudang_' . $gudang['kode_gudang'];
                                if (file_exists(__DIR__ . "/../gudang/$filename") && hasAccess($menu_key, 'view')) {
                                    ?>
                                    <li><a class="dropdown-item" href="<?= url_for('gudang/' . $filename) ?>"><i class="bi bi-stack"></i> Stok <?= htmlspecialchars($gudang['nama_gudang']) ?></a></li>
                                    <?php
                                }
                            }
                        }
                    }
                    ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if (hasAccess('master')): ?>
            <div class="dropdown dropend">
                <button class="minven-sidebar__item <?= $isMasterDataPage ? 'active' : '' ?>" type="button" data-sidebar-dropdown="true" aria-expanded="false" title="Master" aria-label="Master" data-no-loader="true">
                    <i class="bi bi-archive"></i>
                    <span class="minven-sidebar__item-label">Master Data</span>
                </button>
                <ul class="dropdown-menu">
                    <?php if (hasAccess('barang')): ?>
                        <li><a class="dropdown-item" href="<?= url_for('barang/index.php') ?>"><i class="bi bi-box"></i> Master Barang</a></li>
                    <?php endif; ?>
                    <?php if (hasAccess('supplier')): ?>
                        <li><a class="dropdown-item" href="<?= url_for('supplier/index.php') ?>"><i class="bi bi-truck"></i> Master Supplier</a></li>
                    <?php endif; ?>
                    <?php if (hasAccess('satuan')): ?>
                        <li><a class="dropdown-item" href="<?= url_for('satuan/index.php') ?>"><i class="bi bi-rulers"></i> Master Satuan</a></li>
                    <?php endif; ?>
                    <?php if (hasAccess('kategori')): ?>
                        <li><a class="dropdown-item" href="<?= url_for('kategori/index.php') ?>"><i class="bi bi-tags"></i> Master Kategori</a></li>
                    <?php endif; ?>
                    <li><a class="dropdown-item" href="<?= url_for('mapping_items/index.php') ?>"><i class="bi bi-diagram-2"></i> Mapping Items</a></li>
                    <?php if (hasAccess('konversi_masukan')): ?>
                        <li><a class="dropdown-item" href="<?= url_for('stok/konversi_masukan/index.php') ?>"><i class="bi bi-arrow-left-right"></i> Konversi</a></li>
                    <?php endif; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if (hasAccess('transaksi')): ?>
            <div class="dropdown dropend">
                <button class="minven-sidebar__item <?= $isTransaksiPage ? 'active' : '' ?>" type="button" data-sidebar-dropdown="true" aria-expanded="false" title="Transaksi" aria-label="Transaksi" data-no-loader="true">
                    <i class="bi bi-arrow-left-right"></i>
                    <span class="minven-sidebar__item-label">Transaksi</span>
                </button>
                <ul class="dropdown-menu">
                    <?php if (hasAccess('stok_masuk')): ?>
                        <li><a class="dropdown-item" href="<?= url_for('stok/masuk/index.php') ?>"><i class="bi bi-box-arrow-in-down"></i> Stok Masuk</a></li>
                    <?php endif; ?>
                    <?php if (hasAccess('stok_keluar')): ?>
                        <li><a class="dropdown-item" href="<?= url_for('stok/keluar/index.php') ?>"><i class="bi bi-box-arrow-up"></i> Stok Keluar</a></li>
                    <?php endif; ?>
                    <?php if (hasAccess('stok_transfer')): ?>
                        <li><a class="dropdown-item" href="<?= url_for('stok/transfer/index.php') ?>"><i class="bi bi-arrow-repeat"></i> Stok Transfer</a></li>
                    <?php endif; ?>
                    <?php if (hasAccess('adjustment_in')): ?>
                        <li><a class="dropdown-item" href="<?= url_for('stok/adjustment_in/index.php') ?>"><i class="bi bi-arrow-repeat"></i> Stok Adjustment In</a></li>
                    <?php endif; ?>
                    <?php if (hasAccess('adjustment_out')): ?>
                        <li><a class="dropdown-item" href="<?= url_for('stok/adjustment_out/index.php') ?>"><i class="bi bi-arrow-repeat"></i> Stok Adjustment Out</a></li>
                    <?php endif; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if (hasAccess('pembelian')): ?>
            <div class="dropdown dropend">
                <button class="minven-sidebar__item <?= $isPembelianPage ? 'active' : '' ?>" type="button" data-sidebar-dropdown="true" aria-expanded="false" title="Pembelian" aria-label="Pembelian" data-no-loader="true">
                    <i class="bi bi-cart-check"></i>
                    <span class="minven-sidebar__item-label">Pembelian</span>
                </button>
                <ul class="dropdown-menu">
                    <?php if (hasAccess('purchase_order')): ?>
                        <li><a class="dropdown-item" href="<?= url_for('pembelian/po/index.php') ?>"><i class="bi bi-file-earmark-text"></i> Purchase Order</a></li>
                    <?php endif; ?>
                    <li><a class="dropdown-item" href="<?= url_for('pembelian/surat jalan/index.php') ?>"><i class="bi bi-truck"></i> Surat Jalan</a></li>
                    <?php if (hasAccess('pembelian_direct')): ?>
                        <li><a class="dropdown-item" href="<?= url_for('pembelian/direct/index.php') ?>"><i class="bi bi-bag-check"></i> Pembelian Direct</a></li>
                    <?php endif; ?>
                    <?php if (hasAccess('vendor_refund')): ?>
                        <li><a class="dropdown-item" href="<?= url_for('vendor_refund/index.php') ?>"><i class="bi bi-arrow-return-left"></i> Refund Vendor</a></li>
                    <?php endif; ?>
                    <?php if (hasAccess('manufacture')): ?>
                        <li><a class="dropdown-item" href="<?= url_for('manufacture/index.php') ?>"><i class="bi bi-gear-wide-connected"></i> Manufaktur</a></li>
                    <?php endif; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if (hasAccess('laporan')): ?>
            <div class="dropdown dropend">
                <button class="minven-sidebar__item <?= $isLaporanPage ? 'active' : '' ?>" type="button" data-sidebar-dropdown="true" aria-expanded="false" title="Laporan" aria-label="Laporan" data-no-loader="true">
                    <i class="bi bi-file-earmark-bar-graph"></i>
                    <span class="minven-sidebar__item-label">Laporan</span>
                </button>
                <ul class="dropdown-menu">
                    <?php if (hasAccess('laporan')): ?>
                        <li><a class="dropdown-item" href="<?= url_for('laporan/stok_gudang.php') ?>"><i class="bi bi-stack"></i> Laporan Stok</a></li>
                    <?php endif; ?>
                    <?php if (hasAccess('laporan_pembelian')): ?>
                        <li><a class="dropdown-item" href="<?= url_for('laporan/pembelian_direct.php') ?>"><i class="bi bi-cash-stack"></i> Laporan Pembelian</a></li>
                        <li><a class="dropdown-item" href="<?= url_for('laporan/stok_masuk.php') ?>"><i class="bi bi-box-arrow-in-down"></i> Laporan Stok Masuk</a></li>
                        <li><a class="dropdown-item" href="<?= url_for('laporan/stok_keluar.php') ?>"><i class="bi bi-box-arrow-up"></i> Laporan Stok Keluar</a></li>
                    <?php endif; ?>
                    <?php if (hasAccess('laporan_transfer')): ?>
                        <li><a class="dropdown-item" href="<?= url_for('laporan/stok_transfer.php') ?>"><i class="bi bi-arrow-repeat"></i> Laporan Transfer</a></li>
                    <?php endif; ?>
                    <li><a class="dropdown-item" href="<?= url_for('laporan/po.php') ?>"><i class="bi bi-credit-card-2-front"></i> Laporan PO</a></li>
                    <li><a class="dropdown-item" href="<?= url_for('laporan/laporan_perbulan.php') ?>"><i class="bi bi-calendar-month"></i> Laporan Perbulan</a></li>
                    <?php if (hasAccess('laporan_adjustment_in')): ?>
                        <li><a class="dropdown-item" href="<?= url_for('laporan/adjustment_in.php') ?>"><i class="bi bi-adjust"></i> Laporan Adjustment In</a></li>
                    <?php endif; ?>
                    <?php if (hasAccess('laporan_adjustment_out')): ?>
                        <li><a class="dropdown-item" href="<?= url_for('laporan/adjustment_out.php') ?>"><i class="bi bi-adjust"></i> Laporan Adjustment Out</a></li>
                    <?php endif; ?>
                </ul>
            </div>
        <?php endif; ?>
    </div>

    <div class="minven-sidebar__bottom">
        <div class="minven-sidebar__section-label">Sistem</div>
        <?php if (hasAccess('backoffice')): ?>
            <a class="minven-sidebar__item" href="<?= url_for('backoffice/dashboard.php') ?>" title="Backoffice" aria-label="Backoffice">
                <i class="bi bi-building-gear"></i>
                <span class="minven-sidebar__item-label">Backoffice</span>
            </a>
        <?php endif; ?>

        <?php if (hasAccess('setup') || hasAccess('user') || hasAccess('reset_stok') || hasAccess('edit_nama_gudang') || hasAccess('template_po') || hasAccess('barcode') || hasAccess('get_wa') || hasAccess('menu_access') || hasAccess('setup_upload_template')): ?>
            <div class="dropdown dropend">
                <button class="minven-sidebar__item <?= ($isUserPage || strpos($current_uri, '/setup/') !== false) ? 'active' : '' ?>" type="button" data-sidebar-dropdown="true" aria-expanded="false" title="Setup" aria-label="Setup" data-no-loader="true">
                    <i class="bi bi-gear"></i>
                    <span class="minven-sidebar__item-label">Setup</span>
                </button>
                <ul class="dropdown-menu">
                    <?php if (hasAccess('user')): ?>
                        <li><a class="dropdown-item" href="<?= url_for('user/index.php') ?>"><i class="bi bi-people"></i> Daftar User</a></li>
                    <?php endif; ?>
                    <?php if (hasAccess('setup_upload_template')): ?>
                        <li><a class="dropdown-item" href="<?= url_for('setup/upload_template_setup.php') ?>"><i class="bi bi-upload"></i> Upload Template Laporan & Logo</a></li>
                    <?php endif; ?>
                    <?php if (hasAccess('edit_nama_gudang')): ?>
                        <li><a class="dropdown-item" href="<?= url_for('setup/edit_nama_gudang.php') ?>"><i class="bi bi-pencil-square"></i> Edit Nama Gudang</a></li>
                    <?php endif; ?>
                    <?php if (hasAccess('template_po')): ?>
                        <li><a class="dropdown-item" href="<?= url_for('setup/po_template_setup.php') ?>"><i class="bi bi-file-earmark-plus"></i> Template PO</a></li>
                    <?php endif; ?>
                    <?php if (hasAccess('barcode')): ?>
                        <li><a class="dropdown-item" href="<?= url_for('setup/scan_barcode.php') ?>"><i class="bi bi-upc-scan"></i> Setup Barcode</a></li>
                    <?php endif; ?>
                    <?php if (hasAccess('get_wa')): ?>
                        <li><a class="dropdown-item" href="<?= url_for('setup/whatsapp_config.php') ?>"><i class="bi bi-whatsapp"></i> Get WA</a></li>
                    <?php endif; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="dropdown dropend">
            <button class="minven-sidebar__user" type="button" data-sidebar-dropdown="true" aria-expanded="false" title="<?= htmlspecialchars($_SESSION['username'] ?? 'User') ?>" aria-label="User" data-no-loader="true">
                <i class="bi bi-person"></i>
                <span class="minven-sidebar__item-label">Akun Saya</span>
            </button>
            <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="<?= url_for('user/profile.php') ?>"><i class="bi bi-person-circle"></i> Profile</a></li>
                <li><a class="dropdown-item" href="<?= url_for('user/live_chat.php') ?>"><i class="bi bi-chat-dots"></i> Live Chat</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="<?= url_for('logout.php') ?>"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
            </ul>
        </div>
    </div>
</aside>

<nav class="navbar navbar-expand-xl navbar-dark minven-navbar">
    <div class="container minven-navbar__container">
        <!-- Brand/Logo -->
        <a class="navbar-brand d-flex align-items-center" href="<?= url_for('dashboard.php') ?>">
             MINVEN
         </a>

        <!-- Mobile Toggle Button -->
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation" data-no-loader="true">
            <span class="navbar-toggler-icon"></span>
        </button>

        <!-- Navigation Menu -->
        <div class="collapse navbar-collapse minven-navbar__collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                
                <!-- Dashboard -->
                <?php if (hasAccess('dashboard')): ?>
                <li class="nav-item">
                    <a class="nav-link d-flex align-items-center <?= $isDashboardPage ? 'active' : '' ?>" href="<?= url_for('dashboard.php') ?>">
                        <i class="bi bi-grid-fill me-1"></i> Dashboard
                    </a>
                </li>
                <?php endif; ?>

                <!-- Gudang -->
                <?php if (hasAccess('gudang') || hasAccess('gudang_central') || hasAccess('gudang_antapani')): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle d-flex align-items-center <?= $isGudangPage ? 'active' : '' ?>" href="#" data-bs-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false" data-no-loader="true">
                        <i class="bx bx-store fs-5 me-1"></i> Gudang
                    </a>
                    <ul class="dropdown-menu">
                        <?php if (hasAccess('gudang')): ?>
                        <li><a class="dropdown-item" href="<?= url_for('gudang/master_gudang.php') ?>"><i class="bi bi-building me-1"></i> Master Gudang</a></li>
                        <?php endif; ?>
                        <?php if (hasAccess('tambah_gudang', 'add')): ?>
                        <li><a class="dropdown-item" href="<?= url_for('gudang/tambah_gudang.php') ?>"><i class="bi bi-plus-square me-1"></i> Tambah Gudang</a></li>
                        <?php endif; ?>
                        <?php if (hasAccess('gudang_central')): ?>
                        <li><a class="dropdown-item" href="<?= url_for('gudang/gudang_central.php') ?>"><i class="bi bi-building me-1"></i> <?= htmlspecialchars($gudang_central_nama) ?></a></li>
                        <?php endif; ?>
                        <?php if (hasAccess('gudang_antapani')): ?>
                        <li><a class="dropdown-item" href="<?= url_for('gudang/gudang_antapani.php') ?>"><i class="bi bi-building me-1"></i> <?= htmlspecialchars($gudang_antapani_nama) ?></a></li>
                        <?php endif; ?>
                        <?php 
                        if (hasAccess('gudang')) {
                            $gudang_sql = "SELECT id, kode_gudang, nama_gudang FROM gudang ORDER BY nama_gudang";
                            $gudang_result = $GLOBALS['conn']->query($gudang_sql);
                            if ($gudang_result) {
                                while($gudang = $gudang_result->fetch_assoc()) { 
                                    $filename = "operasional_stok_gudang" . $gudang['kode_gudang'] . ".php";
                                    $menu_key = 'gudang_' . $gudang['kode_gudang'];
                                    if(file_exists(__DIR__ . "/../gudang/$filename") && hasAccess($menu_key, 'view')) { 
                        ?>
                            <li><a class="dropdown-item" href="<?= url_for('gudang/' . $filename) ?>"><i class="bi bi-stack me-1"></i> Stok <?= htmlspecialchars($gudang['nama_gudang']) ?></a></li>
                        <?php 
                                    } 
                                }
                            }
                        }
                        ?>
                    </ul>
                </li>
                <?php endif; ?>

                <!-- Master Data -->
                <?php if (hasAccess('master')): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle d-flex align-items-center <?= $isMasterDataPage ? 'active' : '' ?>" href="#" data-bs-toggle="dropdown" data-no-loader="true">
                        <i class="bi bi-archive me-1"></i> Master
                    </a>
                    <ul class="dropdown-menu">
                        <?php if (hasAccess('barang')): ?>
                            <li><a class="dropdown-item" href="<?= url_for('barang/index.php') ?>"><i class="bi bi-box me-1"></i> Master Barang</a></li>
                        <?php endif; ?>
                        <?php if (hasAccess('supplier')): ?>
                            <li><a class="dropdown-item" href="<?= url_for('supplier/index.php') ?>"><i class="bi bi-truck me-1"></i> Master Supplier</a></li>
                        <?php endif; ?>
                        <?php if (hasAccess('satuan')): ?>
                            <li><a class="dropdown-item" href="<?= url_for('satuan/index.php') ?>"><i class="bi bi-rulers me-1"></i> Master Satuan</a></li>
                        <?php endif; ?>
                        <?php if (hasAccess('kategori')): ?>
                            <li><a class="dropdown-item" href="<?= url_for('kategori/index.php') ?>"><i class="bi bi-tags me-1"></i> Master Kategori</a></li>
                        <?php endif; ?>
                        <li><a class="dropdown-item" href="<?= url_for('mapping_items/index.php') ?>"><i class="bi bi-diagram-2 me-1"></i> Mapping Items</a></li>
                        <?php if (hasAccess('konversi_masukan')): ?>
                            <li><a class="dropdown-item" href="<?= url_for('stok/konversi_masukan/index.php') ?>"><i class="bi bi-arrow-left-right me-1"></i> Konversi</a></li>
                        <?php endif; ?>
                    </ul>
                </li>
                <?php endif; ?>

                <!-- Transaksi -->
                <?php if (hasAccess('transaksi')): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle d-flex align-items-center <?= $isTransaksiPage ? 'active' : '' ?>" href="#" data-bs-toggle="dropdown" data-no-loader="true">
                        <i class="bi bi-arrow-left-right me-1"></i> Transaksi
                    </a>
                    <ul class="dropdown-menu">
                        <?php if (hasAccess('stok_masuk')): ?>
                            <li><a class="dropdown-item" href="<?= url_for('stok/masuk/index.php') ?>"><i class="bi bi-box-arrow-in-down me-1"></i> Stok Masuk</a></li>
                        <?php endif; ?>
                        <?php if (hasAccess('stok_keluar')): ?>
                            <li><a class="dropdown-item" href="<?= url_for('stok/keluar/index.php') ?>"><i class="bi bi-box-arrow-up me-1"></i> Stok Keluar</a></li>
                        <?php endif; ?>
                        <?php if (hasAccess('stok_transfer')): ?>
                            <li><a class="dropdown-item" href="<?= url_for('stok/transfer/index.php') ?>"><i class="bi bi-arrow-repeat me-1"></i> Stok Transfer</a></li>
                        <?php endif; ?>
                        <?php if (hasAccess('adjustment_in')): ?>
                            <li><a class="dropdown-item" href="<?= url_for('stok/adjustment_in/index.php') ?>"><i class="bi bi-arrow-repeat me-1"></i> Stok Adjustment In</a></li>
                        <?php endif; ?>
                        <?php if (hasAccess('adjustment_out')): ?>
                            <li><a class="dropdown-item" href="<?= url_for('stok/adjustment_out/index.php') ?>"><i class="bi bi-arrow-repeat me-1"></i> Stok Adjustment Out</a></li>
                        <?php endif; ?>
                    </ul>
                </li>
                <?php endif; ?>

                <!-- Pembelian -->
                <?php if (hasAccess('pembelian')): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle d-flex align-items-center <?= $isPembelianPage ? 'active' : '' ?>" href="#" data-bs-toggle="dropdown" data-no-loader="true">
                        <i class="bi bi-cart-check me-1"></i> Pembelian
                    </a>
                    <ul class="dropdown-menu">
                        <?php if (hasAccess('purchase_order')): ?>
                            <li><a class="dropdown-item" href="<?= url_for('pembelian/po/index.php') ?>"><i class="bi bi-file-earmark-text me-1"></i> Purchase Order</a></li>
                        <?php endif; ?>
                        <li><a class="dropdown-item" href="<?= url_for('pembelian/surat jalan/index.php') ?>"><i class="bi bi-truck me-1"></i> Surat Jalan</a></li>
                        <?php if (hasAccess('pembelian_direct')): ?>
                            <li><a class="dropdown-item" href="<?= url_for('pembelian/direct/index.php') ?>"><i class="bi bi-bag-check me-1"></i> Pembelian Direct</a></li>
                        <?php endif; ?>
                        <?php if (hasAccess('vendor_refund')): ?>
                            <li><a class="dropdown-item" href="<?= url_for('vendor_refund/index.php') ?>"><i class="bi bi-arrow-return-left me-1"></i> Refund Vendor</a></li>
                        <?php endif; ?>
                        <?php if (hasAccess('manufacture')): ?>
                            <li><a class="dropdown-item" href="<?= url_for('manufacture/index.php') ?>"><i class="bi bi-gear-wide-connected me-1"></i> Manufaktur</a></li>
                        <?php endif; ?>
                    </ul>
                </li>
                <?php endif; ?>

                <?php if (hasAccess('vendor_refund') && !hasAccess('pembelian')): ?>
                <li class="nav-item">
                    <a class="nav-link d-flex align-items-center <?= $isVendorRefundPage ? 'active' : '' ?>" href="<?= url_for('vendor_refund/index.php') ?>">
                        <i class="bi bi-arrow-return-left me-1"></i> Refund Vendor
                    </a>
                </li>
                <?php endif; ?>

                <?php if (hasAccess('manufacture') && !hasAccess('pembelian')): ?>
                <li class="nav-item">
                    <a class="nav-link d-flex align-items-center <?= $isManufacturePage ? 'active' : '' ?>" href="<?= url_for('manufacture/index.php') ?>">
                        <i class="bi bi-gear-wide-connected me-1"></i> Manufaktur
                    </a>
                </li>
                <?php endif; ?>
            <!---
                
                <?php if (hasAccess('order_management')): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" data-bs-toggle="dropdown" data-no-loader="true">
                        <i class="bi bi-cart me-1"></i> Order
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="<?= url_for('order/index.php') ?>"><i class="bi bi-list-ul me-1"></i> Daftar Order</a></li>
                        <li><a class="dropdown-item" href="<?= url_for('order/create.php') ?>"><i class="bi bi-plus-circle me-1"></i> Buat Order</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="<?= url_for('order/items/index.php') ?>"><i class="bi bi-box me-1"></i> Kelola Items</a></li>
                        <li><a class="dropdown-item" href="<?= url_for('order/categories/index.php') ?>"><i class="bi bi-tags me-1"></i> Kategori Items</a></li>
                        <li><a class="dropdown-item" href="<?= url_for('order/tables/index.php') ?>"><i class="bi bi-table me-1"></i> Manajemen Tabel</a></li>
                    </ul>
                </li>
                <?php endif; ?>
                -->
                <!-- Laporan -->
                <?php if (hasAccess('laporan')): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle d-flex align-items-center <?= $isLaporanPage ? 'active' : '' ?>" href="#" data-bs-toggle="dropdown" data-no-loader="true">
                        <i class="bi bi-file-bar-graph me-1"></i> Laporan
                    </a>
                    <ul class="dropdown-menu">
                        <?php if (hasAccess('laporan')): ?>
                            <li><a class="dropdown-item" href="<?= url_for('laporan/stok_gudang.php') ?>"><i class="bi bi-stack me-1"></i> Laporan Stok</a></li>
                        <?php endif; ?>
                        <?php if (hasAccess('laporan_pembelian')): ?>
                            <li><a class="dropdown-item" href="<?= url_for('laporan/pembelian_direct.php') ?>"><i class="bi bi-cash-stack me-1"></i> Laporan Pembelian</a></li>
                            <li><a class="dropdown-item" href="<?= url_for('laporan/stok_masuk.php') ?>"><i class="bi bi-box-arrow-in-down me-1"></i> Laporan Stok Masuk</a></li>
                            <li><a class="dropdown-item" href="<?= url_for('laporan/stok_keluar.php') ?>"><i class="bi bi-box-arrow-up me-1"></i> Laporan Stok Keluar</a></li>
                        <?php endif; ?>
                        <?php if (hasAccess('laporan_transfer')): ?>
                            <li><a class="dropdown-item" href="<?= url_for('laporan/stok_transfer.php') ?>"><i class="bi bi-arrow-repeat me-1"></i> Laporan Transfer</a></li>
                        <?php endif; ?>
                        <li><a class="dropdown-item" href="<?= url_for('laporan/po.php') ?>"><i class="bi bi-credit-card-2-front me-1"></i> Laporan PO</a></li>
                        <li><a class="dropdown-item" href="<?= url_for('laporan/laporan_perbulan.php') ?>"><i class="bi bi-calendar-month me-1"></i> Laporan Perbulan</a></li>
                        <?php if (hasAccess('laporan_adjustment_in')): ?>
                            <li><a class="dropdown-item" href="<?= url_for('laporan/adjustment_in.php') ?>"><i class="bi bi-adjust me-1"></i> Laporan Adjustment In</a></li>
                        <?php endif; ?>
                        <?php if (hasAccess('laporan_adjustment_out')): ?>
                            <li><a class="dropdown-item" href="<?= url_for('laporan/adjustment_out.php') ?>"><i class="bi bi-adjust me-1"></i> Laporan Adjustment Out</a></li>
                        <?php endif; ?>
                    </ul>
                </li>
                <?php endif; ?>

                <!-- Backoffice -->
                <?php if (hasAccess('backoffice')): ?>
                <li class="nav-item">
                    <a class="nav-link d-flex align-items-center" href="<?= url_for('backoffice/dashboard.php') ?>">
                        <i class="bi bi-building-gear me-1"></i> Backoffice
                    </a>
                </li>
                <?php endif; ?>

                <!-- Setup Management -->
                <?php if (hasAccess('setup') || hasAccess('user') || hasAccess('reset_stok') || hasAccess('edit_nama_gudang') || hasAccess('template_po') || hasAccess('barcode') || hasAccess('get_wa') || hasAccess('menu_access') || hasAccess('setup_upload_template')): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle d-flex align-items-center <?= ($isUserPage || strpos($current_uri, '/setup/') !== false) ? 'active' : '' ?>" href="#" data-bs-toggle="dropdown" data-no-loader="true">
                        <i class="bi bi-gear me-1"></i> Setup
                    </a>
                    <ul class="dropdown-menu">
                        <?php if (hasAccess('user')): ?>
                            <li><a class="dropdown-item" href="<?= url_for('user/index.php') ?>"><i class="bi bi-people me-1"></i> Daftar User</a></li>
                        <?php endif; ?>
                        <?php if (hasAccess('setup_upload_template')): ?>
                            <li><a class="dropdown-item" href="<?= url_for('setup/upload_template_setup.php') ?>"><i class="bi bi-upload me-1"></i> Upload Template Laporan & Logo</a></li>
                        <?php endif; ?>
                        <?php if (hasAccess('edit_nama_gudang')): ?>
                            <li><a class="dropdown-item" href="<?= url_for('setup/edit_nama_gudang.php') ?>"><i class="bi bi-pencil-square me-1"></i> Edit Nama Gudang</a></li>
                        <?php endif; ?>
                        <?php if (hasAccess('template_po')): ?>
                            <li><a class="dropdown-item" href="<?= url_for('setup/po_template_setup.php') ?>"><i class="bi bi-file-earmark-plus me-1"></i> Template PO</a></li>
                        <?php endif; ?>
                        <?php if (hasAccess('barcode')): ?>
                            <li><a class="dropdown-item" href="<?= url_for('setup/scan_barcode.php') ?>"><i class="bi bi-upc-scan me-1"></i> Setup Barcode</a></li>
                        <?php endif; ?>
                        <?php if (hasAccess('get_wa')): ?>
                            <li><a class="dropdown-item" href="<?= url_for('setup/whatsapp_config.php') ?>"><i class="bi bi-whatsapp me-1"></i> Get WA</a></li>
                        <?php endif; ?>
                    </ul>
                </li>
                <?php endif; ?>

                <!-- Absensi -->
                <?php if (isset($_SESSION['user_id'])): 
                // Cek apakah user sudah check-in hari ini
                $today = date('Y-m-d');
                $is_checked_in = false;
                $absensi_table_exists = false;
                if (isset($GLOBALS['conn']) && $GLOBALS['conn'] instanceof mysqli) {
                    $table_check = $GLOBALS['conn']->query("SHOW TABLES LIKE 'absensi'");
                    $absensi_table_exists = ($table_check && $table_check->num_rows > 0);
                }
                if ($absensi_table_exists) {
                    try {
                        $absensi_check_sql = "SELECT * FROM absensi WHERE user_id = ? AND tanggal = ? AND jam_keluar IS NULL";
                        $absensi_check_stmt = $GLOBALS['conn']->prepare($absensi_check_sql);
                        if ($absensi_check_stmt) {
                            $absensi_check_stmt->bind_param("is", $_SESSION['user_id'], $today);
                            $absensi_check_stmt->execute();
                            $absensi_check_result = $absensi_check_stmt->get_result();
                            $is_checked_in = ($absensi_check_result && $absensi_check_result->num_rows > 0);
                        }
                    } catch (mysqli_sql_exception $e) {
                        $is_checked_in = false;
                    }
                }
                ?>
                <li class="nav-item">
                    <a class="nav-link d-flex align-items-center position-relative" href="/minven_absensi/login.php">
                        <i class="bi bi-person-badge me-1"></i> Absensi
                        <?php if ($is_checked_in): ?>
                            <span class="position-absolute top-0 start-100 translate-middle p-1 bg-success border border-light rounded-circle">
                                <span class="visually-hidden">Sudah check-in</span>
                            </span>
                        <?php endif; ?>
                    </a>
                </li>
                <?php endif; ?>

            </ul>

            <!-- Right Side Menu -->
            <ul class="navbar-nav">
                <!-- Notifications -->
                <li class="nav-item dropdown" id="notification-dropdown">
                    <a class="nav-link d-flex align-items-center position-relative" href="#" id="notificationDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false" data-no-loader="true">
                        <i class="bx bx-bell fs-5"></i>
                        <span class="notification-badge" id="notification-badge" style="display: none;">0</span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="notificationDropdown" id="notification-list">
                        <li><div class="dropdown-item text-center">Tidak ada notifikasi baru</div></li>
                    </ul>
                </li>

                <!-- User Profile -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" data-bs-toggle="dropdown" data-no-loader="true">
                        <i class="bx bx-user fs-5 me-1"></i> <?= htmlspecialchars($_SESSION['username'] ?? 'User') ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item d-flex align-items-center" href="<?= url_for('user/profile.php') ?>"><i class="bi bi-person-circle me-1"></i> Profile</a></li>
                        <li><a class="dropdown-item d-flex align-items-center" href="<?= url_for('user/live_chat.php') ?>"><i class="bi bi-chat-dots me-1"></i> Live Chat</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item d-flex align-items-center" href="<?= url_for('logout.php') ?>"><i class="bi bi-box-arrow-right me-1"></i> Logout</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>

<nav class="minven-mobile-nav" aria-label="Navigasi cepat">
    <div class="minven-mobile-nav__inner">
        <?php if (hasAccess('dashboard')): ?>
            <a class="minven-mobile-nav__item <?= $isDashboardPage ? 'active' : '' ?>" href="<?= url_for('dashboard.php') ?>">
                <i class="bi bi-grid-fill minven-mobile-nav__icon"></i>
                <span class="minven-mobile-nav__label">Home</span>
            </a>
        <?php endif; ?>

        <?php if (hasAccess('gudang') || hasAccess('gudang_central') || hasAccess('gudang_antapani')): ?>
            <a class="minven-mobile-nav__item <?= $isGudangPage ? 'active' : '' ?>" href="<?= htmlspecialchars($mobileGudangHref) ?>">
                <i class="bi bi-building minven-mobile-nav__icon"></i>
                <span class="minven-mobile-nav__label">Gudang</span>
            </a>
        <?php endif; ?>

        <?php if (hasAccess('transaksi')): ?>
            <a class="minven-mobile-nav__item <?= $isTransaksiPage ? 'active' : '' ?>" href="<?= htmlspecialchars($mobileTransaksiHref) ?>">
                <i class="bi bi-arrow-left-right minven-mobile-nav__icon"></i>
                <span class="minven-mobile-nav__label">Transaksi</span>
            </a>
        <?php endif; ?>

        <?php if (hasAccess('laporan')): ?>
            <a class="minven-mobile-nav__item <?= $isLaporanPage ? 'active' : '' ?>" href="<?= htmlspecialchars($mobileLaporanHref) ?>">
                <i class="bi bi-file-bar-graph minven-mobile-nav__icon"></i>
                <span class="minven-mobile-nav__label">Laporan</span>
            </a>
        <?php endif; ?>

        <button class="minven-mobile-nav__item" type="button" data-bs-toggle="offcanvas" data-bs-target="#minvenMobileMenu" aria-controls="minvenMobileMenu" aria-label="Buka menu utama" data-no-loader="true">
            <i class="bi bi-grid-3x3-gap minven-mobile-nav__icon"></i>
            <span class="minven-mobile-nav__label">Menu</span>
        </button>
    </div>
</nav>

<div class="offcanvas offcanvas-end minven-mobile-sheet" tabindex="-1" id="minvenMobileMenu" aria-labelledby="minvenMobileMenuLabel">
    <div class="minven-sheet-handle"></div>
    <div class="offcanvas-header">
        <div>
            <div class="fw-bold" id="minvenMobileMenuLabel"><?= htmlspecialchars($_SESSION['username'] ?? 'User') ?></div>
            <div class="text-muted small">MINVEN <span class="minven-brand-pro">PRO</span></div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body">
        <div class="list-group list-group-flush">
            <?php if (hasAccess('gudang_central')): ?>
                <a class="list-group-item list-group-item-action d-flex align-items-center gap-2" href="<?= url_for('gudang/gudang_central.php') ?>">
                    <i class="bi bi-building"></i><span>Gudang Central</span>
                </a>
            <?php endif; ?>
            <?php if (hasAccess('gudang_antapani')): ?>
                <a class="list-group-item list-group-item-action d-flex align-items-center gap-2" href="<?= url_for('gudang/gudang_antapani.php') ?>">
                    <i class="bi bi-building"></i><span>Gudang Antapani</span>
                </a>
            <?php endif; ?>

            <?php if (hasAccess('stok_masuk')): ?>
                <a class="list-group-item list-group-item-action d-flex align-items-center gap-2" href="<?= url_for('stok/masuk/index.php') ?>">
                    <i class="bi bi-box-arrow-in-down"></i><span>Stok Masuk</span>
                </a>
            <?php endif; ?>
            <?php if (hasAccess('stok_keluar')): ?>
                <a class="list-group-item list-group-item-action d-flex align-items-center gap-2" href="<?= url_for('stok/keluar/index.php') ?>">
                    <i class="bi bi-box-arrow-up"></i><span>Stok Keluar</span>
                </a>
            <?php endif; ?>
            <?php if (hasAccess('stok_transfer')): ?>
                <a class="list-group-item list-group-item-action d-flex align-items-center gap-2" href="<?= url_for('stok/transfer/index.php') ?>">
                    <i class="bi bi-arrow-repeat"></i><span>Stok Transfer</span>
                </a>
            <?php endif; ?>
            <?php if (hasAccess('adjustment_in')): ?>
                <a class="list-group-item list-group-item-action d-flex align-items-center gap-2" href="<?= url_for('stok/adjustment_in/index.php') ?>">
                    <i class="bi bi-arrow-repeat"></i><span>Stok Adjustment In</span>
                </a>
            <?php endif; ?>
            <?php if (hasAccess('adjustment_out')): ?>
                <a class="list-group-item list-group-item-action d-flex align-items-center gap-2" href="<?= url_for('stok/adjustment_out/index.php') ?>">
                    <i class="bi bi-arrow-repeat"></i><span>Stok Adjustment Out</span>
                </a>
            <?php endif; ?>

            <?php if (hasAccess('purchase_order')): ?>
                <a class="list-group-item list-group-item-action d-flex align-items-center gap-2" href="<?= url_for('pembelian/po/index.php') ?>">
                    <i class="bi bi-file-earmark-text"></i><span>Purchase Order</span>
                </a>
            <?php endif; ?>
            <?php if (hasAccess('pembelian_direct')): ?>
                <a class="list-group-item list-group-item-action d-flex align-items-center gap-2" href="<?= url_for('pembelian/direct/index.php') ?>">
                    <i class="bi bi-bag-check"></i><span>Pembelian Direct</span>
                </a>
            <?php endif; ?>
            <a class="list-group-item list-group-item-action d-flex align-items-center gap-2" href="<?= url_for('pembelian/surat jalan/index.php') ?>">
                <i class="bi bi-truck"></i><span>Surat Jalan</span>
            </a>

            <?php if (hasAccess('laporan')): ?>
                <a class="list-group-item list-group-item-action d-flex align-items-center gap-2" href="<?= url_for('laporan/stok_gudang.php') ?>">
                    <i class="bi bi-stack"></i><span>Laporan Stok</span>
                </a>
                <a class="list-group-item list-group-item-action d-flex align-items-center gap-2" href="<?= url_for('laporan/laporan_perbulan.php') ?>">
                    <i class="bi bi-calendar-month"></i><span>Laporan Perbulan</span>
                </a>
                <?php if (hasAccess('adjustment_in')): ?>
                    <a class="list-group-item list-group-item-action d-flex align-items-center gap-2" href="<?= url_for('adjustment_in.php') ?>">
                        <i class="bi bi-adjust"></i><span>Laporan Adjustment In</span>
                    </a>
                <?php endif; ?>
                <?php if (hasAccess('adjustment_out')): ?>
                    <a class="list-group-item list-group-item-action d-flex align-items-center gap-2" href="<?= url_for('adjustment_out.php') ?>">
                        <i class="bi bi-adjust"></i><span>Laporan Adjustment Out</span>
                    </a>
                <?php endif; ?>
            <?php endif; ?>

            <?php if (hasAccess('setup') || hasAccess('user') || hasAccess('reset_stok') || hasAccess('edit_nama_gudang') || hasAccess('template_po') || hasAccess('barcode') || hasAccess('get_wa') || hasAccess('menu_access') || hasAccess('setup_upload_template')): ?>
                <?php if (hasAccess('user')): ?>
                    <a class="list-group-item list-group-item-action d-flex align-items-center gap-2" href="<?= url_for('user/index.php') ?>">
                        <i class="bi bi-people"></i><span>Daftar User</span>
                    </a>
                <?php endif; ?>
                <?php if (hasAccess('menu_access')): ?>
                    <a class="list-group-item list-group-item-action d-flex align-items-center gap-2" href="<?= url_for('menu_access/index.php') ?>">
                        <i class="bi bi-shield-lock"></i><span>Menu Access</span>
                    </a>
                <?php endif; ?>
                <?php if (hasAccess('reset_stok')): ?>
                    <a class="list-group-item list-group-item-action d-flex align-items-center gap-2" href="<?= url_for('setup/index.php') ?>">
                        <i class="bi bi-clock-history"></i><span>Jam Reset Stok Harian</span>
                    </a>
                <?php endif; ?>
                <?php if (hasAccess('setup_upload_template')): ?>
                    <a class="list-group-item list-group-item-action d-flex align-items-center gap-2" href="<?= url_for('setup/upload_template_setup.php') ?>">
                        <i class="bi bi-upload"></i><span>Upload Template & Logo</span>
                    </a>
                <?php endif; ?>
                <?php if (hasAccess('edit_nama_gudang')): ?>
                    <a class="list-group-item list-group-item-action d-flex align-items-center gap-2" href="<?= url_for('setup/edit_nama_gudang.php') ?>">
                        <i class="bi bi-pencil-square"></i><span>Edit Nama Gudang</span>
                    </a>
                <?php endif; ?>
                <?php if (hasAccess('template_po')): ?>
                    <a class="list-group-item list-group-item-action d-flex align-items-center gap-2" href="<?= url_for('setup/po_template_setup.php') ?>">
                        <i class="bi bi-file-earmark-plus"></i><span>Template PO</span>
                    </a>
                <?php endif; ?>
                <?php if (hasAccess('barcode')): ?>
                    <a class="list-group-item list-group-item-action d-flex align-items-center gap-2" href="<?= url_for('setup/scan_barcode.php') ?>">
                        <i class="bi bi-upc-scan"></i><span>Setup Barcode</span>
                    </a>
                <?php endif; ?>
                <?php if (hasAccess('get_wa')): ?>
                    <a class="list-group-item list-group-item-action d-flex align-items-center gap-2" href="<?= url_for('setup/whatsapp_config.php') ?>">
                        <i class="bi bi-whatsapp"></i><span>Get WA</span>
                    </a>
                <?php endif; ?>
            <?php endif; ?>

            <a class="list-group-item list-group-item-action d-flex align-items-center gap-2" href="<?= url_for('user/profile.php') ?>">
                <i class="bi bi-person-circle"></i><span>Profile</span>
            </a>
            <a class="list-group-item list-group-item-action d-flex align-items-center gap-2" href="<?= url_for('logout.php') ?>">
                <i class="bi bi-box-arrow-right"></i><span>Logout</span>
            </a>
        </div>
    </div>
</div>

<!-- Notification Audio -->
<audio id="navbar-notification-sound" preload="auto" style="display: none;">
    <source src="<?= htmlspecialchars(url_for('asset/masuk.mp3')) ?>" type="audio/mpeg">
</audio>

<script>
(function ensureFaviconLinks() {
    const head = document.head || document.getElementsByTagName('head')[0];
    if (!head) return;

    const href = '<?= addslashes(url_for('asset/LOGO1.png')) ?>';

    const ensure = (rel, type) => {
        const selector = type
            ? `link[rel="${rel}"][href="${href}"][type="${type}"]`
            : `link[rel="${rel}"][href="${href}"]`;
        if (head.querySelector(selector)) return;

        const link = document.createElement('link');
        link.rel = rel;
        link.href = href;
        if (type) link.type = type;
        head.appendChild(link);
    };

    ensure('shortcut icon', 'image/png');
    ensure('apple-touch-icon');
})();

(function minvenPageLoaderInit() {
    const LOADER_ID = 'minven-page-loader';
    const STYLE_ID = 'minven-page-loader-style';
    const LOGO_SRC = '<?= addslashes(url_for('asset/LOGO1.png')) ?>';

    const ensureStyle = () => {
        if (document.getElementById(STYLE_ID)) return;
        const style = document.createElement('style');
        style.id = STYLE_ID;
        style.textContent = `
            #${LOADER_ID}{
                position:fixed;inset:0;z-index:20000;display:flex;align-items:center;justify-content:center;
                background:#004AAD;
                opacity:1;visibility:visible;pointer-events:auto;
                transition:opacity .18s ease,visibility .18s ease;
            }
            #${LOADER_ID}.minven-hidden{opacity:0;visibility:hidden;pointer-events:none}
            #${LOADER_ID} .minven-inner{position:relative;display:flex;align-items:center;justify-content:center}
            #${LOADER_ID} .minven-logo{display:block;width:min(70vw,260px);height:auto;object-fit:contain;filter:drop-shadow(0 14px 26px rgba(0,0,0,.35))}
        `;
        (document.head || document.documentElement).appendChild(style);
    };

    const ensureLoader = () => {
        let el = document.getElementById(LOADER_ID);
        if (el) return el;
        el = document.createElement('div');
        el.id = LOADER_ID;
        el.setAttribute('aria-hidden', 'true');
        el.innerHTML = `<div class="minven-inner"><img class="minven-logo" src="${LOGO_SRC}" alt=""></div>`;
        document.body.appendChild(el);
        return el;
    };

    const setLoaderState = (visible) => {
        const el = document.getElementById(LOADER_ID);
        if (!el) return;
        el.style.opacity = visible ? '1' : '0';
        el.style.visibility = visible ? 'visible' : 'hidden';
        el.style.pointerEvents = visible ? 'auto' : 'none';
        el.classList.toggle('minven-hidden', !visible);
    };

    const show = () => {
        ensureStyle();
        if (!document.body) return;
        const el = ensureLoader();
        setLoaderState(true);
    };

    const hide = () => {
        const el = document.getElementById(LOADER_ID);
        if (!el) return;
        setLoaderState(false);
    };

    window.MinvenPageLoader = window.MinvenPageLoader || { show, hide };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', show, { once: true });
    } else {
        show();
    }

    window.addEventListener('load', () => setTimeout(hide, 120));
    if (document.readyState === 'complete') setTimeout(hide, 0);

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
            if (a.getAttribute('data-bs-toggle') !== null) return;

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

            setTimeout(() => {
                if (e.defaultPrevented) return;
                show();
            }, 0);
        },
        true
    );

    document.addEventListener(
        'submit',
        (e) => {
            if (e.defaultPrevented) return;
            setTimeout(() => {
                if (e.defaultPrevented) return;
                show();
            }, 0);
        },
        true
    );

    window.addEventListener('beforeunload', show);
})();

// Notification System
let previousUnreadCount = 0;

function checkNotifications() {
    fetch('<?= addslashes(url_for('user/check_notifications.php')) ?>')
        .then(response => response.json())
        .then(data => {
            const badge = document.getElementById('notification-badge');
            const notificationList = document.getElementById('notification-list');
            const liveChatUrlBase = '<?= addslashes(url_for('user/live_chat.php')) ?>';
            
            // Update badge
            if (data.unread > 0) {
                badge.textContent = data.unread;
                badge.style.display = 'block';
                
                // Play sound if new notifications
                if (data.unread > previousUnreadCount) {
                    document.getElementById('navbar-notification-sound').play();
                }
                
                // Update notification list
                notificationList.innerHTML = '';
                data.notifications.forEach(notification => {
                    const item = document.createElement('li');
                    item.innerHTML = `<a class="dropdown-item" href="${liveChatUrlBase}?with_user=${notification.sender_id}">
                        <strong>${notification.sender_name}</strong>: ${notification.unread} pesan baru
                    </a>`;
                    notificationList.appendChild(item);
                });
            } else {
                badge.style.display = 'none';
                notificationList.innerHTML = '<li><div class="dropdown-item text-center">Tidak ada notifikasi baru</div></li>';
            }
            
            previousUnreadCount = data.unread;
        })
        .catch(error => console.error('Error checking notifications:', error));
}

// Initialize notifications
document.addEventListener('DOMContentLoaded', function() {
    checkNotifications();
    setInterval(checkNotifications, 10000); // Check every 10 seconds
});

(function minvenNavbarCloseOnNavigate() {
    const navCollapseEl = document.getElementById('navbarNav');
    const mobileMenuEl = document.getElementById('minvenMobileMenu');

    const hideCollapse = () => {
        if (!navCollapseEl) return;
        try {
            const instance = window.bootstrap?.Collapse?.getInstance?.(navCollapseEl)
                ?? (window.bootstrap?.Collapse ? new window.bootstrap.Collapse(navCollapseEl, { toggle: false }) : null);
            if (instance?.hide) {
                instance.hide();
            }
        } catch {
            navCollapseEl.classList.remove('show');
        }
    };

    const hideMobileMenu = () => {
        if (!mobileMenuEl) return;
        try {
            const instance = window.bootstrap?.Offcanvas?.getInstance?.(mobileMenuEl)
                ?? (window.bootstrap?.Offcanvas ? new window.bootstrap.Offcanvas(mobileMenuEl) : null);
            if (instance?.hide) {
                instance.hide();
            }
        } catch {
            mobileMenuEl.classList.remove('show');
        }
        mobileMenuEl.setAttribute('aria-hidden', 'true');
    };

    const closeAllMenus = () => {
        hideCollapse();
        hideMobileMenu();
    };

    const shouldHandle = (target) => {
        if (!target) return false;
        if (target.closest('[data-bs-toggle]')) return false;
        if (target.getAttribute('href') === '#') return false;
        return target.tagName === 'A' || target.tagName === 'BUTTON';
    };

    document.addEventListener('click', (event) => {
        const target = event.target && event.target.closest ? event.target.closest('a, button') : null;
        if (!shouldHandle(target)) return;

        const href = target.getAttribute('href') || '';
        if (!href || href === '#' || href.startsWith('javascript:')) return;

        closeAllMenus();
    }, { passive: true });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeAllMenus();
        }
    }, { passive: true });
})();

(function initMinvenNavbarFallback() {
    const navCollapseEl = document.getElementById('navbarNav');
    const mobileMenuEl = document.getElementById('minvenMobileMenu');
    const navToggleBtn = document.querySelector('[data-bs-target="#navbarNav"]');
    const mobileMenuBtn = document.querySelector('[data-bs-target="#minvenMobileMenu"]');

    let backdropEl = null;

    const closeDropdowns = () => {
        document.querySelectorAll('.minven-navbar .dropdown-menu.show, .minven-sidebar .dropdown-menu.show').forEach((menu) => {
            menu.classList.remove('show');
            const toggle = menu.closest('.dropdown, .nav-item')?.querySelector('[data-bs-toggle="dropdown"], [data-sidebar-dropdown="true"]');
            if (toggle) {
                toggle.setAttribute('aria-expanded', 'false');
            }
        });
    };

    const closeCollapse = () => {
        if (!navCollapseEl) return;
        navCollapseEl.classList.remove('show');
        if (navToggleBtn) {
            navToggleBtn.setAttribute('aria-expanded', 'false');
        }
    };

    const removeBackdrop = () => {
        if (backdropEl) {
            backdropEl.remove();
            backdropEl = null;
        }
    };

    const closeMobileMenu = () => {
        if (mobileMenuEl) {
            mobileMenuEl.classList.remove('show');
            mobileMenuEl.setAttribute('aria-hidden', 'true');
        }
        if (mobileMenuBtn) {
            mobileMenuBtn.setAttribute('aria-expanded', 'false');
        }
        removeBackdrop();
        document.body.classList.remove('minven-offcanvas-open');
    };

    const openMobileMenu = () => {
        closeDropdowns();
        closeCollapse();
        if (!mobileMenuEl) return;
        mobileMenuEl.classList.add('show');
        mobileMenuEl.setAttribute('aria-hidden', 'false');
        if (!backdropEl) {
            backdropEl = document.createElement('div');
            backdropEl.className = 'minven-navbar-backdrop';
            document.body.appendChild(backdropEl);
            backdropEl.addEventListener('click', closeMobileMenu);
        }
        document.body.classList.add('minven-offcanvas-open');
        if (mobileMenuBtn) {
            mobileMenuBtn.setAttribute('aria-expanded', 'true');
        }
    };

    // Remove custom toggleDropdown, use Bootstrap's native Dropdown

    const closeAllMenus = () => {
        closeDropdowns();
        closeCollapse();
        closeMobileMenu();
    };

    document.addEventListener('click', (event) => {
        const target = event.target && event.target.closest ? event.target.closest('a, button') : null;
        if (!target) return;

        if (target.classList.contains('minven-navbar-backdrop')) {
            event.preventDefault();
            closeMobileMenu();
            return;
        }

        if (target.matches('[data-sidebar-dropdown="true"], [data-bs-toggle="dropdown"]')) {
            event.preventDefault();
            event.stopPropagation();
            const sidebarToggle = target.closest('.minven-sidebar');
            const dropdownRoot = target.closest('.dropdown, .nav-item');
            const dropdownMenu = dropdownRoot?.querySelector('.dropdown-menu');
            const isOpen = !!dropdownMenu?.classList.contains('show');

            if (sidebarToggle) {
                if (isOpen) {
                    dropdownMenu?.classList.remove('show');
                    target.setAttribute('aria-expanded', 'false');
                    return;
                }

                document.querySelectorAll('.minven-sidebar .dropdown-menu.show').forEach((menu) => {
                    menu.classList.remove('show');
                    const toggleBtn = menu.closest('.dropdown')?.querySelector('[data-bs-toggle="dropdown"], [data-sidebar-dropdown="true"]');
                    if (toggleBtn) {
                        toggleBtn.setAttribute('aria-expanded', 'false');
                    }
                });

                dropdownMenu?.classList.add('show');
                target.setAttribute('aria-expanded', 'true');
                return;
            }

            if (isOpen) {
                closeDropdowns();
                return;
            }

            closeDropdowns();
            const dropdown = bootstrap.Dropdown.getOrCreateInstance(target);
            dropdown.toggle();
            return;
        }

        if (target.matches('[data-bs-target="#navbarNav"]')) {
            event.preventDefault();
            event.stopPropagation();
            if (navCollapseEl?.classList.contains('show')) {
                closeCollapse();
            } else {
                closeDropdowns();
                closeMobileMenu();
                navCollapseEl?.classList.add('show');
                if (navToggleBtn) navToggleBtn.setAttribute('aria-expanded', 'true');
            }
            return;
        }

        if (target.matches('[data-bs-target="#minvenMobileMenu"]')) {
            event.preventDefault();
            event.stopPropagation();
            if (mobileMenuEl?.classList.contains('show')) {
                closeMobileMenu();
            } else {
                openMobileMenu();
            }
            return;
        }

        if (target.matches('.minven-mobile-nav__item') && target.tagName === 'A') {
            closeMobileMenu();
            return;
        }

        if (target.matches('.dropdown-item')) {
            closeDropdowns();
            closeCollapse();
            closeMobileMenu();
            return;
        }
    }, { passive: false });

    document.addEventListener('click', (event) => {
        if (!event.target || !event.target.closest) return;
        const clickedInsideNavbar = event.target.closest('.minven-navbar');
        const clickedInsideSidebar = event.target.closest('.minven-sidebar');
        if (clickedInsideNavbar || clickedInsideSidebar) return;
        closeAllMenus();
    }, { passive: true });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeAllMenus();
        }
    }, { passive: true });

    window.addEventListener('resize', () => {
        if (window.innerWidth >= 1200) {
            closeCollapse();
        }
    });
})();
</script>
