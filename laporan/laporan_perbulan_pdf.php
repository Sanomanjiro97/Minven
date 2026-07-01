<?php
require_once '../config.php';
require_once '../libs/fpdf/fpdf.php';

session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

require_once '../includes/page_access_check.php';

$bulan = $_GET['bulan'] ?? date('m');
$tahun = $_GET['tahun'] ?? date('Y');
$gudang_id_raw = $_GET['gudang_id'] ?? '';
$search_raw = $_GET['q'] ?? '';
$search = trim((string)$search_raw);
$only_transaksi = isset($_GET['only_transaksi']) && (string)$_GET['only_transaksi'] === '1';

$bulan_int = (int)$bulan;
if ($bulan_int < 1 || $bulan_int > 12) $bulan_int = (int)date('n');
$bulan = str_pad((string)$bulan_int, 2, '0', STR_PAD_LEFT);

$tahun_int = (int)$tahun;
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

function getData($conn, $table, $gudang_id = '') {
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
                            AND ts.tanggal BETWEEN ? AND ? " . ($gudang_id !== '' ? "AND ts.gudang_id = ? " : "") . "
                        GROUP BY dts.barang_id
                    ) x ON x.barang_id = b.id
                    GROUP BY b.id";
            if ($gudang_id !== '') {
                $params = [$start_date, $end_date, $gudang_id];
                $types = "ssi";
            } else {
                $params = [$start_date, $end_date];
                $types = "ss";
            }
            break;
        case 'stok_masuk':
            $sql = "SELECT b.id AS barang_id, COALESCE(SUM(x.jumlah), 0) as total
                    FROM barang b
                    LEFT JOIN (
                        SELECT dts.barang_id, dts.jumlah as jumlah
                        FROM detail_transaksi_stok dts
                        JOIN transaksi_stok ts ON ts.id = dts.transaksi_stok_id
                        WHERE ts.jenis_transaksi = 'masuk'
                            AND ts.tanggal BETWEEN ? AND ? " . ($gudang_id !== '' ? "AND ts.gudang_id = ? " : "") . "
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
                            AND gsh.created_at >= ? AND gsh.created_at < ? " . ($gudang_id !== '' ? "AND gsh.gudang_id = ? " : "") . "
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
            if ($gudang_id !== '') {
                $params = [$start_date, $end_date, $gudang_id, $start_dt, $end_dt_excl, $gudang_id];
                $types = "ssissi";
            } else {
                $params = [$start_date, $end_date, $start_dt, $end_dt_excl];
                $types = "ssss";
            }
            break;
        case 'stok_keluar':
            $sql = "SELECT b.id AS barang_id, COALESCE(SUM(CASE 
                        WHEN ts.jenis_transaksi = 'keluar'
                            AND ts.tanggal BETWEEN ? AND ? " . ($gudang_id !== '' ? "AND ts.gudang_id = ? " : "") . "
                        THEN dts.jumlah ELSE 0 END
                    ), 0) as total 
                    FROM barang b
                    LEFT JOIN detail_transaksi_stok dts ON dts.barang_id = b.id
                    LEFT JOIN transaksi_stok ts ON ts.id = dts.transaksi_stok_id
                    GROUP BY b.id";
            if ($gudang_id !== '') {
                $params = [$start_date, $end_date, $gudang_id];
                $types = "ssi";
            } else {
                $params = [$start_date, $end_date];
                $types = "ss";
            }
            break;
        case 'stok_transfer':
            $sql = "SELECT b.id AS barang_id, COALESCE(SUM(CASE 
                        WHEN tt.tanggal BETWEEN ? AND ? " . ($gudang_id !== '' ? "AND tt.gudang_tujuan_id = ? " : "") . "
                        THEN dtt.jumlah ELSE 0 END
                    ), 0) as total 
                    FROM barang b
                    LEFT JOIN detail_transaksi_transfer dtt ON dtt.barang_id = b.id
                    LEFT JOIN transaksi_transfer tt ON tt.id = dtt.transaksi_transfer_id
                    GROUP BY b.id";
            if ($gudang_id !== '') {
                $params = [$start_date, $end_date, $gudang_id];
                $types = "ssi";
            } else {
                $params = [$start_date, $end_date];
                $types = "ss";
            }
            break;
        case 'stok_transfer_masuk':
            $sql = "SELECT b.id AS barang_id, COALESCE(SUM(CASE 
                        WHEN tt.tanggal BETWEEN ? AND ? " . ($gudang_id !== '' ? "AND tt.gudang_tujuan_id = ? " : "") . "
                        THEN dtt.jumlah ELSE 0 END
                    ), 0) as total 
                    FROM barang b
                    LEFT JOIN detail_transaksi_transfer dtt ON dtt.barang_id = b.id
                    LEFT JOIN transaksi_transfer tt ON tt.id = dtt.transaksi_transfer_id
                    GROUP BY b.id";
            if ($gudang_id !== '') {
                $params = [$start_date, $end_date, $gudang_id];
                $types = "ssi";
            } else {
                $params = [$start_date, $end_date];
                $types = "ss";
            }
            break;
        case 'stok_transfer_keluar':
            $sql = "SELECT b.id AS barang_id, COALESCE(SUM(CASE 
                        WHEN tt.tanggal BETWEEN ? AND ? " . ($gudang_id !== '' ? "AND tt.gudang_asal_id = ? " : "") . "
                        THEN dtt.jumlah ELSE 0 END
                    ), 0) as total 
                    FROM barang b
                    LEFT JOIN detail_transaksi_transfer dtt ON dtt.barang_id = b.id
                    LEFT JOIN transaksi_transfer tt ON tt.id = dtt.transaksi_transfer_id
                    GROUP BY b.id";
            if ($gudang_id !== '') {
                $params = [$start_date, $end_date, $gudang_id];
                $types = "ssi";
            } else {
                $params = [$start_date, $end_date];
                $types = "ss";
            }
            break;
        case 'pembelian_direct':
            $sql = "SELECT b.id AS barang_id, COALESCE(SUM(CASE WHEN ts.tanggal BETWEEN ? AND ? THEN dts.jumlah ELSE 0 END), 0) as total
                    FROM barang b
                    LEFT JOIN detail_transaksi_stock dts ON dts.barang_id = b.id
                    LEFT JOIN transaksi_stock ts ON ts.id = dts.transaksi_stock_id
                        AND ts.keterangan LIKE 'Dari pembelian mendadak:%' " . ($gudang_id !== '' ? "AND ts.gudang_id = ? " : "") . "
                    GROUP BY b.id";
            if ($gudang_id !== '') {
                $params = [$start_date, $end_date, $gudang_id];
                $types = "ssi";
            } else {
                $params = [$start_date, $end_date];
                $types = "ss";
            }
            break;
        default:
            return $data;
    }
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $data[(int)$row['barang_id']] = (float)$row['total'];
        }
        $stmt->close();
    }
    return $data;
}

