<?php
session_start();
require_once '../../config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit();
}

// Query untuk mengambil daftar supplier
$sql = "SELECT id, nama_supplier FROM supplier ORDER BY nama_supplier";
$supplier_result = $conn->query($sql);

// Query untuk mengambil daftar barang
// Data barang ini hanya digunakan untuk mengisi dropdown di baris pertama saat halaman dimuat
// Selanjutnya, dropdown akan diisi via AJAX berdasarkan supplier yang dipilih
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
        ORDER BY b.kode_barang";
$barang_result = $conn->query($sql);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $supplier_id = $_POST['supplier_id'] ?? null;
    $tanggal = $_POST['tanggal'] ?? null;
    $keterangan = $_POST['keterangan'] ?? '';
    $barang_ids = $_POST['barang_id'] ?? [];
    $jumlah = $_POST['jumlah'] ?? [];
    $harga_satuan = $_POST['harga_satuan'] ?? [];
    $keterangan_detail = $_POST['keterangan_detail'] ?? [];
    
    // Konversi Satuan
    $satuan_asal_ids = $_POST['satuan_asal_id'] ?? [];
    $satuan_tujuan_ids = $_POST['satuan_tujuan_id'] ?? [];
    $nilai_konversis = $_POST['nilai_konversi'] ?? [];

    $parse_harga_to_float = function($value) {
        $str = (string)($value ?? '');
        $str = preg_replace('/[^\d,]/u', '', $str);
        if ($str === '' || $str === null) return 0.0;
        $str = str_replace(',', '.', $str);
        return (float)$str;
    };
    
    // Hapus kode berikut
    /*
    // Variabel untuk menyimpan path foto
    $foto_path = null;
    
    // Proses upload foto jika ada
    if (isset($_FILES['foto_po']) && $_FILES['foto_po']['error'] == 0) {
        $upload_dir = '../../uploads/po/';
        
        // Buat direktori jika belum ada
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_name = 'po_' . date('YmdHis') . '_' . uniqid() . '.' . pathinfo($_FILES['foto_po']['name'], PATHINFO_EXTENSION);
        $upload_path = $upload_dir . $file_name;
        
        // Validasi file
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $max_size = 2 * 1024 * 1024; // 2MB
        
        if (!in_array($_FILES['foto_po']['type'], $allowed_types)) {
            $_SESSION['error'] = "Format file tidak didukung. Gunakan JPG, PNG, atau GIF.";
            header("Location: create.php");
            exit();
        }
        
        if ($_FILES['foto_po']['size'] > $max_size) {
            $_SESSION['error'] = "Ukuran file terlalu besar. Maksimal 2MB.";
            header("Location: create.php");
            exit();
        }
        
        if (move_uploaded_file($_FILES['foto_po']['tmp_name'], $upload_path)) {
            $foto_path = 'uploads/po/' . $file_name; // Simpan path relatif
        } else {
            $_SESSION['error'] = "Gagal mengupload file.";
            header("Location: create.php");
            exit();
        }
    }
    */
    
    // Validasi dasar
    if (empty($supplier_id) || !is_array($barang_ids) || count($barang_ids) === 0) {
         $_SESSION['error'] = "Data transaksi tidak lengkap atau tidak ada item.";
         header("Location: create.php");
         exit();
    }
    if (!is_array($jumlah) || !is_array($harga_satuan)) {
        $_SESSION['error'] = "Detail item tidak valid.";
        header("Location: create.php");
        exit();
    }

    // Generate nomor PO
    $tahun = date('Y');
    $bulan = date('m');
    $sql = "SELECT MAX(SUBSTRING(no_po, 12, 4)) as max_num
            FROM purchase_order
            WHERE SUBSTRING(no_po, 4, 4) = ?
            AND SUBSTRING(no_po, 8, 2) = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ss', $tahun, $bulan);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $nomor = (int)$row['max_num'] + 1;
    $no_po = 'PO-' . $tahun . $bulan . str_pad($nomor, 4, '0', STR_PAD_LEFT);

    // Hitung total item dan total harga
    $total_item = 0;
    $total_harga_po = 0;

    $detail_items = [];
    foreach ($barang_ids as $index => $barang_id) {
        $selected = trim((string)$barang_id);
        $qty = isset($jumlah[$index]) ? (int)$jumlah[$index] : 0;
        $price = $parse_harga_to_float($harga_satuan[$index] ?? '0');

        if ($selected === '') {
            $_SESSION['error'] = "Ada item yang belum memilih barang.";
            header("Location: create.php");
            exit();
        }
        if ($selected !== '' && !ctype_digit($selected)) {
            if (str_starts_with($selected, '__new__:')) {
                $nama_manual = trim(substr($selected, 8));
                if ($nama_manual === '') {
                    $_SESSION['error'] = "Nama barang manual tidak boleh kosong.";
                    header("Location: create.php");
                    exit();
                }
            }
        }

        $item_total = $qty * $price;
        $total_item += $qty;
        $total_harga_po += $item_total;

        $detail_items[] = [
            'barang_id_raw' => $selected,
            'jumlah' => $qty,
            'harga_satuan' => $price,
            'total_harga' => $item_total,
            'keterangan_detail' => $keterangan_detail[$index] ?? '',
            'satuan_asal_id' => $satuan_asal_ids[$index] ?? null,
            'satuan_tujuan_id' => $satuan_tujuan_ids[$index] ?? null,
            'nilai_konversi' => $nilai_konversis[$index] ?? null
        ];
    }

    try {
        $conn->begin_transaction();

        $find_barang_stmt = $conn->prepare("SELECT id FROM barang WHERE nama_barang = ? AND (supplier_id = ? OR supplier_id IS NULL) LIMIT 1");
        $check_kode_stmt = $conn->prepare("SELECT id FROM barang WHERE kode_barang = ? LIMIT 1");
        $has_harga_po = db_has_column($conn, 'barang', 'harga_po');
        $insert_barang_sql = $has_harga_po
            ? "INSERT INTO barang (kode_barang, nama_barang, supplier_id, harga_beli, harga_po, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())"
            : "INSERT INTO barang (kode_barang, nama_barang, supplier_id, harga_beli, created_by, created_at) VALUES (?, ?, ?, ?, ?, NOW())";
        $insert_barang_stmt = $conn->prepare($insert_barang_sql);

        if ($find_barang_stmt === false || $check_kode_stmt === false || $insert_barang_stmt === false) {
            throw new Exception("Database error: " . $conn->error);
        }

        // Insert header PO
        $sql = "INSERT INTO purchase_order (no_po, tanggal, supplier_id, total_item, total_harga, keterangan, status, created_by)
                VALUES (?, ?, ?, ?, ?, ?, 'draft', ?)";
        $stmt = $conn->prepare($sql);
        
        if ($stmt === false) {
            throw new Exception("Database error: " . $conn->error);
        }
        
        $stmt->bind_param('ssiidss', $no_po, $tanggal, $supplier_id, $total_item, $total_harga_po, $keterangan, $_SESSION['user_id']);

        if (!$stmt->execute()) {
             throw new Exception("Gagal membuat Purchase Order: " . $stmt->error);
        }
        
        $purchase_order_id = $conn->insert_id;

        // Insert detail PO
        $sql_detail = "INSERT INTO detail_purchase_order 
                       (purchase_order_id, barang_id, jumlah, harga_satuan, total_harga, keterangan_detail)
                       VALUES (?, ?, ?, ?, ?, ?)";
        $stmt_detail = $conn->prepare($sql_detail);

        foreach ($detail_items as $item) {
            $raw = $item['barang_id_raw'];
            $final_barang_id = null;
            if (ctype_digit($raw)) {
                $final_barang_id = (int)$raw;
            } else {
                $nama_barang_manual = $raw;
                if (str_starts_with($nama_barang_manual, '__new__:')) {
                    $nama_barang_manual = substr($nama_barang_manual, 8);
                }
                $nama_barang_manual = trim($nama_barang_manual);

                $find_barang_stmt->bind_param('si', $nama_barang_manual, $supplier_id);
                if (!$find_barang_stmt->execute()) {
                    throw new Exception("Gagal mencari barang: " . $find_barang_stmt->error);
                }
                $result_find = $find_barang_stmt->get_result();
                if ($result_find && ($row_find = $result_find->fetch_assoc())) {
                    $final_barang_id = (int)$row_find['id'];
                } else {
                    do {
                        $kode_barang = 'MNL-' . date('Ymd') . '-' . str_pad((string)random_int(0, 9999), 4, '0', STR_PAD_LEFT);
                        $check_kode_stmt->bind_param('s', $kode_barang);
                        if (!$check_kode_stmt->execute()) {
                            throw new Exception("Gagal cek kode barang: " . $check_kode_stmt->error);
                        }
                        $exists = $check_kode_stmt->get_result()->num_rows > 0;
                    } while ($exists);

                    $harga_beli_for_barang = $item['harga_satuan'] ?? null;
                    $harga_po_for_barang = $item['harga_satuan'] ?? null;
                    $created_by = $_SESSION['user_id'] ?? null;
                    if ($has_harga_po) {
                        $insert_barang_stmt->bind_param('ssiddi', $kode_barang, $nama_barang_manual, $supplier_id, $harga_beli_for_barang, $harga_po_for_barang, $created_by);
                    } else {
                        $insert_barang_stmt->bind_param('ssidi', $kode_barang, $nama_barang_manual, $supplier_id, $harga_beli_for_barang, $created_by);
                    }
                    if (!$insert_barang_stmt->execute()) {
                        throw new Exception("Gagal membuat barang baru: " . $insert_barang_stmt->error);
                    }
                    $final_barang_id = $conn->insert_id;
                }
            }

            $stmt_detail->bind_param('iiidds',
                $purchase_order_id,
                $final_barang_id,
                $item['jumlah'],
                $item['harga_satuan'],
                $item['total_harga'],
                $item['keterangan_detail']
            );
            if (!$stmt_detail->execute()) {
                throw new Exception("Gagal menyimpan detail item: " . $stmt_detail->error);
            }
            
            $detail_po_id = $conn->insert_id;

            // Simpan data konversi jika ada
            if (!empty($item['satuan_tujuan_id']) && !empty($item['nilai_konversi'])) {
                $sql_konversi = "INSERT INTO conversi_po_detail 
                                (purchase_order_id, detail_purchase_order_id, satuan_asal_id, satuan_tujuan_id, nilai_konversi)
                                VALUES (?, ?, ?, ?, ?)";
                $stmt_konversi = $conn->prepare($sql_konversi);
                if ($stmt_konversi) {
                    $stmt_konversi->bind_param('iiiid', 
                        $purchase_order_id, 
                        $detail_po_id, 
                        $item['satuan_asal_id'], 
                        $item['satuan_tujuan_id'], 
                        $item['nilai_konversi']
                    );
                    if (!$stmt_konversi->execute()) {
                        throw new Exception("Gagal menyimpan data konversi: " . $stmt_konversi->error);
                    }
                    $stmt_konversi->close();
                }
            }
        }

        $conn->commit();
        $_SESSION['success'] = "Purchase Order berhasil dibuat dengan nomor: " . $no_po;
        header("Location: index.php");
        exit();

    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = $e->getMessage();
        header("Location: create.php");
        exit();
    } finally {
        if (isset($stmt)) $stmt->close();
        if (isset($stmt_detail)) $stmt_detail->close();
        if (isset($find_barang_stmt)) $find_barang_stmt->close();
        if (isset($check_kode_stmt)) $check_kode_stmt->close();
        if (isset($insert_barang_stmt)) $insert_barang_stmt->close();
        $conn->close();
    }
}

