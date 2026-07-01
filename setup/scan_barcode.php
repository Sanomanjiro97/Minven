<?php
session_start();
require_once '../config.php';
require_once '../includes/access_check.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

if (!checkAccess('barcode', 'view')) {
    $_SESSION['error'] = 'Anda tidak memiliki akses ke halaman Setup Scan Barcode!';
    header('Location: /minven_pro/dashboard.php');
    exit();
}

$createBarcodeConfigTable = "CREATE TABLE IF NOT EXISTS setup_barcode_config (
    id TINYINT UNSIGNED PRIMARY KEY,
    mode ENUM('scanner','hp') NOT NULL DEFAULT 'scanner',
    is_connected TINYINT(1) NOT NULL DEFAULT 0,
    last_connected_at DATETIME DEFAULT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by INT DEFAULT NULL
)";
$conn->query($createBarcodeConfigTable);
$conn->query("INSERT INTO setup_barcode_config (id, mode, is_connected) VALUES (1, 'scanner', 0) ON DUPLICATE KEY UPDATE id = id");

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_barcode_setup'])) {
    if (!checkAccess('barcode', 'edit')) {
        $_SESSION['error'] = 'Anda tidak memiliki akses untuk menyimpan setup barcode!';
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }

    $barcode_mode_input = (isset($_POST['barcode_mode']) && $_POST['barcode_mode'] === 'hp') ? 'hp' : 'scanner';
    $barcode_is_connected_input = (isset($_POST['barcode_is_connected']) && (int)$_POST['barcode_is_connected'] === 1) ? 1 : 0;
    $barcode_last_connected_input = isset($_POST['barcode_last_connected_at']) ? trim((string)$_POST['barcode_last_connected_at']) : '';
    if ($barcode_last_connected_input === '') {
        $barcode_last_connected_input = null;
    }

    $saveBarcodeSql = "UPDATE setup_barcode_config 
                       SET mode = ?, is_connected = ?, last_connected_at = ?, updated_by = ? 
                       WHERE id = 1";
    $saveBarcodeStmt = $conn->prepare($saveBarcodeSql);
    $saveBarcodeStmt->bind_param("sisi", $barcode_mode_input, $barcode_is_connected_input, $barcode_last_connected_input, $_SESSION['user_id']);
    $saveBarcodeStmt->execute();

    $_SESSION['success'] = "Pengaturan koneksi barcode berhasil disimpan!";
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

$barcode_config = [
    'mode' => 'scanner',
    'is_connected' => 0,
    'last_connected_at' => null
];
$barcodeResult = $conn->query("SELECT mode, is_connected, last_connected_at FROM setup_barcode_config WHERE id = 1 LIMIT 1");
if ($barcodeResult && $barcodeResult->num_rows > 0) {
    $barcode_config = $barcodeResult->fetch_assoc();
}

$barcode_mode = ($barcode_config['mode'] ?? 'scanner') === 'hp' ? 'hp' : 'scanner';
$barcode_is_connected = (int)($barcode_config['is_connected'] ?? 0);
$barcode_last_connected_at = !empty($barcode_config['last_connected_at']) ? date('d/m/Y H:i:s', strtotime($barcode_config['last_connected_at'])) : '-';
$barcode_status_class = $barcode_is_connected === 1 ? 'status-connected' : 'status-disconnected';
$barcode_status_text = $barcode_is_connected === 1
    ? "Terhubung (terakhir berhasil: {$barcode_last_connected_at})"
    : "Belum terhubung";
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup Scan Barcode - MINVEN PRO</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background: #ffffff;
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .card {
            border-radius: 0.75rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            background: #ffffff;
            border: 1px solid #e2e8f0;
        }

        .card-header {
            background: #0008f9;
            color: white;
            border-radius: 0.75rem 0.75rem 0 0 !important;
            padding: 15px 20px;
        }

        .form-control:focus, .form-select:focus {
            border-color: #0008f9;
            box-shadow: 0 0 0 0.2rem rgba(0, 8, 249, 0.25);
        }

        .btn-primary {
            background: #0008f9;
            border: none;
            border-radius: 8px;
        }

        .btn-primary:hover {
            background: #0006d4;
        }

        .status-lamp {
            width: 14px;
            height: 14px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 8px;
        }

        .status-connected {
            background-color: #28a745;
            box-shadow: 0 0 0 0.2rem rgba(40, 167, 69, 0.25);
        }

        .status-disconnected {
            background-color: #dc3545;
            box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25);
        }
    </style>
