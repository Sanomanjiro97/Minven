<?php
session_start();
require_once '../../config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit();
}

// Fungsi untuk menghasilkan nomor transaksi unik
function generateTransactionNumber($conn, $prefix = 'SK') {
    $date = date('Ymd');
    // Query untuk mendapatkan nomor urut terakhir untuk tanggal hari ini
    $sql = "SELECT COUNT(*) as count FROM transaksi_stok WHERE DATE(created_at) = CURDATE() AND jenis_transaksi = 'keluar'";
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
    $gudang_id = $_POST['gudang_id'] ?? null;
    $keterangan = $_POST['keterangan'] ?? '';
    $items_data_json = $_POST['items_data'] ?? '[]'; // Ambil data item dari hidden input

    // Decode data item JSON
    $items = json_decode($items_data_json, true);

    // Validasi dasar
    if (empty($gudang_id) || !is_array($items) || count($items) === 0) {
        $_SESSION['error'] = "Data transaksi tidak lengkap atau tidak ada item.";
        header("Location: create.php");
        exit();
    }

    // Mulai transaksi database
    $conn->begin_transaction();

    try {
        // 1. Generate Nomor Transaksi Unik
        $no_transaksi = generateTransactionNumber($conn, 'SK'); // SK = Stok Keluar
        error_log("Generated Transaction Number: " . $no_transaksi); // Log ini

        // 2. Insert ke tabel transaksi_stok (Header)
        $sql_header = "INSERT INTO transaksi_stok (no_transaksi, tanggal, gudang_id, jenis_transaksi, keterangan, created_by)
                       VALUES (?, ?, ?, 'keluar', ?, ?)";
        $stmt_header = $conn->prepare($sql_header);
        if ($stmt_header === false) {
             throw new Exception("Prepare statement failed for header: " . $conn->error);
        }
        $stmt_header->bind_param("ssisi", $no_transaksi, $tanggal, $gudang_id, $keterangan, $_SESSION['user_id']);

        if (!$stmt_header->execute()) {
            throw new Exception("Execute statement failed for header: " . $stmt_header->error);
        }

        $transaksi_stok_id = $conn->insert_id; // Ambil ID transaksi yang baru saja di-insert
        error_log("Inserted Header with ID: " . $transaksi_stok_id); // Log ini
        $stmt_header->close();

        // 3. Insert ke tabel detail_transaksi_stok dan Update stok_barang (Detail)
        $sql_detail = "INSERT INTO detail_transaksi_stok (transaksi_stok_id, barang_id, detail_barang, jumlah)
                       VALUES (?, ?, ?, ?)";
        $stmt_detail = $conn->prepare($sql_detail);
        if ($stmt_detail === false) {
            throw new Exception("Prepare statement failed for detail: " . $conn->error);
        }

        // Query untuk mengecek stok yang sudah ada berdasarkan gudang, barang ID, dan detail_barang
        $sql_stok_check = "SELECT id, jumlah, stok_awal, detail_barang FROM gudang_stok WHERE gudang_id = ? AND barang_id = ?";
        $stmt_stok_check = $conn->prepare($sql_stok_check);
        if ($stmt_stok_check === false) {
            throw new Exception("Prepare statement failed for stok check: " . $conn->error);
        }

        // Query untuk update stok dengan jumlah total yang baru (mengurangi stok)
        // Mengurangi hanya kolom 'jumlah', bukan 'stok_awal'
        $sql_stok_update = "UPDATE gudang_stok SET stok_awal = stok_awal - ? WHERE id = ?";
        $stmt_stok_update = $conn->prepare($sql_stok_update);
        if ($stmt_stok_update === false) {
            throw new Exception("Prepare statement failed for stok update: " . $conn->error);
        }

        foreach ($items as $item) {
            $barang_id = $item['barang_id'];
            $detail_barang = $item['detail_barang'] ?? ''; // Gunakan string kosong jika detail_barang kosong
            $jumlah = $item['jumlah'];
            error_log("Processing Item: Barang ID=" . $barang_id . ", Detail=" . $detail_barang . ", Jumlah=" . $jumlah); // Log ini

            // Cek stok yang tersedia berdasarkan gudang_id dan barang_id saja
            $stmt_stok_check->bind_param("ii", $gudang_id, $barang_id);
            if (!$stmt_stok_check->execute()) {
                throw new Exception("Execute statement failed for stok check: " . $stmt_stok_check->error);
            }
            $stok_result = $stmt_stok_check->get_result();

            if ($stok_result->num_rows > 0) {
                // Jika stok sudah ada, cek apakah cukup untuk dikurangi
                $stok_row = $stok_result->fetch_assoc();
                $stok_existing_jumlah = $stok_row['jumlah'];
                $stok_existing_awal = $stok_row['stok_awal'];
                $existing_detail_barang = $stok_row['detail_barang'];
                $stok_id = $stok_row['id'];

                // Validasi stok cukup - MENGGUNAKAN STOK_AWAL SESUAI PERMINTAAN
                if ($stok_existing_awal < $jumlah) {
                    throw new Exception("Stok awal tidak mencukupi untuk barang ID: " . $barang_id .
                                       ". Stok awal tersedia: " . $stok_existing_awal .
                                       ", Dibutuhkan: " . $jumlah);
                }

                error_log("Stok exists. Updating quantity for Gudang ID: " . $gudang_id .
                         ", Barang ID: " . $barang_id .
                         ", Stok Awal (not updated by transaction): " . $stok_existing_awal); // Log disesuaikan

                // Insert detail transaksi dengan detail_barang dari database
                $stmt_detail->bind_param("iiis", $transaksi_stok_id, $barang_id, $existing_detail_barang, $jumlah);
                if (!$stmt_detail->execute()) {
                    throw new Exception("Execute statement failed for detail: " . $stmt_detail->error);
                }
                error_log("Inserted Detail for Item: " . $barang_id); // Log ini

                // Update stok menggunakan ID stok yang ditemukan
                $stmt_stok_update->bind_param("ii", $jumlah, $stok_id);
                if (!$stmt_stok_update->execute()) {
                    throw new Exception("Execute statement failed for stok update: " . $stmt_stok_update->error);
                }
            } else {
                // Jika stok tidak ada, tidak bisa mengurangi
                throw new Exception("Stok tidak ditemukan untuk barang ID: " . $barang_id .
                                   " di gudang ID: " . $gudang_id);
            }
        }

        $stmt_detail->close();
        $stmt_stok_check->close();
        $stmt_stok_update->close();

        // Commit transaksi jika semua berhasil
        $conn->commit();
        error_log("Transaction Committed Successfully."); // Log ini
        $_SESSION['success'] = "Stok keluar berhasil ditambahkan dengan No Transaksi: " . $no_transaksi;
        header("Location: index.php");
        exit();

    } catch (Exception $e) {
        // Rollback transaksi jika terjadi error
        $conn->rollback();
        error_log("Transaction Rolled Back. Error: " . $e->getMessage()); // Log ini
        $_SESSION['error'] = "Gagal menambahkan stok keluar: " . $e->getMessage();
        header("Location: create.php");
        exit();
    }

} elseif ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    // Logika untuk menghapus transaksi stok keluar
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

        // 2. Ambil gudang_id dari transaksi
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

        // 3. Kembalikan stok untuk setiap item
        foreach ($items_to_delete as $item) {
            $barang_id = $item['barang_id'];
            $detail_barang = $item['detail_barang'];
            $jumlah = $item['jumlah'];

            // Cari stok berdasarkan gudang_id dan barang_id
            $sql_find_stok = "SELECT id FROM gudang_stok WHERE gudang_id = ? AND barang_id = ?";
            $stmt_find_stok = $conn->prepare($sql_find_stok);
            if ($stmt_find_stok === false) {
                throw new Exception("Prepare statement failed for find stok: " . $conn->error);
            }
            $stmt_find_stok->bind_param("ii", $gudang_id, $barang_id);
            $stmt_find_stok->execute();
            $stok_result = $stmt_find_stok->get_result();
            
            if ($stok_result->num_rows > 0) {
                $stok_row = $stok_result->fetch_assoc();
                $stok_id = $stok_row['id'];
                
                // Update stok (kembalikan stok_awal yang dikeluarkan)
                $sql_stok_update = "UPDATE gudang_stok SET stok_awal = stok_awal + ? WHERE id = ?";
                $stmt_stok_update = $conn->prepare($sql_stok_update);
                if ($stmt_stok_update === false) {
                    throw new Exception("Prepare statement failed for stok update: " . $conn->error);
                }
                
                $stmt_stok_update->bind_param("ii", $jumlah, $stok_id);
                if (!$stmt_stok_update->execute()) {
                    throw new Exception("Execute statement failed for stok update: " . $stmt_stok_update->error);
                }
                $stmt_stok_update->close();
            }
            $stmt_find_stok->close();
        }

        // 4. Hapus detail transaksi
        $sql_delete_detail = "DELETE FROM detail_transaksi_stok WHERE transaksi_stok_id = ?";
        $stmt_delete_detail = $conn->prepare($sql_delete_detail);
        if ($stmt_delete_detail === false) {
            throw new Exception("Prepare statement failed for delete detail: " . $conn->error);
        }
        $stmt_delete_detail->bind_param("i", $transaksi_id);
        if (!$stmt_delete_detail->execute()) {
            throw new Exception("Execute statement failed for delete detail: " . $stmt_delete_detail->error);
        }
        $stmt_delete_detail->close();

        // 5. Hapus header transaksi
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
        $_SESSION['success'] = "Transaksi stok keluar berhasil dihapus.";
        header("Location: index.php");
        exit();

    } catch (Exception $e) {
        // Rollback transaksi jika terjadi error
        $conn->rollback();
        $_SESSION['error'] = "Gagal menghapus transaksi stok keluar: " . $e->getMessage();
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