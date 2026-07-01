<?php
session_start();
require_once '../../config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit();
}

// Fungsi untuk menghasilkan nomor transaksi unik
function generateTransactionNumber($conn, $prefix = 'TRF') {
    $date = date('Ymd');
    // Query untuk mendapatkan nomor urut terakhir untuk tanggal hari ini
    $sql = "SELECT COUNT(*) as count FROM transaksi_transfer WHERE DATE(created_at) = CURDATE()";
    $result = $conn->query($sql);
    $row = $result->fetch_assoc();
    $count = $row['count'] + 1; // Tambahkan 1 untuk nomor urut berikutnya

    // Format nomor urut menjadi 4 digit (contoh: 0001, 0002)
    $sequence = str_pad($count, 4, '0', STR_PAD_LEFT);

    return $prefix . '-' . $date . $sequence;
}

// Proses tambah transfer stok
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add') {
    db_drop_column_if_exists($conn, 'gudang_stok', 'snapshot_date');
    $conn->begin_transaction();
    
    try {
        // Validasi input
        $no_transaksi = $_POST['no_transaksi'] ?? generateTransactionNumber($conn);
        $tanggal = $_POST['tanggal'] ?? date('Y-m-d');
        $gudang_asal_id = (int)$_POST['gudang_asal_id'];
        $gudang_tujuan_id = (int)$_POST['gudang_tujuan_id'];
        $keterangan = $_POST['keterangan'] ?? '';
        
        // Validasi gudang asal dan tujuan tidak boleh sama
        if ($gudang_asal_id === $gudang_tujuan_id) {
            throw new Exception("Gudang asal dan tujuan tidak boleh sama!");
        }
        
        // Insert header transaksi
        $sql = "INSERT INTO transaksi_transfer (no_transaksi, tanggal, gudang_asal_id, gudang_tujuan_id, keterangan, created_by) 
                VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            throw new Exception("Prepare statement failed for header: " . $conn->error);
        }
        $stmt->bind_param('ssiisi', $no_transaksi, $tanggal, $gudang_asal_id, $gudang_tujuan_id, $keterangan, $_SESSION['user_id']);
        
        if (!$stmt->execute()) {
            throw new Exception("Execute statement failed for header: " . $stmt->error);
        }
        
        $transaksi_id = $conn->insert_id;
        $stmt->close();
        
        // Process detail items
        $barang_ids = $_POST['barang_id'] ?? [];
        $jumlah_array = $_POST['jumlah'] ?? [];
        $detail_keterangan = $_POST['detail_keterangan'] ?? [];
        
        if (empty($barang_ids)) {
            throw new Exception("Tidak ada barang yang dipilih!");
        }
        
        // Prepare statement untuk detail transaksi
        $sql_detail = "INSERT INTO detail_transaksi_transfer (transaksi_transfer_id, barang_id, jumlah, keterangan) 
                       VALUES (?, ?, ?, ?)";
        $stmt_detail = $conn->prepare($sql_detail);
        if ($stmt_detail === false) {
            throw new Exception("Prepare statement failed for detail: " . $conn->error);
        }
        
        // Prepare statement untuk stok history (keluar dari gudang asal)
        $sql_history_out = "INSERT INTO stok_history (tanggal, barang_id, gudang_id, jumlah, jenis_transaksi, keterangan, referensi, created_by) 
                           VALUES (?, ?, ?, ?, 'transfer_out', ?, ?, ?)";
        $stmt_history_out = $conn->prepare($sql_history_out);
        if ($stmt_history_out === false) {
            throw new Exception("Prepare statement failed for history out: " . $conn->error);
        }
        
        // Prepare statement untuk stok history (masuk ke gudang tujuan)
        $sql_history_in = "INSERT INTO stok_history (tanggal, barang_id, gudang_id, jumlah, jenis_transaksi, keterangan, referensi, created_by) 
                          VALUES (?, ?, ?, ?, 'transfer_in', ?, ?, ?)";
        $stmt_history_in = $conn->prepare($sql_history_in);
        if ($stmt_history_in === false) {
            throw new Exception("Prepare statement failed for history in: " . $conn->error);
        }
        
        // Prepare statement untuk update stok di gudang asal
        $sql_update_stok_asal = "UPDATE gudang_stok SET jumlah = jumlah - ?, stok_awal = stok_awal - ? WHERE gudang_id = ? AND barang_id = ? AND detail_barang = ?";
        $stmt_update_stok_asal = $conn->prepare($sql_update_stok_asal);
        if ($stmt_update_stok_asal === false) {
            throw new Exception("Prepare statement failed for update stok asal: " . $conn->error);
        }
        
        // Prepare statement untuk cek stok di gudang tujuan
        $sql_check_stok_tujuan = "SELECT id, jumlah, stok_awal FROM gudang_stok WHERE gudang_id = ? AND barang_id = ? AND detail_barang = ?";
        $stmt_check_stok_tujuan = $conn->prepare($sql_check_stok_tujuan);
        if ($stmt_check_stok_tujuan === false) {
            throw new Exception("Prepare statement failed for check stok tujuan: " . $conn->error);
        }
        
        // Prepare statement untuk update stok di gudang tujuan
        $sql_update_stok_tujuan = "UPDATE gudang_stok SET jumlah = jumlah + ?, stok_awal = stok_awal + ? WHERE gudang_id = ? AND barang_id = ? AND detail_barang = ?";
        $stmt_update_stok_tujuan = $conn->prepare($sql_update_stok_tujuan);
        if ($stmt_update_stok_tujuan === false) {
            throw new Exception("Prepare statement failed for update stok tujuan: " . $conn->error);
        }
        
        // Prepare statement untuk insert stok di gudang tujuan jika belum ada
        $sql_insert_stok_tujuan = "INSERT INTO gudang_stok (gudang_id, barang_id, detail_barang, jumlah, stok_awal) VALUES (?, ?, ?, ?, ?)";
        $stmt_insert_stok_tujuan = $conn->prepare($sql_insert_stok_tujuan);
        if ($stmt_insert_stok_tujuan === false) {
            throw new Exception("Prepare statement failed for insert stok tujuan: " . $conn->error);
        }
        
        // Loop untuk setiap barang
        for ($i = 0; $i < count($barang_ids); $i++) {
            $barang_id = (int)$barang_ids[$i];
            $jumlah = (float)$jumlah_array[$i];
            $ket_detail = $detail_keterangan[$i] ?? '';
            
            if ($barang_id <= 0 || $jumlah <= 0) {
                continue; // Skip jika data tidak valid
            }
            
            // Cek stok di gudang asal - TIDAK MENGGUNAKAN detail_barang untuk pencarian
            $sql_check_stok = "SELECT id, jumlah, stok_awal, detail_barang FROM gudang_stok WHERE gudang_id = ? AND barang_id = ?";
            $stmt_check = $conn->prepare($sql_check_stok);
            $stmt_check->bind_param('ii', $gudang_asal_id, $barang_id);
            $stmt_check->execute();
            $result_check = $stmt_check->get_result();
            
            if ($result_check->num_rows === 0) {
                throw new Exception("Stok barang tidak ditemukan di gudang asal!");
            }
            
            $stok_data = $result_check->fetch_assoc();
            // Use stok_awal instead of jumlah for stock validation
            $stok_asal = $stok_data['stok_awal'];
            $existing_detail_barang = $stok_data['detail_barang'];
            $stok_id = $stok_data['id'];
            
            if ($stok_asal < $jumlah) {
                throw new Exception("Stok tidak mencukupi untuk barang dengan ID: $barang_id");
            }
            
            // Gunakan detail_barang yang sudah ada di database, bukan yang diisi user
            $final_detail_barang = $existing_detail_barang;
            
            // Insert detail transaksi dengan keterangan dari input user
            $stmt_detail->bind_param('iids', $transaksi_id, $barang_id, $jumlah, $ket_detail);
            if (!$stmt_detail->execute()) {
                throw new Exception("Gagal menyimpan detail transaksi: " . $stmt_detail->error);
            }
            
            // Insert stok history (keluar dari gudang asal)
            $keterangan_history = "Transfer ke " . $gudang_tujuan_id . " - " . $final_detail_barang;
            $stmt_history_out->bind_param('siidsii', $tanggal, $barang_id, $gudang_asal_id, $jumlah, $keterangan_history, $transaksi_id, $_SESSION['user_id']);
            if (!$stmt_history_out->execute()) {
                throw new Exception("Gagal menyimpan history stok keluar: " . $stmt_history_out->error);
            }
            
            // Insert stok history (masuk ke gudang tujuan)
            $keterangan_history = "Transfer dari " . $gudang_asal_id . " - " . $final_detail_barang;
            $stmt_history_in->bind_param('siidsii', $tanggal, $barang_id, $gudang_tujuan_id, $jumlah, $keterangan_history, $transaksi_id, $_SESSION['user_id']);
            if (!$stmt_history_in->execute()) {
                throw new Exception("Gagal menyimpan history stok masuk: " . $stmt_history_in->error);
            }
            
            // Update stok di gudang asal - only update stok_awal, not jumlah
            $stmt_update_stok_asal = $conn->prepare("UPDATE gudang_stok SET stok_awal = stok_awal - ? WHERE id = ?");
            if ($stmt_update_stok_asal === false) {
                throw new Exception("Prepare statement failed for update stok asal: " . $conn->error);
            }
            
            $stmt_update_stok_asal->bind_param('di', $jumlah, $stok_id);
            if (!$stmt_update_stok_asal->execute()) {
                throw new Exception("Gagal update stok di gudang asal: " . $stmt_update_stok_asal->error);
            }
            
            // Cek apakah barang sudah ada di gudang tujuan dengan detail_barang yang sama
            $stmt_check_stok_tujuan->bind_param('iis', $gudang_tujuan_id, $barang_id, $final_detail_barang);
            $stmt_check_stok_tujuan->execute();
            $result_tujuan = $stmt_check_stok_tujuan->get_result();
            
            if ($result_tujuan->num_rows > 0) {
                // Update stok di gudang tujuan jika sudah ada - only update stok_awal
                $stmt_update_stok_tujuan = $conn->prepare("UPDATE gudang_stok SET stok_awal = stok_awal + ? WHERE gudang_id = ? AND barang_id = ? AND detail_barang = ?");
                $stmt_update_stok_tujuan->bind_param('diis', $jumlah, $gudang_tujuan_id, $barang_id, $final_detail_barang);
                if (!$stmt_update_stok_tujuan->execute()) {
                    throw new Exception("Gagal update stok di gudang tujuan: " . $stmt_update_stok_tujuan->error);
                }
            } else {
                // Insert stok baru di gudang tujuan jika belum ada - set jumlah equal to stok_awal
                $stmt_insert_stok_tujuan->bind_param('iisdd', $gudang_tujuan_id, $barang_id, $final_detail_barang, $jumlah, $jumlah);
                if (!$stmt_insert_stok_tujuan->execute()) {
                    throw new Exception("Gagal insert stok di gudang tujuan: " . $stmt_insert_stok_tujuan->error);
                }
            }
        }
        
        // Commit transaksi jika semua berhasil
        $conn->commit();
        $_SESSION['success'] = "Transfer stok berhasil disimpan!";
        header("Location: index.php");
        exit();
        
    } catch (Exception $e) {
        // Rollback transaksi jika terjadi error
        $conn->rollback();
        $_SESSION['error'] = "Error: " . $e->getMessage();
        header("Location: create.php");
        exit();
    }
}

