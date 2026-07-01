<?php
session_start();
require_once '../../config.php';
require_once '../../includes/access_check.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

if (!checkAccess('purchase_order', 'complete')) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Forbidden']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit();
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Payload tidak valid']);
    exit();
}

$po_id = isset($data['po_id']) ? (int)$data['po_id'] : 0;
$tanggal = isset($data['tanggal']) ? (string)$data['tanggal'] : '';
$gudang_1_id = isset($data['gudang_1_id']) ? (int)$data['gudang_1_id'] : 0;
$gudang_2_id = isset($data['gudang_2_id']) && $data['gudang_2_id'] !== null && $data['gudang_2_id'] !== '' ? (int)$data['gudang_2_id'] : null;
$items = isset($data['items']) && is_array($data['items']) ? $data['items'] : [];
$canceled_rows = isset($data['canceled_rows']) && is_array($data['canceled_rows']) ? $data['canceled_rows'] : [];

if ($po_id <= 0) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'ID PO tidak valid']);
    exit();
}

if ($tanggal === '' || !DateTime::createFromFormat('Y-m-d', $tanggal)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Tanggal tidak valid']);
    exit();
}

if ($gudang_1_id <= 0) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Gudang 1 wajib dipilih']);
    exit();
}

if ($gudang_2_id !== null && $gudang_2_id === $gudang_1_id) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Gudang 2 tidak boleh sama dengan Gudang 1']);
    exit();
}

if (count($items) === 0) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Item kosong']);
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

function generateTransactionNumber($conn, $prefix = 'SM') {
    $date = date('Ymd');
    $sql = "SELECT COUNT(*) as count FROM transaksi_stok WHERE DATE(created_at) = CURDATE() AND jenis_transaksi = 'masuk'";
    $result = $conn->query($sql);
    $row = $result ? $result->fetch_assoc() : null;
    $count = (int)($row['count'] ?? 0) + 1;
    $sequence = str_pad((string)$count, 4, '0', STR_PAD_LEFT);
    return $prefix . '-' . $date . $sequence;
}

