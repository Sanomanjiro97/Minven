<?php
/**
 * Inventory Management Module
 * Auto Inventory, Recipe/BOM, Stock Reservation, Low Stock Alert
 */

session_start();
require_once('../_init.php');

$access = new AccessControl('kasir_inventory');
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
    <title>Inventory Management - POS System</title>
    <link rel="stylesheet" href="../../asset/css/bootstrap.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container-fluid inventory-container">
        <div class="header-section">
            <h2>Manajemen Inventori</h2>
        </div>

        <!-- Tabs -->
        <ul class="nav nav-tabs" role="tablist">
            <li class="nav-item">
                <a class="nav-link active" data-toggle="tab" href="#stockTab">Stok Produk</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="tab" href="#reservationTab">Stok Ditahan</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="tab" href="#lowStockTab">
                    Stok Minimum
                    <span class="badge badge-danger" id="lowStockCount">0</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="tab" href="#recipeTab">Resep/BOM</a>
            </li>
        </ul>

        <!-- Tab Content -->
        <div class="tab-content mt-3">
            <!-- Stock Tab -->
            <div id="stockTab" class="tab-pane fade show active">
                <div class="search-section mb-3">
                    <input type="text" id="productSearch" class="form-control" placeholder="Cari produk...">
                </div>
                <table class="table table-striped" id="stockTable">
                    <thead>
                        <tr>
                            <th>Produk</th>
                            <th>Stok Saat Ini</th>
                            <th>Stok Minimum</th>
                            <th>Stok Ditahan</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="stockBody">
                        <!-- Stock items will be loaded here -->
                    </tbody>
                </table>
            </div>

            <!-- Reservation Tab -->
            <div id="reservationTab" class="tab-pane fade">
                <div class="alert alert-info">
                    Stok yang sedang digunakan/ditahan bisa diperhitungkan dalam kalkulasi stok tersedia
                </div>
                <table class="table table-striped" id="reservationTable">
                    <thead>
                        <tr>
                            <th>Produk</th>
                            <th>Qty Ditahan</th>
                            <th>Alasan</th>
                            <th>Tanggal</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="reservationBody">
                        <!-- Reservations will be loaded here -->
                    </tbody>
                </table>
            </div>

            <!-- Low Stock Tab -->
            <div id="lowStockTab" class="tab-pane fade">
                <table class="table table-striped table-warning" id="lowStockTable">
                    <thead>
                        <tr>
                            <th>Produk</th>
                            <th>Stok Saat Ini</th>
                            <th>Stok Minimum</th>
                            <th>Kekurangan</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="lowStockBody">
                        <!-- Low stock items will be loaded here -->
                    </tbody>
                </table>
            </div>

            <!-- Recipe/BOM Tab -->
            <div id="recipeTab" class="tab-pane fade">
                <button class="btn btn-primary mb-3" data-toggle="modal" data-target="#recipeModal">
                    + Tambah Resep
                </button>
                <table class="table table-striped" id="recipeTable">
                    <thead>
                        <tr>
                            <th>Produk Jadi</th>
                            <th>Bahan Baku</th>
                            <th>Qty</th>
                            <th>Satuan</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="recipeBody">
                        <!-- Recipes will be loaded here -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Recipe Modal -->
    <div class="modal fade" id="recipeModal">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Tambah Resep</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <form id="recipeForm">
                    <div class="modal-body">
                        <div class="form-group">
                            <label>Produk Jadi</label>
                            <select class="form-control" name="finished_product" required>
                                <option value="">Pilih Produk</option>
                                <!-- Products will be loaded here -->
                            </select>
                        </div>
                        <div id="ingredientsContainer">
                            <div class="ingredient-row">
                                <div class="form-group">
                                    <label>Bahan Baku</label>
                                    <select class="form-control ingredient-select" required>
                                        <option value="">Pilih Bahan</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Jumlah</label>
                                    <input type="number" class="form-control ingredient-qty" required>
                                </div>
                            </div>
                        </div>
                        <button type="button" class="btn btn-sm btn-info" onclick="addIngredient()">
                            + Tambah Bahan
                        </button>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="../../asset/js/jquery.min.js"></script>
    <script src="../../asset/js/bootstrap.min.js"></script>
    <script src="inventory.js"></script>
</body>
</html>
