<?php
/**
 * Mobile/Tablet POS
 * Tampilan responsif untuk tablet/HP
 */

session_start();
require_once('../_init.php');

$access = new AccessControl('kasir_mobile');
if (!$access->canAccess()) {
    header('Location: ../unauthorized.php');
    exit;
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Mobile POS - <?php echo STORE_NAME; ?></title>
    <link rel="stylesheet" href="../../asset/css/bootstrap.min.css">
    <link rel="stylesheet" href="mobile-style.css">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black">
    <meta name="apple-mobile-web-app-title" content="Mobile POS">
</head>
<body class="mobile-body">
    <div class="mobile-container">
        <!-- Mobile Header -->
        <div class="mobile-header">
            <h3><?php echo STORE_NAME; ?></h3>
            <span class="time" id="currentTime"></span>
        </div>

        <!-- Mobile Navigation -->
        <div class="mobile-nav">
            <button class="nav-btn active" data-section="checkout">
                <i class="fas fa-shopping-cart"></i>
                <span>Kasir</span>
            </button>
            <button class="nav-btn" data-section="orders">
                <i class="fas fa-list"></i>
                <span>Pesanan</span>
            </button>
            <button class="nav-btn" data-section="customers">
                <i class="fas fa-users"></i>
                <span>Pelanggan</span>
            </button>
            <button class="nav-btn" data-section="settings">
                <i class="fas fa-cog"></i>
                <span>Pengaturan</span>
            </button>
        </div>

        <!-- Mobile Content Area -->
        <div class="mobile-content">
            <!-- Checkout Section -->
            <div class="section active" id="checkout-section">
                <!-- Product Search -->
                <div class="search-box">
                    <input type="text" id="productSearchMobile" placeholder="Cari / Scan produk...">
                </div>

                <!-- Product Grid -->
                <div class="product-grid" id="productGridMobile">
                    <!-- Products will be displayed here in grid -->
                </div>

                <!-- Cart Summary (Fixed Bottom) -->
                <div class="cart-summary-mobile">
                    <div class="summary-row">
                        <span>Total:</span>
                        <span id="totalMobile" class="amount">Rp 0</span>
                    </div>
                    <button class="btn-checkout-mobile">Bayar</button>
                </div>
            </div>

            <!-- Orders Section -->
            <div class="section" id="orders-section">
                <div class="section-header">
                    <h5>Pesanan</h5>
                </div>
                <div id="ordersMobileList">
                    <!-- Orders will be loaded here -->
                </div>
            </div>

            <!-- Customers Section -->
            <div class="section" id="customers-section">
                <div class="section-header">
                    <h5>Pelanggan</h5>
                </div>
                <div id="customersMobileList">
                    <!-- Customers will be loaded here -->
                </div>
            </div>

            <!-- Settings Section -->
            <div class="section" id="settings-section">
                <div class="section-header">
                    <h5>Pengaturan</h5>
                </div>
                <div class="settings-list">
                    <button class="settings-item" onclick="openSettings('general')">
                        <i class="fas fa-sliders-h"></i> Umum
                    </button>
                    <button class="settings-item" onclick="openSettings('printer')">
                        <i class="fas fa-print"></i> Printer
                    </button>
                    <button class="settings-item" onclick="openSettings('sync')">
                        <i class="fas fa-sync"></i> Sinkronisasi
                    </button>
                    <button class="settings-item" onclick="logout()">
                        <i class="fas fa-sign-out-alt"></i> Keluar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="../../asset/js/jquery.min.js"></script>
    <script src="../../asset/js/bootstrap.min.js"></script>
    <script src="mobile.js"></script>
</body>
</html>
