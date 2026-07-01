<?php
session_start();
require_once '../config.php';
require_once '../includes/page_access_check.php';


// Ambil bulan dan tahun
$filter_session_key = 'laporan_perbulan_filter';
$saved_filters = [];
if (isset($_SESSION[$filter_session_key]) && is_array($_SESSION[$filter_session_key])) {
    $saved_filters = $_SESSION[$filter_session_key];
}

$filter_keys = ['bulan', 'tahun', 'gudang_id', 'q', 'only_transaksi'];
$has_any_filter_param = false;
foreach ($filter_keys as $k) {
    if (array_key_exists($k, $_GET)) {
        $has_any_filter_param = true;
        break;
    }
}

if (!$has_any_filter_param && !empty($saved_filters)) {
    $redirect_params = [];
    foreach ($filter_keys as $k) {
        if ($k === 'only_transaksi') {
            if ((string)($saved_filters[$k] ?? '0') === '1') {
                $redirect_params[$k] = '1';
            }
            continue;
        }

        if (isset($saved_filters[$k]) && (string)$saved_filters[$k] !== '') {
            $redirect_params[$k] = (string)$saved_filters[$k];
        }
    }

    if (!empty($redirect_params)) {
        header('Location: laporan_perbulan.php?' . http_build_query($redirect_params));
        exit;
    }
}

$bulan_raw = $_GET['bulan'] ?? ($saved_filters['bulan'] ?? date('m'));
$tahun_raw = $_GET['tahun'] ?? ($saved_filters['tahun'] ?? date('Y'));
$gudang_id_raw = $_GET['gudang_id'] ?? ($saved_filters['gudang_id'] ?? '');
$search_raw = $_GET['q'] ?? ($saved_filters['q'] ?? '');
$search = trim((string)$search_raw);
$only_transaksi_raw = $_GET['only_transaksi'] ?? ($saved_filters['only_transaksi'] ?? '0');
$only_transaksi = (string)$only_transaksi_raw === '1';

$bulan_int = (int)$bulan_raw;
if ($bulan_int < 1 || $bulan_int > 12) $bulan_int = (int)date('n');
$bulan = str_pad((string)$bulan_int, 2, '0', STR_PAD_LEFT);

$tahun_int = (int)$tahun_raw;
$tahun_sekarang = (int)date('Y');
if ($tahun_int < 2000 || $tahun_int > $tahun_sekarang) $tahun_int = $tahun_sekarang;
$tahun = (string)$tahun_int;

$bulan_list = [
    1 => 'Januari',
    2 => 'Februari',
    3 => 'Maret',
    4 => 'April',
    5 => 'Mei',
    6 => 'Juni',
    7 => 'Juli',
    8 => 'Agustus',
    9 => 'September',
    10 => 'Oktober',
    11 => 'November',
    12 => 'Desember',
];
$bulan_label = $bulan_list[$bulan_int] ?? $bulan;

$start_date = date('Y-m-d', strtotime("$tahun-$bulan-01"));
$end_date = date('Y-m-t', strtotime($start_date));
$start_dt = $start_date . ' 00:00:00';
$end_dt_excl = date('Y-m-d', strtotime($end_date . ' +1 day')) . ' 00:00:00';
$allowed_gudang = [];
if (function_exists('get_accessible_gudang_list')) {
    $allowed = get_accessible_gudang_list($conn);
    foreach ($allowed as $g) {
        $allowed_gudang[] = ['id' => (int)$g['id'], 'nama_gudang' => (string)$g['nama_gudang']];
    }
}

$gudang_id = '';
if ($gudang_id_raw !== '' && ctype_digit((string)$gudang_id_raw)) {
    $candidate_id = (int)$gudang_id_raw;
    foreach ($allowed_gudang as $g) {
        if ((int)$g['id'] === $candidate_id) {
            $gudang_id = $candidate_id;
            break;
        }
    }
}

if ($gudang_id === '' && !empty($allowed_gudang)) {
    $gudang_id = (int)$allowed_gudang[0]['id'];
}

if ($gudang_id === '' && empty($allowed_gudang)) {
    $fallback_result = $conn->query("SELECT id, nama_gudang FROM gudang WHERE status = 'aktif' ORDER BY nama_gudang LIMIT 1");
    if ($fallback_result && ($row = $fallback_result->fetch_assoc())) {
        $gudang_id = (int)$row['id'];
        $allowed_gudang = [['id' => (int)$row['id'], 'nama_gudang' => (string)$row['nama_gudang']]];
    }
}

