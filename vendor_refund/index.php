<?php
session_start();
require_once '../config.php';
require_once '../includes/access_check.php';
require_once '../includes/menu_access_helper.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

if (!checkAccess('vendor_refund', 'view')) {
    $_SESSION['error'] = 'Anda tidak memiliki akses untuk melihat menu refund vendor!';
    header('Location: ../dashboard.php');
    exit();
}

function ensureVendorRefundTables(mysqli $conn): void
{
    $conn->query("
        CREATE TABLE IF NOT EXISTS vendor_refund (
            id INT(11) NOT NULL AUTO_INCREMENT,
            no_refund VARCHAR(30) NOT NULL,
            tanggal DATE NOT NULL,
            purchase_order_id INT(11) DEFAULT NULL,
            gudang_id INT(11) NOT NULL,
            supplier_id INT(11) DEFAULT NULL,
            keterangan TEXT DEFAULT NULL,
            transaksi_stok_id INT(11) DEFAULT NULL,
            created_by INT(11) DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_vendor_refund_no (no_refund),
            KEY idx_vendor_refund_tanggal (tanggal),
            KEY idx_vendor_refund_po (purchase_order_id),
            KEY idx_vendor_refund_gudang (gudang_id),
            KEY idx_vendor_refund_supplier (supplier_id),
            KEY idx_vendor_refund_trx (transaksi_stok_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");

    $conn->query("
        CREATE TABLE IF NOT EXISTS vendor_refund_detail (
            id INT(11) NOT NULL AUTO_INCREMENT,
            vendor_refund_id INT(11) NOT NULL,
            barang_id INT(11) NOT NULL,
            detail_barang VARCHAR(255) NOT NULL DEFAULT '',
            qty INT(11) NOT NULL DEFAULT 0,
            satuan_label VARCHAR(50) DEFAULT NULL,
            qty_input INT(11) DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_vendor_refund_detail_refund (vendor_refund_id),
            KEY idx_vendor_refund_detail_barang (barang_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");

    $res = $conn->query("SHOW COLUMNS FROM vendor_refund LIKE 'purchase_order_id'");
    if ($res instanceof mysqli_result) {
        $exists = $res->num_rows > 0;
        $res->free();
        if (!$exists) {
            $conn->query("ALTER TABLE vendor_refund ADD COLUMN purchase_order_id INT(11) DEFAULT NULL AFTER tanggal");
            $conn->query("ALTER TABLE vendor_refund ADD KEY idx_vendor_refund_po (purchase_order_id)");
        }
    }
}

function generateVendorRefundNumber(mysqli $conn, string $prefix = 'RV'): string
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

ensureVendorRefundTables($conn);

if (isset($_GET['ajax'])) {
    header('Content-Type: application/json; charset=utf-8');

    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit();
    }

    if (!checkAccess('vendor_refund', 'view')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Forbidden']);
        exit();
    }

    $ajax = (string)$_GET['ajax'];

    if ($ajax === 'stok_tersedia') {
        $gudangId = isset($_GET['gudang_id']) ? (int)$_GET['gudang_id'] : 0;
        $barangId = isset($_GET['barang_id']) ? (int)$_GET['barang_id'] : 0;
        $detailBarang = isset($_GET['detail_barang']) ? trim((string)$_GET['detail_barang']) : '';

        if ($gudangId <= 0 || $barangId <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Parameter tidak valid']);
            exit();
        }

        if ($detailBarang !== '') {
            $stmt = $conn->prepare("SELECT COALESCE(SUM(stok_awal - stok_terpakai), 0) AS stok_tersedia FROM gudang_stok WHERE gudang_id = ? AND barang_id = ? AND detail_barang = ?");
            if (!$stmt) {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Database error']);
                exit();
            }
            $stmt->bind_param('iis', $gudangId, $barangId, $detailBarang);
        } else {
            $stmt = $conn->prepare("SELECT COALESCE(SUM(stok_awal - stok_terpakai), 0) AS stok_tersedia FROM gudang_stok WHERE gudang_id = ? AND barang_id = ?");
            if (!$stmt) {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Database error']);
                exit();
            }
            $stmt->bind_param('ii', $gudangId, $barangId);
        }

        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();

        echo json_encode([
            'success' => true,
            'stok_tersedia' => (int)($row['stok_tersedia'] ?? 0),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit();
    }

    if ($ajax === 'refund_wa') {
        if (!checkAccess('vendor_refund', 'send_wa')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Forbidden']);
            exit();
        }

        $refundId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if ($refundId <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'ID tidak valid']);
            exit();
        }

        $stmtH = $conn->prepare("
            SELECT vr.no_refund, vr.tanggal, vr.keterangan, vr.gudang_id, vr.supplier_id, vr.purchase_order_id,
                   g.nama_gudang,
                   s.nama_supplier, s.telepon,
                   po.no_po
            FROM vendor_refund vr
            LEFT JOIN gudang g ON vr.gudang_id = g.id
            LEFT JOIN supplier s ON vr.supplier_id = s.id
            LEFT JOIN purchase_order po ON vr.purchase_order_id = po.id
            WHERE vr.id = ?
            LIMIT 1
        ");
        if (!$stmtH) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Database error']);
            exit();
        }
        $stmtH->bind_param('i', $refundId);
        $stmtH->execute();
        $hRes = $stmtH->get_result();
        $header = $hRes ? $hRes->fetch_assoc() : null;
        $stmtH->close();

        if (!$header) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Data refund tidak ditemukan']);
            exit();
        }

        $telepon = trim((string)($header['telepon'] ?? ''));
        if ($telepon === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Nomor telepon supplier tidak ditemukan']);
            exit();
        }

        $stmtD = $conn->prepare("
            SELECT vrd.qty, vrd.qty_input, vrd.satuan_label, vrd.detail_barang,
                   b.kode_barang, b.nama_barang
            FROM vendor_refund_detail vrd
            INNER JOIN barang b ON vrd.barang_id = b.id
            WHERE vrd.vendor_refund_id = ?
            ORDER BY vrd.id ASC
        ");
        if (!$stmtD) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Database error']);
            exit();
        }
        $stmtD->bind_param('i', $refundId);
        $stmtD->execute();
        $dRes = $stmtD->get_result();
        $details = [];
        if ($dRes) {
            while ($d = $dRes->fetch_assoc()) {
                $details[] = $d;
            }
        }
        $stmtD->close();

        $lines = [];
        $lines[] = "Refund Vendor: " . (string)($header['no_refund'] ?? '');
        $lines[] = "Tanggal: " . (string)($header['tanggal'] ?? '');
        if (!empty($header['no_po'])) {
            $lines[] = "PO: " . (string)$header['no_po'];
        }
        if (!empty($header['nama_gudang'])) {
            $lines[] = "Gudang: " . (string)$header['nama_gudang'];
        }
        if (!empty($header['nama_supplier'])) {
            $lines[] = "Supplier: " . (string)$header['nama_supplier'];
        }
        $lines[] = "";
        $lines[] = "Item Refund:";
        if (empty($details)) {
            $lines[] = "- (tidak ada detail)";
        } else {
            foreach ($details as $d) {
                $qty = (string)($d['qty_input'] ?? $d['qty'] ?? '0');
                $satuan = trim((string)($d['satuan_label'] ?? ''));
                $detailBarang = trim((string)($d['detail_barang'] ?? ''));
                $kode = trim((string)($d['kode_barang'] ?? ''));
                $nama = trim((string)($d['nama_barang'] ?? ''));
                $line = "- " . ($kode !== '' ? ($kode . " - ") : "") . $nama . " x" . $qty;
                if ($satuan !== '') $line .= " " . $satuan;
                if ($detailBarang !== '') $line .= " (" . $detailBarang . ")";
                $lines[] = $line;
            }
        }
        $ket = trim((string)($header['keterangan'] ?? ''));
        if ($ket !== '') {
            $lines[] = "";
            $lines[] = "Keterangan: " . $ket;
        }

        echo json_encode([
            'success' => true,
            'telepon' => $telepon,
            'message' => implode("\n", $lines),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit();
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Unknown ajax action']);
    exit();
}

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

$supplier_list = [];
$resSup = $conn->query("SELECT id, nama_supplier, kode_supplier FROM supplier ORDER BY nama_supplier");
if ($resSup) {
    while ($row = $resSup->fetch_assoc()) {
        $supplier_list[] = $row;
    }
}

$selected_po_id = isset($_GET['po_id']) ? (int)$_GET['po_id'] : 0;
$po_list = [];
$sqlPoList = "
    SELECT po.id, po.no_po, po.tanggal, po.supplier_id,
           s.nama_supplier, s.telepon
    FROM purchase_order po
    LEFT JOIN supplier s ON po.supplier_id = s.id
    WHERE po.status = 'completed'
    ORDER BY COALESCE(po.completed_at, po.tanggal) DESC, po.id DESC
    LIMIT 200
";
$resPoList = $conn->query($sqlPoList);
if ($resPoList) {
    while ($row = $resPoList->fetch_assoc()) {
        $po_list[] = $row;
    }
}

$wa_config_res = $conn->query("SELECT * FROM setup_whatsapp WHERE id = 1");
$wa_config = $wa_config_res ? $wa_config_res->fetch_assoc() : null;
$wa_active = $wa_config && $wa_config['is_active'] == 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!checkAccess('vendor_refund', 'add')) {
        $_SESSION['error'] = 'Anda tidak memiliki akses untuk menambah refund vendor!';
        header('Location: index.php');
        exit();
    }

    $tanggal = $_POST['tanggal'] ?? date('Y-m-d');
    $purchase_order_id = (int)($_POST['po_id'] ?? 0);
    $gudang_id = (int)($_POST['gudang_id'] ?? 0);
    $supplier_id = (int)($_POST['supplier_id'] ?? 0);
    $keterangan = trim((string)($_POST['keterangan'] ?? ''));
    $items_data_json = $_POST['items_data'] ?? '[]';
    $items = json_decode($items_data_json, true);

    if ($purchase_order_id <= 0 || $gudang_id <= 0 || !is_array($items) || count($items) === 0) {
        $_SESSION['error'] = 'Data refund tidak lengkap atau tidak ada item.';
        header('Location: index.php');
        exit();
    }

    $poNo = '';
    $poSupplierId = 0;
    $stmtPo = $conn->prepare("SELECT id, no_po, supplier_id, status FROM purchase_order WHERE id = ? LIMIT 1");
    if ($stmtPo) {
        $stmtPo->bind_param('i', $purchase_order_id);
        $stmtPo->execute();
        $poRes = $stmtPo->get_result();
        $poRow = $poRes ? $poRes->fetch_assoc() : null;
        $stmtPo->close();
        if (!$poRow) {
            $_SESSION['error'] = 'PO tidak ditemukan.';
            header('Location: index.php');
            exit();
        }
        if ((string)($poRow['status'] ?? '') !== 'completed') {
            $_SESSION['error'] = 'PO harus dalam status completed.';
            header('Location: index.php');
            exit();
        }
        $poNo = (string)($poRow['no_po'] ?? '');
        $poSupplierId = (int)($poRow['supplier_id'] ?? 0);
    } else {
        $_SESSION['error'] = 'Gagal memuat data PO.';
        header('Location: index.php');
        exit();
    }

    if ($supplier_id <= 0 && $poSupplierId > 0) {
        $supplier_id = $poSupplierId;
    }

    $conn->begin_transaction();
    try {
        $noRefund = generateVendorRefundNumber($conn, 'RV');
        $userId = (int)$_SESSION['user_id'];

        $trxKeterangan = 'Refund Vendor ' . $noRefund;
        if ($poNo !== '') {
            $trxKeterangan .= ' (PO ' . $poNo . ')';
        }
        if ($keterangan !== '') {
            $trxKeterangan .= ' - ' . $keterangan;
        }

        $stmtTrx = $conn->prepare("INSERT INTO transaksi_stok (no_transaksi, tanggal, gudang_id, jenis_transaksi, keterangan, created_by) VALUES (?, ?, ?, 'keluar', ?, ?)");
        if (!$stmtTrx) {
            throw new Exception('Gagal menyiapkan transaksi stok.');
        }
        $stmtTrx->bind_param('ssisi', $noRefund, $tanggal, $gudang_id, $trxKeterangan, $userId);
        if (!$stmtTrx->execute()) {
            throw new Exception('Gagal membuat transaksi stok: ' . $stmtTrx->error);
        }
        $transaksiStokId = (int)$conn->insert_id;
        $stmtTrx->close();

        $stmtRefund = $conn->prepare("INSERT INTO vendor_refund (no_refund, tanggal, purchase_order_id, gudang_id, supplier_id, keterangan, transaksi_stok_id, created_by) VALUES (?, ?, ?, ?, NULLIF(?, 0), ?, ?, ?)");
        if (!$stmtRefund) {
            throw new Exception('Gagal menyiapkan header refund.');
        }
        $stmtRefund->bind_param('ssiiisii', $noRefund, $tanggal, $purchase_order_id, $gudang_id, $supplier_id, $keterangan, $transaksiStokId, $userId);
        if (!$stmtRefund->execute()) {
            throw new Exception('Gagal menyimpan refund: ' . $stmtRefund->error);
        }
        $vendorRefundId = (int)$conn->insert_id;
        $stmtRefund->close();

        $stmtDetailTrx = $conn->prepare("INSERT INTO detail_transaksi_stok (transaksi_stok_id, barang_id, detail_barang, jumlah) VALUES (?, ?, ?, ?)");
        if (!$stmtDetailTrx) {
            throw new Exception('Gagal menyiapkan detail transaksi.');
        }

        $stmtDetailRefund = $conn->prepare("INSERT INTO vendor_refund_detail (vendor_refund_id, barang_id, detail_barang, qty, satuan_label, qty_input) VALUES (?, ?, ?, ?, ?, ?)");
        if (!$stmtDetailRefund) {
            throw new Exception('Gagal menyiapkan detail refund.');
        }

        $stmtStokWithDetail = $conn->prepare("SELECT id, stok_awal, stok_terpakai, detail_barang FROM gudang_stok WHERE gudang_id = ? AND barang_id = ? AND detail_barang = ? ORDER BY id ASC FOR UPDATE");
        if (!$stmtStokWithDetail) {
            throw new Exception('Gagal menyiapkan cek stok (detail).');
        }
        $stmtStokAny = $conn->prepare("SELECT id, stok_awal, stok_terpakai, detail_barang FROM gudang_stok WHERE gudang_id = ? AND barang_id = ? ORDER BY id ASC FOR UPDATE");
        if (!$stmtStokAny) {
            throw new Exception('Gagal menyiapkan cek stok.');
        }
        $stmtStokUpdate = $conn->prepare("UPDATE gudang_stok SET stok_awal = stok_awal - ? WHERE id = ?");
        if (!$stmtStokUpdate) {
            throw new Exception('Gagal menyiapkan update stok.');
        }

        foreach ($items as $item) {
            $barangId = (int)($item['barang_id'] ?? 0);
            $qtyBase = (int)($item['jumlah'] ?? 0);
            $qtyInput = (int)($item['jumlah_input'] ?? $qtyBase);
            $detailBarangInput = trim((string)($item['detail_barang'] ?? ''));
            $satuanLabel = trim((string)($item['satuan'] ?? ''));

            if ($barangId <= 0 || $qtyBase <= 0) {
                throw new Exception('Item tidak valid.');
            }

            if ($detailBarangInput !== '') {
                $stmtStokWithDetail->bind_param('iis', $gudang_id, $barangId, $detailBarangInput);
                if (!$stmtStokWithDetail->execute()) {
                    throw new Exception('Gagal cek stok.');
                }
                $stokRes = $stmtStokWithDetail->get_result();
            } else {
                $stmtStokAny->bind_param('ii', $gudang_id, $barangId);
                if (!$stmtStokAny->execute()) {
                    throw new Exception('Gagal cek stok.');
                }
                $stokRes = $stmtStokAny->get_result();
            }

            $stokRows = [];
            if ($stokRes) {
                while ($sr = $stokRes->fetch_assoc()) {
                    $stokRows[] = $sr;
                }
            }
            if (empty($stokRows)) {
                throw new Exception('Stok tidak ditemukan untuk barang ID: ' . $barangId);
            }

            $remaining = $qtyBase;
            $allocations = [];
            foreach ($stokRows as $sr) {
                $stokId = (int)$sr['id'];
                $stokAwal = (int)$sr['stok_awal'];
                $stokTerpakai = (int)($sr['stok_terpakai'] ?? 0);
                $detailBarangDb = (string)($sr['detail_barang'] ?? '');
                $available = $stokAwal - $stokTerpakai;
                if ($available <= 0) {
                    continue;
                }
                $take = min($available, $remaining);
                if ($take <= 0) {
                    continue;
                }

                $stmtStokUpdate->bind_param('ii', $take, $stokId);
                if (!$stmtStokUpdate->execute()) {
                    throw new Exception('Gagal update stok.');
                }

                $stmtDetailTrx->bind_param('iisi', $transaksiStokId, $barangId, $detailBarangDb, $take);
                if (!$stmtDetailTrx->execute()) {
                    throw new Exception('Gagal simpan detail transaksi.');
                }

                $allocations[] = ['detail_barang' => $detailBarangDb, 'qty' => $take];
                $remaining -= $take;
                if ($remaining <= 0) {
                    break;
                }
            }
            if ($remaining > 0) {
                throw new Exception('Stok tidak mencukupi untuk barang ID: ' . $barangId);
            }

            if ($detailBarangInput !== '') {
                $stmtDetailRefund->bind_param('iisisi', $vendorRefundId, $barangId, $detailBarangInput, $qtyBase, $satuanLabel, $qtyInput);
                if (!$stmtDetailRefund->execute()) {
                    throw new Exception('Gagal simpan detail refund.');
                }
            } else {
                foreach ($allocations as $a) {
                    $detailBarangDb = (string)($a['detail_barang'] ?? '');
                    $qtyAlloc = (int)($a['qty'] ?? 0);
                    if ($qtyAlloc <= 0) continue;
                    $stmtDetailRefund->bind_param('iisisi', $vendorRefundId, $barangId, $detailBarangDb, $qtyAlloc, $satuanLabel, $qtyAlloc);
                    if (!$stmtDetailRefund->execute()) {
                        throw new Exception('Gagal simpan detail refund.');
                    }
                }
            }
        }

        $stmtDetailTrx->close();
        $stmtDetailRefund->close();
        $stmtStokWithDetail->close();
        $stmtStokAny->close();
        $stmtStokUpdate->close();

        $conn->commit();
        $_SESSION['success'] = 'Refund vendor berhasil disimpan: ' . $noRefund;
        header('Location: index.php');
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = 'Gagal menyimpan refund vendor: ' . $e->getMessage();
        header('Location: index.php');
        exit();
    }
}

$refund_rows = [];
$sqlList = "
    SELECT vr.*,
           g.nama_gudang,
           s.nama_supplier,
           s.kode_supplier,
           po.no_po
    FROM vendor_refund vr
    LEFT JOIN gudang g ON vr.gudang_id = g.id
    LEFT JOIN supplier s ON vr.supplier_id = s.id
    LEFT JOIN purchase_order po ON vr.purchase_order_id = po.id
    ORDER BY vr.created_at DESC
    LIMIT 50
";
$resList = $conn->query($sqlList);
if ($resList) {
    while ($row = $resList->fetch_assoc()) {
        $refund_rows[] = $row;
    }
}

$refund_details = [];
if (!empty($refund_rows)) {
    $ids = array_map(function ($r) { return (int)$r['id']; }, $refund_rows);
    $ids = array_filter($ids, function ($v) { return $v > 0; });
    if (!empty($ids)) {
        $idList = implode(',', $ids);
        $sqlDet = "
            SELECT vrd.*,
                   b.kode_barang,
                   b.nama_barang
            FROM vendor_refund_detail vrd
            INNER JOIN barang b ON vrd.barang_id = b.id
            WHERE vrd.vendor_refund_id IN ($idList)
            ORDER BY vrd.vendor_refund_id DESC, vrd.id ASC
        ";
        $resDet = $conn->query($sqlDet);
        if ($resDet) {
            while ($row = $resDet->fetch_assoc()) {
                $rid = (int)$row['vendor_refund_id'];
                if (!isset($refund_details[$rid])) $refund_details[$rid] = [];
                $refund_details[$rid][] = $row;
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
    <title>Refund Vendor - MINVEN</title>
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
                            <i class="bi bi-arrow-return-left me-2"></i>
                            Refund Vendor
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

                        <?php if (checkAccess('vendor_refund', 'add')): ?>
                        <form method="POST" id="vendorRefundForm" class="mb-4">
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
                                <div class="col-md-4">
                                    <label class="form-label">PO (Completed)</label>
                                    <select name="po_id" class="form-select select2" id="po_id" required>
                                        <option value="">-- Pilih PO --</option>
                                        <?php foreach ($po_list as $po): ?>
                                            <?php $pid = (int)($po['id'] ?? 0); ?>
                                            <option value="<?= $pid ?>"
                                                data-supplier-id="<?= (int)($po['supplier_id'] ?? 0) ?>"
                                                data-supplier-name="<?= htmlspecialchars((string)($po['nama_supplier'] ?? '')) ?>"
                                                data-supplier-phone="<?= htmlspecialchars((string)($po['telepon'] ?? '')) ?>"
                                                <?= ($selected_po_id > 0 && $selected_po_id === $pid) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars((string)($po['no_po'] ?? '') . ' - ' . (string)($po['nama_supplier'] ?? '') . ' (' . (string)($po['tanggal'] ?? '') . ')') ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Supplier</label>
                                    <select name="supplier_id" class="form-select select2" id="supplier_id">
                                        <option value="">-- Opsional --</option>
                                        <?php foreach ($supplier_list as $s): ?>
                                            <option value="<?= (int)$s['id'] ?>"><?= htmlspecialchars(($s['kode_supplier'] ?? '') . ' - ' . ($s['nama_supplier'] ?? '')) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label">Keterangan</label>
                                    <input type="text" name="keterangan" class="form-control" placeholder="Opsional">
                                </div>
                            </div>

                            <hr class="my-4">

                            <div class="row g-3 align-items-end">
                                <div class="col-md-4">
                                    <label class="form-label">Item PO</label>
                                    <select class="form-select select2" id="select_barang_id">
                                        <option value="">-- Pilih Item PO --</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Stok Tersedia</label>
                                    <input type="text" id="display_stok_tersedia" class="form-control" readonly>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Satuan</label>
                                    <select class="form-select" id="select_satuan_id" disabled>
                                        <option value="">-</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Qty</label>
                                    <input type="number" id="input_jumlah" class="form-control" min="1" value="1">
                                </div>
                                <div class="col-md-2">
                                    <button type="button" id="addItemBtn" class="btn btn-success w-100">Tambah</button>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Detail Barang (Opsional)</label>
                                    <input type="text" id="input_detail_barang" class="form-control" placeholder="Contoh: Batch / Catatan">
                                </div>
                            </div>

                            <div class="table-responsive mt-3">
                                <table class="table table-bordered" id="itemsTable">
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

                            <input type="hidden" name="items_data" id="items_data">
                            <button type="submit" class="btn btn-primary" id="submitBtn" disabled>Simpan Refund Vendor</button>
                        </form>
                        <?php endif; ?>

                        <div class="table-responsive">
                            <table class="table table-sm table-striped table-bordered">
                                <thead>
                                    <tr>
                                        <th>No</th>
                                        <th>Tanggal</th>
                                        <th>PO</th>
                                        <th>Gudang</th>
                                        <th>Supplier</th>
                                        <th>Keterangan</th>
                                        <th>Detail</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($refund_rows)): ?>
                                        <tr><td colspan="8" class="text-center text-muted">Belum ada data refund.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($refund_rows as $r): ?>
                                            <?php $rid = (int)$r['id']; ?>
                                            <tr>
                                                <td><?= htmlspecialchars($r['no_refund'] ?? '') ?></td>
                                                <td><?= htmlspecialchars($r['tanggal'] ?? '') ?></td>
                                                <td><?= htmlspecialchars($r['no_po'] ?? '-') ?></td>
                                                <td><?= htmlspecialchars($r['nama_gudang'] ?? '') ?></td>
                                                <td><?= htmlspecialchars(($r['kode_supplier'] ?? '') . ' - ' . ($r['nama_supplier'] ?? '')) ?></td>
                                                <td><?= htmlspecialchars($r['keterangan'] ?? '') ?></td>
                                                <td>
                                                    <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#detailModal<?= $rid ?>">
                                                        Lihat
                                                    </button>
                                                </td>
                                                <td>
                                                    <?php if ($wa_active && checkAccess('vendor_refund', 'send_wa')): ?>
                                                        <button type="button" class="btn btn-sm btn-success btn-send-wa" data-id="<?= $rid ?>" title="Kirim WhatsApp">
                                                            <i class="bi bi-whatsapp"></i>
                                                        </button>
                                                    <?php else: ?>
                                                        -
                                                    <?php endif; ?>
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

    <?php foreach ($refund_rows as $r): ?>
        <?php $rid = (int)$r['id']; ?>
        <div class="modal fade" id="detailModal<?= $rid ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Detail Refund: <?= htmlspecialchars($r['no_refund'] ?? '') ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered">
                                <thead>
                                    <tr>
                                        <th>Kode</th>
                                        <th>Nama</th>
                                        <th>Detail</th>
                                        <th>Qty (Base)</th>
                                        <th>Qty Input</th>
                                        <th>Satuan</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $details = $refund_details[$rid] ?? []; ?>
                                    <?php if (empty($details)): ?>
                                        <tr><td colspan="6" class="text-center text-muted">Tidak ada detail.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($details as $d): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($d['kode_barang'] ?? '') ?></td>
                                                <td><?= htmlspecialchars($d['nama_barang'] ?? '') ?></td>
                                                <td><?= htmlspecialchars($d['detail_barang'] ?? '') ?></td>
                                                <td><?= htmlspecialchars((string)($d['qty'] ?? '0')) ?></td>
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
            const poSelect = document.getElementById('po_id');
            const supplierSelect = document.getElementById('supplier_id');
            const barangSelect = document.getElementById('select_barang_id');
            const satuanSelect = document.getElementById('select_satuan_id');
            const stokDisplay = document.getElementById('display_stok_tersedia');
            const qtyInput = document.getElementById('input_jumlah');
            const detailInput = document.getElementById('input_detail_barang');
            const addBtn = document.getElementById('addItemBtn');
            const itemsTableBody = document.querySelector('#itemsTable tbody');
            const itemsDataInput = document.getElementById('items_data');
            const submitBtn = document.getElementById('submitBtn');

            if (supplierSelect) {
                $('#supplier_id').select2({ theme: 'bootstrap-5', width: '100%', placeholder: '-- Opsional --', allowClear: true });
            }
            if (poSelect) {
                $('#po_id').select2({ theme: 'bootstrap-5', width: '100%', placeholder: '-- Pilih PO --', allowClear: true });
            }
            if (barangSelect) {
                $('#select_barang_id').select2({ theme: 'bootstrap-5', width: '100%', placeholder: '-- Pilih Item PO --', allowClear: true });
            }

            let items = [];
            let currentKonversi = [];
            let currentStockBase = 0;

            function resetSatuan() {
                if (!satuanSelect) return;
                satuanSelect.innerHTML = '<option value="">-</option>';
                satuanSelect.value = '';
                satuanSelect.disabled = true;
            }

            function getBaseSatuanId() {
                const opt = barangSelect.options[barangSelect.selectedIndex];
                return parseInt(opt?.getAttribute('data-satuan-id') || '0', 10) || 0;
            }

            function getBaseSatuanName() {
                const opt = barangSelect.options[barangSelect.selectedIndex];
                return opt?.getAttribute('data-satuan') || '';
            }

            function getStokBase() {
                return currentStockBase || 0;
            }

            function getFactorToBase(selectedSatuanId, baseSatuanId) {
                if (!selectedSatuanId || !baseSatuanId) return null;
                if (selectedSatuanId === baseSatuanId) return 1;
                for (const k of currentKonversi) {
                    const asalId = parseInt(k.satuan_asal_id, 10);
                    const tujuanId = parseInt(k.satuan_tujuan_id, 10);
                    const nilai = parseFloat(k.nilai_konversi);
                    if (!nilai || nilai <= 0) continue;
                    if (asalId === selectedSatuanId && tujuanId === baseSatuanId) return nilai;
                    if (asalId === baseSatuanId && tujuanId === selectedSatuanId) return 1 / nilai;
                }
                return null;
            }

            function populateSatuan() {
                const baseId = getBaseSatuanId();
                const baseName = getBaseSatuanName();
                if (!baseId) {
                    if (satuanSelect) {
                        satuanSelect.innerHTML = `<option value="">${baseName ? baseName : '-'}</option>`;
                        satuanSelect.value = '';
                        satuanSelect.disabled = true;
                    } else {
                        resetSatuan();
                    }
                    return;
                }
                const optionsById = new Map();
                optionsById.set(baseId, baseName || 'Satuan');
                for (const k of currentKonversi) {
                    const asalId = parseInt(k.satuan_asal_id, 10);
                    const tujuanId = parseInt(k.satuan_tujuan_id, 10);
                    if (asalId && asalId !== baseId) {
                        const factor = getFactorToBase(asalId, baseId);
                        if (factor) optionsById.set(asalId, k.satuan_asal_nama || '');
                    }
                    if (tujuanId && tujuanId !== baseId) {
                        const factor = getFactorToBase(tujuanId, baseId);
                        if (factor) optionsById.set(tujuanId, k.satuan_tujuan_nama || '');
                    }
                }
                satuanSelect.innerHTML = '';
                for (const [id, name] of optionsById.entries()) {
                    const o = document.createElement('option');
                    o.value = String(id);
                    o.textContent = name || String(id);
                    satuanSelect.appendChild(o);
                }
                satuanSelect.value = String(baseId);
                satuanSelect.disabled = optionsById.size <= 1;
            }

            async function loadKonversi(barangId) {
                try {
                    const res = await fetch(`../stok/masuk/get_konversi_barang.php?barang_id=${encodeURIComponent(barangId)}`);
                    const data = await res.json();
                    if (data && data.success && Array.isArray(data.konversi)) return data.konversi;
                } catch (e) {}
                return [];
            }

            async function onBarangChanged() {
                const opt = barangSelect.options[barangSelect.selectedIndex];
                if (!opt || !opt.value) {
                    currentStockBase = 0;
                    stokDisplay.value = '';
                    resetSatuan();
                    return;
                }
                const barangId = parseInt(opt.getAttribute('data-barang-id') || '0', 10) || 0;
                const qtyPo = parseInt(opt.getAttribute('data-qty-po') || '0', 10) || 0;
                const poDetail = (opt.getAttribute('data-detail') || '').trim();
                const gudangId = parseInt(gudangSelect?.value || '0', 10) || 0;

                if (detailInput && detailInput.value.trim() === '' && poDetail !== '') {
                    detailInput.value = poDetail;
                }
                if (qtyInput && (!qtyInput.value || qtyInput.value === '1') && qtyPo > 0) {
                    qtyInput.value = String(qtyPo);
                }

                currentKonversi = barangId ? await loadKonversi(barangId) : [];
                populateSatuan();

                currentStockBase = 0;
                stokDisplay.value = '';
                if (gudangId > 0 && barangId > 0) {
                    try {
                        const detail = detailInput ? detailInput.value.trim() : '';
                        const res = await fetch(`index.php?ajax=stok_tersedia&gudang_id=${encodeURIComponent(gudangId)}&barang_id=${encodeURIComponent(barangId)}&detail_barang=${encodeURIComponent(detail)}`);
                        const data = await res.json();
                        if (data && data.success) {
                            currentStockBase = parseInt(data.stok_tersedia || '0', 10) || 0;
                            stokDisplay.value = String(currentStockBase);
                        }
                    } catch (e) {}
                }
            }

            function renderItems() {
                itemsTableBody.innerHTML = '';
                items.forEach((it, idx) => {
                    const tr = itemsTableBody.insertRow();
                    tr.innerHTML = `
                        <td>${it.kode_barang}</td>
                        <td>${it.nama_barang}</td>
                        <td>${it.detail_barang || '-'}</td>
                        <td>${it.jumlah_input ?? it.jumlah}</td>
                        <td>${it.satuan || '-'}</td>
                        <td><button type="button" class="btn btn-danger btn-sm remove-item-btn" data-index="${idx}">Hapus</button></td>
                    `;
                });
                itemsDataInput.value = JSON.stringify(items);
                submitBtn.disabled = items.length === 0;
            }

            function clearItemInputs() {
                $('#select_barang_id').val(null).trigger('change');
                qtyInput.value = '1';
                detailInput.value = '';
                stokDisplay.value = '';
                resetSatuan();
            }

            async function loadItemsByPo(poId) {
                $('#select_barang_id').empty().append(new Option('-- Pilih Item PO --', '')).val(null).trigger('change');
                currentStockBase = 0;
                stokDisplay.value = '';
                qtyInput.value = '1';
                detailInput.value = '';
                resetSatuan();
                currentKonversi = [];
                if (!poId) return;
                try {
                    const res = await fetch(`../pembelian/po/get_po_items.php?id=${encodeURIComponent(poId)}`);
                    const data = await res.json();
                    if (data && data.status === 'success' && Array.isArray(data.items)) {
                        data.items.forEach(item => {
                            const opt = document.createElement('option');
                            opt.value = String(item.detail_id || '');
                            opt.setAttribute('data-barang-id', String(item.barang_id || ''));
                            opt.setAttribute('data-kode', item.kode_barang || '');
                            opt.setAttribute('data-nama', item.nama_barang || '');
                            opt.setAttribute('data-satuan', item.satuan || '');
                            opt.setAttribute('data-qty-po', String(item.jumlah || 0));
                            opt.setAttribute('data-detail', item.keterangan_detail || '');
                            opt.textContent = `${item.kode_barang || ''} - ${item.nama_barang || ''} (Qty PO: ${item.jumlah || 0})`;
                            barangSelect.appendChild(opt);
                        });
                        $('#select_barang_id').trigger('change');
                    }
                } catch (e) {
                    alert('Gagal mengambil item PO.');
                }
            }

            if (gudangSelect) {
                gudangSelect.addEventListener('change', function() {
                    items = [];
                    renderItems();
                    onBarangChanged();
                });
            }

            if (poSelect) {
                poSelect.addEventListener('change', function() {
                    items = [];
                    renderItems();
                    const opt = poSelect.options[poSelect.selectedIndex];
                    const supplierId = opt ? (opt.getAttribute('data-supplier-id') || '') : '';
                    if (supplierId && supplierSelect) {
                        $('#supplier_id').val(supplierId).trigger('change');
                    }
                    loadItemsByPo(this.value);
                });
                if (poSelect.value) {
                    const opt = poSelect.options[poSelect.selectedIndex];
                    const supplierId = opt ? (opt.getAttribute('data-supplier-id') || '') : '';
                    if (supplierId && supplierSelect) {
                        $('#supplier_id').val(supplierId).trigger('change');
                    }
                    loadItemsByPo(poSelect.value);
                }
            }

            if (barangSelect) {
                barangSelect.addEventListener('change', onBarangChanged);
                $('#select_barang_id').on('select2:select select2:clear', onBarangChanged);
            }
            if (detailInput) {
                detailInput.addEventListener('input', function() {
                    onBarangChanged();
                });
            }

            addBtn?.addEventListener('click', function() {
                const opt = barangSelect.options[barangSelect.selectedIndex];
                if (!opt || !opt.value) {
                    alert('Pilih item PO terlebih dahulu.');
                    return;
                }
                const poId = parseInt(poSelect?.value || '0', 10) || 0;
                if (!poId) {
                    alert('Pilih PO terlebih dahulu.');
                    return;
                }
                const gudangId = parseInt(gudangSelect.value || '0', 10) || 0;
                if (!gudangId) {
                    alert('Pilih gudang terlebih dahulu.');
                    return;
                }

                const jumlahInput = parseInt(qtyInput.value, 10);
                const jumlahBase = jumlahInput || 0;
                if (!jumlahInput || jumlahInput <= 0) {
                    alert('Qty harus angka positif.');
                    return;
                }

                const qtyPo = parseInt(opt.getAttribute('data-qty-po') || '0', 10) || 0;
                if (qtyPo > 0 && jumlahBase > qtyPo) {
                    alert(`Qty refund melebihi Qty PO. Maksimal: ${qtyPo}`);
                    return;
                }

                const stokBase = getStokBase();
                if (jumlahBase > stokBase) {
                    alert(`Stok tidak mencukupi. Stok tersedia: ${stokBase}`);
                    return;
                }

                const detailBarang = detailInput.value.trim();
                const satuanLabel = (opt.getAttribute('data-satuan') || '').trim();
                const barangId = String(opt.getAttribute('data-barang-id') || '');
                const existingIdx = items.findIndex(x => x.barang_id === barangId && (x.detail_barang || '') === detailBarang);
                if (existingIdx > -1) {
                    const newTotalBase = items[existingIdx].jumlah + jumlahBase;
                    if (newTotalBase > stokBase) {
                        alert(`Stok tidak mencukupi. Stok tersedia: ${stokBase}`);
                        return;
                    }
                    items[existingIdx].jumlah += jumlahBase;
                    items[existingIdx].jumlah_input += jumlahInput;
                } else {
                    items.push({
                        barang_id: barangId,
                        kode_barang: opt.getAttribute('data-kode') || '',
                        nama_barang: opt.getAttribute('data-nama') || '',
                        detail_barang: detailBarang,
                        jumlah: jumlahBase,
                        jumlah_input: jumlahInput,
                        satuan: satuanLabel,
                        satuan_id: 0,
                        satuan_base_id: 0,
                        satuan_base: satuanLabel,
                        nilai_konversi: 1
                    });
                }
                renderItems();
                clearItemInputs();
            });

            itemsTableBody?.addEventListener('click', function(e) {
                if (e.target.classList.contains('remove-item-btn')) {
                    const idx = parseInt(e.target.getAttribute('data-index') || '0', 10);
                    items.splice(idx, 1);
                    renderItems();
                }
            });

            function normalizePhone(phone) {
                const clean = String(phone || '').replace(/[^0-9]/g, '');
                if (!clean) return '';
                if (clean.startsWith('62')) return clean;
                if (clean.startsWith('0')) return '62' + clean.slice(1);
                return clean;
            }

            document.querySelectorAll('.btn-send-wa').forEach(button => {
                button.addEventListener('click', async function() {
                    const id = this.getAttribute('data-id');
                    if (!id) return;
                    try {
                        const res = await fetch(`index.php?ajax=refund_wa&id=${encodeURIComponent(id)}`);
                        const data = await res.json();
                        if (!data || !data.success) {
                            alert(data?.message || 'Gagal menyiapkan pesan WhatsApp');
                            return;
                        }
                        const phone = normalizePhone(data.telepon);
                        if (!phone) {
                            alert('Nomor telepon supplier tidak valid');
                            return;
                        }
                        const waUrl = `https://wa.me/${phone}?text=${encodeURIComponent(data.message || '')}`;
                        window.open(waUrl, '_blank');
                    } catch (e) {
                        alert('Gagal menyiapkan pesan WhatsApp');
                    }
                });
            });

            renderItems();
        });
    </script>
</body>
</html>
