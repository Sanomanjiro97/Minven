<?php
session_start();
require_once '../../config.php';
require_once '../../includes/access_check.php';
require_once '../../includes/menu_access_helper.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit();
}

if (!checkAccess('purchase_order', 'complete')) {
    $_SESSION['error'] = 'Anda tidak memiliki akses untuk melakukan pembagian stok PO!';
    header('Location: index.php');
    exit();
}

function ensure_po_stock_split_table($conn) {
    $res = $conn->query("SHOW TABLES LIKE 'po_stock_split'");
    if ($res instanceof mysqli_result) {
        $exists = $res->num_rows > 0;
        $res->free();
        if ($exists) {
            return true;
        }
    }
    $sql = "CREATE TABLE IF NOT EXISTS po_stock_split (
        id INT(11) NOT NULL AUTO_INCREMENT,
        purchase_order_id INT(11) NOT NULL,
        detail_purchase_order_id INT(11) NOT NULL,
        detail_barang VARCHAR(255) NOT NULL,
        qty_output INT(11) NOT NULL DEFAULT 0,
        created_by INT(11) DEFAULT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (id),
        KEY idx_po (purchase_order_id),
        KEY idx_dpo (detail_purchase_order_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
    return $conn->query($sql) === true;
}

ensure_po_stock_split_table($conn);

$po_id = 0;
if (isset($_GET['id'])) {
    $po_id = (int)$_GET['id'];
} elseif (isset($_POST['po_id'])) {
    $po_id = (int)$_POST['po_id'];
}

if ($po_id <= 0) {
    header('Location: index.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $use_split = isset($_POST['use_split']) && is_array($_POST['use_split']) ? $_POST['use_split'] : [];
    $split_label = isset($_POST['split_label']) && is_array($_POST['split_label']) ? $_POST['split_label'] : [];
    $split_qty = isset($_POST['split_qty']) && is_array($_POST['split_qty']) ? $_POST['split_qty'] : [];

    $stmt_po = $conn->prepare("SELECT id, status FROM purchase_order WHERE id = ? FOR UPDATE");
    if (!$stmt_po) {
        $_SESSION['error'] = 'Database error';
        header('Location: index.php');
        exit();
    }

    $conn->begin_transaction();
    try {
        $stmt_po->bind_param('i', $po_id);
        $stmt_po->execute();
        $po_res = $stmt_po->get_result();
        $po_row = $po_res ? $po_res->fetch_assoc() : null;
        $stmt_po->close();

        if (!$po_row) {
            throw new Exception('PO tidak ditemukan');
        }
        if ((string)$po_row['status'] !== 'completed') {
            throw new Exception('Pembagian stok hanya bisa untuk PO berstatus completed');
        }

        $stmt_d = $conn->prepare("SELECT id FROM detail_purchase_order WHERE purchase_order_id = ? AND (status IS NULL OR status != 'rejected')");
        if (!$stmt_d) {
            throw new Exception('Database error');
        }
        $stmt_d->bind_param('i', $po_id);
        $stmt_d->execute();
        $d_res = $stmt_d->get_result();
        $allowed_detail_ids = [];
        if ($d_res) {
            while ($r = $d_res->fetch_assoc()) {
                $allowed_detail_ids[(int)$r['id']] = true;
            }
        }
        $stmt_d->close();

        $stmt_del = $conn->prepare("DELETE FROM po_stock_split WHERE purchase_order_id = ?");
        if (!$stmt_del) {
            throw new Exception('Database error');
        }
        $stmt_del->bind_param('i', $po_id);
        if (!$stmt_del->execute()) {
            $stmt_del->close();
            throw new Exception('Gagal menghapus data pembagian stok lama');
        }
        $stmt_del->close();

        $stmt_ins = $conn->prepare("INSERT INTO po_stock_split (purchase_order_id, detail_purchase_order_id, detail_barang, qty_output, created_by) VALUES (?, ?, ?, ?, ?)");
        if (!$stmt_ins) {
            throw new Exception('Database error');
        }

        $created_by = (int)$_SESSION['user_id'];
        $inserted = 0;

        foreach ($allowed_detail_ids as $detail_id => $_) {
            $enabled = isset($use_split[(string)$detail_id]) || isset($use_split[$detail_id]);
            if (!$enabled) {
                continue;
            }

            $labels = $split_label[(string)$detail_id] ?? $split_label[$detail_id] ?? [];
            $qtys = $split_qty[(string)$detail_id] ?? $split_qty[$detail_id] ?? [];
            if (!is_array($labels) || !is_array($qtys)) {
                throw new Exception('Format pembagian stok tidak valid');
            }

            $merged = [];
            $max = max(count($labels), count($qtys));
            for ($i = 0; $i < $max; $i++) {
                $label = trim((string)($labels[$i] ?? ''));
                $qty = (int)($qtys[$i] ?? 0);
                if ($label === '' && $qty === 0) {
                    continue;
                }
                if ($label === '') {
                    throw new Exception('Nama porsi/detail wajib diisi');
                }
                if ($qty <= 0) {
                    throw new Exception('Qty hasil pembagian wajib lebih dari 0');
                }
                if (!isset($merged[$label])) {
                    $merged[$label] = 0;
                }
                $merged[$label] += $qty;
            }

            if (count($merged) === 0) {
                throw new Exception('Minimal 1 baris pembagian stok per item yang diaktifkan');
            }

            foreach ($merged as $label => $qty) {
                $qty = (int)$qty;
                $label = (string)$label;
                $stmt_ins->bind_param('iisii', $po_id, $detail_id, $label, $qty, $created_by);
                if (!$stmt_ins->execute()) {
                    throw new Exception('Gagal menyimpan pembagian stok');
                }
                $inserted++;
            }
        }

        $stmt_ins->close();
        $conn->commit();
        $_SESSION['success'] = $inserted > 0 ? 'Pembagian stok berhasil disimpan' : 'Pembagian stok dikosongkan';
        header('Location: split_stock.php?id=' . $po_id);
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = $e->getMessage();
        header('Location: split_stock.php?id=' . $po_id);
        exit();
    }
}

$stmt_po = $conn->prepare("SELECT po.*, s.nama_supplier FROM purchase_order po LEFT JOIN supplier s ON po.supplier_id = s.id WHERE po.id = ?");
$stmt_po->bind_param('i', $po_id);
$stmt_po->execute();
$po_res = $stmt_po->get_result();
$po = $po_res ? $po_res->fetch_assoc() : null;
$stmt_po->close();

if (!$po) {
    $_SESSION['error'] = 'PO tidak ditemukan';
    header('Location: index.php');
    exit();
}
if ((string)$po['status'] !== 'completed') {
    $_SESSION['error'] = 'Pembagian stok hanya bisa untuk PO berstatus completed';
    header('Location: index.php');
    exit();
}

$stmt_d = $conn->prepare("SELECT dpo.id AS detail_id, dpo.barang_id, dpo.jumlah,
                             b.kode_barang, b.nama_barang, 
                             s.nama_satuan AS satuan_dasar,
                             s1.nama_satuan AS satuan_asal,
                             cpd.nilai_konversi
                      FROM detail_purchase_order dpo
                      LEFT JOIN barang b ON dpo.barang_id = b.id
                      LEFT JOIN satuan s ON b.satuan_id = s.id
                      LEFT JOIN conversi_po_detail cpd ON dpo.id = cpd.detail_purchase_order_id
                      LEFT JOIN satuan s1 ON cpd.satuan_asal_id = s1.id
                      WHERE dpo.purchase_order_id = ? AND (dpo.status IS NULL OR dpo.status != 'rejected')
                      ORDER BY b.kode_barang, dpo.id");
$stmt_d->bind_param('i', $po_id);
$stmt_d->execute();
$d_res = $stmt_d->get_result();
$details = [];
if ($d_res) {
    while ($r = $d_res->fetch_assoc()) {
        $jumlah_po = (float)$r['jumlah'];
        $nilai_konversi = isset($r['nilai_konversi']) ? (float)$r['nilai_konversi'] : 1.0;
        $jumlah_final = round($jumlah_po * $nilai_konversi);
        $satuan_final = $nilai_konversi != 1.0 ? (string)$r['satuan_dasar'] : (string)$r['satuan_asal'];
        if (empty($satuan_final)) $satuan_final = (string)$r['satuan_dasar'];

        $details[] = [
            'detail_id' => (int)$r['detail_id'],
            'barang_id' => (int)$r['barang_id'],
            'kode_barang' => (string)($r['kode_barang'] ?? ''),
            'nama_barang' => (string)($r['nama_barang'] ?? ''),
            'satuan' => $satuan_final,
            'jumlah' => (int)$jumlah_final,
            'satuan_po' => (string)($r['satuan_asal'] ?? $r['satuan_dasar']),
            'jumlah_po' => (int)$jumlah_po
        ];
    }
}
$stmt_d->close();

$splitsByDetail = [];
$stmt_s = $conn->prepare("SELECT id, detail_purchase_order_id, detail_barang, qty_output FROM po_stock_split WHERE purchase_order_id = ? ORDER BY detail_purchase_order_id, id");
if ($stmt_s) {
    $stmt_s->bind_param('i', $po_id);
    $stmt_s->execute();
    $s_res = $stmt_s->get_result();
    if ($s_res) {
        while ($s = $s_res->fetch_assoc()) {
            $dpoId = (int)$s['detail_purchase_order_id'];
            if (!isset($splitsByDetail[$dpoId])) {
                $splitsByDetail[$dpoId] = [];
            }
            $splitsByDetail[$dpoId][] = [
                'id' => (int)$s['id'],
                'detail_barang' => (string)($s['detail_barang'] ?? ''),
                'qty_output' => (int)($s['qty_output'] ?? 0),
            ];
        }
    }
    $stmt_s->close();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pembagian Stok PO</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.0.7/css/boxicons.min.css" rel="stylesheet">
    <style>
        .split-table td, .split-table th { vertical-align: middle; }
        .split-rows[data-enabled="0"] { display: none; }
    </style>
</head>
<body>
    <?php include_once '../../templates/navbar.php'; ?>

    <div class="container-fluid mt-4">
        <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
            <div>
                <h2 class="mb-0">Pembagian Stok</h2>
                <div class="text-muted">PO: <?= htmlspecialchars((string)$po['no_po']) ?> · Supplier: <?= htmlspecialchars((string)($po['nama_supplier'] ?? '-')) ?></div>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="index.php" class="btn btn-outline-secondary"><i class='bx bx-arrow-back'></i> Kembali</a>
                <a href="view.php?id=<?= (int)$po_id ?>" class="btn btn-outline-info"><i class='bx bx-show'></i> Detail PO</a>
            </div>
        </div>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success"><?= $_SESSION['success']; unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger"><?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>

        <form method="post" class="card">
            <div class="card-body">
                <input type="hidden" name="po_id" value="<?= (int)$po_id ?>">

                <div class="alert alert-warning mb-3">
                    Pembagian stok bersifat opsional. Jika diaktifkan, qty hasil boleh berbeda dengan qty PO.
                </div>

                <div class="accordion" id="splitAccordion">
                    <?php foreach ($details as $idx => $d): ?>
                        <?php
                            $detailId = (int)$d['detail_id'];
                            $existing = $splitsByDetail[$detailId] ?? [];
                            $enabled = count($existing) > 0;
                            $collapseId = 'collapse_' . $detailId;
                            $headingId = 'heading_' . $detailId;
                        ?>
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="<?= htmlspecialchars($headingId) ?>">
                                <button class="accordion-button <?= $idx === 0 ? '' : 'collapsed' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#<?= htmlspecialchars($collapseId) ?>" aria-expanded="<?= $idx === 0 ? 'true' : 'false' ?>" aria-controls="<?= htmlspecialchars($collapseId) ?>">
                                    <div class="w-100 d-flex flex-wrap justify-content-between align-items-center gap-2">
                                        <div class="fw-semibold">
                                            <?= htmlspecialchars((string)$d['kode_barang']) ?> · <?= htmlspecialchars((string)$d['nama_barang']) ?>
                                        </div>
                                        <div class="text-muted">
                                            Qty PO: <?= number_format((int)$d['jumlah_po']) ?> <?= htmlspecialchars((string)$d['satuan_po']) ?>
                                            <?php if ($d['jumlah_po'] != $d['jumlah']): ?>
                                                → <span class="fw-bold text-primary">Konversi: <?= number_format((int)$d['jumlah']) ?> <?= htmlspecialchars((string)$d['satuan']) ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </button>
                            </h2>
                            <div id="<?= htmlspecialchars($collapseId) ?>" class="accordion-collapse collapse <?= $idx === 0 ? 'show' : '' ?>" aria-labelledby="<?= htmlspecialchars($headingId) ?>" data-bs-parent="#splitAccordion">
                                <div class="accordion-body">
                                    <div class="form-check form-switch mb-2">
                                        <input class="form-check-input use-split" type="checkbox" role="switch" id="use_split_<?= $detailId ?>" name="use_split[<?= $detailId ?>]" value="1" <?= $enabled ? 'checked' : '' ?> data-detail-id="<?= $detailId ?>">
                                        <label class="form-check-label" for="use_split_<?= $detailId ?>">Gunakan pembagian stok untuk item ini</label>
                                    </div>

                                    <div class="split-rows" data-detail-id="<?= $detailId ?>" data-enabled="<?= $enabled ? '1' : '0' ?>">
                                        <div class="table-responsive">
                                            <table class="table table-sm table-bordered split-table mb-2">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th style="width: 55%;">Nama Porsi/Detail</th>
                                                        <th style="width: 25%;" class="text-end">Qty Hasil</th>
                                                        <th style="width: 20%;" class="text-center">Aksi</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="split-body" data-detail-id="<?= $detailId ?>">
                                                    <?php if (count($existing) > 0): ?>
                                                        <?php foreach ($existing as $s): ?>
                                                            <tr>
                                                                <td>
                                                                    <input type="text" class="form-control form-control-sm split-label" name="split_label[<?= $detailId ?>][]" value="<?= htmlspecialchars((string)$s['detail_barang']) ?>">
                                                                </td>
                                                                <td>
                                                                    <input type="number" class="form-control form-control-sm text-end split-qty" name="split_qty[<?= $detailId ?>][]" min="1" step="1" value="<?= (int)$s['qty_output'] ?>">
                                                                </td>
                                                                <td class="text-center">
                                                                    <button type="button" class="btn btn-sm btn-outline-danger remove-split" data-detail-id="<?= $detailId ?>"><i class='bx bx-trash'></i></button>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    <?php else: ?>
                                                        <tr>
                                                            <td>
                                                                <input type="text" class="form-control form-control-sm split-label" name="split_label[<?= $detailId ?>][]" value="">
                                                            </td>
                                                            <td>
                                                                <input type="number" class="form-control form-control-sm text-end split-qty" name="split_qty[<?= $detailId ?>][]" min="1" step="1" value="1">
                                                            </td>
                                                            <td class="text-center">
                                                                <button type="button" class="btn btn-sm btn-outline-danger remove-split" data-detail-id="<?= $detailId ?>"><i class='bx bx-trash'></i></button>
                                                            </td>
                                                        </tr>
                                                    <?php endif; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap">
                                            <button type="button" class="btn btn-sm btn-outline-primary add-split" data-detail-id="<?= $detailId ?>"><i class='bx bx-plus'></i> Tambah Baris</button>
                                            <div class="text-muted small">Contoh: Kentang A, Kentang B, Kentang C</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="card-footer d-flex justify-content-end gap-2">
                <button type="submit" class="btn btn-primary"><i class='bx bx-save'></i> Simpan</button>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function setEnabled(detailId, enabled) {
            const wrap = document.querySelector('.split-rows[data-detail-id="' + detailId + '"]');
            if (!wrap) return;
            wrap.dataset.enabled = enabled ? '1' : '0';

            const labels = wrap.querySelectorAll('.split-label');
            const qtys = wrap.querySelectorAll('.split-qty');
            for (const el of labels) el.required = !!enabled;
            for (const el of qtys) el.required = !!enabled;
        }

        function ensureAtLeastOneRow(detailId) {
            const tbody = document.querySelector('.split-body[data-detail-id="' + detailId + '"]');
            if (!tbody) return;
            if (tbody.querySelectorAll('tr').length > 0) return;
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td><input type="text" class="form-control form-control-sm split-label" name="split_label[${detailId}][]" value=""></td>
                <td><input type="number" class="form-control form-control-sm text-end split-qty" name="split_qty[${detailId}][]" min="1" step="1" value="1"></td>
                <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger remove-split" data-detail-id="${detailId}"><i class='bx bx-trash'></i></button></td>
            `;
            tbody.appendChild(tr);
        }

        document.querySelectorAll('.use-split').forEach(cb => {
            const detailId = cb.dataset.detailId;
            setEnabled(detailId, cb.checked);
            cb.addEventListener('change', () => {
                setEnabled(detailId, cb.checked);
                if (cb.checked) ensureAtLeastOneRow(detailId);
            });
        });

        document.addEventListener('click', (e) => {
            const addBtn = e.target.closest('.add-split');
            if (addBtn) {
                const detailId = addBtn.dataset.detailId;
                const tbody = document.querySelector('.split-body[data-detail-id="' + detailId + '"]');
                if (!tbody) return;
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td><input type="text" class="form-control form-control-sm split-label" name="split_label[${detailId}][]" value=""></td>
                    <td><input type="number" class="form-control form-control-sm text-end split-qty" name="split_qty[${detailId}][]" min="1" step="1" value="1"></td>
                    <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger remove-split" data-detail-id="${detailId}"><i class='bx bx-trash'></i></button></td>
                `;
                tbody.appendChild(tr);
                const wrap = document.querySelector('.split-rows[data-detail-id="' + detailId + '"]');
                const enabled = wrap && wrap.dataset.enabled === '1';
                if (enabled) {
                    tr.querySelector('.split-label').required = true;
                    tr.querySelector('.split-qty').required = true;
                }
                return;
            }

            const rmBtn = e.target.closest('.remove-split');
            if (rmBtn) {
                const detailId = rmBtn.dataset.detailId;
                const tr = rmBtn.closest('tr');
                if (tr) tr.remove();
                ensureAtLeastOneRow(detailId);
            }
        });
    </script>
</body>
</html>

