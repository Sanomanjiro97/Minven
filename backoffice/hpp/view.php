<?php
require_once __DIR__ . '/../_init.php';
bo_require_login();

$id = intval($_GET['id']);
$hpp = $boMainConn->query("SELECT h.*, u.nama as created_by_name FROM hpp h LEFT JOIN users u ON h.created_by = u.id WHERE h.id = $id")->fetch_assoc();
if (!$hpp) {
    header('Location: ' . bo_url_for('hpp/index.php'));
    exit;
}

$detail_bahan = $boMainConn->query("SELECT d.*, b.nama_barang, b.kode_barang FROM hpp_detail_bahan d LEFT JOIN barang b ON d.barang_id = b.id WHERE d.hpp_id = $id");
$detail_biaya = $boMainConn->query("SELECT * FROM hpp_detail_biaya WHERE hpp_id = $id");

$headerActions = '<a class="btn btn-warning me-2" href="' . htmlspecialchars(bo_url_for('hpp/edit.php?id=' . $hpp['id'])) . '"><i class="bi bi-pencil me-1"></i>Edit</a><a class="btn btn-outline-secondary" href="' . htmlspecialchars(bo_url_for('hpp/index.php')) . '"><i class="bi bi-arrow-left me-1"></i>Kembali</a>';
bo_render_shell_start([
    'title' => 'Detail HPP - Backoffice',
    'page_title' => 'Detail HPP',
    'page_subtitle' => 'Lihat detail informasi HPP.',
    'active' => 'hpp',
    'header_actions' => $headerActions,
]);
?>

<div class="bo-card p-4 mb-4">
    <div class="bo-card-header">
        <div>
            <h3 class="bo-card-title">Informasi Umum</h3>
        </div>
    </div>
    <div class="row g-3">
        <div class="col-md-3">
            <label class="bo-form-label">Kode HPP</label>
            <p class="mb-0"><?= htmlspecialchars($hpp['kode_hpp']) ?></p>
        </div>
        <div class="col-md-5">
            <label class="bo-form-label">Nama Menu</label>
            <p class="mb-0"><?= htmlspecialchars($hpp['nama_barang_hpp']) ?></p>
        </div>
        <div class="col-md-4">
            <label class="bo-form-label">Harga Jual</label>
            <p class="mb-0">Rp <?= number_format($hpp['harga_jual'], 0, ',', '.') ?></p>
        </div>
    </div>
</div>

<div class="bo-card p-4 mb-4">
    <div class="bo-card-header">
        <div>
            <h3 class="bo-card-title">Bahan Baku</h3>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>No</th>
                    <th>Nama Bahan</th>
                    <th>Qty</th>
                    <th>Satuan</th>
                    <th>Harga Satuan</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                <?php $no = 1; $total_bahan = 0; while ($row = $detail_bahan->fetch_assoc()): ?>
                    <tr>
                        <td><?= $no++ ?></td>
                        <td><?= htmlspecialchars($row['nama_barang'] ?? 'Unknown') ?></td>
                        <td><?= $row['qty'] ?></td>
                        <td><?= htmlspecialchars($row['satuan']) ?></td>
                        <td>Rp <?= number_format($row['harga_satuan'], 0, ',', '.') ?></td>
                        <td>Rp <?= number_format($row['total'], 0, ',', '.') ?></td>
                    </tr>
                    <?php $total_bahan += $row['total']; endwhile; ?>
            </tbody>
            <tfoot>
                <tr class="table-info">
                    <th colspan="5" class="text-end">Total Bahan Baku:</th>
                    <th>Rp <?= number_format($total_bahan, 0, ',', '.') ?></th>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<div class="bo-card p-4 mb-4">
    <div class="bo-card-header">
        <div>
            <h3 class="bo-card-title">Biaya Lainnya</h3>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>No</th>
                    <th>Nama Biaya</th>
                    <th>Nominal</th>
                </tr>
            </thead>
            <tbody>
                <?php $no = 1; $total_biaya = 0; while ($row = $detail_biaya->fetch_assoc()): ?>
                    <tr>
                        <td><?= $no++ ?></td>
                        <td><?= htmlspecialchars($row['nama_biaya']) ?></td>
                        <td>Rp <?= number_format($row['nominal'], 0, ',', '.') ?></td>
                    </tr>
                    <?php $total_biaya += $row['nominal']; endwhile; ?>
            </tbody>
            <tfoot>
                <tr class="table-info">
                    <th colspan="2" class="text-end">Total Biaya Lainnya:</th>
                    <th>Rp <?= number_format($total_biaya, 0, ',', '.') ?></th>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<div class="bo-card p-4">
    <div class="bo-card-header">
        <div>
            <h3 class="bo-card-title">Ringkasan</h3>
        </div>
    </div>
    <div class="row g-3">
        <div class="col-md-3">
            <div class="bo-card p-4 bg-light">
                <div class="h6 mb-1">Total HPP</div>
                <div class="h4 text-primary mb-0">Rp <?= number_format($hpp['hpp_total'], 0, ',', '.') ?></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="bo-card p-4 bg-light">
                <div class="h6 mb-1">Harga Jual</div>
                <div class="h4 text-success mb-0">Rp <?= number_format($hpp['harga_jual'], 0, ',', '.') ?></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="bo-card p-4 bg-light">
                <div class="h6 mb-1">Profit</div>
                <div class="h4 text-info mb-0">Rp <?= number_format($hpp['profit'], 0, ',', '.') ?></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="bo-card p-4 bg-light">
                <div class="h6 mb-1">Persentase Profit</div>
                <div class="h4 text-warning mb-0"><?= number_format($hpp['persentase_profit'], 2) ?>%</div>
            </div>
        </div>
    </div>
</div>

<?php bo_render_shell_end(); ?>