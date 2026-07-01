<?php
session_start();
require_once '../../config.php';
require_once '../../includes/access_check.php';
require_once '../../includes/menu_access_helper.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit();
}

// Check access untuk menu purchase_order
if (!checkAccess('purchase_order', 'view')) {
    $_SESSION['error'] = 'Anda tidak memiliki akses untuk melihat menu purchase order!';
    header('Location: ../../dashboard.php');
    exit();
}

// Access control is handled by checkAccess() function calls

// Ambil daftar supplier untuk filter
$supplier_filter_result = $conn->query("SELECT id, nama_supplier FROM supplier ORDER BY nama_supplier");

// Ambil daftar gudang sesuai hak akses user
$gudang_list = [];
if (function_exists('get_accessible_gudang_list')) {
    $allowed = get_accessible_gudang_list($conn);
    foreach ($allowed as $g) {
        $gudang_list[] = [
            'id' => (int)$g['id'],
            'nama_gudang' => (string)$g['nama_gudang']
        ];
    }
}

 $filter_session_key = 'filter_pembelian_po_index';
if (isset($_GET['reset']) && (string)$_GET['reset'] === '1') {
    unset($_SESSION[$filter_session_key]);
    header('Location: index.php');
    exit();
}

$saved_filters = [];
if (isset($_SESSION[$filter_session_key]) && is_array($_SESSION[$filter_session_key])) {
    $saved_filters = $_SESSION[$filter_session_key];
}

$filter_keys = ['no_po', 'supplier_id', 'status', 'tanggal_dari', 'tanggal_sampai'];
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

// Ambil filter dari GET
$filter_no_po = isset($_GET['no_po']) ? trim((string)$_GET['no_po']) : trim((string)($saved_filters['no_po'] ?? ''));
$filter_supplier = isset($_GET['supplier_id']) ? trim((string)$_GET['supplier_id']) : trim((string)($saved_filters['supplier_id'] ?? ''));
$filter_status = isset($_GET['status']) ? trim((string)$_GET['status']) : trim((string)($saved_filters['status'] ?? ''));
$filter_tanggal_dari = isset($_GET['tanggal_dari']) ? trim((string)$_GET['tanggal_dari']) : trim((string)($saved_filters['tanggal_dari'] ?? ''));
$filter_tanggal_sampai = isset($_GET['tanggal_sampai']) ? trim((string)$_GET['tanggal_sampai']) : trim((string)($saved_filters['tanggal_sampai'] ?? ''));

$_SESSION[$filter_session_key] = [
    'no_po' => $filter_no_po,
    'supplier_id' => $filter_supplier,
    'status' => $filter_status,
    'tanggal_dari' => $filter_tanggal_dari,
    'tanggal_sampai' => $filter_tanggal_sampai,
];

// Build WHERE
$where = [];
$params = [];
$types = '';
if ($filter_no_po !== '') {
    $where[] = "(po.no_po LIKE ? OR s.nama_supplier LIKE ? OR u.nama LIKE ?)";
    $params[] = "%$filter_no_po%";
    $params[] = "%$filter_no_po%";
    $params[] = "%$filter_no_po%";
    $types .= 'sss';
}
if ($filter_supplier !== '') {
    $where[] = "po.supplier_id = ?";
    $params[] = $filter_supplier;
    $types .= 'i';
}

// Ambil pengaturan WhatsApp
$wa_config_res = $conn->query("SELECT * FROM setup_whatsapp WHERE id = 1");
$wa_config = $wa_config_res ? $wa_config_res->fetch_assoc() : null;
$wa_active = $wa_config && $wa_config['is_active'] == 1;
if ($filter_status !== '') {
    $where[] = "po.status = ?";
    $params[] = $filter_status;
    $types .= 's';
}
if ($filter_tanggal_dari !== '') {
    $where[] = "po.tanggal >= ?";
    $params[] = $filter_tanggal_dari;
    $types .= 's';
}
if ($filter_tanggal_sampai !== '') {
    $where[] = "po.tanggal <= ?";
    $params[] = $filter_tanggal_sampai;
    $types .= 's';
}

// Query untuk mengambil daftar PO dengan total yang benar
$sql = "SELECT po.*, 
        s.nama_supplier, 
        s.telepon,
        u.nama as created_by_name,
        keterangan_complete,
        
        (
            SELECT COALESCE(SUM(
                CASE
                    WHEN dpo.total_harga IS NOT NULL AND dpo.total_harga <> 0 THEN dpo.total_harga
                    ELSE (dpo.jumlah * dpo.harga_satuan)
                END
            ), 0)
            FROM detail_purchase_order dpo
            WHERE dpo.purchase_order_id = po.id
            AND (dpo.status IS NULL OR dpo.status != 'rejected')
        ) as total_harga_calc,
        (
            SELECT GROUP_CONCAT(b.nama_barang SEPARATOR ', ')
            FROM detail_purchase_order dpo
            JOIN barang b ON dpo.barang_id = b.id
            WHERE dpo.purchase_order_id = po.id
            AND (dpo.status IS NULL OR dpo.status != 'rejected')
        ) as item_names,
        (
            SELECT COALESCE(SUM(jumlah), 0)
            FROM detail_purchase_order 
            WHERE purchase_order_id = po.id
            AND (status IS NULL OR status != 'rejected')
        ) as total_item_calc
        FROM purchase_order po
        LEFT JOIN supplier s ON po.supplier_id = s.id
        LEFT JOIN users u ON po.created_by = u.id";
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY po.tanggal DESC, po.id DESC';

