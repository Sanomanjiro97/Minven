<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/access_check.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit();
}

function api_json(array $payload, int $statusCode = 200): void {
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit();
}

function api_input(): array {
    $data = $_POST;
    $contentType = (string)($_SERVER['CONTENT_TYPE'] ?? '');
    if (stripos($contentType, 'application/json') !== false) {
        $raw = file_get_contents('php://input');
        if (is_string($raw) && $raw !== '') {
            $json = json_decode($raw, true);
            if (is_array($json)) {
                $data = array_merge($data, $json);
            }
        }
    }
    return $data;
}

function require_login(): void {
    if (!isset($_SESSION['user_id'])) {
        api_json(['success' => false, 'message' => 'Unauthorized'], 401);
    }
}

function require_permission(string $menuName, string $action = 'view'): void {
    require_login();
    if (!function_exists('checkAccess') || !checkAccess($menuName, $action)) {
        api_json(['success' => false, 'message' => 'Forbidden'], 403);
    }
}

function require_backoffice(string $menuName, string $action = 'view'): void {
    require_permission('backoffice', 'view');
    require_permission($menuName, $action);
}

$action = isset($_GET['action']) ? trim((string)$_GET['action']) : '';
if ($action === '') {
    api_json(['success' => false, 'message' => 'Missing action'], 400);
}

$authConn = auth_db_conn();
if (!$authConn) {
    $authConn = $GLOBALS['conn'] ?? null;
}
$mainConn = main_db_conn();

if ($action === 'roles') {
    if (!$authConn) {
        api_json(['success' => false, 'message' => 'Database connection not available'], 500);
    }
    $roles = [];
    $result = $authConn->query("SELECT id, nama_role FROM roles ORDER BY id ASC");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $roles[] = ['id' => (int)$row['id'], 'nama_role' => (string)$row['nama_role']];
        }
    }
    api_json(['success' => true, 'data' => ['roles' => $roles]]);
}

if ($action === 'login') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        api_json(['success' => false, 'message' => 'Method not allowed'], 405);
    }
    if (!$authConn) {
        api_json(['success' => false, 'message' => 'Database connection not available'], 500);
    }

    $in = api_input();
    $username = trim((string)($in['username'] ?? ''));
    $password = (string)($in['password'] ?? '');
    $roleId = (int)($in['role_id'] ?? 0);

    if ($username === '' || $password === '' || $roleId <= 0) {
        api_json(['success' => false, 'message' => 'username, password, role_id wajib diisi'], 400);
    }

    $sql = "SELECT u.*, ur.role_id
        FROM users u
        JOIN user_roles ur ON u.id = ur.user_id
        WHERE u.username = ? AND ur.role_id = ?
        LIMIT 1";
    $stmt = $authConn->prepare($sql);
    if (!$stmt) {
        api_json(['success' => false, 'message' => 'Database error'], 500);
    }
    $stmt->bind_param('si', $username, $roleId);
    $stmt->execute();
    $res = $stmt->get_result();
    $user = $res ? $res->fetch_assoc() : null;
    $stmt->close();

    if (!$user || !isset($user['password']) || !password_verify($password, (string)$user['password'])) {
        api_json(['success' => false, 'message' => 'Username/password/role salah'], 401);
    }
    if (isset($user['is_active']) && (int)$user['is_active'] !== 1) {
        api_json(['success' => false, 'message' => 'User inaktif'], 403);
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['username'] = (string)$user['username'];
    $_SESSION['nama'] = (string)($user['nama'] ?? '');
    $_SESSION['role_id'] = (int)$user['role_id'];

    api_json([
        'success' => true,
        'data' => [
            'user' => [
                'id' => (int)$user['id'],
                'username' => (string)$user['username'],
                'nama' => (string)($user['nama'] ?? ''),
                'role_id' => (int)$user['role_id'],
            ],
        ],
    ]);
}

if ($action === 'logout') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        api_json(['success' => false, 'message' => 'Method not allowed'], 405);
    }
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool)$params['secure'], (bool)$params['httponly']);
    }
    session_destroy();
    api_json(['success' => true, 'message' => 'Logged out']);
}

if ($action === 'me') {
    require_login();
    api_json([
        'success' => true,
        'data' => [
            'user' => [
                'id' => (int)$_SESSION['user_id'],
                'username' => (string)($_SESSION['username'] ?? ''),
                'nama' => (string)($_SESSION['nama'] ?? ''),
                'role_id' => (int)($_SESSION['role_id'] ?? 0),
            ],
        ],
    ]);
}

if ($action === 'barang_list') {
    require_login();
    $conn = $GLOBALS['conn'] ?? null;
    if (!$conn) {
        api_json(['success' => false, 'message' => 'Database connection not available'], 500);
    }

    $q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
    if ($limit <= 0) {
        $limit = 50;
    }
    if ($limit > 200) {
        $limit = 200;
    }

    if ($q !== '') {
        $like = '%' . $q . '%';
        $stmt = $conn->prepare("SELECT id, nama_barang, kode_barang, satuan, stok_minimum FROM barang WHERE nama_barang LIKE ? OR kode_barang LIKE ? ORDER BY id DESC LIMIT ?");
        if (!$stmt) {
            api_json(['success' => false, 'message' => 'Database error'], 500);
        }
        $stmt->bind_param('ssi', $like, $like, $limit);
    } else {
        $stmt = $conn->prepare("SELECT id, nama_barang, kode_barang, satuan, stok_minimum FROM barang ORDER BY id DESC LIMIT ?");
        if (!$stmt) {
            api_json(['success' => false, 'message' => 'Database error'], 500);
        }
        $stmt->bind_param('i', $limit);
    }

    $stmt->execute();
    $res = $stmt->get_result();
    $items = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $items[] = [
                'id' => (int)$row['id'],
                'nama_barang' => (string)($row['nama_barang'] ?? ''),
                'kode_barang' => (string)($row['kode_barang'] ?? ''),
                'satuan' => (string)($row['satuan'] ?? ''),
                'stok_minimum' => isset($row['stok_minimum']) ? (float)$row['stok_minimum'] : 0,
            ];
        }
    }
    $stmt->close();

    api_json(['success' => true, 'data' => ['items' => $items]]);
}

if ($action === 'barang_detail') {
    require_login();
    $conn = $GLOBALS['conn'] ?? null;
    if (!$conn) {
        api_json(['success' => false, 'message' => 'Database connection not available'], 500);
    }
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id <= 0) {
        api_json(['success' => false, 'message' => 'id wajib'], 400);
    }

    $stmt = $conn->prepare("SELECT * FROM barang WHERE id = ? LIMIT 1");
    if (!$stmt) {
        api_json(['success' => false, 'message' => 'Database error'], 500);
    }
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();

    if (!$row) {
        api_json(['success' => false, 'message' => 'Barang tidak ditemukan'], 404);
    }

    api_json(['success' => true, 'data' => ['barang' => $row]]);
}

