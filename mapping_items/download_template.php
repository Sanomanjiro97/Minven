<?php
session_start();
require_once '../config.php';
require_once '../includes/access_check.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

if (!checkAccess('mapping_items', 'view')) {
    $_SESSION['error'] = 'Anda tidak memiliki akses untuk mengunduh template mapping items!';
    header('Location: index.php');
    exit();
}

function xml_escape($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_XML1, 'UTF-8');
}

$barang_result = $conn->query("SELECT id, kode_barang, nama_barang FROM barang ORDER BY nama_barang");
$barang_list = [];
if ($barang_result instanceof mysqli_result) {
    while ($row = $barang_result->fetch_assoc()) {
        $barang_list[] = $row;
    }
}

$lokasi_sql = "SELECT id, kode_lokasi, nama_lokasi FROM lokasi_mapping";
if (function_exists('db_has_column') && db_has_column($conn, 'lokasi_mapping', 'aktif')) {
    $lokasi_sql .= " WHERE aktif = 1";
}
$lokasi_sql .= " ORDER BY nama_lokasi";
$lokasi_result = $conn->query($lokasi_sql);
$lokasi_list = [];
if ($lokasi_result instanceof mysqli_result) {
    while ($row = $lokasi_result->fetch_assoc()) {
        $lokasi_list[] = $row;
    }
}

$example_kode_barang = !empty($barang_list) ? (string)$barang_list[0]['kode_barang'] : 'ANT-001';
$example_kode_lokasi = !empty($lokasi_list) ? (string)$lokasi_list[0]['kode_lokasi'] : '1';

while (ob_get_level() > 0) {
    ob_end_clean();
}

header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename="template_mapping_items.xls"');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
header('X-Content-Type-Options: nosniff');

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
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

    <Worksheet ss:Name="Template Import">
        <Table ss:DefaultColumnWidth="140">
            <Column ss:Width="140"/>
            <Column ss:Width="140"/>
            <Column ss:Width="120"/>

            <Row ss:Height="30" ss:StyleID="HeaderRed">
                <Cell><Data ss:Type="String">Kode Barang*</Data></Cell>
                <Cell><Data ss:Type="String">Kode Lokasi*</Data></Cell>
                <Cell><Data ss:Type="String">Aktif (1/0)</Data></Cell>
            </Row>

            <Row>
                <Cell><Data ss:Type="String"><?= xml_escape($example_kode_barang) ?></Data></Cell>
                <Cell><Data ss:Type="String"><?= xml_escape($example_kode_lokasi) ?></Data></Cell>
                <Cell><Data ss:Type="Number">1</Data></Cell>
            </Row>
        </Table>
    </Worksheet>

    <Worksheet ss:Name="Lokasi">
        <Table ss:DefaultColumnWidth="140">
            <Column ss:Width="60"/>
            <Column ss:Width="120"/>
            <Column ss:Width="220"/>

            <Row ss:Height="30" ss:StyleID="Header">
                <Cell><Data ss:Type="String">ID</Data></Cell>
                <Cell><Data ss:Type="String">Kode Lokasi</Data></Cell>
                <Cell><Data ss:Type="String">Nama Lokasi</Data></Cell>
            </Row>
            <?php foreach ($lokasi_list as $row): ?>
            <Row>
                <Cell><Data ss:Type="Number"><?= (int)$row['id'] ?></Data></Cell>
                <Cell><Data ss:Type="String"><?= xml_escape($row['kode_lokasi']) ?></Data></Cell>
                <Cell><Data ss:Type="String"><?= xml_escape($row['nama_lokasi']) ?></Data></Cell>
            </Row>
            <?php endforeach; ?>
        </Table>
    </Worksheet>

    <Worksheet ss:Name="Barang">
        <Table ss:DefaultColumnWidth="160">
            <Column ss:Width="60"/>
            <Column ss:Width="120"/>
            <Column ss:Width="260"/>

            <Row ss:Height="30" ss:StyleID="Header">
                <Cell><Data ss:Type="String">ID</Data></Cell>
                <Cell><Data ss:Type="String">Kode Barang</Data></Cell>
                <Cell><Data ss:Type="String">Nama Barang</Data></Cell>
            </Row>
            <?php foreach ($barang_list as $row): ?>
            <Row>
                <Cell><Data ss:Type="Number"><?= (int)$row['id'] ?></Data></Cell>
                <Cell><Data ss:Type="String"><?= xml_escape($row['kode_barang']) ?></Data></Cell>
                <Cell><Data ss:Type="String"><?= xml_escape($row['nama_barang']) ?></Data></Cell>
            </Row>
            <?php endforeach; ?>
        </Table>
    </Worksheet>

    <Worksheet ss:Name="Petunjuk">
        <Table ss:DefaultColumnWidth="450">
            <Row ss:StyleID="Header">
                <Cell><Data ss:Type="String">PENTING! Petunjuk Pengisian Template</Data></Cell>
            </Row>
            <Row><Cell><Data ss:Type="String">1. Kolom yang bertanda * wajib diisi</Data></Cell></Row>
            <Row><Cell><Data ss:Type="String">2. Gunakan Kode Barang dari sheet "Barang"</Data></Cell></Row>
            <Row><Cell><Data ss:Type="String">3. Gunakan Kode Lokasi dari sheet "Lokasi"</Data></Cell></Row>
            <Row><Cell><Data ss:Type="String">4. Kolom Aktif opsional: isi 1 untuk aktif, 0 untuk nonaktif</Data></Cell></Row>
            <Row><Cell><Data ss:Type="String">5. Simpan file dalam format .xls atau .xlsx</Data></Cell></Row>
        </Table>
    </Worksheet>
</Workbook>
<?php exit(); ?>
