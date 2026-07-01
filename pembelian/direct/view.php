<?php
session_start();
require_once '../../config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit();
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    header("Location: index.php");
    exit();
}

// Fungsi untuk format Rupiah di PHP
function formatRupiah($angka) {
    $hasil_rupiah = "Rp " . number_format($angka, 2, ',', '.');
    return $hasil_rupiah;
}

// Query untuk mengambil data header pembelian
$sql = "SELECT dp.*, s.nama_supplier, u.nama as created_by_name
        FROM direct_purchase dp
        LEFT JOIN supplier s ON dp.supplier_id = s.id
        LEFT JOIN users u ON dp.created_by = u.id
        WHERE dp.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error'] = "Data Pembelian tidak ditemukan";
    header("Location: index.php");
    exit();
}

$purchase = $result->fetch_assoc();

$stock_transaction = null;
$stock_items = [];
$stok_column = null;
if (function_exists('db_has_column')) {
    $stok_column = db_has_column($conn, 'gudang_stok', 'stok_awal') ? 'stok_awal' : (db_has_column($conn, 'gudang_stok', 'jumlah') ? 'jumlah' : null);
}
$stok_keterangan = 'Dari pembelian mendadak: ' . ($purchase['no_transaksi'] ?? '');
if ($stok_keterangan !== 'Dari pembelian mendadak: ') {
    $stok_sql = "SELECT ts.id, ts.kode_transaksi, ts.tanggal, ts.gudang_id, g.nama_gudang
                FROM transaksi_stock ts
                LEFT JOIN gudang g ON ts.gudang_id = g.id
                WHERE ts.keterangan = ? OR ts.keterangan LIKE ?
                ORDER BY ts.id DESC
                LIMIT 1";
    $stok_stmt = $conn->prepare($stok_sql);
    if ($stok_stmt) {
        $like_keterangan = $stok_keterangan . '%';
        $stok_stmt->bind_param('ss', $stok_keterangan, $like_keterangan);
        $stok_stmt->execute();
        $stok_res = $stok_stmt->get_result();
        $stock_transaction = $stok_res ? $stok_res->fetch_assoc() : null;
        $stok_stmt->close();
    }
}

if ($stock_transaction && !empty($stock_transaction['id'])) {
    $select_stok = $stok_column ? "gs.`{$stok_column}` AS stok_sekarang" : "NULL AS stok_sekarang";
    $items_sql = "SELECT dts.barang_id, dts.jumlah AS jumlah_masuk, dts.keterangan, b.kode_barang, b.nama_barang, s.nama_satuan, {$select_stok}
                FROM detail_transaksi_stock dts
                JOIN barang b ON dts.barang_id = b.id
                LEFT JOIN satuan s ON b.satuan_id = s.id
                LEFT JOIN gudang_stok gs ON gs.gudang_id = ? AND gs.barang_id = dts.barang_id
                WHERE dts.transaksi_stock_id = ?
                ORDER BY b.kode_barang";
    $items_stmt = $conn->prepare($items_sql);
    if ($items_stmt) {
        $items_stmt->bind_param('ii', $stock_transaction['gudang_id'], $stock_transaction['id']);
        $items_stmt->execute();
        $items_res = $items_stmt->get_result();
        if ($items_res) {
            while ($r = $items_res->fetch_assoc()) {
                $stock_items[] = $r;
            }
        }
        $items_stmt->close();
    }
}

// Query untuk mengambil detail pembelian
$sql = "SELECT ddp.*, b.kode_barang, 
    CASE 
        WHEN ddp.barang_id IS NULL OR b.nama_barang IS NULL OR b.nama_barang = '' THEN ddp.keterangan 
        ELSE b.nama_barang 
    END as nama_barang, 
    s.nama_satuan,
    ddp.harga_satuan as harga_satuan,
    (ddp.jumlah * ddp.harga_satuan) as subtotal,
    CASE 
        WHEN ddp.harga_satuan >= 1000000 THEN CONCAT('Rp ', FORMAT(ddp.harga_satuan, 2))
        ELSE CONCAT('Rp ', FORMAT(ddp.harga_satuan, 2))
    END as formatted_harga_satuan,
    CASE 
        WHEN (ddp.jumlah * ddp.harga_satuan) >= 1000000 THEN CONCAT('Rp ', FORMAT((ddp.jumlah * ddp.harga_satuan), 2))
        ELSE CONCAT('Rp ', FORMAT((ddp.jumlah * ddp.harga_satuan), 2))
    END as formatted_subtotal
    FROM detail_direct_purchase ddp
    LEFT JOIN barang b ON ddp.barang_id = b.id
    LEFT JOIN satuan s ON b.satuan_id = s.id
    WHERE ddp.direct_purchase_id = ?
    ORDER BY b.kode_barang";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $id);
