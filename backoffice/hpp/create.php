<?php
require_once __DIR__ . '/../_init.php';
bo_require_login();

// 1. Ambil data master satuan untuk mapping satuan_id -> nama_satuan
$satuan_map = [];
$check_satuan_table = $boMainConn->query("SHOW TABLES LIKE 'satuan'");
if ($check_satuan_table && $check_satuan_table->num_rows > 0) {
    $get_satuan = $boMainConn->query("SELECT * FROM satuan");
    if ($get_satuan) {
        while ($s = $get_satuan->fetch_assoc()) {
            $nama_s = $s['nama_satuan'] ?? $s['nama'] ?? $s['satuan'] ?? '';
            if (!empty($s['id'])) {
                $satuan_map[$s['id']] = $nama_s;
            }
        }
    }
}

// 2. Query utama barang - Selalu mengambil harga_beli ter-update dari database barang
$query_barang = "
    SELECT 
        b.id, 
        b.kode_barang, 
        b.nama_barang, 
        b.harga_beli, 
        b.satuan_id,
        b.satuan AS satuan_text
    FROM barang b
    ORDER BY b.nama_barang
";
$barang_result = $boMainConn->query($query_barang);

$last_hpp = $boMainConn->query("SELECT kode_hpp FROM hpp ORDER BY id DESC LIMIT 1");
$new_kode = 'HPP-001';
if ($last_hpp && $last_hpp->num_rows > 0) {
    $row = $last_hpp->fetch_assoc();
    $last_num = intval(str_replace('HPP-', '', $row['kode_hpp']));
    $new_kode = 'HPP-' . str_pad($last_num + 1, 3, '0', STR_PAD_LEFT);
}

// 2b. Ambil data gudang untuk dropdown
$gudang_options = [];
$gudang_result = $boMainConn->query("SELECT id, nama_gudang FROM gudang ORDER BY nama_gudang ASC");
if ($gudang_result && $gudang_result->num_rows > 0) {
    while ($g = $gudang_result->fetch_assoc()) {
        $gudang_options[] = $g;
    }
}

$headerActions = '<a class="btn btn-outline-secondary" href="' . htmlspecialchars(bo_url_for('hpp/index.php')) . '"><i class="bi bi-arrow-left me-1"></i>Kembali</a>';

$extraHead = '
<link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>
<style>
    .ts-dropdown .dropdown-input-wrap { padding: 6px 8px; }
    .ts-dropdown .dropdown-input {
        width: 100%; padding: 6px 12px; font-size: 0.875rem;
        border: 1px solid #ced4da; border-radius: 4px; outline: none;
    }
    .ts-dropdown .dropdown-input:focus {
        border-color: #86b7fe; box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
    }
</style>
';

bo_render_shell_start([
    'title' => 'Tambah HPP Baru - Backoffice',
    'page_title' => 'Tambah HPP Baru',
    'page_subtitle' => 'Buat data HPP baru dengan bahan baku dan biaya lainnya.',
    'active' => 'hpp',
    'header_actions' => $headerActions,
    'extra_head' => $extraHead ?? ''
]);

// Buat array data barang untuk dikirim ke Javascript
$barang_list = [];
if ($barang_result && $barang_result->num_rows > 0) {
    while ($row = $barang_result->fetch_assoc()) {
        $satuan_item = $satuan_map[$row['satuan_id']] ?? $row['satuan_text'] ?? 'pcs';
        if (empty($satuan_item)) $satuan_item = 'pcs';

        $barang_list[] = [
            'id' => $row['id'],
            'nama' => $row['nama_barang'],
            'harga' => (float)$row['harga_beli'], // Mengambil harga_beli terbaru dari DB
            'satuan' => $satuan_item
        ];
    }
}
?>

