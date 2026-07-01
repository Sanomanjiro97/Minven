<?php
session_start();
require_once '../../config.php';
require_once '../../includes/access_check.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit();
}

if (!checkAccess('adjustment_in', 'add')) {
    $_SESSION['error'] = 'Anda tidak memiliki akses untuk menambah adjustment in!';
    header('Location: ../../dashboard.php');
    exit();
}

$gudang_list = [];
if (function_exists('get_accessible_gudang_list')) {
    $gudang_list = array_map(function($g) {
        return ['id' => $g['id'], 'nama_gudang' => $g['nama_gudang']];
    }, get_accessible_gudang_list($GLOBALS['conn']));
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
    <title>Tambah Adjustment In - Sistem Inventory</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.0.7/css/boxicons.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
    <style>
        .select2-container--bootstrap-5 .select2-selection--single {
            min-height: calc(2.25rem + 2px);
            padding: 0.375rem 2.25rem 0.375rem 0.75rem;
            font-size: 1rem;
            display: flex;
            align-items: center;
        }
        .card {
            border-radius: 0.75rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>
<body>
    <?php include '../../templates/navbar.php'; ?>

    <div class="container mt-4">
        <div class="row">
            <div class="col-md-10 offset-md-1">
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="card-title"><i class="bx bx-plus-circle me-2"></i>Form Tambah Adjustment In</h5>
                            <a href="index.php" class="btn btn-secondary">Batal</a>
                        </div>
                    </div>
                    <div class="card-body">
                        <form id="adjustmentInForm" action="process.php" method="POST">
                            <div class="mb-3">
                                <label for="tanggal" class="form-label">Tanggal</label>
                                <input type="date" class="form-control" id="tanggal" name="tanggal" value="<?= date('Y-m-d') ?>" required>
                            </div>

                            <div class="mb-3">
                                <label for="gudang_id" class="form-label">Pilih Gudang</label>
                                <select class="form-select" id="gudang_id" name="gudang_id" required>
                                    <option value="">-- Pilih Gudang --</option>
                                    <?php foreach ($gudang_list as $gudang): ?>
                                        <option value="<?= htmlspecialchars($gudang['id']) ?>"><?= htmlspecialchars($gudang['nama_gudang']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="keterangan" class="form-label">Keterangan Transaksi</label>
                                <textarea class="form-control" id="keterangan" name="keterangan" rows="3" placeholder="Contoh: Adjustment stok hasil opname, Koreksi stok barang rusak"></textarea>
                            </div>

                            <hr>

                            <h5>Detail Barang Adjustment In</h5>

                            <div class="row g-3 mb-3 align-items-end">
                                <div class="col-md-3">
                                    <label for="select_barang_id" class="form-label">Barang</label>
                                    <select class="form-select select2" id="select_barang_id">
                                        <option value="">-- Pilih Barang --</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label for="display_stok_tersedia" class="form-label">Stok Tersedia</label>
                                    <input type="text" class="form-control" id="display_stok_tersedia" value="" readonly>
                                </div>
                                <div class="col-md-1">
                                    <label for="display_satuan" class="form-label">Satuan</label>
                                    <input type="text" class="form-control" id="display_satuan" value="" readonly>
                                </div>
                                <div class="col-md-1">
                                    <label for="input_jumlah" class="form-label">Jumlah</label>
                                    <input type="number" class="form-control" id="input_jumlah" min="1" value="1">
                                </div>
                                <div class="col-md-3">
                                    <label for="input_keterangan_item" class="form-label">Keterangan</label>
                                    <input type="text" class="form-control" id="input_keterangan_item" placeholder="Contoh: Hasil opname gudang">
                                </div>
                                <div class="col-md-2">
                                    <button type="button" class="btn btn-success w-100" id="addItemBtn">Tambah Item</button>
                                </div>
                            </div>

                            <div class="table-responsive mb-3">
                                <table class="table table-bordered" id="itemsTable">
                                    <thead>
                                        <tr>
                                            <th>Kode Barang</th>
                                            <th>Nama Barang</th>
                                            <th>Keterangan</th>
                                            <th>Jumlah</th>
                                            <th>Satuan</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    </tbody>
                                </table>
                            </div>

                            <input type="hidden" name="items_data" id="items_data">

                            <button type="submit" class="btn btn-success" id="submitFormBtn" name="submitFormBtn">Simpan Adjustment In</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const addItemBtn = document.getElementById('addItemBtn');
            const itemsTableBody = document.querySelector('#itemsTable tbody');
            const selectBarangId = document.getElementById('select_barang_id');
            const inputKeteranganItem = document.getElementById('input_keterangan_item');
            const inputJumlah = document.getElementById('input_jumlah');
            const itemsDataInput = document.getElementById('items_data');
            const adjustmentInForm = document.getElementById('adjustmentInForm');
            const submitFormBtn = document.getElementById('submitFormBtn');
            const displaySatuan = document.getElementById('display_satuan');
            const displayStokTersedia = document.getElementById('display_stok_tersedia');
            const gudangSelect = document.getElementById('gudang_id');

            let items = [];

            $('#select_barang_id').select2({
                theme: 'bootstrap-5',
                width: '100%',
                placeholder: '-- Pilih Barang --',
                allowClear: true,
                minimumResultsForSearch: 0
            });

            function syncBarangFields() {
                const selectedOption = selectBarangId.options[selectBarangId.selectedIndex];
                if (selectedOption && selectedOption.value) {
                    const satuan = selectedOption.getAttribute('data-satuan') || '';
                    const stokTersedia = selectedOption.getAttribute('data-stok-tersedia') || '0';
                    displaySatuan.value = satuan;
                    displayStokTersedia.value = stokTersedia;
                } else {
                    displaySatuan.value = '';
                    displayStokTersedia.value = '';
                }
            }

            function renderItems() {
                itemsTableBody.innerHTML = '';
                items.forEach((item, index) => {
                    const row = itemsTableBody.insertRow();
                    row.innerHTML = `
                        <td>${item.kode_barang}</td>
                        <td>${item.nama_barang}</td>
                        <td>${item.detail_barang || '-'}</td>
                        <td>${item.jumlah}</td>
                        <td>${item.satuan || '-'}</td>
                        <td>
                            <button type="button" class="btn btn-danger btn-sm remove-item-btn" data-index="${index}">Hapus</button>
                        </td>
                    `;
                });
                itemsDataInput.value = JSON.stringify(items);
                submitFormBtn.disabled = items.length === 0;
            }

            function loadBarangByGudang(gudangId) {
                $('#select_barang_id')
                    .empty()
                    .append(new Option('-- Pilih Barang --', ''))
                    .val(null)
                    .trigger('change');
                syncBarangFields();

                if (!gudangId) return Promise.resolve({ success: false, barang: [] });

                return fetch(`../masuk/get_barang_by_gudang.php?gudang_id=${gudangId}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            data.barang.forEach(item => {
                                const option = document.createElement('option');
                                option.value = item.id;
                                option.setAttribute('data-kode', item.kode_barang);
                                option.setAttribute('data-nama', item.nama_barang);
                                option.setAttribute('data-satuan', item.nama_satuan || '');
                                option.setAttribute('data-stok-tersedia', item.stok_tersedia || 0);
                                option.setAttribute('data-stok-awal', item.stok_awal || 0);
                                option.textContent = `${item.kode_barang} - ${item.nama_barang} (Tersedia: ${item.stok_tersedia || 0})`;
                                selectBarangId.appendChild(option);
                            });
                            $('#select_barang_id').trigger('change');
                        }
                        return data;
                    })
                    .catch(error => {
                        console.error('Error fetching barang by gudang:', error);
                        alert('Terjadi kesalahan saat mengambil data barang.');
                        return { success: false, barang: [] };
                    });
            }

            gudangSelect.addEventListener('change', function() {
                loadBarangByGudang(this.value);
            });

            $('#select_barang_id').on('select2:select', syncBarangFields);
            $('#select_barang_id').on('select2:clear', syncBarangFields);

            addItemBtn.addEventListener('click', function() {
                const selectedOption = selectBarangId.options[selectBarangId.selectedIndex];
                if (!selectedOption || !selectedOption.value) {
                    alert('Silakan pilih barang terlebih dahulu.');
                    return;
                }

                const jumlah = parseInt(inputJumlah.value) || 0;
                if (jumlah <= 0) {
                    alert('Jumlah harus lebih dari 0.');
                    return;
                }

                const existingIndex = items.findIndex(item => 
                    item.barang_id == selectedOption.value && 
                    item.detail_barang === (inputKeteranganItem.value || '')
                );

                if (existingIndex !== -1) {
                    items[existingIndex].jumlah += jumlah;
                } else {
                    items.push({
                        barang_id: selectedOption.value,
                        kode_barang: selectedOption.getAttribute('data-kode') || '',
                        nama_barang: selectedOption.getAttribute('data-nama') || '',
                        satuan: selectedOption.getAttribute('data-satuan') || '',
                        jumlah: jumlah,
                        detail_barang: inputKeteranganItem.value || ''
                    });
                }

                renderItems();
                
                inputJumlah.value = 1;
                inputKeteranganItem.value = '';
                $(selectBarangId).val(null).trigger('change');
                syncBarangFields();
            });

            itemsTableBody.addEventListener('click', function(e) {
                if (e.target.classList.contains('remove-item-btn')) {
                    const index = parseInt(e.target.getAttribute('data-index'));
                    items.splice(index, 1);
                    renderItems();
                }
            });

            adjustmentInForm.addEventListener('submit', function(e) {
                if (items.length === 0) {
                    e.preventDefault();
                    alert('Silakan tambahkan minimal 1 item.');
                    return;
                }
                itemsDataInput.value = JSON.stringify(items);
            });

            renderItems();
        });
    </script>
</body>
</html>