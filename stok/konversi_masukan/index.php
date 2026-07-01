<?php
session_start();
require_once '../../config.php';
require_once '../../includes/access_check.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../index.php');
    exit();
}

if (!checkAccess('konversi_masukan', 'view')) {
    $_SESSION['error'] = 'Anda tidak memiliki akses untuk melihat menu konversi!';
    header('Location: ../../dashboard.php');
    exit();
}

function ensureKonversiTables(mysqli $conn): void
{
    $conn->query("
        CREATE TABLE IF NOT EXISTS konversi_masukan (
            id INT(11) NOT NULL AUTO_INCREMENT,
            tanggal DATE NOT NULL,
            barang_id INT(11) NOT NULL,
            satuan_asal_id INT(11) NOT NULL,
            satuan_tujuan_id INT(11) NOT NULL,
            qty_asal DECIMAL(12,4) NOT NULL DEFAULT 0,
            nilai_konversi DECIMAL(12,4) NOT NULL DEFAULT 0,
            qty_hasil DECIMAL(12,4) NOT NULL DEFAULT 0,
            keterangan VARCHAR(255) DEFAULT NULL,
            created_by INT(11) DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_konversi_masukan_barang (barang_id),
            KEY idx_konversi_masukan_tanggal (tanggal),
            KEY idx_konversi_masukan_satuan_asal (satuan_asal_id),
            KEY idx_konversi_masukan_satuan_tujuan (satuan_tujuan_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");

    $conn->query("
        CREATE TABLE IF NOT EXISTS konversi_satuan_barang (
            id INT(11) NOT NULL AUTO_INCREMENT,
            barang_id INT(11) NOT NULL,
            satuan_asal_id INT(11) NOT NULL,
            satuan_tujuan_id INT(11) NOT NULL,
            nilai_konversi DECIMAL(12,4) NOT NULL DEFAULT 0,
            created_by INT(11) DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_konversi_barang (barang_id, satuan_asal_id, satuan_tujuan_id),
            KEY idx_konversi_barang_barang (barang_id),
            KEY idx_konversi_barang_satuan_asal (satuan_asal_id),
            KEY idx_konversi_barang_satuan_tujuan (satuan_tujuan_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");
}

ensureKonversiTables($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formAction = $_POST['form_action'] ?? 'save_konversi';

    if ($formAction === 'save_master_konversi') {
        if (!checkAccess('konversi_masukan', 'add')) {
            $_SESSION['error'] = 'Anda tidak memiliki akses untuk menambah data konversi!';
            header('Location: index.php');
            exit();
        }

        $barangId = (int)($_POST['master_barang_id'] ?? 0);
        $satuanAsalId = (int)($_POST['master_satuan_asal_id'] ?? 0);
        $satuanTujuanId = (int)($_POST['master_satuan_tujuan_id'] ?? 0);
        $qtyAsalRef = (float)($_POST['master_qty_asal_ref'] ?? 0);
        $gramPerAsal = (float)($_POST['master_gram_per_asal'] ?? 0);
        $mlPerGram = 1.0;
        $qtyTengahRef = $qtyAsalRef * $gramPerAsal;
        $qtyTujuanRef = $qtyTengahRef * $mlPerGram;

        if ($barangId <= 0 || $satuanAsalId <= 0 || $satuanTujuanId <= 0 || $qtyAsalRef <= 0 || $gramPerAsal <= 0 || $qtyTujuanRef <= 0) {
            $_SESSION['error'] = 'Master konversi gagal disimpan. Data harus lengkap dan lebih besar dari 0.';
            header('Location: index.php');
            exit();
        }
        if ($satuanAsalId === $satuanTujuanId) {
            $_SESSION['error'] = 'Satuan asal dan satuan tujuan harus berbeda.';
            header('Location: index.php');
            exit();
        }

        $nilaiKonversi = $qtyTujuanRef / $qtyAsalRef;
        $userId = (int)$_SESSION['user_id'];

        $sqlMaster = "INSERT INTO konversi_satuan_barang
                      (barang_id, satuan_asal_id, satuan_tujuan_id, nilai_konversi, created_by)
                      VALUES (?, ?, ?, ?, ?)
                      ON DUPLICATE KEY UPDATE
                          nilai_konversi = VALUES(nilai_konversi),
                          created_by = VALUES(created_by),
                          updated_at = CURRENT_TIMESTAMP";
        $stmtMaster = $conn->prepare($sqlMaster);
        if (!$stmtMaster) {
            $_SESSION['error'] = 'Gagal menyiapkan query master: ' . $conn->error;
            header('Location: index.php');
            exit();
        }
        $stmtMaster->bind_param('iiidi', $barangId, $satuanAsalId, $satuanTujuanId, $nilaiKonversi, $userId);
        if ($stmtMaster->execute()) {
            $_SESSION['success'] = 'Master konversi berhasil disimpan.';
        } else {
            $_SESSION['error'] = 'Gagal menyimpan master konversi: ' . $stmtMaster->error;
        }
        $stmtMaster->close();
        header('Location: index.php');
        exit();
    }
    if ($formAction === 'delete_master_konversi') {
        if (!checkAccess('konversi_masukan', 'delete')) {
            $_SESSION['error'] = 'Anda tidak memiliki akses untuk menghapus master konversi!';
            header('Location: index.php');
            exit();
        }

        $masterId = (int)($_POST['master_id'] ?? 0);
        if ($masterId <= 0) {
            $_SESSION['error'] = 'ID master konversi tidak valid.';
            header('Location: index.php');
            exit();
        }

        $stmtDelete = $conn->prepare("DELETE FROM konversi_satuan_barang WHERE id = ? LIMIT 1");
        if (!$stmtDelete) {
            $_SESSION['error'] = 'Gagal menyiapkan query hapus: ' . $conn->error;
            header('Location: index.php');
            exit();
        }
        $stmtDelete->bind_param('i', $masterId);
        if ($stmtDelete->execute() && $stmtDelete->affected_rows > 0) {
            $_SESSION['success'] = 'Master konversi berhasil dihapus.';
        } else {
            $_SESSION['error'] = 'Data master konversi tidak ditemukan atau gagal dihapus.';
        }
        $stmtDelete->close();
        header('Location: index.php');
        exit();
    }

    $_SESSION['error'] = 'Aksi form tidak dikenal.';
    header('Location: index.php');
    exit();
}

$barangList = [];
$barangResult = $conn->query("SELECT id, kode_barang, nama_barang FROM barang ORDER BY nama_barang");
if ($barangResult) {
    while ($row = $barangResult->fetch_assoc()) {
        $barangList[] = $row;
    }
}

$satuanList = [];
$satuanResult = $conn->query("SELECT id, kode_satuan, nama_satuan FROM satuan ORDER BY nama_satuan");
if ($satuanResult) {
    while ($row = $satuanResult->fetch_assoc()) {
        $satuanList[] = $row;
    }
}

$masterKonversiRows = [];
$masterSql = "
    SELECT kb.*,
           b.kode_barang,
           b.nama_barang,
           s1.nama_satuan AS satuan_asal_nama,
           s2.nama_satuan AS satuan_tujuan_nama
    FROM konversi_satuan_barang kb
    INNER JOIN barang b ON kb.barang_id = b.id
    INNER JOIN satuan s1 ON kb.satuan_asal_id = s1.id
    INNER JOIN satuan s2 ON kb.satuan_tujuan_id = s2.id
    ORDER BY kb.updated_at DESC
    LIMIT 200
";
$masterResult = $conn->query($masterSql);
if ($masterResult) {
    while ($row = $masterResult->fetch_assoc()) {
        $masterKonversiRows[] = $row;
    }
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Konversi - Sistem Inventory</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <?php include '../../templates/navbar.php'; ?>

    <div class="container-fluid mt-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="mb-0">Konversi</h4>
        </div>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($_SESSION['success']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($_SESSION['error']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <div class="card mb-4">
            <div class="card-header">
                <strong>Master Konversi</strong>
            </div>
            <div class="card-body">
                <form method="POST" class="mb-3">
                    <input type="hidden" name="form_action" value="save_master_konversi">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Barang</label>
                            <div class="dropdown w-100" id="master_barang_dropdown">
                                <button class="btn btn-outline-secondary dropdown-toggle w-100 text-start" type="button" id="master_barang_dropdown_btn" data-bs-toggle="dropdown" aria-expanded="false">
                                    -- Pilih Barang --
                                </button>
                                <div class="dropdown-menu w-100 p-2" aria-labelledby="master_barang_dropdown_btn" style="max-height: 360px; overflow: auto;">
                                    <input type="text" class="form-control mb-2" id="master_barang_filter" placeholder="Cari barang..." autocomplete="off">
                                    <div class="list-group" id="master_barang_list">
                                        <button type="button" class="list-group-item list-group-item-action master-barang-item" data-value="" data-text="-- Pilih Barang --">-- Pilih Barang --</button>
                                        <?php foreach ($barangList as $barang): ?>
                                            <?php $barangText = $barang['kode_barang'] . ' - ' . $barang['nama_barang']; ?>
                                            <button type="button" class="list-group-item list-group-item-action master-barang-item" data-value="<?= (int)$barang['id'] ?>" data-text="<?= htmlspecialchars($barangText) ?>">
                                                <?= htmlspecialchars($barangText) ?>
                                            </button>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                            <select name="master_barang_id" id="master_barang_id" class="form-select d-none" required>
                                <option value="">-- Pilih Barang --</option>
                                <?php foreach ($barangList as $barang): ?>
                                    <option value="<?= (int)$barang['id'] ?>">
                                        <?= htmlspecialchars($barang['kode_barang'] . ' - ' . $barang['nama_barang']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Qty Asal</label>
                            <input type="number" step="0.0001" min="0.0001" id="master_qty_asal_ref" name="master_qty_asal_ref" class="form-control" placeholder="1" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Satuan Asal</label>
                            <select name="master_satuan_asal_id" id="master_satuan_asal_id" class="form-select" required>
                                <option value="">-- Pilih --</option>
                                <?php foreach ($satuanList as $satuan): ?>
                                    <option value="<?= (int)$satuan['id'] ?>">
                                        <?= htmlspecialchars($satuan['nama_satuan']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Isi per 1 Asal (Gram)</label>
                            <input type="number" step="0.0001" min="0.0001" id="master_gram_per_asal" name="master_gram_per_asal" class="form-control" placeholder="Contoh: 500" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Hasil Gram (Auto)</label>
                            <input type="number" step="0.0001" min="0.0001" id="master_qty_gram_ref" class="form-control" readonly>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Satuan Tujuan</label>
                            <select name="master_satuan_tujuan_id" id="master_satuan_tujuan_id" class="form-select" required>
                                <option value="">-- Pilih --</option>
                                <?php foreach ($satuanList as $satuan): ?>
                                    <option value="<?= (int)$satuan['id'] ?>">
                                        <?= htmlspecialchars($satuan['nama_satuan']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Nilai Konversi Akhir</label>
                            <input type="number" step="0.0001" min="0.0001" id="master_nilai_konversi" class="form-control" readonly>
                            <small class="text-muted">Rumus: Qty Asal x Gram per Asal</small>
                        </div>
                        <div class="col-md-2 d-grid">
                            <label class="form-label d-none d-md-block">&nbsp;</label>
                            <button type="submit" class="btn btn-success">Simpan Master</button>
                        </div>
                    </div>
                </form>

                <div class="table-responsive">
                    <div class="row g-2 mb-2">
                        <div class="col-md-4">
                            <input type="text" id="master_table_filter" class="form-control form-control-sm" placeholder="Cari nama/kode barang...">
                        </div>
                    </div>
                    <table class="table table-sm table-striped table-bordered" id="master_konversi_table">
                        <thead>
                            <tr>
                                <th>Barang</th>
                                <th>Qty Asal</th>
                                <th>Satuan Asal</th>
                                <th>Satuan Tujuan</th>
                                <th>Nilai Konversi</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="master_konversi_tbody">
                            <?php if (empty($masterKonversiRows)): ?>
                                <tr><td colspan="6" class="text-center text-muted">Belum ada master konversi.</td></tr>
                            <?php else: ?>
                                <?php foreach ($masterKonversiRows as $m): ?>
                                    <tr data-master-row data-text="<?= htmlspecialchars($m['kode_barang'] . ' - ' . $m['nama_barang']) ?>">
                                        <td><?= htmlspecialchars($m['kode_barang'] . ' - ' . $m['nama_barang']) ?></td>
                                        <td><?= htmlspecialchars(isset($m['qty_asal']) ? rtrim(rtrim(number_format((float)$m['qty_asal'], 4, '.', ''), '0'), '.') : '1') ?></td>
                                        <td><?= htmlspecialchars($m['satuan_asal_nama']) ?></td>
                                        <td><?= htmlspecialchars($m['satuan_tujuan_nama']) ?></td>
                                        <td><?= htmlspecialchars(rtrim(rtrim(number_format((float)$m['nilai_konversi'], 4, '.', ''), '0'), '.')) ?></td>
                                        <td>
                                            <form method="POST" onsubmit="return confirm('Yakin hapus master konversi ini?');">
                                                <input type="hidden" name="form_action" value="delete_master_konversi">
                                                <input type="hidden" name="master_id" value="<?= (int)$m['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-danger">Hapus</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            <tr id="master_table_no_match" style="display:none;">
                                <td colspan="6" class="text-center text-muted">Tidak ada data yang cocok.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        (function initKonversiMasukan() {
            const masterBarangSelect = document.getElementById('master_barang_id');
            const masterBarangDropdownBtn = document.getElementById('master_barang_dropdown_btn');
            const masterBarangDropdown = document.getElementById('master_barang_dropdown');
            const masterBarangFilter = document.getElementById('master_barang_filter');
            const masterQtyAsalRef = document.getElementById('master_qty_asal_ref');
            const masterGramPerAsal = document.getElementById('master_gram_per_asal');
            const masterQtyGramRef = document.getElementById('master_qty_gram_ref');
            const masterNilaiKonversi = document.getElementById('master_nilai_konversi');
            const masterSatuanAsal = document.getElementById('master_satuan_asal_id');
            const masterSatuanTujuan = document.getElementById('master_satuan_tujuan_id');

            function normalizeText(value) {
                return String(value || '').toLowerCase().trim();
            }

            function initBarangDropdown() {
                if (!masterBarangSelect || !masterBarangDropdownBtn || !masterBarangDropdown || !masterBarangFilter) return;

                const items = Array.from(masterBarangDropdown.querySelectorAll('.master-barang-item'));

                function syncButtonLabel() {
                    const selected = masterBarangSelect.options[masterBarangSelect.selectedIndex];
                    const label = selected && selected.value ? (selected.textContent || '').trim() : '-- Pilih Barang --';
                    masterBarangDropdownBtn.textContent = label || '-- Pilih Barang --';
                }

                function applyFilter(q) {
                    const query = normalizeText(q);
                    for (const item of items) {
                        const text = item.getAttribute('data-text') || item.textContent || '';
                        const visible = !query || normalizeText(text).includes(query);
                        item.style.display = visible ? '' : 'none';
                    }
                }

                function selectValue(value) {
                    masterBarangSelect.value = String(value || '');
                    masterBarangSelect.dispatchEvent(new Event('change', { bubbles: true }));
                    syncButtonLabel();
                }

                for (const item of items) {
                    item.addEventListener('click', function() {
                        selectValue(item.getAttribute('data-value') || '');
                        const instance = bootstrap.Dropdown.getOrCreateInstance(masterBarangDropdownBtn);
                        instance.hide();
                    });
                }

                masterBarangFilter.addEventListener('input', function() {
                    applyFilter(masterBarangFilter.value);
                });

                masterBarangDropdown.addEventListener('show.bs.dropdown', function() {
                    masterBarangFilter.value = '';
                    applyFilter('');
                    setTimeout(() => masterBarangFilter.focus(), 0);
                });

                masterBarangSelect.addEventListener('change', syncButtonLabel);
                syncButtonLabel();
            }

            function formatNumber(value) {
                return Number(value).toFixed(4).replace(/\.?0+$/, '');
            }

            function initMasterTableFilter() {
                const filterInput = document.getElementById('master_table_filter');
                const tbody = document.getElementById('master_konversi_tbody');
                const noMatchRow = document.getElementById('master_table_no_match');
                if (!filterInput || !tbody) return;

                function applyTableFilter(q) {
                    const query = normalizeText(q);
                    const rows = Array.from(tbody.querySelectorAll('tr[data-master-row]'));
                    let visibleCount = 0;

                    for (const row of rows) {
                        const haystack = row.getAttribute('data-text') || row.textContent || '';
                        const visible = !query || normalizeText(haystack).includes(query);
                        row.style.display = visible ? '' : 'none';
                        if (visible) visibleCount++;
                    }

                    if (noMatchRow) {
                        const showNoMatch = rows.length > 0 && visibleCount === 0;
                        noMatchRow.style.display = showNoMatch ? '' : 'none';
                    }
                }

                filterInput.addEventListener('input', function() {
                    applyTableFilter(filterInput.value);
                });

                applyTableFilter('');
            }

            function recalcMasterRate() {
                const asal = parseFloat(masterQtyAsalRef.value || '0');
                const gramPerAsal = parseFloat(masterGramPerAsal.value || '0');
                if (asal > 0 && gramPerAsal > 0) {
                    const qtyGram = asal * gramPerAsal;
                    masterQtyGramRef.value = formatNumber(qtyGram);
                    masterNilaiKonversi.value = formatNumber(gramPerAsal);
                } else {
                    masterQtyGramRef.value = '';
                    masterNilaiKonversi.value = '';
                }
            }

            function validateUnits() {
                const asal = masterSatuanAsal.value;
                const tujuan = masterSatuanTujuan.value;

                if (!asal || !tujuan) return true;
                if (asal === tujuan) {
                    alert('Satuan asal dan tujuan tidak boleh sama.');
                    return false;
                }
                return true;
            }

            masterQtyAsalRef.addEventListener('input', recalcMasterRate);
            masterGramPerAsal.addEventListener('input', recalcMasterRate);
            masterSatuanAsal.addEventListener('change', function() {
                if (!validateUnits()) masterSatuanAsal.value = '';
            });
            masterSatuanTujuan.addEventListener('change', function() {
                if (!validateUnits()) masterSatuanTujuan.value = '';
            });

            initBarangDropdown();
            initMasterTableFilter();
            recalcMasterRate();
        })();
    </script>
</body>
</html>
