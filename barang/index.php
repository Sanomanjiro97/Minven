<?php
session_start();
require_once '../config.php';
require_once '../includes/access_check.php';
require_once '../includes/menu_access_helper.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

// Check access untuk menu barang
if (!checkAccess('barang', 'view')) {
    $_SESSION['error'] = 'Anda tidak memiliki akses untuk melihat menu barang!';
    header('Location: ../dashboard.php');
    exit();
}

// Ambil role user dari session atau query user_roles
$user_id = $_SESSION['user_id'];
$role_ids = [];
$res = $conn->query("SELECT role_id FROM user_roles WHERE user_id = $user_id");
while ($row = $res->fetch_assoc()) $role_ids[] = $row['role_id'];
if (!check_menu_access($conn, $role_ids, 'barang')) {
    $_SESSION['error'] = 'Anda tidak memiliki akses ke menu ini!';
    header('Location: ../user/roles.php?noaccess=1');
    exit();
}

$filter_session_key = 'filter_barang_index';
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
    $where[] = "(b.kode_barang LIKE ? OR CAST(b.id AS CHAR) LIKE ?)";
    $params[] = "%$filter_id%";
    $params[] = "%$filter_id%";
    $types .= 'ss';
}

if ($filter_nama !== '') {
    $where[] = "b.nama_barang LIKE ?";
    $params[] = "%$filter_nama%";
    $types .= 's';
}

// Query untuk mengambil data barang
$sql = "SELECT 
            b.*, 
            k.nama_kategori,
            s.nama_satuan,
            sup.nama_supplier,
            u.nama as created_by_name,
            CASE WHEN b.baku_non_baku = 'baku' THEN 'Baku' ELSE 'Non-Baku' END as jenis_barang
        FROM barang b
        LEFT JOIN kategori k ON b.kategori_id = k.id
        LEFT JOIN satuan s ON b.satuan_id = s.id
        LEFT JOIN supplier sup ON b.supplier_id = sup.id
        LEFT JOIN users u ON b.created_by = u.id
";
if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}
$sql .= " ORDER BY b.kode_barang";

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

if ($result === false || $result === null) {
    $sql_fallback = "SELECT 
            b.*, 
            k.nama_kategori,
            s.nama_satuan,
            sup.nama_supplier,
            u.nama as created_by_name,
            'Non-Baku' as jenis_barang
        FROM barang b
        LEFT JOIN kategori k ON b.kategori_id = k.id
        LEFT JOIN satuan s ON b.satuan_id = s.id
        LEFT JOIN supplier sup ON b.supplier_id = sup.id
        LEFT JOIN users u ON b.created_by = u.id
    ";
    if (!empty($where)) {
        $sql_fallback .= " WHERE " . implode(" AND ", $where);
    }
    $sql_fallback .= " ORDER BY b.kode_barang";

    if (!empty($where)) {
        $stmt2 = $conn->prepare($sql_fallback);
        if (!$stmt2) {
            die("Query error: " . $conn->error);
        }
        $bind2 = [$types];
        foreach ($params as $i => $val) {
            $bind2[] = &$params[$i];
        }
        call_user_func_array([$stmt2, 'bind_param'], $bind2);
        $stmt2->execute();
        $result = $stmt2->get_result();
    } else {
        $result = $conn->query($sql_fallback);
    }
}

// Query untuk dropdown
$kategori_sql = "SELECT id, nama_kategori FROM kategori ORDER BY nama_kategori";
$kategori_result = $conn->query($kategori_sql);

$satuan_sql = "SELECT id, nama_satuan FROM satuan ORDER BY nama_satuan";
$satuan_result = $conn->query($satuan_sql);

$supplier_sql = "SELECT id, nama_supplier FROM supplier ORDER BY nama_supplier";
$supplier_result = $conn->query($supplier_sql);

