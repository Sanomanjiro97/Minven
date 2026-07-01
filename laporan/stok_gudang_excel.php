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

header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="laporan_stok_gudang_' . date('YmdHis') . '.xls"');
header('Pragma: no-cache');
header('Expires: 0');

echo "<table border='1'>";
echo "<tr><th colspan='20' style='background-color:#2c3e50;color:#fff;font-size:16px;padding:10px;'>LAPORAN STOK GUDANG</th></tr>";
echo "<tr><th colspan='20' style='background-color:#f8f9fa;padding:8px;'>Tanggal Cetak: " . date('d/m/Y H:i:s') . "</th></tr>";

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

    echo "<tr><th colspan='20' style='background-color:#f8f9fa;padding:8px;'>";
    echo "Tampilan: " . ($view_type === 'history' ? 'History Perubahan' : 'Stok Saat Ini');
    echo " | Mode: " . ($view_type === 'history' ? ($history_mode === 'period' ? 'Rekap per Periode' : 'Detail') : '-');
    echo " | Periode: " . htmlspecialchars($period);
    echo " | Tanggal: " . htmlspecialchars($start_date) . " s/d " . htmlspecialchars($end_date);
    echo " | Gudang ID: " . ($gudang_id !== '' ? (int)$gudang_id : 'Semua');
    echo "</th></tr>";

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

            $stmt = $conn->prepare($sql);
            if ($stmt === false) {
                echo "<tr><td colspan='20'>Error preparing query</td></tr></table>";
                exit();
            }
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();

            echo "<tr style='background-color:#2c3e50;color:#fff;'>";
            echo "<th>No</th><th>Periode</th><th>Gudang</th><th>Kode Barang</th><th>Nama Barang</th><th>Kategori</th><th>Satuan</th><th>Saldo Awal</th><th>Masuk</th><th>Keluar</th><th>Saldo Akhir</th><th>Perubahan</th>";
            echo "</tr>";

            $no = 1;
            while ($row = $result->fetch_assoc()) {
                $saldo_awal = (float)($row['saldo_awal'] ?? 0);
                $saldo_akhir = (float)($row['saldo_akhir'] ?? 0);
                $perubahan = $saldo_akhir - $saldo_awal;
                echo "<tr>";
                echo "<td>" . $no++ . "</td>";
                echo "<td>" . htmlspecialchars($row['period_label']) . "</td>";
                echo "<td>" . htmlspecialchars($row['nama_gudang']) . "</td>";
                echo "<td>" . htmlspecialchars($row['kode_barang']) . "</td>";
                echo "<td>" . htmlspecialchars($row['nama_barang']) . "</td>";
                echo "<td>" . htmlspecialchars($row['nama_kategori']) . "</td>";
                echo "<td>" . htmlspecialchars($row['nama_satuan']) . "</td>";
                echo "<td style='text-align:right;'>" . $saldo_awal . "</td>";
                echo "<td style='text-align:right;'>" . (float)($row['masuk'] ?? 0) . "</td>";
                echo "<td style='text-align:right;'>" . (float)($row['keluar'] ?? 0) . "</td>";
                echo "<td style='text-align:right;font-weight:bold;'>" . $saldo_akhir . "</td>";
                echo "<td style='text-align:right;'>" . $perubahan . "</td>";
                echo "</tr>";
            }
            $stmt->close();
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

            $stmt = $conn->prepare($sql);
            if ($stmt === false) {
                echo "<tr><td colspan='20'>Error preparing query</td></tr></table>";
                exit();
            }
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();

            echo "<tr style='background-color:#2c3e50;color:#fff;'>";
            echo "<th>No</th><th>Tanggal Perubahan</th><th>Gudang</th><th>Kode Barang</th><th>Nama Barang</th><th>Kategori</th><th>Satuan</th><th>Jenis</th><th>Stok Awal (Sebelum)</th><th>Stok Awal (Sesudah)</th><th>Terpakai (Sebelum)</th><th>Terpakai (Sesudah)</th><th>Sisa (Sebelum)</th><th>Sisa (Sesudah)</th><th>Jumlah Perubahan</th><th>Keterangan</th><th>Updated By</th>";
            echo "</tr>";

            $no = 1;
            while ($row = $result->fetch_assoc()) {
                echo "<tr>";
                echo "<td>" . $no++ . "</td>";
                echo "<td>" . htmlspecialchars($row['tanggal_perubahan']) . "</td>";
                echo "<td>" . htmlspecialchars($row['nama_gudang']) . "</td>";
                echo "<td>" . htmlspecialchars($row['kode_barang']) . "</td>";
                echo "<td>" . htmlspecialchars($row['nama_barang']) . "</td>";
                echo "<td>" . htmlspecialchars($row['nama_kategori']) . "</td>";
                echo "<td>" . htmlspecialchars($row['nama_satuan']) . "</td>";
                echo "<td>" . htmlspecialchars($row['jenis_perubahan']) . "</td>";
                echo "<td style='text-align:right;'>" . (float)$row['stok_awal_sebelum'] . "</td>";
                echo "<td style='text-align:right;'>" . (float)$row['stok_awal_sesudah'] . "</td>";
                echo "<td style='text-align:right;'>" . (float)$row['stok_terpakai_sebelum'] . "</td>";
                echo "<td style='text-align:right;'>" . (float)$row['stok_terpakai_sesudah'] . "</td>";
                echo "<td style='text-align:right;'>" . (float)$row['stok_sisa_sebelum'] . "</td>";
                echo "<td style='text-align:right;'>" . (float)$row['stok_sisa_sesudah'] . "</td>";
                echo "<td style='text-align:right;'>" . (float)$row['jumlah_perubahan'] . "</td>";
                echo "<td>" . htmlspecialchars($row['keterangan']) . "</td>";
                echo "<td>" . htmlspecialchars($row['updated_by'] ?? 'N/A') . "</td>";
                echo "</tr>";
            }
            $stmt->close();
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
                    COALESCE(u_hist_end.nama, u_hist_start.nama, u.nama) AS updated_by,
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
                LEFT JOIN users u_hist_start ON gsh_start.created_by = u_hist_start.id
                LEFT JOIN users u_hist_end ON gsh_end.created_by = u_hist_end.id
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

        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            echo "<tr><td colspan='20'>Error preparing query</td></tr></table>";
            exit();
        }
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();

        echo "<tr style='background-color:#2c3e50;color:#fff;'>";
        echo "<th>No</th><th>Tanggal</th><th>Gudang</th><th>Kode Barang</th><th>Nama Barang</th><th>Kategori</th><th>Satuan</th><th>Stok Awal Periode</th><th>Stok Awal</th><th>Stok Terpakai</th><th>Stok Akhir</th><th>Perubahan Periode</th><th>Stok Minimum</th><th>Updated By</th>";
        echo "</tr>";

        $no = 1;
        while ($row = $result->fetch_assoc()) {
            $perubahan_periode = (float)($row['perubahan_periode'] ?? 0);
            echo "<tr>";
            echo "<td>" . $no++ . "</td>";
            if ($start_date === $end_date) {
                echo "<td>" . htmlspecialchars($end_date) . "</td>";
            } else {
                echo "<td>" . htmlspecialchars($start_date . " - " . $end_date) . "</td>";
            }
            echo "<td>" . htmlspecialchars($row['nama_gudang']) . "</td>";
            echo "<td>" . htmlspecialchars($row['kode_barang']) . "</td>";
            echo "<td>" . htmlspecialchars($row['nama_barang']) . "</td>";
            echo "<td>" . htmlspecialchars($row['nama_kategori']) . "</td>";
            echo "<td>" . htmlspecialchars($row['nama_satuan']) . "</td>";
            echo "<td style='text-align:right;'>" . (float)($row['stok_awal_periode'] ?? 0) . "</td>";
            echo "<td style='text-align:right;'>" . (float)$row['stok_awal'] . "</td>";
            echo "<td style='text-align:right;'>" . (float)$row['stok_terpakai'] . "</td>";
            echo "<td style='text-align:right;font-weight:bold;'>" . (float)$row['stok_akhir'] . "</td>";
            echo "<td style='text-align:right;'>" . $perubahan_periode . "</td>";
            echo "<td style='text-align:right;'>" . (float)$row['stok_minimum'] . "</td>";
            echo "<td>" . htmlspecialchars($row['updated_by'] ?? 'N/A') . "</td>";
            echo "</tr>";
        }
        $stmt->close();
    }
} else {
    $gudang_id = isset($_GET['gudang_id']) ? (int)$_GET['gudang_id'] : '';
    $kategori_id = isset($_GET['kategori_id']) ? (int)$_GET['kategori_id'] : '';
    $stok_minimum = $_GET['stok_minimum'] ?? '';

    $sql = "SELECT 
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

    if ($gudang_id !== '') {
        $sql .= " AND gs.gudang_id = " . (int)$gudang_id;
    }
    if ($kategori_id !== '') {
        $sql .= " AND b.kategori_id = " . (int)$kategori_id;
    }
    if ($stok_minimum === 'low') {
        $sql .= " AND gs.stok_sisa <= b.stok_minimum";
    } elseif ($stok_minimum === 'critical') {
        $sql .= " AND gs.stok_sisa <= (b.stok_minimum * 0.5)";
    }
    $sql .= " ORDER BY g.nama_gudang, b.nama_barang";

    $result = $conn->query($sql);

    echo "<tr style='background-color:#2c3e50;color:#fff;'>";
    echo "<th>No</th><th>Kode Gudang</th><th>Nama Gudang</th><th>Kode Barang</th><th>Nama Barang</th><th>Kategori</th><th>Satuan</th><th>Stok Awal</th><th>Stok Terpakai</th><th>Stok Sisa</th><th>Stok Minimum</th><th>Status</th><th>Expire Date</th><th>Harga Beli</th>";
    echo "</tr>";

    if ($result && $result->num_rows > 0) {
        $no = 1;
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $no++ . "</td>";
            echo "<td>" . htmlspecialchars($row['kode_gudang']) . "</td>";
            echo "<td>" . htmlspecialchars($row['nama_gudang']) . "</td>";
            echo "<td>" . htmlspecialchars($row['kode_barang']) . "</td>";
            echo "<td>" . htmlspecialchars($row['nama_barang']) . "</td>";
            echo "<td>" . htmlspecialchars($row['nama_kategori']) . "</td>";
            echo "<td>" . htmlspecialchars($row['nama_satuan']) . "</td>";
            echo "<td style='text-align:right;'>" . (float)$row['stok_awal'] . "</td>";
            echo "<td style='text-align:right;'>" . (float)$row['stok_terpakai'] . "</td>";
            echo "<td style='text-align:right;font-weight:bold;'>" . (float)$row['stok_sisa'] . "</td>";
            echo "<td style='text-align:right;'>" . (float)$row['stok_minimum'] . "</td>";
            echo "<td>" . htmlspecialchars($row['status_stok']) . "</td>";
            echo "<td>" . ($row['expire_date'] ? htmlspecialchars(date('Y-m-d', strtotime($row['expire_date']))) : '-') . "</td>";
            echo "<td style='text-align:right;'>" . (float)$row['harga_beli'] . "</td>";
            echo "</tr>";
        }
    } else {
        echo "<tr><td colspan='20' style='text-align:center;padding:20px;'>Tidak ada data</td></tr>";
    }
}

echo "</table>";
$conn->close();
