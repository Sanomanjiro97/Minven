<?php
require 'config.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$user_id   = $_SESSION['user_id'];
$gudang_id = $_SESSION['gudang_id'] ?? 1;

$tanggal = date('Y-m-d');
$jam     = date('H:i:s');

// Ambil data shift yang tersedia
$shift_sql = "SELECT * FROM shifts WHERE is_active = 1 ORDER BY jam_mulai";
$shifts = $conn->query($shift_sql);

// Cek absensi hari ini
$sql = "SELECT * FROM absensi WHERE user_id=? AND tanggal=?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("is", $user_id, $tanggal);
$stmt->execute();
$cek = $stmt->get_result();

// Proses check-in/check-out
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action == 'checkin') {
        $shift_id = $_POST['shift_id'] ?? 0;
        
        // Validasi shift
        if ($shift_id == 0) {
            echo json_encode(['success' => false, 'message' => 'Pilih shift terlebih dahulu!']);
            exit;
        }
        
        // Cek apakah sudah absen hari ini
        if ($cek->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'Anda sudah melakukan check-in hari ini!']);
            exit;
        }
        
        // Insert check-in
        $insert_sql = "INSERT INTO absensi (user_id, gudang_id, tanggal, jam_masuk, shift_id, status_kehadiran) VALUES (?, ?, ?, ?, ?, 'Hadir')";
        $insert_stmt = $conn->prepare($insert_sql);
        $insert_stmt->bind_param("iissi", $user_id, $gudang_id, $tanggal, $jam, $shift_id);
        
        if ($insert_stmt->execute()) {
            echo json_encode(['success' => true, 'message' => "Check-in berhasil pada jam $jam", 'jam_masuk' => $jam]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Gagal melakukan check-in!']);
        }
        exit;
        
    } elseif ($action == 'checkout') {
        if ($cek->num_rows == 0) {
            echo json_encode(['success' => false, 'message' => 'Anda belum check-in hari ini!']);
            exit;
        }
        
        $data = $cek->fetch_assoc();
        
        if ($data['jam_keluar'] != NULL) {
            echo json_encode(['success' => false, 'message' => 'Anda sudah check-out hari ini!']);
            exit;
        }
        
        $jam_masuk = $data['jam_masuk'];
        $durasi = gmdate('H:i:s', strtotime($jam) - strtotime($jam_masuk));
        
        // Update check-out
        $update_sql = "UPDATE absensi SET jam_keluar=?, durasi=?, status_kehadiran='Hadir' WHERE id=?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("ssi", $jam, $durasi, $data['id']);
        
        if ($update_stmt->execute()) {
            echo json_encode(['success' => true, 'message' => "Check-out berhasil pada jam $jam", 'jam_keluar' => $jam, 'durasi' => $durasi]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Gagal melakukan check-out!']);
        }
        exit;
    }
}