<div class="bo-card p-4">
    <div class="bo-card-header">
        <div>
            <h3 class="bo-card-title">Form Tambah HPP</h3>
            <div class="bo-card-subtitle">Isi detail informasi HPP di bawah ini.</div>
        </div>
    </div>
    <form method="post" action="<?= htmlspecialchars(bo_url_for('hpp/process.php?action=save')) ?>" id="form_hpp">
        <div class="row g-3 mb-4">
            <div class="col-md-2">
                <label class="bo-form-label">Kode HPP</label>
                <input type="text" class="form-control" name="kode_hpp" value="<?= htmlspecialchars($new_kode) ?>" readonly>
            </div>
            <div class="col-md-2">
                <label class="bo-form-label">Gudang</label>
                <select class="form-select" name="gudang_id" required>
                    <?php foreach ($gudang_options as $g): ?>
                        <option value="<?= (int)$g['id'] ?>"><?= htmlspecialchars($g['nama_gudang']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="bo-form-label">Nama Menu</label>
                <input type="text" class="form-control" name="nama_barang_hpp" required placeholder="Contoh: Es Kopi Susu">
            </div>
            <div class="col-md-2">
                <label class="bo-form-label">Status</label>
                <select class="form-select" name="status">
                    <option value="active" selected>Aktif</option>
                    <option value="inactive">Tidak Aktif</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="bo-form-label">Harga Jual</label>
                <input type="text" class="form-control" name="harga_jual" id="harga_jual" required>
            </div>
        </div>

        <hr class="my-4">
        <h5 class="mb-3">Bahan Baku</h5>
        <div id="bahan_baku_container"></div>
        
        <button type="button" class="btn btn-primary mb-4" onclick="addBahan()">
            <i class="bi bi-plus-circle me-1"></i>Tambah Bahan
        </button>

        <hr class="my-4">
        <h5 class="mb-3">Biaya Lainnya</h5>
        <div id="biaya_lainnya_container"></div>
        <button type="button" class="btn btn-outline-primary mb-4" onclick="addBiaya()">
            <i class="bi bi-plus-circle me-1"></i>Tambah Biaya Lainnya
        </button>

        <hr class="my-4">
        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <div class="bo-card p-4 bg-light">
                    <h5 class="bo-card-title mb-3">Ringkasan</h5>
                    <div class="mb-2">
                        <strong>Total HPP:</strong> <span id="total_hpp_display">Rp 0</span>
                    </div>
                    <div class="mb-2">
                        <strong>Profit:</strong> <span id="profit_display">Rp 0</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Simpan</button>
            <a href="<?= htmlspecialchars(bo_url_for('hpp/index.php')) ?>" class="btn btn-outline-secondary">Batal</a>
        </div>
    </form>
</div>

<?php ob_start(); ?>
<script>
let bahanIndex = 0;
const barangData = <?= json_encode($barang_list) ?>;

function formatNumber(num) {
    if (isNaN(num)) return '0';
    return Math.round(num).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
}

function unformatNumber(str) {
    if (!str) return 0;
    return parseFloat(str.toString().replace(/\./g, '')) || 0;
}

function initSearchableSelect(selectElement, index) {
    const ts = new TomSelect(selectElement, {
        plugins: ['dropdown_input'],
        create: false,
        sortField: { field: "text", order: "asc" },
        placeholder: "-- Pilih Bahan --",
        allowEmptyOption: true,
        onChange: function(value) {
            getBarangDetailByVal(value, selectElement, index);
        }
    });

    const inputSearch = ts.dropdown.querySelector('.dropdown-input');
    if (inputSearch) {
        inputSearch.placeholder = 'Ketik untuk mencari bahan...';
    }
}

function getBarangDetailByVal(barangId, selectElement, index) {
    const barang = barangData.find(b => b.id == barangId);
    const container = selectElement.closest('.bahan-baku-item');
    
    if (barang && container) {
        // Mengisi harga ter-update secara otomatis dari DB
        container.querySelector('.harga-satuan').value = formatNumber(barang.harga);
        container.querySelector('.satuan').value = barang.satuan;
        calculateItemTotal(container.querySelector('.qty'), index);
    } else if (container) {
        container.querySelector('.harga-satuan').value = '';
        container.querySelector('.satuan').value = '';
        container.querySelector('.item-total').value = '';
        calculateTotals();
    }
}

function calculateItemTotal(input, index) {
    const container = input.closest('.bahan-baku-item');
    const harga = unformatNumber(container.querySelector('.harga-satuan').value);
    const qty = parseFloat(container.querySelector('.qty').value.replace(',', '.')) || 0;
    const total = harga * qty;
    
    container.querySelector('.item-total').value = formatNumber(total);
    calculateTotals();
}

function calculateTotals() {
    let totalHPP = 0;
    
    document.querySelectorAll('.bahan-baku-item').forEach(item => {
        const total = unformatNumber(item.querySelector('.item-total').value);
        totalHPP += total;
    });
    
    document.querySelectorAll('.biaya-item').forEach(item => {
        const nominal = unformatNumber(item.querySelector('.nominal-biaya').value);
        totalHPP += nominal;
    });
    
    const hargaJual = unformatNumber(document.getElementById('harga_jual').value);
    const profit = hargaJual - totalHPP;
    
    document.getElementById('total_hpp_display').textContent = 'Rp ' + formatNumber(totalHPP);
    document.getElementById('profit_display').textContent = 'Rp ' + formatNumber(profit);
}

function addBahan() {
    const container = document.getElementById('bahan_baku_container');
    const currentIndex = bahanIndex;
    
    let optionsHtml = '<option value="">-- Pilih Bahan --</option>';
    barangData.forEach(item => {
        optionsHtml += `<option value="${item.id}">${item.nama}</option>`;
    });

    const div = document.createElement('div');
    div.className = 'row g-3 mb-3 bahan-baku-item';
    div.dataset.index = currentIndex;

    // Keterangan: 'harga-satuan' kembali dikunci menggunakan atribut 'readonly'
    div.innerHTML = `
        <div class="col-md-4">
            <label class="bo-form-label">Bahan Baku</label>
            <select class="form-select select-barang" name="detail_bahan[${currentIndex}][barang_id]">
                ${optionsHtml}
            </select>
        </div>
        <div class="col-md-2">
            <label class="bo-form-label">Harga Satuan</label>
            <input type="text" class="form-control harga-satuan" name="detail_bahan[${currentIndex}][harga_satuan]" readonly>
        </div>
        <div class="col-md-2">
            <label class="bo-form-label">Resep (Qty)</label>
            <input type="text" inputmode="decimal" class="form-control qty" name="detail_bahan[${currentIndex}][qty]" oninput="calculateItemTotal(this, ${currentIndex})">
        </div>
        <div class="col-md-1">
            <label class="bo-form-label">Satuan</label>
            <input type="text" class="form-control satuan" name="detail_bahan[${currentIndex}][satuan]" readonly>
        </div>
        <div class="col-md-2">
            <label class="bo-form-label">Total</label>
            <input type="text" class="form-control item-total" name="detail_bahan[${currentIndex}][total]" readonly>
        </div>
        <div class="col-md-1 d-flex align-items-end">
            <button type="button" class="btn btn-danger btn-remove-bahan" onclick="removeBahan(this)">
                <i class="bi bi-trash"></i>
            </button>
        </div>
    `;

    container.appendChild(div);
    initSearchableSelect(div.querySelector('.select-barang'), currentIndex);
    bahanIndex++;
}

function removeBahan(btn) {
    if (document.querySelectorAll('.bahan-baku-item').length > 1) {
        btn.closest('.bahan-baku-item').remove();
        calculateTotals();
    } else {
        alert('Minimal 1 bahan baku!');
    }
}

function addBiaya() {
    const container = document.getElementById('biaya_lainnya_container');
    const index = container.children.length;
    const div = document.createElement('div');
    div.className = 'row g-3 mb-3 biaya-item';
    div.innerHTML = `
        <div class="col-md-5">
            <label class="bo-form-label">Nama Biaya</label>
            <input type="text" class="form-control" name="detail_biaya[${index}][nama_biaya]" placeholder="Contoh: Tenaga Kerja">
        </div>
        <div class="col-md-4">
            <label class="bo-form-label">Nominal</label>
            <input type="text" class="form-control nominal-biaya" name="detail_biaya[${index}][nominal]" onkeyup="this.value = formatNumber(unformatNumber(this.value)); calculateTotals();">
        </div>
        <div class="col-md-3 d-flex align-items-end">
            <button type="button" class="btn btn-danger" onclick="this.closest('.biaya-item').remove(); calculateTotals();">
                <i class="bi bi-trash"></i>
            </button>
        </div>
    `;
    container.appendChild(div);
}

document.addEventListener('DOMContentLoaded', function() {
    addBahan();

    document.getElementById('harga_jual').addEventListener('keyup', function() {
        this.value = formatNumber(unformatNumber(this.value));
        calculateTotals();
    });
});
</script>
<?php $pageScripts = ob_get_clean(); ?>
<?php bo_render_shell_end($pageScripts); ?>