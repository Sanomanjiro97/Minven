<?php
session_start();
require_once '../../config.php';
require_once '../../includes/access_check.php';
require_once '../../includes/menu_access_helper.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit();
}

// Check access untuk menu stok_transfer
if (!checkAccess('stok_transfer', 'view')) {
    $_SESSION['error'] = 'Anda tidak memiliki akses untuk melihat menu stok transfer!';
    header('Location: ../../dashboard.php');
    exit();
}

// Get filter dates from GET parameters
$filter_session_key = 'filter_stok_transfer_index';
if (isset($_GET['reset']) && (string)$_GET['reset'] === '1') {
    unset($_SESSION[$filter_session_key]);
    header('Location: index.php');
    exit();
}

$saved_filters = [];
if (isset($_SESSION[$filter_session_key]) && is_array($_SESSION[$filter_session_key])) {
    $saved_filters = $_SESSION[$filter_session_key];
}

$filter_keys = ['start_date', 'end_date', 'item_name', 'gudang_name'];
$has_any_filter_param = false;
foreach ($filter_keys as $k) {
    if (array_key_exists($k, $_GET)) {
        $has_any_filter_param = true;
        break;
    }
}

if (!$has_any_filter_param && !empty($saved_filters)) {
    $redirect_params = [];
    foreach ($filter_keys as $k) {
        if (array_key_exists($k, $saved_filters) && (string)$saved_filters[$k] !== '') {
            $redirect_params[$k] = (string)$saved_filters[$k];
        }
    }
    if (!empty($redirect_params)) {
        header('Location: index.php?' . http_build_query($redirect_params));
        exit();
    }
}

$start_date = isset($_GET['start_date']) ? (string)$_GET['start_date'] : (string)($saved_filters['start_date'] ?? '');
$end_date = isset($_GET['end_date']) ? (string)$_GET['end_date'] : (string)($saved_filters['end_date'] ?? '');
$item_name = isset($_GET['item_name']) ? trim((string)$_GET['item_name']) : trim((string)($saved_filters['item_name'] ?? ''));
$gudang_name = isset($_GET['gudang_name']) ? trim((string)$_GET['gudang_name']) : trim((string)($saved_filters['gudang_name'] ?? ''));

$_SESSION[$filter_session_key] = [
    'start_date' => $start_date,
    'end_date' => $end_date,
    'item_name' => $item_name,
    'gudang_name' => $gudang_name,
];

// Base query
$sql = "SELECT ts.*, g1.nama_gudang as gudang_asal, g2.nama_gudang as gudang_tujuan, u.nama as created_by_name, ts.keterangan as keterangan
        FROM transaksi_transfer ts
        LEFT JOIN gudang g1 ON ts.gudang_asal_id = g1.id
        LEFT JOIN gudang g2 ON ts.gudang_tujuan_id = g2.id
        LEFT JOIN users u ON ts.created_by = u.id
        WHERE 1=1";

// Add date filters if provided
$types = '';
$params = [];

if ($start_date && $end_date) {
    $sql .= " AND DATE(ts.tanggal) BETWEEN ? AND ?";
    $types .= "ss";
    $params[] = $start_date;
    $params[] = $end_date;
}

if ($gudang_name !== '') {
    $sql .= " AND (g1.nama_gudang LIKE ? OR g2.nama_gudang LIKE ?)";
    $types .= "ss";
    $params[] = "%" . $gudang_name . "%";
    $params[] = "%" . $gudang_name . "%";
}

if ($item_name !== '') {
    $sql .= " AND EXISTS (
        SELECT 1
        FROM detail_transaksi_transfer dtt
        JOIN barang b ON dtt.barang_id = b.id
        WHERE dtt.transaksi_transfer_id = ts.id
          AND b.nama_barang LIKE ?
    )";
    $types .= "s";
    $params[] = "%" . $item_name . "%";
}

$sql .= " ORDER BY ts.tanggal DESC, ts.created_at DESC";

// Prepare and execute the statement
$stmt = $conn->prepare($sql);

