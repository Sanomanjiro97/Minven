# Dokumentasi Fitur PO Otomatis - Payment Method dan Lokasi

## Fitur Baru
Sistem sekarang mendukung pengisian otomatis **metode pembayaran** dan **lokasi** untuk Purchase Order (PO) berdasarkan lokasi user yang login.

## Cara Instalasi

### 1. Jalankan Migration Script
Jalankan file SQL berikut untuk menambahkan kolom dan tabel yang diperlukan:

```bash
# Buka phpMyAdmin atau terminal MySQL
# Jalankan file: migrate_add_location_columns.sql
```

Atau jalankan perintah SQL berikut secara manual:

```sql
-- 1. Tambahkan kolom lokasi_id ke tabel users
ALTER TABLE `users` 
ADD COLUMN `lokasi_id` INT(11) DEFAULT NULL AFTER `profile_picture`,
ADD INDEX `idx_lokasi_id` (`lokasi_id`);

-- 2. Tambahkan kolom location_id ke tabel purchase_order
ALTER TABLE `purchase_order` 
ADD COLUMN `location_id` INT(11) DEFAULT NULL AFTER `payment_method`,
ADD INDEX `idx_location_id` (`location_id`);

-- 3. Buat tabel untuk mapping lokasi dengan metode pembayaran default
CREATE TABLE IF NOT EXISTS `lokasi_payment_mapping` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `lokasi_id` int(11) NOT NULL,
  `default_payment_method` enum('cash', 'saldo') NOT NULL DEFAULT 'cash',
  `keterangan` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_lokasi` (`lokasi_id`),
  FOREIGN KEY (`lokasi_id`) REFERENCES `lokasi_mapping`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
```

### 2. Setup Lokasi untuk User
Setiap user perlu di-assign ke lokasi tertentu:

```sql
-- Contoh: Assign user ke lokasi
UPDATE users SET lokasi_id = 17 WHERE id = 7; -- User ID 7 ke lokasi Bar
UPDATE users SET lokasi_id = 18 WHERE id = 35; -- User ID 35 ke lokasi Kitchen
UPDATE users SET lokasi_id = 19 WHERE id = 36; -- User ID 36 ke lokasi Front House
```

### 3. Setup Default Payment Method per Lokasi
Konfigurasikan metode pembayaran default untuk setiap lokasi:

```sql
-- Insert default payment method untuk lokasi yang ada
INSERT INTO `lokasi_payment_mapping` (`lokasi_id`, `default_payment_method`, `keterangan`) VALUES
(17, 'cash', 'Bar - default payment cash'),
(18, 'saldo', 'Kitchen - default payment saldo'),
(19, 'cash', 'Front House - default payment cash')
ON DUPLICATE KEY UPDATE 
  default_payment_method = VALUES(default_payment_method),
  keterangan = VALUES(keterangan);
```

## Cara Kerja Sistem

### 1. Saat User Login
- Sistem mengambil `lokasi_id` dari tabel `users`
- Sistem mencari `default_payment_method` dari tabel `lokasi_payment_mapping` berdasarkan `lokasi_id` user

### 2. Saat Membuat PO Baru
- **Metode Pembayaran**: Otomatis terisi sesuai default lokasi user (bisa diubah manual jika perlu)
- **Lokasi**: Otomatis terisi dengan `lokasi_id` user yang login
- Form menampilkan informasi lokasi user dan metode pembayaran default

### 3. Saat Menyimpan PO
- `payment_method` disimpan ke tabel `purchase_order`
- `location_id` disimpan ke tabel `purchase_order`
- Jika user tidak punya lokasi, sistem menggunakan default 'cash' dan location_id NULL

## Contoh Penggunaan

### Scenario 1: User dari Kitchen membuat PO
1. User dengan `lokasi_id = 18` (Kitchen) login
2. Sistem otomatis set payment method = 'saldo' (sesuai konfigurasi Kitchen)
3. Sistem otomatis set location_id = 18
4. Form PO menampilkan:
   - Metode Pembayaran: Saldo (selected)
   - Lokasi User: Kitchen
5. User bisa ubah payment method jika perlu, tapi lokasi tetap otomatis

### Scenario 2: User tanpa lokasi membuat PO
1. User dengan `lokasi_id = NULL` login
2. Sistem menggunakan default payment method = 'cash'
3. Sistem set location_id = NULL
4. Form PO menampilkan:
   - Metode Pembayaran: Cash (selected)
   - Lokasi User: Tidak ada lokasi

## Manajemen Lokasi dan Payment Method

### Melihat Daftar Lokasi
```sql
SELECT * FROM lokasi_mapping WHERE aktif = 1;
```

### Melihat Mapping Payment Method
```sql
SELECT lpm.*, lm.nama_lokasi 
FROM lokasi_payment_mapping lpm
JOIN lokasi_mapping lm ON lpm.lokasi_id = lm.id;
```

### Update Payment Method untuk Lokasi
```sql
UPDATE lokasi_payment_mapping 
SET default_payment_method = 'saldo', 
    keterangan = 'Updated payment method'
WHERE lokasi_id = 17;
```

### Melihat User dan Lokasinya
```sql
SELECT u.id, u.username, u.nama, u.lokasi_id, lm.nama_lokasi
FROM users u
LEFT JOIN lokasi_mapping lm ON u.lokasi_id = lm.id
ORDER BY u.nama;
```

## Troubleshooting

### Masalah: Payment method tidak otomatis terisi
**Solusi**: 
- Pastikan user memiliki `lokasi_id` di tabel `users`
- Pastikan `lokasi_id` user ada di tabel `lokasi_payment_mapping`
- Cek apakah migration script sudah dijalankan dengan benar

### Masalah: Lokasi tidak muncul di form
**Solusi**:
- Pastikan kolom `lokasi_id` sudah ditambahkan ke tabel `users`
- Pastikan user sudah di-assign ke lokasi yang valid
- Cek apakah lokasi tersebut aktif di tabel `lokasi_mapping`

### Masalah: Error saat menyimpan PO
**Solusi**:
- Pastikan kolom `location_id` sudah ditambahkan ke tabel `purchase_order`
- Pastikan tabel `lokasi_payment_mapping` sudah dibuat
- Cek foreign key constraint antara tabel

## File yang Dimodifikasi

1. `migrate_add_location_columns.sql` - Migration script baru
2. `pembelian/po/create.php` - Form PO dengan fitur otomatis
3. `backoffice/reports/po.php` - Laporan PO (sudah mendukung location_id)

## Catatan Penting

- User tetap bisa mengubah payment method secara manual saat membuat PO
- Lokasi user diambil dari session saat login, tidak bisa diubah saat membuat PO
- Jika lokasi user diubah di database, user perlu logout dan login ulang untuk mendapatkan lokasi baru
- Sistem menggunakan default 'cash' jika tidak ada konfigurasi payment method untuk lokasi tersebut

## Support

Untuk bantuan lebih lanjut, hubungi tim teknis atau administrator sistem.
