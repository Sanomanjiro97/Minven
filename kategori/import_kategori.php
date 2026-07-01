<?php
session_start();
require_once '../config.php';
require_once '../libs/SimpleXLSX.php';

use Shuchkin\SimpleXLSX;

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

// Add this at the beginning of the file, before the POST handling
if (isset($_GET['action']) && $_GET['action'] === 'download') {
    header("Content-Type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=Template_Import_Kategori.xls");
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
                <Font ss:Bold="1"/>
                <Interior ss:Color="#CCCCCC" ss:Pattern="Solid"/>
            </Style>
            <Style ss:ID="Example">
                <Interior ss:Color="#E8F0FE" ss:Pattern="Solid"/>
            </Style>
        </Styles>
        
        <Worksheet ss:Name="Template Import Kategori">
            <Table>
                <Column ss:Width="100"/>
                <Column ss:Width="150"/>
                <Column ss:Width="100"/>
                
                <Row ss:StyleID="Header">
                    <Cell><Data ss:Type="String">Kode Kategori*</Data></Cell>
                    <Cell><Data ss:Type="String">Nama Kategori*</Data></Cell>
                    <Cell><Data ss:Type="String">Parent ID</Data></Cell>
                </Row>
                <Row ss:StyleID="Example">
                    <Cell><Data ss:Type="String">KTG001</Data></Cell>
                    <Cell><Data ss:Type="String">Elektronik</Data></Cell>
                    <Cell><Data ss:Type="String"></Data></Cell>
                </Row>
                <Row ss:StyleID="Example">
                    <Cell><Data ss:Type="String">KTG002</Data></Cell>
                    <Cell><Data ss:Type="String">Komputer</Data></Cell>
                    <Cell><Data ss:Type="Number">1</Data></Cell>
                </Row>
            </Table>
        </Worksheet>
    </Workbook>
    <?php
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['xlsx_file'])) {
    $fileTmpPath = $_FILES['xlsx_file']['tmp_name'];
    $fileName = $_FILES['xlsx_file']['name'];
    $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

    if ($ext !== 'xlsx') {
        $_SESSION['error'] = "File harus berformat XLSX.";
        header("Location: index.php");
        exit();
    }

    if ($xlsx = SimpleXLSX::parse($fileTmpPath)) {
        $rows = $xlsx->rows();

        $successCount = 0;
        $rowCount = 0;

        foreach ($rows as $row) {
            // Lewati header (baris pertama)
            if ($rowCount === 0) {
                $rowCount++;
                continue;
            }

            $kode = trim($row[0] ?? '');
            $nama = trim($row[1] ?? '');
            $parent_id = isset($row[2]) && is_numeric($row[2]) ? intval($row[2]) : null;

            if ($kode !== '' && $nama !== '') {
                if ($parent_id === null) {
                    $stmt = $conn->prepare("INSERT INTO kategori (kode_kategori, nama_kategori) VALUES (?, ?)");
                    $stmt->bind_param("ss", $kode, $nama);
                } else {
                    // Pastikan parent_id ada di database sebelum insert
                    $cek = $conn->prepare("SELECT id FROM kategori WHERE id = ?");
                    $cek->bind_param("i", $parent_id);
                    $cek->execute();
                    $cek->store_result();
                    if ($cek->num_rows === 0) {
                        $parent_id = null; // Reset jika parent_id tidak valid
                    }
                    $cek->close();

                    if ($parent_id === null) {
                        $stmt = $conn->prepare("INSERT INTO kategori (kode_kategori, nama_kategori) VALUES (?, ?)");
                        $stmt->bind_param("ss", $kode, $nama);
                    } else {
                        $stmt = $conn->prepare("INSERT INTO kategori (kode_kategori, nama_kategori, parent_id) VALUES (?, ?, ?)");
                        $stmt->bind_param("ssi", $kode, $nama, $parent_id);
                    }
                }

                if ($stmt->execute()) {
                    $successCount++;
                }
                $stmt->close();
            }
            $rowCount++;
        }

        $_SESSION['success'] = "Import selesai. Berhasil menambah $successCount kategori.";
    } else {
        $_SESSION['error'] = "Gagal parsing file XLSX: " . SimpleXLSX::parseError();
    }
} else {
    $_SESSION['error'] = "Tidak ada file yang diupload.";
}

header("Location: index.php");
exit();
?>
