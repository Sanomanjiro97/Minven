<?php
/**
 * Offline Mode
 * Tetap transaksi ketika internet putus
 * Auto Sync transaksi offline ketika online
 */

session_start();
require_once('../_init.php');

$access = new AccessControl('kasir_offline');
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
    <title>Offline Mode - POS System</title>
    <link rel="stylesheet" href="../../asset/css/bootstrap.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container-fluid offline-container">
        <!-- Status Bar -->
        <div class="status-bar" id="statusBar">
            <span class="status-indicator" id="connectionStatus"></span>
            <span id="statusText">Memeriksa koneksi...</span>
        </div>

        <!-- Offline Transactions Queue -->
        <div class="card mt-3">
            <div class="card-header">
                <h5>
                    Antrian Transaksi Offline
                    <span class="badge badge-warning" id="offlineQueueCount">0</span>
                </h5>
            </div>
            <div class="card-body">
                <div id="offlineQueueList">
                    <p class="text-muted text-center">Tidak ada transaksi offline</p>
                </div>
            </div>
        </div>

        <!-- Sync Status -->
        <div class="card mt-3">
            <div class="card-header">
                <h5>Status Sinkronisasi</h5>
            </div>
            <div class="card-body">
                <div class="alert alert-info" id="syncStatus" style="display:none;">
                    <i class="fas fa-sync-alt fa-spin"></i>
                    <span id="syncStatusText">Menyinkronkan data...</span>
                </div>
                <div class="alert alert-success" id="syncSuccess" style="display:none;">
                    <i class="fas fa-check-circle"></i>
                    <span id="syncSuccessText">Semua data sudah tersinkronisasi!</span>
                </div>
                <div class="alert alert-danger" id="syncError" style="display:none;">
                    <i class="fas fa-exclamation-circle"></i>
                    <span id="syncErrorText">Gagal menyinkronkan data</span>
                </div>

                <!-- Manual Sync Button -->
                <button class="btn btn-primary" id="manualSyncBtn" onclick="manualSync()">
                    <i class="fas fa-sync"></i> Sinkronisasi Manual
                </button>
            </div>
        </div>

        <!-- Offline Stats -->
        <div class="row mt-3">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body text-center">
                        <h6>Transaksi Offline</h6>
                        <h3 id="offlineTransCount">0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body text-center">
                        <h6>Total Nilai</h6>
                        <h3 id="offlineTotalValue">Rp 0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body text-center">
                        <h6>Storage Terpakai</h6>
                        <h3 id="storageUsed">0 MB</h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Cache Management -->
        <div class="card mt-3">
            <div class="card-header">
                <h5>Manajemen Cache</h5>
            </div>
            <div class="card-body">
                <p>Hapus cache lokal untuk membebaskan ruang penyimpanan</p>
                <button class="btn btn-danger" onclick="clearCache()">
                    <i class="fas fa-trash"></i> Hapus Cache
                </button>
            </div>
        </div>
    </div>

    <script src="../../asset/js/jquery.min.js"></script>
    <script src="../../asset/js/bootstrap.min.js"></script>
    <script src="offline.js"></script>
</body>
</html>
