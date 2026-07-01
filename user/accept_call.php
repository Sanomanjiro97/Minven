<?php
session_start();
require_once '../config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$user_id = (int) $_SESSION['user_id'];
$caller_id = isset($_POST['caller_id']) ? intval($_POST['caller_id']) : 0;
$answer = isset($_POST['answer']) ? json_decode($_POST['answer'], true) : null;

if ($caller_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid caller']);
    exit();
}

if ($answer) {
    $answer_json = json_encode($answer);
    $stmt = $conn->prepare("UPDATE calls SET answer = ?, status = 'active' WHERE caller_id = ? AND receiver_id = ? AND status = 'pending'");
    $stmt->bind_param('sii', $answer_json, $caller_id, $user_id);

    if ($stmt->execute() && $stmt->affected_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'Call accepted']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to accept call']);
    }
    $stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'No answer provided']);
}
