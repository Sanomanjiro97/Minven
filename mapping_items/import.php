<?php
session_start();
require_once '../config.php';
require_once '../includes/access_check.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

if (!hasAccess('mapping_items', 'add')) {
    $_SESSION['error'] = "Akses tidak diizinkan untuk mengimport mapping items";
    header("Location: index.php");
    exit();
}

function read_csv_rows($filepath) {
    $rows = [];
    $handle = fopen($filepath, 'rb');
    if ($handle === false) {
        return $rows;
    }

    $firstLine = fgets($handle);
    if ($firstLine === false) {
        fclose($handle);
        return $rows;
    }

    if (strncmp($firstLine, "\xEF\xBB\xBF", 3) === 0) {
        $firstLine = substr($firstLine, 3);
    }

    $delimiters = [',', ';', "\t"];
    $bestDelimiter = ',';
    $bestCount = -1;
    foreach ($delimiters as $d) {
        $count = substr_count($firstLine, $d);
        if ($count > $bestCount) {
            $bestCount = $count;
            $bestDelimiter = $d;
        }
    }

    $headers = str_getcsv($firstLine, $bestDelimiter);
    if (is_array($headers)) {
        $rows[] = $headers;
    }

    while (($data = fgetcsv($handle, 0, $bestDelimiter)) !== false) {
        $rows[] = $data;
    }

    fclose($handle);
    return $rows;
}

function read_spreadsheetml_rows($filepath) {
    $rows = [];
    $contents = @file_get_contents($filepath);
    if ($contents === false) {
        return $rows;
    }

    if (strncmp($contents, "PK", 2) === 0) {
        require_once '../libs/SimpleXLSX.php';
        $xlsx = \Shuchkin\SimpleXLSX::parse($filepath);
        if ($xlsx) {
            return $xlsx->rows();
        }
        return $rows;
    }

    libxml_use_internal_errors(true);
    $xml = simplexml_load_string($contents);
    if ($xml === false) {
        return $rows;
    }

    $ssNs = 'urn:schemas-microsoft-com:office:spreadsheet';
    $xml->registerXPathNamespace('ss', $ssNs);
    $rowNodes = $xml->xpath('//ss:Worksheet[1]//ss:Table/ss:Row');
    if (!is_array($rowNodes)) {
        return $rows;
    }

    foreach ($rowNodes as $rowNode) {
        $cells = $rowNode->xpath('ss:Cell');
        if (!is_array($cells)) {
            $rows[] = [];
            continue;
        }

        $row = [];
        $col = 0;
        $maxCol = -1;

        foreach ($cells as $cell) {
            $attrs = $cell->attributes($ssNs);
            if (isset($attrs['Index'])) {
                $col = max(0, ((int)$attrs['Index']) - 1);
            }

            $dataNode = $cell->children($ssNs)->Data;
            $value = $dataNode !== null ? (string)$dataNode : '';

            $row[$col] = $value;
            $maxCol = max($maxCol, $col);
            $col++;
        }

        if ($maxCol >= 0) {
            $normalized = [];
            for ($i = 0; $i <= $maxCol; $i++) {
                $normalized[] = isset($row[$i]) ? $row[$i] : '';
            }
            $rows[] = $normalized;
        } else {
            $rows[] = [];
        }
    }

    return $rows;
}

function normalize_header($value) {
    $value = is_string($value) ? $value : '';
    $value = trim($value);
    $value = str_replace('*', '', $value);
    $value = mb_strtolower($value);
    $value = preg_replace('/\s+/', ' ', $value);
    return $value;
}