$stmt = $conn->prepare($sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

if ($result === false) {
    // Log the error for debugging
    error_log("SQL Error: " . $conn->error);
    die("Error executing query: " . htmlspecialchars($conn->error));
}

// Now proceed with fetch_assoc() only if we have a valid result
// Fungsi untuk format Rupiah di PHP
function formatRupiah($angka) {
    $hasil_rupiah = "Rp " . number_format($angka, 0, ',', '.');
    return $hasil_rupiah;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Purchase Order - Sistem Inventory</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.0.7/css/boxicons.min.css" rel="stylesheet">
    <link href="https://code.jquery.com/ui/1.13.2/themes/ui-lightness/jquery-ui.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const navCollapseEl = document.getElementById('navbarNav');
            const mobileMenuEl = document.getElementById('minvenMobileMenu');
            const navToggleBtn = document.querySelector('[data-bs-target="#navbarNav"]');
            const mobileMenuBtn = document.querySelector('[data-bs-target="#minvenMobileMenu"]');
            const dropdownToggles = document.querySelectorAll('[data-bs-toggle="dropdown"]');

            const collapseInstance = navCollapseEl ? bootstrap.Collapse.getOrCreateInstance(navCollapseEl, { toggle: false }) : null;
            const offcanvasInstance = mobileMenuEl ? bootstrap.Offcanvas.getOrCreateInstance(mobileMenuEl) : null;

            const closeAllMenus = () => {
                dropdownToggles.forEach((toggle) => {
                    const dropdown = new bootstrap.Dropdown(toggle);
                    setTimeout(() => dropdown.hide(), 0);
                });
                if (collapseInstance) {
                    collapseInstance.hide();
                }
                if (offcanvasInstance) {
                    offcanvasInstance.hide();
                }
                if (navToggleBtn) {
                    navToggleBtn.setAttribute('aria-expanded', 'false');
                }
                if (mobileMenuBtn) {
                    mobileMenuBtn.setAttribute('aria-expanded', 'false');
                }
            };

            dropdownToggles.forEach((toggle) => {
                toggle.addEventListener('click', (event) => {
                    event.preventDefault();
                    event.stopPropagation();
                    setTimeout(() => {
                        new bootstrap.Dropdown(toggle).show();
                    }, 0);
                });
            });

            if (navToggleBtn && navCollapseEl) {
                navToggleBtn.addEventListener('click', (event) => {
                    event.preventDefault();
                    event.stopPropagation();
                    dropdownToggles.forEach((toggle) => {
                        bootstrap.Dropdown.getOrCreateInstance(toggle).hide();
                    });
                    if (offcanvasInstance) {
                        offcanvasInstance.hide();
                    }
                    collapseInstance.toggle();
                });
            }

            if (mobileMenuBtn && mobileMenuEl) {
                mobileMenuBtn.addEventListener('click', (event) => {
                    event.preventDefault();
                    event.stopPropagation();
                    dropdownToggles.forEach((toggle) => {
                        bootstrap.Dropdown.getOrCreateInstance(toggle).hide();
                    });
                    if (collapseInstance) {
                        collapseInstance.hide();
                    }
                    if (mobileMenuEl.classList.contains('show')) {
                        offcanvasInstance.hide();
                    } else {
                        offcanvasInstance.show();
                    }
                });
            }

            document.addEventListener('click', (event) => {
                if (!event.target || !event.target.closest) return;
                const clickedInsideNavbar = event.target.closest('.minven-navbar');
                if (clickedInsideNavbar) return;
                closeAllMenus();
            }, { passive: true });

            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') {
                    closeAllMenus();
                }
            }, { passive: true });

            document.addEventListener('click', (event) => {
                if (!event.target || !event.target.closest) return;
                const navItem = event.target.closest('.dropdown-item');
                if (navItem) {
                    closeAllMenus();
                }
            }, { passive: true });
        });
    </script>
    <style>
        /* Optional: Style for the photo column */
        .photo-col {
            width: 80px; /* Adjust as needed */
            text-align: center;
        }
        .photo-col img {
            max-width: 70px; /* Limit thumbnail size */
            height: auto;
            border: 1px solid #ddd;
            padding: 2px;
            border-radius: 4px;
        }
        
        /* Tambahkan style untuk tabel responsif */
        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        
        /* Style untuk autocomplete */
        .ui-autocomplete {
            max-height: 200px;
            overflow-y: auto;
            overflow-x: hidden;
            z-index: 1050 !important;
        }
        .ui-autocomplete .ui-menu-item {
            padding: 8px 12px;
            cursor: pointer;
        }
        .ui-autocomplete .ui-menu-item:hover {
            background-color: #f8f9fa;
        }
        .ui-autocomplete .ui-state-active {
            background-color: #0d6efd;
            color: white;
        }
        
        /* Pastikan semua kolom memiliki lebar minimum */
        .table th, .table td {
            min-width: 80px;
            vertical-align: middle;
        }
        
        /* Atur lebar maksimum untuk container tabel */
        .card-body {
            padding: 0.75rem;
        }
        
        /* Pastikan tombol aksi tidak terlalu besar */
        .btn-sm {
            padding: 0.25rem 0.4rem;
            font-size: 0.75rem;
        }
        
        /* Atur lebar kolom aksi */
        .action-col {
            min-width: 120px;
        }
    </style>
