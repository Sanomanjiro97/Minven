<?php
session_start();
require_once '../../config.php';
require_once '../../includes/access_check.php';

// Check if user has access to view surat jalan
if (!hasAccess('surat_jalan')) {
    header('Location: ../../unauthorized.php');
    exit();
}

$surat_jalan_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($surat_jalan_id <= 0) {
    header('Location: index.php');
    exit();
}

// Fetch surat jalan data
$sql = "SELECT sj.*, p.no_po as po_number, s.nama_supplier, s.alamat as supplier_address, u.username as created_by
        FROM surat_jalan sj
        LEFT JOIN purchase_order p ON sj.po_id = p.id
        LEFT JOIN supplier s ON p.supplier_id = s.id
        LEFT JOIN users u ON CAST(sj.created_by AS UNSIGNED) = u.id
        WHERE sj.id = ?";

$stmt = $GLOBALS['conn']->prepare($sql);
$stmt->bind_param("i", $surat_jalan_id);
$stmt->execute();
$result = $stmt->get_result();
$surat_jalan_data = $result->fetch_assoc();

$surat_jalan_items = [];
if ($surat_jalan_data) {
    $po_items_sql = "SELECT 
                        dpo.barang_id,
                        dpo.jumlah as quantity,
                        COALESCE(cpd.satuan_asal_id, dpo.satuan_id, b.satuan_id, 78) as satuan_id,
                        b.kode_barang,
                        b.nama_barang,
                        s.nama_satuan
                     FROM detail_purchase_order dpo
                     LEFT JOIN barang b ON dpo.barang_id = b.id
                     LEFT JOIN conversi_po_detail cpd ON cpd.detail_purchase_order_id = dpo.id
                     LEFT JOIN satuan s ON s.id = COALESCE(cpd.satuan_asal_id, dpo.satuan_id, b.satuan_id)
                     WHERE dpo.purchase_order_id = ?";

    $po_items_stmt = $GLOBALS['conn']->prepare($po_items_sql);
    $po_items_stmt->bind_param("i", $surat_jalan_data['po_id']);
    $po_items_stmt->execute();
    $po_items_result = $po_items_stmt->get_result();

    while ($row = $po_items_result->fetch_assoc()) {
        $surat_jalan_items[] = $row;
    }

    if (!empty($surat_jalan_items)) {
        $check_items_sql = "SELECT COUNT(*) as count FROM surat_jalan_items WHERE surat_jalan_id = ?";
        $check_stmt = $GLOBALS['conn']->prepare($check_items_sql);
        $check_stmt->bind_param("i", $surat_jalan_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        $check_row = $check_result->fetch_assoc();

        if ($check_row['count'] == 0) {
            $insert_sql = "INSERT INTO surat_jalan_items (surat_jalan_id, barang_id, quantity, satuan_id) VALUES (?, ?, ?, ?)";
            $insert_stmt = $GLOBALS['conn']->prepare($insert_sql);

            foreach ($surat_jalan_items as $item) {
                $satuan_id = isset($item['satuan_id']) ? (int)$item['satuan_id'] : 78;
                $quantity = isset($item['quantity']) ? (int)$item['quantity'] : 0;

                $insert_stmt->bind_param("iiii", $surat_jalan_id, $item['barang_id'], $quantity, $satuan_id);
                $insert_stmt->execute();
            }
        }
    }
}

// Include header and navbar
include_once "../../templates/header.php";
include_once "../../templates/navbar.php";

// Handle record not found
if (!$surat_jalan_data) {
    ?>
    <div class="surat-jalan-container">
        <div class="alert alert-danger text-center">
            <h2><i class="fas fa-exclamation-triangle"></i> Data Tidak Ditemukan</h2>
            <div class="mt-3">
                <a href="index.php" class="btn btn-primary me-2">
                    <i class="fas fa-list"></i> Kembali ke Daftar
                </a>
                <?php if(checkAccess('surat_jalan_create')): ?>
                    <a href="create.php" class="btn btn-success">
                        <i class="fas fa-plus"></i> Buat Baru
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
    include_once "../../templates/footer.php";
    exit();
}
?>

<!-- Load custom CSS -->
<link rel="stylesheet" href="../../asset/css/surat-jalan.css">

<div class="surat-jalan-container">
    <!-- Notifications -->
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i>
            <?= htmlspecialchars($_SESSION['success_message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i>
            <?= htmlspecialchars($_SESSION['error_message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>
    
       <!-- Kop Surat -->
    <div class="text-center mb-4">
        <div class="d-flex align-items-center justify-content-center mb-3">
            <img src="../../asset/cjawilnew.png" alt="Company Logo" class="header-logo">
            <div class="text-start">
                <h1 class="company-header mb-1">Kopicjawil</h1>
                <p class="company-address mb-0">Jl. Sindang_Sari3. 11, Bandung - Telp. (081) 2xxxxxx</p>
            </div>
        </div>
        </div>
        <hr class="document-divider">
        <h2 class="document-title text-center">SURAT JALAN</h2>
        <hr class="document-divider">
    </div>

    <!-- Action buttons -->
    <div class="action-buttons">
        <a href="index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i>
            <span>Kembali</span>
        </a>
        <button type="button" class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#editPaymentModal">
            <i class="fas fa-edit"></i>
            <span>Edit Pembayaran</span>
        </button>
        <a href="print.php?id=<?= $surat_jalan_id ?>" class="btn btn-print text-white" target="_blank">
            <i class="fas fa-print"></i>
            <span>Cetak</span>
        </a>
    </div>

    <!-- Document number -->
    <div class="document-number">
        <span class="info-label">Nomor:</span>
        <span class="info-value"><?= htmlspecialchars($surat_jalan_data['surat_jalan_number']) ?></span>
    </div>

    <!-- Detail card with status pembayaran -->
    <div class="card info-card">
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <span class="info-label">Tanggal:</span>
                        <span class="info-value"><?= date('d/m/Y', strtotime($surat_jalan_data['surat_jalan_date'])) ?></span>
                    </div>
                    <div class="mb-3">
                        <span class="info-label">Dibuat Oleh:</span>
                        <span class="info-value"><?= isset($surat_jalan_data['created_by']) ? htmlspecialchars($surat_jalan_data['created_by']) : '-' ?></span>
                    </div>
                    <?php if (isset($surat_jalan_data['updated_at']) && $surat_jalan_data['updated_at'] != $surat_jalan_data['created_at']): ?>
                        <div class="mb-3">
                            <span class="info-label">Terakhir Diupdate:</span>
                            <span class="info-value text-muted">
                                <?= date('d/m/Y H:i', strtotime($surat_jalan_data['updated_at'])) ?>
                            </span>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <span class="info-label">Nomor PO:</span>
                        <span class="info-value"><?= htmlspecialchars($surat_jalan_data['po_number']) ?></span>
                    </div>
                    <div class="mb-3">
                        <span class="info-label">Supplier:</span>
                        <span class="info-value"><?= htmlspecialchars($surat_jalan_data['nama_supplier']) ?></span>
                    </div>
                    <div class="mb-3">
                        <span class="info-label">Alamat:</span>
                        <span class="info-value"><?= htmlspecialchars($surat_jalan_data['supplier_address']) ?></span>
                    </div>
                    <div class="mb-3">
                        <span class="info-label">Status Pembayaran:</span>
                        <?php 
                        $status_text = '';
                        $badge_class = '';
                        
                        switch($surat_jalan_data['status_pembayaran']) {
                            case 'belum_dibayar':
                                $status_text = 'Belum Dibayar';
                                $badge_class = 'bg-danger';
                                break;
                            case 'sebagian':
                                $status_text = 'Dibayar Sebagian';
                                $badge_class = 'bg-warning';
                                break;
                            case 'lunas':
                                $status_text = 'Lunas';
                                $badge_class = 'bg-success';
                                break;
                            default:
                                $status_text = 'Belum Dibayar';
                                $badge_class = 'bg-danger';
                        }
                        ?>
                        <span class="badge <?= $badge_class ?>"><?= $status_text ?></span>
                    </p>
                    <?php if (!empty($surat_jalan_data['keterangan_pembayaran'])): ?>
                        <p class="mb-0">
                            <span class="detail-label">Keterangan Pembayaran:</span>
                            <br>
                            <small class="text-muted">
                                <?= htmlspecialchars($surat_jalan_data['keterangan_pembayaran']) ?>
                            </small>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Daftar Barang -->
    <div class="card info-card">
        <div class="card-header">
            <h5 class="mb-0 d-flex align-items-center">
                <i class="fas fa-boxes me-2"></i>
                Daftar Barang <span class="badge bg-light text-primary ms-2"><?= count($surat_jalan_items) ?> item</span>
            </h5>
        </div>
        <div class="card-body p-0">
            <div class="table-container">
                <table class="table items-table mb-0">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Kode Barang</th>
                            <th>Nama Barang</th>
                            <th>Jumlah</th>
                            <th>Satuan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($surat_jalan_items)): ?>
                            <tr>
                                <td colspan="5" class="text-center py-4">
                                    <i class="fas fa-exclamation-circle me-2"></i>
                                    Tidak ada data barang
                                    <br>
                                    <small class="text-muted">PO ID: <?= $surat_jalan_data['po_id'] ?? 'N/A' ?></small>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach($surat_jalan_items as $i => $item): ?>
                                <tr>
                                    <td><?= $i+1 ?></td>
                                    <td>
                                        <strong><?= htmlspecialchars($item['kode_barang'] ?? 'N/A') ?></strong>
                                    </td>
                                    <td><?= htmlspecialchars($item['nama_barang'] ?? 'N/A') ?></td>
                                    <td>
                                        <strong><?= number_format($item['quantity'] ?? 0, 0, ',', '.') ?></strong>
                                    </td>
                                    <td><?= htmlspecialchars($item['nama_satuan'] ?? 'N/A') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Additional information -->
    <div class="card info-card">
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <span class="info-label">Catatan:</span>
                        <span class="info-value text-muted">Tidak ada catatan</span>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <span class="info-label">Status:</span>
                        <span class="info-value">
                        <?php 
                        $status_badge = '';
                        switch($surat_jalan_data['status']) {
                            case 'draft':
                                $status_badge = '<span class="badge bg-secondary">Draft</span>';
                                break;
                            case 'sent':
                                $status_badge = '<span class="badge bg-info">Terkirim</span>';
                                break;
                            case 'received':
                                $status_badge = '<span class="badge bg-success">Diterima</span>';
                                break;
                            default:
                                $status_badge = '<span class="badge bg-secondary">Draft</span>';
                        }
                        echo $status_badge;
                        ?>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Edit Pembayaran -->
<div class="modal fade" id="editPaymentModal" tabindex="-1" aria-labelledby="editPaymentModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editPaymentModalLabel">
                    <i class="fas fa-edit me-2"></i>Edit Status Pembayaran
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="editPaymentForm" action="update_payment.php" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="surat_jalan_id" value="<?= $surat_jalan_id ?>">
                    
                    <div class="mb-3">
                        <label for="status_pembayaran" class="form-label">
                            <strong>Status Pembayaran:</strong>
                        </label>
                        <select class="form-select" id="status_pembayaran" name="status_pembayaran" required
                                data-current-status="<?= $surat_jalan_data['status_pembayaran'] ?>">
                            <option value="">-- Pilih Status Pembayaran --</option>
                            <option value="belum_dibayar" <?= ($surat_jalan_data['status_pembayaran'] == 'belum_dibayar') ? 'selected' : '' ?>>
                                Belum Dibayar
                            </option>
                            <option value="sebagian" <?= ($surat_jalan_data['status_pembayaran'] == 'sebagian') ? 'selected' : '' ?>>
                                Dibayar Sebagian
                            </option>
                            <option value="lunas" <?= ($surat_jalan_data['status_pembayaran'] == 'lunas') ? 'selected' : '' ?>>
                                Lunas
                            </option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="keterangan_pembayaran" class="form-label">
                            <strong>Keterangan Pembayaran:</strong>
                        </label>
                        <textarea class="form-control" id="keterangan_pembayaran" name="keterangan_pembayaran" 
                                  rows="3" placeholder="Masukkan keterangan pembayaran (opsional)"><?= htmlspecialchars($surat_jalan_data['keterangan_pembayaran'] ?? '') ?></textarea>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Info:</strong> Jika status diubah menjadi "Lunas", status PO akan otomatis diubah menjadi "delivered".
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i> Batal
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i> Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Load custom JavaScript -->
<script src="../../asset/js/surat-jalan.js"></script>

<?php include_once "../../templates/footer.php"; ?>
