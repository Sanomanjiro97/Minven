<?php
require_once __DIR__ . '/_init.php';
kasir_require_login();

$user = kasir_current_user();
$message = '';
$error = '';

// -----------------------------------------------------------------------------
// 1. KONEKSI DATABASE POS (Fallback & Getter Detection)
// -----------------------------------------------------------------------------
$db = null;

if (isset($posConn) && $posConn instanceof mysqli && !$posConn->connect_error) {
    $db = $posConn;
} elseif (function_exists('pos_db_conn') && pos_db_conn()) {
    $db = pos_db_conn();
} elseif (isset($kasirPosConn) && $kasirPosConn instanceof mysqli && !$kasirPosConn->connect_error) {
    $db = $kasirPosConn;
} elseif (isset($conn) && $conn instanceof mysqli && !$conn->connect_error) {
    $db = $conn; // Fallback terakhir ke DB Utama jika POS tidak tersedia
}

if (!$db || $db->connect_error) {
    die("Koneksi database gagal. Harap periksa file konfigurasi init Anda.");
}

// -----------------------------------------------------------------------------
// 2. AUTO CREATE TABLE 'meja' JIKA BELUM ADA (Auto Schema Migration)
// -----------------------------------------------------------------------------
$checkTable = $db->query("SHOW TABLES LIKE 'meja'");
if (!$checkTable || $checkTable->num_rows === 0) {
    $createTableSql = "CREATE TABLE IF NOT EXISTS `meja` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `nomor_meja` VARCHAR(50) NOT NULL UNIQUE,
        `kapasitas` INT DEFAULT 2,
        `status` ENUM('kosong', 'terisi', 'reservasi') DEFAULT 'kosong',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    
    if (!$db->query($createTableSql)) {
        $error = "Gagal membuat tabel 'meja' secara otomatis: " . $db->error;
    }
}

// -----------------------------------------------------------------------------
// 3. PROSES AKSI FORM (POST)
// -----------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add') {
        $nomorMeja = trim($_POST['nomor_meja'] ?? '');
        $kapasitas = (int)($_POST['kapasitas'] ?? 2);

        if (!empty($nomorMeja)) {
            $stmt = $db->prepare("INSERT INTO meja (nomor_meja, kapasitas, status) VALUES (?, ?, 'kosong')");
            if ($stmt === false) {
                $error = "Gagal menyiapkan query: " . $db->error;
            } else {
                $stmt->bind_param("si", $nomorMeja, $kapasitas);
                if ($stmt->execute()) {
                    $message = "Meja '$nomorMeja' berhasil ditambahkan!";
                } else {
                    $error = "Gagal menambah meja. Nomor meja mungkin sudah ada (Duplicate Entry).";
                }
                $stmt->close();
            }
        } else {
            $error = "Nomor meja tidak boleh kosong.";
        }
    } elseif ($_POST['action'] === 'update_status') {
        $mejaId = (int)($_POST['meja_id'] ?? 0);
        $status = $_POST['status'] ?? 'kosong';

        $stmt = $db->prepare("UPDATE meja SET status = ? WHERE id = ?");
        if ($stmt === false) {
            $error = "Gagal mengupdate status: " . $db->error;
        } else {
            $stmt->bind_param("si", $status, $mejaId);
            if ($stmt->execute()) {
                $message = "Status meja berhasil diperbarui!";
            } else {
                $error = "Gagal memperbarui status meja.";
            }
            $stmt->close();
        }
    } elseif ($_POST['action'] === 'delete') {
        $mejaId = (int)($_POST['meja_id'] ?? 0);

        $stmt = $db->prepare("DELETE FROM meja WHERE id = ?");
        if ($stmt === false) {
            $error = "Gagal menghapus meja: " . $db->error;
        } else {
            $stmt->bind_param("i", $mejaId);
            if ($stmt->execute()) {
                $message = "Meja berhasil dihapus!";
            } else {
                $error = "Gagal menghapus meja.";
            }
            $stmt->close();
        }
    }
}

