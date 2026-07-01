<?php
ob_start();
session_start();
require_once '../../config.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit();
}

function writeEditLog($message, $type = 'info') {
    $logFile = __DIR__ . '/edit_save.log';
    $date = date('Y-m-d H:i:s');
    $logMessage = "[$date][$type] $message\n";
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    header("Location: index.php");
    exit();
}

// Query untuk mengambil data header pembelian
$sql = "SELECT * FROM direct_purchase WHERE id = ? AND status = 'menunggu'"; 
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: index.php");
    exit();
}

$purchase = $result->fetch_assoc();

// Query untuk mengambil daftar barang
$sql = "SELECT b.id, b.kode_barang, b.nama_barang, s.nama_satuan, b.harga_beli 
        FROM barang b
        LEFT JOIN satuan s ON b.satuan_id = s.id
        ORDER BY b.kode_barang";
$barang_result = $conn->query($sql);

// Query untuk mengambil detail pembelian
$sql = "SELECT ddp.*, 
        CASE 
            WHEN ddp.barang_id IS NULL THEN ddp.keterangan
            ELSE b.nama_barang 
        END as nama_barang,
        CASE 
            WHEN ddp.barang_id IS NULL THEN '-'
            ELSE b.kode_barang 
        END as kode_barang,
        CASE 
            WHEN ddp.barang_id IS NULL THEN '-'
            ELSE s.nama_satuan 
        END as nama_satuan
        FROM detail_direct_purchase ddp
        LEFT JOIN barang b ON ddp.barang_id = b.id
        LEFT JOIN satuan s ON b.satuan_id = s.id
        WHERE ddp.direct_purchase_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $id);
$stmt->execute();
$detail_result = $stmt->get_result();

