<?php
require_once '../config.php';

// Aktifkan error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Buka file untuk output
$output = fopen("supplier_list.txt", "w");

// Query untuk mengambil semua supplier
$sql = "SELECT id, nama_supplier FROM supplier ORDER BY id";
$result = $conn->query($sql);

if (!$result) {
    fwrite($output, "Error executing query: " . $conn->error);
    fclose($output);
    die("Error executing query: " . $conn->error);
}

$suppliers = [];
while ($row = $result->fetch_assoc()) {
    $suppliers[] = $row;
}

if (empty($suppliers)) {
    $message = "PERINGATAN: Tidak ada supplier yang terdaftar dalam database!\n";
    $message .= "Anda perlu menambahkan supplier terlebih dahulu sebelum mengimpor barang.";
    fwrite($output, $message);
    fclose($output);
    echo $message;
    exit;
}

$content = "==============================================\n";
$content .= "DAFTAR SUPPLIER YANG TERSEDIA DALAM DATABASE:\n";
$content .= "==============================================\n\n";

foreach ($suppliers as $sup) {
    $content .= sprintf("ID: %d\t- %s\n", $sup['id'], $sup['nama_supplier']);
}

$content .= "\n==============================================\n";
$content .= "HASIL PENGECEKAN SUPPLIER ID 69:\n";
$content .= "==============================================\n\n";

$found = false;
foreach ($suppliers as $sup) {
    if ($sup['id'] == 69) {
        $content .= "✅ Supplier ID 69 ditemukan: " . $sup['nama_supplier'] . "\n";
        $found = true;
        break;
    }
}

if (!$found) {
    $content .= "❌ Supplier ID 69 TIDAK DITEMUKAN dalam database!\n";
    $content .= "\nSaran:\n";
    $content .= "1. Gunakan salah satu ID supplier yang tersedia di atas\n";
    $content .= "2. Atau tambahkan supplier baru melalui menu Supplier\n";
    $content .= "3. Setelah menambah supplier, download template baru\n";
}

$content .= "\n==============================================\n";
$content .= "PETUNJUK PENGGUNAAN:\n";
$content .= "==============================================\n";
$content .= "1. Pilih ID supplier yang tersedia dari daftar di atas\n";
$content .= "2. Ganti semua ID 69 di file Excel dengan ID yang valid\n";
$content .= "3. Pastikan menggunakan ID yang terdaftar di database\n";
$content .= "4. Import ulang file Excel yang sudah diperbarui\n";

fwrite($output, $content);
fclose($output);
echo $content;
?> 