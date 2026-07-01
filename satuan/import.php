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
    header("Content-Disposition: attachment; filename=Template_Import_Satuan.xls");
    header("Pragma: no-cache");
    header("Expires: 0");

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
                <Font ss:Bold="1"></Font>
                <Interior ss:Color="#CCCCCC" ss:Pattern="Solid"/>
            </Style>
            <Style ss:ID="Example">
                <Interior ss:Color="#E8F0FE" ss:Pattern="Solid"/>
            </Style>
        </Styles>
        
        <Worksheet ss:Name="Template Import Satuan">
            <Table>
                <Column ss:Width="100"/>
                <Column ss:Width="150"/>
                
                <Row ss:StyleID="Header">
                    <Cell><Data ss:Type="String">Kode Satuan*</Data></Cell>
                    <Cell><Data ss:Type="String">Nama Satuan*</Data></Cell>
                </Row>
                <Row ss:StyleID="Example">
                    <Cell><Data ss:Type="String">KG</Data></Cell>
                    <Cell><Data ss:Type="String">Kilogram</Data></Cell>
                </Row>
                <Row ss:StyleID="Example">
                    <Cell><Data ss:Type="String">LTR</Data></Cell>
                    <Cell><Data ss:Type="String">Liter</Data></Cell>
                </Row>
            </Table>
        </Worksheet>
        
        <Worksheet ss:Name="Petunjuk">
            <Table>
                <Column ss:Width="150"/>
                <Column ss:Width="300"/>
                
                <Row ss:StyleID="Header">
                    <Cell><Data ss:Type="String">Kolom</Data></Cell>
                    <Cell><Data ss:Type="String">Keterangan</Data></Cell>
                </Row>
                <Row>
                    <Cell><Data ss:Type="String">Kode Satuan*</Data></Cell>
                    <Cell><Data ss:Type="String">Wajib diisi. Kode unik untuk satuan (contoh: KG, LTR, PCS)</Data></Cell>
                </Row>
                <Row>
                    <Cell><Data ss:Type="String">Nama Satuan*</Data></Cell>
                    <Cell><Data ss:Type="String">Wajib diisi. Nama lengkap satuan</Data></Cell>
                </Row>
            </Table>
        </Worksheet>
    </Workbook>
    <?php
    exit();
}

// Handle file import
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file_satuan'])) {
    $file = $_FILES['file_satuan'];
    
    if ($xlsx = SimpleXLSX::parse($file['tmp_name'])) {
        $rows = $xlsx->rows();
        array_shift($rows); // Remove header row
        
        $success = 0;
        $failed = 0;
        $errors = [];
        
        if (!isset($conn) || !$conn) {
            $_SESSION['error'] = "Koneksi database gagal!";
            header("Location: index.php");
            exit();
        }

        foreach ($rows as $index => $row) {
            // Skip empty rows
            if (empty($row[0]) && empty($row[1])) continue;
            
            // Validasi wajib isi
            if (empty($row[0]) || empty($row[1])) {
                $errors[] = "Baris " . ($index + 2) . ": Kode dan Nama Satuan wajib diisi";
                $failed++;
                continue;
            }

            $kode_satuan = trim((string)$row[0]);
            $nama_satuan = trim((string)$row[1]);

            try {
                // Cek duplikat
                $check = $conn->prepare("SELECT id FROM satuan WHERE kode_satuan = ? OR nama_satuan = ?");
                if (!$check) {
                    throw new Exception("Database error: " . $conn->error);
                }
                
                $check->bind_param("ss", $kode_satuan, $nama_satuan);
                $check->execute();
                $result = $check->get_result();
            
                if ($result->num_rows > 0) {
                    $errors[] = "Baris " . ($index + 2) . ": Data dengan kode '$kode_satuan' atau nama '$nama_satuan' sudah ada";
                    $failed++;
                    continue;
                }

                // Insert ke database
                $stmt = $conn->prepare("INSERT INTO satuan (kode_satuan, nama_satuan, created_by, created_at) 
                                        VALUES (?, ?, ?, NOW())");
                if (!$stmt) {
                    throw new Exception("Database error: " . $conn->error);
                }
                
                $user_id = $_SESSION['user_id'];
                $stmt->bind_param("ssi", 
                    $kode_satuan,
                    $nama_satuan,
                    $user_id
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
        
        if ($success > 0) {
            $_SESSION['success'] = "Import selesai. Berhasil: $success, Gagal: $failed";
        }
        
        if (!empty($errors)) {
            $_SESSION['error'] = "Beberapa baris gagal diimport:<br>" . implode("<br>", $errors);
        }
        
        header("Location: index.php");
        exit();
    } else {
        $_SESSION['error'] = "Error parsing file: " . SimpleXLSX::parseError();
        header("Location: import.php");
        exit();
    }
}

$page_title = "Import Data Satuan";
include '../templates/header.php';
include '../templates/navbar.php';
?>

<style>
    .dashboard-container {
        margin-left: 260px; /* Aligned with fixed sidebar */
        padding: 2rem;
        transition: all 0.3s ease;
    }
    
    @media (max-width: 991.98px) {
        .dashboard-container {
            margin-left: 0;
            padding: 1rem;
        }
    }

    .card {
        border: none;
        border-radius: 12px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.05);
    }

    .card-header {
        background-color: #fff;
        border-bottom: 1px solid #edf2f7;
        padding: 1.25rem;
        border-radius: 12px 12px 0 0 !important;
    }

    .btn-primary {
        background-color: #004aad;
        border-color: #004aad;
    }

    .btn-primary:hover {
        background-color: #003a8c;
        border-color: #003a8c;
    }
</style>

<div class="dashboard-container">
    <div class="container-fluid">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <nav aria-label="breadcrumb" class="mb-4">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="../dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="index.php">Satuan</a></li>
                        <li class="breadcrumb-item active">Import</li>
                    </ol>
                </nav>

                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?= $_SESSION['success']; unset($_SESSION['success']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?= $_SESSION['error']; unset($_SESSION['error']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Import Data Satuan</h5>
                        <a href="index.php" class="btn btn-outline-secondary btn-sm">
                            <i class='bx bx-arrow-back'></i> Kembali
                        </a>
                    </div>
                    <div class="card-body p-4">
                        <div class="alert alert-info mb-4">
                            <h6><i class='bx bx-info-circle'></i> Petunjuk Import:</h6>
                            <ul class="mb-0">
                                <li>Gunakan template Excel yang telah disediakan.</li>
                                <li>Kolom <strong>Kode Satuan</strong> dan <strong>Nama Satuan</strong> wajib diisi.</li>
                                <li>Pastikan tidak ada data ganda (Kode atau Nama) yang sudah terdaftar di sistem.</li>
                                <li>Simpan file dalam format <strong>.xlsx</strong>.</li>
                            </ul>
                        </div>

                        <div class="mb-4 text-center">
                            <a href="?action=download" class="btn btn-success px-4">
                                <i class='bx bx-download'></i> Download Template Excel
                            </a>
                        </div>

                        <hr class="my-4">

                        <form action="" method="post" enctype="multipart/form-data">
                            <div class="mb-4">
                                <label for="file_satuan" class="form-label fw-bold">Pilih File Excel (.xlsx)</label>
                                <input type="file" class="form-control form-control-lg" name="file_satuan" id="file_satuan" 
                                       accept=".xlsx" required>
                                <div class="form-text mt-2">Pastikan file yang diunggah adalah file Excel (.xlsx) yang valid.</div>
                            </div>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class='bx bx-upload'></i> Mulai Import Data
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../templates/footer.php'; ?>
