<?php
require_once '../config.php';

try {
    // SQL to add can_complete column
    $sql = "ALTER TABLE menu_access ADD COLUMN can_complete TINYINT(1) DEFAULT 0 AFTER can_delete";
    $conn->query($sql);
    
    // Update existing records
    $sql2 = "UPDATE menu_access SET can_complete = 0 WHERE can_complete IS NULL";
    $conn->query($sql2);
    
    echo "Kolom can_complete berhasil ditambahkan ke tabel menu_access!";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}

$conn->close();
?>