<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get user info
$sql = "SELECT * FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nama = $_POST['nama'];
    $email = $_POST['email'];
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    
    // Handle file upload
    if (!empty($_FILES['profile_picture']['name'])) {
        $target_dir = "../uploads/user/";
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        
        // Delete old profile picture if exists
        if (!empty($user['profile_picture']) && file_exists($user['profile_picture'])) {
            unlink($user['profile_picture']);
        }
        
        $file_name = basename($_FILES['profile_picture']['name']);
        $target_file = $target_dir . $user_id . '_' . time() . '_' . $file_name;
        
        // Check file type and size
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $max_size = 2 * 1024 * 1024; // 2MB
        
        if (in_array($_FILES['profile_picture']['type'], $allowed_types) && 
            $_FILES['profile_picture']['size'] <= $max_size) {
            
            if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $target_file)) {
                $profile_picture = $target_file;
            } else {
                $error = "Gagal mengupload foto profil";
            }
        } else {
            $error = "Format file tidak didukung atau ukuran terlalu besar (maks 2MB)";
        }
    }
    
    // Verify current password
    if (!empty($current_password)) {
        if (password_verify($current_password, $user['password'])) {
            // Update password if new password is provided
            if (!empty($new_password)) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $sql = "UPDATE users SET nama = ?, email = ?, password = ? WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param('sssi', $nama, $email, $hashed_password, $user_id);
            }
        } else {
            $error = "Password saat ini tidak sesuai";
        }
    } else {
        // Update without changing password
        $sql = "UPDATE users SET nama = ?, email = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ssi', $nama, $email, $user_id);
    }
    
    if (!isset($error) && $stmt->execute()) {
        // Update session with new profile picture
        if (isset($profile_picture)) {
            $_SESSION['profile_picture'] = $profile_picture;
        }
        $success = "Profil berhasil diperbarui";
        
        // Update profile picture in database
        if (isset($profile_picture)) {
            $sql = "UPDATE users SET profile_picture = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('si', $profile_picture, $user_id);
            $stmt->execute();
        }
        
        // Refresh user data
        $sql = "SELECT * FROM users WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil User - Sistem Inventory</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.0.7/css/boxicons.min.css" rel="stylesheet">
</head>
<body>
    <?php include '../templates/navbar.php'; ?>

    <div class="container mt-4">
        <div class="row g-4">
            <div class="col-12 col-lg-4">
                <div class="card mb-4">
                    <div class="card-body text-center">
                        <?php if (!empty($user['profile_picture'])): ?>
                            <img src="<?= htmlspecialchars($user['profile_picture']) ?>" class="rounded-circle mb-3" style="width: 150px; height: 150px; object-fit: cover;">
                        <?php else: ?>
                            <div class="rounded-circle bg-light mb-3 d-flex align-items-center justify-content-center" style="width: 150px; height: 150px; margin: 0 auto;">
                                <i class='bx bx-user' style="font-size: 60px;"></i>
                            </div>
                        <?php endif; ?>
                        <h4><?= htmlspecialchars($user['nama']) ?></h4>
                        <p class="text-muted"><?= htmlspecialchars($user['username']) ?></p>
                    </div>
                </div>
            </div>
            <div class="col-12 col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Informasi Profil</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data">
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label">Foto Profil</label>
                                    <input type="file" class="form-control" name="profile_picture" accept="image/*">
                                </div>
                                <div class="col-12 col-md-6">
                                    <label class="form-label">Nama Lengkap</label>
                                    <input type="text" class="form-control" name="nama" value="<?= htmlspecialchars($user['nama']) ?>" required>
                                </div>
                                <div class="col-12 col-md-6">
                                    <label class="form-label">Email</label>
                                    <input type="email" class="form-control" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>
                                </div>
                                <div class="col-12 col-md-6">
                                    <label class="form-label">Password Saat Ini</label>
                                    <input type="password" class="form-control" name="current_password">
                                    <div class="form-text">Kosongkan jika tidak ingin mengubah password</div>
                                </div>
                                <div class="col-12 col-md-6">
                                    <label class="form-label">Password Baru</label>
                                    <input type="password" class="form-control" name="new_password">
                                    <div class="form-text">Kosongkan jika tidak ingin mengubah password</div>
                                </div>
                            </div>
                            <div class="d-grid d-md-flex justify-content-md-end mt-3">
                                <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
