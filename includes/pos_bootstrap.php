<?php
require_once __DIR__ . '/../config.php';

if (!function_exists('pos_server_conn')) {
    function pos_server_conn() {
        static $serverConn = null;
        if ($serverConn instanceof mysqli && !$serverConn->connect_error) {
            return $serverConn;
        }

        $serverConn = @new mysqli(DB_HOST, DB_USER, DB_PASS, '', DB_PORT);
        if ($serverConn instanceof mysqli && !$serverConn->connect_error) {
            $serverConn->set_charset('utf8mb4');
            return $serverConn;
        }

        return null;
    }
}

if (!function_exists('pos_ensure_database')) {
    function pos_ensure_database() {
        $serverConn = pos_server_conn();
        if (!$serverConn instanceof mysqli || $serverConn->connect_error) {
            return null;
        }

        $dbName = defined('POS_DB_NAME') ? (string)POS_DB_NAME : 'minven_pos';
        if ($dbName === '') {
            return null;
        }

        $dbNameEscaped = str_replace('`', '``', $dbName);
        $serverConn->query("CREATE DATABASE IF NOT EXISTS `{$dbNameEscaped}` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");

        if (isset($GLOBALS['posConn']) && $GLOBALS['posConn'] instanceof mysqli && !$GLOBALS['posConn']->connect_error) {
            return $GLOBALS['posConn'];
        }

        $posConn = @new mysqli(DB_HOST, DB_USER, DB_PASS, $dbName, DB_PORT);
        if ($posConn instanceof mysqli && !$posConn->connect_error) {
            $posConn->set_charset('utf8mb4');
            $GLOBALS['posConn'] = $posConn;
            return $posConn;
        }

        return null;
    }
}