if ($action === 'bo_dashboard') {
    require_backoffice('backoffice_dashboard', 'view');
    if (!$mainConn) {
        api_json(['success' => false, 'message' => 'Database connection not available'], 500);
    }

    $stats = [
        'barang' => 0,
        'gudang' => 0,
        'stok' => 0,
        'po_total' => 0,
        'po_pending' => 0,
        'po_completed' => 0,
        'direct_total' => 0,
        'pengeluaran_bulan' => 0.0,
        'po_bulan' => 0.0,
    ];

    $q = $mainConn->query("SELECT COUNT(*) AS c FROM barang");
    if ($q && ($r = $q->fetch_assoc())) $stats['barang'] = (int)$r['c'];

    $q = $mainConn->query("SELECT COUNT(*) AS c FROM gudang");
    if ($q && ($r = $q->fetch_assoc())) $stats['gudang'] = (int)$r['c'];

    $q = $mainConn->query("SELECT COALESCE(SUM(jumlah),0) AS s FROM stok");
    if ($q && ($r = $q->fetch_assoc())) $stats['stok'] = (int)$r['s'];

    $q = $mainConn->query("SELECT COALESCE(SUM(total_harga),0) AS t FROM pengeluaran WHERE DATE_FORMAT(tanggal, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')");
    if ($q && ($r = $q->fetch_assoc())) $stats['pengeluaran_bulan'] = (float)$r['t'];

    $q = $mainConn->query("SELECT COALESCE(SUM(total_harga),0) AS t FROM purchase_order WHERE DATE_FORMAT(tanggal, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')");
    if ($q && ($r = $q->fetch_assoc())) $stats['po_bulan'] = (float)$r['t'];

    $q = $mainConn->query("SELECT COUNT(*) AS c FROM purchase_order");
    if ($q && ($r = $q->fetch_assoc())) $stats['po_total'] = (int)$r['c'];

    $q = $mainConn->query("
        SELECT
            SUM(CASE WHEN LOWER(COALESCE(status,'')) LIKE '%pending%' OR LOWER(COALESCE(status,'')) IN ('pending','proses','process','draft','open') THEN 1 ELSE 0 END) AS pending_count,
            SUM(CASE WHEN LOWER(COALESCE(status,'')) LIKE '%selesai%' OR LOWER(COALESCE(status,'')) LIKE '%complete%' OR LOWER(COALESCE(status,'')) IN ('selesai','complete','completed','done','finish') THEN 1 ELSE 0 END) AS completed_count
        FROM purchase_order
    ");
    if ($q && ($r = $q->fetch_assoc())) {
        $stats['po_pending'] = (int)($r['pending_count'] ?? 0);
        $stats['po_completed'] = (int)($r['completed_count'] ?? 0);
    }

    $q = $mainConn->query("SELECT COUNT(*) AS c FROM direct_purchase");
    if ($q && ($r = $q->fetch_assoc())) $stats['direct_total'] = (int)$r['c'];

    api_json(['success' => true, 'data' => ['stats' => $stats]]);
}

if ($action === 'bo_gudang_list') {
    require_backoffice('backoffice_dashboard', 'view');
    if (!$mainConn) {
        api_json(['success' => false, 'message' => 'Database connection not available'], 500);
    }
    $items = [];
    $res = $mainConn->query("SELECT id, nama_gudang FROM gudang ORDER BY nama_gudang");
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $items[] = ['id' => (int)$r['id'], 'nama_gudang' => (string)$r['nama_gudang']];
        }
    }
    api_json(['success' => true, 'data' => ['items' => $items]]);
}

if ($action === 'bo_supplier_list') {
    require_backoffice('backoffice_dashboard', 'view');
    if (!$mainConn) {
        api_json(['success' => false, 'message' => 'Database connection not available'], 500);
    }
    $items = [];
    $res = $mainConn->query("SELECT id, nama_supplier FROM supplier ORDER BY nama_supplier");
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $items[] = ['id' => (int)$r['id'], 'nama_supplier' => (string)$r['nama_supplier']];
        }
    }
    api_json(['success' => true, 'data' => ['items' => $items]]);
}

if ($action === 'bo_users_list') {
    require_backoffice('backoffice_users', 'view');
    if (!$authConn) {
        api_json(['success' => false, 'message' => 'Database connection not available'], 500);
    }
    $items = [];
    $res = $authConn->query("SELECT u.id, u.username, u.nama, u.email, u.is_active, COALESCE(r.nama_role, '') AS role_name,
                           (SELECT role_id FROM user_roles WHERE user_id = u.id ORDER BY id ASC LIMIT 1) AS primary_role_id
                           FROM users u
                           LEFT JOIN user_roles ur ON u.id = ur.user_id
                           LEFT JOIN roles r ON r.id = ur.role_id
                           GROUP BY u.id
                           ORDER BY u.id DESC");
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $items[] = [
                'id' => (int)$r['id'],
                'username' => (string)($r['username'] ?? ''),
                'nama' => (string)($r['nama'] ?? ''),
                'email' => (string)($r['email'] ?? ''),
                'is_active' => (int)($r['is_active'] ?? 0),
                'role_name' => (string)($r['role_name'] ?? ''),
                'role_id' => (int)($r['primary_role_id'] ?? 0),
            ];
        }
    }
    api_json(['success' => true, 'data' => ['items' => $items]]);
}

if ($action === 'bo_users_create') {
    require_backoffice('backoffice_users', 'add');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        api_json(['success' => false, 'message' => 'Method not allowed'], 405);
    }
    if (!$authConn) {
        api_json(['success' => false, 'message' => 'Database connection not available'], 500);
    }
    $in = api_input();
    $username = trim((string)($in['username'] ?? ''));
    $nama = trim((string)($in['nama'] ?? ''));
    $email = trim((string)($in['email'] ?? ''));
    $password = (string)($in['password'] ?? '');
    $roleId = (int)($in['role_id'] ?? 0);
    $isActive = ((string)($in['is_active'] ?? '1') === '1') ? 1 : 0;
    if ($username === '' || $nama === '' || $email === '' || $password === '' || $roleId <= 0) {
        api_json(['success' => false, 'message' => 'Semua field wajib diisi dan role harus dipilih.'], 400);
    }

    $roleName = '';
    $stmt = $authConn->prepare("SELECT nama_role FROM roles WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param('i', $roleId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $roleName = (string)($row['nama_role'] ?? '');
        $stmt->close();
    }
    if ($roleName === '') {
        api_json(['success' => false, 'message' => 'Role tidak valid.'], 400);
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $createdBy = (int)($_SESSION['user_id'] ?? 0);
    $createdByParam = $createdBy > 0 ? $createdBy : null;

    $authConn->begin_transaction();
    try {
        $stmt = $authConn->prepare("INSERT INTO users (username, nama, nama_lengkap, email, password, is_active, role, role_id, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        if (!$stmt) {
            throw new Exception($authConn->error);
        }
        $namaLengkap = $nama;
        $stmt->bind_param('sssssisii', $username, $nama, $namaLengkap, $email, $hash, $isActive, $roleName, $roleId, $createdByParam);
        if (!$stmt->execute()) {
            throw new Exception($stmt->error);
        }
        $newUserId = (int)$authConn->insert_id;
        $stmt->close();

        $stmt = $authConn->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)");
        if (!$stmt) {
            throw new Exception($authConn->error);
        }
        $stmt->bind_param('ii', $newUserId, $roleId);
        if (!$stmt->execute()) {
            throw new Exception($stmt->error);
        }
        $stmt->close();

        $authConn->commit();
        api_json(['success' => true, 'data' => ['id' => $newUserId]]);
    } catch (Exception $e) {
        $authConn->rollback();
        api_json(['success' => false, 'message' => 'Gagal menambah user: ' . $e->getMessage()], 500);
    }
}