$create_table_sql = "CREATE TABLE IF NOT EXISTS item_mapping (
    id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    barang_id INT(11) NOT NULL,
    lokasi_id INT(11) NOT NULL,
    aktif TINYINT(1) DEFAULT 1,
    created_by INT(11) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_by INT(11) DEFAULT NULL,
    updated_at DATETIME DEFAULT NULL,
    UNIQUE KEY unique_mapping (barang_id, lokasi_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";
$conn->query($create_table_sql);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    $file = $_FILES['file'];
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($file_ext, ['csv', 'xlsx', 'xls'], true)) {
        $_SESSION['error'] = "File harus berformat .csv, .xlsx, atau .xls";
        header("Location: import.php");
        exit();
    }

    $upload_dir = '../uploads/templates/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    $filename = 'import_item_mapping_' . date('Ymd_His') . '.' . $file_ext;
    $filepath = $upload_dir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        $_SESSION['error'] = "Gagal mengupload file";
        header("Location: import.php");
        exit();
    }

    $rows = [];
    if ($file_ext === 'csv') {
        $rows = read_csv_rows($filepath);
    } elseif ($file_ext === 'xlsx') {
        require_once '../libs/SimpleXLSX.php';
        $xlsx = \Shuchkin\SimpleXLSX::parse($filepath);
        if ($xlsx) {
            $rows = $xlsx->rows();
        } else {
            $_SESSION['error'] = "Gagal membaca file Excel: " . \Shuchkin\SimpleXLSX::parseError();
            @unlink($filepath);
            header("Location: import.php");
            exit();
        }
    } else {
        $rows = read_spreadsheetml_rows($filepath);
    }

    if (empty($rows)) {
        $_SESSION['error'] = "File kosong atau tidak dapat dibaca.";
        @unlink($filepath);
        header("Location: import.php");
        exit();
    }

    $headers = $rows[0];
    $dataRows = $rows;
    array_shift($dataRows);

    $headerMap = [];
    foreach ($headers as $idx => $h) {
        $headerMap[normalize_header($h)] = $idx;
    }

    $idxKodeBarang = $headerMap['kode barang'] ?? $headerMap['kode barang '] ?? 0;
    $idxKodeLokasi = $headerMap['kode lokasi'] ?? 1;
    $idxAktif = $headerMap['aktif (1/0)'] ?? $headerMap['aktif'] ?? 2;

    $stmtBarang = $conn->prepare("SELECT id FROM barang WHERE kode_barang = ? LIMIT 1");
    $stmtLokasi = $conn->prepare("SELECT id FROM lokasi_mapping WHERE kode_lokasi = ? LIMIT 1");
    $stmtCheck = $conn->prepare("SELECT id FROM item_mapping WHERE barang_id = ? AND lokasi_id = ? LIMIT 1");

    if (!$stmtBarang || !$stmtLokasi || !$stmtCheck) {
        $_SESSION['error'] = "Gagal mempersiapkan query import: " . $conn->error;
        @unlink($filepath);
        header("Location: import.php");
        exit();
    }

    $canCreatedBy = false;
    $canUpdatedBy = false;
    $canUpdatedAt = false;

    $colCheck = $conn->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'item_mapping'");
    if ($colCheck) {
        $schema = DB_NAME;
        $colCheck->bind_param('s', $schema);
        $colCheck->execute();
        $resCols = $colCheck->get_result();
        $cols = [];
        while ($r = $resCols->fetch_assoc()) {
            $cols[$r['COLUMN_NAME']] = true;
        }
        $canCreatedBy = isset($cols['created_by']);
        $canUpdatedBy = isset($cols['updated_by']);
        $canUpdatedAt = isset($cols['updated_at']);
        $colCheck->close();
    }

    $insertSql = $canCreatedBy
        ? "INSERT INTO item_mapping (barang_id, lokasi_id, aktif, created_by, created_at) VALUES (?, ?, ?, ?, NOW())"
        : "INSERT INTO item_mapping (barang_id, lokasi_id, aktif) VALUES (?, ?, ?)";

    $updateSql = "UPDATE item_mapping SET aktif = ?";
    if ($canUpdatedBy) {
        $updateSql .= ", updated_by = ?";
    }
    if ($canUpdatedAt) {
        $updateSql .= ", updated_at = NOW()";
    }
    $updateSql .= " WHERE id = ?";

    $stmtInsert = $conn->prepare($insertSql);
    $stmtUpdate = $conn->prepare($updateSql);
    if (!$stmtInsert || !$stmtUpdate) {
        $_SESSION['error'] = "Gagal mempersiapkan query simpan mapping: " . $conn->error;
        $stmtBarang->close();
        $stmtLokasi->close();
        $stmtCheck->close();
        @unlink($filepath);
        header("Location: import.php");
        exit();
    }

    $success_count = 0;
    $error_count = 0;
    $errors = [];

    $conn->begin_transaction();

    foreach ($dataRows as $i => $row) {
        $rowNumber = $i + 2;

        $kodeBarang = trim((string)($row[$idxKodeBarang] ?? ''));
        $kodeLokasi = trim((string)($row[$idxKodeLokasi] ?? ''));
        $aktifRaw = trim((string)($row[$idxAktif] ?? ''));

        if ($kodeBarang === '' && $kodeLokasi === '' && $aktifRaw === '') {
            continue;
        }

        if ($kodeBarang === '' || $kodeLokasi === '') {
            $errors[] = "Baris $rowNumber: Kode Barang atau Kode Lokasi kosong";
            $error_count++;
            continue;
        }

        $aktif = 1;
        if ($aktifRaw !== '') {
            $aktif = ((int)$aktifRaw) === 0 ? 0 : 1;
        }

        $stmtBarang->bind_param('s', $kodeBarang);
        $stmtBarang->execute();
        $resBarang = $stmtBarang->get_result();
        $barangRow = $resBarang ? $resBarang->fetch_assoc() : null;
        if (!$barangRow) {
            $errors[] = "Baris $rowNumber: Kode Barang '$kodeBarang' tidak ditemukan";
            $error_count++;
            continue;
        }
        $barangId = (int)$barangRow['id'];

        $stmtLokasi->bind_param('s', $kodeLokasi);
        $stmtLokasi->execute();
        $resLokasi = $stmtLokasi->get_result();
        $lokasiRow = $resLokasi ? $resLokasi->fetch_assoc() : null;
        if (!$lokasiRow) {
            $errors[] = "Baris $rowNumber: Kode Lokasi '$kodeLokasi' tidak ditemukan";
            $error_count++;
            continue;
        }
        $lokasiId = (int)$lokasiRow['id'];

        $stmtCheck->bind_param('ii', $barangId, $lokasiId);
        $stmtCheck->execute();
        $resCheck = $stmtCheck->get_result();
        $existing = $resCheck ? $resCheck->fetch_assoc() : null;

        if ($existing) {
            $mappingId = (int)$existing['id'];
            if ($canUpdatedBy) {
                $userId = (int)$_SESSION['user_id'];
                if ($canUpdatedAt) {
                    $stmtUpdate->bind_param('iii', $aktif, $userId, $mappingId);
                } else {
                    $stmtUpdate->bind_param('iii', $aktif, $userId, $mappingId);
                }
            } else {
                $stmtUpdate->bind_param('ii', $aktif, $mappingId);
            }

            if ($stmtUpdate->execute()) {
                $success_count++;
            } else {
                $errors[] = "Baris $rowNumber: Gagal update mapping ($kodeBarang - $kodeLokasi) - " . $stmtUpdate->error;
                $error_count++;
            }
        } else {
            if ($canCreatedBy) {
                $userId = (int)$_SESSION['user_id'];
                $stmtInsert->bind_param('iiii', $barangId, $lokasiId, $aktif, $userId);
            } else {
                $stmtInsert->bind_param('iii', $barangId, $lokasiId, $aktif);
            }

            if ($stmtInsert->execute()) {
                $success_count++;
            } else {
                $errors[] = "Baris $rowNumber: Gagal insert mapping ($kodeBarang - $kodeLokasi) - " . $stmtInsert->error;
                $error_count++;
            }
        }
    }

    $conn->commit();

    $stmtInsert->close();
    $stmtUpdate->close();
    $stmtBarang->close();
    $stmtLokasi->close();
    $stmtCheck->close();

    @unlink($filepath);

    if ($success_count > 0) {
        $_SESSION['success'] = "$success_count mapping item berhasil diimport.";
    }
    if ($error_count > 0) {
        $_SESSION['error'] = "$error_count mapping item gagal diimport.<br>" . implode("<br>", $errors);
    }

    header("Location: index.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import Mapping Items - Sistem Inventory</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <?php include '../templates/navbar.php'; ?>

    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="card-title mb-0">Import Mapping Items</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-4 d-flex gap-2 flex-wrap">
                            <a href="download_template.php" class="btn btn-success">Download Template</a>
                            <a href="index.php" class="btn btn-secondary">Kembali</a>
                        </div>

                        <form action="" method="post" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label for="file" class="form-label">Pilih File (.csv, .xlsx, atau .xls)</label>
                                <input type="file" class="form-control" id="file" name="file" accept=".csv,.xlsx,.xls" required>
                                <div class="form-text">Gunakan template yang sudah disediakan agar format kolom sesuai.</div>
                            </div>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">Import Data</button>
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

