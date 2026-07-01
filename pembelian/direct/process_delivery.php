<?php
session_start();
require_once '../../config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit();
}

// Validasi input
if (!isset($_POST['purchase_id']) || !isset($_POST['gudang_id'])) {
    $_SESSION['error'] = "Data tidak lengkap!";
    header("Location: index.php");
    exit();
}

$purchase_id = $_POST['purchase_id'];
$gudang_id = $_POST['gudang_id'];
$user_id = $_SESSION['user_id'];

// Aktifkan debugging mode
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Mulai transaksi database
$conn->begin_transaction();

try {
    // 1. Ambil data pembelian
    $purchase_sql = "SELECT * FROM direct_purchase WHERE id = ?";
    $purchase_stmt = $conn->prepare($purchase_sql);
    $purchase_stmt->bind_param('i', $purchase_id);
    $purchase_stmt->execute();
    $purchase_result = $purchase_stmt->get_result();
    
    if ($purchase_result->num_rows === 0) {
        throw new Exception("Data pembelian tidak ditemukan!");
    }
    
    $purchase = $purchase_result->fetch_assoc();
    
    // Validasi status pembelian
    if ($purchase['status'] !== 'payment') {
        throw new Exception("Status pembelian tidak valid untuk dikirim ke gudang!");
    }
    
    // 2. Ambil detail pembelian
    $detail_sql = "SELECT ddp.*, b.nama_barang, b.satuan 
                  FROM detail_direct_purchase ddp 
                  LEFT JOIN barang b ON ddp.barang_id = b.id 
                  WHERE ddp.direct_purchase_id = ?";
    $detail_stmt = $conn->prepare($detail_sql);
    $detail_stmt->bind_param('i', $purchase_id);
    $detail_stmt->execute();
    $detail_result = $detail_stmt->get_result();
    
    if ($detail_result->num_rows === 0) {
        throw new Exception("Detail pembelian tidak ditemukan!");
    }
    
    // Validasi: Pastikan ada minimal satu item valid (bukan others) untuk diproses
    $valid_items_count = 0;
    $detail_result->data_seek(0);
    while ($item = $detail_result->fetch_assoc()) {
        if (!empty($item['barang_id'])) {
            $valid_items_count++;
        }
    }
    
    if ($valid_items_count === 0) {
        throw new Exception("Tidak ada item valid untuk dikirim ke gudang. Semua item adalah 'others' yang tidak dapat diproses.");
    }
    
    // 3. Buat kode transaksi stok masuk
    $today = date('Ymd');
    
    // Periksa apakah tabel transaksi_stock sudah ada
    $check_table_sql = "SHOW TABLES LIKE 'transaksi_stock'";
    $check_table_result = $conn->query($check_table_sql);
    
    if ($check_table_result->num_rows === 0) {
        // Buat tabel transaksi_stock jika belum ada
        $create_table_sql = "CREATE TABLE transaksi_stock (
            id INT(11) NOT NULL AUTO_INCREMENT,
            kode_transaksi VARCHAR(20) NOT NULL,
            tanggal DATE NOT NULL,
            gudang_id INT(11) NOT NULL,
            keterangan TEXT,
            created_by INT(11) NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME DEFAULT NULL,
            PRIMARY KEY (id),
            KEY gudang_id (gudang_id),
            KEY created_by (created_by),
            CONSTRAINT transaksi_stock_ibfk_1 FOREIGN KEY (gudang_id) REFERENCES gudang (id),
            CONSTRAINT transaksi_stock_ibfk_2 FOREIGN KEY (created_by) REFERENCES users (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        
        if (!$conn->query($create_table_sql)) {
            throw new Exception("Gagal membuat tabel transaksi_stock: " . $conn->error);
        }
        
        // Buat tabel detail_transaksi_stock jika belum ada
        $create_detail_table_sql = "CREATE TABLE detail_transaksi_stock (
            id INT(11) NOT NULL AUTO_INCREMENT,
            transaksi_stock_id INT(11) NOT NULL,
            barang_id INT(11) NOT NULL,
            jumlah DECIMAL(10,2) NOT NULL,
            keterangan TEXT,
            PRIMARY KEY (id),
            KEY transaksi_stock_id (transaksi_stock_id),
            KEY barang_id (barang_id),
            CONSTRAINT detail_transaksi_stock_ibfk_1 FOREIGN KEY (transaksi_stock_id) REFERENCES transaksi_stock (id) ON DELETE CASCADE,
            CONSTRAINT detail_transaksi_stock_ibfk_2 FOREIGN KEY (barang_id) REFERENCES barang (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        
        if (!$conn->query($create_detail_table_sql)) {
            throw new Exception("Gagal membuat tabel detail_transaksi_stock: " . $conn->error);
        }
    }
    
    // Periksa apakah tabel gudang_stok sudah ada
    $check_stok_table_sql = "SHOW TABLES LIKE 'gudang_stok'";
    $check_stok_table_result = $conn->query($check_stok_table_sql);
    
    if ($check_stok_table_result->num_rows === 0) {
        // Buat tabel gudang_stok jika belum ada
        $create_stok_table_sql = "CREATE TABLE gudang_stok (
            id INT(11) NOT NULL AUTO_INCREMENT,
            gudang_id INT(11) NOT NULL,
            barang_id INT(11) NOT NULL,
            jumlah DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            created_at DATETIME NOT NULL,
            updated_at DATETIME DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY gudang_barang (gudang_id,barang_id),
            KEY barang_id (barang_id),
            CONSTRAINT gudang_stok_ibfk_1 FOREIGN KEY (gudang_id) REFERENCES gudang (id),
            CONSTRAINT gudang_stok_ibfk_2 FOREIGN KEY (barang_id) REFERENCES barang (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        
        if (!$conn->query($create_stok_table_sql)) {
            throw new Exception("Gagal membuat tabel gudang_stok: " . $conn->error);
        }
    }
    
    // Periksa struktur tabel gudang_stok
    $check_structure_sql = "DESCRIBE gudang_stok";
    $check_structure_result = $conn->query($check_structure_sql);
    if ($check_structure_result === false) {
        error_log("Error saat memeriksa struktur tabel gudang_stok: " . $conn->error);
    } else {
        while ($row = $check_structure_result->fetch_assoc()) {
            error_log("Kolom: " . $row['Field'] . ", Tipe: " . $row['Type']);
        }
    }
    
    // Buat kode transaksi
    $kode_sql = "SELECT MAX(SUBSTRING(kode_transaksi, 4)) as max_num 
                FROM transaksi_stock 
                WHERE kode_transaksi LIKE 'SM-%'";
    $kode_result = $conn->query($kode_sql);
    
    if ($kode_result === false) {
        throw new Exception("Error saat mengambil kode transaksi: " . $conn->error);
    }
    
    $kode_row = $kode_result->fetch_assoc();
    $num = (int)($kode_row['max_num'] ?? 0) + 1;
    $kode_transaksi = "SM-" . str_pad($num, 6, '0', STR_PAD_LEFT);
    
    // 4. Buat transaksi stok masuk
    $stok_masuk_sql = "INSERT INTO transaksi_stock (kode_transaksi, tanggal, gudang_id, keterangan, created_by, created_at) 
                    VALUES (?, NOW(), ?, ?, ?, NOW())";
    $stok_masuk_stmt = $conn->prepare($stok_masuk_sql);
    $keterangan = "Dari pembelian mendadak: " . $purchase['no_transaksi'];
    $stok_masuk_stmt->bind_param('siss', $kode_transaksi, $gudang_id, $keterangan, $user_id);
    
    if (!$stok_masuk_stmt->execute()) {
        throw new Exception("Gagal membuat transaksi stok masuk: " . $stok_masuk_stmt->error);
    }
    
    $stok_masuk_id = $conn->insert_id;
    
    // 5. Proses setiap item
    $detail_result->data_seek(0); // Reset pointer hasil query
    while ($item = $detail_result->fetch_assoc()) {
        // Skip items dengan barang_id kosong (others) - tidak akan masuk ke gudang
        if (empty($item['barang_id'])) {
            error_log("Melewati item others: " . ($item['keterangan'] ?? 'Barang Others') . " - tidak akan masuk ke gudang");
            continue; // Skip ke item berikutnya
        }
        error_log("Memproses barang ID: " . $item['barang_id'] . ", Jumlah: " . $item['jumlah']);
        
        // 5.1 Tambahkan detail stok masuk
        $detail_masuk_sql = "INSERT INTO detail_transaksi_stock (transaksi_stock_id, barang_id, jumlah, keterangan) 
                        VALUES (?, ?, ?, ?)";
        $detail_masuk_stmt = $conn->prepare($detail_masuk_sql);
        $detail_masuk_stmt->bind_param('iids', $stok_masuk_id, $item['barang_id'], $item['jumlah'], $item['keterangan']);
        
        if (!$detail_masuk_stmt->execute()) {
            throw new Exception("Gagal menambahkan detail stok masuk: " . $detail_masuk_stmt->error);
        }
        
        // 5.2 Periksa apakah barang sudah ada di gudang
        $stok_sql = "SELECT id FROM gudang_stok WHERE gudang_id = ? AND barang_id = ?";
        $stok_stmt = $conn->prepare($stok_sql);
        $stok_stmt->bind_param('ii', $gudang_id, $item['barang_id']);
        $stok_stmt->execute();
        $stok_result = $stok_stmt->get_result();
        
        if ($stok_result->num_rows > 0) {
            // Update stok yang sudah ada
            $stok_row = $stok_result->fetch_assoc();
            $update_stok_sql = "UPDATE gudang_stok SET stok_awal = stok_awal + ?, updated_at = NOW() WHERE id = ?";
            $update_stok_stmt = $conn->prepare($update_stok_sql);
            $update_stok_stmt->bind_param('di', $item['jumlah'], $stok_row['id']);
            
            if (!$update_stok_stmt->execute()) {
                throw new Exception("Gagal memperbarui stok gudang: " . $update_stok_stmt->error);
            } else {
                error_log("Berhasil update stok_awal untuk barang ID: " . $item['barang_id'] . ", Jumlah baru: " . ($item['jumlah'] + $stok_row['stok_awal']));
            }
        } else {
            // Tambahkan stok baru
            $insert_stok_sql = "INSERT INTO gudang_stok (gudang_id, barang_id, stok_awal, jumlah, created_at) 
                              VALUES (?, ?, ?, 0, NOW())";
            $insert_stok_stmt = $conn->prepare($insert_stok_sql);
            $insert_stok_stmt->bind_param('iid', $gudang_id, $item['barang_id'], $item['jumlah']);
            
            if (!$insert_stok_stmt->execute()) {
                throw new Exception("Gagal menambahkan stok baru: " . $insert_stok_stmt->error);
            } else {
                error_log("Berhasil insert stok baru untuk barang ID: " . $item['barang_id'] . ", Stok awal: " . $item['jumlah']);
            }
        }
    }
    
    // 6. Update status pembelian menjadi stok_masuk
    $update_purchase_sql = "UPDATE direct_purchase SET status = 'stok_masuk', updated_at = NOW() WHERE id = ?";
    $update_purchase_stmt = $conn->prepare($update_purchase_sql);
    $update_purchase_stmt->bind_param('i', $purchase_id);
    
    if (!$update_purchase_stmt->execute()) {
        throw new Exception("Gagal memperbarui status pembelian: " . $update_purchase_stmt->error);
    }
    
    // Sebelum commit transaksi, simpan informasi stok yang ditambahkan
    $_SESSION['added_stocks'] = [];
    $detail_result->data_seek(0); // Reset pointer hasil query
    while ($item = $detail_result->fetch_assoc()) {
        // Skip items dengan barang_id kosong (others) - tidak akan masuk ke gudang
        if (empty($item['barang_id'])) {
            continue; // Skip ke item berikutnya
        }
        
        // Ambil data lengkap dari master barang
        $barang_sql = "SELECT b.*, s.nama_satuan 
                      FROM barang b 
                      LEFT JOIN satuan s ON b.satuan_id = s.id 
                      WHERE b.id = ?";
        $barang_stmt = $conn->prepare($barang_sql);
        $barang_stmt->bind_param('i', $item['barang_id']);
        $barang_stmt->execute();
        $barang_result = $barang_stmt->get_result();
        $barang_data = $barang_result->fetch_assoc();
        
        $_SESSION['added_stocks'][] = [
            'nama_barang' => $barang_data['nama_barang'] ?? 'Barang #' . $item['barang_id'],
            'jumlah' => $item['jumlah'],
            'satuan' => $barang_data['nama_satuan'] ?? 'Unit',
            'kode_barang' => $barang_data['kode_barang'] ?? '',
            'kategori_id' => $barang_data['kategori_id'] ?? '',
            'harga_beli' => $barang_data['harga_beli'] ?? 0,
            'harga_jual' => $barang_data['harga_jual'] ?? 0,
            'stok_minimum' => $barang_data['stok_minimum'] ?? 0
        ];
    }
    
    // Ambil informasi gudang
    $gudang_sql = "SELECT * FROM gudang WHERE id = ?";
    $gudang_stmt = $conn->prepare($gudang_sql);
    $gudang_stmt->bind_param('i', $gudang_id);
    $gudang_stmt->execute();
    $gudang_result = $gudang_stmt->get_result();
    $gudang_data = $gudang_result->fetch_assoc();
    
    $_SESSION['gudang_info'] = $gudang_data;
    
    // Commit transaksi jika semua berhasil
    $conn->commit();
    
    // Tambahkan log untuk debugging
    error_log("Transaksi berhasil di-commit untuk pembelian ID: $purchase_id, Gudang ID: $gudang_id");
    
    // Periksa stok setelah transaksi
    $check_stocks = [];
    $detail_result->data_seek(0); // Reset pointer hasil query
    while ($item = $detail_result->fetch_assoc()) {
        // Skip items dengan barang_id kosong (others) - tidak akan masuk ke gudang
        if (empty($item['barang_id'])) {
            continue; // Skip ke item berikutnya
        }
        
        $check_sql = "SELECT gs.stok_awal, b.nama_barang, b.satuan 
                     FROM gudang_stok gs 
                     JOIN barang b ON gs.barang_id = b.id 
                     WHERE gs.gudang_id = ? AND gs.barang_id = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param('ii', $gudang_id, $item['barang_id']);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        $check_data = $check_result->fetch_assoc();
        
        if ($check_data) {
            error_log("Stok awal setelah transaksi untuk barang ID: " . $item['barang_id'] . ", Jumlah: " . $check_data['stok_awal']);
            $check_stocks[] = [
                'nama_barang' => $check_data['nama_barang'],
                'jumlah' => $check_data['stok_awal'],
                'satuan' => $check_data['satuan']
            ];
        } else {
            error_log("Tidak ada stok untuk barang ID: " . $item['barang_id'] . " di gudang ID: " . $gudang_id);
        }
    }
    
    $_SESSION['current_stocks'] = $check_stocks;
    
    $_SESSION['success'] = "Barang berhasil dikirim ke gudang dan stok awal telah ditambahkan!";
    header("Location: index.php");
    exit();
    
} catch (Exception $e) {
    // Rollback transaksi jika terjadi kesalahan
    $conn->rollback();
    
    $_SESSION['error'] = "Gagal mengirim barang ke gudang: " . $e->getMessage();
    header("Location: index.php");
    exit();
}
?>
