<?php
session_start();
require_once '../config.php';
require_once '../libs/SimpleXLSX.php'; // Pastikan path benar dan file ada

// Tambahkan baris ini untuk menggunakan kelas SimpleXLSX dari namespace Shuchkin
use Shuchkin\SimpleXLSX;

// Pastikan path benar

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

if (!isset($_FILES['file_barang']) || $_FILES['file_barang']['error'] !== UPLOAD_ERR_OK) {
    $error_code = $_FILES['file_barang']['error'] ?? 'Unknown';
    die("❌ File belum diupload atau terjadi kesalahan upload (Error: $error_code).");
}

$file_tmp = $_FILES['file_barang']['tmp_name'];
$filename = $_FILES['file_barang']['name'];
$extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

$imported = 0;
$errors = [];
$rowNumber = 1;

function import_row($conn, $row, $rowNumber) {
    // Debug: Print row data
    error_log("Processing row $rowNumber: " . print_r($row, true));
    
    // Pastikan indeks kolom sesuai dengan file Excel/CSV Anda
    $kode = trim($row[0] ?? '');
    $nama = trim($row[1] ?? '');
    $sup  = is_numeric($row[2] ?? null) ? intval($row[2]) : null;  // Supplier ID sekarang di kolom ke-3
    $kat  = is_numeric($row[3] ?? null) ? intval($row[3]) : null;  // Kategori ID sekarang di kolom ke-4
    $sat  = is_numeric($row[4] ?? null) ? intval($row[4]) : null;  // Satuan ID sekarang di kolom ke-5
    $stok_minimum = is_numeric($row[5] ?? null) ? intval($row[5]) : 0;
    $harga_beli = is_numeric($row[6] ?? null) ? floatval($row[6]) : 0.0;
    $expired_at_str = trim($row[7] ?? '');
    $expired_at = null;
    
    // Debug: Print parsed values
    error_log("Parsed values for row $rowNumber:");
    error_log("Supplier ID: " . var_export($sup, true));
    error_log("Kategori ID: " . var_export($kat, true));
    error_log("Satuan ID: " . var_export($sat, true));
    
    // Validasi data kosong
    if ($kode === '' || $nama === '') {
        return "Baris $rowNumber: Kode atau Nama kosong.";
    }

    // Validasi supplier_id (harus divalidasi pertama karena paling penting)
    if ($sup === null) {
        error_log("Supplier ID is null for row $rowNumber");
        return "Baris $rowNumber: Supplier ID tidak boleh kosong.";
    }

    $check_supplier = $conn->prepare("SELECT id FROM supplier WHERE id = ?");
    if (!$check_supplier) {
        error_log("Failed to prepare supplier check statement: " . $conn->error);
        return "Baris $rowNumber: Prepare statement cek supplier gagal - " . $conn->error;
    }
    $check_supplier->bind_param("i", $sup);
    $check_supplier->execute();
    $check_supplier->store_result();
    
    // Debug: Log supplier check results
    error_log("Supplier check for ID $sup returned {$check_supplier->num_rows} rows");
    
    if ($check_supplier->num_rows === 0) {
        $check_supplier->close();
        return "Baris $rowNumber: Supplier ID $sup tidak ditemukan dalam database.";
    }
    $check_supplier->close();

    // Validasi kategori_id
    if ($kat === null) {
        return "Baris $rowNumber: Kategori ID tidak boleh kosong.";
    }

    $check_kategori = $conn->prepare("SELECT id FROM kategori WHERE id = ?");
    if (!$check_kategori) {
        return "Baris $rowNumber: Prepare statement cek kategori gagal - " . $conn->error;
    }
    $check_kategori->bind_param("i", $kat);
    $check_kategori->execute();
    $check_kategori->store_result();
    if ($check_kategori->num_rows === 0) {
        $check_kategori->close();
        return "Baris $rowNumber: Kategori ID $kat tidak ditemukan dalam database.";
    }
    $check_kategori->close();

    // Validasi satuan_id
    if ($sat === null) {
        return "Baris $rowNumber: Satuan ID tidak boleh kosong.";
    }

    $check_satuan = $conn->prepare("SELECT id FROM satuan WHERE id = ?");
    if (!$check_satuan) {
        return "Baris $rowNumber: Prepare statement cek satuan gagal - " . $conn->error;
    }
    $check_satuan->bind_param("i", $sat);
    $check_satuan->execute();
    $check_satuan->store_result();
    if ($check_satuan->num_rows === 0) {
        $check_satuan->close();
        return "Baris $rowNumber: Satuan ID $sat tidak ditemukan dalam database.";
    }
    $check_satuan->close();

    // Parse tanggal kadaluarsa
    if (!empty($expired_at_str)) {
        $date_obj = DateTime::createFromFormat('Y-m-d', $expired_at_str);
        if ($date_obj) {
            $expired_at = $date_obj->format('Y-m-d');
        } else {
            $date_obj = DateTime::createFromFormat('d/m/Y', $expired_at_str);
            if ($date_obj) {
                $expired_at = $date_obj->format('Y-m-d');
            }
        }
    }

    // Cek duplikat kode barang
    $check_duplicate = $conn->prepare("SELECT id FROM barang WHERE kode_barang = ?");
    if (!$check_duplicate) {
         return "Baris $rowNumber: Prepare statement cek duplikat gagal - " . $conn->error;
    }
    $check_duplicate->bind_param("s", $kode);
    $check_duplicate->execute();
    $check_duplicate->store_result();

    if ($check_duplicate->num_rows > 0) {
        $check_duplicate->close();
        return "Baris $rowNumber: Kode barang '$kode' sudah ada.";
    }
    $check_duplicate->close();

    // Query INSERT dengan semua field yang diperlukan
    $sql = "INSERT INTO barang (
        kode_barang, 
        nama_barang, 
        kategori_id, 
        satuan_id, 
        supplier_id, 
        stok_minimum,
        harga_beli,
        expired_at,
        created_by,
        created_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return "Baris $rowNumber: Prepare statement INSERT gagal - " . $conn->error;
    }

    $stmt->bind_param("ssiiiddsi", 
        $kode,          // kode_barang (string)
        $nama,          // nama_barang (string)
        $kat,           // kategori_id (int)
        $sat,           // satuan_id (int)
        $sup,           // supplier_id (int)
        $stok_minimum,  // stok_minimum (double)
        $harga_beli,    // harga_beli (double)
        $expired_at,    // expired_at (string)
        $_SESSION['user_id'] // created_by (int)
    );

    if (!$stmt->execute()) {
        $error = "Baris $rowNumber: Gagal import ($kode): " . $stmt->error;
        $stmt->close();
        return $error;
    }

    $stmt->close();
    return null; // Berhasil
}

