<?php
session_start();
require_once '../config.php';
require_once '../libs/fpdf.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$period = isset($_GET['period']) ? $_GET['period'] : 'daily';

// Query untuk mengambil data
$sql = "SELECT 
            p.id,
            p.tanggal,
            p.no_pengeluaran,
            p.total_item,
            p.keterangan,
            u.nama as created_by_name
        FROM pengeluaran p
        LEFT JOIN users u ON p.created_by = u.id
        WHERE p.tanggal BETWEEN ? AND ?
        ORDER BY p.tanggal DESC, p.id";

$stmt = $conn->prepare($sql);
$stmt->bind_param('ss', $start_date, $end_date);
$stmt->execute();
$result = $stmt->get_result();

class PDF extends FPDF {
    function Header() {
        $this->SetFont('Arial', 'B', 15);
        $this->Cell(0, 10, 'Laporan Pengeluaran', 0, 1, 'C');
        $this->SetFont('Arial', '', 12);
        $this->Cell(0, 10, 'Periode: ' . date('d/m/Y', strtotime($_GET['start_date'])) . ' - ' . date('d/m/Y', strtotime($_GET['end_date'])), 0, 1, 'C');
        $this->Ln(10);
        
        // Header Tabel
        $this->SetFont('Arial', 'B', 11);
        $this->Cell(10, 10, 'No', 1, 0, 'C');
        $this->Cell(30, 10, 'Tanggal', 1, 0, 'C');
        $this->Cell(40, 10, 'No Pengeluaran', 1, 0, 'C');
        $this->Cell(30, 10, 'Total Item', 1, 0, 'C');
        $this->Cell(50, 10, 'Keterangan', 1, 0, 'C');
        $this->Cell(30, 10, 'Dibuat Oleh', 1, 1, 'C');
    }
    
    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, 'Halaman ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }
}

$pdf = new PDF('P', 'mm', 'A4');
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetFont('Arial', '', 11);

$no = 1;
$total_item = 0;

while($row = $result->fetch_assoc()) {
    $pdf->Cell(10, 10, $no++, 1, 0, 'C');
    $pdf->Cell(30, 10, date('d/m/Y', strtotime($row['tanggal'])), 1, 0, 'C');
    $pdf->Cell(40, 10, $row['no_pengeluaran'], 1, 0, 'L');
    $pdf->Cell(30, 10, number_format($row['total_item']), 1, 0, 'R');
    $pdf->Cell(50, 10, $row['keterangan'], 1, 0, 'L');
    $pdf->Cell(30, 10, $row['created_by_name'], 1, 1, 'L');
    
    $total_item += $row['total_item'];
}

$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(80, 10, 'Total Item:', 1, 0, 'R');
$pdf->Cell(30, 10, number_format($total_item), 1, 0, 'R');
$pdf->Cell(80, 10, '', 1, 1, 'L');

$pdf->Output('Laporan_Pengeluaran_' . date('Ymd') . '.pdf', 'D');