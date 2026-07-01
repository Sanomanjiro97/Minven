<?php
require_once __DIR__ . '/_init.php';
require_once __DIR__ . '/../includes/access_check.php';

$conn = $conn ?? null;
if (!($conn instanceof mysqli)) {
    $_SESSION['error'] = 'Koneksi database tidak tersedia.';
    header('Location: ' . url_for('product/index.php'));
    exit();
}

if (!checkAccess('product', 'view')) {
    $_SESSION['error'] = 'Anda tidak memiliki akses untuk melihat menu product!';
    header('Location: ' . url_for('dashboard.php'));
    exit();
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    $_SESSION['error'] = 'ID product tidak valid.';
    header('Location: ' . url_for('product/index.php'));
    exit();
}

$stmt = $conn->prepare("SELECT p.id, p.nama_product, p.gudang_id, g.nama_gudang, p.created_at
    FROM product p
    LEFT JOIN gudang g ON p.gudang_id = g.id
    WHERE p.id = ?
    LIMIT 1");
if (!$stmt) {
    $_SESSION['error'] = 'Gagal memuat product: ' . $conn->error;
    header('Location: ' . url_for('product/index.php'));
    exit();
}
$stmt->bind_param('i', $id);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$product) {
    $_SESSION['error'] = 'Product tidak ditemukan.';
    header('Location: ' . url_for('product/index.php'));
    exit();
}

$allowed = false;
$rows = get_accessible_gudang_list($conn);
foreach ($rows as $r) {
    if ((int)($r['id'] ?? 0) === (int)($product['gudang_id'] ?? 0)) {
        $allowed = true;
        break;
    }
}
if (!$allowed) {
    $_SESSION['error'] = 'Anda tidak memiliki akses ke gudang product ini.';
    header('Location: ' . url_for('product/index.php'));
    exit();
}

$items = [];
$sqlItems = "SELECT d.barang_id, d.qty, b.kode_barang, b.nama_barang, s.nama_satuan
    FROM product_detail d
    LEFT JOIN barang b ON d.barang_id = b.id
    LEFT JOIN satuan s ON b.satuan_id = s.id
    WHERE d.product_id = ?
    ORDER BY b.nama_barang ASC, d.id ASC";
$stmt2 = $conn->prepare($sqlItems);
if ($stmt2) {
    $stmt2->bind_param('i', $id);
    $stmt2->execute();
    $res = $stmt2->get_result();
    while ($row = $res->fetch_assoc()) {
        $items[] = [
            'barang_id' => (int)($row['barang_id'] ?? 0),
            'kode_barang' => (string)($row['kode_barang'] ?? ''),
            'nama_barang' => (string)($row['nama_barang'] ?? ''),
            'nama_satuan' => (string)($row['nama_satuan'] ?? ''),
            'qty' => (int)($row['qty'] ?? 0),
        ];
    }
    $stmt2->close();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Detail Product - Sistem Inventory</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.0.7/css/boxicons.min.css" rel="stylesheet" />
    <style>
        body { background: #f6f8fb; }
        .page-title { font-size: 1.25rem; }
        .table td, .table th { vertical-align: middle; }
        .table thead th { position: sticky; top: 0; z-index: 1; }
    </style>
</head>
<body>
<?php include '../templates/navbar.php'; ?>

<div class="container-fluid py-4">
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a class="text-decoration-none" href="<?= url_for('dashboard.php') ?>">Dashboard</a></li>
            <li class="breadcrumb-item"><a class="text-decoration-none" href="<?= url_for('product/index.php') ?>">Product</a></li>
            <li class="breadcrumb-item active" aria-current="page">Detail</li>
        </ol>
    </nav>

    <div class="card border-0 shadow-sm mb-4 overflow-hidden">
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <div class="d-flex align-items-center gap-2">
                    <i class='bx bx-detail text-primary' style="font-size:1.4rem;"></i>
                    <h1 class="mb-0 fw-bold page-title">Detail Product</h1>
                </div>
                <a class="btn btn-outline-secondary" href="<?= url_for('product/index.php') ?>">
                    <i class='bx bx-arrow-back'></i> Kembali
                </a>
            </div>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?= h($_SESSION['success']); unset($_SESSION['success']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php
                $total_qty = 0;
                foreach ($items as $it) {
                    $total_qty += (int)($it['qty'] ?? 0);
                }
            ?>

            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="text-muted small">Nama Product</div>
                            <div class="fw-semibold"><?= h($product['nama_product']) ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body d-flex flex-column">
                            <div class="text-muted small">Gudang</div>
                            <div class="fw-semibold"><?= h($product['nama_gudang'] ?? '-') ?></div>
                            <div class="mt-2 text-muted small">Total Qty: <span class="fw-semibold text-dark"><?= (int)$total_qty ?></span></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm overflow-hidden">
                <div class="card-header bg-white border-0 py-3">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                        <h5 class="mb-0">Items</h5>
                        <span class="badge bg-light text-dark border"><?= count($items) ?> item</span>
                    </div>
                </div>
                <div class="card-body pt-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width:70px;">No</th>
                                    <th>Barang</th>
                                    <th style="width:160px;">Qty</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($items)): ?>
                                    <tr>
                                        <td colspan="3" class="text-center text-muted py-4">Tidak ada item.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php $no = 1; foreach ($items as $it): ?>
                                        <tr>
                                            <td class="text-muted"><?= $no++ ?></td>
                                            <td><?= h(trim($it['kode_barang'] . ' - ' . $it['nama_barang'])) ?></td>
                                            <td><span class="fw-semibold"><?= (int)$it['qty'] ?></span> <span class="text-muted"><?= h($it['nama_satuan'] ?: '') ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
