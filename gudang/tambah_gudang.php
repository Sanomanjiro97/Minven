<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/access_check.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: " . url_for('index.php'));
    exit();
}

if (!checkAccess('tambah_gudang', 'add')) {
    $_SESSION['error'] = 'Anda tidak memiliki akses untuk menambah gudang!';
    header('Location: ' . url_for('dashboard.php'));
    exit();
}

$error = '';
$success = '';

function sanitize_kode($kode) {
    $kode = trim($kode);
    $kode = preg_replace('/\s+/', '', $kode);
    $kode = preg_replace('/[^A-Za-z0-9_]/', '', $kode);
    return $kode;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $kode_gudang = isset($_POST['kode_gudang']) ? sanitize_kode($_POST['kode_gudang']) : '';
    $nama_gudang = isset($_POST['nama_gudang']) ? trim($_POST['nama_gudang']) : '';
    $alamat = isset($_POST['alamat']) ? trim($_POST['alamat']) : null;
    $kapasitas = isset($_POST['kapasitas']) && $_POST['kapasitas'] !== '' ? (int)$_POST['kapasitas'] : null;
    $status = isset($_POST['status']) ? trim($_POST['status']) : 'aktif';

    if ($kode_gudang === '' || $nama_gudang === '') {
        $error = 'Kode gudang dan nama gudang wajib diisi.';
    } else {
        $stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM gudang WHERE kode_gudang = ?");
        $stmt->bind_param('s', $kode_gudang);
        $stmt->execute();
        $cnt = $stmt->get_result()->fetch_assoc()['cnt'] ?? 0;
        $stmt->close();

        if ($cnt > 0) {
            $error = 'Kode gudang sudah digunakan.';
        } else {
            $stmt = $conn->prepare("INSERT INTO gudang (kode_gudang, nama_gudang, alamat, kapasitas, status) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param('sssis', $kode_gudang, $nama_gudang, $alamat, $kapasitas, $status);
            $ok = $stmt->execute();
            $new_id = $ok ? $stmt->insert_id : 0;
            $stmt->close();

            if ($ok && $new_id > 0) {
                $src_operasional = __DIR__ . '/gudang_antapani.php';
                $src_process = __DIR__ . '/process_antapani.php';
                $dst_operasional = __DIR__ . '/operasional_stok_gudang' . $kode_gudang . '.php';
                $dst_process = __DIR__ . '/process_operasional_stok_gudang' . $kode_gudang . '.php';

                $base_ok = true;
                if (!file_exists($src_operasional) || !file_exists($src_process)) {
                    $base_ok = false;
                }

                if ($base_ok) {
                    $op = file_get_contents($src_operasional);
                    $pr = file_get_contents($src_process);

                    $op = preg_replace("/checkAccess\\('gudang_antapani'\\s*,\\s*'view'\\)/", "checkAccess('gudang_" . $kode_gudang . "', 'view')", $op);
                    $op = preg_replace("/checkAccess\\('gudang_antapani'\\s*,\\s*'add'\\)/", "checkAccess('gudang_" . $kode_gudang . "', 'add')", $op);
                    $op = preg_replace("/checkAccess\\('gudang_antapani'\\s*,\\s*'edit'\\)/", "checkAccess('gudang_" . $kode_gudang . "', 'edit')", $op);
                    $op = preg_replace("/checkAccess\\('gudang_antapani'\\s*,\\s*'delete'\\)/", "checkAccess('gudang_" . $kode_gudang . "', 'delete')", $op);
                    $op = str_replace("'gudang_antapani'", "'gudang_" . $kode_gudang . "'", $op);
                    $op = str_replace('Gudang Antapani', $nama_gudang, $op);
                    $op = preg_replace('/gudang_id\s*=\s*13\b/', 'gudang_id = ' . $new_id, $op);
                    $op = preg_replace('/value="13"/', 'value="' . $new_id . '"', $op);
                    $op = preg_replace('/\?gudang_id=13\b/', '?gudang_id=' . $new_id, $op);
                    $op = str_replace('process_antapani.php', 'process_operasional_stok_gudang' . $kode_gudang . '.php', $op);

                    $pr = preg_replace('/\$gudang_id\s*=\s*13\b;/', '$gudang_id = ' . $new_id . ';', $pr);
                    $pr = str_replace('Gudang Antapani', $nama_gudang, $pr);
                    $pr = str_replace("'gudang_antapani'", "'gudang_" . $kode_gudang . "'", $pr);

                    $write1 = @file_put_contents($dst_operasional, $op);
                    $write2 = @file_put_contents($dst_process, $pr);

                    if ($write1 !== false && $write2 !== false) {
                        $_SESSION['success'] = 'Gudang berhasil ditambahkan dan halaman operasional berhasil dibuat.';
                        header('Location: ' . url_for('gudang/operasional_stok_gudang' . $kode_gudang . '.php'));
                        exit();
                    } else {
                        $error = 'Gagal membuat file operasional gudang.';
                    }
                } else {
                    $error = 'File referensi tidak ditemukan.';
                }
            } else {
                $error = 'Gagal menambahkan gudang.';
            }
        }
    }
}
$page_title = 'Tambah Gudang';
require_once __DIR__ . '/../templates/header.php';
require_once __DIR__ . '/../templates/navbar.php';
?>
<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    Tambah Gudang
                </div>
                <div class="card-body">
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>
                    <form method="post">
                        <div class="mb-3">
                            <label class="form-label">Kode Gudang</label>
                            <input type="text" name="kode_gudang" class="form-control" required maxlength="20">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Nama Gudang</label>
                            <input type="text" name="nama_gudang" class="form-control" required maxlength="100">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Alamat</label>
                            <textarea name="alamat" class="form-control" rows="2"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Kapasitas</label>
                            <input type="number" name="kapasitas" class="form-control" min="0">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="aktif">Aktif</option>
                                <option value="nonaktif">Nonaktif</option>
                            </select>
                        </div>
                        <div class="d-flex justify-content-end gap-2">
                            <a href="<?= url_for('gudang/master_gudang.php') ?>" class="btn btn-secondary">Batal</a>
                            <button type="submit" class="btn btn-primary">Simpan</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <div class="text-muted small mt-3">
        File akan dibuat: gudang/operasional_stok_gudang{Kode}.php dan gudang/process_operasional_stok_gudang{Kode}.php
    </div>
    <?php require_once __DIR__ . '/../templates/footer.php'; ?>
</div>
