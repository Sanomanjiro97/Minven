<?php
session_start();
require_once '../config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$check_table = $conn->query("SHOW TABLES LIKE 'calls'");
if ($check_table->num_rows == 0) {
    $create_table = "CREATE TABLE calls (
        id INT(11) NOT NULL AUTO_INCREMENT,
        caller_id INT(11) NOT NULL,
        receiver_id INT(11) NOT NULL,
        call_type ENUM('audio', 'video') DEFAULT 'audio',
        status ENUM('pending', 'active', 'ended', 'rejected', 'missed') DEFAULT 'pending',
        offer TEXT DEFAULT NULL,
        answer TEXT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY caller_id (caller_id),
        KEY receiver_id (receiver_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    $conn->query($create_table);
}

if (!db_has_column($conn, 'calls', 'ice_candidates')) {
    $conn->query("ALTER TABLE calls ADD COLUMN ice_candidates TEXT DEFAULT NULL AFTER answer");
}

$user_id = (int) $_SESSION['user_id'];
$receiver_id = isset($_POST['receiver_id']) ? intval($_POST['receiver_id']) : 0;
$call_type = isset($_POST['call_type']) && $_POST['call_type'] === 'video' ? 'video' : 'audio';
$offer = isset($_POST['offer']) ? json_decode($_POST['offer'], true) : null;

if ($receiver_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid receiver']);
    exit();
}

if ($offer) {
    $offer_json = json_encode($offer);
    $stmt = $conn->prepare("INSERT INTO calls (caller_id, receiver_id, call_type, status, offer) VALUES (?, ?, ?, 'pending', ?)");
    $stmt->bind_param('iiss', $user_id, $receiver_id, $call_type, $offer_json);

    if ($stmt->execute()) {
        $call_id = $conn->insert_id;
        echo json_encode(['success' => true, 'call_id' => $call_id, 'message' => 'Call initiated']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to initiate call']);
    }
    $stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'No offer provided']);
}
