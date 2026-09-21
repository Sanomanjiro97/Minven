<?php
require_once __DIR__ . '/../_init.php';
bo_require_login();

$id = intval($_GET['id']);
$hpp = $boMainConn->query("SELECT * FROM hpp WHERE id = $id")->fetch_assoc();
if (!$hpp) {
    header('Location: ' . bo_url_for('hpp/index.php'));
    exit;
}

$detail_bahan = $boMainConn->query("SELECT d.*, b.nama_barang, b.kode_barang FROM hpp_detail_bahan d LEFT JOIN barang b ON d.barang_id = b.id WHERE d.hpp_id = $id");
$detail_biaya = $boMainConn->query("SELECT * FROM hpp_detail_biaya WHERE hpp_id = $id");
$barang_result = $boMainConn->query("SELECT id, kode_barang, nama_barang, harga_beli, harga_po FROM barang ORDER BY nama_barang");

$headerActions = '<a class="btn btn-outline-secondary" href="' . htmlspecialchars(bo_url_for('hpp/index.php')) . '"><i class="bi bi-arrow-left me-1"></i>Kembali</a>';
bo_render_shell_start([
    'title' => 'Edit HPP - Backoffice',
    'page_title' => 'Edit HPP',
    'page_subtitle' => 'Perbarui data HPP yang sudah ada.',
    'active' => 'hpp',
    'header_actions' => $headerActions,
]);
?>

