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

if (!checkAccess('purchase_order', 'view')) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Forbidden']);
    exit();
}

$po_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($po_id <= 0) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'ID PO tidak valid']);
    exit();
}

$stmt_po = $conn->prepare("SELECT id, no_po, status FROM purchase_order WHERE id = ?");
if (!$stmt_po) {
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $conn->error]);
    exit();
}
$stmt_po->bind_param('i', $po_id);
$stmt_po->execute();
$po_res = $stmt_po->get_result();
$po = $po_res ? $po_res->fetch_assoc() : null;
$stmt_po->close();

if (!$po) {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'message' => 'PO tidak ditemukan']);
    exit();
}

if ($po['status'] !== 'completed') {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'PO tidak dalam status completed']);
    exit();
}

$ensure_split_table = function() use ($conn) {
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
};

$ensure_conversi_table = function() use ($conn) {
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
};

$ensure_conversi_table();

$status_col_exists = db_has_column($conn, 'detail_purchase_order', 'status');
$status_condition = $status_col_exists ? "AND (dpo.status IS NULL OR dpo.status != 'rejected')" : "";

$sql = "SELECT
            dpo.id AS detail_id,
            dpo.barang_id,
            dpo.jumlah,
            dpo.keterangan_detail,
            b.kode_barang,
            b.nama_barang,
            s.nama_satuan AS satuan_dasar,
            s1.nama_satuan AS satuan_asal,
            cpd.nilai_konversi
        FROM detail_purchase_order dpo
        LEFT JOIN barang b ON dpo.barang_id = b.id
        LEFT JOIN satuan s ON b.satuan_id = s.id
        LEFT JOIN conversi_po_detail cpd ON dpo.id = cpd.detail_purchase_order_id
        LEFT JOIN satuan s1 ON cpd.satuan_asal_id = s1.id
        WHERE dpo.purchase_order_id = ?
          $status_condition
        ORDER BY b.kode_barang, dpo.id";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $conn->error, 'sql' => $sql]);
    exit();
}
$stmt->bind_param('i', $po_id);
$stmt->execute();
$res = $stmt->get_result();

$details = [];
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $jumlah_po = (float)$row['jumlah'];
        $nilai_konversi = isset($row['nilai_konversi']) ? (float)$row['nilai_konversi'] : 1.0;
        $jumlah_final = round($jumlah_po * $nilai_konversi);
        
        $details[] = [
            'detail_id' => (int)$row['detail_id'],
            'barang_id' => (int)$row['barang_id'],
            'kode_barang' => (string)($row['kode_barang'] ?? ''),
            'nama_barang' => (string)($row['nama_barang'] ?? ''),
            'satuan' => (string)($row['satuan_dasar'] ?? ''),
            'jumlah' => (int)$jumlah_final,
            'keterangan_detail' => (string)($row['keterangan_detail'] ?? '')
        ];
    }
}
$stmt->close();

$splitsByDetail = [];
if ($ensure_split_table()) {
    $stmt_s = $conn->prepare("SELECT ps.id, ps.detail_purchase_order_id, ps.split_barang_id, ps.detail_barang, ps.qty_output,
                                     b.kode_barang, b.nama_barang, s.nama_satuan AS satuan
                              FROM po_stock_split ps
                              LEFT JOIN barang b ON ps.split_barang_id = b.id
                              LEFT JOIN satuan s ON b.satuan_id = s.id
                              WHERE ps.purchase_order_id = ?
                              ORDER BY ps.detail_purchase_order_id, ps.id");
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
                    'kode_barang' => (string)($s['kode_barang'] ?? ''),
                    'nama_barang' => (string)($s['nama_barang'] ?? ''),
                    'satuan' => (string)($s['satuan'] ?? ''),
                    'detail_barang' => (string)($s['detail_barang'] ?? ''),
                    'qty_output' => (int)($s['qty_output'] ?? 0),
                ];
            }
        }
        $stmt_s->close();
    }
}

$items = [];
foreach ($details as $d) {
    $detailId = (int)$d['detail_id'];
    $hasSplit = isset($splitsByDetail[$detailId]) && count($splitsByDetail[$detailId]) > 0;
    if ($hasSplit) {
        foreach ($splitsByDetail[$detailId] as $s) {
            $splitBarangId = (int)($s['split_barang_id'] ?? 0);
            $isSplitBarang = $splitBarangId > 0;
            $splitKode = (string)($s['kode_barang'] ?? '');
            $splitNama = (string)($s['nama_barang'] ?? '');
            $splitSatuan = (string)($s['satuan'] ?? '');
            $useSplitInfo = $isSplitBarang && ($splitKode !== '' || $splitNama !== '' || $splitSatuan !== '');
            $items[] = [
                'detail_id' => 's:' . (string)$s['id'],
                'row_type' => 'split',
                'parent_detail_id' => (int)$detailId,
                'barang_id' => $isSplitBarang ? $splitBarangId : (int)$d['barang_id'],
                'kode_barang' => $useSplitInfo ? $splitKode : (string)$d['kode_barang'],
                'nama_barang' => $useSplitInfo ? $splitNama : (string)$d['nama_barang'],
                'satuan' => $useSplitInfo ? $splitSatuan : (string)$d['satuan'],
                'jumlah' => (int)$s['qty_output'],
                'qty_po_asal' => (int)$d['jumlah'],
                'keterangan_detail' => (string)$s['detail_barang'],
                'keterangan_detail_asal' => (string)$d['keterangan_detail'],
                'parent_kode_barang' => (string)$d['kode_barang'],
                'parent_nama_barang' => (string)$d['nama_barang']
            ];
        }
        continue;
    }

    $items[] = [
        'detail_id' => 'd:' . (string)$detailId,
        'row_type' => 'detail',
        'barang_id' => (int)$d['barang_id'],
        'kode_barang' => (string)$d['kode_barang'],
        'nama_barang' => (string)$d['nama_barang'],
        'satuan' => (string)$d['satuan'],
        'jumlah' => (int)$d['jumlah'],
        'keterangan_detail' => (string)$d['keterangan_detail']
    ];
}

echo json_encode([
    'status' => 'success',
    'po' => [
        'id' => (int)$po['id'],
        'no_po' => (string)$po['no_po']
    ],
    'items' => $items
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
