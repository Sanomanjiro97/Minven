<?php
require_once __DIR__ . '/_init.php';
require_once __DIR__ . '/../includes/access_check.php';

$conn = $conn ?? null;
if (!($conn instanceof mysqli)) {
    $_SESSION['error'] = 'Koneksi database tidak tersedia.';
    header('Location: ' . url_for('product/index.php'));
    exit();
}

if (!checkAccess('product', 'add')) {
    $_SESSION['error'] = 'Anda tidak memiliki akses untuk menambah product!';
    header('Location: ' . url_for('product/index.php'));
    exit();
}

$gudang_rows = get_accessible_gudang_list($conn);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Tambah Product - Sistem Inventory</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.0.7/css/boxicons.min.css" rel="stylesheet" />
    <style>
        body { background: #f6f8fb; }
        .page-title { font-size: 1.25rem; }
        .table td, .table th { vertical-align: middle; }
        .table thead th { position: sticky; top: 0; z-index: 1; }
    </style>
</head>
<body>
<?php include '../templates/navbar.php'; ?>

<div class="container-fluid py-4">
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a class="text-decoration-none" href="<?= url_for('dashboard.php') ?>">Dashboard</a></li>
            <li class="breadcrumb-item"><a class="text-decoration-none" href="<?= url_for('product/index.php') ?>">Product</a></li>
            <li class="breadcrumb-item active" aria-current="page">Tambah</li>
        </ol>
    </nav>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <div class="d-flex align-items-center gap-2">
                    <i class='bx bx-plus-circle text-primary' style="font-size:1.4rem;"></i>
                    <h1 class="mb-0 fw-bold page-title">Tambah Product</h1>
                </div>
                <a class="btn btn-outline-secondary" href="<?= url_for('product/index.php') ?>">
                    <i class='bx bx-arrow-back'></i> Kembali
                </a>
            </div>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?= h($_SESSION['error']); unset($_SESSION['error']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <form method="post" action="<?= url_for('product/process.php') ?>" id="formProduct">
                <input type="hidden" name="action" value="add" />

                <div class="row g-3 mb-3">
                    <div class="col-lg-6">
                        <label class="form-label">Nama Product</label>
                        <input type="text" class="form-control" name="nama_product" placeholder="Contoh: Product A" required />
                    </div>
                    <div class="col-lg-6">
                        <label class="form-label">Gudang</label>
                        <select class="form-select" name="gudang_id" id="gudang_id" required>
                            <option value="">Pilih Gudang</option>
                            <?php foreach ($gudang_rows as $g): ?>
                                <option value="<?= (int)$g['id'] ?>"><?= h($g['nama_gudang']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Item akan muncul setelah gudang dipilih.</div>
                    </div>
                </div>

                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                    <div>
                        <h5 class="mb-0">Items</h5>
                        <div class="text-muted small" id="itemsHint">Pilih gudang untuk memuat item.</div>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="btnAddRow" disabled>
                        <i class='bx bx-plus'></i> Tambah Item
                    </button>
                </div>

                <div class="table-responsive">
                    <table class="table table-sm table-bordered align-middle mb-0" id="itemsTable">
                        <thead class="table-light">
                            <tr>
                                <th style="width:70px;">No</th>
                                <th>Barang</th>
                                <th style="width:140px;">Stok</th>
                                <th style="width:160px;">Qty</th>
                                <th style="width:80px;"></th>
                            </tr>
                        </thead>
                        <tbody id="itemsTbody">
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">Pilih gudang untuk melihat item.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="d-flex justify-content-end gap-2 mt-3">
                    <button type="submit" class="btn btn-success" id="btnSubmit" disabled>
                        <i class='bx bx-save'></i> Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
let barangCache = [];
const tbody = document.getElementById('itemsTbody');
const btnAddRow = document.getElementById('btnAddRow');
const btnSubmit = document.getElementById('btnSubmit');
const gudangSelect = document.getElementById('gudang_id');
const itemsHint = document.getElementById('itemsHint');

function setTbodyMessage(message) {
    tbody.innerHTML = '';
    const tr = document.createElement('tr');
    const td = document.createElement('td');
    td.colSpan = 5;
    td.className = 'text-center text-muted py-4';
    td.textContent = message;
    tr.appendChild(td);
    tbody.appendChild(tr);
}

function updateRowNumbers() {
    Array.from(tbody.querySelectorAll('tr')).forEach((tr, idx) => {
        const no = tr.querySelector('[data-col="no"]');
        if (no) {
            no.textContent = String(idx + 1);
        }
    });
}

function buildBarangOptions(selectedId) {
    const frag = document.createDocumentFragment();
    const opt0 = document.createElement('option');
    opt0.value = '';
    opt0.textContent = 'Pilih Barang';
    frag.appendChild(opt0);

    barangCache.forEach(item => {
        const opt = document.createElement('option');
        opt.value = item.id;
        opt.textContent = `${item.kode_barang} - ${item.nama_barang} (Tersedia: ${item.stok_tersedia || 0})`;
        opt.dataset.stokTersedia = item.stok_tersedia || 0;
        if (String(selectedId || '') === String(item.id)) {
            opt.selected = true;
        }
        frag.appendChild(opt);
    });
    return frag;
}

function addRow(prefBarangId, prefQty) {
    const tr = document.createElement('tr');

    const tdNo = document.createElement('td');
    tdNo.className = 'text-muted';
    tdNo.setAttribute('data-col', 'no');
    tdNo.textContent = String(tbody.querySelectorAll('tr').length + 1);

    const tdBarang = document.createElement('td');
    const sel = document.createElement('select');
    sel.name = 'barang_id[]';
    sel.className = 'form-select form-select-sm';
    sel.required = true;
    sel.appendChild(buildBarangOptions(prefBarangId));
    tdBarang.appendChild(sel);

    const tdStok = document.createElement('td');
    const stokBadge = document.createElement('span');
    stokBadge.className = 'badge bg-light text-dark border';
    stokBadge.textContent = '-';
    tdStok.appendChild(stokBadge);

    const tdQty = document.createElement('td');
    const qty = document.createElement('input');
    qty.type = 'number';
    qty.min = '1';
    qty.step = '1';
    qty.name = 'qty[]';
    qty.className = 'form-control form-control-sm';
    qty.required = true;
    qty.value = prefQty ? String(prefQty) : '';
    tdQty.appendChild(qty);

    const tdAct = document.createElement('td');
    tdAct.className = 'text-center';
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'btn btn-sm btn-outline-danger';
    btn.innerHTML = "<i class='bx bx-trash'></i>";
    btn.addEventListener('click', () => {
        tr.remove();
        ensureAtLeastOneRow();
        updateRowNumbers();
    });
    tdAct.appendChild(btn);

    function syncMax() {
        const opt = sel.options[sel.selectedIndex];
        const max = opt && opt.dataset && opt.dataset.stokTersedia ? parseInt(opt.dataset.stokTersedia, 10) : 0;
        if (max > 0) {
            qty.max = String(max);
            stokBadge.textContent = String(max);
            stokBadge.className = 'badge bg-success';
        } else {
            qty.removeAttribute('max');
            stokBadge.textContent = opt && opt.value ? '0' : '-';
            stokBadge.className = opt && opt.value ? 'badge bg-warning text-dark' : 'badge bg-light text-dark border';
        }
    }

    sel.addEventListener('change', syncMax);
    qty.addEventListener('input', () => {
        const maxAttr = qty.getAttribute('max');
        if (maxAttr) {
            const max = parseInt(maxAttr, 10);
            const val = parseInt(qty.value || '0', 10);
            if (val > max) {
                qty.value = String(max);
            }
        }
    });

    tr.appendChild(tdNo);
    tr.appendChild(tdBarang);
    tr.appendChild(tdStok);
    tr.appendChild(tdQty);
    tr.appendChild(tdAct);
    tbody.appendChild(tr);
    syncMax();
}

function ensureAtLeastOneRow() {
    if (!Array.isArray(barangCache) || barangCache.length === 0) {
        return;
    }
    if (tbody.children.length === 0) {
        addRow('', '');
    }
}

async function loadBarangByGudang(gudangId) {
    barangCache = [];
    if (!gudangId) {
        btnAddRow.disabled = true;
        btnSubmit.disabled = true;
        itemsHint.textContent = 'Pilih gudang untuk memuat item.';
        setTbodyMessage('Pilih gudang untuk melihat item.');
        return;
    }
    btnAddRow.disabled = true;
    btnSubmit.disabled = true;
    itemsHint.textContent = 'Memuat item...';
    setTbodyMessage('Memuat item...');
    const res = await fetch(`get_barang_by_gudang.php?gudang_id=${encodeURIComponent(gudangId)}`);
    const data = await res.json();
    if (!data || !data.success) {
        barangCache = [];
        itemsHint.textContent = 'Gagal memuat item dari gudang.';
        setTbodyMessage('Gagal memuat item dari gudang.');
        return;
    }
    barangCache = data.barang || [];
    if (barangCache.length === 0) {
        itemsHint.textContent = 'Tidak ada item tersedia di gudang ini.';
        setTbodyMessage('Tidak ada item tersedia di gudang ini.');
        btnAddRow.disabled = true;
        btnSubmit.disabled = true;
        return;
    }
    itemsHint.textContent = 'Pilih barang dan isi qty sesuai stok tersedia.';
    tbody.innerHTML = '';
    btnAddRow.disabled = false;
    btnSubmit.disabled = false;
    ensureAtLeastOneRow();
    updateRowNumbers();
}

function refreshAllRowsSelect() {
    Array.from(tbody.querySelectorAll('select[name="barang_id[]"]')).forEach(sel => {
        const prev = sel.value;
        sel.innerHTML = '';
        sel.appendChild(buildBarangOptions(prev));
        sel.dispatchEvent(new Event('change'));
    });
}

btnAddRow.addEventListener('click', () => addRow('', ''));
gudangSelect.addEventListener('change', (e) => {
    loadBarangByGudang(e.target.value);
});
</script>
</body>
</html>
