<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

// Handle tambah role baru
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['nama_role'])) {
    $nama_role = $_POST['nama_role'];
    $sql = "INSERT INTO roles (nama_role) VALUES (?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $nama_role);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Role baru berhasil ditambahkan";
    } else {
        $_SESSION['error'] = "Gagal menambahkan role: " . $conn->error;
    }
    
    header("Location: roles.php");
    exit();
}

// Handle hapus role
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $role_id = (int)$_GET['id'];
    
    // Check if role is being used
    $check_sql = "SELECT COUNT(*) as count FROM user_roles WHERE role_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param('i', $role_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    $row = $result->fetch_assoc();
    
    if ($row['count'] > 0) {
        $_SESSION['error'] = "Role tidak dapat dihapus karena sedang digunakan oleh pengguna";
    } else {
        // Delete menu access first
        $delete_access_sql = "DELETE FROM menu_access WHERE role_id = ?";
        $delete_access_stmt = $conn->prepare($delete_access_sql);
        $delete_access_stmt->bind_param('i', $role_id);
        $delete_access_stmt->execute();
        
        // Delete role
        $delete_role_sql = "DELETE FROM roles WHERE id = ?";
        $delete_role_stmt = $conn->prepare($delete_role_sql);
        $delete_role_stmt->bind_param('i', $role_id);
        
        if ($delete_role_stmt->execute()) {
            $_SESSION['success'] = "Role berhasil dihapus";
        } else {
            $_SESSION['error'] = "Gagal menghapus role: " . $conn->error;
        }
    }
    
    header("Location: roles.php");
    exit();
}

// Query untuk mengambil data role
$sql = "SELECT r.*, COUNT(ur.id) as total_users 
        FROM roles r 
        LEFT JOIN user_roles ur ON r.id = ur.role_id 
        GROUP BY r.id 
        ORDER BY r.nama_role";
$result = $conn->query($sql);
if (!$result) {
    die("Query failed: " . $conn->error);
}

