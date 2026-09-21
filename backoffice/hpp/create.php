<?php
require_once __DIR__ . '/../_init.php';
bo_require_login();

$barang_result = $boMainConn->query("SELECT id, kode_barang, nama_barang, harga_beli, harga_po FROM barang ORDER BY nama_barang");

$last_hpp = $boMainConn->query("SELECT kode_hpp FROM hpp ORDER BY id DESC LIMIT 1");
$new_kode = 'HPP-001';
if ($last_hpp->num_rows > 0) {
    $row = $last_hpp->fetch_assoc();
    $last_num = intval(str_replace('HPP-', '', $row['kode_hpp']));
    $new_kode = 'HPP-' . str_pad($last_num + 1, 3, '0', STR_PAD_LEFT);
}

$headerActions = '<a class="btn btn-outline-secondary" href="' . htmlspecialchars(bo_url_for('hpp/index.php')) . '"><i class="bi bi-arrow-left me-1"></i>Kembali</a>';
bo_render_shell_start([
    'title' => 'Tambah HPP Baru - Backoffice',
    'page_title' => 'Tambah HPP Baru',
    'page_subtitle' => 'Buat data HPP baru dengan bahan baku dan biaya lainnya.',
    'active' => 'hpp',
    'header_actions' => $headerActions,
]);
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
            <div class="col-md-3">
                <label class="bo-form-label">Kode HPP</label>
                <input type="text" class="form-control" name="kode_hpp" value="<?= htmlspecialchars($new_kode) ?>" readonly>
            </div>
            <div class="col-md-5">
                <label class="bo-form-label">Nama Menu</label>
                <input type="text" class="form-control" name="nama_barang_hpp" required placeholder="Contoh: Es Kopi Susu">
            </div>
            <div class="col-md-4">
                <label class="bo-form-label">Harga Jual</label>
                <input type="text" class="form-control" name="harga_jual" id="harga_jual" required onkeyup="calculateTotals()">
            </div>
        </div>

        <hr class="my-4">
        <h5 class="mb-3">Bahan Baku</h5>
        <div id="bahan_baku_container">
            <div class="row g-3 mb-3 bahan-baku-item" data-index="0">
                <div class="col-md-4">
                    <label class="bo-form-label">Bahan Baku</label>
                    <select class="form-select select-barang" name="detail_bahan[0][barang_id]" onchange="getBarangDetail(this, 0)">
                        <option value="">-- Pilih Bahan --</option>
                        <?php 
                        $barang_result->data_seek(0);
                        while ($row = $barang_result->fetch_assoc()): 
                        ?>
                            <option value="<?= $row['id'] ?>" data-harga="<?= $row['harga_po'] > 0 ? $row['harga_po'] : $row['harga_beli'] ?>" data-satuan="pcs">
                                <?= htmlspecialchars($row['nama_barang']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="bo-form-label">Harga Satuan</label>
                    <input type="text" class="form-control harga-satuan" name="detail_bahan[0][harga_satuan]" readonly>
                </div>
                <div class="col-md-2">
                    <label class="bo-form-label">Resep (Qty)</label>
                    <input type="number" step="0.01" class="form-control qty" name="detail_bahan[0][qty]" onkeyup="calculateItemTotal(this, 0)">
                </div>
                <div class="col-md-1">
                    <label class="bo-form-label">Satuan</label>
                    <input type="text" class="form-control satuan" name="detail_bahan[0][satuan]" value="pcs" readonly>
                </div>
                <div class="col-md-2">
                    <label class="bo-form-label">Total</label>
                    <input type="text" class="form-control item-total" name="detail_bahan[0][total]" readonly>
                </div>
                <div class="col-md-1 d-flex align-items-end">
                    <button type="button" class="btn btn-danger btn-remove-bahan" onclick="removeBahan(this)">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            </div>
        </div>
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
let bahanIndex = 1;

<?php
$barang_result->data_seek(0);
$barang_list = [];
while ($row = $barang_result->fetch_assoc()) {
    $barang_list[] = [
        'id' => $row['id'],
        'nama' => $row['nama_barang'],
        'harga' => $row['harga_po'] > 0 ? $row['harga_po'] : $row['harga_beli'],
        'satuan' => 'pcs'
    ];
}
?>
const barangData = <?= json_encode($barang_list) ?>;

function formatNumber(num) {
    return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
}

function unformatNumber(str) {
    return parseFloat(str.replace(/\./g, '')) || 0;
}

function getBarangDetail(select, index) {
    const barangId = select.value;
    const barang = barangData.find(b => b.id == barangId);
    const container = select.closest('.bahan-baku-item');
    
    if (barang) {
        container.querySelector('.harga-satuan').value = formatNumber(barang.harga);
        container.querySelector('.satuan').value = barang.satuan;
        calculateItemTotal(container.querySelector('.qty'), index);
    }
}

function calculateItemTotal(input, index) {
    const container = input.closest('.bahan-baku-item');
    const harga = unformatNumber(container.querySelector('.harga-satuan').value);
    const qty = parseFloat(input.value) || 0;
    const total = harga * qty;
    container.querySelector('.item-total').value = formatNumber(total.toFixed(0));
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
    
    document.getElementById('total_hpp_display').textContent = 'Rp ' + formatNumber(totalHPP.toFixed(0));
    document.getElementById('profit_display').textContent = 'Rp ' + formatNumber(profit.toFixed(0));
}

function addBahan() {
    const container = document.getElementById('bahan_baku_container');
    const newItem = container.querySelector('.bahan-baku-item').cloneNode(true);
    newItem.dataset.index = bahanIndex;
    
    newItem.innerHTML = newItem.innerHTML.replace(/\[0\]/g, `[${bahanIndex}]`);
    newItem.querySelector('.harga-satuan').value = '';
    newItem.querySelector('.qty').value = '';
    newItem.querySelector('.item-total').value = '';
    newItem.querySelector('.satuan').value = 'pcs';
    
    const select = newItem.querySelector('.select-barang');
    select.onchange = function() {
        getBarangDetail(this, bahanIndex);
    };
    
    newItem.querySelector('.qty').onkeyup = function() {
        calculateItemTotal(this, bahanIndex);
    };
    
    container.appendChild(newItem);
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
            <input type="text" class="form-control nominal-biaya" name="detail_biaya[${index}][nominal]" onkeyup="calculateTotals()">
        </div>
        <div class="col-md-3 d-flex align-items-end">
            <button type="button" class="btn btn-danger" onclick="this.closest('.biaya-item').remove(); calculateTotals();">
                <i class="bi bi-trash"></i>
            </button>
        </div>
    `;
    container.appendChild(div);
}

document.getElementById('harga_jual').addEventListener('keyup', function() {
    this.value = formatNumber(unformatNumber(this.value));
    calculateTotals();
});
</script>
<?php $pageScripts = ob_get_clean(); ?>
<?php bo_render_shell_end($pageScripts); ?>