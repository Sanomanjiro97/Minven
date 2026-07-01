<?php
session_start();
require_once '../../config.php';
require_once '../../includes/access_check.php';
require_once '../../includes/menu_access_helper.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit();
}

// Check access untuk menu purchase_order edit
if (!checkAccess('purchase_order', 'edit')) {
    $_SESSION['error'] = "Anda tidak memiliki akses untuk mengedit PO";
    header("Location: index.php");
    exit();
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    $_SESSION['error'] = "ID PO tidak valid";
    header("Location: index.php");
    exit();
}

// First check if PO exists
$sql = "SELECT * FROM purchase_order WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error'] = "Purchase Order dengan ID tersebut tidak ditemukan";
    header("Location: index.php");
    exit();
}

$po = $result->fetch_assoc();

// Then check if it's editable
if ($po['status'] !== 'draft') {
    $_SESSION['error'] = "Purchase Order ini sudah diproses dan tidak dapat diedit";
    header("Location: view.php?id=".$id);
    exit();
}

// Query untuk mengambil data header PO
$sql = "SELECT * FROM purchase_order WHERE id = ? AND status = 'draft'";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: index.php");
    exit();
}

$po = $result->fetch_assoc();

// Query untuk mengambil daftar supplier
$sql = "SELECT id, nama_supplier FROM supplier ORDER BY nama_supplier";
$supplier_result = $conn->query($sql);

// Query untuk mengambil daftar barang (akan diisi via AJAX berdasarkan supplier)
$has_harga_po = db_has_column($conn, 'barang', 'harga_po');
$sql = "SELECT b.id, b.kode_barang, b.nama_barang, b.satuan_id, s.nama_satuan, b.harga_beli" .
               ($has_harga_po ? ", b.harga_po, COALESCE(NULLIF(b.harga_po, 0), b.harga_beli) AS harga_po_default" : ", b.harga_beli AS harga_po_default") . ",
               ksb.satuan_asal_id as konversi_satuan_id,
               s2.nama_satuan as konversi_nama_satuan,
               ksb.nilai_konversi
        FROM barang b
        LEFT JOIN satuan s ON b.satuan_id = s.id
        LEFT JOIN konversi_satuan_barang ksb ON b.id = ksb.barang_id AND b.satuan_id = ksb.satuan_tujuan_id
        LEFT JOIN satuan s2 ON ksb.satuan_asal_id = s2.id
        WHERE b.supplier_id = ?
        ORDER BY b.kode_barang";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $po['supplier_id']);
$stmt->execute();
$barang_result = $stmt->get_result();

