<?php
require_once __DIR__ . '/_init.php';
require_once __DIR__ . '/../includes/access_check.php';

$conn = $conn ?? null;
if (!($conn instanceof mysqli)) {
    $_SESSION['error'] = 'Koneksi database tidak tersedia.';
    header('Location: ' . url_for('product/index.php'));
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . url_for('product/index.php'));
    exit();
}

$action = (string)($_POST['action'] ?? '');

if ($action !== 'add') {
    $_SESSION['error'] = 'Aksi tidak dikenali.';
    header('Location: ' . url_for('product/index.php'));
    exit();
}

if (!checkAccess('product', 'add')) {
    $_SESSION['error'] = 'Akses tidak diizinkan untuk menambah product.';
    header('Location: ' . url_for('product/index.php'));
    exit();
}

$nama_product = trim((string)($_POST['nama_product'] ?? ''));
$gudang_id = isset($_POST['gudang_id']) ? (int)$_POST['gudang_id'] : 0;
$barang_ids = $_POST['barang_id'] ?? [];
$qtys = $_POST['qty'] ?? [];

if ($nama_product === '') {
    $_SESSION['error'] = 'Nama product wajib diisi.';
    header('Location: ' . url_for('product/create.php'));
    exit();
}
if ($gudang_id <= 0) {
    $_SESSION['error'] = 'Gudang wajib dipilih.';
    header('Location: ' . url_for('product/create.php'));
    exit();
}

$stmtGudang = $conn->prepare("SELECT id FROM gudang WHERE id = ? LIMIT 1");
if (!$stmtGudang) {
    $_SESSION['error'] = 'Gagal validasi gudang: ' . $conn->error;
    header('Location: ' . url_for('product/create.php'));
    exit();
}
$stmtGudang->bind_param('i', $gudang_id);
$stmtGudang->execute();
$existsGudang = $stmtGudang->get_result()->num_rows > 0;
$stmtGudang->close();
if (!$existsGudang) {
    $_SESSION['error'] = 'Gudang tidak ditemukan.';
    header('Location: ' . url_for('product/create.php'));
    exit();
}

$allowed = false;
$rows = get_accessible_gudang_list($conn);
foreach ($rows as $r) {
    if ((int)($r['id'] ?? 0) === $gudang_id) {
        $allowed = true;
        break;
    }
}
if (!$allowed) {
    $_SESSION['error'] = 'Anda tidak memiliki akses ke gudang tersebut.';
    header('Location: ' . url_for('product/create.php'));
    exit();
}

$lines = [];
if (is_array($barang_ids) && is_array($qtys)) {
    $n = max(count($barang_ids), count($qtys));
    for ($i = 0; $i < $n; $i++) {
        $bid = isset($barang_ids[$i]) ? (int)$barang_ids[$i] : 0;
        $qty = isset($qtys[$i]) ? (int)$qtys[$i] : 0;
        if ($bid <= 0 || $qty <= 0) {
            continue;
        }
        if (!isset($lines[$bid])) {
            $lines[$bid] = 0;
        }
        $lines[$bid] += $qty;
    }
}

if (empty($lines)) {
    $_SESSION['error'] = 'Minimal 1 item dengan qty > 0 harus diisi.';
    header('Location: ' . url_for('product/create.php'));
    exit();
}

$stmtBarang = $conn->prepare("SELECT id, kode_barang, nama_barang FROM barang WHERE id = ? LIMIT 1");
$stmtStok = $conn->prepare("SELECT COALESCE((stok_awal - stok_terpakai), 0) AS stok_tersedia FROM gudang_stok WHERE gudang_id = ? AND barang_id = ? LIMIT 1");
if (!$stmtBarang || !$stmtStok) {
    $_SESSION['error'] = 'Gagal menyiapkan validasi stok: ' . $conn->error;
    header('Location: ' . url_for('product/create.php'));
    exit();
}

foreach ($lines as $barang_id => $qty) {
    $barang_id = (int)$barang_id;
    $qty = (int)$qty;

    $stmtBarang->bind_param('i', $barang_id);
    $stmtBarang->execute();
    $barangRow = $stmtBarang->get_result()->fetch_assoc();
    if (!$barangRow) {
        $stmtBarang->close();
        $stmtStok->close();
        $_SESSION['error'] = 'Barang tidak ditemukan (ID: ' . $barang_id . ').';
        header('Location: ' . url_for('product/create.php'));
        exit();
    }

    $stmtStok->bind_param('ii', $gudang_id, $barang_id);
    $stmtStok->execute();
    $stokRow = $stmtStok->get_result()->fetch_assoc();
    $stokTersedia = (int)($stokRow['stok_tersedia'] ?? 0);

    if ($qty > $stokTersedia) {
        $namaBarang = trim((string)($barangRow['kode_barang'] ?? '') . ' - ' . (string)($barangRow['nama_barang'] ?? ''));
        $stmtBarang->close();
        $stmtStok->close();
        $_SESSION['error'] = 'Qty melebihi stok tersedia untuk ' . $namaBarang . '. (Tersedia: ' . $stokTersedia . ')';
        header('Location: ' . url_for('product/create.php'));
        exit();
    }
}

$stmtBarang->close();
$stmtStok->close();

try {
    $conn->begin_transaction();

    $created_by = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

    $stmtIns = $conn->prepare("INSERT INTO product (nama_product, gudang_id, created_by, created_at) VALUES (?, ?, ?, NOW())");
    if (!$stmtIns) {
        throw new Exception($conn->error);
    }
    $stmtIns->bind_param('sii', $nama_product, $gudang_id, $created_by);
    if (!$stmtIns->execute()) {
        throw new Exception($stmtIns->error);
    }
    $product_id = (int)$conn->insert_id;
    $stmtIns->close();

    $stmtDet = $conn->prepare("INSERT INTO product_detail (product_id, barang_id, qty) VALUES (?, ?, ?)");
    if (!$stmtDet) {
        throw new Exception($conn->error);
    }
    foreach ($lines as $barang_id => $qty) {
        $barang_id = (int)$barang_id;
        $qty = (int)$qty;
        $stmtDet->bind_param('iii', $product_id, $barang_id, $qty);
        if (!$stmtDet->execute()) {
            throw new Exception($stmtDet->error);
        }
    }
    $stmtDet->close();

    $conn->commit();
    $_SESSION['success'] = 'Product berhasil disimpan.';
    header('Location: ' . url_for('product/view.php?id=' . $product_id));
    exit();
} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['error'] = 'Gagal menyimpan product: ' . $e->getMessage();
    header('Location: ' . url_for('product/create.php'));
    exit();
}
