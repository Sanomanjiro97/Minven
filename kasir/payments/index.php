<?php
/**
 * Payment Processing Module
 * Multi Payment, Split Payment, Split Bill
 */

session_start();
require_once('../_init.php');

$access = new AccessControl('kasir_payments');
if (!$access->canAccess()) {
    header('Location: ../unauthorized.php');
    exit;
}

// Get transaction data
$items = json_decode($_GET['items'] ?? '[]', true);
$total = isset($_GET['total']) ? floatval($_GET['total']) : 0;

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment - POS System</title>
    <link rel="stylesheet" href="../../asset/css/bootstrap.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container payment-container">
        <h2>Pembayaran</h2>
        
        <!-- Total Amount -->
        <div class="amount-section">
            <h3>Total: <span class="amount">Rp <?php echo number_format($total); ?></span></h3>
        </div>

        <!-- Payment Methods -->
        <div class="payment-methods">
            <div class="row">
                <div class="col-md-4">
                    <button class="method-btn" data-method="cash">
                        <i class="fas fa-money-bill"></i>
                        <p>Cash</p>
                    </button>
                </div>
                <div class="col-md-4">
                    <button class="method-btn" data-method="qris">
                        <i class="fas fa-qrcode"></i>
                        <p>QRIS</p>
                    </button>
                </div>
                <div class="col-md-4">
                    <button class="method-btn" data-method="debit">
                        <i class="fas fa-credit-card"></i>
                        <p>Debit/Kredit</p>
                    </button>
                </div>
                <div class="col-md-4">
                    <button class="method-btn" data-method="ewallet">
                        <i class="fas fa-wallet"></i>
                        <p>E-Wallet</p>
                    </button>
                </div>
                <div class="col-md-4">
                    <button class="method-btn" data-method="transfer">
                        <i class="fas fa-university"></i>
                        <p>Transfer Bank</p>
                    </button>
                </div>
                <div class="col-md-4">
                    <button class="method-btn" data-method="split">
                        <i class="fas fa-code-branch"></i>
                        <p>Pembayaran Ganda</p>
                    </button>
                </div>
            </div>
        </div>

        <!-- Payment Details -->
        <div id="paymentDetails">
            <!-- Will be filled based on selected method -->
        </div>

        <!-- Split Payment Container -->
        <div id="splitPaymentContainer" style="display:none;">
            <h4>Pembayaran Ganda</h4>
            <div id="splitPayments">
                <div class="split-payment-item">
                    <select class="method-select">
                        <option>Pilih Metode</option>
                        <option value="cash">Cash</option>
                        <option value="qris">QRIS</option>
                        <option value="debit">Debit</option>
                        <option value="ewallet">E-Wallet</option>
                        <option value="transfer">Transfer</option>
                    </select>
                    <input type="number" placeholder="Jumlah" class="amount-input">
                </div>
            </div>
            <button class="btn btn-sm btn-info" onclick="addSplitPayment()">+ Tambah Pembayaran</button>
        </div>

        <!-- Action Buttons -->
        <div class="button-section">
            <button class="btn btn-secondary" onclick="history.back()">Kembali</button>
            <button class="btn btn-success btn-lg" id="confirmPaymentBtn">Proses Pembayaran</button>
        </div>
    </div>

    <script src="../../asset/js/jquery.min.js"></script>
    <script src="../../asset/js/bootstrap.min.js"></script>
    <script src="payment.js"></script>
</body>
</html>
