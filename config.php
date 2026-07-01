<?php
define('DB_HOST', getenv('MINVEN_DB_HOST') !== false ? getenv('MINVEN_DB_HOST') : 'localhost');
define('DB_USER', getenv('MINVEN_DB_USER') !== false ? getenv('MINVEN_DB_USER') : 'root');
define('DB_PASS', getenv('MINVEN_DB_PASS') !== false ? getenv('MINVEN_DB_PASS') : '');
define('DB_NAME', getenv('MINVEN_DB_NAME') !== false ? getenv('MINVEN_DB_NAME') : 'minven_pro');
define('AUTH_DB_NAME', getenv('MINVEN_AUTH_DB_NAME') !== false ? getenv('MINVEN_AUTH_DB_NAME') : 'minven_backoffice');

// Konfigurasi Email (SMTP)
define('SMTP_HOST', getenv('MINVEN_SMTP_HOST') !== false ? getenv('MINVEN_SMTP_HOST') : 'smtp.gmail.com'); // Host SMTP, contoh: smtp.gmail.com
define('SMTP_USER', getenv('MINVEN_SMTP_USER') !== false ? getenv('MINVEN_SMTP_USER') : 'akagamisans2@gmail.com'); // Email pengirim
define('SMTP_PASS', getenv('MINVEN_SMTP_PASS') !== false ? getenv('MINVEN_SMTP_PASS') : 'your_app_password'); // Password aplikasi (bukan password email biasa)
define('SMTP_PORT', getenv('MINVEN_SMTP_PORT') !== false ? (int)getenv('MINVEN_SMTP_PORT') : 587); // Port SMTP (587 untuk TLS, 465 untuk SSL)
define('SMTP_SECURE', getenv('MINVEN_SMTP_SECURE') !== false ? getenv('MINVEN_SMTP_SECURE') : 'tls'); // Enkripsi (tls atau ssl)
define('FROM_EMAIL', getenv('MINVEN_FROM_EMAIL') !== false ? getenv('MINVEN_FROM_EMAIL') : 'akagamisans2@gmail.com'); // Email yang muncul di "From"
define('FROM_NAME', getenv('MINVEN_FROM_NAME') !== false ? getenv('MINVEN_FROM_NAME') : 'MINVEN Admin'); // Nama yang muncul di "From"

// Konfigurasi WhatsApp API (contoh untuk development)
define('WA_API_URL', getenv('MINVEN_WA_API_URL') !== false ? getenv('MINVEN_WA_API_URL') : 'http://localhost:3000/send-message'); // URL WhatsApp API endpoint
define('WA_API_KEY', getenv('MINVEN_WA_API_KEY') !== false ? getenv('MINVEN_WA_API_KEY') : 'your_api_key_here'); // API key jika diperlukan

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

if (!isset($authConn)) {
    $authConn = null;
    if (defined('AUTH_DB_NAME') && AUTH_DB_NAME) {
        $tmp = @new mysqli(DB_HOST, DB_USER, DB_PASS, AUTH_DB_NAME);
        if ($tmp && !$tmp->connect_error) {
            $authConn = $tmp;
        }
    }
}

if (!function_exists('main_db_conn')) {
    function main_db_conn() {
        return $GLOBALS['conn'] ?? null;
    }
}

if (!function_exists('auth_db_conn')) {
    function auth_db_conn() {
        if (isset($GLOBALS['authConn']) && $GLOBALS['authConn'] instanceof mysqli && !$GLOBALS['authConn']->connect_error) {
            return $GLOBALS['authConn'];
        }
        return $GLOBALS['conn'] ?? null;
    }
}

