<?php
session_start();
require_once '../config.php';
require_once '../includes/access_check.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

if (!checkAccess('template_po', 'view')) {
    $_SESSION['error'] = 'Anda tidak memiliki akses ke halaman Setup Template PO!';
    header('Location: /minven_pro/dashboard.php');
    exit();
}

$conn->query("
    CREATE TABLE IF NOT EXISTS konversi_satuan_barang (
        id INT(11) NOT NULL AUTO_INCREMENT,
        barang_id INT(11) NOT NULL,
        satuan_asal_id INT(11) NOT NULL,
        satuan_tujuan_id INT(11) NOT NULL,
        nilai_konversi DECIMAL(12,4) NOT NULL DEFAULT 0,
        created_by INT(11) DEFAULT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uniq_konversi_barang (barang_id, satuan_asal_id, satuan_tujuan_id),
        KEY idx_konversi_barang_barang (barang_id),
        KEY idx_konversi_barang_satuan_asal (satuan_asal_id),
        KEY idx_konversi_barang_satuan_tujuan (satuan_tujuan_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
");

if (isset($_GET['get_template']) && $_GET['get_template'] == '1') {
    header('Content-Type: application/json; charset=utf-8');

    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit();
    }

    $supplier_id = isset($_GET['supplier_id']) ? (int)$_GET['supplier_id'] : 0;

    if ($supplier_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Supplier tidak valid']);
        exit();
    }

    $nama_template = isset($_GET['nama_template']) ? trim($_GET['nama_template']) : '';

    if (!empty($nama_template)) {
        $sql = "SELECT pt.*, b.kode_barang, b.nama_barang, b.harga_beli, COALESCE(sa.nama_satuan, s.nama_satuan) AS nama_satuan
                FROM po_template pt
                LEFT JOIN barang b ON pt.barang_id = b.id
                LEFT JOIN satuan s ON b.satuan_id = s.id
                LEFT JOIN (
                    SELECT k1.barang_id, k1.satuan_asal_id
                    FROM konversi_satuan_barang k1
                    INNER JOIN (
                        SELECT barang_id, MIN(id) AS min_id
                        FROM konversi_satuan_barang
                        GROUP BY barang_id
                    ) km ON km.min_id = k1.id
                ) kb ON kb.barang_id = b.id
                LEFT JOIN satuan sa ON kb.satuan_asal_id = sa.id
                WHERE pt.supplier_id = ? AND pt.nama_template = ? AND pt.is_active = 1
                ORDER BY b.kode_barang";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('is', $supplier_id, $nama_template);
    } else {
        $sql = "SELECT pt.*, b.kode_barang, b.nama_barang, b.harga_beli, COALESCE(sa.nama_satuan, s.nama_satuan) AS nama_satuan
                FROM po_template pt
                LEFT JOIN barang b ON pt.barang_id = b.id
                LEFT JOIN satuan s ON b.satuan_id = s.id
                LEFT JOIN (
                    SELECT k1.barang_id, k1.satuan_asal_id
                    FROM konversi_satuan_barang k1
                    INNER JOIN (
                        SELECT barang_id, MIN(id) AS min_id
                        FROM konversi_satuan_barang
                        GROUP BY barang_id
                    ) km ON km.min_id = k1.id
                ) kb ON kb.barang_id = b.id
                LEFT JOIN satuan sa ON kb.satuan_asal_id = sa.id
                WHERE pt.supplier_id = ? AND pt.is_active = 1
                ORDER BY pt.nama_template, b.kode_barang";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $supplier_id);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    $items = [];
    while ($row = $result->fetch_assoc()) {
        $items[] = [
            'nama_template' => $row['nama_template'],
            'id' => $row['barang_id'],
            'kode_barang' => $row['kode_barang'],
            'nama_barang' => $row['nama_barang'],
            'nama_satuan' => $row['nama_satuan'] ?? '',
            'jumlah' => $row['jumlah'],
            'harga_beli' => $row['harga_beli'],
            'keterangan' => $row['keterangan'] ?? ''
        ];
    }

    if (empty($items)) {
        echo json_encode(['success' => false, 'message' => 'Tidak ada template untuk supplier ini']);
        exit();
    }

    echo json_encode(['success' => true, 'data' => $items]);
    exit();
}

if (isset($_GET['get_template_names']) && $_GET['get_template_names'] == '1') {
    header('Content-Type: application/json; charset=utf-8');

    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit();
    }

    $supplier_id = isset($_GET['supplier_id']) ? (int)$_GET['supplier_id'] : 0;

    if ($supplier_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Supplier tidak valid']);
        exit();
    }

    $sql = "SELECT DISTINCT nama_template, COUNT(*) as item_count
            FROM po_template
            WHERE supplier_id = ? AND is_active = 1
            GROUP BY nama_template
            ORDER BY nama_template";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $supplier_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $templates = [];
    while ($row = $result->fetch_assoc()) {
        $templates[] = [
            'nama_template' => $row['nama_template'],
            'item_count' => $row['item_count']
        ];
    }

    echo json_encode(['success' => true, 'data' => $templates]);
    exit();
}

$checkTable = $conn->query("SHOW TABLES LIKE 'po_template'");
if ($checkTable->num_rows == 0) {
    $createTable = "CREATE TABLE IF NOT EXISTS po_template (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nama_template VARCHAR(255) NOT NULL,
        supplier_id INT NOT NULL,
        barang_id INT NOT NULL,
        jumlah DECIMAL(15,2) NOT NULL DEFAULT 1,
        keterangan TEXT,
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        created_by INT,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        updated_by INT,
        UNIQUE KEY unique_supplier_template_barang (supplier_id, nama_template, barang_id)
    )";
    $conn->query($createTable);
    
    $conn->query("ALTER TABLE po_template ADD COLUMN created_by INT AFTER is_active");
    $conn->query("ALTER TABLE po_template ADD COLUMN updated_by INT AFTER updated_at");
} else {
    $checkUnique = $conn->query("SHOW INDEX FROM po_template WHERE Key_name = 'unique_supplier_barang'");
    if ($checkUnique->num_rows > 0) {
        $conn->query("ALTER TABLE po_template DROP INDEX unique_supplier_barang");
    }
    $checkUnique2 = $conn->query("SHOW INDEX FROM po_template WHERE Key_name = 'unique_supplier_template_barang'");
    if ($checkUnique2->num_rows == 0) {
        $conn->query("ALTER TABLE po_template ADD UNIQUE KEY unique_supplier_template_barang (supplier_id, nama_template, barang_id)");
    }
}

$checkColumn = $conn->query("SHOW COLUMNS FROM po_template LIKE 'nama_template'");
if ($checkColumn->num_rows == 0) {
    $conn->query("ALTER TABLE po_template ADD COLUMN nama_template VARCHAR(255) NOT NULL AFTER id");
}

$checkColumn = $conn->query("SHOW COLUMNS FROM po_template LIKE 'is_active'");
if ($checkColumn->num_rows == 0) {
    $conn->query("ALTER TABLE po_template ADD COLUMN is_active TINYINT(1) DEFAULT 1 AFTER keterangan");
} else {
    $conn->query("UPDATE po_template SET is_active = 1 WHERE is_active IS NULL OR is_active = 0");
}

$supplier_list = $conn->query("SELECT id, nama_supplier FROM supplier ORDER BY nama_supplier");
$all_suppliers = [];
while ($s = $supplier_list->fetch_assoc()) {
    $all_suppliers[] = $s;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_template'])) {
        $nama_template = trim($_POST['nama_template']) ?? 'Template PO';
        $supplier_id = (int)$_POST['supplier_id'];
        $barang_ids = $_POST['barang_id'] ?? [];
        $jumlahs = $_POST['jumlah'] ?? [];
        $keterangans = $_POST['keterangan_detail'] ?? [];

        if (empty($supplier_id)) {
            $_SESSION['error'] = "Supplier harus dipilih!";
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        }

        if (empty($nama_template)) {
            $_SESSION['error'] = "Nama template harus diisi!";
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        }

        if (!is_array($barang_ids) || count($barang_ids) === 0) {
            $_SESSION['error'] = "Minimal harus ada 1 barang dalam template!";
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        }

        try {
            $conn->begin_transaction();

            $insertStmt = $conn->prepare("INSERT INTO po_template (nama_template, supplier_id, barang_id, jumlah, keterangan, created_by) VALUES (?, ?, ?, ?, ?, ?)");

            foreach ($barang_ids as $index => $barang_id) {
                if (empty($barang_id)) continue;

                $jumlah = isset($jumlahs[$index]) ? (float)$jumlahs[$index] : 1;
                $keterangan = isset($keterangans[$index]) ? trim($keterangans[$index]) : '';

                $insertStmt->bind_param("siidsi", $nama_template, $supplier_id, $barang_id, $jumlah, $keterangan, $_SESSION['user_id']);
                $insertStmt->execute();
            }

            $conn->commit();
            $_SESSION['success'] = "Template PO '$nama_template' berhasil disimpan!";
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['error'] = "Gagal menyimpan template: " . $e->getMessage();
        }
        
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
    
    if (isset($_POST['delete_template'])) {
        $supplier_id = (int)$_POST['supplier_id'];
        $nama_template = $_POST['nama_template'] ?? '';
        
        try {
            if (!empty($nama_template)) {
                $stmt = $conn->prepare("DELETE FROM po_template WHERE supplier_id = ? AND nama_template = ?");
                $stmt->bind_param('is', $supplier_id, $nama_template);
                $stmt->execute();
                $stmt->close();
                $_SESSION['success'] = "Template '$nama_template' berhasil dihapus!";
            } else {
                $conn->query("DELETE FROM po_template WHERE supplier_id = $supplier_id");
                $_SESSION['success'] = "Semua template untuk supplier ini berhasil dihapus!";
            }
        } catch (Exception $e) {
            $_SESSION['error'] = "Gagal menghapus template: " . $e->getMessage();
        }
        
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
}

$templates_by_supplier = [];
$sql = "SELECT pt.*, b.kode_barang, b.nama_barang, COALESCE(sa.nama_satuan, s.nama_satuan) AS nama_satuan, sup.nama_supplier
        FROM po_template pt
        LEFT JOIN barang b ON pt.barang_id = b.id
        LEFT JOIN satuan s ON b.satuan_id = s.id
        LEFT JOIN (
            SELECT k1.barang_id, k1.satuan_asal_id
            FROM konversi_satuan_barang k1
            INNER JOIN (
                SELECT barang_id, MIN(id) AS min_id
                FROM konversi_satuan_barang
                GROUP BY barang_id
            ) km ON km.min_id = k1.id
        ) kb ON kb.barang_id = b.id
        LEFT JOIN satuan sa ON kb.satuan_asal_id = sa.id
        LEFT JOIN supplier sup ON pt.supplier_id = sup.id
        ORDER BY sup.nama_supplier, pt.nama_template, b.kode_barang";
$result = $conn->query($sql);
while ($row = $result->fetch_assoc()) {
    $key = $row['supplier_id'] . '|' . $row['nama_template'];
    if (!isset($templates_by_supplier[$key])) {
        $templates_by_supplier[$key] = [
            'supplier_id' => $row['supplier_id'],
            'nama_supplier' => $row['nama_supplier'],
            'nama_template' => $row['nama_template'],
            'items' => []
        ];
    }
    $templates_by_supplier[$key]['items'][] = $row;
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup Template PO - MINVEN PRO</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <style>
        body {
            background: #ffffff;
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .card {
            border-radius: 0.75rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            background: #ffffff;
            border: 1px solid #e2e8f0;
        }
        
        .card-header {
            background: #0008f9;
            color: white;
            border-radius: 0.75rem 0.75rem 0 0 !important;
            padding: 15px 20px;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #0008f9;
            box-shadow: 0 0 0 0.2rem rgba(0, 8, 249, 0.25);
        }
        
        .btn-primary {
            background: #0008f9;
            border: none;
            padding: 0.6rem 1.5rem;
            border-radius: 8px;
        }
        
        .btn-primary:hover {
            background: #0006d4;
        }
        
        .btn-success {
            background: #28a745;
            border: none;
        }
        
        .btn-success:hover {
            background: #218838;
        }
        
        .btn-danger {
            background: #dc3545;
            border: none;
        }
        
        .btn-danger:hover {
            background: #bb2d3b;
        }
        
        .info-box {
            background: #f8f9fa;
            border-left: 4px solid #0008f9;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
        }
        
        .table thead th {
            background: #0008f9;
            color: white;
            border: none;
            padding: 1rem;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.875rem;
            letter-spacing: 0.05em;
        }
        
        .template-card {
            border: 1px solid #e2e8f0;
            border-radius: 0.5rem;
            padding: 1rem;
            margin-bottom: 1rem;
            background: #f8f9fa;
        }
        
        .template-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }
        
        .item-row {
            background: white;
            padding: 0.5rem;
            border-radius: 0.25rem;
            margin-bottom: 0.5rem;
        }
        
        .template-item {
            background: #fff;
        }
        
        .template-header.cursor-pointer {
            cursor: pointer;
        }
        
        .template-header.cursor-pointer:hover {
            background: #e9ecef !important;
        }
    </style>
</head>
<body>
    <?php include '../templates/navbar.php'; ?>

    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <div class="card shadow">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="bi bi-file-earmark-text me-2"></i>
                            Setup Template PO
                        </h5>
                        <a href="index.php" class="btn btn-sm btn-light">
                            <i class="bi bi-arrow-left me-1"></i> Kembali
                        </a>
                    </div>
                    <div class="card-body">
                        <?php if (isset($_SESSION['success'])): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="bi bi-check-circle me-1"></i> <?= $_SESSION['success']; unset($_SESSION['success']); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (isset($_SESSION['error'])): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="bi bi-exclamation-circle me-1"></i> <?= $_SESSION['error']; unset($_SESSION['error']); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <div class="info-box">
                            <small class="text-muted">
                                <i class="bi bi-info-circle me-1"></i>
                                Template PO digunakan untuk menyimpan daftar barang yang sering dipesan ulang setiap bulan.
                                Pilih supplier, tambahkan barang beserta jumlah default, lalu simpan template.
                                Template dapat digunakan saat membuat PO baru.
                            </small>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-5">
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="mb-0"><i class="bi bi-plus-circle me-2"></i>Buat / Update Template</h6>
                                    </div>
                                    <div class="card-body">
                                        <form method="POST" action="">
                                            <div class="mb-3">
                                                <label for="supplier_id" class="form-label">Supplier</label>
                                                <select class="form-select" id="supplier_id" name="supplier_id" required>
                                                    <option value="">Pilih Supplier</option>
                                                    <?php foreach ($all_suppliers as $sup): ?>
                                                        <option value="<?= $sup['id'] ?>"><?= htmlspecialchars($sup['nama_supplier']) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="nama_template" class="form-label">Nama Template</label>
                                                <input type="text" class="form-control" id="nama_template" name="nama_template" value="Template PO Bulanan" required>
                                            </div>
                                            
                                            <hr>
                                            <h6>Item Barang</h6>
                                            <div id="itemContainer">
                                                <div class="item-row">
                                                    <div class="row">
                                                        <div class="col-md-7">
                                                            <label class="form-label small">Barang</label>
                                                            <select class="form-select form-select-sm item-barang" name="barang_id[]">
                                                                <option value="">Pilih Supplier terlebih dahulu</option>
                                                            </select>
                                                        </div>
                                                        <div class="col-md-3">
                                                            <label class="form-label small">Jumlah</label>
                                                            <input type="number" class="form-control form-control-sm item-jumlah" name="jumlah[]" value="1" min="1">
                                                        </div>
                                                        <div class="col-md-2 d-flex align-items-end">
                                                            <button type="button" class="btn btn-danger btn-sm remove-item-row">
                                                                <i class="bi bi-trash"></i>
                                                            </button>
                                                        </div>
                                                    </div>
                                                    <div class="row mt-2">
                                                        <div class="col-md-12">
                                                            <label class="form-label small">Keterangan (Opsional)</label>
                                                            <input type="text" class="form-control form-control-sm item-keterangan" name="keterangan_detail[]" placeholder="Keterangan item">
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <button type="button" class="btn btn-secondary btn-sm mt-2" id="addItemRow">
                                                <i class="bi bi-plus me-1"></i>Tambah Item
                                            </button>
                                            
                                            <hr class="mt-3">
                                            <div class="d-grid gap-2">
                                                <button type="submit" name="save_template" class="btn btn-primary">
                                                    <i class="bi bi-save me-1"></i>Simpan Template
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-7">
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="mb-0"><i class="bi bi-list-check me-2"></i>Template Tersimpan</h6>
                                    </div>
                                    <div class="card-body">
                                        <?php if (empty($templates_by_supplier)): ?>
                                            <div class="text-center text-muted py-4">
                                                <i class="bi bi-inbox" style="font-size: 3rem;"></i>
                                                <p class="mt-2">Belum ada template tersimpan</p>
                                            </div>
                                        <?php else: ?>
                                            <?php
                                            $grouped_templates = [];
                                            foreach ($templates_by_supplier as $key => $template) {
                                                $sup_id = $template['supplier_id'];
                                                if (!isset($grouped_templates[$sup_id])) {
                                                    $grouped_templates[$sup_id] = [
                                                        'nama_supplier' => $template['nama_supplier'],
                                                        'templates' => []
                                                    ];
                                                }
                                                $grouped_templates[$sup_id]['templates'][] = $template;
                                            }
                                            ?>
                                            <?php foreach ($grouped_templates as $sup_id => $group): ?>
                                                <div class="supplier-template-group mb-3">
                                                    <div class="fw-bold text-primary mb-2">
                                                        <i class="bi bi-building me-1"></i><?= htmlspecialchars($group['nama_supplier']) ?>
                                                    </div>
                                                    <?php foreach ($group['templates'] as $template): ?>
                                                        <div class="template-item border rounded mb-2">
                                                            <div class="template-header p-2 bg-light d-flex justify-content-between align-items-center cursor-pointer" onclick="toggleTemplateDetail(this)">
                                                                <div>
                                                                    <i class="bi bi-caret-right me-2 toggle-icon"></i>
                                                                    <span class="fw-medium"><?= htmlspecialchars($template['nama_template']) ?></span>
                                                                    <span class="badge bg-secondary ms-2"><?= count($template['items']) ?> item</span>
                                                                </div>
                                                                <div class="template-actions">
                                                                    <button type="button" class="btn btn-info btn-sm me-1" onclick="event.stopPropagation(); loadTemplateToForm(<?= $template['supplier_id'] ?>, '<?= htmlspecialchars(addslashes($template['nama_template'])) ?>')" title="Pilih">
                                                                        <i class="bi bi-check-circle"></i> Pilih
                                                                    </button>
                                                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Hapus template <?= htmlspecialchars($template['nama_template']) ?>?')">
                                                                        <input type="hidden" name="supplier_id" value="<?= $template['supplier_id'] ?>">
                                                                        <input type="hidden" name="nama_template" value="<?= htmlspecialchars($template['nama_template']) ?>">
                                                                        <button type="submit" name="delete_template" class="btn btn-danger btn-sm" title="Hapus">
                                                                            <i class="bi bi-trash"></i>
                                                                        </button>
                                                                    </form>
                                                                </div>
                                                            </div>
                                                            <div class="template-detail" style="display: none;">
                                                                <table class="table table-sm table-striped mb-0">
                                                                    <thead>
                                                                        <tr>
                                                                            <th>Kode</th>
                                                                            <th>Nama Barang</th>
                                                                            <th>Jumlah</th>
                                                                            <th>Keterangan</th>
                                                                        </tr>
                                                                    </thead>
                                                                    <tbody>
                                                                        <?php foreach ($template['items'] as $item): ?>
                                                                            <tr>
                                                                                <td><?= htmlspecialchars($item['kode_barang']) ?></td>
                                                                                <td><?= htmlspecialchars($item['nama_barang']) ?></td>
                                                                                <td><?= number_format($item['jumlah']) ?> <?= htmlspecialchars($item['nama_satuan']) ?></td>
                                                                                <td><?= htmlspecialchars($item['keterangan'] ?? '-') ?></td>
                                                                            </tr>
                                                                        <?php endforeach; ?>
                                                                    </tbody>
                                                                </table>
                                                            </div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(document).ready(function() {
            let selectedSupplierId = null;
            
            $('.item-barang').select2({
                width: '100%',
                placeholder: 'Pilih Barang'
            });
            
            $('#supplier_id').select2({
                width: '100%',
                placeholder: 'Pilih Supplier'
            });
            
            function loadBarangOptions(supplierId, $select) {
                if (supplierId) {
                    $.ajax({
                        url: '../ajax/get_barang_by_supplier.php',
                        method: 'GET',
                        data: { supplier_id: supplierId },
                        dataType: 'json',
                        success: function(data) {
                            $select.empty();
                            $select.append('<option value="">Pilih Barang</option>');
                            
                            if (data && data.length > 0) {
                                data.forEach(function(barang) {
                                    $select.append(
                                        `<option value="${barang.id}" data-harga="${barang.harga_beli}" data-satuan="${barang.nama_satuan || ''}">
                                            ${barang.kode_barang} - ${barang.nama_barang}
                                        </option>`
                                    );
                                });
                            } else {
                                $select.append('<option value="">Tidak ada barang untuk supplier ini</option>');
                            }
                        },
                        error: function() {
                            $select.empty();
                            $select.append('<option value="">Gagal memuat barang</option>');
                        }
                    });
                } else {
                    $select.empty();
                    $select.append('<option value="">Pilih Supplier terlebih dahulu</option>');
                }
            }
            
            $('#supplier_id').change(function() {
                selectedSupplierId = $(this).val();
                
                $('#itemContainer').find('.item-barang').each(function() {
                    loadBarangOptions(selectedSupplierId, $(this));
                });
            });
            
            $('#addItemRow').click(function() {
                const newRow = `
                    <div class="item-row">
                        <div class="row">
                            <div class="col-md-7">
                                <label class="form-label small">Barang</label>
                                <select class="form-select form-select-sm item-barang" name="barang_id[]">
                                    <option value="">Pilih Supplier terlebih dahulu</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small">Jumlah</label>
                                <input type="number" class="form-control form-control-sm item-jumlah" name="jumlah[]" value="1" min="1">
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="button" class="btn btn-danger btn-sm remove-item-row">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </div>
                        <div class="row mt-2">
                            <div class="col-md-12">
                                <label class="form-label small">Keterangan (Opsional)</label>
                                <input type="text" class="form-control form-control-sm item-keterangan" name="keterangan_detail[]" placeholder="Keterangan item">
                            </div>
                        </div>
                    </div>
                `;
                $('#itemContainer').append(newRow);
                const $newSelect = $('#itemContainer .item-barang').last();
                $newSelect.select2({
                    width: '100%',
                    placeholder: 'Pilih Barang'
                });
                
                if (selectedSupplierId) {
                    loadBarangOptions(selectedSupplierId, $newSelect);
                }
            });
            
            $(document).on('click', '.remove-item-row', function() {
                if ($('.item-row').length > 1) {
                    $(this).closest('.item-row').remove();
                } else {
                    alert('Minimal harus ada 1 item!');
                }
            });
        });
        
        function toggleTemplateDetail(element) {
            const $header = $(element);
            const $detail = $header.siblings('.template-detail');
            const $icon = $header.find('.toggle-icon');
            
            if ($detail.is(':hidden')) {
                $detail.slideDown();
                $icon.removeClass('bi-caret-right').addClass('bi-caret-down');
            } else {
                $detail.slideUp();
                $icon.removeClass('bi-caret-down').addClass('bi-caret-right');
            }
        }
        
        function loadTemplateToForm(supplierId, templateName) {
            $('#supplier_id').val(supplierId).trigger('change.select2');
            $('#nama_template').val(templateName);
            
            $.ajax({
                url: 'po_template_setup.php?get_template=1',
                method: 'GET',
                data: { supplier_id: supplierId, nama_template: templateName },
                dataType: 'json',
                success: function(response) {
                    if (response.success && response.data && response.data.length > 0) {
                        $('#itemContainer').empty();
                        
                        response.data.forEach(function(item, index) {
                            const $newRow = $(`
                                <div class="item-row">
                                    <div class="row">
                                        <div class="col-md-7">
                                            <label class="form-label small">Barang</label>
                                            <select class="form-select form-select-sm item-barang" name="barang_id[]">
                                                <option value="">Pilih Barang</option>
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label small">Jumlah</label>
                                            <input type="number" class="form-control form-control-sm item-jumlah" name="jumlah[]" value="1" min="1">
                                        </div>
                                        <div class="col-md-2 d-flex align-items-end">
                                            <button type="button" class="btn btn-danger btn-sm remove-item-row">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="row mt-2">
                                        <div class="col-md-12">
                                            <label class="form-label small">Keterangan (Opsional)</label>
                                            <input type="text" class="form-control form-control-sm item-keterangan" name="keterangan_detail[]" placeholder="Keterangan item">
                                        </div>
                                    </div>
                                </div>
                            `);
                            
                            $('#itemContainer').append($newRow);
                            
                            const $select = $newRow.find('.item-barang');
                            $select.select2({
                                width: '100%',
                                placeholder: 'Pilih Barang'
                            });
                            
                            loadBarangOptionsForTemplate(supplierId, $select, item);
                        });
                        
                        $('html, body').animate({
                            scrollTop: $('#supplier_id').offset().top - 100
                        }, 500);
                    }
                },
                error: function() {
                    alert('Gagal memuat template');
                }
            });
        }
        
        function loadBarangOptionsForTemplate(supplierId, $select, selectedItem) {
            if (supplierId) {
                $.ajax({
                    url: '../ajax/get_barang_by_supplier.php',
                    method: 'GET',
                    data: { supplier_id: supplierId },
                    dataType: 'json',
                    success: function(data) {
                        $select.empty();
                        $select.append('<option value="">Pilih Barang</option>');
                        
                        if (data && data.length > 0) {
                            data.forEach(function(barang) {
                                const isSelected = barang.id == selectedItem.id;
                                $select.append(
                                    `<option value="${barang.id}" data-harga="${barang.harga_beli}" data-satuan="${barang.nama_satuan || ''}" ${isSelected ? 'selected' : ''}>
                                        ${barang.kode_barang} - ${barang.nama_barang}
                                    </option>`
                                );
                            });
                        }
                    }
                });
            }
        }
    </script>
</body>
</html>
