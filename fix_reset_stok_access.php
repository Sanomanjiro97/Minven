<?php
/**
 * Script untuk menambahkan akses menu reset_stok ke user
 */
session_start();
require_once 'config.php';

echo "<h2>Fix Akses Menu Reset Stok</h2>";

// Cek session user
echo "<h3>Session Info:</h3>";
echo "<pre>";
echo "User ID: " . ($_SESSION['user_id'] ?? 'Not set') . "\n";
echo "Role ID: " . ($_SESSION['role_id'] ?? 'Not set') . "\n";
echo "Nama Role: " . ($_SESSION['nama_role'] ?? 'Not set') . "\n";
echo "</pre>";

// Cek koneksi auth database
$authConn = auth_db_conn();
if (!$authConn) {
    echo "<p style='color: red;'>Gagal koneksi ke auth database</p>";
    exit;
}

// Cek menu access untuk role user
$role_id = (int)($_SESSION['role_id'] ?? 0);
if ($role_id > 0) {
    echo "<h3>Menu Access untuk Role ID: $role_id</h3>";
    
    $sql = "SELECT menu_name, can_view, can_add, can_edit, can_delete 
            FROM menu_access 
            WHERE role_id = ? 
            ORDER BY menu_name";
    $stmt = $authConn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('i', $role_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>Menu Name</th><th>Can View</th><th>Can Add</th><th>Can Edit</th><th>Can Delete</th></tr>";
        
        $has_reset_stok = false;
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['menu_name']) . "</td>";
            echo "<td>" . ($row['can_view'] ? '✓' : '-') . "</td>";
            echo "<td>" . ($row['can_add'] ? '✓' : '-') . "</td>";
            echo "<td>" . ($row['can_edit'] ? '✓' : '-') . "</td>";
            echo "<td>" . ($row['can_delete'] ? '✓' : '-') . "</td>";
            echo "</tr>";
            
            if (strtolower(trim($row['menu_name'])) === 'reset_stok') {
                $has_reset_stok = true;
            }
        }
        echo "</table>";
        
        if (!$has_reset_stok) {
            echo "<p style='color: red; font-weight: bold;'>Menu 'reset_stok' tidak ditemukan untuk role ini!</p>";
            
            // Tambahkan menu reset_stok
            echo "<h3>Menambahkan akses menu reset_stok...</h3>";
            $insert_sql = "INSERT INTO menu_access (role_id, menu_name, can_view, can_add, can_edit, can_delete) 
                          VALUES (?, 'reset_stok', 1, 1, 1, 0)";
            $insert_stmt = $authConn->prepare($insert_sql);
            if ($insert_stmt) {
                $insert_stmt->bind_param('i', $role_id);
                if ($insert_stmt->execute()) {
                    echo "<p style='color: green;'>✓ Berhasil menambahkan akses menu reset_stok dengan full permission!</p>";
                } else {
                    echo "<p style='color: red;'>✗ Gagal menambahkan: " . $insert_stmt->error . "</p>";
                }
                $insert_stmt->close();
            }
        } else {
            echo "<p style='color: green;'>✓ Menu 'reset_stok' sudah ada untuk role ini.</p>";
            
            // Update permission jika perlu
            echo "<h3>Update permission reset_stok ke full access...</h3>";
            $update_sql = "UPDATE menu_access 
                          SET can_view = 1, can_add = 1, can_edit = 1, can_delete = 0 
                          WHERE role_id = ? AND menu_name = 'reset_stok'";
            $update_stmt = $authConn->prepare($update_sql);
            if ($update_stmt) {
                $update_stmt->bind_param('i', $role_id);
                if ($update_stmt->execute()) {
                    echo "<p style='color: green;'>✓ Berhasil update permission reset_stok!</p>";
                } else {
                    echo "<p style='color: red;'>✗ Gagal update: " . $update_stmt->error . "</p>";
                }
                $update_stmt->close();
            }
        }
        
        $stmt->close();
    }
}

// Test fungsi hasAccess
echo "<h3>Test Fungsi hasAccess:</h3>";
require_once 'includes/access_check.php';
echo "hasAccess('reset_stok'): " . (hasAccess('reset_stok') ? 'TRUE' : 'FALSE') . "<br>";
echo "hasAccess('reset_stok', 'view'): " . (hasAccess('reset_stok', 'view') ? 'TRUE' : 'FALSE') . "<br>";
echo "hasAccess('reset_stok', 'edit'): " . (hasAccess('reset_stok', 'edit') ? 'TRUE' : 'FALSE') . "<br>";

// Cek file setup
echo "<h3>Cek File Setup:</h3>";
$setup_file = __DIR__ . '/setup/index.php';
if (file_exists($setup_file)) {
    echo "<p style='color: green;'>✓ File setup/index.php tersedia</p>";
} else {
    echo "<p style='color: red;'>✗ File setup/index.php tidak tersedia</p>";
}

echo "<p><a href='setup/index.php' class='btn btn-primary'>Buka Halaman Setup Reset Stok</a></p>";
echo "<p><a href='dashboard.php' class='btn btn-secondary'>Kembali ke Dashboard</a></p>";
