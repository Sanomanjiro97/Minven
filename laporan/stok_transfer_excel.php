<?php
session_start();
require_once '../config.php';
require_once '../libs/SimpleXLSXGen.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

require_once '../includes/page_access_check.php';

$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$period = isset($_GET['period']) ? $_GET['period'] : 'daily';
$gudang_id = isset($_GET['gudang_id']) ? $_GET['gudang_id'] : '';

// Query untuk mengambil data transfer stok
$sql = "SELECT 
    tt.tanggal,
    b.kode_barang,
    b.nama_barang,
    s.nama_satuan,
    dtt.jumlah,
    dtt.keterangan,
    u.nama as created_by_name,
    g_asal.nama_gudang as gudang_asal,
    g_tujuan.nama_gudang as gudang_tujuan
FROM transaksi_transfer tt
JOIN detail_transaksi_transfer dtt ON tt.id = dtt.transaksi_transfer_id
JOIN barang b ON dtt.barang_id = b.id
LEFT JOIN satuan s ON b.satuan_id = s.id
LEFT JOIN users u ON tt.created_by = u.id
LEFT JOIN gudang g_asal ON tt.gudang_asal_id = g_asal.id
LEFT JOIN gudang g_tujuan ON tt.gudang_tujuan_id = g_tujuan.id
WHERE tt.tanggal BETWEEN ? AND ?
".($gudang_id ? "AND tt.gudang_asal_id = ?" : "")."
ORDER BY tt.tanggal DESC, b.kode_barang";

$stmt = $conn->prepare($sql);
if ($gudang_id) {
    $stmt->bind_param('ssi', $start_date, $end_date, $gudang_id);
} else {
    $stmt->bind_param('ss', $start_date, $end_date);
}
$stmt->execute();
$result = $stmt->get_result();

// Set header untuk download Excel
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="Laporan_Stok_Transfer_' . date('Ymd') . '.xls"');
header('Cache-Control: max-age=0');

// Get warehouse name if filter is applied
$gudang_name = '';
if ($gudang_id) {
    $gudang_sql = "SELECT nama_gudang FROM gudang WHERE id = ?";
    $gudang_stmt = $conn->prepare($gudang_sql);
    $gudang_stmt->bind_param('i', $gudang_id);
    $gudang_stmt->execute();
    $gudang_result = $gudang_stmt->get_result();
    $gudang_name = $gudang_result->fetch_assoc()['nama_gudang'];
}
?>
<table border="1" style="width: 100%; border-collapse: collapse;">
    <tr style="background-color: #f0f0f0;">
        <td colspan="10" style="text-align: center; font-weight: bold; font-size: 16px; padding: 10px;">
            LAPORAN STOK TRANSFER
        </td>
    </tr>
    <tr style="background-color: #f8f9fa;">
        <td colspan="10" style="text-align: center; padding: 8px;">
            Periode: <?= date('d/m/Y', strtotime($start_date)) ?> - <?= date('d/m/Y', strtotime($end_date)) ?>
            <?php if ($gudang_name): ?>
            <br>Gudang: <?= htmlspecialchars($gudang_name) ?>
            <?php endif; ?>
        </td>
    </tr>
    <tr></tr>
    <tr style="background-color: #e9ecef; font-weight: bold; text-align: center;">
        <td style="border: 1px solid #000; padding: 8px;">No</td>
        <td style="border: 1px solid #000; padding: 8px;">Tanggal</td>
        <td style="border: 1px solid #000; padding: 8px;">Kode Barang</td>
        <td style="border: 1px solid #000; padding: 8px;">Nama Barang</td>
        <td style="border: 1px solid #000; padding: 8px;">Gudang Asal</td>
        <td style="border: 1px solid #000; padding: 8px;">Gudang Tujuan</td>
        <td style="border: 1px solid #000; padding: 8px;">Jumlah Transfer</td>
        <td style="border: 1px solid #000; padding: 8px;">Satuan</td>
        <td style="border: 1px solid #000; padding: 8px;">Keterangan</td>
        <td style="border: 1px solid #000; padding: 8px;">Dibuat Oleh</td>
    </tr>
    <?php 
    $no = 1;
    $total_transfer = 0;
    mysqli_data_seek($result, 0);
    while($row = $result->fetch_assoc()): 
        $total_transfer += $row['jumlah'];
    ?>
    <tr>
        <td style="border: 1px solid #000; padding: 6px; text-align: center;"> <?= $no++ ?> </td>
        <td style="border: 1px solid #000; padding: 6px;"> <?= date('d/m/Y', strtotime($row['tanggal'])) ?> </td>
        <td style="border: 1px solid #000; padding: 6px;"> <?= htmlspecialchars($row['kode_barang']) ?> </td>
        <td style="border: 1px solid #000; padding: 6px;"> <?= htmlspecialchars($row['nama_barang']) ?> </td>
        <td style="border: 1px solid #000; padding: 6px;"> <?= htmlspecialchars($row['gudang_asal']) ?> </td>
        <td style="border: 1px solid #000; padding: 6px;"> <?= htmlspecialchars($row['gudang_tujuan']) ?> </td>
        <td style="border: 1px solid #000; padding: 6px; text-align: right;"> <?= number_format($row['jumlah']) ?> </td>
        <td style="border: 1px solid #000; padding: 6px;"> <?= htmlspecialchars($row['nama_satuan']) ?> </td>
        <td style="border: 1px solid #000; padding: 6px;"> <?= htmlspecialchars($row['keterangan']) ?> </td>
        <td style="border: 1px solid #000; padding: 6px;"> <?= htmlspecialchars($row['created_by_name']) ?> </td>
    </tr>
    <?php endwhile; ?>
    <tr style="font-weight: bold; background: #f8f9fa;">
        <td colspan="6" style="text-align: right; border: 1px solid #000;">Total Transfer:</td>
        <td style="text-align: right; border: 1px solid #000;"> <?= number_format($total_transfer) ?> </td>
        <td colspan="3" style="border: 1px solid #000;"></td>
    </tr>
</table>
<?php exit(); // pastikan tidak ada output lain setelah table ?>
