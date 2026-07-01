-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 31, 2026 at 08:29 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `minven_pro`
--

-- --------------------------------------------------------

--
-- Table structure for table `absensi`
--

CREATE TABLE `absensi` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `gudang_id` int(11) NOT NULL,
  `tanggal` date NOT NULL,
  `shift` enum('pagi','siang','malam') DEFAULT 'pagi',
  `shift_id` int(11) DEFAULT NULL,
  `jam_masuk` time DEFAULT NULL,
  `jam_keluar` time DEFAULT NULL,
  `durasi` time DEFAULT NULL,
  `status_kehadiran` enum('hadir','izin','sakit','cuti','alpha') DEFAULT 'hadir',
  `keterangan` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `barang`
--

CREATE TABLE `barang` (
  `id` int(11) NOT NULL,
  `kode_barang` varchar(20) NOT NULL,
  `nama_barang` varchar(100) NOT NULL,
  `barcode` varchar(100) DEFAULT NULL,
  `barcode_dus` varchar(100) DEFAULT NULL,
  `satuan` varchar(50) DEFAULT NULL,
  `kategori_id` int(11) DEFAULT NULL,
  `satuan_id` int(11) DEFAULT NULL,
  `supplier_id` int(11) DEFAULT NULL,
  `stok_minimum` int(11) DEFAULT 0,
  `harga_beli` decimal(15,2) DEFAULT 0.00,
  `harga_po` decimal(15,2) DEFAULT NULL,
  `harga_jual` decimal(15,2) DEFAULT 0.00,
  `gambar` varchar(255) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expired_at` date DEFAULT NULL,
  `baku_non_baku` enum('baku','non_baku') DEFAULT 'non_baku'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `barang`
--

INSERT INTO `barang` (`id`, `kode_barang`, `nama_barang`, `barcode`, `barcode_dus`, `satuan`, `kategori_id`, `satuan_id`, `supplier_id`, `stok_minimum`, `harga_beli`, `harga_po`, `harga_jual`, `gambar`, `created_by`, `created_at`, `expired_at`, `baku_non_baku`) VALUES
(532, 'B-MCC', 'Bubuk Cotton Candy Arteristo', '', '', NULL, 122, 87, 12484, 120, 0.00, 63000.00, 0.00, NULL, 7, '2026-05-31 10:45:53', NULL, 'non_baku'),
(533, 'B-MCKLT', 'Bubuk Chocolate Arteristo', '', '', NULL, 122, 87, 12484, 120, 186.00, 100000.00, 0.00, NULL, 7, '2026-05-31 10:45:53', NULL, 'non_baku'),
(535, 'B-MMTC', 'Bubuk Matcha Tofico', '', '', NULL, 122, 87, 12484, 120, 234.00, 234000.00, 0.00, NULL, 7, '2026-05-31 10:45:53', NULL, 'non_baku'),
(536, 'B-MSTW', 'Bubuk Strawberry Tofico', '', '', NULL, 122, 87, 12484, 120, 150.00, 150000.00, 0.00, NULL, 7, '2026-05-31 10:45:53', NULL, 'non_baku'),
(537, 'B-MTRO', 'Bubuk Taro Ateristo', '', '', NULL, 122, 87, 12484, 140, 126.00, 63000.00, 0.00, NULL, 7, '2026-05-31 10:45:53', NULL, 'non_baku'),
(538, 'B-TLM', 'Bubuk Lemon Tea', '', '', NULL, 122, 87, 12484, 200, 0.00, 106000.00, 0.00, NULL, 7, '2026-05-31 10:45:53', NULL, 'non_baku'),
(539, 'BB-ASYP', 'Ayam Sayap', '', '', NULL, 121, 91, 12473, 16, 1782.00, 41000.00, 0.00, NULL, 7, '2026-05-31 10:45:53', NULL, 'non_baku'),
(540, 'BB-BSCF', 'Biscoff Biiscuit', NULL, NULL, NULL, 121, 91, 12484, 8, 1459.00, NULL, 0.00, NULL, 7, '2026-05-31 10:45:53', NULL, 'baku'),
(542, 'BB-GLA', 'Gula Pasir', NULL, NULL, NULL, 121, 87, 12486, 100, 19.00, NULL, 0.00, NULL, 7, '2026-05-31 10:45:53', NULL, 'baku'),
(543, 'BB-KCP', 'Kecap', NULL, NULL, NULL, 121, 89, 12486, 100, 0.00, NULL, 0.00, NULL, 7, '2026-05-31 10:45:53', NULL, 'baku'),
(544, 'BB-MGR', 'Indomie Goreng', NULL, NULL, NULL, 121, 91, 12486, 10, 3200.00, NULL, 0.00, NULL, 7, '2026-05-31 10:45:53', NULL, 'baku'),
(545, 'BB-MRB', 'Indomie Rebus', NULL, NULL, NULL, 121, 91, 12486, 10, 3100.00, NULL, 0.00, NULL, 7, '2026-05-31 10:45:53', NULL, 'baku'),
(546, 'BB-MX', 'Bubuk Max Creamer', '', '', NULL, 121, 87, 12484, 500, 84.00, 49000.00, 0.00, NULL, 7, '2026-05-31 10:45:53', NULL, 'non_baku'),
(548, 'BB-SKM', 'Susu Kental Manis', '', '', NULL, 121, 87, 12486, 1480, 34.00, 8600.00, 0.00, NULL, 7, '2026-05-31 10:45:53', NULL, 'non_baku'),
(549, 'BB-SOR', 'Saori (1L)', NULL, NULL, NULL, 121, 89, 12486, 200, 13.00, NULL, 0.00, NULL, 7, '2026-05-31 10:45:53', NULL, 'baku'),
(551, 'BB-TKT', 'Tepung Kentucky', '', '', NULL, 121, 87, 12486, 500, 0.00, 21000.00, 0.00, NULL, 7, '2026-05-31 10:45:53', NULL, 'non_baku'),
(552, 'BB-TLR', 'Telur', NULL, NULL, NULL, 121, 91, 12482, 10, 1900.00, NULL, 0.00, NULL, 7, '2026-05-31 10:45:53', NULL, 'non_baku'),
(553, 'BB-TTLE', 'Totole', '', '', NULL, 121, 87, 12486, 50, 135.00, 54000.00, 0.00, NULL, 7, '2026-05-31 10:45:53', NULL, 'non_baku'),
(554, 'BB-UHT', 'Susu Diamond UHT', '', '', NULL, 121, 89, 12486, 3000, 22.00, 22000.00, 0.00, NULL, 7, '2026-05-31 10:45:53', NULL, 'non_baku'),
(555, 'BB-VIT', 'VIT 330 Ml', '', '', NULL, 121, 91, 12476, 8, 1666.00, 40000.00, 0.00, NULL, 7, '2026-05-31 10:45:53', NULL, 'non_baku'),
(556, 'BB-YKLT', 'Yakult', '', '', NULL, 121, 91, 12486, 5, 2100.00, 10500.00, 0.00, NULL, 7, '2026-05-31 10:45:53', NULL, 'non_baku'),
(557, 'C-ARBC', 'Arabica Beans Classic', '', '', NULL, 123, 87, 12485, 250, 245.00, 245000.00, 0.00, NULL, 7, '2026-05-31 10:45:53', NULL, 'non_baku'),
(558, 'C-ARBK', 'Arabica Beans Kopsu', '', '', NULL, 123, 87, 12474, 500, 235.00, 235000.00, 0.00, NULL, 7, '2026-05-31 10:45:53', NULL, 'non_baku'),
(559, 'EUR-3W', 'Eurution 3 warna (bar)', NULL, NULL, NULL, 126, 91, 12483, 1, 0.00, NULL, 0.00, NULL, 7, '2026-05-31 10:45:53', NULL, 'non_baku'),
(560, 'EUR-7w', 'Eurution 7w Kuning', NULL, NULL, NULL, 126, 91, 12483, 1, 0.00, NULL, 0.00, NULL, 7, '2026-05-31 10:45:53', NULL, 'non_baku'),
(561, 'FF-MRM', 'Mister Max Sosis', '', '', NULL, 124, 91, 12486, 5, 1166.00, 28000.00, 0.00, NULL, 7, '2026-05-31 10:45:53', NULL, 'non_baku'),
(562, 'FF-CHR', 'Churros', '', '', NULL, 124, 92, 12486, 2, 0.00, 25000.00, 0.00, NULL, 7, '2026-05-31 10:45:53', NULL, 'non_baku'),
(563, 'FF-CHSK', 'Chicken Skin (Fiesta Crispy Crunch)', '', '', NULL, 124, 90, 12486, 2, 0.00, 25500.00, 0.00, NULL, 7, '2026-05-31 10:45:53', NULL, 'non_baku'),
(564, 'FF-CPLT', 'Cireng Platter', '', '', NULL, 124, 92, 12486, 2, 3125.00, 0.00, 0.00, NULL, 7, '2026-05-31 10:45:53', NULL, 'baku'),
(565, 'FF-CRGLD', 'Cireng Lada Garam', '', '', NULL, 124, 92, 12486, 3, 6250.00, 0.00, 0.00, NULL, 7, '2026-05-31 10:45:53', NULL, 'baku'),
(566, 'FF-CRGP', 'Cireng Porsian', '', '', NULL, 124, 92, 12486, 4, 4687.00, 0.00, 0.00, NULL, 7, '2026-05-31 10:45:53', NULL, 'baku'),
(567, 'FF-DAD', 'Ayam Dada', NULL, NULL, NULL, 124, 91, 12483, 2, 0.00, NULL, 0.00, NULL, 7, '2026-05-31 10:45:53', NULL, 'non_baku'),
(568, 'FF-FJWL', 'French Fries Jawil', '', '', NULL, 124, 92, 12486, 3, 0.00, 0.00, 0.00, NULL, 7, '2026-05-31 10:45:53', NULL, 'non_baku'),
(569, 'FF-FPLT', 'French Fries Platter', '', '', NULL, 124, 92, 12486, 2, 3153.00, 0.00, 0.00, NULL, 7, '2026-05-31 10:45:53', NULL, 'non_baku'),
(570, 'FF-FPOR', 'French Fries Porsian', '', '', NULL, 124, 92, 12486, 4, 6307.00, 0.00, 0.00, NULL, 7, '2026-05-31 10:45:53', NULL, 'non_baku'),
(571, 'FF-KANZ', 'Sosis Kanzler', '', '', NULL, 124, 91, 12486, 8, 1357.00, 28500.00, 0.00, NULL, 7, '2026-05-31 10:45:53', NULL, 'non_baku'),
(572, 'FF-KARG', 'Karage Porsian', '', '', NULL, 124, 92, 12486, 3, 0.00, 38000.00, 0.00, NULL, 7, '2026-05-31 10:45:53', NULL, 'non_baku'),
(573, 'FF-KTSU', 'Katsu Porsian', '', '', NULL, 124, 92, 12486, 5, 10250.00, 41000.00, 0.00, NULL, 7, '2026-05-31 10:45:53', NULL, 'non_baku'),
(574, 'FF-SUW', 'Ayam Suwir', '', '', NULL, 124, 92, 12483, 1, 0.00, 0.00, 0.00, NULL, 7, '2026-05-31 10:45:53', NULL, 'non_baku'),
(575, 'FF-WDGS', 'Wedges Porsian', '', '', NULL, 124, 92, 12486, 3, 0.00, 0.00, 0.00, NULL, 7, '2026-05-31 10:45:53', NULL, 'non_baku'),
(576, 'GR-APL', 'Apel', NULL, NULL, NULL, 125, 91, 12484, 5, 0.00, NULL, 0.00, NULL, 7, '2026-05-31 10:45:53', NULL, 'non_baku'),
(577, 'GR-GMB', 'Gummy Bear', NULL, NULL, NULL, 125, 91, 12486, 9, 0.00, NULL, 0.00, NULL, 7, '2026-05-31 10:45:53', NULL, 'non_baku'),
(578, 'GR-LMGC', 'Lemon Garmish GC', NULL, NULL, NULL, 125, 90, 12484, 2, 23000.00, NULL, 0.00, NULL, 7, '2026-05-31 10:45:53', NULL, 'non_baku'),
(579, 'GR-LMO', 'Lemon Garmish', NULL, NULL, NULL, 125, 91, 12484, 12, 0.00, NULL, 0.00, NULL, 7, '2026-05-31 10:45:53', NULL, 'non_baku'),
(580, 'GR-PINE', 'Nanas', NULL, NULL, NULL, 125, 91, 12480, 7, 0.00, NULL, 0.00, NULL, 7, '2026-05-31 10:45:53', NULL, 'non_baku'),
(581, 'GR-POP', 'Poppin', NULL, NULL, NULL, 125, 91, 12484, 7, 0.00, NULL, 0.00, NULL, 7, '2026-05-31 10:45:53', NULL, 'non_baku'),
(582, 'GR-POPGC', 'Poppin', NULL, NULL, NULL, 125, 90, 12484, 1, 0.00, NULL, 0.00, NULL, 7, '2026-05-31 10:45:53', NULL, 'non_baku'),
(583, 'GR-PPS', 'Pocky Stick', NULL, NULL, NULL, 125, 91, 12486, 8, 0.00, NULL, 0.00, NULL, 7, '2026-05-31 10:45:53', NULL, 'non_baku'),
(584, 'KRIS-5w', 'Krisbow 5w Warm White', NULL, NULL, NULL, 126, 91, 12483, 1, 0.00, NULL, 0.00, NULL, 7, '2026-05-31 10:45:53', NULL, 'non_baku'),
(585, 'KRIS-7w', 'Krisbow 7w Coolday white', NULL, NULL, NULL, 126, 91, 12483, 1, 0.00, NULL, 0.00, NULL, 7, '2026-05-31 10:45:53', NULL, 'non_baku'),
(586, 'LMP-K8w', 'Philips 8w kuning', NULL, NULL, NULL, 131, 91, 12483, 1, 0.00, NULL, 0.00, NULL, 7, '2026-05-31 10:45:53', NULL, 'non_baku'),
(588, 'PK-10', 'Plastik Ukr 10', '', '', NULL, 127, 90, 12481, 1, 0.00, 5299.00, 0.00, NULL, 7, '2026-05-31 10:45:53', NULL, 'non_baku'),
(589, 'PK-24', 'Plastik Ukr 24', '', '', NULL, 127, 90, 12481, 1, 0.00, 14986.00, 0.00, NULL, 7, '2026-05-31 10:45:53', NULL, 'non_baku'),
(590, 'PK-26', 'Plastik Ukr 26', '', '', NULL, 127, 90, 12481, 1, 0.00, 29286.00, 0.00, NULL, 7, '2026-05-31 10:45:53', NULL, 'non_baku'),
(591, 'PK-BTL1', 'Botol Kale 1 Liter', '', '', NULL, 127, 91, 12481, 3, 2649.00, 2649.00, 0.00, NULL, 7, '2026-05-31 10:45:53', NULL, 'non_baku'),
(592, 'PK-CAN', 'Kaleng (PET)', NULL, NULL, NULL, 127, 91, 12475, 25, 0.00, NULL, 0.00, NULL, 7, '2026-05-31 10:45:53', NULL, 'non_baku'),
(593, 'PK-PC', 'Plastic Cup', NULL, NULL, NULL, 127, 91, 12470, 250, 950.00, NULL, 0.00, NULL, 7, '2026-05-31 10:45:53', NULL, 'non_baku'),
(594, 'PK-PL', 'Plastic Tutup', NULL, NULL, NULL, 127, 91, 12470, 250, 0.00, NULL, 0.00, NULL, 7, '2026-05-31 10:45:53', NULL, 'non_baku'),
(595, 'PK-SDT', 'Sedotan Merah', NULL, NULL, NULL, 131, 91, 12483, 0, 0.00, NULL, 0.00, NULL, 7, '2026-05-31 10:45:53', NULL, 'non_baku'),
(596, 'PK-TT2', 'Take Away Tray 2 cup', NULL, NULL, NULL, 127, 91, 12481, 25, 1623.98, NULL, 0.00, NULL, 7, '2026-05-31 10:45:53', NULL, 'non_baku'),
(597, 'PK-TT4', 'Take Away Tray 4 cup', NULL, NULL, NULL, 127, 91, 12481, 25, 2249.00, NULL, 0.00, NULL, 7, '2026-05-31 10:45:53', NULL, 'non_baku'),
(598, 'SAN-LIVI', 'Tissue Livi', '', '', NULL, 128, 91, 12483, 5, 3432.00, 205893.00, 0.00, NULL, 7, '2026-05-31 10:45:53', NULL, 'non_baku'),
(599, 'SAY-BWD', 'Bawang Daun', '', '', NULL, 130, 87, 12482, 0, 0.00, 3000.00, 0.00, NULL, 7, '2026-05-31 10:45:53', NULL, 'non_baku'),
(600, 'SAY-BWM', 'Bawang Merah', '', '', NULL, 130, 87, 12482, 100, 0.00, 20000.00, 0.00, NULL, 7, '2026-05-31 10:45:54', NULL, 'non_baku'),
(601, 'SAY-BWP', 'Bawang Putih', '', '', NULL, 130, 87, 12482, 100, 0.00, 18000.00, 0.00, NULL, 7, '2026-05-31 10:45:54', NULL, 'non_baku'),
(602, 'SAY-TIM', 'Timun', '', '', NULL, 130, 87, 12482, 150, 0.00, 7000.00, 0.00, NULL, 7, '2026-05-31 10:45:54', NULL, 'non_baku'),
(603, 'SAY-TOM', 'Tomat', '', '', NULL, 130, 87, 12482, 100, 0.00, 8000.00, 0.00, NULL, 7, '2026-05-31 10:45:54', NULL, 'non_baku'),
(604, 'SER-KAT', 'Kertas Anti Tumpah', NULL, NULL, NULL, 131, 91, 12483, 50, 0.00, NULL, 0.00, NULL, 7, '2026-05-31 10:45:54', NULL, 'non_baku'),
(605, 'SER-ZIP', 'Zip Tie Satuan', NULL, NULL, NULL, 131, 91, 12483, 0, 0.00, NULL, 0.00, NULL, 7, '2026-05-31 10:45:54', NULL, 'non_baku'),
(606, 'SO-BBQ', 'Delmonte Saos BBQ', '', '', NULL, 129, 87, 12486, 200, 335.00, 33500.00, 0.00, NULL, 7, '2026-05-31 10:45:54', NULL, 'non_baku'),
(607, 'SO-CUR', 'Saos Curry', '', '', NULL, 129, 91, 12489, 8, 4900.00, 49000.00, 0.00, NULL, 7, '2026-05-31 10:45:54', NULL, 'non_baku'),
(608, 'SO-JWL', 'Saos Jawil', NULL, NULL, NULL, 129, 90, 12483, 3, 8775.00, NULL, 0.00, NULL, 7, '2026-05-31 10:45:54', NULL, 'non_baku'),
(609, 'SO-SKJ', 'Saos Keju', '', '', NULL, 129, 87, 12486, 100, 0.00, 22000.00, 0.00, NULL, 7, '2026-05-31 10:45:54', NULL, 'non_baku'),
(610, 'SO-SSS', 'Saos Sambal Saset ABC', NULL, NULL, NULL, 129, 91, 12486, 5, 7500.00, NULL, 0.00, NULL, 7, '2026-05-31 10:45:54', NULL, 'non_baku'),
(611, 'SO-TMS', 'Saos Tomat Saset ABC', '', '', NULL, 129, 91, 12486, 5, 0.00, 6500.00, 0.00, NULL, 7, '2026-05-31 10:45:54', NULL, 'non_baku'),
(612, 'SO-VOL', 'Mama Suka Saos Volcano', '', '', NULL, 129, 87, 12486, 200, 0.00, 35000.00, 0.00, NULL, 7, '2026-05-31 10:45:54', NULL, 'non_baku'),
(613, 'SS-HG', 'Sarung Tangan', NULL, NULL, NULL, 131, 91, 12483, 12, 0.00, NULL, 0.00, NULL, 7, '2026-05-31 10:45:54', NULL, 'non_baku'),
(614, 'SY-APPL', 'Syrup Apple Delifru', '', '', NULL, 133, 87, 12484, 0, 0.00, 110999.98, 0.00, NULL, 7, '2026-05-31 10:45:54', NULL, 'non_baku'),
(615, 'SY-BLGN', 'Syrup Blue Lagoon Delifru', '', '', NULL, 133, 89, 12484, 70, 0.00, 111000.00, 0.00, NULL, 7, '2026-05-31 10:45:54', NULL, 'non_baku'),
(616, 'SY-BUTS', 'Syrup Butterscotch Trieste', '', '', NULL, 133, 89, 12484, 70, 77.00, 77000.00, 0.00, NULL, 7, '2026-05-31 10:45:54', NULL, 'non_baku'),
(617, 'SY-CRML', 'Syrup Caramel Trieste', '', '', NULL, 133, 89, 12484, 70, 77.00, 77000.00, 0.00, NULL, 7, '2026-05-31 10:45:54', NULL, 'non_baku'),
(618, 'SY-HAZE', 'Syrup Hazelnut Trieste', '', '', NULL, 133, 89, 12484, 70, 77.00, 77000.00, 0.00, NULL, 7, '2026-05-31 10:45:54', NULL, 'non_baku'),
(619, 'SY-LMEX', 'Syrup Lemon Extract', NULL, NULL, NULL, 133, 89, 12484, 50, 0.00, NULL, 0.00, NULL, 7, '2026-05-31 10:45:54', NULL, 'non_baku'),
(620, 'SY-LYCHE', 'Syrup Lychee Delifru', '', '', NULL, 133, 89, 12484, 70, 0.00, 110999.97, 0.00, NULL, 7, '2026-05-31 10:45:54', NULL, 'non_baku'),
(621, 'SY-RSTD', 'Syrup Roasted Almond Davinci', '', '', NULL, 133, 89, 12484, 90, 77.00, 77000.00, 0.00, NULL, 7, '2026-05-31 10:45:54', NULL, 'non_baku'),
(622, 'TB-TEA', 'Tea Bag', NULL, NULL, NULL, 134, 91, 12486, 10, 260.00, NULL, 0.00, NULL, 7, '2026-05-31 10:45:54', NULL, 'non_baku'),
(623, 'UT-KRB', 'Kertas Roll Bon', NULL, NULL, NULL, 131, 91, 12483, 2, 7000.00, NULL, 0.00, NULL, 7, '2026-05-31 10:45:54', NULL, 'non_baku'),
(630, 'SY-BEINDLCE', 'Syrup BEIN DOLCE', '', '', NULL, 133, 91, 12471, 0, 0.00, 72500.00, 0.00, NULL, 7, '2026-05-31 10:45:54', NULL, 'non_baku'),
(631, 'SY-BEINPCH', 'Syrup BEIN Peach', '', '', NULL, 133, 91, 12471, 0, 72500.00, 72500.00, 0.00, NULL, 7, '2026-05-31 10:45:54', NULL, 'non_baku'),
(632, 'BU-BKBB', 'Bumbu Kentang BBQ Indofood', '', '', NULL, 121, 87, 12486, 25, 200.00, 5000.00, 0.00, NULL, 7, '2026-05-31 10:45:54', NULL, 'baku'),
(633, 'BU-BKJB', 'Bumbu Kentang Jagung Bakar Indofood', '', '', NULL, 121, 87, 12486, 25, 200.00, 5000.00, 0.00, NULL, 7, '2026-05-31 10:45:54', NULL, 'non_baku'),
(635, 'SER-BONC10', 'Bon Cabe lvl 10', '', '', NULL, 125, 91, 12486, 0, 0.00, 10000.00, 0.00, NULL, 7, '2026-05-31 10:45:54', NULL, 'non_baku'),
(637, 'BB-BRS', 'Resto Beras SLYP Super 5kg', '', '', NULL, 121, 87, 12486, 1000, 16.00, 78000.00, 0.00, NULL, 7, '2026-05-31 10:45:54', NULL, 'non_baku'),
(644, 'SAY-CBE', 'Cabe Merah', '', '', NULL, 130, 87, 12482, 0, 0.00, 35000.00, 0.00, NULL, 7, '2026-05-31 10:45:54', NULL, 'non_baku'),
(647, 'ZZ-Cireng Rujak pak', 'Cireng Rujak isi 16', '', '', NULL, 124, 90, 12486, 0, 0.00, 12500.00, 0.00, NULL, 7, '2026-05-31 10:45:54', NULL, 'non_baku'),
(648, 'ZZ-CKR', 'Cikur', NULL, NULL, NULL, 121, 91, 12482, 0, 0.00, NULL, 0.00, NULL, 7, '2026-05-31 10:45:54', NULL, 'non_baku'),
(652, 'ZZ-DJ', 'Daun Jeruk 36gr', NULL, NULL, NULL, 121, 87, 12482, 0, 0.00, NULL, 0.00, NULL, 7, '2026-05-31 10:45:54', NULL, 'non_baku'),
(653, 'ZZ-DNPIS', 'Daun Pisang', NULL, NULL, NULL, 125, 91, 12482, 0, 0.00, NULL, 0.00, NULL, 7, '2026-05-31 10:45:54', NULL, 'non_baku'),
(654, 'BB-FILMA', 'Mentega Filma (200 gr)', '', '', NULL, 121, 91, 12486, 0, 38.00, 7500.00, 0.00, NULL, 7, '2026-05-31 10:45:54', NULL, 'non_baku'),
(655, 'ZZ-GCLE', 'Galon Cleo', NULL, NULL, NULL, 121, 91, 12476, 0, 23000.00, NULL, 0.00, NULL, 7, '2026-05-31 10:45:54', NULL, 'non_baku'),
(656, 'ZZ-GLIS', 'Galon Isi Ulang', NULL, NULL, NULL, 121, 91, 12476, 0, 6000.00, NULL, 0.00, NULL, 7, '2026-05-31 10:45:54', NULL, 'non_baku'),
(657, 'ZZ-HASOAP', 'Yuri Hand Wash 375 ML', NULL, NULL, NULL, 128, 91, 12486, 0, 19000.00, NULL, 0.00, NULL, 7, '2026-05-31 10:45:54', NULL, 'non_baku'),
(658, 'ZZ-HASOP-YOA', 'Yoa Hand Wash 4L', NULL, NULL, NULL, 128, 91, 12473, 0, 95000.00, NULL, 0.00, NULL, 7, '2026-05-31 10:45:54', NULL, 'non_baku'),
(660, 'ZZ-HEK', 'Isi Ulang Hektar', NULL, NULL, NULL, 132, 90, 12483, 0, 0.00, NULL, 0.00, NULL, 7, '2026-05-31 10:45:54', NULL, 'non_baku'),
(663, 'ZZ-KAVSS', 'Kentang Aviko Shoestring 2.5 Kg', NULL, NULL, NULL, 124, 90, 12486, 0, 82000.00, NULL, 0.00, NULL, 7, '2026-05-31 10:45:54', NULL, 'non_baku'),
(664, 'ZZ-KMCSS', 'Kentang Mcain Shoestring 1kg', NULL, NULL, NULL, 124, 88, 12486, 0, 28000.00, NULL, 0.00, NULL, 7, '2026-05-31 10:45:54', NULL, 'non_baku'),
(665, 'ZZ-KRO', 'Kerupuk Oren', NULL, NULL, NULL, 121, 87, 12482, 0, 0.00, NULL, 0.00, NULL, 7, '2026-05-31 10:45:54', NULL, 'non_baku'),
(666, 'ZZ-KRP', 'Kerupuk Putih', NULL, NULL, NULL, 121, 87, 12482, 0, 0.00, NULL, 0.00, NULL, 7, '2026-05-31 10:45:54', NULL, 'non_baku'),
(668, 'ZZ-LMO', 'Lemon', NULL, NULL, NULL, 121, 91, 12482, 0, 0.00, NULL, 0.00, NULL, 7, '2026-05-31 10:45:54', NULL, 'non_baku'),
(676, 'BB-MNYKG', 'Sunco 2L Pouch', '', '', NULL, 121, 89, 12486, 0, 0.00, 45000.00, 0.00, NULL, 7, '2026-05-31 10:45:54', NULL, 'non_baku'),
(677, 'BB-MNYKS1', 'Sunco 1L Pouch', '', '', NULL, 121, 89, 12486, 0, 22500.00, 224999.99, 0.00, NULL, 7, '2026-05-31 10:45:54', NULL, 'non_baku'),
(678, 'ZZ-MNYKSUN', 'Minyak Sunco 5 Liter', '', '', NULL, 121, 89, 12472, 0, 0.00, 118000.00, 0.00, NULL, 7, '2026-05-31 10:45:54', NULL, 'non_baku'),
(683, 'ZZ-OAT', 'Oatside Milk 1L', NULL, NULL, NULL, 121, 91, 12484, 0, 42000.00, NULL, 0.00, NULL, 7, '2026-05-31 10:45:54', NULL, 'non_baku'),
(684, 'SER-PARS', 'Jays Parsley', '', '', NULL, 125, 91, 12486, 0, 0.00, 0.00, 0.00, NULL, 7, '2026-05-31 10:45:54', NULL, 'non_baku'),
(685, 'ZZ-PEN', 'Pulpen', NULL, NULL, NULL, 132, 91, 12483, 0, 0.00, NULL, 0.00, NULL, 7, '2026-05-31 10:45:54', NULL, 'non_baku'),
(686, 'ZZ-PINE', 'Garnish Nanas Kering 100gr', NULL, NULL, NULL, 125, 90, 12480, 0, 56000.00, NULL, 0.00, NULL, 7, '2026-05-31 10:45:54', NULL, 'non_baku'),
(687, 'ZZ-PPS', 'Pocky', NULL, NULL, NULL, 125, 90, 12486, 0, 8000.00, NULL, 0.00, NULL, 7, '2026-05-31 10:45:54', NULL, 'non_baku'),
(688, 'ZZ-RBL', 'Buah Lychee Red Boat', NULL, NULL, NULL, 121, 90, 12484, 0, 27500.00, NULL, 0.00, NULL, 7, '2026-05-31 10:45:54', NULL, 'non_baku'),
(690, 'ZZ-SAL', 'Salada', NULL, NULL, NULL, 121, 91, 12482, 0, 0.00, NULL, 0.00, NULL, 7, '2026-05-31 10:45:54', NULL, 'non_baku'),
(691, 'ZZ-SDO', 'Soda', NULL, NULL, NULL, 121, 91, 12484, 5, 0.00, NULL, 0.00, NULL, 7, '2026-05-31 10:45:54', NULL, 'non_baku'),
(692, 'ZZ-SDT', 'Sedotan', NULL, NULL, NULL, 131, 90, 12483, 0, 0.00, NULL, 0.00, NULL, 7, '2026-05-31 10:45:54', NULL, 'non_baku'),
(697, 'SO-SMAYO', 'Mayo Gourment 1KG', '', '', NULL, 129, 90, 12486, 0, 0.00, 0.00, 0.00, NULL, 7, '2026-05-31 10:45:54', NULL, 'non_baku'),
(698, 'SO-SOSTRF', 'Delmonte Saos Tomat Refill', '', '', NULL, 129, 87, 12486, 300, 17.00, 17000.00, 0.00, NULL, 7, '2026-05-31 10:45:54', NULL, 'non_baku'),
(699, 'ZZ-SPDLH', 'Spidol Hitam', NULL, NULL, NULL, 132, 91, 12483, 0, 0.00, NULL, 0.00, NULL, 7, '2026-05-31 10:45:54', NULL, 'non_baku'),
(700, 'PK-SPDLM', 'Spidol Merah', '', '', NULL, 132, 91, 12483, 0, 0.00, 0.00, 0.00, NULL, 7, '2026-05-31 10:45:54', NULL, 'non_baku'),
(701, 'ZZ-SPEL', 'Super pell', NULL, NULL, NULL, 128, 91, 12486, 0, 16000.00, NULL, 0.00, NULL, 7, '2026-05-31 10:45:54', NULL, 'non_baku'),
(702, 'ZZ-SPG', 'Sponge', NULL, NULL, NULL, 128, 91, 12486, 0, 0.00, NULL, 0.00, NULL, 7, '2026-05-31 10:45:54', NULL, 'non_baku'),
(703, 'SO-SSM', 'Saos Sambel Delmonte 1KG', '', '', NULL, 129, 87, 12486, 300, 21000.00, 0.00, 0.00, NULL, 7, '2026-05-31 10:45:54', NULL, 'non_baku'),
(705, 'ZZ-SUN', 'Sunlight Pouch 610 ML', NULL, NULL, NULL, 128, 91, 12486, 0, 9899.97, NULL, 0.00, NULL, 7, '2026-05-31 10:45:54', NULL, 'non_baku'),
(708, 'ZZ-TLG', 'Tulang Seblak', NULL, NULL, NULL, 121, 91, 12482, 0, 0.00, NULL, 0.00, NULL, 7, '2026-05-31 10:45:54', NULL, 'non_baku'),
(711, 'ZZ-TRASH', 'Trash Bag (L)', NULL, NULL, NULL, 128, 90, 12486, 0, 22000.00, NULL, 0.00, NULL, 7, '2026-05-31 10:45:54', NULL, 'non_baku'),
(715, 'ZZ-WDGS', 'Spicy Wedges 1KG', NULL, NULL, NULL, 124, 88, 12486, 0, 65000.00, NULL, 0.00, NULL, 7, '2026-05-31 10:45:54', NULL, 'non_baku'),
(716, 'ZZ-WDT-L', 'Wadah Takeaway (Large)', NULL, NULL, NULL, 131, 91, 12483, 0, 0.00, NULL, 0.00, NULL, 7, '2026-05-31 10:45:54', NULL, 'non_baku'),
(717, 'ZZ-WDT-S', 'Wadah Takeaway (small)', NULL, NULL, NULL, 131, 90, 12483, 0, 0.00, NULL, 0.00, NULL, 7, '2026-05-31 10:45:54', NULL, 'non_baku'),
(720, 'ZZ-ZPTIE', 'Zip Tie', NULL, NULL, NULL, 131, 90, 12483, 0, 5500.00, NULL, 0.00, NULL, 7, '2026-05-31 10:45:54', NULL, 'non_baku');

-- --------------------------------------------------------

--
-- Table structure for table `barang_split_setup`
--

CREATE TABLE `barang_split_setup` (
  `id` int(11) NOT NULL,
  `parent_barang_id` int(11) NOT NULL,
  `split_barang_id` int(11) NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `barang_split_setup`
--

INSERT INTO `barang_split_setup` (`id`, `parent_barang_id`, `split_barang_id`, `created_by`, `created_at`) VALUES
(3, 378, 386, 7, '2026-02-15 02:06:46'),
(4, 378, 385, 7, '2026-02-15 02:06:46'),
(5, 387, 388, 1, '2026-02-15 03:19:41'),
(6, 389, 390, 1, '2026-02-15 03:19:57'),
(7, 379, 392, 7, '2026-02-15 04:05:29'),
(8, 379, 391, 7, '2026-02-15 04:05:29'),
(10, 393, 395, 7, '2026-02-15 04:49:51'),
(11, 393, 394, 7, '2026-02-15 04:49:51'),
(12, 390, 389, 7, '2026-02-15 05:26:24'),
(13, 396, 397, 1, '2026-02-15 08:59:26'),
(14, 375, 374, 7, '2026-02-16 04:40:51'),
(0, 361, 360, 1, '2026-02-25 06:35:16'),
(0, 443, 389, 1, '2026-03-01 09:53:31'),
(0, 444, 390, 1, '2026-03-01 09:53:42'),
(0, 445, 371, 1, '2026-03-01 09:53:51'),
(0, 452, 391, 1, '2026-03-01 09:54:14'),
(0, 454, 413, 1, '2026-03-01 09:54:22'),
(0, 461, 392, 1, '2026-03-01 09:54:37'),
(0, 462, 393, 1, '2026-03-01 09:54:49'),
(0, 462, 394, 1, '2026-03-01 09:54:49'),
(0, 462, 395, 1, '2026-03-01 09:54:49'),
(0, 465, 422, 1, '2026-03-01 09:55:09'),
(0, 474, 399, 1, '2026-03-01 09:55:26'),
(0, 475, 397, 1, '2026-03-01 09:55:37'),
(0, 475, 398, 1, '2026-03-01 09:55:37'),
(0, 476, 397, 1, '2026-03-01 09:55:44'),
(0, 476, 398, 1, '2026-03-01 09:55:44'),
(0, 479, 400, 1, '2026-03-01 09:55:52'),
(0, 481, 420, 1, '2026-03-01 09:55:59'),
(0, 482, 378, 1, '2026-03-01 09:56:06'),
(0, 483, 364, 1, '2026-03-01 09:56:25'),
(0, 484, 365, 1, '2026-03-01 09:56:34'),
(0, 487, 367, 1, '2026-03-01 09:56:47'),
(0, 490, 368, 1, '2026-03-01 09:57:12'),
(0, 491, 428, 1, '2026-03-01 09:57:20'),
(0, 492, 369, 1, '2026-03-01 09:57:29'),
(0, 493, 378, 1, '2026-03-01 09:57:39'),
(0, 503, 425, 1, '2026-03-01 09:58:00'),
(0, 504, 380, 1, '2026-03-01 09:58:19'),
(0, 517, 383, 1, '2026-03-01 09:58:47'),
(0, 519, 370, 1, '2026-03-01 09:58:59'),
(0, 522, 386, 1, '2026-03-01 09:59:08'),
(0, 523, 387, 1, '2026-03-01 09:59:14'),
(0, 527, 388, 1, '2026-03-01 09:59:22'),
(0, 529, 423, 1, '2026-03-06 07:19:13'),
(0, 532, 533, 1, '2026-03-24 22:11:35'),
(0, 530, 531, 1, '2026-03-24 22:11:51'),
(0, 534, 535, 1, '2026-03-24 22:14:25'),
(0, 536, 385, 1, '2026-03-24 22:17:30'),
(0, 537, 408, 1, '2026-03-24 22:24:38'),
(0, 458, 546, 1, '2026-05-04 23:06:07'),
(0, 459, 547, 1, '2026-05-04 23:06:18'),
(0, 516, 548, 1, '2026-05-04 23:07:02'),
(0, 520, 549, 1, '2026-05-04 23:07:47');

-- --------------------------------------------------------

--
-- Table structure for table `calls`
--

CREATE TABLE `calls` (
  `id` int(11) NOT NULL,
  `caller_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `call_type` enum('voice','video') NOT NULL DEFAULT 'voice',
  `status` enum('ringing','active','ended','declined','missed') NOT NULL DEFAULT 'ringing',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `answered_at` timestamp NULL DEFAULT NULL,
  `ended_at` timestamp NULL DEFAULT NULL,
  `duration` int(11) DEFAULT NULL COMMENT 'Duration in seconds'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `chat_messages`
--

CREATE TABLE `chat_messages` (
  `id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `conversi_po_detail`
--

CREATE TABLE `conversi_po_detail` (
  `id` int(11) NOT NULL,
  `purchase_order_id` int(11) NOT NULL,
  `detail_purchase_order_id` int(11) NOT NULL,
  `satuan_asal_id` int(11) DEFAULT NULL,
  `satuan_tujuan_id` int(11) DEFAULT NULL,
  `nilai_konversi` decimal(10,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `detail_direct_purchase`
--

CREATE TABLE `detail_direct_purchase` (
  `id` int(11) NOT NULL,
  `direct_purchase_id` int(11) NOT NULL,
  `barang_id` int(11) DEFAULT NULL,
  `jumlah` int(11) NOT NULL,
  `harga_satuan` decimal(15,2) NOT NULL,
  `total_harga` decimal(15,2) NOT NULL,
  `keterangan` text DEFAULT NULL,
  `foto` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `detail_purchase_order`
--

CREATE TABLE `detail_purchase_order` (
  `id` int(11) NOT NULL,
  `purchase_order_id` int(11) NOT NULL,
  `barang_id` int(11) NOT NULL,
  `jumlah` int(11) NOT NULL,
  `satuan_id` int(11) DEFAULT NULL,
  `harga_satuan` decimal(15,2) NOT NULL,
  `total_harga` decimal(15,2) DEFAULT 0.00,
  `keterangan_detail` text DEFAULT NULL,
  `keterangan` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` varchar(20) NOT NULL DEFAULT 'menunggu',
  `created_by` int(11) NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Triggers `detail_purchase_order`
--
DELIMITER $$
CREATE TRIGGER `update_po_total` AFTER INSERT ON `detail_purchase_order` FOR EACH ROW BEGIN
    -- Your trigger logic here
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `update_po_total_on_delete` AFTER DELETE ON `detail_purchase_order` FOR EACH ROW BEGIN
    UPDATE purchase_order 
    SET total_harga = (
        SELECT COALESCE(SUM(jumlah * harga_satuan), 0)
        FROM detail_purchase_order
        WHERE purchase_order_id = OLD.purchase_order_id
    )
    WHERE id = OLD.purchase_order_id;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `update_po_total_on_update` AFTER UPDATE ON `detail_purchase_order` FOR EACH ROW BEGIN
    UPDATE purchase_order 
    SET total_harga = (
        SELECT COALESCE(SUM(jumlah * harga_satuan), 0)
        FROM detail_purchase_order
        WHERE purchase_order_id = NEW.purchase_order_id
    )
    WHERE id = NEW.purchase_order_id;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `detail_transaksi_stock`
--

CREATE TABLE `detail_transaksi_stock` (
  `id` int(11) NOT NULL,
  `transaksi_stock_id` int(11) NOT NULL,
  `barang_id` int(11) NOT NULL,
  `jumlah` decimal(10,2) NOT NULL,
  `keterangan` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `detail_transaksi_stok`
--

CREATE TABLE `detail_transaksi_stok` (
  `id` int(11) NOT NULL,
  `transaksi_stok_id` int(11) NOT NULL,
  `barang_id` int(11) NOT NULL,
  `detail_barang` varchar(255) DEFAULT '',
  `jumlah` int(11) NOT NULL CHECK (`jumlah` >= 0),
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `detail_transaksi_transfer`
--

CREATE TABLE `detail_transaksi_transfer` (
  `id` int(11) NOT NULL,
  `transaksi_transfer_id` int(11) NOT NULL,
  `barang_id` int(11) NOT NULL,
  `jumlah` int(11) NOT NULL,
  `keterangan` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `direct_purchase`
--

CREATE TABLE `direct_purchase` (
  `id` int(11) NOT NULL,
  `no_transaksi` varchar(20) NOT NULL,
  `tanggal` date NOT NULL,
  `no_nota` varchar(255) DEFAULT NULL,
  `nama_toko` varchar(255) NOT NULL,
  `supplier_id` int(11) DEFAULT NULL,
  `total_item` int(11) NOT NULL DEFAULT 0,
  `total_harga` decimal(15,2) NOT NULL DEFAULT 0.00,
  `keterangan` text DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'menunggu',
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `barang_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `gudang`
--

CREATE TABLE `gudang` (
  `id` int(11) NOT NULL,
  `kode_gudang` varchar(20) NOT NULL,
  `nama_gudang` varchar(100) NOT NULL,
  `alamat` text DEFAULT NULL,
  `kapasitas` int(11) DEFAULT NULL,
  `status` varchar(20) DEFAULT 'aktif'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `gudang`
--

INSERT INTO `gudang` (`id`, `kode_gudang`, `nama_gudang`, `alamat`, `kapasitas`, `status`) VALUES
(13, 'GudangA01', 'Gudang_Antapani', 'ant', 6000, 'aktif'),
(23, 'GUD014', 'Gudang_Central', 'CBG', 2000, 'aktif');

-- --------------------------------------------------------

--
-- Table structure for table `gudang_stok`
--

CREATE TABLE `gudang_stok` (
  `id` int(11) NOT NULL,
  `nama_gudang` varchar(100) DEFAULT NULL,
  `barang_id` int(11) NOT NULL,
  `gudang_id` int(11) NOT NULL,
  `stok_awal` int(11) DEFAULT 0,
  `stok_terpakai` int(11) NOT NULL,
  `stok_sisa` int(11) DEFAULT 0,
  `expire_date` date DEFAULT NULL,
  `batch_number` varchar(50) DEFAULT NULL,
  `harga_beli` decimal(15,2) DEFAULT 0.00,
  `modified_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `last_reset` datetime DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `stok_minimum` int(11) DEFAULT 0,
  `jumlah` int(11) NOT NULL DEFAULT 0 CHECK (`jumlah` >= 0),
  `detail_barang` varchar(255) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `gudang_stok`
--

INSERT INTO `gudang_stok` (`id`, `nama_gudang`, `barang_id`, `gudang_id`, `stok_awal`, `stok_terpakai`, `stok_sisa`, `expire_date`, `batch_number`, `harga_beli`, `modified_by`, `created_at`, `updated_at`, `last_reset`, `created_by`, `updated_by`, `stok_minimum`, `jumlah`, `detail_barang`) VALUES
(2, NULL, 554, 13, 0, 0, 0, NULL, NULL, 0.00, 1, '2026-06-01 01:07:05', '2026-06-01 01:23:57', NULL, 1, NULL, 2000, 0, '');

--
-- Triggers `gudang_stok`
--
DELIMITER $$
CREATE TRIGGER `tr_gudang_stok_daily_insert` AFTER INSERT ON `gudang_stok` FOR EACH ROW BEGIN
    -- Buat snapshot untuk data baru
    INSERT INTO gudang_stok_daily (
        gudang_id, barang_id, stok_awal, stok_terpakai, 
        stok_akhir, stok_minimum, snapshot_date
    ) VALUES (
        NEW.gudang_id, NEW.barang_id, NEW.stok_awal, NEW.stok_terpakai,
        (NEW.stok_awal - NEW.stok_terpakai), NEW.stok_minimum, DATE(NEW.created_at)
    );
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `tr_gudang_stok_daily_snapshot` BEFORE UPDATE ON `gudang_stok` FOR EACH ROW BEGIN
    DECLARE current_date_val DATE;
    DECLARE old_date_val DATE;
    
    -- Ambil tanggal dari updated_at
    SET current_date_val = DATE(NEW.updated_at);
    SET old_date_val = DATE(OLD.updated_at);
    
    -- Jika tanggal berubah (bukan NULL dan berbeda)
    IF current_date_val IS NOT NULL AND old_date_val IS NOT NULL 
       AND current_date_val != old_date_val THEN
        
        -- Pastikan snapshot untuk tanggal sebelumnya belum ada
        IF NOT EXISTS (
            SELECT 1 FROM gudang_stok_daily 
            WHERE gudang_id = OLD.gudang_id 
            AND barang_id = OLD.barang_id 
            AND snapshot_date = old_date_val
        ) THEN
            -- Buat snapshot untuk tanggal sebelumnya
            INSERT INTO gudang_stok_daily (
                gudang_id, barang_id, stok_awal, stok_terpakai, 
                stok_akhir, stok_minimum, snapshot_date
            ) VALUES (
                OLD.gudang_id, OLD.barang_id, OLD.stok_awal, OLD.stok_terpakai,
                (OLD.stok_awal - OLD.stok_terpakai), OLD.stok_minimum, old_date_val
            );
        END IF;
        
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `tr_gudang_stok_history_insert` AFTER INSERT ON `gudang_stok` FOR EACH ROW BEGIN
    INSERT INTO gudang_stok_history (
        gudang_stok_id, gudang_id, barang_id,
        stok_awal_sebelum, stok_awal_sesudah,
        stok_terpakai_sebelum, stok_terpakai_sesudah,
        stok_sisa_sebelum, stok_sisa_sesudah,
        jenis_perubahan, jumlah_perubahan, keterangan, referensi, created_by
    ) VALUES (
        NEW.id, NEW.gudang_id, NEW.barang_id,
        0, NEW.stok_awal,
        0, NEW.stok_terpakai,
        0, NEW.stok_sisa,
        'masuk', NEW.stok_awal, 'Stok awal', 'INIT', NEW.created_by
    );
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `tr_gudang_stok_history_update` AFTER UPDATE ON `gudang_stok` FOR EACH ROW BEGIN
    DECLARE jenis_perubahan VARCHAR(20);
    DECLARE jumlah_perubahan INT;
    DECLARE keterangan TEXT;

    -- Tentukan jenis perubahan
    IF NEW.stok_awal != OLD.stok_awal THEN
        SET jenis_perubahan = 'masuk';
        SET jumlah_perubahan = NEW.stok_awal - OLD.stok_awal;
        SET keterangan = CONCAT('Stok awal berubah dari ', OLD.stok_awal, ' menjadi ', NEW.stok_awal);
    ELSEIF NEW.stok_terpakai != OLD.stok_terpakai THEN
        SET jenis_perubahan = 'keluar';  -- TAMBAHKAN INI!
        SET jumlah_perubahan = NEW.stok_terpakai - OLD.stok_terpakai;
        SET keterangan = CONCAT('Stok terpakai berubah dari ', OLD.stok_terpakai, ' menjadi ', NEW.stok_terpakai);
    ELSE
        SET jenis_perubahan = 'update';
        SET jumlah_perubahan = 0;
        SET keterangan = 'Update data stok';
    END IF;

    INSERT INTO gudang_stok_history (
        gudang_stok_id, gudang_id, barang_id,
        stok_awal_sebelum, stok_awal_sesudah,
        stok_terpakai_sebelum, stok_terpakai_sesudah,
        stok_sisa_sebelum, stok_sisa_sesudah,
        jenis_perubahan, jumlah_perubahan, keterangan, referensi, created_by
    ) VALUES (
        NEW.id, NEW.gudang_id, NEW.barang_id,
        OLD.stok_awal, NEW.stok_awal,
        OLD.stok_terpakai, NEW.stok_terpakai,
        OLD.stok_sisa, NEW.stok_sisa,
        jenis_perubahan, jumlah_perubahan, keterangan, 'UPDATE', NEW.modified_by
    );
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `gudang_stok_daily`
--

CREATE TABLE `gudang_stok_daily` (
  `id` int(11) NOT NULL,
  `gudang_id` int(11) NOT NULL,
  `barang_id` int(11) NOT NULL,
  `stok_awal` int(11) DEFAULT 0,
  `stok_terpakai` int(11) DEFAULT 0,
  `stok_akhir` int(11) DEFAULT 0,
  `stok_minimum` int(11) DEFAULT 0,
  `snapshot_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `keterangan` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `gudang_stok_daily`
--

INSERT INTO `gudang_stok_daily` (`id`, `gudang_id`, `barang_id`, `stok_awal`, `stok_terpakai`, `stok_akhir`, `stok_minimum`, `snapshot_date`, `created_at`, `created_by`, `updated_by`, `keterangan`) VALUES
(0, 13, 364, 274, 0, 274, 120, '2026-02-28', '2026-05-31 17:37:55', NULL, NULL, NULL),
(0, 13, 365, 385, 0, 385, 120, '2026-02-28', '2026-05-31 17:37:55', NULL, NULL, NULL),
(0, 13, 366, 0, 0, 0, 120, '2026-02-28', '2026-05-31 17:37:55', NULL, NULL, NULL),
(0, 13, 367, 489, 0, 489, 120, '2026-02-28', '2026-05-31 17:37:55', NULL, NULL, NULL),
(0, 13, 368, 0, 0, 0, 120, '2026-02-28', '2026-05-31 17:37:55', NULL, NULL, NULL),
(0, 13, 369, 65, 0, 65, 140, '2026-02-28', '2026-05-31 17:37:55', NULL, NULL, NULL),
(0, 13, 370, 623, 0, 623, 200, '2026-02-28', '2026-05-31 17:37:55', NULL, NULL, NULL),
(0, 13, 371, 36, 0, 36, 16, '2026-02-28', '2026-05-31 17:37:55', NULL, NULL, NULL),
(0, 13, 372, 7, 0, 7, 8, '2026-02-28', '2026-05-31 17:37:55', NULL, NULL, NULL),
(0, 13, 374, 0, 0, 0, 100, '2026-02-28', '2026-05-31 17:37:55', NULL, NULL, NULL),
(0, 13, 375, 427, 0, 427, 100, '2026-02-28', '2026-05-31 17:37:55', NULL, NULL, NULL),
(0, 13, 376, 10, 0, 10, 10, '2026-02-28', '2026-05-31 17:37:55', NULL, NULL, NULL),
(0, 13, 377, 8, 0, 8, 10, '2026-02-28', '2026-05-31 17:37:55', NULL, NULL, NULL),
(0, 13, 378, 1620, 0, 1620, 500, '2026-02-28', '2026-05-31 17:37:55', NULL, NULL, NULL),
(0, 13, 380, 2752, 0, 2752, 1480, '2026-02-28', '2026-05-31 17:37:55', NULL, NULL, NULL),
(0, 13, 381, 462, 0, 462, 200, '2026-02-28', '2026-05-31 17:37:55', NULL, NULL, NULL),
(0, 13, 383, 478, 0, 478, 500, '2026-02-28', '2026-05-31 17:37:55', NULL, NULL, NULL),
(0, 13, 384, 10, 0, 10, 10, '2026-02-28', '2026-05-31 17:37:55', NULL, NULL, NULL),
(0, 13, 385, 360, 0, 360, 50, '2026-02-28', '2026-05-31 17:37:55', NULL, NULL, NULL),
(0, 13, 386, 2207, 0, 2207, 3000, '2026-02-28', '2026-05-31 17:37:55', NULL, NULL, NULL),
(0, 13, 387, 26, 0, 26, 8, '2026-02-28', '2026-05-31 17:37:55', NULL, NULL, NULL),
(0, 13, 388, 1, 0, 1, 5, '2026-02-28', '2026-05-31 17:37:55', NULL, NULL, NULL),
(0, 13, 389, 0, 0, 0, 250, '2026-02-28', '2026-05-31 17:37:55', NULL, NULL, NULL),
(0, 13, 390, 1285, 0, 1285, 500, '2026-02-28', '2026-05-31 17:37:55', NULL, NULL, NULL),
(0, 13, 391, 9, 0, 9, 5, '2026-02-28', '2026-05-31 17:37:55', NULL, NULL, NULL),
(0, 13, 392, 0, 0, 0, 2, '2026-02-28', '2026-05-31 17:37:55', NULL, NULL, NULL),
(0, 13, 393, 6, 0, 6, 2, '2026-02-28', '2026-05-31 17:37:55', NULL, NULL, NULL),
(0, 13, 394, 2, 0, 2, 3, '2026-02-28', '2026-05-31 17:37:55', NULL, NULL, NULL),
(0, 13, 395, 6, 0, 6, 4, '2026-02-28', '2026-05-31 17:37:55', NULL, NULL, NULL),
(0, 13, 397, 6, 0, 6, 2, '2026-02-28', '2026-05-31 17:37:55', NULL, NULL, NULL),
(0, 13, 398, 0, 0, 0, 4, '2026-02-28', '2026-05-31 17:37:55', NULL, NULL, NULL),
(0, 13, 399, 0, 0, 0, 8, '2026-02-28', '2026-05-31 17:37:55', NULL, NULL, NULL),
(0, 13, 400, 3, 0, 3, 5, '2026-02-28', '2026-05-31 17:37:55', NULL, NULL, NULL),
(0, 13, 401, 0, 0, 0, 5, '2026-02-28', '2026-05-31 17:37:55', NULL, NULL, NULL),
(0, 13, 402, 5, 0, 5, 9, '2026-02-28', '2026-05-31 17:37:55', NULL, NULL, NULL),
(0, 13, 404, 50, 0, 50, 12, '2026-02-28', '2026-05-31 17:37:55', NULL, NULL, NULL),
(0, 13, 405, 48, 0, 48, 7, '2026-02-28', '2026-05-31 17:37:55', NULL, NULL, NULL),
(0, 13, 406, 0, 0, 0, 7, '2026-02-28', '2026-05-31 17:37:55', NULL, NULL, NULL),
(0, 13, 408, 37, 0, 37, 8, '2026-02-28', '2026-05-31 17:37:55', NULL, NULL, NULL),
(0, 13, 410, 152, 0, 152, 1, '2026-02-28', '2026-05-31 17:37:55', NULL, NULL, NULL),
(0, 13, 411, 21, 0, 21, 1, '2026-02-28', '2026-05-31 17:37:55', NULL, NULL, NULL),
(0, 13, 412, 105, 0, 105, 1, '2026-02-28', '2026-05-31 17:37:55', NULL, NULL, NULL),
(0, 13, 413, 3, 0, 3, 3, '2026-02-28', '2026-05-31 17:37:55', NULL, NULL, NULL),
(0, 13, 415, 57, 0, 57, 250, '2026-02-28', '2026-05-31 17:37:55', NULL, NULL, NULL),
(0, 13, 416, 115, 0, 115, 250, '2026-02-28', '2026-05-31 17:37:55', NULL, NULL, NULL),
(0, 13, 418, 20, 0, 20, 10, '2026-02-28', '2026-05-31 17:37:55', NULL, NULL, NULL),
(0, 13, 419, 7, 0, 7, 10, '2026-02-28', '2026-05-31 17:37:55', NULL, NULL, NULL),
(0, 13, 420, 21, 0, 21, 5, '2026-02-28', '2026-05-31 17:37:55', NULL, NULL, NULL),
(0, 13, 422, 1240, 0, 1240, 200, '2026-02-28', '2026-05-31 17:37:55', NULL, NULL, NULL),
(0, 13, 423, 34, 0, 34, 8, '2026-02-28', '2026-05-31 17:37:55', NULL, NULL, NULL),
(0, 13, 425, 640, 0, 640, 100, '2026-02-28', '2026-05-31 17:37:55', NULL, NULL, NULL),
(0, 13, 426, 29, 0, 29, 5, '2026-02-28', '2026-05-31 17:37:55', NULL, NULL, NULL),
(0, 13, 427, 43, 0, 43, 5, '2026-02-28', '2026-05-31 17:37:55', NULL, NULL, NULL),
(0, 13, 428, 424, 0, 424, 200, '2026-02-28', '2026-05-31 17:37:55', NULL, NULL, NULL),
(0, 13, 429, 119, 0, 119, 12, '2026-02-28', '2026-05-31 17:37:55', NULL, NULL, NULL),
(0, 13, 438, 32, 0, 32, 10, '2026-02-28', '2026-05-31 17:37:55', NULL, NULL, NULL),
(0, 13, 439, 1, 0, 1, 2, '2026-02-28', '2026-05-31 17:37:55', NULL, NULL, NULL),
(0, 23, 365, 500, 0, 500, 120, '2026-02-28', '2026-05-31 17:37:55', NULL, NULL, NULL),
(0, 23, 367, 0, 0, 0, 120, '2026-02-28', '2026-05-31 17:37:55', NULL, NULL, NULL),
(0, 23, 374, 0, 0, 0, 100, '2026-02-28', '2026-05-31 17:37:55', NULL, NULL, NULL),
(0, 23, 376, 0, 0, 0, 10, '2026-02-28', '2026-05-31 17:37:55', NULL, NULL, NULL),
(0, 23, 377, 0, 0, 0, 10, '2026-02-28', '2026-05-31 17:37:55', NULL, NULL, NULL),
(0, 23, 378, 0, 0, 0, 500, '2026-02-28', '2026-05-31 17:37:55', NULL, NULL, NULL),
(0, 23, 380, 0, 0, 0, 1480, '2026-02-28', '2026-05-31 17:37:55', NULL, NULL, NULL),
(0, 23, 386, 0, 0, 0, 3000, '2026-02-28', '2026-05-31 17:37:55', NULL, NULL, NULL),
(0, 23, 389, 0, 0, 0, 250, '2026-02-28', '2026-05-31 17:37:55', NULL, NULL, NULL),
(0, 23, 390, 0, 0, 0, 500, '2026-02-28', '2026-05-31 17:37:55', NULL, NULL, NULL),
(0, 23, 401, 0, 0, 0, 5, '2026-02-28', '2026-05-31 17:37:55', NULL, NULL, NULL),
(0, 23, 404, 0, 0, 0, 12, '2026-02-28', '2026-05-31 17:37:55', NULL, NULL, NULL),
(0, 23, 405, 0, 0, 0, 7, '2026-02-28', '2026-05-31 17:37:55', NULL, NULL, NULL),
(0, 23, 407, 0, 0, 0, 1, '2026-02-28', '2026-05-31 17:37:55', NULL, NULL, NULL),
(0, 23, 415, 450, 0, 450, 250, '2026-02-28', '2026-05-31 17:37:55', NULL, NULL, NULL),
(0, 23, 416, 650, 0, 650, 250, '2026-02-28', '2026-05-31 17:37:55', NULL, NULL, NULL),
(0, 23, 420, 42, 0, 42, 5, '2026-02-28', '2026-05-31 17:37:55', NULL, NULL, NULL),
(0, 23, 425, 0, 0, 0, 100, '2026-02-28', '2026-05-31 17:37:55', NULL, NULL, NULL),
(0, 23, 439, 8, 0, 8, 2, '2026-02-28', '2026-05-31 17:37:55', NULL, NULL, NULL),
(0, 13, 531, 4, 0, 4, 2, '2026-03-25', '2026-05-31 17:37:55', NULL, NULL, NULL),
(0, 13, 533, 0, 0, 0, 2, '2026-03-25', '2026-05-31 17:37:55', NULL, NULL, NULL),
(0, 13, 535, 2, 0, 2, 3, '2026-03-25', '2026-05-31 17:37:55', NULL, NULL, NULL),
(0, 23, 418, 50, 0, 50, 25, '2026-04-02', '2026-05-31 17:37:55', NULL, NULL, NULL),
(0, 23, 419, 50, 0, 50, 15, '2026-04-02', '2026-05-31 17:37:55', NULL, NULL, NULL),
(0, 23, 502, 5, 0, 5, 1, '2026-04-02', '2026-05-31 17:37:55', NULL, NULL, NULL),
(0, 23, 412, 0, 0, 0, 35, '2026-04-02', '2026-05-31 17:37:55', NULL, NULL, NULL),
(0, 23, 411, 84, 0, 84, 21, '2026-04-02', '2026-05-31 17:37:55', NULL, NULL, NULL),
(0, 23, 410, 0, 0, 0, 38, '2026-04-02', '2026-05-31 17:37:55', NULL, NULL, NULL),
(0, 13, 541, 3, 0, 3, 1, '2026-05-02', '2026-05-31 17:37:55', NULL, NULL, NULL),
(0, 13, 542, 4, 0, 4, 1, '2026-05-02', '2026-05-31 17:37:55', NULL, NULL, NULL),
(0, 13, 543, 1, 0, 1, 1, '2026-05-02', '2026-05-31 17:37:55', NULL, NULL, NULL),
(0, 13, 544, 1, 0, 1, 1, '2026-05-02', '2026-05-31 17:37:55', NULL, NULL, NULL),
(0, 13, 548, 378, 0, 378, 100, '2026-05-05', '2026-05-31 17:37:55', NULL, NULL, NULL),
(0, 13, 549, 258, 0, 258, 100, '2026-05-05', '2026-05-31 17:37:55', NULL, NULL, NULL),
(0, 13, 546, 758, 0, 758, 100, '2026-05-05', '2026-05-31 17:37:55', NULL, NULL, NULL),
(0, 13, 547, 64, 0, 64, 100, '2026-05-05', '2026-05-31 17:37:55', NULL, NULL, NULL),
(0, 23, 421, 700, 0, 700, 100, '2026-05-18', '2026-05-31 17:37:55', NULL, NULL, NULL),
(0, 13, 421, 18, 0, 18, 50, '2026-05-18', '2026-05-31 17:37:55', NULL, NULL, NULL),
(0, 13, 551, 2, 0, 2, 2, '2026-05-18', '2026-05-31 17:37:55', NULL, NULL, NULL),
(0, 13, 552, 2, 0, 2, 1, '2026-05-18', '2026-05-31 17:37:55', NULL, NULL, NULL),
(0, 13, 554, 0, 0, 0, 2000, '2026-06-01', '2026-05-31 17:56:39', NULL, NULL, NULL),
(0, 13, 554, 0, 0, 0, 2000, '2026-06-01', '2026-05-31 18:07:05', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `gudang_stok_history`
--

CREATE TABLE `gudang_stok_history` (
  `id` int(11) NOT NULL,
  `gudang_stok_id` int(11) NOT NULL,
  `gudang_id` int(11) NOT NULL,
  `barang_id` int(11) NOT NULL,
  `stok_awal_sebelum` int(11) DEFAULT 0,
  `stok_awal_sesudah` int(11) DEFAULT 0,
  `stok_terpakai_sebelum` int(11) DEFAULT 0,
  `stok_terpakai_sesudah` int(11) DEFAULT 0,
  `stok_sisa_sebelum` int(11) DEFAULT 0,
  `stok_sisa_sesudah` int(11) DEFAULT 0,
  `jenis_perubahan` enum('masuk','keluar','transfer_in','transfer_out','reset','update') NOT NULL,
  `jumlah_perubahan` int(11) DEFAULT 0,
  `keterangan` text DEFAULT NULL,
  `referensi` varchar(50) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `gudang_stok_history`
--

INSERT INTO `gudang_stok_history` (`id`, `gudang_stok_id`, `gudang_id`, `barang_id`, `stok_awal_sebelum`, `stok_awal_sesudah`, `stok_terpakai_sebelum`, `stok_terpakai_sesudah`, `stok_sisa_sebelum`, `stok_sisa_sesudah`, `jenis_perubahan`, `jumlah_perubahan`, `keterangan`, `referensi`, `created_by`, `created_at`) VALUES
(0, 1, 13, 554, 0, 0, 0, 0, 0, 0, 'masuk', 0, 'Stok awal', 'INIT', 1, '2026-05-31 17:56:39'),
(0, 1, 13, 554, 0, 4, 0, 0, 0, 0, 'masuk', 4, 'Stok awal berubah dari 0 menjadi 4', 'UPDATE', 1, '2026-05-31 18:00:51'),
(0, 2, 13, 554, 0, 0, 0, 0, 0, 0, 'masuk', 0, 'Stok awal', 'INIT', 1, '2026-05-31 18:07:05'),
(0, 2, 13, 554, 0, 2000, 0, 0, 0, 0, 'masuk', 2000, 'Stok awal berubah dari 0 menjadi 2000', 'UPDATE', 1, '2026-05-31 18:17:32'),
(0, 2, 13, 554, 2000, 2000, 0, 1999, 0, 1, 'keluar', 1999, 'Stok terpakai berubah dari 0 menjadi 1999', 'UPDATE', 1, '2026-05-31 18:18:05'),
(0, 2, 13, 554, 2000, 2000, 1999, 999, 1, 1001, 'keluar', -1000, 'Stok terpakai berubah dari 1999 menjadi 999', 'UPDATE', 1, '2026-05-31 18:18:07'),
(0, 2, 13, 554, 2000, 2000, 999, 1999, 1001, 1, 'keluar', 1000, 'Stok terpakai berubah dari 999 menjadi 1999', 'UPDATE', 1, '2026-05-31 18:18:21'),
(0, 2, 13, 554, 2000, 2000, 1999, 1998, 1, 2, 'keluar', -1, 'Stok terpakai berubah dari 1999 menjadi 1998', 'UPDATE', 1, '2026-05-31 18:18:50'),
(0, 2, 13, 554, 2000, 2000, 1998, 1997, 2, 3, 'keluar', -1, 'Stok terpakai berubah dari 1998 menjadi 1997', 'UPDATE', 1, '2026-05-31 18:18:51'),
(0, 2, 13, 554, 2000, 2000, 1997, 1996, 3, 4, 'keluar', -1, 'Stok terpakai berubah dari 1997 menjadi 1996', 'UPDATE', 1, '2026-05-31 18:23:42'),
(0, 2, 13, 554, 2000, 2000, 1996, 1995, 4, 5, 'keluar', -1, 'Stok terpakai berubah dari 1996 menjadi 1995', 'UPDATE', 1, '2026-05-31 18:23:44'),
(0, 2, 13, 554, 2000, 2000, 1995, 995, 5, 1005, 'keluar', -1000, 'Stok terpakai berubah dari 1995 menjadi 995', 'UPDATE', 1, '2026-05-31 18:23:57'),
(0, 2, 13, 554, 2000, 4000, 995, 995, 1005, 1005, 'masuk', 2000, 'Stok awal berubah dari 2000 menjadi 4000', 'UPDATE', 1, '2026-05-31 18:24:34'),
(0, 2, 13, 554, 4000, 0, 995, 0, 1005, 0, 'masuk', -4000, 'Stok awal berubah dari 4000 menjadi 0', 'UPDATE', 1, '2026-05-31 18:26:15');

-- --------------------------------------------------------

--
-- Table structure for table `item_mapping`
--

CREATE TABLE `item_mapping` (
  `id` int(11) NOT NULL,
  `barang_id` int(11) NOT NULL,
  `lokasi_id` int(11) NOT NULL,
  `aktif` tinyint(1) DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_by` int(11) DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `item_mapping`
--

INSERT INTO `item_mapping` (`id`, `barang_id`, `lokasi_id`, `aktif`, `created_by`, `created_at`, `updated_by`, `updated_at`) VALUES
(81, 401, 17, 1, NULL, '2026-02-28 23:08:36', 7, '2026-02-28 23:56:35'),
(82, 389, 17, 1, 7, '2026-02-28 23:56:35', NULL, NULL),
(83, 390, 17, 1, 7, '2026-02-28 23:56:35', NULL, NULL),
(84, 371, 18, 1, 7, '2026-02-28 23:56:35', NULL, NULL),
(85, 372, 17, 1, 7, '2026-02-28 23:56:35', NULL, NULL),
(86, 413, 17, 1, 7, '2026-02-28 23:56:35', NULL, NULL),
(87, 363, 17, 1, 7, '2026-02-28 23:56:35', NULL, NULL),
(88, 364, 17, 1, 7, '2026-02-28 23:56:35', NULL, NULL),
(89, 365, 17, 1, 7, '2026-02-28 23:56:35', NULL, NULL),
(90, 373, 17, 1, 7, '2026-02-28 23:56:35', NULL, NULL),
(91, 370, 17, 1, 7, '2026-02-28 23:56:35', NULL, NULL),
(92, 366, 17, 1, 7, '2026-02-28 23:56:35', NULL, NULL),
(93, 367, 17, 1, 7, '2026-02-28 23:56:35', NULL, NULL),
(94, 378, 17, 1, 7, '2026-02-28 23:56:35', NULL, NULL),
(95, 368, 17, 1, 7, '2026-02-28 23:56:35', NULL, NULL),
(96, 369, 17, 1, 7, '2026-02-28 23:56:35', NULL, NULL),
(97, 392, 18, 1, 7, '2026-02-28 23:56:35', NULL, NULL),
(98, 394, 18, 1, 7, '2026-02-28 23:56:35', NULL, NULL),
(99, 393, 18, 1, 7, '2026-02-28 23:56:35', NULL, NULL),
(100, 395, 18, 1, 7, '2026-02-28 23:56:35', NULL, NULL),
(101, 422, 18, 1, 7, '2026-02-28 23:56:35', NULL, NULL),
(102, 396, 18, 1, 7, '2026-02-28 23:56:35', NULL, NULL),
(103, 397, 18, 1, 7, '2026-02-28 23:56:35', NULL, NULL),
(104, 398, 18, 1, 7, '2026-02-28 23:56:35', NULL, NULL),
(105, 374, 17, 1, 7, '2026-02-28 23:56:35', NULL, NULL),
(106, 402, 17, 1, 7, '2026-02-28 23:56:35', NULL, NULL),
(107, 376, 18, 1, 7, '2026-02-28 23:56:35', NULL, NULL),
(108, 377, 18, 1, 7, '2026-02-28 23:56:35', NULL, NULL),
(109, 414, 17, 0, 7, '2026-02-28 23:56:35', NULL, NULL),
(110, 400, 18, 1, 7, '2026-02-28 23:56:35', NULL, NULL),
(111, 375, 17, 0, 7, '2026-02-28 23:56:35', NULL, NULL),
(112, 375, 18, 1, 7, '2026-02-28 23:56:35', NULL, NULL),
(113, 439, 17, 1, 7, '2026-02-28 23:56:35', NULL, NULL),
(114, 404, 17, 1, 7, '2026-02-28 23:56:35', NULL, NULL),
(115, 403, 17, 1, 7, '2026-02-28 23:56:35', NULL, NULL),
(116, 428, 18, 1, 7, '2026-02-28 23:56:35', NULL, NULL),
(117, 405, 17, 1, 7, '2026-02-28 23:56:35', NULL, NULL),
(118, 379, 17, 1, 7, '2026-02-28 23:56:35', NULL, NULL),
(119, 415, 17, 1, 7, '2026-02-28 23:56:35', NULL, NULL),
(120, 416, 17, 1, 7, '2026-02-28 23:56:35', NULL, NULL),
(121, 410, 17, 1, 7, '2026-02-28 23:56:35', NULL, NULL),
(122, 411, 17, 1, 7, '2026-02-28 23:56:35', NULL, NULL),
(123, 412, 17, 1, 7, '2026-02-28 23:56:35', NULL, NULL),
(124, 408, 17, 1, 7, '2026-02-28 23:56:35', NULL, NULL),
(125, 407, 17, 1, 7, '2026-02-28 23:56:35', NULL, NULL),
(126, 406, 17, 1, 7, '2026-02-28 23:56:35', NULL, NULL),
(127, 381, 18, 1, 7, '2026-02-28 23:56:35', NULL, NULL),
(128, 423, 18, 1, 7, '2026-02-28 23:56:35', NULL, NULL),
(129, 424, 18, 1, 7, '2026-02-28 23:56:35', NULL, NULL),
(130, 425, 18, 1, 7, '2026-02-28 23:56:35', NULL, NULL),
(131, 426, 18, 1, 7, '2026-02-28 23:56:35', NULL, NULL),
(132, 427, 18, 1, 7, '2026-02-28 23:56:35', NULL, NULL),
(133, 429, 18, 1, 7, '2026-02-28 23:56:35', NULL, NULL),
(134, 391, 18, 1, 7, '2026-02-28 23:56:35', NULL, NULL),
(135, 399, 18, 1, 7, '2026-02-28 23:56:35', NULL, NULL),
(136, 382, 18, 1, 7, '2026-02-28 23:56:35', NULL, NULL),
(137, 386, 17, 1, 7, '2026-02-28 23:56:35', NULL, NULL),
(138, 380, 17, 1, 7, '2026-02-28 23:56:35', NULL, NULL),
(139, 431, 17, 1, 7, '2026-02-28 23:56:35', NULL, NULL),
(140, 432, 17, 1, 7, '2026-02-28 23:56:35', NULL, NULL),
(141, 433, 17, 1, 7, '2026-02-28 23:56:35', NULL, NULL),
(142, 434, 17, 1, 7, '2026-02-28 23:56:35', NULL, NULL),
(143, 435, 17, 1, 7, '2026-02-28 23:56:35', NULL, NULL),
(144, 436, 17, 1, 7, '2026-02-28 23:56:35', NULL, NULL),
(145, 437, 17, 1, 7, '2026-02-28 23:56:35', NULL, NULL),
(146, 418, 17, 1, 7, '2026-02-28 23:56:35', NULL, NULL),
(147, 419, 17, 1, 7, '2026-02-28 23:56:35', NULL, NULL),
(148, 438, 17, 1, 7, '2026-02-28 23:56:35', NULL, NULL),
(149, 384, 18, 1, 7, '2026-02-28 23:56:35', NULL, NULL),
(150, 383, 18, 1, 7, '2026-02-28 23:56:35', NULL, NULL),
(151, 517, 18, 1, 7, '2026-02-28 23:56:35', NULL, NULL),
(152, 481, 19, 1, 7, '2026-02-28 23:56:35', NULL, NULL),
(153, 385, 18, 1, 7, '2026-02-28 23:56:35', NULL, NULL),
(154, 387, 17, 1, 7, '2026-02-28 23:56:35', NULL, NULL),
(155, 388, 17, 1, 7, '2026-02-28 23:56:35', NULL, NULL),
(156, 543, 19, 1, NULL, '2026-05-02 15:38:03', NULL, NULL),
(157, 544, 19, 1, NULL, '2026-05-02 15:38:11', NULL, NULL),
(158, 541, 19, 1, NULL, '2026-05-02 15:38:30', NULL, NULL),
(159, 542, 19, 1, NULL, '2026-05-02 15:38:37', NULL, NULL),
(160, 546, 18, 1, NULL, '2026-05-05 13:12:27', NULL, NULL),
(161, 547, 18, 1, NULL, '2026-05-05 13:12:38', NULL, NULL),
(162, 549, 18, 1, NULL, '2026-05-05 13:13:43', NULL, NULL),
(163, 548, 18, 1, NULL, '2026-05-05 13:13:54', NULL, NULL),
(164, 421, 17, 1, NULL, '2026-05-18 14:36:11', NULL, NULL),
(165, 551, 18, 1, NULL, '2026-05-18 14:49:20', NULL, NULL),
(166, 552, 18, 1, NULL, '2026-05-18 14:49:28', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `kategori`
--

CREATE TABLE `kategori` (
  `id` int(11) NOT NULL,
  `nama_kategori` varchar(100) NOT NULL,
  `kode_kategori` varchar(20) NOT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `kategori`
--

INSERT INTO `kategori` (`id`, `nama_kategori`, `kode_kategori`, `parent_id`, `created_at`) VALUES
(121, 'Bahan Baku', 'BB', NULL, '2026-05-31 09:57:19'),
(122, 'Bubuk', 'B', NULL, '2026-05-31 09:57:19'),
(123, 'Coffe', 'C', NULL, '2026-05-31 09:57:19'),
(124, 'Frozen Food', 'FF', NULL, '2026-05-31 09:57:19'),
(125, 'Garnish', 'GR', NULL, '2026-05-31 09:57:19'),
(126, 'Maintenace', 'MN', NULL, '2026-05-31 09:57:19'),
(127, 'Packaging', 'PK', NULL, '2026-05-31 09:57:19'),
(128, 'Sanitation', 'SAN', NULL, '2026-05-31 09:57:19'),
(129, 'Saos', 'SO', NULL, '2026-05-31 09:57:19'),
(130, 'Sayur', 'SAY', NULL, '2026-05-31 09:57:19'),
(131, 'Serve', 'SER', NULL, '2026-05-31 09:57:19'),
(132, 'Supplies', 'SUP', NULL, '2026-05-31 09:57:19'),
(133, 'Syrup', 'SY', NULL, '2026-05-31 09:57:19'),
(134, 'Tea Bag', 'TB', NULL, '2026-05-31 09:57:19');

-- --------------------------------------------------------

--
-- Table structure for table `konversi_masukan`
--

CREATE TABLE `konversi_masukan` (
  `id` int(11) NOT NULL,
  `tanggal` date NOT NULL,
  `barang_id` int(11) NOT NULL,
  `satuan_asal_id` int(11) NOT NULL,
  `satuan_tujuan_id` int(11) NOT NULL,
  `qty_asal` decimal(12,4) NOT NULL DEFAULT 0.0000,
  `nilai_konversi` decimal(12,4) NOT NULL DEFAULT 0.0000,
  `qty_hasil` decimal(12,4) NOT NULL DEFAULT 0.0000,
  `keterangan` varchar(255) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `konversi_satuan`
--

CREATE TABLE `konversi_satuan` (
  `id` int(11) NOT NULL,
  `satuan_asal_id` int(11) DEFAULT NULL,
  `satuan_tujuan_id` int(11) DEFAULT NULL,
  `nilai_konversi` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `konversi_satuan_barang`
--

CREATE TABLE `konversi_satuan_barang` (
  `id` int(11) NOT NULL,
  `barang_id` int(11) NOT NULL,
  `satuan_asal_id` int(11) NOT NULL,
  `satuan_tujuan_id` int(11) NOT NULL,
  `nilai_konversi` decimal(12,4) NOT NULL DEFAULT 0.0000,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `konversi_satuan_barang`
--

INSERT INTO `konversi_satuan_barang` (`id`, `barang_id`, `satuan_asal_id`, `satuan_tujuan_id`, `nilai_konversi`, `created_by`, `created_at`, `updated_at`) VALUES
(2, 557, 88, 87, 1000.0000, 1, '2026-05-31 16:58:05', '2026-05-31 16:58:05'),
(3, 558, 88, 87, 1000.0000, 1, '2026-05-31 16:58:47', '2026-05-31 16:58:47'),
(4, 540, 90, 91, 32.0000, 1, '2026-05-31 17:01:17', '2026-05-31 17:01:17'),
(5, 533, 90, 87, 500.0000, 1, '2026-05-31 17:02:12', '2026-05-31 17:02:12'),
(6, 532, 90, 87, 500.0000, 1, '2026-05-31 17:02:27', '2026-05-31 17:02:27'),
(7, 538, 90, 87, 1000.0000, 1, '2026-05-31 17:02:42', '2026-05-31 17:02:42'),
(8, 535, 90, 87, 1000.0000, 1, '2026-05-31 17:02:57', '2026-05-31 17:02:57'),
(9, 546, 90, 87, 500.0000, 1, '2026-05-31 17:03:49', '2026-05-31 17:03:49'),
(10, 536, 90, 87, 1000.0000, 1, '2026-05-31 17:04:31', '2026-05-31 17:04:31'),
(11, 537, 90, 87, 500.0000, 1, '2026-05-31 17:04:47', '2026-05-31 17:04:47'),
(12, 562, 90, 92, 2.0000, 1, '2026-05-31 17:05:56', '2026-05-31 17:05:56'),
(13, 606, 91, 87, 1000.0000, 1, '2026-05-31 17:07:23', '2026-05-31 17:07:23'),
(14, 698, 91, 87, 1000.0000, 1, '2026-05-31 17:07:46', '2026-05-31 17:07:46'),
(15, 542, 90, 87, 1000.0000, 1, '2026-05-31 17:08:28', '2026-05-31 17:08:28'),
(16, 544, 86, 91, 40.0000, 1, '2026-05-31 17:09:17', '2026-05-31 17:09:17'),
(17, 545, 86, 91, 40.0000, 1, '2026-05-31 17:09:58', '2026-05-31 17:09:58'),
(18, 612, 91, 87, 1000.0000, 1, '2026-05-31 17:10:51', '2026-05-31 17:10:51'),
(19, 697, 91, 87, 1000.0000, 1, '2026-05-31 17:11:10', '2026-05-31 17:11:10'),
(20, 654, 91, 87, 200.0000, 1, '2026-05-31 17:11:24', '2026-05-31 17:11:24'),
(21, 678, 91, 89, 5000.0000, 1, '2026-05-31 17:13:39', '2026-05-31 17:13:39'),
(22, 677, 91, 89, 1000.0000, 1, '2026-05-31 17:14:02', '2026-05-31 17:14:02'),
(23, 676, 90, 89, 2000.0000, 1, '2026-05-31 17:14:31', '2026-05-31 17:14:31'),
(24, 563, 90, 92, 3.0000, 1, '2026-05-31 17:16:03', '2026-05-31 17:16:03'),
(25, 573, 90, 92, 3.0000, 1, '2026-05-31 17:16:18', '2026-05-31 17:18:55'),
(26, 572, 90, 92, 3.0000, 1, '2026-05-31 17:16:32', '2026-05-31 17:16:32'),
(27, 543, 91, 89, 700.0000, 1, '2026-05-31 17:16:50', '2026-05-31 17:16:50'),
(28, 549, 91, 89, 1000.0000, 1, '2026-05-31 17:17:14', '2026-05-31 17:17:14'),
(30, 561, 90, 91, 24.0000, 1, '2026-05-31 17:19:59', '2026-05-31 17:19:59'),
(31, 593, 86, 91, 1000.0000, 1, '2026-05-31 17:20:31', '2026-05-31 17:20:31'),
(32, 594, 86, 91, 1000.0000, 1, '2026-05-31 17:21:04', '2026-05-31 17:21:04'),
(33, 637, 91, 87, 5000.0000, 1, '2026-05-31 17:21:53', '2026-05-31 17:21:53'),
(34, 609, 90, 87, 500.0000, 1, '2026-05-31 17:22:47', '2026-05-31 17:22:47'),
(35, 703, 91, 87, 1000.0000, 1, '2026-05-31 17:23:06', '2026-05-31 17:23:06'),
(36, 554, 91, 89, 1000.0000, 1, '2026-05-31 17:28:48', '2026-05-31 17:28:48'),
(37, 630, 91, 89, 700.0000, 1, '2026-05-31 17:36:46', '2026-05-31 17:36:46'),
(38, 631, 91, 89, 700.0000, 1, '2026-05-31 17:37:01', '2026-05-31 17:37:01'),
(39, 616, 91, 89, 650.0000, 1, '2026-05-31 17:37:15', '2026-05-31 17:37:15'),
(40, 617, 91, 89, 650.0000, 1, '2026-05-31 17:37:49', '2026-05-31 17:37:49'),
(41, 618, 91, 89, 650.0000, 1, '2026-05-31 17:38:02', '2026-05-31 17:38:02'),
(42, 551, 90, 87, 850.0000, 1, '2026-05-31 17:38:28', '2026-05-31 17:38:28'),
(43, 556, 90, 91, 5.0000, 1, '2026-05-31 17:38:46', '2026-05-31 17:38:46'),
(44, 614, 91, 89, 1000.0000, 1, '2026-05-31 17:39:13', '2026-05-31 17:39:13'),
(45, 615, 91, 89, 1000.0000, 1, '2026-05-31 17:39:27', '2026-05-31 17:39:27'),
(46, 620, 91, 89, 1000.0000, 1, '2026-05-31 17:39:43', '2026-05-31 17:39:43'),
(47, 555, 86, 91, 24.0000, 1, '2026-05-31 17:40:06', '2026-05-31 17:40:06'),
(48, 548, 90, 87, 222.0000, 1, '2026-05-31 17:51:04', '2026-05-31 17:51:04');

-- --------------------------------------------------------

--
-- Table structure for table `lokasi_mapping`
--

CREATE TABLE `lokasi_mapping` (
  `id` int(11) NOT NULL,
  `nama_lokasi` varchar(100) NOT NULL,
  `kode_lokasi` varchar(20) NOT NULL,
  `deskripsi` text DEFAULT NULL,
  `aktif` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `lokasi_mapping`
--

INSERT INTO `lokasi_mapping` (`id`, `nama_lokasi`, `kode_lokasi`, `deskripsi`, `aktif`, `created_at`, `updated_at`) VALUES
(17, 'Bar', '1', '', 1, '2026-02-27 15:08:50', '2026-02-27 15:08:50'),
(18, 'Kitchen', '2', '', 1, '2026-02-27 15:09:02', '2026-02-27 15:09:02'),
(19, 'Front House', '3', '', 1, '2026-02-27 15:09:15', '2026-02-27 15:09:15');

-- --------------------------------------------------------

--
-- Table structure for table `manufacture`
--

CREATE TABLE `manufacture` (
  `id` int(11) NOT NULL,
  `no_manufacture` varchar(30) NOT NULL,
  `tanggal` date NOT NULL,
  `gudang_id` int(11) NOT NULL,
  `produk_id` int(11) NOT NULL,
  `qty_hasil` int(11) NOT NULL DEFAULT 0,
  `satuan_label` varchar(50) DEFAULT NULL,
  `qty_input` int(11) DEFAULT NULL,
  `keterangan` text DEFAULT NULL,
  `transaksi_keluar_id` int(11) DEFAULT NULL,
  `transaksi_masuk_id` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `manufacture_bahan`
--

CREATE TABLE `manufacture_bahan` (
  `id` int(11) NOT NULL,
  `manufacture_id` int(11) NOT NULL,
  `barang_id` int(11) NOT NULL,
  `detail_barang` varchar(255) NOT NULL DEFAULT '',
  `qty_pakai` int(11) NOT NULL DEFAULT 0,
  `satuan_label` varchar(50) DEFAULT NULL,
  `qty_input` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `manufacture_bom`
--

CREATE TABLE `manufacture_bom` (
  `id` int(11) NOT NULL,
  `barang_hasil_id` int(11) NOT NULL,
  `nama_resep` varchar(150) NOT NULL,
  `output_qty` decimal(12,4) NOT NULL DEFAULT 1.0000,
  `biaya_overhead` decimal(15,2) NOT NULL DEFAULT 0.00,
  `aktif` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `manufacture_bom_items`
--

CREATE TABLE `manufacture_bom_items` (
  `id` int(11) NOT NULL,
  `bom_id` int(11) NOT NULL,
  `barang_id` int(11) NOT NULL,
  `qty_per_output` decimal(12,4) NOT NULL DEFAULT 0.0000,
  `urutan` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `manufacture_orders`
--

CREATE TABLE `manufacture_orders` (
  `id` int(11) NOT NULL,
  `no_produksi` varchar(30) NOT NULL,
  `tanggal` date NOT NULL,
  `gudang_id` int(11) NOT NULL,
  `barang_hasil_id` int(11) NOT NULL,
  `bom_id` int(11) DEFAULT NULL,
  `jumlah_hasil` int(11) NOT NULL DEFAULT 0,
  `total_biaya_bahan` decimal(15,2) NOT NULL DEFAULT 0.00,
  `total_biaya_overhead` decimal(15,2) NOT NULL DEFAULT 0.00,
  `hpp_per_unit` decimal(15,2) NOT NULL DEFAULT 0.00,
  `keterangan` text DEFAULT NULL,
  `transaksi_keluar_id` int(11) DEFAULT NULL,
  `transaksi_masuk_id` int(11) DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `manufacture_order_materials`
--

CREATE TABLE `manufacture_order_materials` (
  `id` int(11) NOT NULL,
  `manufacture_order_id` int(11) NOT NULL,
  `barang_id` int(11) NOT NULL,
  `detail_barang` varchar(255) NOT NULL DEFAULT '',
  `jumlah` int(11) NOT NULL DEFAULT 0,
  `harga_satuan` decimal(15,2) NOT NULL DEFAULT 0.00,
  `subtotal` decimal(15,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `menu_access`
--

CREATE TABLE `menu_access` (
  `id` int(11) NOT NULL,
  `role_id` int(11) NOT NULL,
  `menu_name` varchar(50) NOT NULL,
  `can_view` tinyint(1) DEFAULT 0,
  `can_add` tinyint(1) DEFAULT 0,
  `can_edit` tinyint(1) DEFAULT 0,
  `can_delete` tinyint(1) DEFAULT 0,
  `can_approve` tinyint(1) DEFAULT 0,
  `can_export` tinyint(1) DEFAULT 0,
  `can_import` tinyint(1) DEFAULT 0,
  `can_complete` tinyint(1) NOT NULL DEFAULT 0,
  `can_setup_split` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `can_send_wa` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `menu_access`
--

INSERT INTO `menu_access` (`id`, `role_id`, `menu_name`, `can_view`, `can_add`, `can_edit`, `can_delete`, `can_approve`, `can_export`, `can_import`, `can_complete`, `can_setup_split`, `created_at`, `updated_at`, `can_send_wa`) VALUES
(2041, 12, 'dashboard', 1, 1, 0, 0, 0, 0, 0, 0, 0, '2025-06-28 05:14:13', '2025-06-28 05:14:13', 0),
(2042, 12, 'master', 1, 1, 0, 0, 0, 0, 0, 0, 0, '2025-06-28 05:14:13', '2025-06-28 05:14:13', 0),
(2043, 12, 'barang', 1, 1, 0, 0, 0, 0, 0, 0, 0, '2025-06-28 05:14:13', '2025-06-28 05:14:13', 0),
(2044, 12, 'kategori', 1, 0, 0, 0, 0, 0, 0, 0, 0, '2025-06-28 05:14:13', '2025-06-28 05:14:13', 0),
(2045, 12, 'supplier', 1, 0, 0, 0, 0, 0, 0, 0, 0, '2025-06-28 05:14:13', '2025-06-28 05:14:13', 0),
(2046, 12, 'satuan', 1, 0, 0, 0, 0, 0, 0, 0, 0, '2025-06-28 05:14:13', '2025-06-28 05:14:13', 0),
(2047, 12, 'mapping_items', 1, 0, 0, 0, 0, 0, 0, 0, 0, '2025-06-28 05:14:13', '2025-06-28 05:14:13', 0),
(2049, 12, 'master_gudang', 1, 1, 0, 0, 0, 0, 0, 0, 0, '2025-06-28 05:14:13', '2026-05-26 15:29:51', 0),
(2050, 12, 'transaksi', 1, 1, 0, 0, 0, 0, 0, 0, 0, '2025-06-28 05:14:13', '2025-06-28 05:14:13', 0),
(2051, 12, 'stok_masuk', 1, 1, 0, 0, 0, 0, 0, 0, 0, '2025-06-28 05:14:13', '2025-06-28 05:14:13', 0),
(2052, 12, 'stok_keluar', 1, 1, 0, 0, 0, 0, 0, 0, 0, '2025-06-28 05:14:13', '2025-06-28 05:14:13', 0),
(2053, 12, 'stok_transfer', 1, 1, 0, 0, 0, 0, 0, 0, 0, '2025-06-28 05:14:13', '2025-06-28 05:14:13', 0),
(2317, 9, 'dashboard', 1, 1, 1, 0, 0, 0, 0, 0, 0, '2025-06-29 15:58:58', '2025-06-29 15:58:58', 0),
(2318, 9, 'master', 1, 1, 1, 0, 0, 0, 0, 0, 0, '2025-06-29 15:58:58', '2025-06-29 15:58:58', 0),
(2319, 9, 'barang', 1, 1, 0, 0, 0, 0, 0, 0, 0, '2025-06-29 15:58:58', '2025-06-29 15:58:58', 0),
(2320, 9, 'kategori', 1, 1, 1, 0, 0, 0, 0, 0, 0, '2025-06-29 15:58:58', '2025-06-29 15:58:58', 0),
(2321, 9, 'supplier', 1, 1, 1, 0, 0, 0, 0, 0, 0, '2025-06-29 15:58:58', '2025-06-29 15:58:58', 0),
(2322, 9, 'satuan', 1, 1, 0, 0, 0, 0, 0, 0, 0, '2025-06-29 15:58:58', '2025-06-29 15:58:58', 0),
(2323, 9, 'mapping_items', 1, 1, 1, 0, 0, 0, 0, 0, 0, '2025-06-29 15:58:58', '2025-06-29 15:58:58', 0),
(2325, 9, 'master_gudang', 1, 1, 1, 0, 0, 0, 0, 0, 0, '2025-06-29 15:58:58', '2026-05-26 15:29:51', 0),
(2326, 9, 'transaksi', 1, 1, 1, 0, 0, 0, 0, 0, 0, '2025-06-29 15:58:58', '2025-06-29 15:58:58', 0),
(2327, 9, 'stok_masuk', 1, 1, 1, 0, 0, 0, 0, 0, 0, '2025-06-29 15:58:58', '2025-06-29 15:58:58', 0),
(2328, 9, 'stok_keluar', 1, 1, 1, 0, 0, 0, 0, 0, 0, '2025-06-29 15:58:58', '2025-06-29 15:58:58', 0),
(2329, 9, 'stok_transfer', 1, 1, 1, 0, 0, 0, 0, 0, 0, '2025-06-29 15:58:58', '2025-06-29 15:58:58', 0),
(2330, 9, 'pembelian', 1, 1, 1, 0, 0, 0, 0, 0, 0, '2025-06-29 15:58:58', '2025-06-29 15:58:58', 0),
(2331, 9, 'purchase_order', 1, 1, 1, 0, 0, 0, 0, 0, 0, '2025-06-29 15:58:58', '2025-06-29 15:58:58', 0),
(2333, 9, 'pembelian_direct', 1, 1, 1, 0, 0, 0, 0, 0, 0, '2025-06-29 15:58:58', '2025-06-29 15:58:58', 0),
(2334, 9, 'surat_jalan', 1, 1, 1, 0, 0, 0, 0, 0, 0, '2025-06-29 15:58:58', '2025-06-29 15:58:58', 0),
(2335, 9, 'laporan', 1, 1, 1, 0, 0, 0, 0, 0, 0, '2025-06-29 15:58:58', '2025-06-29 15:58:58', 0),
(2336, 9, 'laporan_stok', 1, 1, 1, 0, 0, 0, 0, 0, 0, '2025-06-29 15:58:58', '2025-06-29 15:58:58', 0),
(2337, 9, 'po', 1, 1, 1, 0, 0, 0, 0, 0, 0, '2025-06-29 15:58:58', '2025-06-29 15:58:58', 0),
(2338, 9, 'laporan_pembelian', 1, 1, 1, 0, 0, 0, 0, 0, 0, '2025-06-29 15:58:58', '2025-06-29 15:58:58', 0),
(2339, 9, 'laporan_transfer', 1, 1, 1, 0, 0, 0, 0, 0, 0, '2025-06-29 15:58:58', '2025-06-29 15:58:58', 0),
(2340, 9, 'user', 1, 1, 1, 0, 0, 0, 0, 0, 0, '2025-06-29 15:58:58', '2025-06-29 15:58:58', 0),
(2341, 9, 'setup', 1, 1, 1, 0, 0, 0, 0, 0, 0, '2025-06-29 15:58:58', '2025-06-29 15:58:58', 0),
(2342, 9, 'reset_stok', 1, 1, 1, 0, 0, 0, 0, 0, 0, '2025-06-29 15:58:58', '2025-06-29 15:58:58', 0),
(4354, 21, 'dashboard', 1, 1, 0, 0, 0, 0, 0, 0, 0, '2026-05-26 15:20:45', '2026-05-26 15:20:45', 0),
(4355, 21, 'master', 1, 1, 0, 0, 0, 0, 0, 0, 0, '2026-05-26 15:20:45', '2026-05-26 15:20:45', 0),
(4356, 21, 'barang', 1, 1, 0, 0, 0, 0, 0, 0, 0, '2026-05-26 15:20:45', '2026-05-26 15:20:45', 0),
(4357, 21, 'kategori', 1, 1, 0, 0, 0, 0, 0, 0, 0, '2026-05-26 15:20:45', '2026-05-26 15:20:45', 0),
(4358, 21, 'supplier', 1, 1, 0, 0, 0, 0, 0, 0, 0, '2026-05-26 15:20:45', '2026-05-26 15:20:45', 0),
(4359, 21, 'satuan', 1, 1, 0, 0, 0, 0, 0, 0, 0, '2026-05-26 15:20:45', '2026-05-26 15:20:45', 0),
(4360, 21, 'mapping_items', 1, 1, 0, 0, 0, 0, 0, 0, 0, '2026-05-26 15:20:45', '2026-05-26 15:20:45', 0),
(4361, 21, 'konversi_masukan', 1, 0, 0, 0, 0, 0, 0, 0, 0, '2026-05-26 15:20:45', '2026-05-26 15:20:45', 0),
(4363, 21, 'master_gudang', 1, 1, 0, 0, 0, 0, 0, 0, 0, '2026-05-26 15:20:45', '2026-05-26 15:29:51', 0),
(4364, 21, 'gudang_central', 1, 1, 1, 0, 0, 0, 0, 0, 0, '2026-05-26 15:20:45', '2026-05-26 15:20:45', 0),
(4365, 21, 'gudang_antapani', 1, 1, 1, 0, 0, 0, 0, 0, 0, '2026-05-26 15:20:45', '2026-05-26 15:20:45', 0),
(4366, 21, 'transaksi', 1, 1, 0, 0, 0, 0, 0, 0, 0, '2026-05-26 15:20:45', '2026-05-26 15:20:45', 0),
(4367, 21, 'stok_masuk', 1, 1, 0, 0, 0, 0, 0, 0, 0, '2026-05-26 15:20:45', '2026-05-26 15:20:45', 0),
(4368, 21, 'stok_keluar', 1, 1, 0, 0, 0, 0, 0, 0, 0, '2026-05-26 15:20:45', '2026-05-26 15:20:45', 0),
(4369, 21, 'stok_transfer', 1, 1, 0, 0, 0, 0, 0, 0, 0, '2026-05-26 15:20:45', '2026-05-26 15:20:45', 0),
(5586, 1, 'dashboard', 1, 1, 1, 1, 0, 0, 0, 0, 0, '2026-05-31 12:39:12', '2026-05-31 12:39:12', 0),
(5587, 1, 'master', 1, 1, 1, 1, 0, 0, 0, 0, 0, '2026-05-31 12:39:12', '2026-05-31 12:39:12', 0),
(5588, 1, 'barang', 1, 1, 1, 1, 0, 0, 0, 0, 1, '2026-05-31 12:39:12', '2026-05-31 12:39:12', 0),
(5589, 1, 'kategori', 1, 1, 1, 1, 0, 0, 0, 0, 0, '2026-05-31 12:39:12', '2026-05-31 12:39:12', 0),
(5590, 1, 'supplier', 1, 1, 1, 1, 0, 0, 0, 0, 0, '2026-05-31 12:39:12', '2026-05-31 12:39:12', 0),
(5591, 1, 'satuan', 1, 1, 1, 1, 0, 0, 0, 0, 0, '2026-05-31 12:39:12', '2026-05-31 12:39:12', 0),
(5592, 1, 'mapping_items', 1, 1, 1, 1, 0, 0, 0, 0, 0, '2026-05-31 12:39:12', '2026-05-31 12:39:12', 0),
(5593, 1, 'konversi_masukan', 1, 1, 1, 1, 0, 0, 0, 0, 0, '2026-05-31 12:39:12', '2026-05-31 12:39:12', 0),
(5594, 1, 'gudang', 1, 1, 1, 1, 0, 0, 0, 0, 0, '2026-05-31 12:39:12', '2026-05-31 12:39:12', 0),
(5595, 1, 'master_gudang', 1, 1, 1, 1, 0, 0, 0, 0, 0, '2026-05-31 12:39:12', '2026-05-31 12:39:12', 0),
(5596, 1, 'gudang_central', 1, 1, 1, 1, 0, 0, 0, 0, 0, '2026-05-31 12:39:12', '2026-05-31 12:39:12', 0),
(5597, 1, 'gudang_antapani', 1, 1, 1, 1, 0, 0, 0, 0, 0, '2026-05-31 12:39:12', '2026-05-31 12:39:12', 0),
(5598, 1, 'tambah_gudang', 1, 1, 1, 1, 0, 0, 0, 0, 0, '2026-05-31 12:39:12', '2026-05-31 12:39:12', 0),
(5599, 1, 'transaksi', 1, 1, 1, 1, 0, 0, 0, 0, 0, '2026-05-31 12:39:12', '2026-05-31 12:39:12', 0),
(5600, 1, 'stok_masuk', 1, 1, 1, 1, 0, 0, 0, 0, 0, '2026-05-31 12:39:12', '2026-05-31 12:39:12', 0),
(5601, 1, 'stok_keluar', 1, 1, 1, 1, 0, 0, 0, 0, 0, '2026-05-31 12:39:12', '2026-05-31 12:39:12', 0),
(5602, 1, 'stok_transfer', 1, 1, 1, 1, 0, 0, 0, 0, 0, '2026-05-31 12:39:12', '2026-05-31 12:39:12', 0),
(5603, 1, 'adjustment_in', 1, 1, 1, 1, 0, 0, 0, 0, 0, '2026-05-31 12:39:12', '2026-05-31 12:39:12', 0),
(5604, 1, 'adjustment_out', 1, 1, 1, 1, 0, 0, 0, 0, 0, '2026-05-31 12:39:12', '2026-05-31 12:39:12', 0),
(5605, 1, 'pembelian', 1, 1, 1, 1, 0, 0, 0, 0, 0, '2026-05-31 12:39:12', '2026-05-31 12:39:12', 0),
(5606, 1, 'purchase_order', 1, 1, 1, 1, 0, 0, 0, 1, 0, '2026-05-31 12:39:12', '2026-05-31 12:39:12', 0),
(5607, 1, 'approve', 1, 0, 0, 0, 0, 0, 0, 0, 0, '2026-05-31 12:39:12', '2026-05-31 12:39:12', 0),
(5608, 1, 'pembelian_direct', 1, 1, 1, 1, 0, 0, 0, 0, 0, '2026-05-31 12:39:12', '2026-05-31 12:39:12', 0),
(5609, 1, 'payment', 1, 0, 0, 0, 0, 0, 0, 0, 0, '2026-05-31 12:39:12', '2026-05-31 12:39:12', 0),
(5610, 1, 'vendor_refund', 1, 1, 1, 1, 0, 0, 0, 0, 0, '2026-05-31 12:39:12', '2026-05-31 12:39:12', 0),
(5611, 1, 'manufacture', 1, 1, 1, 1, 0, 0, 0, 0, 0, '2026-05-31 12:39:12', '2026-05-31 12:39:12', 0),
(5612, 1, 'surat_jalan', 1, 1, 1, 1, 0, 0, 0, 0, 0, '2026-05-31 12:39:12', '2026-05-31 12:39:12', 0),
(5613, 1, 'laporan', 1, 0, 0, 0, 0, 0, 0, 0, 0, '2026-05-31 12:39:12', '2026-05-31 12:39:12', 0),
(5614, 1, 'laporan_stok', 1, 0, 0, 0, 0, 0, 0, 0, 0, '2026-05-31 12:39:12', '2026-05-31 12:39:12', 0),
(5615, 1, 'laporan_po', 1, 0, 0, 0, 0, 0, 0, 0, 0, '2026-05-31 12:39:12', '2026-05-31 12:39:12', 0),
(5616, 1, 'laporan_pembelian', 1, 0, 0, 0, 0, 0, 0, 0, 0, '2026-05-31 12:39:12', '2026-05-31 12:39:12', 0),
(5617, 1, 'laporan_transfer', 1, 0, 0, 0, 0, 0, 0, 0, 0, '2026-05-31 12:39:12', '2026-05-31 12:39:12', 0),
(5618, 1, 'laporan_adjustment_in', 1, 0, 0, 0, 0, 0, 0, 0, 0, '2026-05-31 12:39:12', '2026-05-31 12:39:12', 0),
(5619, 1, 'laporan_adjustment_out', 1, 0, 0, 0, 0, 0, 0, 0, 0, '2026-05-31 12:39:12', '2026-05-31 12:39:12', 0),
(5620, 1, 'backoffice', 1, 1, 1, 1, 0, 0, 0, 0, 0, '2026-05-31 12:39:12', '2026-05-31 12:39:12', 0),
(5621, 1, 'backoffice_dashboard', 1, 1, 1, 1, 0, 0, 0, 0, 0, '2026-05-31 12:39:12', '2026-05-31 12:39:12', 0),
(5622, 1, 'backoffice_reports_inventory', 1, 1, 1, 1, 0, 0, 0, 0, 0, '2026-05-31 12:39:12', '2026-05-31 12:39:12', 0),
(5623, 1, 'backoffice_reports_finance', 1, 1, 1, 1, 0, 0, 0, 0, 0, '2026-05-31 12:39:12', '2026-05-31 12:39:12', 0),
(5624, 1, 'backoffice_reports_po', 1, 1, 1, 1, 0, 0, 0, 0, 0, '2026-05-31 12:39:12', '2026-05-31 12:39:12', 0),
(5625, 1, 'backoffice_reports_direct', 1, 1, 1, 1, 0, 0, 0, 0, 0, '2026-05-31 12:39:12', '2026-05-31 12:39:12', 0),
(5626, 1, 'backoffice_reports_inventory_price', 1, 1, 1, 1, 0, 0, 0, 0, 0, '2026-05-31 12:39:12', '2026-05-31 12:39:12', 0),
(5627, 1, 'backoffice_reports_item_movement', 1, 1, 1, 1, 0, 0, 0, 0, 0, '2026-05-31 12:39:12', '2026-05-31 12:39:12', 0),
(5628, 1, 'backoffice_users', 1, 1, 1, 1, 0, 0, 0, 0, 0, '2026-05-31 12:39:12', '2026-05-31 12:39:12', 0),
(5629, 1, 'backoffice_roles', 1, 1, 1, 1, 0, 0, 0, 0, 0, '2026-05-31 12:39:12', '2026-05-31 12:39:12', 0),
(5630, 1, 'user', 1, 1, 1, 1, 0, 0, 0, 0, 0, '2026-05-31 12:39:12', '2026-05-31 12:39:12', 0),
(5631, 1, 'setup', 1, 1, 1, 1, 0, 0, 0, 0, 0, '2026-05-31 12:39:12', '2026-05-31 12:39:12', 0),
(5632, 1, 'edit_nama_gudang', 1, 1, 1, 1, 0, 0, 0, 0, 0, '2026-05-31 12:39:12', '2026-05-31 12:39:12', 0),
(5633, 1, 'template_po', 1, 1, 1, 1, 0, 0, 0, 0, 0, '2026-05-31 12:39:12', '2026-05-31 12:39:12', 0),
(5634, 1, 'barcode', 1, 0, 0, 0, 0, 0, 0, 0, 0, '2026-05-31 12:39:12', '2026-05-31 12:39:12', 0),
(5635, 1, 'get_wa', 1, 1, 1, 1, 0, 0, 0, 0, 0, '2026-05-31 12:39:12', '2026-05-31 12:39:12', 0),
(5636, 24, 'laporan', 1, 0, 0, 0, 0, 0, 0, 0, 0, '2026-05-31 12:43:54', '2026-05-31 12:43:54', 0),
(5637, 24, 'laporan_stok', 1, 0, 0, 0, 0, 0, 0, 0, 0, '2026-05-31 12:43:54', '2026-05-31 12:43:54', 0),
(5638, 24, 'laporan_po', 1, 0, 0, 0, 0, 0, 0, 0, 0, '2026-05-31 12:43:54', '2026-05-31 12:43:54', 0),
(5639, 24, 'laporan_pembelian', 1, 0, 0, 0, 0, 0, 0, 0, 0, '2026-05-31 12:43:54', '2026-05-31 12:43:54', 0),
(5640, 24, 'laporan_transfer', 1, 0, 0, 0, 0, 0, 0, 0, 0, '2026-05-31 12:43:54', '2026-05-31 12:43:54', 0),
(5641, 24, 'laporan_adjustment_in', 1, 0, 0, 0, 0, 0, 0, 0, 0, '2026-05-31 12:43:54', '2026-05-31 12:43:54', 0),
(5642, 24, 'laporan_adjustment_out', 1, 0, 0, 0, 0, 0, 0, 0, 0, '2026-05-31 12:43:54', '2026-05-31 12:43:54', 0),
(5643, 24, 'backoffice', 1, 1, 1, 1, 0, 0, 0, 0, 0, '2026-05-31 12:43:54', '2026-05-31 12:43:54', 0),
(5644, 24, 'backoffice_dashboard', 1, 1, 1, 1, 0, 0, 0, 0, 0, '2026-05-31 12:43:54', '2026-05-31 12:43:54', 0),
(5645, 24, 'backoffice_reports_inventory', 1, 1, 1, 1, 0, 0, 0, 0, 0, '2026-05-31 12:43:54', '2026-05-31 12:43:54', 0),
(5646, 24, 'backoffice_reports_finance', 1, 1, 1, 1, 0, 0, 0, 0, 0, '2026-05-31 12:43:54', '2026-05-31 12:43:54', 0),
(5647, 24, 'backoffice_reports_po', 1, 1, 1, 1, 0, 0, 0, 0, 0, '2026-05-31 12:43:54', '2026-05-31 12:43:54', 0),
(5648, 24, 'backoffice_reports_direct', 1, 1, 1, 1, 0, 0, 0, 0, 0, '2026-05-31 12:43:54', '2026-05-31 12:43:54', 0),
(5649, 24, 'backoffice_reports_inventory_price', 1, 1, 1, 1, 0, 0, 0, 0, 0, '2026-05-31 12:43:54', '2026-05-31 12:43:54', 0),
(5650, 24, 'backoffice_reports_item_movement', 1, 1, 1, 0, 0, 0, 0, 0, 0, '2026-05-31 12:43:54', '2026-05-31 12:43:54', 0),
(5651, 24, 'backoffice_users', 1, 1, 1, 1, 0, 0, 0, 0, 0, '2026-05-31 12:43:54', '2026-05-31 12:43:54', 0),
(5652, 24, 'backoffice_roles', 1, 1, 1, 1, 0, 0, 0, 0, 0, '2026-05-31 12:43:54', '2026-05-31 12:43:54', 0);

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `message_type` enum('text','image','audio','file') DEFAULT 'text',
  `file_path` varchar(500) DEFAULT NULL,
  `is_notification` tinyint(1) DEFAULT 0,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `expired_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pengeluaran`
--

CREATE TABLE `pengeluaran` (
  `id` int(11) NOT NULL,
  `tanggal` date NOT NULL,
  `no_pengeluaran` varchar(50) NOT NULL,
  `total_item` int(11) NOT NULL,
  `total_harga` decimal(15,2) NOT NULL,
  `keterangan` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `po_detail`
--

CREATE TABLE `po_detail` (
  `id` int(11) NOT NULL,
  `po_id` int(11) DEFAULT NULL,
  `barang_id` int(11) DEFAULT NULL,
  `jumlah` int(11) NOT NULL,
  `harga_satuan` decimal(15,2) NOT NULL,
  `total_harga` decimal(15,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `po_stock_split`
--

CREATE TABLE `po_stock_split` (
  `id` int(11) NOT NULL,
  `purchase_order_id` int(11) NOT NULL,
  `detail_purchase_order_id` int(11) NOT NULL,
  `split_barang_id` int(11) DEFAULT NULL,
  `detail_barang` varchar(255) NOT NULL,
  `qty_output` int(11) NOT NULL DEFAULT 0,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `po_template`
--

CREATE TABLE `po_template` (
  `id` int(11) NOT NULL,
  `nama_template` varchar(255) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `barang_id` int(11) NOT NULL,
  `jumlah` decimal(15,2) NOT NULL DEFAULT 1.00,
  `keterangan` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `product`
--

CREATE TABLE `product` (
  `id` int(11) NOT NULL,
  `nama_product` varchar(150) NOT NULL,
  `gudang_id` int(11) NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `product_detail`
--

CREATE TABLE `product_detail` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `barang_id` int(11) NOT NULL,
  `qty` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `purchase_order`
--

CREATE TABLE `purchase_order` (
  `id` int(11) NOT NULL,
  `nomor` varchar(20) DEFAULT NULL,
  `no_po` varchar(20) NOT NULL,
  `tanggal` date NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `total_item` int(11) DEFAULT 0,
  `total_harga` decimal(15,2) DEFAULT 0.00,
  `keterangan` text DEFAULT NULL,
  `foto` varchar(255) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'menunggu',
  `purchase_date` date DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `keterangan_complete` text DEFAULT NULL,
  `keterangan_reject` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `purchase_orders`
--

CREATE TABLE `purchase_orders` (
  `id` int(11) NOT NULL,
  `po_number` varchar(255) NOT NULL,
  `po_date` date NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `status` enum('pending','approved','rejected','delivered') NOT NULL DEFAULT 'pending',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `satuan` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `riwayat_stok`
--

CREATE TABLE `riwayat_stok` (
  `id` int(11) NOT NULL,
  `gudang_id` int(11) DEFAULT NULL,
  `barang_id` int(11) DEFAULT NULL,
  `stok_awal_sebelum` int(11) DEFAULT NULL,
  `stok_terpakai_sebelum` int(11) DEFAULT NULL,
  `stok_akhir_sebelum` int(11) DEFAULT NULL,
  `tanggal_reset` datetime DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` int(11) NOT NULL,
  `nama_role` varchar(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deskripsi` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `nama_role`, `created_at`, `updated_at`, `deskripsi`) VALUES
(1, 'Administrator', '2025-05-13 10:15:50', '2025-05-13 10:15:50', NULL),
(9, 'Purchasing', '2025-05-20 13:55:38', '2025-05-20 13:55:38', NULL),
(21, 'staff', '2025-06-28 05:23:38', '2025-06-28 05:23:38', NULL),
(24, 'Backoffice', '2026-05-30 11:48:56', '2026-05-30 11:48:56', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `satuan`
--

CREATE TABLE `satuan` (
  `id` int(11) NOT NULL,
  `nama_satuan` varchar(50) NOT NULL,
  `kode_satuan` varchar(10) NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `satuan`
--

INSERT INTO `satuan` (`id`, `nama_satuan`, `kode_satuan`, `created_by`, `created_at`) VALUES
(86, 'dus', '6', NULL, '2026-05-31 16:54:20'),
(87, 'gr', '2', NULL, '2026-05-31 16:54:20'),
(88, 'kg', '1', NULL, '2026-05-31 16:54:20'),
(89, 'ml', '3', NULL, '2026-05-31 16:54:20'),
(90, 'pak', '4', NULL, '2026-05-31 16:54:20'),
(91, 'pcs', '5', NULL, '2026-05-31 16:54:20'),
(92, 'porsi', '7', 1, '2026-05-31 22:19:56');

-- --------------------------------------------------------

--
-- Table structure for table `setup_barcode_config`
--

CREATE TABLE `setup_barcode_config` (
  `id` tinyint(3) UNSIGNED NOT NULL,
  `mode` enum('scanner','hp') NOT NULL DEFAULT 'scanner',
  `is_connected` tinyint(1) NOT NULL DEFAULT 0,
  `last_connected_at` datetime DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `setup_barcode_config`
--

INSERT INTO `setup_barcode_config` (`id`, `mode`, `is_connected`, `last_connected_at`, `updated_at`, `updated_by`) VALUES
(1, 'scanner', 0, NULL, '2026-05-06 11:31:17', 7);

-- --------------------------------------------------------

--
-- Table structure for table `setup_file_template`
--

CREATE TABLE `setup_file_template` (
  `id` int(11) NOT NULL,
  `kategori` enum('laporan','cetakan','logo') NOT NULL,
  `nama_file_asli` varchar(255) NOT NULL,
  `nama_file_simpan` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_ext` varchar(20) NOT NULL,
  `mime_type` varchar(100) DEFAULT NULL,
  `file_size` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `uploaded_by` int(11) DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `setup_reset_stok`
--

CREATE TABLE `setup_reset_stok` (
  `id` int(11) NOT NULL,
  `jam_reset` time NOT NULL DEFAULT '00:00:00',
  `gudang_id` int(11) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `last_reset` datetime DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `setup_whatsapp`
--

CREATE TABLE `setup_whatsapp` (
  `id` int(11) NOT NULL,
  `wa_number` varchar(20) NOT NULL,
  `template_message` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `setup_whatsapp`
--

INSERT INTO `setup_whatsapp` (`id`, `wa_number`, `template_message`, `is_active`, `updated_at`, `updated_by`) VALUES
(1, 'DYNAMIC', 'Halo, ada Purchase Order baru dengan nomor {no_po} untuk supplier {supplier}.  {items}\r\nMohon diproses. Terima kasih.\r\nJason', 1, '2026-05-30 13:28:29', 7);

-- --------------------------------------------------------

--
-- Table structure for table `stok`
--

CREATE TABLE `stok` (
  `id` int(11) NOT NULL,
  `barang_id` int(11) DEFAULT NULL,
  `gudang_id` int(11) DEFAULT NULL,
  `jumlah` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `stok_barang`
--

CREATE TABLE `stok_barang` (
  `id` int(11) NOT NULL,
  `gudang_id` int(11) NOT NULL,
  `barang_id` int(11) NOT NULL,
  `detail_barang` varchar(255) DEFAULT '',
  `jumlah` int(11) NOT NULL DEFAULT 0 CHECK (`jumlah` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `stok_gudang`
--

CREATE TABLE `stok_gudang` (
  `id` int(11) NOT NULL,
  `gudang_id` int(11) NOT NULL,
  `barang_id` int(11) NOT NULL,
  `jumlah` decimal(10,2) NOT NULL DEFAULT 0.00,
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `stok_history`
--

CREATE TABLE `stok_history` (
  `id` int(11) NOT NULL,
  `tanggal` datetime NOT NULL,
  `barang_id` int(11) NOT NULL,
  `jumlah` decimal(10,2) NOT NULL,
  `jenis_transaksi` enum('transfer_in','transfer_out') NOT NULL,
  `keterangan` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `gudang_id` int(11) DEFAULT NULL,
  `gudang_tujuan_id` int(11) DEFAULT NULL,
  `referensi` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `stok_keluar`
--

CREATE TABLE `stok_keluar` (
  `id` int(11) NOT NULL,
  `tanggal` datetime NOT NULL DEFAULT current_timestamp(),
  `barang_id` int(11) NOT NULL,
  `gudang_id` int(11) NOT NULL,
  `jumlah` int(11) NOT NULL,
  `keterangan` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `stok_masuk`
--

CREATE TABLE `stok_masuk` (
  `id` int(11) NOT NULL,
  `tanggal` datetime NOT NULL DEFAULT current_timestamp(),
  `barang_id` int(11) NOT NULL,
  `gudang_id` int(11) NOT NULL,
  `jumlah` int(11) NOT NULL,
  `keterangan` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `stok_terpakai`
--

CREATE TABLE `stok_terpakai` (
  `id` int(11) NOT NULL,
  `tanggal` datetime NOT NULL,
  `barang_id` int(11) NOT NULL,
  `gudang_id` int(11) NOT NULL,
  `jumlah` int(11) NOT NULL,
  `keterangan` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `supplier`
--

CREATE TABLE `supplier` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `kode_supplier` varchar(20) NOT NULL,
  `nama_supplier` varchar(100) NOT NULL,
  `alamat` text DEFAULT NULL,
  `telepon` varchar(15) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `no_rekening` varchar(255) DEFAULT NULL,
  `terms_of_payment` int(11) DEFAULT 30,
  `gambar` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `supplier`
--

INSERT INTO `supplier` (`id`, `name`, `kode_supplier`, `nama_supplier`, `alamat`, `telepon`, `email`, `no_rekening`, `terms_of_payment`, `gambar`, `created_at`) VALUES
(12470, 'Atlas Packaging', '08ATLBDG', 'Atlas Packaging', 'Jl. Cikawao Dalam 1 No.31, Bandung', '0881012275888', NULL, 'GRACE MULYA S (BCA) 8090515251', 30, NULL, '2026-05-31 10:20:27'),
(12471, 'Bein', '30BEINBDG', 'Bein', 'Djunjunan, Bandung, Jawa Barat', '+62 82262990098', NULL, '4491348121 (BCA an. Christian Alfonsus L.)', 30, NULL, '2026-05-31 10:20:27'),
(12472, 'Borma Antapani', '0010', 'Borma Antapani', NULL, NULL, NULL, NULL, 30, NULL, '2026-05-31 10:20:27'),
(12473, 'Chicken Nusantara', '28BDG', 'Chicken Nusantara', 'Pasirkoja', NULL, NULL, NULL, 30, NULL, '2026-05-31 10:20:27'),
(12474, 'Classified', '01TGBV', 'Classified', NULL, NULL, NULL, NULL, 30, NULL, '2026-05-31 10:20:27'),
(12475, 'Coffee Solution Indo', '10CSITKP', 'Coffee Solution Indo', 'Tokopedia', NULL, NULL, NULL, 30, NULL, '2026-05-31 10:20:27'),
(12476, 'Depo Fresh Water', '13FRBDG', 'Depo Fresh Water', 'Jl.Cibatu Raya 2, Antapani', '085102903151', NULL, 'NINING SARININGSIH (BRI) - 074801025047538', 30, NULL, '2026-05-31 10:20:27'),
(12477, 'Engko Es', '04ENBDG', 'Engko Es', 'Jl. Cikadut No.15, Jatihandap', '089512185898', NULL, 'DANA - 3901083820921', 30, NULL, '2026-05-31 10:20:27'),
(12478, 'Firsian Flag', '07FFBDG', 'Firsian Flag', NULL, NULL, NULL, 'MUHAMAD ARMIN (BCA) 4530117575', 30, NULL, '2026-05-31 10:20:27'),
(12479, 'FK Sinar Indah', '019FKSITKP', 'FK Sinar Indah', 'Tokopedia', NULL, NULL, NULL, 30, NULL, '2026-05-31 10:20:27'),
(12480, 'Garnishan', '18GARTKP', 'Garnishan', 'Tokopedia', NULL, NULL, NULL, 30, NULL, '2026-05-31 10:20:27'),
(12481, 'Gudeg Yu Nap Online', '09GYNTKP', 'Gudeg Yu Nap Online', 'Tokopedia', NULL, NULL, NULL, 30, NULL, '2026-05-31 10:20:27'),
(12482, 'Hendra Sayur', '05HSBDG', 'Hendra Sayur', NULL, '085783352624', NULL, 'MELYA YUHADA (BCA) 3791416025', 30, NULL, '2026-05-31 10:20:27'),
(12483, 'In-House (JAWIL)', '12INH', 'In-House (JAWIL)', NULL, NULL, NULL, NULL, 30, NULL, '2026-05-31 10:20:27'),
(12484, 'Lets Brew', '03LBBDG', 'Lets Brew', NULL, NULL, NULL, NULL, 30, NULL, '2026-05-31 10:20:27'),
(12485, 'Mantaps\'s Coffee', '034', 'Mantaps\'s Coffee', 'Jl. Sindangsari Asri III, No. 17, Antapani Wetan, Antapani, Kota Bandung', NULL, NULL, NULL, 30, NULL, '2026-05-31 10:20:27'),
(12486, 'Mine Sosis Baso (Frozen)', '06MINBDG', 'Mine Sosis Baso (Frozen)', 'Jl. Subang, Antapani', NULL, NULL, NULL, 30, NULL, '2026-05-31 10:20:27'),
(12487, 'Nusantara Powder Drink', '222NPSBDG', 'Nusantara Powder Drink', 'Jl. Margacinta No.72A, Bandung', '+62 89507382182', NULL, NULL, 30, NULL, '2026-05-31 10:20:27'),
(12488, 'Pintu tiga raharja', '15FFBNJ', 'Pintu tiga raharja', 'jalan raya banjaran', '0882001245688', NULL, 'BCA : 0863733399', 30, NULL, '2026-05-31 10:20:27'),
(12489, 'PT. Bandung Kulina Utama', '11BKUBDG', 'PT. Bandung Kulina Utama', 'Jl. Gede Bage Selatan No.88, Cisaranten Kidul, A/N Pramudita 0 Gede Bage, Kota Bandung', NULL, NULL, 'BANDUNGKULINAUTAMAPT (BCA) 2833008781', 30, NULL, '2026-05-31 10:20:27');

-- --------------------------------------------------------

--
-- Table structure for table `supplier_barang`
--

CREATE TABLE `supplier_barang` (
  `id` int(11) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `barang_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `surat_jalan`
--

CREATE TABLE `surat_jalan` (
  `id` int(11) NOT NULL,
  `po_id` int(11) NOT NULL,
  `surat_jalan_number` varchar(50) NOT NULL,
  `surat_jalan_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `tujuan` varchar(255) NOT NULL,
  `status` enum('Draft','Dikirim','Selesai') DEFAULT 'Draft',
  `created_by` varchar(100) DEFAULT 'Admin',
  `status_pembayaran` varchar(20) DEFAULT 'belum_dibayar'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `surat_jalan_items`
--

CREATE TABLE `surat_jalan_items` (
  `id` int(11) NOT NULL,
  `surat_jalan_id` int(11) NOT NULL,
  `barang_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `satuan_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `toko`
--

CREATE TABLE `toko` (
  `id` int(11) NOT NULL,
  `nama_toko` varchar(255) NOT NULL,
  `alamat` text DEFAULT NULL,
  `telepon` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `transaksi_stock`
--

CREATE TABLE `transaksi_stock` (
  `id` int(11) NOT NULL,
  `kode_transaksi` varchar(20) NOT NULL,
  `tanggal` date NOT NULL,
  `gudang_id` int(11) NOT NULL,
  `keterangan` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `transaksi_stok`
--

CREATE TABLE `transaksi_stok` (
  `id` int(11) NOT NULL,
  `no_transaksi` varchar(20) NOT NULL,
  `tanggal` date NOT NULL,
  `gudang_id` int(11) NOT NULL,
  `jenis_transaksi` enum('masuk','keluar','adjustment_in','adjustment_out') NOT NULL,
  `keterangan` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `jumlah` int(11) DEFAULT 0,
  `barang_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `transaksi_transfer`
--

CREATE TABLE `transaksi_transfer` (
  `id` int(11) NOT NULL,
  `no_transaksi` varchar(20) NOT NULL,
  `tanggal` date NOT NULL,
  `barang_id` int(11) DEFAULT NULL,
  `gudang_id` int(11) DEFAULT NULL,
  `gudang_asal_id` int(11) NOT NULL,
  `gudang_tujuan_id` int(11) NOT NULL,
  `keterangan` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `jumlah` int(11) DEFAULT NULL,
  `jenis_transaksi` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `transfer_gudang`
--

CREATE TABLE `transfer_gudang` (
  `id` int(11) NOT NULL,
  `po_id` int(11) NOT NULL,
  `gudang_id` int(11) NOT NULL,
  `tanggal` date NOT NULL,
  `jumlah` int(11) NOT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `kode_pegawai` varchar(20) DEFAULT NULL,
  `telepon` varchar(15) DEFAULT NULL,
  `alamat` text DEFAULT NULL,
  `keterangan` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `nama_lengkap` varchar(100) NOT NULL,
  `role` enum('admin','staff','purchasing') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `role_id` int(11) NOT NULL,
  `profile_picture` varchar(255) DEFAULT NULL,
  `status` varchar(20) DEFAULT 'aktif'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `nama`, `email`, `kode_pegawai`, `telepon`, `alamat`, `keterangan`, `is_active`, `created_by`, `password`, `nama_lengkap`, `role`, `created_at`, `updated_at`, `role_id`, `profile_picture`, `status`) VALUES
(1, 'admin', 'Administrator', 'admin@minven.com', NULL, NULL, NULL, 'Administrator utama sistem', 1, NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator', 'admin', '2025-05-13 03:19:22', '2025-06-28 23:47:11', 0, NULL, 'aktif'),
(7, 'sano', 'sano', 'Fauzan@Minven.com', 'TK-00225A', NULL, NULL, 'Admin cabang Antapani', 1, NULL, '$2y$10$lfkDWiCNax8cwVedMJpsI.78hmArBGaO1x4Q6gsxf.ZwyQSwyt2vu', 'sano', 'admin', '2025-05-13 06:35:36', '2026-05-31 10:05:12', 1, '../uploads/user/7_1748079836_phantom.jpeg', 'aktif'),
(35, 'staff', 'staff-Minven', 'Staff@minven.com', 'MIN-001', '08xxxxxxxxxx', '', 'Staff operasional', 0, NULL, '$2y$10$qoZE68dKaU0b97MIKfs8CuJ1HsLSoJ60EzDl2jW9XY73f8tg9llIe', 'staff-Minven', 'staff', '2025-06-27 15:07:23', '2026-01-11 03:27:23', 0, '', 'aktif'),
(36, 'petugas', 'petugas-test', 'petugas@minven.com', NULL, '08xxxxxxxxxx', 'sinsar', 'Petugas testing', 1, 7, '$2y$10$jrV/45j8LAXPFVA2kRNvVOXeH5r8lSKECe4GV/tmNyh4GcZVWPn5.', 'petugas-test', 'admin', '2025-06-27 22:01:03', '2025-06-28 16:47:11', 0, NULL, 'aktif'),
(38, 'Oca', 'Bella Octaviani', 'kimocaa810@gmail.com', 'CJA-FB824', '', '', '', 0, 1, '$2y$10$0Oq1mCH9rEC7k4makJ3nbeB/HV2fl2T62XoI/sS2ylwpMU/uFDf8y', 'Bella Oktaviani', '', '2025-07-02 02:37:30', '2026-01-11 02:33:09', 0, NULL, 'aktif'),
(39, 'gadis', 'Gadis Meila C', 'gadismeila292@gmail.com', 'CJA-MF1525', '', '', '', 0, 1, '$2y$10$DU6v4Odnxmu75Ma4sirAMuWH.OjmLq.dEEFLEPaULqtUoVMuLi6/u', 'Gadis Meila Canda Setiadi', '', '2025-07-02 02:43:33', '2026-01-11 02:33:19', 0, NULL, 'aktif'),
(40, 'Raisya', 'Raisya Mylia', 'raisyamylia@gmail.com', 'CJL-A013', '', '', '', 1, 1, '$2y$10$N8Ouh.DbulZKlLA4iwaiBubXz/wqbzOBTfS./g8sgJaixEQkCMJpm', 'Raisya Mylia Nulaifar', '', '2025-07-03 02:22:38', '2025-07-03 02:22:38', 0, NULL, 'aktif'),
(41, 'Risna ', 'Risna Seftia', 'risnaseftiadwi@gmail.com', 'CJA-FC1425', '', '', '', 1, 1, '$2y$10$BIHaJJXHOno1dhv065GoNOk94i2DspCTTMMqtU6A8qTp9gMiq4XDe', 'Risna Seftia Dewi', '', '2025-07-03 16:00:18', '2025-07-03 16:00:18', 0, NULL, 'aktif'),
(42, 'Razka', 'Razka Fadillah', 'razka.fs30@gmail.com', 'CJA-PB1625', '', '', '', 0, 1, '$2y$10$NpMdsqB.Zi8gLeWE4kR9D.VB6555QvgUYcReZFnT3Z4bNSHTcpZ8i', 'Razka Fadillah S', '', '2025-07-09 03:54:58', '2026-01-11 02:34:56', 0, NULL, 'aktif'),
(43, 'Lulu', 'Lulu Nafisah', 'lulunafisahm@gmail.com', 'CJA-PB1725', '', '', '', 0, 1, '$2y$10$yuQVGZd8lL1UzbtgepKFBuu52yuWAmB0czikg0m5s3SFAspspJBwe', 'Lulu Nafisah Muthmainnah', '', '2025-07-09 23:57:10', '2026-01-11 02:33:50', 0, NULL, 'aktif'),
(44, 'Sarah', 'Siti Sarah', 'sarahmunawiyah9@gmail.com', 'CJA-PB1825', '', '', '', 0, 1, '$2y$10$cFyXxZ87OrdOEq5l4/3h4O4icDPiKRlc9MnTFbpAkAqJ/77tsZ6Tq', 'Siti Sarah Muawiyah', '', '2025-07-28 13:49:03', '2026-01-11 02:35:52', 0, NULL, 'aktif'),
(46, 'Azhar', 'Muhammad Azhar', 'ajharkhairy@gmail.com', 'CJA-PB2025', '', '', '', 0, 1, '$2y$10$2OlW4nawPya8l1JkwbFc5uTMDR1Hphqw/3knMtaI.EyHmED.4n0Yu', 'Muhammad Azhar Khairy', '', '2025-09-09 15:08:08', '2026-01-11 02:34:07', 0, NULL, 'aktif'),
(47, 'Daffa', 'Daffa N', 'daffanugraha445@gmail.com', '', '', '', '', 1, 1, '$2y$10$iZNG8V8MX4V4b6h/5pnIt.0qUr5jzLtDYtTTH.fgxBP1nI2aSwxd.', 'Daffa N', '', '2025-10-31 01:45:46', '2025-10-31 01:45:46', 0, NULL, 'aktif'),
(48, 'Nazwa', 'Nazwa F', 'nf908104@gmail.com', 'CJA-PB00225', '', '', '', 1, 1, '$2y$10$.wZa/108GguBVPeH0sT7oOp4U4hRxF1BIc6qt9Mu8OCpqhNLxae/W', 'Nazwa F', '', '2025-10-31 01:50:14', '2026-01-19 12:24:20', 0, NULL, 'aktif'),
(49, 'Ica', 'Ica A', 'afilaatn16@gmail.com', 'xxx', '', '', '', 1, 1, '$2y$10$7vtPsDXXmSQ40OPA33Kj4uUxr3ZBO1oA3yeMMtAPhwrR627nwFecu', 'Ica A', '', '2025-11-07 17:15:19', '2025-11-07 17:15:19', 0, NULL, 'aktif'),
(50, 'Fitri', 'Fitriah Hikmah', 'fitriahhikmah551@gmail.com', 'CJA-PB00325', '', '', '', 0, 1, '$2y$10$pNgaFBgSo0.qiHuzpx2zJeUatUMPZiw7kAlDkZdrq7uk7P1pmHdky', 'Fitriah Hikmah', '', '2025-12-13 15:30:09', '2026-04-21 07:16:43', 0, NULL, 'aktif'),
(51, 'lilyadmin', 'lilyadmin', 'cjawilcafe@gmail.com', '001', '', '', '', 1, 1, '$2y$10$yjPWblLuMe4wj9V6jCOobugTUU2SwTUuZVGaASb/RRSF0VPrxHUQm', 'lilyadmin', '', '2026-01-16 18:55:28', '2026-01-16 18:56:26', 0, '../uploads/user/51_1768640186_ChatGPT Image Jun 6, 2025, 04_57_26 PM.png', 'aktif'),
(52, 'Mutiara ', 'Mutiara Aprilia', 'tiara.pinky04@gmail.com', 'CJA-PC00625', '', '', '', 1, 51, '$2y$10$71oYDFDOs.g0VSBX62MRoORVkG4TktF53lPCNRWbTETR9L6pZ/hj2', 'Mutiara Aprilia', '', '2026-01-18 16:28:18', '2026-01-18 16:28:18', 0, NULL, 'aktif'),
(53, 'backoffice', 'Minven-Backoffice', 'Minven@gmail.com', 'Min-B', '-', '-', '-', 1, 7, '$2y$10$I7YZ7ruLupwB8tiZGEaEWeEgyDbo31cE78QMNQpKmNnSNw4MPRmXu', 'Minven.id', '', '2026-05-31 12:41:18', '2026-05-31 12:41:18', 24, NULL, 'aktif');

-- --------------------------------------------------------

--
-- Table structure for table `user_activity`
--

CREATE TABLE `user_activity` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `last_activity` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `status` enum('online','away','offline') DEFAULT 'online'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_activity_log`
--

CREATE TABLE `user_activity_log` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `activity` varchar(255) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_roles`
--

CREATE TABLE `user_roles` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `role_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_roles`
--

INSERT INTO `user_roles` (`id`, `user_id`, `role_id`, `created_at`) VALUES
(52, 35, 21, '2025-06-28 05:59:13'),
(54, 36, 21, '2025-06-28 12:01:03'),
(55, 1, 1, '2025-06-28 14:04:58'),
(58, 40, 21, '2026-02-28 16:16:16'),
(59, 47, 21, '2026-02-28 16:17:13'),
(60, 50, 21, '2026-02-28 16:17:25'),
(61, 49, 21, '2026-02-28 16:17:33'),
(62, 51, 21, '2026-02-28 16:17:44'),
(63, 41, 21, '2026-02-28 16:17:55'),
(64, 48, 21, '2026-02-28 16:18:08'),
(65, 52, 21, '2026-02-28 16:18:16'),
(208, 53, 24, '2026-05-31 12:41:18'),
(242, 7, 1, '2026-05-31 18:16:29');

-- --------------------------------------------------------

--
-- Table structure for table `vendor_refund`
--

CREATE TABLE `vendor_refund` (
  `id` int(11) NOT NULL,
  `no_refund` varchar(30) NOT NULL,
  `tanggal` date NOT NULL,
  `purchase_order_id` int(11) DEFAULT NULL,
  `gudang_id` int(11) NOT NULL,
  `supplier_id` int(11) DEFAULT NULL,
  `keterangan` text DEFAULT NULL,
  `transaksi_stok_id` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `vendor_refunds`
--

CREATE TABLE `vendor_refunds` (
  `id` int(11) NOT NULL,
  `no_refund` varchar(30) NOT NULL,
  `tanggal` date NOT NULL,
  `gudang_id` int(11) NOT NULL,
  `jenis_refund` varchar(20) NOT NULL DEFAULT 'vendor',
  `po_id` int(11) DEFAULT NULL,
  `pihak` varchar(150) NOT NULL,
  `referensi` varchar(100) DEFAULT NULL,
  `keterangan` text DEFAULT NULL,
  `total_item` int(11) NOT NULL DEFAULT 0,
  `transaksi_stok_id` int(11) DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `vendor_refund_detail`
--

CREATE TABLE `vendor_refund_detail` (
  `id` int(11) NOT NULL,
  `vendor_refund_id` int(11) NOT NULL,
  `barang_id` int(11) NOT NULL,
  `detail_barang` varchar(255) NOT NULL DEFAULT '',
  `qty` int(11) NOT NULL DEFAULT 0,
  `satuan_label` varchar(50) DEFAULT NULL,
  `qty_input` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `vendor_refund_items`
--

CREATE TABLE `vendor_refund_items` (
  `id` int(11) NOT NULL,
  `refund_id` int(11) NOT NULL,
  `po_detail_id` int(11) DEFAULT NULL,
  `barang_id` int(11) NOT NULL,
  `detail_barang` varchar(255) NOT NULL DEFAULT '',
  `jumlah` int(11) NOT NULL DEFAULT 0,
  `alasan` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `barang`
--
ALTER TABLE `barang`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `kode_barang` (`kode_barang`),
  ADD KEY `kategori_id` (`kategori_id`),
  ADD KEY `satuan_id` (`satuan_id`),
  ADD KEY `supplier_id` (`supplier_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `calls`
--
ALTER TABLE `calls`
  ADD PRIMARY KEY (`id`),
  ADD KEY `caller_id` (`caller_id`),
  ADD KEY `receiver_id` (`receiver_id`),
  ADD KEY `status` (`status`),
  ADD KEY `created_at` (`created_at`);

--
-- Indexes for table `chat_messages`
--
ALTER TABLE `chat_messages`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `conversi_po_detail`
--
ALTER TABLE `conversi_po_detail`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_po` (`purchase_order_id`),
  ADD KEY `idx_dpo` (`detail_purchase_order_id`),
  ADD KEY `idx_satuan_asal` (`satuan_asal_id`),
  ADD KEY `idx_satuan_tujuan` (`satuan_tujuan_id`);

--
-- Indexes for table `detail_direct_purchase`
--
ALTER TABLE `detail_direct_purchase`
  ADD PRIMARY KEY (`id`),
  ADD KEY `direct_purchase_id` (`direct_purchase_id`),
  ADD KEY `barang_id` (`barang_id`),
  ADD KEY `idx_harga_satuan` (`harga_satuan`);

--
-- Indexes for table `detail_purchase_order`
--
ALTER TABLE `detail_purchase_order`
  ADD PRIMARY KEY (`id`),
  ADD KEY `purchase_order_id` (`purchase_order_id`),
  ADD KEY `barang_id` (`barang_id`);

--
-- Indexes for table `detail_transaksi_stock`
--
ALTER TABLE `detail_transaksi_stock`
  ADD PRIMARY KEY (`id`),
  ADD KEY `transaksi_stock_id` (`transaksi_stock_id`),
  ADD KEY `barang_id` (`barang_id`);

--
-- Indexes for table `detail_transaksi_stok`
--
ALTER TABLE `detail_transaksi_stok`
  ADD PRIMARY KEY (`id`),
  ADD KEY `transaksi_stok_id` (`transaksi_stok_id`),
  ADD KEY `barang_id` (`barang_id`),
  ADD KEY `idx_transaksi` (`transaksi_stok_id`),
  ADD KEY `idx_barang` (`barang_id`);

--
-- Indexes for table `detail_transaksi_transfer`
--
ALTER TABLE `detail_transaksi_transfer`
  ADD PRIMARY KEY (`id`),
  ADD KEY `transaksi_transfer_id` (`transaksi_transfer_id`),
  ADD KEY `barang_id` (`barang_id`);

--
-- Indexes for table `direct_purchase`
--
ALTER TABLE `direct_purchase`
  ADD PRIMARY KEY (`id`),
  ADD KEY `supplier_id` (`supplier_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `gudang`
--
ALTER TABLE `gudang`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `gudang_stok`
--
ALTER TABLE `gudang_stok`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_gudang_barang_detail` (`gudang_id`,`barang_id`,`detail_barang`),
  ADD KEY `barang_id` (`barang_id`),
  ADD KEY `modified_by` (`modified_by`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `updated_by` (`updated_by`);

--
-- Indexes for table `gudang_stok_history`
--
ALTER TABLE `gudang_stok_history`
  ADD KEY `idx_gsh_created_at` (`created_at`),
  ADD KEY `idx_gsh_gudang_barang_created_at` (`gudang_id`,`barang_id`,`created_at`,`id`);

--
-- Indexes for table `item_mapping`
--
ALTER TABLE `item_mapping`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_mapping` (`barang_id`,`lokasi_id`);

--
-- Indexes for table `kategori`
--
ALTER TABLE `kategori`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `kode_kategori` (`kode_kategori`),
  ADD KEY `parent_id` (`parent_id`);

--
-- Indexes for table `konversi_masukan`
--
ALTER TABLE `konversi_masukan`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_konversi_masukan_barang` (`barang_id`),
  ADD KEY `idx_konversi_masukan_tanggal` (`tanggal`),
  ADD KEY `idx_konversi_masukan_satuan_asal` (`satuan_asal_id`),
  ADD KEY `idx_konversi_masukan_satuan_tujuan` (`satuan_tujuan_id`);

--
-- Indexes for table `konversi_satuan`
--
ALTER TABLE `konversi_satuan`
  ADD PRIMARY KEY (`id`),
  ADD KEY `satuan_asal_id` (`satuan_asal_id`),
  ADD KEY `satuan_tujuan_id` (`satuan_tujuan_id`);

--
-- Indexes for table `konversi_satuan_barang`
--
ALTER TABLE `konversi_satuan_barang`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_konversi_barang` (`barang_id`,`satuan_asal_id`,`satuan_tujuan_id`),
  ADD KEY `idx_konversi_barang_barang` (`barang_id`),
  ADD KEY `idx_konversi_barang_satuan_asal` (`satuan_asal_id`),
  ADD KEY `idx_konversi_barang_satuan_tujuan` (`satuan_tujuan_id`);

--
-- Indexes for table `lokasi_mapping`
--
ALTER TABLE `lokasi_mapping`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `kode_lokasi` (`kode_lokasi`);

--
-- Indexes for table `manufacture`
--
ALTER TABLE `manufacture`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_mf_no` (`no_manufacture`),
  ADD KEY `idx_mf_tanggal` (`tanggal`),
  ADD KEY `idx_mf_gudang` (`gudang_id`),
  ADD KEY `idx_mf_produk` (`produk_id`);

--
-- Indexes for table `manufacture_bahan`
--
ALTER TABLE `manufacture_bahan`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_mf_bahan_mf` (`manufacture_id`),
  ADD KEY `idx_mf_bahan_barang` (`barang_id`);

--
-- Indexes for table `manufacture_bom`
--
ALTER TABLE `manufacture_bom`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_mfg_bom_barang_hasil` (`barang_hasil_id`),
  ADD KEY `idx_mfg_bom_aktif` (`aktif`);

--
-- Indexes for table `manufacture_bom_items`
--
ALTER TABLE `manufacture_bom_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_mfg_bom_item_bom` (`bom_id`),
  ADD KEY `idx_mfg_bom_item_barang` (`barang_id`);

--
-- Indexes for table `manufacture_orders`
--
ALTER TABLE `manufacture_orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_manufacture_no_produksi` (`no_produksi`),
  ADD KEY `idx_manufacture_tanggal` (`tanggal`),
  ADD KEY `idx_manufacture_gudang` (`gudang_id`),
  ADD KEY `idx_manufacture_barang_hasil` (`barang_hasil_id`);

--
-- Indexes for table `manufacture_order_materials`
--
ALTER TABLE `manufacture_order_materials`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_mfg_material_order` (`manufacture_order_id`),
  ADD KEY `idx_mfg_material_barang` (`barang_id`);

--
-- Indexes for table `menu_access`
--
ALTER TABLE `menu_access`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_role_menu` (`role_id`,`menu_name`),
  ADD KEY `role_id` (`role_id`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sender_id` (`sender_id`),
  ADD KEY `receiver_id` (`receiver_id`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_token` (`token`);

--
-- Indexes for table `pengeluaran`
--
ALTER TABLE `pengeluaran`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `po_detail`
--
ALTER TABLE `po_detail`
  ADD PRIMARY KEY (`id`),
  ADD KEY `po_id` (`po_id`),
  ADD KEY `barang_id` (`barang_id`);

--
-- Indexes for table `po_stock_split`
--
ALTER TABLE `po_stock_split`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_po` (`purchase_order_id`),
  ADD KEY `idx_dpo` (`detail_purchase_order_id`),
  ADD KEY `idx_split_barang` (`split_barang_id`);

--
-- Indexes for table `po_template`
--
ALTER TABLE `po_template`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_supplier_template_barang` (`supplier_id`,`nama_template`,`barang_id`);

--
-- Indexes for table `product`
--
ALTER TABLE `product`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_product_gudang_id` (`gudang_id`),
  ADD KEY `idx_product_created_by` (`created_by`);

--
-- Indexes for table `product_detail`
--
ALTER TABLE `product_detail`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_product_detail_product_id` (`product_id`),
  ADD KEY `idx_product_detail_barang_id` (`barang_id`);

--
-- Indexes for table `purchase_order`
--
ALTER TABLE `purchase_order`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `purchase_orders`
--
ALTER TABLE `purchase_orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `po_number` (`po_number`),
  ADD KEY `supplier_id` (`supplier_id`);

--
-- Indexes for table `riwayat_stok`
--
ALTER TABLE `riwayat_stok`
  ADD PRIMARY KEY (`id`),
  ADD KEY `gudang_id` (`gudang_id`),
  ADD KEY `barang_id` (`barang_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `satuan`
--
ALTER TABLE `satuan`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `kode_satuan` (`kode_satuan`);

--
-- Indexes for table `setup_barcode_config`
--
ALTER TABLE `setup_barcode_config`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `setup_file_template`
--
ALTER TABLE `setup_file_template`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `setup_reset_stok`
--
ALTER TABLE `setup_reset_stok`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_gudang` (`gudang_id`);

--
-- Indexes for table `setup_whatsapp`
--
ALTER TABLE `setup_whatsapp`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `stok`
--
ALTER TABLE `stok`
  ADD PRIMARY KEY (`id`),
  ADD KEY `barang_id` (`barang_id`),
  ADD KEY `gudang_id` (`gudang_id`);

--
-- Indexes for table `stok_barang`
--
ALTER TABLE `stok_barang`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_stok_per_item_gudang` (`gudang_id`,`barang_id`,`detail_barang`),
  ADD KEY `barang_id` (`barang_id`);

--
-- Indexes for table `stok_gudang`
--
ALTER TABLE `stok_gudang`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `gudang_barang` (`gudang_id`,`barang_id`),
  ADD KEY `barang_id` (`barang_id`);

--
-- Indexes for table `stok_history`
--
ALTER TABLE `stok_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `barang_id` (`barang_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `stok_keluar`
--
ALTER TABLE `stok_keluar`
  ADD PRIMARY KEY (`id`),
  ADD KEY `barang_id` (`barang_id`),
  ADD KEY `gudang_id` (`gudang_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `stok_masuk`
--
ALTER TABLE `stok_masuk`
  ADD PRIMARY KEY (`id`),
  ADD KEY `barang_id` (`barang_id`),
  ADD KEY `gudang_id` (`gudang_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `stok_terpakai`
--
ALTER TABLE `stok_terpakai`
  ADD PRIMARY KEY (`id`),
  ADD KEY `barang_id` (`barang_id`),
  ADD KEY `gudang_id` (`gudang_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `supplier`
--
ALTER TABLE `supplier`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `kode_supplier` (`kode_supplier`);

--
-- Indexes for table `supplier_barang`
--
ALTER TABLE `supplier_barang`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `supplier_id` (`supplier_id`,`barang_id`),
  ADD KEY `barang_id` (`barang_id`);

--
-- Indexes for table `surat_jalan`
--
ALTER TABLE `surat_jalan`
  ADD PRIMARY KEY (`id`),
  ADD KEY `po_id` (`po_id`);

--
-- Indexes for table `surat_jalan_items`
--
ALTER TABLE `surat_jalan_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `surat_jalan_id` (`surat_jalan_id`),
  ADD KEY `barang_id` (`barang_id`),
  ADD KEY `satuan_id` (`satuan_id`);

--
-- Indexes for table `toko`
--
ALTER TABLE `toko`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `transaksi_stock`
--
ALTER TABLE `transaksi_stock`
  ADD PRIMARY KEY (`id`),
  ADD KEY `gudang_id` (`gudang_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `transaksi_stok`
--
ALTER TABLE `transaksi_stok`
  ADD PRIMARY KEY (`id`),
  ADD KEY `gudang_id` (`gudang_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_jenis` (`jenis_transaksi`),
  ADD KEY `idx_tanggal` (`tanggal`);

--
-- Indexes for table `transaksi_transfer`
--
ALTER TABLE `transaksi_transfer`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `no_transaksi` (`no_transaksi`),
  ADD KEY `gudang_asal_id` (`gudang_asal_id`),
  ADD KEY `gudang_tujuan_id` (`gudang_tujuan_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `transfer_gudang`
--
ALTER TABLE `transfer_gudang`
  ADD PRIMARY KEY (`id`),
  ADD KEY `po_id` (`po_id`),
  ADD KEY `gudang_id` (`gudang_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `username_2` (`username`),
  ADD KEY `idx_username` (`username`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_is_active` (`is_active`),
  ADD KEY `idx_kode_pegawai` (`kode_pegawai`);

--
-- Indexes for table `user_activity`
--
ALTER TABLE `user_activity`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `user_activity_log`
--
ALTER TABLE `user_activity_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `created_at` (`created_at`),
  ADD KEY `activity` (`activity`);

--
-- Indexes for table `user_roles`
--
ALTER TABLE `user_roles`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `role_id` (`role_id`);

--
-- Indexes for table `vendor_refund`
--
ALTER TABLE `vendor_refund`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_vendor_refund_no` (`no_refund`),
  ADD KEY `idx_vendor_refund_tanggal` (`tanggal`),
  ADD KEY `idx_vendor_refund_gudang` (`gudang_id`),
  ADD KEY `idx_vendor_refund_supplier` (`supplier_id`),
  ADD KEY `idx_vendor_refund_trx` (`transaksi_stok_id`),
  ADD KEY `idx_vendor_refund_po` (`purchase_order_id`);

--
-- Indexes for table `vendor_refunds`
--
ALTER TABLE `vendor_refunds`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_vendor_refunds_no_refund` (`no_refund`),
  ADD KEY `idx_vendor_refunds_tanggal` (`tanggal`),
  ADD KEY `idx_vendor_refunds_gudang` (`gudang_id`),
  ADD KEY `idx_vendor_refunds_jenis` (`jenis_refund`),
  ADD KEY `idx_vendor_refunds_transaksi_stok` (`transaksi_stok_id`);

--
-- Indexes for table `vendor_refund_detail`
--
ALTER TABLE `vendor_refund_detail`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_vendor_refund_detail_refund` (`vendor_refund_id`),
  ADD KEY `idx_vendor_refund_detail_barang` (`barang_id`);

--
-- Indexes for table `vendor_refund_items`
--
ALTER TABLE `vendor_refund_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_vendor_refund_items_refund` (`refund_id`),
  ADD KEY `idx_vendor_refund_items_barang` (`barang_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `barang`
--
ALTER TABLE `barang`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=721;

--
-- AUTO_INCREMENT for table `calls`
--
ALTER TABLE `calls`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `chat_messages`
--
ALTER TABLE `chat_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `conversi_po_detail`
--
ALTER TABLE `conversi_po_detail`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `detail_direct_purchase`
--
ALTER TABLE `detail_direct_purchase`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=188;

--
-- AUTO_INCREMENT for table `detail_purchase_order`
--
ALTER TABLE `detail_purchase_order`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `detail_transaksi_stock`
--
ALTER TABLE `detail_transaksi_stock`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `detail_transaksi_stok`
--
ALTER TABLE `detail_transaksi_stok`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `detail_transaksi_transfer`
--
ALTER TABLE `detail_transaksi_transfer`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `direct_purchase`
--
ALTER TABLE `direct_purchase`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=172;

--
-- AUTO_INCREMENT for table `gudang`
--
ALTER TABLE `gudang`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT for table `gudang_stok`
--
ALTER TABLE `gudang_stok`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `item_mapping`
--
ALTER TABLE `item_mapping`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=167;

--
-- AUTO_INCREMENT for table `kategori`
--
ALTER TABLE `kategori`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=135;

--
-- AUTO_INCREMENT for table `konversi_masukan`
--
ALTER TABLE `konversi_masukan`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `konversi_satuan`
--
ALTER TABLE `konversi_satuan`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `konversi_satuan_barang`
--
ALTER TABLE `konversi_satuan_barang`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=49;

--
-- AUTO_INCREMENT for table `lokasi_mapping`
--
ALTER TABLE `lokasi_mapping`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `manufacture`
--
ALTER TABLE `manufacture`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `manufacture_bahan`
--
ALTER TABLE `manufacture_bahan`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `manufacture_bom`
--
ALTER TABLE `manufacture_bom`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `manufacture_bom_items`
--
ALTER TABLE `manufacture_bom_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `manufacture_orders`
--
ALTER TABLE `manufacture_orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `manufacture_order_materials`
--
ALTER TABLE `manufacture_order_materials`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `menu_access`
--
ALTER TABLE `menu_access`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5653;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pengeluaran`
--
ALTER TABLE `pengeluaran`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `po_detail`
--
ALTER TABLE `po_detail`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `po_stock_split`
--
ALTER TABLE `po_stock_split`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `po_template`
--
ALTER TABLE `po_template`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `product`
--
ALTER TABLE `product`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `product_detail`
--
ALTER TABLE `product_detail`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `purchase_order`
--
ALTER TABLE `purchase_order`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `purchase_orders`
--
ALTER TABLE `purchase_orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `riwayat_stok`
--
ALTER TABLE `riwayat_stok`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `satuan`
--
ALTER TABLE `satuan`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=93;

--
-- AUTO_INCREMENT for table `setup_file_template`
--
ALTER TABLE `setup_file_template`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `setup_reset_stok`
--
ALTER TABLE `setup_reset_stok`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `setup_whatsapp`
--
ALTER TABLE `setup_whatsapp`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `stok`
--
ALTER TABLE `stok`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `stok_barang`
--
ALTER TABLE `stok_barang`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `stok_gudang`
--
ALTER TABLE `stok_gudang`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `stok_history`
--
ALTER TABLE `stok_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `stok_keluar`
--
ALTER TABLE `stok_keluar`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `stok_masuk`
--
ALTER TABLE `stok_masuk`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `stok_terpakai`
--
ALTER TABLE `stok_terpakai`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `supplier`
--
ALTER TABLE `supplier`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12491;

--
-- AUTO_INCREMENT for table `supplier_barang`
--
ALTER TABLE `supplier_barang`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `surat_jalan`
--
ALTER TABLE `surat_jalan`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `surat_jalan_items`
--
ALTER TABLE `surat_jalan_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `toko`
--
ALTER TABLE `toko`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `transaksi_stock`
--
ALTER TABLE `transaksi_stock`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=63;

--
-- AUTO_INCREMENT for table `transaksi_stok`
--
ALTER TABLE `transaksi_stok`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `transaksi_transfer`
--
ALTER TABLE `transaksi_transfer`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `transfer_gudang`
--
ALTER TABLE `transfer_gudang`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=54;

--
-- AUTO_INCREMENT for table `user_activity`
--
ALTER TABLE `user_activity`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_activity_log`
--
ALTER TABLE `user_activity_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_roles`
--
ALTER TABLE `user_roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=243;

--
-- AUTO_INCREMENT for table `vendor_refund`
--
ALTER TABLE `vendor_refund`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `vendor_refunds`
--
ALTER TABLE `vendor_refunds`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `vendor_refund_detail`
--
ALTER TABLE `vendor_refund_detail`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `vendor_refund_items`
--
ALTER TABLE `vendor_refund_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `barang`
--
ALTER TABLE `barang`
  ADD CONSTRAINT `barang_ibfk_1` FOREIGN KEY (`kategori_id`) REFERENCES `kategori` (`id`),
  ADD CONSTRAINT `barang_ibfk_2` FOREIGN KEY (`satuan_id`) REFERENCES `satuan` (`id`),
  ADD CONSTRAINT `barang_ibfk_3` FOREIGN KEY (`supplier_id`) REFERENCES `supplier` (`id`),
  ADD CONSTRAINT `barang_ibfk_4` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `detail_direct_purchase`
--
ALTER TABLE `detail_direct_purchase`
  ADD CONSTRAINT `detail_direct_purchase_ibfk_1` FOREIGN KEY (`direct_purchase_id`) REFERENCES `direct_purchase` (`id`),
  ADD CONSTRAINT `detail_direct_purchase_ibfk_2` FOREIGN KEY (`barang_id`) REFERENCES `barang` (`id`);

--
-- Constraints for table `detail_transaksi_stock`
--
ALTER TABLE `detail_transaksi_stock`
  ADD CONSTRAINT `detail_transaksi_stock_ibfk_1` FOREIGN KEY (`transaksi_stock_id`) REFERENCES `transaksi_stock` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `detail_transaksi_stock_ibfk_2` FOREIGN KEY (`barang_id`) REFERENCES `barang` (`id`);

--
-- Constraints for table `manufacture_bom_items`
--
ALTER TABLE `manufacture_bom_items`
  ADD CONSTRAINT `fk_mfg_bom_item_header` FOREIGN KEY (`bom_id`) REFERENCES `manufacture_bom` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `manufacture_order_materials`
--
ALTER TABLE `manufacture_order_materials`
  ADD CONSTRAINT `fk_mfg_material_order` FOREIGN KEY (`manufacture_order_id`) REFERENCES `manufacture_orders` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `vendor_refund_items`
--
ALTER TABLE `vendor_refund_items`
  ADD CONSTRAINT `fk_vendor_refund_items_refund` FOREIGN KEY (`refund_id`) REFERENCES `vendor_refunds` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
