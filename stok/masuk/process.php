<?php
session_start();
require_once '../../config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit();
}

// Fungsi untuk menghasilkan nomor transaksi unik
function generateTransactionNumber($conn, $prefix = 'SM') {
    $date = date('Ymd');
    // Query untuk mendapatkan nomor urut terakhir untuk tanggal hari ini
    $sql = "SELECT COUNT(*) as count FROM transaksi_stok WHERE DATE(created_at) = CURDATE() AND jenis_transaksi = 'masuk'";
    $result = $conn->query($sql);
    $row = $result->fetch_assoc();
    $count = $row['count'] + 1; // Tambahkan 1 untuk nomor urut berikutnya

    // Format nomor urut menjadi 4 digit (contoh: 0001, 0002)
    $sequence = str_pad($count, 4, '0', STR_PAD_LEFT);

    return $prefix . '-' . $date . $sequence;
}


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Ambil data dari form
    $tanggal = $_POST['tanggal'] ?? date('Y-m-d');
    $gudang_tujuan_id = $_POST['gudang_tujuan_id'] ?? null;
    $keterangan = $_POST['keterangan'] ?? '';
    $items_data_json = $_POST['items_data'] ?? '[]'; // Ambil data item dari hidden input

    // Decode data item JSON
    $items = json_decode($items_data_json, true);

    // Validasi dasar
    if (empty($gudang_tujuan_id) || !is_array($items) || count($items) === 0) {
        $_SESSION['error'] = "Data transaksi tidak lengkap atau tidak ada item.";
        header("Location: create.php");
        exit();
    }

    db_drop_column_if_exists($conn, 'gudang_stok', 'snapshot_date');

    // Mulai transaksi database
    $conn->begin_transaction();

    try {
        // 1. Generate Nomor Transaksi Unik
        $no_transaksi = generateTransactionNumber($conn, 'SM'); // SM = Stok Masuk
        error_log("Generated Transaction Number: " . $no_transaksi); // Log ini

        // 2. Insert ke tabel transaksi_stok (Header)
        $sql_header = "INSERT INTO transaksi_stok (no_transaksi, tanggal, gudang_id, jenis_transaksi, keterangan, created_by)
                       VALUES (?, ?, ?, 'masuk', ?, ?)";
        $stmt_header = $conn->prepare($sql_header);
        if ($stmt_header === false) {
             throw new Exception("Prepare statement failed for header: " . $conn->error);
        }
        $stmt_header->bind_param("ssisi", $no_transaksi, $tanggal, $gudang_tujuan_id, $keterangan, $_SESSION['user_id']);

        if (!$stmt_header->execute()) {
            throw new Exception("Execute statement failed for header: " . $stmt_header->error);
        }

        $transaksi_stok_id = $conn->insert_id; // Ambil ID transaksi yang baru saja di-insert
        error_log("Inserted Header with ID: " . $transaksi_stok_id); // Log ini
        $stmt_header->close();

        // 3. Insert ke tabel detail_transaksi_stok dan Update/Insert stok_barang (Detail)
        $sql_detail = "INSERT INTO detail_transaksi_stok (transaksi_stok_id, barang_id, detail_barang, jumlah)
                       VALUES (?, ?, ?, ?)";
        $stmt_detail = $conn->prepare($sql_detail);
         if ($stmt_detail === false) {
             throw new Exception("Prepare statement failed for detail: " . $conn->error);
        }

        // Mengganti stok_barang menjadi gudang_stok
        // Query untuk mengecek stok yang sudah ada berdasarkan gudang, barang ID, dan detail_barang
        $sql_stok_check = "SELECT id, jumlah, stok_awal FROM gudang_stok WHERE gudang_id = ? AND barang_id = ? AND detail_barang = ?";
        $stmt_stok_check = $conn->prepare($sql_stok_check);
        if ($stmt_stok_check === false) {
            throw new Exception("Prepare statement failed for stok check: " . $conn->error);
        }

        // Query untuk update stok dengan jumlah total yang baru
        $sql_stok_update = "UPDATE gudang_stok 
            SET jumlah = jumlah + ?,
                stok_awal = stok_awal + ?
            WHERE gudang_id = ? AND barang_id = ? AND detail_barang = ?";
        $stmt_stok_update = $conn->prepare($sql_stok_update);
        if ($stmt_stok_update === false) {
            throw new Exception("Prepare statement failed for stok update: " . $conn->error);
        }

        // Query untuk insert dengan stok awal
        $sql_stok_insert = "INSERT INTO gudang_stok (gudang_id, barang_id, detail_barang, jumlah, stok_awal) VALUES (?, ?, ?, ?, ?)";
        $stmt_stok_insert = $conn->prepare($sql_stok_insert);
        if ($stmt_stok_insert === false) {
            throw new Exception("Prepare statement failed for stok insert: " . $conn->error);
        }


        foreach ($items as $item) {
            $barang_id = $item['barang_id'];
            $detail_barang = $item['detail_barang'] ?? ''; // Gunakan string kosong jika detail_barang kosong
            $jumlah = $item['jumlah'];
            error_log("Processing Item: Barang ID=" . $barang_id . ", Detail=" . $detail_barang . ", Jumlah=" . $jumlah); // Log ini

            // Insert detail transaksi
            $stmt_detail->bind_param("iiis", $transaksi_stok_id, $barang_id, $detail_barang, $jumlah);
            if (!$stmt_detail->execute()) {
                 throw new Exception("Execute statement failed for detail: " . $stmt_detail->error);
            }
            error_log("Inserted Detail for Item: " . $barang_id); // Log ini

            // Update atau Insert stok_barang (sekarang gudang_stok)
            // Bind parameter untuk pengecekan stok (gudang_id, barang_id, detail_barang)
            $stmt_stok_check->bind_param("iis", $gudang_tujuan_id, $barang_id, $detail_barang);
            if (!$stmt_stok_check->execute()) {
                 throw new Exception("Execute statement failed for stok check: " . $stmt_stok_check->error);
            }
            $stok_result = $stmt_stok_check->get_result();

            if ($stok_result->num_rows > 0) {
                // Jika stok sudah ada, update jumlah dan stok awal
                $stok_row = $stok_result->fetch_assoc();
                $stok_existing_jumlah = $stok_row['jumlah'];
                $stok_existing_awal = $stok_row['stok_awal'];
                
                error_log("Stok exists. Updating quantity for Gudang ID: " . $gudang_tujuan_id . 
                         ", Barang ID: " . $barang_id . 
                         ", Detail: '" . $detail_barang . 
                         "' from " . $stok_existing_jumlah . " to " . ($stok_existing_jumlah + $jumlah) .
                         ", Stok Awal from " . $stok_existing_awal . " to " . ($stok_existing_awal + $jumlah));

                // Bind parameter untuk update stok (jumlah, stok_awal, gudang_id, barang_id, detail_barang)
                $stmt_stok_update->bind_param("iiiis", $jumlah, $jumlah, $gudang_tujuan_id, $barang_id, $detail_barang);
                if (!$stmt_stok_update->execute()) {
                    throw new Exception("Execute statement failed for stok update: " . $stmt_stok_update->error);
                }
            } else {
                // Jika stok belum ada, insert baru
                error_log("Stok does not exist. Inserting new with quantity: " . $jumlah);
                $stmt_stok_insert->bind_param("iisii", $gudang_tujuan_id, $barang_id, $detail_barang, $jumlah, $jumlah);
                if (!$stmt_stok_insert->execute()) {
                    throw new Exception("Execute statement failed for stok insert: " . $stmt_stok_insert->error);
                }
            }
        }

        $stmt_detail->close();
        $stmt_stok_check->close();
        $stmt_stok_update->close();
        $stmt_stok_insert->close();


        // Commit transaksi jika semua berhasil
        $conn->commit();
        error_log("Transaction Committed Successfully."); // Log ini
        $_SESSION['success'] = "Stok masuk berhasil ditambahkan dengan No Transaksi: " . $no_transaksi;
        header("Location: index.php");
        exit();

    } catch (Exception $e) {
        // Rollback transaksi jika terjadi error
        $conn->rollback();
        error_log("Transaction Rolled Back. Error: " . $e->getMessage()); // Log ini
        $_SESSION['error'] = "Gagal menambahkan stok masuk: " . $e->getMessage();
        header("Location: create.php");
        exit();
    }

} elseif ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    // Logika untuk menghapus transaksi stok masuk
    $transaksi_id = $_GET['id'];

    // Mulai transaksi database untuk operasi delete
    $conn->begin_transaction();

    try {
        // 1. Ambil detail item dari transaksi yang akan dihapus
        $sql_get_details = "SELECT barang_id, detail_barang, jumlah FROM detail_transaksi_stok WHERE transaksi_stok_id = ?";
        $stmt_get_details = $conn->prepare($sql_get_details);
         if ($stmt_get_details === false) {
             throw new Exception("Prepare statement failed for get details: " . $conn->error);
        }
        $stmt_get_details->bind_param("i", $transaksi_id);
        if (!$stmt_get_details->execute()) {
             throw new Exception("Execute statement failed for get details: " . $stmt_get_details->error);
        }
        $details_result = $stmt_get_details->get_result();
        $items_to_delete = $details_result->fetch_all(MYSQLI_ASSOC);
        $stmt_get_details->close();

        // Ambil gudang tujuan dari transaksi header
        $sql_get_gudang = "SELECT gudang_id FROM transaksi_stok WHERE id = ?";
        $stmt_get_gudang = $conn->prepare($sql_get_gudang);
         if ($stmt_get_gudang === false) {
             throw new Exception("Prepare statement failed for get gudang: " . $conn->error);
        }
        $stmt_get_gudang->bind_param("i", $transaksi_id);
         if (!$stmt_get_gudang->execute()) {
             throw new Exception("Execute statement failed for get gudang: " . $stmt_get_gudang->error);
        }
        $gudang_result = $stmt_get_gudang->get_result();
        $gudang_row = $gudang_result->fetch_assoc();
        $gudang_id = $gudang_row['gudang_id'];
        $stmt_get_gudang->close();


        // 2. Kurangi stok di tabel stok_barang (sekarang gudang_stok) untuk setiap item
        // Mengganti stok_barang menjadi gudang_stok
        foreach ($items_to_delete as $item) {
            $barang_id = $item['barang_id'];
            $detail_barang = $item['detail_barang'] ?? '';
            $jumlah = $item['jumlah'];

            // Cari stok berdasarkan gudang_id, barang_id, dan detail_barang
            $sql_find_stok = "SELECT id FROM gudang_stok WHERE gudang_id = ? AND barang_id = ? AND detail_barang = ?";
            $stmt_find_stok = $conn->prepare($sql_find_stok);
            if ($stmt_find_stok === false) {
                throw new Exception("Prepare statement failed for find stok: " . $conn->error);
            }
            $stmt_find_stok->bind_param("iis", $gudang_id, $barang_id, $detail_barang);
            $stmt_find_stok->execute();
            $stok_result = $stmt_find_stok->get_result();
            
            if ($stok_result->num_rows > 0) {
                $stok_row = $stok_result->fetch_assoc();
                $stok_id = $stok_row['id'];
                
                // Update stok (kurangi jumlah dan stok_awal)
                $sql_stok_decrease = "UPDATE gudang_stok SET jumlah = jumlah - ?, stok_awal = stok_awal - ? WHERE id = ?";
                $stmt_stok_decrease = $conn->prepare($sql_stok_decrease);
                if ($stmt_stok_decrease === false) {
                    throw new Exception("Prepare statement failed for stok decrease: " . $conn->error);
                }
                
                $stmt_stok_decrease->bind_param("iii", $jumlah, $jumlah, $stok_id);
                if (!$stmt_stok_decrease->execute()) {
                    throw new Exception("Execute statement failed for stok decrease: " . $stmt_stok_decrease->error);
                }
                $stmt_stok_decrease->close();
            } else {
                // Handle case where stok entry wasn't found (shouldn't happen if logic is correct)
                error_log("Warning: Stok entry not found for decrease (Gudang: $gudang_id, Barang: $barang_id, Detail: $detail_barang)");
            }
            $stmt_find_stok->close();
        }

        // 3. Hapus detail transaksi dari tabel detail_transaksi_stok
        $sql_delete_details = "DELETE FROM detail_transaksi_stok WHERE transaksi_stok_id = ?";
        $stmt_delete_details = $conn->prepare($sql_delete_details);
         if ($stmt_delete_details === false) {
             throw new Exception("Prepare statement failed for delete details: " . $conn->error);
        }
        $stmt_delete_details->bind_param("i", $transaksi_id);
         if (!$stmt_delete_details->execute()) {
             throw new Exception("Execute statement failed for delete details: " . $stmt_delete_details->error);
        }
        $stmt_delete_details->close();

        // 4. Hapus header transaksi dari tabel transaksi_stok
        $sql_delete_header = "DELETE FROM transaksi_stok WHERE id = ?";
        $stmt_delete_header = $conn->prepare($sql_delete_header);
         if ($stmt_delete_header === false) {
             throw new Exception("Prepare statement failed for delete header: " . $conn->error);
        }
        $stmt_delete_header->bind_param("i", $transaksi_id);
         if (!$stmt_delete_header->execute()) {
             throw new Exception("Execute statement failed for delete header: " . $stmt_delete_header->error);
        }
        $stmt_delete_header->close();


        // Commit transaksi jika semua berhasil
        $conn->commit();
        $_SESSION['success'] = "Transaksi stok masuk berhasil dihapus.";
        header("Location: index.php");
        exit();

    } catch (Exception $e) {
        // Rollback transaksi jika terjadi error
        $conn->rollback();
        $_SESSION['error'] = "Gagal menghapus transaksi stok masuk: " . $e->getMessage();
        header("Location: index.php");
        exit();
    }

} else {
    // Jika request tidak valid
    $_SESSION['error'] = "Permintaan tidak valid.";
    header("Location: index.php");
    exit();
}

$conn->close();
?>
