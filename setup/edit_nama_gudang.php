<?php
session_start();
require_once '../config.php';
require_once '../includes/access_check.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

if (!checkAccess('edit_nama_gudang', 'view')) {
    $_SESSION['error'] = 'Anda tidak memiliki akses ke halaman Edit Nama Gudang!';
    header('Location: /minven_pro/dashboard.php');
    exit();
}

$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_nama_gudang'])) {
    if (!checkAccess('edit_nama_gudang', 'edit')) {
        $_SESSION['error'] = 'Anda tidak memiliki akses untuk mengubah nama gudang!';
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }

    $gudang_id = isset($_POST['gudang_id']) ? (int)$_POST['gudang_id'] : 0;
    $nama_baru = isset($_POST['nama_gudang']) ? trim((string)$_POST['nama_gudang']) : '';

    if ($gudang_id <= 0) {
        $error = 'Gudang tidak valid.';
    } elseif ($nama_baru === '') {
        $error = 'Nama gudang tidak boleh kosong.';
    } else {
        $stmt = $conn->prepare("SELECT nama_gudang FROM gudang WHERE id = ?");
        $stmt->bind_param('i', $gudang_id);
        $stmt->execute();
        $current = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$current) {
            $error = 'Gudang tidak ditemukan.';
        } elseif (trim((string)$current['nama_gudang']) === $nama_baru) {
            $success = 'Nama gudang tidak berubah.';
        } else {
            $stmt = $conn->prepare("UPDATE gudang SET nama_gudang = ? WHERE id = ?");
            $stmt->bind_param('si', $nama_baru, $gudang_id);
            $ok = $stmt->execute();
            $stmt->close();

            if ($ok) {
                $_SESSION['success'] = 'Nama gudang berhasil diubah.';
                header("Location: " . $_SERVER['PHP_SELF']);
                exit();
            }

            $error = 'Gagal mengubah nama gudang.';
        }
    }
}

$gudang_list = [];
$result = $conn->query("SELECT id, kode_gudang, nama_gudang FROM gudang ORDER BY nama_gudang");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $gudang_list[] = $row;
    }
}

if (isset($_SESSION['error'])) {
    $error = (string)$_SESSION['error'];
    unset($_SESSION['error']);
}
if (isset($_SESSION['success'])) {
    $success = (string)$_SESSION['success'];
    unset($_SESSION['success']);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Nama Gudang - MINVEN</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
</head>
<body>
    <?php include '../templates/navbar.php'; ?>

    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-lg-7">
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class='bx bx-edit me-2'></i>Edit Nama Gudang</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger mb-3"><?= htmlspecialchars($error) ?></div>
                        <?php endif; ?>
                        <?php if ($success): ?>
                            <div class="alert alert-success mb-3"><?= htmlspecialchars($success) ?></div>
                        <?php endif; ?>

                        <form method="post" action="">
                            <div class="mb-3">
                                <label for="gudang_id" class="form-label">Pilih Gudang</label>
                                <select class="form-select" id="gudang_id" name="gudang_id" required>
                                    <option value="">-- Pilih Gudang --</option>
                                    <?php foreach ($gudang_list as $g): ?>
                                        <?php
                                            $kode = (string)($g['kode_gudang'] ?? '');
                                            $nama = (string)($g['nama_gudang'] ?? '');
                                            $label = $kode !== '' ? ($kode . ' - ' . $nama) : $nama;
                                        ?>
                                        <option value="<?= (int)$g['id'] ?>"><?= htmlspecialchars($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="nama_gudang" class="form-label">Nama Gudang Baru</label>
                                <input type="text" class="form-control" id="nama_gudang" name="nama_gudang" maxlength="150" required>
                                <small class="text-muted">Hanya mengubah nama, kode gudang tidak berubah.</small>
                            </div>

                            <div class="d-flex gap-2">
                                <button type="submit" name="update_nama_gudang" class="btn btn-primary">
                                    <i class='bx bx-save me-2'></i>Simpan
                                </button>
                                <a href="/minven_pro/dashboard.php" class="btn btn-secondary">
                                    <i class='bx bx-arrow-back me-2'></i>Kembali
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