if ($action === 'bo_users_update') {
    require_backoffice('backoffice_users', 'edit');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        api_json(['success' => false, 'message' => 'Method not allowed'], 405);
    }
    if (!$authConn) {
        api_json(['success' => false, 'message' => 'Database connection not available'], 500);
    }
    $in = api_input();
    $userId = (int)($in['user_id'] ?? 0);
    $nama = trim((string)($in['nama'] ?? ''));
    $email = trim((string)($in['email'] ?? ''));
    $password = (string)($in['password'] ?? '');
    $roleId = (int)($in['role_id'] ?? 0);
    $isActive = ((string)($in['is_active'] ?? '1') === '1') ? 1 : 0;
    if ($userId <= 0 || $nama === '' || $email === '' || $roleId <= 0) {
        api_json(['success' => false, 'message' => 'Data tidak valid.'], 400);
    }

    $roleName = '';
    $stmt = $authConn->prepare("SELECT nama_role FROM roles WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param('i', $roleId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $roleName = (string)($row['nama_role'] ?? '');
        $stmt->close();
    }
    if ($roleName === '') {
        api_json(['success' => false, 'message' => 'Role tidak valid.'], 400);
    }

    $authConn->begin_transaction();
    try {
        if ($password !== '') {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $authConn->prepare("UPDATE users SET nama = ?, nama_lengkap = ?, email = ?, password = ?, is_active = ?, role = ?, role_id = ? WHERE id = ?");
            if (!$stmt) throw new Exception($authConn->error);
            $namaLengkap = $nama;
            $stmt->bind_param('ssssisii', $nama, $namaLengkap, $email, $hash, $isActive, $roleName, $roleId, $userId);
        } else {
            $stmt = $authConn->prepare("UPDATE users SET nama = ?, nama_lengkap = ?, email = ?, is_active = ?, role = ?, role_id = ? WHERE id = ?");
            if (!$stmt) throw new Exception($authConn->error);
            $namaLengkap = $nama;
            $stmt->bind_param('sssisii', $nama, $namaLengkap, $email, $isActive, $roleName, $roleId, $userId);
        }
        if (!$stmt->execute()) throw new Exception($stmt->error);
        $stmt->close();

        $stmt = $authConn->prepare("DELETE FROM user_roles WHERE user_id = ?");
        if (!$stmt) throw new Exception($authConn->error);
        $stmt->bind_param('i', $userId);
        if (!$stmt->execute()) throw new Exception($stmt->error);
        $stmt->close();

        $stmt = $authConn->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)");
        if (!$stmt) throw new Exception($authConn->error);
        $stmt->bind_param('ii', $userId, $roleId);
        if (!$stmt->execute()) throw new Exception($stmt->error);
        $stmt->close();

        $authConn->commit();
        api_json(['success' => true]);
    } catch (Exception $e) {
        $authConn->rollback();
        api_json(['success' => false, 'message' => 'Gagal mengubah user: ' . $e->getMessage()], 500);
    }
}

if ($action === 'bo_users_delete') {
    require_backoffice('backoffice_users', 'delete');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        api_json(['success' => false, 'message' => 'Method not allowed'], 405);
    }
    if (!$authConn) {
        api_json(['success' => false, 'message' => 'Database connection not available'], 500);
    }
    $in = api_input();
    $userId = (int)($in['user_id'] ?? 0);
    if ($userId <= 0) {
        api_json(['success' => false, 'message' => 'user_id wajib'], 400);
    }
    if ((int)($_SESSION['user_id'] ?? 0) === $userId) {
        api_json(['success' => false, 'message' => 'Tidak bisa menghapus user yang sedang login.'], 400);
    }

    $authConn->begin_transaction();
    try {
        $stmt = $authConn->prepare("DELETE FROM user_roles WHERE user_id = ?");
        if (!$stmt) throw new Exception($authConn->error);
        $stmt->bind_param('i', $userId);
        if (!$stmt->execute()) throw new Exception($stmt->error);
        $stmt->close();

        $stmt = $authConn->prepare("DELETE FROM users WHERE id = ?");
        if (!$stmt) throw new Exception($authConn->error);
        $stmt->bind_param('i', $userId);
        if (!$stmt->execute()) throw new Exception($stmt->error);
        $stmt->close();

        $authConn->commit();
        api_json(['success' => true]);
    } catch (Exception $e) {
        $authConn->rollback();
        api_json(['success' => false, 'message' => 'Gagal menghapus user: ' . $e->getMessage()], 500);
    }
}

if ($action === 'bo_roles_list') {
    require_backoffice('backoffice_roles', 'view');
    if (!$authConn) {
        api_json(['success' => false, 'message' => 'Database connection not available'], 500);
    }
    $items = [];
    $res = $authConn->query("SELECT r.*, COUNT(ur.id) AS total_users
                           FROM roles r
                           LEFT JOIN user_roles ur ON r.id = ur.role_id
                           GROUP BY r.id
                           ORDER BY r.nama_role");
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $items[] = [
                'id' => (int)$r['id'],
                'nama_role' => (string)($r['nama_role'] ?? ''),
                'total_users' => (int)($r['total_users'] ?? 0),
            ];
        }
    }
    api_json(['success' => true, 'data' => ['items' => $items]]);
}

if ($action === 'bo_roles_create') {
    require_backoffice('backoffice_roles', 'add');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        api_json(['success' => false, 'message' => 'Method not allowed'], 405);
    }
    if (!$authConn) {
        api_json(['success' => false, 'message' => 'Database connection not available'], 500);
    }
    $in = api_input();
    $namaRole = trim((string)($in['nama_role'] ?? ''));
    if ($namaRole === '') {
        api_json(['success' => false, 'message' => 'Nama role wajib diisi.'], 400);
    }
    $stmt = $authConn->prepare("INSERT INTO roles (nama_role) VALUES (?)");
    if (!$stmt) {
        api_json(['success' => false, 'message' => 'Database error'], 500);
    }
    $stmt->bind_param('s', $namaRole);
    if (!$stmt->execute()) {
        $msg = $stmt->error ?: 'Gagal menambah role';
        $stmt->close();
        api_json(['success' => false, 'message' => $msg], 500);
    }
    $newId = (int)$authConn->insert_id;
    $stmt->close();
    api_json(['success' => true, 'data' => ['id' => $newId]]);
}

if ($action === 'bo_roles_delete') {
    require_backoffice('backoffice_roles', 'delete');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        api_json(['success' => false, 'message' => 'Method not allowed'], 405);
    }
    if (!$authConn) {
        api_json(['success' => false, 'message' => 'Database connection not available'], 500);
    }
    $in = api_input();
    $roleId = (int)($in['role_id'] ?? 0);
    if ($roleId <= 0) {
        api_json(['success' => false, 'message' => 'role_id wajib'], 400);
    }
    if ($roleId === 1) {
        api_json(['success' => false, 'message' => 'Role Administrator tidak bisa dihapus.'], 400);
    }

    $stmt = $authConn->prepare("SELECT COUNT(*) AS c FROM user_roles WHERE role_id = ?");
    if (!$stmt) {
        api_json(['success' => false, 'message' => 'Database error'], 500);
    }
    $stmt->bind_param('i', $roleId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ((int)($row['c'] ?? 0) > 0) {
        api_json(['success' => false, 'message' => 'Role tidak dapat dihapus karena sedang digunakan.'], 400);
    }

    $authConn->begin_transaction();
    try {
        $stmt = $authConn->prepare("DELETE FROM menu_access WHERE role_id = ?");
        if (!$stmt) throw new Exception($authConn->error);
        $stmt->bind_param('i', $roleId);
        if (!$stmt->execute()) throw new Exception($stmt->error);
        $stmt->close();

        $stmt = $authConn->prepare("DELETE FROM roles WHERE id = ?");
        if (!$stmt) throw new Exception($authConn->error);
        $stmt->bind_param('i', $roleId);
        if (!$stmt->execute()) throw new Exception($stmt->error);
        $stmt->close();

        $authConn->commit();
        api_json(['success' => true]);
    } catch (Exception $e) {
        $authConn->rollback();
        api_json(['success' => false, 'message' => 'Gagal menghapus role: ' . $e->getMessage()], 500);
    }
}