$nama_gudang = "Gudang";
foreach ($allowed_gudang as $g) {
    if ((int)$g['id'] === (int)$gudang_id) {
        $nama_gudang = $g['nama_gudang'];
        break;
    }
}

$_SESSION[$filter_session_key] = [
    'bulan' => $bulan,
    'tahun' => $tahun,
    'gudang_id' => (int)$gudang_id,
    'q' => $search,
    'only_transaksi' => $only_transaksi ? '1' : '0',
];

require_once '../templates/header.php';

function getData($conn, $table, $id_barang_column, $jumlah_column, $tanggal_column, $gudang_id) {
    global $start_date, $end_date, $start_dt, $end_dt_excl;
    $data = [];

    switch ($table) {
        case 'po':
            $sql = "SELECT b.id AS barang_id, COALESCE(x.total, 0) as total
                    FROM barang b
                    LEFT JOIN (
                        SELECT dts.barang_id, SUM(dts.jumlah) as total
                        FROM detail_transaksi_stok dts
                        JOIN transaksi_stok ts ON ts.id = dts.transaksi_stok_id
                        WHERE ts.jenis_transaksi = 'masuk'
                            AND ts.keterangan LIKE 'PO %'
                            AND ts.tanggal BETWEEN ? AND ?
                            AND ts.gudang_id = ?
                        GROUP BY dts.barang_id
                    ) x ON x.barang_id = b.id
                    GROUP BY b.id";
            break;

        case 'stok_masuk':
            $sql = "SELECT b.id AS barang_id, COALESCE(SUM(x.jumlah), 0) as total
                    FROM barang b
                    LEFT JOIN (
                        SELECT dts.barang_id, dts.jumlah as jumlah
                        FROM detail_transaksi_stok dts
                        JOIN transaksi_stok ts ON ts.id = dts.transaksi_stok_id
                        WHERE ts.jenis_transaksi = 'masuk'
                            AND ts.tanggal BETWEEN ? AND ?
                            AND ts.gudang_id = ?
                            AND (
                                ts.keterangan IS NULL
                                OR (
                                    ts.keterangan NOT LIKE 'PO %'
                                    AND ts.keterangan NOT LIKE 'Dari pembelian mendadak:%'
                                )
                            )

                        UNION ALL

                        SELECT gsh.barang_id, gsh.jumlah_perubahan as jumlah
                        FROM gudang_stok_history gsh
                        WHERE gsh.jenis_perubahan = 'masuk'
                            AND gsh.referensi = 'QUICK_MASUK'
                            AND gsh.created_at >= ? AND gsh.created_at < ?
                            AND gsh.gudang_id = ?
                            AND NOT EXISTS (
                                SELECT 1
                                FROM transaksi_stok ts2
                                JOIN detail_transaksi_stok dts2 ON ts2.id = dts2.transaksi_stok_id
                                WHERE ts2.jenis_transaksi = 'masuk'
                                    AND ts2.gudang_id = gsh.gudang_id
                                    AND ts2.tanggal = DATE(gsh.created_at)
                                    AND ts2.created_by <=> gsh.created_by
                                    AND dts2.barang_id = gsh.barang_id
                                    AND dts2.jumlah = gsh.jumlah_perubahan
                            )
                    ) x ON x.barang_id = b.id
                    GROUP BY b.id";
            break;

        case 'stok_keluar':
            $sql = "SELECT b.id AS barang_id, COALESCE(SUM(CASE 
                        WHEN ts.jenis_transaksi = 'keluar' 
                            AND ts.tanggal BETWEEN ? AND ?
                            AND ts.gudang_id = ?
                        THEN dts.jumlah ELSE 0 END
                    ), 0) as total 
                    FROM barang b
                    LEFT JOIN detail_transaksi_stok dts ON dts.barang_id = b.id
                    LEFT JOIN transaksi_stok ts ON ts.id = dts.transaksi_stok_id
                    GROUP BY b.id";
            break;

        case 'stok_transfer':
            $sql = "SELECT b.id AS barang_id, COALESCE(SUM(CASE 
                        WHEN tt.tanggal BETWEEN ? AND ?
                            AND tt.gudang_tujuan_id = ?
                        THEN dtt.jumlah ELSE 0 END
                    ), 0) as total 
                    FROM barang b
                    LEFT JOIN detail_transaksi_transfer dtt ON dtt.barang_id = b.id
                    LEFT JOIN transaksi_transfer tt ON tt.id = dtt.transaksi_transfer_id
                    GROUP BY b.id";
            break;

        case 'stok_transfer_masuk':
            $sql = "SELECT b.id AS barang_id, COALESCE(SUM(CASE 
                        WHEN tt.tanggal BETWEEN ? AND ?
                            AND tt.gudang_tujuan_id = ?
                        THEN dtt.jumlah ELSE 0 END
                    ), 0) as total 
                    FROM barang b
                    LEFT JOIN detail_transaksi_transfer dtt ON dtt.barang_id = b.id
                    LEFT JOIN transaksi_transfer tt ON tt.id = dtt.transaksi_transfer_id
                    GROUP BY b.id";
            break;

        case 'stok_transfer_keluar':
            $sql = "SELECT b.id AS barang_id, COALESCE(SUM(CASE 
                        WHEN tt.tanggal BETWEEN ? AND ?
                            AND tt.gudang_asal_id = ?
                        THEN dtt.jumlah ELSE 0 END
                    ), 0) as total 
                    FROM barang b
                    LEFT JOIN detail_transaksi_transfer dtt ON dtt.barang_id = b.id
                    LEFT JOIN transaksi_transfer tt ON tt.id = dtt.transaksi_transfer_id
                    GROUP BY b.id";
            break;

        case 'pembelian_direct':
            $sql = "SELECT b.id AS barang_id, COALESCE(SUM(CASE WHEN ts.tanggal BETWEEN ? AND ? THEN dts.jumlah ELSE 0 END), 0) as total
                    FROM barang b
                    LEFT JOIN detail_transaksi_stock dts ON dts.barang_id = b.id
                    LEFT JOIN transaksi_stock ts ON ts.id = dts.transaksi_stock_id
                        AND ts.keterangan LIKE 'Dari pembelian mendadak:%'
                        AND ts.gudang_id = ?
                    GROUP BY b.id";
            break;

        default:
            return $data;
    }

    $stmt = $conn->prepare($sql);
    if ($stmt) {
        if ($table === 'stok_masuk') {
            $stmt->bind_param("ssissi", $start_date, $end_date, $gudang_id, $start_dt, $end_dt_excl, $gudang_id);
        } else {
            $stmt->bind_param("ssi", $start_date, $end_date, $gudang_id);
        }
        
        if (!$stmt->execute()) {
            echo "Execute failed: (" . $stmt->errno . ") " . $stmt->error;
        }
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $data[(int)$row['barang_id']] = (float)$row['total'];
        }
        $stmt->close();
    } else {
        echo "Prepare failed: (" . $conn->errno . ") " . $conn->error;
    }

    return $data;
}

