<?php
require_once __DIR__ . '/_init.php';
bo_require_login();

$error = null;
$success = null;

$roles = [];
$rolesRes = $boAuthConn->query("SELECT id, nama_role FROM roles ORDER BY nama_role");
if ($rolesRes) {
    while ($r = $rolesRes->fetch_assoc()) {
        $roles[] = $r;
    }
}

$action = (string)($_POST['action'] ?? $_GET['action'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'create') {
        $username = trim((string)($_POST['username'] ?? ''));
        $nama = trim((string)($_POST['nama'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $roleId = (int)($_POST['role_id'] ?? 0);
        $isActive = ((string)($_POST['is_active'] ?? '1') === '1') ? 1 : 0;

        if ($username === '' || $nama === '' || $email === '' || $password === '' || $roleId <= 0) {
            $error = 'Semua field wajib diisi dan role harus dipilih.';
        } else {
            $roleName = '';
            $stmt = $boAuthConn->prepare("SELECT nama_role FROM roles WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param('i', $roleId);
                $stmt->execute();
                $row = $stmt->get_result()->fetch_assoc();
                $roleName = (string)($row['nama_role'] ?? '');
                $stmt->close();
            }

            if ($roleName === '') {
                $error = 'Role tidak valid.';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $createdBy = isset($_SESSION['bo_user_id']) ? (int)$_SESSION['bo_user_id'] : null;
                $createdByParam = $createdBy;

                $boAuthConn->begin_transaction();
                try {
                    $stmt = $boAuthConn->prepare("INSERT INTO users (username, nama, nama_lengkap, email, password, is_active, role, role_id, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    if (!$stmt) {
                        throw new Exception($boAuthConn->error);
                    }
                    $namaLengkap = $nama;
                    $stmt->bind_param('sssssisii', $username, $nama, $namaLengkap, $email, $hash, $isActive, $roleName, $roleId, $createdByParam);
                    if (!$stmt->execute()) {
                        throw new Exception($stmt->error);
                    }
                    $newUserId = (int)$boAuthConn->insert_id;
                    $stmt->close();

                    $stmt = $boAuthConn->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)");
                    if (!$stmt) {
                        throw new Exception($boAuthConn->error);
                    }
                    $stmt->bind_param('ii', $newUserId, $roleId);
                    if (!$stmt->execute()) {
                        throw new Exception($stmt->error);
                    }
                    $stmt->close();

                    $boAuthConn->commit();
                    header('Location: ' . bo_url_for('users.php?success=1'));
                    exit();
                } catch (Exception $e) {
                    $boAuthConn->rollback();
                    $error = 'Gagal menambah user: ' . $e->getMessage();
                }
            }
        }
    } elseif ($action === 'update') {
        $userId = (int)($_POST['user_id'] ?? 0);
        $nama = trim((string)($_POST['nama'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $roleId = (int)($_POST['role_id'] ?? 0);
        $isActive = ((string)($_POST['is_active'] ?? '1') === '1') ? 1 : 0;

        if ($userId <= 0 || $nama === '' || $email === '' || $roleId <= 0) {
            $error = 'Data tidak valid.';
        } else {
            $roleName = '';
            $stmt = $boAuthConn->prepare("SELECT nama_role FROM roles WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param('i', $roleId);
                $stmt->execute();
                $row = $stmt->get_result()->fetch_assoc();
                $roleName = (string)($row['nama_role'] ?? '');
                $stmt->close();
            }

            if ($roleName === '') {
                $error = 'Role tidak valid.';
            } else {
                $boAuthConn->begin_transaction();
                try {
                    if ($password !== '') {
                        $hash = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $boAuthConn->prepare("UPDATE users SET nama = ?, nama_lengkap = ?, email = ?, password = ?, is_active = ?, role = ?, role_id = ? WHERE id = ?");
                        if (!$stmt) throw new Exception($boAuthConn->error);
                        $namaLengkap = $nama;
                        $stmt->bind_param('ssssisii', $nama, $namaLengkap, $email, $hash, $isActive, $roleName, $roleId, $userId);
                    } else {
                        $stmt = $boAuthConn->prepare("UPDATE users SET nama = ?, nama_lengkap = ?, email = ?, is_active = ?, role = ?, role_id = ? WHERE id = ?");
                        if (!$stmt) throw new Exception($boAuthConn->error);
                        $namaLengkap = $nama;
                        $stmt->bind_param('sssisii', $nama, $namaLengkap, $email, $isActive, $roleName, $roleId, $userId);
                    }
                    if (!$stmt->execute()) throw new Exception($stmt->error);
                    $stmt->close();

                    $stmt = $boAuthConn->prepare("DELETE FROM user_roles WHERE user_id = ?");
                    if (!$stmt) throw new Exception($boAuthConn->error);
                    $stmt->bind_param('i', $userId);
                    if (!$stmt->execute()) throw new Exception($stmt->error);
                    $stmt->close();

                    $stmt = $boAuthConn->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)");
                    if (!$stmt) throw new Exception($boAuthConn->error);
                    $stmt->bind_param('ii', $userId, $roleId);
                    if (!$stmt->execute()) throw new Exception($stmt->error);
                    $stmt->close();

                    $boAuthConn->commit();
                    header('Location: ' . bo_url_for('users.php?success=1'));
                    exit();
                } catch (Exception $e) {
                    $boAuthConn->rollback();
                    $error = 'Gagal mengubah user: ' . $e->getMessage();
                }
            }
        }
    }
}

if ($action === 'delete' && isset($_GET['id'])) {
    $userId = (int)$_GET['id'];
    if ($userId > 0) {
        if (isset($_SESSION['bo_user_id']) && (int)$_SESSION['bo_user_id'] === $userId) {
            $error = 'Tidak bisa menghapus user yang sedang login.';
        } else {
            $boAuthConn->begin_transaction();
            try {
                $stmt = $boAuthConn->prepare("DELETE FROM user_roles WHERE user_id = ?");
                if (!$stmt) throw new Exception($boAuthConn->error);
                $stmt->bind_param('i', $userId);
                if (!$stmt->execute()) throw new Exception($stmt->error);
                $stmt->close();

                $stmt = $boAuthConn->prepare("DELETE FROM users WHERE id = ?");
                if (!$stmt) throw new Exception($boAuthConn->error);
                $stmt->bind_param('i', $userId);
                if (!$stmt->execute()) throw new Exception($stmt->error);
                $stmt->close();

                $boAuthConn->commit();
                header('Location: ' . bo_url_for('users.php?success=1'));
                exit();
            } catch (Exception $e) {
                $boAuthConn->rollback();
                $error = 'Gagal menghapus user: ' . $e->getMessage();
            }
        }
    }
}

$editUser = null;
if ($action === 'edit' && isset($_GET['id'])) {
    $userId = (int)$_GET['id'];
    if ($userId > 0) {
        $stmt = $boAuthConn->prepare("SELECT u.*, (SELECT role_id FROM user_roles WHERE user_id = u.id ORDER BY id ASC LIMIT 1) AS primary_role_id FROM users u WHERE u.id = ?");
        if ($stmt) {
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $editUser = $stmt->get_result()->fetch_assoc();
            $stmt->close();
        }
    }
}

$users = [];
$res = $boAuthConn->query("SELECT u.id, u.username, u.nama, u.email, u.is_active, COALESCE(r.nama_role, '') AS role_name
                           FROM users u
                           LEFT JOIN user_roles ur ON u.id = ur.user_id
                           LEFT JOIN roles r ON r.id = ur.role_id
                           GROUP BY u.id
                           ORDER BY u.id DESC");
if ($res) {
    while ($r = $res->fetch_assoc()) {
        $users[] = $r;
    }
}

if (isset($_GET['success']) && $_GET['success'] === '1') {
    $success = 'Perubahan berhasil disimpan.';
}
?>
<?php
$headerActions = '<a class="btn btn-outline-secondary" href="' . htmlspecialchars(bo_url_for('dashboard.php')) . '"><i class="bi bi-grid-1x2 me-1"></i>Dashboard</a>';
bo_render_shell_start([
    'title' => 'Users - Backoffice',
    'page_title' => 'Users',
    'page_subtitle' => 'Kelola akun backoffice, role, dan status aktif pengguna dari satu halaman.',
    'active' => 'users',
    'header_actions' => $headerActions,
]);
?>
<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

<div class="bo-card p-4 mb-4">
    <div class="bo-card-header">
        <div>
            <h3 class="bo-card-title"><?= $editUser ? 'Edit User' : 'Tambah User' ?></h3>
            <div class="bo-card-subtitle">Form manajemen user dengan tampilan yang konsisten dengan dashboard backoffice.</div>
        </div>
    </div>
    <form method="post" class="row g-3">
                <input type="hidden" name="action" value="<?= $editUser ? 'update' : 'create' ?>">
                <?php if ($editUser): ?>
                    <input type="hidden" name="user_id" value="<?= (int)$editUser['id'] ?>">
                <?php endif; ?>

                <div class="col-md-3">
                    <label class="form-label">Username</label>
                    <input class="form-control" name="username" value="<?= htmlspecialchars($editUser['username'] ?? '') ?>" <?= $editUser ? 'readonly' : 'required' ?>>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Nama</label>
                    <input class="form-control" name="nama" value="<?= htmlspecialchars($editUser['nama'] ?? '') ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Email</label>
                    <input class="form-control" type="email" name="email" value="<?= htmlspecialchars($editUser['email'] ?? '') ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Role</label>
                    <?php $selectedRole = (int)($editUser['primary_role_id'] ?? $editUser['role_id'] ?? 0); ?>
                    <select class="form-select" name="role_id" required>
                        <option value="0">Pilih Role</option>
                        <?php foreach ($roles as $r): ?>
                            <option value="<?= (int)$r['id'] ?>" <?= ((int)$r['id'] === $selectedRole) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($r['nama_role']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Password <?= $editUser ? '(kosongkan jika tidak ganti)' : '' ?></label>
                    <input class="form-control" type="password" name="password" <?= $editUser ? '' : 'required' ?>>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <?php $status = (int)($editUser['is_active'] ?? 1); ?>
                    <select class="form-select" name="is_active">
                        <option value="1" <?= $status === 1 ? 'selected' : '' ?>>Aktif</option>
                        <option value="0" <?= $status === 0 ? 'selected' : '' ?>>Nonaktif</option>
                    </select>
                </div>
                <div class="col-md-6 d-flex align-items-end gap-2">
                    <button class="btn btn-primary" type="submit"><i class="bi bi-save me-1"></i>Simpan</button>
                    <?php if ($editUser): ?>
                        <a class="btn btn-outline-secondary" href="<?= htmlspecialchars(bo_url_for('users.php')) ?>">Batal</a>
                    <?php endif; ?>
                </div>
    </form>
</div>

<div class="bo-card p-4">
    <div class="bo-card-header">
        <div>
            <h3 class="bo-card-title">Daftar User</h3>
            <div class="bo-card-subtitle">Ringkasan akun yang sudah terdaftar beserta role dan statusnya.</div>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Username</th>
                    <th>Nama</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th class="text-end">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($users)): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">Belum ada user.</td></tr>
                <?php else: ?>
                    <?php foreach ($users as $u): ?>
                        <tr>
                            <td><?= (int)$u['id'] ?></td>
                            <td><?= htmlspecialchars($u['username']) ?></td>
                            <td><?= htmlspecialchars($u['nama']) ?></td>
                            <td><?= htmlspecialchars($u['email']) ?></td>
                            <td><?= htmlspecialchars($u['role_name']) ?></td>
                            <td>
                                <?php if ((int)$u['is_active'] === 1): ?>
                                    <span class="badge bg-success">Aktif</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Nonaktif</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <a class="btn btn-sm btn-outline-primary" href="<?= htmlspecialchars(bo_url_for('users.php?action=edit&id=' . (int)$u['id'])) ?>">Edit</a>
                                <a class="btn btn-sm btn-outline-danger" href="<?= htmlspecialchars(bo_url_for('users.php?action=delete&id=' . (int)$u['id'])) ?>" onclick="return confirm('Hapus user ini?')">Hapus</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php bo_render_shell_end(); ?>
