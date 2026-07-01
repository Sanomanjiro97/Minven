<?php
require 'config.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

$gudang_id = $_SESSION['gudang_id'] ?? 1;

 $filter_session_key = 'filter_rekap_absensi';
if (isset($_GET['reset']) && (string)$_GET['reset'] === '1') {
    unset($_SESSION[$filter_session_key]);
    header('Location: rekap_absensi.php');
    exit();
}

$saved_filters = [];
if (isset($_SESSION[$filter_session_key]) && is_array($_SESSION[$filter_session_key])) {
    $saved_filters = $_SESSION[$filter_session_key];
}

$filter_keys = ['tanggal_awal', 'tanggal_akhir', 'shift_id', 'user_id'];
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
        if (array_key_exists($k, $saved_filters) && (string)$saved_filters[$k] !== '') {
            $redirect_params[$k] = (string)$saved_filters[$k];
        }
    }
    if (!empty($redirect_params)) {
        header('Location: rekap_absensi.php?' . http_build_query($redirect_params));
        exit();
    }
}

// Ambil parameter filter
$tanggal_awal = isset($_GET['tanggal_awal']) ? (string)$_GET['tanggal_awal'] : (string)($saved_filters['tanggal_awal'] ?? date('Y-m-01'));
$tanggal_akhir = isset($_GET['tanggal_akhir']) ? (string)$_GET['tanggal_akhir'] : (string)($saved_filters['tanggal_akhir'] ?? date('Y-m-d'));
$shift_id = isset($_GET['shift_id']) ? (string)$_GET['shift_id'] : (string)($saved_filters['shift_id'] ?? '');
$user_id = isset($_GET['user_id']) ? (string)$_GET['user_id'] : (string)($saved_filters['user_id'] ?? '');

$_SESSION[$filter_session_key] = [
    'tanggal_awal' => $tanggal_awal,
    'tanggal_akhir' => $tanggal_akhir,
    'shift_id' => $shift_id,
    'user_id' => $user_id,
];

// Query dasar
$sql = "
    SELECT 
        a.*, 
        u.username,
        u.nama_lengkap,
        s.nama_shift,
        s.jam_mulai as shift_mulai,
        s.jam_selesai as shift_selesai,
        g.nama_gudang
    FROM absensi a
    JOIN users u ON u.id = a.user_id
    LEFT JOIN shifts s ON s.id = a.shift_id
    LEFT JOIN gudang g ON g.id = a.gudang_id
    WHERE a.gudang_id = ? 
    AND a.tanggal BETWEEN ? AND ?
";

$params = [$gudang_id, $tanggal_awal, $tanggal_akhir];
$types = "iss";

// Tambahkan filter shift jika dipilih
if ($shift_id != '') {
    $sql .= " AND a.shift_id = ?";
    $params[] = $shift_id;
    $types .= "i";
}

// Tambahkan filter user jika dipilih
if ($user_id != '') {
    $sql .= " AND a.user_id = ?";
    $params[] = $user_id;
    $types .= "i";
}

$sql .= " ORDER BY a.tanggal DESC, a.jam_masuk ASC";

// Eksekusi query dengan prepared statement
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

// Ambil data shift untuk dropdown
$shift_sql = "SELECT * FROM shifts WHERE is_active = 1 ORDER BY jam_mulai";
$shifts = $conn->query($shift_sql);

// Ambil data user untuk dropdown
$user_sql = "SELECT id, username, nama_lengkap FROM users WHERE is_active = 1 ORDER BY username";
$users = $conn->query($user_sql);

// Hitung statistik
$stat_sql = "
    SELECT 
        COUNT(*) as total_absen,
        COUNT(CASE WHEN status_kehadiran = 'Hadir' THEN 1 END) as total_hadir,
        COUNT(CASE WHEN status_kehadiran = 'Izin' THEN 1 END) as total_izin,
        COUNT(CASE WHEN status_kehadiran = 'Sakit' THEN 1 END) as total_sakit,
        COUNT(CASE WHEN status_kehadiran = 'Alpa' THEN 1 END) as total_alpa,
        COUNT(CASE WHEN keterlambatan > '00:00:00' THEN 1 END) as total_terlambat
    FROM absensi 
    WHERE gudang_id = ? AND tanggal BETWEEN ? AND ?
