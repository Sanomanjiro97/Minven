<?php
session_start();
require_once '../config.php';
require_once '../libs/SimpleXLSXGen.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

require_once '../includes/page_access_check.php';

// Ambil bulan dan tahun
$bulan_raw = $_GET['bulan'] ?? date('m');
$tahun_raw = $_GET['tahun'] ?? date('Y');
$gudang_id_raw = $_GET['gudang_id'] ?? '';
$search_raw = $_GET['q'] ?? '';
$search = trim((string)$search_raw);
$only_transaksi = isset($_GET['only_transaksi']) && (string)$_GET['only_transaksi'] === '1';

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

$nama_gudang = "Semua Gudang";
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

foreach ($allowed_gudang as $g) {
    if ((int)$g['id'] === (int)$gudang_id) {
        $nama_gudang = $g['nama_gudang'];
        break;
    }
}

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
        
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $data[(int)$row['barang_id']] = (float)$row['total'];
            }
        }
        $stmt->close();
    }

    return $data;
}

// Ambil semua barang dari tabel barang yang memiliki aktivitas di gudang yang dipilih
$all_items_from_db = [];

if ($gudang_id) {
    // Jika ada filter gudang, ambil hanya barang yang memiliki aktivitas di gudang tersebut
    $sql_all_barang = "SELECT DISTINCT b.id, b.kode_barang, b.nama_barang 
                       FROM barang b
                       LEFT JOIN detail_transaksi_stok dts ON dts.barang_id = b.id
                       LEFT JOIN transaksi_stok ts ON ts.id = dts.transaksi_stok_id 
                           AND ts.gudang_id = ?
                           AND ts.tanggal BETWEEN ? AND ?
                       LEFT JOIN detail_transaksi_stock dtsx ON dtsx.barang_id = b.id
                       LEFT JOIN transaksi_stock tsx ON tsx.id = dtsx.transaksi_stock_id
                           AND tsx.gudang_id = ?
                           AND tsx.tanggal BETWEEN ? AND ?
                       LEFT JOIN detail_transaksi_transfer dtt ON dtt.barang_id = b.id
                       LEFT JOIN transaksi_transfer tt ON tt.id = dtt.transaksi_transfer_id 
                           AND tt.tanggal BETWEEN ? AND ?
                           AND (tt.gudang_asal_id = ? OR tt.gudang_tujuan_id = ?)
                       WHERE ts.id IS NOT NULL OR tt.id IS NOT NULL OR tsx.id IS NOT NULL
                       ORDER BY b.nama_barang, b.kode_barang";
    
    $stmt_barang = $conn->prepare($sql_all_barang);
    if ($stmt_barang) {
        $stmt_barang->bind_param("ississssii", $gudang_id, $start_date, $end_date, $gudang_id, $start_date, $end_date, $start_date, $end_date, $gudang_id, $gudang_id);
        $stmt_barang->execute();
        $result_all_barang = $stmt_barang->get_result();
        while ($row = $result_all_barang->fetch_assoc()) {
            $all_items_from_db[] = [
                'id' => (int)$row['id'],
                'kode_barang' => (string)($row['kode_barang'] ?? ''),
                'nama_barang' => (string)($row['nama_barang'] ?? ''),
            ];
        }
        $stmt_barang->close();
    }
} else {
    // Jika tidak ada filter gudang, ambil semua barang
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
}

// Ambil data
$data_po        = getData($conn, 'po', 'id_barang', 'jumlah', 'tanggal', $gudang_id);
$data_masuk     = getData($conn, 'stok_masuk', 'id_barang', 'jumlah', 'tanggal_masuk', $gudang_id);
$data_keluar    = getData($conn, 'stok_keluar', 'id_barang', 'jumlah', 'tanggal_keluar', $gudang_id);
$data_transfer_masuk  = getData($conn, 'stok_transfer_masuk', 'id_barang', 'jumlah', 'tanggal_transfer', $gudang_id);
$data_transfer_keluar = getData($conn, 'stok_transfer_keluar', 'id_barang', 'jumlah', 'tanggal_transfer', $gudang_id);
$data_direct    = getData($conn, 'pembelian_direct', 'id_barang', 'jumlah', 'tanggal', $gudang_id);

// Gabungkan nama barang
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

