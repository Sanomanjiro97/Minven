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

$sql = "SELECT 
            po.id,
            po.tanggal,
            po.status,
            s.nama_supplier,
            GROUP_CONCAT(DISTINCT CASE WHEN (dpo.status IS NULL OR dpo.status != 'rejected') THEN b.nama_barang END ORDER BY b.nama_barang SEPARATOR ', ') AS nama_barang,
            SUM(CASE WHEN (dpo.status IS NULL OR dpo.status != 'rejected') THEN dpo.jumlah ELSE 0 END) AS total_item,
            u.nama AS created_by_name,
            po.keterangan
        FROM purchase_order po
        LEFT JOIN detail_purchase_order dpo ON po.id = dpo.purchase_order_id
        LEFT JOIN barang b ON dpo.barang_id = b.id
        LEFT JOIN supplier s ON po.supplier_id = s.id
        LEFT JOIN users u ON po.created_by = u.id
        WHERE po.tanggal BETWEEN ? AND ?
        GROUP BY po.id
        ORDER BY po.tanggal DESC, po.id";

$stmt = $conn->prepare($sql);
if ($stmt === false) {
    die("Error preparing query: " . $conn->error);
}

$stmt->bind_param('ss', $start_date, $end_date);
if (!$stmt->execute()) {
    die("Error executing query: " . $stmt->error);
}
$result = $stmt->get_result();

// Set header untuk download Excel
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="Laporan_PO_' . date('Ymd') . '.xls"');
header('Cache-Control: max-age=0');
?>
<table border="1" style="width: 100%; border-collapse: collapse;">
    <tr style="background-color: #f0f0f0;">
        <td colspan="8" style="text-align: center; font-weight: bold; font-size: 16px; padding: 10px;">
            LAPORAN PURCHASE ORDER
        </td>
    </tr>
    <tr style="background-color: #f8f9fa;">
        <td colspan="8" style="text-align: center; padding: 8px;">
            Periode: <?= date('d/m/Y', strtotime($start_date)) ?> - <?= date('d/m/Y', strtotime($end_date)) ?>
        </td>
    </tr>
    <tr></tr>
    <tr style="background-color: #e9ecef; font-weight: bold; text-align: center;">
        <td style="border: 1px solid #000; padding: 8px;">No</td>
        <td style="border: 1px solid #000; padding: 8px;">Tanggal</td>
        <td style="border: 1px solid #000; padding: 8px;">Supplier</td>
        <td style="border: 1px solid #000; padding: 8px;">Nama Barang</td>
        <td style="border: 1px solid #000; padding: 8px;">Total Item</td>
        <td style="border: 1px solid #000; padding: 8px;">Status</td>
        <td style="border: 1px solid #000; padding: 8px;">Keterangan</td>
        <td style="border: 1px solid #000; padding: 8px;">Dibuat Oleh</td>
    </tr>
    <?php 
    $no = 1;
    $total_po = 0;
    $has_data = false;
    while($row = $result->fetch_assoc()): 
        $has_data = true;
        $total_po += $row['total_item'];
    ?>
    <tr>
        <td style="border: 1px solid #000; padding: 6px; text-align: center;"> <?= $no++ ?> </td>
        <td style="border: 1px solid #000; padding: 6px;"> <?= !empty($row['tanggal']) ? date('d/m/Y', strtotime($row['tanggal'])) : '' ?> </td>
        <td style="border: 1px solid #000; padding: 6px;"> <?= htmlspecialchars($row['nama_supplier'] ?? '') ?> </td>
        <td style="border: 1px solid #000; padding: 6px;"> <?= htmlspecialchars($row['nama_barang'] ?? '-') ?> </td>
        <td style="border: 1px solid #000; padding: 6px; text-align: right;"> <?= number_format($row['total_item'] ?? 0, 0) ?> </td>
        <td style="border: 1px solid #000; padding: 6px;"> <?= htmlspecialchars(ucfirst($row['status']) ?? '') ?> </td>
        <td style="border: 1px solid #000; padding: 6px;"> <?= !empty($row['keterangan']) ? htmlspecialchars($row['keterangan']) : '-' ?> </td>
        <td style="border: 1px solid #000; padding: 6px;"> <?= htmlspecialchars($row['created_by_name'] ?? '') ?> </td>
    </tr>
    <?php endwhile; ?>
    <?php if (!$has_data): ?>
    <tr>
        <td colspan="8" style="text-align: center; padding: 20px; font-style: italic;">Tidak ada data PO untuk periode ini.</td>
    </tr>
    <?php endif; ?>
    <tr style="font-weight: bold; background: #f8f9fa;">
        <td colspan="4" style="text-align: right; border: 1px solid #000;">Total Item:</td>
        <td style="text-align: right; border: 1px solid #000;"> <?= number_format($total_po, 0) ?> </td>
        <td colspan="3" style="border: 1px solid #000;"></td>
    </tr>
</table>
