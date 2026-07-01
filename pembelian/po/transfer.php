<?php
session_start();
require_once '../../config.php';
require_once '../../includes/access_check.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'transfer') {
    $po_id = $_POST['po_id'];
    $gudang_id = $_POST['gudang_id'];
    $tanggal = $_POST['tanggal'];
    $jumlah = (int)$_POST['jumlah'];
    
    // Validate gudang exists
    $check_gudang = $conn->query("SELECT id FROM gudang_stok WHERE id = $gudang_id");
    if ($check_gudang->num_rows == 0) {
        $_SESSION['error'] = "Gudang tujuan tidak valid";
        header("Location: transfer.php?id=$po_id");
        exit();
    }
    // Validasi jumlah tidak boleh <= 0
    if ($jumlah <= 0) {
        $_SESSION['error'] = "Jumlah transfer harus lebih dari 0";
        header("Location: transfer.php?id=$po_id");
        exit();
    }
    
    // Mulai transaksi
    $conn->begin_transaction();
    try {
        // 1. Kurangi stok PO
        $sql = "UPDATE purchase_order SET total_item = total_item - ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Error preparing statement: " . $conn->error);
        }
        $stmt->bind_param('ii', $jumlah, $po_id);
        if (!$stmt->execute()) {
            throw new Exception("Error executing statement: " . $stmt->error);
        }
        
        // 2. Tambahkan ke tabel transfer
        $sql = "INSERT INTO transfer_gudang 
                (po_id, gudang_id, tanggal, jumlah, created_by) 
                VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Error preparing statement: " . $conn->error);
        }
        $stmt->bind_param('iisii', $po_id, $gudang_id, $tanggal, $jumlah, $_SESSION['user_id']);
        if (!$stmt->execute()) {
            throw new Exception("Error executing statement: " . $stmt->error);
        }
        
        $conn->commit();
        $_SESSION['success'] = "Transfer ke gudang berhasil!";
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = "Gagal transfer: " . $e->getMessage();
    }
    
    header("Location: index.php");
    exit();
}

// Ambil data PO
$po_id = $_GET['id'];
// First verify database connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Then modify the PO query to be more robust
// Modify the SQL query to use the correct column name
$sql = "SELECT po.id, po.no_po, po.total_item, po.status, 
               IFNULL(s.nama_supplier, 'Unknown') as nama_supplier
        FROM purchase_order po
        LEFT JOIN supplier s ON po.supplier_id = s.id
        WHERE po.id = ?";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("Error preparing statement: " . $conn->error);
}
$stmt->bind_param('i', $po_id);
if (!$stmt->execute()) {
    $_SESSION['error'] = "Error executing statement: " . $stmt->error;
    header("Location: index.php");
    exit();
}
$po = $stmt->get_result()->fetch_assoc();

if (!$po) {
    $_SESSION['error'] = "Data PO tidak ditemukan";
    header("Location: index.php");
    exit();
}

$gudang_list = [];
if (function_exists('get_accessible_gudang_list')) {
    foreach (get_accessible_gudang_list($conn) as $g) {
        $gudang_list[] = ['id' => $g['id'], 'nama_gudang' => $g['nama_gudang']];
    }
} else {
    $res = $conn->query("SELECT id, nama_gudang FROM gudang ORDER BY nama_gudang");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $gudang_list[] = $row;
        }
    }
}

