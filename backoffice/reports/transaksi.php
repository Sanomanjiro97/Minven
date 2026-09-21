<?php
// Hubungkan langsung ke file init utama backoffice Anda
require_once __DIR__ . '/../_init.php'; 

// Proteksi halaman backoffice
bo_require_login();

$db = $GLOBALS['boMainConn'] ?? $GLOBALS['conn'] ?? null;
if (!$db) {
    die("Koneksi database tidak tersedia.");
}

// -------------------------------------------------------------------------
// PENGAMAN OTOMATIS: BUAT TABEL JIKA BELUM ADA
// -------------------------------------------------------------------------
$db->query("CREATE TABLE IF NOT EXISTS pendapatan_manual (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tanggal DATE UNIQUE NOT NULL,
    total_omset_hari DOUBLE DEFAULT 0,
    total_dine_in DOUBLE DEFAULT 0,
    total_take_away DOUBLE DEFAULT 0,
    total_online_order DOUBLE DEFAULT 0,
    total_cash_sales DOUBLE DEFAULT 0,
    total_qr_gopay DOUBLE DEFAULT 0,
    total_edc DOUBLE DEFAULT 0,
    total_online_payment DOUBLE DEFAULT 0,
    total_transfers DOUBLE DEFAULT 0,
    total_shopeefood DOUBLE DEFAULT 0,
    total_gofood_gopay DOUBLE DEFAULT 0,
    total_ovo DOUBLE DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

$pesan_sukses = '';
$pesan_error = '';

// Ambil pesan kiriman dari redirect URL
if (isset($_GET['sukses'])) { $pesan_sukses = htmlspecialchars($_GET['sukses']); }
if (isset($_GET['error'])) { $pesan_error = htmlspecialchars($_GET['error']); }

// -------------------------------------------------------------------------
// 1. PROSES AKSI: HAPUS DATA (PENDAPATAN ATAU PENGELUARAN)
// -------------------------------------------------------------------------
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['type']) && isset($_GET['id'])) {
    $id_hapus = (int)$_GET['id'];
    $type = $_GET['type'];
    
    if ($type === 'pendapatan') {
        $stmt = $db->prepare("DELETE FROM pendapatan_manual WHERE id = ?");
    } else {
        $stmt = $db->prepare("DELETE FROM pengeluaran WHERE id = ?");
    }
    
    if ($stmt) {
        $stmt->bind_param('i', $id_hapus);
        if ($stmt->execute()) {
            header("Location: transaksi.php?sukses=Data " . $type . " berhasil dihapus");
            exit();
        } else {
            $pesan_error = "Gagal menghapus data: " . $stmt->error;
        }
        $stmt->close();
    }
}

