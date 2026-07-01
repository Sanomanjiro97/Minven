<?php
// Manual SQL execution for adding payment update columns
// Run this file directly in browser to add the required columns

require_once '../../config.php';

echo "<h2>Menambahkan Kolom untuk Fitur Update Pembayaran</h2>";
echo "<hr>";

try {
    // Check if updated_at column exists
    $check_updated_sql = "SHOW COLUMNS FROM surat_jalan LIKE 'updated_at'";
    $check_updated_result = $GLOBALS['conn']->query($check_updated_sql);
    $has_updated_at = $check_updated_result && $check_updated_result->num_rows > 0;
    
    if (!$has_updated_at) {
        echo "<p>Menambahkan kolom <strong>updated_at</strong>...</p>";
        $add_updated_sql = "ALTER TABLE `surat_jalan` 
                           ADD COLUMN `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() 
                           AFTER `created_at`";
        if ($GLOBALS['conn']->query($add_updated_sql)) {
            echo "<p style='color: green;'>✓ Kolom <strong>updated_at</strong> berhasil ditambahkan!</p>";
        } else {
            echo "<p style='color: red;'>✗ Gagal menambahkan kolom <strong>updated_at</strong>: " . $GLOBALS['conn']->error . "</p>";
        }
    } else {
        echo "<p style='color: blue;'>ℹ Kolom <strong>updated_at</strong> sudah ada.</p>";
    }
    
    // Check if keterangan_pembayaran column exists
    $check_keterangan_sql = "SHOW COLUMNS FROM surat_jalan LIKE 'keterangan_pembayaran'";
    $check_keterangan_result = $GLOBALS['conn']->query($check_keterangan_sql);
    $has_keterangan = $check_keterangan_result && $check_keterangan_result->num_rows > 0;
    
    if (!$has_keterangan) {
        echo "<p>Menambahkan kolom <strong>keterangan_pembayaran</strong>...</p>";
        $add_keterangan_sql = "ALTER TABLE `surat_jalan` 
                              ADD COLUMN `keterangan_pembayaran` text DEFAULT NULL 
                              AFTER `status_pembayaran`";
        if ($GLOBALS['conn']->query($add_keterangan_sql)) {
            echo "<p style='color: green;'>✓ Kolom <strong>keterangan_pembayaran</strong> berhasil ditambahkan!</p>";
        } else {
            echo "<p style='color: red;'>✗ Gagal menambahkan kolom <strong>keterangan_pembayaran</strong>: " . $GLOBALS['conn']->error . "</p>";
        }
    } else {
        echo "<p style='color: blue;'>ℹ Kolom <strong>keterangan_pembayaran</strong> sudah ada.</p>";
    }
    
    echo "<hr>";
    echo "<h3>Status Akhir:</h3>";
    
    // Final check
    $final_check_updated = $GLOBALS['conn']->query("SHOW COLUMNS FROM surat_jalan LIKE 'updated_at'");
    $final_check_keterangan = $GLOBALS['conn']->query("SHOW COLUMNS FROM surat_jalan LIKE 'keterangan_pembayaran'");
    
    if ($final_check_updated && $final_check_updated->num_rows > 0 && 
        $final_check_keterangan && $final_check_keterangan->num_rows > 0) {
        echo "<p style='color: green; font-weight: bold;'>🎉 SEMUA KOLOM BERHASIL DITAMBAHKAN!</p>";
        echo "<p>Fitur update pembayaran surat jalan siap digunakan.</p>";
    } else {
        echo "<p style='color: red; font-weight: bold;'>❌ ADA KOLOM YANG GAGAL DITAMBAHKAN!</p>";
        echo "<p>Silakan cek error di atas dan coba lagi.</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<p><a href='view.php'>Kembali ke Surat Jalan</a></p>";
?> 