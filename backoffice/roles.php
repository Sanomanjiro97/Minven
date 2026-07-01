<?php
require_once __DIR__ . '/_init.php';
bo_require_login();

$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nama_role'])) {
    $namaRole = trim((string)$_POST['nama_role']);
    if ($namaRole === '') {
        $error = 'Nama role wajib diisi.';
    } else {
        $stmt = $boAuthConn->prepare("INSERT INTO roles (nama_role) VALUES (?)");
        if ($stmt) {
            $stmt->bind_param('s', $namaRole);
            if ($stmt->execute()) {
                header('Location: ' . bo_url_for('roles.php?success=1'));
                exit();
            }
            $error = 'Gagal menambah role: ' . $stmt->error;
            $stmt->close();
        } else {
            $error = 'Gagal menyiapkan query.';
        }
    }
}

if (isset($_GET['action'], $_GET['id']) && $_GET['action'] === 'delete') {
    $roleId = (int)$_GET['id'];
    if ($roleId === 1) {
        $error = 'Role Administrator tidak bisa dihapus.';
    } elseif ($roleId > 0) {
        $stmt = $boAuthConn->prepare("SELECT COUNT(*) AS c FROM user_roles WHERE role_id = ?");
        if ($stmt) {
            $stmt->bind_param('i', $roleId);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ((int)($row['c'] ?? 0) > 0) {
                $error = 'Role tidak dapat dihapus karena sedang digunakan.';
            } else {
                $boAuthConn->begin_transaction();
                try {
                    $stmt = $boAuthConn->prepare("DELETE FROM menu_access WHERE role_id = ?");
                    if (!$stmt) throw new Exception($boAuthConn->error);
                    $stmt->bind_param('i', $roleId);
                    if (!$stmt->execute()) throw new Exception($stmt->error);
                    $stmt->close();

                    $stmt = $boAuthConn->prepare("DELETE FROM roles WHERE id = ?");
                    if (!$stmt) throw new Exception($boAuthConn->error);
                    $stmt->bind_param('i', $roleId);
                    if (!$stmt->execute()) throw new Exception($stmt->error);
                    $stmt->close();

                    $boAuthConn->commit();
                    header('Location: ' . bo_url_for('roles.php?success=1'));
                    exit();
                } catch (Exception $e) {
                    $boAuthConn->rollback();
                    $error = 'Gagal menghapus role: ' . $e->getMessage();
                }
            }
        }
    }
}

$roles = [];
$res = $boAuthConn->query("SELECT r.*, COUNT(ur.id) AS total_users
                           FROM roles r
                           LEFT JOIN user_roles ur ON r.id = ur.role_id
                           GROUP BY r.id
                           ORDER BY r.nama_role");
if ($res) {
    while ($r = $res->fetch_assoc()) {
        $roles[] = $r;
    }
}

if (isset($_GET['success']) && $_GET['success'] === '1') {
    $success = 'Perubahan berhasil disimpan.';
}
?>
<?php
$headerActions = '<a class="btn btn-outline-secondary" href="' . htmlspecialchars(bo_url_for('dashboard.php')) . '"><i class="bi bi-grid-1x2 me-1"></i>Dashboard</a>';
bo_render_shell_start([
    'title' => 'Roles - Backoffice',
    'page_title' => 'Roles',
    'page_subtitle' => 'Kelola role dan akses backoffice dengan tampilan yang seragam seperti dashboard.',
    'active' => 'roles',
    'header_actions' => $headerActions,
]);
?>
<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

<div class="bo-card p-4 mb-4">
    <div class="bo-card-header">
        <div>
            <h3 class="bo-card-title">Tambah Role</h3>
            <div class="bo-card-subtitle">Buat role baru untuk membedakan hak akses user backoffice.</div>
        </div>
    </div>
    <form method="post" class="row g-3">
                <div class="col-md-8">
                    <input class="form-control" name="nama_role" placeholder="Nama role" required>
                </div>
                <div class="col-md-4">
                    <button class="btn btn-primary w-100" type="submit"><i class="bi bi-plus-circle me-1"></i>Tambah</button>
                </div>
    </form>
</div>

<div class="bo-card p-4">
    <div class="bo-card-header">
        <div>
            <h3 class="bo-card-title">Daftar Role</h3>
            <div class="bo-card-subtitle">Lihat jumlah user per role dan akses ke pengaturan permission.</div>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nama Role</th>
                    <th class="text-end">Total User</th>
                    <th class="text-end">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($roles)): ?>
                    <tr><td colspan="4" class="text-center text-muted py-4">Belum ada role.</td></tr>
                <?php else: ?>
                    <?php foreach ($roles as $r): ?>
                        <tr>
                            <td><?= (int)$r['id'] ?></td>
                            <td><?= htmlspecialchars($r['nama_role']) ?></td>
                            <td class="text-end"><?= number_format((int)$r['total_users']) ?></td>
                            <td class="text-end">
                                <a class="btn btn-sm btn-outline-primary" href="<?= htmlspecialchars(bo_url_for('role_access.php?id=' . (int)$r['id'])) ?>">Role Akses</a>
                                <a class="btn btn-sm btn-outline-danger" href="<?= htmlspecialchars(bo_url_for('roles.php?action=delete&id=' . (int)$r['id'])) ?>" onclick="return confirm('Hapus role ini?')">Hapus</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php bo_render_shell_end(); ?>