function ensure_conversi_po_table($conn) {
    $res = $conn->query("SHOW TABLES LIKE 'conversi_po_detail'");
    if ($res instanceof mysqli_result) {
        $exists = $res->num_rows > 0;
        $res->free();
        if ($exists) return true;
    }
    $sql = "CREATE TABLE IF NOT EXISTS conversi_po_detail (
        id INT(11) NOT NULL AUTO_INCREMENT,
        purchase_order_id INT(11) NOT NULL,
        detail_purchase_order_id INT(11) NOT NULL,
        satuan_asal_id INT(11) DEFAULT NULL,
        satuan_tujuan_id INT(11) DEFAULT NULL,
        nilai_konversi DECIMAL(10,2) DEFAULT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT current_timestamp(),
        updated_at TIMESTAMP NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
        PRIMARY KEY (id),
        KEY idx_po (purchase_order_id),
        KEY idx_dpo (detail_purchase_order_id),
        KEY idx_satuan_asal (satuan_asal_id),
        KEY idx_satuan_tujuan (satuan_tujuan_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
    return $conn->query($sql) === true;
}

ensure_conversi_po_table($conn);

function parseRowId($raw) {
    if (is_int($raw)) {
        $raw = (string)$raw;
    }
    if (!is_string($raw)) {
        return null;
    }
    $raw = trim($raw);
    if ($raw === '') {
        return null;
    }
    if (preg_match('/^(d|s):(\d+)$/', $raw, $m)) {
        $type = (string)$m[1];
        $id = (int)$m[2];
        return ['type' => $type, 'id' => $id, 'key' => $type . ':' . (string)$id];
    }
    if (ctype_digit($raw)) {
        $id = (int)$raw;
        return ['type' => 'd', 'id' => $id, 'key' => 'd:' . (string)$id];
    }
    return null;
}

ensure_po_stock_split_table($conn);

try {
    $conn->begin_transaction();

    $stmt_po = $conn->prepare("SELECT id, no_po, status FROM purchase_order WHERE id = ? FOR UPDATE");
    if (!$stmt_po) {
        throw new Exception('Database error');
    }
    $stmt_po->bind_param('i', $po_id);
    $stmt_po->execute();
    $po_res = $stmt_po->get_result();
    $po = $po_res ? $po_res->fetch_assoc() : null;
    $stmt_po->close();

    if (!$po) {
        throw new Exception('PO tidak ditemukan');
    }

    if ($po['status'] !== 'completed') {
        throw new Exception('PO harus berstatus completed');
    }

    $gudang_ids = [$gudang_1_id];
    if ($gudang_2_id !== null) {
        $gudang_ids[] = $gudang_2_id;
    }
    $stmt_g = $conn->prepare("SELECT id FROM gudang WHERE id = ?");
    if (!$stmt_g) {
        throw new Exception('Database error');
    }
    foreach ($gudang_ids as $gid) {
        $gid = (int)$gid;
        $stmt_g->bind_param('i', $gid);
        $stmt_g->execute();
        $g_res = $stmt_g->get_result();
        if (!$g_res || $g_res->num_rows === 0) {
            $stmt_g->close();
            throw new Exception('Gudang tidak valid');
        }
    }
    $stmt_g->close();

    $status_col_exists = db_has_column($conn, 'detail_purchase_order', 'status');
    $status_condition = $status_col_exists ? "AND (dpo.status IS NULL OR dpo.status != 'rejected')" : "";

    $sql_details = "SELECT dpo.id, dpo.barang_id, dpo.jumlah, dpo.keterangan_detail, cpd.nilai_konversi
                    FROM detail_purchase_order dpo
                    LEFT JOIN conversi_po_detail cpd ON dpo.id = cpd.detail_purchase_order_id
                    WHERE dpo.purchase_order_id = ?
                      $status_condition
                    FOR UPDATE";
    $stmt_d = $conn->prepare($sql_details);
    if (!$stmt_d) {
        throw new Exception('Database error');
    }
    $stmt_d->bind_param('i', $po_id);
    $stmt_d->execute();
    $d_res = $stmt_d->get_result();
    $details = [];
    if ($d_res) {
        while ($r = $d_res->fetch_assoc()) {
            $jumlah_asal = (float)$r['jumlah'];
             $nilai_konversi = isset($r['nilai_konversi']) ? (float)$r['nilai_konversi'] : 1.0;
             $jumlah_tujuan = round($jumlah_asal * $nilai_konversi);

             $details[(int)$r['id']] = [
                 'detail_id' => (int)$r['id'],
                 'barang_id' => (int)$r['barang_id'],
                 'jumlah' => (int)$jumlah_tujuan,
                 'detail_barang' => (string)($r['keterangan_detail'] ?? '')
             ];
        }
    }
    $stmt_d->close();

    if (count($details) === 0) {
        throw new Exception('Item PO kosong');
    }

    $resSplit = $conn->query("SHOW TABLES LIKE 'po_stock_split'");
    $hasSplitTable = ($resSplit instanceof mysqli_result) && $resSplit->num_rows > 0;
    if ($resSplit instanceof mysqli_result) {
        $resSplit->free();
    }

    $splitsByDetail = [];
    if ($hasSplitTable) {
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
    }

    $allowedRows = [];
    foreach ($details as $detail_id => $d) {
        $detailId = (int)$detail_id;
        $hasSplit = isset($splitsByDetail[$detailId]) && count($splitsByDetail[$detailId]) > 0;
        if ($hasSplit) {
            foreach ($splitsByDetail[$detailId] as $s) {
                $rowKey = 's:' . (string)$s['id'];
                $split_barang_id = (int)($s['split_barang_id'] ?? 0);
                $allowedRows[$rowKey] = [
                    'qty' => (int)$s['qty_output'],
                    'barang_id' => $split_barang_id > 0 ? $split_barang_id : (int)$d['barang_id'],
                    'detail_barang' => (string)$s['detail_barang'],
                ];
            }
        } else {
            $rowKey = 'd:' . (string)$detailId;
            $allowedRows[$rowKey] = [
                'qty' => (int)$d['jumlah'],
                'barang_id' => (int)$d['barang_id'],
                'detail_barang' => (string)$d['detail_barang'],
            ];
        }
    }

    $reqByRow = [];
    foreach ($items as $it) {
        if (!is_array($it)) {
            continue;
        }
        $parsed = parseRowId($it['detail_id'] ?? null);
        if (!$parsed) {
            continue;
        }
        $rowKey = (string)$parsed['key'];
        $q1 = isset($it['qty_g1']) ? (int)$it['qty_g1'] : 0;
        $q2 = isset($it['qty_g2']) ? (int)$it['qty_g2'] : 0;
        if ($q1 < 0 || $q2 < 0) {
            throw new Exception('Qty tidak boleh negatif');
        }
        if (!isset($reqByRow[$rowKey])) {
            $reqByRow[$rowKey] = ['qty_g1' => 0, 'qty_g2' => 0];
        }
        $reqByRow[$rowKey]['qty_g1'] += $q1;
        $reqByRow[$rowKey]['qty_g2'] += $q2;
    }

    foreach ($reqByRow as $rowKey => $_) {
        if (!isset($allowedRows[$rowKey])) {
            throw new Exception('Item tidak valid untuk PO ini');
        }
    }

    $canceledSet = [];
    foreach ($canceled_rows as $rawKey) {
        $p = parseRowId($rawKey);
        if (!$p) {
            continue;
        }
        $key = (string)$p['key'];
        if (!isset($allowedRows[$key])) {
            continue;
        }
        $canceledSet[$key] = true;
    }

    foreach ($allowedRows as $rowKey => $row) {
        if (!isset($reqByRow[$rowKey])) {
            throw new Exception('Pembagian qty belum lengkap');
        }
        $q1 = (int)$reqByRow[$rowKey]['qty_g1'];
        $q2 = (int)$reqByRow[$rowKey]['qty_g2'];
        if (isset($canceledSet[$rowKey])) {
            if (($q1 + $q2) !== 0) {
                throw new Exception('Item dibatalkan harus qty 0');
            }
        } elseif (($q1 + $q2) !== (int)$row['qty']) {
            throw new Exception('Pembagian qty harus sama dengan qty item');
        }
        if ($gudang_2_id === null && $q2 > 0) {
            throw new Exception('Gudang 2 belum dipilih tetapi ada Qty Gudang 2');
        }
    }

    $items_by_gudang = [
        $gudang_1_id => [],
    ];
    if ($gudang_2_id !== null) {
        $items_by_gudang[$gudang_2_id] = [];
    }

    foreach ($allowedRows as $rowKey => $row) {
        $q1 = (int)$reqByRow[$rowKey]['qty_g1'];
        $q2 = (int)$reqByRow[$rowKey]['qty_g2'];
        $barang_id = (int)$row['barang_id'];
        $detail_barang = (string)$row['detail_barang'];

        if ($q1 > 0) {
            $key = $barang_id . '|' . $detail_barang;
            if (!isset($items_by_gudang[$gudang_1_id][$key])) {
                $items_by_gudang[$gudang_1_id][$key] = ['barang_id' => $barang_id, 'detail_barang' => $detail_barang, 'jumlah' => 0];
            }
            $items_by_gudang[$gudang_1_id][$key]['jumlah'] += $q1;
        }
        if ($gudang_2_id !== null && $q2 > 0) {
            $key = $barang_id . '|' . $detail_barang;
            if (!isset($items_by_gudang[$gudang_2_id][$key])) {
                $items_by_gudang[$gudang_2_id][$key] = ['barang_id' => $barang_id, 'detail_barang' => $detail_barang, 'jumlah' => 0];
            }
            $items_by_gudang[$gudang_2_id][$key]['jumlah'] += $q2;
        }
    }

    $sql_header = "INSERT INTO transaksi_stok (no_transaksi, tanggal, gudang_id, jenis_transaksi, keterangan, created_by)
                   VALUES (?, ?, ?, 'masuk', ?, ?)";
    $stmt_header = $conn->prepare($sql_header);
    if (!$stmt_header) {
        throw new Exception('Database error');
    }

    $sql_detail = "INSERT INTO detail_transaksi_stok (transaksi_stok_id, barang_id, detail_barang, jumlah)
                   VALUES (?, ?, ?, ?)";
    $stmt_detail = $conn->prepare($sql_detail);
    if (!$stmt_detail) {
        throw new Exception('Database error');
    }

    $sql_stok_check = "SELECT id FROM gudang_stok WHERE gudang_id = ? AND barang_id = ? AND detail_barang = ?";
    $stmt_stok_check = $conn->prepare($sql_stok_check);
    if (!$stmt_stok_check) {
        throw new Exception('Database error');
    }

    $sql_stok_update = "UPDATE gudang_stok
                        SET jumlah = jumlah + ?,
                            stok_awal = stok_awal + ?
                        WHERE gudang_id = ? AND barang_id = ? AND detail_barang = ?";
    $stmt_stok_update = $conn->prepare($sql_stok_update);
    if (!$stmt_stok_update) {
        throw new Exception('Database error');
    }

    $sql_stok_insert = "INSERT INTO gudang_stok (gudang_id, barang_id, detail_barang, jumlah, stok_awal)
                        VALUES (?, ?, ?, ?, ?)";
    $stmt_stok_insert = $conn->prepare($sql_stok_insert);
    if (!$stmt_stok_insert) {
        throw new Exception('Database error');
    }

    $created_transactions = [];
    $total_sent = 0;

    foreach ($items_by_gudang as $gid => $group) {
        $group_items = array_values($group);
        $group_items = array_filter($group_items, fn($x) => (int)$x['jumlah'] > 0);
        if (count($group_items) === 0) {
            continue;
        }

        $no_transaksi = generateTransactionNumber($conn, 'SM');
        $keterangan = "PO " . (string)$po['no_po'];
        $created_by = (int)$_SESSION['user_id'];

        $stmt_header->bind_param('ssisi', $no_transaksi, $tanggal, $gid, $keterangan, $created_by);
        if (!$stmt_header->execute()) {
            throw new Exception('Gagal membuat transaksi stok');
        }
        $transaksi_stok_id = (int)$conn->insert_id;

        foreach ($group_items as $it) {
            $barang_id = (int)$it['barang_id'];
            $detail_barang = (string)($it['detail_barang'] ?? '');
            $jumlah = (int)$it['jumlah'];

            $stmt_detail->bind_param('iisi', $transaksi_stok_id, $barang_id, $detail_barang, $jumlah);
            if (!$stmt_detail->execute()) {
                throw new Exception('Gagal menyimpan detail stok');
            }

            $stmt_stok_check->bind_param('iis', $gid, $barang_id, $detail_barang);
            if (!$stmt_stok_check->execute()) {
                throw new Exception('Gagal cek stok gudang');
            }
            $stok_res = $stmt_stok_check->get_result();
            if ($stok_res && $stok_res->num_rows > 0) {
                $stmt_stok_update->bind_param('iiiis', $jumlah, $jumlah, $gid, $barang_id, $detail_barang);
                if (!$stmt_stok_update->execute()) {
                    throw new Exception('Gagal update stok gudang');
                }
            } else {
                $stmt_stok_insert->bind_param('iisii', $gid, $barang_id, $detail_barang, $jumlah, $jumlah);
                if (!$stmt_stok_insert->execute()) {
                    throw new Exception('Gagal insert stok gudang');
                }
            }

            $total_sent += $jumlah;
        }

        $created_transactions[] = $no_transaksi;
    }

    if (count($created_transactions) === 0) {
        throw new Exception('Tidak ada qty yang dikirim');
    }

    $updates = [];
    $bind_types = '';
    $bind_values = [];

    $updates[] = "status = 'dikirim'";
    if (db_has_column($conn, 'purchase_order', 'delivered_at')) {
        $updates[] = "delivered_at = NOW()";
    }
    if (db_has_column($conn, 'purchase_order', 'delivered_by')) {
        $updates[] = "delivered_by = ?";
        $bind_types .= 'i';
        $bind_values[] = (int)$_SESSION['user_id'];
    }
    if (db_has_column($conn, 'purchase_order', 'updated_at')) {
        $updates[] = "updated_at = NOW()";
    }

    $sql_upd_po = "UPDATE purchase_order SET " . implode(', ', $updates) . " WHERE id = ? AND status = 'completed'";
    $stmt_upd = $conn->prepare($sql_upd_po);
    if (!$stmt_upd) {
        throw new Exception('Database error');
    }
    $bind_types .= 'i';
    $bind_values[] = $po_id;

    $stmt_upd->bind_param($bind_types, ...$bind_values);
    if (!$stmt_upd->execute()) {
        throw new Exception('Gagal update status PO');
    }
    if ($stmt_upd->affected_rows === 0) {
        throw new Exception('Status PO berubah, silakan refresh');
    }
    $stmt_upd->close();

    $stmt_header->close();
    $stmt_detail->close();
    $stmt_stok_check->close();
    $stmt_stok_update->close();
    $stmt_stok_insert->close();

    $conn->commit();

    $txLabel = implode(', ', $created_transactions);
    echo json_encode([
        'status' => 'success',
        'message' => "Berhasil kirim stok ke gudang. No Transaksi: " . $txLabel,
        'no_transaksi' => $created_transactions,
        'total_qty' => $total_sent
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit();
} catch (Exception $e) {
    $conn->rollback();
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit();
}
