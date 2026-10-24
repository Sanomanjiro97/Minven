<?php
// Local repair tool for `menu_access` table
// Safety: only allow access from localhost
require_once __DIR__ . '/../config.php';

if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    echo "Forbidden: this tool is local-only.";
    exit;
}

if (!isset($conn) || !($conn instanceof mysqli)) {
    echo "Database connection not available. Check config.php.";
    exit;
}

$action = $_GET['action'] ?? '';

function render_header()
{
    echo "<meta charset=\"utf-8\"><title>Repair menu_access</title><style>body{font-family:Segoe UI,Arial;margin:18px}</style>";
}

function escape_val($conn, $v)
{
    if ($v === null) return 'NULL';
    return "'" . $conn->real_escape_string((string)$v) . "'";
}

render_header();
echo "<h2>menu_access Repair Tool (local only)</h2>\n";
echo "<p>Actions: <a href='?action=backup'>Backup</a> | <a href='?action=check'>CHECK</a> | <a href='?action=repair'>REPAIR</a> | <a href='?action=force'>ALTER .. FORCE</a> | <a href='?action=optimize'>OPTIMIZE</a></p>\n";

if ($action === 'backup') {
    $now = date('Ymd_His');
    $fname = __DIR__ . "/menu_access_backup_{$now}.sql";
    $fbase = basename($fname);
    $fp = fopen($fname, 'wb');
    if (!$fp) {
        echo "Failed to create backup file: {$fname}";
        exit;
    }

    // Get create table
    $r = $conn->query("SHOW CREATE TABLE menu_access");
    if ($r && ($row = $r->fetch_assoc())) {
        fwrite($fp, $row['Create Table'] . ";\n\n");
    }

    $res = $conn->query("SELECT * FROM menu_access");
    if ($res && $res instanceof mysqli_result) {
        while ($row = $res->fetch_assoc()) {
            $cols = array_map(function ($c) { return "`$c`"; }, array_keys($row));
            $vals = array_map(function ($v) use ($conn) { return escape_val($conn, $v); }, array_values($row));
            $sql = "INSERT INTO `menu_access` (" . implode(',', $cols) . ") VALUES (" . implode(',', $vals) . ");\n";
            fwrite($fp, $sql);
        }
    }
    fclose($fp);
    echo "Backup created: <a href='" . rawurlencode($fbase) . "'>{$fbase}</a>";
    exit;
}

if ($action === 'check' || $action === 'repair' || $action === 'optimize' || $action === 'force') {
    $queries = [];
    if ($action === 'check') {
        $queries[] = "CHECK TABLE menu_access";
    } elseif ($action === 'repair') {
        $queries[] = "REPAIR TABLE menu_access";
    } elseif ($action === 'optimize') {
        $queries[] = "OPTIMIZE TABLE menu_access";
    } elseif ($action === 'force') {
        $queries[] = "ALTER TABLE menu_access FORCE";
    }

    echo "<pre>";
    foreach ($queries as $q) {
        echo "-- Running: {$q}\n";
        $res = $conn->query($q);
        if ($res === false) {
            echo "ERROR: " . $conn->error . "\n";
            continue;
        }
        if ($res instanceof mysqli_result) {
            while ($r = $res->fetch_assoc()) {
                echo htmlspecialchars(json_encode($r, JSON_PRETTY_PRINT)) . "\n";
            }
            $res->free();
        } else {
            echo "OK\n";
        }
    }
    echo "</pre>";
    exit;
}

// Default page
echo "<p>Open this page from the server (localhost) and choose an action. Start with <strong>Backup</strong>, then <strong>CHECK</strong>, then <strong>REPAIR</strong> if needed. If your table is InnoDB, use <strong>ALTER .. FORCE</strong> or <strong>OPTIMIZE</strong>.</p>";
echo "<p>Backup files are created in the scripts directory and can be downloaded from the browser if your server serves that folder.</p>";

echo "<hr><p>Note: This tool performs potentially destructive operations. Only run on a trusted local environment.</p>";

?>
