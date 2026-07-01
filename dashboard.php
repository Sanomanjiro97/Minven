<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$gudang_central_id = 23;
$gudang_antapani_id = 13;
$gudang_central_nama = 'Gudang Central';
$gudang_antapani_nama = 'Gudang Antapani';
$stmt_dashboard_gudang = $conn->prepare("SELECT id, nama_gudang FROM gudang WHERE id IN (?, ?)");
if ($stmt_dashboard_gudang) {
    $stmt_dashboard_gudang->bind_param('ii', $gudang_central_id, $gudang_antapani_id);
    $stmt_dashboard_gudang->execute();
    $res_dashboard_gudang = $stmt_dashboard_gudang->get_result();
    while ($row = $res_dashboard_gudang->fetch_assoc()) {
        $id = (int)($row['id'] ?? 0);
        $nama = trim((string)($row['nama_gudang'] ?? ''));
        if ($nama === '') continue;
        if ($id === $gudang_central_id) $gudang_central_nama = $nama;
        if ($id === $gudang_antapani_id) $gudang_antapani_nama = $nama;
    }
    $stmt_dashboard_gudang->close();
}

// Query untuk transaksi terbaru, mengambil data dari detail_transaksi_stok
$transaksi_sql = "SELECT
                    ts.no_transaksi,
                    DATE_FORMAT(ts.tanggal, '%d/%m/%Y %H:%i') as formatted_tanggal,
                    ts.tanggal as original_tanggal,
                    g.nama_gudang,
                    GROUP_CONCAT(b.nama_barang SEPARATOR ', ') as nama_barang,
                    ts.jenis_transaksi,
                    SUM(dts.jumlah) as total_jumlah,
                    u.nama as created_by,
                    tt.gudang_asal_id,
                    tt.gudang_tujuan_id,
                    g1.nama_gudang as nama_gudang_asal,
                    g2.nama_gudang as nama_gudang_tujuan
                  FROM transaksi_stok ts
                  JOIN gudang g ON ts.gudang_id = g.id
                  LEFT JOIN detail_transaksi_stok dts ON ts.id = dts.transaksi_stok_id
                  LEFT JOIN barang b ON dts.barang_id = b.id
                  LEFT JOIN transaksi_transfer tt ON ts.id = tt.id
                  LEFT JOIN gudang g1 ON tt.gudang_asal_id = g1.id
                  LEFT JOIN gudang g2 ON tt.gudang_tujuan_id = g2.id
                  LEFT JOIN users u ON ts.created_by = u.id
                  GROUP BY ts.id, ts.no_transaksi, ts.tanggal, g.nama_gudang, ts.jenis_transaksi, u.nama, 
                           tt.gudang_asal_id, tt.gudang_tujuan_id, g1.nama_gudang, g2.nama_gudang
                  
                  UNION
                  
                  SELECT 
                    tt.no_transaksi,
                    DATE_FORMAT(tt.tanggal, '%d/%m/%Y %H:%i') as formatted_tanggal,
                    tt.tanggal as original_tanggal,
                    CONCAT(g1.nama_gudang, ' → ', g2.nama_gudang) as nama_gudang,
                    GROUP_CONCAT(b.nama_barang SEPARATOR ', ') as nama_barang,
                    'transfer' as jenis_transaksi,
                    SUM(dtt.jumlah) as total_jumlah,
                    u.nama as created_by,
                    tt.gudang_asal_id,
                    tt.gudang_tujuan_id,
                    g1.nama_gudang as nama_gudang_asal,
                    g2.nama_gudang as nama_gudang_tujuan
                  FROM transaksi_transfer tt
                  JOIN gudang g1 ON tt.gudang_asal_id = g1.id
                  JOIN gudang g2 ON tt.gudang_tujuan_id = g2.id
                  LEFT JOIN detail_transaksi_transfer dtt ON tt.id = dtt.transaksi_transfer_id
                  LEFT JOIN barang b ON dtt.barang_id = b.id
                  LEFT JOIN users u ON tt.created_by = u.id
                  GROUP BY tt.id, tt.no_transaksi, tt.tanggal, g1.nama_gudang, g2.nama_gudang, u.nama
                  
                  ORDER BY original_tanggal DESC, no_transaksi DESC
                  LIMIT 10";

$transaksi_result = $conn->query($transaksi_sql);
if (!$transaksi_result) {
    die("Error mengambil data transaksi: " . $conn->error);
}

// Query untuk stok minimum
$stok_min_sql = "SELECT 
                   b.kode_barang,
                   b.nama_barang,
                   g.nama_gudang,
                   gs.stok_awal,
                   gs.stok_terpakai,
                   (gs.stok_awal - gs.stok_terpakai) as stok_akhir,
                   b.stok_minimum
                 FROM gudang_stok gs
                 JOIN barang b ON gs.barang_id = b.id
                 JOIN gudang g ON gs.gudang_id = g.id
                 WHERE (gs.stok_awal - gs.stok_terpakai) <= b.stok_minimum
                 ORDER BY g.nama_gudang, b.nama_barang";
$stok_min_result = $conn->query($stok_min_sql);

// Query untuk stok habis
$stok_habis_sql = "SELECT 
                     b.kode_barang,
                     b.nama_barang,
                     g.nama_gudang,
                     gs.stok_awal,
                     gs.stok_terpakai
                   FROM gudang_stok gs
                   JOIN barang b ON gs.barang_id = b.id
                   JOIN gudang g ON gs.gudang_id = g.id
                   WHERE (gs.stok_awal - gs.stok_terpakai) <= 0
                   ORDER BY g.nama_gudang, b.nama_barang";
$stok_habis_result = $conn->query($stok_habis_sql);
$stok_habis_count = ($stok_habis_result instanceof mysqli_result) ? (int)$stok_habis_result->num_rows : 0;

// Get counts for dashboard cards
$total_barang_sql = "SELECT COUNT(*) as total FROM barang";
$total_barang_result = $conn->query($total_barang_sql);
$total_barang = $total_barang_result->fetch_assoc()['total'];

$total_supplier_sql = "SELECT COUNT(*) as total FROM supplier";
$total_supplier_result = $conn->query($total_supplier_sql);
$total_supplier = $total_supplier_result->fetch_assoc()['total'];

$total_transaksi_sql = "SELECT COUNT(*) as total FROM (
    SELECT id FROM transaksi_stok WHERE DATE(tanggal) = CURDATE()
    UNION ALL
    SELECT id FROM transaksi_transfer WHERE DATE(tanggal) = CURDATE()
) as today_transactions";
$total_transaksi_result = $conn->query($total_transaksi_sql);
$total_transaksi = $total_transaksi_result->fetch_assoc()['total'];