if ($action === 'bo_role_access_get') {
    require_backoffice('backoffice_roles', 'view');
    if (!$authConn) {
        api_json(['success' => false, 'message' => 'Database connection not available'], 500);
    }
    $roleId = isset($_GET['role_id']) ? (int)$_GET['role_id'] : 0;
    if ($roleId <= 0) {
        api_json(['success' => false, 'message' => 'role_id wajib'], 400);
    }
    $stmt = $authConn->prepare("SELECT * FROM roles WHERE id = ?");
    if (!$stmt) {
        api_json(['success' => false, 'message' => 'Database error'], 500);
    }
    $stmt->bind_param('i', $roleId);
    $stmt->execute();
    $role = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$role) {
        api_json(['success' => false, 'message' => 'Role tidak ditemukan'], 404);
    }

    $hasComplete = function_exists('db_has_column') ? db_has_column($authConn, 'menu_access', 'can_complete') : false;
    $hasSetupSplit = function_exists('db_has_column') ? db_has_column($authConn, 'menu_access', 'can_setup_split') : false;
    $permissions = ['can_view', 'can_add', 'can_edit', 'can_delete'];
    if ($hasSetupSplit) $permissions[] = 'can_setup_split';
    if ($hasComplete) $permissions[] = 'can_complete';

    $menuGroups = [
        'Aplikasi Utama' => [
            'dashboard', 'gudang', 'gudang_central', 'gudang_antapani',
            'master', 'barang', 'supplier', 'satuan', 'kategori', 'mapping_items', 'konversi_masukan',
            'transaksi', 'stok_masuk', 'stok_keluar', 'stok_transfer', 'adjustment_in', 'adjustment_out',
            'pembelian', 'purchase_order', 'approve', 'pembelian_direct', 'payment', 'vendor_refund', 'manufacture', 'surat_jalan',
            'laporan', 'laporan_stok', 'po', 'laporan_pembelian', 'laporan_transfer', 'laporan_adjustment_in', 'laporan_adjustment_out',
            'setup', 'reset_stok', 'edit_nama_gudang', 'template_po', 'barcode', 'get_wa', 'menu_access',
            'user', 'absensi', 'tambah_gudang',
        ],
        'Backoffice' => [
            'backoffice', 'backoffice_dashboard', 'backoffice_reports_inventory', 'backoffice_reports_finance', 'backoffice_reports_direct', 'backoffice_reports_po', 'backoffice_reports_inventory_price', 'backoffice_reports_item_movement', 'backoffice_users', 'backoffice_roles',
        ],
    ];

    if ($mainConn) {
        $q = $mainConn->query("SELECT DISTINCT kode_gudang, nama_gudang FROM gudang WHERE kode_gudang IS NOT NULL AND kode_gudang != '' ORDER BY nama_gudang");
        if ($q) {
            while ($g = $q->fetch_assoc()) {
                $kode = trim((string)$g['kode_gudang']);
                if ($kode === '') continue;
                $menuGroups['Aplikasi Utama'][] = 'gudang_' . strtolower($kode);
            }
        }
    }
    $menuGroups['Aplikasi Utama'] = array_values(array_unique($menuGroups['Aplikasi Utama']));
    $menuGroups['Backoffice'] = array_values(array_unique($menuGroups['Backoffice']));

    $existing = [];
    $stmt = $authConn->prepare("SELECT * FROM menu_access WHERE role_id = ?");
    if ($stmt) {
        $stmt->bind_param('i', $roleId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($res && ($r = $res->fetch_assoc())) {
            $existing[(string)$r['menu_name']] = $r;
        }
        $stmt->close();
    }

    api_json([
        'success' => true,
        'data' => [
            'role' => ['id' => (int)$role['id'], 'nama_role' => (string)$role['nama_role']],
            'permissions' => $permissions,
            'menu_groups' => $menuGroups,
            'existing' => $existing,
        ],
    ]);
}

if ($action === 'bo_role_access_set') {
    require_backoffice('backoffice_roles', 'edit');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        api_json(['success' => false, 'message' => 'Method not allowed'], 405);
    }
    if (!$authConn) {
        api_json(['success' => false, 'message' => 'Database connection not available'], 500);
    }
    $in = api_input();
    $roleId = (int)($in['role_id'] ?? 0);
    $access = $in['access'] ?? null;
    if ($roleId <= 0 || !is_array($access)) {
        api_json(['success' => false, 'message' => 'role_id dan access wajib'], 400);
    }

    $hasComplete = function_exists('db_has_column') ? db_has_column($authConn, 'menu_access', 'can_complete') : false;
    $hasSetupSplit = function_exists('db_has_column') ? db_has_column($authConn, 'menu_access', 'can_setup_split') : false;

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

    $authConn->begin_transaction();
    try {
        $stmt = $authConn->prepare("DELETE FROM menu_access WHERE role_id = ?");
        if (!$stmt) throw new Exception($authConn->error);
        $stmt->bind_param('i', $roleId);
        if (!$stmt->execute()) throw new Exception($stmt->error);
        $stmt->close();

        $stmt = $authConn->prepare($sql);
        if (!$stmt) throw new Exception($authConn->error);

        foreach ($access as $menuName => $perms) {
            $menuName = (string)$menuName;
            if ($menuName === '' || !is_array($perms)) continue;
            $canView = !empty($perms['can_view']) ? 1 : 0;
            $canAdd = !empty($perms['can_add']) ? 1 : 0;
            $canEdit = !empty($perms['can_edit']) ? 1 : 0;
            $canDelete = !empty($perms['can_delete']) ? 1 : 0;
            $canSetupSplit = ($hasSetupSplit && !empty($perms['can_setup_split'])) ? 1 : 0;
            $canComplete = ($hasComplete && !empty($perms['can_complete'])) ? 1 : 0;

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
        $authConn->commit();
        api_json(['success' => true]);
    } catch (Exception $e) {
        $authConn->rollback();
        api_json(['success' => false, 'message' => 'Gagal menyimpan hak akses: ' . $e->getMessage()], 500);
    }
}

