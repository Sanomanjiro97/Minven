<?php
/**
 * Returns & Refunds Module
 * Retur barang dan pengembalian dana
 */

session_start();
require_once('../_init.php');

$access = new AccessControl('kasir_returns');
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
    <title>Returns & Refunds - POS System</title>
    <link rel="stylesheet" href="../../asset/css/bootstrap.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container-fluid returns-container">
        <div class="header-section">
            <h2>Retur & Pengembalian Dana</h2>
        </div>

        <!-- Search Transaction -->
        <div class="search-section">
            <div class="row">
                <div class="col-md-6">
                    <input type="text" id="transactionSearch" class="form-control" 
                        placeholder="Masukkan nomor transaksi atau nomor struk...">
                </div>
                <div class="col-md-6">
                    <input type="tel" id="customerPhone" class="form-control" 
                        placeholder="Atau cari berdasarkan nomor HP pelanggan...">
                </div>
            </div>
        </div>

        <!-- Transaction Details -->
        <div id="transactionDetails" class="card mt-3" style="display:none;">
            <div class="card-header">
                <h5>Detail Transaksi</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>No. Transaksi:</strong> <span id="transNo"></span></p>
                        <p><strong>Tanggal:</strong> <span id="transDate"></span></p>
                        <p><strong>Kasir:</strong> <span id="cashierName"></span></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Total:</strong> <span id="totalAmount" class="h5"></span></p>
                        <p><strong>Metode Bayar:</strong> <span id="paymentMethod"></span></p>
                        <p><strong>Status:</strong> <span id="status"></span></p>
                    </div>
                </div>

                <!-- Items to Return -->
                <div class="mt-3">
                    <h6>Pilih Barang untuk Diretur</h6>
                    <table class="table table-striped" id="itemsTable">
                        <thead>
                            <tr>
                                <th>Pilih</th>
                                <th>Produk</th>
                                <th>Qty</th>
                                <th>Harga</th>
                                <th>Subtotal</th>
                            </tr>
                        </thead>
                        <tbody id="itemsBody">
                            <!-- Items will be loaded here -->
                        </tbody>
                    </table>
                </div>

                <!-- Return Reason -->
                <div class="form-group mt-3">
                    <label>Alasan Retur</label>
                    <select class="form-control" id="returnReason" required>
                        <option value="">Pilih Alasan</option>
                        <option value="rusak">Barang Rusak</option>
                        <option value="salah">Pesanan Salah</option>
                        <option value="cacat">Cacat Produksi</option>
                        <option value="kadaluarsa">Kadaluarsa</option>
                        <option value="tidak_puas">Tidak Puas</option>
                        <option value="lainnya">Lainnya</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Keterangan Tambahan</label>
                    <textarea class="form-control" id="returnNotes" rows="3"></textarea>
                </div>

                <!-- Refund Summary -->
                <div class="alert alert-info mt-3">
                    <h6>Ringkasan Pengembalian Dana</h6>
                    <p><strong>Subtotal Retur:</strong> <span id="returnSubtotal">Rp 0</span></p>
                    <p><strong>Biaya Admin (jika ada):</strong> <span id="adminFee">Rp 0</span></p>
                    <p class="h5"><strong>Total Dikembalikan:</strong> <span id="refundTotal">Rp 0</span></p>
                </div>

                <button class="btn btn-success btn-lg btn-block" id="processReturnBtn">
                    Proses Retur
                </button>
            </div>
        </div>

        <!-- Return History -->
        <div class="card mt-4">
            <div class="card-header">
                <h5>Riwayat Retur</h5>
            </div>
            <div class="card-body">
                <table class="table table-striped" id="returnHistoryTable">
                    <thead>
                        <tr>
                            <th>No. Retur</th>
                            <th>Transaksi Asal</th>
                            <th>Tanggal Retur</th>
                            <th>Jumlah</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="returnHistoryBody">
                        <!-- Return history will be loaded here -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="../../asset/js/jquery.min.js"></script>
    <script src="../../asset/js/bootstrap.min.js"></script>
    <script src="returns.js"></script>
</body>
</html>
