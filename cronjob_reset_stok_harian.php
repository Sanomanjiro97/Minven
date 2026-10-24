<?php
/**
 * Cronjob Script untuk Reset Stok Harian
 * 
 * Script ini dirancang untuk dijalankan via cronjob atau Windows Task Scheduler
 * Setiap hari pada jam yang ditentukan untuk mereset stok harian gudang
 * 
 * Penggunaan:
 * - CLI: php cronjob_reset_stok_harian.php
 * - Web: http://domain/minven_pro/cronjob_reset_stok_harian.php
 * - Cron: 0 0 * * * php /path/to/cronjob_reset_stok_harian.php
 */

// Set timezone ke Asia/Jakarta
date_default_timezone_set('Asia/Jakarta');

// Cek apakah dijalankan dari CLI atau web
$is_cli = (php_sapi_name() === 'cli');

// Load konfigurasi
require_once __DIR__ . '/config.php';

// Logging function
function log_message($message) {
    global $is_cli;
    $timestamp = date('Y-m-d H:i:s');
    $log_message = "[{$timestamp}] {$message}\n";
    
    if ($is_cli) {
        echo $log_message;
    }
    
    // Juga simpan ke file log
    $log_file = __DIR__ . '/logs/reset_stok_harian.log';
    $log_dir = dirname($log_file);
    
    if (!is_dir($log_dir)) {
        @mkdir($log_dir, 0755, true);
    }
    
    @file_put_contents($log_file, $log_message, FILE_APPEND);
}

