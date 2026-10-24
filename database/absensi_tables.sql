-- SQL Script untuk Tabel Sistem Absensi
-- Database: minven

-- Tabel shifts: Menyimpan data shift kerja
CREATE TABLE IF NOT EXISTS `shifts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nama_shift` varchar(50) NOT NULL,
  `jam_mulai` time NOT NULL,
  `jam_selesai` time NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Tabel absensi: Menyimpan data kehadiran karyawan
CREATE TABLE IF NOT EXISTS `absensi` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `gudang_id` int(11) DEFAULT 1,
  `tanggal` date NOT NULL,
  `jam_masuk` time NOT NULL,
  `jam_keluar` time DEFAULT NULL,
  `durasi` time DEFAULT NULL,
  `shift_id` int(11) DEFAULT NULL,
  `status_kehadiran` varchar(20) DEFAULT 'Hadir',
  `keterlambatan` time DEFAULT '00:00:00',
  `keterangan` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_tanggal` (`user_id`,`tanggal`),
  KEY `idx_tanggal` (`tanggal`),
  KEY `idx_gudang` (`gudang_id`),
  KEY `idx_shift` (`shift_id`),
  CONSTRAINT `fk_absensi_shift` FOREIGN KEY (`shift_id`) REFERENCES `shifts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_absensi_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Data default untuk shift
INSERT INTO `shifts` (`nama_shift`, `jam_mulai`, `jam_selesai`, `is_active`) VALUES
('Pagi', '08:00:00', '16:00:00', 1),
('Siang', '16:00:00', '00:00:00', 1),
('Malam', '00:00:00', '08:00:00', 1);