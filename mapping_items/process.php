<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $barang_id = $_POST['barang_id'];
        $lokasi_id = $_POST['lokasi_id'];
        
        // Check if mapping already exists
        $check_sql = "SELECT id FROM item_mapping WHERE barang_id = ? AND lokasi_id = ? AND aktif = 1";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("ii", $barang_id, $lokasi_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $_SESSION['error'] = "Mapping untuk barang dan lokasi ini sudah ada!";
        } else {
            $sql = "INSERT INTO item_mapping (barang_id, lokasi_id, aktif) VALUES (?, ?, 1)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ii", $barang_id, $lokasi_id);
            
            if ($stmt->execute()) {
                $_SESSION['success'] = "Mapping berhasil ditambahkan.";
            } else {
                $_SESSION['error'] = "Gagal menambahkan mapping: " . $stmt->error;
            }
            $stmt->close();
        }
        $check_stmt->close();
        
    } elseif ($action === 'delete') {
        $id = $_POST['id'];
        
        // Instead of deleting, set aktif = 0
        $sql = "UPDATE item_mapping SET aktif = 0 WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Mapping berhasil dinonaktifkan.";
        } else {
            $_SESSION['error'] = "Gagal menonaktifkan mapping: " . $stmt->error;
        }
        $stmt->close();
        
    } elseif ($action === 'toggle') {
        $id = $_POST['id'];
        
        // Get current status
        $status_sql = "SELECT aktif FROM item_mapping WHERE id = ?";
        $status_stmt = $conn->prepare($status_sql);
        $status_stmt->bind_param("i", $id);
        $status_stmt->execute();
        $status_result = $status_stmt->get_result();
        $current_status = $status_result->fetch_assoc()['aktif'];
        $new_status = $current_status ? 0 : 1;
        
        // Update status
        $sql = "UPDATE item_mapping SET aktif = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $new_status, $id);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Status mapping berhasil diubah.";
        } else {
            $_SESSION['error'] = "Gagal mengubah status mapping: " . $stmt->error;
        }
        $stmt->close();
        $status_stmt->close();
    }
}

header("Location: index.php");
exit(); 