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

// Query untuk mengambil data pengeluaran
$sql = "SELECT 
            p.id,
            p.tanggal,
            p.no_pengeluaran,
            p.total_item,
            p.keterangan,
            u.nama as created_by_name
        FROM pengeluaran p
        LEFT JOIN users u ON p.created_by = u.id
        WHERE p.tanggal BETWEEN ? AND ?
        ORDER BY p.tanggal DESC, p.id";

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
    <title>Cetak Laporan Pengeluaran - Sistem Inventory</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            border: 1px solid #000;
            padding: 5px;
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
        .filter-info {
            margin-bottom: 20px;
        }
        @media print {
            @page {
                size: landscape;
            }
        }
    </style>
</head>
<body onload="window.print()">
    <div class="header">
        <h2>Laporan Pengeluaran</h2>
        <p>Periode: <?= date('d/m/Y', strtotime($start_date)) ?> - <?= date('d/m/Y', strtotime($end_date)) ?></p>
    </div>

    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>Tanggal</th>
                <th>No Pengeluaran</th>
                <th>Total Item</th>
                <th>Keterangan</th>
                <th>Dibuat Oleh</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $no = 1;
            $total_item = 0;
            while($row = $result->fetch_assoc()): 
                $total_item += $row['total_item'];
            ?>
            <tr>
                <td><?= $no++ ?></td>
                <td><?= date('d/m/Y', strtotime($row['tanggal'])) ?></td>
                <td><?= htmlspecialchars($row['no_pengeluaran']) ?></td>
                <td class="text-end"><?= number_format($row['total_item']) ?></td>
                <td><?= htmlspecialchars($row['keterangan']) ?></td>
                <td><?= htmlspecialchars($row['created_by_name']) ?></td>
            </tr>
            <?php endwhile; ?>
        </tbody>
        <tfoot>
            <tr>
                <th colspan="3" class="text-end">Total Item:</th>
                <th class="text-end"><?= number_format($total_item) ?></th>
                <th colspan="2"></th>
            </tr>
        </tfoot>
    </table>

    <div style="margin-top: 50px;">
        <div style="float: right; text-align: center;">
            <p>
                <?= date('d/m/Y') ?><br>
                Mengetahui,<br><br><br><br>
                (_______________)
            </p>
        </div>
    </div>
</body>
</html>
