# Menu Access Management System

## Overview

Sistem Menu Access Management memungkinkan administrator untuk mengelola akses user ke berbagai menu dan fitur berdasarkan role yang telah ditentukan. Sistem ini menyediakan antarmuka yang mudah digunakan untuk mengatur permission (view, add, edit, delete) untuk setiap kombinasi role dan menu.

## Fitur Utama

### 1. CRUD Operations
- **Create**: Menambah menu access baru untuk role tertentu
- **Read**: Melihat daftar semua menu access dengan filter dan pencarian
- **Update**: Mengedit permission yang ada
- **Delete**: Menghapus menu access yang tidak diperlukan

### 2. Bulk Operations
- **Bulk Create**: Membuat multiple menu access sekaligus
- **Bulk Update**: Mengupdate permission untuk multiple menu access
- **Bulk Delete**: Menghapus multiple menu access sekaligus

### 3. Advanced Features
- **DataTables Integration**: Tabel dengan fitur sorting, searching, dan pagination
- **Filter System**: Filter berdasarkan role, menu, dan permission
- **Access Control**: Setiap operasi dilindungi dengan permission check
- **Activity Logging**: Mencatat semua aktivitas untuk audit trail

## Struktur File

```
menu_access/
├── index.php              # Halaman utama dengan daftar menu access
├── create.php             # Form untuk menambah menu access baru
├── view.php               # Detail view untuk menu access tertentu
├── edit.php               # Form untuk mengedit menu access
├── delete.php             # Script untuk menghapus menu access
├── bulk_operations.php    # Halaman untuk bulk operations
└── README.md              # Dokumentasi ini
```

## Cara Penggunaan

### 1. Mengakses Menu Access Management

1. Login ke sistem dengan role yang memiliki akses ke menu access management
2. Klik menu "Manajemen User" di navbar
3. Pilih "Menu Access" dari dropdown menu

### 2. Menambah Menu Access Baru

1. Klik tombol "Tambah Menu Access" di halaman utama
2. Pilih role dari dropdown
3. Pilih menu dari dropdown
4. Centang permission yang diinginkan (View, Add, Edit, Delete)
5. Klik "Simpan"

### 3. Mengedit Menu Access

1. Klik icon edit (pensil) pada baris yang ingin diedit
2. Ubah permission sesuai kebutuhan
3. Klik "Update"

### 4. Menghapus Menu Access

1. Klik icon delete (tempat sampah) pada baris yang ingin dihapus
2. Konfirmasi penghapusan di modal yang muncul
3. Klik "Hapus"

### 5. Bulk Operations

1. Klik "Bulk Operations" dari menu dropdown
2. Pilih operasi yang diinginkan:
   - **Bulk Create**: Pilih role dan menu, set permission, klik "Bulk Create"
   - **Bulk Update**: Pilih menu access yang akan diupdate, set permission baru, klik "Bulk Update"
   - **Bulk Delete**: Pilih menu access yang akan dihapus, klik "Bulk Delete"

## Permission System

### Jenis Permission
- **View**: Kemampuan untuk melihat/mengakses menu
- **Add**: Kemampuan untuk menambah data baru
- **Edit**: Kemampuan untuk mengedit data yang ada
- **Delete**: Kemampuan untuk menghapus data

### Aturan Permission
- Jika user memiliki permission Add, Edit, atau Delete, maka permission View otomatis akan aktif
- Permission bersifat hierarkis - user harus memiliki View untuk bisa melakukan operasi lain

## Menu yang Tersedia

Sistem mendukung menu-menu berikut:
- Dashboard
- Master Data (Barang, Kategori, Supplier, Satuan, Mapping Items)
- Gudang
- Transaksi (Stok Masuk, Stok Keluar, Stok Transfer)
- Pembelian (PO, Direct, Surat Jalan)
- Laporan (berbagai jenis laporan)
- Manajemen User
- Menu Access Management
- Setup
- Reset Stok Gudang

## Security Features

### 1. Access Control
- Setiap halaman dan operasi dilindungi dengan permission check
- User hanya bisa mengakses fitur sesuai role yang dimiliki

### 2. Input Validation
- Validasi input untuk mencegah SQL injection
- Pengecekan duplikasi menu access
- Validasi role dan menu yang valid

### 3. Activity Logging
- Semua operasi CRUD dicatat dalam activity log
- Informasi yang dicatat: user, action, table, record ID, description, IP address

### 4. Critical Access Protection
- Peringatan khusus untuk penghapusan akses role admin atau menu penting
- Konfirmasi tambahan untuk operasi yang berisiko

## Troubleshooting

### Masalah Umum

1. **Menu Access tidak muncul di navbar**
   - Pastikan user memiliki permission 'menu_access' dengan action 'view'
   - Cek apakah role user sudah memiliki akses ke menu access management

2. **Tidak bisa menambah menu access**
   - Pastikan user memiliki permission 'menu_access' dengan action 'add'
   - Cek apakah kombinasi role dan menu sudah ada (tidak boleh duplikat)

3. **Bulk operations tidak berfungsi**
   - Pastikan user memiliki permission yang sesuai
   - Cek apakah ada data yang dipilih untuk operasi bulk

### Debug Mode

Untuk debugging, gunakan file-file berikut:
- `/user/debug_role_access.php` - Debug role access
- `/user/test_role_access.php` - Test role access functionality

## Database Schema

### Tabel menu_access
```sql
CREATE TABLE menu_access (
    id INT PRIMARY KEY AUTO_INCREMENT,
    role_id INT NOT NULL,
    menu_name VARCHAR(50) NOT NULL,
    can_view TINYINT(1) DEFAULT 0,
    can_add TINYINT(1) DEFAULT 0,
    can_edit TINYINT(1) DEFAULT 0,
    can_delete TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
);
```

## Dependencies

- Bootstrap 5.1.3
- DataTables 1.11.5
- Bootstrap Icons 1.8.1
- jQuery 3.6.0

## Support

Untuk bantuan lebih lanjut, silakan hubungi administrator sistem atau konsultasikan dokumentasi access control di `/docs/ACCESS_CONTROL_GUIDE.md`. 