</head>
<body>
    <?php include '../templates/navbar.php'; ?>

    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <div class="card shadow">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="bi bi-upc-scan me-2"></i>
                            Setup Scan Barcode
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (isset($_SESSION['success'])): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="bi bi-check-circle me-1"></i> <?= $_SESSION['success']; unset($_SESSION['success']); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <?php if (isset($_SESSION['error'])): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="bi bi-exclamation-circle me-1"></i> <?= $_SESSION['error']; unset($_SESSION['error']); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <form method="POST" id="barcodeSetupForm">
                            <div class="row g-3 align-items-end">
                                <div class="col-md-4">
                                    <label for="barcode_mode" class="form-label">Mode Koneksi</label>
                                    <select class="form-select" id="barcode_mode" name="barcode_mode">
                                        <option value="scanner" <?= $barcode_mode === 'scanner' ? 'selected' : '' ?>>Alat Scanner (USB/Bluetooth)</option>
                                        <option value="hp" <?= $barcode_mode === 'hp' ? 'selected' : '' ?>>HP (Kamera Browser)</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label d-block">Status Koneksi</label>
                                    <div class="d-flex align-items-center">
                                        <span id="barcodeStatusLamp" class="status-lamp <?= $barcode_status_class ?>"></span>
                                        <strong id="barcodeStatusText"><?= htmlspecialchars($barcode_status_text) ?></strong>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="d-flex gap-2">
                                        <button type="button" class="btn btn-outline-primary" id="testBarcodeConnectionBtn">
                                            <i class="bi bi-wifi me-1"></i>Tes Koneksi
                                        </button>
                                        <button type="submit" name="save_barcode_setup" class="btn btn-primary">
                                            <i class="bi bi-save me-1"></i>Simpan Setup
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <input type="hidden" id="barcode_is_connected" name="barcode_is_connected" value="<?= $barcode_is_connected === 1 ? '1' : '0' ?>">
                            <input type="hidden" id="barcode_last_connected_at" name="barcode_last_connected_at" value="<?= !empty($barcode_config['last_connected_at']) ? htmlspecialchars((string)$barcode_config['last_connected_at']) : '' ?>">
                            <small class="text-muted d-block mt-3">
                                Jalankan tes koneksi sesuai mode. Lampu hijau berarti terhubung, lampu merah berarti belum terhubung.
                            </small>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.js"></script>
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    <script>
        let setupBarcodeScanner = null;
        let scannerInputListenerAttached = false;

        function getCurrentSqlDatetime() {
            const now = new Date();
            const yyyy = now.getFullYear();
            const mm = String(now.getMonth() + 1).padStart(2, '0');
            const dd = String(now.getDate()).padStart(2, '0');
            const hh = String(now.getHours()).padStart(2, '0');
            const mi = String(now.getMinutes()).padStart(2, '0');
            const ss = String(now.getSeconds()).padStart(2, '0');
            return `${yyyy}-${mm}-${dd} ${hh}:${mi}:${ss}`;
        }

        function setBarcodeConnectionStatus(isConnected, noteText) {
            const lamp = document.getElementById('barcodeStatusLamp');
            const text = document.getElementById('barcodeStatusText');
            const hiddenConnected = document.getElementById('barcode_is_connected');
            const hiddenLastConnected = document.getElementById('barcode_last_connected_at');
            if (!lamp || !text || !hiddenConnected || !hiddenLastConnected) return;

            if (isConnected) {
                lamp.classList.remove('status-disconnected');
                lamp.classList.add('status-connected');
                hiddenConnected.value = '1';
                hiddenLastConnected.value = getCurrentSqlDatetime();
                text.textContent = noteText || 'Terhubung';
            } else {
                lamp.classList.remove('status-connected');
                lamp.classList.add('status-disconnected');
                hiddenConnected.value = '0';
                text.textContent = noteText || 'Belum terhubung';
            }
        }

        function setConnectionTestMessage(message, typeClass) {
            const msg = document.getElementById('barcodeConnectionTestMsg');
            if (!msg) return;
            msg.classList.remove('alert-success', 'alert-danger', 'alert-info', 'd-none');
            msg.classList.add(typeClass || 'alert-info');
            msg.textContent = message;
        }

        async function stopSetupBarcodeScanner() {
            if (!setupBarcodeScanner) return;
            try {
                if (setupBarcodeScanner.isScanning) {
                    await setupBarcodeScanner.stop();
                }
                await setupBarcodeScanner.clear();
            } catch (err) {
                console.error(err);
            } finally {
                setupBarcodeScanner = null;
                const readerEl = document.getElementById('setup-barcode-reader');
                if (readerEl) readerEl.innerHTML = '';
            }
        }

        async function startHpConnectionTest() {
            const readerWrap = document.getElementById('setupBarcodeReaderWrap');
            const scannerWrap = document.getElementById('setupScannerInputWrap');
            if (readerWrap) readerWrap.classList.remove('d-none');
            if (scannerWrap) scannerWrap.classList.add('d-none');

            if (typeof Html5Qrcode === 'undefined') {
                setConnectionTestMessage('Library scanner tidak tersedia.', 'alert-danger');
                setBarcodeConnectionStatus(false, 'Belum terhubung');
                return;
            }

            try {
                setupBarcodeScanner = new Html5Qrcode('setup-barcode-reader');
                await setupBarcodeScanner.start(
                    { facingMode: 'environment' },
                    { fps: 10, qrbox: { width: 250, height: 120 } },
                    async (decodedText) => {
                        if (!decodedText) return;
                        setBarcodeConnectionStatus(true, 'Terhubung (HP scanner aktif)');
                        setConnectionTestMessage('Scan berhasil dibaca dari HP/kamera.', 'alert-success');
                        const modalEl = document.getElementById('barcodeConnectionModal');
                        if (modalEl) {
                            const modal = bootstrap.Modal.getInstance(modalEl);
                            if (modal) {
                                setTimeout(() => modal.hide(), 600);
                            }
                        }
                    }
                );
                setConnectionTestMessage('Arahkan kamera ke barcode untuk mengetes koneksi HP.', 'alert-info');
            } catch (err) {
                setConnectionTestMessage('Kamera tidak bisa diakses. Izinkan akses kamera di browser.', 'alert-danger');
                setBarcodeConnectionStatus(false, 'Belum terhubung');
                console.error(err);
            }
        }

        function startScannerConnectionTest() {
            const readerWrap = document.getElementById('setupBarcodeReaderWrap');
            const scannerWrap = document.getElementById('setupScannerInputWrap');
            const scannerInput = document.getElementById('setupScannerInput');
            if (readerWrap) readerWrap.classList.add('d-none');
            if (scannerWrap) scannerWrap.classList.remove('d-none');
            if (scannerInput) {
                scannerInput.value = '';
                scannerInput.focus();
            }
            setConnectionTestMessage('Scan barcode menggunakan alat scanner lalu tekan Enter.', 'alert-info');

            if (scannerInput && !scannerInputListenerAttached) {
                scannerInput.addEventListener('keydown', function(e) {
                    if (e.key !== 'Enter') return;
                    e.preventDefault();
                    const value = String(scannerInput.value || '').trim();
                    if (value.length >= 3) {
                        setBarcodeConnectionStatus(true, 'Terhubung (scanner terdeteksi)');
                        setConnectionTestMessage('Input scanner terbaca. Koneksi berhasil.', 'alert-success');
                        const modalEl = document.getElementById('barcodeConnectionModal');
                        if (modalEl) {
                            const modal = bootstrap.Modal.getInstance(modalEl);
                            if (modal) {
                                setTimeout(() => modal.hide(), 500);
                            }
                        }
                    } else {
                        setBarcodeConnectionStatus(false, 'Belum terhubung');
                        setConnectionTestMessage('Data scan tidak valid. Coba scan ulang.', 'alert-danger');
                    }
                });
                scannerInputListenerAttached = true;
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            const testBtn = document.getElementById('testBarcodeConnectionBtn');
            const modalEl = document.getElementById('barcodeConnectionModal');
            const modeEl = document.getElementById('barcode_mode');
            const markFailBtn = document.getElementById('markBarcodeFailedBtn');

            if (testBtn && modalEl) {
                testBtn.addEventListener('click', async function() {
                    await stopSetupBarcodeScanner();
                    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
                    modal.show();

                    const mode = modeEl ? modeEl.value : 'scanner';
                    if (mode === 'hp') {
                        startHpConnectionTest();
                    } else {
                        startScannerConnectionTest();
                    }
                });
            }

            if (modalEl) {
                modalEl.addEventListener('hidden.bs.modal', function() {
                    stopSetupBarcodeScanner();
                });
            }

            if (markFailBtn) {
                markFailBtn.addEventListener('click', function() {
                    setBarcodeConnectionStatus(false, 'Belum terhubung');
                    setConnectionTestMessage('Status ditandai belum terhubung.', 'alert-danger');
                });
            }
        });
    </script>

    <div class="modal fade" id="barcodeConnectionModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Tes Koneksi Barcode</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="barcodeConnectionTestMsg" class="alert alert-info mb-3">
                        Menyiapkan pengujian koneksi...
                    </div>
                    <div id="setupBarcodeReaderWrap" class="d-none">
                        <div id="setup-barcode-reader" style="width:100%; min-height:240px;"></div>
                    </div>
                    <div id="setupScannerInputWrap" class="d-none">
                        <label for="setupScannerInput" class="form-label">Input Tes Scanner</label>
                        <input type="text" id="setupScannerInput" class="form-control" placeholder="Fokus di sini, lalu scan barcode dengan alat scanner">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" id="markBarcodeFailedBtn" class="btn btn-outline-danger">Tandai Tidak Terhubung</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
