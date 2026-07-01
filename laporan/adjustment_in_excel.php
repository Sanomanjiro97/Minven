<?php
ob_start();
session_start();
require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

require_once '../includes/page_access_check.php';
require_once '../includes/template_override_helper.php';
applyUploadedTemplateOverride($conn, 'laporan');


$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$gudang_id = isset($_GET['gudang_id']) ? $_GET['gudang_id'] : '';

$sql = "SELECT
            ts.tanggal,
            b.kode_barang,
            b.nama_barang,
            s.nama_satuan,
            dts.jumlah,
            ts.keterangan,
            ts.no_transaksi,
            g.nama_gudang,
            u.username as created_by_username
        FROM detail_transaksi_stok dts
        JOIN transaksi_stok ts ON dts.transaksi_stok_id = ts.id
        LEFT JOIN barang b ON dts.barang_id = b.id
        LEFT JOIN satuan s ON b.satuan_id = s.id
        JOIN gudang g ON ts.gudang_id = g.id
        LEFT JOIN users u ON ts.created_by = u.id
        WHERE ts.jenis_transaksi = 'adjustment_in'
        AND ts.tanggal BETWEEN ? AND ?";

$param_types = 'ss';
$param_values = [$start_date, $end_date];

if (!empty($gudang_id)) {
    $sql .= " AND ts.gudang_id = ?";
    $param_types .= 'i';
    $param_values[] = $gudang_id;
}

$sql .= " ORDER BY ts.tanggal DESC, ts.no_transaksi DESC";

$stmt = $conn->prepare($sql);
if ($stmt === false) {
    die("Error preparing query: " . $conn->error);
}

if (!empty($param_values)) {
    mysqli_stmt_bind_param($stmt, $param_types, ...$param_values);
}

$stmt->execute();
$result = $stmt->get_result();

if (ob_get_level()) {
    ob_end_clean();
}

header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="Laporan Adjustment In ' . date('Y-m-d') . '.xls"');
header('Pragma: no-cache');
header('Expires: 0');

echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
echo '<head>';
echo '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">';
echo '<meta name="ProgId" content="Excel.Sheet">';
echo '<meta name="Generator" content="Microsoft Excel 11">';
echo '<style>';
echo 'table { border-collapse: collapse; }';
echo 'th, td { border: 1px solid #000; padding: 5px; text-align: left; }';
echo 'th { background-color: #28a745; color: white; font-weight: bold; }';
echo '</style>';
echo '</head>';
echo '<body>';
echo '<table>';

echo '<tr>';
echo '<th>No</th>';
echo '<th>Tanggal</th>';
echo '<th>No Transaksi</th>';
echo '<th>Kode Barang</th>';
echo '<th>Nama Barang</th>';
echo '<th>Jumlah</th>';
echo '<th>Gudang</th>';
echo '<th>Keterangan</th>';
echo '<th>User</th>';
echo '</tr>';

$no = 1;
$total_jumlah = 0;

while($row = $result->fetch_assoc()) {
    $total_jumlah += $row['jumlah'];

    echo '<tr>';
    echo '<td>' . $no++ . '</td>';
    echo '<td>' . (!empty($row['tanggal']) ? date('d/m/Y', strtotime($row['tanggal'])) : '') . '</td>';
    echo '<td>' . htmlspecialchars($row['no_transaksi'] ?? '') . '</td>';
    echo '<td>' . htmlspecialchars($row['kode_barang'] ?? '') . '</td>';
    echo '<td>' . htmlspecialchars($row['nama_barang'] ?? '') . '</td>';
    echo '<td>' . number_format($row['jumlah'] ?? 0) . '</td>';
    echo '<td>' . htmlspecialchars($row['nama_gudang'] ?? '') . '</td>';
    echo '<td>' . htmlspecialchars($row['keterangan'] ?? '-') . '</td>';
    echo '<td>' . htmlspecialchars($row['created_by_username'] ?? '') . '</td>';
    echo '</tr>';
}

echo '<tr style="font-weight: bold; background-color: #d4edda;">';
echo '<td colspan="5">Total Adjustment In:</td>';
echo '<td>' . number_format($total_jumlah) . '</td>';
echo '<td colspan="3"></td>';
echo '</tr>';

echo '</table>';
echo '</body>';
echo '</html>';

exit();
?>