if (!defined('BASE_PATH')) {
    $envBase = getenv('MINVEN_BASE_PATH');
    if ($envBase !== false && $envBase !== '') {
        $base = rtrim((string)$envBase, '/') . '/';
    } else {
        $projectRoot = @realpath(__DIR__);
        $projectRoot = $projectRoot ? str_replace('\\', '/', $projectRoot) : null;
        $scriptFile = isset($_SERVER['SCRIPT_FILENAME']) ? str_replace('\\', '/', (string)$_SERVER['SCRIPT_FILENAME']) : '';
        $scriptName = isset($_SERVER['SCRIPT_NAME']) ? (string)$_SERVER['SCRIPT_NAME'] : '';
        $base = '/';
        if ($projectRoot && $scriptFile && $scriptName && strpos($scriptFile, $projectRoot) === 0) {
            $relFromRoot = ltrim(substr($scriptFile, strlen($projectRoot)), '/');
            $sn = str_replace('\\', '/', $scriptName);
            if ($relFromRoot !== '' && strlen($sn) >= strlen($relFromRoot)) {
                if (substr($sn, -strlen($relFromRoot)) === $relFromRoot) {
                    $base = substr($sn, 0, strlen($sn) - strlen($relFromRoot));
                    $base = $base === '' ? '/' : rtrim($base, '/') . '/';
                }
            }
        }
        // Fallback ke metode DOCUMENT_ROOT jika perlu
        if ($base === '/' || $base === '') {
            $docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? @realpath($_SERVER['DOCUMENT_ROOT']) : null;
            $docRoot = $docRoot ? str_replace('\\', '/', $docRoot) : null;
            if ($docRoot && $projectRoot && strpos($projectRoot, $docRoot) === 0) {
                $rel = substr($projectRoot, strlen($docRoot));
                $rel = trim($rel, '/');
                $base = '/' . ($rel !== '' ? $rel . '/' : '');
            } elseif ($projectRoot) {
                $base = '/' . trim(basename($projectRoot), '/') . '/';
            }
        }
    }
    define('BASE_PATH', $base);
}

if (!function_exists('url_for')) {
    function url_for($path = '') {
        $path = ltrim((string)$path, '/\\');
        return BASE_PATH . $path;
    }
}

function db_has_column($conn, $table, $column) {
    static $cache = [];
    $key = $table . ':' . $column;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    $sql = "SELECT 1
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = ?
            AND COLUMN_NAME = ?
        LIMIT 1";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        $cache[$key] = false;
        return false;
    }

    $stmt->bind_param("ss", $table, $column);
    $stmt->execute();
    $result = $stmt->get_result();
    $exists = $result && $result->num_rows > 0;
    $stmt->close();

    $cache[$key] = $exists;
    return $exists;
}

function db_drop_column_if_exists($conn, $table, $column) {
    if (!preg_match('/^[A-Za-z0-9_]+$/', (string)$table) || !preg_match('/^[A-Za-z0-9_]+$/', (string)$column)) {
        return false;
    }
    if (!db_has_column($conn, $table, $column)) {
        return false;
    }
    $sql = "ALTER TABLE `" . $table . "` DROP COLUMN `" . $column . "`";
    return $conn->query($sql) === true;
}

function db_has_index($conn, $table, $index_name) {
    static $cache = [];
    $key = $table . ':idx:' . $index_name;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    $sql = "SELECT 1
        FROM INFORMATION_SCHEMA.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = ?
            AND INDEX_NAME = ?
        LIMIT 1";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        $cache[$key] = false;
        return false;
    }
    $stmt->bind_param("ss", $table, $index_name);
    $stmt->execute();
    $result = $stmt->get_result();
    $exists = $result && $result->num_rows > 0;
    $stmt->close();
    $cache[$key] = $exists;
    return $exists;
}

function ensure_report_indexes_on_init($conn) {
    static $done = false;
    if ($done) return;
    $done = true;
    if (!$conn) return;

    try {
        $tables = [
            'gudang_stok_history' => [
                'idx_gsh_created_at' => "CREATE INDEX idx_gsh_created_at ON gudang_stok_history (created_at)",
                'idx_gsh_gudang_barang_created_at' => "CREATE INDEX idx_gsh_gudang_barang_created_at ON gudang_stok_history (gudang_id, barang_id, created_at, id)",
            ],
        ];

        foreach ($tables as $table => $indexes) {
            $check = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($table) . "'");
            if (!$check || $check->num_rows === 0) {
                continue;
            }
            foreach ($indexes as $idxName => $ddl) {
                if (!db_has_index($conn, $table, $idxName)) {
                    @$conn->query($ddl);
                }
            }
        }
    } catch (Exception $e) {
    }
}

