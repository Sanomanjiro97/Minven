<?php
session_start();
require_once '../config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$user_id = (int) $_SESSION['user_id'];
$receiver_id = isset($_POST['receiver_id']) ? intval($_POST['receiver_id']) : 0;
$candidate = isset($_POST['candidate']) ? json_decode($_POST['candidate'], true) : null;

if ($receiver_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid receiver']);
    exit();
}

if ($candidate) {
    $stmt = $conn->prepare("UPDATE calls SET ice_candidates = JSON_ARRAY_APPEND(COALESCE(ice_candidates, '[]'), '$', JSON_OBJECT('candidate', JSON_QUOTE(?), 'sdpMid', COALESCE(JSON_QUOTE(?), NULL), 'sdpMLineIndex', COALESCE(?, 0))) WHERE (caller_id = ? AND receiver_id = ?) OR (caller_id = ? AND receiver_id = ?) AND status IN ('pending', 'active')");
    
    $candidate_json = json_encode($candidate);
    $sdp_mid = $candidate['sdpMid'] ?? null;
    $sdp_mline_index = $candidate['sdpMLineIndex'] ?? 0;

    $stmt->bind_param('ssi', $candidate_json, $receiver_id, $user_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to store ICE candidate']);
    }
    $stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'No candidate provided']);
}
