<?php
/**
 * Orders Management Module
 * Order Type: Dine-in, Takeaway, Delivery
 * Table Management for cafe/restaurant
 */

session_start();
require_once('../_init.php');

$access = new AccessControl('kasir_orders');
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
    <title>Orders - POS System</title>
    <link rel="stylesheet" href="../../asset/css/bootstrap.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container-fluid orders-container">
        <div class="header-section">
            <h2>Manajemen Pesanan</h2>
            <button class="btn btn-primary" data-toggle="modal" data-target="#newOrderModal">
                + Pesanan Baru
            </button>
        </div>

        <!-- Filter Tabs -->
        <ul class="nav nav-tabs" role="tablist">
            <li class="nav-item">
                <a class="nav-link active" data-toggle="tab" href="#allOrders">Semua</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="tab" href="#dineInOrders">Dine-in</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="tab" href="#takeAwayOrders">Takeaway</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="tab" href="#deliveryOrders">Delivery</a>
            </li>
        </ul>

        <!-- Tab Content -->
        <div class="tab-content">
            <div id="allOrders" class="tab-pane fade show active">
                <table class="table table-striped" id="allOrdersTable">
                    <thead>
                        <tr>
                            <th>No. Pesanan</th>
                            <th>Tipe</th>
                            <th>Meja/Lokasi</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="allOrdersBody">
                        <!-- Orders will be loaded here -->
                    </tbody>
                </table>
            </div>
            <div id="dineInOrders" class="tab-pane fade">
                <!-- Dine-in orders -->
            </div>
            <div id="takeAwayOrders" class="tab-pane fade">
                <!-- Takeaway orders -->
            </div>
            <div id="deliveryOrders" class="tab-pane fade">
                <!-- Delivery orders -->
            </div>
        </div>
    </div>

    <!-- New Order Modal -->
    <div class="modal fade" id="newOrderModal">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Pesanan Baru</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <form id="newOrderForm">
                    <div class="modal-body">
                        <div class="form-group">
                            <label>Tipe Pesanan</label>
                            <select class="form-control" name="order_type" required>
                                <option value="">Pilih Tipe</option>
                                <option value="dine_in">Dine-in</option>
                                <option value="takeaway">Takeaway</option>
                                <option value="delivery">Delivery</option>
                            </select>
                        </div>
                        <div class="form-group" id="tableGroup" style="display:none;">
                            <label>Nomor Meja</label>
                            <input type="text" class="form-control" name="table_number">
                        </div>
                        <div class="form-group" id="addressGroup" style="display:none;">
                            <label>Alamat Pengiriman</label>
                            <textarea class="form-control" name="delivery_address"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">Lanjut</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="../../asset/js/jquery.min.js"></script>
    <script src="../../asset/js/bootstrap.min.js"></script>
    <script src="orders.js"></script>
</body>
</html>
