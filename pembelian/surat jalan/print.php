<?php
include_once __DIR__ . "/../../config.php";
include_once __DIR__ . "/../../includes/access_check.php";

$surat_jalan_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$surat_jalan_data = null;
$surat_jalan_items = [];

if ($surat_jalan_id > 0) {
    $sql = "SELECT sj.id, sj.surat_jalan_number, sj.surat_jalan_date, 
                   po.id as po_id, po.no_po as po_number, po.tanggal as po_date,
                   s.id as supplier_id, s.nama_supplier, 
                   s.alamat as supplier_address, sj.created_by
            FROM surat_jalan sj
            LEFT JOIN purchase_order po ON sj.po_id = po.id
            LEFT JOIN supplier s ON po.supplier_id = s.id
            WHERE sj.id = ?";
    
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("i", $surat_jalan_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $surat_jalan_data = $result->fetch_assoc();
        $stmt->close();
    }

    if ($surat_jalan_data) {
        $sql_items_po = "SELECT 
                            dpo.jumlah as quantity,
                            b.kode_barang,
                            b.nama_barang,
                            sa.nama_satuan
                         FROM detail_purchase_order dpo
                         JOIN barang b ON dpo.barang_id = b.id
                         LEFT JOIN conversi_po_detail cpd ON cpd.detail_purchase_order_id = dpo.id
                         LEFT JOIN satuan sa ON sa.id = COALESCE(cpd.satuan_asal_id, dpo.satuan_id, b.satuan_id)
                         WHERE dpo.purchase_order_id = ?";

        $stmt_items_po = $conn->prepare($sql_items_po);
        if ($stmt_items_po) {
            $stmt_items_po->bind_param("i", $surat_jalan_data['po_id']);
            $stmt_items_po->execute();
            $result_items_po = $stmt_items_po->get_result();
            while ($row = $result_items_po->fetch_assoc()) {
                $surat_jalan_items[] = $row;
            }
            $stmt_items_po->close();
        }
    }
}

if (!$surat_jalan_data) {
    die("Surat Jalan tidak ditemukan.");
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cetak Surat Jalan #<?= htmlspecialchars($surat_jalan_data['surat_jalan_number']) ?></title>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            margin: 0;
            padding: 20px;
            font-size: 12pt;
            color: #333;
        }
        .container {
            width: 21cm;
            min-height: 29.7cm;
            margin: 0 auto;
            padding: 1.5cm;
            box-sizing: border-box;
        }
        .letterhead {
            text-align: center;
            margin-bottom: 20px;
            position: relative;
        }
        .company-logo {
            height: 80px;
            margin-right: 20px;
        }
        .company-name {
            font-size: 18pt;
            font-weight: bold;
            margin-bottom: 5px;
            color: #2c3e50;
        }
        .company-address {
            font-size: 10pt;
            color: #555;
        }
        .document-title {
            font-size: 14pt;
            font-weight: bold;
            margin: 15px 0;
            text-transform: uppercase;
        }
        .divider {
            border-top: 3px solid #2c3e50;
            margin: 10px 0;
        }
        .document-info {
            margin-bottom: 20px;
        }
        .document-number {
            font-weight: bold;
            margin-bottom: 5px;
        }
        .document-details {
            display: flex;
            margin-bottom: 20px;
        }
        .left-column, .right-column {
            width: 50%;
            padding: 0 10px;
            box-sizing: border-box;
        }
        .detail-label {
            font-weight: bold;
            min-width: 100px;
            display: inline-block;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        .items-table th {
            background-color: #2c3e50;
            color: white;
            padding: 8px;
            text-align: left;
        }
        .items-table td {
            padding: 8px;
            border-bottom: 1px solid #ddd;
        }
        .items-table tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .signature-section {
            margin-top: 50px;
            text-align: right;
        }
        .signature-line {
            border-top: 1px solid #333;
            width: 200px;
            display: inline-block;
            margin-top: 50px;
        }
        @media print {
            body {
                padding: 0;
                background: none;
            }
            .container {
                width: 100%;
                height: 100%;
                margin: 0;
                padding: 1cm;
            }
        }
    </style>
</head>
<body>
<div class="container">
    <!-- Letterhead -->
    <div class="letterhead">
        <div style="display: flex; align-items: center; justify-content: center;">
            <img src="../../asset/cjawilnew.png" alt="Company Logo" class="company-logo">
            <div>
                <div class="company-name">Kopicjawil</div>
                <div class="company-address">
                    Jl. Sindang_Sari3. 11, Bandung - Telp. (081) 2xxxxxx
                </div>
            </div>
        </div>
        <div class="divider"></div>
        <div class="document-title">Surat Jalan</div>
        <div class="divider"></div>
    </div>

    <!-- Document Info -->
    <div class="document-info">
        <div class="document-number">
            No: <?= htmlspecialchars($surat_jalan_data['surat_jalan_number']) ?>
        </div>
        <div style="font-size: 10pt;">
            Tanggal: <?= date('d F Y', strtotime($surat_jalan_data['surat_jalan_date'])) ?>
        </div>
    </div>

    <!-- Document Details -->
    <div class="document-details">
        <div class="left-column">
            <p><span class="detail-label">Kepada Yth:</span><br>
            <?= htmlspecialchars($surat_jalan_data['nama_supplier']) ?><br>
            <?= htmlspecialchars($surat_jalan_data['supplier_address']) ?></p>
            
            <p><span class="detail-label">Nomor PO:</span> <?= htmlspecialchars($surat_jalan_data['po_number']) ?></p>
        </div>
        <div class="right-column">
            <p><span class="detail-label">Dibuat Oleh:</span> <?= htmlspecialchars($surat_jalan_data['created_by']) ?></p>
        </div>
    </div>

    <!-- Items Table -->
    <table class="items-table">
        <thead>
            <tr>
                <th width="5%">No</th>
                <th width="15%">Kode Barang</th>
                <th width="40%">Nama Barang</th>
                <th width="15%">Jumlah</th>
                <th width="15%">Satuan</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($surat_jalan_items as $i => $item): ?>
            <tr>
                <td><?= $i+1 ?></td>
                <td><?= htmlspecialchars($item['kode_barang'] ?? 'N/A') ?></td>
                <td><?= htmlspecialchars($item['nama_barang'] ?? 'N/A') ?></td>
                <td><?= number_format($item['quantity'] ?? 0, 0) ?></td>
                <td><?= htmlspecialchars($item['nama_satuan'] ?? '') ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Signature Section -->
    <div class="signature-section">
        <p>Hormat Kami,</p>
        <div class="signature-line"></div>
        <p>(<?= htmlspecialchars($surat_jalan_data['created_by']) ?>)</p>
    </div>
</div>

<script>
    window.onload = function() {
        window.print();
    };
</script>
</body>
</html>
