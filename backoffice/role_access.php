<?php
require_once __DIR__ . '/_init.php';
bo_require_login();

$roleId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($roleId <= 0) {
    header('Location: ' . bo_url_for('roles.php'));
    exit();
}

$stmt = $boAuthConn->prepare("SELECT * FROM roles WHERE id = ?");
$stmt->bind_param('i', $roleId);
$stmt->execute();
$role = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$role) {
    header('Location: ' . bo_url_for('roles.php'));
    exit();
}

$menuGroups = [
    'Aplikasi Utama' => [
        'dashboard', 'gudang', 'gudang_central', 'gudang_antapani',
        'master', 'barang', 'supplier', 'satuan', 'kategori', 'mapping_items', 'konversi_masukan',
        'transaksi', 'stok_masuk', 'stok_keluar', 'stok_transfer', 'adjustment_in', 'adjustment_out',
        'pembelian', 'purchase_order', 'approve', 'pembelian_direct', 'payment', 'vendor_refund', 'manufacture', 'surat_jalan',
        'laporan', 'laporan_stok', 'po', 'laporan_pembelian', 'laporan_transfer', 'laporan_adjustment_in', 'laporan_adjustment_out',
        'setup', 'reset_stok', 'edit_nama_gudang', 'template_po', 'barcode', 'get_wa', 'menu_access',
        'user', 'absensi', 'tambah_gudang'
    ],
    'Backoffice' => [
        'backoffice_dashboard', 'backoffice_reports_inventory', 'backoffice_reports_finance', 'backoffice_reports_direct', 'backoffice_users', 'backoffice_roles'
    ],
];

if ($boMainConn) {
    $q = $boMainConn->query("SELECT DISTINCT kode_gudang, nama_gudang FROM gudang WHERE kode_gudang IS NOT NULL AND kode_gudang != '' ORDER BY nama_gudang");
    if ($q) {
        while ($g = $q->fetch_assoc()) {
            $kode = trim((string)$g['kode_gudang']);
            $nama = trim((string)$g['nama_gudang']);
            if ($kode === '') continue;
            $menuKey = 'gudang_' . strtolower($kode);
            $menuGroups['Aplikasi Utama'][] = $menuKey;
        }
    }
}

$menuGroups['Aplikasi Utama'] = array_values(array_unique($menuGroups['Aplikasi Utama']));
$menuGroups['Backoffice'] = array_values(array_unique($menuGroups['Backoffice']));

$hasComplete = function_exists('db_has_column') ? db_has_column($boAuthConn, 'menu_access', 'can_complete') : false;
$hasSetupSplit = function_exists('db_has_column') ? db_has_column($boAuthConn, 'menu_access', 'can_setup_split') : false;