// Proses update data
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $in_transaction = false;
    try {
        $nama_toko = $_POST['nama_toko'] ?? '';
        $tanggal = $_POST['tanggal'] ?? '';
        $keterangan = $_POST['keterangan'] ?? '';
        $barang_ids = $_POST['barang_id'] ?? [];
        $jumlah = $_POST['jumlah'] ?? [];
        $harga_satuan = $_POST['harga_satuan'] ?? [];
        $keterangan_detail = $_POST['keterangan_detail'] ?? [];
        $nama_barang_lain = isset($_POST['nama_barang_lain']) ? $_POST['nama_barang_lain'] : array();
        $existing_fotos = isset($_POST['existing_foto']) ? $_POST['existing_foto'] : array();

        $active_keys = [];
        foreach ($barang_ids as $key => $barang_id) {
            $selected = isset($barang_ids[$key]) ? trim((string)$barang_ids[$key]) : '';
            $qty = isset($jumlah[$key]) ? (int)$jumlah[$key] : 0;
            if ($selected !== '' && $qty > 0) {
                $active_keys[] = $key;
            }
        }

        if (count($active_keys) === 0) {
            $_SESSION['error'] = "Minimal harus ada satu item dengan jumlah lebih dari 0";
            ob_end_clean();
            header("Location: edit.php?id=" . $id);
            exit();
        }

        foreach ($active_keys as $key) {
            $barang_id = $barang_ids[$key] ?? '';
            if ((string)$barang_id === '0') {
                $nama = isset($nama_barang_lain[$key]) ? trim((string)$nama_barang_lain[$key]) : '';
                if ($nama === '') {
                    $_SESSION['error'] = "Nama barang wajib diisi untuk item Others";
                    ob_end_clean();
                    header("Location: edit.php?id=" . $id);
                    exit();
                }
            }
        }

        $total_item = 0;
        $total_harga = 0.0;
        foreach ($active_keys as $key) {
            $qty = (int)$jumlah[$key];
            $harga_float = (float)($harga_satuan[$key] ?? 0);
            $total_item += $qty;
            $total_harga += ($qty * $harga_float);
        }

        $conn->begin_transaction();
        $in_transaction = true;

        $sql = "UPDATE direct_purchase 
                SET nama_toko = ?, tanggal = ?, total_item = ?, total_harga = ?, keterangan = ? 
                WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ssidsi', $nama_toko, $tanggal, $total_item, $total_harga, $keterangan, $id);
        $stmt->execute();

        $sql = "DELETE FROM detail_direct_purchase WHERE direct_purchase_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $id);
        $stmt->execute();

        $sql = "INSERT INTO detail_direct_purchase (direct_purchase_id, barang_id, jumlah, harga_satuan, total_harga, keterangan, foto) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);

        $success_count = 0;
        foreach ($active_keys as $key) {
            $barang_id = $barang_ids[$key] ?? '';
            $qty = (int)$jumlah[$key];
            $harga_float = (float)($harga_satuan[$key] ?? 0);
            $total = $qty * $harga_float;

            if ((string)$barang_id === '0') {
                $barang_id_to_insert = NULL;
                $keterangan_to_insert = isset($nama_barang_lain[$key]) ? trim((string)$nama_barang_lain[$key]) : '';
            } else {
                $barang_id_to_insert = (int)$barang_id;
                $keterangan_to_insert = isset($keterangan_detail[$key]) ? trim((string)$keterangan_detail[$key]) : '';
            }

            $foto = isset($existing_fotos[$key]) && !empty($existing_fotos[$key]) ? $existing_fotos[$key] : NULL;

            $stmt->bind_param('iiiddss', $id, $barang_id_to_insert, $qty, $harga_float, $total, $keterangan_to_insert, $foto);
            $stmt->execute();
            $success_count++;
        }

        $conn->commit();
        $in_transaction = false;

        $_SESSION['success'] = "Data pembelian berhasil diperbarui dengan $success_count item!";
        ob_end_clean();
        header("Location: index.php");
        exit();
    } catch (Throwable $e) {
        if ($in_transaction) {
            try {
                $conn->rollback();
            } catch (Throwable $rollbackError) {
            }
        }
        writeEditLog("Update failed: " . $e->getMessage(), 'error');
        $_SESSION['error'] = "Gagal memperbarui data pembelian. Silakan coba lagi.";
        ob_end_clean();
        header("Location: edit.php?id=" . $id);
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Pembelian Dadakan - Sistem Inventory</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.0.7/css/boxicons.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
</head>
<body>
<?php include '../../templates/navbar.php'; ?>

<div class="container mt-4">
    <form method="POST">
        <div class="card">
            <div class="card-header"><h5>Informasi Pembelian</h5></div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label>Nama Toko</label>
                        <input type="text" class="form-control" name="nama_toko" value="<?= htmlspecialchars($purchase['nama_toko']) ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label>Tanggal</label>
                        <input type="date" class="form-control" name="tanggal" value="<?= $purchase['tanggal'] ?>" required>
                    </div>
                    <div class="col-md-12 mb-3">
                        <label>Keterangan</label>
                        <textarea class="form-control" name="keterangan"><?= htmlspecialchars($purchase['keterangan']) ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-header"><h5>Detail Barang</h5></div>
            <div class="table-responsive">
                <table class="table table-bordered" id="detail_table">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Barang</th>
                            <th>Jumlah</th>
                            <th>Harga</th>
                            <th>Total</th>
                            <th>Foto</th>
                            <th>Keterangan</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $row_number = 1;
                        while($detail = $detail_result->fetch_assoc()): 
                        ?>
                        <tr>
                            <td class="text-center"><?= $row_number ?></td>
                            <td>
                                <select name="barang_id[]" class="form-select barang-select" required>
                                    <option value="">Pilih Barang</option>
                                    <option value="0" <?= $detail['barang_id'] == null ? 'selected' : '' ?>>Others</option>
                                    <?php 
                                    $barang_result->data_seek(0);
                                    while($barang = $barang_result->fetch_assoc()): ?>
                                    <option value="<?= $barang['id'] ?>" data-harga="<?= $barang['harga_beli'] ?>" <?= $barang['id'] == $detail['barang_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($barang['kode_barang'] . ' - ' . $barang['nama_barang'] . ' (' . $barang['nama_satuan'] . ')') ?>
                                    </option>
                                    <?php endwhile; ?>
                                </select>
                                <input type="text" name="nama_barang_lain[]" class="form-control mt-1 nama-barang-lain" 
                                       value="<?= $detail['barang_id']==null ? htmlspecialchars($detail['keterangan']) : '' ?>" 
                                       style="display: <?= $detail['barang_id']==null ? 'block':'none' ?>">
                            </td>
                            <td><input type="number" name="jumlah[]" class="form-control jumlah" value="<?= $detail['jumlah'] ?>"></td>
                            <td><input type="number" name="harga_satuan[]" class="form-control harga-satuan" value="<?= $detail['harga_satuan'] ?>"></td>
                            <td><input type="text" class="form-control total" value="<?= number_format($detail['total_harga'],2) ?>" readonly></td>
                            <td>
                                <?php if (!empty($detail['foto'])): ?>
                                    <img src="../../uploads/pembelian/<?= htmlspecialchars($detail['foto']) ?>" width="60">
                                    <input type="hidden" name="existing_foto[]" value="<?= htmlspecialchars($detail['foto']) ?>">
                                <?php else: ?>
                                    <input type="hidden" name="existing_foto[]" value="">
                                <?php endif; ?>
                            </td>
                            <td><input type="text" name="keterangan_detail[]" class="form-control" value="<?= $detail['barang_id']!=null ? htmlspecialchars($detail['keterangan']) : '' ?>"></td>
                            <td><button type="button" class="btn btn-danger btn-sm btn-delete-row">X</button></td>
                        </tr>
                        <?php $row_number++; endwhile; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="8"><button type="button" class="btn btn-success btn-sm" id="btn_add_row">Tambah Baris</button></td>
                        </tr>
                        <tr>
                            <td colspan="4" class="text-end">Total:</td>
                            <td><input type="text" id="grand_total" class="form-control" value="<?= number_format($purchase['total_harga'],2) ?>" readonly></td>
                            <td colspan="3"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <div class="text-end mt-3">
            <a href="index.php" class="btn btn-secondary">Kembali</a>
            <button type="submit" class="btn btn-primary">Simpan</button>
        </div>
    </form>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(document).ready(function() {
    // init select2
    $('.barang-select').select2({ width:'100%' });

    function updateRowNumbers() {
        $('#detail_table tbody tr').each(function(i){ $(this).find('td:first').text(i+1); });
    }

    function hitungTotal(row){
        var jumlah = parseFloat(row.find('.jumlah').val())||0;
        var harga = parseFloat(row.find('.harga-satuan').val())||0;
        row.find('.total').val((jumlah*harga).toLocaleString('id-ID',{minimumFractionDigits:2}));
        hitungGrandTotal();
    }

    function hitungGrandTotal(){
        var grand=0;
        $('.total').each(function(){
            var v=$(this).val().replace(/\./g,'').replace(',','.');
            grand+=parseFloat(v)||0;
        });
        $('#grand_total').val(grand.toLocaleString('id-ID',{minimumFractionDigits:2}));
    }

    $('#detail_table').on('change','.barang-select',function(){
        var row=$(this).closest('tr');
        if($(this).val()==='0'){ row.find('.nama-barang-lain').show(); row.find('.harga-satuan').val('0'); }
        else if($(this).val()!==''){ row.find('.nama-barang-lain').hide(); row.find('.harga-satuan').val($(this).find(':selected').data('harga')); }
        else{ row.find('.nama-barang-lain').hide(); row.find('.harga-satuan').val('0'); }
        hitungTotal(row);
    });

    $('#detail_table').on('input','.jumlah,.harga-satuan',function(){ hitungTotal($(this).closest('tr')); });

    // ✅ FIXED add row
    $('#btn_add_row').click(function(){
        var newRow=$('#detail_table tbody tr:first').clone(false,false);
        newRow.find('input').val('');
        newRow.find('.total').val('0,00');
        newRow.find('select').val('').trigger('change');
        newRow.find('.nama-barang-lain').hide();
        newRow.find('.photo-preview').html('');
        newRow.find('input[name="existing_foto[]"]').val('');

        // hapus select2 lama biar tidak stuck
        newRow.find('.barang-select').removeClass('select2-hidden-accessible').next('.select2').remove();

        $('#detail_table tbody').append(newRow);

        // reinit select2
        newRow.find('.barang-select').select2({ width:'100%' });

        updateRowNumbers();
    });

    $('#detail_table').on('click','.btn-delete-row',function(){
        if($('#detail_table tbody tr').length>1){ $(this).closest('tr').remove(); updateRowNumbers(); hitungGrandTotal(); }
    });

    $('form').submit(function(e){
        var valid=false;
        $('#detail_table tbody tr').each(function(){
            var j=parseFloat($(this).find('.jumlah').val())||0;
            var h=parseFloat($(this).find('.harga-satuan').val())||0;
            if(j>0&&h>0) valid=true;
        });
        if(!valid){ alert('Minimal harus ada satu item valid!'); e.preventDefault(); }
    });

    // hitung awal
    $('#detail_table tbody tr').each(function(){ hitungTotal($(this)); });
});
</script>
</body>
</html>
