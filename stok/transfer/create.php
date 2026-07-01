<?php
session_start();
require_once '../../config.php';
require_once '../../includes/access_check.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit();
}

// Tangkap parameter ID jika ada
$gudang_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Generate nomor transaksi
$today = date('Ymd');
$query = $conn->query("SELECT MAX(no_transaksi) as max_no FROM transaksi_transfer WHERE no_transaksi LIKE 'TRF$today%'");
$result = $query->fetch_assoc();
$last_no = $result['max_no'];

if ($last_no) {
    $sequence = substr($last_no, -4);
    $sequence++;
    $new_no = 'TRF' . $today . str_pad($sequence, 4, '0', STR_PAD_LEFT);
} else {
    $new_no = 'TRF' . $today . '0001';
}

$gudang_list = [];
if (function_exists('get_accessible_gudang_list')) {
    foreach (get_accessible_gudang_list($conn) as $g) {
        $gudang_list[] = ['id' => $g['id'], 'nama_gudang' => $g['nama_gudang']];
    }
} else {
    $res = $conn->query("SELECT id, nama_gudang FROM gudang ORDER BY nama_gudang");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $gudang_list[] = $row;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Transfer Stok - Sistem Inventory</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.0.7/css/boxicons.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet">
</head>
<body>
    <?php include '../../templates/navbar.php'; ?>

    <div class="mt-4">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">Tambah Transfer Stok</h5>
                    </div>
                    <div class="card-body">
                        <form id="formTransferStok" action="process.php" method="post">
                            <input type="hidden" name="action" value="add">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="no_transaksi" class="form-label">No Transaksi</label>
                                        <input type="text" class="form-control" id="no_transaksi" name="no_transaksi" value="<?= $new_no ?>" readonly>
                                    </div>
                                    <div class="mb-3">
                                        <label for="tanggal" class="form-label">Tanggal</label>
                                        <input type="date" class="form-control" id="tanggal" name="tanggal" value="<?= date('Y-m-d') ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="gudang_asal_id" class="form-label">Gudang Asal</label>
                                        <select class="form-select" id="gudang_asal_id" name="gudang_asal_id" required>
                                            <option value="">Pilih Gudang Asal</option>
                                            <?php foreach($gudang_list as $gudang): ?>
                                                <option value="<?= $gudang['id'] ?>"><?= htmlspecialchars($gudang['nama_gudang']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label for="gudang_tujuan_id" class="form-label">Gudang Tujuan</label>
                                        <select class="form-select" id="gudang_tujuan_id" name="gudang_tujuan_id" required>
                                            <option value="">Pilih Gudang Tujuan</option>
                                            <?php foreach($gudang_list as $gudang): ?>
                                                <option value="<?= $gudang['id'] ?>"><?= htmlspecialchars($gudang['nama_gudang']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="keterangan" class="form-label">Keterangan</label>
                                <textarea class="form-control" id="keterangan" name="keterangan" rows="2"></textarea>
                            </div>

                            <div class="table-responsive mb-3">
                                <table class="table table-bordered" id="detailTable">
                                    <thead>
                                        <tr>
                                            <th>Barang</th>
                                            <th>Stok Tersedia</th>
                                            <th>Jumlah</th>
                                            <th>Keterangan</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>
                                                <select class="form-select select2" name="barang_id[]" required>
                                                    <option value="">Pilih Barang</option>
                                                </select>
                                            </td>
                                            <td>
                                                <input type="text" class="form-control stok-tersedia" readonly>
                                            </td>
                                            <td>
                                                <input type="number" class="form-control" name="jumlah[]" required min="1">
                                            </td>
                                            <td>
                                                <input type="text" class="form-control" name="detail_keterangan[]">
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-danger btn-sm delete-row">
                                                    <i class='bx bx-trash'></i>
                                                </button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <div class="mb-3">
                                <button type="button" class="btn btn-success" id="addRow">
                                    <i class='bx bx-plus'></i> Tambah Barang
                                </button>
                            </div>

                            <div class="text-end">
                                <a href="index.php" class="btn btn-secondary">Batal</a>
                                <button type="submit" class="btn btn-primary">Simpan</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(document).ready(function() {
            // Initialize Select2
            initSelect2();

            // Set gudang asal jika ada parameter ID
            const gudangId = new URLSearchParams(window.location.search).get('id');
            if (gudangId) {
                $('#gudang_asal_id').val(gudangId).trigger('change');
            }
            
            // Prevent same warehouse selection
            $('#gudang_tujuan_id').change(function() {
                const asalId = $('#gudang_asal_id').val();
                const tujuanId = $(this).val();
                
                if(asalId && asalId === tujuanId) {
                    alert('Gudang tujuan tidak boleh sama dengan gudang asal!');
                    $(this).val('').trigger('change');
                }
            });

            $('#gudang_asal_id').change(function() {
                const asalId = $(this).val();
                const tujuanId = $('#gudang_tujuan_id').val();
                
                if(tujuanId && asalId === tujuanId) {
                    alert('Gudang asal tidak boleh sama dengan gudang tujuan!');
                    $(this).val('').trigger('change');
                    return;
                }

                // Load barang based on gudang
                if(asalId) {
                    loadBarangByGudang(asalId);
                } else {
                    // Clear barang dropdown if no gudang selected
                    $('select[name="barang_id[]"]').empty().append('<option value="">Pilih Barang</option>');
                }
            });

            // Add new row
            $('#addRow').click(function() {
                const newRow = `
                    <tr>
                        <td>
                            <select class="form-select select2" name="barang_id[]" required>
                                <option value="">Pilih Barang</option>
                                ${$('select[name="barang_id[]"]:first').html()}
                            </select>
                        </td>
                        <td>
                            <input type="text" class="form-control stok-tersedia" readonly>
                        </td>
                        <td>
                            <input type="number" class="form-control" name="jumlah[]" required min="1">
                        </td>
                        <td>
                            <input type="text" class="form-control" name="detail_keterangan[]">
                        </td>
                        <td>
                            <button type="button" class="btn btn-danger btn-sm delete-row">
                                <i class='bx bx-trash'></i>
                            </button>
                        </td>
                    </tr>
                `;
                $('#detailTable tbody').append(newRow);
                initSelect2();
            });

            // Delete row
            $(document).on('click', '.delete-row', function() {
                if($('#detailTable tbody tr').length > 1) {
                    $(this).closest('tr').remove();
                } else {
                    alert('Minimal harus ada satu barang!');
                }
            });

            // Update stok tersedia when barang is selected
            $(document).on('change', 'select[name="barang_id[]"]', function() {
                const barangId = $(this).val();
                const row = $(this).closest('tr');
                const stokInput = row.find('.stok-tersedia');
                const jumlahInput = row.find('input[name="jumlah[]"]');
                
                console.log('Barang selected:', barangId);
                
                if(barangId) {
                    const selectedOption = $(this).find('option:selected');
                    // Use stok_tersedia for both display and validation
                    const stokTersedia = selectedOption.data('stok-tersedia');
                    const stokAwal = selectedOption.data('stok-awal');
                    const stokTerpakai = selectedOption.data('stok-terpakai');

                    console.log('Stok data:', {
                        tersedia: stokTersedia,
                        awal: stokAwal,
                        terpakai: stokTerpakai
                    });

                    // Display stok_tersedia in the 'Stok Tersedia' field
                    if (stokTersedia !== undefined && stokTersedia !== null && !isNaN(stokTersedia)) {
                         stokInput.val(stokTersedia);
                         // Use stok_tersedia for max quantity validation as well
                         jumlahInput.attr('max', stokTersedia);
                         
                         // Show alert with stok info (optional, for debugging)
                         // alert(`Stok tersedia: ${stokTersedia}`);
                    } else {
                         console.error('Stok Tersedia data is missing or invalid for barangId:', barangId, 'Value:', stokTersedia);
                         stokInput.val('N/A');
                         jumlahInput.removeAttr('max');
                    }
                } else {
                    stokInput.val('');
                    jumlahInput.removeAttr('max');
                }
            });

            // Validate jumlah not more than stok
            $(document).on('input', 'input[name="jumlah[]"]', function() {
                const jumlah = parseInt($(this).val());
                const row = $(this).closest('tr');
                // Use stok_tersedia for validation
                const selectedOption = row.find('select[name="barang_id[]"] option:selected');
                const stokTersedia = parseInt(selectedOption.data('stok-tersedia'));
                
                if(isNaN(stokTersedia) || jumlah > stokTersedia) {
                    alert('Jumlah tidak boleh melebihi stok tersedia!');
                    $(this).val(stokTersedia);
                }
            });

            // Form validation before submit
            $('#formTransferStok').submit(function(e) {
                const gudangAsal = $('#gudang_asal_id').val();
                const gudangTujuan = $('#gudang_tujuan_id').val();
                
                if(!gudangAsal || !gudangTujuan) {
                    e.preventDefault();
                    alert('Gudang asal dan tujuan harus dipilih!');
                    return false;
                }
                
                if(gudangAsal === gudangTujuan) {
                    e.preventDefault();
                    alert('Gudang asal dan tujuan tidak boleh sama!');
                    return false;
                }
                
                let valid = true;
                $('select[name="barang_id[]"]').each(function() {
                    if(!$(this).val()) {
                        valid = false;
                        alert('Semua barang harus dipilih!');
                        return false; // Break .each loop
                    }
                });
                
                if(!valid) {
                    e.preventDefault();
                    return false;
                }
                
                $('input[name="jumlah[]"]').each(function() {
                    const jumlah = parseInt($(this).val());
                    const row = $(this).closest('tr');
                    // Use stok_tersedia for validation
                    const selectedOption = row.find('select[name="barang_id[]"] option:selected');
                    const stokTersedia = parseInt(selectedOption.data('stok-tersedia'));
                    
                    if(isNaN(jumlah) || jumlah <= 0) { 
                        valid = false;
                        alert('Jumlah harus lebih dari 0!');
                        return false;
                    }
                    
                    if(isNaN(stokTersedia) || jumlah > stokTersedia) {
                        valid = false;
                        alert('Jumlah tidak boleh melebihi stok tersedia!');
                        return false;
                    }
                });
                
                if(!valid) {
                    e.preventDefault();
                    return false;
                }
                // If all validation passes, form will submit
            });

            // Function to initialize Select2
            function initSelect2() {
                $('.select2').select2({
                    theme: 'bootstrap-5',
                    width: '100%'
                });
            }

            // Function to load barang by gudang
            // Update the loadBarangByGudang function
            function loadBarangByGudang(gudangId) {
                console.log('Loading barang for gudang:', gudangId);
                $.ajax({
                    url: 'get_stok.php',
                    type: 'GET',
                    data: {gudang_id: gudangId},
                    dataType: 'json',
                    beforeSend: function() {
                        // Clear existing rows except the first one
                        $('#detailTable tbody tr:not(:first)').remove();
                        // Reset the first row
                        const firstRow = $('#detailTable tbody tr:first');
                        firstRow.find('select[name="barang_id[]"]').html('<option value="">Memuat stok tersedia...</option>').trigger('change');
                        firstRow.find('.stok-tersedia').val('');
                        firstRow.find('input[name="jumlah[]"]').val('').removeAttr('max');
                    },
                    success: function(data) {
                        console.log('API Response:', data);
                        if(data.success && data.barang && data.barang.length > 0) {
                            console.log('Barang found:', data.barang.length);
                            updateBarangDropdowns(data.barang);
                        } else {
                            // Updated message
                            console.warn('No barang found or API error:', data);
                            showErrorToast('Tidak ada stok tersedia di gudang ini');
                            $('select[name="barang_id[]"]').html('<option value="">Pilih Barang</option>').trigger('change');
                        }
                    },
                    error: function(xhr) {
                        console.error('AJAX Error:', xhr);
                        let errorMsg = 'Terjadi kesalahan saat memuat data stok tersedia'; // Updated message
                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            errorMsg = xhr.responseJSON.message;
                        }
                        showErrorToast(errorMsg);
                        $('select[name="barang_id[]"]').html('<option value="">Pilih Barang</option>').trigger('change');
                    }
                });
            }

            // Add this new function for showing error messages (already exists, keeping for context)
            function showErrorToast(message) {
                // ... existing toast code ...
            }

            // Update the updateBarangDropdowns function
            function updateBarangDropdowns(barangList) {
                let optionsHtml = '<option value="">Pilih Barang</option>';

                barangList.forEach(function(item) {
                    const detailText = item.detail_barang ? ` (${item.detail_barang})` : '';
                    // Use stok_tersedia for both display and validation
                    const stokTersedia = item.stok_tersedia !== null && item.stok_tersedia !== undefined ? parseFloat(item.stok_tersedia) : 0;

                    optionsHtml += `<option value="${item.id}" 
                                      data-stok-tersedia="${stokTersedia}">
                                      ${item.kode_barang} - ${item.nama_barang}${detailText} 
                                      (Stok Tersedia: ${stokTersedia})
                                   </option>`;
                });

                // Update all barang dropdowns
                $('select[name="barang_id[]"]').each(function() {
                     const $select = $(this);
                     const currentVal = $select.val(); // Preserve selected value if any

                     // Clear existing options
                     $select.empty();

                     // Append new options
                     $select.append(optionsHtml);

                     // Restore and trigger change
                     if (currentVal) {
                         $select.val(currentVal);
                         // Check if the restored value is actually in the new options
                         if ($select.val() === currentVal) {
                             $select.trigger('change');
                         } else {
                             // If the old value is no longer valid, reset
                             $select.val('').trigger('change');
                         }
                     } else {
                         // Trigger change to update stok-tersedia field for the first row or new rows
                         $select.trigger('change');
                     }

                     // Re-initialize Select2 for this element
                     // Destroy and re-initialize Select2 to handle dynamic updates
                     if ($select.data('select2')) {
                         $select.select2('destroy');
                     }
                     $select.select2({
                         theme: 'bootstrap-5',
                         width: '100%'
                     });
                });
            }
        });
    </script>
</body>
</html>
