/**
 * Hitung stok final otomatis via scan barcode master barang.
 * Membutuhkan: #scan_barcode_input, #inventoryTable, input[data-id][data-stok-awal], window.updateStok
 */
document.addEventListener('DOMContentLoaded', function initBarcodeScanStokFinal() {
    const scanInput = document.getElementById('scan_barcode_input');
    if (!scanInput) return;

    const statusEl = document.getElementById('scan_barcode_status');
    const scanBtn = document.getElementById('scan_barcode_button');
    const scanCountByStokId = new Map();
    const desiredFinalByStokId = new Map();
    const syncingByStokId = new Map();
    const lastSentByStokId = new Map();
    const konversiDusByBarangId = new Map();
    const konversiDusByStokId = new Map();
    const startedScanAtByStokId = new Map();
    let barcodeIndex = new Map();
    let globalScanBuffer = '';
    let globalScanStartAt = 0;
    let globalScanLastAt = 0;
    let globalScanTimer = null;
    let globalScanIntercepting = false;
    let scanningEnabled = false;
    let isProcessing = false;

    function normalizeBarcode(value) {
        return String(value || '')
            .replace(/[\u0000-\u001F\u007F]/g, '')
            .replace(/\s+/g, '')
            .trim()
            .toLowerCase();
    }

    function barcodeKeys(value) {
        const norm = normalizeBarcode(value);
        if (!norm || norm === '-') return [];
        const keys = [norm];
        if (/^\d+$/.test(norm)) {
            const noLeading = norm.replace(/^0+/, '') || '0';
            if (noLeading !== norm) keys.push(noLeading);
        }
        return keys;
    }

    function isEditableElement(el) {
        if (!el || !(el instanceof HTMLElement)) return false;
        if (el.isContentEditable) return true;
        const tag = (el.tagName || '').toLowerCase();
        if (tag === 'textarea') return true;
        if (tag === 'input') {
            const type = (el.getAttribute('type') || 'text').toLowerCase();
            return type !== 'button' && type !== 'submit' && type !== 'reset' && type !== 'checkbox' && type !== 'radio' && type !== 'file';
        }
        return tag === 'select';
    }

    function buildBarcodeIndex() {
        const index = new Map();
        document.querySelectorAll('#inventoryTable tbody tr').forEach(function(row) {
            const bcRaw = row.getAttribute('data-barcode') || row.cells?.[2]?.textContent || '';
            for (const key of barcodeKeys(bcRaw)) {
                if (!index.has(key)) index.set(key, []);
                index.get(key).push({ row, kind: 'item' });
            }

            const bcKonversiRaw = row.getAttribute('data-barcode-konversi') || row.getAttribute('data-barcode-dus') || row.cells?.[3]?.textContent || '';
            for (const key of barcodeKeys(bcKonversiRaw)) {
                if (!index.has(key)) index.set(key, []);
                index.get(key).push({ row, kind: 'konversi' });
            }
        });
        return index;
    }

    function refreshBarcodeIndex() {
        barcodeIndex = buildBarcodeIndex();
    }

    function findRowByBarcode(scannedBarcode) {
        const keys = barcodeKeys(scannedBarcode);
        for (const key of keys) {
            const entries = barcodeIndex.get(key) || null;
            if (!entries || !Array.isArray(entries) || entries.length === 0) continue;
            const preferItem = entries.find(e => e && e.kind === 'item' && e.row);
            return preferItem || entries.find(e => e && e.row) || null;
        }
        return null;
    }

    function normalizeSatuanName(value) {
        return String(value || '').trim().toLowerCase();
    }

    function getBaseSatuanNameFromRow(row) {
        return normalizeSatuanName(row?.cells?.[6]?.textContent || '');
    }

    async function fetchKonversiDus(barangId, baseSatuanName) {
        const url = '../stok/masuk/get_konversi_barang.php?barang_id=' + encodeURIComponent(String(barangId || ''));
        const res = await fetch(url, {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            }
        });
        if (!res.ok) {
            throw new Error('Gagal ambil konversi (HTTP ' + res.status + ')');
        }
        const data = await res.json();
        if (!data || data.success !== true) {
            throw new Error(String(data?.message || 'Master konversi belum ada'));
        }
        const list = Array.isArray(data.konversi) ? data.konversi : [];
        const base = normalizeSatuanName(baseSatuanName);

        const pickFromRow = function(item) {
            const asal = normalizeSatuanName(item?.satuan_asal_nama || '');
            const tujuan = normalizeSatuanName(item?.satuan_tujuan_nama || '');
            const nilai = parseFloat(String(item?.nilai_konversi ?? '').replace(',', '.'));
            if (!Number.isFinite(nilai) || nilai <= 0) return null;

            if (base) {
                if (tujuan === base) return nilai;
                if (asal === base) return 1 / nilai;
                return null;
            }

            return null;
        };

        let best = null;
        for (const item of list) {
            const factor = pickFromRow(item);
            if (factor == null || !Number.isFinite(factor) || factor <= 1) continue;
            if (best == null || factor > best) best = factor;
        }
        return best;
    }

    function toPositiveIntFactor(value) {
        const n = parseFloat(String(value ?? '').replace(',', '.'));
        if (!Number.isFinite(n) || n <= 0) return null;
        const nearest = Math.round(n);
        if (Math.abs(n - nearest) < 1e-6) {
            return nearest > 0 ? nearest : null;
        }
        const floored = Math.floor(n);
        return floored > 0 ? floored : null;
    }

    async function getKonversiDus(row, stokId) {
        const fromAttr = row.getAttribute('data-konversi-dus');
        const asIntFromAttr = toPositiveIntFactor(fromAttr);
        if (asIntFromAttr) {
            konversiDusByStokId.set(String(stokId || ''), asIntFromAttr);
            return asIntFromAttr;
        }

        const cachedByStok = konversiDusByStokId.get(String(stokId || ''));
        if (cachedByStok) return cachedByStok;

        const barangId = String(row.getAttribute('data-barang-id') || '');
        if (!barangId) return null;

        const cachedByBarang = konversiDusByBarangId.get(barangId);
        if (cachedByBarang) {
            konversiDusByStokId.set(String(stokId || ''), cachedByBarang);
            row.setAttribute('data-konversi-dus', String(cachedByBarang));
            return cachedByBarang;
        }

        const baseSatuanName = getBaseSatuanNameFromRow(row);
        const factor = await fetchKonversiDus(barangId, baseSatuanName);
        const asInt = toPositiveIntFactor(factor);
        if (!asInt) return null;

        konversiDusByBarangId.set(barangId, asInt);
        konversiDusByStokId.set(String(stokId || ''), asInt);
        row.setAttribute('data-konversi-dus', String(asInt));
        return asInt;
    }

    function setStatus(message, type) {
        if (!statusEl) return;
        statusEl.textContent = message || '';
        statusEl.className = 'small mt-1 ' + (type === 'error' ? 'text-danger' : type === 'warning' ? 'text-warning' : 'text-muted');
    }

    function toast(msg, type) {
        if (typeof window.showToast === 'function') {
            window.showToast(msg, type);
        }
    }

    function requestSync(stokId, stokInput) {
        if (syncingByStokId.get(stokId)) return;
        syncingByStokId.set(stokId, true);

        const syncLoop = function() {
            const desiredValue = desiredFinalByStokId.get(stokId);
            const lastSentValue = lastSentByStokId.get(stokId);

            if (desiredValue == null || desiredValue === lastSentValue) {
                syncingByStokId.set(stokId, false);
                return;
            }

            stokInput.value = String(desiredValue);
            lastSentByStokId.set(stokId, desiredValue);

            if (typeof window.updateStok === 'function') {
                stokInput.setAttribute('data-update-scope', 'final-only');
                window.updateStok(stokInput);
            }

            const waitDone = setInterval(function() {
                if (stokInput.disabled) return;
                clearInterval(waitDone);
                syncLoop();
            }, 120);
        };

        syncLoop();
    }

    async function processScannedBarcode(rawBarcode) {
        const scanned = normalizeBarcode(rawBarcode);
        if (!scanned) return;

        if (isProcessing) return;
        isProcessing = true;

        refreshBarcodeIndex();

        const entry = findRowByBarcode(scanned);
        if (!entry || !entry.row) {
            setStatus('Barcode tidak terdaftar di gudang ini.', 'error');
            toast('Barcode tidak terdaftar di master barang gudang ini.', 'error');
            isProcessing = false;
            return;
        }

        const row = entry.row;
        const kind = entry.kind || 'item';

        const stokInput = row.querySelector('input[data-id][data-stok-awal]');
        if (!stokInput) {
            setStatus('Input stok final tidak ditemukan.', 'error');
            isProcessing = false;
            return;
        }
        const isSyncing = stokInput.getAttribute('data-syncing') === '1';
        const stokIsDisabled = !!stokInput.disabled;
        if (stokIsDisabled && !isSyncing) {
            setStatus('Tidak ada akses ubah stok final.', 'warning');
            isProcessing = false;
            return;
        }

        const stokId = String(stokInput.getAttribute('data-id') || '');
        const stokAwal = parseFloat(stokInput.getAttribute('data-stok-awal') || '0');
        const inputFinal = parseFloat(String(stokInput.value || '0').replace(',', '.')) || 0;
        if (!scanCountByStokId.has(stokId)) {
            scanCountByStokId.set(stokId, inputFinal);
        } else if (scanCountByStokId.get(stokId) !== inputFinal && !stokInput.disabled) {
            scanCountByStokId.set(stokId, inputFinal);
        }
        const currentFinal = parseFloat(String(scanCountByStokId.get(stokId) || '0').replace(',', '.')) || 0;
        let increment = 1;
        if (kind === 'konversi' || kind === 'dus') {
            try {
                const k = await getKonversiDus(row, stokId);
                if (!k) {
                    setStatus('Konversi belum diset untuk barang ini.', 'error');
                    toast('Konversi belum diset untuk barang ini.', 'error');
                    isProcessing = false;
                    return;
                }
                increment = k;
            } catch (e) {
                setStatus('Gagal ambil konversi: ' + (e?.message || 'unknown'), 'error');
                toast('Gagal ambil konversi.', 'error');
                isProcessing = false;
                return;
            }
        }
        const nextCount = currentFinal + increment;

        if (currentFinal > stokAwal) {
            setStatus('Stok final sudah melebihi stok awal (maks: ' + stokAwal + ').', 'warning');
            toast('Stok final sudah melebihi stok awal (maks: ' + stokAwal + ').', 'warning');
            isProcessing = false;
            return;
        }

        if (nextCount > stokAwal) {
            setStatus('Melebihi stok awal (maks: ' + stokAwal + ').', 'warning');
            toast('Melebihi stok awal (maks: ' + stokAwal + '), scan tidak dapat ditambahkan.', 'warning');
            isProcessing = false;
            return;
        }

        scanCountByStokId.set(stokId, nextCount);
        desiredFinalByStokId.set(stokId, nextCount);
        startedScanAtByStokId.set(stokId, Date.now());

        row.classList.add('table-info');
        setTimeout(function() { row.classList.remove('table-info'); }, 600);
        row.scrollIntoView({ block: 'nearest', behavior: 'smooth' });

        const nama = (row.cells[4]?.textContent || row.cells[1]?.textContent || '').trim();
        const isKonversiScan = (kind === 'konversi' || kind === 'dus');
        const barcodeLabel = (isKonversiScan ? (row.getAttribute('data-barcode-konversi') || row.getAttribute('data-barcode-dus') || scanned) : (row.getAttribute('data-barcode') || scanned));
        const incLabel = (isKonversiScan ? ('+' + increment + ' (konversi)') : '+1');
        setStatus(nama + ' [' + barcodeLabel + '] — terhitung: ' + nextCount + ' (' + incLabel + ')' + (stokIsDisabled ? ' (sinkron...)' : ''));

        requestSync(stokId, stokInput);
        isProcessing = false;
    }

    function finishScan() {
        if (!scanningEnabled) return;
        const value = scanInput.value.trim();
        if (!value) return;
        processScannedBarcode(value);
        scanInput.value = '';
        scanInput.focus();
    }

    scanInput.addEventListener('keydown', function(e) {
        if (!scanningEnabled) return;
        if (e.key !== 'Enter') return;
        e.preventDefault();
        finishScan();
    });

    refreshBarcodeIndex();
    setStatus('Klik "Scan Barcode (Item/Konversi)" untuk mulai.', 'info');

    function setScanButtonState() {
        if (!scanBtn) return;
        if (scanningEnabled) {
            scanBtn.classList.remove('btn-outline-primary');
            scanBtn.classList.add('btn-primary');
            scanBtn.innerHTML = "<i class='bx bx-radio-circle-marked me-1'></i>Mode Scan Aktif";
        } else {
            scanBtn.classList.remove('btn-primary');
            scanBtn.classList.add('btn-outline-primary');
            scanBtn.innerHTML = "<i class='bx bx-scan me-1'></i>Scan Barcode (Item/Konversi)";
        }
    }

    if (scanBtn) {
        setScanButtonState();
        scanBtn.addEventListener('click', function() {
            scanningEnabled = true;
            setScanButtonState();
            refreshBarcodeIndex();
                scanCountByStokId.clear();
                desiredFinalByStokId.clear();
                syncingByStokId.clear();
                lastSentByStokId.clear();
                startedScanAtByStokId.clear();
            setStatus('Mode scan aktif. Silakan scan barcode.', 'info');
            scanInput.value = '';
            try { scanInput.focus(); scanInput.select(); } catch (_) {}
        });
    }

    window.addEventListener('focus', function() {
        if (!scanningEnabled) return;
        setTimeout(function() {
            try { scanInput.focus(); } catch (_) {}
        }, 0);
    });

    document.addEventListener('click', function(e) {
        if (!scanningEnabled) return;
        const target = e.target;
        if (target === scanInput) return;
        if (isEditableElement(target)) return;
        setTimeout(function() {
            try { scanInput.focus(); } catch (_) {}
        }, 0);
    }, true);

    document.addEventListener('keydown', function(e) {
        if (!scanningEnabled) return;
        if (e.defaultPrevented) return;
        if (e.ctrlKey || e.metaKey || e.altKey) return;

        const now = Date.now();
        const key = e.key;
        const active = document.activeElement;
        const activeIsEditable = isEditableElement(active);

        const resetGlobalScan = function() {
            globalScanBuffer = '';
            globalScanStartAt = 0;
            globalScanLastAt = 0;
            globalScanIntercepting = false;
            if (globalScanTimer) clearTimeout(globalScanTimer);
            globalScanTimer = null;
        };

        const scheduleReset = function() {
            if (globalScanTimer) clearTimeout(globalScanTimer);
            globalScanTimer = setTimeout(function() {
                resetGlobalScan();
            }, 350);
        };

        if (key === 'Enter') {
            const duration = globalScanStartAt ? (now - globalScanStartAt) : Number.POSITIVE_INFINITY;
            const sinceLast = globalScanLastAt ? (now - globalScanLastAt) : Number.POSITIVE_INFINITY;
            const looksLikeScan = globalScanBuffer.length >= 4 && duration < 1200 && sinceLast < 220;

            if ((globalScanIntercepting || !activeIsEditable) && looksLikeScan) {
                e.preventDefault();
                e.stopPropagation();
                processScannedBarcode(globalScanBuffer);
                resetGlobalScan();
                scanInput.value = '';
                try { scanInput.focus(); } catch (_) {}
            } else {
                resetGlobalScan();
            }
            return;
        }

        if (key.length !== 1) return;

        const gap = globalScanLastAt ? (now - globalScanLastAt) : Number.POSITIVE_INFINITY;
        const isNewBurst = !globalScanBuffer || gap > 120;

        if (isNewBurst) {
            globalScanBuffer = key;
            globalScanStartAt = now;
            globalScanIntercepting = false;
        } else {
            globalScanBuffer += key;
        }

        const burstDuration = now - globalScanStartAt;
        const looksScannerFast = !isNewBurst && gap < 55 && burstDuration < 200;

        if (!globalScanIntercepting && looksScannerFast && globalScanBuffer.length >= 2) {
            globalScanIntercepting = true;

            if (activeIsEditable && active !== scanInput && 'value' in active) {
                const prefix = globalScanBuffer.slice(0, -1);
                const currentValue = String(active.value || '');
                if (prefix && currentValue.endsWith(prefix)) {
                    active.value = currentValue.slice(0, currentValue.length - prefix.length);
                }
            }
        }

        globalScanLastAt = now;
        scheduleReset();

        if (globalScanIntercepting && active !== scanInput) {
            e.preventDefault();
            e.stopPropagation();
            scanInput.value = globalScanBuffer;
            try { scanInput.focus(); } catch (_) {}
        }
    }, true);

    window.refreshBarcodeScanIndex = refreshBarcodeIndex;
});
