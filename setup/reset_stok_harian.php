<?php
require_once __DIR__ . '/../config.php';

date_default_timezone_set('Asia/Jakarta');

if (!($conn instanceof mysqli) || $conn->connect_error) {
    http_response_code(500);
    echo "Koneksi gagal";
    exit();
}

function ensure_setup_reset_stok_table(mysqli $conn): bool {
    $check = $conn->query("SHOW TABLES LIKE 'setup_reset_stok'");
    if (!$check) {
        return false;
    }
    if ($check->num_rows > 0) {
        return true;
    }

    $create = "CREATE TABLE IF NOT EXISTS setup_reset_stok (
        id INT AUTO_INCREMENT PRIMARY KEY,
        jam_reset TIME NOT NULL DEFAULT '00:00:00',
        gudang_id INT NOT NULL,
        is_active TINYINT(1) DEFAULT 1,
        last_reset DATETIME DEFAULT NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        updated_by INT,
        UNIQUE KEY unique_gudang (gudang_id)
    )";

    if ($conn->query($create) !== true) {
        return false;
    }

    $gudangList = $conn->query("SELECT id FROM gudang");
    if ($gudangList) {
        while ($g = $gudangList->fetch_assoc()) {
            $gid = (int)($g['id'] ?? 0);
            if ($gid > 0) {
                @$conn->query("INSERT IGNORE INTO setup_reset_stok (jam_reset, gudang_id, is_active) VALUES ('00:00:00', $gid, 1)");
            }
        }
    }

    return true;
}

