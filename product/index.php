<?php
require_once __DIR__ . '/_init.php';
require_once __DIR__ . '/../templates/header.php';
require_once __DIR__ . '/../templates/navbar.php';

if (!checkAccess('product', 'view')) {
    $_SESSION['error'] = 'Anda tidak memiliki akses untuk melihat menu product!';
    header('Location: ' . url_for('dashboard.php'));
    exit();
}

$filter_session_key = 'filter_product_index';
if (isset($_GET['reset']) && (string)$_GET['reset'] === '1') {
    unset($_SESSION[$filter_session_key]);
    header('Location: index.php');
    exit();
}

$saved_filters = [];
if (isset($_SESSION[$filter_session_key]) && is_array($_SESSION[$filter_session_key])) {
    $saved_filters = $_SESSION[$filter_session_key];
}

$filter_keys = ['filter_id', 'filter_nama'];
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

$filter_id = isset($_GET['filter_id']) ? trim((string)$_GET['filter_id']) : trim((string)($saved_filters['filter_id'] ?? ''));
$filter_nama = isset($_GET['filter_nama']) ? trim((string)$_GET['filter_nama']) : trim((string)($saved_filters['filter_nama'] ?? ''));

$_SESSION[$filter_session_key] = [
    'filter_id' => $filter_id,
    'filter_nama' => $filter_nama,
];

$where = [];
$params = [];
$types = '';

if ($filter_id !== '') {
    $where[] = "CAST(p.id AS CHAR) LIKE ?";
    $params[] = "%$filter_id%";
    $types .= 's';
}

if ($filter_nama !== '') {
    $where[] = "p.nama_product LIKE ?";
    $params[] = "%$filter_nama%";
    $types .= 's';
}

$products = [];
$sql = "SELECT p.id, p.nama_product FROM product p";
if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}
$sql .= " ORDER BY p.nama_product ASC, p.id DESC";

$result = null;
if (!empty($where)) {
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $bind = [$types];
        foreach ($params as $i => $val) {
            $bind[] = &$params[$i];
        }
        call_user_func_array([$stmt, 'bind_param'], $bind);
        $stmt->execute();
        $result = $stmt->get_result();
    }
} else {
    $result = $conn->query($sql);
}

if ($result instanceof mysqli_result) {
    while ($row = $result->fetch_assoc()) {
        $products[] = [
            'id' => (int)$row['id'],
            'nama_product' => (string)($row['nama_product'] ?? ''),
        ];
    }
    $result->free();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Manajemen Product - Sistem Inventory</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.0.7/css/boxicons.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/buttons/2.2.2/css/buttons.bootstrap5.min.css" rel="stylesheet">
    <style>
        body {
            background: #ffffff;
            min-height: 100vh;
            font-size: 0.875rem;
            padding-bottom: 70px;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .page-header {
            padding: 18px 18px 14px;
            margin: 18px;
            border-radius: 12px;
            background: linear-gradient(135deg, rgba(0, 8, 249, 0.08) 0%, rgba(0, 188, 212, 0.06) 100%);
            border: 1px solid rgba(0, 8, 249, 0.12);
        }

        .page-header h2 {
            margin: 0;
            font-weight: 700;
            letter-spacing: 0.2px;
        }

        .card {
            margin: 18px;
        }

        #productTable th,
        #productTable td {
            font-size: 0.8rem;
            padding: 0.4rem 0.3rem;
            vertical-align: middle;
        }

        #productTable thead th {
            background: #0008f9 !important;
            color: white !important;
            border: none !important;
            font-weight: 600 !important;
            text-transform: uppercase !important;
            font-size: 0.75rem !important;
            letter-spacing: 0.05em !important;
            white-space: nowrap;
        }

        .dataTables_wrapper .dataTables_length,
        .dataTables_wrapper .dataTables_filter {
            margin: 0 !important;
            padding: 0 !important;
        }

        .dataTables_wrapper .dataTables_length label,
        .dataTables_wrapper .dataTables_filter label {
            margin: 0 !important;
            display: flex;
            align-items: center;
            gap: 8px;
            white-space: nowrap;
        }

        .dataTables_wrapper .dataTables_filter input {
            margin-left: 0 !important;
        }

        .dt-buttons .btn {
            font-size: 0.8rem !important;
            padding: 0.25rem 0.5rem !important;
        }

        .dt-buttons .btn .bx {
            font-size: 1rem !important;
        }
    </style>
