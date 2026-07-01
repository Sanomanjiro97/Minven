<?php
require_once '../config.php';

// Get list of items
$sql = "SELECT id, kode_barang, nama_barang FROM barang ORDER BY nama_barang";
$result = $conn->query($sql);
?>

<form method="POST" action="process_antapani.php">
    <input type="hidden" name="action" value="add_stok">
    <input type="hidden" name="stok_awal" value="0">
    
    <div class="mb-3">
        <label for="barang_id" class="form-label">Pilih Barang:</label>
        <select class="form-select" id="barang_id" name="barang_id" required>
            <option value="">Pilih Barang</option>
            <?php while($row = $result->fetch_assoc()): ?>
                <option value="<?= $row['id'] ?>"><?= $row['kode_barang'] . ' - ' . $row['nama_barang'] ?></option>
            <?php endwhile; ?>
        </select>
    </div>

    <div class="mb-3">
        <label for="stok_minimum" class="form-label">Par Stok:</label>
        <input type="number" class="form-control" id="stok_minimum" name="stok_minimum" min="0" required>
    </div>

    <div class="mb-3">
        <label for="expire_date" class="form-label">Tanggal Expire:</label>
        <input type="date" class="form-control" id="expire_date" name="expire_date">
    </div>

    <button type="submit" class="btn btn-primary">Tambah Item</button>
</form>
