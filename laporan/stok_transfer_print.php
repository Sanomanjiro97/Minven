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

// Query untuk mengambil data stok transfer
$sql = "SELECT 
             tt.tanggal,
            b.kode_barang,
            b.nama_barang,
            s.nama_satuan,
            dtt.jumlah,
            dtt.keterangan,
            u.nama as created_by_name,
            g_asal.nama_gudang as gudang_asal,
            g_tujuan.nama_gudang as gudang_tujuan,
            gs_asal.stok_awal as stok_awal_asal,
            gs_asal.jumlah as stok_akhir_asal,
            gs_tujuan.stok_awal as stok_awal_tujuan,
            gs_tujuan.jumlah as stok_akhir_tujuan
        FROM transaksi_transfer tt
        JOIN detail_transaksi_transfer dtt ON tt.id = dtt.transaksi_transfer_id
        JOIN barang b ON dtt.barang_id = b.id
        LEFT JOIN satuan s ON b.satuan_id = s.id
        LEFT JOIN users u ON tt.created_by = u.id
        LEFT JOIN gudang g_asal ON tt.gudang_asal_id = g_asal.id
        LEFT JOIN gudang g_tujuan ON tt.gudang_tujuan_id = g_tujuan.id
        LEFT JOIN gudang_stok gs_asal ON (gs_asal.barang_id = b.id AND gs_asal.gudang_id = tt.gudang_asal_id AND gs_asal.detail_barang = dtt.keterangan)
        LEFT JOIN gudang_stok gs_tujuan ON (gs_tujuan.barang_id = b.id AND gs_tujuan.gudang_id = tt.gudang_tujuan_id AND gs_tujuan.detail_barang = dtt.keterangan)
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
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cetak Laporan Stok Transfer - Sistem Inventory</title>
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
        <img src="../asset/cjawilnew.png" alt="Logo" style="height:60px; display:block; margin:0 auto 10px auto;">
        <h2>Laporan Stok Transfer</h2>
        <p>Periode: <?= date('d/m/Y', strtotime($start_date)) ?> - <?= date('d/m/Y', strtotime($end_date)) ?></p>
        <?php if ($gudang_id): 
            $gudang_sql = "SELECT nama_gudang FROM gudang WHERE id = ?";
            $gudang_stmt = $conn->prepare($gudang_sql);
            $gudang_stmt->bind_param('i', $gudang_id);
            $gudang_stmt->execute();
            $gudang_result = $gudang_stmt->get_result();
            $gudang_name = $gudang_result->fetch_assoc()['nama_gudang'];
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
                                <th>Gudang Asal</th>
                                <th>Gudang Tujuan</th>
                                <th>Jumlah Transfer</th>
                                <th>Satuan</th>
                                <th>Keterangan</th>
                                <th>Updated by</th>
            </tr>
        </thead>
        <tbody>
                            <?php 
                            $no = 1;
                            $total_transfer = 0;
                            while($row = $result->fetch_assoc()): 
                                $total_transfer += $row['jumlah'];
                            ?>
            <tr>
            <td><?= $no++ ?></td>
                                <td><?= date('d/m/Y', strtotime($row['tanggal'])) ?></td>
                                <td><?= htmlspecialchars($row['kode_barang']) ?></td>
                                <td><?= htmlspecialchars($row['nama_barang']) ?></td>
                                <td><?= htmlspecialchars($row['gudang_asal']) ?></td>
                                <td><?= htmlspecialchars($row['gudang_tujuan']) ?></td>
                                <td class="text-end"><?= number_format($row['jumlah']) ?></td>
                                <td><?= htmlspecialchars($row['nama_satuan']) ?></td>
                                <td><?= htmlspecialchars($row['keterangan']) ?></td>
                                <td><?= htmlspecialchars($row['created_by_name']) ?></td>
            </tr>
            <?php endwhile; ?>
        </tbody>
        <tfoot>
                            <tr>
                                <th colspan="6" class="text-end">Total Transfer:</th>
                                <th class="text-end"><?= number_format($total_transfer) ?></th>
                                <th colspan="3"></th>
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
