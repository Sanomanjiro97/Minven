<?php
/**
 * KASIR SYSTEM - Main Dashboard
 * Menampilkan semua fitur dan modul yang tersedia
 */

require_once __DIR__ . '/_init.php';

header('Location: ' . (kasir_is_logged_in() ? kasir_url_for('dashboard.php') : kasir_url_for('index.php')));
exit();

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kasir System - Dashboard</title>
    <link rel="stylesheet" href="../../asset/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .navbar-custom {
            background: rgba(0,0,0,0.9);
            padding: 1rem 2rem;
            border-bottom: 3px solid #667eea;
            margin-bottom: 2rem;
        }
        
        .navbar-custom .store-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: white;
        }
        
        .store-name {
            font-size: 1.5rem;
            font-weight: bold;
            color: #667eea;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            background: #667eea;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }
        
        .dashboard-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }
        
        .welcome-section {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .welcome-section h2 {
            color: #667eea;
            margin-bottom: 1rem;
        }
        
        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 3rem;
        }
        
        .feature-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            cursor: pointer;
            text-decoration: none;
            color: inherit;
            border-left: 5px solid transparent;
        }
        
        .feature-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 12px rgba(0,0,0,0.2);
        }
        
        .feature-card.primary {
            border-left-color: #667eea;
        }
        
        .feature-card.success {
            border-left-color: #28a745;
        }
        
        .feature-card.info {
            border-left-color: #17a2b8;
        }
        
        .feature-card.warning {
            border-left-color: #ffc107;
        }
        
        .feature-card.danger {
            border-left-color: #dc3545;
        }
        
        .feature-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            width: 60px;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            background: #f0f0f0;
        }
        
        .feature-card.primary .feature-icon {
            background: #e7e5ff;
            color: #667eea;
        }
        
        .feature-card.success .feature-icon {
            background: #e8f5e9;
            color: #28a745;
        }
        
        .feature-card.info .feature-icon {
            background: #e0f2f1;
            color: #17a2b8;
        }
        
        .feature-card.warning .feature-icon {
            background: #fff3e0;
            color: #ffc107;
        }
        
        .feature-card.danger .feature-icon {
            background: #ffebee;
            color: #dc3545;
        }
        
        .feature-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #333;
        }
        
        .feature-desc {
            font-size: 0.95rem;
            color: #666;
            margin-bottom: 1rem;
        }
        
        .feature-badge {
            display: inline-block;
            background: #f0f0f0;
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.8rem;
            color: #666;
            margin-right: 0.5rem;
        }
        
        .section-title {
            font-size: 1.5rem;
            font-weight: bold;
            color: white;
            margin: 2rem 0 1.5rem 0;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }
        
        .quick-actions {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        
        .quick-actions h4 {
            color: #667eea;
            margin-bottom: 1rem;
        }
        
        .btn-action {
            display: inline-block;
            background: #667eea;
            color: white;
            padding: 0.7rem 1.5rem;
            border-radius: 5px;
            text-decoration: none;
            margin-right: 0.5rem;
            margin-bottom: 0.5rem;
            transition: all 0.3s;
        }
        
        .btn-action:hover {
            background: #5568d3;
            color: white;
            transform: scale(1.05);
        }
        
        .btn-action.secondary {
            background: #6c757d;
        }
        
        .btn-action.secondary:hover {
            background: #5a6268;
        }
        
        .features-category {
            margin-bottom: 3rem;
        }
        
        @media (max-width: 768px) {
            .features-grid {
                grid-template-columns: 1fr;
            }
            
            .navbar-custom .store-info {
                flex-direction: column;
                text-align: center;
                gap: 1rem;
            }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <div class="navbar-custom">
        <div class="store-info">
            <div>
                <div class="store-name">🏪 MINVEN POS SYSTEM</div>
                <small style="color: #999;">Sistem Kasir Modern untuk Toko & Restoran</small>
            </div>
            <div class="user-info">
                <div style="text-align: right;">
                    <div style="color: white;"><?php echo htmlspecialchars($user_name); ?></div>
                    <small style="color: #999;"><?php echo htmlspecialchars($outlet_name); ?></small>
                </div>
                <div class="user-avatar"><?php echo strtoupper(substr($user_name, 0, 1)); ?></div>
            </div>
        </div>
    </div>

    <div class="dashboard-container">
        <!-- Welcome Section -->
        <div class="welcome-section">
            <h2>👋 Selamat datang di Kasir System!</h2>
            <p>Pilih modul di bawah untuk mulai mengelola penjualan, pelanggan, inventori, dan laporan Anda.</p>
        </div>

        <!-- Quick Actions -->
        <div class="quick-actions">
            <h4>⚡ Akses Cepat</h4>
            <a href="checkout/" class="btn-action"><i class="fas fa-shopping-cart"></i> Mulai Checkout</a>
            <a href="orders/" class="btn-action"><i class="fas fa-list"></i> Lihat Pesanan</a>
            <a href="analytics/" class="btn-action secondary"><i class="fas fa-chart-bar"></i> Laporan Hari Ini</a>
            <a href="settings/" class="btn-action secondary"><i class="fas fa-cog"></i> Pengaturan</a>
        </div>

        <!-- SECTION 1: TRANSAKSI & PENJUALAN -->
        <div class="section-title">💰 TRANSAKSI & PENJUALAN</div>
        <div class="features-grid">
            <a href="checkout/" class="feature-card primary">
                <div class="feature-icon"><i class="fas fa-shopping-cart"></i></div>
                <div class="feature-title">🧾 Fast Checkout</div>
                <div class="feature-desc">Kasir cepat dengan barcode scanner & keyboard shortcut</div>
                <span class="feature-badge">Alt+P Bayar</span>
                <span class="feature-badge">Alt+H Parkir</span>
            </a>

            <a href="orders/" class="feature-card primary">
                <div class="feature-icon"><i class="fas fa-list"></i></div>
                <div class="feature-title">📋 Order Management</div>
                <div class="feature-desc">Kelola pesanan dine-in, takeaway, dan delivery</div>
                <span class="feature-badge">Dine-in</span>
                <span class="feature-badge">Delivery</span>
            </a>

            <a href="receipts/" class="feature-card primary">
                <div class="feature-icon"><i class="fas fa-receipt"></i></div>
                <div class="feature-title">🧾 Digital Receipt</div>
                <div class="feature-desc">Struk via WhatsApp, Email, PDF, dan Printer thermal</div>
                <span class="feature-badge">WhatsApp</span>
                <span class="feature-badge">Print</span>
            </a>
        </div>

        <!-- SECTION 2: PEMBAYARAN -->
        <div class="section-title">💳 PEMBAYARAN & METODE</div>
        <div class="features-grid">
            <a href="payments/" class="feature-card success">
                <div class="feature-icon"><i class="fas fa-money-bill"></i></div>
                <div class="feature-title">💸 Multi Payment</div>
                <div class="feature-desc">Cash, QRIS, Debit, E-Wallet, Transfer Bank</div>
                <span class="feature-badge">6 Metode</span>
                <span class="feature-badge">Split Bill</span>
            </a>

            <a href="settings/" class="feature-card success">
                <div class="feature-icon"><i class="fas fa-exchange-alt"></i></div>
                <div class="feature-title">🔄 Split Payment</div>
                <div class="feature-desc">Pembayaran ganda dari beberapa sumber</div>
                <span class="feature-badge">Rp100rb + Rp50rb</span>
            </a>

            <a href="returns/" class="feature-card success">
                <div class="feature-icon"><i class="fas fa-undo"></i></div>
                <div class="feature-title">↩️ Return/Refund</div>
                <div class="feature-desc">Retur barang dan pengembalian dana</div>
                <span class="feature-badge">Easy Return</span>
            </a>
        </div>

        <!-- SECTION 3: PELANGGAN & LOYALITAS -->
        <div class="section-title">👥 PELANGGAN & LOYALITAS</div>
        <div class="features-grid">
            <a href="customers/" class="feature-card info">
                <div class="feature-icon"><i class="fas fa-users"></i></div>
                <div class="feature-title">👤 Customer Management</div>
                <div class="feature-desc">Kelola member, regular, dan VIP pelanggan</div>
                <span class="feature-badge">Member</span>
                <span class="feature-badge">VIP</span>
            </a>

            <a href="customers/" class="feature-card info">
                <div class="feature-icon"><i class="fas fa-star"></i></div>
                <div class="feature-title">⭐ Loyalty Point</div>
                <div class="feature-desc">Poin otomatis berdasarkan transaksi & redemption</div>
                <span class="feature-badge">1 poin/Rp1000</span>
            </a>

            <a href="vouchers_promo/" class="feature-card info">
                <div class="feature-icon"><i class="fas fa-tag"></i></div>
                <div class="feature-title">🎫 Voucher & Promo</div>
                <div class="feature-desc">Diskon produk, transaksi, buy 1 get 1</div>
                <span class="feature-badge">Unlimited</span>
            </a>
        </div>

        <!-- SECTION 4: INVENTORI & STOK -->
        <div class="section-title">📦 INVENTORI & STOK</div>
        <div class="features-grid">
            <a href="inventory/" class="feature-card warning">
                <div class="feature-icon"><i class="fas fa-box"></i></div>
                <div class="feature-title">📊 Auto Inventory</div>
                <div class="feature-desc">Stok otomatis berkurang saat transaksi</div>
                <span class="feature-badge">Real-time</span>
            </a>

            <a href="inventory/" class="feature-card warning">
                <div class="feature-icon"><i class="fas fa-bell"></i></div>
                <div class="feature-title">🔔 Low Stock Alert</div>
                <div class="feature-desc">Notifikasi stok minimum produk</div>
                <span class="feature-badge">Alert</span>
            </a>

            <a href="inventory/" class="feature-card warning">
                <div class="feature-icon"><i class="fas fa-list-check"></i></div>
                <div class="feature-title">🧪 Recipe/BOM</div>
                <div class="feature-desc">Resep produk cafe mengurangi bahan baku</div>
                <span class="feature-badge">Cafe</span>
            </a>

            <a href="inventory/" class="feature-card warning">
                <div class="feature-icon"><i class="fas fa-lock"></i></div>
                <div class="feature-title">🔒 Stock Reservation</div>
                <div class="feature-desc">Stok ditahan untuk pesanan/pengiriman</div>
                <span class="feature-badge">Booking</span>
            </a>
        </div>

        <!-- SECTION 5: OPERASIONAL & KITCHEN -->
        <div class="section-title">🍳 OPERASIONAL & KITCHEN</div>
        <div class="features-grid">
            <a href="kitchen/" class="feature-card danger">
                <div class="feature-icon"><i class="fas fa-tv"></i></div>
                <div class="feature-title">🍳 Kitchen Display System</div>
                <div class="feature-desc">Pesanan real-time langsung ke layar dapur</div>
                <span class="feature-badge">Real-time</span>
                <span class="feature-badge">Sound Alert</span>
            </a>

            <a href="settings/" class="feature-card danger">
                <div class="feature-icon"><i class="fas fa-clock"></i></div>
                <div class="feature-title">👨‍💼 Shift Kasir</div>
                <div class="feature-desc">Buka/tutup shift, cash in/out, reconciliation</div>
                <span class="feature-badge">Tracking</span>
            </a>

            <a href="settings/" class="feature-card danger">
                <div class="feature-icon"><i class="fas fa-wallet"></i></div>
                <div class="feature-title">💵 Cash Drawer</div>
                <div class="feature-desc">Kas masuk/keluar dan selisih kas harian</div>
                <span class="feature-badge">Audit</span>
            </a>
        </div>

        <!-- SECTION 6: ANALITIK & LAPORAN -->
        <div class="section-title">📊 ANALITIK & LAPORAN</div>
        <div class="features-grid">
            <a href="analytics/" class="feature-card primary">
                <div class="feature-icon"><i class="fas fa-chart-bar"></i></div>
                <div class="feature-title">📈 Dashboard Real-time</div>
                <div class="feature-desc">Omzet, transaksi, profit, produk terlaris</div>
                <span class="feature-badge">Live Update</span>
            </a>

            <a href="analytics/" class="feature-card primary">
                <div class="feature-icon"><i class="fas fa-chart-line"></i></div>
                <div class="feature-title">📊 Sales Analytics</div>
                <div class="feature-desc">Analisis penjualan per jam, produk, kasir</div>
                <span class="feature-badge">Charts</span>
            </a>

            <a href="analytics/" class="feature-card primary">
                <div class="feature-icon"><i class="fas fa-calculator"></i></div>
                <div class="feature-title">💰 Profit Analysis</div>
                <div class="feature-desc">HPP otomatis → laba kotor & margin</div>
                <span class="feature-badge">Akurat</span>
            </a>

            <a href="reports/" class="feature-card primary">
                <div class="feature-icon"><i class="fas fa-file-alt"></i></div>
                <div class="feature-title">📋 Custom Reports</div>
                <div class="feature-desc">Laporan custom untuk kebutuhan bisnis</div>
                <span class="feature-badge">Export</span>
            </a>
        </div>

        <!-- SECTION 7: TEKNOLOGI & SYNC -->
        <div class="section-title">☁️ TEKNOLOGI & SINKRONISASI</div>
        <div class="features-grid">
            <a href="mobile/" class="feature-card info">
                <div class="feature-icon"><i class="fas fa-mobile-alt"></i></div>
                <div class="feature-title">📱 Mobile/Tablet POS</div>
                <div class="feature-desc">Responsive design untuk tablet & smartphone</div>
                <span class="feature-badge">Responsive</span>
            </a>

            <a href="offline/" class="feature-card info">
                <div class="feature-icon"><i class="fas fa-wifi-slash"></i></div>
                <div class="feature-title">📴 Offline Mode</div>
                <div class="feature-desc">Tetap transaksi meski internet putus</div>
                <span class="feature-badge">Auto Sync</span>
            </a>

            <a href="settings/" class="feature-card info">
                <div class="feature-icon"><i class="fas fa-globe"></i></div>
                <div class="feature-title">🏪 Multi Outlet</div>
                <div class="feature-desc">Kelola banyak toko/cabang dalam 1 sistem</div>
                <span class="feature-badge">Cloud Sync</span>
            </a>

            <a href="settings/" class="feature-card info">
                <div class="feature-icon"><i class="fas fa-plug"></i></div>
                <div class="feature-title">🔗 API Integration</div>
                <div class="feature-desc">Integrasi dengan aplikasi lain</div>
                <span class="feature-badge">REST API</span>
            </a>
        </div>

        <!-- SECTION 8: KEAMANAN & AUDIT -->
        <div class="section-title">🔐 KEAMANAN & AUDIT</div>
        <div class="features-grid">
            <a href="settings/" class="feature-card danger">
                <div class="feature-icon"><i class="fas fa-shield-alt"></i></div>
                <div class="feature-title">🔐 Role & Permission</div>
                <div class="feature-desc">Owner, Manager, Supervisor, Kasir</div>
                <span class="feature-badge">Access Control</span>
            </a>

            <a href="settings/" class="feature-card danger">
                <div class="feature-icon"><i class="fas fa-history"></i></div>
                <div class="feature-title">📝 Audit Log</div>
                <div class="feature-desc">Semua edit, void, refund tercatat lengkap</div>
                <span class="feature-badge">Complete Tracking</span>
            </a>
        </div>

        <!-- Footer -->
        <div style="text-align: center; margin-top: 4rem; color: white;">
            <p>© 2024 Minven POS System - Sistem Kasir Modern untuk Semua Bisnis</p>
            <small>Version 2.0 | Database: minven_pos</small>
        </div>
    </div>

    <script src="../../asset/js/jquery.min.js"></script>
    <script src="../../asset/js/bootstrap.min.js"></script>
    <script>
        // Add keyboard shortcuts info
        document.addEventListener('keydown', function(e) {
            if (e.altKey && e.key.toLowerCase() === 'c') {
                window.location.href = 'checkout/';
            }
            if (e.altKey && e.key.toLowerCase() === 'd') {
                window.location.href = 'analytics/';
            }
            if (e.altKey && e.key.toLowerCase() === 'o') {
                window.location.href = 'orders/';
            }
        });

        // Add hover effects
        document.querySelectorAll('.feature-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.boxShadow = '0 12px 20px rgba(0,0,0,0.2)';
            });
            card.addEventListener('mouseleave', function() {
                this.style.boxShadow = '0 4px 6px rgba(0,0,0,0.1)';
            });
        });
    </script>
</body>
</html>
