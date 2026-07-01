/**
 * Scan barcode pada form stok masuk / keluar.
 * Setiap scan barcode terdaftar menambah +1 jumlah item (atau item baru jumlah 1).
 */
window.initBarcodeScanTransaksi = function(options) {
    const scanInput = document.getElementById(options.scanInputId || 'scan_barcode_input');
    const statusEl = document.getElementById(options.statusId || 'scan_barcode_status');
    const barangSelect = options.barangSelect;
    const gudangSelect = options.gudangSelect;
    const mode = options.mode || 'masuk';
    let isProcessing = false;

    if (!scanInput || !barangSelect || typeof options.getItems !== 'function' || typeof options.renderItems !== 'function') {
        return;
    }

    function normalizeBarcode(value) {
        return String(value || '').trim().toLowerCase();
    }

    function normalizeName(value) {
        return String(value || '').trim().toLowerCase();
    }

    function toInt(value, fallback) {
        const n = parseInt(String(value ?? ''), 10);
        return Number.isFinite(n) ? n : (fallback ?? 0);
    }

    function toFloat(value, fallback) {
        const n = parseFloat(String(value ?? '').replace(',', '.'));
        return Number.isFinite(n) ? n : (fallback ?? 0);
    }

    function setStatus(message, type) {
        if (!statusEl) return;
        statusEl.textContent = message || '';
        statusEl.className = 'small mt-1 ' + (type === 'error' ? 'text-danger' : type === 'warning' ? 'text-warning' : 'text-muted');
    }

    function getGudangId() {
        if (typeof options.getGudangId === 'function') {
            return options.getGudangId();
        }
        return gudangSelect ? String(gudangSelect.value || '') : '';
    }

    function getDetailBarang() {
        if (typeof options.getDetailBarang === 'function') {
            return options.getDetailBarang();
        }
        return '';
    }

    function getBaseSatuanId(option) {
        if (typeof options.getBaseSatuanId === 'function') {
            return toInt(options.getBaseSatuanId(option), 0);
        }
        return toInt(option?.getAttribute('data-satuan-id') || '0', 0);
    }

    function getSelectedSatuanId(option, baseSatuanId) {
        if (typeof options.getSelectedSatuanId === 'function') {
            return toInt(options.getSelectedSatuanId(option, baseSatuanId), baseSatuanId || 0);
        }
        return baseSatuanId || 0;
    }

    function getFactorToBase(selectedSatuanId, baseSatuanId) {
        if (typeof options.getFactorToBase === 'function') {
            const v = toFloat(options.getFactorToBase(selectedSatuanId, baseSatuanId), 1);
            return v > 0 ? v : 1;
        }
        return 1;
    }

    function getSatuanName(selectedSatuanId, option) {
        if (typeof options.getSatuanName === 'function') {
            return String(options.getSatuanName(selectedSatuanId, option) || '');
        }
        return String(option?.getAttribute('data-satuan') || '');
    }

    function getBaseSatuanName(option) {
        if (typeof options.getBaseSatuanName === 'function') {
            return String(options.getBaseSatuanName(option) || '');
        }
        return String(option?.getAttribute('data-satuan') || '');
    }

    function buildBarcodeIndex() {
        const index = new Map();
        Array.from(barangSelect.options).forEach(function(opt) {
            if (!opt.value) return;

            const bcItem = normalizeBarcode(opt.getAttribute('data-barcode') || '');
            if (bcItem && bcItem !== '-' && !index.has(bcItem)) {
                index.set(bcItem, { option: opt, kind: 'item' });
            }

            const bcKonversi = normalizeBarcode(opt.getAttribute('data-barcode-konversi') || opt.getAttribute('data-barcode-dus') || '');
            if (bcKonversi && bcKonversi !== '-' && !index.has(bcKonversi)) {
                index.set(bcKonversi, { option: opt, kind: 'konversi' });
            }
        });
        return index;
    }

    function findEntryByBarcode(scanned) {
        const target = normalizeBarcode(scanned);
        if (!target) return null;
        const v = buildBarcodeIndex().get(target) || null;
        if (!v) return null;
        if (v && v.option) return v;
        return { option: v, kind: 'item' };
    }

    function addQtyFromScan(option, qtyInputToAdd, scanInfo) {
        const barangId = option.value;
        const kodeBarang = option.getAttribute('data-kode') || '';
        const namaBarang = option.getAttribute('data-nama') || '';
        const stokTersediaBase = toInt(option.getAttribute('data-stok-tersedia') || '0', 0);
        const detailBarang = getDetailBarang();
        const items = options.getItems();

        const baseSatuanId = getBaseSatuanId(option);
        const selectedSatuanId = getSelectedSatuanId(option, baseSatuanId);
        const factorToBase = (baseSatuanId && selectedSatuanId) ? getFactorToBase(selectedSatuanId, baseSatuanId) : 1;
        const qtyBaseToAdd = Math.round((toFloat(qtyInputToAdd, 0) || 0) * (factorToBase || 1));
        const satuan = (baseSatuanId && selectedSatuanId) ? getSatuanName(selectedSatuanId, option) : (option.getAttribute('data-satuan') || '');
        const satuanBaseName = (baseSatuanId && selectedSatuanId) ? getBaseSatuanName(option) : '';
        const scanKind = String(scanInfo?.kind || 'item');
        const isKonversiScan = (scanKind === 'konversi' || scanKind === 'dus');
        const scannedRaw = String(scanInfo?.scanned || '').trim();
        const barcodeLabel = isKonversiScan
            ? (option.getAttribute('data-barcode-konversi') || option.getAttribute('data-barcode-dus') || scannedRaw || option.getAttribute('data-barcode') || '')
            : (option.getAttribute('data-barcode') || scannedRaw || '');

        const matchBySatuan = !!(baseSatuanId && selectedSatuanId);
        const existingIndex = items.findIndex(function(item) {
            if (!matchBySatuan) {
                return String(item.barang_id) === String(barangId) && (item.detail_barang || '') === detailBarang;
            }
            return (
                String(item.barang_id) === String(barangId) &&
                (item.detail_barang || '') === detailBarang &&
                String(item.satuan_id || '') === String(selectedSatuanId)
            );
        });

        if (existingIndex > -1) {
            const currentJumlahBase = toInt(items[existingIndex].jumlah, 0);
            const newTotalBase = currentJumlahBase + qtyBaseToAdd;
            if (mode === 'keluar' && newTotalBase > stokTersediaBase) {
                return { ok: false, message: 'Stok tidak mencukupi. Tersedia: ' + stokTersediaBase };
            }
            items[existingIndex].jumlah = newTotalBase;
            if (matchBySatuan) {
                const curInput = toInt(items[existingIndex].jumlah_input, 0);
                items[existingIndex].jumlah_input = curInput + toInt(qtyInputToAdd, 0);
                items[existingIndex].satuan_id = selectedSatuanId;
                items[existingIndex].satuan = satuan;
                items[existingIndex].satuan_base_id = baseSatuanId;
                items[existingIndex].satuan_base = satuanBaseName;
                items[existingIndex].nilai_konversi = factorToBase;
            }
            if (typeof options.setItems === 'function') {
                options.setItems(items);
            }
            const displayJumlah = matchBySatuan ? items[existingIndex].jumlah_input : newTotalBase;
            return { ok: true, jumlah: displayJumlah, nama: namaBarang, barcode: barcodeLabel, satuan: matchBySatuan ? satuan : '' };
        }

        if (mode === 'keluar' && qtyBaseToAdd > stokTersediaBase) {
            return { ok: false, message: 'Stok tidak mencukupi. Tersedia: ' + stokTersediaBase };
        }

        const newItem = {
            barang_id: barangId,
            kode_barang: kodeBarang,
            nama_barang: namaBarang,
            detail_barang: detailBarang,
            jumlah: matchBySatuan ? qtyBaseToAdd : toInt(qtyInputToAdd, 0),
            satuan: satuan
        };
        if (matchBySatuan) {
            newItem.jumlah_input = toInt(qtyInputToAdd, 0);
            newItem.satuan_id = selectedSatuanId;
            newItem.satuan_base_id = baseSatuanId;
            newItem.satuan_base = satuanBaseName;
            newItem.nilai_konversi = factorToBase;
        }
        items.push(newItem);
        if (typeof options.setItems === 'function') {
            options.setItems(items);
        }
        const displayJumlah = matchBySatuan ? newItem.jumlah_input : newItem.jumlah;
        return { ok: true, jumlah: displayJumlah, nama: namaBarang, barcode: barcodeLabel, satuan: matchBySatuan ? satuan : '' };
    }

    async function processScannedBarcode(rawBarcode) {
        const scanned = String(rawBarcode || '').trim();
        if (!scanned) return;

        if (!getGudangId()) {
            setStatus('Pilih gudang terlebih dahulu.', 'warning');
            if (gudangSelect) gudangSelect.focus();
            return;
        }

        const entry = findEntryByBarcode(scanned);
        if (!entry || !entry.option) {
            setStatus('Barcode tidak terdaftar di gudang ini.', 'error');
            return;
        }
        const option = entry.option;

        if (typeof options.prepareForScan === 'function') {
            try {
                await options.prepareForScan(option, scanned, entry);
            } catch (_) {}
        }

        const baseSatuanId = getBaseSatuanId(option);
        const selectedSatuanId = getSelectedSatuanId(option, baseSatuanId);
        const scanKind = String(entry.kind || 'item');
        const isKonversiScan = (scanKind === 'konversi' || scanKind === 'dus');
        if (isKonversiScan && baseSatuanId && selectedSatuanId) {
            const factorToBase = getFactorToBase(selectedSatuanId, baseSatuanId);
            if (!factorToBase || factorToBase <= 1) {
                setStatus('Konversi belum diset untuk barang ini.', 'error');
                return;
            }
        }

        const qtyInputToAdd = (typeof options.getScanQty === 'function') ? toInt(options.getScanQty(option, scanned, entry), 1) : 1;
        const result = addQtyFromScan(option, qtyInputToAdd, { kind: entry.kind, scanned: scanned });
        if (!result.ok) {
            setStatus(result.message, 'warning');
            return;
        }

        options.renderItems();
        const qtyLabel = (result.satuan ? (String(result.jumlah) + ' ' + result.satuan) : String(result.jumlah));
        setStatus(result.nama + ' [' + result.barcode + '] — jumlah: ' + qtyLabel);

        if (typeof options.onAfterScan === 'function') {
            options.onAfterScan(option, result, entry);
        }
    }

    async function finishScan() {
        if (isProcessing) return;
        const value = scanInput.value.trim();
        if (!value) return;
        isProcessing = true;
        try {
            await processScannedBarcode(value);
        } finally {
            isProcessing = false;
        }
        scanInput.value = '';
        scanInput.focus();
    }

    scanInput.addEventListener('keydown', function(e) {
        if (e.key !== 'Enter') return;
        e.preventDefault();
        finishScan();
    });

    if (gudangSelect) {
        gudangSelect.addEventListener('change', function() {
            scanInput.value = '';
            setStatus(getGudangId() ? 'Siap scan barcode.' : 'Pilih gudang dulu.');
        });
    }
};