</head>
<body>
<?php include '../templates/navbar.php'; ?>

<div class="container-fluid mt-4">
         <div class="page-header">
            <div class="d-flex justify-content-between align-items-center">
                <h2>Manajemen Product</h2>
        <div class="d-flex gap-2">
            <?php if (checkAccess('product', 'add')): ?>
                <a class="btn btn-primary" href="<?= url_for('product/create.php') ?>">
                    <i class='bx bx-plus'></i> Tambah Product
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert" style="margin: 0 18px;">
        <?= h($_SESSION['success']); unset($_SESSION['success']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert" style="margin: 0 18px;">
        <?= h($_SESSION['error']); unset($_SESSION['error']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <form method="get" class="row g-2 align-items-end mb-3">
            <div class="col-md-3">
                <label for="filter_id" class="form-label mb-1">Cari ID</label>
                <input type="text" class="form-control" id="filter_id" name="filter_id" value="<?= h($filter_id) ?>" placeholder="ID product...">
            </div>
            <div class="col-md-4">
                <label for="filter_nama" class="form-label mb-1">Cari Nama</label>
                <input type="text" class="form-control" id="filter_nama" name="filter_nama" value="<?= h($filter_nama) ?>" placeholder="Nama product...">
            </div>
            <div class="col-md-5 d-flex gap-2">
                <button type="submit" class="btn btn-primary">Filter</button>
                <a href="index.php?reset=1" class="btn btn-secondary">Reset</a>
            </div>
        </form>

        <div class="table-responsive">
            <table id="productTable" class="table table-striped">
                <thead>
                    <tr>
                        <th style="width:70px;">No</th>
                        <th>Nama Product</th>
                        <th style="width:180px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $p): ?>
                        <tr>
                            <td></td>
                            <td><?= h($p['nama_product']) ?></td>
                            <td>
                                <a class="btn btn-info btn-sm" href="<?= url_for('product/view.php?id=' . (int)$p['id']) ?>" title="Detail">
                                    <i class='bx bx-show'></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.2.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.html5.min.js"></script>

<script>
    $(document).ready(function() {
        $('#productTable').DataTable({
            dom:
                "<'row align-items-center mb-2'<'col-12 col-lg-6'B><'col-12 col-lg-6 d-flex justify-content-lg-end align-items-center gap-3 mt-2 mt-lg-0'lf>>" +
                "<'row'<'col-12'tr>>" +
                "<'row align-items-center mt-2'<'col-12 col-md-6'i><'col-12 col-md-6 d-flex justify-content-md-end'p>>",
            pageLength: 10,
            lengthMenu: [
                [10, 20, 50, 100, -1],
                [10, 20, 50, 100, 'Semua']
            ],
            buttons: [
                {
                    extend: 'excel',
                    text: '<i class="bx bx-export"></i> Export Excel',
                    className: 'btn btn-success',
                    exportOptions: {
                        columns: [0, 1]
                    }
                }
            ],
            columnDefs: [
                {
                    targets: 0,
                    searchable: false,
                    orderable: false,
                    render: function(data, type, row, meta) {
                        return meta.row + meta.settings._iDisplayStart + 1;
                    }
                },
                {
                    targets: 2,
                    searchable: false,
                    orderable: false
                }
            ],
            order: [[1, 'asc']]
        });
    });
</script>
</body>
</html>