";

$stat_stmt = $conn->prepare($stat_sql);
$stat_stmt->bind_param("iss", $gudang_id, $tanggal_awal, $tanggal_akhir);
$stat_stmt->execute();
$stats = $stat_stmt->get_result()->fetch_assoc();

// Fungsi untuk format durasi
function formatDurasi($durasi) {
    if (empty($durasi) || $durasi == '00:00:00') {
        return '-';
    }
    
    $parts = explode(':', $durasi);
    $jam = (int)$parts[0];
    $menit = (int)$parts[1];
    
    if ($jam > 0) {
        return $jam . ' jam ' . $menit . ' menit';
    } else {
        return $menit . ' menit';
    }
}

// Fungsi untuk format keterlambatan
function formatKeterlambatan($keterlambatan) {
    if (empty($keterlambatan) || $keterlambatan == '00:00:00') {
        return '-';
    }
    
    $parts = explode(':', $keterlambatan);
    $jam = (int)$parts[0];
    $menit = (int)$parts[1];
    
    if ($jam > 0) {
        return $jam . ' jam ' . $menit . ' menit';
    } else {
        return $menit . ' menit';
    }
}

// Fungsi untuk get status badge
function getStatusBadge($status) {
    $badges = [
        'Hadir' => 'success',
        'Izin' => 'warning',
        'Sakit' => 'info',
        'Alpa' => 'danger'
    ];
    
    $badge = $badges[$status] ?? 'secondary';
    return "<span class='badge bg-{$badge}'>{$status}</span>";
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rekap Absensi - Sistem Inventory</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <style>
        .rekap-header {
            background: linear-gradient(135deg, #4e73df, #224abe);
            color: white;
            padding: 2rem;
            border-radius: 15px;
            margin-bottom: 2rem;
        }
        .stat-card {
            border-radius: 15px;
            padding: 1.5rem;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            border: none;
            transition: transform 0.3s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }
        .filter-card {
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            border: none;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        .table-responsive {
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            background: white;
        }
        .btn-export {
            background: linear-gradient(135deg, #1cc88a, #13855c);
            border: none;
            padding: 0.5rem 1.5rem;
            border-radius: 10px;
        }
        .btn-filter {
            background: linear-gradient(135deg, #4e73df, #224abe);
            border: none;
            padding: 0.5rem 1.5rem;
            border-radius: 10px;
        }
    </style>
</head>
<body>
    <?php include '../templates/navbar.php'; ?>
    
    <div class="container mt-4">
        <!-- Header -->
        <div class="rekap-header">
            <h2><i class='bx bx-calendar-check me-2'></i>Rekap Absensi Karyawan</h2>
            <p class="mb-0">Sistem monitoring kehadiran karyawan berdasarkan shift</p>
        </div>

        <!-- Statistik -->
        <div class="row mb-4">
            <div class="col-md-2">
                <div class="card stat-card bg-primary text-white">
                    <div class="stat-number"><?= $stats['total_absen'] ?></div>
                    <div>Total Absen</div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card stat-card bg-success text-white">
                    <div class="stat-number"><?= $stats['total_hadir'] ?></div>
                    <div>Total Hadir</div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card stat-card bg-warning text-white">
                    <div class="stat-number"><?= $stats['total_izin'] ?></div>
                    <div>Total Izin</div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card stat-card bg-info text-white">
                    <div class="stat-number"><?= $stats['total_sakit'] ?></div>
                    <div>Total Sakit</div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card stat-card bg-danger text-white">
                    <div class="stat-number"><?= $stats['total_alpa'] ?></div>
                    <div>Total Alpa</div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card stat-card bg-secondary text-white">
                    <div class="stat-number"><?= $stats['total_terlambat'] ?></div>
                    <div>Total Terlambat</div>
                </div>
            </div>
        </div>

        <!-- Filter -->
        <div class="card filter-card">
            <h5><i class='bx bx-filter me-2'></i>Filter Data</h5>
            <form method="GET" class="row">
                <div class="col-md-3">
                    <label for="tanggal_awal" class="form-label">Tanggal Awal:</label>
                    <input type="date" class="form-control" id="tanggal_awal" name="tanggal_awal" value="<?= $tanggal_awal ?>">
                </div>
                <div class="col-md-3">
                    <label for="tanggal_akhir" class="form-label">Tanggal Akhir:</label>
                    <input type="date" class="form-control" id="tanggal_akhir" name="tanggal_akhir" value="<?= $tanggal_akhir ?>">
                </div>
                <div class="col-md-2">
                    <label for="shift_id" class="form-label">Shift:</label>
                    <select class="form-select" id="shift_id" name="shift_id">
                        <option value="">Semua Shift</option>
                        <?php while($shift = $shifts->fetch_assoc()): ?>
                            <option value="<?= $shift['id'] ?>" <?= $shift_id == $shift['id'] ? 'selected' : '' ?>>
                                <?= $shift['nama_shift'] ?> (<?= $shift['jam_mulai'] ?> - <?= $shift['jam_selesai'] ?>)
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="user_id" class="form-label">Karyawan:</label>
                    <select class="form-select" id="user_id" name="user_id">
                        <option value="">Semua Karyawan</option>
                        <?php while($user = $users->fetch_assoc()): ?>
                            <option value="<?= $user['id'] ?>" <?= $user_id == $user['id'] ? 'selected' : '' ?>>
                                <?= $user['nama_lengkap'] ?> (<?= $user['username'] ?>)
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-filter me-2">
                        <i class='bx bx-search me-1'></i>Filter
                    </button>
                    <button type="button" class="btn btn-export" onclick="exportToExcel()">
                        <i class='bx bx-download me-1'></i>Excel
                    </button>
                </div>
            </form>
        </div>

        <!-- Tabel Rekap -->
        <div class="table-responsive">
            <table class="table table-striped table-hover mb-0">
                <thead class="table-dark">
                    <tr>
                        <th>No</th>
                        <th>Tanggal</th>
                        <th>Karyawan</th>
                        <th>Shift</th>
                        <th>Jam Masuk</th>
                        <th>Jam Keluar</th>
                        <th>Durasi</th>
                        <th>Keterlambatan</th>
                        <th>Status</th>
                        <th>Gudang</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $no = 1;
                    if ($result->num_rows > 0): 
                        while($row = $result->fetch_assoc()): 
                    ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td><?= date('d/m/Y', strtotime($row['tanggal'])) ?></td>
                            <td>
                                <strong><?= $row['nama_lengkap'] ?></strong><br>
                                <small class="text-muted">@<?= $row['username'] ?></small>
                            </td>
                            <td>
                                <?php if($row['nama_shift']): ?>
                                    <strong><?= $row['nama_shift'] ?></strong><br>
                                    <small class="text-muted"><?= $row['shift_mulai'] ?> - <?= $row['shift_selesai'] ?></small>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td><?= $row['jam_masuk'] ?></td>
                            <td><?= $row['jam_keluar'] ?? '-' ?></td>
                            <td><?= formatDurasi($row['durasi']) ?></td>
                            <td><?= formatKeterlambatan($row['keterlambatan']) ?></td>
                            <td><?= getStatusBadge($row['status_kehadiran']) ?></td>
                            <td><?= $row['nama_gudang'] ?></td>
                        </tr>
                    <?php 
                        endwhile;
                    else: 
                    ?>
                        <tr>
                            <td colspan="10" class="text-center text-muted">
                                <i class='bx bx-calendar-x me-2'></i>Tidak ada data absensi untuk periode yang dipilih
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script>
        function exportToExcel() {
            // Ambil data dari tabel
            const table = document.querySelector('table');
            const workbook = XLSX.utils.table_to_book(table, {sheet: "Rekap Absensi"});
            
            // Download file
            XLSX.writeFile(workbook, 'Rekap_Absensi_<?= date('Y-m-d') ?>.xlsx');
        }

        // Auto-submit form saat tanggal berubah
        document.getElementById('tanggal_awal').addEventListener('change', function() {
            this.form.submit();
        });
        
        document.getElementById('tanggal_akhir').addEventListener('change', function() {
            this.form.submit();
        });
    </script>
</body>
</html>
