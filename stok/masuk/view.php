<?php
session_start();
require_once '../../config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit();
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Query untuk mengambil data header transaksi stok masuk
$sql = "SELECT ts.*, g.nama_gudang 
        FROM transaksi_stok ts
        LEFT JOIN gudang g ON ts.gudang_id = g.id
        WHERE ts.id = ? AND ts.jenis_transaksi = 'masuk'"; // Filter jenis_transaksi
$stmt = $conn->prepare($sql);

// Check if prepare failed
if ($stmt === false) {
    die("Error preparing statement for header: " . $conn->error);
}
$stmt->bind_param("i", $id);
$stmt->execute();
$header = $stmt->get_result()->fetch_assoc();

if (!$header) {
    $_SESSION['error'] = "Transaksi tidak ditemukan!";
    header("Location: index.php");
    exit();
}

// Query untuk mengambil detail transaksi
$sql = "SELECT d.*, b.kode_barang, b.nama_barang, s.nama_satuan
        FROM detail_transaksi_stok d
        JOIN barang b ON d.barang_id = b.id
        LEFT JOIN satuan s ON b.satuan_id = s.id
        WHERE d.transaksi_stok_id = ?
        ORDER BY b.nama_barang";
$stmt = $conn->prepare($sql);
// Check if prepare failed for the details query as well
if ($stmt === false) {
    die("Error preparing statement for details: " . $conn->error);
}

// Check if prepare failed
if ($stmt === false) {
    die("Error preparing statement for header: " . $conn->error);
}
$stmt->bind_param("i", $id);
$stmt->execute();
$details = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Stok Masuk - Sistem Inventory</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.0.7/css/boxicons.min.css" rel="stylesheet">
</head>
<body>
    <?php include '../../templates/navbar.php'; ?>

    <div class="container mt-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                    <div class="card-header" style="background-color: Bluesky; color: Black;">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="card-title">Detail Stok Masuk</h5>
                            <div>
                                <img src="../../asset/cjawilnew.png" alt="Logo" style="height: 60px; margin-right: 10px;">
                                <a href="index.php" class="btn btn-secondary">Kembali</a>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <table class="table table-sm">
                                    <tr>
                                        <td width="150">No Transaksi</td>
                                        <td width="20">:</td>
                                        <td><?= htmlspecialchars($header['no_transaksi']) ?></td>
                                    </tr>
                                    <tr>
                                        <td>Tanggal</td>
                                        <td>:</td>
                                        <td><?= date('d/m/Y', strtotime($header['tanggal'])) ?></td>
                                    </tr>
                                    <tr>
                                        <td>Gudang</td>
                                        <td>:</td>
                                        <td><?= htmlspecialchars($header['nama_gudang']) ?></td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <table class="table table-sm">
                                    <tr>
                                        <td width="150">Keterangan</td>
                                        <td width="20">:</td>
                                        <td><?= htmlspecialchars($header['keterangan']) ?></td>
                                    </tr>
                                </table>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th>No</th>
                                        <th>Kode Barang</th>
                                        <th>Nama Barang</th>
                                        <th>Jumlah</th>
                                        <th>Satuan</th>
                                        <th>Keterangan</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $no = 1;
                                    while($detail = $details->fetch_assoc()): 
                                    ?>
                                    <tr>
                                        <td><?= $no++ ?></td>
                                        <td><?= htmlspecialchars($detail['kode_barang']) ?></td>
                                        <td><?= htmlspecialchars($detail['nama_barang']) ?></td>
                                        <td class="text-end"><?= number_format($detail['jumlah']) ?></td>
                                        <td><?= htmlspecialchars($detail['nama_satuan']) ?></td>
                                        <td><?= isset($detail['keterangan']) ? htmlspecialchars($detail['keterangan']) : '' ?></td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="text-start mt-3">
                            <small style="font-family: Arial, sans-serif; font-weight: bold;">MINVEN</small>
                        </div>
                    </div>
                </div>
            </div>
                            
    