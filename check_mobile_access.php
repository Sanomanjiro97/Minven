<?php
/**
 * Script untuk cek dan fix akses menu mobile
 */
session_start();
require_once 'config.php';

echo "<h2>Cek Akses Menu Mobile</h2>";

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
        
        $has_mobile = false;
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['menu_name']) . "</td>";
            echo "<td>" . ($row['can_view'] ? '✓' : '-') . "</td>";
            echo "<td>" . ($row['can_add'] ? '✓' : '-') . "</td>";
            echo "<td>" . ($row['can_edit'] ? '✓' : '-') . "</td>";
            echo "<td>" . ($row['can_delete'] ? '✓' : '-') . "</td>";
            echo "</tr>";
            
            if (strtolower(trim($row['menu_name'])) === 'mobile') {
                $has_mobile = true;
            }
        }
        echo "</table>";
        
        if (!$has_mobile) {
            echo "<p style='color: red; font-weight: bold;'>Menu 'mobile' tidak ditemukan untuk role ini!</p>";
            
            // Tambahkan menu mobile
            echo "<h3>Menambahkan akses menu mobile...</h3>";
            $insert_sql = "INSERT INTO menu_access (role_id, menu_name, can_view, can_add, can_edit, can_delete) 
                          VALUES (?, 'mobile', 1, 0, 0, 0)";
            $insert_stmt = $authConn->prepare($insert_sql);
            if ($insert_stmt) {
                $insert_stmt->bind_param('i', $role_id);
                if ($insert_stmt->execute()) {
                    echo "<p style='color: green;'>✓ Berhasil menambahkan akses menu mobile!</p>";
                } else {
                    echo "<p style='color: red;'>✗ Gagal menambahkan: " . $insert_stmt->error . "</p>";
                }
                $insert_stmt->close();
            }
        } else {
            echo "<p style='color: green;'>✓ Menu 'mobile' sudah ada untuk role ini.</p>";
        }
        
        $stmt->close();
    }
}

// Cek semua menu yang tersedia
echo "<h3>Semua Menu yang Tersedia di Sistem:</h3>";
$sql = "SELECT DISTINCT menu_name FROM menu_access ORDER BY menu_name";
$result = $authConn->query($sql);
if ($result) {
    echo "<ul>";
    while ($row = $result->fetch_assoc()) {
        echo "<li>" . htmlspecialchars($row['menu_name']) . "</li>";
    }
    echo "</ul>";
}

// Test fungsi hasAccess
echo "<h3>Test Fungsi hasAccess:</h3>";
require_once 'includes/access_check.php';
echo "hasAccess('mobile'): " . (hasAccess('mobile') ? 'TRUE' : 'FALSE') . "<br>";
echo "hasAccess('mobile', 'view'): " . (hasAccess('mobile', 'view') ? 'TRUE' : 'FALSE') . "<br>";

echo "<p><a href='index.php'>Kembali ke Dashboard</a></p>";