// Proses hapus transfer stok
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    
    $conn->begin_transaction();
    
    try {
        // Cek apakah transaksi ada dan tanggalnya hari ini
        $sql = "SELECT * FROM transaksi_transfer WHERE id = ? AND DATE(tanggal) = CURDATE()";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception("Transaksi tidak ditemukan atau tidak dapat dihapus karena bukan transaksi hari ini!");
        }
        
        $transaksi = $result->fetch_assoc();
        $gudang_asal_id = $transaksi['gudang_asal_id'];
        $gudang_tujuan_id = $transaksi['gudang_tujuan_id'];
        
        // Ambil detail transaksi
        $sql = "SELECT * FROM detail_transaksi_transfer WHERE transaksi_transfer_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $details = $stmt->get_result();
        
        // Kembalikan stok untuk setiap barang
        while ($detail = $details->fetch_assoc()) {
            $barang_id = $detail['barang_id'];
            $jumlah = $detail['jumlah'];
            $ket_detail = $detail['keterangan'] ?? '';
            
            // Cari stok di gudang asal berdasarkan barang_id saja
            $sql_check_asal = "SELECT id, detail_barang FROM gudang_stok WHERE gudang_id = ? AND barang_id = ?";
            $stmt_check_asal = $conn->prepare($sql_check_asal);
            $stmt_check_asal->bind_param('ii', $gudang_asal_id, $barang_id);
            $stmt_check_asal->execute();
            $result_check_asal = $stmt_check_asal->get_result();
            
            if ($result_check_asal->num_rows > 0) {
                $stok_asal_data = $result_check_asal->fetch_assoc();
                $asal_detail_barang = $stok_asal_data['detail_barang'];
                $asal_stok_id = $stok_asal_data['id'];
                
                // Kembalikan stok ke gudang asal - only update stok_awal
                $sql = "UPDATE gudang_stok SET stok_awal = stok_awal + ? WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param('di', $jumlah, $asal_stok_id);
                $stmt->execute();
            }
            
            // Cari stok di gudang tujuan berdasarkan barang_id dan detail_barang yang sama
            $sql_check_tujuan = "SELECT id FROM gudang_stok WHERE gudang_id = ? AND barang_id = ? AND detail_barang = ?";
            $stmt_check_tujuan = $conn->prepare($sql_check_tujuan);
            $stmt_check_tujuan->bind_param('iis', $gudang_tujuan_id, $barang_id, $ket_detail);
            $stmt_check_tujuan->execute();
            $result_check_tujuan = $stmt_check_tujuan->get_result();
            
            if ($result_check_tujuan->num_rows > 0) {
                $stok_tujuan_data = $result_check_tujuan->fetch_assoc();
                $tujuan_stok_id = $stok_tujuan_data['id'];
                
                // Kurangi stok dari gudang tujuan - only update stok_awal
                $sql = "UPDATE gudang_stok SET stok_awal = stok_awal - ? WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param('di', $jumlah, $tujuan_stok_id);
                $stmt->execute();
            }
        }
        
        // Hapus history stok
        $sql = "DELETE FROM stok_history WHERE referensi = ? AND (jenis_transaksi = 'transfer_in' OR jenis_transaksi = 'transfer_out')";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $id);
        $stmt->execute();
        
        // Hapus detail transaksi
        $sql = "DELETE FROM detail_transaksi_transfer WHERE transaksi_transfer_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $id);
        $stmt->execute();
        
        // Hapus header transaksi
        $sql = "DELETE FROM transaksi_transfer WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $id);
        $stmt->execute();
        
        $conn->commit();
        $_SESSION['success'] = "Transaksi transfer stok berhasil dihapus!";
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = "Error: " . $e->getMessage();
    }
    
    header("Location: index.php");
    exit();
}

// Redirect jika tidak ada action yang valid
header("Location: index.php");
exit();
?>
