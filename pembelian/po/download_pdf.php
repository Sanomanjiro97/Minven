<?php
require_once '../../config.php';

if (!isset($_GET['file']) || empty($_GET['file'])) {
    http_response_code(404);
    exit('File tidak valid');
}

$filename = basename($_GET['file']);
$timestamp = isset($_GET['t']) ? (int)$_GET['t'] : 0;
$signature = isset($_GET['s']) ? $_GET['s'] : '';

if (!$filename || !$timestamp || !$signature) {
    http_response_code(400);
    exit('Parameter tidak lengkap');
}

if (abs(time() - $timestamp) > 86400) {
    http_response_code(410);
    exit('Link sudah expired');
}

$DOWNLOAD_SECRET = 'minven_secure_key';

$filename  = $_GET['file'] ?? '';
$timestamp = $_GET['ts'] ?? '';

$expectedSig = hash_hmac(
    'sha256',
    $filename . $timestamp,
    $DOWNLOAD_SECRET
);
if (!hash_equals($expectedSig, $signature)) {
    http_response_code(403);
    exit('Signature tidak valid');
}

$filepath = __DIR__ . '/../../uploads/po/' . $filename;

if (!preg_match('/^PO_[a-zA-Z0-9_-]+\.pdf$/i', $filename)) {
    http_response_code(400);
    exit('Nama file tidak valid');
}

if (!file_exists($filepath) || !is_file($filepath)) {
    http_response_code(404);
    exit('File tidak ditemukan');
}

$filesize = filesize($filepath);

if (ob_get_level()) {
    ob_end_clean();
}

header('Content-Description: File Transfer');
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Transfer-Encoding: binary');
header('Expires: 0');
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Pragma: public');
header('Content-Length: ' . $filesize);

flush();
readfile($filepath);
exit();
?>