<?php
session_start();
require_once '../config.php';
require_once '../includes/access_check.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

function ensure_barang_split_setup_table($conn) {
    $res = $conn->query("SHOW TABLES LIKE 'barang_split_setup'");
    if ($res instanceof mysqli_result) {
        $exists = $res->num_rows > 0;
        $res->free();
        if ($exists) {
            return true;
        }
    }
    $sql = "CREATE TABLE IF NOT EXISTS barang_split_setup (
        id INT(11) NOT NULL AUTO_INCREMENT,
        parent_barang_id INT(11) NOT NULL,
        split_barang_id INT(11) NOT NULL,
        created_by INT(11) DEFAULT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (id),
        UNIQUE KEY uq_parent_split (parent_barang_id, split_barang_id),
        KEY idx_parent (parent_barang_id),
        KEY idx_split (split_barang_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
    return $conn->query($sql) === true;
}

$action = '';
if (isset($_POST['action']) && is_string($_POST['action'])) {
    $action = $_POST['action'];
} elseif (isset($_GET['action']) && is_string($_GET['action'])) {
    $action = $_GET['action'];
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'get_split_setup') {
    header('Content-Type: application/json; charset=utf-8');
    if (!checkAccess('barang', 'setup_split')) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Akses tidak diizinkan'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit();
    }

    $parent_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($parent_id <= 0) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'ID barang tidak valid'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit();
    }

    ensure_barang_split_setup_table($conn);
    $stmt = $conn->prepare("SELECT split_barang_id FROM barang_split_setup WHERE parent_barang_id = ? ORDER BY split_barang_id");
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Database error'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit();
    }
    $stmt->bind_param('i', $parent_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $split_ids = [];
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $sid = (int)($r['split_barang_id'] ?? 0);
            if ($sid > 0) {
                $split_ids[] = $sid;
            }
        }
    }
    $stmt->close();

    echo json_encode(['status' => 'success', 'split_barang_ids' => $split_ids], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'save_split_setup') {
    header('Content-Type: application/json; charset=utf-8');
    if (!checkAccess('barang', 'setup_split')) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Akses tidak diizinkan'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit();
    }

    $parent_id = isset($_POST['parent_barang_id']) ? (int)$_POST['parent_barang_id'] : 0;
    $split_ids = isset($_POST['split_barang_ids']) && is_array($_POST['split_barang_ids']) ? $_POST['split_barang_ids'] : [];

    if ($parent_id <= 0) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Barang utama tidak valid'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit();
    }

    $clean_split_ids = [];
    foreach ($split_ids as $v) {
        if ($v === null || $v === '') {
            continue;
        }
        $sid = (int)$v;
        if ($sid > 0 && $sid !== $parent_id) {
            $clean_split_ids[$sid] = true;
        }
    }
    $clean_split_ids = array_keys($clean_split_ids);

    ensure_barang_split_setup_table($conn);

    $conn->begin_transaction();
    try {
        $stmt_chk = $conn->prepare("SELECT id FROM barang WHERE id = ? LIMIT 1");
        if (!$stmt_chk) {
            throw new Exception('Database error');
        }
        $stmt_chk->bind_param('i', $parent_id);
        $stmt_chk->execute();
        $res_chk = $stmt_chk->get_result();
        $exists_parent = $res_chk && $res_chk->num_rows > 0;
        $stmt_chk->close();
        if (!$exists_parent) {
            throw new Exception('Barang utama tidak ditemukan');
        }

        if (count($clean_split_ids) > 0) {
            $placeholders = implode(',', array_fill(0, count($clean_split_ids), '?'));
            $types = str_repeat('i', count($clean_split_ids));
            $sql_in = "SELECT id FROM barang WHERE id IN ($placeholders)";
            $stmt_in = $conn->prepare($sql_in);
            if (!$stmt_in) {
                throw new Exception('Database error');
            }
            $bind = [];
            $bind[] = &$types;
            foreach ($clean_split_ids as $k => $v) {
                $clean_split_ids[$k] = (int)$v;
                $bind[] = &$clean_split_ids[$k];
            }
            call_user_func_array([$stmt_in, 'bind_param'], $bind);
            $stmt_in->execute();
            $res_in = $stmt_in->get_result();
            $found = [];
            if ($res_in) {
                while ($r = $res_in->fetch_assoc()) {
                    $found[(int)$r['id']] = true;
                }
            }
            $stmt_in->close();

            foreach ($clean_split_ids as $sid) {
                if (!isset($found[(int)$sid])) {
                    throw new Exception('Ada barang split yang tidak ditemukan');
                }
            }
        }

        $stmt_del = $conn->prepare("DELETE FROM barang_split_setup WHERE parent_barang_id = ?");
        if (!$stmt_del) {
            throw new Exception('Database error');
        }
        $stmt_del->bind_param('i', $parent_id);
        if (!$stmt_del->execute()) {
            $stmt_del->close();
            throw new Exception('Gagal menghapus setup lama');
        }
        $stmt_del->close();

        if (count($clean_split_ids) > 0) {
            $stmt_ins = $conn->prepare("INSERT INTO barang_split_setup (parent_barang_id, split_barang_id, created_by) VALUES (?, ?, ?)");
            if (!$stmt_ins) {
                throw new Exception('Database error');
            }
            $created_by = (int)($_SESSION['user_id'] ?? 0);
            foreach ($clean_split_ids as $sid) {
                $sid = (int)$sid;
                $stmt_ins->bind_param('iii', $parent_id, $sid, $created_by);
                if (!$stmt_ins->execute()) {
                    throw new Exception('Gagal menyimpan setup');
                }
            }
            $stmt_ins->close();
        }

        $conn->commit();
        echo json_encode(['status' => 'success', 'message' => 'Setup split tersimpan'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit();
    }
}

function clean_input($data) {
    global $conn;
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $conn->real_escape_string($data);
}

function upload_image($file) {
    $target_dir = "../uploads/barang/";
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }

    $file_extension = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));
    $new_filename = uniqid() . '.' . $file_extension;
    $target_file = $target_dir . $new_filename;

    // Check if image file is a actual image or fake image
    $check = getimagesize($file["tmp_name"]);
    if($check === false) {
        return false;
    }

    // Check file size (max 5MB)
    if ($file["size"] > 5000000) {
        return false;
    }

    // Allow certain file formats
    if($file_extension != "jpg" && $file_extension != "png" && $file_extension != "jpeg") {
        return false;
    }

    if (move_uploaded_file($file["tmp_name"], $target_file)) {
        return $new_filename;
    }

    return false;
}