// -----------------------------------------------------------------------------
// 4. FETCH SELURUH DAFTAR MEJA
// -----------------------------------------------------------------------------
$mejaList = [];
$result = $db->query("SELECT * FROM meja ORDER BY nomor_meja ASC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $mejaList[] = $row;
    }
} else {
    $error = "Gagal mengambil data meja: " . $db->error;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Meja - POS Kasir</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        body {
            margin: 0;
            background: linear-gradient(180deg, #eef4ff 0%, #f8fafc 35%, #f8fafc 100%);
            color: #0f172a;
        }
        .app-shell {
            min-height: 100vh;
            display: flex;
        }
        .sidebar {
            width: 290px;
            background: linear-gradient(180deg, #0f172a 0%, #172554 48%, #1e3a8a 100%);
            color: #fff;
            padding: 26px 20px;
            display: flex;
            flex-direction: column;
        }
        .brand-mark {
            width: 48px;
            height: 48px;
            border-radius: 16px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: rgba(255, 255, 255, 0.12);
            font-size: 1.2rem;
        }
        .nav-link-pos {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 16px;
            border-radius: 18px;
            color: rgba(255, 255, 255, 0.86);
            text-decoration: none;
            margin-bottom: 10px;
            background: rgba(255, 255, 255, 0.05);
        }
        .nav-link-pos.active,
        .nav-link-pos:hover {
            color: #fff;
            background: linear-gradient(90deg, rgba(59, 130, 246, .95), rgba(14, 165, 233, .95));
        }
        .content {
            flex: 1;
            padding: 28px;
        }
        .card-soft {
            border: 0;
            border-radius: 24px;
            box-shadow: 0 18px 45px rgba(15, 23, 42, 0.08);
            background: #fff;
        }
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.82rem;
            font-weight: 600;
        }
        .badge-kosong { background-color: #dcfce7; color: #15803d; }
        .badge-terisi { background-color: #fee2e2; color: #b91c1c; }
        .badge-reservasi { background-color: #fef3c7; color: #b45309; }

        @media (max-width: 991.98px) {
            .app-shell { display: block; }
            .sidebar { width: 100%; }
            .content { padding: 18px; }
        }
    </style>
</head>
<body>
    <div class="app-shell">
        <!-- Sidebar Navigation -->
        <aside class="sidebar">
            <div class="d-flex align-items-center gap-3 mb-4">
                <div class="brand-mark"><i class="bi bi-shop"></i></div>
                <div>
                    <div class="text-uppercase small text-white-50">Minven POS</div>
                    <div class="fw-bold fs-5">Dashboard Kasir</div>
                </div>
            </div>

            <div class="rounded-4 p-3 mb-4" style="background: rgba(255,255,255,.08);">
                <div class="small text-white-50 mb-1">Login sebagai</div>
                <div class="fw-semibold"><?= htmlspecialchars($user['nama'] ?? $user['username'] ?? 'Kasir') ?></div>
            </div>

            <a class="nav-link-pos" href="<?= htmlspecialchars(kasir_url_for('dashboard.php')) ?>">
                <i class="bi bi-grid"></i><span>Dashboard</span>
            </a>
            <a class="nav-link-pos" href="<?= htmlspecialchars(kasir_url_for('create.php')) ?>">
                <i class="bi bi-cart-plus"></i><span>Transaksi Baru</span>
            </a>
            <a class="nav-link-pos active" href="<?= htmlspecialchars(kasir_url_for('tables.php')) ?>">
                <i class="bi bi-border-all"></i><span>Management Meja</span>
            </a>
            <a class="nav-link-pos" href="<?= htmlspecialchars(kasir_url_for('shift.php')) ?>">
                <i class="bi bi-clock-history"></i><span>Shift Kasir</span>
            </a>
            <a class="nav-link-pos" href="<?= htmlspecialchars(kasir_url_for('settlement.php')) ?>">
                <i class="bi bi-file-earmark-check"></i><span>Settlement Harian</span>
            </a>

            <div class="mt-auto pt-4">
                <a class="nav-link-pos" href="<?= htmlspecialchars(kasir_url_for('logout.php')) ?>">
                    <i class="bi bi-box-arrow-right"></i><span>Logout</span>
                </a>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="content">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h3 class="fw-bold mb-1">Management Meja</h3>
                    <p class="text-muted mb-0">Kelola ketersediaan, kapasitas, dan status meja restoran.</p>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success border-0 rounded-4 shadow-sm mb-4"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger border-0 rounded-4 shadow-sm mb-4"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <div class="row g-4">
                <!-- Form Tambah Meja Baru -->
                <div class="col-lg-4">
                    <div class="card card-soft p-4">
                        <h5 class="fw-bold mb-3"><i class="bi bi-plus-circle me-2 text-primary"></i>Tambah Meja</h5>
                        <form method="post">
                            <input type="hidden" name="action" value="add">
                            
                            <div class="mb-3">
                                <label class="form-label font-weight-bold">Nomor / Nama Meja</label>
                                <input type="text" class="form-control rounded-3" name="nomor_meja" placeholder="Contoh: 01, T-02, VIP-1" required>
                            </div>

                            <div class="mb-4">
                                <label class="form-label">Kapasitas (Orang)</label>
                                <input type="number" class="form-control rounded-3" name="kapasitas" value="4" min="1" required>
                            </div>

                            <button type="submit" class="btn btn-primary w-100 rounded-3 py-2 fw-semibold">
                                <i class="bi bi-save me-1"></i> Simpan Meja
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Daftar Meja -->
                <div class="col-lg-8">
                    <div class="card card-soft p-4">
                        <h5 class="fw-bold mb-3"><i class="bi bi-grid-3x3-gap me-2 text-primary"></i>Daftar Meja</h5>
                        
                        <div class="table-responsive">
                            <table class="table align-middle border-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="border-0">Meja</th>
                                        <th class="border-0">Kapasitas</th>
                                        <th class="border-0">Status</th>
                                        <th class="border-0">Ubah Status</th>
                                        <th class="border-0 text-end">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($mejaList as $meja): ?>
                                        <tr>
                                            <td class="fw-bold fs-6">
                                                Meja <?= htmlspecialchars($meja['nomor_meja']) ?>
                                            </td>
                                            <td>
                                                <i class="bi bi-people me-1 text-muted"></i><?= (int)$meja['kapasitas'] ?> Kursi
                                            </td>
                                            <td>
                                                <span class="status-badge badge-<?= htmlspecialchars($meja['status']) ?>">
                                                    <?= ucfirst(htmlspecialchars($meja['status'])) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <form method="post" class="m-0">
                                                    <input type="hidden" name="action" value="update_status">
                                                    <input type="hidden" name="meja_id" value="<?= $meja['id'] ?>">
                                                    <select name="status" class="form-select form-select-sm rounded-3" onchange="this.form.submit()">
                                                        <option value="kosong" <?= $meja['status'] === 'kosong' ? 'selected' : '' ?>>Kosong</option>
                                                        <option value="terisi" <?= $meja['status'] === 'terisi' ? 'selected' : '' ?>>Terisi</option>
                                                        <option value="reservasi" <?= $meja['status'] === 'reservasi' ? 'selected' : '' ?>>Reservasi</option>
                                                    </select>
                                                </form>
                                            </td>
                                            <td class="text-end">
                                                <form method="post" class="d-inline" onsubmit="return confirm('Apakah Anda yakin ingin menghapus Meja <?= htmlspecialchars($meja['nomor_meja']) ?>?')">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="meja_id" value="<?= $meja['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger rounded-3">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>

                                    <?php if (empty($mejaList)): ?>
                                        <tr>
                                            <td colspan="5" class="text-center text-muted py-4">
                                                <i class="bi bi-inbox fs-1 d-block text-secondary mb-2"></i>
                                                Belum ada data meja yang ditambahkan.
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>