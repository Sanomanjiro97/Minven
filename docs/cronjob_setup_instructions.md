# Panduan Setup Cronjob Reset Stok Harian

## Ringkasan
Script `cronjob_reset_stok_harian.php` telah dibuat untuk mereset stok harian gudang secara otomatis. Script ini dapat dijalankan melalui:

1. **Windows Task Scheduler** (untuk Windows/XAMPP)
2. **Cronjob Linux** (untuk server Linux)
3. **Manual via CLI** (command line)
4. **Manual via Web** (browser)

## Fitur Script
- ✅ Reset stok harian otomatis berdasarkan konfigurasi di tabel `setup_reset_stok`
- ✅ Logging ke file dan console
- ✅ Support untuk CLI dan Web
- ✅ Transaksi database yang aman (dengan rollback jika error)
- ✅ Cek ganda untuk mencegah reset berulang di hari yang sama
- ✅ Mencatat history reset jika tabel `gudang_stok_history` tersedia

## Cara Setup

### 1. Untuk Windows (XAMPP/Laragon)

#### Menggunakan Windows Task Scheduler:

1. Buka **Task Scheduler** (cari di Start Menu)
2. Klik **Create Task** di sebelah kanan
3. **General Tab**:
   - Name: `Reset Stok Harian Minven`
   - Description: `Otomatis reset stok harian sistem inventori`
   - Select: `Run whether user is logged on or not`
   - Check: `Run with highest privileges`

4. **Triggers Tab**:
   - Klik **New**
   - Begin the task: `Daily`
   - Settings: Set jam reset (misal: 00:00:00)
   - Click **OK**

5. **Actions Tab**:
   - Klik **New**
   - Action: `Start a program`
   - Program/script: `C:\xampp\php\php.exe` (sesuaikan dengan path PHP anda)
   - Add arguments: `C:\xampp\htdocs\minven_pro\cronjob_reset_stok_harian.php`
   - Start in: `C:\xampp\htdocs\minven_pro\`
   - Click **OK**

6. **Conditions Tab** (optional):
   - Uncheck: `Start the task only if the computer is on AC power`
   - Check: `Wake the computer to run this task`

7. Click **OK** untuk menyimpan task

#### Menggunakan Batch File (.bat):

Buat file `reset_stok_harian.bat` di folder `C:\xampp\htdocs\minven_pro\`:

```batch
@echo off
cd /d C:\xampp\htdocs\minven_pro
C:\xampp\php\php.exe cronjob_reset_stok_harian.php
pause
```

Kemudian setup Task Scheduler untuk menjalankan file .bat ini.

### 2. Untuk Linux Server

#### Menggunakan Crontab:

1. Buka terminal dan jalankan:
   ```bash
   crontab -e
   ```

2. Tambahkan baris berikut (jalankan setiap hari jam 00:00):
   ```bash
   0 0 * * * /usr/bin/php /var/www/html/minven_pro/cronjob_reset_stok_harian.php
   ```

3. Simpan dan exit

#### Contoh lain:
- Setiap jam 01:00: `0 1 * * * /usr/bin/php /path/to/cronjob_reset_stok_harian.php`
- Setiap 6 jam: `0 */6 * * * /usr/bin/php /path/to/cronjob_reset_stok_harian.php`

### 3. Konfigurasi Database

Pastikan tabel `setup_reset_stok` ada dengan konfigurasi yang benar:

```sql
-- Cek tabel
SELECT * FROM setup_reset_stok;

-- Insert konfigurasi jika belum ada
INSERT INTO setup_reset_stok (gudang_id, jam_reset, is_active) 
VALUES (13, '00:00:00', 1);

-- Update jam reset
UPDATE setup_reset_stok 
SET jam_reset = '00:00:00', is_active = 1 
WHERE gudang_id = 13;
```

### 4. Manual Testing

#### Via CLI (Command Line):
```bash
cd C:\xampp\htdocs\minven_pro
php cronjob_reset_stok_harian.php
```

#### Via Web Browser:
```
http://localhost/minven_pro/cronjob_reset_stok_harian.php
```

## Logging

Script akan menyimpan log ke file:
- **Path**: `C:\xampp\htdocs\minven_pro\logs\reset_stok_harian.log`
- **Format**: `[YYYY-MM-DD HH:MM:SS] Pesan log`

Contoh output log:
```
[2026-10-04 00:00:05] === CRONJOB RESET STOK HARIAN (CLI) ===
[2026-10-04 00:00:05] Memulai proses reset stok harian...
[2026-10-04 00:00:05] Konfigurasi reset: Jam = 00:00, Terakhir reset = 2026-10-03 00:00:00
[2026-10-04 00:00:05] Transaksi dimulai...
[2026-10-04 00:00:05] Menjalankan query: UPDATE gudang_stok SET stok_awal = (stok_awal - stok_terpakai), stok_terpakai = 0, updated_at = NOW(), last_reset = NOW() WHERE 1=1
[2026-10-04 00:00:06] Berhasil mereset 150 record stok.
[2026-10-04 00:00:06] Konfigurasi last_reset diperbarui.
[2026-10-04 00:00:06] Transaksi berhasil di-commit.
[2026-10-04 00:00:06] History reset stok dicatat.
[2026-10-04 00:00:06] Proses reset stok harian berhasil diselesaikan!
[2026-10-04 00:00:06] === SELESAI ===
```

## Troubleshooting

### 1. Script tidak berjalan
- Pastikan path PHP benar
- Cek permission file (Linux: `chmod +x cronjob_reset_stok_harian.php`)
- Pastikan database connection berfungsi

### 2. Reset terjadi berulang
- Script sudah memiliki proteksi untuk mencegah reset berulang di hari yang sama
- Cek kolom `last_reset` di tabel `setup_reset_stok`

### 3. Log file tidak dibuat
- Pastikan folder `logs` ada dan writable
- Windows: Pastikan user punya akses write
- Linux: `chmod 755 logs/`

### 4. Database error
- Cek koneksi database di `config.php`
- Pastikan tabel `gudang_stok` dan `setup_reset_stok` ada
- Cek kolom yang diperlukan: `stok_awal`, `stok_terpakai`, `updated_at`, `last_reset`

## Keamanan

Untuk keamanan tambahan, Anda bisa:

1. **Batasi akses web** - Tambahkan autentikasi di script jika diakses via web
2. **Gunakan IP restriction** - Batasi akses hanya dari IP tertentu
3. **Encrypt log file** - Jika log mengandung data sensitif

## Kontak

Jika mengalami masalah, cek log file untuk detail error yang lebih lengkap.
