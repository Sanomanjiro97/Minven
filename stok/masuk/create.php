<?php
session_start();
require_once '../../config.php';
require_once '../../includes/access_check.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit();
}

// --- PHP Logic to fetch data for dropdowns ---

// Fetch list of warehouses (gudang) sesuai hak akses
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

// Fetch list of items (barang) - We will fetch all items initially for the item selection dropdown
$barang_list = [];
// Modified query to include satuan_id and join with satuan table
$sql_barang = "SELECT b.id, b.kode_barang, b.nama_barang, s.nama_satuan 
               FROM barang b
               LEFT JOIN satuan s ON b.satuan_id = s.id
               ORDER BY b.nama_barang"; // Sesuaikan nama tabel barang
$result_barang = $conn->query($sql_barang);
if ($result_barang && $result_barang->num_rows > 0) {
    while($row = $result_barang->fetch_assoc()) {
        $barang_list[] = $row;
    }
}

// --- End PHP Logic ---

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Stok Masuk - Sistem Inventory</title>
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

        body.quick-mode #submitFormBtn {
            width: 100%;
        }

        body.quick-mode #input_jumlah {
            text-align: center;
        }

        body.quick-mode #input_jumlah {
            max-width: none;
        }
    </style>
</head>
<body>
    <?php include '../../templates/navbar.php'; // Sesuaikan path ke navbar ?>

    <div class="container mt-4">
        <div class="row">
            <div class="col-md-10 offset-md-1"> <!-- Adjust column size for more content -->
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="card-title">Form Tambah Stok Masuk</h5>
                            <a href="index.php" class="btn btn-secondary">Batal</a>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- Form untuk input stok masuk -->
                        <form id="stokMasukForm" action="process.php" method="POST"> <!-- Added ID for easier selection -->

                            <!-- Field no_transaksi - Will be generated in process.php -->
                            <!-- <div class="mb-3">
                                <label for="no_transaksi" class="form-label">No Transaksi</label>
                                <input type="text" class="form-control" id="no_transaksi" name="no_transaksi" required>
                            </div> -->
                            <!-- No Transaksi field removed from form, will be generated -->

                            <div class="mb-3">
                                <label for="tanggal" class="form-label">Tanggal</label>
                                <input type="date" class="form-control" id="tanggal" name="tanggal" value="<?= date('Y-m-d') ?>" required>
                            </div>

                            <div class="mb-3">
                                <label for="gudang_tujuan_id" class="form-label">Pilih Gudang Tujuan</label>
                                <select class="form-select" id="gudang_tujuan_id" name="gudang_tujuan_id" required>
                                    <option value="">-- Pilih Gudang --</option>
                                    <?php foreach ($gudang_list as $gudang): ?>
                                        <option value="<?= htmlspecialchars($gudang['id']) ?>"><?= htmlspecialchars($gudang['nama_gudang']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="keterangan" class="form-label">Keterangan Transaksi</label>
                                <textarea class="form-control" id="keterangan" name="keterangan" rows="3"></textarea>
                            </div>

                            <hr>

                            <h5>Detail Barang Masuk</h5>

                            <?php require_once __DIR__ . '/../../includes/barcode_scan_field_transaksi.php'; ?>

                            <!-- Input fields for adding a single item -->
                            <div class="row g-3 mb-3 align-items-end">
                                <div class="col-md-3">
                                    <label for="select_barang_id" class="form-label">Barang</label>
                                    <select class="form-select select2" id="select_barang_id">
                                        <option value="">-- Pilih Barang --</option>
                                        <?php
                                        // --- REMOVED INITIAL BARANG LIST POPULATION ---
                                        // Barang akan di-load berdasarkan gudang yang dipilih
                                        ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label for="display_stok_tersedia" class="form-label">Stok Tersedia</label>
                                    <input type="text" class="form-control" id="display_stok_tersedia" value="" readonly>
                                </div>
                                <div class="col-md-1">
                                    <label for="select_satuan_id" class="form-label">Satuan</label>
                                    <select class="form-select" id="select_satuan_id" disabled>
                                        <option value="">-</option>
                                    </select>
                                </div>
                                <div class="col-md-1">
                                    <label for="input_jumlah" class="form-label">Jumlah</label>
                                    <input type="number" class="form-control" id="input_jumlah" min="1" value="1">
                                </div>
                                <div class="col-md-3">
                                    <label for="input_keterangan_item" class="form-label">Keterangan</label>
                                    <input type="text" class="form-control" id="input_keterangan_item" placeholder="Contoh: Ukuran M, Warna Biru">
                                </div>
                                <div class="col-md-2">
                                    <button type="button" class="btn btn-success w-100" id="addItemBtn">Tambah Item</button>
                                </div>
                            </div>

                            <!-- Table to display added items -->
                            <div class="table-responsive mb-3">
                                <table class="table table-bordered" id="itemsTable">
                                    <thead>
                                        <tr>
                                            <th>Kode Barang</th>
                                            <th>Nama Barang</th>
                                            <th>Keterangan</th>
                                            <th>Jumlah</th>
                                            <th>Satuan</th> <!-- Added Satuan column header -->
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <!-- Item rows will be added here by JavaScript -->
                                    </tbody>
                                </table>
                            </div>

                            <!-- Hidden input to hold item data -->
                            <input type="hidden" name="items_data" id="items_data">

                            <button type="submit" class="btn btn-primary" id="submitFormBtn">Simpan Stok Masuk</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="../../includes/barcode_scan_transaksi.js?v=<?= urlencode((string)@filemtime(__DIR__ . '/../../includes/barcode_scan_transaksi.js')) ?>"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const addItemBtn = document.getElementById('addItemBtn');
            const itemsTableBody = document.querySelector('#itemsTable tbody');
            const selectBarangId = document.getElementById('select_barang_id');
            const inputKeteranganItem = document.getElementById('input_keterangan_item');
            const inputJumlah = document.getElementById('input_jumlah');
            const itemsDataInput = document.getElementById('items_data');
            const stokMasukForm = document.getElementById('stokMasukForm');
            const submitFormBtn = document.getElementById('submitFormBtn');
            const selectSatuanId = document.getElementById('select_satuan_id');
            const displayStokTersedia = document.getElementById('display_stok_tersedia');
            const gudangTujuanSelect = document.getElementById('gudang_tujuan_id');

            let items = []; // Array to hold item data
            let currentKonversi = [];
            let suppressBarangChange = false;
            const params = new URLSearchParams(window.location.search);
            const quickMode = params.get('quick') === '1';
            const quickGudangId = params.get('gudang_tujuan_id') || '';
            const quickBarangId = params.get('barang_id') || '';


            $('#select_barang_id').select2({
                theme: 'bootstrap-5',
                width: '100%',
                placeholder: '-- Pilih Barang --',
                allowClear: true,
                minimumResultsForSearch: 0
            });

            function getCurrentBaseSatuanId() {
                const selectedOption = selectBarangId.options[selectBarangId.selectedIndex];
                return parseInt(selectedOption?.getAttribute('data-satuan-id') || '0', 10) || 0;
            }

            function getCurrentBaseSatuanName() {
                const selectedOption = selectBarangId.options[selectBarangId.selectedIndex];
                return selectedOption?.getAttribute('data-satuan') || '';
            }

            function getCurrentStokTersediaBase() {
                const selectedOption = selectBarangId.options[selectBarangId.selectedIndex];
                return parseFloat(selectedOption?.getAttribute('data-stok-tersedia') || '0') || 0;
            }

            function resetSatuanSelect() {
                selectSatuanId.innerHTML = '<option value="">-</option>';
                selectSatuanId.value = '';
                selectSatuanId.disabled = true;
            }

            function getFactorToBase(selectedSatuanId, baseSatuanId) {
                if (!selectedSatuanId || !baseSatuanId) return null;
                if (selectedSatuanId === baseSatuanId) return 1;
                for (const k of currentKonversi) {
                    const asalId = parseInt(k.satuan_asal_id, 10);
                    const tujuanId = parseInt(k.satuan_tujuan_id, 10);
                    const nilai = parseFloat(k.nilai_konversi);
                    if (!nilai || nilai <= 0) continue;
                    if (asalId === selectedSatuanId && tujuanId === baseSatuanId) return nilai;
                    if (asalId === baseSatuanId && tujuanId === selectedSatuanId) return 1 / nilai;
                }
                return null;
            }

            function normalizeSatuanName(value) {
                return String(value || '').trim().toLowerCase();
            }

            function pickKemasanSatuanId(baseSatuanId) {
                const re = /(dus|karton|box|ctn|pack|pak)/i;
                const candidates = new Map();
                const allIds = new Set();
                for (const k of currentKonversi) {
                    const asalId = parseInt(k.satuan_asal_id, 10) || 0;
                    const tujuanId = parseInt(k.satuan_tujuan_id, 10) || 0;
                    const asalName = normalizeSatuanName(k.satuan_asal_nama);
                    const tujuanName = normalizeSatuanName(k.satuan_tujuan_nama);
                    if (asalId) allIds.add(asalId);
                    if (tujuanId) allIds.add(tujuanId);
                    if (asalId && re.test(asalName)) candidates.set(asalId, asalName);
                    if (tujuanId && re.test(tujuanName)) candidates.set(tujuanId, tujuanName);
                }
                let bestId = 0;
                let bestScore = -1;
                const orderedIds = [];
                for (const id of candidates.keys()) orderedIds.push(id);
                for (const id of allIds.values()) {
                    if (!candidates.has(id)) orderedIds.push(id);
                }

                for (const id of orderedIds) {
                    if (!id || id === baseSatuanId) continue;
                    const factor = getFactorToBase(id, baseSatuanId);
                    if (!factor || factor <= 1) continue;
                    const name = candidates.get(id) || '';
                    const nameScore = !name ? 0 : (name === 'dus' ? 1000 : name.includes('dus') ? 900 : 500);
                    const score = nameScore + factor;
                    if (score > bestScore) {
                        bestScore = score;
                        bestId = id;
                    }
                }
                return bestId || null;
            }

            function updateStokDisplay() {
                const stokBase = getCurrentStokTersediaBase();
                displayStokTersedia.value = Number.isFinite(stokBase) ? String(stokBase) : '';
            }

            function populateSatuanSelect() {
                const baseSatuanId = getCurrentBaseSatuanId();
                const baseSatuanName = getCurrentBaseSatuanName();
                if (!baseSatuanId) {
                    resetSatuanSelect();
                    return;
                }

                const optionsById = new Map();
                optionsById.set(baseSatuanId, baseSatuanName || 'Satuan');

                for (const k of currentKonversi) {
                    const asalId = parseInt(k.satuan_asal_id, 10);
                    const tujuanId = parseInt(k.satuan_tujuan_id, 10);
                    if (asalId && asalId !== baseSatuanId) {
                        const factor = getFactorToBase(asalId, baseSatuanId);
                        if (factor) optionsById.set(asalId, k.satuan_asal_nama || '');
                    }
                    if (tujuanId && tujuanId !== baseSatuanId) {
                        const factor = getFactorToBase(tujuanId, baseSatuanId);
                        if (factor) optionsById.set(tujuanId, k.satuan_tujuan_nama || '');
                    }
                }

                selectSatuanId.innerHTML = '';
                for (const [id, name] of optionsById.entries()) {
                    const opt = document.createElement('option');
                    opt.value = String(id);
                    opt.textContent = name || String(id);
                    selectSatuanId.appendChild(opt);
                }
                selectSatuanId.value = String(baseSatuanId);
                selectSatuanId.disabled = optionsById.size <= 1;
            }

            async function loadKonversiForBarang(barangId) {
                if (!barangId) return [];
                try {
                    const res = await fetch(`get_konversi_barang.php?barang_id=${encodeURIComponent(barangId)}`);
                    const data = await res.json();
                    if (data && data.success && Array.isArray(data.konversi)) {
                        return data.konversi;
                    }
                } catch (e) {
                    console.warn('Gagal load master konversi:', e);
                }
                return [];
            }

            async function onBarangChanged() {
                const selectedOption = selectBarangId.options[selectBarangId.selectedIndex];
                if (!selectedOption || !selectedOption.value) {
                    displayStokTersedia.value = '';
                    resetSatuanSelect();
                    return;
                }

                currentKonversi = await loadKonversiForBarang(selectedOption.value);
                populateSatuanSelect();
                updateStokDisplay();
            }

            // Function to render items in the table
            function renderItems() {
                itemsTableBody.innerHTML = ''; // Clear current table body
                items.forEach((item, index) => {
                    const row = itemsTableBody.insertRow();
                    row.innerHTML = `
                        <td>${item.kode_barang}</td>
                        <td>${item.nama_barang}</td>
                        <td>${item.detail_barang || '-'}</td>
                        <td>${item.jumlah_input ?? item.jumlah}</td>
                        <td>${item.satuan || '-'}</td>
                        <td>
                            <button type="button" class="btn btn-danger btn-sm remove-item-btn" data-index="${index}">Hapus</button>
                        </td>
                    `;
                });
                // Update the hidden input field with JSON string of items
                itemsDataInput.value = JSON.stringify(items);

                // Enable/disable submit button based on whether there are items
                submitFormBtn.disabled = items.length === 0;
            }

            function loadBarangByGudang(gudangId) {
                $('#select_barang_id')
                    .empty()
                    .append(new Option('-- Pilih Barang --', ''))
                    .val(null)
                    .trigger('change');
                displayStokTersedia.value = '';
                resetSatuanSelect();

                if (!gudangId) return Promise.resolve({ success: false, barang: [] });

                return fetch(`get_barang_by_gudang.php?gudang_id=${gudangId}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            data.barang.forEach(item => {
                                const option = document.createElement('option');
                                option.value = item.id;
                                option.setAttribute('data-kode', item.kode_barang);
                                option.setAttribute('data-barcode', item.barcode || '');
                                option.setAttribute('data-barcode-dus', item.barcode_dus || '');
                                option.setAttribute('data-barcode-konversi', item.barcode_dus || '');
                                option.setAttribute('data-nama', item.nama_barang);
                                option.setAttribute('data-satuan-id', item.satuan_id || 0);
                                option.setAttribute('data-satuan', item.nama_satuan || '');
                                option.setAttribute('data-stok-tersedia', item.stok_tersedia || 0);
                                option.setAttribute('data-stok-awal', item.stok_awal || 0);
                                option.setAttribute('data-stok-terpakai', item.stok_terpakai || 0);
                                option.textContent = `${item.kode_barang} - ${item.nama_barang} (Tersedia: ${item.stok_tersedia || 0})`;
                                selectBarangId.appendChild(option);
                            });
                            $('#select_barang_id').trigger('change');
                        } else {
                            console.warn('API Error:', data.message);
                            console.warn('Warning:', data.message || 'No items found for the selected warehouse.');
                        }
                        return data;
                    })
                    .catch(error => {
                        console.error('Error fetching barang by gudang:', error);
                        alert('Terjadi kesalahan saat mengambil data barang.');
                        return { success: false, barang: [] };
                    });
            }

            // Event listener for gudang selection change
            gudangTujuanSelect.addEventListener('change', function() {
                loadBarangByGudang(this.value);
            });

            function enableQuickMode() {
                document.body.classList.add('quick-mode');

                const barangCol = selectBarangId?.closest('.col-md-3') || selectBarangId?.closest('[class*="col-"]');
                if (barangCol) {
                    barangCol.className = 'col-9 col-md-3';
                }

                const jumlahCol = inputJumlah?.closest('.col-md-1') || inputJumlah?.closest('[class*="col-"]');
                if (jumlahCol) {
                    jumlahCol.className = 'col-3 col-md-1';
                }

                const addItemBtnCol = addItemBtn?.closest('.col-md-2');
                if (addItemBtnCol) addItemBtnCol.style.display = 'none';

                const inputKeteranganCol = inputKeteranganItem?.closest('.col-md-3');
                if (inputKeteranganCol) inputKeteranganCol.style.display = 'none';

                const inputStokTersediaCol = displayStokTersedia?.closest('.col-md-2');
                if (inputStokTersediaCol) inputStokTersediaCol.style.display = 'none';

                const itemsTableContainer = document.getElementById('itemsTable')?.closest('.table-responsive');
                if (itemsTableContainer) itemsTableContainer.style.display = 'none';

                if (gudangTujuanSelect) {
                    gudangTujuanSelect.disabled = true;
                    let hiddenGudang = document.getElementById('quick_gudang_tujuan_id');
                    if (!hiddenGudang) {
                        hiddenGudang = document.createElement('input');
                        hiddenGudang.type = 'hidden';
                        hiddenGudang.id = 'quick_gudang_tujuan_id';
                        hiddenGudang.name = 'gudang_tujuan_id';
                        stokMasukForm.appendChild(hiddenGudang);
                    }
                    hiddenGudang.value = gudangTujuanSelect.value || quickGudangId || '';
                }
                $('#select_barang_id').prop('disabled', true);

                if (submitFormBtn) {
                    submitFormBtn.classList.add('btn-lg', 'w-100');
                }
            }

            function setQuickItems() {
                const selectedOption = selectBarangId.options[selectBarangId.selectedIndex];
                const barangId = selectBarangId.value;
                const jumlahInput = parseInt(inputJumlah.value, 10);
                const baseSatuanId = getCurrentBaseSatuanId();
                const selectedSatuanId = parseInt(selectSatuanId.value || '0', 10) || baseSatuanId;
                const factorToBase = getFactorToBase(selectedSatuanId, baseSatuanId) || 1;
                const jumlahBase = Math.round((jumlahInput || 0) * factorToBase);

                if (!barangId || isNaN(jumlahInput) || jumlahInput <= 0) {
                    items = [];
                    renderItems();
                    return;
                }

                items = [
                    {
                        barang_id: barangId,
                        kode_barang: selectedOption?.getAttribute('data-kode') || '',
                        nama_barang: selectedOption?.getAttribute('data-nama') || '',
                        detail_barang: '',
                        jumlah: jumlahBase,
                        jumlah_input: jumlahInput,
                        satuan_id: selectedSatuanId,
                        satuan: selectSatuanId.options[selectSatuanId.selectedIndex]?.textContent || '',
                        satuan_base_id: baseSatuanId,
                        satuan_base: getCurrentBaseSatuanName(),
                        nilai_konversi: factorToBase
                    }
                ];
                renderItems();
            }

            // Add real-time validation for jumlah input (optional for stok masuk)
            inputJumlah.addEventListener('input', function() {
                const selectedOption = selectBarangId.options[selectBarangId.selectedIndex];
                if (selectedOption && selectedOption.value) {
                    const stokTersedia = parseInt(selectedOption.getAttribute('data-stok-tersedia') || '0', 10);
                    const jumlah = parseInt(this.value, 10);
                    
                    // Optional validation for stok masuk (can be commented out)
                    // if (jumlah > stokTersedia) {
                    //     alert(`Jumlah tidak boleh melebihi stok tersedia (${stokTersedia})`);
                    //     this.value = stokTersedia;
                    // }
                }
                if (quickMode) setQuickItems();
            });

            $('#select_barang_id').on('change', function() {
                if (suppressBarangChange) return;
                onBarangChanged();
            });
            selectSatuanId.addEventListener('change', function() {
                updateStokDisplay();
                if (quickMode) setQuickItems();
            });

            // Add item button click handler
            addItemBtn.addEventListener('click', function() {
                const selectedOption = selectBarangId.options[selectBarangId.selectedIndex];
                if (!selectedOption || !selectBarangId.value) {
                    alert('Pilih barang terlebih dahulu.');
                    return;
                }

                const barangId = selectBarangId.value;
                const kodeBarang = selectedOption.getAttribute('data-kode');
                const namaBarang = selectedOption.getAttribute('data-nama');
                const baseSatuanId = getCurrentBaseSatuanId();
                const selectedSatuanId = parseInt(selectSatuanId.value || '0', 10) || baseSatuanId;
                const satuan = selectSatuanId.options[selectSatuanId.selectedIndex]?.textContent || '';
                const detailBarang = inputKeteranganItem.value.trim();
                const jumlahInput = parseInt(inputJumlah.value, 10);
                const factorToBase = getFactorToBase(selectedSatuanId, baseSatuanId) || 1;
                const jumlahBase = Math.round((jumlahInput || 0) * factorToBase);

                if (isNaN(jumlahInput) || jumlahInput <= 0) {
                    alert('Jumlah harus angka positif.');
                    return;
                }

                // Check if item already exists in the list
                const existingItemIndex = items.findIndex(item =>
                    item.barang_id === barangId &&
                    item.detail_barang === detailBarang &&
                    String(item.satuan_id || '') === String(selectedSatuanId)
                );

                if (existingItemIndex > -1) {
                    // If item exists, update the quantity
                    items[existingItemIndex].jumlah += jumlahBase;
                    items[existingItemIndex].jumlah_input += jumlahInput;
                } else {
                    // Otherwise, add new item
                    items.push({
                        barang_id: barangId,
                        kode_barang: kodeBarang,
                        nama_barang: namaBarang,
                        detail_barang: detailBarang,
                        jumlah: jumlahBase,
                        jumlah_input: jumlahInput,
                        satuan_id: selectedSatuanId,
                        satuan: satuan,
                        satuan_base_id: baseSatuanId,
                        satuan_base: getCurrentBaseSatuanName(),
                        nilai_konversi: factorToBase
                    });
                }

                // Clear input fields after adding
                $('#select_barang_id').val(null).trigger('change');
                inputKeteranganItem.value = '';
                inputJumlah.value = '1';
                displayStokTersedia.value = ''; // Clear stok tersedia display
                resetSatuanSelect();

                renderItems(); // Re-render the table
            });

            // Remove item button click handler (using event delegation)
            itemsTableBody.addEventListener('click', function(e) {
                if (e.target.classList.contains('remove-item-btn')) {
                    const index = parseInt(e.target.getAttribute('data-index'), 10);
                    items.splice(index, 1); // Remove item from array
                    renderItems(); // Re-render the table
                }
            });

            // Initial render to ensure submit button state is correct
            renderItems();

            if (quickMode && quickGudangId && quickBarangId) {
                gudangTujuanSelect.value = quickGudangId;
                loadBarangByGudang(quickGudangId).then(async () => {
                    $('#select_barang_id').val(String(quickBarangId)).trigger('change');
                    await onBarangChanged();
                    enableQuickMode();
                    inputJumlah.focus();
                    inputJumlah.select();
                    setQuickItems();
                });
            }

            stokMasukForm.addEventListener('submit', function() {
                if (quickMode) setQuickItems();
            });

            if (typeof initBarcodeScanTransaksi === 'function') {
                initBarcodeScanTransaksi({
                    barangSelect: selectBarangId,
                    gudangSelect: gudangTujuanSelect,
                    mode: 'masuk',
                    getItems: function() { return items; },
                    setItems: function(newItems) { items = newItems; },
                    renderItems: renderItems,
                    getDetailBarang: function() { return inputKeteranganItem.value.trim(); },
                    getGudangId: function() {
                        const hidden = document.getElementById('quick_gudang_tujuan_id');
                        if (hidden && hidden.value) return hidden.value;
                        return gudangTujuanSelect.value;
                    },
                    prepareForScan: async function(option, _, entry) {
                        const targetId = String(option.value || '');
                        const currentId = String(selectBarangId.value || '');
                        if (currentId !== targetId) {
                            suppressBarangChange = true;
                            $('#select_barang_id').val(targetId).trigger('change');
                            suppressBarangChange = false;
                            await onBarangChanged();
                        }

                        const baseSatuanId = parseInt(option?.getAttribute('data-satuan-id') || '0', 10) || 0;
                        if (!baseSatuanId) return;

                        const kind = String(entry?.kind || 'item');
                            if (kind === 'konversi' || kind === 'dus') {
                            const kemasanId = pickKemasanSatuanId(baseSatuanId);
                            if (kemasanId) {
                                selectSatuanId.value = String(kemasanId);
                                selectSatuanId.dispatchEvent(new Event('change'));
                            }
                            return;
                        }

                        selectSatuanId.value = String(baseSatuanId);
                        selectSatuanId.dispatchEvent(new Event('change'));
                    },
                    getBaseSatuanId: function(option) {
                        return parseInt(option?.getAttribute('data-satuan-id') || '0', 10) || 0;
                    },
                    getSelectedSatuanId: function(_, baseSatuanId) {
                        return parseInt(selectSatuanId.value || '0', 10) || baseSatuanId || 0;
                    },
                    getFactorToBase: function(selectedSatuanId, baseSatuanId) {
                        return getFactorToBase(selectedSatuanId, baseSatuanId) || 1;
                    },
                    getSatuanName: function(_, option) {
                        return selectSatuanId.options[selectSatuanId.selectedIndex]?.textContent || option?.getAttribute('data-satuan') || '';
                    },
                    getBaseSatuanName: function() {
                        return getCurrentBaseSatuanName();
                    }
                });
            }
        });
    </script>
</body>
</html>