// Query untuk mengambil detail PO
$sql = "SELECT * FROM detail_purchase_order WHERE purchase_order_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $id);
$stmt->execute();
$detail_result = $stmt->get_result();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $supplier_id = $_POST['supplier_id'];
    $tanggal = $_POST['tanggal'];
    $keterangan = $_POST['keterangan'];
    $barang_ids = $_POST['barang_id'];
    $jumlah = $_POST['jumlah'];
    $keterangan_detail = $_POST['keterangan_detail'];
    
    // Hitung total item
    $total_item = array_sum($jumlah);
    
    // Update header PO
    $sql = "UPDATE purchase_order 
            SET supplier_id = ?, tanggal = ?, total_item = ?, keterangan = ? 
            WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('isisi', $supplier_id, $tanggal, $total_item, $keterangan, $id);
    
    if ($stmt->execute()) {
        // Hapus detail PO lama
        $sql = "DELETE FROM detail_purchase_order WHERE purchase_order_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $id);
        $stmt->execute();
        
        // Insert detail PO baru
        $sql = "INSERT INTO detail_purchase_order (purchase_order_id, barang_id, jumlah, harga_satuan, keterangan) 
                VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        
        foreach ($barang_ids as $key => $barang_id) {
            if ($jumlah[$key] > 0) {
                // Bersihkan format Rupiah dari harga_satuan
                $harga_bersih = str_replace(['Rp ', '.', ','], ['', '', '.'], $_POST['harga_satuan'][$key]);
                $harga = (float)$harga_bersih;
                $stmt->bind_param('iiids', $id, $barang_id, $jumlah[$key], $harga, $keterangan_detail[$key]);
                $stmt->execute();
                
                $detail_id = $conn->insert_id;
                
                // Simpan konversi jika ada
                $satuan_asal_id = $_POST['satuan_asal_id'][$key] ?? null;
                $satuan_tujuan_id = $_POST['satuan_tujuan_id'][$key] ?? null;
                $nilai_konversi = $_POST['nilai_konversi'][$key] ?? null;
                
                if (!empty($satuan_tujuan_id) && !empty($nilai_konversi)) {
                    $sql_konv = "INSERT INTO conversi_po_detail (purchase_order_id, detail_purchase_order_id, satuan_asal_id, satuan_tujuan_id, nilai_konversi)
                                VALUES (?, ?, ?, ?, ?)";
                    $stmt_konv = $conn->prepare($sql_konv);
                    $stmt_konv->bind_param('iiiid', $id, $detail_id, $satuan_asal_id, $satuan_tujuan_id, $nilai_konversi);
                    $stmt_konv->execute();
                    $stmt_konv->close();
                }
            }
        }
        
        $_SESSION['success'] = "Purchase Order berhasil diperbarui!";
        header("Location: view.php?id=" . $id);
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Purchase Order - Sistem Inventory</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.0.7/css/boxicons.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
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
            background: rgb(64, 64, 64);
            color: white;
            border: 1px solid #e2e8f0;
            padding: 1rem;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.875rem;
            letter-spacing: 0.05em;
        }
        .table td {
            padding: 15px 12px;
            vertical-align: middle;
            border-bottom: 1px solid #e9ecef;
        }
        .btn {
            border-radius: 8px;
            font-weight: 500;
        }
        .form-control, .form-select {
            border-radius: 8px;
            border: 2px solid #e9ecef;
            transition: all 0.3s ease;
        }
        .form-control:focus, .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(252, 0, 0, 0.25);
        }
        .table-hover tbody tr:hover {
            background-color: #f8f9fa;
            transform: translateY(-1px);
            transition: all 0.2s ease;
        }
        .select2-container--default .select2-selection--single {
            border-radius: 8px;
            border: 2px solid #e9ecef;
            height: 38px;
        }
        .select2-container--default .select2-selection--single:focus {
            border-color:rgb(0, 47, 255);
        }
        .alert {
            border-radius: 10px;
            border: none;
        }
        .fw-bold {
            font-weight: 700 !important;
        }
        .fw-semibold {
            font-weight: 600 !important;
        }
        .text-primary {
            color:rgb(0, 0, 0) !important;
        }
        .text-success {
            color:rgb(0, 0, 0) !important;
        }
    </style>
