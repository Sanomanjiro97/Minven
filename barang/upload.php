<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['file_barang'])) {
    $file = $_FILES['file_barang'];
    
    // Validasi file
    $allowed_ext = ['csv', 'xlsx', 'xls'];
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($file_ext, $allowed_ext)) {
        $error = "Format file tidak didukung. Gunakan file CSV atau Excel.";
    } elseif ($file['size'] > 5000000) { // 5MB max
        $error = "Ukuran file terlalu besar. Maksimal 5MB.";
    } else {
        $upload_dir = '../uploads/';
        
        // Buat direktori jika belum ada
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_name = 'barang_' . date('YmdHis') . '.' . $file_ext;
        $upload_path = $upload_dir . $file_name;
        
        if (move_uploaded_file($file['tmp_name'], $upload_path)) {
            // Proses file berdasarkan ekstensi
            if ($file_ext == 'csv') {
                $result = processCSV($upload_path, $conn);
            } else {
                // Untuk file Excel, perlu library tambahan seperti PhpSpreadsheet
                $result = processExcel($upload_path, $conn);
            }
            
            if ($result['status']) {
                $message = "Berhasil mengupload dan memproses {$result['count']} data barang.";
            } else {
                $error = "Gagal memproses file: " . $result['message'];
            }
        } else {
            $error = "Gagal mengupload file.";
        }
    }
}

// Fungsi untuk memproses file CSV
function processCSV($file_path, $conn) {
    $count = 0;
    $errors = [];
    
    if (($handle = fopen($file_path, "r")) !== FALSE) {
        // Skip header row
        $header = fgetcsv($handle, 1000, ",");
        
        // Mulai transaksi
        $conn->begin_transaction();
        
        try {
            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                // Pastikan jumlah kolom sesuai
                if (count($data) < 5) {
                    $errors[] = "Baris " . ($count + 2) . ": Jumlah kolom tidak sesuai";
                    continue;
                }
                
                // Ambil data dari CSV
                $kode_barang = $conn->real_escape_string(trim($data[0]));
                $nama_barang = $conn->real_escape_string(trim($data[1]));
                $kategori_id = (int)trim($data[2]);
                $satuan_id = (int)trim($data[3]);
                $harga_beli = (float)str_replace(['Rp', '.', ','], ['', '', '.'], trim($data[4]));
                $harga_jual = isset($data[5]) ? (float)str_replace(['Rp', '.', ','], ['', '', '.'], trim($data[5])) : 0;
                
                // Cek apakah kode barang sudah ada
                $check = $conn->query("SELECT id FROM barang WHERE kode_barang = '$kode_barang'");
                
                if ($check->num_rows > 0) {
                    // Update barang yang sudah ada
                    $sql = "UPDATE barang SET 
                            nama_barang = '$nama_barang',
                            kategori_id = $kategori_id,
                            satuan_id = $satuan_id,
                            harga_beli = $harga_beli,
                            harga_jual = $harga_jual,
                            updated_at = NOW()
                            WHERE kode_barang = '$kode_barang'";
                } else {
                    // Insert barang baru
                    $sql = "INSERT INTO barang (kode_barang, nama_barang, kategori_id, satuan_id, harga_beli, harga_jual, created_at, updated_at)
                            VALUES ('$kode_barang', '$nama_barang', $kategori_id, $satuan_id, $harga_beli, $harga_jual, NOW(), NOW())";
                }
                
                if ($conn->query($sql)) {
                    $count++;
                } else {
                    $errors[] = "Baris " . ($count + 2) . ": " . $conn->error;
                }
            }
            
            // Commit transaksi jika tidak ada error
            if (empty($errors)) {
                $conn->commit();
                return ['status' => true, 'count' => $count];
            } else {
                throw new Exception(implode(", ", $errors));
            }
        } catch (Exception $e) {
            $conn->rollback();
            return ['status' => false, 'message' => $e->getMessage()];
        } finally {
            fclose($handle);
        }
    } else {
        return ['status' => false, 'message' => 'Tidak dapat membuka file'];
    }
}

