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

$table_exists = function($table) use ($conn) {
    $escaped_table = $conn->real_escape_string($table);
    $res = $conn->query("SHOW TABLES LIKE '{$escaped_table}'");
    if ($res instanceof mysqli_result) {
        $exists = $res->num_rows > 0;
        $res->free();
        return $exists;
    }
    return false;
};

$has_split_table = $table_exists('po_stock_split');
$has_split_barang_id = $has_split_table && db_has_column($conn, 'po_stock_split', 'split_barang_id');
$has_conversi_table = $table_exists('conversi_po_detail');

$status_col_exists = db_has_column($conn, 'detail_purchase_order', 'status');
$status_condition = $status_col_exists ? "AND (dpo.status IS NULL OR dpo.status != 'rejected')" : "";
$conversion_select = $has_conversi_table ? 'cpd.nilai_konversi' : 'NULL AS nilai_konversi';
$conversion_join = $has_conversi_table
    ? 'LEFT JOIN conversi_po_detail cpd ON dpo.id = cpd.detail_purchase_order_id'
    : '';
$conversion_satuan_select = $has_conversi_table ? 's1.nama_satuan AS satuan_asal' : 'NULL AS satuan_asal';
$conversion_satuan_join = $has_conversi_table
    ? 'LEFT JOIN satuan s1 ON cpd.satuan_asal_id = s1.id'
    : '';

$sql = "SELECT
            dpo.id AS detail_id,
            dpo.barang_id,
            dpo.jumlah,
            dpo.keterangan_detail,
            b.kode_barang,
            b.nama_barang,
            s.nama_satuan AS satuan_dasar,
            $conversion_satuan_select,
            $conversion_select
        FROM detail_purchase_order dpo
        LEFT JOIN barang b ON dpo.barang_id = b.id
        LEFT JOIN satuan s ON b.satuan_id = s.id
        $conversion_join
        $conversion_satuan_join
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
$split_barang_select = $has_split_barang_id
    ? 'ps.split_barang_id, b.kode_barang, b.nama_barang, s.nama_satuan AS satuan'
    : 'NULL AS split_barang_id, NULL AS kode_barang, NULL AS nama_barang, NULL AS satuan';
$split_barang_join = $has_split_barang_id
    ? 'LEFT JOIN barang b ON ps.split_barang_id = b.id
       LEFT JOIN satuan s ON b.satuan_id = s.id'
    : '';
if ($has_split_table) {
    $stmt_s = $conn->prepare("SELECT ps.id, ps.detail_purchase_order_id, $split_barang_select, ps.detail_barang, ps.qty_output
                              FROM po_stock_split ps
                              $split_barang_join
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
