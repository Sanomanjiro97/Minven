<?php
session_start();
require_once '../../config.php';
require_once '../../includes/access_check.php';
require_once '../../includes/menu_access_helper.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit();
}

// Check access untuk menu stok_masuk
if (!checkAccess('stok_masuk', 'view')) {
    $_SESSION['error'] = 'Anda tidak memiliki akses untuk melihat menu stok masuk!';
    header('Location: ../../dashboard.php');
    exit();
}

ini_set('display_errors', 1);
error_reporting(E_ALL);

$filter_session_key = 'filter_stok_masuk_index';
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

// Get filter dates from GET parameters
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
$sql = "SELECT
            ts.*,
            g.nama_gudang,
            u.username as created_by_username,
            GROUP_CONCAT(b.nama_barang SEPARATOR ', ') as nama_barang -- Select and aggregate nama_barang from detail
        FROM transaksi_stok ts
        LEFT JOIN detail_transaksi_stok dts ON ts.id = dts.transaksi_stok_id
        LEFT JOIN barang b ON dts.barang_id = b.id
        LEFT JOIN satuan s ON b.satuan_id = s.id
        LEFT JOIN gudang g ON ts.gudang_id = g.id
        LEFT JOIN users u ON ts.created_by = u.id -- Join dengan tabel users
        WHERE ts.jenis_transaksi = 'masuk'";

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
    $sql .= " AND g.nama_gudang LIKE ?";
    $types .= "s";
    $params[] = "%" . $gudang_name . "%";
}

if ($item_name !== '') {
    $sql .= " AND EXISTS (
        SELECT 1
        FROM detail_transaksi_stok dts2
        JOIN barang b2 ON dts2.barang_id = b2.id
        WHERE dts2.transaksi_stok_id = ts.id
          AND b2.nama_barang LIKE ?
    )";
    $types .= "s";
    $params[] = "%" . $item_name . "%";
}

$sql .= " GROUP BY ts.id ORDER BY ts.created_at DESC, ts.no_transaksi DESC";

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
    <title>Stok Masuk - Sistem Inventory</title>
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
            <h2>Stok Masuk</h2>
            <a href="create.php" class="btn btn-primary">
                <i class='bx bx-plus'></i> Tambah Stok Masuk
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
                                <th>Gudang Tujuan</th>
                                <th>Nama Barang</th>
                                <th>Keterangan</th>
                                <th>Total Item</th>
                                <th>Created By</th> <!-- Tambah kolom Created By -->
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($row = $result->fetch_assoc()):
                                $trans_id = $row['id'];

                                // Use prepared statement for total item query
                                $sql_total_item = "SELECT SUM(jumlah) as total FROM detail_transaksi_stok WHERE transaksi_stok_id = ?";
                                $stmt_total_item = $conn->prepare($sql_total_item);

                                $total_item = 0; // Default value

                                if ($stmt_total_item) {
                                    $stmt_total_item->bind_param("i", $trans_id);
                                    $stmt_total_item->execute();
                                    $total_item_result = $stmt_total_item->get_result();

                                    if ($total_item_result && $total_item_data = $total_item_result->fetch_assoc()) {
                                        $total_item = $total_item_data['total'] ?? 0;
                                    } else {
                                         error_log("Error fetching total item for transaction ID $trans_id: " . $stmt_total_item->error);
                                    }
                                    $stmt_total_item->close();
                                } else {
                                    error_log("Error preparing total item statement: " . $conn->error);
                                }
                            ?>
                            <tr>
                                <td><?= htmlspecialchars($row['no_transaksi']) ?></td>
                                <td><?= isset($row['tanggal']) ? date('d/m/Y', strtotime($row['tanggal'])) : 'N/A' ?></td>
                                <td><?= htmlspecialchars($row['nama_gudang'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($row['nama_barang'] ?? '-') ?></td> <!-- Display aggregated nama_barang -->
                                <td><?= htmlspecialchars($row['keterangan'] ?? '-') ?></td>
                                <td><?= number_format($total_item) ?></td>
                                <td><?= htmlspecialchars($row['created_by_username'] ?? 'N/A') ?></td> <!-- Tampilkan username -->
                                <td>
                                    <a href="view.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-info">
                                        <i class='bx bx-detail'></i>
                                    </a>
                                    <?php if(isset($row['tanggal']) && date('Y-m-d') == date('Y-m-d', strtotime($row['tanggal']))): ?>
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
