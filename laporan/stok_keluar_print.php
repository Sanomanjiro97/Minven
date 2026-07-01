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
$gudang_id = isset($_GET['gudang_id']) ? $_GET['gudang_id'] : '';

// Adjust dates based on period
if ($period == 'weekly') {
    $start_date = date('Y-m-d', strtotime('monday this week'));
    $end_date = date('Y-m-d', strtotime('sunday this week'));
} elseif ($period == 'monthly') {
    $start_date = date('Y-m-01');
    $end_date = date('Y-m-t');
}

// Query untuk mengambil data stok keluar dari transaksi_stok dan detail_transaksi_stok
$sql = "SELECT
            ts.tanggal,
            b.kode_barang,
            b.nama_barang,
            dts.jumlah, -- Mengambil jumlah dari detail_transaksi_stok
            g.nama_gudang,
            u.username as created_by_username -- Tambahkan created_by_username
        FROM detail_transaksi_stok dts -- Mulai dari detail
        JOIN transaksi_stok ts ON dts.transaksi_stok_id = ts.id -- Bergabung ke transaksi header
        LEFT JOIN barang b ON dts.barang_id = b.id -- Bergabung ke barang
        JOIN gudang g ON ts.gudang_id = g.id -- Bergabung ke gudang
        LEFT JOIN users u ON ts.created_by = u.id -- Join dengan tabel users
        WHERE ts.jenis_transaksi = 'keluar' -- Filter hanya transaksi keluar
        AND ts.tanggal BETWEEN ? AND ? -- Filter berdasarkan tanggal
        ".($gudang_id ? "AND ts.gudang_id = ?" : "")." -- Filter berdasarkan gudang jika dipilih
        ORDER BY ts.tanggal DESC, b.kode_barang";

$stmt = $conn->prepare($sql);
if ($stmt === false) {
    die("Error preparing query: " . $conn->error);
}

if ($gudang_id) {
    $stmt->bind_param('ssi', $start_date, $end_date, $gudang_id);
} else {
    $stmt->bind_param('ss', $start_date, $end_date);
}

$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cetak Laporan Stok Keluar - Sistem Inventory</title>
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
        <h2>Laporan Stok Keluar</h2>
        <p>Periode: <?= date('d/m/Y', strtotime($start_date)) ?> - <?= date('d/m/Y', strtotime($end_date)) ?></p>
        <?php if ($gudang_id): ?>
            <?php 
            // Get warehouse name for display
            $gudang_sql = "SELECT nama_gudang FROM gudang WHERE id = ?";
            $gudang_stmt = $conn->prepare($gudang_sql);
            $gudang_stmt->bind_param('i', $gudang_id);
            $gudang_stmt->execute();
            $gudang_result = $gudang_stmt->get_result();
            $gudang_name = $gudang_result->fetch_assoc()['nama_gudang'] ?? '';
            ?>
            <p>Gudang: <?= htmlspecialchars($gudang_name) ?></p>
        <?php endif; ?>
    </div>

    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>Tanggal</th>
                <th>Kode Barang</th>
                <th>Nama Barang</th>
                <th>Jumlah Keluar</th>
                <th>Gudang Asal</th>
                <th>Created by</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $no = 1;
            $total_keluar = 0;
            while($row = $result->fetch_assoc()): 
                $total_keluar += $row['jumlah'];
            ?>
            <tr>
                <td><?= $no++ ?></td>
                <td><?= date('d/m/Y', strtotime($row['tanggal'])) ?></td>
                <td><?= htmlspecialchars($row['kode_barang'] ?? '-') ?></td>
                <td><?= htmlspecialchars($row['nama_barang'] ?? '-') ?></td>
                <td class="text-end"><?= number_format($row['jumlah']) ?></td>
                <td><?= htmlspecialchars($row['nama_gudang'] ?? '-') ?></td>
                <td><?= htmlspecialchars($row['created_by_username'] ?? 'N/A') ?></td>
            </tr>
            <?php endwhile; ?>
        </tbody>
        <tfoot>
            <tr>
                <th colspan="4" class="text-end">Total Keluar:</th>
                <th class="text-end"><?= number_format($total_keluar) ?></th>
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
