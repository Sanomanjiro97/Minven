<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

$gudang_central_id = 23;
$gudang_antapani_id = 13;
$gudang_central_nama = 'Gudang Central';
$gudang_antapani_nama = 'Gudang Antapani';
$stmt_gudang = $conn->prepare("SELECT id, nama_gudang FROM gudang WHERE id IN (?, ?)");
if ($stmt_gudang) {
    $stmt_gudang->bind_param('ii', $gudang_central_id, $gudang_antapani_id);
    $stmt_gudang->execute();
    $res_gudang = $stmt_gudang->get_result();
    while ($row = $res_gudang->fetch_assoc()) {
        $id = (int)($row['id'] ?? 0);
        $nama = trim((string)($row['nama_gudang'] ?? ''));
        if ($id === $gudang_central_id && $nama !== '') {
            $gudang_central_nama = $nama;
        }
        if ($id === $gudang_antapani_id && $nama !== '') {
            $gudang_antapani_nama = $nama;
        }
    }
    $stmt_gudang->close();
}

// Function to update stok_terpakai based on actual transactions
function updateStokTerpakai($conn, $gudang_id = null) {
    try {
        $conn->begin_transaction();
        
        // Get all gudang if gudang_id is not specified
        if ($gudang_id) {
            $gudang_condition = "WHERE g.id = ?";
            $params = [$gudang_id];
            $types = "i";
        } else {
            $gudang_condition = "";
            $params = [];
            $types = "";
        }
        
        // Get all gudang and barang combinations
        $sql_gudang_barang = "SELECT g.id as gudang_id, b.id as barang_id 
                              FROM gudang g 
                              CROSS JOIN barang b 
                              $gudang_condition";
        
        $stmt = $conn->prepare($sql_gudang_barang);
        if ($gudang_id) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        
        $updated_count = 0;
        
        while ($row = $result->fetch_assoc()) {
            $gudang_id = $row['gudang_id'];
            $barang_id = $row['barang_id'];
            
            // Calculate total stok keluar for this barang in this gudang
            $sql_stok_keluar = "SELECT COALESCE(SUM(dts.jumlah), 0) as total_keluar
                                FROM detail_transaksi_stok dts
                                JOIN transaksi_stok ts ON dts.transaksi_stok_id = ts.id
                                WHERE ts.gudang_id = ? 
                                AND dts.barang_id = ? 
                                AND ts.jenis_transaksi = 'keluar'
                                AND ts.deleted_at IS NULL";
            
            $stmt_keluar = $conn->prepare($sql_stok_keluar);
            $stmt_keluar->bind_param("ii", $gudang_id, $barang_id);
            $stmt_keluar->execute();
            $result_keluar = $stmt_keluar->get_result();
            $total_keluar = $result_keluar->fetch_assoc()['total_keluar'];
            
            // Check if gudang_stok record exists
            $sql_check = "SELECT id, stok_terpakai FROM gudang_stok WHERE gudang_id = ? AND barang_id = ?";
            $stmt_check = $conn->prepare($sql_check);
            $stmt_check->bind_param("ii", $gudang_id, $barang_id);
            $stmt_check->execute();
            $result_check = $stmt_check->get_result();
            
            if ($result_check->num_rows > 0) {
                // Update existing record
                $current_data = $result_check->fetch_assoc();
                $current_stok_terpakai = $current_data['stok_terpakai'];
                
                if ($current_stok_terpakai != $total_keluar) {
                    $sql_update = "UPDATE gudang_stok 
                                   SET stok_terpakai = ?, 
                                       modified_by = ?, 
                                       updated_at = NOW() 
                                   WHERE gudang_id = ? AND barang_id = ?";
                    $stmt_update = $conn->prepare($sql_update);
                    $stmt_update->bind_param("diii", $total_keluar, $_SESSION['user_id'], $gudang_id, $barang_id);
                    $stmt_update->execute();
                    $updated_count++;
                }
            } else {
                // Create new record if doesn't exist
                $sql_insert = "INSERT INTO gudang_stok (gudang_id, barang_id, stok_awal, stok_terpakai, stok_minimum, created_by, modified_by) 
                               VALUES (?, ?, 0, ?, 0, ?, ?)";
                $stmt_insert = $conn->prepare($sql_insert);
                $stmt_insert->bind_param("iidii", $gudang_id, $barang_id, $total_keluar, $_SESSION['user_id'], $_SESSION['user_id']);
                $stmt_insert->execute();
                $updated_count++;
            }
        }
        
        $conn->commit();
        return ['success' => true, 'updated_count' => $updated_count];
        
    } catch (Exception $e) {
        $conn->rollback();
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_stok_terpakai') {
        $gudang_id = isset($_POST['gudang_id']) ? intval($_POST['gudang_id']) : null;
        
        $result = updateStokTerpakai($conn, $gudang_id);
        
        header('Content-Type: application/json');
        echo json_encode($result);
        exit();
    }
}

