<?php
session_start();
require_once '../../config.php';
require_once '../../includes/access_check.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit();
}

// Check access untuk add barang
redirectIfNoAccess('barang', 'add', '../../dashboard.php');

// Ambil data kategori
$sql_kategori = "SELECT id, nama_kategori FROM kategori ORDER BY nama_kategori";
$result_kategori = $conn->query($sql_kategori);

// Ambil data satuan
$sql_satuan = "SELECT id, nama_satuan FROM satuan ORDER BY nama_satuan";
$result_satuan = $conn->query($sql_satuan);

// Ambil data supplier
$sql_supplier = "SELECT id, nama_supplier FROM supplier ORDER BY nama_supplier";
$result_supplier = $conn->query($sql_supplier);

// Generate kode barang otomatis
$sql_last_code = "SELECT kode_barang FROM barang ORDER BY id DESC LIMIT 1";
$result_last_code = $conn->query($sql_last_code);
$last_code = "BRG001";
if ($result_last_code->num_rows > 0) {
    $row = $result_last_code->fetch_assoc();
    $last_code = $row['kode_barang'];
    // Extract number and increment
    $number = intval(substr($last_code, 3)) + 1;
    $new_code = "BRG" . str_pad($number, 3, '0', STR_PAD_LEFT);
} else {
    $new_code = "BRG001";
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Barang Baru</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <style>
        .form-label {
            font-weight: 600;
        }
        .required {
            color: red;
        }
        .foto-preview img { max-width: 80px; max-height: 60px; object-fit: cover; }
    </style>
</head>
<body>
    <?php include '../../templates/navbar.php'; ?>
    
    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class='bx bx-plus-circle'></i> Tambah Barang Baru (Multi-Item)
                        </h5>
                    </div>
                    <div class="card-body">
                        <form action="process.php" method="POST" enctype="multipart/form-data" id="multiItemForm">
                            <div class="table-responsive">
                                <table class="table table-bordered align-middle" id="itemTable">
                                    <thead class="table-light">
                                        <tr>
                                            <th>No</th>
                                            <th>Nama Barang <span class="required">*</span></th>
                                            <th>Kategori <span class="required">*</span></th>
                                            <th>Satuan <span class="required">*</span></th>
                                            <th>Harga Beli <span class="required">*</span></th>
                                            <th>Harga Jual</th>
                                            <th>Supplier</th>
                                            <th>Stok Minimal</th>
                                            <th>Deskripsi</th>
                                            <th>Foto</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody id="itemTableBody">
                                        <!-- Baris item akan di-generate JS -->
                                    </tbody>
                                </table>
                            </div>
                            <button type="button" class="btn btn-success mb-3" id="addItemBtn">
                                <i class='bx bx-plus'></i> Tambah Item
                            </button>
                            <div class="d-flex justify-content-between">
                                <a href="index.php" class="btn btn-secondary">
                                    <i class='bx bx-arrow-back'></i> Kembali
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class='bx bx-save'></i> Simpan Semua Barang
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        let itemCounter = 0;
        const kategoriOptions = `<?php $result_kategori->data_seek(0); while($kategori = $result_kategori->fetch_assoc()): ?><option value="<?= $kategori['id'] ?>"><?= htmlspecialchars($kategori['nama_kategori']) ?></option><?php endwhile; ?>`;
        const satuanOptions = `<?php $result_satuan->data_seek(0); while($satuan = $result_satuan->fetch_assoc()): ?><option value="<?= $satuan['id'] ?>"><?= htmlspecialchars($satuan['nama_satuan']) ?></option><?php endwhile; ?>`;
        const supplierOptions = `<?php $result_supplier->data_seek(0); while($supplier = $result_supplier->fetch_assoc()): ?><option value="<?= $supplier['id'] ?>"><?= htmlspecialchars($supplier['nama_supplier']) ?></option><?php endwhile; ?>`;

        function addItemRow() {
            itemCounter++;
            const row = `<tr>
                <td class="text-center">${itemCounter}</td>
                <td><input type="text" name="nama_barang[]" class="form-control" required></td>
                <td><select name="kategori_id[]" class="form-select select2" required><option value="">Pilih</option>${kategoriOptions}</select></td>
                <td><select name="satuan_id[]" class="form-select select2" required><option value="">Pilih</option>${satuanOptions}</select></td>
                <td><input type="text" name="harga_beli[]" class="form-control harga-beli" required></td>
                <td><input type="text" name="harga_jual[]" class="form-control harga-jual"></td>
                <td><select name="supplier_id[]" class="form-select select2"><option value="">Pilih</option>${supplierOptions}</select></td>
                <td><input type="number" name="stok_minimal[]" class="form-control" min="0" value="0"></td>
                <td><textarea name="deskripsi[]" class="form-control" rows="1"></textarea></td>
                <td>
                    <input type="file" name="foto[]" class="form-control foto-barang" accept="image/*">
                    <div class="foto-preview mt-1" style="display:none;"><img src="" alt="Preview"></div>
                </td>
                <td class="text-center">
                    <button type="button" class="btn btn-danger btn-sm remove-item"><i class='bx bx-trash'></i></button>
                </td>
            </tr>`;
            $('#itemTableBody').append(row);
            $('.select2').select2({theme: 'bootstrap-5'});
        }

        $(document).ready(function() {
            addItemRow();
            $('#addItemBtn').on('click', function() { addItemRow(); });
            $(document).on('click', '.remove-item', function() {
                $(this).closest('tr').remove();
                // Re-number rows
                itemCounter = 0;
                $('#itemTableBody tr').each(function() {
                    itemCounter++;
                    $(this).find('td:first').text(itemCounter);
                });
            });
            // Format harga
            $(document).on('input', '.harga-beli, .harga-jual', function() {
                let value = this.value.replace(/[^\d]/g, '');
                this.value = value ? parseInt(value).toLocaleString('id-ID') : '';
            });
            // Foto preview
            $(document).on('change', '.foto-barang', function() {
                const file = this.files[0];
                const $row = $(this).closest('tr');
                const $preview = $row.find('.foto-preview');
                const $img = $preview.find('img');
                if (file) {
                    if (file.size > 2 * 1024 * 1024) {
                        alert('Ukuran file terlalu besar. Maksimal 2MB.');
                        this.value = '';
                        $preview.hide();
                        return;
                    }
                    if (!file.type.match('image.*')) {
                        alert('File harus berupa gambar.');
                        this.value = '';
                        $preview.hide();
                        return;
                    }
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        $img.attr('src', e.target.result);
                        $preview.show();
                    };
                    reader.readAsDataURL(file);
                } else {
                    $preview.hide();
                }
            });
        });
    </script>
</body>
</html> 