<?php
session_start();
require_once '../../config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit();
}

function generateTransactionNumber($conn, $prefix = 'ADJIN') {
    $date = date('Ymd');
    $sql = "SELECT COUNT(*) as count FROM transaksi_stok WHERE DATE(created_at) = CURDATE() AND jenis_transaksi = 'adjustment_in'";
    $result = $conn->query($sql);
    $row = $result->fetch_assoc();
    $count = $row['count'] + 1;
    $sequence = str_pad($count, 4, '0', STR_PAD_LEFT);
    return $prefix . '-' . $date . $sequence;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $tanggal = $_POST['tanggal'] ?? date('Y-m-d');
    $gudang_id = $_POST['gudang_id'] ?? null;
    $keterangan = $_POST['keterangan'] ?? '';
    $items_data_json = $_POST['items_data'] ?? '[]';

    $items = json_decode($items_data_json, true);

    if (empty($gudang_id) || !is_array($items) || count($items) === 0) {
        $_SESSION['error'] = "Data transaksi tidak lengkap atau tidak ada item.";
        header("Location: create.php");
        exit();
    }

    $conn->begin_transaction();

    try {
        $no_transaksi = generateTransactionNumber($conn, 'ADJIN');

        $sql_header = "INSERT INTO transaksi_stok (no_transaksi, tanggal, gudang_id, jenis_transaksi, keterangan, created_by)
                       VALUES (?, ?, ?, 'adjustment_in', ?, ?)";
        $stmt_header = $conn->prepare($sql_header);
        if ($stmt_header === false) {
             throw new Exception("Prepare failed: " . $conn->error);
        }
        $stmt_header->bind_param("ssisi", $no_transaksi, $tanggal, $gudang_id, $keterangan, $_SESSION['user_id']);

        if (!$stmt_header->execute()) {
            throw new Exception("Execute failed: " . $stmt_header->error);
        }

        $transaksi_stok_id = $conn->insert_id;
        $stmt_header->close();

        $sql_detail = "INSERT INTO detail_transaksi_stok (transaksi_stok_id, barang_id, detail_barang, jumlah)
                       VALUES (?, ?, ?, ?)";
        $stmt_detail = $conn->prepare($sql_detail);
        if ($stmt_detail === false) {
            throw new Exception("Prepare detail failed: " . $conn->error);
        }

        $sql_stok_check = "SELECT id FROM gudang_stok WHERE gudang_id = ? AND barang_id = ? AND detail_barang = ?";
        $stmt_stok_check = $conn->prepare($sql_stok_check);
        if ($stmt_stok_check === false) {
            throw new Exception("Prepare stok check failed: " . $conn->error);
        }

        $sql_stok_update = "UPDATE gudang_stok SET stok_awal = stok_awal + ? WHERE id = ?";
        $stmt_stok_update = $conn->prepare($sql_stok_update);
        if ($stmt_stok_update === false) {
            throw new Exception("Prepare stok update failed: " . $conn->error);
        }

        $sql_stok_insert = "INSERT INTO gudang_stok (gudang_id, barang_id, detail_barang, jumlah, stok_awal)
                            VALUES (?, ?, ?, ?, ?)";
        $stmt_stok_insert = $conn->prepare($sql_stok_insert);
        if ($stmt_stok_insert === false) {
            throw new Exception("Prepare stok insert failed: " . $conn->error);
        }

        foreach ($items as $item) {
            $barang_id = intval($item['barang_id']);
            $detail_barang = $item['detail_barang'] ?? '';
            $jumlah = intval($item['jumlah']);

            if ($jumlah <= 0) {
                throw new Exception("Jumlah harus lebih dari 0 untuk barang ID: " . $barang_id);
            }

            $stmt_detail->bind_param("iisi", $transaksi_stok_id, $barang_id, $detail_barang, $jumlah);
            if (!$stmt_detail->execute()) {
                 throw new Exception("Execute detail failed: " . $stmt_detail->error);
            }

            $stmt_stok_check->bind_param("iis", $gudang_id, $barang_id, $detail_barang);
            if (!$stmt_stok_check->execute()) {
                throw new Exception("Execute stok check failed: " . $stmt_stok_check->error);
            }

            $stok_result = $stmt_stok_check->get_result();
            if ($stok_result && $stok_result->num_rows > 0) {
                $stok_row = $stok_result->fetch_assoc();
                $stok_id = (int)$stok_row['id'];
                $stmt_stok_update->bind_param("ii", $jumlah, $stok_id);
                if (!$stmt_stok_update->execute()) {
                    throw new Exception("Execute stok update failed: " . $stmt_stok_update->error);
                }
            } else {
                $stmt_stok_insert->bind_param("iisii", $gudang_id, $barang_id, $detail_barang, $jumlah, $jumlah);
                if (!$stmt_stok_insert->execute()) {
                    throw new Exception("Execute stok insert failed: " . $stmt_stok_insert->error);
                }
            }
        }

        $stmt_detail->close();
        $stmt_stok_check->close();
        $stmt_stok_update->close();
        $stmt_stok_insert->close();
        $conn->commit();

        $_SESSION['success'] = "Adjustment In berhasil ditambahkan dengan No Transaksi: " . $no_transaksi;
        header("Location: index.php");
        exit();

    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = "Gagal: " . $e->getMessage();
        header("Location: create.php");
        exit();
    }

} elseif ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $transaksi_id = (int)$_GET['id'];
    $conn->begin_transaction();

    try {
        $sql_get_details = "SELECT barang_id, detail_barang, jumlah FROM detail_transaksi_stok WHERE transaksi_stok_id = ?";
        $stmt_get_details = $conn->prepare($sql_get_details);
        if ($stmt_get_details === false) {
            throw new Exception("Prepare get detail failed: " . $conn->error);
        }
        $stmt_get_details->bind_param("i", $transaksi_id);
        if (!$stmt_get_details->execute()) {
            throw new Exception("Execute get detail failed: " . $stmt_get_details->error);
        }
        $details_result = $stmt_get_details->get_result();
        $items_to_delete = $details_result ? $details_result->fetch_all(MYSQLI_ASSOC) : [];
        $stmt_get_details->close();

        $sql_get_header = "SELECT gudang_id FROM transaksi_stok WHERE id = ? AND jenis_transaksi = 'adjustment_in'";
        $stmt_get_header = $conn->prepare($sql_get_header);
        if ($stmt_get_header === false) {
            throw new Exception("Prepare get header failed: " . $conn->error);
        }
        $stmt_get_header->bind_param("i", $transaksi_id);
        if (!$stmt_get_header->execute()) {
            throw new Exception("Execute get header failed: " . $stmt_get_header->error);
        }
        $header_result = $stmt_get_header->get_result();
        $header_row = $header_result ? $header_result->fetch_assoc() : null;
        $stmt_get_header->close();

        if (!$header_row) {
            throw new Exception("Transaksi adjustment in tidak ditemukan.");
        }
        $gudang_id = (int)$header_row['gudang_id'];

        $sql_find_stok = "SELECT id, stok_awal FROM gudang_stok WHERE gudang_id = ? AND barang_id = ? AND detail_barang = ?";
        $stmt_find_stok = $conn->prepare($sql_find_stok);
        if ($stmt_find_stok === false) {
            throw new Exception("Prepare find stok failed: " . $conn->error);
        }

        $sql_stok_decrease = "UPDATE gudang_stok SET stok_awal = stok_awal - ? WHERE id = ?";
        $stmt_stok_decrease = $conn->prepare($sql_stok_decrease);
        if ($stmt_stok_decrease === false) {
            throw new Exception("Prepare stok decrease failed: " . $conn->error);
        }

        foreach ($items_to_delete as $item) {
            $barang_id = (int)$item['barang_id'];
            $detail_barang = (string)($item['detail_barang'] ?? '');
            $jumlah = (int)$item['jumlah'];

            $stmt_find_stok->bind_param("iis", $gudang_id, $barang_id, $detail_barang);
            if (!$stmt_find_stok->execute()) {
                throw new Exception("Execute find stok failed: " . $stmt_find_stok->error);
            }
            $stok_result = $stmt_find_stok->get_result();
            $stok_row = $stok_result ? $stok_result->fetch_assoc() : null;
            if (!$stok_row) {
                throw new Exception("Stok tidak ditemukan saat rollback untuk barang ID: " . $barang_id);
            }

            if ((int)$stok_row['stok_awal'] < $jumlah) {
                throw new Exception("Stok awal tidak cukup untuk rollback adjustment in pada barang ID: " . $barang_id);
            }

            $stok_id = (int)$stok_row['id'];
            $stmt_stok_decrease->bind_param("ii", $jumlah, $stok_id);
            if (!$stmt_stok_decrease->execute()) {
                throw new Exception("Execute stok decrease failed: " . $stmt_stok_decrease->error);
            }
        }

        $stmt_find_stok->close();
        $stmt_stok_decrease->close();

        $sql_delete_detail = "DELETE FROM detail_transaksi_stok WHERE transaksi_stok_id = ?";
        $stmt_delete_detail = $conn->prepare($sql_delete_detail);
        if ($stmt_delete_detail === false) {
            throw new Exception("Prepare delete detail failed: " . $conn->error);
        }
        $stmt_delete_detail->bind_param("i", $transaksi_id);
        if (!$stmt_delete_detail->execute()) {
            throw new Exception("Execute delete detail failed: " . $stmt_delete_detail->error);
        }
        $stmt_delete_detail->close();

        $sql_delete_header = "DELETE FROM transaksi_stok WHERE id = ? AND jenis_transaksi = 'adjustment_in'";
        $stmt_delete_header = $conn->prepare($sql_delete_header);
        if ($stmt_delete_header === false) {
            throw new Exception("Prepare delete header failed: " . $conn->error);
        }
        $stmt_delete_header->bind_param("i", $transaksi_id);
        if (!$stmt_delete_header->execute()) {
            throw new Exception("Execute delete header failed: " . $stmt_delete_header->error);
        }
        $stmt_delete_header->close();

        $conn->commit();
        $_SESSION['success'] = "Adjustment In berhasil dihapus.";
        header("Location: index.php");
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = "Gagal menghapus adjustment in: " . $e->getMessage();
        header("Location: index.php");
        exit();
    }
} else {
    header("Location: index.php");
    exit();
}
?>