if (!function_exists('pos_ensure_schema')) {
    function pos_ensure_schema() {
        static $done = false;
        if ($done) {
            return pos_db_conn();
        }
        $done = true;

        $posConn = pos_ensure_database();
        if (!$posConn instanceof mysqli || $posConn->connect_error) {
            return null;
        }

        $queries = [
            "CREATE TABLE IF NOT EXISTS pos_sales (
                id INT AUTO_INCREMENT PRIMARY KEY,
                sale_number VARCHAR(30) NOT NULL UNIQUE,
                sale_date DATETIME NOT NULL,
                gudang_id INT NOT NULL,
                product_id INT DEFAULT NULL,
                cashier_user_id INT NOT NULL,
                cashier_username VARCHAR(100) NOT NULL,
                customer_name VARCHAR(120) DEFAULT NULL,
                payment_method ENUM('cash', 'transfer', 'qris', 'debit', 'ewallet') NOT NULL DEFAULT 'cash',
                payment_status ENUM('paid', 'partial') NOT NULL DEFAULT 'paid',
                subtotal DECIMAL(15,2) NOT NULL DEFAULT 0,
                discount_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
                tax_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
                grand_total DECIMAL(15,2) NOT NULL DEFAULT 0,
                amount_paid DECIMAL(15,2) NOT NULL DEFAULT 0,
                change_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
                notes TEXT DEFAULT NULL,
                shift_id INT DEFAULT NULL,
                settlement_id INT DEFAULT NULL,
                inventory_transaction_id INT DEFAULT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_sale_date (sale_date),
                INDEX idx_gudang_id (gudang_id),
                INDEX idx_cashier_user_id (cashier_user_id),
                INDEX idx_shift_id (shift_id),
                INDEX idx_settlement_id (settlement_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
            "CREATE TABLE IF NOT EXISTS pos_sale_items (
                id INT AUTO_INCREMENT PRIMARY KEY,
                sale_id INT NOT NULL,
                product_id INT NOT NULL,
                kode_product VARCHAR(50) NOT NULL,
                nama_product VARCHAR(150) NOT NULL,
                qty INT NOT NULL,
                unit_price DECIMAL(15,2) NOT NULL DEFAULT 0,
                line_total DECIMAL(15,2) NOT NULL DEFAULT 0,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_sale_id (sale_id),
                INDEX idx_product_id (product_id),
                CONSTRAINT fk_pos_sale_items_sale FOREIGN KEY (sale_id) REFERENCES pos_sales(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
            "CREATE TABLE IF NOT EXISTS pos_shifts (
                id INT AUTO_INCREMENT PRIMARY KEY,
                shift_number VARCHAR(30) NOT NULL UNIQUE,
                shift_date DATE NOT NULL,
                shift_label VARCHAR(50) NOT NULL,
                gudang_id INT NOT NULL,
                opened_by INT NOT NULL,
                opened_by_username VARCHAR(100) NOT NULL,
                opened_at DATETIME NOT NULL,
                opening_cash DECIMAL(15,2) NOT NULL DEFAULT 0,
                closed_by INT DEFAULT NULL,
                closed_by_username VARCHAR(100) DEFAULT NULL,
                closed_at DATETIME DEFAULT NULL,
                closing_cash DECIMAL(15,2) DEFAULT NULL,
                expected_cash DECIMAL(15,2) DEFAULT NULL,
                cash_difference DECIMAL(15,2) DEFAULT NULL,
                status ENUM('open', 'closed') NOT NULL DEFAULT 'open',
                notes TEXT DEFAULT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_shift_date (shift_date),
                INDEX idx_gudang_id (gudang_id),
                INDEX idx_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
            "CREATE TABLE IF NOT EXISTS pos_settlements (
                id INT AUTO_INCREMENT PRIMARY KEY,
                settlement_number VARCHAR(30) NOT NULL UNIQUE,
                settlement_date DATE NOT NULL,
                gudang_id INT NOT NULL,
                total_sales DECIMAL(15,2) NOT NULL DEFAULT 0,
                total_cash DECIMAL(15,2) NOT NULL DEFAULT 0,
                total_transfer DECIMAL(15,2) NOT NULL DEFAULT 0,
                total_qris DECIMAL(15,2) NOT NULL DEFAULT 0,
                total_debit DECIMAL(15,2) NOT NULL DEFAULT 0,
                total_ewallet DECIMAL(15,2) NOT NULL DEFAULT 0,
                synced_to_omset ENUM('0', '1') NOT NULL DEFAULT '0',
                synced_at DATETIME DEFAULT NULL,
                notes TEXT DEFAULT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_settlement_date (settlement_date),
                INDEX idx_gudang_id (gudang_id),
                INDEX idx_synced_to_omset (synced_to_omset)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
        ];

        foreach ($queries as $sql) {
            $posConn->query($sql);
        }

        return $posConn;
    }
}

if (!function_exists('pos_find_or_create_role')) {
    function pos_find_or_create_role($authConn, $roleName, $description = null) {
        $roleName = trim((string)$roleName);
        if (!$authConn instanceof mysqli || $authConn->connect_error || $roleName === '') {
            return 0;
        }

        $stmt = $authConn->prepare("SELECT id FROM roles WHERE nama_role = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('s', $roleName);
            $stmt->execute();
            $res = $stmt->get_result();
            $row = $res ? $res->fetch_assoc() : null;
            $stmt->close();
            if ($row) {
                return (int)$row['id'];
            }
        }

        if ($description !== null && function_exists('db_has_column') && db_has_column($authConn, 'roles', 'deskripsi')) {
            $stmt = $authConn->prepare("INSERT INTO roles (nama_role, deskripsi) VALUES (?, ?)");
            if ($stmt) {
                $stmt->bind_param('ss', $roleName, $description);
                if ($stmt->execute()) {
                    $roleId = (int)$authConn->insert_id;
                    $stmt->close();
                    return $roleId;
                }
                $stmt->close();
            }
        }

        $stmt = $authConn->prepare("INSERT INTO roles (nama_role) VALUES (?)");
        if ($stmt) {
            $stmt->bind_param('s', $roleName);
            if ($stmt->execute()) {
                $roleId = (int)$authConn->insert_id;
                $stmt->close();
                return $roleId;
            }
            $stmt->close();
        }

        return 0;
    }
}

if (!function_exists('pos_ensure_cashier_access')) {
    function pos_ensure_cashier_access($authConn, $roleId) {
        if (!$authConn instanceof mysqli || $authConn->connect_error || $roleId <= 0) {
            return;
        }

        $menus = [
            'dashboard' => ['view' => 1, 'add' => 0, 'edit' => 0, 'delete' => 0],
            'cashier' => ['view' => 1, 'add' => 1, 'edit' => 0, 'delete' => 0],
        ];

        foreach ($menus as $menuName => $permissions) {
            $stmt = $authConn->prepare("SELECT id FROM menu_access WHERE role_id = ? AND menu_name = ? LIMIT 1");
            if (!$stmt) {
                continue;
            }
            $stmt->bind_param('is', $roleId, $menuName);
            $stmt->execute();
            $res = $stmt->get_result();
            $row = $res ? $res->fetch_assoc() : null;
            $stmt->close();

            $canView = (int)$permissions['view'];
            $canAdd = (int)$permissions['add'];
            $canEdit = (int)$permissions['edit'];
            $canDelete = (int)$permissions['delete'];

            if ($row) {
                $accessId = (int)$row['id'];
                $stmt = $authConn->prepare("UPDATE menu_access SET can_view = ?, can_add = ?, can_edit = ?, can_delete = ? WHERE id = ?");
                if ($stmt) {
                    $stmt->bind_param('iiiii', $canView, $canAdd, $canEdit, $canDelete, $accessId);
                    $stmt->execute();
                    $stmt->close();
                }
                continue;
            }

            $stmt = $authConn->prepare("INSERT INTO menu_access (role_id, menu_name, can_view, can_add, can_edit, can_delete, can_complete, can_setup_split, can_send_wa) VALUES (?, ?, ?, ?, ?, ?, 0, 0, 0)");
            if ($stmt) {
                $stmt->bind_param('isiiii', $roleId, $menuName, $canView, $canAdd, $canEdit, $canDelete);
                $stmt->execute();
                $stmt->close();
            }
        }
    }
}

if (!function_exists('pos_ensure_admin_cashier_access')) {
    function pos_ensure_admin_cashier_access($authConn) {
        if (!$authConn instanceof mysqli || $authConn->connect_error) {
            return;
        }

        $menuName = 'cashier';
        $stmt = $authConn->prepare("SELECT id FROM menu_access WHERE role_id = 1 AND menu_name = ? LIMIT 1");
        if (!$stmt) {
            return;
        }
        $stmt->bind_param('s', $menuName);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();

        $canView = 1;
        $canAdd = 1;
        $canEdit = 1;
        $canDelete = 1;

        if ($row) {
            $accessId = (int)$row['id'];
            $stmt = $authConn->prepare("UPDATE menu_access SET can_view = ?, can_add = ?, can_edit = ?, can_delete = ? WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param('iiiii', $canView, $canAdd, $canEdit, $canDelete, $accessId);
                $stmt->execute();
                $stmt->close();
            }
            return;
        }

        $stmt = $authConn->prepare("INSERT INTO menu_access (role_id, menu_name, can_view, can_add, can_edit, can_delete, can_complete, can_setup_split, can_send_wa) VALUES (1, ?, ?, ?, ?, ?, 0, 0, 0)");
        if ($stmt) {
            $stmt->bind_param('siiii', $menuName, $canView, $canAdd, $canEdit, $canDelete);
            $stmt->execute();
            $stmt->close();
        }
    }
}

if (!function_exists('pos_ensure_cashier_user')) {
    function pos_ensure_cashier_user($authConn, $roleId) {
        if (!$authConn instanceof mysqli || $authConn->connect_error || $roleId <= 0) {
            return 0;
        }

        $username = 'kasir';
        $plainPassword = 'minven25';
        $displayName = 'Kasir Minven';
        $email = 'kasir@minven.local';

        $stmt = $authConn->prepare("SELECT id, password FROM users WHERE username = ? LIMIT 1");
        if (!$stmt) {
            return 0;
        }
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();

        $hash = password_hash($plainPassword, PASSWORD_DEFAULT);
        $userId = 0;

        if ($row) {
            $userId = (int)$row['id'];
            $stmt = $authConn->prepare("UPDATE users SET nama = ?, email = ?, password = ?, nama_lengkap = ?, role = 'staff', role_id = ?, is_active = 1 WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param('ssssii', $displayName, $email, $hash, $displayName, $roleId, $userId);
                $stmt->execute();
                $stmt->close();
            }
        } else {
            $stmt = $authConn->prepare("INSERT INTO users (username, nama, email, password, nama_lengkap, role, role_id, is_active) VALUES (?, ?, ?, ?, ?, 'staff', ?, 1)");
            if ($stmt) {
                $stmt->bind_param('sssssi', $username, $displayName, $email, $hash, $displayName, $roleId);
                if ($stmt->execute()) {
                    $userId = (int)$authConn->insert_id;
                }
                $stmt->close();
            }
        }

        if ($userId <= 0) {
            return 0;
        }

        $stmt = $authConn->prepare("SELECT id FROM user_roles WHERE user_id = ? AND role_id = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('ii', $userId, $roleId);
            $stmt->execute();
            $res = $stmt->get_result();
            $exists = $res ? $res->fetch_assoc() : null;
            $stmt->close();

            if (!$exists) {
                $stmt = $authConn->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)");
                if ($stmt) {
                    $stmt->bind_param('ii', $userId, $roleId);
                    $stmt->execute();
                    $stmt->close();
                }
            }
        }

        return $userId;
    }
}

if (!function_exists('pos_ensure_auth_seed')) {
    function pos_ensure_auth_seed() {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;

        $authConn = auth_db_conn();
        if (!$authConn instanceof mysqli || $authConn->connect_error) {
            return;
        }

        foreach (['roles', 'users', 'user_roles', 'menu_access'] as $tableName) {
            $check = $authConn->query("SHOW TABLES LIKE '" . $authConn->real_escape_string($tableName) . "'");
            if (!$check || $check->num_rows === 0) {
                return;
            }
        }

        $roleId = pos_find_or_create_role($authConn, 'Kasir', 'Role khusus aplikasi kasir POS');
        if ($roleId <= 0) {
            return;
        }

        pos_ensure_cashier_access($authConn, $roleId);
        pos_ensure_admin_cashier_access($authConn);
        pos_ensure_cashier_user($authConn, $roleId);
    }
}

if (!function_exists('pos_bootstrap')) {
    function pos_bootstrap() {
        pos_ensure_schema();
        pos_ensure_auth_seed();
        return pos_db_conn();
    }
}
