<?php
session_start();
require_once '../config.php';
require_once '../includes/access_check.php';

// Check access control
if (!isset($_SESSION['user_id'])) {
    header('Location: ../unauthorized.php');
    exit();
}

// Check if user has access to laporan_stok menu
if (!checkAccess('laporan_stok', 'view')) {
    header('Location: ../unauthorized.php');
    exit();
}

function normalizeDateYmd($value, $fallback) {
    if (!is_string($value) || $value === '') {
        return $fallback;
    }
    $dt = DateTime::createFromFormat('Y-m-d', $value);
    if ($dt === false) {
        return $fallback;
    }
    $errors = DateTime::getLastErrors();
    if (is_array($errors) && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) {
        return $fallback;
    }
    return $dt->format('Y-m-d');
}

$view_type = $_GET['view_type'] ?? '';
if ($view_type !== '') {
    $start_date = normalizeDateYmd($_GET['start_date'] ?? '', date('Y-m-01'));
    $end_date = normalizeDateYmd($_GET['end_date'] ?? '', date('Y-m-d'));
    if ($start_date > $end_date) {
        $tmp = $start_date;
        $start_date = $end_date;
        $end_date = $tmp;
    }
    $start_dt = $start_date . ' 00:00:00';
    $start_dt_excl = date('Y-m-d', strtotime($start_date . ' +1 day')) . ' 00:00:00';
    $end_dt_excl = date('Y-m-d', strtotime($end_date . ' +1 day')) . ' 00:00:00';

    $allowed_periods = ['daily', 'weekly', 'monthly', 'yearly'];
    $period = $_GET['period'] ?? 'daily';
    if (!in_array($period, $allowed_periods, true)) {
        $period = 'daily';
    }

    $gudang_id_raw = $_GET['gudang_id'] ?? '';
    $gudang_id = $gudang_id_raw !== '' ? (int)$gudang_id_raw : '';
    $jenis_perubahan = $_GET['jenis_perubahan'] ?? '';

    $allowed_view_types = ['current', 'history'];
    if (!in_array($view_type, $allowed_view_types, true)) {
        $view_type = 'current';
    }

    $allowed_history_modes = ['detail', 'period'];
    $history_mode = $_GET['history_mode'] ?? 'detail';
    if (!in_array($history_mode, $allowed_history_modes, true)) {
        $history_mode = 'detail';
    }

    $params = [];
    $types = "";

    if ($view_type === 'history') {
        $history_filters_sql = "";
        if ($gudang_id !== '') {
            $history_filters_sql .= " AND gsh.gudang_id = ?";
            $params[] = $gudang_id;
            $types .= "i";
        }
        if ($jenis_perubahan !== '') {
            $history_filters_sql .= " AND gsh.jenis_perubahan = ?";
            $params[] = $jenis_perubahan;
            $types .= "s";
        }
        $history_filters_sql .= " AND gsh.created_at >= ? AND gsh.created_at < ?";
        $params[] = $start_dt;
        $params[] = $end_dt_excl;
        $types .= "ss";

        if ($history_mode === 'period') {
            $period_expr = "DATE(gsh.created_at)";
            if ($period === 'weekly') {
                $period_expr = "CONCAT(YEAR(gsh.created_at), '-W', LPAD(WEEK(gsh.created_at, 1), 2, '0'))";
            } elseif ($period === 'monthly') {
                $period_expr = "DATE_FORMAT(gsh.created_at, '%Y-%m')";
            } elseif ($period === 'yearly') {
                $period_expr = "DATE_FORMAT(gsh.created_at, '%Y')";
            }

            $sql = "SELECT
                        grp.period_label,
                        g.nama_gudang,
                        b.kode_barang,
                        b.nama_barang,
                        k.nama_kategori,
                        s.nama_satuan,
                        gsh_first.stok_sisa_sebelum AS saldo_awal,
                        grp.masuk,
                        grp.keluar,
                        gsh_last.stok_sisa_sesudah AS saldo_akhir
                    FROM (
                        SELECT
                            $period_expr AS period_label,
                            gsh.gudang_id,
                            gsh.barang_id,
                            MIN(gsh.id) AS first_id,
                            MAX(gsh.id) AS last_id,
                            COALESCE(SUM(CASE WHEN gsh.jenis_perubahan IN ('masuk', 'transfer_in') THEN gsh.jumlah_perubahan ELSE 0 END), 0) AS masuk,
                            COALESCE(SUM(CASE WHEN gsh.jenis_perubahan IN ('keluar', 'transfer_out') THEN ABS(gsh.jumlah_perubahan) ELSE 0 END), 0) AS keluar
                        FROM gudang_stok_history gsh
                        WHERE 1=1
                        $history_filters_sql
                        GROUP BY $period_expr, gsh.gudang_id, gsh.barang_id
                    ) grp
                    LEFT JOIN gudang_stok_history gsh_first ON gsh_first.id = grp.first_id
                    LEFT JOIN gudang_stok_history gsh_last ON gsh_last.id = grp.last_id
                    LEFT JOIN barang b ON grp.barang_id = b.id
                    LEFT JOIN kategori k ON b.kategori_id = k.id
                    LEFT JOIN satuan s ON b.satuan_id = s.id
                    LEFT JOIN gudang g ON grp.gudang_id = g.id
                    ORDER BY grp.period_label DESC, b.nama_barang, g.nama_gudang";
        } else {
            $sql = "SELECT 
                        gsh.created_at as tanggal_perubahan,
                        b.kode_barang,
                        b.nama_barang,
                        k.nama_kategori,
                        s.nama_satuan,
                        gsh.stok_awal_sebelum,
                        gsh.stok_awal_sesudah,
                        gsh.stok_terpakai_sebelum,
                        gsh.stok_terpakai_sesudah,
                        gsh.stok_sisa_sebelum,
                        gsh.stok_sisa_sesudah,
                        gsh.jenis_perubahan,
                        gsh.jumlah_perubahan,
                        gsh.keterangan,
                        u.nama as updated_by,
                        g.nama_gudang
                    FROM gudang_stok_history gsh
                    LEFT JOIN barang b ON gsh.barang_id = b.id
                    LEFT JOIN kategori k ON b.kategori_id = k.id
                    LEFT JOIN satuan s ON b.satuan_id = s.id
                    LEFT JOIN users u ON gsh.created_by = u.id
                    LEFT JOIN gudang g ON gsh.gudang_id = g.id
                    WHERE 1=1
                    $history_filters_sql
                    ORDER BY gsh.created_at DESC, b.nama_barang, g.nama_gudang";
        }
    } else {
        $sql = "SELECT 
                    b.kode_barang,
                    b.nama_barang,
                    k.nama_kategori,
                    s.nama_satuan,
                    COALESCE(gsh_end.stok_awal_sesudah, gs.stok_awal) AS stok_awal,
                    COALESCE(gsh_end.stok_terpakai_sesudah, gs.stok_terpakai) AS stok_terpakai,
                    COALESCE(gsh_end.stok_sisa_sesudah, (gs.stok_awal - gs.stok_terpakai)) AS stok_akhir,
                    COALESCE(gsh_start.stok_sisa_sesudah, COALESCE(gsh_end.stok_sisa_sesudah, (gs.stok_awal - gs.stok_terpakai))) AS stok_awal_periode,
                    (
                        COALESCE(gsh_end.stok_sisa_sesudah, (gs.stok_awal - gs.stok_terpakai))
                        - COALESCE(gsh_start.stok_sisa_sesudah, COALESCE(gsh_end.stok_sisa_sesudah, (gs.stok_awal - gs.stok_terpakai)))
                    ) AS perubahan_periode,
                    gs.stok_minimum,
                    COALESCE(gsh_end.created_at, gsh_start.created_at, gs.updated_at) AS updated_at,
                    COALESCE(u_hist.nama, u.nama) AS updated_by,
                    g.nama_gudang
                FROM gudang_stok gs
                LEFT JOIN (
                    SELECT gudang_id, barang_id, MAX(id) AS last_id
                    FROM gudang_stok_history
                    WHERE created_at < ?
                    GROUP BY gudang_id, barang_id
                ) last_hist_start ON last_hist_start.gudang_id = gs.gudang_id AND last_hist_start.barang_id = gs.barang_id
                LEFT JOIN gudang_stok_history gsh_start ON gsh_start.id = last_hist_start.last_id
                LEFT JOIN (
                    SELECT gudang_id, barang_id, MAX(id) AS last_id
                    FROM gudang_stok_history
                    WHERE created_at < ?
                    GROUP BY gudang_id, barang_id
                ) last_hist_end ON last_hist_end.gudang_id = gs.gudang_id AND last_hist_end.barang_id = gs.barang_id
                LEFT JOIN gudang_stok_history gsh_end ON gsh_end.id = last_hist_end.last_id
                LEFT JOIN barang b ON gs.barang_id = b.id
                LEFT JOIN kategori k ON b.kategori_id = k.id
                LEFT JOIN satuan s ON b.satuan_id = s.id
                LEFT JOIN users u ON gs.modified_by = u.id
                LEFT JOIN users u_hist ON gsh_end.created_by = u_hist.id
                LEFT JOIN gudang g ON gs.gudang_id = g.id
                WHERE 1=1";

        $params[] = $start_dt_excl;
        $types .= "s";
        $params[] = $end_dt_excl;
        $types .= "s";

        if ($gudang_id !== '') {
            $sql .= " AND gs.gudang_id = ?";
            $params[] = $gudang_id;
            $types .= "i";
        }

        $sql .= " AND (last_hist_end.last_id IS NOT NULL OR gs.updated_at < ?)";
        $params[] = $end_dt_excl;
        $types .= "s";

        $sql .= " ORDER BY b.nama_barang, g.nama_gudang";
    }

    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        die("Error preparing query: " . $conn->error);
    }
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    $filter_desc = "Tampilan: " . ($view_type === 'history' ? 'History Perubahan' : 'Stok Saat Ini');
    if ($view_type === 'history') {
        $filter_desc .= " | Mode: " . ($history_mode === 'period' ? 'Rekap per Periode' : 'Detail');
    }
    $filter_desc .= " | Periode: $period | Tanggal: $start_date s/d $end_date";
    if ($gudang_id !== '') {
        $gudang_name_row = $conn->query("SELECT nama_gudang FROM gudang WHERE id = " . (int)$gudang_id)->fetch_assoc();
        if ($gudang_name_row && isset($gudang_name_row['nama_gudang'])) {
            $filter_desc .= " | Gudang: " . $gudang_name_row['nama_gudang'];
        }
    }
    if ($view_type === 'history' && $jenis_perubahan !== '') {
        $filter_desc .= " | Jenis: " . $jenis_perubahan;
    }

    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Cetak Laporan Stok Gudang</title>
        <style>
            @media print {
                body { font-family: Arial, sans-serif; margin: 20px; font-size: 12px; }
                .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #333; padding-bottom: 10px; }
                table { width: 100%; border-collapse: collapse; margin-top: 10px; }
                th, td { border: 1px solid #ddd; padding: 6px; text-align: left; }
                th { background-color: #f5f5f5; font-weight: bold; }
                .no-print { display: none; }
            }
            @media screen {
                body { font-family: Arial, sans-serif; margin: 20px; }
                .print-controls { margin-bottom: 20px; padding: 10px; background-color: #f8f9fa; border-radius: 5px; }
            }
        </style>
    </head>
    <body>
        <div class="print-controls no-print">
            <button onclick="window.print()" style="padding: 10px 20px; background-color: #007bff; color: white; border: none; border-radius: 5px; cursor: pointer;">Cetak Laporan</button>
            <button onclick="window.close()" style="padding: 10px 20px; background-color: #dc3545; color: white; border: none; border-radius: 5px; cursor: pointer; margin-left: 10px;">Tutup</button>
        </div>

        <div class="header">
            <div style="font-size: 18px; font-weight: bold;">MINVEN INVENTORY SYSTEM</div>
            <div style="font-size: 16px; margin: 5px 0;">LAPORAN STOK GUDANG</div>
            <div style="font-size: 11px; color: #666;">Tanggal: <?php echo date('d/m/Y H:i:s'); ?></div>
            <div style="font-size: 11px; color: #666; margin-top: 6px;"><?php echo htmlspecialchars($filter_desc); ?></div>
        </div>

        <table>
            <thead>
            <?php if ($view_type === 'history' && $history_mode === 'period'): ?>
                <tr>
                    <th>No</th>
                    <th>Periode</th>
                    <th>Gudang</th>
                    <th>Kode Barang</th>
                    <th>Nama Barang</th>
                    <th>Kategori</th>
                    <th>Satuan</th>
                    <th>Saldo Awal</th>
                    <th>Masuk</th>
                    <th>Keluar</th>
                    <th>Saldo Akhir</th>
                    <th>Perubahan</th>
                </tr>
            <?php elseif ($view_type === 'history'): ?>
                <tr>
                    <th>No</th>
                    <th>Tanggal Perubahan</th>
                    <th>Gudang</th>
                    <th>Kode Barang</th>
                    <th>Nama Barang</th>
                    <th>Kategori</th>
                    <th>Jenis</th>
                    <th>Stok Awal (Sebelum)</th>
                    <th>Stok Awal (Sesudah)</th>
                    <th>Terpakai (Sebelum)</th>
                    <th>Terpakai (Sesudah)</th>
                    <th>Sisa (Sebelum)</th>
                    <th>Sisa (Sesudah)</th>
                    <th>Jumlah Perubahan</th>
                    <th>Keterangan</th>
                    <th>Updated By</th>
                </tr>
            <?php else: ?>
                <tr>
                    <th>No</th>
                    <th>Tanggal</th>
                    <th>Gudang</th>
                    <th>Kode Barang</th>
                    <th>Nama Barang</th>
                    <th>Kategori</th>
                    <th>Satuan</th>
                    <th>Stok Awal Periode</th>
                    <th>Stok Awal</th>
                    <th>Stok Terpakai</th>
                    <th>Stok Akhir</th>
                    <th>Perubahan Periode</th>
                    <th>Stok Minimum</th>
                    <th>Updated By</th>
                </tr>
            <?php endif; ?>
            </thead>
            <tbody>
            <?php
            $no = 1;
            while ($row = $result->fetch_assoc()):
                if ($view_type === 'history' && $history_mode === 'period') {
                    $saldo_awal = (float)($row['saldo_awal'] ?? 0);
                    $saldo_akhir = (float)($row['saldo_akhir'] ?? 0);
                    $perubahan = $saldo_akhir - $saldo_awal;
                    ?>
                    <tr>
                        <td><?php echo $no++; ?></td>
                        <td><?php echo htmlspecialchars($row['period_label']); ?></td>
                        <td><?php echo htmlspecialchars($row['nama_gudang']); ?></td>
                        <td><?php echo htmlspecialchars($row['kode_barang']); ?></td>
                        <td><?php echo htmlspecialchars($row['nama_barang']); ?></td>
                        <td><?php echo htmlspecialchars($row['nama_kategori']); ?></td>
                        <td><?php echo htmlspecialchars($row['nama_satuan']); ?></td>
                        <td style="text-align:right;"><?php echo number_format($saldo_awal); ?></td>
                        <td style="text-align:right;"><?php echo number_format((float)($row['masuk'] ?? 0)); ?></td>
                        <td style="text-align:right;"><?php echo number_format((float)($row['keluar'] ?? 0)); ?></td>
                        <td style="text-align:right;font-weight:bold;"><?php echo number_format($saldo_akhir); ?></td>
                        <td style="text-align:right;"><?php echo ($perubahan > 0 ? '+' : '') . number_format($perubahan); ?></td>
                    </tr>
                    <?php
                } elseif ($view_type === 'history') {
                    ?>
                    <tr>
                        <td><?php echo $no++; ?></td>
                        <td><?php echo htmlspecialchars($row['tanggal_perubahan']); ?></td>
                        <td><?php echo htmlspecialchars($row['nama_gudang']); ?></td>
                        <td><?php echo htmlspecialchars($row['kode_barang']); ?></td>
                        <td><?php echo htmlspecialchars($row['nama_barang']); ?></td>
                        <td><?php echo htmlspecialchars($row['nama_kategori']); ?></td>
                        <td><?php echo htmlspecialchars($row['jenis_perubahan']); ?></td>
                        <td style="text-align:right;"><?php echo number_format((float)$row['stok_awal_sebelum']); ?></td>
                        <td style="text-align:right;"><?php echo number_format((float)$row['stok_awal_sesudah']); ?></td>
                        <td style="text-align:right;"><?php echo number_format((float)$row['stok_terpakai_sebelum']); ?></td>
                        <td style="text-align:right;"><?php echo number_format((float)$row['stok_terpakai_sesudah']); ?></td>
                        <td style="text-align:right;"><?php echo number_format((float)$row['stok_sisa_sebelum']); ?></td>
                        <td style="text-align:right;"><?php echo number_format((float)$row['stok_sisa_sesudah']); ?></td>
                        <td style="text-align:right;"><?php echo number_format((float)$row['jumlah_perubahan']); ?></td>
                        <td><?php echo htmlspecialchars($row['keterangan']); ?></td>
                        <td><?php echo htmlspecialchars($row['updated_by'] ?? 'N/A'); ?></td>
                    </tr>
                    <?php
                } else {
                    $perubahan_periode = (float)($row['perubahan_periode'] ?? 0);
                    ?>
                    <tr>
                        <td><?php echo $no++; ?></td>
                        <td>
                            <?php if ($start_date === $end_date): ?>
                                <?php echo htmlspecialchars($end_date); ?>
                            <?php else: ?>
                                <?php echo htmlspecialchars($start_date . ' - ' . $end_date); ?>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($row['nama_gudang']); ?></td>
                        <td><?php echo htmlspecialchars($row['kode_barang']); ?></td>
                        <td><?php echo htmlspecialchars($row['nama_barang']); ?></td>
                        <td><?php echo htmlspecialchars($row['nama_kategori']); ?></td>
                        <td><?php echo htmlspecialchars($row['nama_satuan']); ?></td>
                        <td style="text-align:right;"><?php echo number_format((float)($row['stok_awal_periode'] ?? 0)); ?></td>
                        <td style="text-align:right;"><?php echo number_format((float)$row['stok_awal']); ?></td>
                        <td style="text-align:right;"><?php echo number_format((float)$row['stok_terpakai']); ?></td>
                        <td style="text-align:right;font-weight:bold;"><?php echo number_format((float)$row['stok_akhir']); ?></td>
                        <td style="text-align:right;">
                            <?php if ($perubahan_periode > 0): ?>
                                +<?php echo number_format($perubahan_periode); ?>
                            <?php else: ?>
                                <?php echo number_format($perubahan_periode); ?>
                            <?php endif; ?>
                        </td>
                        <td style="text-align:right;"><?php echo number_format((float)$row['stok_minimum']); ?></td>
                        <td><?php echo htmlspecialchars($row['updated_by'] ?? 'N/A'); ?></td>
                    </tr>
                    <?php
                }
            endwhile;
            ?>
            </tbody>
        </table>

        <script>
            window.onload = function() { window.print(); };
            window.onafterprint = function() { setTimeout(function() { window.close(); }, 1000); };
        </script>
    </body>
    </html>
    <?php
    $stmt->close();
    $conn->close();
    exit();
}

// Get filter parameters
$gudang_id = isset($_GET['gudang_id']) ? intval($_GET['gudang_id']) : '';
$kategori_id = isset($_GET['kategori_id']) ? intval($_GET['kategori_id']) : '';
$stok_minimum = isset($_GET['stok_minimum']) ? $_GET['stok_minimum'] : '';

// Build query
$query = "SELECT 
    g.nama_gudang,
    g.kode_gudang,
    b.kode_barang,
    b.nama_barang,
    b.stok_minimum,
    k.nama_kategori,
    s.nama_satuan,
    gs.stok_awal,
    gs.stok_terpakai,
    gs.stok_sisa,
    gs.expire_date,
    gs.batch_number,
    gs.harga_beli,
    CASE 
        WHEN gs.stok_sisa = 0 THEN 'HABIS'
        WHEN gs.stok_sisa <= b.stok_minimum THEN 'MINIMUM'
        ELSE 'AMAN'
    END as status_stok
FROM gudang_stok gs
LEFT JOIN gudang g ON gs.gudang_id = g.id
LEFT JOIN barang b ON gs.barang_id = b.id
LEFT JOIN kategori k ON b.kategori_id = k.id
LEFT JOIN satuan s ON b.satuan_id = s.id
WHERE g.status = 'aktif'";

// Add filters
if (!empty($gudang_id)) {
    $query .= " AND gs.gudang_id = $gudang_id";
}
if (!empty($kategori_id)) {
    $query .= " AND b.kategori_id = $kategori_id";
}
if ($stok_minimum === 'low') {
    $query .= " AND gs.stok_sisa <= b.stok_minimum";
} elseif ($stok_minimum === 'critical') {
    $query .= " AND gs.stok_sisa <= (b.stok_minimum * 0.5)";
}

$query .= " ORDER BY g.nama_gudang, b.nama_barang";

$result = $conn->query($query);

// Get filter descriptions for header
$filter_desc = "";
if (!empty($gudang_id)) {
    $gudang_name = $conn->query("SELECT nama_gudang FROM gudang WHERE id = $gudang_id")->fetch_assoc()['nama_gudang'];
    $filter_desc .= "Gudang: $gudang_name, ";
}
if (!empty($kategori_id)) {
    $kategori_name = $conn->query("SELECT nama_kategori FROM kategori WHERE id = $kategori_id")->fetch_assoc()['nama_kategori'];
    $filter_desc .= "Kategori: $kategori_name, ";
}
if (!empty($stok_minimum)) {
    $filter_desc .= "Status: " . ($stok_minimum == 'low' ? 'Stok Minimum' : 'Stok Kritis') . ", ";
}
$filter_desc = rtrim($filter_desc, ', ');

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cetak Laporan Stok Gudang</title>
    <style>
        @media print {
            body {
                font-family: Arial, sans-serif;
                margin: 20px;
                font-size: 12px;
            }
            .header {
                text-align: center;
                margin-bottom: 20px;
                border-bottom: 2px solid #333;
                padding-bottom: 10px;
            }
            .company-name {
                font-size: 18px;
                font-weight: bold;
            }
            .report-title {
                font-size: 16px;
                margin: 5px 0;
            }
            .filter-info {
                font-size: 11px;
                color: #666;
                margin-bottom: 10px;
            }
            table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 10px;
            }
            th, td {
                border: 1px solid #ddd;
                padding: 6px;
                text-align: left;
            }
            th {
                background-color: #f5f5f5;
                font-weight: bold;
            }
            .stok-critical {
                background-color: #f8d7da !important;
                color: #721c24 !important;
            }
            .stok-low {
                background-color: #fff3cd !important;
                color: #856404 !important;
            }
            .stok-adequate {
                background-color: #d4edda !important;
                color: #155724 !important;
            }
            .no-print {
                display: none;
            }
            .footer {
                margin-top: 30px;
                text-align: right;
                font-size: 11px;
                color: #666;
            }
        }
        @media screen {
            body {
                font-family: Arial, sans-serif;
                margin: 20px;
            }
            .print-controls {
                margin-bottom: 20px;
                padding: 10px;
                background-color: #f8f9fa;
                border-radius: 5px;
            }
        }
    </style>
</head>
<body>
    <div class="print-controls no-print">
        <button onclick="window.print()" style="padding: 10px 20px; background-color: #007bff; color: white; border: none; border-radius: 5px; cursor: pointer;">
            🖨️ Cetak Laporan
        </button>
        <button onclick="window.close()" style="padding: 10px 20px; background-color: #dc3545; color: white; border: none; border-radius: 5px; cursor: pointer; margin-left: 10px;">
            ✖️ Tutup
        </button>
    </div>

    <div class="header">
        <div class="company-name">MINVEN INVENTORY SYSTEM</div>
        <div class="report-title">LAPORAN STOK GUDANG</div>
        <div class="report-date">Tanggal: <?php echo date('d/m/Y H:i:s'); ?></div>
        <?php if (!empty($filter_desc)): ?>
            <div class="filter-info">Filter: <?php echo $filter_desc; ?></div>
        <?php endif; ?>
    </div>

    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>Gudang</th>
                <th>Kode Barang</th>
                <th>Nama Barang</th>
                <th>Kategori</th>
                <th>Satuan</th>
                <th>Stok Awal</th>
                <th>Stok Terpakai</th>
                <th>Stok Sisa</th>
                <th>Stok Minimum</th>
                <th>Status</th>
                <th>Expire Date</th>
                <th>Harga Beli</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($result->num_rows > 0): ?>
                <?php $no = 1; ?>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <?php
                    $stok_status = '';
                    if ($row['stok_sisa'] == 0) {
                        $stok_status = 'stok-critical';
                        $status_text = 'HABIS';
                    } elseif ($row['stok_sisa'] <= $row['stok_minimum']) {
                        $stok_status = 'stok-low';
                        $status_text = 'MINIMUM';
                    } else {
                        $stok_status = 'stok-adequate';
                        $status_text = 'AMAN';
                    }
                    ?>
                    <tr class="<?php echo $stok_status; ?>">
                        <td><?php echo $no++; ?></td>
                        <td><?php echo $row['kode_gudang'] . ' - ' . $row['nama_gudang']; ?></td>
                        <td><?php echo $row['kode_barang']; ?></td>
                        <td><?php echo $row['nama_barang']; ?></td>
                        <td><?php echo $row['nama_kategori']; ?></td>
                        <td><?php echo $row['nama_satuan']; ?></td>
                        <td style="text-align: right;"><?php echo number_format($row['stok_awal']); ?></td>
                        <td style="text-align: right;"><?php echo number_format($row['stok_terpakai']); ?></td>
                        <td style="text-align: right; font-weight: bold;"><?php echo number_format($row['stok_sisa']); ?></td>
                        <td style="text-align: right;"><?php echo number_format($row['stok_minimum']); ?></td>
                        <td style="text-align: center;"><?php echo $status_text; ?></td>
                        <td style="text-align: center;"><?php echo $row['expire_date'] ? date('d/m/Y', strtotime($row['expire_date'])) : '-'; ?></td>
                        <td style="text-align: right;">Rp <?php echo number_format($row['harga_beli'], 2); ?></td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="13" style="text-align: center; padding: 20px;">Tidak ada data stok</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="footer">
        <div>Dicetak oleh: <?php echo isset($_SESSION['username']) ? $_SESSION['username'] : 'System'; ?></div>
        <div>Halaman: 1</div>
    </div>

    <script>
        // Auto print when page loads
        window.onload = function() {
            window.print();
        }
        
        // Close window after print
        window.onafterprint = function() {
            setTimeout(function() {
                window.close();
            }, 1000);
        }
    </script>
</body>
</html>

<?php
$conn->close();
?>