function ensure_barang_barcode_dus_column($conn) {
    if (db_has_column($conn, 'barang', 'barcode_dus')) {
        return true;
    }
    $conn->query("ALTER TABLE barang ADD COLUMN barcode_dus VARCHAR(100) NULL AFTER barcode");
    return db_has_column($conn, 'barang', 'barcode_dus');
}

// Proses Tambah Barang
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $_POST['action'] == 'add') {
    $kode_barang = clean_input($_POST['kode_barang']);
    $barcode = clean_input($_POST['barcode']);
    $barcode_dus = clean_input($_POST['barcode_dus'] ?? '');
    $nama_barang = clean_input($_POST['nama_barang']);
    $kategori_id = clean_input($_POST['kategori_id']);
    $satuan_id = clean_input($_POST['satuan_id']);
    $baku_non_baku = isset($_POST['baku_non_baku']) ? 'baku' : 'non_baku';
    $supplier_id = clean_input($_POST['supplier_id']);
    $stok_minimum = clean_input($_POST['stok_minimum']);
    $harga_beli = clean_input($_POST['harga_beli']);
    $harga_po = isset($_POST['harga_po']) ? clean_input($_POST['harga_po']) : '';
    $expired_at = !empty($_POST['expired_at']) ? clean_input($_POST['expired_at']) : null;
    $created_by = $_SESSION['user_id'];

    // Cek duplikasi kode barang
    $check = $conn->query("SELECT id FROM barang WHERE kode_barang = '$kode_barang'");
    if ($check->num_rows > 0) {
        $_SESSION['error'] = "Kode barang sudah digunakan!";
        header("Location: index.php");
        exit();
    }

    // Upload gambar jika ada
    $gambar = null;
    if(isset($_FILES['gambar']) && $_FILES['gambar']['error'] == 0) {
        $gambar = upload_image($_FILES['gambar']);
        if($gambar === false) {
            $_SESSION['error'] = "Gagal mengupload gambar!";
            header("Location: index.php");
            exit();
        }
    }

    $has_baku = db_has_column($conn, 'barang', 'baku_non_baku');
    $has_gambar = db_has_column($conn, 'barang', 'gambar');
    $has_expired = db_has_column($conn, 'barang', 'expired_at');
    $has_created_by = db_has_column($conn, 'barang', 'created_by');
    $has_harga_po = db_has_column($conn, 'barang', 'harga_po');
    $has_barcode_dus = ensure_barang_barcode_dus_column($conn);

    $cols = ['kode_barang', 'barcode'];
    $types = 'ss';
    $params = [];
    $params[] = &$kode_barang;
    $params[] = &$barcode;

    if ($has_barcode_dus) {
        $cols[] = 'barcode_dus';
        $types .= 's';
        $params[] = &$barcode_dus;
    }

    $cols[] = 'nama_barang';
    $types .= 's';
    $params[] = &$nama_barang;

    $cols[] = 'supplier_id';
    $cols[] = 'kategori_id';
    $cols[] = 'satuan_id';
    $types .= 'iii';
    $supplier_id_i = (int)$supplier_id;
    $kategori_id_i = (int)$kategori_id;
    $satuan_id_i = (int)$satuan_id;
    $params[] = &$supplier_id_i;
    $params[] = &$kategori_id_i;
    $params[] = &$satuan_id_i;

    if ($has_baku) {
        $cols[] = 'baku_non_baku';
        $types .= 's';
        $params[] = &$baku_non_baku;
    }

    $cols[] = 'stok_minimum';
    $stok_minimum_i = (int)$stok_minimum;
    $types .= 'i';
    $params[] = &$stok_minimum_i;

    $cols[] = 'harga_beli';
    $harga_beli_d = (float)$harga_beli;
    $types .= 'd';
    $params[] = &$harga_beli_d;

    if ($has_harga_po) {
        $cols[] = 'harga_po';
        $harga_po_d = (float)$harga_po;
        $types .= 'd';
        $params[] = &$harga_po_d;
    }

    if ($has_expired) {
        $cols[] = 'expired_at';
        $types .= 's';
        $params[] = &$expired_at;
    }
    if ($has_created_by) {
        $cols[] = 'created_by';
        $created_by_i = (int)$created_by;
        $types .= 'i';
        $params[] = &$created_by_i;
    }
    if ($has_gambar) {
        $cols[] = 'gambar';
        $types .= 's';
        $params[] = &$gambar;
    }

    $placeholders = implode(',', array_fill(0, count($cols), '?'));
    $sql = "INSERT INTO barang (" . implode(',', $cols) . ") VALUES ($placeholders)";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        $_SESSION['error'] = "Gagal menambahkan barang: " . $conn->error;
        header("Location: index.php");
        exit();
    }
    array_unshift($params, $types);
    call_user_func_array([$stmt, 'bind_param'], $params);

    if ($stmt->execute()) {
        $_SESSION['success'] = "Barang berhasil ditambahkan!";
    } else {
        $_SESSION['error'] = "Gagal menambahkan barang!";
    }

    header("Location: index.php");
    exit();
}

