<?php
require_once __DIR__ . '/../_init.php';
bo_require_login();

// Get filter values
$filter_kode = isset($_GET['filter_kode']) ? trim((string)$_GET['filter_kode']) : '';
$filter_nama = isset($_GET['filter_nama']) ? trim((string)$_GET['filter_nama']) : '';

$where = [];
$params = [];
$types = '';

if ($filter_kode !== '') {
    $where[] = "(h.kode_hpp LIKE ?)";
    $params[] = "%$filter_kode%";
    $types .= 's';
}

if ($filter_nama !== '') {
    $where[] = "h.nama_barang_hpp LIKE ?";
    $params[] = "%$filter_nama%";
    $types .= 's';
}

$where_sql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

$sql = "SELECT h.*, u.nama as created_by_name,
           bahan.harga_beli_satuan,
           bahan.jumlah_bahan
        FROM hpp h
        LEFT JOIN users u ON h.created_by = u.id
    LEFT JOIN (
        SELECT hpp_id,
               GROUP_CONCAT(harga_satuan ORDER BY id SEPARATOR ',') AS harga_beli_satuan,
               COUNT(*) AS jumlah_bahan
        FROM hpp_detail_bahan
        GROUP BY hpp_id
    ) bahan ON bahan.hpp_id = h.id
        $where_sql
        ORDER BY h.created_at DESC";

$stmt = $boMainConn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$headerActions = '<a class="btn btn-primary" href="' . htmlspecialchars(bo_url_for('hpp/create.php')) . '" title="Tambah HPP Baru"><i class="bi bi-plus-circle"></i> Tambah HPP Baru</a>';
bo_render_shell_start([
    'title' => 'Data Base HPP - Backoffice',
    'page_title' => 'Data Base HPP',
    'page_subtitle' => 'Kelola data harga pokok penjualan.',
    'active' => 'hpp',
    'header_actions' => $headerActions,
]);
?>

<div class="bo-card p-4 mb-4">
    <div class="bo-card-header">
        <div>
            <h3 class="bo-card-title">Filter</h3>
            <div class="bo-card-subtitle">Cari berdasarkan kode HPP atau nama barang.</div>
        </div>
    </div>
    <form method="get" class="row g-3">
        <div class="col-md-3">
            <label class="bo-form-label">Kode HPP</label>
            <input type="text" class="form-control" name="filter_kode" value="<?= htmlspecialchars($filter_kode) ?>">
        </div>
        <div class="col-md-3">
            <label class="bo-form-label">Nama Barang</label>
            <input type="text" class="form-control" name="filter_nama" value="<?= htmlspecialchars($filter_nama) ?>">
        </div>
        <div class="col-md-6 d-flex align-items-end gap-2">
            <button type="submit" class="btn btn-primary" title="Cari"><i class="bi bi-search"></i></button>
            <a href="<?= htmlspecialchars(bo_url_for('hpp/index.php')) ?>" class="btn btn-outline-secondary" title="Reset"><i class="bi bi-x-circle"></i></a>
        </div>
    </form>
</div>

<div class="bo-card p-4">
    <div class="bo-card-header">
        <div>
            <h3 class="bo-card-title">Daftar HPP</h3>
            <div class="bo-card-subtitle">Semua data HPP yang telah dibuat.</div>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th style="width: 80px;">No</th>
                    <th>Kode HPP</th>
                    <th>Nama Barang</th>
                    <th>Total Harga Beli</th>
                    <th class="text-center">Jml Bahan</th>
                    <th>Harga Jual</th>
                    <th>HPP Total</th>
                    <th>Profit</th>
                    <th>Persentase Profit</th>
                    <th style="width: 140px;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result->num_rows > 0): ?>
                    <?php $no = 1; while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td><?= htmlspecialchars($row['kode_hpp']) ?></td>
                            <td><?= htmlspecialchars($row['nama_barang_hpp']) ?></td>
                            <td>
                                <?php
                                $harga_satuan_list = array_filter(explode(',', (string)($row['harga_beli_satuan'] ?? '')));
                                if (empty($harga_satuan_list)) {
                                    echo 'Rp 0';
                                } else {
                                    $total_harga = array_sum(array_map(function($harga) {
                                        return (float)$harga;
                                    }, $harga_satuan_list));
                                    echo 'Rp ' . number_format($total_harga, 0, ',', '.');
                                }
                                ?>
                            </td>
                            <td class="text-center"><?= (int)($row['jumlah_bahan'] ?? 0) ?> item</td>
                            <td>Rp <?= number_format($row['harga_jual'], 0, ',', '.') ?></td>
                            <td>Rp <?= number_format($row['hpp_total'], 0, ',', '.') ?></td>
                            <td>Rp <?= number_format($row['profit'], 0, ',', '.') ?></td>
                            <td><?= number_format($row['persentase_profit'], 2) ?>%</td>
                            <td>
                                <div class="d-flex justify-content-start gap-1">
                                    <a href="<?= htmlspecialchars(bo_url_for('hpp/view.php?id=' . $row['id'])) ?>" class="btn btn-sm btn-outline-info" title="Lihat"><i class="bi bi-eye"></i></a>
                                    <a href="<?= htmlspecialchars(bo_url_for('hpp/edit.php?id=' . $row['id'])) ?>" class="btn btn-sm btn-outline-warning" title="Edit"><i class="bi bi-pencil"></i></a>
                                    <a href="<?= htmlspecialchars(bo_url_for('hpp/process.php?action=delete&id=' . $row['id'])) ?>" 
                                       class="btn btn-sm btn-outline-danger" 
                                       title="Hapus"
                                       onclick="return confirm('Yakin ingin menghapus?')"><i class="bi bi-trash"></i></a>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="10" class="text-center text-muted py-4">Belum ada data HPP.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php bo_render_shell_end(); ?>