// -------------------------------------------------------------------------
// 2. PROSES AKSI: SIMPAN DATA PENDAPATAN
// -------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_pendapatan') {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $tanggal = $_POST['tanggal'];
    $total_omset = (float)$_POST['total_omset_hari'];
    $dine_in = (float)$_POST['total_dine_in'];
    $take_away = (float)$_POST['total_take_away'];
    $online_order = (float)$_POST['total_online_order'];
    $cash = (float)$_POST['total_cash_sales'];
    $qr_gopay = (float)$_POST['total_qr_gopay'];
    $edc = (float)$_POST['total_edc'];
    $online_pay = (float)$_POST['total_online_payment'];
    $transfer = (float)$_POST['total_transfers'];
    $shopeefood = (float)$_POST['total_shopeefood'];
    $gofood = (float)$_POST['total_gofood_gopay'];
    $ovo = (float)$_POST['total_ovo'];

    if ($id > 0) {
        $stmt = $db->prepare("UPDATE pendapatan_manual SET 
            tanggal = ?, total_omset_hari = ?, total_dine_in = ?, total_take_away = ?, total_online_order = ?, 
            total_cash_sales = ?, total_qr_gopay = ?, total_edc = ?, total_online_payment = ?, total_transfers = ?, 
            total_shopeefood = ?, total_gofood_gopay = ?, total_ovo = ? WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param('sddddddddddddi', $tanggal, $total_omset, $dine_in, $take_away, $online_order, $cash, $qr_gopay, $edc, $online_pay, $transfer, $shopeefood, $gofood, $ovo, $id);
            if ($stmt->execute()) {
                header("Location: transaksi.php?sukses=Data pendapatan berhasil diperbarui");
                exit();
            } else { $pesan_error = "Gagal memperbarui pendapatan: " . $stmt->error; }
            $stmt->close();
        }
    } else {
        $stmt = $db->prepare("INSERT INTO pendapatan_manual 
            (tanggal, total_omset_hari, total_dine_in, total_take_away, total_online_order, total_cash_sales, total_qr_gopay, total_edc, total_online_payment, total_transfers, total_shopeefood, total_gofood_gopay, total_ovo) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
            total_omset_hari = VALUES(total_omset_hari), total_dine_in = VALUES(total_dine_in), total_take_away = VALUES(total_take_away), total_online_order = VALUES(total_online_order),
            total_cash_sales = VALUES(total_cash_sales), total_qr_gopay = VALUES(total_qr_gopay), total_edc = VALUES(total_edc), total_online_payment = VALUES(total_online_payment),
            total_transfers = VALUES(total_transfers), total_shopeefood = VALUES(total_shopeefood), total_gofood_gopay = VALUES(total_gofood_gopay), total_ovo = VALUES(total_ovo)");
        if ($stmt) {
            $stmt->bind_param('sdddddddddddd', $tanggal, $total_omset, $dine_in, $take_away, $online_order, $cash, $qr_gopay, $edc, $online_pay, $transfer, $shopeefood, $gofood, $ovo);
            if ($stmt->execute()) {
                header("Location: transaksi.php?sukses=Data pendapatan berhasil disimpan");
                exit();
            } else { $pesan_error = "Gagal menyimpan pendapatan: " . $stmt->error; }
            $stmt->close();
        }
    }
}

// -------------------------------------------------------------------------
// 3. PROSES AKSI: SIMPAN DATA PENGELUARAN
// -------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_pengeluaran') {
    $tanggal = $_POST['tanggal'] ?? date('Y-m-d');
    $payment_method = $_POST['payment_method'] ?? 'cash';
    $deskripsi = trim($_POST['deskripsi'] ?? '');
    $total_harga_raw = str_replace(['.', ','], ['', '.'], $_POST['total_harga'] ?? '0');
    $total_harga = floatval($total_harga_raw);

    if ($deskripsi === '') { $pesan_error = 'Deskripsi pengeluaran harus diisi.'; } 
    elseif ($total_harga <= 0) { $pesan_error = 'Total harga harus lebih dari 0.'; } 
    else {
        $today = date('Y-m-d');
        $q = $db->query("SELECT COUNT(*) AS c FROM pengeluaran WHERE tanggal LIKE '$today%'");
        $count = $q ? (int)($q->fetch_assoc()['c'] ?? 0) + 1 : 1;
        $no_pengeluaran = 'PENG-' . date('Ymd') . str_pad($count, 4, '0', STR_PAD_LEFT);

        $stmt = $db->prepare("INSERT INTO pengeluaran (no_pengeluaran, tanggal, payment_method, deskripsi, total_harga, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        if ($stmt) {
            $stmt->bind_param('ssssd', $no_pengeluaran, $tanggal, $payment_method, $deskripsi, $total_harga);
            if ($stmt->execute()) {
                $pengeluaran_id = $db->insert_id;
                $stmt_detail = $db->prepare("INSERT INTO detail_pengeluaran (pengeluaran_id, nama_item, jumlah, harga_satuan, total_harga, created_at) VALUES (?, ?, 1, ?, ?, NOW())");
                $stmt_detail->bind_param('isdd', $pengeluaran_id, $deskripsi, $total_harga, $total_harga);
                $stmt_detail->execute();
                $stmt_detail->close();
                header("Location: transaksi.php?sukses=Pengeluaran berhasil ditambahkan!");
                exit();
            } else { $pesan_error = 'Gagal menyimpan pengeluaran: ' . $stmt->error; }
            $stmt->close();
        }
    }
}

// -------------------------------------------------------------------------
// 4. AMBIL DATA RINGKASAN DASHBOARD BULAN INI (KONSOLIDASI)
// -------------------------------------------------------------------------
$bulan_ini = date('Y-m');
$bulan_ini_sql = date('Y-m-01');
$bulan_akhir_sql = date('Y-m-t');