// Ambil semua barang dari tabel barang
$all_items_from_db = [];
$sql_all_barang = "SELECT id, kode_barang, nama_barang FROM barang ORDER BY nama_barang, kode_barang";
$result_all_barang = $conn->query($sql_all_barang);
if ($result_all_barang) {
    while ($row = $result_all_barang->fetch_assoc()) {
        $all_items_from_db[] = [
            'id' => (int)$row['id'],
            'kode_barang' => (string)($row['kode_barang'] ?? ''),
            'nama_barang' => (string)($row['nama_barang'] ?? ''),
        ];
    }
}

// Ambil data
$data_po        = getData($conn, 'po', 'id_barang', 'jumlah', 'tanggal', $gudang_id);
$data_masuk     = getData($conn, 'stok_masuk', 'id_barang', 'jumlah', 'tanggal_masuk', $gudang_id);
$data_keluar    = getData($conn, 'stok_keluar', 'id_barang', 'jumlah', 'tanggal_keluar', $gudang_id);
$data_transfer_masuk  = getData($conn, 'stok_transfer_masuk', 'id_barang', 'jumlah', 'tanggal_transfer', $gudang_id);
$data_transfer_keluar = getData($conn, 'stok_transfer_keluar', 'id_barang', 'jumlah', 'tanggal_transfer', $gudang_id);
$data_direct    = getData($conn, 'pembelian_direct', 'id_barang', 'jumlah', 'tanggal', $gudang_id);