// Proses Edit Barang
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $_POST['action'] == 'edit') {
    $id = clean_input($_POST['id']);
    $kode_barang = clean_input($_POST['kode_barang']);
    $barcode = clean_input($_POST['barcode']);
    $barcode_dus = clean_input($_POST['barcode_dus'] ?? '');
    $nama_barang = clean_input($_POST['nama_barang']);
    $supplier_id = clean_input($_POST['supplier_id']);
    $kategori_id = clean_input($_POST['kategori_id']);
    $satuan_id = clean_input($_POST['satuan_id']);
    $baku_non_baku = isset($_POST['baku_non_baku']) ? clean_input($_POST['baku_non_baku']) : 'non_baku';
    if (!in_array($baku_non_baku, ['baku', 'non_baku'], true)) {
        $baku_non_baku = 'non_baku';
    }
    $stok_minimum = clean_input($_POST['stok_minimum']);
    $harga_beli = clean_input($_POST['harga_beli']);
    $harga_po = isset($_POST['harga_po']) ? clean_input($_POST['harga_po']) : '';
    $expired_at = !empty($_POST['expired_at']) ? clean_input($_POST['expired_at']) : null;

    // Cek duplikasi kode barang
    $check = $conn->query("SELECT id FROM barang WHERE kode_barang = '$kode_barang' AND id != $id");
    if ($check->num_rows > 0) {
        $_SESSION['error'] = "Kode barang sudah digunakan!";
        header("Location: index.php");
        exit();
    }

    $has_harga_po = db_has_column($conn, 'barang', 'harga_po');
    $has_barcode_dus = ensure_barang_barcode_dus_column($conn);

    // Upload gambar baru jika ada
    if(isset($_FILES['gambar']) && $_FILES['gambar']['error'] == 0) {
        $gambar = upload_image($_FILES['gambar']);
        if($gambar === false) {
            $_SESSION['error'] = "Gagal mengupload gambar!";
            header("Location: index.php");
            exit();
        }

        // Hapus gambar lama
        $old_image = $conn->query("SELECT gambar FROM barang WHERE id = $id")->fetch_assoc();
        if($old_image['gambar']) {
            @unlink("../uploads/barang/" . $old_image['gambar']);
        }

        $sql = "UPDATE barang SET 
                kode_barang = ?, 
                barcode = ?," .
                ($has_barcode_dus ? " barcode_dus = ?," : "") . "
                nama_barang = ?, 
                supplier_id = ?,
                kategori_id = ?, 
                satuan_id = ?, 
                baku_non_baku = ?,
                stok_minimum = ?, 
                harga_beli = ?," .
                ($has_harga_po ? " harga_po = ?," : "") . "
                expired_at = ?,
                gambar = ? 
                WHERE id = ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            $_SESSION['error'] = "Gagal mengupdate barang: " . $conn->error;
            header("Location: index.php");
            exit();
        }
        $supplier_id_i = (int)$supplier_id;
        $kategori_id_i = (int)$kategori_id;
        $satuan_id_i = (int)$satuan_id;
        $stok_minimum_i = (int)$stok_minimum;
        $harga_beli_d = (float)$harga_beli;
        $harga_po_d = (float)$harga_po;
        $id_i = (int)$id;
        if ($has_barcode_dus) {
            if ($has_harga_po) {
                $stmt->bind_param("ssssiiisiddssi", $kode_barang, $barcode, $barcode_dus, $nama_barang, $supplier_id_i, $kategori_id_i, $satuan_id_i, $baku_non_baku, $stok_minimum_i, $harga_beli_d, $harga_po_d, $expired_at, $gambar, $id_i);
            } else {
                $stmt->bind_param("ssssiiisidssi", $kode_barang, $barcode, $barcode_dus, $nama_barang, $supplier_id_i, $kategori_id_i, $satuan_id_i, $baku_non_baku, $stok_minimum_i, $harga_beli_d, $expired_at, $gambar, $id_i);
            }
        } else {
            if ($has_harga_po) {
                $stmt->bind_param("sssiiisiddssi", $kode_barang, $barcode, $nama_barang, $supplier_id_i, $kategori_id_i, $satuan_id_i, $baku_non_baku, $stok_minimum_i, $harga_beli_d, $harga_po_d, $expired_at, $gambar, $id_i);
            } else {
                $stmt->bind_param("sssiiisidssi", $kode_barang, $barcode, $nama_barang, $supplier_id_i, $kategori_id_i, $satuan_id_i, $baku_non_baku, $stok_minimum_i, $harga_beli_d, $expired_at, $gambar, $id_i);
            }
        }
    } else {
        $sql = "UPDATE barang SET 
                kode_barang = ?, 
                barcode = ?," .
                ($has_barcode_dus ? " barcode_dus = ?," : "") . "
                nama_barang = ?, 
                supplier_id = ?,
                kategori_id = ?, 
                satuan_id = ?, 
                baku_non_baku = ?,
                stok_minimum = ?, 
                harga_beli = ?," .
                ($has_harga_po ? " harga_po = ?," : "") . "
                expired_at = ?
                WHERE id = ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            $_SESSION['error'] = "Gagal mengupdate barang: " . $conn->error;
            header("Location: index.php");
            exit();
        }
        $supplier_id_i = (int)$supplier_id;
        $kategori_id_i = (int)$kategori_id;
        $satuan_id_i = (int)$satuan_id;
        $stok_minimum_i = (int)$stok_minimum;
        $harga_beli_d = (float)$harga_beli;
        $harga_po_d = (float)$harga_po;
        $id_i = (int)$id;
        if ($has_barcode_dus) {
            if ($has_harga_po) {
                $stmt->bind_param("ssssiiisiddsi", $kode_barang, $barcode, $barcode_dus, $nama_barang, $supplier_id_i, $kategori_id_i, $satuan_id_i, $baku_non_baku, $stok_minimum_i, $harga_beli_d, $harga_po_d, $expired_at, $id_i);
            } else {
                $stmt->bind_param("ssssiiisidsi", $kode_barang, $barcode, $barcode_dus, $nama_barang, $supplier_id_i, $kategori_id_i, $satuan_id_i, $baku_non_baku, $stok_minimum_i, $harga_beli_d, $expired_at, $id_i);
            }
        } else {
            if ($has_harga_po) {
                $stmt->bind_param("sssiiisiddsi", $kode_barang, $barcode, $nama_barang, $supplier_id_i, $kategori_id_i, $satuan_id_i, $baku_non_baku, $stok_minimum_i, $harga_beli_d, $harga_po_d, $expired_at, $id_i);
            } else {
                $stmt->bind_param("sssiiisidsi", $kode_barang, $barcode, $nama_barang, $supplier_id_i, $kategori_id_i, $satuan_id_i, $baku_non_baku, $stok_minimum_i, $harga_beli_d, $expired_at, $id_i);
            }
        }
    }

    if ($stmt->execute()) {
        $_SESSION['success'] = "Barang berhasil diupdate!";
    } else {
        $_SESSION['error'] = "Gagal mengupdate barang!";
    }

    header("Location: index.php");
    exit();
}

