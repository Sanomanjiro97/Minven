<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
session_start();
require_once '../../config.php';
require_once '../../includes/access_check.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit();
}

 $filter_session_key = 'filter_pembelian_direct_index';
if (isset($_GET['reset']) && (string)$_GET['reset'] === '1') {
    unset($_SESSION[$filter_session_key]);
    header('Location: index.php');
    exit();
}

$saved_filters = [];
if (isset($_SESSION[$filter_session_key]) && is_array($_SESSION[$filter_session_key])) {
    $saved_filters = $_SESSION[$filter_session_key];
}

$filter_keys = ['no_transaksi', 'status', 'tanggal_dari', 'tanggal_sampai'];
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
$filter_no_transaksi = isset($_GET['no_transaksi']) ? trim((string)$_GET['no_transaksi']) : trim((string)($saved_filters['no_transaksi'] ?? ''));
$filter_status = isset($_GET['status']) ? trim((string)$_GET['status']) : trim((string)($saved_filters['status'] ?? ''));
$filter_tanggal_dari = isset($_GET['tanggal_dari']) ? trim((string)$_GET['tanggal_dari']) : trim((string)($saved_filters['tanggal_dari'] ?? ''));
$filter_tanggal_sampai = isset($_GET['tanggal_sampai']) ? trim((string)$_GET['tanggal_sampai']) : trim((string)($saved_filters['tanggal_sampai'] ?? ''));

$_SESSION[$filter_session_key] = [
    'no_transaksi' => $filter_no_transaksi,
    'status' => $filter_status,
    'tanggal_dari' => $filter_tanggal_dari,
    'tanggal_sampai' => $filter_tanggal_sampai,
];

// Build WHERE
$where = [];
$params = [];
$types = '';
if ($filter_no_transaksi !== '') {
    $where[] = "(dp.no_transaksi LIKE ? OR dp.nama_toko LIKE ? OR u.nama LIKE ?)";
    $params[] = "%$filter_no_transaksi%";
    $params[] = "%$filter_no_transaksi%";
    $params[] = "%$filter_no_transaksi%";
    $types .= 'sss';
}
if ($filter_status !== '') {
    $where[] = "dp.status = ?";
    $params[] = $filter_status;
    $types .= 's';
}
if ($filter_tanggal_dari !== '') {
    $where[] = "dp.tanggal >= ?";
    $params[] = $filter_tanggal_dari;
    $types .= 's';
}
if ($filter_tanggal_sampai !== '') {
    $where[] = "dp.tanggal <= ?";
    $params[] = $filter_tanggal_sampai;
    $types .= 's';
}

// Query untuk mengambil daftar pembelian dadakan
$sql = "SELECT dp.*, u.nama as created_by_name
        FROM direct_purchase dp
        LEFT JOIN users u ON dp.created_by = u.id";
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= " ORDER BY dp.tanggal DESC, dp.no_transaksi DESC";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("Error preparing query: " . $conn->error);
}
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

if ($result === false) {
    die("Error executing query: " . $conn->error);
}