$stok_min_count_sql = "SELECT COUNT(*) as total FROM gudang_stok gs
                       JOIN barang b ON gs.barang_id = b.id
                       WHERE (gs.stok_awal - gs.stok_terpakai) <= b.stok_minimum";
$stok_min_count_result = $conn->query($stok_min_count_sql);
$stok_min_count = $stok_min_count_result->fetch_assoc()['total'];

$total_transaksi_yesterday_sql = "SELECT COUNT(*) as total FROM (
    SELECT id FROM transaksi_stok WHERE DATE(tanggal) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)
    UNION ALL
    SELECT id FROM transaksi_transfer WHERE DATE(tanggal) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)
) as yesterday_transactions";
$total_transaksi_yesterday_result = $conn->query($total_transaksi_yesterday_sql);
$total_transaksi_yesterday = 0;
if ($total_transaksi_yesterday_result instanceof mysqli_result) {
    $total_transaksi_yesterday = (int)($total_transaksi_yesterday_result->fetch_assoc()['total'] ?? 0);
}

$transaksi_change_pct = 0.0;
if ($total_transaksi_yesterday > 0) {
    $transaksi_change_pct = (($total_transaksi - $total_transaksi_yesterday) / $total_transaksi_yesterday) * 100.0;
} elseif ($total_transaksi > 0) {
    $transaksi_change_pct = 100.0;
}
$transaksi_change_dir = ($total_transaksi_yesterday === 0 && $total_transaksi === 0) ? 'flat' : (($transaksi_change_pct >= 0) ? 'up' : 'down');
$transaksi_change_class = ($transaksi_change_dir === 'down') ? 'down' : (($transaksi_change_dir === 'up') ? 'up' : 'flat');
$transaksi_change_label = ($transaksi_change_dir === 'flat')
    ? '0% dibanding kemarin'
    : (number_format(abs($transaksi_change_pct), 1) . '% dibanding kemarin');

$stok_min_label = ($stok_min_count > 0) ? 'Perlu dicek' : 'Aman';
$stok_min_class = ($stok_min_count > 0) ? 'warn' : 'ok';

$bulan_id = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni',
    7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];
$today_dt = new DateTime('now');
$today_label = $today_dt->format('d') . ' ' . ($bulan_id[(int)$today_dt->format('n')] ?? $today_dt->format('M')) . ' ' . $today_dt->format('Y');
$hour_now = (int)$today_dt->format('G');
$dashboard_greeting = 'Selamat datang kembali';
if ($hour_now < 11) {
    $dashboard_greeting = 'Selamat pagi';
} elseif ($hour_now < 15) {
    $dashboard_greeting = 'Selamat siang';
} elseif ($hour_now < 19) {
    $dashboard_greeting = 'Selamat sore';
}

$user_role_name = trim((string)($_SESSION['nama_role'] ?? ''));
$role_id = (int)($_SESSION['role_id'] ?? 0);
if ($user_role_name === '' && $role_id > 0) {
    $authConn = auth_db_conn();
    if ($authConn instanceof mysqli && !$authConn->connect_error) {
        $stmt_role = $authConn->prepare("SELECT nama_role FROM roles WHERE id = ? LIMIT 1");
        if ($stmt_role) {
            $stmt_role->bind_param('i', $role_id);
            $stmt_role->execute();
            $result_role = $stmt_role->get_result();
            if ($result_role instanceof mysqli_result) {
                $role_row = $result_role->fetch_assoc();
                $user_role_name = trim((string)($role_row['nama_role'] ?? ''));
            }
            $stmt_role->close();
        }
    }
}

$kategori_labels = [];
$kategori_values = [];
$kategori_sql = "SELECT COALESCE(NULLIF(TRIM(k.nama_kategori), ''), 'Tanpa Kategori') as nama_kategori, COUNT(b.id) as total
                FROM barang b
                LEFT JOIN kategori k ON b.kategori_id = k.id
                GROUP BY nama_kategori
                ORDER BY total DESC";
$kategori_result = $conn->query($kategori_sql);
if ($kategori_result instanceof mysqli_result) {
    $rows = [];
    while ($r = $kategori_result->fetch_assoc()) {
        $rows[] = [
            'label' => (string)($r['nama_kategori'] ?? 'Tanpa Kategori'),
            'total' => (int)($r['total'] ?? 0),
        ];
    }
    $kategori_result->free();
    $top = array_slice($rows, 0, 5);
    $rest = array_slice($rows, 5);
    $rest_total = 0;
    foreach ($rest as $it) $rest_total += (int)($it['total'] ?? 0);
    foreach ($top as $it) {
        $kategori_labels[] = $it['label'];
        $kategori_values[] = $it['total'];
    }
    if ($rest_total > 0) {
        $kategori_labels[] = 'Lainnya';
        $kategori_values[] = $rest_total;
    }
}

$activity_counts = array_fill(0, 24, 0);
$activity_stok_sql = "SELECT HOUR(tanggal) as h, COUNT(*) as c
                      FROM transaksi_stok
                      WHERE DATE(tanggal) = CURDATE()
                      GROUP BY HOUR(tanggal)";
$activity_transfer_sql = "SELECT HOUR(tanggal) as h, COUNT(*) as c
                          FROM transaksi_transfer
                          WHERE DATE(tanggal) = CURDATE()
                          GROUP BY HOUR(tanggal)";
$activity_stok_res = $conn->query($activity_stok_sql);
if ($activity_stok_res instanceof mysqli_result) {
    while ($r = $activity_stok_res->fetch_assoc()) {
        $h = (int)($r['h'] ?? -1);
        if ($h >= 0 && $h <= 23) $activity_counts[$h] += (int)($r['c'] ?? 0);
    }
    $activity_stok_res->free();
}
$activity_transfer_res = $conn->query($activity_transfer_sql);
if ($activity_transfer_res instanceof mysqli_result) {
    while ($r = $activity_transfer_res->fetch_assoc()) {
        $h = (int)($r['h'] ?? -1);
        if ($h >= 0 && $h <= 23) $activity_counts[$h] += (int)($r['c'] ?? 0);
    }
    $activity_transfer_res->free();
}

$activity_labels = [];
for ($i = 0; $i < 24; $i++) {
    $activity_labels[] = str_pad((string)$i, 2, '0', STR_PAD_LEFT) . ':00';
}

$total_item_today = 0;
$qty_stok_sql = "SELECT COALESCE(SUM(dts.jumlah), 0) as total
                 FROM transaksi_stok ts
                 LEFT JOIN detail_transaksi_stok dts ON ts.id = dts.transaksi_stok_id
                 WHERE DATE(ts.tanggal) = CURDATE()";