$permissions = ['can_view', 'can_add', 'can_edit', 'can_delete'];
if ($hasSetupSplit) $permissions[] = 'can_setup_split';
if ($hasComplete) $permissions[] = 'can_complete';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $access = isset($_POST['access']) && is_array($_POST['access']) ? $_POST['access'] : [];
    $boAuthConn->begin_transaction();
    try {
        $stmt = $boAuthConn->prepare("DELETE FROM menu_access WHERE role_id = ?");
        if (!$stmt) throw new Exception($boAuthConn->error);
        $stmt->bind_param('i', $roleId);
        if (!$stmt->execute()) throw new Exception($stmt->error);
        $stmt->close();

        $cols = ['role_id', 'menu_name', 'can_view', 'can_add', 'can_edit', 'can_delete'];
        if ($hasSetupSplit) $cols[] = 'can_setup_split';
        if ($hasComplete) $cols[] = 'can_complete';

        $placeholders = implode(',', array_fill(0, count($cols), '?'));
        $colSql = implode(',', $cols);
        $updates = [];
        foreach ($cols as $c) {
            if ($c === 'role_id' || $c === 'menu_name') continue;
            $updates[] = "$c = VALUES($c)";
        }
        $updateSql = !empty($updates) ? (' ON DUPLICATE KEY UPDATE ' . implode(', ', $updates)) : '';
        $sql = "INSERT INTO menu_access ($colSql) VALUES ($placeholders)$updateSql";
        $stmt = $boAuthConn->prepare($sql);
        if (!$stmt) throw new Exception($boAuthConn->error);

        foreach ($access as $menuName => $perms) {
            $menuName = (string)$menuName;
            if ($menuName === '') continue;
            $canView = isset($perms['can_view']) ? 1 : 0;
            $canAdd = isset($perms['can_add']) ? 1 : 0;
            $canEdit = isset($perms['can_edit']) ? 1 : 0;
            $canDelete = isset($perms['can_delete']) ? 1 : 0;
            $canSetupSplit = $hasSetupSplit && isset($perms['can_setup_split']) ? 1 : 0;
            $canComplete = $hasComplete && isset($perms['can_complete']) ? 1 : 0;

            $types = 'isiiii';
            $params = [$roleId, $menuName, $canView, $canAdd, $canEdit, $canDelete];

            if ($hasSetupSplit) {
                $types .= 'i';
                $params[] = $canSetupSplit;
            }
            if ($hasComplete) {
                $types .= 'i';
                $params[] = $canComplete;
            }

            $bindArgs = [];
            $bindArgs[] = $types;
            foreach ($params as $k => $v) {
                $bindArgs[] = $params[$k];
            }
            $refs = [];
            foreach ($bindArgs as $k => $v) {
                $refs[$k] = &$bindArgs[$k];
            }
            call_user_func_array([$stmt, 'bind_param'], $refs);
            if (!$stmt->execute()) throw new Exception($stmt->error);
        }

        $stmt->close();
        $boAuthConn->commit();
        header('Location: ' . bo_url_for('role_access.php?id=' . $roleId . '&success=1'));
        exit();
    } catch (Exception $e) {
        $boAuthConn->rollback();
        $error = $e->getMessage();
    }
}

$existing = [];
$stmt = $boAuthConn->prepare("SELECT * FROM menu_access WHERE role_id = ?");
$stmt->bind_param('i', $roleId);
$stmt->execute();
$res = $stmt->get_result();
while ($res && ($r = $res->fetch_assoc())) {
    $existing[$r['menu_name']] = $r;
}
$stmt->close();

$success = (isset($_GET['success']) && $_GET['success'] === '1') ? 'Hak akses berhasil disimpan.' : null;
?>
<?php
$headerActions = '<a class="btn btn-outline-secondary" href="' . htmlspecialchars(bo_url_for('roles.php')) . '"><i class="bi bi-arrow-left me-1"></i>Kembali</a>';
bo_render_shell_start([
    'title' => 'Role Akses - Backoffice',
    'page_title' => 'Role Akses',
    'page_subtitle' => 'Atur permission untuk role ' . $role['nama_role'] . ' dengan layout yang sama seperti halaman lain.',
    'active' => 'role-access',
    'header_actions' => $headerActions,
]);
?>
<?php if (!empty($error)): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

<form method="post">
    <?php foreach ($menuGroups as $groupName => $menus): ?>
        <div class="bo-card p-4 mb-4">
            <div class="bo-card-header">
                <div>
                    <h3 class="bo-card-title"><?= htmlspecialchars($groupName) ?></h3>
                    <div class="bo-card-subtitle">Permission untuk menu dalam kelompok <?= htmlspecialchars($groupName) ?>.</div>
                </div>
            </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Menu</th>
                                    <?php foreach ($permissions as $perm): ?>
                                        <th class="text-center"><?= htmlspecialchars(str_replace('can_', '', $perm)) ?></th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($menus as $menuName): ?>
                                    <?php
                                        $row = $existing[$menuName] ?? null;
                                    ?>
                                    <tr>
                                        <td><?= htmlspecialchars($menuName) ?></td>
                                        <?php foreach ($permissions as $perm): ?>
                                            <?php $checked = $row && isset($row[$perm]) && (int)$row[$perm] === 1; ?>
                                            <td class="text-center">
                                                <input type="checkbox" name="access[<?= htmlspecialchars($menuName) ?>][<?= htmlspecialchars($perm) ?>]" <?= $checked ? 'checked' : '' ?>>
                                            </td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
        </div>
    <?php endforeach; ?>

    <button class="btn btn-primary w-100" type="submit">Simpan Hak Akses</button>
</form>
<?php bo_render_shell_end(); ?>
