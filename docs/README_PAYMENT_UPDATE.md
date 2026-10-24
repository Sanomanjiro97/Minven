# Fitur Update Pembayaran Surat Jalan

## Deskripsi
Fitur ini memungkinkan pengguna untuk mengedit status pembayaran surat jalan dan memberikan keterangan tambahan terkait pembayaran.

## Fitur yang Ditambahkan

### 1. Tombol Edit Pembayaran
- Tombol kuning dengan ikon edit di halaman view surat jalan
- Terletak di sebelah tombol "Kembali" dan "Cetak"
- Hanya muncul jika user memiliki akses ke modul surat jalan

### 2. Modal Edit Pembayaran
- Form untuk mengubah status pembayaran
- Dropdown dengan 3 opsi: Belum Dibayar, Dibayar Sebagian, Lunas
- Field keterangan pembayaran (opsional)
- Informasi bahwa status PO akan otomatis berubah jika status diubah menjadi "Lunas"

### 3. Validasi dan Konfirmasi
- Validasi client-side untuk memastikan status dipilih
- Konfirmasi khusus ketika mengubah status menjadi "Lunas"
- Loading state saat proses penyimpanan
- Auto-hide alert setelah 5 detik

### 4. Update Database
- File: `update_payment.php` untuk memproses update
- Menggunakan transaction untuk keamanan data
- Log aktivitas perubahan status pembayaran
- Update otomatis status PO menjadi "delivered" jika pembayaran lunas

### 5. Tampilan Informasi
- Menampilkan status pembayaran dengan badge berwarna
- Menampilkan keterangan pembayaran jika ada
- Menampilkan waktu terakhir diupdate
- Notifikasi sukses/error

## Struktur Database

### Kolom Baru di Tabel `surat_jalan`
```sql
-- Menambahkan kolom updated_at
ALTER TABLE `surat_jalan` 
ADD COLUMN `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() 
AFTER `created_at`;

-- Menambahkan kolom keterangan_pembayaran
ALTER TABLE `surat_jalan` 
ADD COLUMN `keterangan_pembayaran` text DEFAULT NULL 
AFTER `status_pembayaran`;
```

## File yang Dimodifikasi

1. **`view.php`**
   - Menambahkan tombol "Edit Pembayaran"
   - Menambahkan modal edit pembayaran
   - Menambahkan notifikasi sukses/error
   - Menambahkan JavaScript untuk validasi dan konfirmasi
   - Menampilkan keterangan pembayaran dan waktu update

2. **`update_payment.php`** (file baru)
   - Menangani proses update status pembayaran
   - Validasi input dan keamanan
   - Transaction management
   - Logging aktivitas
   - Update otomatis status PO

3. **`add_updated_at_column.sql`** (file baru)
   - Script SQL untuk menambahkan kolom baru

## Cara Penggunaan

### Langkah 1: Setup Database
Sebelum menggunakan fitur, pastikan kolom database sudah ditambahkan:

**Opsi A: Menggunakan File Batch**
```bash
# Jalankan file batch di folder pembelian/surat jalan/
run_payment_update.bat
```

**Opsi B: Menggunakan File PHP Manual**
```bash
# Akses file ini melalui browser
http://localhost/minven/pembelian/surat%20jalan/run_sql_manual.php
```

**Opsi C: Manual SQL**
```sql
-- Jalankan di phpMyAdmin atau MySQL client
ALTER TABLE `surat_jalan` 
ADD COLUMN `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() 
AFTER `created_at`;

ALTER TABLE `surat_jalan` 
ADD COLUMN `keterangan_pembayaran` text DEFAULT NULL 
AFTER `status_pembayaran`;
```

### Langkah 2: Menggunakan Fitur
1. **Akses Halaman View Surat Jalan**
   - Buka halaman detail surat jalan
   - Pastikan user memiliki akses ke modul surat jalan

2. **Edit Pembayaran**
   - Klik tombol "Edit Pembayaran" (kuning)
   - Pilih status pembayaran yang baru
   - Isi keterangan pembayaran (opsional)
   - Klik "Simpan Perubahan"

3. **Konfirmasi**
   - Jika mengubah ke status "Lunas", akan ada konfirmasi
   - Konfirmasi bahwa status PO akan berubah otomatis

4. **Hasil**
   - Status pembayaran akan terupdate
   - Keterangan pembayaran akan tersimpan
   - Jika status "Lunas", PO status berubah menjadi "delivered"
   - Notifikasi sukses akan muncul

## Troubleshooting

### Error: "Call to a member function bind_param() on bool"
**Penyebab:** Kolom database belum ditambahkan atau struktur tabel berbeda.

**Solusi:**
1. Jalankan file `run_sql_manual.php` melalui browser
2. Atau jalankan script SQL manual di phpMyAdmin
3. Pastikan kolom `updated_at` dan `keterangan_pembayaran` sudah ada di tabel `surat_jalan`

### Error: "Table doesn't exist" atau "Column doesn't exist"
**Penyebab:** Database belum diupdate dengan struktur baru.

**Solusi:**
1. Jalankan file `run_sql_manual.php` untuk menambahkan kolom
2. Periksa apakah ada error saat menjalankan script SQL
3. Pastikan user database memiliki hak akses ALTER TABLE

## Keamanan

- Validasi input di server-side
- Pengecekan akses user
- Penggunaan prepared statements
- Transaction untuk konsistensi data
- Logging semua perubahan

## Log Aktivitas

Setiap perubahan status pembayaran akan dicatat di tabel `user_activity_log` dengan:
- `activity_type`: 'update_payment'
- `activity_description`: Deskripsi perubahan
- `related_id`: ID surat jalan
- `related_table`: 'surat_jalan'

## Dependencies

- Bootstrap 5 (untuk modal dan styling)
- Font Awesome (untuk ikon)
- jQuery (untuk JavaScript)
- PHP dengan MySQL
- Session management
- Access control system 