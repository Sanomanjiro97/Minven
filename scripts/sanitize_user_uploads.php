<?php
// Local-only utility to sanitize filenames in uploads/user and update users.profile_picture
require_once __DIR__ . '/../config.php';

if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    echo "Forbidden: local use only.";
    exit;
}

$uploadDir = realpath(__DIR__ . '/../uploads/user');
if (!$uploadDir || !is_dir($uploadDir)) {
    echo "uploads/user directory not found: {$uploadDir}";
    exit;
}

function sanitize_name($name) {
    $ext = '';
    $pos = strrpos($name, '.');
    if ($pos !== false) {
        $ext = substr($name, $pos);
        $base = substr($name, 0, $pos);
    } else {
        $base = $name;
    }
    // Replace any sequence of non-alphanumeric, dot, underscore, or hyphen with underscore
    $base = preg_replace('/[^A-Za-z0-9_\-\.]+/', '_', $base);
    // Collapse multiple underscores
    $base = preg_replace('/_+/', '_', $base);
    // Trim underscores/dots from ends
    $base = trim($base, '_.-');
    if ($base === '') $base = 'file';
    return $base . $ext;
}

function render_header() {
    echo "<meta charset=\"utf-8\"><title>Sanitize uploads/user</title><style>body{font-family:Segoe UI,Arial;margin:18px}table{border-collapse:collapse}td,th{padding:6px;border:1px solid #ddd}</style>";
}

render_header();
echo "<h2>Sanitize uploads/user filenames</h2>\n";
echo "<p>Scans files in <code>uploads/user</code>, shows suggested safe names, and can rename files and update <code>users.profile_picture</code>.</p>\n";

$files = array_values(array_filter(scandir($uploadDir), function($f) use ($uploadDir) {
    return is_file($uploadDir . DIRECTORY_SEPARATOR . $f);
}));

if (empty($files)) {
    echo "<p>No files found in uploads/user.</p>";
    exit;
}

echo "<form method=GET><input type=hidden name=action value=apply><button type=submit style='padding:8px;border-radius:6px'>Apply suggested renames and update DB</button></form>";

echo "<table><tr><th>Current filename</th><th>Suggested</th><th>Action</th></tr>";

$suggested = [];
foreach ($files as $f) {
    $safe = sanitize_name($f);
    $orig = $f;
    $target = $safe;
    $i = 1;
    while (file_exists($uploadDir . DIRECTORY_SEPARATOR . $target) && $target !== $orig) {
        $pos = strrpos($safe, '.');
        if ($pos !== false) {
            $base = substr($safe, 0, $pos);
            $ext = substr($safe, $pos);
        } else {
            $base = $safe; $ext = '';
        }
        $target = $base . '_' . $i . $ext;
        $i++;
    }
    $suggested[$orig] = $target;
    $needs = $orig !== $target ? 'rename' : 'ok';
    echo "<tr><td>" . htmlspecialchars($orig) . "</td><td>" . htmlspecialchars($target) . "</td><td>" . $needs . "</td></tr>\n";
}
echo "</table>";

if (isset($_GET['action']) && $_GET['action'] === 'apply') {
    echo "<h3>Applying changes...</h3>\n<pre>";
    $renamed = 0;
    foreach ($suggested as $orig => $target) {
        if ($orig === $target) continue;
        $from = $uploadDir . DIRECTORY_SEPARATOR . $orig;
        $to = $uploadDir . DIRECTORY_SEPARATOR . $target;
        if (!is_file($from)) { echo "Missing: $orig\n"; continue; }
        if (file_exists($to)) { echo "Target exists, skipping: $target\n"; continue; }
        if (@rename($from, $to)) {
            $renamed++;
            echo "Renamed: $orig -> $target\n";
            // Update users.profile_picture if matches
            $q = $conn->prepare("UPDATE users SET profile_picture = ? WHERE profile_picture = ?");
            if ($q) {
                $q->bind_param('ss', $target, $orig);
                $q->execute();
                $q->close();
            }
        } else {
            echo "Failed to rename: $orig\n";
        }
    }
    echo "\nRenamed count: $renamed\n</pre>";
    echo "<p>Done. Reload your app and check if missing images are resolved.</p>";
}

?>
