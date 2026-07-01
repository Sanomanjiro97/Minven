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

// Query untuk mengambil data header PO
$sql = "SELECT 
            po.*, 
            s.nama_supplier, 
            s.alamat as alamat_supplier,
            s.telepon as telepon_supplier,
            u.nama as created_by_name
        FROM purchase_order po
        LEFT JOIN supplier s ON po.supplier_id = s.id
        LEFT JOIN users u ON po.created_by = u.id
        WHERE po.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $id);
$stmt->execute();
$result = $stmt->get_result();
$po = $result->fetch_assoc();

if (!$po) {
    $_SESSION['error'] = "Data Purchase Order tidak ditemukan";
    header("Location: index.php");
    exit();
}

// Query untuk mengambil detail PO
$sql = "SELECT 
            dpo.*, 
            b.kode_barang, 
            b.nama_barang, 
        b.gambar,
        COALESCE(s_konv.nama_satuan, s.nama_satuan) as nama_satuan,
        (dpo.jumlah * dpo.harga_satuan) as subtotal
    FROM detail_purchase_order dpo
    LEFT JOIN barang b ON dpo.barang_id = b.id
    LEFT JOIN satuan s ON b.satuan_id = s.id
    LEFT JOIN conversi_po_detail cpd ON dpo.id = cpd.detail_purchase_order_id
    LEFT JOIN satuan s_konv ON cpd.satuan_asal_id = s_konv.id
    WHERE dpo.purchase_order_id = ?
    ORDER BY b.kode_barang";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $id);
$stmt->execute();
$detail_result = $stmt->get_result();

if ($detail_result->num_rows === 0) {
    $_SESSION['warning'] = "Tidak ada item ditemukan untuk PO ini";
}

// Calculate grand total and items list early for WA button
$temp_total = 0;
$temp_items = [];
$temp_total_item = 0;
while($temp_row = $detail_result->fetch_assoc()) {
    if ($temp_row['status'] !== 'rejected') {
        $temp_total += ($temp_row['jumlah'] * $temp_row['harga_satuan']);
        $temp_items[] = $temp_row['nama_barang'];
        $temp_total_item += $temp_row['jumlah'];
    }
}
$detail_items_str = implode(', ', $temp_items);
$detail_result->data_seek(0); // Reset for later loop

$total_item = 0; // Initialize total_item here, before the HTML table
$grand_total_po = 0; // Initialize grand_total_po
$no = 1; // Initialize item counter

// Ambil pengaturan WhatsApp
$wa_config_res = $conn->query("SELECT * FROM setup_whatsapp WHERE id = 1");
$wa_config = $wa_config_res ? $wa_config_res->fetch_assoc() : null;
$wa_active = $wa_config && $wa_config['is_active'] == 1;
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Purchase Order - Sistem Inventory</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.0.7/css/boxicons.min.css" rel="stylesheet">
    <!-- Tambahkan library jsPDF dan html2canvas -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <style>
    @media print {
        .no-print, nav {
            display: none !important;
        }
        body {
            padding: 10mm;
            margin: 0;
            font-family: Arial, sans-serif;
            font-size: 10pt;
        }
        .print-title {
            text-align: center;
            margin-bottom: 15px;
            font-size: 16pt;
            font-weight: bold;
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
        }
        .print-logo {
            height: 70px;
            margin: 0 auto 15px;
            display: block;
        }
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
            font-size: 9pt;
        }
        .table th {
            background-color: #333 !important;
            color: #fff !important;
            padding: 8px;
            text-align: center;
        }
        .table td {
            padding: 6px;
            border: 1px solid #ddd;
        }
        @page {
            size: A4 landscape;
            margin: 10mm;
        }
        .print-footer {
            margin-top: 30px;
            border-top: 2px solid #333;
            padding-top: 10px;
            text-align: right;
        }
    }
    </style>