// Auto-fix for menu access integrity (prevents missing menus issue)
function ensure_menu_access_on_init($conn) {
    static $done = false;
    if ($done) return;
    $done = true;
    
    if (!$conn) return;
    
    try {
        // Check menu_access table exists and has records
        $result = $conn->query("SELECT COUNT(*) as cnt FROM menu_access");
        if (!$result) return;
        
        $row = $result->fetch_assoc();
        // Hanya seed awal jika tabel benar-benar kosong (bukan saat admin menonaktifkan menu)
        if ((int)$row['cnt'] > 0) return;
        
        // Need to rebuild menu_access
        $conn->query("TRUNCATE TABLE menu_access");
        
        $roles_result = $conn->query("SELECT id FROM roles");
        if (!$roles_result) return;
        
        $roles = [];
        while ($r = $roles_result->fetch_assoc()) {
            $roles[] = $r['id'];
        }
        
        if (empty($roles)) return;
        
        $menus = ['dashboard', 'gudang', 'gudang_central', 'gudang_antapani', 'master', 'barang', 'supplier', 'satuan', 'kategori', 'mapping_items', 'konversi_masukan', 'transaksi', 'stok_masuk', 'stok_keluar', 'stok_transfer', 'adjustment_in', 'adjustment_out', 'pembelian', 'purchase_order', 'approve', 'pembelian_direct', 'payment', 'vendor_refund', 'manufacture', 'laporan', 'laporan_pembelian', 'laporan_transfer', 'laporan_adjustment_in', 'laporan_adjustment_out', 'order_management', 'cashier', 'backoffice', 'backoffice_dashboard', 'backoffice_reports_inventory', 'backoffice_reports_finance', 'backoffice_reports_po', 'backoffice_reports_inventory_price', 'backoffice_reports_item_movement', 'backoffice_users', 'backoffice_roles', 'setup', 'user', 'reset_stok', 'edit_nama_gudang', 'template_po', 'barcode', 'get_wa', 'menu_access', 'absensi', 'tambah_gudang'];
        
        foreach ($roles as $role_id) {
            $is_admin = ($role_id == 1);
            foreach ($menus as $menu) {
                $can_view = 1;
                $can_add = $is_admin ? 1 : 0;
                $can_edit = $is_admin ? 1 : 0;
                $can_delete = $is_admin ? 1 : 0;
                
                if (in_array($menu, ['dashboard', 'gudang', 'gudang_central', 'gudang_antapani', 'laporan', 'absensi'])) {
                    $can_view = 1;
                }
                
                $menu_esc = $conn->real_escape_string($menu);
                $sql = "INSERT INTO menu_access (role_id, menu_name, can_view, can_add, can_edit, can_delete, can_complete, can_setup_split) VALUES ($role_id, '$menu_esc', $can_view, $can_add, $can_edit, $can_delete, 0, 0)";
                $conn->query($sql);
            }
        }
        
        $conn->query("UPDATE menu_access SET can_view = 1, can_add = 1, can_edit = 1, can_delete = 1 WHERE role_id = 1");
    } catch (Exception $e) {
        // Silently fail if auto-fix doesn't work
    }
}

// Initialize menu access integrity check
if (isset($conn) && $conn && !$conn->connect_error) {
    $authInitConn = auth_db_conn();
    if ($authInitConn) {
        ensure_menu_access_on_init($authInitConn);
    }
    ensure_report_indexes_on_init($conn);
    
    // Also ensure current user has menu access (for session restore)
    if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['user_id']) && isset($_SESSION['role_id'])) {
        @include_once __DIR__ . '/includes/ensure_menu_access.php';
    }
}

