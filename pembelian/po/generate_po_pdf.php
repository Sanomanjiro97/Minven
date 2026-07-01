<?php
session_start();
require_once '../../config.php';
require_once '../../libs/fpdf/fpdf.php';

if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid PO ID']);
    exit();
}

$po_id = (int)$_GET['id'];

$sql = "SELECT po.*, s.nama_supplier, s.alamat as alamat_supplier, s.telepon as telepon_supplier, u.nama as created_by_name
        FROM purchase_order po
        LEFT JOIN supplier s ON po.supplier_id = s.id
        LEFT JOIN users u ON po.created_by = u.id
        WHERE po.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $po_id);
$stmt->execute();
$po = $stmt->get_result()->fetch_assoc();

if (!$po) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'PO not found']);
    exit();
}

$sql_items = "SELECT dpo.*, b.kode_barang, b.nama_barang, COALESCE(s_konv.nama_satuan, s.nama_satuan) as nama_satuan
              FROM detail_purchase_order dpo
              LEFT JOIN barang b ON dpo.barang_id = b.id
              LEFT JOIN satuan s ON b.satuan_id = s.id
              LEFT JOIN conversi_po_detail cpd ON dpo.id = cpd.detail_purchase_order_id
              LEFT JOIN satuan s_konv ON cpd.satuan_asal_id = s_konv.id
              WHERE dpo.purchase_order_id = ? AND (dpo.status IS NULL OR dpo.status != 'rejected')
              ORDER BY b.kode_barang";
$stmt_items = $conn->prepare($sql_items);
$stmt_items->bind_param('i', $po_id);
$stmt_items->execute();
$items = $stmt_items->get_result()->fetch_all(MYSQLI_ASSOC);

$pdf = new FPDF('P', 'mm', 'A4');
$pdf->AddPage();
$pdf->SetAutoPageBreak(true, 10);

$logoPath = __DIR__ . '/../../asset/cjawilnew.png';
if (file_exists($logoPath)) {
    $pdf->Image($logoPath, 10, 10, 30);
}
$pdf->SetFont('Arial', 'B', 16);
$pdf->Cell(0, 8, 'PURCHASE ORDER', 0, 1, 'C');
$pdf->SetFont('Arial', '', 10);
$pdf->Ln(5);

$pdf->SetFont('Arial', '', 10);
$pdf->Cell(0, 6, 'No PO: ' . $po['no_po'], 0, 1);
$pdf->Cell(0, 6, 'Tanggal: ' . date('d/m/Y', strtotime($po['tanggal'])), 0, 1);
$pdf->Ln(3);

$pdf->SetFillColor(220, 220, 220);
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(0, 7, 'SUPPLIER', 1, 1, 'L', true);
$pdf->SetFont('Arial', '', 9);
$pdf->Cell(0, 6, 'Nama: ' . $po['nama_supplier'], 0, 1);
$pdf->Cell(0, 6, 'Alamat: ' . $po['alamat_supplier'], 0, 1);
$pdf->Cell(0, 6, 'Telepon: ' . $po['telepon_supplier'], 0, 1);
$pdf->Ln(3);

$pdf->SetFont('Arial', 'B', 9);
$pdf->SetFillColor(50, 50, 50);
$pdf->SetTextColor(255, 255, 255);
$pdf->Cell(8, 8, 'No', 1, 0, 'C', true);
$pdf->Cell(30, 8, 'Kode', 1, 0, 'C', true);
$pdf->Cell(65, 8, 'Nama Barang', 1, 0, 'C', true);
$pdf->Cell(20, 8, 'Jumlah', 1, 0, 'C', true);
$pdf->Cell(25, 8, 'Satuan', 1, 0, 'C', true);
$pdf->Cell(42, 8, 'Harga Satuan', 1, 1, 'C', true);

$pdf->SetTextColor(0, 0, 0);
$pdf->SetFont('Arial', '', 8);
$no = 1;
$total = 0;
foreach ($items as $item) {
    $subtotal = $item['jumlah'] * $item['harga_satuan'];
    $total += $subtotal;
    
    $pdf->Cell(8, 7, $no++, 1, 0, 'C');
    $pdf->Cell(30, 7, $item['kode_barang'], 1, 0, 'C');
    $pdf->Cell(65, 7, substr($item['nama_barang'], 0, 40), 1, 0, 'L');
    $pdf->Cell(20, 7, number_format($item['jumlah']), 1, 0, 'R');
    $pdf->Cell(25, 7, $item['nama_satuan'], 1, 0, 'C');
    $pdf->Cell(42, 7, 'Rp ' . number_format($item['harga_satuan'], 0, ',', '.'), 1, 1, 'R');
    
    if ($pdf->GetY() > 270) {
        $pdf->AddPage();
    }
}

$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(148, 8, 'GRAND TOTAL', 1, 0, 'R', true);
$pdf->Cell(42, 8, 'Rp ' . number_format($total, 0, ',', '.'), 1, 1, 'R', true);

$pdf->Ln(5);
$pdf->SetFont('Arial', '', 9);
$pdf->Cell(0, 5, 'Keterangan: ' . ($po['keterangan'] ?? '-'), 0, 1);
$pdf->Ln(10);

$pdf->SetFont('Arial', '', 9);
$pdf->Cell(95, 5, 'Tanggal: ' . date('d/m/Y'), 0, 0);
$pdf->Cell(95, 5, 'Mengetahui,', 0, 1);
$pdf->Ln(15);
$pdf->Cell(95, 5, '(___________________)', 0, 0);
$pdf->Cell(95, 5, '(___________________)', 0, 1);

$pdf->SetFont('Arial', 'I', 8);
$pdf->Text(10, 285, 'Dicetak dari sistem pada ' . date('d/m/Y H:i:s'));

$uploadDir = __DIR__ . '/../../uploads/po/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$filename = 'PO_' . $po['no_po'] . '_' . date('YmdHis') . '.pdf';
$filepath = $uploadDir . $filename;

$pdf->Output('F', $filepath);

$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$scriptPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$basePath = rtrim(dirname($scriptPath), '/');
$timestamp = time();
$signature = hash_hmac('sha256', $filename . $timestamp, $DOWNLOAD_SECRET);
$pdfUrl = $protocol . '://' . $host . $basePath . '/download_pdf.php?file=' . rawurlencode($filename) . '&t=' . $timestamp . '&s=' . $signature;

header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'filename' => $filename,
    'filepath' => $filepath,
    'url' => $pdfUrl,
    'no_po' => $po['no_po'],
    'supplier' => $po['nama_supplier'],
    'telepon' => $po['telepon_supplier'],
    'total' => $total
]);
exit();
?>