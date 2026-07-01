<?php
session_start();
require_once '../config.php';
require_once '../includes/access_check.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

// Check access untuk download template
if (!hasAccess('supplier', 'view')) {
    $_SESSION['error'] = "Akses tidak diizinkan untuk mengunduh template supplier";
    header("Location: index.php");
    exit();
}

header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=Template_Import_Supplier.xls");
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
        <Style ss:ID="Default" ss:Name="Normal">
            <Alignment ss:Vertical="Center"/>
            <Borders>
                <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/>
                <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/>
                <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/>
                <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/>
            </Borders>
        </Style>
        <Style ss:ID="Header">
            <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
            <Font ss:Bold="1"/>
            <Interior ss:Color="#CCCCCC" ss:Pattern="Solid"/>
            <Borders>
                <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/>
                <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/>
                <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/>
                <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/>
            </Borders>
        </Style>
        <Style ss:ID="HeaderRed">
            <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
            <Font ss:Bold="1" ss:Color="#FF0000"/>
            <Interior ss:Color="#CCCCCC" ss:Pattern="Solid"/>
            <Borders>
                <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/>
                <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/>
                <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/>
                <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/>
            </Borders>
        </Style>
    </Styles>

    <!-- Sheet: Template -->
    <Worksheet ss:Name="Template Import">
        <Table ss:DefaultColumnWidth="100">
            <Column ss:Width="120"/>
            <Column ss:Width="200"/>
            <Column ss:Width="250"/>
            <Column ss:Width="120"/>
            <Column ss:Width="200"/>
            <Column ss:Width="200"/>
            <Column ss:Width="100"/>
            
            <!-- Header Row -->
            <Row ss:Height="30" ss:StyleID="HeaderRed">
                <Cell><Data ss:Type="String">Kode Supplier*</Data></Cell>
                <Cell><Data ss:Type="String">Nama Supplier*</Data></Cell>
                <Cell><Data ss:Type="String">Alamat</Data></Cell>
                <Cell><Data ss:Type="String">Telepon</Data></Cell>
                <Cell><Data ss:Type="String">Email</Data></Cell>
                <Cell><Data ss:Type="String">No Rekening</Data></Cell>
                <Cell><Data ss:Type="String">Terms of Payment (hari)</Data></Cell>
            </Row>
            
            <!-- Example Row -->
            <Row>
                <Cell><Data ss:Type="String">SUP001</Data></Cell>
                <Cell><Data ss:Type="String">PT Supplier Example</Data></Cell>
                <Cell><Data ss:Type="String">Jl. Contoh No. 123</Data></Cell>
                <Cell><Data ss:Type="String">08123456789</Data></Cell>
                <Cell><Data ss:Type="String">supplier@example.com</Data></Cell>
                <Cell><Data ss:Type="String">BCA 1234567890</Data></Cell>
                <Cell><Data ss:Type="Number">30</Data></Cell>
            </Row>
        </Table>
    </Worksheet>

    <!-- Sheet: Petunjuk -->
    <Worksheet ss:Name="Petunjuk">
        <Table ss:DefaultColumnWidth="400">
            <Row ss:StyleID="Header">
                <Cell><Data ss:Type="String">PENTING! Petunjuk Pengisian Template</Data></Cell>
            </Row>
            <Row><Cell><Data ss:Type="String">1. Kolom yang bertanda * wajib diisi</Data></Cell></Row>
            <Row><Cell><Data ss:Type="String">2. Kode Supplier: Gunakan format yang konsisten (contoh: SUP001)</Data></Cell></Row>
            <Row><Cell><Data ss:Type="String">3. Nama Supplier: Isi dengan nama lengkap supplier</Data></Cell></Row>
            <Row><Cell><Data ss:Type="String">4. Alamat: Boleh dikosongkan</Data></Cell></Row>
            <Row><Cell><Data ss:Type="String">5. Telepon: Boleh dikosongkan, gunakan format yang konsisten</Data></Cell></Row>
            <Row><Cell><Data ss:Type="String">6. Email: Boleh dikosongkan, pastikan format email valid jika diisi</Data></Cell></Row>
            <Row><Cell><Data ss:Type="String">7. No Rekening: Boleh dikosongkan</Data></Cell></Row>
            <Row><Cell><Data ss:Type="String">8. Terms of Payment: Isi dengan angka dalam satuan hari (default: 30)</Data></Cell></Row>
            <Row></Row>
            <Row ss:StyleID="Header">
                <Cell><Data ss:Type="String">PERHATIAN!</Data></Cell>
            </Row>
            <Row><Cell><Data ss:Type="String">- Pastikan Kode Supplier UNIK dan belum ada di database</Data></Cell></Row>
            <Row><Cell><Data ss:Type="String">- Jika Kode Supplier sudah ada, data tidak akan bisa diimpor</Data></Cell></Row>
            <Row><Cell><Data ss:Type="String">- Terms of Payment akan otomatis diisi 30 hari jika dikosongkan</Data></Cell></Row>
            <Row><Cell><Data ss:Type="String">- Contoh data di template bisa digunakan sebagai referensi format</Data></Cell></Row>
        </Table>
    </Worksheet>
</Workbook> 