</head>
<body>
    <?php include_once '../../templates/navbar.php'; ?>

    <?php if(isset($_SESSION['success'])): ?>
    <script>
        Swal.fire({
            icon: 'success',
            title: 'Berhasil!',
            text: '<?= $_SESSION['success'] ?>',
            timer: 3000,
            showConfirmButton: false
        });
    </script>
    <?php unset($_SESSION['success']); endif; ?>

    <div class="container-fluid mt-4">
        <!-- Header -->
        <div class="row mb-3">
            <div class="col">
                <h2>Daftar Purchase Order</h2>
            </div>
            <div class="col text-end">
                <?php if(checkAccess('purchase_order', 'add')): ?>
                    <a href="create.php" class="btn btn-primary">
                        <i class='bx bx-plus'></i> Buat PO Baru
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Table Card -->
        <div class="card">
            <div class="card-body">
                <!-- Filter Section -->
                <form method="get" class="mb-3">
                    <div class="row g-2 align-items-end">
                        <div class="col-md-3">
                            <label for="no_po" class="form-label">Cari (No PO / Supplier / User)</label>
                            <input type="text" class="form-control" id="no_po" name="no_po" value="<?= htmlspecialchars($filter_no_po) ?>" placeholder="Cari...">
                        </div>
                        <div class="col-md-2">
                            <label for="supplier_id" class="form-label">Supplier</label>
                            <select class="form-select" id="supplier_id" name="supplier_id">
                                <option value="">Semua</option>
                                <?php if($supplier_filter_result) { 
                                    $supplier_filter_result->data_seek(0); // Reset pointer
                                    while($sup = $supplier_filter_result->fetch_assoc()): 
                                ?>
                                    <option value="<?= $sup['id'] ?>" <?= $filter_supplier == $sup['id'] ? 'selected' : '' ?>><?= htmlspecialchars($sup['nama_supplier']) ?></option>
                                <?php endwhile; } ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="">Semua</option>
                                <option value="draft" <?= $filter_status == 'draft' ? 'selected' : '' ?>>Menunggu</option>
                                <option value="approved" <?= $filter_status == 'approved' ? 'selected' : '' ?>>Approved</option>
                                <option value="delivered" <?= $filter_status == 'delivered' ? 'selected' : '' ?>>Delivery</option>
                                <option value="completed" <?= $filter_status == 'completed' ? 'selected' : '' ?>>Selesai</option>
                                <option value="dikirim" <?= $filter_status == 'dikirim' ? 'selected' : '' ?>>Dikirim</option>
                                <option value="rejected" <?= $filter_status == 'rejected' ? 'selected' : '' ?>>Rejected</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="tanggal_dari" class="form-label">Tanggal Dari</label>
                            <input type="date" class="form-control" id="tanggal_dari" name="tanggal_dari" value="<?= htmlspecialchars($filter_tanggal_dari) ?>">
                        </div>
                        <div class="col-md-2">
                            <label for="tanggal_sampai" class="form-label">Tanggal Sampai</label>
                            <input type="date" class="form-control" id="tanggal_sampai" name="tanggal_sampai" value="<?= htmlspecialchars($filter_tanggal_sampai) ?>">
                        </div>
                        <div class="col-md-1 d-grid">
                            <button type="submit" class="btn btn-secondary"><i class='bx bx-search'></i> Cari</button>
                        </div>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="table table-bordered table-striped table-hover">
                        <thead>
                            <tr>
                                <th class="text-center" style="width: 5%;">No</th>
                                <th class="text-center" style="width: 12%;">No PO</th>
                                <th class="text-center" style="width: 10%;">Tanggal</th>
                                <th class="text-center" style="width: 15%;">Supplier</th>
                                <th class="text-center" style="width: 8%;">Total Item</th>
                                <th class="text-center" style="width: 12%;">Total Harga</th>
                                <th class="text-center" style="width: 10%;">Dibuat Oleh</th>
                                <th class="text-center" style="width: 8%;">Status</th>
                                <th class="text-center" style="width: 8%;">Foto</th>
                                <th class="text-center" style="width: 10%;">Tanggal Completed</th>
                                <th class="text-center" style="width: 12%;">User Complete</th>
                                <th class="text-center action-col" style="width: 12%;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $no = 1;
                            while($row = $result->fetch_assoc()):
                            ?>
                            <tr>
                                <td class="text-center"><?= $no++ ?></td>
                                <td><?= htmlspecialchars($row['no_po']) ?></td>
                                <td class="text-center"><?= date('d/m/Y', strtotime($row['tanggal'])) ?></td>
                                <td><?= htmlspecialchars($row['nama_supplier']) ?></td>
                                <td class="text-center"><?= number_format($row['total_item_calc']) ?></td>
                                <td class="text-end"><?= formatRupiah($row['total_harga_calc']) ?></td>
                                <td><?= htmlspecialchars($row['created_by_name']) ?></td>
                                <td class="text-center">
                                    <?php
                                    switch($row['status']) {
                                        case 'draft':
                                            $badge_class = 'bg-warning';
                                            $status_text = 'Menunggu';
                                            break;
                                        case 'approved':
                                            $badge_class = 'bg-success';
                                            $status_text = 'Approved';
                                            break;
                                        case 'delivered':
                                            $badge_class = 'bg-info';
                                            $status_text = 'Delivery';
                                            break;
                                        case 'dikirim':
                                            $badge_class = 'bg-dark';
                                            $status_text = 'Dikirim';
                                            break;
                                        case 'completed':
                                            $badge_class = 'bg-primary';
                                            $status_text = 'Selesai';
                                            break;
                                        case 'rejected':
                                            $badge_class = 'bg-danger';
                                            $status_text = 'Rejected';
                                            break;
                                        default:
                                            $badge_class = 'bg-secondary';
                                            $status_text = ucfirst($row['status']);
                                    }
                                    ?>
                                    <span class="badge <?= $badge_class ?> w-100"><?= $status_text ?></span>
                                </td>
                                <td class="photo-col"> <!-- Added Photo Column Data -->
                                    <?php if (!empty($row['foto'])): ?>
                                        <?php
                                            // Construct the correct path to the image
                                            // Assuming foto path is like 'uploads/po/filename.jpg'
                                            // and index.php is in 'pembelian/po/'
                                            $image_path = '../../' . htmlspecialchars($row['foto']);
                                        ?>
                                        <a href="<?= $image_path ?>" target="_blank" title="Lihat Foto">
                                            <img src="<?= $image_path ?>" alt="Foto PO" class="img-thumbnail">
                                        </a>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if (!empty($row['completed_at'])): ?>
                                        <?= date('d/m/Y H:i', strtotime($row['completed_at'])) ?>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($row['keterangan_complete'] ?? '-') ?></td>
                                <td style="white-space: nowrap;" class="action-col">
                                    <div class="d-flex flex-wrap gap-1 justify-content-center">
                                        <a href="view.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-info" title="Lihat Detail">
                                            <i class='bx bx-show'></i>
                                        </a>
                                        <?php if($wa_active && $row['status'] == 'approved' && checkAccess('purchase_order', 'send_wa')): ?>
                                            <button type="button" class="btn btn-sm btn-success btn-send-wa" 
                                                    data-no-po="<?= htmlspecialchars($row['no_po']) ?>"
                                                    data-supplier="<?= htmlspecialchars($row['nama_supplier']) ?>"
                                                    data-telepon="<?= htmlspecialchars($row['telepon']) ?>"
                                                    data-tanggal="<?= date('d/m/Y', strtotime($row['tanggal'])) ?>"
                                                    data-total="<?= formatRupiah($row['total_harga_calc']) ?>"
                                                    data-items="<?= htmlspecialchars($row['item_names']) ?>"
                                                    data-total-item="<?= number_format($row['total_item_calc']) ?>"
                                                    title="Kirim WhatsApp">
                                                <i class='bi bi-whatsapp'></i>
                                            </button>
                                        <?php endif; ?>
                                        <?php if($row['status'] == 'draft'): ?>
                                            <?php if(checkAccess('purchase_order', 'edit')): ?>
                                                <a href="edit.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-warning" title="Edit">
                                                    <i class='bx bx-edit'></i>
                                                </a>
                                            <?php endif; ?>
                                            <?php if(checkAccess('purchase_order', 'edit') || checkAccess('approve', 'view')): ?>
                                                <button type="button" class="btn btn-sm btn-success btn-approve" data-id="<?= $row['id'] ?>" title="Approve">
                                                    <i class='bx bx-check'></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-secondary btn-reject" data-id="<?= $row['id'] ?>" title="Reject">
                                                    <i class='bx bx-x'></i>
                                                </button>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                        <?php if($row['status'] == 'completed'): ?>
                                            <?php if(checkAccess('vendor_refund', 'add')): ?>
                                                <a href="../../vendor_refund/index.php?po_id=<?= $row['id'] ?>" class="btn btn-sm btn-outline-danger" title="Refund Vendor">
                                                    <i class='bx bx-undo'></i>
                                                </a>
                                            <?php endif; ?>
                                            <?php if(checkAccess('purchase_order', 'complete')): ?>
                                                <button type="button" class="btn btn-sm btn-dark btn-send-warehouse" data-id="<?= $row['id'] ?>" title="Kirim ke Gudang">
                                                    <i class='bx bx-send'></i>
                                                </button>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                        <?php if($row['status'] == 'approved' || $row['status'] == 'delivered'): ?>
                                            <?php if(checkAccess('purchase_order', 'complete')): ?>
                                                <button type="button" class="btn btn-sm btn-primary btn-complete" data-id="<?= $row['id'] ?>" title="Konfirmasi Pembelian">
                                                    <i class='bx bx-check-circle'></i>
                                                </button>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                        <?php if(checkAccess('purchase_order', 'delete')): ?>
                                            <button type="button" class="btn btn-sm btn-danger btn-delete" data-id="<?= $row['id'] ?>" title="Hapus">
                                                <i class='bx bx-trash'></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                            
                            <!-- Tambahkan baris total -->
                            <?php
                            // Reset pointer result set
                            $result->data_seek(0);
                            $grand_total = 0;
                            $total_items = 0;
                            
                            // Hitung grand total
                            while($row = $result->fetch_assoc()) {
                                $grand_total += floatval($row['total_harga_calc']);
                                $total_items += intval($row['total_item_calc']);
                            }
                            ?>
                            <tfoot>
                                <tr>
                                    <td colspan="4" class="text-end"><strong>Total:</strong></td>
                                    <td class="text-center"><?= number_format($total_items) ?></td>
                                    <td class="text-end"><?= formatRupiah($grand_total) ?></td>
                                    <td colspan="5"></td>
                                </tr>
                            </tfoot>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
    const GUDANG_LIST = <?= json_encode($gudang_list, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    const WA_CONFIG = <?= json_encode($wa_config) ?>;

    // Inisialisasi tombol kirim WA
    document.querySelectorAll('.btn-send-wa').forEach(button => {
        button.addEventListener('click', function() {
            const telepon = this.dataset.telepon || '';
            
            if (!telepon) {
                Swal.fire('Error', 'Nomor telepon supplier tidak ditemukan', 'error');
                return;
            }

            // Bersihkan nomor telepon dari karakter non-digit
            let cleanPhone = telepon.replace(/\D/g, '');
            // Jika diawali dengan 0, ganti dengan 62
            if (cleanPhone.startsWith('0')) {
                cleanPhone = '62' + cleanPhone.substring(1);
            }

            let message = (WA_CONFIG && WA_CONFIG.template_message) || '';
            message = message.replace('{no_po}', this.dataset.noPo);
            message = message.replace('{supplier}', this.dataset.supplier);
            message = message.replace('{tanggal}', this.dataset.tanggal);
            message = message.replace('{total}', this.dataset.total);
            message = message.replace('{items}', this.dataset.items);
            message = message.replace('{total_item}', this.dataset.totalItem);

            const waUrl = `https://wa.me/${cleanPhone}?text=${encodeURIComponent(message)}`;
            window.open(waUrl, '_blank');
        });
    });

    // Inisialisasi tombol approve
    document.querySelectorAll('.btn-approve').forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const id = this.dataset.id;
            Swal.fire({
                title: 'Konfirmasi Approval',
                text: "Apakah Anda yakin ingin menyetujui PO ini?",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#28a745',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Ya, Setuju!',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = `approve.php?id=${id}&status=approved`;
                }
            });
        });
    });

    // Inisialisasi tombol reject
    document.querySelectorAll('.btn-reject').forEach(button => {
        button.addEventListener('click', function() {
            const id = this.dataset.id;
            Swal.fire({
                title: 'Konfirmasi Penolakan',
                text: "Apakah Anda yakin ingin menolak PO ini?",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Ya, Tolak!',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = `approve.php?id=${id}&status=rejected`;
                }
            });
        });
    });
    document.addEventListener('DOMContentLoaded', function() {
    // Your code that manipulates the DOM here
});
    // Inisialisasi tombol complete
    document.querySelectorAll('.btn-complete').forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const id = this.dataset.id;
            
            // Cek status PO sebelum melanjutkan
            fetch(`get_po_status.php?id=${id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'completed') {
                        Swal.fire('Error', 'Tidak dapat mengkonfirmasi PO yang sudah completed', 'error');
                        return;
                    }
            
                    Swal.fire({
                        title: 'Konfirmasi Penerimaan',
                        html: `
                            <form id="completeForm">
                                <div class="mb-3">
                                    <label for="purchase_date" class="form-label">Tanggal Penerimaan Barang </label>
                                    <input type="date" class="form-control" id="purchase_date" name="purchase_date" value="<?= date('Y-m-d') ?>" required>
                                </div>
                            </form>
                        `,
                        showCancelButton: true,
                        confirmButtonText: 'Simpan',
                        cancelButtonText: 'Batal',
                        focusConfirm: false,
                        preConfirm: () => {
                            const date = document.getElementById('purchase_date').value;
                            if (!date) {
                                Swal.showValidationMessage('Tanggal pembelian harus diisi');
                                return false;
                            }
                            return { date: date };
                        }
                    }).then((result) => {
                        if (result.isConfirmed) {
                            window.location.href = `complete.php?id=${id}&date=${result.value.date}`;
                        }
                    });
                })
                .catch(error => {
                    console.error('Error:', error);
                    Swal.fire('Error', 'Terjadi kesalahan saat memeriksa status PO', 'error');
                });
        });
    });

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = String(text ?? '');
        return div.innerHTML;
    }

    function findGudangIdByName(keyword) {
        const kw = String(keyword ?? '').toLowerCase();
        const found = (GUDANG_LIST || []).find(g => String(g.nama_gudang ?? '').toLowerCase().includes(kw));
        return found ? String(found.id) : '';
    }

    function getGudangNameById(id) {
        const sid = String(id ?? '');
        const found = (GUDANG_LIST || []).find(g => String(g.id) === sid);
        return String(found?.nama_gudang ?? '');
    }

    function labelMatchesGudangName(label, gudangName) {
        const l = String(label ?? '').toLowerCase();
        const g = String(gudangName ?? '').toLowerCase();
        if (!l || !g) return false;
        const keywords = [];
        if (g.includes('antapani')) keywords.push('antapani');
        if (g.includes('central')) keywords.push('central');
        if (g.includes('pusat')) keywords.push('pusat');
        return keywords.some(kw => l.includes(kw));
    }

    function buildGudangOptions(selectedId, allowEmpty) {
        const options = [];
        if (allowEmpty) {
            options.push(`<option value="">Tidak digunakan</option>`);
        }
        for (const g of (GUDANG_LIST || [])) {
            const id = String(g.id);
            const selected = id === String(selectedId) ? 'selected' : '';
            options.push(`<option value="${escapeHtml(id)}" ${selected}>${escapeHtml(g.nama_gudang)}</option>`);
        }
        return options.join('');
    }

    function toInt(value) {
        const n = parseInt(String(value ?? '').replace(/[^\d-]/g, ''), 10);
        return Number.isFinite(n) ? n : 0;
    }

    // Inisialisasi tombol kirim ke gudang
    document.querySelectorAll('.btn-send-warehouse').forEach(button => {
        button.addEventListener('click', async function(e) {
            e.preventDefault();
            const poId = this.dataset.id;

            let payload;
            try {
                const resp = await fetch(`get_po_items.php?id=${encodeURIComponent(poId)}`);
                payload = await resp.json();
            } catch (err) {
                Swal.fire('Error', 'Gagal mengambil detail PO', 'error');
                return;
            }

            if (!payload || payload.status !== 'success') {
                Swal.fire('Error', payload?.message || 'Gagal mengambil detail PO', 'error');
                return;
            }

            const items = payload.items || [];
            if (items.length === 0) {
                Swal.fire('Error', 'Item PO kosong', 'error');
                return;
            }

            const defaultGudang1 = findGudangIdByName('central') || (GUDANG_LIST?.[0] ? String(GUDANG_LIST[0].id) : '');
            const defaultGudang2 = findGudangIdByName('antapani') || (GUDANG_LIST?.[1] ? String(GUDANG_LIST[1].id) : '');

            const itemMap = new Map(items.map(it => [String(it.detail_id), it]));
            const cssEscape = (value) => {
                const v = String(value ?? '');
                if (window.CSS && typeof window.CSS.escape === 'function') return window.CSS.escape(v);
                return v.replace(/([ #;?%&,.+*~':"!^$[\]()=>|\/@])/g, '\\$1');
            };
            let nextClientRowId = 1;
            const newClientRowId = () => `r${nextClientRowId++}`;
            const getItemQty = (detailId) => {
                const it = itemMap.get(String(detailId));
                return it ? toInt(it.jumlah) : 0;
            };

            const syncSplitRowInputs = (rowEl, moveQtyIfSwitch) => {
                if (!rowEl) return;
                const sel = rowEl.querySelector('.split-target');
                if (!sel) return;
                const target = String(sel.value || 'g1');
                const q1 = rowEl.querySelector('.qty-g1');
                const q2 = rowEl.querySelector('.qty-g2');
                if (!q1 || !q2) return;

                if (target === 'g2') {
                    const prev = moveQtyIfSwitch ? toInt(q1.value) : 0;
                    q1.value = '0';
                    if (moveQtyIfSwitch && prev > 0) q2.value = String(toInt(q2.value) + prev);
                    q1.readOnly = true;
                    q2.readOnly = false;
                } else {
                    const prev = moveQtyIfSwitch ? toInt(q2.value) : 0;
                    q2.value = '0';
                    if (moveQtyIfSwitch && prev > 0) q1.value = String(toInt(q1.value) + prev);
                    q2.readOnly = true;
                    q1.readOnly = false;
                }
            };

            const computeAssignedTotals = (detailId) => {
                const rows = Array.from(document.querySelectorAll(`tr[data-detail-id="${cssEscape(String(detailId))}"]`));
                return rows.reduce((acc, r) => {
                    const q1 = toInt(r.querySelector('.qty-g1')?.value);
                    const q2 = toInt(r.querySelector('.qty-g2')?.value);
                    acc.g1 += q1;
                    acc.g2 += q2;
                    return acc;
                }, { g1: 0, g2: 0 });
            };

            const renumberTableRows = () => {
                const rows = Array.from(document.querySelectorAll('#sendWarehouseItems tbody tr'));
                rows.forEach((r, i) => {
                    const cell = r.querySelector('td[data-role="row-number"]');
                    if (cell) cell.textContent = String(i + 1);
                });
            };

            const updateSplitTargetUI = () => {
                const gudang1Id = document.getElementById('gudang_1_id')?.value || '';
                const gudang2Id = document.getElementById('gudang_2_id')?.value || '';
                const g1Name = getGudangNameById(gudang1Id);
                const g2Name = getGudangNameById(gudang2Id);

                const targetSelects = Array.from(document.querySelectorAll('.split-target'));

                for (const sel of targetSelects) {
                    const prevValue = String(sel.value || 'g1');
                    sel.innerHTML = '';

                    const opt1 = document.createElement('option');
                    opt1.value = 'g1';
                    opt1.textContent = g1Name ? `Gudang 1 (${g1Name})` : 'Gudang 1';
                    sel.appendChild(opt1);

                    if (gudang2Id) {
                        const opt2 = document.createElement('option');
                        opt2.value = 'g2';
                        opt2.textContent = g2Name ? `Gudang 2 (${g2Name})` : 'Gudang 2';
                        sel.appendChild(opt2);
                        sel.disabled = false;
                    } else {
                        sel.disabled = true;
                    }

                    let nextValue = prevValue === 'g2' ? 'g2' : 'g1';
                    if (!gudang2Id) nextValue = 'g1';
                    sel.value = nextValue;

                    const rowId = String(sel.dataset.rowId || '');
                    const rowEl = rowId ? document.querySelector(`tr[data-row-id="${cssEscape(rowId)}"]`) : sel.closest('tr');
                    syncSplitRowInputs(rowEl, prevValue !== nextValue);
                }
            };

            const buildSplitRowHtml = (it, idx, clientRowId, defaultTarget, defaultQ1, defaultQ2, allowRemove) => {
                const qty = toInt(it.jumlah);
                const detailId = String(it.detail_id);
                const parentLabel = `${String(it.parent_kode_barang || '').trim()} ${String(it.parent_nama_barang || '').trim()}`.trim();
                const g1NameDefault = getGudangNameById(defaultGudang1);
                const g2NameDefault = getGudangNameById(defaultGudang2);
                return `
                    <tr data-row-id="${escapeHtml(clientRowId)}" data-detail-id="${escapeHtml(detailId)}" data-row-key="${escapeHtml(detailId)}">
                        <td class="text-center" data-role="row-number">${idx + 1}</td>
                        <td>
                            <div class="fw-semibold">
                                <span class="badge bg-info me-1">Split</span>
                                <span>${escapeHtml(it.kode_barang || '')} ${escapeHtml(it.nama_barang || '')}</span>
                            </div>
                            ${parentLabel ? `<div class="text-muted small">Dari: ${escapeHtml(parentLabel)}</div>` : ``}
                            ${it.keterangan_detail ? `<div class="text-muted small">${escapeHtml(it.keterangan_detail)}</div>` : ``}
                        </td>
                        <td class="text-center">${escapeHtml(it.satuan || '')}</td>
                        <td class="text-end">${escapeHtml(qty)}</td>
                        <td class="text-center">
                            <input type="checkbox" class="form-check-input send-toggle" data-row-key="${escapeHtml(detailId)}" checked>
                        </td>
                        <td class="text-center">
                            <select class="form-select form-select-sm split-target" data-detail-id="${escapeHtml(detailId)}" data-row-id="${escapeHtml(clientRowId)}" data-manual="0">
                                <option value="g1" ${defaultTarget === 'g1' ? 'selected' : ''}>${escapeHtml(g1NameDefault ? `Gudang 1 (${g1NameDefault})` : 'Gudang 1')}</option>
                                <option value="g2" ${defaultTarget === 'g2' ? 'selected' : ''}>${escapeHtml(g2NameDefault ? `Gudang 2 (${g2NameDefault})` : 'Gudang 2')}</option>
                            </select>
                        </td>
                        <td class="text-end">
                            <input type="number" class="form-control form-control-sm qty-g1" data-detail-id="${escapeHtml(detailId)}" data-row-id="${escapeHtml(clientRowId)}" min="0" value="${escapeHtml(defaultQ1)}" required>
                        </td>
                        <td class="text-end">
                            <input type="number" class="form-control form-control-sm qty-g2" data-detail-id="${escapeHtml(detailId)}" data-row-id="${escapeHtml(clientRowId)}" min="0" value="${escapeHtml(defaultQ2)}" required>
                        </td>
                        <td class="text-center">
                            <div class="d-flex justify-content-center gap-1 flex-wrap">
                                <button type="button" class="btn btn-outline-primary btn-sm py-0 px-2 split-divide" data-detail-id="${escapeHtml(detailId)}" data-row-id="${escapeHtml(clientRowId)}">
                                    Bagi
                                </button>
                                ${allowRemove ? `<button type="button" class="btn btn-outline-secondary btn-sm py-0 px-2 split-remove" data-row-id="${escapeHtml(clientRowId)}">Baris</button>` : ``}
                                <button type="button" class="btn btn-outline-danger btn-sm py-0 px-2 row-delete" data-row-key="${escapeHtml(detailId)}">
                                    Delete
                                </button>
                            </div>
                        </td>
                    </tr>
                `;
            };

            const buildDetailRowHtml = (it, idx) => {
                const qty = toInt(it.jumlah);
                const detailId = String(it.detail_id);
                return `
                    <tr data-detail-id="${escapeHtml(detailId)}" data-row-key="${escapeHtml(detailId)}">
                        <td class="text-center" data-role="row-number">${idx + 1}</td>
                        <td>
                            <div class="fw-semibold">
                                ${escapeHtml(it.kode_barang || '')} ${escapeHtml(it.nama_barang || '')}
                            </div>
                            ${it.keterangan_detail ? `<div class="text-muted small">${escapeHtml(it.keterangan_detail)}</div>` : ``}
                        </td>
                        <td class="text-center">${escapeHtml(it.satuan || '')}</td>
                        <td class="text-end">${escapeHtml(qty)}</td>
                        <td class="text-center">
                            <input type="checkbox" class="form-check-input send-toggle" data-row-key="${escapeHtml(detailId)}" checked>
                        </td>
                        <td class="text-center"><span class="text-muted">-</span></td>
                        <td class="text-end">
                            <input type="number" class="form-control form-control-sm qty-g1" data-detail-id="${escapeHtml(detailId)}" min="0" max="${escapeHtml(qty)}" value="${escapeHtml(qty)}" required>
                        </td>
                        <td class="text-end">
                            <input type="number" class="form-control form-control-sm qty-g2" data-detail-id="${escapeHtml(detailId)}" min="0" max="${escapeHtml(qty)}" value="0" required>
                        </td>
                        <td class="text-center">
                            <button type="button" class="btn btn-outline-danger btn-sm py-0 px-2 row-delete" data-row-key="${escapeHtml(detailId)}">
                                Delete
                            </button>
                        </td>
                    </tr>
                `;
            };

            const tableRows = items.map((it, idx) => {
                const qty = toInt(it.jumlah);
                const detailId = String(it.detail_id);
                const isSplit = String(it.row_type || '') === 'split';
                let defaultQ1 = qty;
                let defaultQ2 = 0;
                let defaultTarget = 'g1';
                if (isSplit) {
                    const label = String(it.keterangan_detail || '');
                    const g1Name = getGudangNameById(defaultGudang1);
                    const g2Name = getGudangNameById(defaultGudang2);
                    const matchG1 = labelMatchesGudangName(label, g1Name);
                    const matchG2 = labelMatchesGudangName(label, g2Name);
                    if (matchG2 && !matchG1) {
                        defaultQ1 = 0;
                        defaultQ2 = qty;
                        defaultTarget = 'g2';
                    } else if (matchG1 && !matchG2) {
                        defaultQ1 = qty;
                        defaultQ2 = 0;
                        defaultTarget = 'g1';
                    }
                }
                if (isSplit) {
                    const clientRowId = newClientRowId();
                    return buildSplitRowHtml(it, idx, clientRowId, defaultTarget, defaultQ1, defaultQ2, false);
                }
                return buildDetailRowHtml(it, idx);
            }).join('');

            const deletedRowKeys = new Set();

            const html = `
                <form id="sendWarehouseForm">
                    <div class="row g-2 mb-2">
                        <div class="col-12 col-md-4">
                            <label class="form-label">Tanggal</label>
                            <input type="date" class="form-control" id="send_tanggal" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label">Gudang 1</label>
                            <select class="form-select" id="gudang_1_id" required>
                                ${buildGudangOptions(defaultGudang1, false)}
                            </select>
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label">Gudang 2</label>
                            <select class="form-select" id="gudang_2_id">
                                ${buildGudangOptions(defaultGudang2, true)}
                            </select>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered table-hover align-middle mb-0" id="sendWarehouseItems">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 40px;">#</th>
                                    <th>Barang</th>
                                    <th style="width: 90px;" class="text-center">Satuan</th>
                                    <th style="width: 110px;" class="text-end">Qty Item</th>
                                    <th style="width: 80px;" class="text-center">Kirim</th>
                                    <th style="width: 160px;" class="text-center">Tujuan (Split)</th>
                                    <th style="width: 130px;" class="text-end">Qty Gudang 1</th>
                                    <th style="width: 130px;" class="text-end">Qty Gudang 2</th>
                                    <th style="width: 140px;" class="text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${tableRows}
                            </tbody>
                        </table>
                    </div>
                </form>
            `;

            const res = await Swal.fire({
                title: 'Kirim Stok ke Gudang',
                html,
                width: 1000,
                showCancelButton: true,
                confirmButtonText: 'Simpan',
                cancelButtonText: 'Batal',
                focusConfirm: false,
                didOpen: () => {
                    const g1 = document.getElementById('gudang_1_id');
                    const g2 = document.getElementById('gudang_2_id');
                    const tbody = document.querySelector('#sendWarehouseItems tbody');
                    const deleteByRowKey = (rowKey) => {
                        const key = String(rowKey || '');
                        if (!key) return;
                        const rows = Array.from(tbody.querySelectorAll(`tr[data-row-key="${cssEscape(key)}"]`));
                        if (rows.length === 0) return;
                        rows.forEach(r => r.remove());
                        deletedRowKeys.add(key);
                        renumberTableRows();
                    };
                    const setEnabledByRowKey = (rowKey, enabled) => {
                        const key = String(rowKey || '');
                        if (!key) return;
                        const rows = Array.from(tbody.querySelectorAll(`tr[data-row-key="${cssEscape(key)}"]`));
                        for (const r of rows) {
                            const toggle = r.querySelector(`.send-toggle[data-row-key="${cssEscape(key)}"]`);
                            if (toggle) {
                                toggle.dataset.busy = '1';
                                toggle.checked = !!enabled;
                                toggle.dataset.busy = '0';
                            }

                            const sel = r.querySelector('.split-target');
                            if (sel) sel.disabled = !enabled;

                            const q1 = r.querySelector('.qty-g1');
                            const q2 = r.querySelector('.qty-g2');
                            const cachePrev = (el) => {
                                if (!el) return;
                                if (!el.dataset.prevValue) el.dataset.prevValue = String(el.value ?? '');
                            };
                            const restorePrev = (el, fallback) => {
                                if (!el) return;
                                const prev = el.dataset.prevValue;
                                el.value = prev !== undefined ? String(prev) : String(fallback ?? 0);
                                delete el.dataset.prevValue;
                            };

                            if (!enabled) {
                                cachePrev(q1);
                                cachePrev(q2);
                                if (q1) q1.value = '0';
                                if (q2) q2.value = '0';
                            } else {
                                restorePrev(q1, q1?.value ?? '0');
                                restorePrev(q2, q2?.value ?? '0');
                            }

                            if (!enabled) {
                                if (q1) q1.readOnly = true;
                                if (q2) q2.readOnly = true;
                            } else {
                                if (q1) q1.readOnly = false;
                                if (q2) q2.readOnly = false;
                                syncSplitRowInputs(r, false);
                            }

                            r.classList.toggle('table-secondary', !enabled);
                            r.classList.toggle('opacity-75', !enabled);

                            r.querySelectorAll('.split-divide, .split-remove').forEach(btn => {
                                btn.disabled = !enabled;
                            });
                        }
                    };

                    const wireRowEvents = (rowEl) => {
                        const sel = rowEl.querySelector('.split-target');
                        if (sel) {
                            sel.addEventListener('change', () => {
                                sel.dataset.manual = '1';
                                syncSplitRowInputs(rowEl, true);
                            });
                        }
                    };

                    const wireAllRows = () => {
                        Array.from(document.querySelectorAll('#sendWarehouseItems tbody tr')).forEach(r => wireRowEvents(r));
                    };

                    const divideSplitLine = (rowId) => {
                        const rowEl = tbody.querySelector(`tr[data-row-id="${cssEscape(String(rowId))}"]`);
                        if (!rowEl) return;

                        const detailId = String(rowEl.dataset.detailId || '');
                        const it = itemMap.get(String(detailId));
                        if (!it || String(it.row_type || '') !== 'split') return;

                        const gudang2Id = document.getElementById('gudang_2_id')?.value || '';
                        const sel = rowEl.querySelector('.split-target');
                        const q1El = rowEl.querySelector('.qty-g1');
                        const q2El = rowEl.querySelector('.qty-g2');
                        if (!sel || !q1El || !q2El) return;

                        const currentTarget = String(sel.value || 'g1');
                        const currentQty = currentTarget === 'g2' ? toInt(q2El.value) : toInt(q1El.value);
                        if (currentQty <= 1) {
                            Swal.fire('Info', 'Qty terlalu kecil untuk dibagi.', 'info');
                            return;
                        }

                        const moveQty = Math.floor(currentQty / 2);
                        const nextTarget = gudang2Id ? (currentTarget === 'g2' ? 'g1' : 'g2') : currentTarget;

                        if (currentTarget === 'g2') {
                            q2El.value = String(currentQty - moveQty);
                        } else {
                            q1El.value = String(currentQty - moveQty);
                        }

                        const clientRowId = newClientRowId();
                        const q1 = nextTarget === 'g1' ? moveQty : 0;
                        const q2 = nextTarget === 'g2' ? moveQty : 0;
                        const rowHtml = buildSplitRowHtml(it, 0, clientRowId, nextTarget, q1, q2, true);
                        rowEl.insertAdjacentHTML('beforebegin', rowHtml);

                        const newRowEl = tbody.querySelector(`tr[data-row-id="${cssEscape(clientRowId)}"]`);
                        wireRowEvents(newRowEl);
                        updateSplitTargetUI();
                        syncSplitRowInputs(rowEl, false);
                        syncSplitRowInputs(newRowEl, false);
                        renumberTableRows();
                    };

                    const removeSplitLine = (rowId) => {
                        const rowEl = tbody.querySelector(`tr[data-row-id="${cssEscape(String(rowId))}"]`);
                        if (!rowEl) return;
                        const detailId = String(rowEl.dataset.detailId || '');
                        const siblings = Array.from(document.querySelectorAll(`tr[data-detail-id="${cssEscape(detailId)}"]`));
                        if (siblings.length <= 1) {
                            Swal.fire('Info', 'Minimal harus ada 1 baris untuk item ini.', 'info');
                            return;
                        }
                        rowEl.remove();
                        renumberTableRows();
                    };

                    tbody?.addEventListener('click', (ev) => {
                        const btnDivide = ev.target?.closest?.('.split-divide');
                        if (btnDivide) {
                            ev.preventDefault();
                            divideSplitLine(String(btnDivide.dataset.rowId || ''));
                            return;
                        }
                        const btnRemove = ev.target?.closest?.('.split-remove');
                        if (btnRemove) {
                            ev.preventDefault();
                            removeSplitLine(String(btnRemove.dataset.rowId || ''));
                            return;
                        }
                        const btnDelete = ev.target?.closest?.('.row-delete');
                        if (btnDelete) {
                            ev.preventDefault();
                            deleteByRowKey(String(btnDelete.dataset.rowKey || ''));
                        }
                    });

                    tbody?.addEventListener('change', (ev) => {
                        const chk = ev.target?.closest?.('.send-toggle');
                        if (!chk) return;
                        if (chk.dataset.busy === '1') return;
                        const rowKey = String(chk.dataset.rowKey || '');
                        setEnabledByRowKey(rowKey, !!chk.checked);
                    });

                    wireAllRows();
                    if (g1) g1.addEventListener('change', updateSplitTargetUI);
                    if (g2) g2.addEventListener('change', updateSplitTargetUI);
                    updateSplitTargetUI();
                    Array.from(document.querySelectorAll('#sendWarehouseItems tbody tr')).forEach(r => syncSplitRowInputs(r, false));
                },
                preConfirm: () => {
                    const tanggal = document.getElementById('send_tanggal')?.value;
                    const gudang1Id = document.getElementById('gudang_1_id')?.value || '';
                    const gudang2Id = document.getElementById('gudang_2_id')?.value || '';

                    if (!tanggal) {
                        Swal.showValidationMessage('Tanggal wajib diisi');
                        return false;
                    }
                    if (!gudang1Id) {
                        Swal.showValidationMessage('Gudang 1 wajib dipilih');
                        return false;
                    }
                    if (gudang2Id && gudang2Id === gudang1Id) {
                        Swal.showValidationMessage('Gudang 2 tidak boleh sama dengan Gudang 1');
                        return false;
                    }

                    const detailPayload = [];
                    const rows = Array.from(document.querySelectorAll('#sendWarehouseItems tbody tr'));
                    const totalsByDetail = new Map();
                    const canceledRows = new Set(Array.from(deletedRowKeys));
                    for (const rowEl of rows) {
                        const detailId = String(rowEl.dataset.detailId || '');
                        if (!detailId) continue;
                        const chk = rowEl.querySelector('.send-toggle');
                        if (chk && !chk.checked) {
                            canceledRows.add(String(rowEl.dataset.rowKey || detailId));
                        }
                        const q1 = toInt(rowEl.querySelector('.qty-g1')?.value);
                        const q2 = toInt(rowEl.querySelector('.qty-g2')?.value);
                        if (q1 < 0 || q2 < 0) {
                            Swal.showValidationMessage('Qty tidak boleh negatif');
                            return false;
                        }
                        if (!totalsByDetail.has(detailId)) {
                            totalsByDetail.set(detailId, { q1: 0, q2: 0 });
                        }
                        const agg = totalsByDetail.get(detailId);
                        agg.q1 += q1;
                        agg.q2 += q2;
                        detailPayload.push({ detail_id: detailId, qty_g1: q1, qty_g2: q2 });
                    }

                    for (const it of items) {
                        const detailId = String(it.detail_id);
                        const poQty = toInt(it.jumlah);
                        const agg = totalsByDetail.get(detailId) || { q1: 0, q2: 0 };
                        if (canceledRows.has(detailId)) {
                            if (agg.q1 + agg.q2 !== 0) {
                                Swal.showValidationMessage(`Item ${escapeHtml(it.kode_barang || it.nama_barang || '')} dibatalkan, qty harus 0`);
                                return false;
                            }
                        } else if (agg.q1 + agg.q2 !== poQty) {
                            Swal.showValidationMessage(`Pembagian qty harus sama dengan Qty PO untuk item ${escapeHtml(it.kode_barang || it.nama_barang || '')}`);
                            return false;
                        }
                    }

                    for (const key of deletedRowKeys) {
                        if (!totalsByDetail.has(String(key))) {
                            detailPayload.push({ detail_id: String(key), qty_g1: 0, qty_g2: 0 });
                            totalsByDetail.set(String(key), { q1: 0, q2: 0 });
                        }
                    }

                    const total1 = detailPayload.reduce((a, r) => a + (toInt(r.qty_g1) || 0), 0);
                    const total2 = detailPayload.reduce((a, r) => a + (toInt(r.qty_g2) || 0), 0);
                    if (total1 <= 0 && total2 <= 0) {
                        Swal.showValidationMessage('Minimal ada 1 qty yang dikirim');
                        return false;
                    }

                    if (!gudang2Id && total2 > 0) {
                        Swal.showValidationMessage('Gudang 2 belum dipilih tetapi ada Qty Gudang 2');
                        return false;
                    }

                    return {
                        po_id: String(poId),
                        tanggal,
                        gudang_1_id: String(gudang1Id),
                        gudang_2_id: gudang2Id ? String(gudang2Id) : null,
                        items: detailPayload,
                        canceled_rows: Array.from(canceledRows)
                    };
                }
            });

            if (!res.isConfirmed) return;

            let saveResp;
            try {
                saveResp = await fetch('send_to_warehouse.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(res.value)
                });
            } catch (err) {
                Swal.fire('Error', 'Gagal mengirim data', 'error');
                return;
            }

            let saveJson;
            try {
                saveJson = await saveResp.json();
            } catch (err) {
                Swal.fire('Error', 'Response tidak valid', 'error');
                return;
            }

            if (!saveResp.ok || saveJson.status !== 'success') {
                Swal.fire('Error', saveJson.message || 'Gagal menyimpan', 'error');
                return;
            }

            Swal.fire('Berhasil', saveJson.message || 'Berhasil mengirim stok ke gudang', 'success')
                .then(() => window.location.reload());
        });
    });

    // Inisialisasi tombol delete dengan SweetAlert
    document.querySelectorAll('.btn-delete').forEach(button => {
        button.addEventListener('click', function() {
            const id = this.dataset.id;
            Swal.fire({
                title: 'Konfirmasi Hapus',
                text: "Apakah Anda yakin ingin menghapus PO ini?",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Ya, Hapus!',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = `delete.php?id=${id}`;
                }
            });
        });
    });
    
    // Autocomplete untuk input pencarian
    $(document).ready(function() {
        $("#no_po").autocomplete({
            source: function(request, response) {
                $.ajax({
                    url: "autocomplete.php",
                    dataType: "json",
                    data: {
                        term: request.term
                    },
                    success: function(data) {
                        response(data);
                    },
                    error: function() {
                        response([]);
                    }
                });
            },
            minLength: 2, // Mulai mencari setelah 2 karakter
            select: function(event, ui) {
                // Ketika item dipilih, isi input dengan value
                $("#no_po").val(ui.item.value);
                return false;
            }
        }).autocomplete("instance")._renderItem = function(ul, item) {
            return $("<li>")
                .append("<div>" + item.label + "</div>")
                .appendTo(ul);
        };
    });
    
    // Remove the debugging code below
    </script>
</body>
</html>