$roles = [];
while ($row = $result->fetch_assoc()) {
    $roles[] = $row;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Role - Sistem Inventory</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.0.7/css/boxicons.min.css" rel="stylesheet">
    <style>
        :root {
            --minven-nav-start: #000000;
            --minven-nav-end: #0800f9;
            --minven-surface: #ffffff;
            --minven-border: rgba(17, 24, 39, 0.10);
            --minven-shadow: 0 18px 40px rgba(2, 6, 23, 0.10);
        }

        body {
            background: radial-gradient(1200px 600px at 15% 0%, rgba(8, 0, 249, 0.08) 0%, rgba(8, 0, 249, 0.00) 60%),
                        radial-gradient(900px 500px at 85% 10%, rgba(8, 0, 249, 0.06) 0%, rgba(8, 0, 249, 0.00) 55%),
                        #f6f8ff;
            min-height: 100vh;
        }

        .minven-wrap {
            background: var(--minven-surface);
            border: 1px solid var(--minven-border);
            border-radius: 18px;
            box-shadow: var(--minven-shadow);
            overflow: hidden;
        }

        .minven-header {
            background: linear-gradient(135deg, var(--minven-nav-start) 0%, var(--minven-nav-end) 100%);
            color: rgba(255, 255, 255, 0.96);
            padding: 18px 18px;
        }

        .minven-header__title {
            font-weight: 800;
            letter-spacing: 0.2px;
            margin: 0;
            font-size: 1.1rem;
        }

        .minven-header__subtitle {
            margin: 4px 0 0;
            color: rgba(255, 255, 255, 0.82);
            font-size: 0.92rem;
        }

        .minven-actions .btn {
            border-radius: 12px;
            min-height: 44px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-weight: 700;
        }

        .minven-actions .btn-outline-light {
            border-color: rgba(255, 255, 255, 0.65);
            color: rgba(255, 255, 255, 0.95);
        }

        .minven-actions .btn-outline-light:hover,
        .minven-actions .btn-outline-light:focus {
            background: rgba(255, 255, 255, 0.16);
            border-color: rgba(255, 255, 255, 0.75);
            color: #ffffff;
        }

        .minven-actions .btn-light {
            color: #111827;
            background: rgba(255, 255, 255, 0.95);
            border-color: rgba(255, 255, 255, 0.95);
        }

        .minven-actions .btn-light:hover,
        .minven-actions .btn-light:focus {
            background: #ffffff;
            border-color: #ffffff;
            color: #111827;
        }

        .table thead th {
            background: #f1f5ff;
            border-bottom: 1px solid rgba(17, 24, 39, 0.12);
            font-weight: 800;
            color: #0f172a;
            white-space: nowrap;
        }

        .table tbody td {
            vertical-align: middle;
        }

        .minven-badge {
            background: rgba(8, 0, 249, 0.12);
            color: #0800f9;
            border: 1px solid rgba(8, 0, 249, 0.22);
            font-weight: 800;
        }

        .btn-soft {
            border-radius: 10px;
            font-weight: 700;
        }

        .btn-soft-info {
            background: rgba(13, 202, 240, 0.16);
            border-color: rgba(13, 202, 240, 0.22);
            color: #055160;
        }

        .btn-soft-info:hover,
        .btn-soft-info:focus {
            background: rgba(13, 202, 240, 0.24);
            border-color: rgba(13, 202, 240, 0.30);
            color: #055160;
        }

        .btn-soft-danger {
            background: rgba(220, 53, 69, 0.12);
            border-color: rgba(220, 53, 69, 0.22);
            color: #842029;
        }

        .btn-soft-danger:hover,
        .btn-soft-danger:focus {
            background: rgba(220, 53, 69, 0.18);
            border-color: rgba(220, 53, 69, 0.28);
            color: #842029;
        }

        @media (max-width: 575.98px) {
            .minven-header {
                padding: 16px 14px;
            }
            .minven-actions {
                width: 100%;
            }
            .minven-actions .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <?php include '../templates/navbar.php'; ?>

    <div class="container py-4">
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= $_SESSION['success'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= $_SESSION['error'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <div class="minven-wrap">
            <div class="minven-header">
                <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-sm-between gap-3">
                    <div>
                        <h1 class="minven-header__title">Manajemen Role</h1>
                        <p class="minven-header__subtitle mb-0">Kelola role dan hak akses pengguna</p>
                    </div>
                    <div class="minven-actions d-flex flex-column flex-sm-row gap-2">
                        <button type="button" class="btn btn-outline-light" data-bs-toggle="modal" data-bs-target="#roleAccessPickerModal">
                            <i class="bi bi-shield-lock"></i> Menu Access
                        </button>
                        <button type="button" class="btn btn-light" data-bs-toggle="modal" data-bs-target="#addRoleModal">
                            <i class='bx bx-plus'></i> Tambah Role
                        </button>
                    </div>
                </div>
            </div>

            <div class="p-3 p-md-4">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th style="width: 72px;">No</th>
                                <th>Nama Role</th>
                                <th style="width: 160px;">Jumlah User</th>
                                <th style="width: 210px;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $no = 1;
                            foreach ($roles as $row): 
                            ?>
                            <tr>
                                <td class="text-muted fw-bold"><?= $no++ ?></td>
                                <td class="fw-semibold"><?= htmlspecialchars($row['nama_role']) ?></td>
                                <td>
                                    <span class="badge rounded-pill minven-badge px-3 py-2">
                                        <?= (int)$row['total_users'] ?> User
                                    </span>
                                </td>
                                <td>
                                    <div class="d-flex flex-wrap gap-2">
                                        <a href="role_access.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-soft btn-soft-info">
                                            <i class='bx bx-key'></i> Hak Akses
                                        </a>
                                        <?php if((int)$row['total_users'] === 0): ?>
                                            <a href="roles.php?action=delete&id=<?= $row['id'] ?>" class="btn btn-sm btn-soft btn-soft-danger" onclick="return confirm('Apakah Anda yakin ingin menghapus role ini?')">
                                                <i class='bx bx-trash'></i> Hapus
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="roleAccessPickerModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="GET" action="role_access.php">
                    <div class="modal-header">
                        <h5 class="modal-title">Pilih Role untuk Hak Akses</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Role</label>
                            <select class="form-select" name="id" required>
                                <option value="" selected disabled>Pilih role...</option>
                                <?php foreach ($roles as $r): ?>
                                    <option value="<?= (int)$r['id'] ?>"><?= htmlspecialchars($r['nama_role']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">Buka</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Tambah Role -->
    <div class="modal fade" id="addRoleModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Tambah Role Baru</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Nama Role</label>
                            <input type="text" class="form-control" name="nama_role" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
