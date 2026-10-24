<?php
/**
 * Digital Receipt Module
 * Struk via WhatsApp/email/PDF, Multiple Printer support
 */

session_start();
require_once('../_init.php');

$access = new AccessControl('kasir_receipts');
if (!$access->canAccess()) {
    header('Location: ../unauthorized.php');
    exit;
}

$transactionId = $_GET['id'] ?? null;

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt - POS System</title>
    <link rel="stylesheet" href="../../asset/css/bootstrap.min.css">
    <link rel="stylesheet" href="receipt-style.css">
</head>
<body>
    <div class="receipt-container">
        <!-- Receipt Preview -->
        <div id="receiptPreview" class="receipt-preview">
            <div class="receipt-header">
                <h3>TOKO ANDA</h3>
                <p>Jl. Contoh Alamat No. 123</p>
                <p>Telp: 021-1234567</p>
            </div>

            <div class="receipt-divider">----------------------------</div>

            <div class="receipt-details">
                <p>No. Transaksi: <span id="transNo"></span></p>
                <p>Tanggal: <span id="transDate"></span></p>
                <p>Kasir: <span id="cashierName"></span></p>
            </div>

            <div class="receipt-divider">----------------------------</div>

            <table class="receipt-items">
                <tbody id="receiptItems">
                    <!-- Items will be loaded here -->
                </tbody>
            </table>

            <div class="receipt-divider">----------------------------</div>

            <div class="receipt-summary">
                <div><span>Subtotal:</span> <span id="subtotalReceipt">Rp 0</span></div>
                <div><span>Diskon:</span> <span id="discountReceipt">Rp 0</span></div>
                <div class="total-line">
                    <span>TOTAL:</span> <span id="totalReceipt">Rp 0</span>
                </div>
                <div><span>Pembayaran:</span> <span id="paymentReceipt">Rp 0</span></div>
                <div><span>Kembali:</span> <span id="changeReceipt">Rp 0</span></div>
            </div>

            <div class="receipt-divider">----------------------------</div>

            <div class="receipt-footer">
                <p>Terima kasih atas kunjungan Anda!</p>
                <p>Selamat datang kembali!</p>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="button-section mt-3">
            <button class="btn btn-primary" id="printBtn">
                <i class="fas fa-print"></i> Cetak
            </button>
            <button class="btn btn-info" id="whatsappBtn">
                <i class="fab fa-whatsapp"></i> Kirim WhatsApp
            </button>
            <button class="btn btn-warning" id="emailBtn">
                <i class="fas fa-envelope"></i> Kirim Email
            </button>
            <button class="btn btn-success" id="pdfBtn">
                <i class="fas fa-file-pdf"></i> Download PDF
            </button>
            <button class="btn btn-secondary" id="doneBtn">
                Selesai
            </button>
        </div>
    </div>

    <script src="../../asset/js/jquery.min.js"></script>
    <script src="../../asset/js/bootstrap.min.js"></script>
    <script src="receipt.js"></script>
</body>
</html>
