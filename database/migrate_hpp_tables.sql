-- Migrasi untuk tabel HPP
-- Created: 2026

-- Tabel utama untuk HPP (Harga Pokok Penjualan)
CREATE TABLE IF NOT EXISTS `hpp` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `kode_hpp` varchar(50) NOT NULL,
  `nama_barang_hpp` varchar(255) NOT NULL,
  `harga_jual` decimal(15,2) NOT NULL DEFAULT 0.00,
  `hpp_total` decimal(15,2) NOT NULL DEFAULT 0.00,
  `profit` decimal(15,2) NOT NULL DEFAULT 0.00,
  `persentase_profit` decimal(5,2) NOT NULL DEFAULT 0.00,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `kode_hpp` (`kode_hpp`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Tabel detail bahan baku untuk HPP
CREATE TABLE IF NOT EXISTS `hpp_detail_bahan` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hpp_id` int(11) NOT NULL,
  `barang_id` int(11) NOT NULL,
  `qty` decimal(10,2) NOT NULL DEFAULT 0.00,
  `harga_satuan` decimal(15,2) NOT NULL DEFAULT 0.00,
  `satuan` varchar(50) DEFAULT NULL,
  `total` decimal(15,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `hpp_id` (`hpp_id`),
  KEY `barang_id` (`barang_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Tabel detail biaya lainnya untuk HPP
CREATE TABLE IF NOT EXISTS `hpp_detail_biaya` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hpp_id` int(11) NOT NULL,
  `nama_biaya` varchar(255) NOT NULL,
  `nominal` decimal(15,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `hpp_id` (`hpp_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
