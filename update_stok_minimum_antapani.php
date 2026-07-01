<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Check if this is a POST request to update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_stok_minimum'])) {
    try {
        // Update stok_minimum from 0 to 75 for gudang antapani (ID 13)
        $update_sql = "UPDATE gudang_stok SET stok_minimum = 75 WHERE gudang_id = 13 AND stok_minimum = 0";
        $result = $conn->query($update_sql);
        
        if ($result) {
            $affected_rows = $conn->affected_rows;
            $_SESSION['success'] = "Berhasil mengupdate $affected_rows record(s) dengan stok_minimum dari 0 menjadi 75 untuk Gudang Antapani.";
        } else {
            $_SESSION['error'] = "Gagal mengupdate stok_minimum: " . $conn->error;
        }
        
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    } catch (Exception $e) {
        $_SESSION['error'] = "Error: " . $e->getMessage();
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
}

// Get current data for display
$sql = "SELECT 
            gs.id,
            b.kode_barang,
            b.nama_barang,
            gs.stok_minimum,
            gs.modified_by,
            u.nama as updated_by
        FROM gudang_stok gs
        LEFT JOIN barang b ON gs.barang_id = b.id
        LEFT JOIN users u ON gs.modified_by = u.id
        WHERE gs.gudang_id = 13 AND gs.stok_minimum = 0
        ORDER BY b.nama_barang";

$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Stok Minimum Gudang Antapani</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
</head>
<body>
    <?php include __DIR__ . '/templates/navbar.php'; ?>

    <div class="container mt-4">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class='bx bx-edit me-2'></i>
                            Update Stok Minimum Gudang Antapani
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (isset($_SESSION['success'])): ?>
                            <div class="alert alert-success">
                                <?= $_SESSION['success'] ?>
                                <?php unset($_SESSION['success']); ?>
                            </div>
                        <?php endif; ?>

                        <?php if (isset($_SESSION['error'])): ?>
                            <div class="alert alert-danger">
                                <?= $_SESSION['error'] ?>
                                <?php unset($_SESSION['error']); ?>
                            </div>
                        <?php endif; ?>

                        <div class="alert alert-info">
                            <strong>Informasi:</strong> Script ini akan mengupdate stok_minimum dari 0 menjadi 75 untuk semua barang di Gudang Antapani yang saat ini memiliki stok_minimum = 0.
                        </div>

                        <?php if ($result && $result->num_rows > 0): ?>
                            <div class="mb-3">
                                <h6>Barang yang akan diupdate (<?= $result->num_rows ?> item):</h6>
                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered">
                                        <thead>
                                            <tr>
                                                <th>No</th>
                                                <th>Kode Barang</th>
                                                <th>Nama Barang</th>
                                                <th>Stok Minimum Saat Ini</th>
                                                <th>Updated By</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            $no = 1;
                                            while($row = $result->fetch_assoc()): 
                                            ?>
                                            <tr>
                                                <td><?= $no++ ?></td>
                                                <td><?= htmlspecialchars($row['kode_barang']) ?></td>
                                                <td><?= htmlspecialchars($row['nama_barang']) ?></td>
                                                <td class="text-center"><?= $row['stok_minimum'] ?></td>
                                                <td><?= !empty($row['updated_by']) ? htmlspecialchars($row['updated_by']) : 'N/A' ?></td>
                                            </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <form method="POST" onsubmit="return confirm('Apakah Anda yakin ingin mengupdate stok_minimum untuk <?= $result->num_rows ?> barang dari 0 menjadi 75?')">
                                <button type="submit" name="update_stok_minimum" class="btn btn-warning">
                                    <i class='bx bx-update me-1'></i>
                                    Update Stok Minimum (0 → 75)
                                </button>
                                <a href="laporan/stok_gudang.php" class="btn btn-secondary">
                                    <i class='bx bx-arrow-back me-1'></i>
                                    Kembali ke Laporan
                                </a>
                            </form>
                        <?php else: ?>
                            <div class="alert alert-success">
                                <i class='bx bx-check-circle me-2'></i>
                                Tidak ada barang dengan stok_minimum = 0 di Gudang Antapani. Semua sudah sesuai!
                            </div>
                            <a href="laporan/stok_gudang.php" class="btn btn-primary">
                                <i class='bx bx-arrow-back me-1'></i>
                                Kembali ke Laporan
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 
