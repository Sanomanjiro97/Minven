<?php
/**
 * AJAX endpoint untuk mendapatkan daftar konversi satuan berdasarkan satuan asal
 * 
 * Usage:
 * GET /pembelian/po/get_konversi_by_satuan.php?satuan_id=1
 * 
 * Response:
 * {
 *   "success": true,
 *   "data": [
 *     {
 *       "id": 1,
 *       "satuan_asal_id": 1,
 *       "satuan_tujuan_id": 2,
 *       "nilai_konversi": 1000,
 *       "satuan_asal": "kg",
 *       "satuan_tujuan": "gram"
 *     }
 *   ]
 * }
 */

header('Content-Type: application/json; charset=utf-8');

require_once '../../config.php';

$satuan_id = isset($_GET['satuan_id']) ? (int)$_GET['satuan_id'] : 0;

if ($satuan_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid satuan_id']);
    exit;
}

try {
    $sql = "SELECT 
                ks.id,
                ks.satuan_asal_id,
                ks.satuan_tujuan_id,
                ks.nilai_konversi,
                s1.nama_satuan as satuan_asal,
                s2.nama_satuan as satuan_tujuan
            FROM konversi_satuan ks
            JOIN satuan s1 ON ks.satuan_asal_id = s1.id
            JOIN satuan s2 ON ks.satuan_tujuan_id = s2.id
            WHERE ks.satuan_asal_id = ?
            ORDER BY s2.nama_satuan";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $satuan_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = [
            'id' => (int)$row['id'],
            'satuan_asal_id' => (int)$row['satuan_asal_id'],
            'satuan_tujuan_id' => (int)$row['satuan_tujuan_id'],
            'nilai_konversi' => (float)$row['nilai_konversi'],
            'satuan_asal' => $row['satuan_asal'],
            'satuan_tujuan' => $row['satuan_tujuan']
        ];
    }
    
    $stmt->close();
    
    echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
?>
