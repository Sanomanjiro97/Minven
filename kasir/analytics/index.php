<?php
/**
 * Analytics & Dashboard
 * Dashboard Real-time, Omzet, transaksi, profit, produk terlaris, pembayaran
 * Sales Analytics, Profit Analysis
 */

session_start();
require_once('../_init.php');

$access = new AccessControl('kasir_analytics');
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
    <title>Analytics Dashboard - POS System</title>
    <link rel="stylesheet" href="../../asset/css/bootstrap.min.css">
    <link rel="stylesheet" href="style.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
</head>
<body>
    <div class="container-fluid analytics-container">
        <div class="header-section">
            <h2>Analytics Dashboard</h2>
            <div class="date-filter">
                <input type="date" id="startDate" class="form-control d-inline" style="width: auto;">
                <span>-</span>
                <input type="date" id="endDate" class="form-control d-inline" style="width: auto;">
                <button class="btn btn-primary" id="filterBtn">Filter</button>
            </div>
        </div>

        <!-- KPI Cards -->
        <div class="row kpi-cards">
            <div class="col-md-3">
                <div class="card kpi-card">
                    <div class="card-body">
                        <h6>Total Omzet</h6>
                        <h3 id="totalOmzet">Rp 0</h3>
                        <small id="omzetChange" class="text-success">+15% dari kemarin</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card kpi-card">
                    <div class="card-body">
                        <h6>Total Transaksi</h6>
                        <h3 id="totalTransactions">0</h3>
                        <small>Jumlah transaksi</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card kpi-card">
                    <div class="card-body">
                        <h6>Profit Kotor</h6>
                        <h3 id="grossProfit">Rp 0</h3>
                        <small id="profitMargin">Margin: 0%</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card kpi-card">
                    <div class="card-body">
                        <h6>Rata-rata Transaksi</h6>
                        <h3 id="avgTransaction">Rp 0</h3>
                        <small>Per transaksi</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts -->
        <div class="row charts-section">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5>Penjualan per Jam</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="hourlyChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5>Metode Pembayaran</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="paymentMethodChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Top Products -->
        <div class="row mt-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5>Produk Terlaris</h5>
                    </div>
                    <div class="card-body">
                        <table class="table table-sm" id="topProductsTable">
                            <thead>
                                <tr>
                                    <th>Produk</th>
                                    <th>Terjual</th>
                                    <th>Total</th>
                                </tr>
                            </thead>
                            <tbody id="topProductsBody">
                                <!-- Top products will be loaded here -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5>Performa Kasir</h5>
                    </div>
                    <div class="card-body">
                        <table class="table table-sm" id="cashierPerformanceTable">
                            <thead>
                                <tr>
                                    <th>Kasir</th>
                                    <th>Transaksi</th>
                                    <th>Total</th>
                                </tr>
                            </thead>
                            <tbody id="cashierPerformanceBody">
                                <!-- Cashier performance will be loaded here -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="../../asset/js/jquery.min.js"></script>
    <script src="../../asset/js/bootstrap.min.js"></script>
    <script src="analytics.js"></script>
</body>
</html>
