<?php
// Grant full access to a role by parsing available menus from user/role_access.php
// Local-only safety
require_once __DIR__ . '/../config.php';

if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    echo "Forbidden: local use only.";
    exit;
}

if (!($conn instanceof mysqli)) {
    echo "DB connection not available.";
    exit;
}

$role_id = (int)($_GET['role_id'] ?? 1);
$role_file = __DIR__ . '/../user/role_access.php';
if (!is_file($role_file)) {
    echo "role_access.php not found.";
    exit;
}

$content = file_get_contents($role_file);
if ($content === false) {
    echo "Failed to read role_access.php";
    exit;
}

// Extract main menu keys: patterns like 'dashboard' => [
preg_match_all("/'([a-z0-9_]+)'\s*=>\s*\[/i", $content, $m_main);
$main_keys = $m_main[1] ?? [];

// Extract submenu blocks then keys inside them
$submenu_keys = [];
preg_match_all("/'submenus'\s*=>\s*\[(.*?)\n\s*\]", $content, $m_sub, PREG_SET_ORDER | PREG_DOTALL);
foreach ($m_sub as $block) {
    $subtext = $block[1];
    preg_match_all("/'([a-z0-9_]+)'\s*=>", $subtext, $m2);
    foreach ($m2[1] as $k) $submenu_keys[] = $k;
}

$all_keys = array_unique(array_merge($main_keys, $submenu_keys));
sort($all_keys);

if (empty($all_keys)) {
    echo "No menu keys found to insert.\n";
    exit;
}

// Backup current menu_access
$now = date('Ymd_His');
$backupFile = __DIR__ . "/menu_access_restore_{$now}.sql";
$fh = fopen($backupFile, 'wb');
if ($fh) {
    $row = $conn->query("SHOW CREATE TABLE menu_access");
    if ($row && ($r = $row->fetch_assoc())) fwrite($fh, $r['Create Table'] . ";\n\n");
    $res = $conn->query("SELECT * FROM menu_access");
    if ($res && $res instanceof mysqli_result) {
        while ($r = $res->fetch_assoc()) {
            $cols = array_map(function($c){return "`$c`";}, array_keys($r));
            $vals = array_map(function($v) use ($conn){ return is_null($v)?'NULL':"'".$conn->real_escape_string((string)$v)."'"; }, array_values($r));
            fwrite($fh, "INSERT INTO `menu_access` (".implode(',', $cols).") VALUES (".implode(',', $vals).");\n");
        }
    }
    fclose($fh);
    echo "Backup created: " . basename($backupFile) . "<br>\n";
} else {
    echo "Failed to create backup file. Aborting.\n"; exit;
}

// Detect optional columns
$has_setup_split = $conn->query("SHOW COLUMNS FROM menu_access LIKE 'can_setup_split'")->num_rows > 0;
$has_can_complete = $conn->query("SHOW COLUMNS FROM menu_access LIKE 'can_complete'")->num_rows > 0;

// Build SQL
if ($has_setup_split && $has_can_complete) {
    $sql = "INSERT INTO menu_access (role_id, menu_name, can_view, can_add, can_edit, can_delete, can_setup_split, can_complete) VALUES (?, ?, 1,1,1,1,1,1) ON DUPLICATE KEY UPDATE can_view=1, can_add=1, can_edit=1, can_delete=1, can_setup_split=1, can_complete=1";
} elseif ($has_can_complete) {
    $sql = "INSERT INTO menu_access (role_id, menu_name, can_view, can_add, can_edit, can_delete, can_complete) VALUES (?, ?, 1,1,1,1,1) ON DUPLICATE KEY UPDATE can_view=1, can_add=1, can_edit=1, can_delete=1, can_complete=1";
} elseif ($has_setup_split) {
    $sql = "INSERT INTO menu_access (role_id, menu_name, can_view, can_add, can_edit, can_delete, can_setup_split) VALUES (?, ?, 1,1,1,1,1) ON DUPLICATE KEY UPDATE can_view=1, can_add=1, can_edit=1, can_delete=1, can_setup_split=1";
} else {
    $sql = "INSERT INTO menu_access (role_id, menu_name, can_view, can_add, can_edit, can_delete) VALUES (?, ?, 1,1,1,1) ON DUPLICATE KEY UPDATE can_view=1, can_add=1, can_edit=1, can_delete=1";
}

$stmt = $conn->prepare($sql);
if (!$stmt) { echo "Prepare failed: " . $conn->error; exit; }

$count = 0;
foreach ($all_keys as $menu_name) {
    $stmt->bind_param('is', $role_id, $menu_name);
    if (!$stmt->execute()) {
        echo "Failed for {$menu_name}: " . $stmt->error . "<br>\n";
    } else {
        $count++;
    }
}
$stmt->close();

echo "Inserted/updated $count menu entries for role_id={$role_id}.\n";
echo "Reload <a href=\"/minven_pro/user/role_access.php?id={$role_id}\">role_access.php?id={$role_id}</a> to verify.";

?>