function run_reset_stok_harian(mysqli $conn): array {
    $result = [
        'checked_at' => date('Y-m-d H:i:s'),
        'processed_gudang' => 0,
        'updated_items' => 0,
        'skipped_gudang' => 0,
        'errors' => [],
    ];

    if (!ensure_setup_reset_stok_table($conn)) {
        $result['errors'][] = 'setup_reset_stok tidak tersedia';
        return $result;
    }

    $now = new DateTimeImmutable('now', new DateTimeZone('Asia/Jakarta'));
    $today = $now->format('Y-m-d');

    $sqlSchedule = "SELECT gudang_id, jam_reset, last_reset
        FROM setup_reset_stok
        WHERE is_active = 1";
    $schedules = $conn->query($sqlSchedule);
    if (!$schedules) {
        $result['errors'][] = 'Gagal membaca jadwal reset';
        return $result;
    }

    $sqlSelectStok = "SELECT id, barang_id, detail_barang, stok_awal, stok_terpakai, stok_sisa
        FROM gudang_stok
        WHERE gudang_id = ?";
    $stmtSelectStok = $conn->prepare($sqlSelectStok);
    if (!$stmtSelectStok) {
        $result['errors'][] = 'Gagal mempersiapkan query stok';
        return $result;
    }

    $sqlUpdateStok = "UPDATE gudang_stok
        SET stok_awal = ?,
            stok_terpakai = 0,
            stok_sisa = ?,
            updated_at = NOW(),
            last_reset = NOW(),
            modified_by = 0
        WHERE id = ? AND gudang_id = ?";
    $stmtUpdateStok = $conn->prepare($sqlUpdateStok);
    if (!$stmtUpdateStok) {
        $stmtSelectStok->close();
        $result['errors'][] = 'Gagal mempersiapkan query update stok';
        return $result;
    }

    $riwayatTableExists = false;
    $checkRiwayat = $conn->query("SHOW TABLES LIKE 'riwayat_stok'");
    if ($checkRiwayat && $checkRiwayat->num_rows > 0) {
        $riwayatTableExists = true;
    }

    $stmtInsertRiwayat = null;
    if ($riwayatTableExists) {
        $sqlInsertRiwayat = "INSERT INTO riwayat_stok
            (gudang_id, barang_id, stok_awal_sebelum, stok_terpakai_sebelum, stok_akhir_sebelum, tanggal_reset, user_id)
            VALUES (?, ?, ?, ?, ?, NOW(), 0)";
        $stmtInsertRiwayat = $conn->prepare($sqlInsertRiwayat);
        if (!$stmtInsertRiwayat) {
            $stmtInsertRiwayat = null;
        }
    }

    $stmtUpdateSetup = $conn->prepare("UPDATE setup_reset_stok
        SET last_reset = NOW(), updated_by = 0
        WHERE gudang_id = ?
        AND (last_reset IS NULL OR DATE(last_reset) < CURDATE())");
    if (!$stmtUpdateSetup) {
        $stmtInsertRiwayat && $stmtInsertRiwayat->close();
        $stmtUpdateStok->close();
        $stmtSelectStok->close();
        $result['errors'][] = 'Gagal mempersiapkan update setup_reset_stok';
        return $result;
    }

    while ($schedule = $schedules->fetch_assoc()) {
        $gudangId = (int)($schedule['gudang_id'] ?? 0);
        $jamReset = (string)($schedule['jam_reset'] ?? '00:00:00');
        $lastReset = $schedule['last_reset'] ?? null;

        if ($gudangId <= 0) {
            $result['skipped_gudang']++;
            continue;
        }

        $jamResetNorm = strlen($jamReset) === 5 ? ($jamReset . ':00') : $jamReset;
        $resetAt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $today . ' ' . $jamResetNorm, new DateTimeZone('Asia/Jakarta'));
        if (!$resetAt) {
            $result['errors'][] = "Format jam_reset tidak valid untuk gudang_id=$gudangId";
            continue;
        }

        $alreadyResetToday = false;
        if ($lastReset) {
            $lastResetDate = substr((string)$lastReset, 0, 10);
            if ($lastResetDate === $today) {
                $alreadyResetToday = true;
            }
        }

        if ($alreadyResetToday || $now < $resetAt) {
            $result['skipped_gudang']++;
            continue;
        }

        $conn->begin_transaction();
        try {
            $stmtUpdateSetup->bind_param("i", $gudangId);
            if (!$stmtUpdateSetup->execute()) {
                throw new Exception("Gagal mengunci reset gudang_id=$gudangId");
            }
            if ($conn->affected_rows === 0) {
                $conn->rollback();
                $result['skipped_gudang']++;
                continue;
            }

            $stmtSelectStok->bind_param("i", $gudangId);
            if (!$stmtSelectStok->execute()) {
                throw new Exception("Gagal mengambil stok gudang_id=$gudangId");
            }

            $stokRes = $stmtSelectStok->get_result();
            $updated = 0;

            while ($row = $stokRes->fetch_assoc()) {
                $stokId = (int)($row['id'] ?? 0);
                $barangId = (int)($row['barang_id'] ?? 0);
                $stokAwal = (float)($row['stok_awal'] ?? 0);
                $stokTerpakai = (float)($row['stok_terpakai'] ?? 0);
                $stokSisa = $row['stok_sisa'] ?? null;

                $stokAkhir = $stokSisa !== null ? (float)$stokSisa : ($stokAwal - $stokTerpakai);
                if ($stokAkhir < 0) {
                    $stokAkhir = 0;
                }

                if ($stmtInsertRiwayat) {
                    $stmtInsertRiwayat->bind_param("iiiii", $gudangId, $barangId, $stokAwal, $stokTerpakai, $stokAkhir);
                    $stmtInsertRiwayat->execute();
                }

                $stmtUpdateStok->bind_param("ddii", $stokAkhir, $stokAkhir, $stokId, $gudangId);
                if (!$stmtUpdateStok->execute()) {
                    throw new Exception("Gagal update stok id=$stokId gudang_id=$gudangId");
                }
                $updated++;
            }

            $conn->commit();

            $result['processed_gudang']++;
            $result['updated_items'] += $updated;
        } catch (Exception $e) {
            $conn->rollback();
            $result['errors'][] = $e->getMessage();
        }
    }

    $stmtUpdateSetup->close();
    $stmtInsertRiwayat && $stmtInsertRiwayat->close();
    $stmtUpdateStok->close();
    $stmtSelectStok->close();

    return $result;
}

$isDirectRun = false;
if (isset($_SERVER['SCRIPT_FILENAME'])) {
    $isDirectRun = realpath((string)$_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__);
}
if ($isDirectRun) {
    $out = run_reset_stok_harian($conn);
    if (PHP_SAPI !== 'cli') {
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode($out, JSON_UNESCAPED_UNICODE);
}