<div class="bo-card p-4">
    <div class="bo-card-header">
        <div>
            <h3 class="bo-card-title">Form Edit HPP</h3>
            <div class="bo-card-subtitle">Perbarui detail informasi HPP di bawah ini.</div>
        </div>
    </div>
    <form method="post" action="<?= htmlspecialchars(bo_url_for('hpp/process.php?action=update')) ?>" id="form_hpp">
        <input type="hidden" name="id" value="<?= $hpp['id'] ?>">
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <label class="bo-form-label">Kode HPP</label>
                <input type="text" class="form-control" name="kode_hpp" value="<?= htmlspecialchars($hpp['kode_hpp']) ?>" readonly>
            </div>
            <div class="col-md-5">
                <label class="bo-form-label">Nama Menu</label>
                <input type="text" class="form-control" name="nama_barang_hpp" value="<?= htmlspecialchars($hpp['nama_barang_hpp']) ?>" required>
            </div>
            <div class="col-md-4">
                <label class="bo-form-label">Harga Jual</label>
                <input type="text" class="form-control" name="harga_jual" id="harga_jual" value="<?= number_format($hpp['harga_jual'], 0, ',', '.') ?>" required onkeyup="calculateTotals()">
            </div>
        </div>

        <hr class="my-4">
        <h5 class="mb-3">Bahan Baku</h5>
        <div id="bahan_baku_container">
            <?php $index = 0; while ($row = $detail_bahan->fetch_assoc()): ?>
                <div class="row g-3 mb-3 bahan-baku-item" data-index="<?= $index ?>">
                    <div class="col-md-4">
                        <label class="bo-form-label">Bahan Baku</label>
                        <select class="form-select select-barang" name="detail_bahan[<?= $index ?>][barang_id]" onchange="getBarangDetail(this, <?= $index ?>)">
                            <option value="">-- Pilih Bahan --</option>
                            <?php
                            $barang_result->data_seek(0);
                            while ($b = $barang_result->fetch_assoc()):
                                $selected = $b['id'] == $row['barang_id'] ? 'selected' : '';
                            ?>
                                <option value="<?= $b['id'] ?>" data-harga="<?= $b['harga_po'] > 0 ? $b['harga_po'] : $b['harga_beli'] ?>" data-satuan="pcs" <?= $selected ?>>
                                    <?= htmlspecialchars($b['nama_barang']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="bo-form-label">Harga Satuan</label>
                        <input type="text" class="form-control harga-satuan" name="detail_bahan[<?= $index ?>][harga_satuan]" value="<?= number_format($row['harga_satuan'], 0, ',', '.') ?>" readonly>
                    </div>
                    <div class="col-md-2">
                        <label class="bo-form-label">Resep (Qty)</label>
                        <input type="number" step="0.01" class="form-control qty" name="detail_bahan[<?= $index ?>][qty]" value="<?= $row['qty'] ?>" onkeyup="calculateItemTotal(this, <?= $index ?>)">
                    </div>
                    <div class="col-md-1">
                        <label class="bo-form-label">Satuan</label>
                        <input type="text" class="form-control satuan" name="detail_bahan[<?= $index ?>][satuan]" value="<?= htmlspecialchars($row['satuan']) ?>" readonly>
                    </div>
                    <div class="col-md-2">
                        <label class="bo-form-label">Total</label>
                        <input type="text" class="form-control item-total" name="detail_bahan[<?= $index ?>][total]" value="<?= number_format($row['total'], 0, ',', '.') ?>" readonly>
                    </div>
                    <div class="col-md-1 d-flex align-items-end">
                        <button type="button" class="btn btn-danger btn-remove-bahan" onclick="removeBahan(this)">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </div>
            <?php $index++; endwhile; ?>
        </div>
        <button type="button" class="btn btn-primary mb-4" onclick="addBahan()">
            <i class="bi bi-plus-circle me-1"></i>Tambah Bahan
        </button>

        <hr class="my-4">
        <h5 class="mb-3">Biaya Lainnya</h5>
        <div id="biaya_lainnya_container">
            <?php $biaya_index = 0; while ($row = $detail_biaya->fetch_assoc()): ?>
                <div class="row g-3 mb-3 biaya-item">
                    <div class="col-md-5">
                        <label class="bo-form-label">Nama Biaya</label>
                        <input type="text" class="form-control" name="detail_biaya[<?= $biaya_index ?>][nama_biaya]" value="<?= htmlspecialchars($row['nama_biaya']) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="bo-form-label">Nominal</label>
                        <input type="text" class="form-control nominal-biaya" name="detail_biaya[<?= $biaya_index ?>][nominal]" value="<?= number_format($row['nominal'], 0, ',', '.') ?>" onkeyup="calculateTotals()">
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="button" class="btn btn-danger" onclick="this.closest('.biaya-item').remove(); calculateTotals();">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </div>
            <?php $biaya_index++; endwhile; ?>
        </div>
        <button type="button" class="btn btn-outline-primary mb-4" onclick="addBiaya()">
            <i class="bi bi-plus-circle me-1"></i>Tambah Biaya Lainnya
        </button>

        <hr class="my-4">
        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <div class="bo-card p-4 bg-light">
                    <h5 class="bo-card-title mb-3">Ringkasan</h5>
                    <div class="mb-2">
                        <strong>Total HPP:</strong> <span id="total_hpp_display">Rp <?= number_format($hpp['hpp_total'], 0, ',', '.') ?></span>
                    </div>
                    <div class="mb-2">
                        <strong>Profit:</strong> <span id="profit_display">Rp <?= number_format($hpp['profit'], 0, ',', '.') ?></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Update</button>
            <a href="<?= htmlspecialchars(bo_url_for('hpp/index.php')) ?>" class="btn btn-outline-secondary">Batal</a>
        </div>
    </form>
</div>

<?php ob_start(); ?>
<script>
let bahanIndex = <?= $index ?>;
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
    const template = container.querySelector('.bahan-baku-item');
    if (!template) {
        window.location.reload();
        return;
    }
    const newItem = template.cloneNode(true);
    newItem.dataset.index = bahanIndex;
    
    newItem.innerHTML = newItem.innerHTML.replace(/\[\d+\]/g, `[${bahanIndex}]`);
    newItem.querySelectorAll('input').forEach(input => input.value = '');
    newItem.querySelector('.satuan').value = 'pcs';
    newItem.querySelector('select').selectedIndex = 0;
    
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