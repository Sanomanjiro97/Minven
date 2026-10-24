<?php
/**
 * Customer Management Module
 * Member, pelanggan, riwayat pembelian, Loyalty Point
 */

session_start();
require_once('../_init.php');

$access = new AccessControl('kasir_customers');
if (!$access->canAccess()) {
    header('Location: ../unauthorized.php');
    exit;
}

// Get all customers
$customers = []; // TODO: Load from database
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Management - POS System</title>
    <link rel="stylesheet" href="../../asset/css/bootstrap.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container-fluid customer-container">
        <div class="header-section">
            <h2>Manajemen Pelanggan</h2>
            <button class="btn btn-primary" data-toggle="modal" data-target="#addCustomerModal">
                + Tambah Pelanggan
            </button>
        </div>

        <!-- Search Section -->
        <div class="search-section">
            <input type="text" id="customerSearch" class="form-control" placeholder="Cari pelanggan...">
        </div>

        <!-- Customers Table -->
        <table class="table table-striped" id="customerTable">
            <thead>
                <tr>
                    <th>Nama</th>
                    <th>Nomor HP</th>
                    <th>Total Pembelian</th>
                    <th>Poin Loyalitas</th>
                    <th>Member Sejak</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody id="customerBody">
                <!-- Customers list will be loaded here -->
            </tbody>
        </table>
    </div>

    <!-- Add Customer Modal -->
    <div class="modal fade" id="addCustomerModal">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Tambah Pelanggan Baru</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <form id="addCustomerForm">
                    <div class="modal-body">
                        <div class="form-group">
                            <label>Nama</label>
                            <input type="text" class="form-control" name="name" required>
                        </div>
                        <div class="form-group">
                            <label>Nomor HP</label>
                            <input type="tel" class="form-control" name="phone" required>
                        </div>
                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" class="form-control" name="email">
                        </div>
                        <div class="form-group">
                            <label>Alamat</label>
                            <textarea class="form-control" name="address"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Customer Details Modal -->
    <div class="modal fade" id="customerDetailsModal">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Detail Pelanggan</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body" id="customerDetailsBody">
                    <!-- Customer details will be loaded here -->
                </div>
            </div>
        </div>
    </div>

    <script src="../../asset/js/jquery.min.js"></script>
    <script src="../../asset/js/bootstrap.min.js"></script>
    <script src="customers.js"></script>
</body>
</html>
