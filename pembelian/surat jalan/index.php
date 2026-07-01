<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit();
}

include_once "../../config.php";
include_once "../../includes/access_check.php";
// checkAccess('surat_jalan_view'); // Uncomment and implement if needed

$filter_session_key = 'filter_surat_jalan_index';
if (isset($_GET['reset']) && (string)$_GET['reset'] === '1') {
    unset($_SESSION[$filter_session_key]);
    header('Location: index.php');
    exit();
}

$saved_filters = [];
if (isset($_SESSION[$filter_session_key]) && is_array($_SESSION[$filter_session_key])) {
    $saved_filters = $_SESSION[$filter_session_key];
}

$filter_keys = ['search', 'date_start', 'date_end'];
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

$search = isset($_GET['search']) ? trim((string)$_GET['search']) : trim((string)($saved_filters['search'] ?? ''));
$date_start = isset($_GET['date_start']) ? trim((string)$_GET['date_start']) : trim((string)($saved_filters['date_start'] ?? ''));
$date_end = isset($_GET['date_end']) ? trim((string)$_GET['date_end']) : trim((string)($saved_filters['date_end'] ?? ''));

$_SESSION[$filter_session_key] = [
    'search' => $search,
    'date_start' => $date_start,
    'date_end' => $date_end,
];

include_once "../../templates/header.php";
include_once "../../templates/navbar.php";

// Fetch approved Purchase Orders
$approved_pos = [];
$sql_po = "SELECT po.id, po.nomor as po_number, po.supplier_id, po.tanggal, s.nama_supplier 
           FROM purchase_order po
           LEFT JOIN supplier s ON po.supplier_id = s.id
           WHERE po.status = 'approved'
           ORDER BY po.tanggal DESC";
$result_po = $conn->query($sql_po);

if ($result_po) {
    if ($result_po->num_rows > 0) {
        while ($row_po = $result_po->fetch_assoc()) {
            $approved_pos[] = $row_po;
        }
    }
} else {
    echo "<div class='alert alert-danger'>Error fetching approved POs: " . $conn->error . "</div>";
}

// Fetch Surat Jalan data with approved PO status
$surat_jalan_list = [];
$sql = "SELECT sj.id, sj.surat_jalan_number, sj.surat_jalan_date,
               po.id as po_id, po.no_po as po_number, po.tanggal as po_date,
               s.id as supplier_id, s.nama_supplier,
               s.alamat as supplier_address,
               u.nama as created_by_name,
               po.status as po_status,
               sj.status_pembayaran,
               GROUP_CONCAT(CONCAT(b.nama_barang, ' (', sji.jumlah, ' ', COALESCE(sa.nama_satuan, ''), ')') SEPARATOR '<br>') as daftar_barang,
               COUNT(sji.id) as item_count
        FROM surat_jalan sj
        LEFT JOIN purchase_order po ON sj.po_id = po.id
        LEFT JOIN supplier s ON po.supplier_id = s.id
        LEFT JOIN users u ON CAST(sj.created_by AS UNSIGNED) = u.id
        LEFT JOIN detail_purchase_order sji ON po.id = sji.purchase_order_id
        LEFT JOIN barang b ON sji.barang_id = b.id
        LEFT JOIN conversi_po_detail cpd ON cpd.detail_purchase_order_id = sji.id
        LEFT JOIN satuan sa ON sa.id = COALESCE(cpd.satuan_asal_id, sji.satuan_id, b.satuan_id)
        GROUP BY sj.id
        ORDER BY sj.surat_jalan_date DESC";

$result = $conn->query($sql);

if ($result) {
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $surat_jalan_list[] = $row;
        }
    }
} else {
    echo "<div class='alert alert-danger'>Error fetching Surat Jalan data: " . $conn->error . "</div>";
}
?>