if ($action === 'bo_reports_inventory') {
    require_backoffice('backoffice_reports_inventory', 'view');
    if (!$mainConn) {
        api_json(['success' => false, 'message' => 'Database connection not available'], 500);
    }

    $gudangId = isset($_GET['gudang_id']) ? (int)$_GET['gudang_id'] : 0;
    $start = (string)($_GET['start'] ?? '');
    $end = (string)($_GET['end'] ?? '');
    $group = (string)($_GET['group'] ?? 'day');
    $qText = trim((string)($_GET['q'] ?? ''));

    if ($start === '' || $end === '') {
        $start = date('Y-m-01');
        $end = date('Y-m-t');
    }
    if (!in_array($group, ['day', 'month', 'year'], true)) {
        $group = 'day';
    }

    $gudangOptions = [];
    $q = $mainConn->query("SELECT id, nama_gudang FROM gudang ORDER BY nama_gudang");
    if ($q) {
        while ($r = $q->fetch_assoc()) {
            $gudangOptions[] = ['id' => (int)$r['id'], 'nama_gudang' => (string)$r['nama_gudang']];
        }
    }

    $stockRows = [];
    $totalQty = 0;
    $totalValue = 0.0;
    $types = '';
    $params = [];
    $where = "1=1";
    if ($gudangId > 0) {
        $where .= " AND gs.gudang_id = ?";
        $types .= 'i';
        $params[] = $gudangId;
    }
    if ($qText !== '') {
        $where .= " AND (b.kode_barang LIKE ? OR b.nama_barang LIKE ?)";
        $types .= 'ss';
        $like = '%' . $qText . '%';
        $params[] = $like;
        $params[] = $like;
    }
    $sql = "SELECT g.nama_gudang, b.kode_barang, b.nama_barang,
                   SUM(gs.stok_awal) AS stok_awal,
                   SUM(gs.stok_terpakai) AS stok_terpakai,
                   SUM(gs.stok_awal - gs.stok_terpakai) AS stok_sisa,
                   b.harga_beli
            FROM gudang_stok gs
            JOIN barang b ON b.id = gs.barang_id
            JOIN gudang g ON g.id = gs.gudang_id
            WHERE $where
            GROUP BY gs.gudang_id, gs.barang_id
            ORDER BY g.nama_gudang ASC, b.nama_barang ASC";
    $stmt = $mainConn->prepare($sql);
    if ($stmt) {
        if ($types !== '') {
            $bindArgs = [];
            $bindArgs[] = $types;
            foreach ($params as $k => $v) $bindArgs[] = $params[$k];
            $refs = [];
            foreach ($bindArgs as $k => $v) $refs[$k] = &$bindArgs[$k];
            call_user_func_array([$stmt, 'bind_param'], $refs);
        }
        $stmt->execute();
        $res = $stmt->get_result();
        while ($res && ($r = $res->fetch_assoc())) {
            $stockRows[] = $r;
            $qty = (int)($r['stok_sisa'] ?? 0);
            $totalQty += $qty;
            $totalValue += ((float)($r['harga_beli'] ?? 0)) * $qty;
        }
        $stmt->close();
    }

    $changeRows = [];
    $bucket = "DATE(h.created_at)";
    if ($group === 'month') {
        $bucket = "DATE_FORMAT(h.created_at, '%Y-%m')";
    } elseif ($group === 'year') {
        $bucket = "DATE_FORMAT(h.created_at, '%Y')";
    }

    $types = 'ss';
    $params = [$start, $end];
    $where = "DATE(h.created_at) BETWEEN ? AND ?";
    $groupBy = "$bucket, b.id";
    $selectGudang = '';
    if ($gudangId > 0) {
        $where .= " AND h.gudang_id = ?";
        $types .= 'i';
        $params[] = $gudangId;
        $groupBy .= ", g.id";
        $selectGudang = ", g.nama_gudang";
    }
    if ($qText !== '') {
        $where .= " AND (b.kode_barang LIKE ? OR b.nama_barang LIKE ?)";
        $types .= 'ss';
        $like = '%' . $qText . '%';
        $params[] = $like;
        $params[] = $like;
    }
    $sql = "SELECT $bucket AS periode,
                   b.kode_barang, b.nama_barang
                   $selectGudang,
                   SUM(CASE WHEN h.jenis_perubahan IN ('masuk','transfer_in') THEN h.jumlah_perubahan ELSE 0 END) AS qty_masuk,
                   SUM(CASE WHEN h.jenis_perubahan IN ('keluar','transfer_out') THEN h.jumlah_perubahan ELSE 0 END) AS qty_keluar,
                   SUM(CASE WHEN h.jenis_perubahan = 'reset' THEN 1 ELSE 0 END) AS jumlah_reset
            FROM gudang_stok_history h
            JOIN barang b ON b.id = h.barang_id
            JOIN gudang g ON g.id = h.gudang_id
            WHERE $where
            GROUP BY $groupBy
            ORDER BY periode DESC, b.nama_barang ASC";
    $stmt = $mainConn->prepare($sql);
    if ($stmt) {
        $bindArgs = [];
        $bindArgs[] = $types;
        foreach ($params as $k => $v) $bindArgs[] = $params[$k];
        $refs = [];
        foreach ($bindArgs as $k => $v) $refs[$k] = &$bindArgs[$k];
        call_user_func_array([$stmt, 'bind_param'], $refs);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($res && ($r = $res->fetch_assoc())) {
            $changeRows[] = $r;
        }
        $stmt->close();
    }

    api_json([
        'success' => true,
        'data' => [
            'filters' => ['gudang_id' => $gudangId, 'start' => $start, 'end' => $end, 'group' => $group, 'q' => $qText],
            'gudang_options' => $gudangOptions,
            'stock_rows' => $stockRows,
            'change_rows' => $changeRows,
            'total_qty' => $totalQty,
            'total_value_hpp' => $totalValue,
        ],
    ]);
}

