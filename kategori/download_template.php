<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

// Set headers for XLS download
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=template_kategori.xls");
header("Pragma: no-cache");
header("Expires: 0");

// Output XLS content
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
        <Table ss:DefaultColumnWidth="120">
            <Column ss:Width="150"/>
            <Column ss:Width="200"/>
            
            <!-- Header Row -->
            <Row ss:Height="30" ss:StyleID="HeaderRed">
                <Cell><Data ss:Type="String">Kode Kategori*</Data></Cell>
                <Cell><Data ss:Type="String">Nama Kategori*</Data></Cell>
            </Row>
            
            <!-- Example Rows -->
            <Row>
                <Cell><Data ss:Type="String">KAT001</Data></Cell>
                <Cell><Data ss:Type="String">Contoh Kategori 1</Data></Cell>
            </Row>
            <Row>
                <Cell><Data ss:Type="String">KAT002</Data></Cell>
                <Cell><Data ss:Type="String">Contoh Kategori 2</Data></Cell>
            </Row>
            <Row>
                <Cell><Data ss:Type="String">KAT003</Data></Cell>
                <Cell><Data ss:Type="String">Contoh Kategori 3</Data></Cell>
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
            <Row><Cell><Data ss:Type="String">2. Kode Kategori: Gunakan format yang konsisten (contoh: KAT001)</Data></Cell></Row>
            <Row><Cell><Data ss:Type="String">3. Nama Kategori: Isi dengan nama kategori yang jelas</Data></Cell></Row>
            <Row><Cell><Data ss:Type="String">4. Pastikan tidak ada kode kategori yang duplikat</Data></Cell></Row>
            <Row><Cell><Data ss:Type="String">5. Simpan file dalam format .xls atau .xlsx</Data></Cell></Row>
            <Row></Row>
            <Row ss:StyleID="Header">
                <Cell><Data ss:Type="String">PERHATIAN!</Data></Cell>
            </Row>
            <Row><Cell><Data ss:Type="String">- Kode kategori harus unik dan tidak boleh sama dengan yang sudah ada</Data></Cell></Row>
            <Row><Cell><Data ss:Type="String">- Jika kode kategori sudah ada, data tidak akan bisa diimpor</Data></Cell></Row>
            <Row><Cell><Data ss:Type="String">- Pastikan format file sesuai dengan template yang disediakan</Data></Cell></Row>
        </Table>
    </Worksheet>
</Workbook>