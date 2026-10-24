<?php
session_start();

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/access_check.php';
require_once __DIR__ . '/../includes/pos_bootstrap.php';

$kasirAuthConn = auth_db_conn();
$kasirMainConn = main_db_conn();
$kasirPosConn  = pos_bootstrap();

if (!function_exists('kasir_url_for')) {
    function kasir_url_for($path = '') {
        $path = ltrim((string)$path, '/\\');
        return url_for('kasir/' . $path);
    }
}

if (!function_exists('kasir_current_user')) {
    function kasir_current_user() {
        return [
            'id'        => (int)($_SESSION['kasir_user_id'] ?? $_SESSION['user_id'] ?? 0),
            'username'  => (string)($_SESSION['kasir_username'] ?? $_SESSION['username'] ?? ''),
            'nama'      => (string)($_SESSION['kasir_nama'] ?? $_SESSION['nama_lengkap'] ?? $_SESSION['nama'] ?? $_SESSION['username'] ?? ''),
            'role_id'   => (int)($_SESSION['kasir_role_id'] ?? $_SESSION['role_id'] ?? 0),
            'role_name' => (string)($_SESSION['kasir_role_name'] ?? $_SESSION['nama_role'] ?? 'Kasir'),
        ];
    }
}

if (!function_exists('kasir_user_can_login')) {
    function kasir_user_can_login($authConn, $userId) {
        if (!$authConn instanceof mysqli || $authConn->connect_error || $userId <= 0) {
            return false;
        }

        $sql = "SELECT 1
                        FROM user_roles ur
                        INNER JOIN roles kasir_role ON kasir_role.id = ur.role_id
            WHERE ur.user_id = ?
                            AND LOWER(TRIM(kasir_role.nama_role)) = 'kasir'
            LIMIT 1";
        $stmt = $authConn->prepare($sql);
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        $allowed = $res && $res->fetch_assoc();
        $stmt->close();

        return (bool)$allowed;
    }
}

if (!function_exists('kasir_is_logged_in')) {
    function kasir_is_logged_in() {
        $user = kasir_current_user();
        if ($user['id'] <= 0) {
            return false;
        }

        $authConn = auth_db_conn();
        return kasir_user_can_login($authConn, $user['id']);
    }
}

if (!function_exists('kasir_require_login')) {
    function kasir_require_login() {
        if (!kasir_is_logged_in()) {
            $user = kasir_current_user();
            if ($user['id'] <= 0) {
                header('Location: ' . url_for('index.php'));
                exit();
            }

            $_SESSION['kasir_error'] = 'Akun ini tidak memiliki role Kasir.';
            header('Location: ' . kasir_url_for('index.php'));
            exit();
        }
    }
}

if (!function_exists('kasir_logout')) {
    function kasir_logout() {
        unset(
            $_SESSION['kasir_user_id'],
            $_SESSION['kasir_username'],
            $_SESSION['kasir_nama'],
            $_SESSION['kasir_role_id'],
            $_SESSION['kasir_role_name']
        );
    }
}

if (!function_exists('kasir_format_currency')) {
    function kasir_format_currency($amount) {
        return 'Rp ' . number_format((float)$amount, 0, ',', '.');
    }
}

if (!function_exists('kasir_generate_sale_number')) {
    function kasir_generate_sale_number($posConn) {
        $dateKey = date('Ymd');
        $prefix = 'POS-' . $dateKey;
        $stmt = $posConn->prepare("SELECT COUNT(*) AS total FROM pos_sales WHERE sale_number LIKE CONCAT(?, '%')");
        if (!$stmt) {
            return $prefix . '-0001';
        }
        $stmt->bind_param('s', $prefix);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();
        $next = ((int)($row['total'] ?? 0)) + 1;
        return $prefix . '-' . str_pad((string)$next, 4, '0', STR_PAD_LEFT);
    }
}