</head>
<body>
    <?php include '../../templates/navbar.php'; ?>

    <div class="container mt-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
            <h2 class="mb-0">Detail Purchase Order</h2>
            <div>
                <a href="index.php" class="btn btn-outline-secondary">
                    <i class='bx bx-arrow-back'></i> Kembali
                </a>
                <button onclick="window.print()" class="btn btn-outline-primary ms-2">
                    <i class='bx bx-printer'></i> Print
                </button>
                <?php if($wa_active && $po['status'] == 'approved' && checkAccess('purchase_order', 'send_wa')): ?>
                <button type="button" class="btn btn-outline-success ms-2 btn-send-wa" 
                        data-no-po="<?= htmlspecialchars($po['no_po']) ?>"
                          data-supplier="<?= htmlspecialchars($po['nama_supplier']) ?>"
                          data-telepon="<?= htmlspecialchars($po['telepon_supplier']) ?>"
                          data-tanggal="<?= date('d/m/Y', strtotime($po['tanggal'])) ?>"
                          data-total="<?= formatRupiah($temp_total) ?>"
                          data-items="<?= htmlspecialchars($detail_items_str) ?>"
                          data-total-item="<?= number_format($temp_total_item) ?>"
                          title="Kirim WhatsApp">
                    <i class='bi bi-whatsapp'></i> WhatsApp
                </button>
                <?php endif; ?>
                <?php if($po['status'] == 'draft'): ?>
                <a href="edit.php?id=<?= $id ?>" class="btn btn-outline-warning ms-2">
                    <i class='bx bx-edit'></i> Edit
                </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Print Header (only visible when printing) -->
        <div class="d-none d-print-block text-center mb-4">
            <img src="../../asset/cjawilnew.png" alt="Logo" class="print-logo">
            <h1 class="print-title">DETAIL PURCHASE ORDER</h1>
        </div>

        <!-- PO Information Card -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-light">
                <h5 class="mb-0">Informasi Purchase Order</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <dl class="row mb-0">
                            <dt class="col-sm-4">No PO</dt>
                            <dd class="col-sm-8"><?= htmlspecialchars($po['no_po']) ?></dd>
                            
                            <dt class="col-sm-4">Tanggal</dt>
                            <dd class="col-sm-8"><?= date('d/m/Y', strtotime($po['tanggal'])) ?></dd>
                            
                            <dt class="col-sm-4">Supplier</dt>
                            <dd class="col-sm-8"><?= htmlspecialchars($po['nama_supplier']) ?></dd>
                            
                            <dt class="col-sm-4">Alamat</dt>
                            <dd class="col-sm-8"><?= htmlspecialchars($po['alamat_supplier']) ?></dd>
                            
                            <dt class="col-sm-4">Telepon</dt>
                            <dd class="col-sm-8"><?= htmlspecialchars($po['telepon_supplier']) ?></dd>

                            <dt class="col-sm-4">Foto</dt>
                            <dd class="col-sm-8">
                                <?php
                                    $poFotoRel = isset($po['foto']) ? (string)$po['foto'] : '';
                                    $poFotoRel = str_replace('\\', '/', $poFotoRel);
                                    $poFotoRel = ltrim($poFotoRel, '/');
                                    $poFotoOk = ($poFotoRel !== '' && strpos($poFotoRel, 'uploads/po/') === 0);
                                    $poFotoFile = $poFotoOk ? basename($poFotoRel) : '';
                                    $poFotoFsPath = $poFotoFile ? (__DIR__ . '/../../uploads/po/' . $poFotoFile) : '';
                                    $poFotoUrl = $poFotoFile ? ('../../uploads/po/' . rawurlencode($poFotoFile)) : '';
                                ?>
                                <?php if ($poFotoFile && file_exists($poFotoFsPath)): ?>
                                    <a href="<?= htmlspecialchars($poFotoUrl) ?>" target="_blank" rel="noopener">
                                        <img src="<?= htmlspecialchars($poFotoUrl) ?>" alt="Foto PO <?= htmlspecialchars($po['no_po']) ?>" style="max-width:160px; max-height:160px; width:auto; height:auto; object-fit:cover; border-radius:8px; background:#fff; border:1px solid #e9ecef;">
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </dd>
                        </dl>
                    </div>
                    <div class="col-md-6">
                        <dl class="row mb-0">
                            <dt class="col-sm-4">Status</dt>
                            <dd class="col-sm-8">
                                <?php
                                    $statusClass = 'bg-warning';
                                    $statusLabel = ucfirst((string)$po['status']);
                                    switch ($po['status']) {
                                        case 'draft':
                                            $statusClass = 'bg-warning';
                                            $statusLabel = 'Menunggu';
                                            break;
                                        case 'approved':
                                            $statusClass = 'bg-success';
                                            $statusLabel = 'Approved';
                                            break;
                                        case 'delivered':
                                            $statusClass = 'bg-info';
                                            $statusLabel = 'Delivery';
                                            break;
                                        case 'completed':
                                            $statusClass = 'bg-primary';
                                            $statusLabel = 'Selesai';
                                            break;
                                        case 'dikirim':
                                            $statusClass = 'bg-dark';
                                            $statusLabel = 'Dikirim';
                                            break;
                                        case 'rejected':
                                            $statusClass = 'bg-danger';
                                            $statusLabel = 'Rejected';
                                            break;
                                    }
                                ?>
                                <span class="badge <?= $statusClass ?> text-white"><?= htmlspecialchars($statusLabel) ?></span>
                            </dd>
                            
                            <dt class="col-sm-4">Dibuat Oleh</dt>
                            <dd class="col-sm-8"><?= htmlspecialchars($po['created_by_name']) ?></dd>
                            
                            <dt class="col-sm-4">Keterangan</dt>
                            <dd class="col-sm-8"><?= nl2br(htmlspecialchars($po['keterangan'])) ?></dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>

        <!-- Items Table -->
        <div class="card shadow-sm">
            <div class="card-header bg-light">
                <h5 class="mb-0">Daftar Barang</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-dark">
                            <tr>
                                <th width="5%">No</th>
                                <th width="15%">Kode</th>
                                <th width="20%">Nama Barang</th>
                                <th width="10%">Foto</th>
                                <th width="10%" class="text-end">Jumlah</th>
                                <th width="10%">Satuan</th>
                                <th width="15%" class="text-end">Harga Satuan</th>
                                <th width="15%" class="text-end">Total</th>
                                <th width="20%">Keterangan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($detail_result->num_rows > 0): ?>
                                <?php while($detail = $detail_result->fetch_assoc()): ?>
                                <?php 
                                    // Only count items that are not rejected
                                    if ($detail['status'] !== 'rejected') {
                                        $total_item += $detail['jumlah'];
                                        $grand_total_po += $detail['subtotal'];
                                    }
                                ?>
                                <tr class="<?= $detail['status'] === 'rejected' ? 'table-danger text-decoration-line-through' : '' ?>">
                                    <td><?= $no++ ?></td>
                                    <td><?= htmlspecialchars($detail['kode_barang']) ?></td>
                                    <td><?= htmlspecialchars($detail['nama_barang']) ?></td>
                                    <td>
                                        <?php
                                            $gambarFile = !empty($detail['gambar']) ? basename($detail['gambar']) : '';
                                            $gambarFsPath = $gambarFile ? (__DIR__ . '/../../uploads/barang/' . $gambarFile) : '';
                                            $gambarUrl = $gambarFile ? ('../../uploads/barang/' . rawurlencode($gambarFile)) : '';
                                        ?>
                                        <?php if ($gambarFile && file_exists($gambarFsPath)): ?>
                                            <a href="<?= htmlspecialchars($gambarUrl) ?>" target="_blank" rel="noopener">
                                                <img src="<?= htmlspecialchars($gambarUrl) ?>" alt="Foto <?= htmlspecialchars($detail['nama_barang']) ?>" style="width:48px; height:48px; object-fit:cover; border-radius:6px; background:#fff; border:1px solid #e9ecef;">
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end"><?= number_format($detail['jumlah']) ?></td>
                                    <td><?= htmlspecialchars($detail['nama_satuan']) ?></td>
                                    <td class="text-end"><?= formatRupiah($detail['harga_satuan']) ?></td>
                                    <td class="text-end"><?= formatRupiah($detail['subtotal']) ?></td>
                                    <td title="<?= htmlspecialchars($detail['keterangan']) ?>">
                                        <?= strlen($detail['keterangan']) > 50 ? 
                                            htmlspecialchars(substr($detail['keterangan'], 0, 50)) . '...' : 
                                            htmlspecialchars($detail['keterangan']) ?>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="9" class="text-center py-4">Tidak ada item ditemukan</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                        <tfoot class="table-group-divider">
                            <tr>
                                <th colspan="4" class="text-end">Total Item (Non-Rejected):</th>
                                <th class="text-end"><?= number_format($total_item) ?></th>
                                <th colspan="2" class="text-end">Grand Total (Non-Rejected):</th>
                                <th class="text-end"><?= formatRupiah($grand_total_po) ?></th>
                                <th></th>
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