// Gabungkan nama barang (sekarang menggunakan semua barang dari DB)
$all_items = $all_items_from_db;

usort($all_items, function($a, $b) {
    $an = strtolower((string)($a['nama_barang'] ?? ''));
    $bn = strtolower((string)($b['nama_barang'] ?? ''));
    if ($an === $bn) {
        return strcmp((string)($a['kode_barang'] ?? ''), (string)($b['kode_barang'] ?? ''));
    }
    return strcmp($an, $bn);
});

if ($search !== '') {
    $all_items = array_values(array_filter($all_items, function($barang) use ($search) {
        $kode = (string)($barang['kode_barang'] ?? '');
        $nama = (string)($barang['nama_barang'] ?? '');
        return stripos($kode, $search) !== false || stripos($nama, $search) !== false;
    }));
}

if ($only_transaksi) {
    $all_items = array_values(array_filter($all_items, function($barang) use ($data_po, $data_masuk, $data_keluar, $data_transfer_masuk, $data_transfer_keluar, $data_direct) {
        $barang_id = (int)($barang['id'] ?? 0);
        $v_po = (float)($data_po[$barang_id] ?? 0);
        $v_masuk = (float)($data_masuk[$barang_id] ?? 0);
        $v_transfer_masuk = (float)($data_transfer_masuk[$barang_id] ?? 0);
        $v_transfer_keluar = (float)($data_transfer_keluar[$barang_id] ?? 0);
        $v_direct = (float)($data_direct[$barang_id] ?? 0);
        $v_keluar = (float)($data_keluar[$barang_id] ?? 0);
        return $v_po != 0 || $v_masuk != 0 || $v_transfer_masuk != 0 || $v_transfer_keluar != 0 || $v_direct != 0 || $v_keluar != 0;
    }));
}

$export_params = [
    'bulan' => $bulan,
    'tahun' => $tahun,
    'gudang_id' => (int)$gudang_id,
];
if ($search !== '') $export_params['q'] = $search;
if ($only_transaksi) $export_params['only_transaksi'] = '1';
$export_query = http_build_query($export_params);
?>

<!-- Custom CSS untuk responsive design -->
<style>
    /* Responsive untuk tablet dan mobile */
    @media (max-width: 768px) {
        .container {
            padding: 10px;
        }
        
        .table-responsive {
            font-size: 12px;
        }
        
        .table th, .table td {
            padding: 8px 4px;
            white-space: nowrap;
        }
        
        .btn {
            padding: 8px 12px;
            font-size: 12px;
        }
        
        .form-control, .form-select {
            font-size: 14px;
            padding: 8px;
        }
        
        h2 {
            font-size: 1.5rem;
        }
        
        .d-flex.gap-2 {
            flex-direction: column;
            gap: 8px !important;
        }
        
        .d-flex.gap-2 .btn {
            width: 100%;
        }
    }
    
    @media (max-width: 576px) {
        .table-responsive {
            font-size: 11px;
        }
        
        .table th, .table td {
            padding: 6px 2px;
        }
        
        .btn {
            padding: 6px 8px;
            font-size: 11px;
        }
        
        h2 {
            font-size: 1.3rem;
        }
        
        .col-md-2, .col-md-3 {
            margin-bottom: 10px;
        }
    }
    
    /* Optimasi untuk tablet landscape */
    @media (min-width: 768px) and (max-width: 1024px) {
        .table-responsive {
            font-size: 13px;
        }
        
        .table th, .table td {
            padding: 10px 6px;
        }
        
        .btn {
            padding: 10px 16px;
            font-size: 13px;
        }
    }
    
    /* Smooth scrolling untuk table */
    .table-responsive {
        scroll-behavior: smooth;
    }
    
    /* Hover effect untuk table rows */
    .table-hover tbody tr:hover {
        background-color: rgba(0,123,255,0.1);
    }
    
    /* Sticky header untuk table */
    .table thead th {
        position: sticky;
        top: 0;
        background-color: #0008f9;
        color: #ffffff !important;
        z-index: 10;
        font-weight: bold;
    }
    
    /* Pastikan teks di dalam table header terlihat jelas */
    .table thead th small {
        color: #e0e0e0 !important;
    }
    
    /* Loading indicator */
    .loading {
        display: none;
        text-align: center;
        padding: 20px;
    }
    
    .loading.show {
        display: block;
    }
    
    .info-icon-svg {
        width: 18px;
        height: 18px;
        display: block;
    }

    .report-info-tooltip {
        position: relative;
        display: inline-flex;
        align-items: center;
        margin-left: 6px;
    }

    .report-info-tooltip button {
        line-height: 0;
    }

    .report-info-tooltip button:focus-visible {
        outline: 2px solid rgba(13, 202, 240, 0.6);
        outline-offset: 2px;
        border-radius: 6px;
    }

    .report-info-content {
        display: none;
        position: absolute;
        top: calc(100% + 10px);
        right: 0;
        z-index: 1050;
        width: 420px;
        max-width: 90vw;
        background: #ffffff;
        color: #212529;
        border: 1px solid rgba(0, 0, 0, 0.15);
        border-radius: 12px;
        box-shadow: 0 10px 24px rgba(0, 0, 0, 0.18);
        padding: 12px 14px;
        text-align: left;
        font-size: 13px;
        line-height: 1.45;
    }

    .report-info-tooltip:hover .report-info-content,
    .report-info-tooltip:focus-within .report-info-content {
        display: block;
    }

    .report-info-content::before {
        content: "";
        position: absolute;
        top: -7px;
        right: 16px;
        width: 14px;
        height: 14px;
        background: #ffffff;
        border-left: 1px solid rgba(0, 0, 0, 0.15);
        border-top: 1px solid rgba(0, 0, 0, 0.15);
        transform: rotate(45deg);
    }
