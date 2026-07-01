<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

// Get available supplier
$supplier_sql = "SELECT id, nama_supplier FROM supplier ORDER BY id";
$supplier_result = $conn->query($supplier_sql);
$supplier_list = [];
while ($row = $supplier_result->fetch_assoc()) {
    $supplier_list[] = $row;
}

// Get available kategori
$kategori_sql = "SELECT id, nama_kategori FROM kategori ORDER BY id";
$kategori_result = $conn->query($kategori_sql);
$kategori_list = [];
while ($row = $kategori_result->fetch_assoc()) {
    $kategori_list[] = $row;
}

// Get available satuan
$satuan_sql = "SELECT id, nama_satuan FROM satuan ORDER BY id";
$satuan_result = $conn->query($satuan_sql);
$satuan_list = [];
while ($row = $satuan_result->fetch_assoc()) {
    $satuan_list[] = $row;
}

// Get first available IDs for example
$example_supplier_id = !empty($supplier_list) ? $supplier_list[0]['id'] : 1;
$example_kategori_id = !empty($kategori_list) ? $kategori_list[0]['id'] : 1;
$example_satuan_id = !empty($satuan_list) ? $satuan_list[0]['id'] : 1;

header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=Template_Import_Barang.xls");
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
            <Column ss:Width="80"/>
            <Column ss:Width="80"/>
            <Column ss:Width="80"/>
            <Column ss:Width="80"/>
            <Column ss:Width="100"/>
            <Column ss:Width="120"/>
            
            <!-- Header Row -->
            <Row ss:Height="30" ss:StyleID="HeaderRed">
                <Cell><Data ss:Type="String">Kode Barang*</Data></Cell>
                <Cell><Data ss:Type="String">Nama Barang*</Data></Cell>
                <Cell><Data ss:Type="String">Supplier ID*</Data></Cell>
                <Cell><Data ss:Type="String">Kategori ID*</Data></Cell>
                <Cell><Data ss:Type="String">Satuan ID*</Data></Cell>
                <Cell><Data ss:Type="String">Par Stock*</Data></Cell>
                <Cell><Data ss:Type="String">Harga Beli*</Data></Cell>
                <Cell><Data ss:Type="String">Tanggal Kadaluarsa</Data></Cell>
            </Row>
            
            <!-- Example Row -->
            <Row>
                <Cell><Data ss:Type="String">ANT-001</Data></Cell>
                <Cell><Data ss:Type="String">Contoh Barang</Data></Cell>
                <Cell><Data ss:Type="Number"><?= $example_supplier_id ?></Data></Cell>
                <Cell><Data ss:Type="Number"><?= $example_kategori_id ?></Data></Cell>
                <Cell><Data ss:Type="Number"><?= $example_satuan_id ?></Data></Cell>
                <Cell><Data ss:Type="Number">10</Data></Cell>
                <Cell><Data ss:Type="Number">20000</Data></Cell>
                <Cell><Data ss:Type="String">2024-12-31</Data></Cell>
            </Row>
        </Table>
    </Worksheet>

    <!-- Sheet: Supplier -->
    <Worksheet ss:Name="Daftar Supplier">
        <Table ss:DefaultColumnWidth="120">
            <Row ss:StyleID="Header">
                <Cell><Data ss:Type="String">ID Supplier</Data></Cell>
                <Cell><Data ss:Type="String">Nama Supplier</Data></Cell>
            </Row>
            <?php foreach ($supplier_list as $sup): ?>
            <Row>
                <Cell><Data ss:Type="Number"><?= $sup['id'] ?></Data></Cell>
                <Cell><Data ss:Type="String"><?= htmlspecialchars($sup['nama_supplier']) ?></Data></Cell>
            </Row>
            <?php endforeach; ?>
            <Row></Row>
            <Row ss:StyleID="Header">
                <Cell><Data ss:Type="String">PENTING!</Data></Cell>
            </Row>
            <Row>
                <Cell ss:MergeAcross="1">
                    <Data ss:Type="String">Gunakan HANYA ID yang tercantum di atas. ID lain tidak akan diterima.</Data>
                </Cell>
            </Row>
        </Table>
    </Worksheet>

    <!-- Sheet: Kategori -->
    <Worksheet ss:Name="Daftar Kategori">
        <Table ss:DefaultColumnWidth="120">
            <Row ss:StyleID="Header">
                <Cell><Data ss:Type="String">ID Kategori</Data></Cell>
                <Cell><Data ss:Type="String">Nama Kategori</Data></Cell>
            </Row>
            <?php foreach ($kategori_list as $kat): ?>
            <Row>
                <Cell><Data ss:Type="Number"><?= $kat['id'] ?></Data></Cell>
                <Cell><Data ss:Type="String"><?= htmlspecialchars($kat['nama_kategori']) ?></Data></Cell>
            </Row>
            <?php endforeach; ?>
        </Table>
    </Worksheet>

    <!-- Sheet: Satuan -->
    <Worksheet ss:Name="Daftar Satuan">
        <Table ss:DefaultColumnWidth="120">
            <Row ss:StyleID="Header">
                <Cell><Data ss:Type="String">ID Satuan</Data></Cell>
                <Cell><Data ss:Type="String">Nama Satuan</Data></Cell>
            </Row>
            <?php foreach ($satuan_list as $sat): ?>
            <Row>
                <Cell><Data ss:Type="Number"><?= $sat['id'] ?></Data></Cell>
                <Cell><Data ss:Type="String"><?= htmlspecialchars($sat['nama_satuan']) ?></Data></Cell>
            </Row>
            <?php endforeach; ?>
        </Table>
    </Worksheet>

    <!-- Sheet: Petunjuk -->
    <Worksheet ss:Name="Petunjuk">
        <Table ss:DefaultColumnWidth="400">
            <Row ss:StyleID="Header">
                <Cell><Data ss:Type="String">PENTING! Petunjuk Pengisian Template</Data></Cell>
            </Row>
            <Row><Cell><Data ss:Type="String">1. Kolom yang bertanda * wajib diisi</Data></Cell></Row>
            <Row><Cell><Data ss:Type="String">2. Kode Barang: Gunakan format yang konsisten (contoh: ANT-001)</Data></Cell></Row>
            <Row><Cell><Data ss:Type="String">3. Supplier ID: WAJIB menggunakan ID yang ada di sheet "Daftar Supplier". Jangan menggunakan ID yang tidak terdaftar!</Data></Cell></Row>
            <Row><Cell><Data ss:Type="String">4. Kategori ID: WAJIB menggunakan ID yang ada di sheet "Daftar Kategori"</Data></Cell></Row>
            <Row><Cell><Data ss:Type="String">5. Satuan ID: WAJIB menggunakan ID yang ada di sheet "Daftar Satuan"</Data></Cell></Row>
            <Row><Cell><Data ss:Type="String">6. Par Stock: Isi dengan angka (contoh: 10)</Data></Cell></Row>
            <Row><Cell><Data ss:Type="String">7. Harga Beli: Isi dengan angka tanpa tanda pemisah ribuan (contoh: 20000)</Data></Cell></Row>
            <Row><Cell><Data ss:Type="String">8. Tanggal Kadaluarsa: Format YYYY-MM-DD (contoh: 2024-12-31), boleh dikosongkan</Data></Cell></Row>
            <Row></Row>
            <Row ss:StyleID="Header">
                <Cell><Data ss:Type="String">PERHATIAN!</Data></Cell>
            </Row>
            <Row><Cell><Data ss:Type="String">- PASTIKAN menggunakan ID Supplier yang VALID dari sheet "Daftar Supplier"</Data></Cell></Row>
            <Row><Cell><Data ss:Type="String">- Jika ID tidak ditemukan di database, data tidak akan bisa diimpor</Data></Cell></Row>
            <Row><Cell><Data ss:Type="String">- Jika ragu, cek kembali ID di sheet "Daftar Supplier"</Data></Cell></Row>
            <Row><Cell><Data ss:Type="String">- Contoh data di template menggunakan ID yang valid dari database</Data></Cell></Row>
        </Table>
    </Worksheet>
</Workbook>
