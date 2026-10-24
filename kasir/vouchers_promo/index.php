<?php
/**
 * Vouchers & Promo Management
 * Diskon produk, diskon transaksi, voucher, buy 1 get 1
 */

session_start();
require_once('../_init.php');

$access = new AccessControl('kasir_vouchers');
if (!$access->canAccess()) {
    header('Location: ../unauthorized.php');
    exit;
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vouchers & Promo - POS System</title>
    <link rel="stylesheet" href="../../asset/css/bootstrap.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container-fluid vouchers-container">
        <div class="header-section">
            <h2>Voucher & Promo</h2>
            <button class="btn btn-primary" data-toggle="modal" data-target="#createVoucherModal">
                + Buat Voucher
            </button>
        </div>

        <!-- Filter Tabs -->
        <ul class="nav nav-tabs" role="tablist">
            <li class="nav-item">
                <a class="nav-link active" data-toggle="tab" href="#activeVouchers">Aktif</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="tab" href="#expiredVouchers">Kadaluarsa</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="tab" href="#voucherStats">Statistik</a>
            </li>
        </ul>

        <div class="tab-content mt-3">
            <!-- Active Vouchers -->
            <div id="activeVouchers" class="tab-pane fade show active">
                <table class="table table-striped" id="activeTable">
                    <thead>
                        <tr>
                            <th>Kode</th>
                            <th>Tipe Diskon</th>
                            <th>Nilai</th>
                            <th>Berlaku Sampai</th>
                            <th>Sisa Kuota</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="activeBody">
                        <!-- Active vouchers will be loaded here -->
                    </tbody>
                </table>
            </div>

            <!-- Expired Vouchers -->
            <div id="expiredVouchers" class="tab-pane fade">
                <table class="table table-striped" id="expiredTable">
                    <thead>
                        <tr>
                            <th>Kode</th>
                            <th>Tipe Diskon</th>
                            <th>Nilai</th>
                            <th>Berakhir</th>
                            <th>Total Digunakan</th>
                        </tr>
                    </thead>
                    <tbody id="expiredBody">
                        <!-- Expired vouchers will be loaded here -->
                    </tbody>
                </table>
            </div>

            <!-- Statistics -->
            <div id="voucherStats" class="tab-pane fade">
                <div class="row">
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-body text-center">
                                <h6>Total Voucher Aktif</h6>
                                <h3 id="totalActiveVouchers">0</h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-body text-center">
                                <h6>Total Diskon Diberikan</h6>
                                <h3 id="totalDiscountGiven">Rp 0</h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-body text-center">
                                <h6>Voucher Paling Populer</h6>
                                <h3 id="mostPopularVoucher">-</h3>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Create Voucher Modal -->
    <div class="modal fade" id="createVoucherModal">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Buat Voucher Baru</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <form id="createVoucherForm">
                    <div class="modal-body">
                        <div class="form-group">
                            <label>Kode Voucher</label>
                            <input type="text" class="form-control" name="voucher_code" required>
                        </div>
                        <div class="form-group">
                            <label>Tipe Diskon</label>
                            <select class="form-control" name="discount_type" required>
                                <option value="percentage">Persen (%)</option>
                                <option value="fixed">Jumlah Tetap (Rp)</option>
                                <option value="bogo">Buy 1 Get 1</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Nilai Diskon</label>
                            <input type="number" class="form-control" name="discount_value" required>
                        </div>
                        <div class="form-group">
                            <label>Kuota Penggunaan</label>
                            <input type="number" class="form-control" name="quota" required>
                        </div>
                        <div class="form-group">
                            <label>Tanggal Berlaku</label>
                            <input type="date" class="form-control" name="start_date" required>
                        </div>
                        <div class="form-group">
                            <label>Tanggal Kadaluarsa</label>
                            <input type="date" class="form-control" name="end_date" required>
                        </div>
                        <div class="form-group">
                            <label>Minimum Pembelian</label>
                            <input type="number" class="form-control" name="min_purchase">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">Buat Voucher</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="../../asset/js/jquery.min.js"></script>
    <script src="../../asset/js/bootstrap.min.js"></script>
    <script src="vouchers.js"></script>
</body>
</html>