if (!function_exists('auto_reset_stok_harian_on_request')) {
    function auto_reset_stok_harian_on_request($conn) {
        static $done = false;
        if ($done) return;
        $done = true;

        if (!$conn || !($conn instanceof mysqli) || $conn->connect_error) {
            return;
        }

        date_default_timezone_set('Asia/Jakarta');
        $tz = new DateTimeZone('Asia/Jakarta');
        $now = new DateTimeImmutable('now', $tz);
        $today = $now->format('Y-m-d');

        $checkTable = $conn->query("SHOW TABLES LIKE 'setup_reset_stok'");
        if (!$checkTable || $checkTable->num_rows === 0) {
            return;
        }

        if (!db_has_column($conn, 'setup_reset_stok', 'last_reset')) {
            @$conn->query("ALTER TABLE setup_reset_stok ADD COLUMN last_reset DATETIME DEFAULT NULL AFTER is_active");
        }

        $summary = $conn->query("SELECT 
                MIN(TIME_FORMAT(jam_reset, '%H:%i')) as jam_reset,
                MAX(last_reset) as last_reset,
                SUM(is_active = 1) as active_count
            FROM setup_reset_stok");
        if (!$summary) {
            return;
        }

        $s = $summary->fetch_assoc();
        if (!$s || (int)($s['active_count'] ?? 0) <= 0) {
            return;
        }

        $jam_reset = isset($s['jam_reset']) ? (string)$s['jam_reset'] : '';
        if (!preg_match('/^\d{2}:\d{2}$/', $jam_reset)) {
            return;
        }

        try {
            $scheduledToday = new DateTimeImmutable($today . ' ' . $jam_reset . ':00', $tz);
        } catch (Exception $e) {
            return;
        }

        if ($now < $scheduledToday) {
            return;
        }

        $lockKey = 'minven_reset_stok_harian';
        $lockRes = $conn->query("SELECT GET_LOCK('" . $conn->real_escape_string($lockKey) . "', 1) as locked");
        $locked = 0;
        if ($lockRes && ($lr = $lockRes->fetch_assoc())) {
            $locked = (int)($lr['locked'] ?? 0);
        }
        if ($locked !== 1) {
            return;
        }

        $lastReset = null;
        if (!empty($s['last_reset'])) {
            try {
                $lastReset = new DateTimeImmutable((string)$s['last_reset'], $tz);
            } catch (Exception $e) {
                $lastReset = null;
            }
        }
        if ($lastReset && $lastReset >= $scheduledToday) {
            $conn->query("SELECT RELEASE_LOCK('" . $conn->real_escape_string($lockKey) . "')");
            return;
        }

        $setParts = [
            "stok_awal = (stok_awal - stok_terpakai)",
            "stok_terpakai = 0",
        ];
        if (db_has_column($conn, 'gudang_stok', 'stok_sisa')) {
            $setParts[] = "stok_sisa = (stok_awal - stok_terpakai)";
        }
        if (db_has_column($conn, 'gudang_stok', 'updated_at')) {
            $setParts[] = "updated_at = NOW()";
        }
        if (db_has_column($conn, 'gudang_stok', 'last_reset')) {
            $setParts[] = "last_reset = NOW()";
        }
        if (db_has_column($conn, 'gudang_stok', 'modified_by')) {
            $setParts[] = "modified_by = 0";
        }
        $updateAllSql = "UPDATE gudang_stok SET " . implode(", ", $setParts);
        $updateAllStmt = $conn->prepare($updateAllSql);
        if (!$updateAllStmt) {
            $conn->query("SELECT RELEASE_LOCK('" . $conn->real_escape_string($lockKey) . "')");
            return;
        }

        try {
            $conn->begin_transaction();

            $executed = $updateAllStmt->execute();
            if ($executed === false) {
                throw new Exception("Execute failed: " . $updateAllStmt->error);
            }
            $resetCount = (int)$updateAllStmt->affected_rows;

            $updateSetup = $conn->prepare("UPDATE setup_reset_stok SET last_reset = NOW()");
            if ($updateSetup) {
                $updateSetup->execute();
                $updateSetup->close();
            }

            $conn->commit();

            if (session_status() === PHP_SESSION_ACTIVE) {
                $_SESSION['reset_notification'] = [
                    'message' => 'Reset stok harian berhasil (' . $jam_reset . ')',
                    'count' => $resetCount,
                    'type' => 'success',
                    'time' => time()
                ];
            }
        } catch (Exception $e) {
            $conn->rollback();
            error_log("Auto reset stok harian gagal: " . $e->getMessage());
        } finally {
            $updateAllStmt->close();
            $conn->query("SELECT RELEASE_LOCK('" . $conn->real_escape_string($lockKey) . "')");
        }
    }
}

if (PHP_SAPI !== 'cli' && isset($conn) && $conn && !$conn->connect_error) {
    auto_reset_stok_harian_on_request($conn);
}