if ($stmt) {
    if ($types !== '') {
        $bind_params = [];
        $bind_params[] = $types;
        foreach ($params as $i => $value) {
            $bind_params[] = &$params[$i];
        }
        call_user_func_array([$stmt, 'bind_param'], $bind_params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    if (!$result) {
        die("Query error: " . $stmt->error);
    }
} else {
    die("Query error: " . $conn->error);
}

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transfer Stok - Sistem Inventory</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.0.7/css/boxicons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <style>
        body {
            background: #ffffff;
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .card {
            border-radius: 0.75rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            background: #ffffff;
            border: 1px solid #e2e8f0;
        }
        
        .table thead th {
            background: #0008f9;
            color: white;
            border: none;
            padding: 1rem;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.875rem;
            letter-spacing: 0.05em;
        }
    </style>
</head>
<body>
    <?php include '../../templates/navbar.php'; ?>

    <div class="container-fluid mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Transfer Stok</h2>
            <a href="create.php" class="btn btn-primary">
                <i class='bx bx-plus'></i> Tambah Transfer Stok
            </a>
        </div>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= $_SESSION['success']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= $_SESSION['error']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label for="start_date" class="form-label">Tanggal Mulai</label>
                        <input type="date" class="form-control" id="start_date" name="start_date" value="<?= htmlspecialchars($start_date) ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="end_date" class="form-label">Tanggal Selesai</label>
                        <input type="date" class="form-control" id="end_date" name="end_date" value="<?= htmlspecialchars($end_date) ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="item_name" class="form-label">Nama Barang</label>
                        <input type="text" class="form-control" id="item_name" name="item_name" value="<?= htmlspecialchars($item_name) ?>" placeholder="Cari barang...">
                    </div>
                    <div class="col-md-3">
                        <label for="gudang_name" class="form-label">Nama Gudang</label>
                        <input type="text" class="form-control" id="gudang_name" name="gudang_name" value="<?= htmlspecialchars($gudang_name) ?>" placeholder="Cari gudang...">
                    </div>
                    <div class="col-12 d-flex justify-content-end gap-2">
                        <button type="submit" class="btn btn-primary">Filter</button>
                        <a href="index.php?reset=1" class="btn btn-secondary">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>No Transaksi</th>
                                <th>Tanggal</th>
                                <th>Gudang Asal</th>
                                <th>Gudang Tujuan</th>
                                <th>Keterangan</th>
                                <th>Total Item</th>
                                <th>Dibuat Oleh</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            
                            <?php while($row = $result->fetch_assoc()): 
                                // Hitung total item
                                $trans_id = $row['id'];
                                $total_item_query = "SELECT SUM(jumlah) as total FROM detail_transaksi_transfer WHERE transaksi_transfer_id = ?";
                                $stmt = $conn->prepare($total_item_query);
                                $stmt->bind_param("i", $trans_id);
                                $stmt->execute();
                                $total_item_result = $stmt->get_result();
                                $total_item = $total_item_result->fetch_assoc()['total'] ?? 0;
                            ?>
                            <tr>
                                <td><?= htmlspecialchars($row['no_transaksi']) ?></td>
                                <td><?= date('d/m/Y', strtotime($row['tanggal'])) ?></td>
                                <td><?= htmlspecialchars($row['gudang_asal']) ?></td>
                                <td><?= htmlspecialchars($row['gudang_tujuan']) ?></td>
                                <td><?= !empty($row['keterangan']) ? htmlspecialchars($row['keterangan']) : '<span class="text-muted">-</span>' ?></td>
                                <td><?= number_format($total_item) ?></td>
                                <td><?= htmlspecialchars($row['created_by_name'] ?? 'N/A') ?></td>
                                <td>
                                    <a href="view.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-info">
                                        <i class='bx bx-detail'></i>
                                    </a>
                                    <?php if(date('Y-m-d') == date('Y-m-d', strtotime($row['tanggal']))): ?>
                                    <button class="btn btn-sm btn-danger delete-btn" 
                                            data-id="<?= $row['id'] ?>"
                                            data-no="<?= htmlspecialchars($row['no_transaksi']) ?>">
                                        <i class='bx bx-trash'></i>
                                    </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
        // Initialize date pickers
        flatpickr("#start_date", {
            dateFormat: "Y-m-d",
            maxDate: "today",
            onChange: function(selectedDates, dateStr) {
                // Update end_date min date when start_date changes
                const endDatePicker = document.querySelector("#end_date")._flatpickr;
                endDatePicker.set("minDate", dateStr);
            }
        });

        flatpickr("#end_date", {
            dateFormat: "Y-m-d",
            maxDate: "today",
            onChange: function(selectedDates, dateStr) {
                // Update start_date max date when end_date changes
                const startDatePicker = document.querySelector("#start_date")._flatpickr;
                startDatePicker.set("maxDate", dateStr);
            }
        });

        // Handle delete
        document.querySelectorAll('.delete-btn').forEach(button => {
            button.addEventListener('click', function() {
                if(confirm('Apakah Anda yakin ingin menghapus transaksi ini?')) {
                    const id = this.dataset.id;
                    window.location.href = `process.php?action=delete&id=${id}`;
                }
            });
        });
    </script>
</body>
</html>