// Fungsi untuk memproses file Excel
// Catatan: Fungsi ini memerlukan library PhpSpreadsheet
function processExcel($file_path, $conn) {
    // Placeholder - implementasi sebenarnya memerlukan library tambahan
    return ['status' => false, 'message' => 'Proses file Excel belum diimplementasikan. Gunakan file CSV.'];
}

// Query untuk mendapatkan data kategori dan satuan untuk template
$kategori_result = $conn->query("SELECT id, nama_kategori FROM kategori ORDER BY nama_kategori");
$satuan_result = $conn->query("SELECT id, nama_satuan FROM satuan ORDER BY nama_satuan");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Data Barang - Sistem Inventory</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
</head>
<body>
    <?php include '../templates/navbar.php'; ?>

    <div class="container mt-4">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">Upload Data Barang</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($message): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <?= $message ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?= $error ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        <?php endif; ?>
                        
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header bg-primary text-white">
                                        <h6 class="mb-0">Upload File</h6>
                                    </div>
                                    <div class="card-body">
                                        <form action="" method="post" enctype="multipart/form-data">
                                            <div class="mb-3">
                                                <label for="file_barang" class="form-label">Pilih File CSV/Excel</label>
                                                <input type="file" class="form-control" id="file_barang" name="file_barang" accept=".csv, .xlsx, .xls" required>
                                                <div class="form-text">Format yang didukung: CSV, Excel (.xlsx, .xls). Maksimal 5MB.</div>
                                            </div>
                                            <button type="submit" class="btn btn-primary">
                                                <i class="bx bx-upload"></i> Upload dan Proses
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header bg-info text-white">
                                        <h6 class="mb-0">Panduan Format</h6>
                                    </div>
                                    <div class="card-body">
                                        <p>File CSV/Excel harus memiliki format sebagai berikut:</p>
                                        <table class="table table-sm table-bordered">
                                            <thead>
                                                <tr>
                                                    <th>Kode Barang</th>
                                                    <th>Nama Barang</th>
                                                    <th>ID Kategori</th>
                                                    <th>ID Satuan</th>
                                                    <th>Harga Beli</th>
                                                    <th>Harga Jual</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr>
                                                    <td>BRG001</td>
                                                    <td>Contoh Barang</td>
                                                    <td>1</td>
                                                    <td>2</td>
                                                    <td>10000</td>
                                                    <td>15000</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                        
                                        <div class="mt-3">
                                            <h6>Daftar ID Kategori:</h6>
                                            <ul class="list-group">
                                                <?php while ($kategori = $kategori_result->fetch_assoc()): ?>
                                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                                    <?= htmlspecialchars($kategori['nama_kategori']) ?>
                                                    <span class="badge bg-primary rounded-pill"><?= $kategori['id'] ?></span>
                                                </li>
                                                <?php endwhile; ?>
                                            </ul>
                                        </div>
                                        
                                        <div class="mt-3">
                                            <h6>Daftar ID Satuan:</h6>
                                            <ul class="list-group">
                                                <?php while ($satuan = $satuan_result->fetch_assoc()): ?>
                                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                                    <?= htmlspecialchars($satuan['nama_satuan']) ?>
                                                    <span class="badge bg-primary rounded-pill"><?= $satuan['id'] ?></span>
                                                </li>
                                                <?php endwhile; ?>
                                            </ul>
                                        </div>
                                        
                                        <div class="mt-3">
                                            <a href="../templates/template_barang.csv" class="btn btn-outline-primary btn-sm" download>
                                                <i class="bx bx-download"></i> Download Template CSV
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="text-end">
                            <a href="index.php" class="btn btn-secondary">
                                <i class="bx bx-arrow-back"></i> Kembali ke Daftar Barang
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include '../templates/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>