// Ambil data absensi hari ini jika ada
$absensi_hari_ini = null;
if ($cek->num_rows > 0) {
    $absensi_hari_ini = $cek->fetch_assoc();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Absensi - Sistem Inventory</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <style>
        .absensi-card {
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            border: none;
        }
        .time-display {
            font-size: 2.5rem;
            font-weight: bold;
            color: #4e73df;
        }
        .date-display {
            font-size: 1.2rem;
            color: #6c757d;
        }
        .btn-checkin {
            background: linear-gradient(135deg, #1cc88a, #13855c);
            border: none;
            padding: 15px 30px;
            font-size: 1.1rem;
            font-weight: 600;
        }
        .btn-checkout {
            background: linear-gradient(135deg, #e74a3b, #be2617);
            border: none;
            padding: 15px 30px;
            font-size: 1.1rem;
            font-weight: 600;
        }
        .status-card {
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .shift-select {
            border-radius: 10px;
            padding: 10px 15px;
            font-size: 1rem;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/templates/navbar.php'; ?>
    
    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card absensi-card">
                    <div class="card-body p-4">
                        <div class="text-center mb-4">
                            <h3 class="card-title mb-3">
                                <i class='bx bx-time-five me-2'></i>Absensi Karyawan
                            </h3>
                            <div class="time-display" id="currentTime"></div>
                            <div class="date-display" id="currentDate"></div>
                        </div>

                        <?php if ($absensi_hari_ini): ?>
                            <!-- Status Check-in -->
                            <div class="status-card bg-success text-white">
                                <div class="row align-items-center">
                                    <div class="col-md-8">
                                        <h5><i class='bx bx-check-circle me-2'></i>Anda sudah check-in</h5>
                                        <p class="mb-0">Jam Masuk: <?= $absensi_hari_ini['jam_masuk'] ?></p>
                                        <p class="mb-0">Shift: <?= $absensi_hari_ini['shift_id'] ? 'Shift ' . $absensi_hari_ini['shift_id'] : '-' ?></p>
                                    </div>
                                    <div class="col-md-4 text-end">
                                        <?php if ($absensi_hari_ini['jam_keluar']): ?>
                                            <button class="btn btn-secondary" disabled>
                                                <i class='bx bx-log-out me-2'></i>Sudah Check-out
                                            </button>
                                        <?php else: ?>
                                            <button class="btn btn-checkout" onclick="doCheckout()">
                                                <i class='bx bx-log-out me-2'></i>Check-out
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <!-- Form Check-in -->
                            <div class="status-card bg-light">
                                <h5><i class='bx bx-log-in me-2'></i>Check-in Hari Ini</h5>
                                <div class="mb-3">
                                    <label for="shift_id" class="form-label">Pilih Shift:</label>
                                    <select class="form-select shift-select" id="shift_id" required>
                                        <option value="">-- Pilih Shift --</option>
                                        <?php while($shift = $shifts->fetch_assoc()): ?>
                                            <option value="<?= $shift['id'] ?>">
                                                <?= $shift['nama_shift'] ?> (<?= $shift['jam_mulai'] ?> - <?= $shift['jam_selesai'] ?>)
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                <button class="btn btn-checkin w-100" onclick="doCheckin()">
                                    <i class='bx bx-log-in me-2'></i>Check-in
                                </button>
                            </div>
                        <?php endif; ?>

                        <!-- Riwayat Absensi -->
                        <div class="mt-4">
                            <h5><i class='bx bx-history me-2'></i>Riwayat Absensi 7 Hari Terakhir</h5>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Tanggal</th>
                                            <th>Jam Masuk</th>
                                            <th>Jam Keluar</th>
                                            <th>Durasi</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $history_sql = "SELECT * FROM absensi WHERE user_id=? ORDER BY tanggal DESC LIMIT 7";
                                        $history_stmt = $conn->prepare($history_sql);
                                        $history_stmt->bind_param("i", $user_id);
                                        $history_stmt->execute();
                                        $history_result = $history_stmt->get_result();
                                        
                                        while($history = $history_result->fetch_assoc()):
                                        ?>
                                        <tr>
                                            <td><?= date('d/m/Y', strtotime($history['tanggal'])) ?></td>
                                            <td><?= $history['jam_masuk'] ?></td>
                                            <td><?= $history['jam_keluar'] ?? '-' ?></td>
                                            <td><?= $history['durasi'] ?? '-' ?></td>
                                            <td>
                                                <span class="badge bg-<?= $history['status_kehadiran'] == 'Hadir' ? 'success' : 'warning' ?>">
                                                    <?= $history['status_kehadiran'] ?>
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
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Update waktu secara real-time
        function updateTime() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('id-ID');
            const dateString = now.toLocaleDateString('id-ID', { 
                weekday: 'long', 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric' 
            });
            
            document.getElementById('currentTime').textContent = timeString;
            document.getElementById('currentDate').textContent = dateString;
        }
        
        updateTime();
        setInterval(updateTime, 1000);

        // Fungsi Check-in
        function doCheckin() {
            const shiftId = document.getElementById('shift_id').value;
            
            if (!shiftId) {
                alert('Pilih shift terlebih dahulu!');
                return;
            }

            fetch('absensi.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=checkin&shift_id=' + shiftId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    location.reload();
                } else {
                    alert(data.message);
                }
            })
            .catch(error => {
                alert('Terjadi kesalahan: ' + error);
            });
        }

        // Fungsi Check-out
        function doCheckout() {
            if (confirm('Apakah Anda yakin ingin check-out?')) {
                fetch('absensi.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=checkout'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(data.message + ' | Durasi: ' + data.durasi);
                        location.reload();
                    } else {
                        alert(data.message);
                    }
                })
                .catch(error => {
                    alert('Terjadi kesalahan: ' + error);
                });
            }
        }
    </script>
</body>
</html>
