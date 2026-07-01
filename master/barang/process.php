<?php
session_start();
require_once '../../config.php';
require_once '../../includes/access_check.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
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
    redirectIfNoAccess('barang', 'setup_split', 'index.php');
    header('Content-Type: application/json; charset=utf-8');

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
    redirectIfNoAccess('barang', 'setup_split', 'index.php');
    header('Content-Type: application/json; charset=utf-8');

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
            $sql = "SELECT id FROM barang WHERE id IN ($placeholders)";
            $stmt_in = $conn->prepare($sql);
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

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Check access untuk add barang
    redirectIfNoAccess('barang', 'add', 'index.php');

    // Validasi input
    if (empty($_POST['nama_barang']) || !is_array($_POST['nama_barang'])) {
        $_SESSION['error'] = "Nama barang harus diisi!";
        header("Location: create.php");
        exit();
    }

    $nama_barang_array = $_POST['nama_barang'];
    $kategori_id_array = $_POST['kategori_id'];
    $satuan_id_array = $_POST['satuan_id'];
    $harga_beli_array = $_POST['harga_beli'];
    $harga_jual_array = $_POST['harga_jual'];
    $supplier_id_array = $_POST['supplier_id'];
    $stok_minimal_array = $_POST['stok_minimal'];
    $deskripsi_array = $_POST['deskripsi'];

    // Proses upload foto jika ada
    $uploaded_files = array();
    if (isset($_FILES['foto']) && is_array($_FILES['foto']['name'])) {
        $upload_dir = '../../uploads/barang/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        foreach ($_FILES['foto']['tmp_name'] as $key => $tmp_name) {
            if ($_FILES['foto']['error'][$key] === UPLOAD_ERR_OK) {
                $file_name = $_FILES['foto']['name'][$key];
                $file_size = $_FILES['foto']['size'][$key];
                $file_type = $_FILES['foto']['type'][$key];
                
                // Validasi file
                if ($file_size > 2 * 1024 * 1024) { // 2MB max
                    continue; // Skip file yang terlalu besar
                }
                
                $allowed_types = array('image/jpeg', 'image/png', 'image/gif');
                if (!in_array($file_type, $allowed_types)) {
                    continue; // Skip file yang tidak valid
                }
                
                // Generate unique filename
                $file_extension = pathinfo($file_name, PATHINFO_EXTENSION);
                $unique_filename = time() . '_' . uniqid() . '.' . $file_extension;
                $upload_path = $upload_dir . $unique_filename;
                
                if (move_uploaded_file($tmp_name, $upload_path)) {
                    $uploaded_files[$key] = $unique_filename;
                }
            }
        }
    }

    // Generate kode barang otomatis untuk setiap item
    $sql_last_code = "SELECT kode_barang FROM barang ORDER BY id DESC LIMIT 1";
    $result_last_code = $conn->query($sql_last_code);
    $last_code = "BRG001";
    if ($result_last_code->num_rows > 0) {
        $row = $result_last_code->fetch_assoc();
        $last_code = $row['kode_barang'];
        $number = intval(substr($last_code, 3));
    } else {
        $number = 0;
    }

    $success_count = 0;
    $error_count = 0;

    foreach ($nama_barang_array as $key => $nama_barang) {
        if (empty($nama_barang)) continue; // Skip jika nama barang kosong

        $number++;
        $kode_barang = "BRG" . str_pad($number, 3, '0', STR_PAD_LEFT);
        
        // Bersihkan format Rupiah dari harga
        $harga_beli_bersih = str_replace(['Rp ', '.', ','], ['', '', '.'], $harga_beli_array[$key]);
        $harga_beli_float = (float)$harga_beli_bersih;
        
        $harga_jual_bersih = str_replace(['Rp ', '.', ','], ['', '', '.'], $harga_jual_array[$key]);
        $harga_jual_float = (float)$harga_jual_bersih;

        // Prepare statement untuk insert barang
        $sql = "INSERT INTO barang (kode_barang, nama_barang, kategori_id, satuan_id, harga_beli, harga_jual, supplier_id, stok_minimum, gambar, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            $error_count++;
            continue;
        }

        $kategori_id = !empty($kategori_id_array[$key]) ? $kategori_id_array[$key] : null;
        $satuan_id = !empty($satuan_id_array[$key]) ? $satuan_id_array[$key] : null;
        $supplier_id = !empty($supplier_id_array[$key]) ? $supplier_id_array[$key] : null;
        $stok_minimum = !empty($stok_minimal_array[$key]) ? $stok_minimal_array[$key] : 0;
        $gambar = isset($uploaded_files[$key]) ? $uploaded_files[$key] : '';
        $created_by = $_SESSION['user_id'];

        $stmt->bind_param('ssiiddiiss', 
            $kode_barang, 
            $nama_barang, 
            $kategori_id, 
            $satuan_id, 
            $harga_beli_float, 
            $harga_jual_float, 
            $supplier_id, 
            $stok_minimum, 
            $gambar
        );

        if ($stmt->execute()) {
            $success_count++;
        } else {
            $error_count++;
        }

        $stmt->close();
    }

    if ($success_count > 0) {
        if ($error_count > 0) {
            $_SESSION['success'] = "Berhasil menambahkan {$success_count} barang. {$error_count} barang gagal ditambahkan.";
        } else {
            $_SESSION['success'] = "Berhasil menambahkan {$success_count} barang!";
        }
    } else {
        $_SESSION['error'] = "Gagal menambahkan barang. Silakan coba lagi.";
    }

    header("Location: index.php");
    exit();
}

// Handle delete action
if (isset($_GET['action']) && $_GET['action'] == 'delete') {
    // Check access untuk delete barang
    redirectIfNoAccess('barang', 'delete', 'index.php');
    
    $id = (int)$_GET['id'];
    
    // Ambil nama file foto sebelum hapus
    $sql = "SELECT gambar FROM barang WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $barang = $result->fetch_assoc();
    
    // Hapus file foto jika ada
    if ($barang && !empty($barang['gambar'])) {
        $foto_path = '../../uploads/barang/' . $barang['gambar'];
        if (file_exists($foto_path)) {
            unlink($foto_path);
        }
    }
    
    // Hapus data barang
    $sql = "DELETE FROM barang WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $id);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Data barang berhasil dihapus";
    } else {
        $_SESSION['error'] = "Gagal menghapus data barang";
    }
    
    header("Location: index.php");
    exit();
}

// Jika bukan POST atau GET yang valid, redirect ke index
header("Location: index.php");
exit();
?>