// Ambil semua barang dari tabel barang yang memiliki aktivitas di gudang yang dipilih
$all_items_from_db = [];
if ($gudang_id) {
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

$data_po        = getData($conn, 'po', $gudang_id);
$data_masuk     = getData($conn, 'stok_masuk', $gudang_id);
$data_keluar    = getData($conn, 'stok_keluar', $gudang_id);
$data_transfer_masuk  = getData($conn, 'stok_transfer_masuk', $gudang_id);
$data_transfer_keluar = getData($conn, 'stok_transfer_keluar', $gudang_id);
$data_direct    = getData($conn, 'pembelian_direct', $gudang_id);
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

$pdf = new FPDF('L', 'mm', 'A4');
$pdf->AddPage();
$pdf->Image('../asset/cjawilnew.png', 10, 8, 40);
$pdf->SetFont('Arial', 'B', 16);
$pdf->Cell(0, 10, 'LAPORAN ITEMS PERBULAN - ' . strtoupper($bulan_label . ' ' . $tahun), 0, 1, 'C');
$pdf->SetFont('Arial', '', 12);
$pdf->Cell(0, 8, $nama_gudang, 0, 1, 'C');
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(0, 6, 'Periode: ' . date('d/m/Y', strtotime($start_date)) . ' - ' . date('d/m/Y', strtotime($end_date)), 0, 1, 'C');
$pdf->SetFont('Arial', '', 9);
$pdf->Cell(0, 5, 'Total = (Item Masuk PO + Total Stok Masuk + Transfer Masuk + Item Masuk Direct) - (Stok Keluar + Transfer Keluar)', 0, 1, 'C');
$pdf->Ln(4);

// Table header
$pdf->SetFont('Arial', 'B', 10);
$pdf->SetFillColor(220, 220, 220);
$pdf->Cell(10, 8, 'No', 1, 0, 'C', true);
$pdf->Cell(25, 8, 'Kode', 1, 0, 'C', true);
$pdf->Cell(70, 8, 'Nama Barang', 1, 0, 'C', true);
$pdf->Cell(24, 8, 'Item Masuk PO', 1, 0, 'C', true);
$pdf->Cell(24, 8, 'Stok Masuk', 1, 0, 'C', true);
$pdf->Cell(24, 8, 'Transfer Masuk', 1, 0, 'C', true);
$pdf->Cell(24, 8, 'Transfer Keluar', 1, 0, 'C', true);
$pdf->Cell(24, 8, 'Item Masuk Direct', 1, 0, 'C', true);
$pdf->Cell(24, 8, 'Stok Keluar', 1, 0, 'C', true);
$pdf->Cell(24, 8, 'Total', 1, 1, 'C', true);

$pdf->SetFont('Arial', '', 10);
$no = 1;
foreach ($all_items as $barang) {
    $barang_id = (int)($barang['id'] ?? 0);
    $v_po = (float)($data_po[$barang_id] ?? 0);
    $v_masuk = (float)($data_masuk[$barang_id] ?? 0);
    $v_transfer_masuk = (float)($data_transfer_masuk[$barang_id] ?? 0);
    $v_transfer_keluar = (float)($data_transfer_keluar[$barang_id] ?? 0);
    $v_direct = (float)($data_direct[$barang_id] ?? 0);
    $v_keluar = (float)($data_keluar[$barang_id] ?? 0);
    $v_total = ($v_po + $v_masuk + $v_transfer_masuk + $v_direct) - ($v_keluar + $v_transfer_keluar);

    $pdf->Cell(10, 8, $no++, 1, 0, 'C');
    $pdf->Cell(25, 8, (string)($barang['kode_barang'] ?? ''), 1, 0);
    $pdf->Cell(70, 8, (string)($barang['nama_barang'] ?? ''), 1, 0);
    $pdf->Cell(24, 8, number_format($v_po, 0, ',', '.'), 1, 0, 'R');
    $pdf->Cell(24, 8, number_format($v_masuk, 0, ',', '.'), 1, 0, 'R');
    $pdf->Cell(24, 8, number_format($v_transfer_masuk, 0, ',', '.'), 1, 0, 'R');
    $pdf->Cell(24, 8, number_format($v_transfer_keluar, 0, ',', '.'), 1, 0, 'R');
    $pdf->Cell(24, 8, number_format($v_direct, 0, ',', '.'), 1, 0, 'R');
    $pdf->Cell(24, 8, number_format($v_keluar, 0, ',', '.'), 1, 0, 'R');
    $pdf->Cell(24, 8, number_format($v_total, 0, ',', '.'), 1, 1, 'R');
}
if (empty($all_items)) {
    $pdf->Cell(273, 10, 'Tidak ada data barang untuk bulan ini.', 1, 1, 'C');
}
$pdf->Ln(10);
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(0, 8, date('d/m/Y') . "  |  Mengetahui,", 0, 1, 'R');
$pdf->Ln(15);
$pdf->Cell(0, 8, '(____________________)', 0, 1, 'R');
$nama_gudang_safe = str_replace(' ', '_', (string)$nama_gudang);
$nama_gudang_safe = preg_replace('/[\\\\\\/:"*?<>|]+/', '_', $nama_gudang_safe);
$pdf->Output('I', 'Laporan_Items_Perbulan_' . $bulan_label . '_' . $tahun . '_' . $nama_gudang_safe . '.pdf');
exit; 
