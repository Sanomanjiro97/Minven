<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

require_once '../includes/page_access_check.php';

$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$period = isset($_GET['period']) ? $_GET['period'] : 'daily';

// Adjust dates based on period
if ($period == 'weekly') {
    $start_date = date('Y-m-d', strtotime('monday this week'));
    $end_date = date('Y-m-d', strtotime('sunday this week'));
} elseif ($period == 'monthly') {
    $start_date = date('Y-m-01');
    $end_date = date('Y-m-t');
}

// Query untuk mengambil data pembelian direct yang dikelompokkan berdasarkan barang
$sql = "SELECT 
            COALESCE(b.nama_barang, dp.keterangan) as nama_barang,
            p.tanggal,
            ? as start_date,
            SUM(dp.jumlah) as total_item,
            SUM(dp.jumlah * dp.harga_satuan) as total_harga,
            GROUP_CONCAT(DISTINCT p.nama_toko) as nama_toko_list,
            GROUP_CONCAT(DISTINCT p.keterangan) as keterangan_list,
            GROUP_CONCAT(DISTINCT u.nama) as created_by_names
        FROM direct_purchase p
        LEFT JOIN detail_direct_purchase dp ON dp.direct_purchase_id = p.id AND dp.barang_id IS NOT NULL
        LEFT JOIN barang b ON dp.barang_id = b.id
        LEFT JOIN users u ON p.created_by = u.id
        WHERE p.tanggal BETWEEN ? AND ?
            AND p.status = 'stok_masuk'
        GROUP BY COALESCE(b.nama_barang, dp.keterangan), p.tanggal
        ORDER BY p.tanggal DESC, COALESCE(b.nama_barang, dp.keterangan)";

$stmt = $conn->prepare($sql);
if ($stmt === false) {
    die("Error preparing query: " . $conn->error);
}
$stmt->bind_param('sss', $start_date, $start_date, $end_date);
$stmt->execute();
$result = $stmt->get_result();

// Set header untuk download XLS
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="Laporan_Pembelian_Direct_' . date('Y-m-d_H-i-s') . '.xls"');
header('Cache-Control: max-age=0');
?>
<table border="1" style="width: 100%; border-collapse: collapse;">
    <tr style="background-color: #f0f0f0;">
        <td colspan="9" style="text-align: center; font-weight: bold; font-size: 16px; padding: 10px;">
            LAPORAN PEMBELIAN DIRECT
        </td>
    </tr>
    <tr style="background-color: #f8f9fa;">
        <td colspan="9" style="text-align: center; padding: 8px;">
            Periode: <?= date('d/m/Y', strtotime($start_date)) ?> - <?= date('d/m/Y', strtotime($end_date)) ?>
        </td>
    </tr>
    <tr></tr>
    <tr style="background-color: #e9ecef; font-weight: bold; text-align: center;">
        <td style="border: 1px solid #000; padding: 8px;">No</td>
        <td style="border: 1px solid #000; padding: 8px;">Tanggal</td>
        <td style="border: 1px solid #000; padding: 8px;">Start Date</td>
        <td style="border: 1px solid #000; padding: 8px;">Nama Barang</td>
        <td style="border: 1px solid #000; padding: 8px;">Total Item</td>
        <td style="border: 1px solid #000; padding: 8px;">Total Harga</td>
        <td style="border: 1px solid #000; padding: 8px;">Nama Toko</td>
        <td style="border: 1px solid #000; padding: 8px;">Keterangan</td>
        <td style="border: 1px solid #000; padding: 8px;">Created by</td>
    </tr>
    <?php 
    $no = 1;
    $total_item = 0;
    $total_harga = 0;
    while($row = $result->fetch_assoc()): 
        $total_item += $row['total_item'];
        $total_harga += $row['total_harga'];
    ?>
    <tr>
        <td style="border: 1px solid #000; padding: 6px; text-align: center;"><?= $no++ ?></td>
        <td style="border: 1px solid #000; padding: 6px;"><?= date('d/m/Y', strtotime($row['tanggal'])) ?></td>
        <td style="border: 1px solid #000; padding: 6px;"><?= date('d/m/Y', strtotime($row['start_date'])) ?></td>
        <td style="border: 1px solid #000; padding: 6px;"><?= htmlspecialchars($row['nama_barang']) ?></td>
        <td style="border: 1px solid #000; padding: 6px; text-align: right;"><?= number_format($row['total_item'], 0, ',', '.') ?></td>
        <td style="border: 1px solid #000; padding: 6px; text-align: right;"><?= number_format($row['total_harga'], 0, ',', '.') ?></td>
        <td style="border: 1px solid #000; padding: 6px;"><?= htmlspecialchars($row['nama_toko_list']) ?></td>
        <td style="border: 1px solid #000; padding: 6px;"><?= htmlspecialchars($row['keterangan_list']) ?></td>
        <td style="border: 1px solid #000; padding: 6px;"><?= htmlspecialchars($row['created_by_names']) ?></td>
    </tr>
    <?php endwhile; ?>
    <tr style="background-color: #f8f9fa; font-weight: bold;">
        <td colspan="4" style="border: 1px solid #000; padding: 6px; text-align: right;">Total:</td>
        <td style="border: 1px solid #000; padding: 6px; text-align: right;"><?= number_format($total_item, 0, ',', '.') ?></td>
        <td style="border: 1px solid #000; padding: 6px; text-align: right;"><?= number_format($total_harga, 0, ',', '.') ?></td>
        <td colspan="3" style="border: 1px solid #000; padding: 6px;"></td>
    </tr>
</table>