$qty_transfer_sql = "SELECT COALESCE(SUM(dtt.jumlah), 0) as total
                     FROM transaksi_transfer tt
                     LEFT JOIN detail_transaksi_transfer dtt ON tt.id = dtt.transaksi_transfer_id
                     WHERE DATE(tt.tanggal) = CURDATE()";
$qty_stok_res = $conn->query($qty_stok_sql);
if ($qty_stok_res instanceof mysqli_result) {
    $total_item_today += (int)($qty_stok_res->fetch_assoc()['total'] ?? 0);
    $qty_stok_res->free();
}
$qty_transfer_res = $conn->query($qty_transfer_sql);
if ($qty_transfer_res instanceof mysqli_result) {
    $total_item_today += (int)($qty_transfer_res->fetch_assoc()['total'] ?? 0);
    $qty_transfer_res->free();
}

$gudang_aktif_today = 0;
$gudang_aktif_sql = "SELECT COUNT(DISTINCT gid) as total FROM (
    SELECT gudang_id as gid FROM transaksi_stok WHERE DATE(tanggal) = CURDATE()
    UNION
    SELECT gudang_asal_id as gid FROM transaksi_transfer WHERE DATE(tanggal) = CURDATE()
    UNION
    SELECT gudang_tujuan_id as gid FROM transaksi_transfer WHERE DATE(tanggal) = CURDATE()
) x";
$gudang_aktif_res = $conn->query($gudang_aktif_sql);
if ($gudang_aktif_res instanceof mysqli_result) {
    $gudang_aktif_today = (int)($gudang_aktif_res->fetch_assoc()['total'] ?? 0);
    $gudang_aktif_res->free();
}