// Proses Delete Barang
if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['action']) && $_GET['action'] == 'delete') {
    $id = clean_input($_GET['id']);
    if(!is_numeric($id) || $id <= null) {
        $_SESSION['error'] = "ID barang tidak valid";
        header("Location: index.php");
        exit();
    }

    // Cek penggunaan di tabel terkait
    $tables_to_check = [
        'stok',
        'detail_transaksi_stok',
        'detail_transaksi_stock',
        'detail_purchase_order',
        'detail_direct_purchase',
        'detail_transaksi_transfer',
        'gudang_stok',
        'po_detail',
        'riwayat_stok',
        'stok_barang',
        'stok_gudang',
        'stok_history',
        'stok_keluar',
        'stok_masuk',
        'stok_terpakai',
        'supplier_barang',
        'surat_jalan_items'
    ];

    foreach($tables_to_check as $table) {
        $check = $conn->query("SELECT id FROM $table WHERE barang_id = $id");
        if ($check->num_rows > 0) {
            $_SESSION['error'] = "Barang ini memiliki relasi di tabel $table!";
            header("Location: index.php");
            exit();
        }
    }

    // Hapus gambar jika ada
    $old_image = $conn->query("SELECT gambar FROM barang WHERE id = $id")->fetch_assoc();
    if($old_image['gambar'] && file_exists("../uploads/barang/" . $old_image['gambar'])) {
        @unlink("../uploads/barang/" . $old_image['gambar']);
    }

    $sql = "DELETE FROM barang WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $id);
    
    if($stmt->execute()) {
        $_SESSION['success'] = 'Barang berhasil dihapus';
    } else {
        $_SESSION['error'] = 'Gagal menghapus barang: ' . $conn->error;
    }
    
    header("Location: index.php");
    exit();
}

// Jika tidak ada action yang sesuai
header("Location: index.php");
exit();
?>