if ($action === 'bo_reports_finance') {
    require_backoffice('backoffice_reports_finance', 'view');
    if (!$mainConn) {
        api_json(['success' => false, 'message' => 'Database connection not available'], 500);
    }
    $start = (string)($_GET['start'] ?? '');
    $end = (string)($_GET['end'] ?? '');
    $supplierId = isset($_GET['supplier_id']) ? (int)$_GET['supplier_id'] : 0;
    if ($start === '' || $end === '') {
        $start = date('Y-m-01');
        $end = date('Y-m-t');
    }

    $suppliers = [];
    $res = $mainConn->query("SELECT id, nama_supplier FROM supplier ORDER BY nama_supplier");
    if ($res) {
        while ($r = $res->fetch_assoc()) $suppliers[] = ['id' => (int)$r['id'], 'nama_supplier' => (string)$r['nama_supplier']];
    }

    $sum = [
        'pengeluaran' => 0.0,
        'po' => 0.0,
        'direct_purchase' => 0.0,
        'pemasukan_refund' => 0.0,
    ];
    $pengeluaranRows = [];
    $pemasukanRows = [];

    $stmt = $mainConn->prepare("SELECT COALESCE(SUM(total_harga),0) AS t FROM pengeluaran WHERE tanggal BETWEEN ? AND ?");
    if ($stmt) {
        $stmt->bind_param('ss', $start, $end);
        $stmt->execute();
        $r = $stmt->get_result()->fetch_assoc();
        $sum['pengeluaran'] = (float)($r['t'] ?? 0);
        $stmt->close();
    }

    $stmt = $mainConn->prepare("SELECT COALESCE(SUM(total_harga),0) AS t FROM purchase_order WHERE tanggal BETWEEN ? AND ?" . ($supplierId > 0 ? " AND supplier_id = ?" : ""));
    if ($stmt) {
        if ($supplierId > 0) {
            $stmt->bind_param('ssi', $start, $end, $supplierId);
        } else {
            $stmt->bind_param('ss', $start, $end);
        }
        $stmt->execute();
        $r = $stmt->get_result()->fetch_assoc();
        $sum['po'] = (float)($r['t'] ?? 0);
        $stmt->close();
    }

    $stmt = $mainConn->prepare("SELECT COALESCE(SUM(total_harga),0) AS t FROM direct_purchase WHERE tanggal BETWEEN ? AND ?" . ($supplierId > 0 ? " AND supplier_id = ?" : ""));
    if ($stmt) {
        if ($supplierId > 0) {
            $stmt->bind_param('ssi', $start, $end, $supplierId);
        } else {
            $stmt->bind_param('ss', $start, $end);
        }
        $stmt->execute();
        $r = $stmt->get_result()->fetch_assoc();
        $sum['direct_purchase'] = (float)($r['t'] ?? 0);
        $stmt->close();
    }

    $res = $mainConn->query("SHOW TABLES LIKE 'vendor_refund'");
    $hasRefund = $res && $res->num_rows > 0;
    if ($hasRefund) {
        $sqlRefundSum = "
            SELECT COALESCE(SUM(vrd.qty * COALESCE(pod.harga_satuan, b.harga_beli, 0)), 0) AS t
            FROM vendor_refund vr
            JOIN vendor_refund_detail vrd ON vrd.vendor_refund_id = vr.id
            LEFT JOIN (
                SELECT purchase_order_id, barang_id, AVG(harga_satuan) AS harga_satuan
                FROM detail_purchase_order
                GROUP BY purchase_order_id, barang_id
            ) pod ON pod.purchase_order_id = vr.purchase_order_id AND pod.barang_id = vrd.barang_id
            LEFT JOIN barang b ON b.id = vrd.barang_id
            WHERE vr.tanggal BETWEEN ? AND ?
        ";
        if ($supplierId > 0) {
            $sqlRefundSum .= " AND vr.supplier_id = ?";
        }
        $stmt = $mainConn->prepare($sqlRefundSum);
        if ($stmt) {
            if ($supplierId > 0) {
                $stmt->bind_param('ssi', $start, $end, $supplierId);
            } else {
                $stmt->bind_param('ss', $start, $end);
            }
            $stmt->execute();
            $r = $stmt->get_result()->fetch_assoc();
            $sum['pemasukan_refund'] = (float)($r['t'] ?? 0);
            $stmt->close();
        }
    }

    $sqlPengeluaran = "
        SELECT tanggal, tipe, nomor, pihak, total_harga
        FROM (
            SELECT p.tanggal AS tanggal, 'Pengeluaran' AS tipe, p.no_pengeluaran AS nomor, '' AS pihak, p.total_harga AS total_harga
            FROM pengeluaran p
            WHERE p.tanggal BETWEEN ? AND ?
            UNION ALL
            SELECT po.tanggal AS tanggal, 'PO' AS tipe, po.no_po AS nomor, s.nama_supplier AS pihak, po.total_harga AS total_harga
            FROM purchase_order po
            LEFT JOIN supplier s ON s.id = po.supplier_id
            WHERE po.tanggal BETWEEN ? AND ?
            " . ($supplierId > 0 ? " AND po.supplier_id = ?" : "") . "
            UNION ALL
            SELECT dp.tanggal AS tanggal, 'Pembelian Direct' AS tipe, dp.no_transaksi AS nomor, dp.nama_toko AS pihak, dp.total_harga AS total_harga
            FROM direct_purchase dp
            WHERE dp.tanggal BETWEEN ? AND ?
            " . ($supplierId > 0 ? " AND dp.supplier_id = ?" : "") . "
        ) x
        ORDER BY tanggal DESC
    ";
    $stmt = $mainConn->prepare($sqlPengeluaran);
    if ($stmt) {
        if ($supplierId > 0) {
            $stmt->bind_param('ssssissi', $start, $end, $start, $end, $supplierId, $start, $end, $supplierId);
        } else {
            $stmt->bind_param('ssssss', $start, $end, $start, $end, $start, $end);
        }
        $stmt->execute();
        $res = $stmt->get_result();
        while ($res && ($r = $res->fetch_assoc())) $pengeluaranRows[] = $r;
        $stmt->close();
    }

    if ($hasRefund) {
        $sqlRefundList = "
            SELECT vr.id, vr.no_refund, vr.tanggal, COALESCE(s.nama_supplier,'') AS nama_supplier,
                   COALESCE(SUM(vrd.qty),0) AS total_qty,
                   COALESCE(SUM(vrd.qty * COALESCE(pod.harga_satuan, b.harga_beli, 0)), 0) AS total_nilai
            FROM vendor_refund vr
            LEFT JOIN supplier s ON s.id = vr.supplier_id
            LEFT JOIN vendor_refund_detail vrd ON vrd.vendor_refund_id = vr.id
            LEFT JOIN (
                SELECT purchase_order_id, barang_id, AVG(harga_satuan) AS harga_satuan
                FROM detail_purchase_order
                GROUP BY purchase_order_id, barang_id
            ) pod ON pod.purchase_order_id = vr.purchase_order_id AND pod.barang_id = vrd.barang_id
            LEFT JOIN barang b ON b.id = vrd.barang_id
            WHERE vr.tanggal BETWEEN ? AND ?
            " . ($supplierId > 0 ? " AND vr.supplier_id = ?" : "") . "
            GROUP BY vr.id
            ORDER BY vr.tanggal DESC, vr.id DESC
        ";
        $stmt = $mainConn->prepare($sqlRefundList);
        if ($stmt) {
            if ($supplierId > 0) {
                $stmt->bind_param('ssi', $start, $end, $supplierId);
            } else {
                $stmt->bind_param('ss', $start, $end);
            }
            $stmt->execute();
            $res = $stmt->get_result();
            while ($res && ($r = $res->fetch_assoc())) $pemasukanRows[] = $r;
            $stmt->close();
        }
    }

    $totalPengeluaran = $sum['pengeluaran'] + $sum['po'] + $sum['direct_purchase'];
    $totalPemasukan = $sum['pemasukan_refund'];
    $saldo = $totalPemasukan - $totalPengeluaran;

    api_json([
        'success' => true,
        'data' => [
            'filters' => ['start' => $start, 'end' => $end, 'supplier_id' => $supplierId],
            'suppliers' => $suppliers,
            'summary' => [
                'pengeluaran' => $totalPengeluaran,
                'pemasukan_refund' => $totalPemasukan,
                'saldo' => $saldo,
                'breakdown' => $sum,
            ],
            'pengeluaran_rows' => $pengeluaranRows,
            'pemasukan_rows' => $pemasukanRows,
        ],
    ]);
}

if ($action === 'bo_reports_po') {
    require_backoffice('backoffice_reports_po', 'view');
    if (!$mainConn) {
        api_json(['success' => false, 'message' => 'Database connection not available'], 500);
    }
    $start = (string)($_GET['start'] ?? '');
    $end = (string)($_GET['end'] ?? '');
    $supplierId = isset($_GET['supplier_id']) ? (int)$_GET['supplier_id'] : 0;
    $q = trim((string)($_GET['q'] ?? ''));
    if ($start === '' || $end === '') {
        $start = date('Y-m-01');
        $end = date('Y-m-t');
    }

    $suppliers = [];
    $res = $mainConn->query("SELECT id, nama_supplier FROM supplier ORDER BY nama_supplier");
    if ($res) {
        while ($row = $res->fetch_assoc()) $suppliers[] = ['id' => (int)$row['id'], 'nama_supplier' => (string)$row['nama_supplier']];
    }

    $pos = [];
    $totalPo = 0.0;
    $detailStatusExists = function_exists('db_has_column') ? db_has_column($mainConn, 'detail_purchase_order', 'status') : true;
    $detailFilter = $detailStatusExists ? "AND (d2.status IS NULL OR d2.status != 'rejected')" : "";

    $params = [];
    $types = '';
    $where = "po.tanggal BETWEEN ? AND ?";
    $types .= 'ss';
    $params[] = $start;
    $params[] = $end;
    $where .= " AND po.status != 'menunggu'";
    if ($supplierId > 0) {
        $where .= " AND po.supplier_id = ?";
        $types .= 'i';
        $params[] = $supplierId;
    }
    $joinDetail = '';
    if ($q !== '') {
        $joinDetail = "LEFT JOIN detail_purchase_order d ON d.purchase_order_id = po.id
                       LEFT JOIN barang b ON b.id = d.barang_id";
        $where .= " AND (b.kode_barang LIKE ? OR b.nama_barang LIKE ?)";
        $types .= 'ss';
        $like = '%' . $q . '%';
        $params[] = $like;
        $params[] = $like;
    }

    $sql = "SELECT po.id, po.no_po, po.tanggal,
                   (
                       SELECT COALESCE(SUM(d2.jumlah), 0)
                       FROM detail_purchase_order d2
                       WHERE d2.purchase_order_id = po.id
                       $detailFilter
                   ) AS total_item,
                   (
                       SELECT COALESCE(SUM(COALESCE(d2.total_harga, (d2.jumlah * d2.harga_satuan))), 0)
                       FROM detail_purchase_order d2
                       WHERE d2.purchase_order_id = po.id
                       $detailFilter
                   ) AS total_harga,
                   po.status,
                   s.nama_supplier
            FROM purchase_order po
            LEFT JOIN supplier s ON s.id = po.supplier_id
            $joinDetail
            WHERE $where
            GROUP BY po.id
            ORDER BY po.tanggal DESC, po.id DESC";
    $stmt = $mainConn->prepare($sql);
    if ($stmt) {
        $bindArgs = [];
        $bindArgs[] = $types;
        foreach ($params as $k => $v) $bindArgs[] = $params[$k];
        $refs = [];
        foreach ($bindArgs as $k => $v) $refs[$k] = &$bindArgs[$k];
        call_user_func_array([$stmt, 'bind_param'], $refs);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($res && ($row = $res->fetch_assoc())) {
            $pos[] = $row;
            $totalPo += (float)($row['total_harga'] ?? 0);
        }
        $stmt->close();
    }

    api_json([
        'success' => true,
        'data' => [
            'filters' => ['start' => $start, 'end' => $end, 'supplier_id' => $supplierId, 'q' => $q],
            'suppliers' => $suppliers,
            'items' => $pos,
            'total_po' => $totalPo,
        ],
    ]);
}