<style>
@media print {
    /* Reset and base styles */
    * {
        box-sizing: border-box;
    }
    body {
        padding: 5mm !important;
        margin: 0 !important;
        font-family: Arial, sans-serif;
        font-size: 8pt !important;
        line-height: 1.2;
    }
    
    /* Header section */
    .print-header {
        text-align: center;
        margin-bottom: 5px;
    }
    .print-logo {
        height: 50px;
        margin: 0 auto 3px;
    }
    .print-title {
        font-size: 12pt;
        font-weight: bold;
        margin-bottom: 3px;
        text-transform: uppercase;
        border-bottom: 1px solid #333;
        padding-bottom: 3px;
    }
    
    /* Table styles */
    .table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 5px;
        font-size: 7pt;
    }
    .table th {
        background-color: #333 !important;
        color: #fff !important;
        border: 1px solid #ddd;
        padding: 2px 3px;
        text-align: center;
        font-weight: bold;
    }
    .table td {
        border: 1px solid #ddd;
        padding: 2px 3px;
    }
    .text-end {
        text-align: right;
    }
    
    /* Footer */
    .print-footer {
        margin-top: 10px;
        text-align: right;
        font-size: 7pt;
        border-top: 1px solid #333;
        padding-top: 3px;
    }
    
    /* Utility classes */
    .no-print, nav {
        display: none !important;
    }
    @page {
        size: A4 landscape;
        margin: 3mm;
    }
}
</style>
</body>
</html>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
const WA_CONFIG = <?= json_encode($wa_config) ?>;

