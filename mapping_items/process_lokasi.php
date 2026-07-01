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
        $nama_lokasi = $_POST['nama_lokasi'];
        $kode_lokasi = $_POST['kode_lokasi'];
        $deskripsi = $_POST['deskripsi'] ?? null;
        
        // Check if location code already exists
        $check_sql = "SELECT id FROM lokasi_mapping WHERE kode_lokasi = ? AND aktif = 1";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("s", $kode_lokasi);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $_SESSION['error'] = "Kode lokasi sudah digunakan!";
        } else {
            $sql = "INSERT INTO lokasi_mapping (nama_lokasi, kode_lokasi, deskripsi, aktif) VALUES (?, ?, ?, 1)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sss", $nama_lokasi, $kode_lokasi, $deskripsi);
            
            if ($stmt->execute()) {
                $_SESSION['success'] = "Lokasi berhasil ditambahkan.";
            } else {
                $_SESSION['error'] = "Gagal menambahkan lokasi: " . $stmt->error;
            }
            $stmt->close();
        }
        $check_stmt->close();
        
    } elseif ($action === 'edit') {
        $id = $_POST['id'];
        $nama_lokasi = $_POST['nama_lokasi'];
        $kode_lokasi = $_POST['kode_lokasi'];
        $deskripsi = $_POST['deskripsi'] ?? null;
        
        // Check if location code already exists for other locations
        $check_sql = "SELECT id FROM lokasi_mapping WHERE kode_lokasi = ? AND id != ? AND aktif = 1";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("si", $kode_lokasi, $id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $_SESSION['error'] = "Kode lokasi sudah digunakan oleh lokasi lain!";
        } else {
            $sql = "UPDATE lokasi_mapping SET nama_lokasi = ?, kode_lokasi = ?, deskripsi = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssi", $nama_lokasi, $kode_lokasi, $deskripsi, $id);
            
            if ($stmt->execute()) {
                $_SESSION['success'] = "Lokasi berhasil diupdate.";
            } else {
                $_SESSION['error'] = "Gagal mengupdate lokasi: " . $stmt->error;
            }
            $stmt->close();
        }
        $check_stmt->close();
        
    } elseif ($action === 'toggle') {
        $id = $_POST['id'];
        
        // Get current status
        $status_sql = "SELECT aktif FROM lokasi_mapping WHERE id = ?";
        $status_stmt = $conn->prepare($status_sql);
        $status_stmt->bind_param("i", $id);
        $status_stmt->execute();
        $status_result = $status_stmt->get_result();
        $current_status = $status_result->fetch_assoc()['aktif'];
        $new_status = $current_status ? 0 : 1;
        
        // Update status
        $sql = "UPDATE lokasi_mapping SET aktif = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $new_status, $id);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Status lokasi berhasil diubah.";
            
            // If deactivating, also deactivate related mappings
            if ($new_status == 0) {
                $update_mappings = "UPDATE item_mapping SET aktif = 0 WHERE lokasi_id = ?";
                $mapping_stmt = $conn->prepare($update_mappings);
                $mapping_stmt->bind_param("i", $id);
                $mapping_stmt->execute();
                $mapping_stmt->close();
            }
        } else {
            $_SESSION['error'] = "Gagal mengubah status lokasi: " . $stmt->error;
        }
        $stmt->close();
        $status_stmt->close();
    }
}

header("Location: lokasi.php");
exit(); 