// Handle direct access (for manual update)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $gudang_id = isset($_GET['gudang_id']) ? intval($_GET['gudang_id']) : null;
    
    $result = updateStokTerpakai($conn, $gudang_id);
    
    if ($result['success']) {
        $_SESSION['success'] = "Stok terpakai berhasil diupdate untuk " . $result['updated_count'] . " item.";
    } else {
        $_SESSION['error'] = "Gagal mengupdate stok terpakai: " . $result['message'];
    }
    
    // Redirect back to appropriate page
    if ($gudang_id == 23) {
        header("Location: gudang_central.php");
    } elseif ($gudang_id == 13) {
        header("Location: gudang_antapani.php");
    } else {
        header("Location: master_gudang.php");
    }
    exit();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Stok Terpakai - Sistem Inventory</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">
                            <i class='bx bx-refresh me-2'></i>
                            Update Stok Terpakai
                        </h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted">
                            Tool ini akan mengupdate stok terpakai berdasarkan transaksi stok keluar yang sebenarnya.
                        </p>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card border-primary">
                                    <div class="card-body text-center">
                                        <h6 class="card-title"><?= htmlspecialchars($gudang_central_nama) ?></h6>
                                        <button class="btn btn-primary" onclick="updateStokTerpakai(<?= (int)$gudang_central_id ?>)">
                                            <i class='bx bx-refresh me-1'></i>
                                            Update
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card border-success">
                                    <div class="card-body text-center">
                                        <h6 class="card-title"><?= htmlspecialchars($gudang_antapani_nama) ?></h6>
                                        <button class="btn btn-success" onclick="updateStokTerpakai(<?= (int)$gudang_antapani_id ?>)">
                                            <i class='bx bx-refresh me-1'></i>
                                            Update
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-4">
                            <div class="card border-warning">
                                <div class="card-body text-center">
                                    <h6 class="card-title">Semua Gudang</h6>
                                    <button class="btn btn-warning" onclick="updateStokTerpakai()">
                                        <i class='bx bx-refresh me-1'></i>
                                        Update Semua Gudang
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-4">
                            <div class="alert alert-info">
                                <h6><i class='bx bx-info-circle me-1'></i>Informasi:</h6>
                                <ul class="mb-0">
                                    <li>Stok terpakai akan dihitung berdasarkan transaksi stok keluar yang sebenarnya</li>
                                    <li>Proses ini akan mengupdate semua barang di gudang yang dipilih</li>
                                    <li>Stok akhir = Stok awal - Stok terpakai</li>
                                    <li>Proses ini aman dan tidak akan menghapus data transaksi</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function updateStokTerpakai(gudangId = null) {
            if (!confirm('Apakah Anda yakin ingin mengupdate stok terpakai?')) {
                return;
            }
            
            const button = event.target;
            const originalText = button.innerHTML;
            button.innerHTML = '<i class="bx bx-loader-alt bx-spin me-1"></i>Updating...';
            button.disabled = true;
            
            const formData = new FormData();
            formData.append('action', 'update_stok_terpakai');
            if (gudangId) {
                formData.append('gudang_id', gudangId);
            }
            
            fetch('update_stok_terpakai.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(`Berhasil mengupdate ${data.updated_count} item!`);
                    location.reload();
                } else {
                    alert('Gagal mengupdate: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Terjadi kesalahan: ' + error.message);
            })
            .finally(() => {
                button.innerHTML = originalText;
                button.disabled = false;
            });
        }
    </script>
</body>
</html> 
