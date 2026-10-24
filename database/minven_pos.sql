-- =====================================================
-- MINVEN POS SYSTEM - UNIFIED DATABASE SCHEMA
-- =====================================================
-- Database: minven_pos
-- Version: 2.0 (Merged with Extended POS Features)
-- Includes: Sales, Customers, Payments, Inventory, Orders, Promo, Audit Log
-- =====================================================

CREATE DATABASE IF NOT EXISTS `minven_pos`
CHARACTER SET utf8mb4
COLLATE utf8mb4_general_ci;

USE `minven_pos`;

-- =====================================================
-- 1. LEGACY: POS SALES (Existing)
-- =====================================================
CREATE TABLE IF NOT EXISTS `pos_sales` (
  `id` int NOT NULL AUTO_INCREMENT,
  `sale_number` varchar(30) NOT NULL,
  `sale_date` datetime NOT NULL,
  `gudang_id` int NOT NULL,
  `cashier_user_id` int NOT NULL,
  `cashier_username` varchar(100) NOT NULL,
  `customer_name` varchar(120) DEFAULT NULL,
  `payment_method` enum('cash','transfer','qris','debit','ewallet') NOT NULL DEFAULT 'cash',
  `payment_status` enum('paid','partial') NOT NULL DEFAULT 'paid',
  `subtotal` decimal(15,2) NOT NULL DEFAULT '0.00',
  `discount_amount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `tax_amount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `grand_total` decimal(15,2) NOT NULL DEFAULT '0.00',
  `amount_paid` decimal(15,2) NOT NULL DEFAULT '0.00',
  `change_amount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `notes` text,
  `inventory_transaction_id` int DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_sale_number` (`sale_number`),
  KEY `idx_sale_date` (`sale_date`),
  KEY `idx_gudang_id` (`gudang_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =====================================================
-- 2. LEGACY: POS SALE ITEMS (Existing)
-- =====================================================
CREATE TABLE IF NOT EXISTS `pos_sale_items` (
  `id` int NOT NULL AUTO_INCREMENT,
  `sale_id` int NOT NULL,
  `barang_id` int NOT NULL,
  `kode_barang` varchar(50) NOT NULL,
  `nama_barang` varchar(150) NOT NULL,
  `satuan` varchar(50) DEFAULT NULL,
  `qty` int NOT NULL,
  `unit_price` decimal(15,2) NOT NULL DEFAULT '0.00',
  `line_total` decimal(15,2) NOT NULL DEFAULT '0.00',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_sale_id` (`sale_id`),
  KEY `idx_barang_id` (`barang_id`),
  CONSTRAINT `fk_pos_sale_items_sale` FOREIGN KEY (`sale_id`) REFERENCES `pos_sales` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =====================================================
-- 3. OUTLETS (Multi-outlet Support)
-- =====================================================
CREATE TABLE IF NOT EXISTS `outlets` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `outlet_name` VARCHAR(255) NOT NULL,
  `location` TEXT NOT NULL,
  `contact_person` VARCHAR(255) NULL,
  `phone` VARCHAR(20) NULL,
  `email` VARCHAR(255) NULL,
  `is_active` BOOLEAN DEFAULT TRUE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_outlet_name` (`outlet_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Insert default outlet (reference dari gudang)
INSERT IGNORE INTO `outlets` (`id`, `outlet_name`, `location`, `is_active`) VALUES
(1, 'Outlet Central', 'Central Store', 1),
(99, 'Outlet Antapani', 'Antapani Store', 1);

-- =====================================================
-- 4. TRANSACTIONS (Enhanced)
-- =====================================================
CREATE TABLE IF NOT EXISTS `pos_transactions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `transaction_no` VARCHAR(50) UNIQUE NOT NULL,
  `outlet_id` INT NOT NULL DEFAULT 1,
  `cashier_id` INT NOT NULL,
  `customer_id` INT NULL,
  `order_type` ENUM('dine_in', 'takeaway', 'delivery') DEFAULT 'dine_in',
  `table_number` VARCHAR(20) NULL,
  `delivery_address` TEXT NULL,
  `subtotal` DECIMAL(15, 2) NOT NULL,
  `discount_amount` DECIMAL(15, 2) DEFAULT 0,
  `voucher_id` INT NULL,
  `tax_amount` DECIMAL(15, 2) DEFAULT 0,
  `total_amount` DECIMAL(15, 2) NOT NULL,
  `payment_status` ENUM('pending', 'paid', 'partial', 'refunded') DEFAULT 'pending',
  `transaction_status` ENUM('active', 'held', 'completed', 'cancelled') DEFAULT 'active',
  `notes` TEXT NULL,
  `shift_id` INT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `is_deleted` BOOLEAN DEFAULT FALSE,
  FOREIGN KEY (`outlet_id`) REFERENCES `outlets`(`id`),
  INDEX `idx_transaction_no` (`transaction_no`),
  INDEX `idx_cashier_id` (`cashier_id`),
  INDEX `idx_created_at` (`created_at`),
  INDEX `idx_transaction_status` (`transaction_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =====================================================
-- 5. TRANSACTION ITEMS
-- =====================================================
CREATE TABLE IF NOT EXISTS `pos_transaction_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `transaction_id` INT NOT NULL,
  `barang_id` INT NOT NULL,
  `quantity` INT NOT NULL,
  `unit_price` DECIMAL(15, 2) NOT NULL,
  `discount_amount` DECIMAL(15, 2) DEFAULT 0,
  `subtotal` DECIMAL(15, 2) NOT NULL,
  `notes` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`transaction_id`) REFERENCES `pos_transactions`(`id`) ON DELETE CASCADE,
  INDEX `idx_transaction_id` (`transaction_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =====================================================
-- 6. PAYMENTS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS `pos_payments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `transaction_id` INT NOT NULL,
  `payment_method` ENUM('cash', 'qris', 'debit', 'credit', 'ewallet', 'transfer') NOT NULL,
  `amount` DECIMAL(15, 2) NOT NULL,
  `reference_no` VARCHAR(100) NULL,
  `payment_status` ENUM('pending', 'success', 'failed') DEFAULT 'pending',
  `payment_time` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `notes` TEXT NULL,
  FOREIGN KEY (`transaction_id`) REFERENCES `pos_transactions`(`id`) ON DELETE CASCADE,
  INDEX `idx_transaction_id` (`transaction_id`),
  INDEX `idx_payment_method` (`payment_method`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =====================================================
-- 7. CUSTOMERS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS `pos_customers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `outlet_id` INT NOT NULL DEFAULT 1,
  `name` VARCHAR(255) NOT NULL,
  `phone` VARCHAR(20) UNIQUE NOT NULL,
  `email` VARCHAR(255) NULL,
  `address` TEXT NULL,
  `member_type` ENUM('regular', 'member', 'vip') DEFAULT 'regular',
  `loyalty_points` INT DEFAULT 0,
  `total_purchases` DECIMAL(15, 2) DEFAULT 0,
  `last_purchase_date` DATETIME NULL,
  `is_active` BOOLEAN DEFAULT TRUE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`outlet_id`) REFERENCES `outlets`(`id`),
  INDEX `idx_phone` (`phone`),
  INDEX `idx_member_type` (`member_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =====================================================
-- 8. LOYALTY POINTS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS `pos_loyalty_points` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `customer_id` INT NOT NULL,
  `transaction_id` INT NULL,
  `points` INT NOT NULL,
  `point_type` ENUM('earn', 'redeem', 'bonus', 'expire') DEFAULT 'earn',
  `notes` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`customer_id`) REFERENCES `pos_customers`(`id`),
  FOREIGN KEY (`transaction_id`) REFERENCES `pos_transactions`(`id`) ON DELETE SET NULL,
  INDEX `idx_customer_id` (`customer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =====================================================
-- 9. VOUCHERS & PROMOTIONS
-- =====================================================
CREATE TABLE IF NOT EXISTS `pos_vouchers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `outlet_id` INT NOT NULL DEFAULT 1,
  `voucher_code` VARCHAR(50) UNIQUE NOT NULL,
  `discount_type` ENUM('percentage', 'fixed', 'bogo') DEFAULT 'fixed',
  `discount_value` DECIMAL(15, 2) NOT NULL,
  `max_discount` DECIMAL(15, 2) NULL,
  `min_purchase` DECIMAL(15, 2) DEFAULT 0,
  `start_date` DATE NOT NULL,
  `end_date` DATE NOT NULL,
  `quota` INT DEFAULT NULL,
  `used_count` INT DEFAULT 0,
  `product_id` INT NULL,
  `is_active` BOOLEAN DEFAULT TRUE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`outlet_id`) REFERENCES `outlets`(`id`),
  INDEX `idx_voucher_code` (`voucher_code`),
  INDEX `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =====================================================
-- 10. INVENTORY & STOCK RESERVATION
-- =====================================================
CREATE TABLE IF NOT EXISTS `pos_stock_reservation` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `barang_id` INT NOT NULL,
  `outlet_id` INT NOT NULL DEFAULT 1,
  `quantity_reserved` INT NOT NULL,
  `reason` VARCHAR(255) NOT NULL,
  `reservation_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `release_date` DATETIME NULL,
  `notes` TEXT NULL,
  FOREIGN KEY (`outlet_id`) REFERENCES `outlets`(`id`),
  INDEX `idx_barang_id` (`barang_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =====================================================
-- 11. PRODUCT RECIPE / BOM (Bill of Materials)
-- =====================================================
CREATE TABLE IF NOT EXISTS `pos_recipes` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `finished_product_id` INT NOT NULL,
  `ingredient_id` INT NOT NULL,
  `quantity_needed` DECIMAL(10, 2) NOT NULL,
  `notes` TEXT NULL,
  `is_active` BOOLEAN DEFAULT TRUE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_recipe` (`finished_product_id`, `ingredient_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =====================================================
-- 12. ORDERS (For Dine-in/Takeaway/Delivery)
-- =====================================================
CREATE TABLE IF NOT EXISTS `pos_orders` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `order_no` VARCHAR(50) UNIQUE NOT NULL,
  `outlet_id` INT NOT NULL DEFAULT 1,
  `transaction_id` INT NULL,
  `order_type` ENUM('dine_in', 'takeaway', 'delivery') NOT NULL,
  `table_number` VARCHAR(20) NULL,
  `delivery_address` TEXT NULL,
  `delivery_phone` VARCHAR(20) NULL,
  `order_status` ENUM('pending', 'preparing', 'ready', 'served', 'cancelled') DEFAULT 'pending',
  `priority` ENUM('normal', 'urgent') DEFAULT 'normal',
  `total_items` INT NOT NULL,
  `notes` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `served_at` DATETIME NULL,
  FOREIGN KEY (`outlet_id`) REFERENCES `outlets`(`id`),
  FOREIGN KEY (`transaction_id`) REFERENCES `pos_transactions`(`id`) ON DELETE SET NULL,
  INDEX `idx_order_no` (`order_no`),
  INDEX `idx_order_status` (`order_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =====================================================
-- 13. ORDER ITEMS
-- =====================================================
CREATE TABLE IF NOT EXISTS `pos_order_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `order_id` INT NOT NULL,
  `barang_id` INT NOT NULL,
  `quantity` INT NOT NULL,
  `notes` TEXT NULL,
  `item_status` ENUM('pending', 'preparing', 'ready', 'served') DEFAULT 'pending',
  `prepared_at` DATETIME NULL,
  FOREIGN KEY (`order_id`) REFERENCES `pos_orders`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =====================================================
-- 14. RETURNS & REFUNDS
-- =====================================================
CREATE TABLE IF NOT EXISTS `pos_returns` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `return_no` VARCHAR(50) UNIQUE NOT NULL,
  `transaction_id` INT NOT NULL,
  `outlet_id` INT NOT NULL DEFAULT 1,
  `return_reason` VARCHAR(255) NOT NULL,
  `return_status` ENUM('pending', 'approved', 'rejected', 'completed') DEFAULT 'pending',
  `total_refund` DECIMAL(15, 2) NOT NULL,
  `refund_method` ENUM('cash', 'original_payment', 'credit') DEFAULT 'cash',
  `notes` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `completed_at` DATETIME NULL,
  FOREIGN KEY (`transaction_id`) REFERENCES `pos_transactions`(`id`),
  FOREIGN KEY (`outlet_id`) REFERENCES `outlets`(`id`),
  INDEX `idx_return_no` (`return_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =====================================================
-- 15. RETURN ITEMS
-- =====================================================
CREATE TABLE IF NOT EXISTS `pos_return_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `return_id` INT NOT NULL,
  `barang_id` INT NOT NULL,
  `quantity` INT NOT NULL,
  `unit_price` DECIMAL(15, 2) NOT NULL,
  `subtotal` DECIMAL(15, 2) NOT NULL,
  FOREIGN KEY (`return_id`) REFERENCES `pos_returns`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =====================================================
-- 16. SHIFT MANAGEMENT
-- =====================================================
CREATE TABLE IF NOT EXISTS `pos_shifts` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `outlet_id` INT NOT NULL DEFAULT 1,
  `cashier_id` INT NOT NULL,
  `shift_date` DATE NOT NULL,
  `opening_time` DATETIME NOT NULL,
  `closing_time` DATETIME NULL,
  `opening_cash` DECIMAL(15, 2) NOT NULL,
  `closing_cash` DECIMAL(15, 2) NULL,
  `expected_cash` DECIMAL(15, 2) NULL,
  `cash_difference` DECIMAL(15, 2) NULL,
  `total_transactions` INT DEFAULT 0,
  `total_sales` DECIMAL(15, 2) DEFAULT 0,
  `notes` TEXT NULL,
  `shift_status` ENUM('open', 'closed') DEFAULT 'open',
  FOREIGN KEY (`outlet_id`) REFERENCES `outlets`(`id`),
  INDEX `idx_shift_date` (`shift_date`),
  INDEX `idx_cashier_id` (`cashier_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =====================================================
-- 17. CASH DRAWER MANAGEMENT
-- =====================================================
CREATE TABLE IF NOT EXISTS `pos_cash_drawer` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `shift_id` INT NOT NULL,
  `transaction_type` ENUM('cash_in', 'cash_out', 'payment_received') DEFAULT 'payment_received',
  `amount` DECIMAL(15, 2) NOT NULL,
  `description` TEXT NOT NULL,
  `recorded_by` INT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`shift_id`) REFERENCES `pos_shifts`(`id`),
  INDEX `idx_shift_id` (`shift_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =====================================================
-- 18. AUDIT LOG
-- =====================================================
CREATE TABLE IF NOT EXISTS `pos_audit_log` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `action_type` VARCHAR(100) NOT NULL,
  `table_name` VARCHAR(100) NULL,
  `record_id` INT NULL,
  `action_details` JSON NULL,
  `old_values` JSON NULL,
  `new_values` JSON NULL,
  `ip_address` VARCHAR(50) NULL,
  `user_agent` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_user_id` (`user_id`),
  INDEX `idx_created_at` (`created_at`),
  INDEX `idx_action_type` (`action_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =====================================================
-- 19. OFFLINE TRANSACTIONS QUEUE
-- =====================================================
CREATE TABLE IF NOT EXISTS `pos_offline_queue` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `outlet_id` INT NOT NULL DEFAULT 1,
  `transaction_data` JSON NOT NULL,
  `sync_status` ENUM('pending', 'synced', 'failed') DEFAULT 'pending',
  `sync_attempt` INT DEFAULT 0,
  `sync_error` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `synced_at` DATETIME NULL,
  FOREIGN KEY (`outlet_id`) REFERENCES `outlets`(`id`),
  INDEX `idx_sync_status` (`sync_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =====================================================
-- NOTES:
-- =====================================================
-- 1. Database: minven_pos (untuk transaksi POS)
-- 2. Foreign keys ke users & barang di database minven_pro (tidak di-define di sini, tapi di-handle di aplikasi)
-- 3. Role `Kasir` dan user login `kasir`/`minven25` disiapkan otomatis saat aplikasi kasir dibuka
-- 4. Pengurangan stok tetap menulis ke database utama minven_pro
-- 5. Multi-outlet support sudah built-in untuk future expansion