// Query untuk dropdown supplier
$sql_supplier = "SELECT id, nama_supplier FROM supplier ORDER BY nama_supplier";
$supplier_result = $conn->query($sql_supplier);

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buat Purchase Order - Sistem Inventory</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.0.7/css/boxicons.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <style>
        .table-item-po tbody tr td {
            vertical-align: middle;
        }
        .table-item-po input[type="number"] {
            width: 80px; /* Adjust width as needed */
        }
        
        /* Tambahkan style untuk tabel responsif */
        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
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
        
        /* Pastikan input tidak terlalu besar */
        .form-control-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
        }
        .select2-container--default .select2-selection--single {
            height: calc(1.5em + 0.5rem + 2px);
            padding: 0.25rem 0.5rem;
            border: 1px solid #ced4da;
            border-radius: 0.2rem;
        }
        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: calc(1.5em + 0.5rem);
            padding-left: 0;
        }
        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: calc(1.5em + 0.5rem + 2px);
        }
    </style>
</head>
<body>
    <?php include '../../templates/navbar.php'; ?>

    <div class="container mt-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h2>Buat Purchase Order</h2>

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

        <form action="" method="post"> <!-- Hapus enctype="multipart/form-data" -->
          
                <div class="card-header">Detail PO</div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="supplier_id" class="form-label">Supplier</label>
                            <select class="form-select" id="supplier_id" name="supplier_id" required>
                                <option value="">Pilih Supplier</option>
                                <?php while ($sup = $supplier_result->fetch_assoc()): ?>
                                    <option value="<?= $sup['id'] ?>"><?= htmlspecialchars($sup['nama_supplier']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="tanggal" class="form-label">Tanggal PO</label>
                            <input type="date" class="form-control" id="tanggal" name="tanggal" value="<?= date('Y-m-d') ?>" required>
                        </div>
                    </div>
                    
                    <!-- Bagian ini dihapus
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="foto_po" class="form-label">Upload Foto (Opsional)</label>
                            <input type="file" class="form-control" id="foto_po" name="foto_po" accept="image/*">
                            <div class="form-text">Format yang didukung: JPG, PNG, GIF. Maksimal 2MB.</div>
                        </div>
                        <div class="col-md-6" id="preview-container" style="display: none;">
                            <label class="form-label">Preview</label>
                            <div class="border p-2">
                                <img id="preview-image" src="#" alt="Preview" style="max-width: 100%; max-height: 200px;">
                            </div>
                        </div>
                    </div>
                    -->
                    
                    <div class="mb-3">
                        <label for="keterangan" class="form-label">Keterangan (Opsional)</label>
                        <textarea class="form-control" id="keterangan" name="keterangan" rows="3"></textarea>
                    </div>

                    <hr>

                    <h4>Item Barang</h4>
                    <div class="table-responsive">
                        <table class="table table-bordered table-item-po" id="itemTable">
                        <thead>
                            <tr>
                                <th style="width: 5%;">No</th>
                                <th style="width: 25%;">Barang</th>
                                <th style="width: 10%;">Satuan</th>
                                <th style="width: 10%;">Qty</th>
                                <th style="width: 15%;">Harga</th>
                                <th style="width: 15%;">Total</th>
                                <th style="width: 15%;">Keterangan</th>
                                <th style="width: 5%;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr id="no-item-row">
                                <td colspan="8" class="text-center">Pilih Supplier terlebih dahulu untuk menampilkan daftar barang.</td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="5" class="text-end"><strong>Total Harga:</strong></td>
                                <td class="total-po">Rp 0</td>
                                <td colspan="2"></td>
                            </tr>
                        </tfoot>
                    </table>
                    <button type="button" class="btn btn-success btn-sm" id="addItemBtn" disabled><i class='bx bx-plus'></i> Tambah Item</button>
                    <button type="button" class="btn btn-info btn-sm" id="loadTemplateBtn" disabled><i class='bx bx-list-check'></i> Ambil dari Template</button>

                </div>
            </div>

            <button type="submit" class="btn btn-primary">Simpan Purchase Order</button>
            <a href="index.php" class="btn btn-secondary">Batal</a>
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

    <?php include '../../templates/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(document).ready(function() {
            let itemCounter = 0;
            const $itemTableBody = $('#itemTable tbody');
            const $addItemBtn = $('#addItemBtn');
            const $loadTemplateBtn = $('#loadTemplateBtn');
            const $templateModal = new bootstrap.Modal(document.getElementById('templateModal'));
            const $templateList = $('#templateList');
            const $supplierSelect = $('#supplier_id');
            const $grandTotalCell = $('.total-po'); // Define the grand total cell reference
            
            // Function untuk format Rupiah
            function formatRupiah(angka) {
                return new Intl.NumberFormat('id-ID', {
                    style: 'currency',
                    currency: 'IDR',
                    minimumFractionDigits: 0,
                    maximumFractionDigits: 0
                }).format(angka);
            }

            // Function untuk parse Rupiah ke angka
            function parseRupiah(rupiahStr) {
                if (!rupiahStr) return 0;
                return parseInt(rupiahStr.replace(/[^\d]/g, ''));
            }

            function formatQty(value, decimals = 0, minValue = 1) {
                const normalized = String(value ?? '').trim().replace(',', '.');
                let num = parseFloat(normalized);
                if (!Number.isFinite(num)) num = minValue;
                if (num < minValue) num = minValue;
                if (decimals === 0) return String(Math.trunc(num));
                const fixed = num.toFixed(decimals);
                return fixed.replace(/\.?0+$/, '');
            }

            function initItemSelect($select) {
                if ($select.hasClass('select2-hidden-accessible')) {
                    $select.select2('destroy');
                }
                $select.select2({
                    width: '100%',
                    placeholder: 'Pilih / ketik barang',
                    allowClear: true,
                    tags: true,
                    createTag: function(params) {
                        const term = $.trim(params.term);
                        if (term === '') return null;
                        const exists = $select.find('option').filter(function() {
                            return $(this).text().trim().toLowerCase() === term.toLowerCase();
                        }).length > 0;
                        if (exists) return null;
                        return {
                            id: `__new__:${term}`,
                            text: term,
                            newTag: true
                        };
                    }
                });
            }

            // Update total per item
            function updateItemTotal($row) {
                const jumlah = parseInt($row.find('.item-jumlah').val()) || 0;
                const hargaSatuanStr = $row.find('.item-harga-satuan').val();
                const hargaSatuan = parseRupiah(hargaSatuanStr);
                const totalHarga = jumlah * hargaSatuan;
                
                $row.find('.item-total').text(formatRupiah(totalHarga));
                updateGrandTotal();
            }

            // Update grand total
            function updateGrandTotal() {
                let grandTotal = 0;
                $itemTableBody.find('tr:not(#no-item-row)').each(function() {
                    const totalText = $(this).find('.item-total').text();
                    const total = parseRupiah(totalText);
                    grandTotal += total;
                });
                
                $('.total-po').text(formatRupiah(grandTotal));
                
                // Tambahkan input hidden untuk menyimpan total
                if (!$('input[name="total_harga"]').length) {
                    $('<input>').attr({
                        type: 'hidden',
                        name: 'total_harga',
                        value: grandTotal
                    }).appendTo('form');
                } else {
                    $('input[name="total_harga"]').val(grandTotal);
                }
            }

            // Event handler untuk input jumlah dan harga
            $itemTableBody.on('input', '.item-jumlah, .item-harga-satuan', function() {
                updateItemTotal($(this).closest('tr'));
            });
            
            // Load items when supplier is selected
            $supplierSelect.change(function() {
                const supplierId = $(this).val();
                $itemTableBody.empty(); // Clear existing items
                itemCounter = 0; // Reset counter
                $grandTotalCell.text('Rp 0'); // Reset total - now using the defined variable

                if (supplierId) {
                    $addItemBtn.prop('disabled', false);
                    $loadTemplateBtn.prop('disabled', false);
                    // Add initial empty row or fetch items via AJAX
                    // For PO, we typically add items manually after selecting supplier
                    // So, just add an empty row template
                     addEmptyItemRow();

                } else {
                    $addItemBtn.prop('disabled', true);
                    $loadTemplateBtn.prop('disabled', true);
                    $itemTableBody.html('<tr id="no-item-row"><td colspan="8" class="text-center">Pilih Supplier terlebih dahulu untuk menampilkan daftar barang.</td></tr>');
                }
            });

            // Function to add an empty item row
            function addEmptyItemRow() {
                 itemCounter++;
                 const newRow = `
                     <tr>
                         <td>${itemCounter}</td>
                         <td>
                             <select class="form-select form-select-sm item-select" name="barang_id[]" required data-row-index="${itemCounter - 1}">
                                 <option value="">Pilih Barang</option>
                             </select>
                         </td>
                         <td class="item-satuan"></td>
                         <td>
                             <input type="number" class="form-control form-control-sm item-jumlah" name="jumlah[]" value="1" min="1" required data-row-index="${itemCounter - 1}">
                             <input type="hidden" name="satuan_asal_id[]" class="item-satuan-asal-id">
                             <input type="hidden" name="satuan_tujuan_id[]" class="item-satuan-tujuan-id">
                             <input type="hidden" name="nilai_konversi[]" class="item-nilai-konversi" value="1">
                         </td>
                         <td>
                             <input type="text" class="form-control form-control-sm item-harga-satuan" name="harga_satuan[]" value="0" required data-row-index="${itemCounter - 1}">
                         </td>
                         <td class="item-total total-harga">0</td>
                         <td>
                             <input type="text" class="form-control form-control-sm item-keterangan-detail" name="keterangan_detail[]" data-row-index="${itemCounter - 1}">
                         </td>
                         <td>
                             <button type="button" class="btn btn-danger btn-sm remove-item-btn"><i class='bx bx-trash'></i></button>
                         </td>
                     </tr>
                 `;
                 $itemTableBody.append(newRow);

                 initItemSelect($itemTableBody.find(`select.item-select[data-row-index="${itemCounter - 1}"]`));

                 loadBarangOptions(itemCounter - 1);
            }


            // Add item button click handler
            $addItemBtn.click(function() {
                addEmptyItemRow();
            });

            // Remove item button click handler
            $itemTableBody.on('click', '.remove-item-btn', function() {
                $(this).closest('tr').remove();
                // Re-number rows
                $itemTableBody.find('tr:not(#no-item-row)').each(function(index) {
                    $(this).find('td:first').text(index + 1);
                    // Update data-row-index for inputs/selects
                    $(this).find('select, input').attr('data-row-index', index);
                });
                itemCounter = $itemTableBody.find('tr:not(#no-item-row)').length; // Update counter
                if (itemCounter === 0) {
                     $itemTableBody.html('<tr id="no-item-row"><td colspan="10" class="text-center">Pilih Supplier terlebih dahulu untuk menampilkan daftar barang.</td></tr>');
                }
                updateGrandTotal(); // Update total after removing
            });

            // Calculate total per item and update grand total
            $itemTableBody.on('input', '.item-jumlah, .item-harga-satuan', function() {
                const $row = $(this).closest('tr');
                const jumlah = parseFloat($row.find('.item-jumlah').val()) || 0;
                // Gunakan parseRupiah untuk mendapatkan nilai numerik dari input harga satuan
                const hargaSatuan = parseRupiah($row.find('.item-harga-satuan').val()) || 0;
                const totalHarga = jumlah * hargaSatuan;
                $row.find('.item-total').text(formatRupiah(totalHarga));
                updateGrandTotal();
            });

            // Load barang options for a specific row index
            function loadBarangOptions(rowIndex, selectedId = null) {
                 const supplierId = $supplierSelect.val();
                 const $itemSelect = $itemTableBody.find(`select.item-select[data-row-index="${rowIndex}"]`);
                 const $itemSatuanCell = $itemTableBody.find(`select.item-select[data-row-index="${rowIndex}"]`).closest('td').next('.item-satuan');


                 if (supplierId) {
                     $.ajax({
                         url: 'get_barang_by_supplier.php', // Sesuaikan path jika perlu
                         method: 'GET',
                         data: { supplier_id: supplierId },
                         dataType: 'json',
                         success: function(response) {
                             $itemSelect.empty();
                             $itemSelect.append('<option value="">Pilih Barang</option>');
                             
                             // Perbaikan: Handle response structure yang benar
                             if (response.success && response.data && response.data.length > 0) {
                                 $.each(response.data, function(index, item) {
                                     const isSelected = selectedId && item.id == selectedId ? 'selected' : '';
                                     $itemSelect.append(`<option value="${item.id}" 
                                         ${isSelected}
                                         data-harga="${item.harga_po_default ?? item.harga_beli}" 
                                         data-satuan="${item.nama_satuan}" 
                                         data-satuan-id="${item.satuan_id}"
                                         data-konversi-satuan-id="${item.konversi_satuan_id || ''}"
                                         data-konversi-nama-satuan="${item.konversi_nama_satuan || ''}"
                                         data-konversi-nilai="${item.nilai_konversi || ''}"
                                     >${item.kode_barang} - ${item.nama_barang}</option>`);
                                 });
                             } else {
                                 $itemSelect.append('<option value="">Tidak ada barang untuk supplier ini</option>');
                                 console.warn("Tidak ada barang ditemukan untuk supplier:", supplierId);
                             }
                             initItemSelect($itemSelect);
                             
                             // If selectedId was provided, trigger change to update price/satuan
                             if (selectedId) {
                                 $itemSelect.trigger('change');
                             }
                         },
                         error: function(xhr, status, error) {
                             console.error("Error loading barang:", error);
                             $itemSelect.empty();
                             $itemSelect.append('<option value="">Gagal memuat barang</option>');
                             initItemSelect($itemSelect);
                         }
                     });
                 } else {
                     $itemSelect.empty();
                     $itemSelect.append('<option value="">Pilih Supplier Dahulu</option>');
                     initItemSelect($itemSelect);
                 }
            }

            // Update harga satuan and satuan when item is selected
            $itemTableBody.on('change', '.item-select', function() {
                const $row = $(this).closest('tr');
                const selectedOption = $(this).find('option:selected');
                const selectedValue = $(this).val();
                const hargaBeli = selectedOption.data('harga') || 0;
                const satuan = selectedOption.data('satuan') || '';
                const satuanId = selectedOption.data('satuan-id') || '';
                
                // Data konversi dari master konversi barang
                const konversiSatuanId = selectedOption.data('konversi-satuan-id');
                const konversiNamaSatuan = selectedOption.data('konversi-nama-satuan');
                const konversiNilai = selectedOption.data('konversi-nilai');
                
                const $hargaInput = $row.find('.item-harga-satuan');
                const $satuanCell = $row.find('.item-satuan');
                const $satuanAsalIdInput = $row.find('.item-satuan-asal-id');
                const $satuanTujuanIdInput = $row.find('.item-satuan-tujuan-id');
                const $nilaiKonversiInput = $row.find('.item-nilai-konversi');

                if (selectedValue && String(selectedValue).startsWith('__new__:')) {
                    $hargaInput.val('0');
                    $satuanCell.text('');
                    $satuanAsalIdInput.val('');
                    $satuanTujuanIdInput.val('');
                    $nilaiKonversiInput.val('1');
                } else {
                    $hargaInput.val(formatRupiah(hargaBeli));
                    
                    // Jika ada master konversi barang, prioritaskan itu
                    if (konversiSatuanId && konversiSatuanId !== '') {
                        $satuanCell.text(konversiNamaSatuan);
                        $satuanAsalIdInput.val(konversiSatuanId);
                        $satuanTujuanIdInput.val(satuanId);
                        $nilaiKonversiInput.val(konversiNilai);
                    } else {
                        // Jika tidak ada konversi khusus barang, gunakan logika lama (satuan dasar)
                        $satuanCell.text(satuan);
                        $satuanAsalIdInput.val(satuanId);
                        $satuanTujuanIdInput.val('');
                        $nilaiKonversiInput.val('1');
                    }
                }

                $hargaInput.trigger('input');
            });

            // Event handler untuk format harga satuan saat input kehilangan fokus
            $itemTableBody.on('focusout', '.item-harga-satuan', function() {
                const value = parseRupiah($(this).val()); // Bersihkan dan ambil nilai numerik
                $(this).val(formatRupiah(value)); // Format kembali sebagai Rupiah
            });

            // Event handler untuk menghapus format Rupiah saat input mendapatkan fokus
            $itemTableBody.on('focusin', '.item-harga-satuan', function() {
                const value = parseRupiah($(this).val()); // Bersihkan dan ambil nilai numerik
                $(this).val(value); // Tampilkan hanya angka
            });

            // Initial state: disable add item button and show message
            $addItemBtn.prop('disabled', true);
            $loadTemplateBtn.prop('disabled', true);

            // Function to add item from template
            function addItemFromTemplate(item) {
                itemCounter++;
                const qty = formatQty(item.jumlah, 0, 1);
                const newRow = `
                    <tr>
                        <td>${itemCounter}</td>
                        <td>
                            <select class="form-select form-select-sm item-select" name="barang_id[]" required data-row-index="${itemCounter - 1}">
                                <option value="${item.id}" selected>${item.kode_barang} - ${item.nama_barang}</option>
                            </select>
                        </td>
                        <td class="item-satuan">${item.nama_satuan || ''}</td>
                        <td>
                            <input type="number" class="form-control form-control-sm item-jumlah" name="jumlah[]" value="${qty}" min="1" required data-row-index="${itemCounter - 1}">
                            <input type="hidden" name="satuan_asal_id[]" class="item-satuan-asal-id">
                            <input type="hidden" name="satuan_tujuan_id[]" class="item-satuan-tujuan-id">
                            <input type="hidden" name="nilai_konversi[]" class="item-nilai-konversi" value="1">
                        </td>
                        <td>
                            <input type="text" class="form-control form-control-sm item-harga-satuan" name="harga_satuan[]" value="${formatRupiah(item.harga_po_default ?? item.harga_beli)}" required data-row-index="${itemCounter - 1}">
                        </td>
                        <td class="item-total total-harga">0</td>
                        <td>
                            <input type="text" class="form-control form-control-sm item-keterangan-detail" name="keterangan_detail[]" value="${item.keterangan || ''}" data-row-index="${itemCounter - 1}">
                        </td>
                        <td>
                            <button type="button" class="btn btn-danger btn-sm remove-item-btn"><i class='bx bx-trash'></i></button>
                        </td>
                    </tr>
                `;
                
                $itemTableBody.append(newRow);

                const $select = $itemTableBody.find(`select.item-select[data-row-index="${itemCounter - 1}"]`);
                initItemSelect($select);
                
                // We still need to load other options for this select so user can change it
                loadBarangOptions(itemCounter - 1, item.id);
                
                updateItemTotal($itemTableBody.find('tr').last());
            }

            // Load template button click handler
            $loadTemplateBtn.click(function() {
                const supplierId = $supplierSelect.val();
                if (!supplierId) return;

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

            // Handle template selection
            $(document).on('click', '.select-template', function() {
                const templateName = $(this).data('name');
                const supplierId = $supplierSelect.val();
                
                $templateModal.hide();
                
                $.ajax({
                    url: '../../setup/po_template_setup.php',
                    method: 'GET',
                    data: { get_template: 1, supplier_id: supplierId, nama_template: templateName },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success && response.data.length > 0) {
                            // Clear existing items if only one empty row
                            const rowCount = $itemTableBody.find('tr:not(#no-item-row)').length;
                            const firstRowSelect = $itemTableBody.find('tr:not(#no-item-row):first .item-select').val();
                            
                            if (rowCount === 1 && !firstRowSelect) {
                                $itemTableBody.empty();
                                itemCounter = 0;
                            }

                            response.data.forEach(function(item) {
                                addItemFromTemplate(item);
                            });
                        }
                    }
                });
            });
        });
    </script>
</body>
</html>