// Fungsi untuk mereset stok harian
function resetStokHarian($conn) {
    try {
        log_message("Memulai proses reset stok harian...");
        
        // Cek koneksi database
        if (!$conn || $conn->connect_error) {
            throw new Exception("Koneksi database gagal: " . ($conn->connect_error ?? 'Unknown error'));
        }
        
        // Cek tabel setup_reset_stok
        $checkTable = $conn->query("SHOW TABLES LIKE 'setup_reset_stok'");
        if (!$checkTable || $checkTable->num_rows === 0) {
            log_message("Tabel setup_reset_stok tidak ditemukan. Membuat tabel...");
            
            $createTable = "CREATE TABLE IF NOT EXISTS setup_reset_stok (
                id INT AUTO_INCREMENT PRIMARY KEY,
                gudang_id INT,
                jam_reset TIME DEFAULT '00:00:00',
                is_active TINYINT(1) DEFAULT 0,
                last_reset DATETIME DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )";
            
            if (!$conn->query($createTable)) {
                throw new Exception("Gagal membuat tabel setup_reset_stok: " . $conn->error);
            }
            
            // Insert default configuration untuk gudang antapani (ID 13)
            $insertDefault = "INSERT INTO setup_reset_stok (gudang_id, jam_reset, is_active) 
                            VALUES (13, '00:00:00', 1) 
                            ON DUPLICATE KEY UPDATE is_active = 1";
            $conn->query($insertDefault);
            
            log_message("Tabel setup_reset_stok berhasil dibuat dengan konfigurasi default.");
        }
        
        // Cek dan tambahkan kolom last_reset jika belum ada
        $checkColumn = $conn->query("SHOW COLUMNS FROM setup_reset_stok LIKE 'last_reset'");
        if (!$checkColumn || $checkColumn->num_rows === 0) {
            $conn->query("ALTER TABLE setup_reset_stok ADD COLUMN last_reset DATETIME DEFAULT NULL AFTER is_active");
            log_message("Kolom last_reset ditambahkan ke tabel setup_reset_stok.");
        }
        
        // Ambil konfigurasi reset
        $configQuery = "SELECT 
                        MIN(TIME_FORMAT(jam_reset, '%H:%i')) as jam_reset,
                        MAX(last_reset) as last_reset,
                        SUM(is_active = 1) as active_count
                       FROM setup_reset_stok";
        
        $configResult = $conn->query($configQuery);
        if (!$configResult) {
            throw new Exception("Gagal mengambil konfigurasi reset: " . $conn->error);
        }
        
        $config = $configResult->fetch_assoc();
        
        // Cek apakah ada konfigurasi aktif
        if ((int)($config['active_count'] ?? 0) <= 0) {
            log_message("Tidak ada konfigurasi reset aktif. Proses dibatalkan.");
            return false;
        }
        
        $jam_reset = $config['jam_reset'] ?? '00:00';
        $last_reset = $config['last_reset'] ?? null;
        
        log_message("Konfigurasi reset: Jam = {$jam_reset}, Terakhir reset = " . ($last_reset ?? 'Belum pernah'));
        
        // Cek apakah sudah waktunya reset
        $current_time = date('H:i');
        $today = date('Y-m-d');
        
        // Jika dijalankan via cron, kita asumsikan ini adalah waktu yang tepat
        // Jika dijalankan manual, kita reset tanpa cek waktu
        $should_reset = true;
        
        if (!$is_cli) {
            // Untuk web request, cek apakah waktu sesuai
            if ($current_time !== $jam_reset) {
                log_message("Waktu saat ini ({$current_time}) belum sesuai dengan jam reset ({$jam_reset}). Proses dibatalkan.");
                return false;
            }
        }
        
        // Cek apakah sudah reset hari ini
        if ($last_reset) {
            $last_reset_date = date('Y-m-d', strtotime($last_reset));
            if ($last_reset_date === $today) {
                log_message("Stok sudah direset hari ini pada {$last_reset}. Proses dibatalkan.");
                return false;
            }
        }
        
        // Mulai transaksi
        $conn->begin_transaction();
        log_message("Transaksi dimulai...");
        
        // Cek kolom yang tersedia di tabel gudang_stok
        $available_columns = [];
        $columns_query = $conn->query("SHOW COLUMNS FROM gudang_stok");
        if ($columns_query) {
            while ($col = $columns_query->fetch_assoc()) {
                $available_columns[] = $col['Field'];
            }
        }
        
        // Build query update berdasarkan kolom yang tersedia
        $set_parts = [
            "stok_awal = (stok_awal - stok_terpakai)",
            "stok_terpakai = 0"
        ];
        
        if (in_array('stok_sisa', $available_columns)) {
            $set_parts[] = "stok_sisa = (stok_awal - stok_terpakai)";
        }
        
        if (in_array('updated_at', $available_columns)) {
            $set_parts[] = "updated_at = NOW()";
        }
        
        if (in_array('last_reset', $available_columns)) {
            $set_parts[] = "last_reset = NOW()";
        }
        
        if (in_array('modified_by', $available_columns)) {
            $set_parts[] = "modified_by = 0"; // 0 = system
        }
        
        // Update stok untuk semua gudang atau hanya gudang aktif
        $where_clause = "WHERE 1=1"; // Default untuk semua gudang
        
        // Jika ingin hanya untuk gudang tertentu, uncomment baris di bawah
        // $where_clause = "WHERE gudang_id = 13"; // Hanya gudang antapani
        
        $update_query = "UPDATE gudang_stok SET " . implode(", ", $set_parts) . " " . $where_clause;
        
        log_message("Menjalankan query: " . $update_query);
        
        $update_result = $conn->query($update_query);
        if (!$update_result) {
            throw new Exception("Gagal update stok: " . $conn->error);
        }
        
        $affected_rows = $conn->affected_rows;
        log_message("Berhasil mereset {$affected_rows} record stok.");
        
        // Update last_reset di tabel setup_reset_stok
        $update_setup = "UPDATE setup_reset_stok SET last_reset = NOW() WHERE is_active = 1";
        $conn->query($update_setup);
        log_message("Konfigurasi last_reset diperbarui.");
        
        // Commit transaksi
        $conn->commit();
        log_message("Transaksi berhasil di-commit.");
        
        // Log ke tabel history jika ada
        $check_history = $conn->query("SHOW TABLES LIKE 'gudang_stok_history'");
        if ($check_history && $check_history->num_rows > 0) {
            $insert_history = "INSERT INTO gudang_stok_history (gudang_id, barang_id, stok_awal, stok_terpakai, stok_akhir, jenis_transaksi, keterangan, created_at)
                              SELECT gudang_id, barang_id, stok_awal, stok_terpakai, (stok_awal - stok_terpakai), 'reset_harian', 'Reset stok harian otomatis', NOW()
                              FROM gudang_stok";
            $conn->query($insert_history);
            log_message("History reset stok dicatat.");
        }
        
        log_message("Proses reset stok harian berhasil diselesaikan!");
        return true;
        
    } catch (Exception $e) {
        // Rollback transaksi jika ada error
        if (isset($conn) && $conn->connect_error === false) {
            $conn->rollback();
            log_message("Transaksi di-rollback karena error.");
        }
        
        log_message("ERROR: " . $e->getMessage());
        return false;
    }
}

// Jalankan fungsi reset
if ($is_cli) {
    log_message("=== CRONJOB RESET STOK HARIAN (CLI) ===");
} else {
    log_message("=== CRONJOB RESET STOK HARIAN (WEB) ===");
}

$result = resetStokHarian($conn);

if ($is_cli) {
    log_message("=== SELESAI ===");
    exit($result ? 0 : 1);
} else {
    // Untuk web request, return JSON response
    header('Content-Type: application/json');
    echo json_encode([
        'success' => $result,
        'message' => $result ? 'Reset stok harian berhasil' : 'Reset stok harian gagal atau tidak diperlukan',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