<!-- Main Content -->
<div class="container-fluid mt-4">
    <!-- Header -->
    <div class="row mb-3">
        <div class="col">
            <h2>Daftar Surat Jalan</h2>
        </div>
        <div class="col text-end">
            <a href="create.php" class="btn btn-primary">
                <i class='bx bx-plus'></i> Buat Surat Jalan Baru
            </a>
        </div>
    </div>

    <!-- Table Card -->
    <div class="card">
        <div class="card-body">
            <!-- Filter Section -->
            <div class="row mb-4">
                <div class="col-md-12">
                    <form method="GET" class="row g-3">
                        <div class="col-md-4">
                            <label for="search" class="form-label">Cari</label>
                            <input type="text" class="form-control" id="search" name="search" placeholder="No SJ / No PO / Supplier" value="<?= isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '' ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="date_start" class="form-label">Dari Tanggal</label>
                            <input type="date" class="form-control" id="date_start" name="date_start" value="<?= isset($_GET['date_start']) ? htmlspecialchars($_GET['date_start']) : '' ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="date_end" class="form-label">Sampai Tanggal</label>
                            <input type="date" class="form-control" id="date_end" name="date_end" value="<?= isset($_GET['date_end']) ? htmlspecialchars($_GET['date_end']) : '' ?>">
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class='bx bx-search'></i> Filter
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Table Section -->
            <div class="table-responsive">
                <table class="table table-bordered table-striped align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="text-center" style="width: 5%;">No</th>
                            <th class="text-center" style="width: 12%;">Nomor SJ</th>
                            <th class="text-center" style="width: 10%;">Tanggal SJ</th>
                            <th class="text-center" style="width: 12%;">Nomor PO</th>
                            <th class="text-center" style="width: 10%;">Tanggal PO</th>
                            <th class="text-center" style="width: 15%;">Supplier</th>
                            <th class="text-center" style="width: 20%;">Daftar Barang</th>
                            <th class="text-center" style="width: 8%;">Status PO</th>
                            <th class="text-center" style="width: 10%;">Status Pembayaran</th>
                            <th class="text-center" style="width: 8%;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($surat_jalan_list)): ?>
                            <?php $no = 1; ?>
                            <?php foreach ($surat_jalan_list as $row): ?>
                                <tr>
                                    <td class="text-center"> <?= $no++ ?> </td>
                                    <td class="fw-semibold"> <?= htmlspecialchars($row['surat_jalan_number']) ?> </td>
                                    <td class="text-center"> <?= date('d/m/Y', strtotime($row['surat_jalan_date'])) ?> </td>
                                    <td class="fw-semibold"> <?= htmlspecialchars($row['po_number']) ?> </td>
                                    <td class="text-center"> <?= date('d/m/Y', strtotime($row['po_date'])) ?> </td>
                                    <td> <?= htmlspecialchars($row['nama_supplier']) ?> </td>
                                    <td>
                                        <?php if (!empty($row['daftar_barang'])): ?>
                                            <div style="max-height: 150px; overflow-y: auto; font-size: 0.875rem;">
                                                <?= $row['daftar_barang'] ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted">Tidak ada barang</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-<?= $row['po_status'] == 'approved' ? 'success' : ($row['po_status'] == 'pending' ? 'warning' : 'secondary') ?>">
                                            <?= ucfirst(htmlspecialchars($row['po_status'])) ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <?php 
                                        $status_text = '';
                                        $badge_class = '';
                                        switch($row['status_pembayaran']) {
                                            case 'belum_dibayar':
                                                $status_text = 'Belum Dibayar';
                                                $badge_class = 'bg-danger';
                                                break;
                                            case 'sebagian':
                                                $status_text = 'Dibayar Sebagian';
                                                $badge_class = 'bg-warning';
                                                break;
                                            case 'lunas':
                                                $status_text = 'Lunas';
                                                $badge_class = 'bg-success';
                                                break;
                                            default:
                                                $status_text = 'Belum Dibayar';
                                                $badge_class = 'bg-danger';
                                        }
                                        ?>
                                        <span class="badge <?= $badge_class ?>"><?= $status_text ?></span>
                                    </td>
                                    <td class="text-center">
                                        <div class="d-flex gap-1 justify-content-center">
                                            <a href="view.php?id=<?= $row['id'] ?>" class="btn btn-info btn-sm" title="Lihat Detail">
                                                <i class='bx bx-show'></i>
                                            </a>
                                            <a href="print.php?id=<?= $row['id'] ?>" class="btn btn-secondary btn-sm" target="_blank" title="Cetak">
                                                <i class='bx bx-printer'></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="10" class="text-center py-4">
                                    <div class="text-muted">
                                        <i class='bx bx-inbox bx-lg mb-2'></i>
                                        <p class="mb-0">Tidak ada data Surat Jalan.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<style>
.table thead th {
    font-weight: 600;
    font-size: 1rem;
    background: #f8f9fa;
    border-bottom: 2px solid #dee2e6;
    color: #212529 !important;
}
.table-bordered th, .table-bordered td {
    border: 1px solid #dee2e6 !important;
    color: #212529 !important;
}
.table-hover tbody tr:hover {
    background: #f1f3f4;
}
.btn-primary.btn-sm {
    font-size: 1rem;
    padding: 0.375rem 0.75rem;
    border-radius: 5px;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 0.4rem;
}
.btn-info.btn-sm, .btn-secondary.btn-sm {
    font-size: 1rem;
    padding: 0.375rem 0.75rem;
    border-radius: 5px;
    display: flex;
    align-items: center;
    justify-content: center;
}
@media (max-width: 768px) {
    .d-flex.justify-content-between.align-items-center.mb-3 {
        flex-direction: column;
        gap: 1rem;
        align-items: stretch;
        text-align: center;
    }
    .table thead th, .table tbody td {
        font-size: 0.9rem;
        padding: 0.5rem 0.3rem;
    }
}
</style>

<?php
include_once "../../templates/footer.php";
?>
