<?php
require_once __DIR__ . '/_init.php';
require_once __DIR__ . '/../includes/product_catalog.php';
kasir_require_login();

$mejaOptions = [];
$products = [];
$currentShift = null;

// Ambil meja_id dari URL jika dikirim dari halaman tables.php
$selectedMejaId = isset($_GET['meja_id']) ? (int)$_GET['meja_id'] : 0;

// 1. Ambil data MEJA dari database POS ($kasirPosConn -> minven_pos)
if ($kasirPosConn instanceof mysqli && !$kasirPosConn->connect_error) {
    $resultMeja = $kasirPosConn->query("SELECT id, nomor_meja, status FROM meja ORDER BY nomor_meja ASC");
    if ($resultMeja) {
        while ($row = $resultMeja->fetch_assoc()) {
            $mejaOptions[] = [
                'id' => (int)$row['id'],
                'nomor_meja' => (string)$row['nomor_meja'],
                'status' => (string)$row['status'],
            ];
        }
    }
}

// 2. Ambil daftar PRODUK dari database utama ($kasirMainConn)
if ($kasirMainConn instanceof mysqli && !$kasirMainConn->connect_error) {
    $products = product_catalog_fetch_active_products_for_sync($kasirMainConn);
}

// 3. Ambil SHIFT aktif untuk kasir saat ini ($kasirPosConn)
$user = kasir_current_user();
if ($kasirPosConn instanceof mysqli && !$kasirPosConn->connect_error) {
    $stmt = $kasirPosConn->prepare("SELECT * FROM pos_shifts WHERE status = 'open' AND opened_by = ? ORDER BY id DESC LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('i', $user['id']);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result) {
            $currentShift = $result->fetch_assoc();
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transaksi Baru POS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        body { background: linear-gradient(180deg, #eef4ff 0%, #f8fafc 35%, #f8fafc 100%); color: #0f172a; }
        .page-wrap { max-width: 1360px; margin: 0 auto; padding: 28px 16px 48px; }
        .hero-card, .card-soft { border: 0; border-radius: 24px; box-shadow: 0 18px 45px rgba(15, 23, 42, 0.08); }
        .hero-card { background: linear-gradient(135deg, #0f172a 0%, #1d4ed8 58%, #38bdf8 100%); color: #fff; }
        .hero-card .text-soft { color: rgba(255,255,255,.78); }
        .info-chip { display: inline-flex; align-items: center; gap: 10px; padding: 10px 16px; border-radius: 999px; background: rgba(255,255,255,.14); }
        .section-title { font-size: 1.02rem; font-weight: 700; }
        .summary-box { background: linear-gradient(180deg, #eff6ff 0%, #f8fbff 100%); border-radius: 20px; padding: 20px; border: 1px solid #dbeafe; }
        .floating-summary { position: sticky; top: 20px; }
        .table thead th { border-bottom: 0; color: #64748b; font-size: .78rem; text-transform: uppercase; letter-spacing: .04em; }
        .quick-meta { padding: 14px 16px; border-radius: 18px; background: #fff; border: 1px solid #e2e8f0; }
        .btn-soft { border-radius: 16px; padding: 12px 18px; }
        .card-soft .form-control, .card-soft .form-select { border-radius: 14px; min-height: 46px; }
        .itemSubtotal { font-weight: 700; color: #0f172a; }
        .empty-state { border: 1px dashed #cbd5e1; border-radius: 20px; padding: 24px; color: #64748b; text-align: center; background: #f8fafc; }
    </style>
</head>
<body>
    <div class="page-wrap">
        <div class="hero-card p-4 p-lg-5 mb-4">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                <div>
                    <div class="text-uppercase small text-soft mb-2">Point Of Sale</div>
                    <h1 class="h3 fw-bold mb-2">Transaksi kasir lebih cepat dan lebih rapi</h1>
                    <p class="text-soft mb-3">Tampilan transaksi modern: fokus ke pilihan meja, input order, total belanja, dan pembayaran.</p>
                    <div class="d-flex flex-wrap gap-2">
                        <span class="info-chip">
                            <i class="bi bi-person-badge"></i>
                            <?= htmlspecialchars($user['nama'] !== '' ? $user['nama'] : $user['username']) ?>
                        </span>
                        <span class="info-chip">
                            <i class="bi bi-clock-history"></i>
                            <?= $currentShift ? 'Shift aktif: ' . htmlspecialchars($currentShift['shift_number']) : 'Belum ada shift aktif' ?>
                        </span>
                    </div>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <button class="btn btn-light btn-soft" id="refreshProductsBtn" type="button">
                        <i class="bi bi-arrow-repeat me-2"></i> Get Product
                    </button>
                    <!-- Tombol Kelola Meja tetap mengarah ke tables.php -->
                    <a class="btn btn-outline-light btn-soft" href="<?= htmlspecialchars(kasir_url_for('tables.php')) ?>">
                        <i class="bi bi-grid-3x3-gap me-2"></i> Kelola Meja
                    </a>
                    <a class="btn btn-outline-light btn-soft" href="<?= htmlspecialchars(kasir_url_for('dashboard.php')) ?>">
                        <i class="bi bi-grid me-2"></i> Dashboard
                    </a>
                    <a class="btn btn-outline-light btn-soft" href="<?= htmlspecialchars(kasir_url_for('shift.php')) ?>">
                        <i class="bi bi-clock me-2"></i> Shift
                    </a>
                </div>
            </div>
        </div>

        <form method="post" action="<?= htmlspecialchars(kasir_url_for('process.php')) ?>" id="posForm">
            <input type="hidden" name="items_payload" id="itemsPayload">
            <input type="hidden" name="shift_id" value="<?= $currentShift ? (int)$currentShift['id'] : '' ?>">
            <input type="hidden" name="gudang_id" id="gudangId" value="1">
            
            <div class="row g-4">
                <div class="col-lg-8">
                    <?php if (!$currentShift): ?>
                        <div class="alert alert-warning border-0 shadow-sm mb-4">
                            <strong>Shift belum dibuka.</strong> Silakan buka shift terlebih dahulu sebelum membuat transaksi.
                            <a href="<?= htmlspecialchars(kasir_url_for('shift.php')) ?>" class="alert-link ms-2">Buka Shift</a>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info border-0 shadow-sm mb-4">
                            Shift aktif: <strong><?= htmlspecialchars($currentShift['shift_number']) ?></strong>,
                            dibuka pada <?= htmlspecialchars(date('d/m/Y H:i', strtotime($currentShift['opened_at']))) ?>.
                        </div>
                    <?php endif; ?>

                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <div class="quick-meta">
                                <div class="text-muted small mb-1">Mode Kasir</div>
                                <div class="fw-semibold">Penjualan Langsung</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="quick-meta">
                                <div class="text-muted small mb-1">Sistem Layanan</div>
                                <div class="fw-semibold">Manajemen Meja</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="quick-meta">
                                <div class="text-muted small mb-1">Stok Bahan</div>
                                <div class="fw-semibold">Sinkron Otomatis</div>
                            </div>
                        </div>
                    </div>

                    <div class="card card-soft mb-4">
                        <div class="card-body p-4">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div class="section-title mb-0">Informasi Transaksi</div>
                                <span class="text-muted small">Lengkapi data dasar transaksi</span>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">Tanggal Transaksi</label>
                                    <input type="datetime-local" class="form-control" name="sale_date" value="<?= htmlspecialchars(date('Y-m-d\TH:i')) ?>" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Pilih Meja</label>
                                    <!-- Select meja standar tanpa redirect otomatis -->
                                    <select class="form-select" name="meja_id" id="mejaSelect" required>
                                        <option value="">Pilih meja</option>
                                        <?php foreach ($mejaOptions as $meja): ?>
                                            <?php 
                                                $mejaId = (int)$meja['id'];
                                                $isSelected = ($selectedMejaId === $mejaId) ? 'selected' : '';
                                            ?>
                                            <option value="<?= $mejaId ?>" <?= $isSelected ?>>
                                                Meja <?= htmlspecialchars((string)$meja['nomor_meja']) ?> (<?= ucfirst($meja['status']) ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Nama Pelanggan</label>
                                    <input type="text" class="form-control" name="customer_name" placeholder="Opsional">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Pembayaran</label>
                                    <select class="form-select" name="payment_method" id="paymentMethod">
                                        <option value="cash">Cash</option>
                                        <option value="transfer">Transfer</option>
                                        <option value="qris">QRIS</option>
                                        <option value="debit">Debit</option>
                                        <option value="ewallet">E-Wallet</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Diskon</label>
                                    <input type="number" class="form-control" name="discount_amount" id="discountAmount" min="0" step="0.01" value="0">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Pajak</label>
                                    <input type="number" class="form-control" name="tax_amount" id="taxAmount" min="0" step="0.01" value="0">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card card-soft">
                        <div class="card-body p-4">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div>
                                    <div class="section-title mb-1">Item Penjualan</div>
                                    <div class="text-muted small">Pilih produk yang tersedia</div>
                                </div>
                                <button type="button" class="btn btn-primary btn-soft" id="addRowBtn">
                                    <i class="bi bi-plus-circle me-2"></i> Tambah Item
                                </button>
                            </div>
                            <div class="table-responsive">
                                <table class="table align-middle" id="itemsTable">
                                    <thead>
                                        <tr>
                                            <th style="min-width: 280px;">Produk</th>
                                            <th style="width: 130px;">HPP</th>
                                            <th style="width: 130px;">Harga Jual</th>
                                            <th style="width: 100px;">Qty</th>
                                            <th style="width: 150px;">Subtotal</th>
                                            <th style="width: 70px;"></th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                            <div class="empty-state mt-3 d-none" id="emptyState">
                                Belum ada item dipilih. Klik <strong>Tambah Item</strong> untuk mulai transaksi.
                            </div>
                            <div class="mt-3">
                                <label class="form-label">Catatan</label>
                                <textarea class="form-control" name="notes" rows="3" placeholder="Catatan transaksi atau pesanan meja"></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="floating-summary">
                        <div class="card card-soft">
                            <div class="card-body p-4">
                                <div class="section-title mb-3">Ringkasan Pembayaran</div>
                                <div class="summary-box mb-3">
                                    <div class="d-flex justify-content-between mb-2">
                                        <span>Total HPP Bahan</span>
                                        <strong id="totalHppText">Rp 0</strong>
                                    </div>
                                    <div class="d-flex justify-content-between mb-2">
                                        <span>Subtotal</span>
                                        <strong id="subtotalText">Rp 0</strong>
                                    </div>
                                    <div class="d-flex justify-content-between mb-2">
                                        <span>Diskon</span>
                                        <strong id="discountText">Rp 0</strong>
                                    </div>
                                    <div class="d-flex justify-content-between mb-2">
                                        <span>Pajak</span>
                                        <strong id="taxText">Rp 0</strong>
                                    </div>
                                    <hr>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="fw-semibold">Grand Total</span>
                                        <strong class="fs-4" id="grandTotalText">Rp 0</strong>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Dibayar</label>
                                    <input type="number" class="form-control form-control-lg" name="amount_paid" id="amountPaid" min="0" step="0.01" value="0" required>
                                </div>
                                <div class="alert alert-secondary">
                                    Kembalian: <strong id="changeText">Rp 0</strong>
                                </div>
                                <button type="submit" class="btn btn-success btn-lg btn-soft w-100" <?= $currentShift ? '' : 'disabled' ?>>
                                    <i class="bi bi-check-circle me-2"></i> Simpan Transaksi
                                </button>
                                <?php if (!$currentShift): ?>
                                    <div class="text-muted small mt-3">Tombol aktif setelah shift dibuka.</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <template id="rowTemplate">
        <tr>
            <td>
                <select class="form-select itemSelect" required>
                    <option value="">Pilih produk</option>
                </select>
            </td>
            <td><input type="number" class="form-control itemHpp" min="0" step="0.01" value="0" readonly style="background:#f1f5f9;"></td>
            <td><input type="number" class="form-control itemPrice" min="0" step="0.01" value="0"></td>
            <td><input type="number" class="form-control itemQty" min="1" step="1" value="1"></td>
            <td class="itemSubtotal">Rp 0</td>
            <td class="text-end">
                <button type="button" class="btn btn-outline-danger removeRow"><i class="bi bi-trash"></i></button>
            </td>
        </tr>
    </template>

    <script>
        let productList = <?= json_encode($products, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?> || [];
        const tableBody = document.querySelector('#itemsTable tbody');
        const rowTemplate = document.getElementById('rowTemplate');
        const mejaSelect = document.getElementById('mejaSelect');
        const discountAmount = document.getElementById('discountAmount');
        const taxAmount = document.getElementById('taxAmount');
        const amountPaid = document.getElementById('amountPaid');
        const itemsPayload = document.getElementById('itemsPayload');
        const refreshProductsBtn = document.getElementById('refreshProductsBtn');
        const emptyState = document.getElementById('emptyState');

        function formatRupiah(value) {
            return 'Rp ' + Number(value || 0).toLocaleString('id-ID');
        }

        function buildOptions(selectEl) {
            const currentValue = selectEl.value;
            selectEl.innerHTML = '<option value="">Pilih produk</option>';
            productList.forEach(item => {
                const option = document.createElement('option');
                option.value = String(item.id);
                option.textContent = `${item.kode_product} - ${item.nama_product}`;
                option.dataset.price = item.harga_jual;
                option.dataset.hpp = item.hpp ?? 0;
                option.dataset.gudangId = item.gudang_id ?? 1;
                option.dataset.kode = item.kode_product || '';
                option.dataset.nama = item.nama_product || '';
                selectEl.appendChild(option);
            });
            if (currentValue) {
                selectEl.value = currentValue;
            }
        }

        function syncEmptyState() {
            emptyState.classList.toggle('d-none', tableBody.querySelectorAll('tr').length > 0);
        }

        function refreshRow(row) {
            const selectEl = row.querySelector('.itemSelect');
            const option = selectEl.selectedOptions[0];
            const hppEl = row.querySelector('.itemHpp');
            const priceEl = row.querySelector('.itemPrice');
            const qtyEl = row.querySelector('.itemQty');
            const subtotalEl = row.querySelector('.itemSubtotal');

            if (!option || !option.value) {
                hppEl.value = 0;
                subtotalEl.textContent = formatRupiah(0);
                updateSummary();
                return;
            }

            let qty = Number(qtyEl.value || 1);
            if (qty < 1) qty = 1;
            qtyEl.value = qty;

            if (!Number(hppEl.value) && hppEl.value !== '0') {
                hppEl.value = Number(option.dataset.hpp || 0);
            }

            if (!Number(priceEl.value) && priceEl.value !== '0') {
                priceEl.value = Number(option.dataset.price || 0);
            }
            subtotalEl.textContent = formatRupiah(Number(priceEl.value || 0) * qty);
            updateSummary();
        }

        function updateSummary() {
            const rows = Array.from(tableBody.querySelectorAll('tr'));
            let subtotal = 0;
            let totalHpp = 0;
            const payload = [];

            rows.forEach(row => {
                const selectEl = row.querySelector('.itemSelect');
                const option = selectEl.selectedOptions[0];
                const hpp = Number(row.querySelector('.itemHpp').value || 0);
                const price = Number(row.querySelector('.itemPrice').value || 0);
                const qty = Number(row.querySelector('.itemQty').value || 0);
                if (!option || !option.value || qty <= 0) {
                    return;
                }
                const lineTotal = price * qty;
                const lineHppTotal = hpp * qty;
                subtotal += lineTotal;
                totalHpp += lineHppTotal;
                payload.push({
                    product_id: Number(option.value),
                    kode_product: option.dataset.kode || '',
                    nama_product: option.dataset.nama || option.textContent || '',
                    gudang_id: Number(option.dataset.gudangId || 1),
                    hpp: hpp,
                    qty: qty,
                    unit_price: price,
                    line_total: lineTotal,
                    line_hpp_total: lineHppTotal
                });
            });

            const discount = Number(discountAmount.value || 0);
            const tax = Number(taxAmount.value || 0);
            const grandTotal = Math.max(0, subtotal - discount + tax);
            const paid = Number(amountPaid.value || 0);
            const change = paid - grandTotal;

            document.getElementById('subtotalText').textContent = formatRupiah(subtotal);
            document.getElementById('discountText').textContent = formatRupiah(discount);
            document.getElementById('taxText').textContent = formatRupiah(tax);
            document.getElementById('grandTotalText').textContent = formatRupiah(grandTotal);
            document.getElementById('changeText').textContent = formatRupiah(change > 0 ? change : 0);
            const totalHppEl = document.getElementById('totalHppText');
            if (totalHppEl) totalHppEl.textContent = formatRupiah(totalHpp);
            itemsPayload.value = JSON.stringify(payload);
            syncEmptyState();
        }

        function addRow() {
            const fragment = rowTemplate.content.cloneNode(true);
            const row = fragment.querySelector('tr');
            buildOptions(row.querySelector('.itemSelect'));
            tableBody.appendChild(row);
            refreshRow(row);
            syncEmptyState();
        }

        refreshProductsBtn.addEventListener('click', async () => {
            const spinner = document.createElement('span');
            spinner.className = 'spinner-border spinner-border-sm me-2';
            refreshProductsBtn.innerHTML = '';
            refreshProductsBtn.appendChild(spinner);
            refreshProductsBtn.insertAdjacentHTML('beforeend', ' Memuat...');
            refreshProductsBtn.disabled = true;

            try {
                const url = '<?= htmlspecialchars(kasir_url_for('api/get_products.php')) ?>' + '?refresh=1&t=' + Date.now();
                const response = await fetch(url, {
                    cache: 'no-store',
                    headers: { 'X-Requested-With': 'fetch' }
                });
                if (!response.ok) throw new Error('Server status ' + response.status);
                const result = await response.json();
                if (result && result.success) {
                    productList = (result.data && result.data.products) || [];
                    tableBody.querySelectorAll('tr').forEach(row => {
                        const selectEl = row.querySelector('.itemSelect');
                        buildOptions(selectEl);
                        selectEl.value = '';
                        row.querySelector('.itemHpp').value = 0;
                        row.querySelector('.itemPrice').value = 0;
                        row.querySelector('.itemQty').value = 1;
                        refreshRow(row);
                    });
                    updateSummary();
                    alert(result.message || 'Produk berhasil diperbarui!');
                } else {
                    alert((result && result.message) || 'Gagal memperbarui produk.');
                }
            } catch (e) {
                alert('Gagal memuat produk: ' + (e.message || 'Unknown error'));
            } finally {
                refreshProductsBtn.innerHTML = '<i class="bi bi-arrow-repeat me-2"></i> Get Product';
                refreshProductsBtn.disabled = false;
            }
        });

        document.getElementById('addRowBtn').addEventListener('click', addRow);

        tableBody.addEventListener('change', (event) => {
            const row = event.target.closest('tr');
            if (!row) return;
            if (event.target.classList.contains('itemSelect')) {
                const option = event.target.selectedOptions[0];
                row.querySelector('.itemPrice').value = option && option.value ? Number(option.dataset.price || 0) : 0;
            }
            refreshRow(row);
        });

        tableBody.addEventListener('input', (event) => {
            const row = event.target.closest('tr');
            if (!row) return;
            if (event.target.classList.contains('itemPrice') || event.target.classList.contains('itemQty')) {
                refreshRow(row);
            }
        });

        tableBody.addEventListener('click', (event) => {
            const button = event.target.closest('.removeRow');
            if (!button) return;
            button.closest('tr').remove();
            updateSummary();
            syncEmptyState();
        });

        [discountAmount, taxAmount, amountPaid].forEach(input => {
            input.addEventListener('input', updateSummary);
        });

        document.getElementById('posForm').addEventListener('submit', (event) => {
            if (!mejaSelect.value) {
                event.preventDefault();
                alert('Silakan pilih meja terlebih dahulu.');
                return;
            }
            const payload = JSON.parse(itemsPayload.value || '[]');
            if (!payload.length) {
                event.preventDefault();
                alert('Tambahkan minimal satu item transaksi.');
                return;
            }
        });

        addRow();
        updateSummary();
    </script>
</body>
</html>