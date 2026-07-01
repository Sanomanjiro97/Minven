<?php
ob_start();
session_start();
require_once '../config.php';
require_once '../includes/access_check.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

$gudang_id = isset($_GET['gudang_id']) ? (int)$_GET['gudang_id'] : 0;
if ($gudang_id <= 0) {
    http_response_code(400);
    echo "Parameter gudang_id tidak valid";
    exit();
}

$menu_map = [13 => 'gudang_antapani', 23 => 'gudang_central', 28 => 'gudang', 29 => 'gudang_02', 30 => 'gudang_JWDGO'];
$menu_key = $menu_map[$gudang_id] ?? null;
if ($menu_key && function_exists('checkAccess') && !checkAccess($menu_key, 'view')) {
    header('Location: ../unauthorized.php');
    exit();
}

$g = $conn->prepare("SELECT nama_gudang, kode_gudang FROM gudang WHERE id = ?");
$g->bind_param("i", $gudang_id);
$g->execute();
$ginfo = $g->get_result()->fetch_assoc();
$nama_gudang = $ginfo['nama_gudang'] ?? ('Gudang ID ' . $gudang_id);
$kode_gudang = $ginfo['kode_gudang'] ?? (string)$gudang_id;

$sql = "SELECT
    gs.id,
    b.kode_barang,
    b.nama_barang,
    k.nama_kategori,
    s.nama_satuan,
    COALESCE(GROUP_CONCAT(DISTINCT lm.nama_lokasi SEPARATOR ', '), '') AS nama_lokasi,
    gs.stok_awal,
    gs.stok_terpakai,
    (gs.stok_awal - gs.stok_terpakai) AS stok_akhir,
    gs.stok_minimum,
    gs.expire_date,
    gs.last_reset,
    gs.updated_at,
    u.nama AS updated_by
FROM gudang_stok gs
LEFT JOIN barang b ON gs.barang_id = b.id
LEFT JOIN kategori k ON b.kategori_id = k.id
LEFT JOIN satuan s ON b.satuan_id = s.id
LEFT JOIN users u ON gs.modified_by = u.id
LEFT JOIN item_mapping im ON im.barang_id = gs.barang_id AND im.aktif = 1
LEFT JOIN lokasi_mapping lm ON lm.id = im.lokasi_id AND lm.aktif = 1
WHERE gs.gudang_id = ?
GROUP BY gs.id
ORDER BY b.nama_barang, b.kode_barang";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $gudang_id);
$stmt->execute();
$result = $stmt->get_result();

if (ob_get_level()) ob_end_clean();
header('Content-Type: application/vnd.ms-excel');
$fname = 'Export_Stok_' . preg_replace('/[^A-Za-z0-9_\-]/', '_', $kode_gudang . '_' . $nama_gudang) . '_' . date('Ymd_His') . '.xls';
header('Content-Disposition: attachment; filename="'.$fname.'"');
header('Pragma: no-cache');
header('Expires: 0');

echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
echo '<head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"><meta name="ProgId" content="Excel.Sheet"><meta name="Generator" content="Microsoft Excel 11"><style>table{border-collapse:collapse}th,td{border:1px solid #000;padding:5px}th{background:#f0f0f0;font-weight:bold}</style></head><body>';
echo '<table>';
echo '<tr><td colspan="14" style="text-align:center;font-weight:bold;font-size:16px;padding:10px;">EXPORT STOK '.htmlspecialchars($nama_gudang).'</td></tr>';
echo '<tr><td colspan="14" style="text-align:center;padding:6px;">Tanggal Cetak: '.date('d/m/Y H:i:s').'</td></tr><tr></tr>';
echo '<tr style="background:#e9ecef;font-weight:bold;text-align:center"><th>No</th><th>Kode Barang</th><th>Nama Barang</th><th>Kategori</th><th>Satuan</th><th>Lokasi</th><th>Stok Awal</th><th>Terpakai</th><th>Stok Akhir</th><th>Par</th><th>Expire</th><th>Last Reset</th><th>Update Terakhir</th><th>Updated By</th></tr>';

$no = 1;
while ($row = $result->fetch_assoc()) {
    $expire = ($row['expire_date'] && $row['expire_date'] != '0000-00-00') ? date('Y-m-d', strtotime($row['expire_date'])) : '-';
    $last_reset = ($row['last_reset'] && $row['last_reset'] != '0000-00-00 00:00:00') ? date('Y-m-d H:i', strtotime($row['last_reset'])) : '-';
    $updated_at = $row['updated_at'] ? date('Y-m-d H:i', strtotime($row['updated_at'])) : '-';
    echo '<tr>';
    echo '<td>'.$no++.'</td>';
    echo '<td>'.htmlspecialchars($row['kode_barang'] ?? '').'</td>';
    echo '<td>'.htmlspecialchars($row['nama_barang'] ?? '').'</td>';
    echo '<td>'.htmlspecialchars($row['nama_kategori'] ?? '').'</td>';
    echo '<td>'.htmlspecialchars($row['nama_satuan'] ?? '').'</td>';
    echo '<td>'.htmlspecialchars($row['nama_lokasi'] ?? '').'</td>';
    echo '<td style="text-align:right;">'.(float)($row['stok_awal'] ?? 0).'</td>';
    echo '<td style="text-align:right;">'.(float)($row['stok_terpakai'] ?? 0).'</td>';
    echo '<td style="text-align:right;font-weight:bold;">'.(float)($row['stok_akhir'] ?? 0).'</td>';
    echo '<td style="text-align:right;">'.(float)($row['stok_minimum'] ?? 0).'</td>';
    echo '<td>'.htmlspecialchars($expire).'</td>';
    echo '<td>'.htmlspecialchars($last_reset).'</td>';
    echo '<td>'.htmlspecialchars($updated_at).'</td>';
    echo '<td>'.htmlspecialchars($row['updated_by'] ?? 'N/A').'</td>';
    echo '</tr>';
}
echo '</table></body></html>';
$conn->close();
exit();