$barang_list = [];
$res_all = $conn->query("SELECT id, kode_barang, nama_barang FROM barang ORDER BY kode_barang, id");
if ($res_all instanceof mysqli_result) {
    while ($r = $res_all->fetch_assoc()) {
        $barang_list[] = [
            'id' => (int)$r['id'],
            'kode_barang' => (string)($r['kode_barang'] ?? ''),
            'nama_barang' => (string)($r['nama_barang'] ?? ''),
        ];
    }
    $res_all->free();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Barang - Sistem Inventory</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.0.7/css/boxicons.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/buttons/2.2.2/css/buttons.bootstrap5.min.css" rel="stylesheet">
    <style>
        /* Penyesuaian Umum untuk Mobile Friendliness */
        body {
            background: #ffffff;
            min-height: 100vh;
            font-size: 0.875rem; /* Sedikit memperkecil font dasar untuk mobile */
            padding-bottom: 70px; /* Padding untuk navbar mobile */
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .container, .container-fluid {
            padding-left: 0.5rem; /* Mengurangi padding kontainer */
            padding-right: 0.5rem;
        }

        .card {
            margin-bottom: 0.75rem;
        }

        /* Penyesuaian untuk header halaman manajemen barang */
        .page-header-custom h2 { /* Ganti .alert h4 dengan selector yang sesuai */
            font-size: 1.1rem; /* Ukuran judul yang lebih ringkas */
            margin-bottom: 0;
        }
        .page-header-custom .btn { /* Tombol di header */
            font-size: 0.75rem; 
            padding: 0.25rem 0.5rem;
        }
         .page-header-custom .btn .bx {
            font-size: 1rem;
        }
        
        /* Penyesuaian Tabel untuk Mobile */
        .table-responsive-custom { 
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            margin-left: -0.5rem; /* Menyesuaikan dengan padding kontainer yang dikurangi */
            margin-right: -0.5rem;
        }
        .table-responsive-custom > #barangTable { /* Target spesifik #barangTable */
             width: 100% !important; 
             margin-bottom: 0; 
        }

        #barangTable th,
        #barangTable td {
            font-size: 0.8rem; 
            padding: 0.4rem 0.3rem; 
            vertical-align: middle;
        }

        #barangTable thead th {
            font-size: 0.75rem; 
            white-space: nowrap; 
            font-weight: 600;
            padding-top: 0.5rem; 
            padding-bottom: 0.5rem;
        }
        
        #barangTable td {
            word-break: break-word; 
        }

        /* Tombol aksi di tabel */
        #barangTable .btn-sm {
            padding: 0.15rem 0.3rem;
            font-size: 0.7rem;
        }
        #barangTable .btn-sm .bx {
            font-size: 0.9rem; 
        }
        
        /* Penyesuaian Modal */
        .modal-title {
            font-size: 1.05rem;
        }
        .modal-body .form-label {
            font-size: 0.85rem;
            margin-bottom: 0.2rem;
        }
        .modal-body .form-control,
        .modal-body .form-select {
            font-size: 0.875rem;
            padding: 0.3rem 0.6rem; 
        }
        .modal-footer .btn {
            font-size: 0.875rem;
        }

        /* DataTables Buttons */
        .dt-buttons .btn {
            font-size: 0.8rem !important; /* Sesuaikan ukuran font tombol DataTables */
            padding: 0.25rem 0.5rem !important;
        }
        .dt-buttons .btn .bx {
            font-size: 1rem !important;
        }

        /* Table header styling for consistency */
        #barangTable thead th {
            background: #0008f9 !important;
            color: white !important;
            border: none !important;
            font-weight: 600 !important;
            text-transform: uppercase !important;
            font-size: 0.75rem !important;
            letter-spacing: 0.05em !important;
        }

        /* DataTables header styling override */
        .dataTables_wrapper .dataTables_length,
        .dataTables_wrapper .dataTables_filter,
        .dataTables_wrapper .dataTables_info,
        .dataTables_wrapper .dataTables_processing,
        .dataTables_wrapper .dataTables_paginate {
            color: #333;
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

        /* Media query untuk penyesuaian lebih lanjut di layar sangat kecil (misal, di bawah 400px) */
        @media (max-width: 576px) { /* Disesuaikan dengan breakpoint Bootstrap sm */
            .page-header-custom {
                flex-direction: column;
                align-items: flex-start !important;
            }
            .page-header-custom h2 {
                margin-bottom: 0.5rem; /* Beri jarak jika tombol pindah ke bawah */
            }
            .page-header-custom div:not(:first-child) .btn { /* Target tombol di div kedua */
                 font-size: 0.7rem;
                 padding: 0.2rem 0.4rem;
            }
             .page-header-custom div:not(:first-child) .btn .bx {
                font-size: 0.9rem;
            }
        }

        @media (max-width: 400px) {
            body {
                font-size: 0.85rem;
            }
            .page-header-custom h2 {
                font-size: 1rem;
            }
            
            #barangTable th,
            #barangTable td {
                font-size: 0.75rem; 
                padding: 0.3rem 0.2rem;
            }
            #barangTable thead th {
                font-size: 0.7rem;
            }
            #barangTable .btn-sm {
                font-size: 0.65rem;
                padding: 0.1rem 0.25rem;
            }
            #barangTable .btn-sm .bx {
                font-size: 0.8rem;
            }
        }
    </style>
