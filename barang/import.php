<?php
session_start();
require_once '../config.php';
require_once '../libs/SimpleXLSX.php';

use Shuchkin\SimpleXLSX;

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

// Handle template download
if (isset($_GET['action']) && $_GET['action'] === 'download') {
    header("Content-Type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=Template_Import_Barang.xls");
    header("Pragma: no-cache");
    header("Expires: 0");

    $supplier_result = $conn->query("SELECT id, nama_supplier FROM supplier ORDER BY nama_supplier");
    $kategori_result = $conn->query("SELECT id, nama_kategori FROM kategori ORDER BY nama_kategori");
    $satuan_result = $conn->query("SELECT id, nama_satuan FROM satuan ORDER BY nama_satuan");

    echo '<?xml version="1.0"?>' . "\n";
    echo '<?mso-application progid="Excel.Sheet"?>' . "\n";
    ?>
    <Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"
              xmlns:o="urn:schemas-microsoft-com:office:office"
              xmlns:x="urn:schemas-microsoft-com:office:excel"
              xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"
              xmlns:html="http://www.w3.org/TR/REC-html40">
        <Styles>
            <Style ss:ID="Header">
            </Style>
        </Styles>
        
        <!-- Template Sheet -->
        <Worksheet ss:Name="Template Import Barang">
            <Table>
                <Row ss:StyleID="Header">
                    <Cell><Data ss:Type="String">Kode Barang*</Data></Cell>
                    <Cell><Data ss:Type="String">Nama Barang*</Data></Cell>
                    <Cell><Data ss:Type="String">Supplier ID*</Data></Cell>
                    <Cell><Data ss:Type="String">Kategori ID*</Data></Cell>
                    <Cell><Data ss:Type="String">Satuan ID*</Data></Cell>
                    <Cell><Data ss:Type="String">Par Stock*</Data></Cell>
                    <Cell><Data ss:Type="String">Harga Beli*</Data></Cell>
                    <Cell><Data ss:Type="String">Tanggal Kadaluarsa</Data></Cell>
                </Row>
            </Table>
        </Worksheet>
        
        <!-- Reference Sheets -->
        <Worksheet ss:Name="Supplier">
            <Table>
                <Row ss:StyleID="Header">
                    <Cell><Data ss:Type="String">ID</Data></Cell>
                    <Cell><Data ss:Type="String">Nama Supplier</Data></Cell>
                </Row>
                <?php while ($row = $supplier_result->fetch_assoc()): ?>
                <Row>
                    <Cell><Data ss:Type="Number"><?= $row['id'] ?></Data></Cell>
                    <Cell><Data ss:Type="String"><?= htmlspecialchars($row['nama_supplier']) ?></Data></Cell>
                </Row>
                <?php endwhile; ?>
            </Table>
        </Worksheet>
        
        <Worksheet ss:Name="Kategori">
            <Table>
                <Row ss:StyleID="Header">
                    <Cell><Data ss:Type="String">ID</Data></Cell>
                    <Cell><Data ss:Type="String">Nama Kategori</Data></Cell>
                </Row>
                <?php while ($row = $kategori_result->fetch_assoc()): ?>
                <Row>
                    <Cell><Data ss:Type="Number"><?= $row['id'] ?></Data></Cell>
                    <Cell><Data ss:Type="String"><?= htmlspecialchars($row['nama_kategori']) ?></Data></Cell>
                </Row>
                <?php endwhile; ?>
            </Table>
        </Worksheet>
        
        <Worksheet ss:Name="Satuan">
            <Table>
                <Row ss:StyleID="Header">
                    <Cell><Data ss:Type="String">ID</Data></Cell>
                    <Cell><Data ss:Type="String">Nama Satuan</Data></Cell>
                </Row>
                <?php while ($row = $satuan_result->fetch_assoc()): ?>
                <Row>
                    <Cell><Data ss:Type="Number"><?= $row['id'] ?></Data></Cell>
                    <Cell><Data ss:Type="String"><?= htmlspecialchars($row['nama_satuan']) ?></Data></Cell>
                </Row>
                <?php endwhile; ?>
            </Table>
        </Worksheet>
    </Workbook>
    <?php
    exit();
}

// Handle file import
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file_barang'])) {
    $file = $_FILES['file_barang'];
    
    if ($xlsx = SimpleXLSX::parse($file['tmp_name'])) {
        $rows = $xlsx->rows();
        array_shift($rows); // Remove header row
        
        $success = 0;
        $failed = 0;
        $errors = [];
        
        foreach ($rows as $index => $row) {
            if (empty($row[0])) continue; // Skip empty rows
            
            // Validate required fields
            if (empty($row[0]) || empty($row[1]) || empty($row[2]) || 
                empty($row[3]) || empty($row[4]) || empty($row[5])) {
                $errors[] = "Baris " . ($index + 2) . ": Data wajib tidak lengkap";
                $failed++;
                continue;
            }

            try {
                $stmt = $conn->prepare("INSERT INTO barang (
                    kode_barang, nama_barang, supplier_id, kategori_id, 
                    satuan_id, stok_minimum, harga_beli, expired_at, created_by, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");

                $expired_at = !empty($row[7]) ? date('Y-m-d', strtotime($row[7])) : null;
                
                $stmt->bind_param("ssiiiddsi", 
                    $row[0], // kode_barang
                    $row[1], // nama_barang
                    $row[2], // supplier_id
                    $row[3], // kategori_id
                    $row[4], // satuan_id
                    $row[5], // stok_minimum
                    $row[6], // harga_beli
                    $expired_at,
                    $_SESSION['user_id']
                );
                
                if ($stmt->execute()) {
                    $success++;
                } else {
                    $errors[] = "Baris " . ($index + 2) . ": " . $stmt->error;
                    $failed++;
                }
            } catch (Exception $e) {
                $errors[] = "Baris " . ($index + 2) . ": " . $e->getMessage();
                $failed++;
            }
        }
        
        $_SESSION['success'] = "Import selesai. Berhasil: $success, Gagal: $failed";
        if (!empty($errors)) {
            $_SESSION['error'] = implode("<br>", $errors);
        }
    } else {
        $_SESSION['error'] = "Error: " . SimpleXLSX::parseError();
    }
    
    header("Location: index.php");
    exit();
}

// If no action or invalid action, redirect to index
header("Location: index.php");
exit();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import Data Barang - Sistem Inventory</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <?php include '../templates/navbar.php'; ?>

    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Import Data Barang</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-4">
                            <a href="download_template.php" class="btn btn-success">
                                <i class="bx bx-download"></i> Download Template
                            </a>
                        </div>

                        <form action="" method="post" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label for="file_barang" class="form-label">Pilih file Excel (XLSX)</label>
                                <input type="file" class="form-control" name="file_barang" id="file_barang" 
                                       accept=".xlsx" required>
                                <div class="form-text">Format yang didukung: XLSX</div>
                            </div>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bx bx-upload"></i> Import Data
                                </button>
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
