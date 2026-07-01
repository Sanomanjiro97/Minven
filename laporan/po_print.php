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

// Query untuk mengambil data PO
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
$stmt->bind_param('ss', $start_date, $end_date);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cetak Laporan Purchase Order - Sistem Inventory</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            font-size: 12pt;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            border: 1px solid #000;
            padding: 5px;
            text-align: left;
        }
        th {
            background-color: #f0f0f0;
        }
        .text-end {
            text-align: right;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
        }
        .period-info {
            text-align: right;
            font-size: 10pt;
            margin-bottom: 10px;
        }
        .signature-section {
            margin-top: 30px;
            text-align: right;
        }
        @media print {
            @page {
                size: landscape;
                margin: 10mm;
            }
            body {
                margin: 0;
                font-size: 10pt;
            }
            table {
                margin-top: 10px;
                font-size: 9pt;
            }
            th, td {
                padding: 3px;
            }
            .header {
                margin-bottom: 5px;
            }
            .header h2 {
                font-size: 14pt;
                margin-bottom: 5px;
            }
        }
    </style>
</head>
<body onload="window.print()">
    <div class="header">
        <h2>Laporan Purchase Order</h2>
    </div>
    
    <div class="period-info">
        <p>Periode: <?= date('d/m/Y', strtotime($start_date)) ?> - <?= date('d/m/Y', strtotime($end_date)) ?></p>
    </div>

    <table>
        <thead>
            <tr>
            <th>No</th>
                                <th>Tanggal</th>
                                <th>Supplier</th>
                                <th>Nama Barang</th>
                                <th>Total Item</th>
                                <th>Status</th>
                                <th>Keterangan</th>
                                <th>Dibuat Oleh</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $no = 1;
            $total_po = 0;
            while($row = $result->fetch_assoc()): 
                $total_po += $row['total_item'];
            ?>
            <tr>
            <td><?= $no++ ?></td>
                                <td><?= date('d/m/Y', strtotime($row['tanggal'])) ?></td>
                                <td><?= htmlspecialchars($row['nama_supplier']) ?></td>
                                <td><?= htmlspecialchars($row['nama_barang']) ?></td>
                                <td class="text-end"><?= number_format($row['total_item']) ?></td>
                                <td class="<?= $status_class ?>"><?= ucfirst($row['status']) ?></td>
                                <td><?= htmlspecialchars($row['keterangan']) ?></td>
                                <td><?= htmlspecialchars($row['created_by_name']) ?></td>
            </tr>
            <?php endwhile; ?>
        </tbody>
        <tfoot>
            <tr>
                <th colspan="3" class="text-end">Total Item:</th>
                <th class="text-end"><?= number_format($total_po) ?></th>
                <th colspan="3"></th>
            </tr>
        </tfoot>
    </table>

    <div class="signature-section">
        <p>
            <?= date('d/m/Y') ?><br>
            Mengetahui,<br><br><br>
            (_______________)
        </p>
    </div>
</body>
</html>