$stmt->execute();
$detail_result = $stmt->get_result();

if ($detail_result->num_rows === 0) {
    $_SESSION['warning'] = "Tidak ada item ditemukan untuk pembelian ini";
}

$purchase_photos = [];
$foto_sql = "SELECT foto FROM detail_direct_purchase WHERE direct_purchase_id = ? AND foto IS NOT NULL AND foto <> ''";
$foto_stmt = $conn->prepare($foto_sql);
if ($foto_stmt) {
    $foto_stmt->bind_param('i', $id);
    if ($foto_stmt->execute()) {
        $foto_result = $foto_stmt->get_result();
        if ($foto_result) {
            $purchase_photos_map = [];
            while ($foto_row = $foto_result->fetch_assoc()) {
                $foto_raw = isset($foto_row['foto']) ? (string)$foto_row['foto'] : '';
                $foto_raw = str_replace('\\', '/', $foto_raw);
                $foto_file = $foto_raw !== '' ? basename($foto_raw) : '';
                if ($foto_file !== '') {
                    $purchase_photos_map[$foto_file] = true;
                }
            }
            $purchase_photos = array_keys($purchase_photos_map);
        }
    }
    $foto_stmt->close();
}

$total_item = 0;
$grand_total = 0;
$no = 1;
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Pembelian Mendadak - Sistem Inventory</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.0.7/css/boxicons.min.css" rel="stylesheet">
    <!-- Tambahkan library jsPDF -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <style>
        body {
            background-color: #f8f9fa;
        }
        .card {
            border: none;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            margin-bottom: 1.5rem;
        }
        .card-header {
            background-color: #fff;
            border-bottom: 2px solid #e9ecef;
            padding: 1rem;
        }
        .table th {
            background-color: #212529;
            color: white;
        }
        .badge {
            padding: 0.5em 1em;
        }
        .company-logo {
            max-height: 80px;
            margin-bottom: 1rem;
        }
        @media print {
            .no-print {
                display: none !important;
            }
            body {
                padding: 0;
                background-color: #fff;
            }
            .card {
                box-shadow: none;
            }
            .table th {
                background-color: #212529 !important;
                color: white !important;
            }
            .print-header {
                text-align: center;
                margin-bottom: 2rem;
            }
            .print-footer {
                margin-top: 2rem;
                text-align: right;
            }
        }
    </style>
