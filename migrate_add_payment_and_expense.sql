-- 1. Add payment_method to purchase_order table
ALTER TABLE `purchase_order` 
ADD COLUMN `payment_method` ENUM('cash', 'saldo') DEFAULT 'cash' AFTER `keterangan_reject`;

-- 2. Modify pengeluaran table: add payment_method and optional columns
ALTER TABLE `pengeluaran` 
ADD COLUMN `payment_method` ENUM('cash', 'saldo') DEFAULT 'cash' AFTER `keterangan`,
ADD COLUMN `deskripsi` TEXT AFTER `payment_method`;

-- 3. Create detail_pengeluaran table if not exists
CREATE TABLE IF NOT EXISTS `detail_pengeluaran` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `pengeluaran_id` int(11) NOT NULL,
  `nama_item` varchar(255) NOT NULL,
  `jumlah` int(11) NOT NULL DEFAULT 1,
  `harga_satuan` decimal(15,2) NOT NULL DEFAULT 0.00,
  `total_harga` decimal(15,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_pengeluaran` (`pengeluaran_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
