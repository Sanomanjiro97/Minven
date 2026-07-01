<?php
session_start();
require_once '../config.php';
require_once '../libs/SimpleXLSXGen.php';

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

// Persiapkan data untuk Excel
$header = [
    ['No', 'Tanggal', 'No Pengeluaran', 'Total Item', 'Keterangan', 'Dibuat Oleh']
];

$data = [];
$no = 1;
$total_item = 0;

while($row = $result->fetch_assoc()) {
    $data[] = [
        $no++,
        date('d/m/Y', strtotime($row['tanggal'])),
        $row['no_pengeluaran'],
        $row['total_item'],
        $row['keterangan'],
        $row['created_by_name']
    ];
    $total_item += $row['total_item'];
}

// Tambahkan total
$data[] = ['', '', 'Total Item:', $total_item, '', ''];

$xlsx = new SimpleXLSXGen();
$xlsx->writeSheet(array_merge($header, $data), 'Laporan Pengeluaran');
$xlsx->saveAs('Laporan_Pengeluaran_' . date('Ymd') . '.xlsx');