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
$stmt->bind_param('ss', $start_date, $end_date);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cetak Laporan Pembelian Direct - Sistem Inventory</title>
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
        <h2>Laporan Pembelian Direct</h2>
    </div>
    
    <div class="period-info">
        <p>Periode: <?= date('d/m/Y', strtotime($start_date)) ?> - <?= date('d/m/Y', strtotime($end_date)) ?></p>
    </div>

    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>Tanggal</th>
                <th>Nama Barang</th>
                <th>Total Item</th>
                <th>Total Harga</th>
                <th>Nama Toko</th>
                <th>Keterangan</th>
                <th>Created by</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $no = 1;
            $total_item = 0;
            $total_harga = 0;
            while($row = $result->fetch_assoc()): 
                $total_item += $row['total_item'];
                $total_harga += $row['total_harga'];
            ?>
            <tr>
                <td><?= $no++ ?></td>
                <td><?= date('d/m/Y', strtotime($row['tanggal'])) ?></td>
                <td><?= htmlspecialchars($row['nama_barang']) ?></td>
                <td class="text-end"><?= number_format($row['total_item']) ?></td>
                <td class="text-end"><?= number_format($row['total_harga']) ?></td>
                <td><?= htmlspecialchars($row['nama_toko_list']) ?></td>
                <td><?= htmlspecialchars($row['keterangan_list']) ?></td>
                <td><?= htmlspecialchars($row['created_by_names']) ?></td>
            </tr>
            <?php endwhile; ?>
        </tbody>
        <tfoot>
            <tr>
                <th colspan="3" class="text-end">Total:</th>
                <th class="text-end"><?= number_format($total_item) ?></th>
                <th class="text-end"><?= number_format($total_harga) ?></th>
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
