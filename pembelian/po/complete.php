<?php
session_start();
require_once '../../config.php';
require_once '../../includes/access_check.php';
require_once '../../includes/menu_access_helper.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit();
}

// Check access untuk menu purchase_order complete
if (!checkAccess('purchase_order', 'complete')) {
    $_SESSION['error'] = "Anda tidak memiliki akses untuk melakukan konfirmasi pembelian";
    header("Location: index.php");
    exit();
}

function ensure_po_stock_split_table($conn) {
    $res = $conn->query("SHOW TABLES LIKE 'po_stock_split'");
    if ($res instanceof mysqli_result) {
        $exists = $res->num_rows > 0;
        $res->free();
        if ($exists) {
            if (!db_has_column($conn, 'po_stock_split', 'split_barang_id')) {
                $conn->query("ALTER TABLE po_stock_split ADD COLUMN split_barang_id INT(11) DEFAULT NULL AFTER detail_purchase_order_id");
                $conn->query("ALTER TABLE po_stock_split ADD KEY idx_split_barang (split_barang_id)");
            }
            return true;
        }
    }
    $sql = "CREATE TABLE IF NOT EXISTS po_stock_split (
        id INT(11) NOT NULL AUTO_INCREMENT,
        purchase_order_id INT(11) NOT NULL,
        detail_purchase_order_id INT(11) NOT NULL,
        split_barang_id INT(11) DEFAULT NULL,
        detail_barang VARCHAR(255) NOT NULL,
        qty_output INT(11) NOT NULL DEFAULT 0,
        created_by INT(11) DEFAULT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (id),
        KEY idx_po (purchase_order_id),
        KEY idx_dpo (detail_purchase_order_id),
        KEY idx_split_barang (split_barang_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
    return $conn->query($sql) === true;
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_split') {
    header('Content-Type: application/json; charset=utf-8');

    $po_id = isset($_POST['po_id']) ? (int)$_POST['po_id'] : 0;
    $detail_id = isset($_POST['detail_id']) ? (int)$_POST['detail_id'] : 0;
    $mode = isset($_POST['mode']) ? (string)$_POST['mode'] : '';
    $split_barang_ids = isset($_POST['split_barang_id']) && is_array($_POST['split_barang_id']) ? $_POST['split_barang_id'] : [];
    $split_qtys = isset($_POST['split_qty']) && is_array($_POST['split_qty']) ? $_POST['split_qty'] : [];

    if ($po_id <= 0 || $detail_id <= 0) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Data tidak valid'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit();
    }
    if ($mode !== 'split' && $mode !== 'sesuai') {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Mode tidak valid'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit();
    }

    ensure_po_stock_split_table($conn);

    $conn->begin_transaction();
    try {
        $stmt_po = $conn->prepare("SELECT id, status FROM purchase_order WHERE id = ? FOR UPDATE");
        if (!$stmt_po) {
            throw new Exception('Database error');
        }
        $stmt_po->bind_param('i', $po_id);
        $stmt_po->execute();
        $po_res = $stmt_po->get_result();
        $po_row = $po_res ? $po_res->fetch_assoc() : null;
        $stmt_po->close();

        if (!$po_row) {
            throw new Exception('PO tidak ditemukan');
        }
        if ((string)$po_row['status'] !== 'approved') {
            throw new Exception('Split hanya bisa disimpan saat PO berstatus approved');
        }

        $stmt_d = $conn->prepare("SELECT id, barang_id, jumlah FROM detail_purchase_order WHERE id = ? AND purchase_order_id = ? AND (status IS NULL OR status != 'rejected') FOR UPDATE");
        if (!$stmt_d) {
            throw new Exception('Database error');
        }
        $stmt_d->bind_param('ii', $detail_id, $po_id);
        $stmt_d->execute();
        $d_res = $stmt_d->get_result();
        $d_row = $d_res ? $d_res->fetch_assoc() : null;
        $stmt_d->close();
        if (!$d_row) {
            throw new Exception('Item PO tidak ditemukan');
        }
        $parent_barang_id = (int)($d_row['barang_id'] ?? 0);
        $parent_qty = (int)($d_row['jumlah'] ?? 0);
        if ($parent_barang_id <= 0 || $parent_qty <= 0) {
            throw new Exception('Data barang PO tidak valid');
        }

        $stmt_del = $conn->prepare("DELETE FROM po_stock_split WHERE purchase_order_id = ? AND detail_purchase_order_id = ?");
        if (!$stmt_del) {
            throw new Exception('Database error');
        }
        $stmt_del->bind_param('ii', $po_id, $detail_id);
        if (!$stmt_del->execute()) {
            $stmt_del->close();
            throw new Exception('Gagal menghapus split lama');
        }
        $stmt_del->close();

        if ($mode === 'sesuai') {
            $conn->commit();
            echo json_encode(['status' => 'success', 'message' => 'Disimpan tanpa split'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit();
        }

        $setup_ids = [];
        $stmt_setup = $conn->prepare("SELECT split_barang_id FROM barang_split_setup WHERE parent_barang_id = ? ORDER BY split_barang_id");
        if ($stmt_setup) {
            $stmt_setup->bind_param('i', $parent_barang_id);
            $stmt_setup->execute();
            $setup_res = $stmt_setup->get_result();
            if ($setup_res) {
                while ($r = $setup_res->fetch_assoc()) {
                    $sid = (int)($r['split_barang_id'] ?? 0);
                    if ($sid > 0) {
                        $setup_ids[$sid] = true;
                    }
                }
            }
            $stmt_setup->close();
        }
        if (count($setup_ids) === 0) {
            throw new Exception('Barang ini belum disetup untuk split');
        }

        $merged = [];
        $max = max(count($split_barang_ids), count($split_qtys));
        for ($i = 0; $i < $max; $i++) {
            $split_barang_id = (int)($split_barang_ids[$i] ?? 0);
            $qty = (int)($split_qtys[$i] ?? 0);
            if ($split_barang_id <= 0 && $qty === 0) {
                continue;
            }
            if ($split_barang_id <= 0) {
                throw new Exception('Barang split wajib dipilih');
            }
            if (!isset($setup_ids[$split_barang_id])) {
                throw new Exception('Barang split tidak sesuai setup master barang');
            }
            if ($qty < 0) {
                throw new Exception('Qty split tidak valid');
            }
            if ($qty === 0) {
                continue;
            }
            if (!isset($merged[$split_barang_id])) {
                $merged[$split_barang_id] = 0;
            }
            $merged[$split_barang_id] += $qty;
        }
        if (count($merged) === 0) {
            throw new Exception('Minimal 1 baris split wajib diisi');
        }

        $stmt_ins = $conn->prepare("INSERT INTO po_stock_split (purchase_order_id, detail_purchase_order_id, split_barang_id, detail_barang, qty_output, created_by) VALUES (?, ?, ?, ?, ?, ?)");
        if (!$stmt_ins) {
            throw new Exception('Database error');
        }
        $created_by = (int)$_SESSION['user_id'];
        foreach ($merged as $split_barang_id => $qty) {
            $split_barang_id = (int)$split_barang_id;
            $qty = (int)$qty;
            $detail_barang = '';
            $stmt_ins->bind_param('iiisii', $po_id, $detail_id, $split_barang_id, $detail_barang, $qty, $created_by);
            if (!$stmt_ins->execute()) {
                throw new Exception('Gagal menyimpan split');
            }
        }
        $stmt_ins->close();

        $conn->commit();
        echo json_encode(['status' => 'success', 'message' => 'Split berhasil disimpan'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit();
    }
}

// Jika form disubmit (POST method) untuk konfirmasi selesai
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_complete'])) {
    $po_id = $_POST['po_id'];
    $purchase_date = $_POST['purchase_date'];
    $keterangan_complete = (string)($_SESSION['username'] ?? $_SESSION['nama'] ?? $_SESSION['user_id'] ?? '');
    
    // Check if all items are rejected
    $sql_check = "SELECT 
        COUNT(*) as total_items,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_items,
        SUM(CASE WHEN status != 'rejected' THEN jumlah ELSE 0 END) as valid_total_items,
        SUM(CASE WHEN status != 'rejected' THEN jumlah * harga_satuan ELSE 0 END) as valid_total_harga
        FROM detail_purchase_order 
        WHERE purchase_order_id = ?";
    
    $stmt_check = $conn->prepare($sql_check);
    if (!$stmt_check) {
        $_SESSION['error'] = "Database error: " . htmlspecialchars($conn->error);
        header("Location: index.php");
        exit();
    }
    
    $stmt_check->bind_param("i", $po_id);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    $counts = $result_check->fetch_assoc();
    $stmt_check->close();

    // If all items are rejected, update PO status to rejected
    if ($counts['total_items'] == $counts['rejected_items']) {
        $_SESSION['error'] = "Tidak dapat menyelesaikan PO karena semua item telah direject.";
        header("Location: index.php");
        exit();
    }
    
    // Validate date format
    if (!DateTime::createFromFormat('Y-m-d', $purchase_date)) {
        $_SESSION['error'] = "Format tanggal tidak valid";
        header("Location: index.php");
        exit();
    }
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Variabel untuk menyimpan path foto
        $foto_path = null;
        
        // Proses upload foto jika ada
        if (isset($_FILES['foto_po']) && $_FILES['foto_po']['error'] == 0) {
            $upload_dir = '../../uploads/po/';
            
            // Buat direktori jika belum ada
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_name = 'po_' . date('YmdHis') . '_' . uniqid() . '.' . pathinfo($_FILES['foto_po']['name'], PATHINFO_EXTENSION);
            $upload_path = $upload_dir . $file_name;
            
            // Validasi file
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            $max_size = 10 * 1024 * 1024; // 10MB
            
            if (!in_array($_FILES['foto_po']['type'], $allowed_types)) {
                throw new Exception("Format file tidak didukung. Gunakan JPG, PNG, atau GIF.");
            }
            
            if ($_FILES['foto_po']['size'] > $max_size) {
                throw new Exception("Ukuran file terlalu besar. Maksimal 10MB.");
            }
            
            if (move_uploaded_file($_FILES['foto_po']['tmp_name'], $upload_path)) {
                $foto_path = 'uploads/po/' . $file_name; // Simpan path relatif
            } else {
                throw new Exception("Gagal mengupload file.");
            }
        } else {
            throw new Exception("Upload foto validasi wajib dilakukan.");
        }
        
        // Update status PO, tanggal pembelian, foto, dan total
        $sql = "UPDATE purchase_order SET 
                status = 'completed', 
                purchase_date = ?, 
                foto = ?, 
                keterangan_complete = ?,
                completed_at = NOW(), 
                updated_at = NOW(),
                total_item = ?,
                total_harga = ?
                WHERE id = ?";
        $stmt = $conn->prepare($sql);
        
        if ($stmt === false) {
            throw new Exception("Database error: " . $conn->error);
        }
        
        $stmt->bind_param('sssidi', 
            $purchase_date, 
            $foto_path, 
            $keterangan_complete, 
            $counts['valid_total_items'],
            $counts['valid_total_harga'],
            $po_id
        );
        
        if (!$stmt->execute()) {
            throw new Exception("Gagal mengupdate status PO: " . $stmt->error);
        }
        
        // Update status items yang tidak di-reject menjadi completed
        $sql_update_items = "UPDATE detail_purchase_order 
                            SET status = 'completed' 
                            WHERE purchase_order_id = ? 
                            AND (status IS NULL OR status != 'rejected')";
        $stmt_items = $conn->prepare($sql_update_items);
        
        if ($stmt_items === false) {
            throw new Exception("Database error: " . $conn->error);
        }
        
        $stmt_items->bind_param('i', $po_id);
        
        if (!$stmt_items->execute()) {
            throw new Exception("Gagal mengupdate status items: " . $stmt_items->error);
        }
        
        $conn->commit();
        $_SESSION['success'] = "PO berhasil dikonfirmasi sebagai selesai";
        header("Location: index.php");
        exit();
        
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = $e->getMessage();
        header("Location: complete.php?id=" . $po_id . "&date=" . $purchase_date);
        exit();
    }
}
// Jika halaman diakses dengan GET method (menampilkan form)
else {
    if (!isset($_GET['id']) || !isset($_GET['date'])) {
        $_SESSION['error'] = "Data tidak lengkap";
        header("Location: index.php");
        exit();
    }
    
    $po_id = $_GET['id'];
    $purchase_date = $_GET['date'];
    
    // Validate date format
    if (!DateTime::createFromFormat('Y-m-d', $purchase_date)) {
        $_SESSION['error'] = "Format tanggal tidak valid";
        header("Location: index.php");
        exit();
    }
    
    // Ambil data PO untuk ditampilkan di form
    $sql = "SELECT po.*, s.nama_supplier 
            FROM purchase_order po 
            LEFT JOIN supplier s ON po.supplier_id = s.id
            WHERE po.id = ?";
    $stmt = $conn->prepare($sql);
    
    if ($stmt === false) {
        $_SESSION['error'] = "Database error: " . htmlspecialchars($conn->error);
        header("Location: index.php");
        exit();
    }
    
    $stmt->bind_param('i', $po_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $_SESSION['error'] = "PO tidak ditemukan";
        header("Location: index.php");
        exit();
    }
    
    $po_data = $result->fetch_assoc();
    $stmt->close();
    
    // Cek jika status sudah completed, tidak boleh di-complete lagi
    if ($po_data['status'] === 'completed') {
        $_SESSION['error'] = "Tidak dapat mengkonfirmasi PO yang sudah completed";
        header("Location: index.php");
        exit();
    }

    // Ambil detail items PO
    $sql_items = "SELECT dpd.*, b.nama_barang, b.kode_barang, s.nama_satuan
                 FROM detail_purchase_order dpd
                 LEFT JOIN barang b ON dpd.barang_id = b.id
                 LEFT JOIN satuan s ON b.satuan_id = s.id
                 WHERE dpd.purchase_order_id = ?";
    $stmt_items = $conn->prepare($sql_items);
    
    if ($stmt_items === false) {
        $_SESSION['error'] = "Database error: " . htmlspecialchars($conn->error);
        header("Location: index.php");
        exit();
    }
    
    $stmt_items->bind_param('i', $po_id);
    $stmt_items->execute();
    $result_items = $stmt_items->get_result();
    $po_items = $result_items->fetch_all(MYSQLI_ASSOC);
    $stmt_items->close();

    ensure_po_stock_split_table($conn);
    ensure_barang_split_setup_table($conn);

    $splitsByDetail = [];
    $stmt_s = $conn->prepare("SELECT id, detail_purchase_order_id, split_barang_id, detail_barang, qty_output FROM po_stock_split WHERE purchase_order_id = ? ORDER BY detail_purchase_order_id, id");
    if ($stmt_s) {
        $stmt_s->bind_param('i', $po_id);
        $stmt_s->execute();
        $s_res = $stmt_s->get_result();
        if ($s_res) {
            while ($s = $s_res->fetch_assoc()) {
                $dpoId = (int)$s['detail_purchase_order_id'];
                if (!isset($splitsByDetail[$dpoId])) {
                    $splitsByDetail[$dpoId] = [];
                }
                $splitsByDetail[$dpoId][] = [
                    'id' => (int)$s['id'],
                    'split_barang_id' => (int)($s['split_barang_id'] ?? 0),
                    'detail_barang' => (string)($s['detail_barang'] ?? ''),
                    'qty_output' => (int)($s['qty_output'] ?? 0),
                ];
            }
        }
        $stmt_s->close();
    }

    $splitSetupByParent = [];
    $barangIds = [];
    foreach ($po_items as $it) {
        if (($it['status'] ?? null) === 'rejected') {
            continue;
        }
        $bid = isset($it['barang_id']) ? (int)$it['barang_id'] : 0;
        if ($bid > 0) {
            $barangIds[$bid] = true;
        }
    }
    $barangIds = array_keys($barangIds);
    if (count($barangIds) > 0) {
        $placeholders = implode(',', array_fill(0, count($barangIds), '?'));
        $types = str_repeat('i', count($barangIds));
        $sql_setup = "SELECT bs.parent_barang_id, bs.split_barang_id, b.kode_barang, b.nama_barang, s.nama_satuan
                      FROM barang_split_setup bs
                      JOIN barang b ON bs.split_barang_id = b.id
                      LEFT JOIN satuan s ON b.satuan_id = s.id
                      WHERE bs.parent_barang_id IN ($placeholders)
                      ORDER BY bs.parent_barang_id, b.kode_barang, b.id";
        $stmt_setup = $conn->prepare($sql_setup);
        if ($stmt_setup) {
            $bind = [];
            $bind[] = &$types;
            foreach ($barangIds as $k => $v) {
                $barangIds[$k] = (int)$v;
                $bind[] = &$barangIds[$k];
            }
            call_user_func_array([$stmt_setup, 'bind_param'], $bind);
            $stmt_setup->execute();
            $setup_res = $stmt_setup->get_result();
            if ($setup_res) {
                while ($r = $setup_res->fetch_assoc()) {
                    $parentId = (int)($r['parent_barang_id'] ?? 0);
                    $splitId = (int)($r['split_barang_id'] ?? 0);
                    if ($parentId <= 0 || $splitId <= 0) {
                        continue;
                    }
                    if (!isset($splitSetupByParent[$parentId])) {
                        $splitSetupByParent[$parentId] = [];
                    }
                    $splitSetupByParent[$parentId][] = [
                        'id' => $splitId,
                        'kode_barang' => (string)($r['kode_barang'] ?? ''),
                        'nama_barang' => (string)($r['nama_barang'] ?? ''),
                        'nama_satuan' => (string)($r['nama_satuan'] ?? '')
                    ];
                }
            }
            $stmt_setup->close();
        }
    }
}

if (isset($_POST['reject_btn'])) {
    $reject_id = $_POST['reject_id'];
    $keterangan = $_POST['keterangan_reject'];

    $query = "UPDATE purchase_order SET status='rejected', keterangan=? WHERE id=?";
    $stmt = $conn->prepare($query);

    if (!$stmt) {
        die("Query prepare() gagal: " . $conn->error . " | SQL: " . $query);
    }

    if (!$stmt->bind_param("si", $keterangan, $reject_id)) {
        die("bind_param gagal: " . $stmt->error);
    }

    if ($stmt->execute()) {
        echo "<script>alert('Barang berhasil di-reject'); window.location.href='complete.php';</script>";
    } else {
        die("Eksekusi gagal: " . $stmt->error);
    }
}

// Handle item rejection
if (isset($_POST['reject_item'])) {
    $item_id = $_POST['item_id'];
    $keterangan = $_POST['keterangan_reject'];
    $po_id = $_POST['po_id'];

    // Start transaction
    $conn->begin_transaction();

    try {
        // Update status item menjadi rejected
        $sql_reject = "UPDATE detail_purchase_order SET status='rejected', keterangan=? WHERE id=?";
        $stmt_reject = $conn->prepare($sql_reject);

        if (!$stmt_reject) {
            throw new Exception("Prepare failed: " . $conn->error);
        }

        $stmt_reject->bind_param("si", $keterangan, $item_id);
        if (!$stmt_reject->execute()) {
            throw new Exception("Execute failed: " . $stmt_reject->error);
        }
        $stmt_reject->close();

        // Check if all items are rejected
        $sql_check = "SELECT 
            COUNT(*) as total_items,
            SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_items
            FROM detail_purchase_order 
            WHERE purchase_order_id = ?";
        
        $stmt_check = $conn->prepare($sql_check);
        if (!$stmt_check) {
            throw new Exception("Prepare check failed: " . $conn->error);
        }

        $stmt_check->bind_param("i", $po_id);
        if (!$stmt_check->execute()) {
            throw new Exception("Execute check failed: " . $stmt_check->error);
        }

        $result_check = $stmt_check->get_result();
        $counts = $result_check->fetch_assoc();
        $stmt_check->close();

        // If all items are rejected, update PO status to rejected
        if ($counts['total_items'] == $counts['rejected_items']) {
            $sql_update_po = "UPDATE purchase_order SET 
                status='rejected', 
                keterangan=?,
                updated_at=NOW() 
                WHERE id=?";
            
            $stmt_update = $conn->prepare($sql_update_po);
            if (!$stmt_update) {
                throw new Exception("Prepare update failed: " . $conn->error);
            }

            $stmt_update->bind_param("si", $keterangan, $po_id);
            if (!$stmt_update->execute()) {
                throw new Exception("Execute update failed: " . $stmt_update->error);
            }
            $stmt_update->close();

            // Update total PO (excluding rejected items)
            $sql_update_totals = "UPDATE purchase_order po 
                SET total_item = (
                    SELECT COALESCE(SUM(jumlah), 0)
                    FROM detail_purchase_order 
                    WHERE purchase_order_id = po.id 
                    AND status != 'rejected'
                ),
                total_harga = (
                    SELECT COALESCE(SUM(jumlah * harga_satuan), 0)
                    FROM detail_purchase_order 
                    WHERE purchase_order_id = po.id 
                    AND status != 'rejected'
                )
                WHERE id = ?";
            
            $stmt_totals = $conn->prepare($sql_update_totals);
            if (!$stmt_totals) {
                throw new Exception("Prepare totals update failed: " . $conn->error);
            }

            $stmt_totals->bind_param("i", $po_id);
            if (!$stmt_totals->execute()) {
                throw new Exception("Execute totals update failed: " . $stmt_totals->error);
            }
            $stmt_totals->close();
        }

        // Commit transaction
        $conn->commit();
        $_SESSION['success'] = "Item berhasil direject" . 
            ($counts['total_items'] == $counts['rejected_items'] ? " dan PO telah diupdate menjadi rejected" : "");
        
        // Redirect to index if all items are rejected
        if ($counts['total_items'] == $counts['rejected_items']) {
            header("Location: index.php");
            exit();
        } else {
            header("Location: complete.php?id=" . $po_id . "&date=" . $_GET['date']);
            exit();
        }

    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        $_SESSION['error'] = "Gagal mereject item: " . $e->getMessage();
        header("Location: complete.php?id=" . $po_id . "&date=" . $_GET['date']);
        exit();
    }
}

// Handle update jumlah item
if (isset($_POST['update_jumlah'])) {
    $item_id = $_POST['item_id'];
    $new_jumlah = (int)$_POST['jumlah'];
    $po_id = $_POST['po_id'];
    $purchase_date = $_POST['purchase_date'];

    if ($new_jumlah < 1) {
        $_SESSION['error'] = "Jumlah minimal 1";
        header("Location: complete.php?id=$po_id&date=$purchase_date");
        exit();
    }

    // Start transaction
    $conn->begin_transaction();
    try {
        // Update jumlah item
        $sql_update = "UPDATE detail_purchase_order SET jumlah=? WHERE id=? AND (status IS NULL OR status != 'rejected')";
        $stmt_update = $conn->prepare($sql_update);
        if (!$stmt_update) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        $stmt_update->bind_param("ii", $new_jumlah, $item_id);
        if (!$stmt_update->execute()) {
            throw new Exception("Execute failed: " . $stmt_update->error);
        }
        $stmt_update->close();

        ensure_po_stock_split_table($conn);
        $stmt_del_split = $conn->prepare("DELETE FROM po_stock_split WHERE purchase_order_id = ? AND detail_purchase_order_id = ?");
        if ($stmt_del_split) {
            $stmt_del_split->bind_param('ii', $po_id, $item_id);
            $stmt_del_split->execute();
            $stmt_del_split->close();
        }

        // Update total_item dan total_harga di purchase_order
        $sql_update_totals = "UPDATE purchase_order po 
            SET total_item = (
                SELECT COALESCE(SUM(jumlah), 0)
                FROM detail_purchase_order 
                WHERE purchase_order_id = po.id 
                AND (status IS NULL OR status != 'rejected')
            ),
            total_harga = (
                SELECT COALESCE(SUM(jumlah * harga_satuan), 0)
                FROM detail_purchase_order 
                WHERE purchase_order_id = po.id 
                AND (status IS NULL OR status != 'rejected')
            )
            WHERE id = ?";
        $stmt_totals = $conn->prepare($sql_update_totals);
        if (!$stmt_totals) {
            throw new Exception("Prepare totals update failed: " . $conn->error);
        }
        $stmt_totals->bind_param("i", $po_id);
        if (!$stmt_totals->execute()) {
            throw new Exception("Execute totals update failed: " . $stmt_totals->error);
        }
        $stmt_totals->close();

        $conn->commit();
        $_SESSION['success'] = "Jumlah item berhasil diupdate";
        header("Location: complete.php?id=$po_id&date=$purchase_date");
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = "Gagal update jumlah: " . $e->getMessage();
        header("Location: complete.php?id=$po_id&date=$purchase_date");
        exit();
    }
}

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Konfirmasi Selesai PO - Sistem Inventory</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.0.7/css/boxicons.min.css" rel="stylesheet">
    <style>
        .rejected {
            background-color: #ffebee !important;
            text-decoration: line-through;
        }
        .split-config-row td {
            background: #f8f9fa;
        }
        .btn-split,
        .btn-save-split,
        .btn-sesuai {
            position: relative;
            z-index: 2;
        }
    </style>
</head>
<body>
    <?php include '../../templates/navbar.php'; ?>

    <div class="mt-4">
        <h2>Konfirmasi Selesai Purchase Order</h2>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= $_SESSION['error']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= $_SESSION['success']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Detail PO: <?= htmlspecialchars($po_data['no_po']) ?></h5>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <p><strong>Supplier:</strong> <?= htmlspecialchars($po_data['nama_supplier']) ?></p>
                        <p><strong>Tanggal PO:</strong> <?= date('d/m/Y', strtotime($po_data['tanggal'])) ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Status:</strong> 
                            <?php 
                            switch($po_data['status']) {
                                case 'draft':
                                    echo '<span class="badge bg-warning">Menunggu</span>';
                                    break;
                                case 'approved':
                                    echo '<span class="badge bg-success">Approved</span>';
                                    break;
                                case 'completed':
                                    echo '<span class="badge bg-primary">Selesai</span>';
                                    break;
                                case 'rejected':
                                    echo '<span class="badge bg-danger">Rejected</span>';
                                    break;
                                default:
                                    echo '<span class="badge bg-secondary">'.ucfirst($po_data['status']).'</span>';
                            }
                            ?>
                        </p>
                        <p><strong>Keterangan:</strong> <?= htmlspecialchars($po_data['keterangan'] ?: '-') ?></p>
                    </div>
                </div>

                <!-- Table for PO Items -->
                <div class="table-responsive mt-4">
                    <table class="table table-bordered table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>No</th>
                                <th>Kode Barang</th>
                                <th>Nama Barang</th>
                                <th>Jumlah</th>
                                <th>Satuan</th>
                                <th>Status</th>
                                <th>Keterangan</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $no = 1; foreach ($po_items as $item): ?>
                            <?php
                                $detailId = (int)($item['id'] ?? 0);
                                $barangId = (int)($item['barang_id'] ?? 0);
                                $existingSplits = $detailId > 0 ? ($splitsByDetail[$detailId] ?? []) : [];
                                $candidates = $barangId > 0 ? ($splitSetupByParent[$barangId] ?? []) : [];
                                $hasSplitSetup = count($candidates) > 0;

                                $existingQtyBySplit = [];
                                $hasLegacySplit = false;
                                foreach ($existingSplits as $s) {
                                    $sid = (int)($s['split_barang_id'] ?? 0);
                                    if ($sid > 0) {
                                        $existingQtyBySplit[$sid] = (int)($s['qty_output'] ?? 0);
                                    } else {
                                        $hasLegacySplit = true;
                                    }
                                }
                                $splitEnabled = $hasLegacySplit || count($existingQtyBySplit) > 0;
                            ?>
                            <tr class="<?= $item['status'] === 'rejected' ? 'rejected' : '' ?>">
                                <td><?= $no++ ?></td>
                                <td><?= htmlspecialchars($item['kode_barang']) ?></td>
                                <td><?= htmlspecialchars($item['nama_barang']) ?></td>
                                <td class="text-end">
                                    <?php if ($item['status'] !== 'rejected'): ?>
                                    <form action="" method="POST" class="d-flex align-items-center" style="gap:4px;">
                                        <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                                        <input type="hidden" name="po_id" value="<?= $po_id ?>">
                                        <input type="hidden" name="purchase_date" value="<?= $purchase_date ?>">
                                        <input type="number" name="jumlah" value="<?= (int)$item['jumlah'] ?>" min="1" class="form-control form-control-sm" style="width:80px;" required>
                                        <button type="submit" name="update_jumlah" class="btn btn-sm btn-success">Update</button>
                                    </form>
                                    <?php else: ?>
                                        <?= number_format($item['jumlah']) ?>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($item['nama_satuan']) ?></td>
                                <td>
                                    <?php
                                    $status_class = '';
                                    $status_text = '';
                                    switch($item['status']) {
                                        case 'rejected':
                                            $status_class = 'bg-danger';
                                            $status_text = 'Rejected';
                                            break;
                                        case 'completed':
                                            $status_class = 'bg-success';
                                            $status_text = 'Completed';
                                            break;
                                        default:
                                            $status_class = 'bg-warning';
                                            $status_text = 'Pending';
                                    }
                                    ?>
                                    <span class="badge <?= $status_class ?>"><?= $status_text ?></span>
                                </td>
                                <td><?= htmlspecialchars($item['keterangan'] ?: '-') ?></td>
                                <td>
                                    <?php if ($item['status'] !== 'rejected'): ?>
                                    <div class="d-flex flex-wrap gap-1">
                                        <?php if ($hasSplitSetup): ?>
                                            <button type="button" class="btn btn-danger btn-sm btn-split" data-detail-id="<?= (int)$item['id'] ?>" data-po-id="<?= (int)$po_id ?>" <?= $detailId <= 0 ? 'disabled' : '' ?>>Split</button>
                                        <?php else: ?>
                                            <button type="button" class="btn btn-outline-danger btn-sm btn-split" data-detail-id="<?= (int)$item['id'] ?>" data-po-id="<?= (int)$po_id ?>" <?= $detailId <= 0 ? 'disabled' : '' ?> title="Barang belum disetup split di master barang">Split</button>
                                        <?php endif; ?>
                                        <button type="button" class="btn btn-success btn-sm btn-sesuai" data-detail-id="<?= (int)$item['id'] ?>" data-po-id="<?= (int)$po_id ?>" <?= $detailId <= 0 ? 'disabled' : '' ?>>Sesuai</button>
                                        <button type="button" class="btn btn-success btn-sm btn-save-split <?= ($splitEnabled && $hasSplitSetup) ? '' : 'd-none' ?>" data-detail-id="<?= (int)$item['id'] ?>" data-po-id="<?= (int)$po_id ?>" <?= $detailId <= 0 ? 'disabled' : '' ?>>SAVE</button>
                                        <button type="button" class="btn btn-outline-danger btn-sm" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#rejectModal<?= $item['id'] ?>">
                                            <i class="bx bx-x"></i> Reject
                                        </button>
                                    </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php if ($item['status'] !== 'rejected'): ?>
                            <tr class="split-config-row" data-detail-id="<?= $detailId ?>" style="<?= $splitEnabled ? '' : 'display:none;' ?>">
                                <td colspan="8">
                                    <div class="p-2">
                                        <?php if ($hasSplitSetup): ?>
                                            <div class="table-responsive">
                                                <table class="table table-sm table-bordered mb-0">
                                                    <thead class="table-light">
                                                        <tr>
                                                            <th style="width: 65%;">Barang Split</th>
                                                            <th style="width: 15%;" class="text-end">Qty</th>
                                                            <th style="width: 20%;">Satuan</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody class="split-body" data-detail-id="<?= $detailId ?>" data-saved="<?= $splitEnabled ? '1' : '0' ?>">
                                                        <?php foreach ($candidates as $c): ?>
                                                            <?php $sid = (int)($c['id'] ?? 0); ?>
                                                            <tr>
                                                                <td>
                                                                    <?= htmlspecialchars((string)($c['kode_barang'] ?? '')) ?> - <?= htmlspecialchars((string)($c['nama_barang'] ?? '')) ?>
                                                                    <input type="hidden" class="split-barang-id" name="split_barang_id_tmp[<?= (int)$detailId ?>][]" value="<?= $sid ?>">
                                                                </td>
                                                                <td>
                                                                    <input type="number" class="form-control form-control-sm text-end split-qty" id="split_qty_<?= (int)$detailId ?>_<?= (int)$sid ?>" name="split_qty_tmp[<?= (int)$detailId ?>][]" min="0" step="1" value="<?= isset($existingQtyBySplit[$sid]) ? (int)$existingQtyBySplit[$sid] : 0 ?>">
                                                                </td>
                                                                <td><?= htmlspecialchars((string)($c['nama_satuan'] ?? '')) ?></td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                            <div class="text-muted small mt-2">Total qty split tidak wajib sama dengan qty PO.</div>
                                        <?php else: ?>
                                            <div class="alert alert-warning mb-0">Barang ini belum memiliki setup split di master barang.</div>
                                        <?php endif; ?>

                                        <?php if ($hasLegacySplit): ?>
                                            <div class="mt-3">
                                                <div class="fw-semibold mb-1">Split lama terdeteksi</div>
                                                <div class="table-responsive">
                                                    <table class="table table-sm table-bordered mb-0">
                                                        <thead class="table-light">
                                                            <tr>
                                                                <th>Detail</th>
                                                                <th class="text-end">Qty</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php foreach ($existingSplits as $s): ?>
                                                                <?php if ((int)($s['split_barang_id'] ?? 0) > 0) continue; ?>
                                                                <tr>
                                                                    <td><?= htmlspecialchars((string)($s['detail_barang'] ?? '')) ?></td>
                                                                    <td class="text-end"><?= (int)($s['qty_output'] ?? 0) ?></td>
                                                                </tr>
                                                            <?php endforeach; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endif; ?>

                            <!-- Reject Modal for each item -->
                            <div class="modal fade" id="rejectModal<?= $item['id'] ?>" tabindex="-1">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Reject Item</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <form action="" method="POST">
                                            <div class="modal-body">
                                                <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                                                <input type="hidden" name="po_id" value="<?= $po_id ?>">
                                                <div class="mb-3">
                                                    <label for="keterangan_reject_<?= (int)$item['id'] ?>" class="form-label">Alasan Reject</label>
                                                    <textarea class="form-control" id="keterangan_reject_<?= (int)$item['id'] ?>" name="keterangan_reject" rows="3" required></textarea>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                                <button type="submit" name="reject_item" class="btn btn-danger">Reject Item</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Upload Foto Validasi dan Konfirmasi Selesai</h5>
            </div>
            <div class="card-body">
                <form action="" method="post" enctype="multipart/form-data">
                    <input type="hidden" name="po_id" value="<?= $po_id ?>">
                    <input type="hidden" name="purchase_date" value="<?= $purchase_date ?>">
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="purchase_date_display" class="form-label">Tanggal Pembelian</label>
                            <input type="date" class="form-control" id="purchase_date_display" value="<?= $purchase_date ?>" disabled>
                            <div class="form-text">Tanggal pembelian yang akan dikonfirmasi</div>
                        </div>
                        <div class="col-md-6">
                            <label for="keterangan_complete" class="form-label">User Complete</label>
                            <textarea class="form-control" id="keterangan_complete" name="keterangan_complete" rows="3" readonly><?= htmlspecialchars((string)($_SESSION['username'] ?? $_SESSION['nama'] ?? $_SESSION['user_id'] ?? '')) ?></textarea>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="foto_po" class="form-label">Upload Foto Validasi <span class="text-danger">*</span></label>
                            <input type="file" class="form-control" id="foto_po" name="foto_po" accept="image/*" required>
                            <div class="form-text">Format yang didukung: JPG, PNG, GIF. Maksimal 2MB.</div>
                        </div>
                    </div>
                    
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <!-- Placeholder for spacing -->
                        </div>
                        <div class="col-md-6" id="preview-container" style="display: none;">
                            <label class="form-label">Preview</label>
                            <div class="border p-2">
                                <img id="preview-image" src="#" alt="Preview" style="max-width: 100%; max-height: 200px;">
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-between">
                        <a href="index.php" class="btn btn-secondary">Kembali</a>
                        <button type="submit" class="btn btn-primary" name="submit_complete">Konfirmasi Selesai</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function() {
            // Preview foto yang diupload
            $('#foto_po').change(function() {
                const file = this.files[0];
                if (file) {
                    // Validasi ukuran file (max 2MB)
                    if (file.size > 10 * 1024 * 1024) {
                        alert('Ukuran file terlalu besar. Maksimal 10MB.');
                        this.value = '';
                        $('#preview-container').hide();
                        return;
                    }
                    
                    // Validasi tipe file
                    const validTypes = ['image/jpeg', 'image/png', 'image/gif'];
                    if (!validTypes.includes(file.type)) {
                        alert('Format file tidak didukung. Gunakan JPG, PNG, atau GIF.');
                        this.value = '';
                        $('#preview-container').hide();
                        return;
                    }
                    
                    // Tampilkan preview
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        $('#preview-image').attr('src', e.target.result);
                        $('#preview-container').show();
                    }
                    reader.readAsDataURL(file);
                } else {
                    $('#preview-container').hide();
                }
            });
            
            // Validasi form sebelum submit - hanya untuk form konfirmasi selesai
            $('form').submit(function(e) {
                // Cek apakah ini form konfirmasi selesai (memiliki button dengan name="submit_complete")
                if ($(this).find('button[name="submit_complete"]').length > 0) {
                    // Cek apakah foto sudah diupload
                    if ($('#foto_po').get(0).files.length === 0) {
                        e.preventDefault();
                        alert('Upload foto validasi wajib dilakukan.');
                        return false;
                    }
                }
                
                return true;
            });
        });
    </script>
    <script>
        function markSplitUnsaved(detailId) {
            const tbody = document.querySelector('.split-body[data-detail-id="' + detailId + '"]');
            if (!tbody) return;
            tbody.dataset.saved = '0';
            const saveBtn = document.querySelector('.btn-save-split[data-detail-id="' + detailId + '"]');
            if (saveBtn) saveBtn.classList.remove('d-none');
        }

        function markSplitSaved(detailId) {
            const tbody = document.querySelector('.split-body[data-detail-id="' + detailId + '"]');
            if (!tbody) return;
            tbody.dataset.saved = '1';
        }

        function showSplit(detailId) {
            const row = document.querySelector('.split-config-row[data-detail-id="' + detailId + '"]');
            if (!row) return;
            const wasHidden = row.style.display === 'none';
            row.style.display = '';
            const tbody = row.querySelector('tbody.split-body');
            const alreadySaved = tbody && String(tbody.dataset.saved || '0') === '1';
            if (wasHidden && !alreadySaved) {
                markSplitUnsaved(detailId);
            }
        }

        function hideSplit(detailId) {
            const row = document.querySelector('.split-config-row[data-detail-id="' + detailId + '"]');
            if (!row) return;
            row.style.display = 'none';
            const saveBtn = document.querySelector('.btn-save-split[data-detail-id="' + detailId + '"]');
            if (saveBtn) saveBtn.classList.add('d-none');
            const tbody = row.querySelector('tbody.split-body');
            if (tbody) {
                tbody.dataset.saved = '1';
                Array.from(tbody.querySelectorAll('input.split-qty')).forEach(i => { i.value = '0'; });
            }
        }

        function showToast(message, variant) {
            const toastEl = document.getElementById('minvenToast');
            const toastBody = document.getElementById('minvenToastBody');
            if (!toastEl || !toastBody || typeof bootstrap === 'undefined' || !bootstrap.Toast) {
                alert(message);
                return;
            }
            const v = String(variant || 'success');
            toastEl.className = 'toast align-items-center border-0 text-bg-' + v;
            const closeBtn = toastEl.querySelector('button.btn-close');
            if (closeBtn) closeBtn.className = 'btn-close btn-close-white me-2 m-auto';
            toastBody.textContent = String(message || '');
            const t = bootstrap.Toast.getOrCreateInstance(toastEl, { delay: 2200 });
            t.show();
        }

        async function postSplit(payload) {
            const form = new FormData();
            Object.entries(payload).forEach(([k, v]) => {
                if (Array.isArray(v)) {
                    v.forEach(val => form.append(k + '[]', val));
                } else {
                    form.append(k, String(v));
                }
            });
            const resp = await fetch(window.location.href, { method: 'POST', body: form });
            const json = await resp.json();
            if (!resp.ok || !json || json.status !== 'success') {
                throw new Error((json && json.message) ? json.message : 'Gagal menyimpan');
            }
            return json;
        }

        document.addEventListener('click', async (e) => {
            const splitBtn = e.target.closest('.btn-split');
            if (splitBtn) {
                const detailId = String(splitBtn.dataset.detailId || '');
                if (!detailId) return;
                showSplit(detailId);
                return;
            }

            const sesuaiBtn = e.target.closest('.btn-sesuai');
            if (sesuaiBtn) {
                const detailId = String(sesuaiBtn.dataset.detailId || '');
                const poId = String(sesuaiBtn.dataset.poId || '');
                if (!detailId || !poId) return;
                try {
                    await postSplit({ action: 'save_split', mode: 'sesuai', po_id: poId, detail_id: detailId });
                    hideSplit(detailId);
                    showToast('Berhasil disimpan', 'success');
                } catch (err) {
                    showToast((err && err.message) ? err.message : 'Gagal menyimpan', 'danger');
                }
                return;
            }

            const addBtn = e.target.closest('.add-split');
            if (addBtn) {
                return;
            }

            const rmBtn = e.target.closest('.remove-split');
            if (rmBtn) {
                return;
            }

            const saveBtn = e.target.closest('.btn-save-split');
            if (saveBtn) {
                const detailId = String(saveBtn.dataset.detailId || '');
                const poId = String(saveBtn.dataset.poId || '');
                if (!detailId || !poId) return;
                const tbody = document.querySelector('.split-body[data-detail-id="' + detailId + '"]');
                if (!tbody) return;
                const ids = Array.from(tbody.querySelectorAll('.split-barang-id')).map(i => i.value);
                const qtys = Array.from(tbody.querySelectorAll('.split-qty')).map(i => i.value);
                try {
                    await postSplit({ action: 'save_split', mode: 'split', po_id: poId, detail_id: detailId, split_barang_id: ids, split_qty: qtys });
                    markSplitSaved(detailId);
                    showToast('Berhasil disimpan', 'success');
                } catch (err) {
                    showToast((err && err.message) ? err.message : 'Gagal menyimpan', 'danger');
                }
                return;
            }
        });

        document.addEventListener('input', (e) => {
            const qty = e.target.closest('.split-qty');
            if (!qty) return;
            const tbody = e.target.closest('tbody.split-body');
            const detailId = tbody && tbody.dataset ? String(tbody.dataset.detailId || '') : '';
            if (detailId) {
                markSplitUnsaved(detailId);
            }
        });

        document.addEventListener('submit', (e) => {
            const form = e.target;
            if (!form || !form.querySelector('button[name="submit_complete"]')) return;
            const bodies = Array.from(document.querySelectorAll('tbody.split-body'));
            const pending = bodies.filter(b => {
                const row = b.closest('.split-config-row');
                const visible = row && row.style.display !== 'none';
                return visible && String(b.dataset.saved || '0') !== '1';
            });
            if (pending.length > 0) {
                e.preventDefault();
                showToast('Masih ada split yang belum di-SAVE.', 'warning');
            }
        });
    </script>

    <div class="position-fixed bottom-0 end-0 p-3" style="z-index: 9999;">
        <div id="minvenToast" class="toast align-items-center border-0" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body" id="minvenToastBody"></div>
                <button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    </div>
</body>
</html>
