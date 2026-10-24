<?php
/**
 * Fast Checkout Module
 * Kasir cepat, keyboard shortcut, barcode scanner, pencarian produk instan
 */

session_start();
require_once('../_init.php');

// Check access
$access = new AccessControl('kasir_checkout');
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
    <title>Fast Checkout - POS System</title>
    <link rel="stylesheet" href="../../asset/css/bootstrap.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container-fluid">
        <!-- Bagian Pencarian Produk -->
        <div class="search-section">
            <input type="text" id="productSearch" class="form-control" placeholder="Scan barcode atau cari produk...">
        </div>

        <!-- Daftar Produk / Keranjang -->
        <div class="checkout-container">
            <div class="cart-items">
                <table class="table" id="cartTable">
                    <thead>
                        <tr>
                            <th>Produk</th>
                            <th>Qty</th>
                            <th>Harga</th>
                            <th>Subtotal</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="cartBody">
                        <!-- Items akan ditambahkan di sini -->
                    </tbody>
                </table>
            </div>

            <!-- Summary -->
            <div class="summary-section">
                <div class="summary-item">
                    <span>Subtotal:</span>
                    <span id="subtotal">Rp 0</span>
                </div>
                <div class="summary-item">
                    <span>Diskon:</span>
                    <span id="discount">Rp 0</span>
                </div>
                <div class="summary-item total">
                    <span>Total:</span>
                    <span id="total">Rp 0</span>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="button-section">
            <button class="btn btn-warning" id="holdBtn">Parkir Transaksi</button>
            <button class="btn btn-info" id="voucherBtn">Voucher/Promo</button>
            <button class="btn btn-primary" id="paymentBtn">Pembayaran</button>
        </div>
    </div>

    <script src="../../asset/js/jquery.min.js"></script>
    <script src="../../asset/js/bootstrap.min.js"></script>
    <script src="checkout.js"></script>
</body>
</html>