// Ambil data transfer sebelumnya untuk PO ini - perbaikan kolom nama
// Coba beberapa kemungkinan nama kolom
$transfers = $conn->query("
    SELECT tg.*, g.id as gudang_id 
    FROM transfer_gudang tg
    JOIN gudang_stok g ON tg.gudang_id = g.id
    WHERE tg.po_id = $po_id
    ORDER BY tg.tanggal DESC
");

if (!$transfers) {
    die("Error mengambil data transfer: " . $conn->error);
}
// Remove the following unused code block:
// $check = $conn->query("SELECT id FROM gudang WHERE kode_gudang = '$kode_gudang'");
// if ($check->num_rows > 0) {
//     // Handle duplicate case
// }
?>

<!DOCTYPE html>
<html>
<head>
    <title>Transfer ke Gudang</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@latest/css/boxicons.min.css" rel="stylesheet">
</head>
<body>
    <?php include '../../templates/navbar.php'; ?>
    
    <div class="container mt-4">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0">Transfer ke Gudang</h4>
            </div>
            
            <div class="card-body">
                <div class="row mb-4">
                    <div class="col-md-6">
                        <h5>Detail PO</h5>
                        <p><strong>No PO:</strong> <?= htmlspecialchars($po['no_po']) ?></p>
                        <p><strong>Supplier:</strong> <?= htmlspecialchars($po['nama_supplier']) ?></p>
                        <p><strong>Total Item:</strong> <?= htmlspecialchars($po['total_item']) ?></p>
                    </div>
                    <div class="col-md-6">
                        <form action="transfer.php" method="post">
                            <input type="hidden" name="po_id" value="<?= $po_id ?>">
                            <div class="mb-3">
                                <label for="gudang_id" class="form-label">Gudang Tujuan</label>
                                <select name="gudang_id" id="gudang_id" class="form-select" required>
                                    <option value="">Pilih Gudang</option>
                                    <?php foreach($gudang_list as $row): ?>
                                        <option value="<?= $row['id'] ?>"><?= htmlspecialchars($row['nama_gudang']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="jumlah" class="form-label">Jumlah Dikirim</label>
                                <input type="number" class="form-control" id="jumlah" name="jumlah" required min="1" max="<?= $po['total_item'] ?>">
                            </div>
                            <div class="mb-3">
                                <label for="tanggal" class="form-label">Tanggal Transfer</label>
                                <input type="date" class="form-control" id="tanggal" name="tanggal" value="<?= date('Y-m-d') ?>" required>
                            </div>
                            <button type="submit" class="btn btn-primary" name="action" value="transfer">Kirim Stok</button>
                        </form>
                    </div>
                </div>
                <p><strong>Total Item:</strong> <?= number_format($po['total_item']) ?></p>
                <p><strong>Status:</strong> <span class="badge bg-success"><?= ucfirst($po['status']) ?></span></p>
            </div>

            <h5 class="mb-3">Riwayat Transfer</h5>
            <div class="table-responsive">
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Gudang Tujuan</th>
                            <th>Jumlah</th>
                            <th>Oleh</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($transfers->num_rows > 0): ?>
                            <?php while($transfer = $transfers->fetch_assoc()): ?>
                            <tr>
                                <td><?= date('d/m/Y', strtotime($transfer['tanggal'])) ?></td>
                                <td><?= htmlspecialchars($transfer['nama_gudang']) ?></td>
                                <td class="text-end"><?= number_format($transfer['jumlah']) ?></td>
                                <td><?= htmlspecialchars($transfer['created_by']) ?></td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="text-center">Belum ada data transfer</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
session_start();
require_once '../../config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'transfer') {
    $po_id = $_POST['po_id'];
    $gudang_id = $_POST['gudang_id'];
    $tanggal = $_POST['tanggal'];
    $jumlah = (int)$_POST['jumlah'];
    
    // Validate gudang exists
    $check_gudang = $conn->query("SELECT id FROM gudang_stok WHERE id = $gudang_id");
    if ($check_gudang->num_rows == 0) {
        $_SESSION['error'] = "Gudang tujuan tidak valid";
        header("Location: transfer.php?id=$po_id");
        exit();
    }
    // Validasi jumlah tidak boleh <= 0
    if ($jumlah <= 0) {
        $_SESSION['error'] = "Jumlah transfer harus lebih dari 0";
        header("Location: transfer.php?id=$po_id");
        exit();
    }
    
    // Mulai transaksi
    $conn->begin_transaction();
    try {
        // 1. Kurangi stok PO
        $sql = "UPDATE purchase_order SET total_item = total_item - ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Error preparing statement: " . $conn->error);
        }
        $stmt->bind_param('ii', $jumlah, $po_id);
        if (!$stmt->execute()) {
            throw new Exception("Error executing statement: " . $stmt->error);
        }
        
        // 2. Tambahkan ke tabel transfer
        $sql = "INSERT INTO transfer_gudang 
                (po_id, gudang_id, tanggal, jumlah, created_by) 
                VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Error preparing statement: " . $conn->error);
        }
        $stmt->bind_param('iisii', $po_id, $gudang_id, $tanggal, $jumlah, $_SESSION['user_id']);
        if (!$stmt->execute()) {
            throw new Exception("Error executing statement: " . $stmt->error);
        }
        
        // Before inserting into transfer_gudang, validate barang exists
        $check_barang = $conn->query("SELECT id FROM barang WHERE id = $barang_id");
        if ($check_barang->num_rows == 0) {
            $_SESSION['error'] = "Barang tidak ditemukan";
            header("Location: transfer.php?id=$po_id");
            exit();
        }
        $conn->commit();
        $_SESSION['success'] = "Transfer ke gudang berhasil!";
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = "Gagal transfer: " . $e->getMessage();
    }
    
    header("Location: index.php");
    exit();
}