</head>
<body>
    <div class="container py-4">
        <!-- Header Section -->
        <!-- Ubah bagian tombol di header -->
        <div class="d-flex justify-content-between align-items-center mb-4 no-print">
            <div class="d-flex align-items-center">
                <img src="../../asset/cjawilnew.png" alt="Logo" class="me-3" style="height: 30px;">
                <h2 class="mb-0">Detail Pembelian Mendadak</h2>
            </div>
            <div class="btn-group">
                <a href="index.php" class="btn btn-outline-secondary">
                    <i class='bx bx-arrow-back'></i> Kembali
                </a>
                <button onclick="window.print()" class="btn btn-outline-primary">
                    <i class='bx bx-printer'></i> Print
                </button>
                <?php if($purchase['status'] == 'menunggu'): ?>
                <a href="edit.php?id=<?= $id ?>" class="btn btn-outline-warning">
                    <i class='bx bx-edit'></i> Edit
                </a>
                <?php endif; ?>
            </div>
        </div>
        <!-- Print Header -->
        <div class="print-header d-none d-print-block text-center">
            <img src="../../asset/cjawilnew.png" alt="Logo Perusahaan" class="company-logo" style="height: 50px;">
            <h1 class="h3 mb-3">DETAIL PEMBELIAN MENDADAK</h1>
        </div>
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Informasi Pembelian</h5>
            </div>
            <div class="card-body">
                <div class="row g-4">
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <td width="40%" class="text-muted">No Transaksi</td>
                                <td width="5%">:</td>
                                <td><?= htmlspecialchars($purchase['no_transaksi']) ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Tanggal</td>
                                <td>:</td>
                                <td><?= date('d/m/Y', strtotime($purchase['tanggal'])) ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Nama Toko</td>
                                <td>:</td>
                                <td><?= htmlspecialchars($purchase['nama_toko']) ?></td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <td width="40%" class="text-muted">Status</td>
                                <td width="5%">:</td>
                                <td>
                                    <?php
                                        $badge_class = 'bg-secondary';
                                        $status_text = ucfirst((string)($purchase['status'] ?? ''));
                                        switch ($purchase['status'] ?? '') {
                                            case 'menunggu':
                                                $badge_class = 'bg-warning';
                                                $status_text = 'Menunggu';
                                                break;
                                            case 'payment':
                                                $badge_class = 'bg-primary';
                                                $status_text = 'Payment';
                                                break;
                                            case 'stok_masuk':
                                                $badge_class = 'bg-info';
                                                $status_text = 'Stok Masuk';
                                                break;
                                            case 'selesai':
                                                $badge_class = 'bg-success';
                                                $status_text = 'Selesai';
                                                break;
                                            case 'batal':
                                                $badge_class = 'bg-danger';
                                                $status_text = 'Batal';
                                                break;
                                        }
                                    ?>
                                    <span class="badge <?= $badge_class ?>">
                                        <?= htmlspecialchars($status_text) ?>
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <td class="text-muted">Dibuat Oleh</td>
                                <td>:</td>
                                <td><?= htmlspecialchars($purchase['created_by_name']) ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Keterangan</td>
                                <td>:</td>
                                <td><?= nl2br(htmlspecialchars($purchase['keterangan'])) ?></td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-12">
                        <div class="text-muted mb-2">Foto</div>
                        <div class="d-flex flex-wrap gap-2">
                            <?php
                                $hasAnyFoto = false;
                                foreach ($purchase_photos as $fotoFile):
                                    $fotoFsPath = __DIR__ . '/../../uploads/pembelian/' . $fotoFile;
                                    $fotoUrl = '../../uploads/pembelian/' . rawurlencode($fotoFile);
                                    if (!$fotoFile || !file_exists($fotoFsPath)) continue;
                                    $hasAnyFoto = true;
                            ?>
                                <a href="<?= htmlspecialchars($fotoUrl) ?>" target="_blank" rel="noopener">
                                    <img src="<?= htmlspecialchars($fotoUrl) ?>" alt="Foto Pembelian <?= htmlspecialchars($purchase['no_transaksi']) ?>" style="width:72px; height:72px; object-fit:cover; border-radius:8px; background:#fff; border:1px solid #e9ecef;">
                                </a>
                            <?php endforeach; ?>
                            <?php if (!$hasAnyFoto): ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php if ($stock_transaction): ?>
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Stok Dikirim ke Gudang</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <table class="table table-borderless mb-0">
                            <tr>
                                <td width="40%" class="text-muted">Gudang</td>
                                <td width="5%">:</td>
                                <td><?= htmlspecialchars($stock_transaction['nama_gudang'] ?? '-') ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Kode Transaksi</td>
                                <td>:</td>
                                <td><?= htmlspecialchars($stock_transaction['kode_transaksi'] ?? '-') ?></td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-borderless mb-0">
                            <tr>
                                <td width="40%" class="text-muted">Tanggal</td>
                                <td width="5%">:</td>
                                <td>
                                    <?php
                                        $ts = !empty($stock_transaction['tanggal']) ? strtotime($stock_transaction['tanggal']) : 0;
                                        echo $ts ? date('d/m/Y', $ts) : '-';
                                    ?>
                                </td>
                            </tr>
                            <tr>
                                <td class="text-muted">Status</td>
                                <td>:</td>
                                <td><span class="badge bg-info">Stok Masuk</span></td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-12">
                        <div class="table-responsive">
                            <table class="table table-hover table-striped mb-0">
                                <thead>
                                    <tr>
                                        <th width="12%">Kode</th>
                                        <th>Nama Barang</th>
                                        <th class="text-end" width="12%">Masuk</th>
                                        <th width="12%">Satuan</th>
                                        <th class="text-end" width="16%">Stok Gudang</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($stock_items) > 0): ?>
                                        <?php foreach ($stock_items as $si): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($si['kode_barang'] ?? '') ?></td>
                                                <td><?= htmlspecialchars($si['nama_barang'] ?? '') ?></td>
                                                <td class="text-end"><?= isset($si['jumlah_masuk']) ? number_format((float)$si['jumlah_masuk'], 3, ',', '.') : '-' ?></td>
                                                <td><?= htmlspecialchars($si['nama_satuan'] ?? '-') ?></td>
                                                <td class="text-end"><?= isset($si['stok_sekarang']) ? number_format((float)$si['stok_sekarang'], 3, ',', '.') : '-' ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5" class="text-center py-3 text-muted">Tidak ada data stok yang ditemukan</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        <!-- Items Table Card -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Daftar Barang</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-striped mb-0">
                        <thead>
                            <tr>
                                <th class="text-center" width="5%">No</th>
                                <th width="10%">Kode</th>
                                <th width="10%">Nama Barang</th>
                                <th class="text-end" width="10%">Jumlah</th>
                                <th width="10%">Satuan</th>
                                <th class="text-end" width="15%">Harga Satuan</th>
                                <th class="text-end" width="15%">Total</th>
                                <th width="10%">Keterangan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($detail_result->num_rows > 0): ?>
                                <?php while($detail = $detail_result->fetch_assoc()): 
                                    $subtotal = $detail['jumlah'] * $detail['harga_satuan'];
                                    $total_item += $detail['jumlah'];
                                    $grand_total += $subtotal;
                                ?>
                                <tr>
                                    <td class="text-center"><?= $no++ ?></td>
                                    <td><?= htmlspecialchars($detail['kode_barang']) ?></td>
                                    <td><?= htmlspecialchars($detail['nama_barang']) ?></td>
                                    <td class="text-end"><?= number_format($detail['jumlah']) ?></td>
                                    <td><?= htmlspecialchars($detail['nama_satuan']) ?></td>
                                    <td class="text-end"><?= formatRupiah($detail['harga_satuan']) ?></td>
                                    <td class="text-end"><?= formatRupiah($detail['subtotal']) ?></td>
                                    <td><?= htmlspecialchars($detail['keterangan']) ?></td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="text-center py-4">Tidak ada item ditemukan</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                        <tfoot class="table-group-divider">
                            <tr class="fw-bold">
                                <td colspan="3" class="text-end">Total Item:</td>
                                <td class="text-end"><?= number_format($total_item) ?></td>
                                <td colspan="2" class="text-end">Grand Total:</td>
                                <td class="text-end"><?= formatRupiah($grand_total) ?></td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
        <!-- Print Footer -->
        <div class="d-none d-print-block mt-4">
            <div class="print-footer">
                <p class="mb-0">
                    <?= date('d/m/Y') ?><br>
                    Mengetahui,<br><br><br>
                    (_______________)
                </p>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Tambahkan scritp untuk generate PDF di bagian bawah sebelum penutup body -->
    <script>
        // Fungsi untuk generate PDF
        function generatePDF() {
            // Tampilkan pesan loading
            const loadingMessage = document.createElement('div');
            loadingMessage.innerHTML = '<div class="position-fixed top-0 start-0 w-100 h-100 d-flex justify-content-center align-items-center bg-white bg-opacity-75" style="z-index: 9999;"><div class="spinner-border text-primary" role="status"></div><span class="ms-2">Generating PDF...</span></div>';
            document.body.appendChild(loadingMessage);
            
            // Sembunyikan elemen yang tidak perlu di PDF
            const noPrintElements = document.querySelectorAll('.no-print');
            noPrintElements.forEach(el => {
                el.style.display = 'none';
            });
            
            // Tampilkan elemen yang hanya untuk print
            const printElements = document.querySelectorAll('.d-none.d-print-block');
            printElements.forEach(el => {
                el.classList.remove('d-none');
                el.classList.remove('d-print-block');
            });
            
            // Ambil elemen yang akan dijadikan PDF
            const element = document.querySelector('.container');
            
            // Konfigurasi html2canvas
            const options = {
                scale: 2,
                useCORS: true,
                logging: false
            };
            
            // Gunakan html2canvas untuk mengambil gambar dari elemen
            window.html2canvas(element, options).then(canvas => {
                // Kembalikan tampilan elemen yang disembunyikan
                noPrintElements.forEach(el => {
                    el.style.display = '';
                });
                
                // Kembalikan elemen print ke kondisi semula
                printElements.forEach(el => {
                    el.classList.add('d-none');
                    el.classList.add('d-print-block');
                });
                
                // Buat instance jsPDF
                const { jsPDF } = window.jspdf;
                const pdf = new jsPDF('p', 'mm', 'a4');
                
                // Konversi canvas ke gambar
                const imgData = canvas.toDataURL('image/png');
                
                // Hitung rasio untuk menyesuaikan dengan ukuran A4
                const pdfWidth = pdf.internal.pageSize.getWidth();
                const pdfHeight = pdf.internal.pageSize.getHeight();
                const canvasRatio = canvas.height / canvas.width;
                const imgWidth = pdfWidth;
                const imgHeight = pdfWidth * canvasRatio;
                
                // Jika gambar terlalu tinggi, bagi menjadi beberapa halaman
                let heightLeft = imgHeight;
                let position = 0;
                let pageNumber = 1;
                
                // Tambahkan halaman pertama
                pdf.addImage(imgData, 'PNG', 0, position, imgWidth, imgHeight);
                heightLeft -= pdfHeight;
                
                // Tambahkan halaman tambahan jika diperlukan
                while (heightLeft > 0) {
                    position = -pdfHeight * pageNumber;
                    pageNumber++;
                    pdf.addPage();
                    pdf.addImage(imgData, 'PNG', 0, position, imgWidth, imgHeight);
                    heightLeft -= pdfHeight;
                }
                
                // Simpan PDF
                const fileName = 'Pembelian_Mendadak_<?= htmlspecialchars($purchase["no_transaksi"]) ?>_<?= date("Ymd") ?>.pdf';
                pdf.save(fileName);
                
                // Hapus pesan loading
                document.body.removeChild(loadingMessage);
            });
        }
    </script>
</body>
</html>
