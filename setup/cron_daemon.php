<?php
require_once '../config.php';

date_default_timezone_set('Asia/Jakarta');

echo "[" . date('Y-m-d H:i:s') . "] Cron Daemon Started - Auto Reset Stok\n";
echo "[" . date('Y-m-d H:i:s') . "] Will check every minute for scheduled resets\n";
echo "[" . date('Y-m-d H:i:s') . "] Press Ctrl+C to stop\n\n";

$last_reset_date = date('Y-m-d');
$processed_resets = [];

while (true) {
    $current_time = date('H:i');
    $current_date = date('Y-m-d');

    if ($current_date !== $last_reset_date) {
        $last_reset_date = $current_date;
        $processed_resets = [];
        echo "[" . date('Y-m-d H:i:s') . "] New day detected, clearing reset cache\n";
    }

    $sql = "SELECT srs.*, g.nama_gudang
            FROM setup_reset_stok srs
            JOIN gudang g ON srs.gudang_id = g.id
            WHERE srs.is_active = 1
            AND TIME_FORMAT(srs.jam_reset, '%H:%i') = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $current_time);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        while ($schedule = $result->fetch_assoc()) {
            $gudang_id = (int)$schedule['gudang_id'];
            $nama_gudang = $schedule['nama_gudang'];
            $jam_reset = $schedule['jam_reset'];
            $reset_key = $gudang_id . '_' . $current_date;

            if (isset($processed_resets[$reset_key])) {
                continue;
            }

            echo "[" . date('Y-m-d H:i:s') . "] Jam reset cocok! Gudang: $nama_gudang (ID: $gudang_id)\n";

            try {
                $conn->begin_transaction();

                $queryGetStok = "SELECT id, stok_awal, stok_terpakai, stok_sisa FROM gudang_stok WHERE gudang_id = ?";
                $stokStmt = $conn->prepare($queryGetStok);
                $stokStmt->bind_param("i", $gudang_id);
                $stokStmt->execute();
                $stokResult = $stokStmt->get_result();

                $resetCount = 0;

                if ($stokResult && $stokResult->num_rows > 0) {
                    while ($row = $stokResult->fetch_assoc()) {
                        $id = (int)$row['id'];
                        $stok_awal = (float)$row['stok_awal'];
                        $stok_terpakai = (float)$row['stok_terpakai'];
                        $stok_sisa = $row['stok_sisa'] ?? null;
                        $stok_akhir = $stok_sisa !== null ? (float)$stok_sisa : ($stok_awal - $stok_terpakai);

                        $updateQuery = "UPDATE gudang_stok
                                      SET stok_awal = ?,
                                          stok_terpakai = 0,
                                          stok_sisa = ?,
                                          updated_at = NOW(),
                                          last_reset = NOW()
                                      WHERE id = ? AND gudang_id = ?";

                        $updateStmt = $conn->prepare($updateQuery);
                        $updateStmt->bind_param("ddii", $stok_akhir, $stok_akhir, $id, $gudang_id);
                        $updateStmt->execute();

                        $resetCount++;
                    }
                }

                $updateSetup = "UPDATE setup_reset_stok SET last_reset = NOW() WHERE gudang_id = ?";
                $setupStmt = $conn->prepare($updateSetup);
                $setupStmt->bind_param("i", $gudang_id);
                $setupStmt->execute();

                $conn->commit();

                $processed_resets[$reset_key] = true;

                echo "[" . date('Y-m-d H:i:s') . "] BERHASIL reset $resetCount item untuk gudang: $nama_gudang\n";

            } catch (Exception $e) {
                $conn->rollback();
                echo "[" . date('Y-m-d H:i:s') . "] GAGAL reset untuk $nama_gudang: " . $e->getMessage() . "\n";
            }

            echo "\n";
        }
    }

    $sleep_seconds = 60 - (date('s'));
    if ($sleep_seconds > 0 && $sleep_seconds < 60) {
        sleep($sleep_seconds);
    } else {
        sleep(60);
    }
}
?>
