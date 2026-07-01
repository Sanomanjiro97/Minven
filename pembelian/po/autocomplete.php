<?php
session_start();
require_once '../../config.php';
require_once '../../includes/access_check.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode([]);
    exit();
}

// Check access untuk menu purchase_order
if (!checkAccess('purchase_order', 'view')) {
    echo json_encode([]);
    exit();
}

// Ambil parameter pencarian
$term = isset($_GET['term']) ? trim($_GET['term']) : '';

if (empty($term)) {
    echo json_encode([]);
    exit();
}

// Query untuk mencari data berdasarkan No PO, Supplier, atau User
$sql = "SELECT DISTINCT po.no_po, s.nama_supplier, u.nama as user_name
        FROM purchase_order po
        LEFT JOIN supplier s ON po.supplier_id = s.id
        LEFT JOIN users u ON po.created_by = u.id
        WHERE po.no_po LIKE ? 
           OR s.nama_supplier LIKE ? 
           OR u.nama LIKE ?
        ORDER BY po.no_po DESC
        LIMIT 10";

$stmt = $conn->prepare($sql);
if ($stmt) {
    $search_term = "%$term%";
    $stmt->bind_param("sss", $search_term, $search_term, $search_term);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $suggestions = [];
    while ($row = $result->fetch_assoc()) {
        $label = $row['no_po'] . ' - ' . $row['nama_supplier'] . ' (' . $row['user_name'] . ')';
        $suggestions[] = [
            'label' => $label,
            'value' => $row['no_po']
        ];
    }
    
    echo json_encode($suggestions);
} else {
    echo json_encode([]);
}

$conn->close();
?>