<?php
/**
 * Settings & Configuration
 * Role & Permission, Multi Outlet, Shift Kasir, Audit Log
 */

session_start();
require_once('../_init.php');

$access = new AccessControl('kasir_settings');
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
    <title>Settings - POS System</title>
    <link rel="stylesheet" href="../../asset/css/bootstrap.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container-fluid settings-container">
        <div class="header-section">
            <h2>Pengaturan</h2>
        </div>

        <!-- Settings Tabs -->
        <ul class="nav nav-tabs" role="tablist">
            <li class="nav-item">
                <a class="nav-link active" data-toggle="tab" href="#generalTab">Umum</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="tab" href="#shiftTab">Shift Kasir</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="tab" href="#printerTab">Printer</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="tab" href="#outletTab">Outlet</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="tab" href="#auditTab">Audit Log</a>
            </li>
        </ul>

        <div class="tab-content mt-3">
            <!-- General Settings -->
            <div id="generalTab" class="tab-pane fade show active">
                <div class="card">
                    <div class="card-body">
                        <h5>Pengaturan Umum POS</h5>
                        <form id="generalSettingsForm">
                            <div class="form-group">
                                <label>Nama Toko</label>
                                <input type="text" class="form-control" name="store_name">
                            </div>
                            <div class="form-group">
                                <label>Alamat</label>
                                <textarea class="form-control" name="store_address"></textarea>
                            </div>
                            <div class="form-group">
                                <label>Nomor Telepon</label>
                                <input type="tel" class="form-control" name="store_phone">
                            </div>
                            <div class="form-group">
                                <label>Email</label>
                                <input type="email" class="form-control" name="store_email">
                            </div>
                            <div class="form-group">
                                <label>Logo</label>
                                <input type="file" class="form-control" name="store_logo" accept="image/*">
                            </div>
                            <div class="form-group">
                                <label>
                                    <input type="checkbox" name="enable_loyalty"> Aktifkan Loyalty Point
                                </label>
                            </div>
                            <div class="form-group">
                                <label>
                                    <input type="checkbox" name="enable_membership"> Aktifkan Membership
                                </label>
                            </div>
                            <button type="submit" class="btn btn-primary">Simpan Pengaturan</button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Shift Settings -->
            <div id="shiftTab" class="tab-pane fade">
                <div class="card">
                    <div class="card-header">
                        <h5>Manajemen Shift</h5>
                    </div>
                    <div class="card-body">
                        <div id="currentShiftStatus">
                            <p><strong>Status Shift:</strong> <span id="shiftStatus">Closed</span></p>
                            <p><strong>Kasir:</strong> <span id="shiftCashier">-</span></p>
                            <p><strong>Jam Buka:</strong> <span id="shiftOpenTime">-</span></p>
                        </div>

                        <div class="form-group mt-3">
                            <label>Nominal Opening Cash</label>
                            <input type="number" class="form-control" id="openingCash" placeholder="Rp">
                        </div>

                        <button class="btn btn-success" id="openShiftBtn" onclick="openShift()">
                            Buka Shift
                        </button>
                        <button class="btn btn-warning" id="closeShiftBtn" onclick="closeShift()" style="display:none;">
                            Tutup Shift
                        </button>
                    </div>
                </div>

                <!-- Shift History -->
                <div class="card mt-3">
                    <div class="card-header">
                        <h5>Riwayat Shift</h5>
                    </div>
                    <table class="table table-striped" id="shiftHistoryTable">
                        <thead>
                            <tr>
                                <th>Tanggal</th>
                                <th>Kasir</th>
                                <th>Jam Buka</th>
                                <th>Jam Tutup</th>
                                <th>Opening Cash</th>
                                <th>Closing Cash</th>
                                <th>Selisih</th>
                            </tr>
                        </thead>
                        <tbody id="shiftHistoryBody">
                            <!-- Shift history will be loaded here -->
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Printer Settings -->
            <div id="printerTab" class="tab-pane fade">
                <div class="card">
                    <div class="card-body">
                        <h5>Pengaturan Printer</h5>
                        <div class="form-group">
                            <label>Printer Kasir</label>
                            <select class="form-control" id="cashierPrinter">
                                <option value="">Pilih Printer</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Printer Dapur (KDS)</label>
                            <select class="form-control" id="kitchenPrinter">
                                <option value="">Pilih Printer</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Printer Bar</label>
                            <select class="form-control" id="barPrinter">
                                <option value="">Pilih Printer</option>
                            </select>
                        </div>
                        <button class="btn btn-primary" onclick="testPrinters()">
                            Test Printer
                        </button>
                    </div>
                </div>
            </div>

            <!-- Outlet Settings -->
            <div id="outletTab" class="tab-pane fade">
                <button class="btn btn-primary mb-3" data-toggle="modal" data-target="#addOutletModal">
                    + Tambah Outlet
                </button>
                <table class="table table-striped" id="outletTable">
                    <thead>
                        <tr>
                            <th>Nama Outlet</th>
                            <th>Lokasi</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="outletBody">
                        <!-- Outlets will be loaded here -->
                    </tbody>
                </table>
            </div>

            <!-- Audit Log -->
            <div id="auditTab" class="tab-pane fade">
                <div class="search-section mb-3">
                    <input type="text" id="auditSearch" class="form-control" placeholder="Cari di audit log...">
                </div>
                <table class="table table-striped table-sm" id="auditTable">
                    <thead>
                        <tr>
                            <th>Waktu</th>
                            <th>User</th>
                            <th>Aksi</th>
                            <th>Detail</th>
                            <th>Perubahan</th>
                        </tr>
                    </thead>
                    <tbody id="auditBody">
                        <!-- Audit logs will be loaded here -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Add Outlet Modal -->
    <div class="modal fade" id="addOutletModal">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Tambah Outlet</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <form id="addOutletForm">
                    <div class="modal-body">
                        <div class="form-group">
                            <label>Nama Outlet</label>
                            <input type="text" class="form-control" name="outlet_name" required>
                        </div>
                        <div class="form-group">
                            <label>Lokasi/Alamat</label>
                            <textarea class="form-control" name="location" required></textarea>
                        </div>
                        <div class="form-group">
                            <label>Kontak</label>
                            <input type="tel" class="form-control" name="contact">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">Tambah</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="../../asset/js/jquery.min.js"></script>
    <script src="../../asset/js/bootstrap.min.js"></script>
    <script src="settings.js"></script>
</body>
</html>