$dashboard_alert_total = $stok_min_count + $stok_habis_count;
$dashboard_status_label = $dashboard_alert_total > 0 ? 'Perlu perhatian' : 'Kondisi stok aman';
$dashboard_status_class = $dashboard_alert_total > 0 ? 'warn' : 'ok';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="format-detection" content="telephone=no">
    <title>Dashboard - Sistem Inventory</title>
    <link rel="icon" type="image/png" href="<?= htmlspecialchars(url_for('asset/LOGO1.png')) ?>">
    <link rel="shortcut icon" type="image/png" href="<?= htmlspecialchars(url_for('asset/LOGO1.png')) ?>">
    <link rel="apple-touch-icon" href="<?= htmlspecialchars(url_for('asset/LOGO1.png')) ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/animate.css@4.1.1/animate.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&family=Inter:wght@400;700&display=swap" rel="stylesheet">
    <audio id="notificationSound" src="asset/masuk.mp3" preload="auto"></audio>
    <audio id="warningSound" src="asset/warning.mp3" preload="auto"></audio>
    <audio id="stokhabisSound" src="asset/stokhabis.mp3" preload="auto"></audio>
    <style>
        :root {
            --primary: #0056b3;
            --primary-2: #004494;
            --primary-soft: rgba(0, 86, 179, 0.12);
            --bg: #f4f6fb;
            --card: #ffffff;
            --text: #0f172a;
            --muted: #64748b;
            --border: rgba(15, 23, 42, 0.08);
            --shadow: 0 10px 24px rgba(15, 23, 42, 0.08);
            --radius: 22px;
            --transition: 0.18s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', 'Poppins', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--bg);
            min-height: 100vh;
            color: var(--text);
            line-height: 1.55;
            position: relative;
        }

        body::before {
            content: "";
            position: fixed;
            inset: 0;
            pointer-events: none;
            background:
                radial-gradient(circle at top right, rgba(0, 86, 179, 0.12), transparent 26%),
                radial-gradient(circle at top left, rgba(0, 68, 148, 0.08), transparent 22%);
        }

        .dash-shell {
            margin: 18px;
            padding: 18px;
            position: relative;
            z-index: 1;
        }

        .dash-topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            margin-bottom: 14px;
        }

        .dash-title {
            font-size: 26px;
            font-weight: 800;
            letter-spacing: -0.4px;
        }

        .dash-subtitle {
            color: var(--muted);
            font-size: 14px;
            font-weight: 600;
            margin-top: 2px;
        }

        .dash-user-meta {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-top: 8px;
            flex-wrap: wrap;
        }

        .dash-user-name {
            color: var(--text);
            font-size: 13px;
            font-weight: 700;
            line-height: 1.2;
        }

        .dash-user-role {
            display: inline-flex;
            align-items: center;
            padding: 4px 10px;
            border-radius: 999px;
            background: rgba(0, 86, 179, 0.10);
            border: 1px solid rgba(0, 86, 179, 0.14);
            color: var(--primary);
            font-size: 11px;
            font-weight: 800;
            line-height: 1;
            white-space: nowrap;
        }

        .dash-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            flex: 0 0 auto;
        }

        .dash-overview {
            display: grid;
            grid-template-columns: minmax(0, 1.8fr) minmax(280px, 1fr);
            gap: 18px;
            margin-bottom: 18px;
        }

        .hero-card {
            position: relative;
            overflow: hidden;
            background:
                radial-gradient(circle at top right, rgba(255, 255, 255, 0.22), transparent 32%),
                linear-gradient(135deg, #0056b3 0%, #004494 100%);
            border-radius: 28px;
            padding: 26px;
            color: #ffffff;
            box-shadow: 0 22px 50px rgba(0, 86, 179, 0.22);
        }

        .hero-card::after {
            content: "";
            position: absolute;
            right: -40px;
            bottom: -50px;
            width: 220px;
            height: 220px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.08);
        }

        .hero-card > * {
            position: relative;
            z-index: 1;
        }

        .hero-card__eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.14);
            border: 1px solid rgba(255, 255, 255, 0.18);
            font-size: 12px;
            font-weight: 700;
        }

        .hero-card__title {
            margin-top: 16px;
            font-size: 30px;
            font-weight: 800;
            letter-spacing: -0.5px;
            line-height: 1.15;
        }

        .hero-card__desc {
            margin-top: 10px;
            max-width: 700px;
            font-size: 14px;
            color: rgba(255, 255, 255, 0.86);
        }

        .hero-chip-row {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 18px;
        }

        .hero-chip {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 14px;
            border-radius: 16px;
            background: rgba(255, 255, 255, 0.12);
            border: 1px solid rgba(255, 255, 255, 0.16);
            font-size: 13px;
            font-weight: 700;
        }

        .hero-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 22px;
        }

        .hero-action {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 16px;
            border-radius: 16px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 800;
            transition: transform var(--transition), box-shadow var(--transition), background var(--transition);
        }

        .hero-action:hover {
            transform: translateY(-1px);
            text-decoration: none;
        }

        .hero-action--primary {
            background: #ffffff;
            color: var(--primary);
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.14);
        }

        .hero-action--secondary {
            color: #ffffff;
            background: rgba(255, 255, 255, 0.12);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .overview-stack {
            display: grid;
            gap: 14px;
        }

        .overview-card {
            background: var(--card);
            border-radius: 22px;
            border: 1px solid var(--border);
            box-shadow: var(--shadow);
            padding: 18px;
        }

        .overview-card--soft {
            background:
                linear-gradient(135deg, rgba(0, 86, 179, 0.12) 0%, rgba(0, 68, 148, 0.08) 100%),
                var(--card);
        }

        .overview-label {
            font-size: 12px;
            font-weight: 800;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .overview-value {
            margin-top: 8px;
            font-size: 28px;
            font-weight: 800;
            line-height: 1;
        }

        .overview-meta {
            margin-top: 10px;
            font-size: 13px;
            color: var(--muted);
            font-weight: 600;
        }

        .overview-mini-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
            margin-top: 14px;
        }

        .overview-mini {
            border-radius: 18px;
            background: rgba(255, 255, 255, 0.78);
            border: 1px solid rgba(15, 23, 42, 0.06);
            padding: 14px;
        }

        .overview-mini__label {
            font-size: 12px;
            font-weight: 700;
            color: var(--muted);
        }

        .overview-mini__value {
            margin-top: 8px;
            font-size: 20px;
            font-weight: 800;
            color: var(--text);
        }

        .dash-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 14px;
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 999px;
            box-shadow: 0 8px 20px rgba(15, 23, 42, 0.06);
            font-weight: 700;
            font-size: 13px;
            color: var(--text);
            white-space: nowrap;
        }

        .dash-icon-btn {
            width: 44px;
            height: 44px;
            display: grid;
            place-items: center;
            border-radius: 14px;
            background: var(--card);
            border: 1px solid var(--border);
            box-shadow: 0 8px 20px rgba(15, 23, 42, 0.06);
            color: var(--text);
            position: relative;
        }

        .dash-icon-btn i {
            font-size: 20px;
        }

        .dash-dot {
            position: absolute;
            right: 10px;
            top: 10px;
            width: 10px;
            height: 10px;
            border-radius: 999px;
            background: #ef4444;
            box-shadow: 0 0 0 4px rgba(239, 68, 68, 0.15);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .stat-card {
            background: var(--card);
            border-radius: var(--radius);
            padding: 18px;
            box-shadow: var(--shadow);
            transition: transform var(--transition), box-shadow var(--transition);
            border: 1px solid var(--border);
            position: relative;
            overflow: hidden;
        }

        .stat-card::after {
            content: "";
            position: absolute;
            top: -20px;
            right: -20px;
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: rgba(0, 86, 179, 0.08);
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 14px 30px rgba(15, 23, 42, 0.12);
        }

        .stat-card > * {
            position: relative;
            z-index: 1;
        }

        .stat-card__head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        .stat-icon {
            width: 44px;
            height: 44px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            color: white;
            background: linear-gradient(135deg, var(--primary), var(--primary-2));
        }

        .stat-caption {
            display: inline-flex;
            align-items: center;
            padding: 7px 11px;
            border-radius: 999px;
            background: rgba(0, 86, 179, 0.08);
            color: var(--primary);
            font-size: 11px;
            font-weight: 800;
        }

        .stat-number {
            font-size: 26px;
            font-weight: 800;
            color: var(--text);
            margin-top: 10px;
        }

        .stat-label {
            color: var(--muted);
            font-size: 13px;
            font-weight: 700;
            margin-top: 2px;
        }

        .stat-progress {
            margin-top: 12px;
            height: 7px;
            border-radius: 999px;
            background: rgba(15, 23, 42, 0.06);
            overflow: hidden;
        }

        .stat-progress span {
            display: block;
            height: 100%;
            border-radius: inherit;
            background: linear-gradient(90deg, var(--primary), var(--primary-2));
        }

        .stat-meta {
            margin-top: 8px;
            font-size: 12px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .stat-meta.up { color: #16a34a; }
        .stat-meta.down { color: #ef4444; }
        .stat-meta.flat { color: #64748b; }
        .stat-meta.ok { color: #16a34a; }
        .stat-meta.warn { color: #f59e0b; }

        .content-section {
            margin-top: 1rem;
        }

        .section-title {
            font-size: 15px;
            font-weight: 800;
            color: var(--text);
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .section-title i {
            color: var(--primary);
        }

        .quick-actions {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            align-items: stretch;
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .action-card {
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
            height: 100%;
            background: var(--card);
            border-radius: var(--radius);
            padding: 14px 14px;
            text-align: center;
            box-shadow: var(--shadow);
            transition: transform var(--transition), box-shadow var(--transition);
            border: 1px solid var(--border);
            text-decoration: none;
            color: inherit;
            position: relative;
            overflow: hidden;
        }

        .action-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 14px 30px rgba(15, 23, 42, 0.12);
            text-decoration: none;
            color: inherit;
        }

        .action-tag {
            display: inline-flex;
            align-items: center;
            align-self: flex-start;
            margin-bottom: 12px;
            padding: 6px 10px;
            border-radius: 999px;
            background: rgba(0, 86, 179, 0.08);
            color: var(--primary);
            font-size: 11px;
            font-weight: 800;
        }

        .action-icon {
            width: 44px;
            height: 44px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            color: white;
            margin: 0 0 1rem;
            background: linear-gradient(135deg, var(--primary), var(--primary-2));
        }

        .action-title {
            font-weight: 800;
            margin-bottom: 0.25rem;
            color: var(--text);
        }

        .action-desc {
            font-size: 0.85rem;
            color: var(--muted);
            font-weight: 600;
        }

        .table-container {
            background: var(--card);
            border-radius: var(--radius);
            overflow: hidden;
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
        }

        .table {
            margin-bottom: 0;
        }

        .table thead th {
            background: rgba(0, 86, 179, 0.08);
            color: var(--text);
            border: none;
            padding: 0.9rem 1rem;
            font-weight: 800;
            font-size: 12px;
            text-transform: none;
            letter-spacing: 0.1px;
        }

        .table tbody tr {
            transition: background var(--transition);
        }

        .table tbody tr:hover {
            background-color: rgba(0, 86, 179, 0.06);
        }

        .table tbody td {
            padding: 1rem;
            border-bottom: 1px solid rgba(15, 23, 42, 0.06);
            vertical-align: middle;
        }

        .badge {
            padding: 0.5rem 0.75rem;
            border-radius: 20px;
            font-weight: 500;
            font-size: 0.8rem;
        }

        .nav-tabs {
            border-bottom: 1px solid rgba(15, 23, 42, 0.10);
        }

        .nav-tabs .nav-link {
            border: none;
            color: var(--muted);
            font-weight: 800;
            padding: 0.9rem 1rem;
            border-radius: 0;
            transition: color var(--transition), border-color var(--transition);
        }

        .nav-tabs .nav-link.active {
            color: var(--primary);
            background: none;
            border-bottom: 2px solid var(--primary);
        }

        .nav-tabs .nav-link:hover {
            color: var(--primary);
            border-color: transparent;
        }

        .stok-aman {
            background: linear-gradient(90deg, rgba(22, 163, 74, 0.08), transparent);
            border-left: 4px solid #16a34a;
        }

        .stok-minimum {
            background: linear-gradient(90deg, rgba(245, 158, 11, 0.10), transparent);
            border-left: 4px solid #f59e0b;
        }

        .stok-habis {
            background: linear-gradient(90deg, rgba(239, 68, 68, 0.10), transparent);
            border-left: 4px solid #ef4444;
        }

        .side-card {
            background: var(--card);
            border-radius: var(--radius);
            padding: 18px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
        }

        .side-card + .side-card {
            margin-top: 1rem;
        }

        .side-card__header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 10px;
        }

        .side-card__title {
            font-size: 14px;
            font-weight: 900;
            color: var(--text);
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .side-card__title i {
            color: var(--primary);
            font-size: 18px;
        }

        .dash-select {
            border: 1px solid var(--border);
            border-radius: 999px;
            padding: 8px 12px;
            background: #ffffff;
            font-weight: 700;
            font-size: 12px;
            color: var(--text);
        }

        .legend-list {
            margin-top: 12px;
            display: grid;
            gap: 8px;
        }

        .side-summary {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
            margin-bottom: 14px;
        }

        .side-summary__item {
            padding: 12px 14px;
            border-radius: 16px;
            background: rgba(0, 86, 179, 0.06);
            border: 1px solid rgba(0, 86, 179, 0.10);
        }

        .side-summary__label {
            font-size: 11px;
            font-weight: 800;
            color: var(--muted);
        }

        .side-summary__value {
            margin-top: 6px;
            font-size: 18px;
            font-weight: 900;
            color: var(--text);
        }

        .legend-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            font-size: 12px;
            font-weight: 700;
            color: var(--muted);
        }

        .legend-left {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            min-width: 0;
        }

        .legend-dot {
            width: 10px;
            height: 10px;
            border-radius: 999px;
            flex: 0 0 auto;
        }

        .legend-label {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .legend-value {
            color: var(--text);
            font-weight: 900;
            white-space: nowrap;
        }

        .activity-stats {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 10px;
            margin-top: 12px;
        }

        .activity-stat {
            background: rgba(0, 86, 179, 0.06);
            border: 1px solid rgba(0, 86, 179, 0.10);
            border-radius: 16px;
            padding: 10px 12px;
        }

        .activity-stat__label {
            font-size: 11px;
            font-weight: 800;
            color: var(--muted);
        }

        .activity-stat__value {
            font-size: 14px;
            font-weight: 900;
            color: var(--text);
            margin-top: 4px;
        }

        @media (max-width: 768px) {
            .dash-overview {
                grid-template-columns: 1fr;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .dash-shell {
                margin: 12px;
                padding: 0;
            }

            .dash-topbar {
                align-items: flex-start;
                flex-direction: column;
            }

            .quick-actions {
                grid-template-columns: 1fr;
            }

            .hero-card {
                padding: 20px;
            }

            .hero-card__title {
                font-size: 24px;
            }

            .overview-mini-grid,
            .side-summary,
            .activity-stats {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/templates/navbar.php'; ?>

    <div class="dash-shell">
        <div class="dash-topbar">
            <div>
                <div class="dash-title">Dashboard Inventory</div>
                <div class="dash-subtitle">Ringkasan operasional inventori harian</div>
                <div class="dash-user-meta">
                    <span class="dash-user-name">
                        <?= htmlspecialchars($_SESSION['nama_lengkap'] ?? $_SESSION['nama'] ?? $_SESSION['username']) ?>
                    </span>
                    <?php if ($user_role_name !== ''): ?>
                        <span class="dash-user-role">
                            <?= htmlspecialchars($user_role_name) ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="dash-actions">
                <div class="dash-pill">
                    <i class='bx bx-calendar'></i>
                    <?= htmlspecialchars($today_label) ?>
                </div>
                
                <button class="dash-icon-btn" type="button" id="dashNotifyBtn" aria-label="Notifikasi">
                    <i class='bx bx-bell'></i>
                    <?php if ($stok_min_count > 0 || $stok_habis_result->num_rows > 0): ?>
                        <span class="dash-dot" aria-hidden="true"></span>
                    <?php endif; ?>
                </button>
            </div>
        </div>

        <div class="dash-overview">
            <div class="hero-card">
                <div class="hero-card__eyebrow">
                    <i class='bx bx-pulse'></i>
                    Dashboard Operasional
                </div>
                <div class="hero-card__title">
                    <?= htmlspecialchars($dashboard_greeting) ?>, <?= htmlspecialchars($_SESSION['nama_lengkap'] ?? $_SESSION['nama'] ?? $_SESSION['username']) ?>
                </div>
                <div class="hero-card__desc">
                    Pantau transaksi, kondisi stok, dan aktivitas gudang dalam satu tampilan yang lebih ringkas untuk membantu pengambilan keputusan lebih cepat.
                </div>
                <div class="hero-chip-row">
                    <div class="hero-chip">
                        <i class='bx bx-calendar'></i>
                        <?= htmlspecialchars($today_label) ?>
                    </div>
                    <div class="hero-chip">
                        <i class='bx bx-check-shield'></i>
                        <?= htmlspecialchars($dashboard_status_label) ?>
                    </div>
                    <div class="hero-chip">
                        <i class='bx bx-building-house'></i>
                        <?= number_format($gudang_aktif_today) ?> gudang aktif hari ini
                    </div>
                </div>
                <div class="hero-actions">
                    <a href="stok/masuk/index.php" class="hero-action hero-action--primary">
                        <i class='bx bx-plus-circle'></i>
                        Input Stok Masuk
                    </a>
                    <a href="pembelian/po/index.php" class="hero-action hero-action--secondary">
                        <i class='bx bx-purchase-tag'></i>
                        Buka Purchase Order
                    </a>
                </div>
            </div>

            <div class="overview-stack">
                <div class="overview-card overview-card--soft">
                    <div class="overview-label">Aktivitas Hari Ini</div>
                    <div class="overview-value"><?= number_format($total_transaksi) ?></div>
                    <div class="overview-meta"><?= htmlspecialchars($transaksi_change_label) ?></div>
                    <div class="overview-mini-grid">
                        <div class="overview-mini">
                            <div class="overview-mini__label">Total Item</div>
                            <div class="overview-mini__value"><?= number_format($total_item_today) ?></div>
                        </div>
                        <div class="overview-mini">
                            <div class="overview-mini__label">Stok Habis</div>
                            <div class="overview-mini__value"><?= number_format($stok_habis_count) ?></div>
                        </div>
                    </div>
                </div>

                <div class="overview-card">
                    <div class="overview-label">Monitoring Stok</div>
                    <div class="overview-value"><?= number_format($dashboard_alert_total) ?></div>
                    <div class="overview-meta">Item yang perlu dicek ulang karena minimum atau habis.</div>
                </div>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-xxl-8 col-xl-7">
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-card__head">
                            <div class="stat-icon">
                                <i class='bx bx-package'></i>
                            </div>
                            <div class="stat-caption">Master</div>
                        </div>
                        <div class="stat-number"><?= number_format($total_barang) ?></div>
                        <div class="stat-label">Total Barang</div>
                        <div class="stat-meta flat">
                            <i class='bx bx-layer'></i>
                            Item terdaftar
                        </div>
                        <div class="stat-progress"><span style="width: 78%"></span></div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-card__head">
                            <div class="stat-icon">
                                <i class='bx bx-user-voice'></i>
                            </div>
                            <div class="stat-caption">Mitra</div>
                        </div>
                        <div class="stat-number"><?= number_format($total_supplier) ?></div>
                        <div class="stat-label">Total Supplier</div>
                        <div class="stat-meta flat">
                            <i class='bx bx-network-chart'></i>
                            Vendor aktif terdata
                        </div>
                        <div class="stat-progress"><span style="width: 62%"></span></div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-card__head">
                            <div class="stat-icon">
                                <i class='bx bx-transfer'></i>
                            </div>
                            <div class="stat-caption">Hari Ini</div>
                        </div>
                        <div class="stat-number"><?= number_format($total_transaksi) ?></div>
                        <div class="stat-label">Transaksi Hari Ini</div>
                        <div class="stat-meta <?= htmlspecialchars($transaksi_change_class) ?>">
                            <i class='bx <?= $transaksi_change_dir === 'down' ? 'bx-trending-down' : ($transaksi_change_dir === 'up' ? 'bx-trending-up' : 'bx-minus') ?>'></i>
                            <?= htmlspecialchars($transaksi_change_label) ?>
                        </div>
                        <div class="stat-progress"><span style="width: <?= min(100, max(18, $total_transaksi * 8)) ?>%"></span></div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-card__head">
                            <div class="stat-icon">
                                <i class='bx bx-error-circle'></i>
                            </div>
                            <div class="stat-caption">Alert</div>
                        </div>
                        <div class="stat-number"><?= number_format($stok_min_count) ?></div>
                        <div class="stat-label">Stok Minimum</div>
                        <div class="stat-meta <?= htmlspecialchars($stok_min_class) ?>">
                            <i class='bx <?= $stok_min_count > 0 ? 'bx-shield-quarter' : 'bx-shield' ?>'></i>
                            <?= htmlspecialchars($stok_min_label) ?>
                        </div>
                        <div class="stat-progress"><span style="width: <?= min(100, max(12, $stok_min_count * 12)) ?>%"></span></div>
                    </div>
                </div>

                <div class="content-section">
                    <h2 class="section-title">
                        <i class='bx bx-navigation'></i>
                        Akses Cepat
                    </h2>

                    <div class="quick-actions">
                        <a href="gudang/gudang_antapani.php" class="action-card">
                            <span class="action-tag">Gudang</span>
                            <div class="action-icon">
                                <i class='bx bx-store-alt'></i>
                            </div>
                            <div class="action-title"><?= htmlspecialchars($gudang_antapani_nama) ?></div>
                            <div class="action-desc">Kelola stok <?= htmlspecialchars($gudang_antapani_nama) ?></div>
                        </a>

                        <a href="gudang/gudang_central.php" class="action-card">
                            <span class="action-tag">Gudang</span>
                            <div class="action-icon">
                                <i class='bx bx-store'></i>
                            </div>
                            <div class="action-title"><?= htmlspecialchars($gudang_central_nama) ?></div>
                            <div class="action-desc">Kelola stok <?= htmlspecialchars($gudang_central_nama) ?></div>
                        </a>

                        <a href="pembelian/po/index.php" class="action-card">
                            <span class="action-tag">Pembelian</span>
                            <div class="action-icon">
                                <i class='bx bx-purchase-tag'></i>
                            </div>
                            <div class="action-title">Purchase Order</div>
                            <div class="action-desc">Kelola pesanan pembelian</div>
                        </a>

                        <a href="stok/masuk/index.php" class="action-card">
                            <span class="action-tag">Stok</span>
                            <div class="action-icon">
                                <i class='bx bx-plus-circle'></i>
                            </div>
                            <div class="action-title">Stok Masuk</div>
                            <div class="action-desc">Tambah stok barang</div>
                        </a>

                        <a href="stok/keluar/index.php" class="action-card">
                            <span class="action-tag">Stok</span>
                            <div class="action-icon">
                                <i class='bx bx-minus-circle'></i>
                            </div>
                            <div class="action-title">Stok Keluar</div>
                            <div class="action-desc">Kurangi stok barang</div>
                        </a>

                        <a href="stok/transfer/index.php" class="action-card">
                            <span class="action-tag">Transfer</span>
                            <div class="action-icon">
                                <i class='bx bx-transfer'></i>
                            </div>
                            <div class="action-title">Transfer Stok</div>
                            <div class="action-desc">Pindahkan antar gudang</div>
                        </a>
                    </div>
                </div>

                <div class="content-section">
                    <h2 class="section-title">
                        <i class='bx bx-history'></i>
                        Transaksi Terbaru
                    </h2>

                    <div class="table-container">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Tanggal</th>
                                        <th>Gudang</th>
                                        <th>Barang</th>
                                        <th>Jenis</th>
                                        <th class="text-end">Jumlah</th>
                                        <th>Dibuat Oleh</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($transaksi_result->num_rows > 0): ?>
                                        <?php while($row = $transaksi_result->fetch_assoc()): ?>
                                        <tr>
                                            <td><?= $row['formatted_tanggal'] ?></td>
                                            <td><?= $row['nama_gudang'] ?></td>
                                            <td><?= $row['nama_barang'] ?? '-' ?></td>
                                            <td>
                                                <?php if($row['jenis_transaksi'] == 'transfer'): ?>
                                                    <span class="badge bg-primary">Transfer</span>
                                                <?php else: ?>
                                                    <span class="badge bg-<?= $row['jenis_transaksi'] == 'masuk' ? 'success' : 'danger' ?>">
                                                        <?= ucfirst($row['jenis_transaksi']) ?>
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end"><?= number_format($row['total_jumlah'] ?? 0) ?></td>
                                            <td><?= $row['created_by'] ?></td>
                                        </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="text-center py-4">
                                                <i class='bx bx-info-circle text-muted' style="font-size: 2rem;"></i>
                                                <p class="mt-2 text-muted mb-0">Tidak ada transaksi terbaru</p>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="content-section" id="stock-status-section">
                    <h2 class="section-title">
                        <i class='bx bx-error-circle'></i>
                        Status Stok
                    </h2>

                    <div class="table-container">
                        <ul class="nav nav-tabs" id="stokTab" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="minimum-tab" data-bs-toggle="tab" data-bs-target="#minimum" type="button" role="tab">
                                    <i class='bx bx-warning'></i> Stok Minimum
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="habis-tab" data-bs-toggle="tab" data-bs-target="#habis" type="button" role="tab">
                                    <i class='bx bx-x-circle'></i> Stok Habis
                                </button>
                            </li>
                        </ul>

                        <div class="tab-content" id="stokTabContent">
                            <div class="tab-pane fade show active" id="minimum" role="tabpanel">
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Kode</th>
                                                <th>Barang</th>
                                                <th>Gudang</th>
                                                <th>Stok Awal</th>
                                                <th>Stok Terpakai</th>
                                                <th>Stok Akhir</th>
                                                <th>Min Stok</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            $stok_min_result->data_seek(0);
                                            while($row = $stok_min_result->fetch_assoc()):
                                                $stok_akhir = $row['stok_awal'] - $row['stok_terpakai'];
                                                $stok_min = $row['stok_minimum'];
                                                $row_class = '';
                                                if ($stok_akhir <= 0) {
                                                    $row_class = 'stok-habis';
                                                } elseif ($stok_akhir <= $stok_min) {
                                                    $row_class = 'stok-minimum';
                                                } else {
                                                    $row_class = 'stok-aman';
                                                }
                                            ?>
                                            <tr class="<?= $row_class ?>">
                                                <td><strong><?= $row['kode_barang'] ?></strong></td>
                                                <td><?= $row['nama_barang'] ?></td>
                                                <td><?= $row['nama_gudang'] ?></td>
                                                <td><?= number_format($row['stok_awal']) ?></td>
                                                <td><?= number_format($row['stok_terpakai']) ?></td>
                                                <td><strong><?= number_format($stok_akhir) ?></strong></td>
                                                <td><?= number_format($stok_min) ?></td>
                                            </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div class="tab-pane fade" id="habis" role="tabpanel">
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Kode</th>
                                                <th>Barang</th>
                                                <th>Gudang</th>
                                                <th>Stok Awal</th>
                                                <th>Stok Terpakai</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while($row = $stok_habis_result->fetch_assoc()): ?>
                                            <tr class="stok-habis">
                                                <td><strong><?= $row['kode_barang'] ?></strong></td>
                                                <td><?= $row['nama_barang'] ?></td>
                                                <td><?= $row['nama_gudang'] ?></td>
                                                <td><?= number_format($row['stok_awal']) ?></td>
                                                <td><?= number_format($row['stok_terpakai']) ?></td>
                                                <td>
                                                    <span class="badge bg-danger">
                                                        <i class='bx bx-x-circle'></i> Habis
                                                    </span>
                                                </td>
                                            </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xxl-4 col-xl-5">
                <div class="side-card">
                    <div class="side-card__header">
                        <div class="side-card__title">
                            <i class='bx bx-pie-chart-alt-2'></i>
                            Ringkasan Stok
                        </div>
                        <select class="dash-select" aria-label="Ringkasan">
                            <option selected>Per Kategori</option>
                        </select>
                    </div>
                    <div class="side-summary">
                        <div class="side-summary__item">
                            <div class="side-summary__label">Butuh Restock</div>
                            <div class="side-summary__value"><?= number_format($stok_min_count) ?></div>
                        </div>
                        <div class="side-summary__item">
                            <div class="side-summary__label">Stok Habis</div>
                            <div class="side-summary__value"><?= number_format($stok_habis_count) ?></div>
                        </div>
                    </div>
                    <div style="position: relative; height: 240px;">
                        <canvas id="stockByCategoryChart"></canvas>
                    </div>
                    <div class="legend-list" id="stockByCategoryLegend"></div>
                </div>

                <div class="side-card">
                    <div class="side-card__header">
                        <div class="side-card__title">
                            <i class='bx bx-line-chart'></i>
                            Aktivitas Hari Ini
                        </div>
                    </div>
                    <div style="position: relative; height: 180px;">
                        <canvas id="activityChart"></canvas>
                    </div>
                    <div class="activity-stats">
                        <div class="activity-stat">
                            <div class="activity-stat__label">Transaksi</div>
                            <div class="activity-stat__value"><?= number_format($total_transaksi) ?></div>
                        </div>
                        <div class="activity-stat">
                            <div class="activity-stat__label">Total Item</div>
                            <div class="activity-stat__value"><?= number_format($total_item_today) ?></div>
                        </div>
                        <div class="activity-stat">
                            <div class="activity-stat__label">Gudang Aktif</div>
                            <div class="activity-stat__value"><?= number_format($gudang_aktif_today) ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script>
        // Track if user has interacted with the page
        let userHasInteracted = false;
        
        // Function to play sound only after user interaction
        function playSound(soundId) {
            if (!userHasInteracted) {
                console.log("Sound not played - user hasn't interacted with page yet");
                return;
            }
            
            const sound = document.getElementById(soundId);
            if (sound) {
                sound.currentTime = 0;
                sound.play().catch(e => console.error("Error playing sound:", e));
            }
        }

        // Function to show toast notification
        function showToast(message, type = 'info') {
            // Create toast element
            const toastContainer = document.getElementById('toastContainer') || createToastContainer();
            const toastHtml = `
                <div class="toast align-items-center text-white bg-${type} border-0" role="alert" aria-live="assertive" aria-atomic="true">
                    <div class="d-flex">
                        <div class="toast-body">
                            ${message}
                        </div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                    </div>
                </div>`;
            
            toastContainer.insertAdjacentHTML('beforeend', toastHtml);
            const toastEl = toastContainer.lastElementChild;
            const toast = new bootstrap.Toast(toastEl);
            toast.show();
            
            // Remove toast element after it's hidden
            toastEl.addEventListener('hidden.bs.toast', () => {
                toastEl.remove();
            });
        }

        function createToastContainer() {
            const container = document.createElement('div');
            container.id = 'toastContainer';
            container.className = 'toast-container position-fixed top-0 end-0 p-3';
            container.style.zIndex = '9999';
            document.body.appendChild(container);
            return container;
        }

        // Initialize dashboard
        document.addEventListener('DOMContentLoaded', function() {
            // Mark user interaction on any click, touch, or keypress
            const markUserInteraction = () => {
                if (!userHasInteracted) {
                    userHasInteracted = true;
                    console.log("User interaction detected - sounds enabled");
                }
            };
            
            // Listen for user interactions
            document.addEventListener('click', markUserInteraction, { once: true });
            document.addEventListener('touchstart', markUserInteraction, { once: true });
            document.addEventListener('keydown', markUserInteraction, { once: true });
            
            // Add smooth scrolling
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function (e) {
                    const href = this.getAttribute('href');
                    // Skip if href is just "#" or empty
                    if (href === '#' || href === '') {
                        return;
                    }
                    
                    e.preventDefault();
                    const target = document.querySelector(href);
                    if (target) {
                        target.scrollIntoView({
                            behavior: 'smooth',
                            block: 'start'
                        });
                    }
                });
            });

            // Add loading animation to cards
            const cards = document.querySelectorAll('.stat-card, .action-card, .side-card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    card.style.transition = 'all 0.6s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });

            const dashNotifyBtn = document.getElementById('dashNotifyBtn');
            const stockStatusSection = document.getElementById('stock-status-section');
            if (dashNotifyBtn && stockStatusSection) {
                dashNotifyBtn.addEventListener('click', function () {
                    stockStatusSection.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                });
            }

            const kategoriLabels = <?= json_encode($kategori_labels, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
            const kategoriValues = <?= json_encode($kategori_values, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
            const activityLabels = <?= json_encode($activity_labels, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
            const activityValues = <?= json_encode($activity_counts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

            const palette = ['#0056b3', '#2f7ad1', '#f59e0b', '#22c55e', '#ef4444', '#14b8a6', '#64748b'];
            const stockCtx = document.getElementById('stockByCategoryChart');
            if (stockCtx && window.Chart && Array.isArray(kategoriLabels) && kategoriLabels.length > 0) {
                const colors = kategoriLabels.map((_, idx) => palette[idx % palette.length]);
                const chart = new Chart(stockCtx, {
                    type: 'doughnut',
                    data: {
                        labels: kategoriLabels,
                        datasets: [{
                            data: kategoriValues,
                            backgroundColor: colors,
                            borderColor: '#ffffff',
                            borderWidth: 3,
                            hoverOffset: 4,
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        cutout: '68%',
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                callbacks: {
                                    label: (ctx) => {
                                        const label = ctx.label || '';
                                        const val = Number(ctx.raw || 0);
                                        const total = (ctx.dataset.data || []).reduce((a, b) => a + Number(b || 0), 0);
                                        const pct = total > 0 ? (val / total * 100) : 0;
                                        return `${label}: ${val.toLocaleString('id-ID')} (${pct.toFixed(1)}%)`;
                                    }
                                }
                            }
                        }
                    }
                });

                const legendEl = document.getElementById('stockByCategoryLegend');
                if (legendEl) {
                    const total = (kategoriValues || []).reduce((a, b) => a + Number(b || 0), 0);
                    const escapeHtml = (unsafe) => {
                        const s = String(unsafe ?? '');
                        return s.replace(/[&<>"']/g, (ch) => ({
                            '&': '&amp;',
                            '<': '&lt;',
                            '>': '&gt;',
                            '"': '&quot;',
                            "'": '&#039;'
                        }[ch]));
                    };

                    legendEl.innerHTML = kategoriLabels.map((label, idx) => {
                        const val = Number(kategoriValues[idx] || 0);
                        const pct = total > 0 ? (val / total * 100) : 0;
                        const color = colors[idx];
                        return `
                            <div class="legend-item">
                                <div class="legend-left">
                                    <span class="legend-dot" style="background:${color}"></span>
                                    <span class="legend-label">${escapeHtml(label)}</span>
                                </div>
                                <span class="legend-value">${pct.toFixed(0)}%</span>
                            </div>
                        `;
                    }).join('');
                }
            }

            const actCtx = document.getElementById('activityChart');
            if (actCtx && window.Chart && Array.isArray(activityLabels) && activityLabels.length === 24) {
                new Chart(actCtx, {
                    type: 'line',
                    data: {
                        labels: activityLabels,
                        datasets: [{
                            label: 'Transaksi',
                            data: activityValues,
                            borderColor: '#0056b3',
                            backgroundColor: 'rgba(0, 86, 179, 0.12)',
                            fill: true,
                            tension: 0.35,
                            pointRadius: 0,
                            pointHitRadius: 10,
                            borderWidth: 2
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            x: {
                                grid: { display: false },
                                ticks: {
                                    maxRotation: 0,
                                    autoSkip: true,
                                    callback: function(value) {
                                        const label = this.getLabelForValue(value);
                                        const hour = Number((label || '').slice(0, 2));
                                        return (hour % 6 === 0) ? label : '';
                                    },
                                    font: { size: 11, weight: '700' }
                                }
                            },
                            y: {
                                beginAtZero: true,
                                ticks: { precision: 0, font: { size: 11, weight: '700' } },
                                grid: { color: 'rgba(15, 23, 42, 0.06)' }
                            }
                        },
                        plugins: {
                            legend: { display: false },
                            tooltip: { intersect: false, mode: 'index' }
                        }
                    }
                });
            }

            // Check for stock alerts - only show toast, don't play sound initially
            const stokMinCount = <?= $stok_min_count ?>;
            const stokHabisCount = <?= $stok_habis_count ?>;
            
            if (stokHabisCount > 0) {
                showToast(`Ada ${stokHabisCount} barang yang stoknya habis!`, 'danger');
                // Play sound after user interaction
                setTimeout(() => {
                    if (userHasInteracted) {
                        playSound('stokhabisSound');
                    }
                }, 1000);
            } else if (stokMinCount > 0) {
                showToast(`Ada ${stokMinCount} barang yang stoknya mencapai batas minimum!`, 'warning');
                // Play sound after user interaction
                setTimeout(() => {
                    if (userHasInteracted) {
                        playSound('warningSound');
                    }
                }, 1000);
            }
        });
    </script>
</body>
</html>
