<?php
session_start();
require_once '../../config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$barang_id = (int)($_GET['barang_id'] ?? 0);
$satuan_asal_id = (int)($_GET['satuan_asal_id'] ?? 0);
$satuan_tujuan_id = (int)($_GET['satuan_tujuan_id'] ?? 0);

if ($barang_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Parameter tidak lengkap']);
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

if ($satuan_asal_id > 0 && $satuan_tujuan_id > 0) {
    if ($satuan_asal_id === $satuan_tujuan_id) {
        echo json_encode(['success' => true, 'nilai_konversi' => 1]);
        exit();
    }

    $sql = "SELECT nilai_konversi
            FROM konversi_satuan_barang
            WHERE barang_id = ? AND satuan_asal_id = ? AND satuan_tujuan_id = ?
            LIMIT 1";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Gagal query konversi']);
        exit();
    }

    $stmt->bind_param('iii', $barang_id, $satuan_asal_id, $satuan_tujuan_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if (!$row) {
        echo json_encode(['success' => false, 'message' => 'Master konversi belum ada']);
        exit();
    }

    echo json_encode(['success' => true, 'nilai_konversi' => (float)$row['nilai_konversi']]);
    exit();
}

$sqlList = "
    SELECT kb.satuan_asal_id,
           s1.nama_satuan AS satuan_asal_nama,
           kb.satuan_tujuan_id,
           s2.nama_satuan AS satuan_tujuan_nama,
           kb.nilai_konversi
    FROM konversi_satuan_barang kb
    INNER JOIN satuan s1 ON kb.satuan_asal_id = s1.id
    INNER JOIN satuan s2 ON kb.satuan_tujuan_id = s2.id
    WHERE kb.barang_id = ?
    ORDER BY kb.updated_at DESC, kb.id DESC
";
$stmtList = $conn->prepare($sqlList);
if (!$stmtList) {
    echo json_encode(['success' => false, 'message' => 'Gagal query daftar konversi']);
    exit();
}
$stmtList->bind_param('i', $barang_id);
$stmtList->execute();
$resultList = $stmtList->get_result();

$konversi = [];
while ($row = $resultList->fetch_assoc()) {
    $konversi[] = [
        'satuan_asal_id' => (int)$row['satuan_asal_id'],
        'satuan_asal_nama' => $row['satuan_asal_nama'] ?? '',
        'satuan_tujuan_id' => (int)$row['satuan_tujuan_id'],
        'satuan_tujuan_nama' => $row['satuan_tujuan_nama'] ?? '',
        'nilai_konversi' => (float)$row['nilai_konversi']
    ];
}
$stmtList->close();

echo json_encode(['success' => true, 'konversi' => $konversi]);
exit();
