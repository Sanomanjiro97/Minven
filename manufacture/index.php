<?php
session_start();
require_once '../config.php';
require_once '../includes/access_check.php';
require_once '../includes/menu_access_helper.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

if (!checkAccess('manufacture', 'view')) {
    $_SESSION['error'] = 'Anda tidak memiliki akses untuk melihat menu manufaktur!';
    header('Location: ../dashboard.php');
    exit();
}

function ensureManufactureTables(mysqli $conn): void
{
    $conn->query("
        CREATE TABLE IF NOT EXISTS manufacture (
            id INT(11) NOT NULL AUTO_INCREMENT,
            no_manufacture VARCHAR(30) NOT NULL,
            tanggal DATE NOT NULL,
            gudang_id INT(11) NOT NULL,
            produk_id INT(11) NOT NULL,
            qty_hasil INT(11) NOT NULL DEFAULT 0,
            satuan_label VARCHAR(50) DEFAULT NULL,
            qty_input INT(11) DEFAULT NULL,
            keterangan TEXT DEFAULT NULL,
            transaksi_keluar_id INT(11) DEFAULT NULL,
            transaksi_masuk_id INT(11) DEFAULT NULL,
            created_by INT(11) DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_mf_no (no_manufacture),
            KEY idx_mf_tanggal (tanggal),
            KEY idx_mf_gudang (gudang_id),
            KEY idx_mf_produk (produk_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");

    $conn->query("
        CREATE TABLE IF NOT EXISTS manufacture_bahan (
            id INT(11) NOT NULL AUTO_INCREMENT,
            manufacture_id INT(11) NOT NULL,
            barang_id INT(11) NOT NULL,
            detail_barang VARCHAR(255) NOT NULL DEFAULT '',
            qty_pakai INT(11) NOT NULL DEFAULT 0,
            satuan_label VARCHAR(50) DEFAULT NULL,
            qty_input INT(11) DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_mf_bahan_mf (manufacture_id),
            KEY idx_mf_bahan_barang (barang_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");
}

function generateTrxNumberByPrefix(mysqli $conn, string $prefix): string
{
    $date = date('Ymd');
    $like = $prefix . '-' . $date . '%';
    $stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM transaksi_stok WHERE DATE(created_at) = CURDATE() AND no_transaksi LIKE ?");
    $cnt = 0;
    if ($stmt) {
        $stmt->bind_param('s', $like);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $cnt = (int)($row['cnt'] ?? 0);
        $stmt->close();
    }
    $seq = str_pad((string)($cnt + 1), 4, '0', STR_PAD_LEFT);
    return $prefix . '-' . $date . $seq;
}

function generateManufactureNumber(mysqli $conn, string $tanggal, string $prefix = 'MF'): string
{
    $ymd = date('Ymd', strtotime($tanggal));
    $stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM manufacture WHERE tanggal = ?");
    $cnt = 0;
    if ($stmt) {
        $stmt->bind_param('s', $tanggal);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $cnt = (int)($row['cnt'] ?? 0);
        $stmt->close();
    }
    $seq = str_pad((string)($cnt + 1), 4, '0', STR_PAD_LEFT);
    return $prefix . '-' . $ymd . $seq;
}

ensureManufactureTables($conn);

$gudang_list = [];
if (function_exists('get_accessible_gudang_list')) {
    $gudang_list = array_map(function ($g) {
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!checkAccess('manufacture', 'add')) {
        $_SESSION['error'] = 'Anda tidak memiliki akses untuk menambah manufaktur!';
        header('Location: index.php');
        exit();
    }

    $tanggal = $_POST['tanggal'] ?? date('Y-m-d');
    $gudang_id = (int)($_POST['gudang_id'] ?? 0);
    $keterangan = trim((string)($_POST['keterangan'] ?? ''));

    $hasil_json = $_POST['hasil_data'] ?? '{}';
    $bahan_json = $_POST['bahan_data'] ?? '[]';
    $hasil = json_decode($hasil_json, true);
    $bahan = json_decode($bahan_json, true);

    $produkId = (int)($hasil['barang_id'] ?? 0);
    $qtyHasilBase = (int)($hasil['jumlah'] ?? 0);
    $qtyHasilInput = (int)($hasil['jumlah_input'] ?? $qtyHasilBase);
    $satuanLabel = trim((string)($hasil['satuan'] ?? ''));

    if ($gudang_id <= 0 || $produkId <= 0 || $qtyHasilBase <= 0 || !is_array($bahan) || count($bahan) === 0) {
        $_SESSION['error'] = 'Data manufaktur tidak lengkap.';
        header('Location: index.php');
        exit();
    }

    $conn->begin_transaction();
    try {
        $noMf = generateManufactureNumber($conn, $tanggal, 'MF');
        $userId = (int)$_SESSION['user_id'];

        $noKeluar = generateTrxNumberByPrefix($conn, 'MFO');
        $noMasuk = generateTrxNumberByPrefix($conn, 'MFI');

        $ketKeluar = 'Manufaktur ' . $noMf . ' - Pemakaian Bahan';
        $ketMasuk = 'Manufaktur ' . $noMf . ' - Hasil Produksi';
        if ($keterangan !== '') {
            $ketKeluar .= ' - ' . $keterangan;
            $ketMasuk .= ' - ' . $keterangan;
        }

        $stmtTrx = $conn->prepare("INSERT INTO transaksi_stok (no_transaksi, tanggal, gudang_id, jenis_transaksi, keterangan, created_by) VALUES (?, ?, ?, ?, ?, ?)");
        if (!$stmtTrx) {
            throw new Exception('Gagal menyiapkan transaksi stok.');
        }
        $jenisKeluar = 'keluar';
        $stmtTrx->bind_param('ssissi', $noKeluar, $tanggal, $gudang_id, $jenisKeluar, $ketKeluar, $userId);
        if (!$stmtTrx->execute()) {
            throw new Exception('Gagal membuat transaksi keluar.');
        }
        $trxKeluarId = (int)$conn->insert_id;

        $jenisMasuk = 'masuk';
        $stmtTrx->bind_param('ssissi', $noMasuk, $tanggal, $gudang_id, $jenisMasuk, $ketMasuk, $userId);
        if (!$stmtTrx->execute()) {
            throw new Exception('Gagal membuat transaksi masuk.');
        }
        $trxMasukId = (int)$conn->insert_id;
        $stmtTrx->close();

        $stmtMf = $conn->prepare("INSERT INTO manufacture (no_manufacture, tanggal, gudang_id, produk_id, qty_hasil, satuan_label, qty_input, keterangan, transaksi_keluar_id, transaksi_masuk_id, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        if (!$stmtMf) {
            throw new Exception('Gagal menyiapkan header manufaktur.');
        }
        $stmtMf->bind_param('ssiiisisiii', $noMf, $tanggal, $gudang_id, $produkId, $qtyHasilBase, $satuanLabel, $qtyHasilInput, $keterangan, $trxKeluarId, $trxMasukId, $userId);
        if (!$stmtMf->execute()) {
            throw new Exception('Gagal menyimpan manufaktur: ' . $stmtMf->error);
        }
        $mfId = (int)$conn->insert_id;
        $stmtMf->close();

        $stmtDetailTrx = $conn->prepare("INSERT INTO detail_transaksi_stok (transaksi_stok_id, barang_id, detail_barang, jumlah) VALUES (?, ?, ?, ?)");
        if (!$stmtDetailTrx) {
            throw new Exception('Gagal menyiapkan detail transaksi.');
        }

        $stmtBahan = $conn->prepare("INSERT INTO manufacture_bahan (manufacture_id, barang_id, detail_barang, qty_pakai, satuan_label, qty_input) VALUES (?, ?, ?, ?, ?, ?)");
        if (!$stmtBahan) {
            throw new Exception('Gagal menyiapkan detail bahan.');
        }

        $stmtStokPick = $conn->prepare("SELECT id, stok_awal, detail_barang FROM gudang_stok WHERE gudang_id = ? AND barang_id = ? ORDER BY id ASC LIMIT 1 FOR UPDATE");
        if (!$stmtStokPick) {
            throw new Exception('Gagal menyiapkan cek stok.');
        }
        $stmtStokUpdate = $conn->prepare("UPDATE gudang_stok SET stok_awal = stok_awal - ? WHERE id = ?");
        if (!$stmtStokUpdate) {
            throw new Exception('Gagal menyiapkan update stok.');
        }

        foreach ($bahan as $item) {
            $barangId = (int)($item['barang_id'] ?? 0);
            $qtyBase = (int)($item['jumlah'] ?? 0);
            $qtyInput = (int)($item['jumlah_input'] ?? $qtyBase);
            $detailBarangInput = trim((string)($item['detail_barang'] ?? ''));
            $satuanItem = trim((string)($item['satuan'] ?? ''));

            if ($barangId <= 0 || $qtyBase <= 0) {
                throw new Exception('Data bahan tidak valid.');
            }

            $stmtStokPick->bind_param('ii', $gudang_id, $barangId);
            if (!$stmtStokPick->execute()) {
                throw new Exception('Gagal cek stok bahan.');
            }
            $stokRes = $stmtStokPick->get_result();
            $stokRow = $stokRes ? $stokRes->fetch_assoc() : null;
            if (!$stokRow) {
                throw new Exception('Stok bahan tidak ditemukan untuk barang ID: ' . $barangId);
            }
            $stokId = (int)$stokRow['id'];
            $stokAwal = (int)$stokRow['stok_awal'];
            $detailBarangDb = (string)($stokRow['detail_barang'] ?? '');

            if ($stokAwal < $qtyBase) {
                throw new Exception('Stok bahan tidak mencukupi untuk barang ID: ' . $barangId);
            }

            $stmtDetailTrx->bind_param('iisi', $trxKeluarId, $barangId, $detailBarangDb, $qtyBase);
            if (!$stmtDetailTrx->execute()) {
                throw new Exception('Gagal simpan detail transaksi keluar.');
            }

            $detailForBahan = $detailBarangInput !== '' ? $detailBarangInput : $detailBarangDb;
            $stmtBahan->bind_param('iisisi', $mfId, $barangId, $detailForBahan, $qtyBase, $satuanItem, $qtyInput);
            if (!$stmtBahan->execute()) {
                throw new Exception('Gagal simpan detail bahan.');
            }

            $stmtStokUpdate->bind_param('ii', $qtyBase, $stokId);
            if (!$stmtStokUpdate->execute()) {
                throw new Exception('Gagal update stok bahan.');
            }
        }

        $stmtStokPickProd = $conn->prepare("SELECT id, detail_barang FROM gudang_stok WHERE gudang_id = ? AND barang_id = ? AND detail_barang = '' LIMIT 1 FOR UPDATE");
        if (!$stmtStokPickProd) {
            throw new Exception('Gagal menyiapkan cek stok hasil.');
        }
        $stmtStokUpdateProd = $conn->prepare("UPDATE gudang_stok SET jumlah = jumlah + ?, stok_awal = stok_awal + ? WHERE id = ?");
        if (!$stmtStokUpdateProd) {
            throw new Exception('Gagal menyiapkan update stok hasil.');
        }
        $stmtStokInsertProd = $conn->prepare("INSERT INTO gudang_stok (gudang_id, barang_id, detail_barang, jumlah, stok_awal, created_by) VALUES (?, ?, '', ?, ?, ?)");
        if (!$stmtStokInsertProd) {
            throw new Exception('Gagal menyiapkan insert stok hasil.');
        }

        $stmtStokPickProd->bind_param('ii', $gudang_id, $produkId);
        if (!$stmtStokPickProd->execute()) {
            throw new Exception('Gagal cek stok hasil.');
        }
        $prodRes = $stmtStokPickProd->get_result();
        $prodRow = $prodRes ? $prodRes->fetch_assoc() : null;
        $detailProd = '';
        if ($prodRow) {
            $prodStokId = (int)$prodRow['id'];
            $detailProd = (string)($prodRow['detail_barang'] ?? '');
            $stmtStokUpdateProd->bind_param('iii', $qtyHasilBase, $qtyHasilBase, $prodStokId);
            if (!$stmtStokUpdateProd->execute()) {
                throw new Exception('Gagal update stok hasil.');
            }
        } else {
            $stmtStokInsertProd->bind_param('iiiii', $gudang_id, $produkId, $qtyHasilBase, $qtyHasilBase, $userId);
            if (!$stmtStokInsertProd->execute()) {
                throw new Exception('Gagal insert stok hasil.');
            }
        }

        $stmtDetailTrx->bind_param('iisi', $trxMasukId, $produkId, $detailProd, $qtyHasilBase);
        if (!$stmtDetailTrx->execute()) {
            throw new Exception('Gagal simpan detail transaksi masuk.');
        }

        $stmtDetailTrx->close();
        $stmtBahan->close();
        $stmtStokPick->close();
        $stmtStokUpdate->close();
        $stmtStokPickProd->close();
        $stmtStokUpdateProd->close();
        $stmtStokInsertProd->close();

        $conn->commit();
        $_SESSION['success'] = 'Manufaktur berhasil disimpan: ' . $noMf;
        header('Location: index.php');
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = 'Gagal menyimpan manufaktur: ' . $e->getMessage();
        header('Location: index.php');
        exit();
    }
}

$mf_rows = [];
$sqlList = "
    SELECT mf.*,
           g.nama_gudang,
           b.kode_barang,
           b.nama_barang
    FROM manufacture mf
    LEFT JOIN gudang g ON mf.gudang_id = g.id
    LEFT JOIN barang b ON mf.produk_id = b.id
    ORDER BY mf.created_at DESC
    LIMIT 50
";
$resList = $conn->query($sqlList);
if ($resList) {
    while ($row = $resList->fetch_assoc()) {
        $mf_rows[] = $row;
    }
}

$mf_bahan = [];
if (!empty($mf_rows)) {
    $ids = array_map(function ($r) { return (int)$r['id']; }, $mf_rows);
    $ids = array_filter($ids, function ($v) { return $v > 0; });
    if (!empty($ids)) {
        $idList = implode(',', $ids);
        $sqlDet = "
            SELECT mb.*,
                   b.kode_barang,
                   b.nama_barang
            FROM manufacture_bahan mb
            INNER JOIN barang b ON mb.barang_id = b.id
            WHERE mb.manufacture_id IN ($idList)
            ORDER BY mb.manufacture_id DESC, mb.id ASC
        ";
        $resDet = $conn->query($sqlDet);
        if ($resDet) {
            while ($row = $resDet->fetch_assoc()) {
                $mid = (int)$row['manufacture_id'];
                if (!isset($mf_bahan[$mid])) $mf_bahan[$mid] = [];
                $mf_bahan[$mid][] = $row;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manufaktur - MINVEN</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet">
</head>
<body>
    <?php include '../templates/navbar.php'; ?>
    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white d-flex align-items-center justify-content-between">
                        <h5 class="mb-0">
                            <i class="bi bi-gear-wide-connected me-2"></i>
                            Manufaktur
                        </h5>
                    </div>
                    <div class="card-body">
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

                        <?php if (checkAccess('manufacture', 'add')): ?>
                        <form method="POST" id="manufactureForm" class="mb-4">
                            <div class="row g-3">
                                <div class="col-md-2">
                                    <label class="form-label">Tanggal</label>
                                    <input type="date" name="tanggal" class="form-control" value="<?= date('Y-m-d') ?>" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Gudang</label>
                                    <select name="gudang_id" id="gudang_id" class="form-select" required>
                                        <option value="">-- Pilih Gudang --</option>
                                        <?php foreach ($gudang_list as $g): ?>
                                            <option value="<?= (int)$g['id'] ?>"><?= htmlspecialchars($g['nama_gudang']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-7">
                                    <label class="form-label">Keterangan</label>
                                    <input type="text" name="keterangan" class="form-control" placeholder="Opsional">
                                </div>
                            </div>

                            <hr class="my-4">

                            <div class="row g-3 align-items-end">
                                <div class="col-md-5">
                                    <label class="form-label">Produk Jadi</label>
                                    <select class="form-select select2" id="produk_id">
                                        <option value="">-- Pilih Produk --</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Satuan</label>
                                    <select class="form-select" id="produk_satuan_id" disabled>
                                        <option value="">-</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Qty Hasil</label>
                                    <input type="number" id="produk_qty" class="form-control" min="1" value="1">
                                </div>
                                <div class="col-md-2">
                                    <button type="button" id="setHasilBtn" class="btn btn-outline-primary w-100">Set Hasil</button>
                                </div>
                            </div>

                            <div class="alert alert-secondary mt-3 mb-0" id="hasilPreview" style="display:none;"></div>

                            <hr class="my-4">

                            <div class="row g-3 align-items-end">
                                <div class="col-md-5">
                                    <label class="form-label">Bahan</label>
                                    <select class="form-select select2" id="bahan_id">
                                        <option value="">-- Pilih Bahan --</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Stok Tersedia</label>
                                    <input type="text" id="bahan_stok" class="form-control" readonly>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Satuan</label>
                                    <select class="form-select" id="bahan_satuan_id" disabled>
                                        <option value="">-</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Qty Pakai</label>
                                    <input type="number" id="bahan_qty" class="form-control" min="1" value="1">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Detail Bahan (Opsional)</label>
                                    <input type="text" id="bahan_detail" class="form-control" placeholder="Contoh: Batch / Catatan">
                                </div>
                                <div class="col-12">
                                    <button type="button" id="addBahanBtn" class="btn btn-success">Tambah Bahan</button>
                                </div>
                            </div>

                            <div class="table-responsive mt-3">
                                <table class="table table-bordered" id="bahanTable">
                                    <thead>
                                        <tr>
                                            <th>Kode</th>
                                            <th>Nama</th>
                                            <th>Detail</th>
                                            <th>Qty</th>
                                            <th>Satuan</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>

                            <input type="hidden" name="hasil_data" id="hasil_data">
                            <input type="hidden" name="bahan_data" id="bahan_data">
                            <button type="submit" class="btn btn-primary" id="submitBtn" disabled>Simpan Manufaktur</button>
                        </form>
                        <?php endif; ?>

                        <div class="table-responsive">
                            <table class="table table-sm table-striped table-bordered">
                                <thead>
                                    <tr>
                                        <th>No</th>
                                        <th>Tanggal</th>
                                        <th>Gudang</th>
                                        <th>Produk</th>
                                        <th>Qty Hasil (Base)</th>
                                        <th>Detail</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($mf_rows)): ?>
                                        <tr><td colspan="6" class="text-center text-muted">Belum ada data manufaktur.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($mf_rows as $m): ?>
                                            <?php $mid = (int)$m['id']; ?>
                                            <tr>
                                                <td><?= htmlspecialchars($m['no_manufacture'] ?? '') ?></td>
                                                <td><?= htmlspecialchars($m['tanggal'] ?? '') ?></td>
                                                <td><?= htmlspecialchars($m['nama_gudang'] ?? '') ?></td>
                                                <td><?= htmlspecialchars(($m['kode_barang'] ?? '') . ' - ' . ($m['nama_barang'] ?? '')) ?></td>
                                                <td><?= htmlspecialchars((string)($m['qty_hasil'] ?? '0')) ?></td>
                                                <td>
                                                    <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#detailModal<?= $mid ?>">Lihat</button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php foreach ($mf_rows as $m): ?>
        <?php $mid = (int)$m['id']; ?>
        <div class="modal fade" id="detailModal<?= $mid ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Detail Manufaktur: <?= htmlspecialchars($m['no_manufacture'] ?? '') ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <div><strong>Produk:</strong> <?= htmlspecialchars(($m['kode_barang'] ?? '') . ' - ' . ($m['nama_barang'] ?? '')) ?></div>
                            <div><strong>Qty Hasil (Base):</strong> <?= htmlspecialchars((string)($m['qty_hasil'] ?? '0')) ?></div>
                            <div><strong>Qty Input:</strong> <?= htmlspecialchars((string)($m['qty_input'] ?? '')) ?> <?= htmlspecialchars($m['satuan_label'] ?? '') ?></div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered">
                                <thead>
                                    <tr>
                                        <th>Kode</th>
                                        <th>Nama</th>
                                        <th>Detail</th>
                                        <th>Qty Pakai (Base)</th>
                                        <th>Qty Input</th>
                                        <th>Satuan</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $details = $mf_bahan[$mid] ?? []; ?>
                                    <?php if (empty($details)): ?>
                                        <tr><td colspan="6" class="text-center text-muted">Tidak ada bahan.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($details as $d): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($d['kode_barang'] ?? '') ?></td>
                                                <td><?= htmlspecialchars($d['nama_barang'] ?? '') ?></td>
                                                <td><?= htmlspecialchars($d['detail_barang'] ?? '') ?></td>
                                                <td><?= htmlspecialchars((string)($d['qty_pakai'] ?? '0')) ?></td>
                                                <td><?= htmlspecialchars((string)($d['qty_input'] ?? '')) ?></td>
                                                <td><?= htmlspecialchars($d['satuan_label'] ?? '') ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const gudangSelect = document.getElementById('gudang_id');
            const produkSelect = document.getElementById('produk_id');
            const produkSatuan = document.getElementById('produk_satuan_id');
            const produkQty = document.getElementById('produk_qty');
            const setHasilBtn = document.getElementById('setHasilBtn');
            const hasilPreview = document.getElementById('hasilPreview');

            const bahanSelect = document.getElementById('bahan_id');
            const bahanStok = document.getElementById('bahan_stok');
            const bahanSatuan = document.getElementById('bahan_satuan_id');
            const bahanQty = document.getElementById('bahan_qty');
            const bahanDetail = document.getElementById('bahan_detail');
            const addBahanBtn = document.getElementById('addBahanBtn');
            const bahanTableBody = document.querySelector('#bahanTable tbody');

            const hasilDataInput = document.getElementById('hasil_data');
            const bahanDataInput = document.getElementById('bahan_data');
            const submitBtn = document.getElementById('submitBtn');

            $('#produk_id').select2({ theme: 'bootstrap-5', width: '100%', placeholder: '-- Pilih Produk --', allowClear: true });
            $('#bahan_id').select2({ theme: 'bootstrap-5', width: '100%', placeholder: '-- Pilih Bahan --', allowClear: true });

            let produkKonversi = [];
            let bahanKonversi = [];
            let hasil = null;
            let bahanItems = [];

            function resetSatuan(sel) {
                sel.innerHTML = '<option value="">-</option>';
                sel.value = '';
                sel.disabled = true;
            }

            function getBaseSatuanId(selectEl) {
                const opt = selectEl.options[selectEl.selectedIndex];
                return parseInt(opt?.getAttribute('data-satuan-id') || '0', 10) || 0;
            }

            function getBaseSatuanName(selectEl) {
                const opt = selectEl.options[selectEl.selectedIndex];
                return opt?.getAttribute('data-satuan') || '';
            }

            function getStokBase(selectEl) {
                const opt = selectEl.options[selectEl.selectedIndex];
                return parseFloat(opt?.getAttribute('data-stok-tersedia') || '0') || 0;
            }

            function getFactorToBase(selectedSatuanId, baseSatuanId, konversiList) {
                if (!selectedSatuanId || !baseSatuanId) return null;
                if (selectedSatuanId === baseSatuanId) return 1;
                for (const k of konversiList) {
                    const asalId = parseInt(k.satuan_asal_id, 10);
                    const tujuanId = parseInt(k.satuan_tujuan_id, 10);
                    const nilai = parseFloat(k.nilai_konversi);
                    if (!nilai || nilai <= 0) continue;
                    if (asalId === selectedSatuanId && tujuanId === baseSatuanId) return nilai;
                    if (asalId === baseSatuanId && tujuanId === selectedSatuanId) return 1 / nilai;
                }
                return null;
            }

            function populateSatuan(selectBarang, selectSatuan, konversiList) {
                const baseId = getBaseSatuanId(selectBarang);
                const baseName = getBaseSatuanName(selectBarang);
                if (!baseId) {
                    resetSatuan(selectSatuan);
                    return;
                }
                const optionsById = new Map();
                optionsById.set(baseId, baseName || 'Satuan');
                for (const k of konversiList) {
                    const asalId = parseInt(k.satuan_asal_id, 10);
                    const tujuanId = parseInt(k.satuan_tujuan_id, 10);
                    if (asalId && asalId !== baseId) {
                        const factor = getFactorToBase(asalId, baseId, konversiList);
                        if (factor) optionsById.set(asalId, k.satuan_asal_nama || '');
                    }
                    if (tujuanId && tujuanId !== baseId) {
                        const factor = getFactorToBase(tujuanId, baseId, konversiList);
                        if (factor) optionsById.set(tujuanId, k.satuan_tujuan_nama || '');
                    }
                }
                selectSatuan.innerHTML = '';
                for (const [id, name] of optionsById.entries()) {
                    const o = document.createElement('option');
                    o.value = String(id);
                    o.textContent = name || String(id);
                    selectSatuan.appendChild(o);
                }
                selectSatuan.value = String(baseId);
                selectSatuan.disabled = optionsById.size <= 1;
            }

            async function loadKonversi(barangId) {
                try {
                    const res = await fetch(`../stok/masuk/get_konversi_barang.php?barang_id=${encodeURIComponent(barangId)}`);
                    const data = await res.json();
                    if (data && data.success && Array.isArray(data.konversi)) return data.konversi;
                } catch (e) {}
                return [];
            }

            async function loadBarangByGudang(gudangId) {
                $('#produk_id').empty().append(new Option('-- Pilih Produk --', '')).val(null).trigger('change');
                $('#bahan_id').empty().append(new Option('-- Pilih Bahan --', '')).val(null).trigger('change');
                resetSatuan(produkSatuan);
                resetSatuan(bahanSatuan);
                bahanStok.value = '';
                bahanKonversi = [];
                produkKonversi = [];
                hasil = null;
                bahanItems = [];
                renderBahan();
                renderHasil();
                if (!gudangId) return;
                try {
                    const res = await fetch(`../stok/keluar/get_barang_by_gudang.php?gudang_id=${encodeURIComponent(gudangId)}`);
                    const data = await res.json();
                    if (data && data.success && Array.isArray(data.barang)) {
                        data.barang.forEach(item => {
                            const opt1 = document.createElement('option');
                            opt1.value = item.id;
                            opt1.setAttribute('data-kode', item.kode_barang);
                            opt1.setAttribute('data-nama', item.nama_barang);
                            opt1.setAttribute('data-satuan-id', item.satuan_id || 0);
                            opt1.setAttribute('data-satuan', item.nama_satuan || '');
                            opt1.setAttribute('data-stok-tersedia', item.stok_tersedia || 0);
                            opt1.textContent = `${item.kode_barang} - ${item.nama_barang}`;
                            produkSelect.appendChild(opt1);

                            const opt2 = opt1.cloneNode(true);
                            opt2.textContent = `${item.kode_barang} - ${item.nama_barang} (Tersedia: ${item.stok_tersedia || 0})`;
                            bahanSelect.appendChild(opt2);
                        });
                        $('#produk_id').trigger('change');
                        $('#bahan_id').trigger('change');
                    }
                } catch (e) {
                    alert('Gagal mengambil data barang.');
                }
            }

            async function onProdukChanged() {
                const opt = produkSelect.options[produkSelect.selectedIndex];
                if (!opt || !opt.value) {
                    resetSatuan(produkSatuan);
                    hasil = null;
                    renderHasil();
                    return;
                }
                produkKonversi = await loadKonversi(opt.value);
                populateSatuan(produkSelect, produkSatuan, produkKonversi);
                hasil = null;
                renderHasil();
            }

            async function onBahanChanged() {
                const opt = bahanSelect.options[bahanSelect.selectedIndex];
                if (!opt || !opt.value) {
                    bahanStok.value = '';
                    resetSatuan(bahanSatuan);
                    bahanKonversi = [];
                    return;
                }
                bahanStok.value = String(getStokBase(bahanSelect));
                bahanKonversi = await loadKonversi(opt.value);
                populateSatuan(bahanSelect, bahanSatuan, bahanKonversi);
            }

            function renderHasil() {
                if (!hasil) {
                    hasilPreview.style.display = 'none';
                    hasilDataInput.value = '';
                } else {
                    hasilPreview.style.display = '';
                    hasilPreview.textContent = `Hasil: ${hasil.kode_barang} - ${hasil.nama_barang} | Qty: ${hasil.jumlah_input} ${hasil.satuan} (Base: ${hasil.jumlah})`;
                    hasilDataInput.value = JSON.stringify(hasil);
                }
                submitBtn.disabled = !hasil || bahanItems.length === 0;
            }

            function renderBahan() {
                bahanTableBody.innerHTML = '';
                bahanItems.forEach((it, idx) => {
                    const tr = bahanTableBody.insertRow();
                    tr.innerHTML = `
                        <td>${it.kode_barang}</td>
                        <td>${it.nama_barang}</td>
                        <td>${it.detail_barang || '-'}</td>
                        <td>${it.jumlah_input ?? it.jumlah}</td>
                        <td>${it.satuan || '-'}</td>
                        <td><button type="button" class="btn btn-danger btn-sm remove-bahan-btn" data-index="${idx}">Hapus</button></td>
                    `;
                });
                bahanDataInput.value = JSON.stringify(bahanItems);
                submitBtn.disabled = !hasil || bahanItems.length === 0;
            }

            setHasilBtn?.addEventListener('click', function() {
                const opt = produkSelect.options[produkSelect.selectedIndex];
                if (!opt || !opt.value) {
                    alert('Pilih produk terlebih dahulu.');
                    return;
                }
                const gudangId = parseInt(gudangSelect.value || '0', 10) || 0;
                if (!gudangId) {
                    alert('Pilih gudang terlebih dahulu.');
                    return;
                }
                const baseSatuanId = getBaseSatuanId(produkSelect);
                const selectedSatuanId = parseInt(produkSatuan.value || '0', 10) || baseSatuanId;
                const factorToBase = getFactorToBase(selectedSatuanId, baseSatuanId, produkKonversi) || 1;
                const qtyInputVal = parseInt(produkQty.value, 10);
                const qtyBase = Math.round((qtyInputVal || 0) * factorToBase);
                if (!qtyInputVal || qtyInputVal <= 0 || qtyBase <= 0) {
                    alert('Qty hasil harus angka positif.');
                    return;
                }
                const satuanLabel = produkSatuan.options[produkSatuan.selectedIndex]?.textContent || '';
                hasil = {
                    barang_id: String(opt.value),
                    kode_barang: opt.getAttribute('data-kode') || '',
                    nama_barang: opt.getAttribute('data-nama') || '',
                    jumlah: qtyBase,
                    jumlah_input: qtyInputVal,
                    satuan_id: selectedSatuanId,
                    satuan: satuanLabel,
                    satuan_base_id: baseSatuanId,
                    satuan_base: getBaseSatuanName(produkSelect),
                    nilai_konversi: factorToBase
                };
                renderHasil();
            });

            addBahanBtn?.addEventListener('click', function() {
                const opt = bahanSelect.options[bahanSelect.selectedIndex];
                if (!opt || !opt.value) {
                    alert('Pilih bahan terlebih dahulu.');
                    return;
                }
                const gudangId = parseInt(gudangSelect.value || '0', 10) || 0;
                if (!gudangId) {
                    alert('Pilih gudang terlebih dahulu.');
                    return;
                }

                const stokBase = getStokBase(bahanSelect);
                const baseSatuanId = getBaseSatuanId(bahanSelect);
                const selectedSatuanId = parseInt(bahanSatuan.value || '0', 10) || baseSatuanId;
                const factorToBase = getFactorToBase(selectedSatuanId, baseSatuanId, bahanKonversi) || 1;

                const qtyInputVal = parseInt(bahanQty.value, 10);
                const qtyBase = Math.round((qtyInputVal || 0) * factorToBase);
                if (!qtyInputVal || qtyInputVal <= 0 || qtyBase <= 0) {
                    alert('Qty bahan harus angka positif.');
                    return;
                }
                if (qtyBase > stokBase) {
                    const availableSelected = Math.floor(stokBase / factorToBase);
                    alert(`Stok tidak mencukupi. Stok tersedia: ${availableSelected}`);
                    return;
                }

                const barangId = String(opt.value);
                const detail = bahanDetail.value.trim();
                const satuanLabel = bahanSatuan.options[bahanSatuan.selectedIndex]?.textContent || '';
                const existingIdx = bahanItems.findIndex(x => x.barang_id === barangId && (x.detail_barang || '') === detail && String(x.satuan_id || '') === String(selectedSatuanId));
                if (existingIdx > -1) {
                    const newTotalBase = bahanItems[existingIdx].jumlah + qtyBase;
                    if (newTotalBase > stokBase) {
                        const availableSelected = Math.floor(stokBase / factorToBase);
                        alert(`Stok tidak mencukupi. Stok tersedia: ${availableSelected}`);
                        return;
                    }
                    bahanItems[existingIdx].jumlah += qtyBase;
                    bahanItems[existingIdx].jumlah_input += qtyInputVal;
                } else {
                    bahanItems.push({
                        barang_id: barangId,
                        kode_barang: opt.getAttribute('data-kode') || '',
                        nama_barang: opt.getAttribute('data-nama') || '',
                        detail_barang: detail,
                        jumlah: qtyBase,
                        jumlah_input: qtyInputVal,
                        satuan_id: selectedSatuanId,
                        satuan: satuanLabel,
                        satuan_base_id: baseSatuanId,
                        satuan_base: getBaseSatuanName(bahanSelect),
                        nilai_konversi: factorToBase
                    });
                }
                renderBahan();
                $('#bahan_id').val(null).trigger('change');
                bahanQty.value = '1';
                bahanDetail.value = '';
                bahanStok.value = '';
                resetSatuan(bahanSatuan);
                bahanKonversi = [];
            });

            bahanTableBody?.addEventListener('click', function(e) {
                if (e.target.classList.contains('remove-bahan-btn')) {
                    const idx = parseInt(e.target.getAttribute('data-index') || '0', 10);
                    bahanItems.splice(idx, 1);
                    renderBahan();
                }
            });

            gudangSelect?.addEventListener('change', function() {
                loadBarangByGudang(this.value);
            });

            produkSelect.addEventListener('change', onProdukChanged);
            $('#produk_id').on('select2:select select2:clear', onProdukChanged);

            bahanSelect.addEventListener('change', onBahanChanged);
            $('#bahan_id').on('select2:select select2:clear', onBahanChanged);

            produkSatuan.addEventListener('change', function() { hasil = null; renderHasil(); });
            bahanSatuan.addEventListener('change', function() { bahanStok.value = String(getStokBase(bahanSelect)); });

            renderBahan();
            renderHasil();
        });
    </script>
</body>
</html>