</style>
<?php include '../templates/navbar.php'; ?>
<div class="container-fluid mt-3 mt-md-5">
    <div class="row mb-3">
        <div class="col-12">
            <h2 class="text-dark mb-3">
            <img src="../asset/cjawilnew.png" alt="Logo Cjawil" style="height:40px;">
                <i class="fas fa-chart-bar me-2"></i>
                Laporan Items Perbulan - <?= htmlspecialchars($bulan_label . ' ' . $tahun) ?> 
                <span class="badge bg-primary"><?= $nama_gudang ?></span>
                <span class="report-info-tooltip">
                    <button
                        type="button"
                        class="btn btn-sm p-0 border-0 bg-transparent text-info shadow-none"
                        aria-label="Informasi laporan"
                    >
                        <svg class="info-icon-svg" viewBox="0 0 16 16" aria-hidden="true" focusable="false">
                            <circle cx="8" cy="8" r="7" fill="none" stroke="currentColor" stroke-width="1.4"></circle>
                            <line x1="8" y1="7" x2="8" y2="12" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"></line>
                            <circle cx="8" cy="4.6" r="1" fill="currentColor"></circle>
                        </svg>
                    </button>
                    <div class="report-info-content">
                        <div class="fw-bold mb-2">Info Laporan Items Perbulan</div>
                        <div class="fw-bold">Cara pakai</div>
                        <div>1) Pilih Bulan, Tahun, dan Gudang.</div>
                        <div>2) (Opsional) isi Cari Item / centang filter transaksi.</div>
                        <div>3) Klik Tampilkan.</div>
                        <div>4) Gunakan Excel/Print/PDF untuk export.</div>
                        <div class="mt-2 fw-bold">Arti kolom</div>
                        <div><span class="fw-bold">Laporan Item Masuk PO</span>: jumlah item dari PO yang masuk gudang.</div>
                        <div><span class="fw-bold">Total Stok Masuk</span>: transaksi stok masuk (selain PO & Direct).</div>
                        <div><span class="fw-bold">Transfer Masuk</span>: transfer yang masuk ke gudang tujuan.</div>
                        <div><span class="fw-bold">Transfer Keluar</span>: transfer yang keluar dari gudang asal.</div>
                        <div><span class="fw-bold">Laporan Item Masuk Direct</span>: direct yang masuk gudang.</div>
                        <div><span class="fw-bold">Total</span>: (PO + Stok Masuk + Transfer Masuk + Direct) - (Stok Keluar + Transfer Keluar).</div>
                        <div class="text-muted mt-2">Catatan: Gudang hanya Antapani & Central.</div>
                    </div>
                </span>
            </h2>
        </div>
    </div>

    <!-- Loading indicator -->
    <div class="loading" id="loadingIndicator">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
        <p class="mt-2">Memuat data laporan...</p>
    </div>

    <form method="get" class="mb-4" id="filterForm">
        <div class="row g-3 align-items-end">
            <div class="col-12 col-md-6 col-lg-2">
                <label class="form-label fw-bold text-dark">
                    <i class="fas fa-calendar me-1"></i>Pilih Bulan
                </label>
                <select name="bulan" class="form-select form-select-sm">
                    <?php foreach ($bulan_list as $i => $nama_bulan): ?>
                        <?php $v = str_pad((string)$i, 2, '0', STR_PAD_LEFT); ?>
                        <option value="<?= $v ?>" <?= $bulan === $v ? 'selected' : '' ?>>
                            <?= htmlspecialchars($nama_bulan) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-6 col-lg-2">
                <label class="form-label fw-bold text-dark">
                    <i class="fas fa-calendar-year me-1"></i>Pilih Tahun
                </label>
                <select name="tahun" class="form-select form-select-sm">
                    <?php for ($y = 2020; $y <= date('Y'); $y++): ?>
                        <option value="<?= $y ?>" <?= $tahun == $y ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-12 col-md-6 col-lg-3">
                <label class="form-label fw-bold text-dark">
                    <i class="fas fa-warehouse me-1"></i>Pilih Gudang
                </label>
                <select name="gudang_id" class="form-select form-select-sm">
                    <?php foreach ($allowed_gudang as $gudang): ?>
                        <option value="<?= (int)$gudang['id'] ?>" <?= (int)$gudang_id === (int)$gudang['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($gudang['nama_gudang']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-6 col-lg-3">
                <label class="form-label fw-bold text-dark">
                    <i class="fas fa-search me-1"></i>Cari Item
                </label>
                <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" class="form-control form-control-sm" placeholder="Kode atau nama barang">
            </div>
            <div class="col-12 col-md-6 col-lg-2">
                <label class="form-label fw-bold text-dark">
                    <i class="fas fa-filter me-1"></i>Filter
                </label>
                <div class="form-check">
                    <input type="hidden" name="only_transaksi" value="0">
                    <input class="form-check-input" type="checkbox" name="only_transaksi" value="1" id="only_transaksi" <?= $only_transaksi ? 'checked' : '' ?>>
                    <label class="form-check-label" for="only_transaksi">
                        Hanya yang ada transaksi
                    </label>
                </div>
            </div>
            <div class="col-12 col-md-6 col-lg-2">
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1" onclick="showLoading()">
                        <i class="fas fa-search me-1"></i>Tampilkan
                    </button>
                </div>
            </div>
        </div>
        <div class="row g-3 mt-1">
            <div class="col-12">
                <div class="d-flex gap-2 flex-wrap">
                    <a href="laporan_perbulan_excel.php?<?= htmlspecialchars($export_query) ?>" class="btn btn-success btn-sm">
                        <i class="fas fa-download me-1"></i>Excel
                    </a>
                    <a href="laporan_perbulan_print.php?<?= htmlspecialchars($export_query) ?>" class="btn btn-info btn-sm" target="_blank">
                        <i class="fas fa-print me-1"></i>Print
                    </a>
                    <a href="laporan_perbulan_pdf.php?<?= htmlspecialchars($export_query) ?>" class="btn btn-danger btn-sm" target="_blank">
                        <i class="fas fa-file-pdf me-1"></i>PDF
                    </a>
                </div>
            </div>
        </div>
    </form>

    <div class="table-responsive shadow-lg rounded-4">
        <table class="table table-bordered table-striped table-hover">
            <thead class="table-light text-center">
                <tr>
                    <th class="text-dark">Kode Barang</th>
                    <th class="text-dark">Nama Barang</th>
                    <th class="text-dark">Laporan Item Masuk PO</th>
                    <th class="text-dark">Total Stok Masuk</th>
                    <th class="text-dark">Transfer Masuk</th>
                    <th class="text-dark">Transfer Keluar</th>
                    <th class="text-dark">Laporan Item Masuk Direct</th>
                    <th class="text-dark">Stok Keluar</th>
                    <th class="text-dark">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $sum_po = 0;
                $sum_masuk = 0;
                $sum_transfer_masuk = 0;
                $sum_transfer_keluar = 0;
                $sum_direct = 0;
                $sum_keluar = 0;
                $sum_total = 0;
                ?>
                <?php foreach ($all_items as $barang): ?>
                    <?php
                    $barang_id = (int)($barang['id'] ?? 0);
                    $v_po = (float)($data_po[$barang_id] ?? 0);
                    $v_masuk = (float)($data_masuk[$barang_id] ?? 0);
                    $v_transfer_masuk = (float)($data_transfer_masuk[$barang_id] ?? 0);
                    $v_transfer_keluar = (float)($data_transfer_keluar[$barang_id] ?? 0);
                    $v_direct = (float)($data_direct[$barang_id] ?? 0);
                    $v_keluar = (float)($data_keluar[$barang_id] ?? 0);
                    $v_total = ($v_po + $v_masuk + $v_transfer_masuk + $v_direct) - ($v_keluar + $v_transfer_keluar);

                    $sum_po += $v_po;
                    $sum_masuk += $v_masuk;
                    $sum_transfer_masuk += $v_transfer_masuk;
                    $sum_transfer_keluar += $v_transfer_keluar;
                    $sum_direct += $v_direct;
                    $sum_keluar += $v_keluar;
                    $sum_total += $v_total;
                    ?>
                    <tr>
                        <td class="text-dark fw-bold"><?= htmlspecialchars((string)($barang['kode_barang'] ?? '')) ?></td>
                        <td class="text-dark fw-bold"><?= htmlspecialchars((string)($barang['nama_barang'] ?? '')) ?></td>
                        <td class="text-end text-dark"><?= number_format($v_po, 0, ',', '.') ?></td>
                        <td class="text-end text-dark"><?= number_format($v_masuk, 0, ',', '.') ?></td>
                        <td class="text-end text-dark"><?= number_format($v_transfer_masuk, 0, ',', '.') ?></td>
                        <td class="text-end text-dark"><?= number_format($v_transfer_keluar, 0, ',', '.') ?></td>
                        <td class="text-end text-dark"><?= number_format($v_direct, 0, ',', '.') ?></td>
                        <td class="text-end text-dark"><?= number_format($v_keluar, 0, ',', '.') ?></td>
                        <td class="text-end text-dark fw-bold"><?= number_format($v_total, 0, ',', '.') ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($all_items)): ?>
                    <tr>
                        <td colspan="9" class="text-center text-muted py-4">
                            <i class="fas fa-inbox fa-2x mb-2"></i><br>
                            Tidak ada data barang sesuai filter.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
            <?php if (!empty($all_items)): ?>
                <tfoot>
                    <tr class="table-light">
                        <td class="text-dark fw-bold" colspan="2">TOTAL</td>
                        <td class="text-end text-dark fw-bold"><?= number_format($sum_po, 0, ',', '.') ?></td>
                        <td class="text-end text-dark fw-bold"><?= number_format($sum_masuk, 0, ',', '.') ?></td>
                        <td class="text-end text-dark fw-bold"><?= number_format($sum_transfer_masuk, 0, ',', '.') ?></td>
                        <td class="text-end text-dark fw-bold"><?= number_format($sum_transfer_keluar, 0, ',', '.') ?></td>
                        <td class="text-end text-dark fw-bold"><?= number_format($sum_direct, 0, ',', '.') ?></td>
                        <td class="text-end text-dark fw-bold"><?= number_format($sum_keluar, 0, ',', '.') ?></td>
                        <td class="text-end text-dark fw-bold"><?= number_format($sum_total, 0, ',', '.') ?></td>
                    </tr>
                </tfoot>
            <?php endif; ?>
        </table>
    </div>
</div>

<!-- JavaScript untuk loading indicator -->
<script>
function showLoading() {
    document.getElementById('loadingIndicator').classList.add('show');
}

// Auto-hide loading after page load
window.addEventListener('load', function() {
    setTimeout(function() {
        document.getElementById('loadingIndicator').classList.remove('show');
    }, 1000);
});

// Smooth scroll to top when form is submitted
document.getElementById('filterForm').addEventListener('submit', function() {
    window.scrollTo({top: 0, behavior: 'smooth'});
});
</script>

<?php include '../templates/footer.php'; ?>