if ($action === 'bo_reports_po_detail') {
    require_backoffice('backoffice_reports_po', 'view');
    if (!$mainConn) {
        api_json(['success' => false, 'message' => 'Database connection not available'], 500);
    }
    $poId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($poId <= 0) {
        api_json(['success' => false, 'message' => 'id wajib'], 400);
    }

    $header = null;
    $items = [];
    $total = 0.0;
    $totalItem = 0;
    $detailStatusExists = function_exists('db_has_column') ? db_has_column($mainConn, 'detail_purchase_order', 'status') : true;

    $stmt = $mainConn->prepare("
        SELECT po.id, po.no_po, po.tanggal, po.total_item, po.total_harga, po.status, po.keterangan,
               s.nama_supplier, s.kode_supplier, s.telepon, s.email
        FROM purchase_order po
        LEFT JOIN supplier s ON s.id = po.supplier_id
        WHERE po.id = ? AND po.status != 'menunggu'
        LIMIT 1
    ");
    if ($stmt) {
        $stmt->bind_param('i', $poId);
        $stmt->execute();
        $header = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
    if (!$header) {
        api_json(['success' => false, 'message' => 'PO tidak ditemukan'], 404);
    }

    $sqlItems = "
        SELECT d.jumlah, d.harga_satuan, d.total_harga, d.keterangan_detail,
               b.kode_barang, b.nama_barang
        FROM detail_purchase_order d
        LEFT JOIN barang b ON b.id = d.barang_id
        WHERE d.purchase_order_id = ?
    ";
    if ($detailStatusExists) {
        $sqlItems .= " AND (d.status IS NULL OR d.status != 'rejected')";
    }
    $sqlItems .= " ORDER BY b.nama_barang ASC, d.id ASC";

    $stmt = $mainConn->prepare($sqlItems);
    if ($stmt) {
        $stmt->bind_param('i', $poId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($res && ($r = $res->fetch_assoc())) {
            $items[] = $r;
            $total += (float)($r['total_harga'] ?? ((float)$r['harga_satuan'] * (int)$r['jumlah']));
            $totalItem += (int)($r['jumlah'] ?? 0);
        }
        $stmt->close();
    }

    $header['total_harga'] = $total;
    $header['total_item'] = $totalItem;

    api_json(['success' => true, 'data' => ['header' => $header, 'items' => $items]]);
}

if ($action === 'bo_reports_direct') {
    require_backoffice('backoffice_reports_direct', 'view');
    if (!$mainConn) {
        api_json(['success' => false, 'message' => 'Database connection not available'], 500);
    }
    $start = (string)($_GET['start'] ?? '');
    $end = (string)($_GET['end'] ?? '');
    $supplierId = isset($_GET['supplier_id']) ? (int)$_GET['supplier_id'] : 0;
    $q = trim((string)($_GET['q'] ?? ''));
    if ($start === '' || $end === '') {
        $start = date('Y-m-01');
        $end = date('Y-m-t');
    }

    $suppliers = [];
    $res = $mainConn->query("SELECT id, nama_supplier FROM supplier ORDER BY nama_supplier");
    if ($res) {
        while ($row = $res->fetch_assoc()) $suppliers[] = ['id' => (int)$row['id'], 'nama_supplier' => (string)$row['nama_supplier']];
    }

    $directs = [];
    $totalDirect = 0.0;
    $params = [];
    $types = '';
    $where = "dp.tanggal BETWEEN ? AND ?";
    $types .= 'ss';
    $params[] = $start;
    $params[] = $end;
    if ($supplierId > 0) {
        $where .= " AND dp.supplier_id = ?";
        $types .= 'i';
        $params[] = $supplierId;
    }
    $joinDetail = '';
    if ($q !== '') {
        $joinDetail = "LEFT JOIN detail_direct_purchase d ON d.direct_purchase_id = dp.id
                       LEFT JOIN barang b ON b.id = d.barang_id";
        $where .= " AND (b.kode_barang LIKE ? OR b.nama_barang LIKE ? OR d.keterangan LIKE ?)";
        $types .= 'sss';
        $like = '%' . $q . '%';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }

    $sql = "SELECT dp.id, dp.no_transaksi, dp.tanggal, dp.total_item, dp.total_harga, dp.status, dp.nama_toko,
                   s.nama_supplier
            FROM direct_purchase dp
            LEFT JOIN supplier s ON s.id = dp.supplier_id
            $joinDetail
            WHERE $where
            GROUP BY dp.id
            ORDER BY dp.tanggal DESC, dp.id DESC";
    $stmt = $mainConn->prepare($sql);
    if ($stmt) {
        $bindArgs = [];
        $bindArgs[] = $types;
        foreach ($params as $k => $v) $bindArgs[] = $params[$k];
        $refs = [];
        foreach ($bindArgs as $k => $v) $refs[$k] = &$bindArgs[$k];
        call_user_func_array([$stmt, 'bind_param'], $refs);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($res && ($row = $res->fetch_assoc())) {
            $directs[] = $row;
            $totalDirect += (float)($row['total_harga'] ?? 0);
        }
        $stmt->close();
    }

    api_json([
        'success' => true,
        'data' => [
            'filters' => ['start' => $start, 'end' => $end, 'supplier_id' => $supplierId, 'q' => $q],
            'suppliers' => $suppliers,
            'items' => $directs,
            'total_direct' => $totalDirect,
        ],
    ]);
}

if ($action === 'bo_reports_direct_detail') {
    require_backoffice('backoffice_reports_direct', 'view');
    if (!$mainConn) {
        api_json(['success' => false, 'message' => 'Database connection not available'], 500);
    }
    $directId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($directId <= 0) {
        api_json(['success' => false, 'message' => 'id wajib'], 400);
    }

    $header = null;
    $items = [];
    $total = 0.0;

    $stmt = $mainConn->prepare("
        SELECT dp.id, dp.no_transaksi, dp.tanggal, dp.total_item, dp.total_harga, dp.status, dp.keterangan, dp.nama_toko,
               s.nama_supplier, s.kode_supplier, s.telepon, s.email
        FROM direct_purchase dp
        LEFT JOIN supplier s ON s.id = dp.supplier_id
        WHERE dp.id = ?
        LIMIT 1
    ");
    if ($stmt) {
        $stmt->bind_param('i', $directId);
        $stmt->execute();
        $header = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
    if (!$header) {
        api_json(['success' => false, 'message' => 'Direct tidak ditemukan'], 404);
    }

    $stmt = $mainConn->prepare("
        SELECT d.jumlah, d.harga_satuan, (d.jumlah * d.harga_satuan) AS total_harga, d.keterangan AS keterangan_detail,
               b.kode_barang, b.nama_barang
        FROM detail_direct_purchase d
        LEFT JOIN barang b ON b.id = d.barang_id
        WHERE d.direct_purchase_id = ?
        ORDER BY b.nama_barang ASC, d.id ASC
    ");
    if ($stmt) {
        $stmt->bind_param('i', $directId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($res && ($r = $res->fetch_assoc())) {
            $items[] = $r;
            $total += (float)($r['total_harga'] ?? ((float)($r['harga_satuan'] ?? 0) * (int)($r['jumlah'] ?? 0)));
        }
        $stmt->close();
    }
    $header['total_harga'] = $total;

    api_json(['success' => true, 'data' => ['header' => $header, 'items' => $items]]);
}

if ($action === 'bo_reports_inventory_price') {
    require_backoffice('backoffice_reports_inventory_price', 'view');
    if (!$mainConn) {
        api_json(['success' => false, 'message' => 'Database connection not available'], 500);
    }
    $qText = trim((string)($_GET['q'] ?? ''));
    $start = (string)($_GET['start'] ?? '');
    $end = (string)($_GET['end'] ?? '');
    if ($start === '' || $end === '') {
        $start = date('Y-m-01');
        $end = date('Y-m-t');
    }

    $rows = [];
    $totalQty = 0;
    $totalValue = 0.0;
    $totalValuePo = 0.0;
    $totalHargaBeliSatuan = 0.0;
    $totalHargaPoSatuan = 0.0;
    $totalKeluar = 0;

    $types = 'ss';
    $params = [$start, $end];
    $where = "1=1";
    if ($qText !== '') {
        $where .= " AND (b.kode_barang LIKE ? OR b.nama_barang LIKE ?)";
        $types .= 'ss';
        $like = '%' . $qText . '%';
        $params[] = $like;
        $params[] = $like;
    }

    $sql = "SELECT b.kode_barang, b.nama_barang, b.harga_beli, b.harga_po,
                   COALESCE(SUM(gs.stok_awal - gs.stok_terpakai), 0) AS stok_sisa,
                   COALESCE(m.qty_keluar, 0) AS qty_keluar,
                   COALESCE(m.qty_masuk, 0) AS qty_masuk
            FROM barang b
            LEFT JOIN gudang_stok gs ON gs.barang_id = b.id
            LEFT JOIN (
                SELECT h.barang_id,
                       SUM(CASE WHEN h.jenis_perubahan IN ('keluar','transfer_out') THEN h.jumlah_perubahan ELSE 0 END) AS qty_keluar,
                       SUM(CASE WHEN h.jenis_perubahan IN ('masuk','transfer_in') THEN h.jumlah_perubahan ELSE 0 END) AS qty_masuk
                FROM gudang_stok_history h
                WHERE DATE(h.created_at) BETWEEN ? AND ?
                GROUP BY h.barang_id
            ) m ON m.barang_id = b.id
            WHERE $where
            GROUP BY b.id
            ORDER BY b.nama_barang ASC";
    $stmt = $mainConn->prepare($sql);
    if ($stmt) {
        $bindArgs = [];
        $bindArgs[] = $types;
        foreach ($params as $k => $v) $bindArgs[] = $params[$k];
        $refs = [];
        foreach ($bindArgs as $k => $v) $refs[$k] = &$bindArgs[$k];
        call_user_func_array([$stmt, 'bind_param'], $refs);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($res && ($r = $res->fetch_assoc())) {
            $rows[] = $r;
            $qty = (int)($r['stok_sisa'] ?? 0);
            $totalQty += $qty;
            $hb = (float)($r['harga_beli'] ?? 0);
            $hp = (float)($r['harga_po'] ?? 0);
            $totalValue += $qty * $hb;
            $totalValuePo += $qty * $hp;
            $totalHargaBeliSatuan += $hb;
            $totalHargaPoSatuan += $hp;
            $totalKeluar += (int)($r['qty_keluar'] ?? 0);
        }
        $stmt->close();
    }

    api_json([
        'success' => true,
        'data' => [
            'filters' => ['start' => $start, 'end' => $end, 'q' => $qText],
            'items' => $rows,
            'totals' => [
                'total_qty' => $totalQty,
                'total_keluar' => $totalKeluar,
                'total_nilai_hpp' => $totalValue,
                'total_nilai_po' => $totalValuePo,
                'total_harga_beli_satuan' => $totalHargaBeliSatuan,
                'total_harga_po_satuan' => $totalHargaPoSatuan,
            ],
        ],
    ]);
}

if ($action === 'bo_reports_item_movement') {
    require_backoffice('backoffice_reports_item_movement', 'view');
    if (!$mainConn) {
        api_json(['success' => false, 'message' => 'Database connection not available'], 500);
    }
    $start = (string)($_GET['start'] ?? '');
    $end = (string)($_GET['end'] ?? '');
    $mode = (string)($_GET['mode'] ?? 'frequent');
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
    $qText = trim((string)($_GET['q'] ?? ''));
    $gudangId = isset($_GET['gudang_id']) ? (int)$_GET['gudang_id'] : 0;

    if ($start === '' || $end === '') {
        $start = date('Y-m-01');
        $end = date('Y-m-t');
    }
    if (!in_array($mode, ['frequent', 'slow'], true)) {
        $mode = 'frequent';
    }
    if ($limit <= 0 || $limit > 500) {
        $limit = 20;
    }

    $gudangOptions = [];
    $res = $mainConn->query("SELECT id, nama_gudang FROM gudang ORDER BY nama_gudang");
    if ($res) {
        while ($r = $res->fetch_assoc()) $gudangOptions[] = ['id' => (int)$r['id'], 'nama_gudang' => (string)$r['nama_gudang']];
    }

    $types = 'ss';
    $params = [$start, $end];
    $whereMovement = "DATE(h.created_at) BETWEEN ? AND ?";
    if ($gudangId > 0) {
        $whereMovement .= " AND h.gudang_id = ?";
        $types .= 'i';
        $params[] = $gudangId;
    }

    $whereItem = "1=1";
    if ($qText !== '') {
        $whereItem .= " AND (b.kode_barang LIKE ? OR b.nama_barang LIKE ?)";
        $types .= 'ss';
        $like = '%' . $qText . '%';
        $params[] = $like;
        $params[] = $like;
    }

    $order = $mode === 'slow' ? 'qty_keluar ASC' : 'qty_keluar DESC';
    $sql = "
        SELECT b.kode_barang, b.nama_barang,
               COALESCE(m.qty_keluar, 0) AS qty_keluar,
               COALESCE(m.qty_masuk, 0) AS qty_masuk,
               COALESCE(s.qty_stok, 0) AS qty_stok
        FROM barang b
        LEFT JOIN (
            SELECT h.barang_id,
                   SUM(CASE WHEN h.jenis_perubahan IN ('keluar','transfer_out') THEN h.jumlah_perubahan ELSE 0 END) AS qty_keluar,
                   SUM(CASE WHEN h.jenis_perubahan IN ('masuk','transfer_in') THEN h.jumlah_perubahan ELSE 0 END) AS qty_masuk
            FROM gudang_stok_history h
            WHERE $whereMovement
            GROUP BY h.barang_id
        ) m ON m.barang_id = b.id
        LEFT JOIN (
            SELECT gs.barang_id, SUM(gs.stok_awal - gs.stok_terpakai) AS qty_stok
            FROM gudang_stok gs
            " . ($gudangId > 0 ? "WHERE gs.gudang_id = " . (int)$gudangId : "") . "
            GROUP BY gs.barang_id
        ) s ON s.barang_id = b.id
        WHERE $whereItem
        ORDER BY $order, b.nama_barang ASC
        LIMIT " . (int)$limit . "
    ";

    $rows = [];
    $stmt = $mainConn->prepare($sql);
    if ($stmt) {
        $bindArgs = [];
        $bindArgs[] = $types;
        foreach ($params as $k => $v) $bindArgs[] = $params[$k];
        $refs = [];
        foreach ($bindArgs as $k => $v) $refs[$k] = &$bindArgs[$k];
        call_user_func_array([$stmt, 'bind_param'], $refs);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($res && ($r = $res->fetch_assoc())) $rows[] = $r;
        $stmt->close();
    }

    api_json([
        'success' => true,
        'data' => [
            'filters' => ['start' => $start, 'end' => $end, 'gudang_id' => $gudangId, 'mode' => $mode, 'limit' => $limit, 'q' => $qText],
            'gudang_options' => $gudangOptions,
            'items' => $rows,
        ],
    ]);
}

api_json(['success' => false, 'message' => 'Action tidak dikenali'], 404);