// Set header untuk download Excel
header('Content-Type: application/vnd.ms-excel');
$nama_gudang_safe = str_replace(' ', '_', (string)$nama_gudang);
$nama_gudang_safe = preg_replace('/[\\\\\\/:"*?<>|]+/', '_', $nama_gudang_safe);
header('Content-Disposition: attachment; filename="Laporan_Items_Perbulan_' . $bulan_label . '_' . $tahun . '_' . $nama_gudang_safe . '.xls"');
header('Cache-Control: max-age=0');
?>
<table border="1" style="width: 100%; border-collapse: collapse;">
    <tr style="background-color: #f0f0f0;">
        <td colspan="9" style="text-align: center; font-weight: bold; font-size: 16px; padding: 10px;">
            LAPORAN ITEMS PERBULAN - <?= strtoupper($bulan_label . ' ' . $tahun) ?> (<?= $nama_gudang ?>)
        </td>
    </tr>
    <tr style="background-color: #f8f9fa;">
        <td colspan="9" style="text-align: center; padding: 8px;">
            Periode: <?= date('d/m/Y', strtotime($start_date)) ?> - <?= date('d/m/Y', strtotime($end_date)) ?>
        </td>
    </tr>
    <tr></tr>
    <tr style="background-color: #e9ecef; font-weight: bold; text-align: center;">
        <td style="border: 1px solid #000; padding: 8px;">Kode Barang</td>
        <td style="border: 1px solid #000; padding: 8px;">Nama Barang</td>
        <td style="border: 1px solid #000; padding: 8px;">Laporan Item Masuk PO</td>
        <td style="border: 1px solid #000; padding: 8px;">Total Stok Masuk</td>
        <td style="border: 1px solid #000; padding: 8px;">Transfer Masuk</td>
        <td style="border: 1px solid #000; padding: 8px;">Transfer Keluar</td>
        <td style="border: 1px solid #000; padding: 8px;">Laporan Item Masuk Direct</td>
        <td style="border: 1px solid #000; padding: 8px;">Stok Keluar</td>
        <td style="border: 1px solid #000; padding: 8px;">Total</td>
    </tr>
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
            <td style="border: 1px solid #000; padding: 6px; font-weight: bold;"><?= htmlspecialchars((string)($barang['kode_barang'] ?? '')) ?></td>
            <td style="border: 1px solid #000; padding: 6px; font-weight: bold;"><?= htmlspecialchars((string)($barang['nama_barang'] ?? '')) ?></td>
            <td style="border: 1px solid #000; padding: 6px; text-align: right;"><?= number_format($v_po, 0, ',', '.') ?></td>
            <td style="border: 1px solid #000; padding: 6px; text-align: right;"><?= number_format($v_masuk, 0, ',', '.') ?></td>
            <td style="border: 1px solid #000; padding: 6px; text-align: right;"><?= number_format($v_transfer_masuk, 0, ',', '.') ?></td>
            <td style="border: 1px solid #000; padding: 6px; text-align: right;"><?= number_format($v_transfer_keluar, 0, ',', '.') ?></td>
            <td style="border: 1px solid #000; padding: 6px; text-align: right;"><?= number_format($v_direct, 0, ',', '.') ?></td>
            <td style="border: 1px solid #000; padding: 6px; text-align: right;"><?= number_format($v_keluar, 0, ',', '.') ?></td>
            <td style="border: 1px solid #000; padding: 6px; text-align: right; font-weight: bold;"><?= number_format($v_total, 0, ',', '.') ?></td>
        </tr>
    <?php endforeach; ?>
    <?php if (!empty($all_items)): ?>
        <tr style="background-color: #f8f9fa; font-weight: bold;">
            <td style="border: 1px solid #000; padding: 8px;" colspan="2">TOTAL</td>
            <td style="border: 1px solid #000; padding: 8px; text-align: right;"><?= number_format($sum_po, 0, ',', '.') ?></td>
            <td style="border: 1px solid #000; padding: 8px; text-align: right;"><?= number_format($sum_masuk, 0, ',', '.') ?></td>
            <td style="border: 1px solid #000; padding: 8px; text-align: right;"><?= number_format($sum_transfer_masuk, 0, ',', '.') ?></td>
            <td style="border: 1px solid #000; padding: 8px; text-align: right;"><?= number_format($sum_transfer_keluar, 0, ',', '.') ?></td>
            <td style="border: 1px solid #000; padding: 8px; text-align: right;"><?= number_format($sum_direct, 0, ',', '.') ?></td>
            <td style="border: 1px solid #000; padding: 8px; text-align: right;"><?= number_format($sum_keluar, 0, ',', '.') ?></td>
            <td style="border: 1px solid #000; padding: 8px; text-align: right;"><?= number_format($sum_total, 0, ',', '.') ?></td>
        </tr>
    <?php endif; ?>
    <?php if (empty($all_items)): ?>
        <tr>
            <td colspan="9" style="text-align: center; padding: 20px; font-style: italic;">
                Tidak ada data barang untuk bulan ini.
            </td>
        </tr>
    <?php endif; ?>
</table>
<?php exit(); // pastikan tidak ada output lain setelah table ?> 