document.querySelectorAll('.btn-send-wa').forEach(button => {
    button.addEventListener('click', function() {
        const telepon = this.dataset.telepon || '';
        
        if (!telepon) {
            Swal.fire('Error', 'Nomor telepon supplier tidak ditemukan', 'error');
            return;
        }

        // Bersihkan nomor telepon dari karakter non-digit
        let cleanPhone = telepon.replace(/\D/g, '');
        // Jika diawali dengan 0, ganti dengan 62
        if (cleanPhone.startsWith('0')) {
            cleanPhone = '62' + cleanPhone.substring(1);
        }

        let message = (WA_CONFIG && WA_CONFIG.template_message) || '';
        message = message.replace('{no_po}', this.dataset.noPo);
        message = message.replace('{supplier}', this.dataset.supplier);
         message = message.replace('{tanggal}', this.dataset.tanggal);
         message = message.replace('{total}', this.dataset.total);
         message = message.replace('{items}', this.dataset.items);
         message = message.replace('{total_item}', this.dataset.totalItem);

        const waUrl = `https://wa.me/${cleanPhone}?text=${encodeURIComponent(message)}`;
        window.open(waUrl, '_blank');
    });
});

function generatePDF() {
    const { jsPDF } = window.jspdf;
    const element = document.querySelector('.card.shadow-sm'); // Target the items card
    
    // Elements manipulation
    const originalStyles = {
        cardHeader: document.querySelector('.card-header').style.display,
        printFooter: document.querySelector('.print-footer').style.display
    };

    // Show all necessary elements
    document.querySelectorAll('.card-header, .print-footer').forEach(el => el.style.display = 'block');

    html2canvas(element, {
        scale: 2,
        useCORS: true,
        logging: true,
        backgroundColor: '#ffffff',
        onclone: (clonedDoc) => {
            // Ensure table visibility in cloned document
            clonedDoc.querySelector('.table-responsive').style.overflow = 'visible';
            clonedDoc.querySelector('.table').style.width = '100%';
        }
    }).then(canvas => {
        const imgData = canvas.toDataURL('image/png');
        const pdf = new jsPDF('l', 'mm', 'a4');
        
        const imgWidth = 280;
        const imgHeight = canvas.height * imgWidth / canvas.width;
        
        pdf.addImage(imgData, 'PNG', 10, 10, imgWidth, imgHeight);
        
        // Restore original styles
        document.querySelector('.card-header').style.display = originalStyles.cardHeader;
        document.querySelector('.print-footer').style.display = originalStyles.printFooter;

        pdf.save(`PO_${Date.now()}.pdf`);
    }).catch(error => {
        console.error('Error:', error);
        alert('Gagal membuat PDF: ' + error.message);
    });
}

</script>
