<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

// Ambil daftar barang untuk dropdown
$sql_barang = "SELECT b.id, b.kode_barang, b.nama_barang, k.nama_kategori, s.nama_satuan
               FROM barang b
               LEFT JOIN kategori k ON b.kategori_id = k.id
               LEFT JOIN satuan s ON b.satuan_id = s.id
               ORDER BY b.nama_barang";
$result_barang = $conn->query($sql_barang);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Stok - Gudang Antapani</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Boxicons CSS -->
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
    <link rel="shortcut icon" type="image/png" href="/minven_pro/asset/LOGO1.png">
    <link rel="apple-touch-icon" href="/minven_pro/asset/LOGO1.png">
    <style>
        body {
            background-color: #f8f9fa;
            padding-top: 20px;
        }
        .card {
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            border-radius: 10px;
        }
        .card-header {
            background-color: #4e73df;
            color: white;
            border-radius: 10px 10px 0 0 !important;
        }
        .btn-primary {
            background-color: #4e73df;
            border-color: #4e73df;
        }
        .btn-primary:hover {
            background-color: #2e59d9;
            border-color: #2e59d9;
        }
        .select2-container--bootstrap-5 .select2-selection {
            height: calc(3.5rem + 2px);
            padding: 1rem 0.75rem;
            font-size: 1rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class='bx bx-plus-circle me-2'></i>Tambah Stok Barang</h5>
                    </div>
                    <div class="card-body">
                        <form id="addStokForm" method="POST">
                            <input type="hidden" name="action" value="add_stok">
                            
                            <div class="mb-3">
                                <label for="barang_id" class="form-label">Pilih Barang</label>
                                <select name="barang_id" id="barang_id" class="form-select select2" required>
                                    <option value="">-- Pilih Barang --</option>
                                    <?php while($barang = $result_barang->fetch_assoc()): ?>
                                    <option value="<?= $barang['id'] ?>">
                                        <?= $barang['kode_barang'] ?> - <?= $barang['nama_barang'] ?> 
                                        (<?= $barang['nama_kategori'] ?>, <?= $barang['nama_satuan'] ?>)
                                    </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="stok_minimum" class="form-label">Stok Minimum</label>
                                <input type="number" name="stok_minimum" id="stok_minimum" class="form-control" 
                                       value="0" min="0">
                            </div>
                            
                            <div class="mb-3">
                                <label for="expire_date" class="form-label">Tanggal Kadaluarsa</label>
                                <input type="date" name="expire_date" id="expire_date" class="form-control">
                            </div>
                            
                            <div class="d-flex justify-content-between">
                                <a href="gudang_antapani.php" class="btn btn-secondary">
                                    <i class='bx bx-arrow-back me-1'></i>Kembali
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class='bx bx-save me-1'></i>Simpan
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast Notification -->
    <div class="position-fixed bottom-0 end-0 p-3" style="z-index: 11">
        <div id="liveToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="toast-header bg-primary text-white">
                <i class='bx bx-bell me-2'></i>
                <strong class="me-auto">Notifikasi</strong>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
            <div class="toast-body bg-light">
                <div class="d-flex align-items-center">
                    <span class="notification-message">Stok berhasil ditambahkan!</span>
                </div>
            </div>
        </div>
    </div>

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Select2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    
    <script>
    $(document).ready(function() {
        // Inisialisasi Select2
        $('.select2').select2({
            theme: 'bootstrap-5',
            width: '100%'
        });
    });
    
    function showToast(message, type = 'info') {
        const toastEl = document.getElementById('liveToast');
        if (toastEl) {
            const header = toastEl.querySelector('.toast-header');
            const icon = toastEl.querySelector('.bx');
            const messageEl = toastEl.querySelector('.notification-message');
            
            // Set warna berdasarkan jenis notifikasi
            const colors = {
                success: 'bg-success',
                warning: 'bg-warning',
                error: 'bg-danger',
                info: 'bg-primary'
            };
            
            // Set icon berbeda berdasarkan jenis
            const icons = {
                success: 'bx bx-check-circle',
                warning: 'bx bx-error',
                error: 'bx bx-x-circle',
                info: 'bx bx-info-circle'
            };
            
            // Update tampilan
            header.className = `toast-header ${colors[type]} text-white`;
            icon.className = `${icons[type]} me-2`;
            messageEl.textContent = message;
            
            const toast = new bootstrap.Toast(toastEl);
            toast.show();
        }
    }

    document.getElementById('addStokForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        
        fetch('process_antapani.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            if(data.success) {
                showToast(data.message || 'Stok berhasil ditambahkan', 'success');
                // Reset form
                document.getElementById('addStokForm').reset();
                $('.select2').val('').trigger('change');
                
                // Redirect setelah 1.5 detik
                setTimeout(() => {
                    window.location.href = 'gudang_antapani.php';
                }, 1500);
            } else {
                showToast(data.message || 'Gagal menambahkan stok', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('Terjadi kesalahan: ' + error.message, 'error');
        });
    });
    </script>
</body>
</html>
