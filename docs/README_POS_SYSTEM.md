# 🧾 POS SYSTEM - KASIR MODULE

## Dokumentasi Lengkap Sistem Kasir Minven Pro

Sistem kasir modern yang komprehensif dengan fitur-fitur lengkap untuk toko retail, kafe, restoran, dan bisnis F&B lainnya.

---

## 📋 DAFTAR ISI

1. [Fitur Utama](#fitur-utama)
2. [Struktur Folder](#struktur-folder)
3. [Database Schema](#database-schema)
4. [API Documentation](#api-documentation)
5. [Instalasi & Setup](#instalasi--setup)
6. [Penggunaan](#penggunaan)
7. [Integrasi & API](#integrasi--api)
8. [Tips Implementasi](#tips-implementasi)

---

## 🎯 Fitur Utama

### Fast Checkout
- ✅ Kasir cepat dengan interface intuitif
- ✅ Keyboard shortcut (Alt+P bayar, Alt+H parkir, Alt+V voucher)
- ✅ Barcode scanner integration
- ✅ Pencarian produk instan
- ✅ Auto-complete produk

### Payment Methods
- ✅ Cash
- ✅ QRIS
- ✅ Debit/Credit Card
- ✅ E-Wallet (OVO, GoPay, DANA, LinkAja)
- ✅ Transfer Bank
- ✅ Split Payment (gabung beberapa metode)
- ✅ Split Bill (pisah pembayaran antar pihak)

### Customer Management
- ✅ Pelanggan reguler & member
- ✅ Riwayat pembelian
- ✅ Loyalty points system
- ✅ Member status (Regular, Member, VIP)
- ✅ Total purchases tracking

### Orders & Table Management
- ✅ Order type: Dine-in, Takeaway, Delivery
- ✅ Table management untuk cafe/restoran
- ✅ Kitchen Display System (KDS)
- ✅ Order priority (normal/urgent)
- ✅ Real-time order status

### Inventory Management
- ✅ Auto inventory reduction
- ✅ Stock reservation
- ✅ Low stock alerts
- ✅ Recipe/BOM untuk produk cafe
- ✅ Multi-warehouse support

### Promo & Discount
- ✅ Voucher management
- ✅ Diskon per produk
- ✅ Diskon per transaksi
- ✅ Buy 1 Get 1 promo
- ✅ Loyalty point redemption

### Digital Receipts
- ✅ Receipt via WhatsApp
- ✅ Receipt via Email
- ✅ Receipt via SMS
- ✅ PDF download
- ✅ Multiple printer support

### Reports & Analytics
- ✅ Dashboard real-time
- ✅ Sales by hour/day/month
- ✅ Top products
- ✅ Cashier performance
- ✅ Profit analysis
- ✅ Payment method breakdown

### Shift Management
- ✅ Open/close shift
- ✅ Cash in/out
- ✅ Cash drawer reconciliation
- ✅ Shift reports

### Mobile & Offline
- ✅ Responsive design (Tablet/Mobile)
- ✅ Offline mode tetap bisa transaksi
- ✅ Auto sync saat online
- ✅ LocalStorage caching

### Security & Audit
- ✅ Role & permission system
- ✅ Complete audit log
- ✅ Void & refund tracking
- ✅ User activity logging

---

## 📁 Struktur Folder

```
kasir/
├── checkout/                 # Fast checkout module
│   ├── index.php
│   └── checkout.js
├── payments/                 # Payment processing
│   ├── index.php
│   └── payment.js
├── products/                 # Product management
├── vouchers_promo/          # Vouchers & promotions
│   ├── index.php
│   └── vouchers.js
├── customers/               # Customer management
│   ├── index.php
│   └── customers.js
├── receipts/                # Receipt generation
│   ├── index.php
│   └── receipt.js
├── kitchen/                 # Kitchen Display System
│   ├── index.php
│   ├── kds.js
│   └── kds-style.css
├── orders/                  # Orders management
│   ├── index.php
│   └── orders.js
├── returns/                 # Returns & refunds
│   ├── index.php
│   └── returns.js
├── inventory/               # Inventory management
│   ├── index.php
│   └── inventory.js
├── analytics/               # Analytics & reports
│   ├── index.php
│   └── analytics.js
├── mobile/                  # Mobile POS
│   ├── index.php
│   ├── mobile.js
│   └── mobile-style.css
├── offline/                 # Offline mode
│   ├── index.php
│   └── offline.js
├── settings/                # Settings & configuration
│   ├── index.php
│   └── settings.js
├── reports/                 # Custom reports
├── delivery/                # Delivery management
├── api/                     # API endpoints
│   ├── search_product.php
│   ├── process_payment.php
│   ├── get_receipt.php
│   ├── create_order.php
│   ├── update_order_status.php
│   └── sync_offline.php
├── pos_tables.sql           # Database schema
├── _init.php                # Initialization
└── README.md                # This file
```

---

## 📊 Database Schema

### Main Tables:

1. **pos_transactions** - Semua transaksi
2. **pos_transaction_items** - Detail item per transaksi
3. **pos_payments** - Catatan pembayaran
4. **pos_customers** - Data pelanggan
5. **pos_loyalty_points** - Poin loyalitas
6. **pos_vouchers** - Daftar voucher
7. **pos_orders** - Pesanan (dine-in/takeaway/delivery)
8. **pos_order_items** - Detail item pesanan
9. **pos_returns** - Retur & refund
10. **pos_recipes** - BOM/resep produk
11. **pos_shifts** - Shift kasir
12. **pos_cash_drawer** - Kas masuk/keluar
13. **pos_stock_reservation** - Reservasi stok
14. **pos_audit_log** - Log semua aktivitas
15. **pos_offline_queue** - Transaksi offline
16. **outlets** - Multi-outlet support

### Import Database:
```bash
mysql -u root < kasir/pos_tables.sql
```

---

## 🔌 API Documentation

### 1. Search Product
**Endpoint:** `kasir/api/search_product.php`
**Method:** POST
**Parameters:**
- `q` (string) - Query (barcode/nama produk)

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "name": "Kopi Espresso",
    "price": 25000,
    "stock": 50,
    "barcode": "123456"
  }
}
```

### 2. Process Payment
**Endpoint:** `kasir/api/process_payment.php`
**Method:** POST
**Body:**
```json
{
  "items": [
    {"id": 1, "qty": 2, "price": 25000, "subtotal": 50000}
  ],
  "payment_method": "cash",
  "amount": 50000,
  "customer_id": 1,
  "order_type": "dine_in"
}
```

**Response:**
```json
{
  "success": true,
  "transaction_id": 123,
  "transaction_no": "TRX20240818123456",
  "receipt_url": "receipts/index.php?id=123"
}
```

### 3. Get Receipt
**Endpoint:** `kasir/api/get_receipt.php`
**Method:** GET
**Parameters:**
- `id` (int) - Transaction ID

---

## 💻 Instalasi & Setup

### 1. Import Database
```sql
-- Run di phpMyAdmin atau command line
SOURCE kasir/pos_tables.sql;
```

### 2. Update Configuration
Edit `kasir/_init.php`:
```php
<?php
define('STORE_NAME', 'Nama Toko Anda');
define('STORE_ADDRESS', 'Alamat');
define('STORE_PHONE', '021-xxx');
// ... konfigurasi lainnya
?>
```

### 3. Setup Roles & Permissions
Role yang diperlukan:
- `kasir_checkout` - Akses fast checkout
- `kasir_payments` - Proses pembayaran
- `kasir_customers` - Kelola pelanggan
- `kasir_orders` - Kelola pesanan
- `kasir_returns` - Proses retur
- `kasir_inventory` - Kelola inventori
- `kasir_analytics` - Lihat laporan
- `kasir_settings` - Ubah pengaturan

### 4. Setup Outlets (Multi-lokasi)
```sql
INSERT INTO outlets (outlet_name, location, phone, email) VALUES 
('Outlet Jakarta', 'Jl. Contoh 1, Jakarta', '021-xxx', 'jakarta@toko.com'),
('Outlet Bandung', 'Jl. Contoh 2, Bandung', '022-xxx', 'bandung@toko.com');
```

---

## 🚀 Penggunaan

### Fast Checkout
1. Buka `kasir/checkout/` 
2. Scan barcode atau ketik nama produk
3. Produk otomatis ditambah ke keranjang
4. Alt+P untuk bayar, Alt+H parkir, Alt+V voucher

### Order Management
1. Buka `kasir/orders/`
2. Pilih tipe pesanan (Dine-in/Takeaway/Delivery)
3. Pesanan langsung terlihat di Kitchen Display System

### Kitchen Display System
1. Buka `kasir/kitchen/`
2. Lihat pesanan real-time dari 3 status: Pending, Preparing, Ready
3. Click untuk ubah status

### Analytics
1. Buka `kasir/analytics/`
2. Pilih periode
3. Lihat KPI, charts, top products, cashier performance

---

## 🔗 Integrasi & API

### WhatsApp Integration
```php
// Kirim receipt via WhatsApp
sendWhatsApp($phone, generateReceiptText($transactionId));
```

### QRIS Payment Integration
```php
// Generate QRIS untuk pembayaran
$qrisCode = generateQRIS([
    'amount' => $totalAmount,
    'description' => 'Purchase Transaction'
]);
```

### Multi-Store Sync
```php
// Sync data antar outlet
syncOutletData($fromOutlet, $toOutlet);
```

---

## 💡 Tips Implementasi

### 1. Barcode Scanner Setup
```html
<!-- Koneksi scanner via keyboard input -->
<input id="barcodeInput" type="text" autofocus>
<script>
$('#barcodeInput').on('keypress', function(e) {
    if (e.which === 13) {
        searchProduct($(this).val());
        $(this).val('');
    }
});
</script>
```

### 2. Offline Mode (Using LocalStorage)
```javascript
// Save transaction offline
function saveOfflineTransaction(data) {
    let queue = JSON.parse(localStorage.getItem('offlineQueue')) || [];
    queue.push(data);
    localStorage.setItem('offlineQueue', JSON.stringify(queue));
}

// Sync saat online
function syncOfflineTransactions() {
    if (!navigator.onLine) return;
    let queue = JSON.parse(localStorage.getItem('offlineQueue')) || [];
    queue.forEach(trans => {
        $.ajax({
            url: 'api/process_payment.php',
            method: 'POST',
            data: JSON.stringify(trans),
            success: () => removeFromQueue(trans.id)
        });
    });
}
```

### 3. Printer Integration
```php
// Print receipt ke thermal printer
function printReceipt($transactionId, $printerName) {
    $receipt = generateReceiptHTML($transactionId);
    // Kirim ke printer via driver atau WebSocket
    sendToPrinter($printerName, $receipt);
}
```

### 4. Kitchen Display System Update (Real-time)
```javascript
// Check order updates setiap 5 detik
setInterval(() => {
    $.ajax({
        url: 'api/get_kds_orders.php',
        success: (data) => {
            updateOrderDisplay(data);
            playNewOrderSound();
        }
    });
}, 5000);
```

### 5. Loyalty Points Calculation
```php
// Hitung poin berdasarkan transaksi
function calculateLoyaltyPoints($purchaseAmount) {
    // 1 poin per Rp 1000
    return floor($purchaseAmount / 1000);
}

// Apply loyalty discount
function applyLoyaltyDiscount($customerId, $pointsToRedeem) {
    $discountAmount = $pointsToRedeem * 1000; // 1 poin = Rp 1000
    return $discountAmount;
}
```

---

## 📝 Checklist Implementasi

- [ ] Import database schema
- [ ] Setup roles & permissions
- [ ] Configure store settings
- [ ] Setup outlets (jika multi-lokasi)
- [ ] Setup printers
- [ ] Integrate barcode scanner
- [ ] Setup payment gateway (jika pakai online)
- [ ] Configure WhatsApp API
- [ ] Setup offline mode storage
- [ ] Test all modules
- [ ] Train staff
- [ ] Go live!

---

## 🆘 Troubleshooting

### Pembayaran tidak tercatat
- Cek koneksi database
- Cek permission user
- Lihat audit log untuk error detail

### Stock tidak berkurang
- Pastikan transaction status = 'completed'
- Cek inventory permission
- Verify recipe/BOM setup

### Kitchen Display tidak update
- Cek AJAX polling interval
- Verify database connection
- Check browser console untuk error

### Offline sync gagal
- Cek koneksi internet
- Verify queue table structure
- Manual sync via settings

---

## 📞 Support & Documentation

- GitHub: [Link Repository]
- Wiki: [Link Documentation]
- Email: support@minven.com
- WhatsApp: +62xxx

---

**Version:** 1.0.0  
**Last Updated:** 2024-08-18  
**Maintained by:** Minven Development Team
