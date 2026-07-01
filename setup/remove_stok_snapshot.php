<?php
require_once __DIR__ . '/../config.php';

header('Content-Type: text/plain; charset=utf-8');

try {
    $errors = [];
    $messages = [];

    // Drop trigger that references stok_snapshot if present
    $triggerName = 'tr_after_gudang_stok_update';
    $checkTriggerSql = "SELECT TRIGGER_NAME 
                        FROM INFORMATION_SCHEMA.TRIGGERS 
                        WHERE TRIGGER_SCHEMA = DATABASE() AND TRIGGER_NAME = ?";
    if ($stmt = $conn->prepare($checkTriggerSql)) {
        $stmt->bind_param('s', $triggerName);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $res->num_rows > 0) {
            if ($conn->query("DROP TRIGGER IF EXISTS `{$triggerName}`") === true) {
                $messages[] = "Trigger {$triggerName} berhasil dihapus.";
            } else {
                $errors[] = "Gagal menghapus trigger {$triggerName}: " . $conn->error;
            }
        } else {
            $messages[] = "Trigger {$triggerName} tidak ditemukan.";
        }
        $stmt->close();
    } else {
        $errors[] = "Gagal memeriksa trigger: " . $conn->error;
    }

    // Optional: drop table if it exists (cleanup only)
    $checkTableSql = "SELECT TABLE_NAME 
                      FROM INFORMATION_SCHEMA.TABLES 
                      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'stok_snapshot'";
    $resTbl = $conn->query($checkTableSql);
    if ($resTbl && $resTbl->num_rows > 0) {
        if ($conn->query("DROP TABLE IF EXISTS `stok_snapshot`") === true) {
            $messages[] = "Tabel stok_snapshot berhasil dihapus.";
        } else {
            $errors[] = "Gagal menghapus tabel stok_snapshot: " . $conn->error;
        }
    } else {
        $messages[] = "Tabel stok_snapshot tidak ditemukan (diabaikan).";
    }

    echo implode(PHP_EOL, $messages) . PHP_EOL;
    if (!empty($errors)) {
        echo "ERRORS:" . PHP_EOL . implode(PHP_EOL, $errors) . PHP_EOL;
        http_response_code(500);
    } else {
        echo "Selesai tanpa error." . PHP_EOL;
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo "Terjadi kesalahan: " . $e->getMessage() . PHP_EOL;
}