// Hitung Statistik Pendapatan
$query_stats = $db->query("SELECT 
    SUM(total_omset_hari) AS total_omset,
    SUM(total_cash_sales) AS total_cash,
    SUM(total_qr_gopay + total_edc + total_online_payment + total_transfers + total_shopeefood + total_gofood_gopay + total_ovo) AS total_saldo
    FROM pendapatan_manual WHERE tanggal LIKE '$bulan_ini-%'");
$stats = $query_stats ? $query_stats->fetch_assoc() : [];
$stat_omset = (float)($stats['total_omset'] ?? 0);
$stat_cash  = (float)($stats['total_cash'] ?? 0);
$stat_saldo = (float)($stats['total_saldo'] ?? 0);

// Hitung Statistik Pengeluaran
$query_pengeluaran = $db->query("SELECT 
    SUM(CASE WHEN payment_method = 'cash' THEN total_harga ELSE 0 END) AS total_pengeluaran_cash,
    SUM(CASE WHEN payment_method = 'saldo' THEN total_harga ELSE 0 END) AS total_pengeluaran_saldo,
    SUM(total_harga) AS total_semua_pengeluaran
    FROM pengeluaran WHERE tanggal BETWEEN '$bulan_ini_sql' AND '$bulan_akhir_sql'");
$pengeluaran_stats = $query_pengeluaran ? $query_pengeluaran->fetch_assoc() : [];
$total_pengeluaran_cash = (float)($pengeluaran_stats['total_pengeluaran_cash'] ?? 0);
$total_pengeluaran_saldo = (float)($pengeluaran_stats['total_pengeluaran_saldo'] ?? 0);
$total_semua_pengeluaran = (float)($pengeluaran_stats['total_semua_pengeluaran'] ?? 0);

// Hitung Sisa Bersih (Net)
$sisa_cash = $stat_cash - $total_pengeluaran_cash;
$sisa_saldo = $stat_saldo - $total_pengeluaran_saldo;

// -------------------------------------------------------------------------
// 5. MEMBUAT LIST TRANSAKSI GABUNGAN (UNION) KRONOLOGIS
// -------------------------------------------------------------------------
$sql_union = "(SELECT id, tanggal, 'pendapatan' AS tipe, '-' AS info_metode, 'Pendapatan Omset Harian' AS keterangan, total_omset_hari AS nominal, JSON_OBJECT('total_cash_sales', total_cash_sales, 'total_dine_in', total_dine_in, 'total_take_away', total_take_away, 'total_online_order', total_online_order, 'total_qr_gopay', total_qr_gopay, 'total_edc', total_edc, 'total_online_payment', total_online_payment, 'total_transfers', total_transfers, 'total_shopeefood', total_shopeefood, 'total_gofood_gopay', total_gofood_gopay, 'total_ovo', total_ovo) AS detail_json FROM pendapatan_manual)
              UNION 
              (SELECT id, tanggal, 'pengeluaran' AS tipe, payment_method AS info_metode, deskripsi AS keterangan, total_harga AS nominal, JSON_OBJECT('no_pengeluaran', no_pengeluaran) AS detail_json FROM pengeluaran)
              ORDER BY tanggal DESC, id DESC LIMIT 100";

$query_list = $db->query($sql_union);
$all_transactions = [];
if ($query_list) {
    while($r = $query_list->fetch_assoc()) { $all_transactions[] = $r; }
}

// Ambil data edit pendapatan jika ada parameter edit_id
$edit_data = null;
if (isset($_GET['edit_id'])) {
    $id_target = (int)$_GET['edit_id'];
    $stmt = $db->prepare("SELECT * FROM pendapatan_manual WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param('i', $id_target);
        $stmt->execute();
        $edit_data = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
}

// Render Shell Layout Utama
bo_render_shell_start([
    'title' => 'Manajemen Transaksi Kas - MINVEN',
    'page_title' => 'Arus Transaksi Keuangan',
    'page_subtitle' => 'Pusat integrasi rekaman pendapatan (Omset) dan pengeluaran kas operasional.',
    'active' => 'reports-income'
]);
?>

<div class="container-fluid py-3">
    <?php if ($pesan_sukses): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i> <?= $pesan_sukses ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <?php if ($pesan_error): ?>
        <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= $pesan_error ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (!isset($_GET['add_pendapatan']) && !isset($_GET['add_pengeluaran']) && !$edit_data): ?>
        


        <div class="d-flex justify-content-between align-items-center mb-4">
            <h5 class="fw-bold m-0 text-secondary"><i class="bi bi-clock-history me-2"></i>Histori Transaksi Gabungan</h5>
            
            <div class="dropdown">
                <button class="btn btn-primary btn-lg fw-bold dropdown-toggle shadow-sm" type="button" id="dropdownTambahTransaksi" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-plus-circle-fill me-2"></i>Tambah Transaksi
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow border-0 p-2" aria-labelledby="dropdownTambahTransaksi">
                    <li>
                        <a class="dropdown-menu-item dropdown-item rounded py-2" href="?add_pendapatan=true">
                            <i class="bi bi-graph-up-arrow text-success me-2"></i> <strong>Pendapatan Baru</strong>
                        </a>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <a class="dropdown-menu-item dropdown-item rounded py-2" href="?add_pengeluaran=true">
                            <i class="bi bi-graph-down-arrow text-danger me-2"></i> <strong>Pengeluaran Baru</strong>
                        </a>
                    </li>
                </ul>
            </div>
        </div>

        <div class="card border-0 shadow-sm rounded-3">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light text-uppercase fs-7 fw-bold text-muted">
                            <tr>
                                <th class="ps-4" style="width: 15%">Tanggal</th>
                                <th style="width: 15%">Tipe</th>
                                <th style="width: 40%">Keterangan / Deskripsi</th>
                                <th class="text-end" style="width: 15%">Nominal</th>
                                <th class="text-center" style="width: 15%">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($all_transactions)): ?>
                                <tr>
                                    <td colspan="5" class="text-center py-5 text-muted">Belum ada aktivitas sirkulasi kas masuk atau keluar.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($all_transactions as $row): ?>
                                    <tr>
                                        <td class="ps-4 fw-bold text-secondary"><?= date('d M Y', strtotime($row['tanggal'])) ?></td>
                                        <td>
                                            <?php if($row['tipe'] === 'pendapatan'): ?>
                                                <span class="badge bg-success-subtle text-success px-2.5 py-1.5 rounded border border-success-subtle uppercase"><i class="bi bi-arrow-up-right me-1"></i>IN (OMSET)</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger-subtle text-danger px-2.5 py-1.5 rounded border border-danger-subtle uppercase"><i class="bi bi-arrow-down-left me-1"></i>OUT (<?=strtoupper($row['info_metode'])?>)</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="fw-medium text-dark"><?= htmlspecialchars($row['keterangan']) ?></span>
                                        </td>
                                        <td class="text-end fw-bold <?= $row['tipe'] === 'pendapatan' ? 'text-success' : 'text-danger' ?>">
                                            <?= $row['tipe'] === 'pendapatan' ? '+' : '-' ?> Rp <?= number_format($row['nominal'], 0, ',', '.') ?>
                                        </td>
                                        <td class="text-center">
                                            <div class="btn-group">
                                                <?php if($row['tipe'] === 'pendapatan'): ?>
                                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="bukaModalDetail(<?= htmlspecialchars($row['detail_json']) ?>, '<?= $row['tanggal'] ?>', '<?= $row['nominal'] ?>')" title="Lihat Rincian Pendapatan"><i class="bi bi-eye"></i></button>
                                                    <a href="?edit_id=<?= $row['id'] ?>" class="btn btn-sm btn-outline-primary" title="Ubah Data Pendapatan"><i class="bi bi-pencil"></i></a>
                                                <?php else: ?>
                                                    <button type="button" class="btn btn-sm btn-outline-secondary" disabled title="Pengeluaran Terkunci Sistem"><i class="bi bi-receipt"></i></button>
                                                <?php endif; ?>
                                                <a href="?action=delete&type=<?= $row['tipe'] ?>&id=<?= $row['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Hapus data transaksi <?= $row['tipe'] ?> ini?')" title="Hapus"><i class="bi bi-trash"></i></a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    <?php elseif (isset($_GET['add_pengeluaran'])): ?>
        <div class="d-flex align-items-center mb-4">
            <a href="transaksi.php" class="btn btn-sm btn-secondary me-2"><i class="bi bi-arrow-left"></i> Kembali</a>
            <h4 class="mb-0 fw-bold">Tambah Pengeluaran Kas Baru</h4>
        </div>

        <div class="card border-0 shadow-sm max-width-form">
            <div class="card-body p-4">
                <form method="POST" action="transaksi.php">
                    <input type="hidden" name="action" value="save_pengeluaran">
                    <div class="row g-3">
                        <div class="col-12 col-md-4">
                            <label class="form-label small fw-bold">Tanggal Pengeluaran</label>
                            <input type="date" class="form-control" name="tanggal" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label small fw-bold">Metode Pembayaran</label>
                            <select class="form-select" name="payment_method" required>
                                <option value="cash">Cash (Uang Fisik Toko)</option>
                                <option value="saldo">Saldo (Rekening Bank/E-Wallet)</option>
                            </select>
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label small fw-bold">Total Nilai Pengeluaran (Rp)</label>
                            <input type="text" class="form-control fw-bold text-danger" name="total_harga" id="total_harga_pengeluaran" placeholder="0" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-bold">Deskripsi Pengeluaran</label>
                            <input type="text" class="form-control" name="deskripsi" placeholder="Contoh: Pembelian Bahan Baku Es Batu, Bayar Token Listrik..." required>
                        </div>
                        <div class="col-12 text-end pt-2">
                            <a href="transaksi.php" class="btn btn-light border px-4">Batal</a>
                            <button type="submit" class="btn btn-danger px-5 fw-bold shadow-sm"><i class="bi bi-plus-lg me-1"></i> Catat Pengeluaran</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

    <?php else: ?>
        <?php 
        $d = $edit_data ?? [
            'id' => 0, 'tanggal' => date('Y-m-d'), 'total_omset_hari' => 0,
            'total_dine_in' => 0, 'total_take_away' => 0, 'total_online_order' => 0,
            'total_cash_sales' => 0, 'total_qr_gopay' => 0, 'total_edc' => 0, 'total_online_payment' => 0,
            'total_transfers' => 0, 'total_shopeefood' => 0, 'total_gofood_gopay' => 0, 'total_ovo' => 0
        ];
        ?>
        <div class="d-flex align-items-center mb-3">
            <a href="transaksi.php" class="btn btn-sm btn-secondary me-2"><i class="bi bi-arrow-left"></i> Kembali</a>
            <h4 class="mb-0 fw-bold"><?= $d['id'] > 0 ? 'Edit Rekaman Pendapatan Tanggal ' . $d['tanggal'] : 'Input Rekaman Pendapatan Baru' ?></h4>
        </div>

        <form id="formPendapatan" method="POST" action="transaksi.php">
            <input type="hidden" name="action" value="save_pendapatan">
            <input type="hidden" name="id" value="<?= $d['id'] ?>">
            
            <div class="row g-4">
                <div class="col-12 col-md-4">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body">
                            <h5 class="card-title mb-3 fw-bold text-primary"><i class="bi bi-calendar-check me-2"></i>Data Omset</h5>
                            <div class="mb-3">
                                <label class="form-label small fw-bold">Tanggal Berjalan</label>
                                <input type="date" name="tanggal" class="form-control" value="<?= $d['tanggal'] ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-bold">Total Omset Hari Ini (Rp)</label>
                                <input type="number" id="total_omset" name="total_omset_hari" class="form-control form-control-lg fw-bold text-success" value="<?= (int)$d['total_omset_hari'] ?>" placeholder="0" min="0" required>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-md-4">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body d-flex flex-column">
                            <h5 class="card-title mb-3 fw-bold text-info"><i class="bi bi-bag-dash me-2"></i>Jenis Pesanan</h5>
                            <div class="mb-2">
                                <label class="form-label small">Total Dine In</label>
                                <input type="number" id="dine_in" name="total_dine_in" class="form-control hitung-pesanan" value="<?= (int)$d['total_dine_in'] ?>" min="0">
                            </div>
                            <div class="mb-2">
                                <label class="form-label small">Total Take Away</label>
                                <input type="number" id="take_away" name="total_take_away" class="form-control hitung-pesanan" value="<?= (int)$d['total_take_away'] ?>" min="0">
                            </div>
                            <div class="mb-3">
                                <label class="form-label small">Total Online Order</label>
                                <input type="number" id="online_order" name="total_online_order" class="form-control hitung-pesanan" value="<?= (int)$d['total_online_order'] ?>" min="0">
                            </div>
                            <div class="mt-auto pt-3 border-top">
                                <div class="d-flex justify-content-between mb-2 small text-muted">
                                    <span>Subtotal Pesanan:</span>
                                    <span id="subtotal_pesanan_label" class="fw-bold text-dark">Rp 0</span>
                                </div>
                                <button type="button" class="btn btn-outline-info w-100 btn-sm fw-bold" onclick="validasiKomponen('pesanan')">
                                    <i class="bi bi-shield-check me-1"></i> Cek Komponen Pesanan
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-md-4">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body d-flex flex-column">
                            <h5 class="card-title mb-3 fw-bold text-warning-custom text-warning"><i class="bi bi-credit-card me-2"></i>Metode Pembayaran Masuk</h5>
                            <div class="row g-2 overflow-auto" style="max-height: 280px;">
                                <div class="col-12"><label class="form-label small mb-0 fw-bold">Cash Sales</label><input type="number" id="cash" name="total_cash_sales" class="form-control form-control-sm hitung-pembayaran" value="<?= (int)$d['total_cash_sales'] ?>" min="0"></div>
                                <div class="col-6"><label class="form-label small mb-0">QR GoPay</label><input type="number" id="qr_gopay" name="total_qr_gopay" class="form-control form-control-sm hitung-pembayaran" value="<?= (int)$d['total_qr_gopay'] ?>" min="0"></div>
                                <div class="col-6"><label class="form-label small mb-0">EDC Mesin</label><input type="number" id="edc" name="total_edc" class="form-control form-control-sm hitung-pembayaran" value="<?= (int)$d['total_edc'] ?>" min="0"></div>
                                <div class="col-6"><label class="form-label small mb-0">Bank Transfer</label><input type="number" id="transfer" name="total_transfers" class="form-control form-control-sm hitung-pembayaran" value="<?= (int)$d['total_transfers'] ?>" min="0"></div>
                                <div class="col-6"><label class="form-label small mb-0">ShopeeFood</label><input type="number" id="shopeefood" name="total_shopeefood" class="form-control form-control-sm hitung-pembayaran" value="<?= (int)$d['total_shopeefood'] ?>" min="0"></div>
                                <div class="col-6"><label class="form-label small mb-0">GoFood App</label><input type="number" id="gofood" name="total_gofood_gopay" class="form-control form-control-sm hitung-pembayaran" value="<?= (int)$d['total_gofood_gopay'] ?>" min="0"></div>
                                <div class="col-12"><label class="form-label small mb-0">OVO Dompet</label><input type="number" id="ovo" name="total_ovo" class="form-control form-control-sm hitung-pembayaran" value="<?= (int)$d['total_ovo'] ?>" min="0"></div>
                            </div>
                            <div class="mt-auto pt-3 border-top">
                                <div class="d-flex justify-content-between mb-2 small text-muted">
                                    <span>Subtotal Pembayaran:</span>
                                    <span id="subtotal_pembayaran_label" class="fw-bold text-dark">Rp 0</span>
                                </div>
                                <button type="button" class="btn btn-outline-warning w-100 btn-sm fw-bold text-dark" onclick="validasiKomponen('pembayaran')">
                                    <i class="bi bi-shield-check me-1"></i> Cek Komponen Pembayaran
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row mt-4">
                <div class="col-12 text-end">
                    <a href="transaksi.php" class="btn btn-light border px-4 py-2 me-2">Batal</a>
                    <button type="button" class="btn btn-success px-5 py-2 fw-bold shadow-sm" onclick="submitFinalForm()">
                        <i class="bi bi-cloud-arrow-up-fill me-2"></i> Simpan Record Omset
                    </button>
                </div>
            </div>
        </form>
    <?php endif; ?>
</div>

<div class="modal fade" id="modalValidasi" data-bs-backdrop="static" tabindex="-1" aria-labelledby="modalValidasiLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <div id="modalHeaderBg" class="modal-header text-white">
        <h5 class="modal-title fw-bold" id="modalValidasiLabel">Status Pemetaan</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body text-center py-4">
        <div id="modalIcon" class="mb-3" style="font-size: 3.5rem;"></div>
        <h5 id="modalTitleStatus" class="fw-bold mb-2"></h5>
        <p id="modalBodyText" class="text-muted px-3"></p>
      </div>
      <div class="modal-footer bg-light border-0">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Mengerti</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="modalDetail" tabindex="-1" aria-labelledby="modalDetailLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <div class="modal-header bg-dark text-white">
        <h5 class="modal-title fw-bold" id="modalDetailLabel"><i class="bi bi-card-list me-2"></i>Breakdown Rincian Omset</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-4" id="modalDetailBody"></div>
      <div class="modal-footer bg-light border-0">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Tutup View</button>
      </div>
    </div>
  </div>
</div>

<?php 
// EMBED LOGIC SCRIPTS JAVASCRIPT
$jsScripts = '
<script>
let validasiPesananOk = false;
let validasiPembayaranOk = false;

function formatRupiah(angka) {
    return "Rp " + parseFloat(angka).toLocaleString("id-ID");
}

// Format Input Input Rupiah Pengeluaran
const targetHargaInput = document.getElementById("total_harga_pengeluaran");
if(targetHargaInput) {
    targetHargaInput.addEventListener("input", function(e) {
        let number_string = this.value.replace(/[^,\\d]/g, "").toString();
        let split = number_string.split(",");
        let sisa = split[0].length % 3;
        let rupiah = split[0].substr(0, sisa);
        let ribuan = split[0].substr(sisa).match(/\\d{3}/gi);
        if (ribuan) {
            let separator = sisa ? "." : "";
            rupiah += separator + ribuan.join(".");
        }
        this.value = split[1] !== undefined ? rupiah + "," + split[1] : rupiah;
    });
}

function updateSubtotals() {
    let tPesanan = 0;
    document.querySelectorAll(".hitung-pesanan").forEach(i => tPesanan += (parseFloat(i.value) || 0));
    const lblPesanan = document.getElementById("subtotal_pesanan_label");
    if(lblPesanan) lblPesanan.innerText = formatRupiah(tPesanan);

    let tPembayaran = 0;
    document.querySelectorAll(".hitung-pembayaran").forEach(i => tPembayaran += (parseFloat(i.value) || 0));
    const lblPembayaran = document.getElementById("subtotal_pembayaran_label");
    if(lblPembayaran) lblPembayaran.innerText = formatRupiah(tPembayaran);
}

document.querySelectorAll(".hitung-pesanan, .hitung-pembayaran").forEach(el => {
    el.addEventListener("input", updateSubtotals);
});

if(document.getElementById("formPendapatan")) {
    updateSubtotals();
}

function validasiKomponen(tipe) {
    const omset = parseFloat(document.getElementById("total_omset").value) || 0;
    if (omset <= 0) {
        alert("Silakan isi Total Omset Utama terlebih dahulu.");
        document.getElementById("total_omset").focus();
        return;
    }

    let subtotal = 0;
    let namaSeksi = (tipe === "pesanan") ? "Jenis Pesanan" : "Komponen Pembayaran";
    
    if (tipe === "pesanan") {
        document.querySelectorAll(".hitung-pesanan").forEach(i => subtotal += (parseFloat(i.value) || 0));
    } else {
        document.querySelectorAll(".hitung-pembayaran").forEach(i => subtotal += (parseFloat(i.value) || 0));
    }

    const modal = new bootstrap.Modal(document.getElementById("modalValidasi"));
    const headerBg = document.getElementById("modalHeaderBg");
    const iconDiv = document.getElementById("modalIcon");
    const titleStatus = document.getElementById("modalTitleStatus");
    const bodyText = document.getElementById("modalBodyText");

    if (subtotal === omset) {
        if (tipe === "pesanan") validasiPesananOk = true;
        if (tipe === "pembayaran") validasiPembayaranOk = true;
        headerBg.className = "modal-header bg-success text-white";
        iconDiv.innerHTML = \'<i class="bi bi-check-circle-fill text-success"></i>\';
        titleStatus.innerText = "Kombinasi Sesuai";
        bodyText.innerHTML = `Jumlah input data <strong>${namaSeksi}</strong> akurat & balance dengan nilai Omset Utama (${formatRupiah(omset)}).`;
    } else {
        if (tipe === "pesanan") validasiPesananOk = false;
        if (tipe === "pembayaran") validasiPembayaranOk = false;
        const diff = subtotal - omset;
        const strDiff = diff > 0 ? `Kelebihan input ${formatRupiah(diff)}` : `Kekurangan input ${formatRupiah(Math.abs(diff))}`;
        headerBg.className = "modal-header bg-danger text-white";
        iconDiv.innerHTML = \'<i class="bi bi-exclamation-circle-fill text-danger"></i>\';
        titleStatus.innerText = "Nilai Belum Cocok!";
        bodyText.innerHTML = `Akumulasi input <strong>${namaSeksi}</strong> adalah ${formatRupiah(subtotal)}.<br>Terjadi Deviasi <strong>(${strDiff})</strong> terhadap nominal target Omset Utama.`;
    }
    modal.show();
}

function submitFinalForm() {
    const omset = parseFloat(document.getElementById("total_omset").value) || 0;
    if (omset <= 0) { alert("Nominal Total Omset tidak valid."); return; }

    if (!validasiPesananOk || !validasiPembayaranOk) {
        if (confirm("Pemberitahuan: Struktur komponen pesanan/pembayaran belum divalidasi balance. Anda ingin memaksakan penyimpanan data?")) {
            document.getElementById("formPendapatan").submit();
        }
    } else {
        document.getElementById("formPendapatan").submit();
    }
}

function bukaModalDetail(data, tgl, total) {
    let html = `
        <table class="table table-bordered table-sm m-0 fs-7">
            <tr class="table-dark"><th colspan="2">Informasi Utama Rekap</th></tr>
            <tr><td width="40%">Tanggal Rekam</td><td><strong>\${tgl}</strong></td></tr>
            <tr><td>Total Omset (Gross)</td><td class="text-success fw-bold">\${formatRupiah(total)}</td></tr>
            
            <tr class="table-light"><th colspan="2" class="fw-bold text-info"><i class="bi bi-tag me-1"></i>Distribusi Pesanan</th></tr>
            <tr><td>Dine In</td><td>\${formatRupiah(data.total_dine_in)}</td></tr>
            <tr><td>Take Away</td><td>\${formatRupiah(data.total_take_away)}</td></tr>
            <tr><td>Online Order</td><td>\${formatRupiah(data.total_online_order)}</td></tr>

            <tr class="table-light"><th colspan="2" class="fw-bold text-warning text-dark"><i class="bi bi-credit-card me-1"></i>Penerimaan Dana</th></tr>
            <tr><td>Cash Sales</td><td><strong>\${formatRupiah(data.total_cash_sales)}</strong></td></tr>
            <tr><td>QR GoPay</td><td>\${formatRupiah(data.total_qr_gopay)}</td></tr>
            <tr><td>EDC Mesin</td><td>\${formatRupiah(data.total_edc)}</td></tr>
            <tr><td>Bank Transfers</td><td>\${formatRupiah(data.total_transfers)}</td></tr>
            <tr><td>ShopeeFood</td><td>\${formatRupiah(data.total_shopeefood)}</td></tr>
            <tr><td>GoFood GoPay</td><td>\${formatRupiah(data.total_gofood_gopay)}</td></tr>
            <tr><td>OVO Wallet</td><td>\${formatRupiah(data.total_ovo)}</td></tr>
        </table>
    `;
    document.getElementById("modalDetailBody").innerHTML = html;
    const modal = new bootstrap.Modal(document.getElementById("modalDetail"));
    modal.show();
}
</script>
';

bo_render_shell_end($jsScripts);
?>