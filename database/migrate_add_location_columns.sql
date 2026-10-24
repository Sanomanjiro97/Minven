-- Migration script untuk menambahkan kolom lokasi ke users dan purchase_order
-- Tanggal: 29 Juli 2026

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

-- 4. Insert default payment method untuk lokasi yang ada
-- Sesuaikan dengan kebutuhan bisnis Anda
INSERT INTO `lokasi_payment_mapping` (`lokasi_id`, `default_payment_method`, `keterangan`) VALUES
(17, 'cash', 'Bar - default payment cash'),
(18, 'saldo', 'Kitchen - default payment saldo'),
(19, 'cash', 'Front House - default payment cash')
ON DUPLICATE KEY UPDATE 
  default_payment_method = VALUES(default_payment_method),
  keterangan = VALUES(keterangan);
