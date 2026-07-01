<?php
require_once '../config.php';
require_once '../libs/SimpleXLSXGen.php';

// Ambil parameter filter yang sama dengan halaman utama
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$gudang_id = isset($_GET['gudang_id']) ? $_GET['gudang_id'] : '';

// Query yang sama dengan halaman utama
$sql = "SELECT ..."; // (query yang sama)

// Eksekusi query
$stmt = $conn->prepare($sql);
$stmt->bind_param('ss'.($gudang_id ? 'i' : ''), $start_date, $end_date, ...($gudang_id ? [$gudang_id] : []));
$stmt->execute();
$result = $stmt->get_result();

// Siapkan data untuk Excel
$data = [];
$data[] = ['No', 'Tanggal Reset', 'Gudang', 'Kode Barang', 'Nama Barang', 'Stok Awal', 'Stok Terpakai', 'Stok Akhir', 'User'];

$no = 1;
while($row = $result->fetch_assoc()) {
    $data[] = [
        $no++,
        date('d/m/Y H:i', strtotime($row['tanggal_reset'])),
        $row['nama_gudang'],
        $row['kode_barang'],
        $row['nama_barang'],
        $row['stok_awal_sebelum'],
        $row['stok_terpakai_sebelum'],
        $row['stok_akhir_sebelum'],
        $row['user_reset']
    ];
}

// Generate Excel

$xlsx->downloadAs('Riwayat_Stok_' . date('Ymd') . '.xlsx');
?>