$gudang_list = [];
if (function_exists('get_accessible_gudang_list')) {
    foreach (get_accessible_gudang_list($conn) as $g) {
        $gudang_list[] = ['id' => $g['id'], 'nama_gudang' => $g['nama_gudang']];
    }
} else {
    $res = $conn->query("SELECT id, nama_gudang FROM gudang ORDER BY nama_gudang");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $gudang_list[] = $row;
        }
    }
}

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Pembelian Dadakan - Sistem Inventory</title>
    
    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.0.7/css/boxicons.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    
    <!-- JavaScript -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
    <?php include '../../templates/navbar.php'; ?>

    <!-- Error Alert -->
    <?php if(isset($_SESSION['error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?= $_SESSION['error'] ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php 
        unset($_SESSION['error']);
    endif; 
    ?>

    <!-- Success Alert -->
    <?php if(isset($_SESSION['success']) && isset($_SESSION['added_stocks'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <strong><?= $_SESSION['success'] ?></strong>
        <h6 class="mt-2">Barang yang ditambahkan ke gudang <?= $_SESSION['gudang_info']['nama_gudang'] ?? '' ?>:</h6>
        <ul>
            <?php foreach($_SESSION['added_stocks'] as $stock): ?>
            <li><?= htmlspecialchars($stock['nama_barang']) ?>: <?= $stock['jumlah'] ?> <?= $stock['satuan'] ?></li>
            <?php endforeach; ?>
        </ul>
        <?php if(isset($_SESSION['current_stocks'])): ?>
        <h6 class="mt-2">Stok saat ini di gudang:</h6>
        <ul>
            <?php foreach($_SESSION['current_stocks'] as $stock): ?>
            <li><?= htmlspecialchars($stock['nama_barang']) ?>: <?= $stock['jumlah'] ?> <?= $stock['satuan'] ?></li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php 
        unset($_SESSION['added_stocks']);
        unset($_SESSION['current_stocks']);
        unset($_SESSION['gudang_info']);
    endif; 
    ?>
    
    <!-- Main Content -->
    <div class="container-fluid mt-4">
        <!-- Header -->
        <div class="row mb-3">
            <div class="col">
                <h2>Daftar Pembelian Mendadak</h2>
            </div>
            <div class="col text-end">
                <a href="create.php" class="btn btn-primary">
                    <i class='bx bx-plus'></i> Buat Pembelian Baru
                </a>
            </div>
        </div>

        <!-- Table Card -->
        <div class="card">
            <div class="card-body">
                <form method="get" class="mb-3">
                    <div class="row g-2 align-items-end">
                        <div class="col-md-3">
                            <label for="no_transaksi" class="form-label">Cari (No Transaksi / Toko / User)</label>
                            <input type="text" class="form-control" id="no_transaksi" name="no_transaksi" value="<?= htmlspecialchars($filter_no_transaksi) ?>" placeholder="Cari...">
                        </div>
                        <div class="col-md-2">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="">Semua</option>
                                <option value="menunggu" <?= $filter_status == 'menunggu' ? 'selected' : '' ?>>Menunggu</option>
                                <option value="payment" <?= $filter_status == 'payment' ? 'selected' : '' ?>>Payment</option>
                                <option value="stok_masuk" <?= $filter_status == 'stok_masuk' ? 'selected' : '' ?>>Stok Masuk</option>
                                <option value="selesai" <?= $filter_status == 'selesai' ? 'selected' : '' ?>>Selesai</option>
                                <option value="batal" <?= $filter_status == 'batal' ? 'selected' : '' ?>>Batal</option>
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
                    <table class="table table-bordered table-striped" id="tablePembelian">
                        <thead>
                            <tr>
                                <th class="text-center">No</th>
                                <th>No Transaksi</th>
                                <th>Tanggal</th>
                                <th>Nama Toko</th>
                                <th class="text-end">Total Item</th>
                                <th class="text-end">Total Harga</th>
                                <th>Nama Barang</th>
                                <th>Dibuat Oleh</th>
                                <th>Status</th>
                                <th class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $no = 1;
                            $has_data = false;
                            while ($row = $result->fetch_assoc()): 
                                $has_data = true;
                                // Debug: Show ID value for first few rows
                                if ($no <= 3) {
                                    echo "<!-- DEBUG: Row $no - ID: " . htmlspecialchars($row['id'] ?? 'NULL') . " -->";
                                }
                                // Query untuk mengambil nama barang dari detail pembelian
                                $detail_sql = "SELECT GROUP_CONCAT(
                                    CASE 
                                        WHEN b.nama_barang IS NOT NULL THEN CONCAT(b.nama_barang, 
                                            CASE 
                                                WHEN ddp.keterangan IS NOT NULL AND ddp.keterangan != '' 
                                                THEN CONCAT(' (', ddp.keterangan, ')')
                                                ELSE ''
                                            END)
                                        ELSE ddp.keterangan 
                                    END
                                    SEPARATOR ', ') as nama_barang
                                    FROM detail_direct_purchase ddp
                                    LEFT JOIN barang b ON ddp.barang_id = b.id
                                    WHERE ddp.direct_purchase_id = ?";
                                $detail_stmt = $conn->prepare($detail_sql);
                                $detail_stmt->bind_param('i', $row['id']);
                                $detail_stmt->execute();
                                $detail_result = $detail_stmt->get_result();
                                $detail = $detail_result->fetch_assoc();
                            ?>
                            <tr>
                                <td class="text-center"><?= $no++ ?><br><small class="text-muted">ID:<?= $row['id'] ?? 'NULL' ?></small></td>
                                <td><?= htmlspecialchars($row['no_transaksi']) ?></td>
                                <?php $tanggal_ts = !empty($row['tanggal']) ? strtotime($row['tanggal']) : 0; ?>
                                <td data-order="<?= (int)$tanggal_ts ?>"><?= $tanggal_ts ? date('d/m/Y', $tanggal_ts) : '' ?></td>
                                <td><?= htmlspecialchars($row['nama_toko']) ?></td>
                                <td class="text-end">RP<?= number_format($row['total_item']) ?></td>
                                <td class="text-end">Rp <?= number_format($row['total_harga']) ?></td>
                                <td><?= htmlspecialchars($detail['nama_barang']) ?></td>
                                <td><?= htmlspecialchars($row['created_by_name']) ?></td>
                                <td>
                                    <?php
                                    switch($row['status']) {
                                        case 'menunggu':
                                            $badge_class = 'bg-warning';
                                            $status_text = 'Menunggu';
                                            break;
                                        case 'payment':
                                            $badge_class = 'bg-primary';
                                            $status_text = 'Payment';
                                            break;
                                        case 'selesai':
                                            $badge_class = 'bg-success';
                                            $status_text = 'Selesai';
                                            break;
                                        case 'batal':
                                            $badge_class = 'bg-danger';
                                            $status_text = 'Batal';
                                            break;
                                        case 'stok_masuk': // Status baru untuk menandai sudah masuk stok
                                            $badge_class = 'bg-info';
                                            $status_text = 'Stok Masuk';
                                            break;
                                        default:
                                            $badge_class = 'bg-secondary';
                                            $status_text = ucfirst($row['status']);
                                    }
                                    ?>
                                    <span class="badge <?= $badge_class ?>"><?= $status_text ?></span>
                                </td>
                                <td class="text-center">
                                    <a href="view.php?id=<?= $row['id'] ?>" class="btn btn-info btn-sm" title="Lihat Detail & Stok Gudang">
                                        <i class='bx bx-show'></i>
                                    </a>
                                    <?php if($row['status'] == 'menunggu'): ?>
                                    <a href="edit.php?id=<?= $row['id'] ?>" class="btn btn-warning btn-sm" title="Edit">
                                        <i class='bx bx-edit'></i>
                                    </a>
                                    <button type="button" class="btn btn-primary btn-sm btn-payment"
                                            data-id="<?= $row['id'] ?>" 
                                            data-no-transaksi="<?= htmlspecialchars($row['no_transaksi']) ?>" 
                                            title="Payment"
                                            onclick="console.log('Payment button clicked:', { id: '<?= $row['id'] ?>', no_transaksi: '<?= htmlspecialchars($row['no_transaksi']) ?>' })">
                                        <i class='bx bx-money'></i>
                                        <span class="d-none"><?= htmlspecialchars($row['no_transaksi']) ?></span>
                                    </button>
                                    <button type="button" class="btn btn-danger btn-sm btn-delete"
                                            data-id="<?= $row['id'] ?>" title="Hapus">
                                        <i class='bx bx-trash'></i>
                                    </button>
                                    <?php elseif($row['status'] == 'payment'): // Tombol baru jika status payment ?>
                                    <button type="button" class="btn btn-success btn-sm btn-set-gudang"
                                            data-id="<?= $row['id'] ?>" title="Set Kirim ke Gudang">
                                        <i class='bx bx-package'></i>
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
    
    <?php if (!$has_data): ?>
    <div class="alert alert-warning">
        <strong>Perhatian:</strong> Tidak ada data pembelian yang ditemukan.
        <a href="create.php" class="btn btn-primary btn-sm ms-2">Buat Pembelian Baru</a>
    </div>
    <?php else: ?>
    <div class="alert alert-info">
        <strong>Info:</strong> Ditemukan <?= $result->num_rows ?> data pembelian.
    </div>
    <?php endif; ?>

    <!-- Modal Set Kirim ke Gudang -->
    <div class="modal fade" id="setGudangModal" tabindex="-1" aria-labelledby="setGudangModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="setGudangModalLabel">Pilih Gudang Tujuan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                                    <form id="formSetGudang" action="process_delivery.php" method="POST" onsubmit="return validateForm()">
                    <div class="modal-body">
                        <input type="hidden" name="purchase_id" id="purchaseId">
                        <div class="mb-3">
                            <label for="gudangSelect" class="form-label">Pilih Gudang:</label>
                            <select class="form-select" id="gudangSelect" name="gudang_id" required>
                                <option value="">-- Pilih Gudang --</option>
                                <?php foreach ($gudang_list as $gudang): ?>
                                    <option value="<?= $gudang['id'] ?>"><?= htmlspecialchars($gudang['nama_gudang']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">
                                Silakan pilih gudang tujuan
                            </div>
                        </div>
                        
                        <!-- Area untuk menampilkan detail barang -->
                        <div id="itemDetails" class="mt-3">
                            <h6>Detail Barang:</h6>
                            <ul id="itemList" class="list-group">
                                <!-- Detail barang akan dimuat di sini oleh JavaScript -->
                            </ul>
                        </div>
                        
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">Kirim ke Gudang</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script>
        // Fungsi validasi form
        function validateForm() {
            const gudangSelect = document.getElementById('gudangSelect');
            if (!gudangSelect.value) {
                Swal.fire({
                    title: 'Error!',
                    text: 'Silakan pilih gudang tujuan terlebih dahulu',
                    icon: 'error'
                });
                return false;
            }
            
            // Disable tombol submit untuk mencegah double submit
            const submitBtn = document.querySelector('#formSetGudang button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Memproses...';
            
            return true;
        }

        // Helper function untuk binding Set Gudang event
        function bindSetGudangEvent(button) {
            button.on('click', function() {
                const purchaseId = $(this).data('id');
                $('#purchaseId').val(purchaseId);
                
                // Kosongkan daftar item sebelumnya
                $('#itemList').empty();

                // Lakukan AJAX call untuk mengambil detail barang
                $.ajax({
                    url: 'get_purchase_details.php',
                    type: 'GET',
                    data: { id: purchaseId },
                    dataType: 'json',
                    success: function(response) {
                        if (response.redirect) {
                            window.location.href = response.redirect;
                            return;
                        }
                        if (response.status === 'success') {
                            if (response.items.length > 0) {
                                response.items.forEach(function(item) {
                                    $('#itemList').append(`
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <div>
                                                <strong>${item.kode_barang ? `[${item.kode_barang}] ` : ''}${item.nama_barang || 'Barang tidak diketahui'}</strong>
                                            </div>
                                            <span class="badge bg-primary rounded-pill">${item.jumlah} ${item.satuan}</span>
                                        </li>
                                    `);
                                });
                            } else {
                                $('#itemList').append('<li class="list-group-item">Tidak ada detail barang</li>');
                            }
                        } else {
                            $('#itemList').append('<li class="list-group-item text-danger">Gagal memuat detail barang</li>');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX Error:', { xhr, status, error });
                        
                        if (xhr.status === 401) {
                            try {
                                const response = JSON.parse(xhr.responseText);
                                if (response.redirect) {
                                    window.location.href = response.redirect;
                                    return;
                                }
                            } catch (e) {
                                console.error('Error parsing JSON response:', e);
                            }
                        }
                        
                        let errorMessage = 'Gagal memuat detail barang';
                        try {
                            const response = JSON.parse(xhr.responseText);
                            if (response.message) {
                                errorMessage = response.message;
                            }
                        } catch (e) {
                            console.error('Error parsing error response:', e);
                        }
                        
                        $('#itemList').append(`<li class="list-group-item text-danger">${errorMessage}</li>`);
                        
                        // Tampilkan error di console untuk debugging
                        console.log('Response Text:', xhr.responseText);
                    }
                });

                const setGudangModal = new bootstrap.Modal(document.getElementById('setGudangModal'));
                setGudangModal.show();
            });
        }

        // DataTables Initialization
        $(document).ready(function() {
            $('#tablePembelian').DataTable({
                "order": [[2, "desc"]],
                "searching": false,
                "language": {
                    "emptyTable": "Tidak ada data yang tersedia pada tabel ini",
                    "info": "Menampilkan _START_ sampai _END_ dari _TOTAL_ entri",
                    "infoEmpty": "Menampilkan 0 sampai 0 dari 0 entri",
                    "search": "_INPUT_",
                    "searchPlaceholder": "Cari..."
                },
                "initComplete": function() {
                    // Add attributes to search input if needed
                    $('.dataTables_filter input').attr({
                        'id': 'searchInput',
                        'name': 'search'
                    });
                }
            });

            // Handle Set Kirim ke Gudang button click
            $(document).on('click', '.btn-set-gudang', function() {
                const purchaseId = $(this).data('id');
                $('#purchaseId').val(purchaseId);
                
                // Kosongkan daftar item sebelumnya
                $('#itemList').empty();

                // Lakukan AJAX call untuk mengambil detail barang
                $.ajax({
                    url: 'get_purchase_details.php',
                    type: 'GET',
                    data: { id: purchaseId },
                    dataType: 'json',
                    success: function(response) {
                        if (response.status === 'success') {
                            // Tampilkan detail barang di modal
                            if (response.items.length > 0) {
                                response.items.forEach(function(item) {
                                    $('#itemList').append(`
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <div>
                                                <strong>${item.kode_barang ? `[${item.kode_barang}] ` : ''}${item.nama_barang || 'Barang tidak diketahui'}</strong>
                                            </div>
                                            <span class="badge bg-primary rounded-pill">${item.jumlah} ${item.satuan}</span>
                                        </li>
                                    `);
                                });
                            } else {
                                $('#itemList').append('<li class="list-group-item">Tidak ada detail barang</li>');
                            }
                        } else {
                            $('#itemList').append('<li class="list-group-item text-danger">Gagal memuat detail barang</li>');
                            console.error(response.message);
                        }
                    },
                    error: function(xhr, status, error) {
                        $('#itemList').append('<li class="list-group-item text-danger">Gagal memuat detail barang</li>');
                        console.error(error);
                    }
                });

                const setGudangModal = new bootstrap.Modal(document.getElementById('setGudangModal'));
                setGudangModal.show();
            });

            // Payment Button Handler
            $(document).on('click', '.btn-payment', function(e) {
                e.preventDefault();
                
                const button = $(this);
                const idValue = button.attr('data-id');
                const noTransaksi = button.attr('data-no-transaksi');
                const row = button.closest('tr');
                const totalHarga = row.find('td:eq(5)').text().trim();
                
                console.log('Payment clicked:', {idValue, noTransaksi, totalHarga});
            
            if (!idValue || idValue === '' || idValue === '0') {
                alert('Error: Cannot find valid transaction number. Please check if there are any purchase records.');
                return;
            }
            
            if (!noTransaksi || noTransaksi === '') {
                alert('Error: Cannot find transaction number for record ID: ' + idValue + '. Please check the data.');
                return;
            }
            
            const numericId = parseInt(idValue, 10);
            if (isNaN(numericId) || numericId <= 0) {
                alert('Error: Invalid transaction number format: ' + idValue);
                return;
            }
                
                // Disable button sementara
                button.prop('disabled', true);
                
                Swal.fire({
                    title: 'Konfirmasi Pembayaran',
                    html: `Apakah Anda yakin ingin melakukan pembayaran untuk:<br><br>
                          <strong>No. Transaksi:</strong> ${noTransaksi}<br>
                          <strong>Total:</strong> ${totalHarga}<br><br>
                          Status akan berubah menjadi "Payment"`,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: '<i class="bx bx-money"></i> Ya, Proses Pembayaran!',
                    cancelButtonText: '<i class="bx bx-x"></i> Batal',
                    allowOutsideClick: false,
                    showLoaderOnConfirm: true,
                    preConfirm: () => {
                        return new Promise((resolve, reject) => {
                            $.ajax({
                                url: 'payment.php',
                                method: 'GET',
                                data: { id: numericId },
                                dataType: 'json',
                                cache: false
                            })
                            .done(function(response) {
                                console.log('Payment Response:', response);
                                if (response.status === 'success') {
                                    resolve(response);
                                } else if (response.redirect) {
                                    window.location.href = response.redirect;
                                } else {
                                    console.error('Payment error:', response);
                                    reject(new Error(response.message || 'Terjadi kesalahan saat memproses pembayaran'));
                                }
                            })
                            .fail(function(jqXHR) {
                                console.error('Payment Error:', jqXHR);
                                let errorMsg = 'Gagal memproses pembayaran';
                                try {
                                    if (jqXHR.responseText) {
                                        const response = JSON.parse(jqXHR.responseText);
                                        console.log('Error Response:', response);
                                        errorMsg = response.message || errorMsg;
                                        if (response.redirect) {
                                            window.location.href = response.redirect;
                                            return;
                                        }
                                    }
                                } catch (e) {
                                    console.error('Parse Error:', e);
                                    console.log('Raw Response:', jqXHR.responseText);
                                }
                                reject(new Error(errorMsg));
                            });
                        })
                        .catch(error => {
                            Swal.showValidationMessage(error.message);
                            button.prop('disabled', false);
                            return false;
                        });
                    }
                }).then((result) => {
                    if (result.isConfirmed && result.value && result.value.status === 'success') {
                        // Update status di tabel tanpa reload
                        const statusCell = row.find('td:eq(8)');
                        statusCell.html('<span class="badge bg-primary">Payment</span>');
                        
                        // Sembunyikan tombol yang tidak relevan
                        const actionCell = button.closest('td');
                        actionCell.find('.btn-payment, .btn-delete, .btn-edit').remove();
                        actionCell.append(`
                            <button type="button" class="btn btn-success btn-sm btn-set-gudang" 
                                    data-id="${numericId}" title="Set Kirim ke Gudang">
                                <i class='bx bx-package'></i>
                            </button>
                        `);

                        // Tampilkan pesan sukses
                        Swal.fire({
                            title: 'Berhasil!',
                            text: 'Status pembayaran berhasil diubah',
                            icon: 'success',
                            timer: 2000,
                            showConfirmButton: false
                        });

                        // Bind event untuk tombol Set Gudang yang baru
                        bindSetGudangEvent(actionCell.find('.btn-set-gudang'));
                    }
                    
                    // Enable kembali button dalam semua kasus kecuali sukses
                    if (!result.isConfirmed || !result.value || result.value.status !== 'success') {
                        button.prop('disabled', false);
                    }
                });
            });

            // Delete Button Handler
            $('.btn-delete').on('click', function() {
                const id = $(this).data('id');
                const row = $(this).closest('tr');
                const noTransaksi = row.find('td:eq(1)').text(); // Ambil nomor transaksi
                const totalHarga = row.find('td:eq(5)').text(); // Ambil total harga
                
                Swal.fire({
                    title: 'Konfirmasi Hapus Data',
                    html: `Anda yakin ingin menghapus pembelian ini?<br><br>
                          <strong>No. Transaksi:</strong> ${noTransaksi}<br>
                          <strong>Total:</strong> ${totalHarga}<br><br>
                          Data yang sudah dihapus tidak dapat dikembalikan!`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: '<i class="bx bx-trash"></i> Ya, Hapus!',
                    cancelButtonText: '<i class="bx bx-x"></i> Batal',
                    allowOutsideClick: false,
                    showLoaderOnConfirm: true,
                    preConfirm: () => {
                        return fetch(`delete.php?id=${id}`)
                            .then(response => {
                                if (response.headers.get("content-type")?.includes("application/json")) {
                                    return response.json().then(data => {
                                        if (data.redirect) {
                                            window.location.href = data.redirect;
                                            return Promise.reject('Redirecting...');
                                        }
                                        if (!response.ok) {
                                            throw new Error(data.message || response.statusText);
                                        }
                                        return response;
                                    });
                                }
                                if (!response.ok) {
                                    throw new Error(response.statusText);
                                }
                                return response;
                            })
                            .catch(error => {
                                if (error !== 'Redirecting...') {
                                    Swal.showValidationMessage(`Request failed: ${error}`);
                                }
                            });
                    }
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.reload();
                    }
                });
            });
        });
    </script>
</body>
</html>
