<?php
require_once __DIR__ . '/../config.php';

function ensure_role_and_menu_access() {
    $conn = $GLOBALS['conn'] ?? null;
    if (!$conn || !($conn instanceof mysqli) || $conn->connect_error) {
        echo "[ERROR] Koneksi database tidak tersedia.\n";
        return 1;
    }

    $roleNames = ['Kasir', 'Kasir POS'];
    $roleIds = [];

    foreach ($roleNames as $roleName) {
        $stmt = $conn->prepare('SELECT id FROM roles WHERE LOWER(TRIM(nama_role)) = LOWER(TRIM(?)) LIMIT 1');
        if (!$stmt) {
            continue;
        }
        $stmt->bind_param('s', $roleName);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();

        if ($row && isset($row['id'])) {
            $roleIds[] = (int)$row['id'];
            continue;
        }

        $ins = $conn->prepare('INSERT INTO roles (nama_role, deskripsi) VALUES (?, ?)');
        if ($ins) {
            $desc = 'Role khusus aplikasi kasir POS';
            $ins->bind_param('ss', $roleName, $desc);
            $ins->execute();
            $roleIds[] = (int)$conn->insert_id;
            $ins->close();
        }
    }

    $roleIds = array_values(array_unique(array_filter($roleIds, static fn($id) => (int)$id > 0)));
    if (empty($roleIds)) {
        echo "[ERROR] Role Kasir tidak bisa dibuat.\n";
        return 1;
    }

    $menuNames = ['cashier', 'dashboard', 'user', 'setup'];
    foreach ($roleIds as $roleId) {
        foreach ($menuNames as $menuName) {
            $exists = $conn->prepare('SELECT id FROM menu_access WHERE role_id = ? AND menu_name = ? LIMIT 1');
            if ($exists) {
                $exists->bind_param('is', $roleId, $menuName);
                $exists->execute();
                $check = $exists->get_result();
                $already = $check && $check->fetch_assoc();
                $exists->close();

                if ($already) {
                    $upd = $conn->prepare('UPDATE menu_access SET can_view = 1, can_add = 1, can_edit = 0, can_delete = 0 WHERE role_id = ? AND menu_name = ?');
                    if ($upd) {
                        $upd->bind_param('is', $roleId, $menuName);
                        $upd->execute();
                        $upd->close();
                    }
                    continue;
                }
            }

            $hasComplete = $conn->query("SHOW COLUMNS FROM menu_access LIKE 'can_complete'")->num_rows > 0;
            $hasSetupSplit = $conn->query("SHOW COLUMNS FROM menu_access LIKE 'can_setup_split'")->num_rows > 0;
            $hasSendWa = $conn->query("SHOW COLUMNS FROM menu_access LIKE 'can_send_wa'")->num_rows > 0;

            if ($hasComplete && $hasSetupSplit && $hasSendWa) {
                $sql = "INSERT INTO menu_access (role_id, menu_name, can_view, can_add, can_edit, can_delete, can_complete, can_setup_split, can_send_wa) VALUES (?, ?, 1, 1, 0, 0, 0, 0, 0)";
            } elseif ($hasComplete && $hasSetupSplit) {
                $sql = "INSERT INTO menu_access (role_id, menu_name, can_view, can_add, can_edit, can_delete, can_complete, can_setup_split) VALUES (?, ?, 1, 1, 0, 0, 0, 0)";
            } elseif ($hasComplete) {
                $sql = "INSERT INTO menu_access (role_id, menu_name, can_view, can_add, can_edit, can_delete, can_complete) VALUES (?, ?, 1, 1, 0, 0, 0)";
            } elseif ($hasSetupSplit) {
                $sql = "INSERT INTO menu_access (role_id, menu_name, can_view, can_add, can_edit, can_delete, can_setup_split) VALUES (?, ?, 1, 1, 0, 0, 0)";
            } else {
                $sql = "INSERT INTO menu_access (role_id, menu_name, can_view, can_add, can_edit, can_delete) VALUES (?, ?, 1, 1, 0, 0)";
            }

            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                echo "[ERROR] Gagal menyiapkan insert menu_access untuk role {$roleId} menu {$menuName}: {$conn->error}\n";
                continue;
            }
            $stmt->bind_param('is', $roleId, $menuName);
            $stmt->execute();
            $stmt->close();
        }
    }

    $kasirUser = 'kasir';
    $userCheck = $conn->prepare('SELECT id FROM users WHERE LOWER(TRIM(username)) = LOWER(TRIM(?)) LIMIT 1');
    if ($userCheck) {
        $userCheck->bind_param('s', $kasirUser);
        $userCheck->execute();
        $userRes = $userCheck->get_result();
        $userRow = $userRes ? $userRes->fetch_assoc() : null;
        $userCheck->close();

        if ($userRow && isset($userRow['id'])) {
            $userId = (int)$userRow['id'];
            foreach ($roleIds as $roleId) {
                $checkRelation = $conn->prepare('SELECT id FROM user_roles WHERE user_id = ? AND role_id = ? LIMIT 1');
                if ($checkRelation) {
                    $checkRelation->bind_param('ii', $userId, $roleId);
                    $checkRelation->execute();
                    $rel = $checkRelation->get_result();
                    $existsRel = $rel && $rel->fetch_assoc();
                    $checkRelation->close();

                    if (!$existsRel) {
                        $insRel = $conn->prepare('INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)');
                        if ($insRel) {
                            $insRel->bind_param('ii', $userId, $roleId);
                            $insRel->execute();
                            $insRel->close();
                        }
                    }
                }
            }
        }
    }

    echo "[OK] Role Kasir dan menu access berhasil dipastikan.\n";
    return 0;
}

if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__)) {
    exit(ensure_role_and_menu_access());
}
