<?php
session_start();
require_once '../config.php';
require_once '../includes/access_check.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

function clean_input($data) {
    global $conn;
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $conn->real_escape_string($data);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        // Check access untuk add supplier
        if (!hasAccess('supplier', 'add')) {
            $_SESSION['error'] = "Akses tidak diizinkan untuk menambah supplier";
            header("Location: index.php");
            exit();
        }
        
        // Get form data
        $kode_supplier = $_POST['kode_supplier'];
        $nama_supplier = $_POST['nama_supplier'];
        $alamat = $_POST['alamat'];
        $telepon = $_POST['telepon'];
        $email = $_POST['email'];
        $no_rekening = $_POST['no_rekening'];
        $terms_of_payment = $_POST['terms_of_payment'];
        
        // Handle image upload
        $gambar = null;
        if (isset($_FILES['gambar']) && $_FILES['gambar']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = '../uploads/supplier/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            $fileExtension = pathinfo($_FILES['gambar']['name'], PATHINFO_EXTENSION);
            $fileName = uniqid() . '_' . time() . '.' . $fileExtension;
            $targetPath = $uploadDir . $fileName;
            
            // Validate file type and size
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
            $maxFileSize = 2 * 1024 * 1024; // 2MB
            
            if (in_array(strtolower($fileExtension), $allowedExtensions) && 
                $_FILES['gambar']['size'] <= $maxFileSize && 
                move_uploaded_file($_FILES['gambar']['tmp_name'], $targetPath)) {
                $gambar = $fileName;
            }
        }
        
        // Check if custom ID is provided
        $custom_id = isset($_POST['custom_id']) ? (int)$_POST['custom_id'] : null;
        
        // Prepare SQL with optional ID
        if ($custom_id !== null) {
            $sql = "INSERT INTO supplier (id, kode_supplier, nama_supplier, alamat, telepon, email, no_rekening, terms_of_payment, gambar) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param("issssssss", $custom_id, $kode_supplier, $nama_supplier, $alamat, $telepon, $email, $no_rekening, $terms_of_payment, $gambar);
            } else {
                $_SESSION['error'] = "Gagal mempersiapkan query: " . $conn->error;
                header("Location: index.php");
                exit();
            }
        } else {
            $sql = "INSERT INTO supplier (kode_supplier, nama_supplier, alamat, telepon, email, no_rekening, terms_of_payment, gambar) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param("ssssssss", $kode_supplier, $nama_supplier, $alamat, $telepon, $email, $no_rekening, $terms_of_payment, $gambar);
            } else {
                $_SESSION['error'] = "Gagal mempersiapkan query: " . $conn->error;
                header("Location: index.php");
                exit();
            }
        }
        
        if ($stmt && $stmt->execute()) {
            $_SESSION['success'] = "Supplier berhasil ditambahkan.";
        } else {
            $_SESSION['error'] = "Gagal menambahkan supplier: " . ($stmt ? $stmt->error : $conn->error);
        }
        
        if ($stmt) {
            $stmt->close();
        }
        
    } elseif ($action === 'edit') {
        // Check access untuk edit supplier
        if (!hasAccess('supplier', 'edit')) {
            $_SESSION['error'] = "Akses tidak diizinkan untuk mengedit supplier";
            header("Location: index.php");
            exit();
        }
        
        $id = $_POST['id'];
        $kode_supplier = $_POST['kode_supplier'];
        $nama_supplier = $_POST['nama_supplier'];
        $alamat = $_POST['alamat'];
        $telepon = $_POST['telepon'];
        $email = $_POST['email'];
        $no_rekening = $_POST['no_rekening'];
        $terms_of_payment = $_POST['terms_of_payment'];
        
        // Handle image upload for edit
        $gambarUpdate = '';
        if (isset($_FILES['gambar']) && $_FILES['gambar']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = '../uploads/supplier/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            $fileExtension = pathinfo($_FILES['gambar']['name'], PATHINFO_EXTENSION);
            $fileName = uniqid() . '_' . time() . '.' . $fileExtension;
            $targetPath = $uploadDir . $fileName;
            
            // Validate file type and size
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
            $maxFileSize = 2 * 1024 * 1024; // 2MB
            
            if (in_array(strtolower($fileExtension), $allowedExtensions) && 
                $_FILES['gambar']['size'] <= $maxFileSize && 
                move_uploaded_file($_FILES['gambar']['tmp_name'], $targetPath)) {
                $gambarUpdate = ', gambar = ?';
                
                // Delete old image if exists
                $oldImageSql = "SELECT gambar FROM supplier WHERE id = ?";
                $oldStmt = $conn->prepare($oldImageSql);
                $oldStmt->bind_param("i", $id);
                $oldStmt->execute();
                $oldStmt->bind_result($oldImage);
                $oldStmt->fetch();
                $oldStmt->close();
                
                if ($oldImage && file_exists($uploadDir . $oldImage)) {
                    unlink($uploadDir . $oldImage);
                }
            }
        }
        
        $sql = "UPDATE supplier SET 
                kode_supplier = ?,
                nama_supplier = ?,
                alamat = ?,
                telepon = ?,
                email = ?,
                no_rekening = ?,
                terms_of_payment = ?" . $gambarUpdate . "
                WHERE id = ?";
                
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            if (!empty($gambarUpdate)) {
                $stmt->bind_param("ssssssssi", $kode_supplier, $nama_supplier, $alamat, $telepon, $email, $no_rekening, $terms_of_payment, $fileName, $id);
            } else {
                $stmt->bind_param("sssssssi", $kode_supplier, $nama_supplier, $alamat, $telepon, $email, $no_rekening, $terms_of_payment, $id);
            }
            
            if ($stmt->execute()) {
                $_SESSION['success'] = "Supplier berhasil diupdate.";
            } else {
                $_SESSION['error'] = "Gagal mengupdate supplier: " . $stmt->error;
            }
            
            $stmt->close();
        } else {
            $_SESSION['error'] = "Gagal mempersiapkan query update: " . $conn->error;
        }
    }
    
    header("Location: index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'delete') {
    // Check access untuk delete supplier
    if (!hasAccess('supplier', 'delete')) {
        $_SESSION['error'] = "Akses tidak diizinkan untuk menghapus supplier";
        header("Location: index.php");
        exit();
    }
    
    $id = $_GET['id'];
    
    $sql = "DELETE FROM supplier WHERE id = ?";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Supplier berhasil dihapus.";
        } else {
            $_SESSION['error'] = "Gagal menghapus supplier: " . $stmt->error;
        }
        
        $stmt->close();
    } else {
        $_SESSION['error'] = "Gagal mempersiapkan query delete: " . $conn->error;
    }
    
    header("Location: index.php");
    exit();
}

// If no valid action, redirect back
header("Location: index.php");
exit();
?>