<?php
session_start();
require_once '../config.php';
require_once '../includes/access_check.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
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

// Check access untuk import supplier
if (!hasAccess('supplier', 'add')) {
    $_SESSION['error'] = "Akses tidak diizinkan untuk mengimport supplier";
    header("Location: index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    $file = $_FILES['file'];
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    // Check file extension
    if (!in_array($file_ext, ['csv', 'xlsx', 'xls'])) {
        $_SESSION['error'] = "File harus berformat .csv, .xlsx, atau .xls";
        header("Location: index.php");
        exit();
    }

    // Set upload directory
    $upload_dir = '../uploads/templates/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    // Generate unique filename
    $filename = 'import_supplier_' . date('Ymd_His') . '.' . $file_ext;
    $filepath = $upload_dir . $filename;

    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
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
                unlink($filepath);
                header("Location: index.php");
                exit();
            }
        } else {
            $rows = read_spreadsheetml_rows($filepath);
        }

        if (empty($rows)) {
            $_SESSION['error'] = "File kosong atau tidak dapat dibaca.";
            unlink($filepath);
            header("Location: index.php");
            exit();
        }

        array_shift($rows);
        
        // Process the data
        $success_count = 0;
        $error_count = 0;
        $errors = [];
        
        // Prepare insert statement
        $stmt = $conn->prepare("INSERT INTO supplier (kode_supplier, nama_supplier, alamat, telepon, email, no_rekening, terms_of_payment) VALUES (?, ?, ?, ?, ?, ?, ?)");
        if (!$stmt) {
            $_SESSION['error'] = "Gagal mempersiapkan query import: " . $conn->error;
            unlink($filepath);
            header("Location: index.php");
            exit();
        }

        $check_stmt = $conn->prepare("SELECT id FROM supplier WHERE kode_supplier = ? LIMIT 1");
        if (!$check_stmt) {
            $_SESSION['error'] = "Gagal mempersiapkan query validasi supplier: " . $conn->error;
            $stmt->close();
            unlink($filepath);
            header("Location: index.php");
            exit();
        }

        $conn->begin_transaction();
        
        foreach ($rows as $i => $row) {
            $rowNumber = $i + 2;
            if (!is_array($row)) {
                $errors[] = "Baris $rowNumber: Format baris tidak valid";
                $error_count++;
                continue;
            }
            if (empty($row[0]) || empty($row[1])) {
                continue;
            }
            
            // Clean and prepare data
            $kode_supplier = trim($row[0]);
            $nama_supplier = trim($row[1]);
            $alamat = isset($row[2]) ? trim($row[2]) : '';
            $telepon = isset($row[3]) ? trim($row[3]) : '';
            $email = isset($row[4]) ? trim($row[4]) : '';
            $no_rekening = isset($row[5]) ? trim($row[5]) : '';
            $terms_of_payment_raw = isset($row[6]) ? trim($row[6]) : '';
            $terms_of_payment = is_numeric($terms_of_payment_raw) ? (int)$terms_of_payment_raw : 30;
            if ($terms_of_payment <= 0) {
                $terms_of_payment = 30;
            }

            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = "Baris $rowNumber: Format email tidak valid untuk kode '$kode_supplier'";
                $error_count++;
                continue;
            }
            
            // Check if supplier code already exists
            $check_stmt->bind_param("s", $kode_supplier);
            if (!$check_stmt->execute()) {
                $errors[] = "Baris $rowNumber: Gagal validasi kode '$kode_supplier' - " . $check_stmt->error;
                $error_count++;
                continue;
            }
            $check_stmt->store_result();
            if ($check_stmt->num_rows > 0) {
                $errors[] = "Baris $rowNumber: Kode Supplier '$kode_supplier' sudah ada dalam database";
                $error_count++;
                continue;
            }
            
            // Insert data
            $stmt->bind_param("ssssssi", 
                $kode_supplier,
                $nama_supplier,
                $alamat,
                $telepon,
                $email,
                $no_rekening,
                $terms_of_payment
            );
            
            if ($stmt->execute()) {
                $success_count++;
            } else {
                $errors[] = "Baris $rowNumber: Gagal menambahkan supplier '$nama_supplier' - " . $stmt->error;
                $error_count++;
            }
        }
        
        $check_stmt->close();
        $stmt->close();

        $conn->commit();
        
        // Delete the uploaded file
        unlink($filepath);
        
        // Set messages
        if ($success_count > 0) {
            $_SESSION['success'] = "$success_count supplier berhasil diimport.";
        }
        if ($error_count > 0) {
            $_SESSION['error'] = "$error_count supplier gagal diimport.<br>" . implode("<br>", $errors);
        }
        
    } else {
        $_SESSION['error'] = "Gagal mengupload file";
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
    <title>Import Supplier - Sistem Inventory</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <?php include '../templates/navbar.php'; ?>
    
    <div class="mt-5">
        <div class="row">
            <div class="col-md-8 offset-md-2">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="card-title mb-0">Import Data Supplier</h5>
                    </div>
                    <div class="card-body">
                        <form action="" method="post" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label for="file" class="form-label">Pilih File (.csv, .xlsx, atau .xls)</label>
                                <input type="file" class="form-control" id="file" name="file" accept=".csv,.xlsx,.xls" required>
                            </div>
                            <div class="mb-3">
                                <a href="download_template.php" class="btn btn-success">Download Template</a>
                                <button type="submit" class="btn btn-primary">Import Data</button>
                                <a href="index.php" class="btn btn-secondary">Kembali</a>
                            </div>
                        </form>
                        
                        <div class="mt-4">
                            <h6>Petunjuk:</h6>
                            <ol>
                                <li>Download template terlebih dahulu</li>
                                <li>Isi data sesuai format template</li>
                                <li>Kolom bertanda * wajib diisi</li>
                                <li>Terms of Payment dalam satuan hari (default: 30)</li>
                                <li>Upload file yang sudah diisi</li>
                                <li>Klik tombol Import Data</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 