</head>
<body>
    <?php include '../templates/navbar.php'; ?>

        
        <div class="page-header fade-in">
            <div class="d-flex justify-content-between align-items-center">
                <h2>Manajemen Barang</h2>
                <div class="d-flex gap-2">
                    <?php if (checkAccess('barang', 'view')): ?>
                    <button class="btn btn-success hover-lift" onclick="downloadTemplate()">
                        <i class='bx bx-download'></i> Download Template
                    </button>
                    <button class="btn btn-info hover-lift" data-bs-toggle="modal" data-bs-target="#importModal">
                        <i class='bx bx-import'></i> Import
                    </button>
                    <?php endif; ?>
                    <?php if (checkAccess('barang', 'add')): ?>
                    <button class="btn btn-primary hover-lift" data-bs-toggle="modal" data-bs-target="#addBarangModal">
                        <i class='bx bx-plus'></i> Tambah Barang
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show slide-up" role="alert">
                <?= $_SESSION['success']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show slide-up" role="alert">
                <?= $_SESSION['error']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <div class="card hover-lift scale-in">
            <div class="card-body">
                <form method="get" class="row g-2 align-items-end mb-3">
                    <div class="col-md-3">
                        <label for="filter_id" class="form-label mb-1">Cari Kode/ID</label>
                        <input type="text" class="form-control" id="filter_id" name="filter_id" value="<?= htmlspecialchars($filter_id) ?>" placeholder="Kode barang...">
                    </div>
                    <div class="col-md-4">
                        <label for="filter_nama" class="form-label mb-1">Cari Nama</label>
                        <input type="text" class="form-control" id="filter_nama" name="filter_nama" value="<?= htmlspecialchars($filter_nama) ?>" placeholder="Nama barang...">
                    </div>
                    <div class="col-md-5 d-flex gap-2">
                        <button type="submit" class="btn btn-primary">Filter</button>
                        <a href="index.php?reset=1" class="btn btn-secondary">Reset</a>
                    </div>
                </form>
                <div class="table-responsive">
                    <table id="barangTable" class="table table-striped">
                        <thead>
                            <tr>
                            <th>No</th>
                            <th>Kode Barang</th>
                            <th>Barcode (Item)</th>
                            <th>Barcode (Konversi)</th>
                            <th>Nama Barang</th>
                            <th>Supplier</th>
                            <th>Kategori</th>
                            <th>Satuan</th>
                            <th>Jenis</th>
                            <th>Par Stock</th>
                            <th>Harga PO</th>
                            <th>Harga Satuan Beli</th>
                            <th>Dibuat Oleh</th>
                            <th>Tanggal Dibuat</th>
                            <th>Tanggal Kadaluarsa</th>
                            <th>Foto</th> <!-- Tambahkan kolom Foto -->
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $no = 1; ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?= $no++ ?></td>
                                <td><?= htmlspecialchars($row['kode_barang']) ?></td>
                                <td><?= htmlspecialchars($row['barcode'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($row['barcode_dus'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($row['nama_barang']) ?></td>
                                <td><?= htmlspecialchars($row['nama_supplier']) ?></td>
                                <td><?= htmlspecialchars($row['nama_kategori']) ?></td>
                                <td><?= htmlspecialchars($row['nama_satuan']) ?></td>
                                <td>
                                    <div class="form-check form-switch d-inline-block">
                                        <input class="form-check-input jenis-checkbox" type="checkbox" 
                                               id="jenis-checkbox-<?= $row['id'] ?>"
                                               data-barang-id="<?= $row['id'] ?>" 
                                               <?= $row['jenis_barang'] == 'Baku' ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="jenis-checkbox-<?= $row['id'] ?>">
                                            <?= htmlspecialchars($row['jenis_barang']) ?>
                                        </label>
                                    </div>
                                </td>
                                <td><?= htmlspecialchars($row['stok_minimum']) ?></td>
                                <td><?= number_format($row['harga_po'], 2) ?></td>
                                <td><?= number_format($row['harga_beli'], 2) ?></td>
                                <td><?= htmlspecialchars($row['created_by_name']) ?></td>
                                <td><?= date('d/m/Y', strtotime($row['created_at'])) ?></td>
                                <td><?= $row['expired_at'] ? date('d/m/Y', strtotime($row['expired_at'])) : '-' ?></td>
                                <td>
                                    <?php
                                    $imgPath = '../uploads/barang/' . htmlspecialchars($row['gambar']);
                                    if (!empty($row['gambar']) && file_exists($imgPath)): ?>
                                        <img src="<?= $imgPath ?>" alt="Foto" 
                                             style="width:90px;height:60px;object-fit:cover;cursor:pointer;" 
                                             onclick="viewPhoto('<?= $imgPath ?>')">
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (checkAccess('barang', 'setup_split')): ?>
                                    <button type="button"
                                            class="btn btn-sm btn-info hover-lift btn-setup-split"
                                            title="Setup Split"
                                            data-id="<?= (int)$row['id'] ?>"
                                            data-kode="<?= htmlspecialchars((string)$row['kode_barang']) ?>"
                                            data-nama="<?= htmlspecialchars((string)$row['nama_barang']) ?>">
                                        <i class='bx bx-git-merge'></i>
                                    </button>
                                    <?php endif; ?>
                                    <?php if (checkAccess('barang', 'edit')): ?>
                                    <button class="btn btn-sm btn-warning hover-lift" onclick="editBarang(<?= $row['id'] ?>)">
                                        <i class='bx bx-edit'></i>
                                    </button>
                                    <?php endif; ?>
                                    <?php if (checkAccess('barang', 'delete')): ?>
                                    <button class="btn btn-sm btn-danger hover-lift" onclick="deleteBarang(<?= $row['id'] ?>)">
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

<!-- Modal Tambah Barang -->
<div class="modal fade" id="addBarangModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class='bx bx-plus-circle me-2'></i>
                        Tambah Barang
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="process.php" method="post" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add">
                        <input type="hidden" name="created_by" value="<?= $_SESSION['user_id'] ?>">
                        
                        <div class="row">
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label class="form-label" for="kode_barang">Kode Barang <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="kode_barang" name="kode_barang" required>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label class="form-label" for="barcode">Barcode per Item</label>
                                    <input type="text" class="form-control" id="barcode" name="barcode" autocomplete="off">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label class="form-label" for="barcode_dus">Barcode Konversi</label>
                                    <input type="text" class="form-control" id="barcode_dus" name="barcode_dus" autocomplete="off">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label class="form-label" for="nama_barang">Nama Barang<span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="nama_barang" name="nama_barang" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label" for="supplier_id">Supplier<span class="text-danger">*</span></label>
                                    <select class="form-select" id="supplier_id" name="supplier_id" required>
                                        <option value="">Pilih Supplier</option>
                                        <?php 
                                        $supplier_result->data_seek(0);
                                        while ($sup = $supplier_result->fetch_assoc()): ?>
                                            <option value="<?= $sup['id'] ?>"><?= htmlspecialchars($sup['nama_supplier']) ?></option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label" for="kategori_id">Kategori<span class="text-danger">*</span></label>
                                    <select class="form-select" id="kategori_id" name="kategori_id" required>
                                        <option value="">Pilih Kategori</option>
                                        <?php while ($kat = $kategori_result->fetch_assoc()): ?>
                                            <option value="<?= $kat['id'] ?>"><?= htmlspecialchars($kat['nama_kategori']) ?></option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label" for="satuan_id">Satuan<span class="text-danger">*</span></label>
                                    <select class="form-select" id="satuan_id" name="satuan_id" required>
                                        <option value="">Pilih Satuan</option>
                                        <?php while ($sat = $satuan_result->fetch_assoc()): ?>
                                            <option value="<?= $sat['id'] ?>"><?= htmlspecialchars($sat['nama_satuan']) ?></option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Jenis Barang<span class="text-danger">*</span></label>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" id="baku" name="baku_non_baku" value="baku" required>
                                        <label class="form-check-label" for="baku">Baku</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" id="non_baku" name="baku_non_baku" value="non_baku" checked>
                                        <label class="form-check-label" for="non_baku">Non-Baku</label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label" for="stok_minimum">Par Stock<span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" id="stok_minimum" name="stok_minimum" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label" for="harga_beli">Harga Satuan</label>
                                    <input type="number" step="0.01" class="form-control" id="harga_beli" name="harga_beli">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label" for="harga_po">Harga PO</label>
                                    <input type="number" step="0.01" class="form-control" id="harga_po" name="harga_po">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label" for="expired_at">Tanggal Kadaluarsa</label>
                                    <input type="date" class="form-control" id="expired_at" name="expired_at">
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label" for="gambar">Gambar</label>
                            <input type="file" class="form-control" id="gambar" name="gambar" accept="image/*" onchange="previewImage(event, 'previewTambah')">
                            <div class="mt-2">
                                <img id="previewTambah" src="#" alt="Preview" style="display:none; width:150px; height:100px; object-fit:cover; border-radius: 8px;">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary hover-lift" data-bs-dismiss="modal">
                            <i class='bx bx-x me-1'></i> Batal
                        </button>
                        <button type="submit" class="btn btn-primary hover-lift">
                            <i class='bx bx-save me-1'></i> Simpan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Import -->
    <div class="modal fade" id="importModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class='bx bx-import me-2'></i>
                        Import Data Barang
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="import_proses.php" method="post" enctype="multipart/form-data" id="importForm">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label" for="import_file">File Excel</label>
                            <input type="file" class="form-control" id="import_file" name="file_barang" accept=".xls,.xlsx,.csv" required>
                            <small class="text-muted">Format yang didukung: XLS, XLSX, CSV</small>
                        </div>
                        <div class="alert alert-info">
                            <div class="d-flex align-items-start">
                                <i class='bx bx-info-circle me-2 mt-1'></i>
                                <div>
                                    <strong>Catatan:</strong><br>
                                    - Pastikan menggunakan template yang sudah disediakan<br>
                                    - Kolom dengan tanda * wajib diisi<br>
                                    - Jangan mengubah urutan kolom pada template
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary hover-lift" data-bs-dismiss="modal">
                            <i class='bx bx-x me-1'></i> Batal
                        </button>
                        <button type="submit" class="btn btn-primary hover-lift">
                            <i class='bx bx-upload me-1'></i> Import
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

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
            $('#barangTable').DataTable({
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
                            columns: [0,1,2,3,4,5,6,7,8,9,10,11,12]
                        }
                    }
                ]
            });

            $('#barcode').on('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    $('#barcode_dus').trigger('focus');
                }
            });
            $('#barcode_dus').on('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    $('#nama_barang').trigger('focus');
                }
            });
            $('#edit_barcode').on('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    $('#edit_barcode_dus').trigger('focus');
                }
            });
            $('#edit_barcode_dus').on('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    $('#edit_nama_barang').trigger('focus');
                }
            });
        });

        function editBarang(id) {
            $.ajax({
                url: 'get_barang.php',
                method: 'POST',
                data: {id: id},
                dataType: 'json',
                success: function(response) {
                    $('#editBarangModal input[name="id"]').val(response.id);
                    $('#editBarangModal input[name="kode_barang"]').val(response.kode_barang);
                    $('#editBarangModal input[name="barcode"]').val(response.barcode);
                    $('#editBarangModal input[name="barcode_dus"]').val(response.barcode_dus || '');
                    $('#editBarangModal input[name="nama_barang"]').val(response.nama_barang);
                    $('#editBarangModal select[name="supplier_id"]').val(response.supplier_id);
                    $('#editBarangModal select[name="kategori_id"]').val(response.kategori_id);
                    $('#editBarangModal select[name="satuan_id"]').val(response.satuan_id);
                    $('#editBarangModal input[name="stok_minimum"]').val(response.stok_minimum);
                    $('#editBarangModal input[name="harga_beli"]').val(response.harga_beli);
                    $('#editBarangModal input[name="harga_po"]').val(response.harga_po);
                    $('#editBarangModal input[name="expired_at"]').val(response.expired_at);
                    
                    $('#editBarangModal').modal('show');
                }
            });
        }

        function deleteBarang(id) {
            if(confirm('Apakah Anda yakin ingin menghapus barang ini?')) {
                window.location.href = 'process.php?action=delete&id=' + id;
            }
        }

        function downloadTemplate() {
            window.location.href = 'download_template.php';
        }
    </script>

    <!-- Modal Edit Barang -->
    <div class="modal fade" id="editBarangModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Barang</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="process.php" method="post" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" id="edit_id" name="id">
                        
                        <div class="mb-3">
                            <label class="form-label" for="edit_kode_barang">Kode Barang</label>
                            <input type="text" class="form-control" id="edit_kode_barang" name="kode_barang" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label" for="edit_barcode">Barcode (Item)</label>
                            <input type="text" class="form-control" id="edit_barcode" name="barcode">
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="edit_barcode_dus">Barcode Konversi</label>
                            <input type="text" class="form-control" id="edit_barcode_dus" name="barcode_dus" autocomplete="off">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label" for="edit_nama_barang">Nama Barang</label>
                            <input type="text" class="form-control" id="edit_nama_barang" name="nama_barang" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label" for="edit_supplier_id">Supplier</label>
                            <select class="form-select" id="edit_supplier_id" name="supplier_id" required>
                                <option value="">Pilih Supplier</option>
                                <?php 
                                $supplier_result->data_seek(0);
                                while ($sup = $supplier_result->fetch_assoc()): ?>
                                    <option value="<?= $sup['id'] ?>"><?= htmlspecialchars($sup['nama_supplier']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label" for="edit_kategori_id">Kategori</label>
                            <select class="form-select" id="edit_kategori_id" name="kategori_id" required>
                                <option value="">Pilih Kategori</option>
                                <?php 
                                $kategori_result->data_seek(0);
                                while ($kat = $kategori_result->fetch_assoc()): ?>
                                    <option value="<?= $kat['id'] ?>"><?= htmlspecialchars($kat['nama_kategori']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label" for="edit_satuan_id">Satuan</label>
                            <select class="form-select" id="edit_satuan_id" name="satuan_id" required>
                                <option value="">Pilih Satuan</option>
                                <?php 
                                $satuan_result->data_seek(0);
                                while ($sat = $satuan_result->fetch_assoc()): ?>
                                    <option value="<?= $sat['id'] ?>"><?= htmlspecialchars($sat['nama_satuan']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label" for="edit_stok_minimum">Par Stock</label>
                            <input type="number" class="form-control" id="edit_stok_minimum" name="stok_minimum" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label" for="edit_harga_beli">Harga</label>
                            <input type="number" step="0.01" class="form-control" id="edit_harga_beli" name="harga_beli">
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="edit_harga_po">Harga Satuan PO</label>
                            <input type="number" step="0.01" class="form-control" id="edit_harga_po" name="harga_po">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label" for="edit_expired_at">Tanggal Kadaluarsa</label>
                            <input type="date" class="form-control" id="edit_expired_at" name="expired_at">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label" for="edit_gambar">Gambar</label>
                            <input type="file" class="form-control" id="edit_gambar" name="gambar" accept="image/*">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        $(document).ready(function() {
            $('#supplierSelect').change(function() {
                const supplierId = $(this).val();
                const barangSelect = $('#barangSelect');
                
                barangSelect.empty().prop('disabled', true);
                
                if (supplierId) {
                    barangSelect.append('<option value="">Loading...</option>');
                    
                    $.ajax({
                        url: '../ajax/get_barang_by_supplier.php',
                        method: 'POST',
                        data: { supplier_id: supplierId },
                        dataType: 'json',
                        success: function(data) {
                            barangSelect.empty().append('<option value="">Pilih Barang</option>');
                            
                            if (data && data.length > 0) {
                                data.forEach(barang => {
                                    barangSelect.append(
                                        `<option value="${barang.id}">
                                            ${barang.kode_barang} - ${barang.nama_barang}
                                        </option>`
                                    );
                                });
                                barangSelect.prop('disabled', false);
                            } else {
                                barangSelect.append('<option value="">Tidak ada barang untuk supplier ini</option>');
                            }
                        },
                        error: function() {
                            barangSelect.empty().append('<option value="">Error memuat data</option>');
                        }
                    });
                } else {
                    barangSelect.empty().append('<option value="">Pilih Supplier terlebih dahulu</option>');
                }
            });
        });
    </script>
    <script>
        function previewImage(event, previewId) {
            const input = event.target;
            const preview = document.getElementById(previewId);
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                }
                reader.readAsDataURL(input.files[0]);
            } else {
                preview.src = '#';
                preview.style.display = 'none';
            }
        }
    </script>

    <?php if (checkAccess('barang', 'setup_split')): ?>
    <div class="modal fade" id="splitSetupModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Setup Split Barang</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="parent_barang_id" value="">
                    <div class="mb-2">
                        <div class="fw-semibold" id="parent_barang_label"></div>
                        <div class="text-muted small">Pilih barang yang menjadi hasil split untuk barang ini.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Daftar Barang Split</label>
                        <input type="text" id="split_search" class="form-control mb-2" placeholder="Cari kode / nama barang...">
                        <div id="split_barang_list" class="border rounded p-2" style="max-height: 50vh; overflow:auto;">
                            <?php foreach ($barang_list as $b): ?>
                                <?php $sid = (int)$b['id']; ?>
                                <div class="form-check split-item-row" data-text="<?= htmlspecialchars(strtolower((string)$b['kode_barang'] . ' ' . (string)$b['nama_barang'])) ?>">
                                    <input class="form-check-input split-item" type="checkbox" value="<?= $sid ?>" id="split_item_<?= $sid ?>">
                                    <label class="form-check-label" for="split_item_<?= $sid ?>">
                                        <?= htmlspecialchars((string)$b['kode_barang']) ?> - <?= htmlspecialchars((string)$b['nama_barang']) ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="text-muted small mt-2" id="split_selected_count"></div>
                    </div>
                    <div class="alert alert-warning mb-0">
                        Jika daftar split dikosongkan, maka barang ini tidak akan muncul opsi split saat konfirmasi PO.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="button" class="btn btn-primary" id="btnSaveSplitSetup">Simpan</button>
                </div>
            </div>
        </div>
    </div>

    <div class="position-fixed bottom-0 end-0 p-3" style="z-index: 1080">
        <div id="splitToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="toast-header">
                <strong class="me-auto" id="splitToastTitle">Info</strong>
                <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
            <div class="toast-body" id="splitToastBody"></div>
        </div>
    </div>

    <script>
        $(document).ready(function() {
            const modalEl = document.getElementById('splitSetupModal');
            const modal = modalEl ? new bootstrap.Modal(modalEl) : null;
            const toastEl = document.getElementById('splitToast');
            const toast = toastEl ? new bootstrap.Toast(toastEl, { delay: 2500 }) : null;

            function showToast(title, message, ok) {
                if (!toastEl || !toast) return;
                $('#splitToastTitle').text(title || 'Info');
                $('#splitToastBody').text(message || '');
                toastEl.classList.remove('text-bg-success', 'text-bg-danger');
                toastEl.classList.add(ok ? 'text-bg-success' : 'text-bg-danger');
                toast.show();
            }

            function updateSelectedCount() {
                const count = $('.split-item:checked').length;
                $('#split_selected_count').text(count > 0 ? `Terpilih: ${count} item` : '');
            }

            async function loadSetup(parentId) {
                const resp = await fetch(`process.php?action=get_split_setup&id=${encodeURIComponent(parentId)}`);
                const json = await resp.json();
                if (!resp.ok || !json || json.status !== 'success') {
                    throw new Error(json?.message || 'Gagal mengambil setup');
                }
                return json;
            }

            async function saveSetup(parentId, splitIds) {
                const form = new FormData();
                form.append('action', 'save_split_setup');
                form.append('parent_barang_id', String(parentId));
                for (const id of splitIds) {
                    form.append('split_barang_ids[]', String(id));
                }
                const resp = await fetch('process.php', { method: 'POST', body: form });
                const json = await resp.json();
                if (!resp.ok || !json || json.status !== 'success') {
                    throw new Error(json?.message || 'Gagal menyimpan setup');
                }
                return json;
            }

            function setParentDisabled(parentId) {
                $('.split-item').each(function() {
                    const isParent = String($(this).val()) === String(parentId);
                    $(this).prop('disabled', isParent);
                    if (isParent) {
                        $(this).prop('checked', false);
                    }
                });
            }

            function resetFilter() {
                $('#split_search').val('');
                $('.split-item-row').each(function() {
                    this.style.display = '';
                });
            }

            $('#split_search').on('input', function() {
                const q = String($(this).val() || '').trim().toLowerCase();
                $('.split-item-row').each(function() {
                    const t = String($(this).data('text') || '');
                    const checked = $(this).find('.split-item').prop('checked');
                    const match = q === '' || t.includes(q) || checked;
                    this.style.display = match ? '' : 'none';
                });
            });

            $(document).on('change', '.split-item', function() {
                updateSelectedCount();
            });

            $(document).on('click', '.btn-setup-split', async function() {
                const parentId = String($(this).data('id') || '');
                const kode = String($(this).data('kode') || '');
                const nama = String($(this).data('nama') || '');
                if (!parentId) return;

                $('#parent_barang_id').val(parentId);
                $('#parent_barang_label').text(`${kode} - ${nama}`);

                $('.split-item').prop('checked', false);
                resetFilter();
                setParentDisabled(parentId);
                updateSelectedCount();

                try {
                    const setup = await loadSetup(parentId);
                    const selected = new Set((setup.split_barang_ids || []).map(String).filter(id => id && id !== parentId));
                    $('.split-item').each(function() {
                        const v = String($(this).val() || '');
                        if (selected.has(v)) {
                            $(this).prop('checked', true);
                        }
                    });
                    updateSelectedCount();
                } catch (err) {
                    showToast('Gagal', err?.message || 'Gagal mengambil setup', false);
                }

                modal?.show();
            });

            $('#btnSaveSplitSetup').on('click', async function() {
                const parentId = String($('#parent_barang_id').val() || '');
                const selected = $('.split-item:checked').map(function() { return String($(this).val() || ''); }).get().filter(id => id && id !== parentId);
                const btn = this;
                btn.disabled = true;
                try {
                    await saveSetup(parentId, selected);
                    modal?.hide();
                    showToast('Berhasil', 'Setup split tersimpan', true);
                } catch (err) {
                    showToast('Gagal', err?.message || 'Gagal menyimpan setup', false);
                } finally {
                    btn.disabled = false;
                }
            });
        });
    </script>
    <?php endif; ?>

<!-- Add this modal at the end of your body, before </body> -->
<div class="modal fade" id="viewPhotoModal" tabindex="-1" aria-labelledby="viewPhotoModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewPhotoModalLabel">Foto Barang</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <img id="modalPhoto" src="#" alt="Foto Barang"
                     style="display:block; margin-left:auto; margin-right:auto; background:#fff; max-width:100%; max-height:60vh; width:auto; height:auto; border-radius:8px; box-shadow:0 2px 8px rgba(0,0,0,0.08);">
            </div>
        </div>
    </div>
</div>

<script>
    function viewPhoto(imgSrc) {
        $('#modalPhoto').attr('src', imgSrc);
        $('#viewPhotoModal').modal('show');
    }
    </script>
    <script>
    // Fungsi untuk menangani perubahan checkbox jenis barang
    document.addEventListener('DOMContentLoaded', function() {
        const checkboxes = document.querySelectorAll('.jenis-checkbox');
        
        checkboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const barangId = this.getAttribute('data-barang-id');
                const isBaku = this.checked ? 'baku' : 'non_baku';
                
                // Kirim permintaan AJAX untuk update database
                fetch('process_jenis.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `barang_id=${barangId}&baku_non_baku=${isBaku}&action=update_jenis`
                })
                .then(response => {
                    // Check if response is JSON before parsing
                    const contentType = response.headers.get('content-type');
                    if (contentType && contentType.includes('application/json')) {
                        return response.json();
                    } else {
                        // If not JSON, read as text to see what we got
                        return response.text().then(text => {
                            throw new Error(`Server returned non-JSON response: ${text.substring(0, 100)}`);
                        });
                    }
                })
                .then(data => {
                    if (data.success) {
                        // Update label sesuai dengan status baru
                        const label = this.nextElementSibling;
                        label.textContent = isBaku === 'baku' ? 'Baku' : 'Non-Baku';
                        
                        // Tampilkan pesan sukses
                        showToast('Jenis barang berhasil diupdate', 'success');
                    } else {
                        // Kembalikan ke state sebelumnya jika gagal
                        this.checked = !this.checked;
                        showToast('Gagal mengupdate jenis barang', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    this.checked = !this.checked;
                    showToast('Terjadi kesalahan', 'error');
                });
            });
        });
    });
    
    // Fungsi untuk menampilkan toast notification
    function showToast(message, type = 'success') {
        // Hapus toast sebelumnya jika ada
        const existingToast = document.getElementById('jenisToast');
        if (existingToast) {
            existingToast.remove();
        }
        
        const toast = document.createElement('div');
        toast.id = 'jenisToast';
        toast.className = `toast align-items-center text-white bg-${type === 'success' ? 'success' : 'danger'} border-0`;
        toast.setAttribute('role', 'alert');
        toast.setAttribute('aria-live', 'assertive');
        toast.setAttribute('aria-atomic', 'true');
        
        toast.innerHTML = `
            <div class="d-flex">
                <div class="toast-body">
                    ${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        `;
        
        document.body.appendChild(toast);
        
        const bsToast = new bootstrap.Toast(toast);
        bsToast.show();
        
        // Hapus toast setelah 3 detik
        setTimeout(() => {
            if (toast.parentNode) {
                toast.remove();
            }
        }, 3000);
    }
    </script>
</body>
</html>