</head>
<body>
    <?php include '../../templates/navbar.php'; ?>
    
    <?php if(isset($_SESSION['error'])): ?>
    <script>
        Swal.fire({
            icon: 'error',
            title: 'Oops...',
            text: '<?= $_SESSION['error'] ?>',
            timer: 3000,
            showConfirmButton: false
        });
    </script>
    <?php unset($_SESSION['error']); endif; ?>
        
        <div class="container mt-4">
            <form method="POST" id="formPO">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class='bx bx-info-circle me-2'></i>
                            Edit Purchase Order
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="supplier_id" class="form-label fw-semibold">
                                        <i class='bx bx-store me-1 text-primary'></i>
                                        Supplier
                                    </label>
                                    <select name="supplier_id" id="supplier_id" class="form-select" required>
                                        <option value="">Pilih Supplier</option>
                                        <?php 
                                        $supplier_result->data_seek(0);
                                        while($supplier = $supplier_result->fetch_assoc()): 
                                        ?>
                                        <option value="<?= $supplier['id'] ?>" <?= $supplier['id'] == $po['supplier_id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($supplier['nama_supplier']) ?>
                                        </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label for="tanggal" class="form-label fw-semibold">
                                        <i class='bx bx-calendar me-1 text-primary'></i>
                                        Tanggal
                                    </label>
                                    <input type="date" class="form-control" id="tanggal" name="tanggal" 
                                           value="<?= $po['tanggal'] ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="keterangan" class="form-label fw-semibold">
                                        <i class='bx bx-note me-1 text-primary'></i>
                                        Keterangan
                                    </label>
                                    <textarea class="form-control" id="keterangan" name="keterangan" rows="4"><?= htmlspecialchars($po['keterangan']) ?></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class='bx bx-package me-2'></i>
                        Detail Barang
                    </h5>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="mb-0 fw-semibold">Daftar Item</h6>
                        <div>
                            <button type="button" class="btn btn-primary me-2" id="btn_add_multiple">
                                <i class='bx bx-plus-circle me-1'></i>Tambah Multiple Item
                            </button>
                            <button type="button" class="btn btn-primary" id="btn_add_row">
                                <i class='bx bx-plus-circle me-1'></i>Tambah Item
                            </button>
                            <button type="button" class="btn btn-info" id="loadTemplateBtn">
                                <i class='bx bx-list-check me-1'></i>Ambil dari Template
                            </button>
                        </div>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover" id="detail_table">
                            <thead>
                                <tr>
                                    <th width="5%">No</th>
                                    <th width="30%">Barang</th>
                                    <th width="10%">Jumlah</th>
                                    <th width="15%">Harga Satuan</th>
                                    <th width="15%">Total</th>
                                    <th width="20%">Keterangan Detail</th>
                                    <th width="5%">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $row_number = 1;
                                while($detail = $detail_result->fetch_assoc()): 
                                    // Ambil data konversi yang sudah tersimpan jika ada
                                    $sql_saved_konv = "SELECT * FROM conversi_po_detail WHERE detail_purchase_order_id = ?";
                                    $stmt_saved_konv = $conn->prepare($sql_saved_konv);
                                    $stmt_saved_konv->bind_param('i', $detail['id']);
                                    $stmt_saved_konv->execute();
                                    $saved_konv = $stmt_saved_konv->get_result()->fetch_assoc();
                                    $stmt_saved_konv->close();
                                ?>
                                <tr>
                                    <td class="text-center fw-bold"><?= $row_number ?></td>
                                    <td>
                                        <select name="barang_id[]" class="form-select barang-select" required>
                                            <option value="">Pilih Barang</option>
                                            <?php 
                                            $barang_result->data_seek(0);
                                            while($barang = $barang_result->fetch_assoc()): 
                                            ?>
                                            <option value="<?= $barang['id'] ?>" 
                                                    data-harga="<?= $barang['harga_po_default'] ?? $barang['harga_beli'] ?>"
                                                    data-satuan="<?= $barang['nama_satuan'] ?>"
                                                    data-satuan-id="<?= $barang['satuan_id'] ?>"
                                                    data-konversi-satuan-id="<?= $barang['konversi_satuan_id'] ?? '' ?>"
                                                    data-konversi-nama-satuan="<?= $barang['konversi_nama_satuan'] ?? '' ?>"
                                                    data-konversi-nilai="<?= $barang['nilai_konversi'] ?? '' ?>"
                                                    <?= $barang['id'] == $detail['barang_id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($barang['kode_barang'] . ' - ' . $barang['nama_barang'] . ' (' . $barang['nama_satuan'] . ')') ?>
                                            </option>
                                            <?php endwhile; ?>
                                        </select>
                                    </td>
                                    <td>
                                        <input type="number" name="jumlah[]" class="form-control jumlah" 
                                               min="0.001" step="0.001" value="<?= rtrim(rtrim((string)($detail['jumlah'] ?? ''), '0'), '.') ?>" required>
                                        <input type="hidden" name="satuan_asal_id[]" class="satuan-asal-id" value="<?= $saved_konv['satuan_asal_id'] ?? '' ?>">
                                        <input type="hidden" name="satuan_tujuan_id[]" class="satuan-tujuan-id" value="<?= $saved_konv['satuan_tujuan_id'] ?? '' ?>">
                                        <input type="hidden" name="nilai_konversi[]" class="nilai-konversi" value="<?= $saved_konv['nilai_konversi'] ?? '1' ?>">
                                    </td>
                                    <td>
                                        <input type="text" name="harga_satuan[]" class="form-control harga-satuan" 
                                               value="Rp <?= number_format($detail['harga_satuan'] ?? 0, 0, ',', '.') ?>" required>
                                    </td>
                                    <td>
                                        <input type="text" name="total[]" class="form-control total fw-bold text-success" readonly>
                                    </td>
                                    <td>
                                        <input type="text" name="keterangan_detail[]" class="form-control keterangan-detail" 
                                               value="<?= htmlspecialchars($detail['keterangan']) ?>" placeholder="Keterangan detail">
                                    </td>
                                    <td class="text-center">
                                        <button type="button" class="btn btn-danger btn-sm btn-delete-row">
                                            <i class='bx bx-trash'></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php 
                                $row_number++;
                                endwhile; 
                                ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Grand Total Section -->
                    <div class="row mt-4">
                        <div class="col-md-6">
                            <div class="alert alert-success">
                                <div class="d-flex align-items-center">
                                    <i class='bx bx-package me-2 fs-4'></i>
                                    <div>
                                        <strong>Total Item: <span id="total_items" class="fw-bold">0</span></strong>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="alert alert-success">
                                <div class="d-flex align-items-center">
                                    <i class='bx bx-money me-2 fs-4'></i>
                                    <div>
                                        <strong>Grand Total: <span id="grand_total" class="fw-bold">Rp 0</span></strong>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="text-end mt-3">
                        <a href="index.php?id=<?= $id ?>" class="btn btn-secondary me-2">
                            <i class='bx bx-arrow-back me-1'></i>Kembali
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class='bx bx-save me-1'></i>Simpan Perubahan
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <!-- Modal Template PO -->
    <div class="modal fade" id="templateModal" tabindex="-1" aria-labelledby="templateModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="templateModalLabel">Pilih Template PO</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="templateList" class="list-group">
                        <!-- Template names will be loaded here -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        let itemCounter = <?= $row_number - 1 ?>;

        $(document).ready(function() {
            // Inisialisasi Select2
            $('.barang-select').select2();

            // Function untuk format Rupiah
            function formatRupiah(angka) {
                angka = typeof angka === 'string' ? parseFloat(angka.replace(/[^\d.]/g, '')) : angka;
                
                if (angka % 1 === 0) {
                    return 'Rp ' + angka.toLocaleString('id-ID', {minimumFractionDigits: 0, maximumFractionDigits: 0});
                } else {
                    return 'Rp ' + angka.toLocaleString('id-ID', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                }
            }

            // Function untuk parse Rupiah ke float
            function parseRupiah(rupiahString) {
                let cleaned = rupiahString.replace('Rp ', '').replace(/\./g, '').replace(',', '.');
                return parseFloat(cleaned) || 0;
            }

            function formatQty(value, decimals = 3, minValue = 0) {
                const normalized = String(value ?? '').trim().replace(',', '.');
                let num = parseFloat(normalized);
                if (!Number.isFinite(num)) num = minValue;
                if (num < minValue) num = minValue;
                const fixed = num.toFixed(decimals);
                return fixed.replace(/\.?0+$/, '');
            }

            // Function untuk update total per baris
            function updateTotal($row) {
                const jumlah = parseFloat($row.find('.jumlah').val()) || 0;
                const hargaSatuan = parseRupiah($row.find('.harga-satuan').val());
                const total = jumlah * hargaSatuan;
                $row.find('.total').val(formatRupiah(total));
                updateGrandTotal();
            }

            // Function untuk update grand total dan total items
            function updateGrandTotal() {
                let grandTotal = 0;
                let totalItems = 0;
                let validRows = 0;
                
                $('.total').each(function() {
                    const total = parseRupiah($(this).val());
                    const $row = $(this).closest('tr');
                    const jumlah = parseFloat($row.find('.jumlah').val()) || 0;
                    const barangId = $row.find('.barang-select').val();
                    
                    if (barangId !== '' && jumlah > 0) {
                        grandTotal += total;
                        totalItems += jumlah;
                        validRows++;
                    }
                });
                
                $('#grand_total').text(formatRupiah(grandTotal));
                $('#total_items').text(formatQty(totalItems, 3, 0));
                
                // Update submit button state
                const $submitBtn = $('button[type="submit"]');
                if (validRows === 0) {
                    $submitBtn.prop('disabled', true).addClass('btn-secondary').removeClass('btn-primary');
                } else {
                    $submitBtn.prop('disabled', false).addClass('btn-primary').removeClass('btn-secondary');
                }
            }

            // Function to add multiple items at once
            function addMultipleItems() {
                const itemCount = prompt('Berapa item yang ingin ditambahkan? (1-10)', '3');
                const count = parseInt(itemCount);
                
                if (isNaN(count) || count < 1 || count > 10) {
                    alert('Masukkan angka antara 1-10');
                    return;
                }
                
                for (let i = 0; i < count; i++) {
                    addEmptyItemRow();
                }
                
                Swal.fire({
                    icon: 'success',
                    title: 'Berhasil!',
                    text: `${count} item berhasil ditambahkan`,
                    timer: 1500,
                    showConfirmButton: false
                });
            }

            // Function to add an empty item row
            function addEmptyItemRow() {
                itemCounter++;
                const $itemTableBody = $('#detail_table tbody');
                const newRow = `
                    <tr>
                        <td class="text-center">${itemCounter}</td>
                        <td>
                            <select name="barang_id[]" class="form-select barang-select" required>
                                <option value="">Pilih Barang</option>
                            </select>
                        </td>
                        <td>
                            <input type="number" name="jumlah[]" class="form-control jumlah" min="0.001" step="0.001" value="0" required>
                            <input type="hidden" name="satuan_asal_id[]" class="satuan-asal-id">
                            <input type="hidden" name="satuan_tujuan_id[]" class="satuan-tujuan-id">
                            <input type="hidden" name="nilai_konversi[]" class="nilai-konversi" value="1">
                        </td>
                        <td>
                            <input type="text" name="harga_satuan[]" class="form-control harga-satuan" value="Rp 0" required>
                        </td>
                        <td>
                            <input type="text" name="total[]" class="form-control total" readonly>
                        </td>
                        <td>
                            <input type="text" name="keterangan_detail[]" class="form-control keterangan-detail" placeholder="Keterangan detail">
                        </td>
                        <td class="text-center">
                            <button type="button" class="btn btn-danger btn-sm btn-delete-row">
                                <i class='bx bx-trash'></i>
                            </button>
                        </td>
                    </tr>
                `;
                $itemTableBody.append(newRow);
                const $newRow = $itemTableBody.find('tr').last();
                $newRow.find('.barang-select').select2();

                // Load barang options for the new select based on current supplier
                loadBarangOptionsForSelect($newRow.find('.barang-select'));

                // Bind events for the new row
                bindRowEvents($newRow);
                updateTotal($newRow);
            }

            // Load barang options for a specific select element based on current supplier
            function loadBarangOptionsForSelect($select) {
                const supplierId = $('#supplier_id').val();
                if (!supplierId) {
                    $select.empty().append('<option value="">Pilih Supplier Dahulu</option>');
                    return;
                }

                $select.empty().append('<option value="">Memuat barang...</option>');
                $.ajax({
                    url: 'get_barang_by_supplier.php',
                    method: 'GET',
                    data: { supplier_id: supplierId },
                    dataType: 'json',
                    success: function(response) {
                        $select.empty().append('<option value="">Pilih Barang</option>');
                        if (response && response.success && Array.isArray(response.data) && response.data.length > 0) {
                            response.data.forEach(function(item) {
                                $select.append(`<option value="${item.id}" 
                                    data-harga="${item.harga_po_default ?? item.harga_beli}"
                                    data-satuan="${item.nama_satuan}"
                                    data-satuan-id="${item.satuan_id}"
                                    data-konversi-satuan-id="${item.konversi_satuan_id || ''}"
                                    data-konversi-nama-satuan="${item.konversi_nama_satuan || ''}"
                                    data-konversi-nilai="${item.nilai_konversi || ''}"
                                >${item.kode_barang} - ${item.nama_barang} (${item.nama_satuan})</option>`);
                            });
                        } else {
                            $select.append('<option value="">Tidak ada barang untuk supplier ini</option>');
                        }
                        $select.trigger('change');
                    },
                    error: function() {
                        $select.empty().append('<option value="">Gagal memuat barang</option>');
                    }
                });
            }

            // Function to bind events for a row
            function bindRowEvents($row) {
                // Bind change event for barang select
                $row.find('.barang-select').on('change', function() {
                    const selectedOption = $(this).find(':selected');
                    const hargaBeli = selectedOption.data('harga') || 0;
                    
                    // Update data konversi
                    const satuanId = selectedOption.data('satuan-id');
                    const konversiSatuanId = selectedOption.data('konversi-satuan-id');
                    const konversiNilai = selectedOption.data('konversi-nilai');
                    
                    if (konversiSatuanId) {
                        $row.find('.satuan-asal-id').val(konversiSatuanId);
                        $row.find('.satuan-tujuan-id').val(satuanId);
                        $row.find('.nilai-konversi').val(konversiNilai);
                    } else {
                        $row.find('.satuan-asal-id').val(satuanId);
                        $row.find('.satuan-tujuan-id').val('');
                        $row.find('.nilai-konversi').val(1);
                    }
                    
                    const $hargaInput = $row.find('.harga-satuan');
                    $hargaInput.val(formatRupiah(hargaBeli));
                    updateTotal($row);
                });

                // Bind input events for quantity and price
                $row.find('.jumlah, .harga-satuan').on('input', function() {
                    updateTotal($row);
                });

                // Bind focusout/focusin for price formatting
                $row.find('.harga-satuan').on('focusout', function() {
                    const value = parseRupiah($(this).val());
                    $(this).val(formatRupiah(value));
                    updateTotal($row);
                });

                $row.find('.harga-satuan').on('focusin', function() {
                    const value = parseRupiah($(this).val());
                    $(this).val(value.toFixed(2));
                    $(this).select();
                });

                // Bind delete button click handler
                $row.find('.btn-delete-row').on('click', function() {
                    const $row = $(this).closest('tr');
                    Swal.fire({
                        title: 'Hapus Item?',
                        text: "Item ini akan dihapus dari daftar",
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#d33',
                        cancelButtonColor: '#3085d6',
                        confirmButtonText: 'Ya, Hapus!',
                        cancelButtonText: 'Batal'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            $row.remove();
                            renumberRows();
                            updateGrandTotal();
                            Swal.fire(
                                'Terhapus!',
                                'Item berhasil dihapus.',
                                'success'
                            );
                        }
                    });
                });
            }

            // Function to renumber rows
            function renumberRows() {
                $('#detail_table tbody tr').each(function(index) {
                    $(this).find('td:first').text(index + 1);
                });
                itemCounter = $('#detail_table tbody tr').length;
            }

            // Template Logic
            const $templateModal = new bootstrap.Modal(document.getElementById('templateModal'));
            const $templateList = $('#templateList');
            const $loadTemplateBtn = $('#loadTemplateBtn');

            function addItemFromTemplate(item) {
                itemCounter++;
                const qty = formatQty(item.jumlah, 3, 0.001);
                const $itemTableBody = $('#detail_table tbody');
                const newRow = `
                    <tr>
                        <td class="text-center fw-bold">${itemCounter}</td>
                        <td>
                            <select name="barang_id[]" class="form-select barang-select" required>
                                <option value="${item.id}" selected>${item.kode_barang} - ${item.nama_barang} (${item.nama_satuan})</option>
                            </select>
                        </td>
                        <td>
                            <input type="number" name="jumlah[]" class="form-control jumlah" min="0.001" step="0.001" value="${qty}" required>
                            <input type="hidden" name="satuan_asal_id[]" class="satuan-asal-id">
                            <input type="hidden" name="satuan_tujuan_id[]" class="satuan-tujuan-id">
                            <input type="hidden" name="nilai_konversi[]" class="nilai-konversi" value="1">
                        </td>
                        <td>
                            <input type="text" name="harga_satuan[]" class="form-control harga-satuan" value="${formatRupiah(item.harga_po_default ?? item.harga_beli)}" required>
                        </td>
                        <td>
                            <input type="text" name="total[]" class="form-control total fw-bold text-success" readonly>
                        </td>
                        <td>
                            <input type="text" name="keterangan_detail[]" class="form-control keterangan-detail" value="${item.keterangan || ''}" placeholder="Keterangan detail">
                        </td>
                        <td class="text-center">
                            <button type="button" class="btn btn-danger btn-sm btn-delete-row">
                                <i class='bx bx-trash'></i>
                            </button>
                        </td>
                    </tr>
                `;
                $itemTableBody.append(newRow);
                const $newRow = $itemTableBody.find('tr').last();
                $newRow.find('.barang-select').select2();

                // Load barang options and pre-select the item
                loadBarangOptionsForSelectFromTemplate($newRow.find('.barang-select'), item.id);

                // Bind events for the new row
                bindRowEvents($newRow);
                updateTotal($newRow);
            }

            function loadBarangOptionsForSelectFromTemplate($select, selectedId) {
                const supplierId = $('#supplier_id').val();
                if (!supplierId) return;

                $.ajax({
                    url: 'get_barang_by_supplier.php',
                    method: 'GET',
                    data: { supplier_id: supplierId },
                    dataType: 'json',
                    success: function(response) {
                        $select.empty().append('<option value="">Pilih Barang</option>');
                        if (response && response.success && Array.isArray(response.data)) {
                            response.data.forEach(function(item) {
                                const isSelected = item.id == selectedId ? 'selected' : '';
                                $select.append(`<option value="${item.id}" ${isSelected}
                                    data-harga="${item.harga_po_default ?? item.harga_beli}"
                                    data-satuan="${item.nama_satuan}"
                                    data-satuan-id="${item.satuan_id}"
                                    data-konversi-satuan-id="${item.konversi_satuan_id || ''}"
                                    data-konversi-nama-satuan="${item.konversi_nama_satuan || ''}"
                                    data-konversi-nilai="${item.nilai_konversi || ''}"
                                >${item.kode_barang} - ${item.nama_barang} (${item.nama_satuan})</option>`);
                            });
                        }
                        $select.trigger('change');
                    }
                });
            }

            $loadTemplateBtn.click(function() {
                const supplierId = $('#supplier_id').val();
                if (!supplierId) {
                    alert('Pilih supplier terlebih dahulu!');
                    return;
                }

                $templateList.html('<div class="text-center p-3"><div class="spinner-border spinner-border-sm" role="status"></div> Loading...</div>');
                $templateModal.show();

                $.ajax({
                    url: '../../setup/po_template_setup.php',
                    method: 'GET',
                    data: { get_template_names: 1, supplier_id: supplierId },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success && response.data.length > 0) {
                            let html = '';
                            response.data.forEach(function(template) {
                                html += `<button type="button" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center select-template" data-name="${template.nama_template}">
                                    ${template.nama_template}
                                    <span class="badge bg-primary rounded-pill">${template.item_count} item</span>
                                </button>`;
                            });
                            $templateList.html(html);
                        } else {
                            $templateList.html('<div class="alert alert-warning m-2">Tidak ada template untuk supplier ini.</div>');
                        }
                    },
                    error: function() {
                        $templateList.html('<div class="alert alert-danger m-2">Gagal mengambil daftar template.</div>');
                    }
                });
            });

            $(document).on('click', '.select-template', function() {
                const templateName = $(this).data('name');
                const supplierId = $('#supplier_id').val();
                
                $templateModal.hide();
                
                $.ajax({
                    url: '../../setup/po_template_setup.php',
                    method: 'GET',
                    data: { get_template: 1, supplier_id: supplierId, nama_template: templateName },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success && response.data.length > 0) {
                            response.data.forEach(function(item) {
                                addItemFromTemplate(item);
                            });
                        }
                    }
                });
            });

            // Supplier change event
            $('#supplier_id').on('change', function() {
                const supplierId = $(this).val();
                const $barangSelects = $('.barang-select');
                
                if (supplierId) {
                    $.ajax({
                        url: 'get_barang_by_supplier.php',
                        method: 'GET',
                        data: { supplier_id: supplierId },
                        dataType: 'json',
                        success: function(response) {
                            $barangSelects.each(function() {
                                const $select = $(this);
                                $select.empty().append('<option value="">Pilih Barang</option>');
                                
                                if (response && response.success && Array.isArray(response.data) && response.data.length > 0) {
                                    response.data.forEach(function(item) {
                                        $select.append(`<option value="${item.id}" data-harga="${item.harga_po_default ?? item.harga_beli}">${item.kode_barang} - ${item.nama_barang} (${item.nama_satuan})</option>`);
                                    });
                                }
                                $select.trigger('change');
                            });
                        },
                        error: function() {
                            $barangSelects.each(function() {
                                $(this).empty().append('<option value="">Gagal memuat barang</option>');
                            });
                        }
                    });
                } else {
                    $barangSelects.each(function() {
                        $(this).empty().append('<option value="">Pilih Supplier Dahulu</option>');
                    });
                }
            });

            // Initial binding for existing rows
            $('#detail_table tbody tr').each(function() {
                bindRowEvents($(this));
                updateTotal($(this));
            });

            // Add row button click handler
            $('#btn_add_row').click(function() {
                addEmptyItemRow();
            });

            // Add multiple items button click handler
            $('#btn_add_multiple').click(function() {
                addMultipleItems();
            });

            // Form submission handler
            $('#formPO').on('submit', function(e) {
                const validRows = $('#detail_table tbody tr').filter(function() {
                    const barangId = $(this).find('.barang-select').val();
                    const jumlah = parseFloat($(this).find('.jumlah').val()) || 0;
                    return barangId !== '' && jumlah > 0;
                }).length;

                if (validRows === 0) {
                    e.preventDefault();
                    Swal.fire({
                        icon: 'error',
                        title: 'Data Tidak Lengkap',
                        text: 'Minimal harus ada satu item dengan jumlah lebih dari 0'
                    });
                    return false;
                }

                // Show loading state
                const $submitBtn = $('button[type="submit"]');
                $submitBtn.prop('disabled', true).html('<i class="bx bx-loader-alt bx-spin"></i> Menyimpan...');
            });


        });
    </script>
</body>
</html>
