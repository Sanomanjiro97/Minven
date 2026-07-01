<?php
session_start();
require_once '../../config.php';

if (!isset($_SESSION['user_id'])) {
    header('HTTP/1.1 401 Unauthorized');
    exit(json_encode(['error' => 'Unauthorized']));
}

if (!isset($_GET['id'])) {
    header('HTTP/1.1 400 Bad Request');
    exit(json_encode(['error' => 'ID required']));
}

$po_id = intval($_GET['id']);

// Query untuk mendapatkan status PO
$sql = "SELECT status FROM purchase_order WHERE id = ?";
$stmt = $conn->prepare($sql);

if ($stmt === false) {
    header('HTTP/1.1 500 Internal Server Error');
    exit(json_encode(['error' => 'Database error']));
}

$stmt->bind_param('i', $po_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('HTTP/1.1 404 Not Found');
    exit(json_encode(['error' => 'PO not found']));
}

$po = $result->fetch_assoc();
header('Content-Type: application/json');
echo json_encode(['status' => $po['status']]);
?>