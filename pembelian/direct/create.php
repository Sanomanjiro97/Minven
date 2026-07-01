<?php
ob_start();
session_start();
require_once '../../config.php';
$conn = $GLOBALS['conn'];
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit();
}

function writeCreateLog($message, $type = 'info') {
    $logFile = __DIR__ . '/create_save.log';
    $date = date('Y-m-d H:i:s');
    $logMessage = "[$date][$type] $message\n";
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}

// Hapus query supplier yang tidak diperlukan lagi
// Query untuk mengambil daftar barang
$sql = "SELECT b.id, b.kode_barang, b.nama_barang, s.nama_satuan, b.harga_beli 
        FROM barang b
        LEFT JOIN satuan s ON b.satuan_id = s.id
        ORDER BY b.kode_barang";
$barang_result = $conn->query($sql);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $uploaded_paths = [];
    $in_transaction = false;
    try {
    // Validasi input
    if (empty($_POST['nama_toko']) || empty($_POST['tanggal'])) {
        $_SESSION['error'] = "Nama toko dan tanggal harus diisi!";
        header("Location: create.php");
        exit();
    }

    if (empty($_POST['barang_id']) || !is_array($_POST['barang_id'])) {
        $_SESSION['error'] = "Detail barang harus diisi!";
        header("Location: create.php");
        exit();
    }

    $nama_toko = $_POST['nama_toko'];
    $tanggal = $_POST['tanggal'];
    $keterangan = $_POST['keterangan'] ?? '';
    $barang_ids = $_POST['barang_id'] ?? [];
    $jumlah = $_POST['jumlah'] ?? [];
    $harga_satuan = $_POST['harga_satuan'] ?? [];
    $keterangan_detail = isset($_POST['keterangan_detail']) ? $_POST['keterangan_detail'] : array();
    
    $active_item_keys = [];
    foreach ($barang_ids as $key => $barang_id) {
        $selected = isset($barang_ids[$key]) ? trim((string)$barang_ids[$key]) : '';
        $qty = isset($jumlah[$key]) ? (float)$jumlah[$key] : 0.0;
        if ($selected !== '' && $qty > 0) {
            $active_item_keys[] = $key;
        }
    }
    
    if (count($active_item_keys) === 0) {
        $_SESSION['error'] = "Minimal harus ada satu item dengan jumlah lebih dari 0";
        header("Location: create.php");
        exit();
    }
    
    if (!isset($_FILES['foto_barang']) || !is_array($_FILES['foto_barang']['name'])) {
        $_SESSION['error'] = "Foto wajib diupload untuk setiap item";
        header("Location: create.php");
        exit();
    }
    
    foreach ($active_item_keys as $key) {
        if (!isset($_FILES['foto_barang']['error'][$key]) || $_FILES['foto_barang']['error'][$key] !== UPLOAD_ERR_OK) {
            $_SESSION['error'] = "Foto wajib diupload untuk item ke-" . ($key + 1);
            header("Location: create.php");
            exit();
        }
    }
    
    // Generate nomor transaksi
    $tahun = date('Y');
    $bulan = date('m');
    $sql = "SELECT MAX(SUBSTRING(no_transaksi, 12, 4)) as max_num 
            FROM direct_purchase 
            WHERE SUBSTRING(no_transaksi, 4, 4) = ? 
            AND SUBSTRING(no_transaksi, 8, 2) = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ss', $tahun, $bulan);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $nomor = (int)$row['max_num'] + 1;
    $no_transaksi = 'DP-' . $tahun . $bulan . str_pad($nomor, 4, '0', STR_PAD_LEFT);
    
    // Hitung total
    $total_item = 0;
    $total_harga = 0;
    foreach ($barang_ids as $key => $barang_id) {
        if (isset($jumlah[$key]) && (float)$jumlah[$key] > 0) {
            $total_item += (float)$jumlah[$key];
            
            // Membersihkan format Rupiah dari harga_satuan sebelum perhitungan
            $harga_bersih = str_replace(['Rp ', '.', ','], ['', '', '.'], $harga_satuan[$key] ?? '0');
            $harga_float = (float)$harga_bersih;
    
            $total_harga += (((float)$jumlah[$key]) * $harga_float);
        }
    }

    $conn->begin_transaction();
    $in_transaction = true;
    
    // Insert header pembelian
    $sql = "INSERT INTO direct_purchase (no_transaksi, tanggal, nama_toko, total_item, total_harga, keterangan, status, created_by, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, 'menunggu', ?, NOW(), NULL)"; // Mengubah 'draft' menjadi 'menunggu'
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        throw new Exception("Error preparing statement");
    }
    
    // Pastikan keterangan tidak null
    $keterangan = !empty($_POST['keterangan']) ? $_POST['keterangan'] : '';
    
    $stmt->bind_param('sssidsi', $no_transaksi, $tanggal, $nama_toko, $total_item, $total_harga, $keterangan, $_SESSION['user_id']);
    
    if (!$stmt->execute()) {
        throw new Exception("Error executing header insert");
    }
    
    $purchase_id = $conn->insert_id;
    
    // Insert detail pembelian
    $sql = "INSERT INTO detail_direct_purchase (direct_purchase_id, barang_id, jumlah, harga_satuan, total_harga, keterangan, foto)
            VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        throw new Exception("Error preparing detail statement");
    }

    // Pastikan nama_barang_lain dan keterangan_detail ada dan berupa array
    $nama_barang_lain = isset($_POST['nama_barang_lain']) ? $_POST['nama_barang_lain'] : array();
    $keterangan_detail = isset($_POST['keterangan_detail']) ? $_POST['keterangan_detail'] : array();

    // Proses upload foto jika ada
    $uploaded_files = array();
    if (isset($_FILES['foto_barang']) && is_array($_FILES['foto_barang']['name'])) {
        $upload_dir = '../../uploads/pembelian/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $allowed_mimes = array('image/jpeg', 'image/png', 'image/gif');
        $allowed_exts = array('jpg', 'jpeg', 'png', 'gif');
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        
        foreach ($active_item_keys as $key) {
            $tmp_name = $_FILES['foto_barang']['tmp_name'][$key] ?? '';
            $file_name = $_FILES['foto_barang']['name'][$key] ?? '';
            $file_size = $_FILES['foto_barang']['size'][$key] ?? 0;
            
            if (empty($tmp_name) || !is_uploaded_file($tmp_name)) {
                $_SESSION['error'] = "Foto wajib diupload untuk item ke-" . ($key + 1);
                header("Location: create.php");
                exit();
            }
            
            if ($file_size > 2 * 1024 * 1024) {
                $_SESSION['error'] = "Ukuran foto maksimal 2MB untuk item ke-" . ($key + 1);
                header("Location: create.php");
                exit();
            }
            
            $mime = null;
            if ($finfo) {
                $mime = finfo_file($finfo, $tmp_name);
            } elseif (function_exists('mime_content_type')) {
                $mime = mime_content_type($tmp_name);
            } else {
                $mime = $_FILES['foto_barang']['type'][$key] ?? null;
            }
            if (!$mime || !in_array($mime, $allowed_mimes, true)) {
                $_SESSION['error'] = "Format foto tidak didukung (JPG/PNG/GIF) untuk item ke-" . ($key + 1);
                header("Location: create.php");
                exit();
            }
            
            $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            if (empty($file_extension) || !in_array($file_extension, $allowed_exts, true)) {
                if ($mime === 'image/png') {
                    $file_extension = 'png';
                } elseif ($mime === 'image/gif') {
                    $file_extension = 'gif';
                } else {
                    $file_extension = 'jpg';
                }
            }
            
            $unique_filename = time() . '_' . uniqid() . '.' . $file_extension;
            $upload_path = $upload_dir . $unique_filename;
            
            if (!move_uploaded_file($tmp_name, $upload_path)) {
                throw new Exception("Gagal mengupload foto untuk item ke-" . ($key + 1));
            }
            
            $uploaded_files[$key] = $unique_filename;
            $uploaded_paths[] = $upload_path;
        }
        
        if ($finfo) {
            finfo_close($finfo);
        }
    }

    foreach ($barang_ids as $key => $barang_id) {
        if (isset($jumlah[$key]) && $jumlah[$key] > 0 && trim((string)$barang_id) !== '') { // Check if jumlah exists and is > 0
            // Bersihkan format Rupiah dari harga_satuan
            $harga_bersih = str_replace(['Rp ', '.', ','], ['', '', '.'], isset($harga_satuan[$key]) ? $harga_satuan[$key] : '0');
            $harga_float = (float) $harga_bersih;
            $total = $jumlah[$key] * $harga_float;
            
            // Handle barang_id untuk "Others"
            $ref_barang_id = ($barang_id == '0' || $barang_id == 0) ? NULL : $barang_id;
            $ref_jumlah = $jumlah[$key];
            $ref_harga_float = $harga_float;
            $ref_total = $total;
            
            // Handle keterangan
            $ref_keterangan = '';
            if ($ref_barang_id === NULL) {
                // Jika barang_id NULL (Others), gunakan nama_barang_lain sebagai keterangan
                $ref_keterangan = isset($nama_barang_lain[$key]) ? trim($nama_barang_lain[$key]) : '';
            } else {
                // Jika barang_id ada (master item), gunakan keterangan_detail
                $ref_keterangan = isset($keterangan_detail[$key]) ? trim($keterangan_detail[$key]) : '';
            }
            
            // Debug: Log keterangan untuk memastikan data tersimpan
            error_log("Keterangan untuk item $key: " . $ref_keterangan);
            
            // Handle foto
            $ref_foto = isset($uploaded_files[$key]) ? $uploaded_files[$key] : NULL;
            if ($ref_foto === NULL || $ref_foto === '') {
                throw new Exception("Foto wajib diupload untuk item ke-" . ($key + 1));
            }
            
            // The type for NULL should be 'i' if the column is INT NULL, or 's' if VARCHAR NULL.
            // Assuming barang_id is INT NULL. 'i' type works for NULL with bind_param for INT columns.
            if (!$stmt->bind_param('iidddss', $purchase_id, $ref_barang_id, $ref_jumlah, $ref_harga_float, $ref_total, $ref_keterangan, $ref_foto)) {
                 throw new Exception("Error binding parameters for detail");
            }

            if (!$stmt->execute()) {
                throw new Exception("Error executing detail insert");
            }
        }
    }
    // Close the detail statement after the loop
    $stmt->close();

    $conn->commit();
    $in_transaction = false;
    $_SESSION['success'] = "Data pembelian berhasil disimpan!";
    ob_end_clean();
    header("Location: index.php");
    exit();
    } catch (Throwable $e) {
        if ($in_transaction) {
            try {
                $conn->rollback();
            } catch (Throwable $rollbackError) {
            }
        }
        foreach ($uploaded_paths as $path) {
            if (is_string($path) && $path !== '' && file_exists($path)) {
                @unlink($path);
            }
        }
        writeCreateLog("Save failed: " . $e->getMessage(), 'error');
        $_SESSION['error'] = "Gagal menyimpan pembelian. Silakan coba lagi.";
        ob_end_clean();
        header("Location: create.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buat Pembelian Mendadak - Sistem Inventory</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.0.7/css/boxicons.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        body {
            background: #ffffff;
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .card {
            border-radius: 0.75rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            background: #ffffff;
            border: 1px solid #e2e8f0;
        }
        .card-header {
            background:linear-gradient(135deg,rgb(0, 0, 0) 0%,rgb(0, 8, 255) 100%);
            color: white;
            border-radius: 0.75rem 0.75rem 0 0 !important;
            padding: 15px 20px;
        }
        .table th {
            background:linear-gradient(135deg,rgb(0, 0, 0) 0%,rgb(0, 8, 255) 100%);
            color: white;
            font-weight: 600;
            border: none;
            padding: 15px 12px;
            font-size: 14px;
        }
        .table td {
            padding: 15px 12px;
            vertical-align: middle;
            border-bottom: 1px solid #e9ecef;
        }
        .btn {
            border-radius: 8px;
            font-weight: 500;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }
        .btn-success {
            background: linear-gradient(135deg, #56ab2f 0%, #a8e6cf 100%);
            border: none;
        }
        .btn-info {
            background: linear-gradient(135deg, #17a2b8 0%, #6f42c1 100%);
            border: none;
        }
        .form-control, .form-select {
            border-radius: 8px;
            border: 2px solid #e9ecef;
            transition: all 0.3s ease;
        }
        .form-control:focus, .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        .foto-preview {
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-top: 8px;
        }
        .foto-preview img {
            max-width: 80px;
            max-height: 50px;
            object-fit: cover;
            border-radius: 6px;
            width: 100%;
        }
        .table-hover tbody tr:hover {
            background-color: #f8f9fa;
            transform: translateY(-1px);
            transition: all 0.2s ease;
        }
        .select2-container--default .select2-selection--single {
            border-radius: 8px;
            border: 2px solid #e9ecef;
            height: 38px;
        }
        .select2-container--default .select2-selection--single:focus {
            border-color: #667eea;
        }
        .alert {
            border-radius: 10px;
            border: none;
        }
        .alert-info {
            background: linear-gradient(135deg, #17a2b8 0%, #6f42c1 100%);
            color: white;
        }
        .alert-success {
            background: linear-gradient(135deg, #56ab2f 0%, #a8e6cf 100%);
            color: #155724;
        }
        .fw-bold {
            font-weight: 700 !important;
        }
        .fw-semibold {
            font-weight: 600 !important;
        }
        .text-primary {
            color: #667eea !important;
        }
        .text-success {
            color: #28a745 !important;
        }
        .file-upload-wrapper {
            position: relative;
            overflow: hidden;
            display: inline-block;
            cursor: pointer;
            border-radius: 8px;
        }
        .file-upload-wrapper input[type=file] {
            font-size: 100px;
            position: absolute;
            left: 0;
            top: 0;
            opacity: 0;
            cursor: pointer;
        }
        .file-upload-btn {
            background: linear-gradient(135deg,rgb(0, 47, 255) 0%,rgb(0, 0, 0) 100%);
            color: white;
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 500;
            display: inline-block;
            transition: all 0.3s ease;
        }
        .file-upload-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }
        .table thead th {
            background: #0008f9;
            color: white;
            border: none;
            padding: 1rem;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.875rem;
            letter-spacing: 0.05em;
        }
    </style>
</head>
<body>
    <?php include '../../templates/navbar.php'; ?>
    
    <?php if(isset($_SESSION['error'])): ?>
    <script>
        Swal.fire({
            icon: 'error',
            title: 'Oops...',
            text: <?= json_encode($_SESSION['error']) ?>,
            timer: 3000,
            showConfirmButton: false
        });
    </script>
    <?php unset($_SESSION['error']); endif; ?>

    <div class="container mt-4">
        <div class="card hover-lift scale-in">
            <div class="card-body">
                <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
                    <div class="d-flex align-items-center gap-3 flex-wrap">
                <h2>Buat Pembelian Mendadak</h2>
            </div>
            <div class="col text-end">
                <a href="index.php" class="btn btn-secondary">
                    <i class='bx bx-arrow-back'></i> Kembali
                </a>
            </div>
        </div>

        <form method="POST" enctype="multipart/form-data" id="formPembelian">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class='bx bx-info-circle me-2'></i>
                        Informasi Pembelian
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="nama_toko" class="form-label fw-semibold">
                                    <i class='bx bx-store me-1 text-primary'></i>
                                    Nama Toko
                                </label>
                                <input type="text" class="form-control" id="nama_toko" name="nama_toko" required>
                            </div>
                            <div class="mb-3">
                                <label for="tanggal" class="form-label fw-semibold">
                                    <i class='bx bx-calendar me-1 text-primary'></i>
                                    Tanggal
                                </label>
                                <input type="date" class="form-control" id="tanggal" name="tanggal" 
                                       value="<?= date('Y-m-d') ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="keterangan" class="form-label fw-semibold">
                                    <i class='bx bx-note me-1 text-primary'></i>
                                    Keterangan
                                </label>
                                <textarea class="form-control" id="keterangan" name="keterangan" rows="4"></textarea>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class='bx bx-package me-2'></i>
                        Detail Barang
                    </h5>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="mb-0 fw-semibold">Daftar Item</h6>
                        <div>
                            <button type="button" class="btn btn-primary" id="btn_add_multiple">
                                <i class='bx bx-plus-circle me-1'></i>Tambah Multiple Item
                            </button>
                            <button type="button" class="btn btn-primary" id="btn_add_row">
                                <i class='bx bx-plus me-1'></i>Tambah Item
                            </button>
                        </div>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover" id="detail_table">
                            <thead>
                                <tr>
                                    <th width="5%">No</th>
                                    <th width="20%">Barang</th>
                                    <th width="10%">Jumlah</th>
                                    <th width="15%">Harga Satuan</th>
                                    <th width="15%">Total</th>
                                    <th width="15%">Foto</th>
                                    <th width="15%">Keterangan Detail</th>
                                    <th width="5%">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td class="text-center fw-bold">1</td>
                                    <td>
                                        <select name="barang_id[]" class="form-select barang-select" required>
                                            <option value="">Pilih Barang</option>
                                            <option value="0">Others</option>
                                            <?php
                                            $barang_result->data_seek(0);
                                            while($barang = $barang_result->fetch_assoc()):
                                            ?>
                                            <option value="<?= $barang['id'] ?>" data-harga="<?= $barang['harga_beli'] ?>">
                                                <?= htmlspecialchars($barang['kode_barang']) ?> - <?= htmlspecialchars($barang['nama_barang']) ?>
                                            </option>
                                            <?php endwhile; ?>
                                        </select>
                                        <input type="text" name="nama_barang_lain[]" class="form-control mt-1 nama-barang-lain" 
                                               placeholder="Nama barang lain" style="display:none;">
                                    </td>
                                    <td>
                                        <input type="number" name="jumlah[]" class="form-control jumlah" min="0.001" step="0.001" value="0" required>
                                    </td>
                                    <td>
                                        <input type="text" name="harga_satuan[]" class="form-control harga-satuan" value="Rp 0" required>
                                    </td>
                                    <td>
                                        <input type="text" name="total[]" class="form-control total fw-bold text-success" readonly>
                                    </td>
                                    <td>
                                        <div class="file-upload-wrapper">
                                            <input type="file" name="foto_barang[]" class="foto-barang" id="foto_barang_1" accept="image/*">
                                            <label class="file-upload-btn" for="foto_barang_1">
                                                <i class='bx bx-camera me-1'></i>Upload Foto
                                            </label>
                                        </div>
                                        <small class="text-muted d-block mt-1">
                                            <i class='bx bx-info-circle me-1'></i>Max 2MB
                                        </small>
                                        <div class="foto-preview" style="display:none;">
                                            <img src="" alt="Preview">
                                        </div>
                                    </td>
                                    <td>
                                        <input type="text" name="keterangan_detail[]" class="form-control keterangan-detail" placeholder="Keterangan detail">
                                    </td>
                                    <td class="text-center">
                                        <button type="button" class="btn btn-danger btn-sm btn-delete-row">
                                            <i class='bx bx-trash'></i>
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Item Status Section -->
                    <div class="row mt-3" id="itemStatusSection" style="display:none;">
                        <div class="col-12">
                            <div class="alert alert-warning">
                                <div class="d-flex align-items-center">
                                    <i class='bx bx-error-circle me-2 fs-4'></i>
                                    <div>
                                        <strong>Status Barang:</strong>
                                        <div class="mt-1">
                                            <span class="badge bg-success me-2">
                                                <i class='bx bx-check-circle me-1'></i>
                                                Barang Valid: <span id="valid_items_count">0</span>
                                            </span>
                                            <span class="badge bg-warning me-2">
                                                <i class='bx bx-x-circle me-1'></i>
                                                Barang "Others": <span id="others_items_count">0</span>
                                            </span>
                                        </div>
                                        <small class="text-muted d-block mt-1">
                                            <i class='bx bx-info-circle me-1'></i>
                                            Barang "Others" tidak dapat dikirim ke gudang. Hanya barang yang sudah terdaftar di master barang yang dapat dikirim.
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Grand Total Section -->
                    <div class="row mt-4">
                        <div class="col-md-6">
                            <div class="alert alert-info">
                                <div class="d-flex align-items-center">
                                    <i class='bx bx-package me-2 fs-4'></i>
                                    <div>
                                        <strong>Total Item: <span id="total_items" class="fw-bold">0</span></strong>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="alert alert-success">
                                <div class="d-flex align-items-center">
                                    <i class='bx bx-money me-2 fs-4'></i>
                                    <div>
                                        <strong>Grand Total: <span id="grand_total" class="fw-bold">Rp 0</span></strong>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="text-end mt-3">
                        <a href="index.php" class="btn btn-secondary me-2">
                            <i class='bx bx-arrow-back me-1'></i>Kembali
                        </a>
                        <button type="submit" class="btn btn-primary">
                                    <i class='bx bx-save'></i> Simpan Pembelian
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        
        // Move itemCounter declaration to the global scope
        let itemCounter = 1;
        let fileInputCounter = 1;

        $(document).ready(function() {
            const $submitBtn = $('button[type="submit"]');
            const submitBtnDefaultHtml = $submitBtn.html();
            let isSubmitting = false;
            let othersConfirmed = false;

            function setSubmittingState(submitting) {
                isSubmitting = submitting;
                if (submitting) {
                    $submitBtn.prop('disabled', true).html('<i class="bx bx-loader-alt bx-spin"></i> Menyimpan...');
                } else {
                    $submitBtn.html(submitBtnDefaultHtml);
                    updateGrandTotal();
                }
            }

            // Inisialisasi Select2
            $('.barang-select').select2();
            $(document).on('select2:open', function() {
                const searchField = document.querySelector('.select2-container--open .select2-search__field');
                if (searchField) {
                    if (!searchField.getAttribute('name')) {
                        searchField.setAttribute('name', 'select2-search');
                    }
                    if (!searchField.getAttribute('id')) {
                        searchField.setAttribute('id', 'select2-search');
                    }
                }
            });
            const barangOptionsHtml = $('#detail_table tbody tr:first .barang-select').html();

            // Function untuk format Rupiah
            function formatRupiah(angka) {
                // Convert to number if it's a string
                angka = typeof angka === 'string' ? parseFloat(angka.replace(/[^\d.]/g, '')) : angka;
                
                // Check if the number has decimal places
                if (angka % 1 === 0) {
                    return 'Rp ' + angka.toLocaleString('id-ID', {minimumFractionDigits: 0, maximumFractionDigits: 0});
                } else {
                    return 'Rp ' + angka.toLocaleString('id-ID', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                }
            }

            // Function untuk parse Rupiah ke float
            function parseRupiah(rupiahString) {
                // Remove 'Rp ', all dots (thousand separators), and replace comma with dot for decimal
                let cleaned = rupiahString.replace('Rp ', '').replace(/\./g, '').replace(',', '.');
                // Handle empty string or non-numeric input gracefully
                return parseFloat(cleaned) || 0;
            }

            function isRowActive($row) {
                const barangId = $row.find('.barang-select').val();
                const jumlah = parseFloat($row.find('.jumlah').val()) || 0;
                return barangId !== '' && jumlah > 0;
            }

            function syncFotoRequired($row) {
                const required = isRowActive($row);
                $row.find('.foto-barang').prop('required', required);
            }

            // Function untuk update total per baris
            function updateTotal($row) {
                const jumlah = parseFloat($row.find('.jumlah').val()) || 0;
                const hargaSatuan = parseRupiah($row.find('.harga-satuan').val());
                const total = jumlah * hargaSatuan;
                $row.find('.total').val(formatRupiah(total));
                syncFotoRequired($row);
                updateGrandTotal();
            }

            // Function untuk update grand total dan total items
            function updateGrandTotal() {
                let grandTotal = 0;
                let totalItems = 0;
                let validRows = 0;
                let validItemsCount = 0;
                let othersItemsCount = 0;
                
                $('.total').each(function() {
                    const total = parseRupiah($(this).val());
                    const $row = $(this).closest('tr');
                    const jumlah = parseFloat($row.find('.jumlah').val()) || 0;
                    const barangId = $row.find('.barang-select').val();
                    
                    if (barangId !== '' && jumlah > 0) {
                        grandTotal += total;
                        totalItems += jumlah;
                        validRows++;
                        
                        // Count valid vs others items
                        if (barangId === '0') {
                            othersItemsCount++;
                        } else {
                            validItemsCount++;
                        }
                    }
                });
                
                $('#grand_total').text(formatRupiah(grandTotal));
                $('#total_items').text(totalItems.toFixed(3));
                
                // Update item status display
                $('#valid_items_count').text(validItemsCount);
                $('#others_items_count').text(othersItemsCount);
                
                // Show/hide status section based on items
                if (validItemsCount > 0 || othersItemsCount > 0) {
                    $('#itemStatusSection').show();
                } else {
                    $('#itemStatusSection').hide();
                }
                
                // Update submit button state
                if (!isSubmitting) {
                    if (validRows === 0) {
                        $submitBtn.prop('disabled', true).addClass('btn-secondary').removeClass('btn-primary');
                    } else {
                        $submitBtn.prop('disabled', false).addClass('btn-primary').removeClass('btn-secondary');
                    }
                }
            }

            // Function to add multiple items at once
            function addMultipleItems() {
                const itemCount = prompt('Berapa item yang ingin ditambahkan? (1-10)', '3');
                const count = parseInt(itemCount);
                
                if (isNaN(count) || count < 1 || count > 10) {
                    alert('Masukkan angka antara 1-10');
                    return;
                }
                
                for (let i = 0; i < count; i++) {
                    addEmptyItemRow();
                }
                
                // Show success message
                Swal.fire({
                    icon: 'success',
                    title: 'Berhasil!',
                    text: `${count} item berhasil ditambahkan`,
                    timer: 1500,
                    showConfirmButton: false
                });
            }

            // Function to add an empty item row
            function addEmptyItemRow() {
                itemCounter++;
                fileInputCounter++;
                const fotoInputId = `foto_barang_${fileInputCounter}`;
                const $itemTableBody = $('#detail_table tbody');
                const newRow = `
                    <tr>
                        <td class="text-center">${itemCounter}</td>
                        <td>
                            <select name="barang_id[]" class="form-select barang-select" required>
                                ${barangOptionsHtml}
                            </select>
                            <input type="text" name="nama_barang_lain[]" class="form-control mt-1 nama-barang-lain" 
                                   placeholder="Nama barang lain" style="display:none;">
                        </td>
                        <td>
                            <input type="number" name="jumlah[]" class="form-control jumlah" min="0.001" step="0.001" value="0" required>
                        </td>
                        <td>
                            <input type="text" name="harga_satuan[]" class="form-control harga-satuan" value="Rp 0" required>
                        </td>
                        <td>
                            <input type="text" name="total[]" class="form-control total" readonly>
                        </td>
                        <td>
                            <div class="file-upload-wrapper">
                                <input type="file" name="foto_barang[]" class="foto-barang" id="${fotoInputId}" accept="image/*">
                                <label class="file-upload-btn" for="${fotoInputId}">
                                    <i class='bx bx-camera me-1'></i>Upload Foto
                                </label>
                            </div>
                            <small class="text-muted d-block mt-1">
                                <i class='bx bx-info-circle me-1'></i>Max 2MB
                            </small>
                            <div class="foto-preview" style="display:none;">
                                <img src="" alt="Preview">
                            </div>
                        </td>
                        <td>
                            <input type="text" name="keterangan_detail[]" class="form-control keterangan-detail" placeholder="Keterangan detail">
                        </td>
                        <td class="text-center">
                                <button type="button" class="btn btn-danger btn-sm btn-delete-row">
                                    <i class='bx bx-trash'></i>
                                </button>
                        </td>
                    </tr>
                `;
                
                $itemTableBody.append(newRow);
                const $newRow = $itemTableBody.find('tr').last();
                $newRow.find('.barang-select').val('').select2();

                // Bind change event for the new select element
                $newRow.find('.barang-select').on('change', function() {
                    const selectedBarangId = $(this).val();
                    const $row = $(this).closest('tr');
                    const $hargaSatuanInput = $row.find('.harga-satuan');
                    const $namaBarangLain = $row.find('.nama-barang-lain');

                    if (selectedBarangId === '0') {
                        // Others selected
                        $namaBarangLain.show();
                        $hargaSatuanInput.val('Rp 0');
                        $row.addClass('table-warning').removeClass('table-success');
                        $row.find('td:first').html('<i class="bx bx-x-circle text-warning"></i>');
                    } else if (selectedBarangId !== '') {
                        // Master item selected
                        $namaBarangLain.hide();
                        const selectedOption = $(this).find(':selected');
                        const hargaBeli = selectedOption.data('harga');
                        $hargaSatuanInput.val(formatRupiah(hargaBeli));
                        $row.addClass('table-success').removeClass('table-warning');
                        $row.find('td:first').html('<i class="bx bx-check-circle text-success"></i>');
                    } else {
                        // 'Pilih Barang' selected
                        $namaBarangLain.hide();
                        $hargaSatuanInput.val('Rp 0');
                        $row.removeClass('table-success table-warning');
                        $row.find('td:first').text($row.index() + 1);
                    }
                    updateTotal($row);
                });

                // Bind input event for quantity and price on the new row
                $newRow.find('.jumlah, .harga-satuan').on('input', function() {
                    const $row = $(this).closest('tr');
                    updateTotal($row);
                });

                // Bind focusout/focusin for price formatting on the new row
                $newRow.find('.harga-satuan').on('focusout', function() {
                    const $row = $(this).closest('tr');
                    const value = parseRupiah($(this).val());
                    $(this).val(formatRupiah(value));
                    updateTotal($row);
                });

                // Re-bind focusin for price input on the new row
                $newRow.find('.harga-satuan').on('focusin', function() {
                    const value = parseRupiah($(this).val());
                    $(this).val(value.toFixed(2));
                    $(this).select();
                });

                // Bind file input change event for photo preview
                $newRow.find('.foto-barang').on('change', function() {
                    const file = this.files[0];
                    const $row = $(this).closest('tr');
                    const $preview = $row.find('.foto-preview');
                    const $previewImg = $preview.find('img');
                    const $uploadBtn = $row.find('.file-upload-btn');
                    
                    if (file) {
                        if (file.size > 2 * 1024 * 1024) {
                            Swal.fire({
                                icon: 'error',
                                title: 'File Terlalu Besar',
                                text: 'Ukuran file maksimal 2MB'
                            });
                            this.value = '';
                            $preview.hide();
                            $uploadBtn.html('<i class="bx bx-camera me-1"></i>Upload Foto');
                            return;
                        }
                        
                        const validTypes = ['image/jpeg', 'image/png', 'image/gif'];
                        if (!validTypes.includes(file.type)) {
                            Swal.fire({
                                icon: 'error',
                                title: 'Format Tidak Didukung',
                                text: 'Gunakan format JPG, PNG, atau GIF'
                            });
                            this.value = '';
                            $preview.hide();
                            $uploadBtn.html('<i class="bx bx-camera me-1"></i>Upload Foto');
                            return;
                        }
                        
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            $previewImg.attr('src', e.target.result);
                            $preview.show();
                            $uploadBtn.html('<i class="bx bx-check me-1"></i>Foto Dipilih');
                            $uploadBtn.css('background', 'linear-gradient(135deg, #56ab2f 0%, #a8e6cf 100%)');
                        }
                        reader.readAsDataURL(file);
                    } else {
                        $preview.hide();
                        $uploadBtn.html('<i class="bx bx-camera me-1"></i>Upload Foto');
                        $uploadBtn.css('background', 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)');
                    }
                });

                // Bind delete button click handler for the new row
                $newRow.find('.btn-delete-row').on('click', function() {
                    const $row = $(this).closest('tr');
                    Swal.fire({
                        title: 'Hapus Item?',
                        text: "Item ini akan dihapus dari daftar",
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#d33',
                        cancelButtonColor: '#3085d6',
                        confirmButtonText: 'Ya, Hapus!',
                        cancelButtonText: 'Batal'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            $row.remove();
                            renumberRows();
                            updateGrandTotal();
                            Swal.fire(
                                'Terhapus!',
                                'Item berhasil dihapus.',
                                'success'
                            );
                        }
                    });
                });

                updateTotal($newRow);
            }

            // Function to renumber rows
            function renumberRows() {
                $('#detail_table tbody tr').each(function(index) {
                    const $row = $(this);
                    const barangId = $row.find('.barang-select').val();
                    
                    if (barangId === '0') {
                        // Others item - show warning icon
                        $row.find('td:first').html('<i class="bx bx-x-circle text-warning"></i>');
                    } else if (barangId !== '') {
                        // Valid item - show success icon
                        $row.find('td:first').html('<i class="bx bx-check-circle text-success"></i>');
                    } else {
                        // No selection - show number
                        $row.find('td:first').text(index + 1);
                    }
                });
                itemCounter = $('#detail_table tbody tr').length;
            }

            // Initial setup for the first row
            $('.barang-select').on('change', function() {
                const selectedBarangId = $(this).val();
                const $row = $(this).closest('tr');
                const $hargaSatuanInput = $row.find('.harga-satuan');
                const $namaBarangLain = $row.find('.nama-barang-lain');

                if (selectedBarangId === '0') {
                    // Others selected
                    $namaBarangLain.show();
                    $hargaSatuanInput.val('Rp 0');
                    $row.addClass('table-warning').removeClass('table-success');
                    $row.find('td:first').html('<i class="bx bx-x-circle text-warning"></i>');
                } else if (selectedBarangId !== '') {
                    // Master item selected
                    $namaBarangLain.hide();
                    const selectedOption = $(this).find(':selected');
                    const hargaBeli = selectedOption.data('harga');
                    $hargaSatuanInput.val(formatRupiah(hargaBeli));
                    $row.addClass('table-success').removeClass('table-warning');
                    $row.find('td:first').html('<i class="bx bx-check-circle text-success"></i>');
                } else {
                    // 'Pilih Barang' selected
                    $namaBarangLain.hide();
                    $hargaSatuanInput.val('Rp 0');
                    $row.removeClass('table-success table-warning');
                    $row.find('td:first').text($row.index() + 1);
                }
                updateTotal($row);
            });

            // Event handler for quantity and price input on the initial row
            $(document).on('input', '.jumlah, .harga-satuan', function() {
                 updateTotal($(this).closest('tr'));
            });

            // Event handler for format harga satuan on the initial row
            $(document).on('focusout', '.harga-satuan', function() {
                const value = parseRupiah($(this).val());
                $(this).val(formatRupiah(value));
                updateTotal($(this).closest('tr'));
            });

            $(document).on('focusin', '.harga-satuan', function() {
                const value = parseRupiah($(this).val());
                $(this).val(value.toFixed(2));
                $(this).select();
            });

            // Add row button click handler
            $('#btn_add_row').click(function() {
                addEmptyItemRow();
            });

            // Add multiple items button click handler
            $('#btn_add_multiple').click(function() {
                addMultipleItems();
            });

            // Remove row button click handler
            $(document).on('click', '.btn-delete-row', function() {
                const $row = $(this).closest('tr');
                Swal.fire({
                    title: 'Hapus Item?',
                    text: "Item ini akan dihapus dari daftar",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: 'Ya, Hapus!',
                    cancelButtonText: 'Batal'
                }).then((result) => {
                    if (result.isConfirmed) {
                        $row.remove();
                        renumberRows();
                        updateGrandTotal();
                        Swal.fire(
                            'Terhapus!',
                            'Item berhasil dihapus.',
                            'success'
                        );
                    }
                });
            });

            // Bind file input change event for photo preview on initial row
            $('.foto-barang').on('change', function() {
                const file = this.files[0];
                const $row = $(this).closest('tr');
                const $preview = $row.find('.foto-preview');
                const $previewImg = $preview.find('img');
                const $uploadBtn = $row.find('.file-upload-btn');
                
                if (file) {
                    if (file.size > 2 * 1024 * 1024) {
                        Swal.fire({
                            icon: 'error',
                            title: 'File Terlalu Besar',
                            text: 'Ukuran file maksimal 2MB'
                        });
                        this.value = '';
                        $preview.hide();
                        $uploadBtn.html('<i class="bx bx-camera me-1"></i>Upload Foto');
                        return;
                    }
                    
                    const validTypes = ['image/jpeg', 'image/png', 'image/gif'];
                    if (!validTypes.includes(file.type)) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Format Tidak Didukung',
                            text: 'Gunakan format JPG, PNG, atau GIF'
                        });
                        this.value = '';
                        $preview.hide();
                        $uploadBtn.html('<i class="bx bx-camera me-1"></i>Upload Foto');
                        return;
                    }
                    
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        $previewImg.attr('src', e.target.result);
                        $preview.show();
                        $uploadBtn.html('<i class="bx bx-check me-1"></i>Foto Dipilih');
                        $uploadBtn.css('background', 'linear-gradient(135deg, #56ab2f 0%, #a8e6cf 100%)');
                    }
                    reader.readAsDataURL(file);
                } else {
                    $preview.hide();
                    $uploadBtn.html('<i class="bx bx-camera me-1"></i>Upload Foto');
                    $uploadBtn.css('background', 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)');
                }
            });

            // Form submission handler
            $('#formPembelian').on('submit', function(e) {
                if (isSubmitting) {
                    return true;
                }

                const validRows = $('#detail_table tbody tr').filter(function() {
                    const barangId = $(this).find('.barang-select').val();
                    const jumlah = parseFloat($(this).find('.jumlah').val()) || 0;
                    return barangId !== '' && jumlah > 0;
                }).length;

                if (validRows === 0) {
                    e.preventDefault();
                    Swal.fire({
                        icon: 'error',
                        title: 'Data Tidak Lengkap',
                        text: 'Minimal harus ada satu item dengan jumlah lebih dari 0'
                    });
                    return false;
                }

                const missingPhotoRows = [];
                $('#detail_table tbody tr').each(function() {
                    const $row = $(this);
                    const barangId = $row.find('.barang-select').val();
                    const jumlah = parseFloat($row.find('.jumlah').val()) || 0;
                    if (barangId !== '' && jumlah > 0) {
                        const input = $row.find('.foto-barang')[0];
                        if (!input || !input.files || input.files.length === 0) {
                            missingPhotoRows.push($row.index() + 1);
                        }
                    }
                });

                if (missingPhotoRows.length > 0) {
                    e.preventDefault();
                    Swal.fire({
                        icon: 'error',
                        title: 'Foto Wajib',
                        text: 'Foto wajib diupload untuk item: ' + missingPhotoRows.join(', ')
                    });
                    return false;
                }

                // Validasi untuk barang "Others" - tampilkan warning
                const othersItems = [];
                const missingOthersNames = [];
                const validItems = [];
                
                $('#detail_table tbody tr').each(function() {
                    const barangId = $(this).find('.barang-select').val();
                    const jumlah = parseFloat($(this).find('.jumlah').val()) || 0;
                    const $namaBarangLain = $(this).find('.nama-barang-lain');
                    const namaBarangLain = ($namaBarangLain.val() || '').trim();
                    
                    if (barangId !== '' && jumlah > 0) {
                        if (barangId === '0') {
                            const itemName = namaBarangLain || 'Barang Others';
                            othersItems.push(itemName);
                            if (!namaBarangLain) {
                                missingOthersNames.push($namaBarangLain);
                            }
                        } else {
                            validItems.push({
                                barangId: barangId,
                                jumlah: jumlah
                            });
                        }
                    }
                });

                if (missingOthersNames.length > 0) {
                    e.preventDefault();
                    Swal.fire({
                        icon: 'error',
                        title: 'Nama Barang Others Wajib',
                        text: 'Isi nama untuk semua item Others terlebih dulu.'
                    }).then(() => {
                        try {
                            const $first = missingOthersNames[0];
                            $first.show().focus();
                        } catch (err) {}
                    });
                    return false;
                }

                // Jika ada barang "Others", tampilkan warning
                if (othersItems.length > 0 && !othersConfirmed) {
                    e.preventDefault();
                    
                    let warningMessage = `
                        <div class="text-start">
                            <p><strong>Barang "Others" yang tidak dapat dikirim ke gudang:</strong></p>
                            <ul class="text-start">
                                ${othersItems.map(item => `<li>${item}</li>`).join('')}
                            </ul>
                            <p class="mt-3">
                                <i class="bx bx-info-circle text-info"></i>
                                <strong>Informasi:</strong> Barang "Others" tidak dapat dikirim ke gudang. 
                                Hanya barang yang sudah terdaftar di master barang yang dapat dikirim ke gudang.
                            </p>
                            <p class="mt-2">
                                <i class="bx bx-check-circle text-success"></i>
                                <strong>Barang valid yang dapat dikirim:</strong> ${validItems.length} item
                            </p>
                        </div>
                    `;

                    Swal.fire({
                        title: 'Barang "Others" Ditemukan',
                        html: warningMessage,
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonText: 'Lanjutkan Simpan',
                        cancelButtonText: 'Batal',
                        confirmButtonColor: '#3085d6',
                        cancelButtonColor: '#d33',
                        reverseButtons: true
                    }).then((result) => {
                        if (result.isConfirmed) {
                            const formEl = document.getElementById('formPembelian');
                            if (formEl && typeof formEl.reportValidity === 'function' && !formEl.reportValidity()) {
                                return;
                            }
                            othersConfirmed = true;
                            setSubmittingState(true);
                            formEl.submit();
                        } else {
                            setSubmittingState(false);
                        }
                    });
                    return false;
                }

                setSubmittingState(true);
            });

            // Initialize first row
            updateTotal($('#detail_table tbody tr:first'));
        });
    </script>
</body>
</html>