if ($extension === 'csv') {
    // Menggunakan delimiter koma (,) untuk CSV
    $handle = fopen($file_tmp, "r");
    if (!$handle) die("❌ Gagal membuka file CSV.");

    // Baca header (opsional, bisa dilewati)
    $header = fgetcsv($handle, 1000, ",");
    $rowNumber++; // Baris data dimulai setelah header

    while (($row = fgetcsv($handle, 1000, ",")) !== false) {
        // Pastikan jumlah kolom sesuai harapan sebelum memproses
        if (count($row) < 5) { // Minimal 5 kolom: Kode, Nama, Kat, Sat, Sup
             $errors[] = "Baris $rowNumber: Jumlah kolom tidak sesuai (" . count($row) . " dari minimal 5 yang diharapkan).";
             $rowNumber++;
             continue;
        }
        $error = import_row($conn, $row, $rowNumber);
        if ($error) {
            $errors[] = $error;
        } else {
            $imported++;
        }
        $rowNumber++;
    }

    fclose($handle);

} elseif ($extension === 'xlsx') {
    // Menggunakan SimpleXLSX untuk file XLSX
    if ($xlsx = SimpleXLSX::parse($file_tmp)) {
        $rows = $xlsx->rows();
        if (empty($rows)) {
             die("❌ File XLSX kosong atau tidak dapat dibaca.");
        }

        // Ambil header untuk referensi (opsional)
        $header = array_shift($rows); // Hapus baris pertama (header)
        $rowNumber++; // Baris data dimulai dari baris ke-2 di file Excel

        foreach ($rows as $row) {
            // Pastikan jumlah kolom sesuai harapan sebelum memproses
             if (count($row) < 5) { // Minimal 5 kolom: Kode, Nama, Kat, Sat, Sup
                 $errors[] = "Baris $rowNumber: Jumlah kolom tidak sesuai (" . count($row) . " dari minimal 5 yang diharapkan).";
                 $rowNumber++;
                 continue;
            }
            $error = import_row($conn, $row, $rowNumber);
            if ($error) {
                $errors[] = $error;
            } else {
                $imported++;
            }
            $rowNumber++;
        }
    } else {
        die("❌ Gagal membaca file XLSX: " . SimpleXLSX::parseError());
    }
} else {
    die("❌ Format file tidak didukung. Gunakan CSV atau XLSX.");
}

// Set session messages
if ($imported > 0) {
    $_SESSION['success'] = "Berhasil mengimpor $imported data barang.";
}
if (!empty($errors)) {
    $_SESSION['error'] = implode("<br>", $errors);
}

// Redirect back to index.php
header("Location: index.php");
exit();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hasil Import</title>
    <style>
        body { font-family: sans-serif; line-height: 1.6; margin: 20px; }
        h2 { color: green; }
        h3 { color: red; }
        ul { list-style: none; padding: 0; }
        li { margin-bottom: 5px; padding: 8px; border: 1px solid #eee; background-color: #f9f9f9; }
        .success { color: green; }
        .error { color: red; }
    </style>
</head>
<body>
  <h2>✅ Hasil Import</h2>
  <p>Data berhasil diimpor: <strong class="success"><?= $imported ?></strong></p>

  <?php if (!empty($errors)): ?>
    <h3>❌ Kesalahan:</h3>
    <ul>
      <?php foreach ($errors as $e): ?>
        <li class="error"><?= htmlspecialchars($e) ?></li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>

  <p><a href="form_import.php">⟵ Kembali ke Form</a></p>
</body>
</html>
