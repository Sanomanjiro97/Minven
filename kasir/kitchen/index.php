<?php
/**
 * Kitchen Display System (KDS)
 * Pesanan langsung masuk layar dapur, status realtime
 */

session_start();
require_once('../_init.php');

$access = new AccessControl('kasir_kitchen');
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
    <title>Kitchen Display System - POS</title>
    <link rel="stylesheet" href="../../asset/css/bootstrap.min.css">
    <link rel="stylesheet" href="kds-style.css">
</head>
<body>
    <div class="kds-container">
        <div class="kds-header">
            <h1>KITCHEN DISPLAY SYSTEM</h1>
            <p id="currentTime"></p>
        </div>

        <!-- Order Queues -->
        <div class="row kds-orders">
            <!-- New Orders (To Do) -->
            <div class="col-md-4">
                <div class="order-status-card pending">
                    <h3>📋 Pesanan Baru</h3>
                    <div class="orders-list" id="pendingOrders">
                        <!-- New orders will be displayed here -->
                    </div>
                </div>
            </div>

            <!-- In Progress -->
            <div class="col-md-4">
                <div class="order-status-card in-progress">
                    <h3>👨‍🍳 Sedang Dimasak</h3>
                    <div class="orders-list" id="inProgressOrders">
                        <!-- In-progress orders will be displayed here -->
                    </div>
                </div>
            </div>

            <!-- Ready -->
            <div class="col-md-4">
                <div class="order-status-card ready">
                    <h3>✅ Siap Ambil</h3>
                    <div class="orders-list" id="readyOrders">
                        <!-- Ready orders will be displayed here -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Sound notification (order alert) -->
    <audio id="orderAlert" preload="auto">
        <source src="../../asset/sounds/order-alert.mp3" type="audio/mpeg">
    </audio>

    <script src="../../asset/js/jquery.min.js"></script>
    <script src="../../asset/js/bootstrap.min.js"></script>
    <script src="kds.js"></script>
</body>
</html>