// Ambil data PO
$po_id = $_GET['id'];
// First verify database connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Then modify the PO query to be more robust
// Modify the SQL query to use the correct column name
$sql = "SELECT po.id, po.no_po, po.total_item, po.status, 
               IFNULL(s.nama_supplier, 'Unknown') as nama_supplier
        FROM purchase_order po
        LEFT JOIN supplier s ON po.supplier_id = s.id
        WHERE po.id = ?";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("Error preparing statement: " . $conn->error);
}
$stmt->bind_param('i', $po_id);
if (!$stmt->execute()) {
    $_SESSION['error'] = "Error executing statement: " . $stmt->error;
    header("Location: index.php");
    exit();
}
$po = $stmt->get_result()->fetch_assoc();

if (!$po) {
    $_SESSION['error'] = "Data PO tidak ditemukan";
    header("Location: index.php");
    exit();
}

// Ambil daftar gudang dengan error handling - diubah ke gudang_stok
$gudang = $conn->query("SELECT id, nama_gudang FROM gudang_stok WHERE 1");
if (!$gudang) {
    die("Error mengambil data gudang_stok: " . $conn->error);
}

// Debug jumlah gudang
echo "<!-- Debug: Jumlah gudang_stok = " . $gudang->num_rows . " -->";

// Ambil data transfer sebelumnya untuk PO ini - perbaikan kolom nama
// Coba beberapa kemungkinan nama kolom
$transfers = $conn->query("
    SELECT tg.*, g.id as gudang_id 
    FROM transfer_gudang tg
    JOIN gudang_stok g ON tg.gudang_id = g.id
    WHERE tg.po_id = $po_id
    ORDER BY tg.tanggal DESC
");

if (!$transfers) {
    die("Error mengambil data transfer: " . $conn->error);
}
// Remove the following unused code block:
// $check = $conn->query("SELECT id FROM gudang WHERE kode_gudang = '$kode_gudang'");
// if ($check->num_rows > 0) {
//     // Handle duplicate case
// }
?>

<!DOCTYPE html>
<html>
<head>
    <title>Transfer ke Gudang</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@latest/css/boxicons.min.css" rel="stylesheet">
</head>
<body>
    <?php include '../../templates/navbar.php'; ?>
    
    <div class="container mt-4">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0">Transfer ke Gudang</h4>
            </div>
            
            <div class="card-body">
                <div class="row mb-4">
                    <div class="col-md-6">
                        <h5>Detail PO</h5>
                        <p><strong>No PO:</strong> <?= htmlspecialchars($po['no_po']) ?></p>
                        <p><strong>Supplier:</strong> <?= htmlspecialchars($po['nama_supplier']) ?></p>
                        <p><strong>Total Item:</strong> <?= htmlspecialchars($po['total_item']) ?></p>
                    </div>
                    <div class="col-md-6">
                        <form action="transfer.php" method="post">
                            <input type="hidden" name="po_id" value="<?= $po_id ?>">
                            <div class="mb-3">
                                <label for="gudang_id" class="form-label">Gudang Tujuan</label>
                                <select name="gudang_id" id="gudang_id" class="form-select" required>
                                    <option value="">Pilih Gudang</option>
                                    <?php foreach($gudang_list as $row): ?>
                                        <option value="<?= $row['id'] ?>"><?= htmlspecialchars($row['nama_gudang']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="jumlah" class="form-label">Jumlah Dikirim</label>
                                <input type="number" class="form-control" id="jumlah" name="jumlah" required min="1" max="<?= $po['total_item'] ?>">
                            </div>
                            <div class="mb-3">
                                <label for="tanggal" class="form-label">Tanggal Transfer</label>
                                <input type="date" class="form-control" id="tanggal" name="tanggal" value="<?= date('Y-m-d') ?>" required>
                            </div>
                            <button type="submit" class="btn btn-primary" name="action" value="transfer">Kirim Stok</button>
                        </form>
                    </div>
                </div>
                <p><strong>Total Item:</strong> <?= number_format($po['total_item']) ?></p>
                <p><strong>Status:</strong> <span class="badge bg-success"><?= ucfirst($po['status']) ?></span></p>
            </div>

            <h5 class="mb-3">Riwayat Transfer</h5>
            <div class="table-responsive">
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Gudang Tujuan</th>
                            <th>Jumlah</th>
                            <th>Oleh</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($transfers->num_rows > 0): ?>
                            <?php while($transfer = $transfers->fetch_assoc()): ?>
                            <tr>
                                <td><?= date('d/m/Y', strtotime($transfer['tanggal'])) ?></td>
                                <td><?= htmlspecialchars($transfer['nama_gudang']) ?></td>
                                <td class="text-end"><?= number_format($transfer['jumlah']) ?></td>
                                <td><?= htmlspecialchars($transfer['created_by']) ?></td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="text-center">Belum ada data transfer</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
