-- =====================================================
-- POS SYSTEM DATABASE SCHEMA - MINVEN PRO
-- =====================================================
-- File ini berisi skema lengkap untuk sistem kasir modern
-- Includes: Transactions, Customers, Payments, Inventory, Promo, Shift Management, Audit Log
-- =====================================================

-- =====================================================
-- 1. TRANSACTIONS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS `pos_transactions` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `transaction_no` VARCHAR(50) UNIQUE NOT NULL,
  `outlet_id` INT NOT NULL,
  `cashier_id` INT NOT NULL,
  `customer_id` INT NULL,
  `order_type` ENUM('dine_in', 'takeaway', 'delivery') DEFAULT 'dine_in',
  `table_number` VARCHAR(20) NULL,
  `delivery_address` TEXT NULL,
  `subtotal` DECIMAL(12, 2) NOT NULL,
  `discount_amount` DECIMAL(12, 2) DEFAULT 0,
  `voucher_id` INT NULL,
  `tax_amount` DECIMAL(12, 2) DEFAULT 0,
  `total_amount` DECIMAL(12, 2) NOT NULL,
  `payment_status` ENUM('pending', 'paid', 'partial', 'refunded') DEFAULT 'pending',
  `transaction_status` ENUM('active', 'held', 'completed', 'cancelled') DEFAULT 'active',
  `notes` TEXT NULL,
  `shift_id` INT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `is_deleted` BOOLEAN DEFAULT FALSE,
  FOREIGN KEY (`outlet_id`) REFERENCES `outlets`(`id`),
  FOREIGN KEY (`cashier_id`) REFERENCES `users`(`id`),
  FOREIGN KEY (`customer_id`) REFERENCES `pos_customers`(`id`),
  FOREIGN KEY (`voucher_id`) REFERENCES `pos_vouchers`(`id`),
  FOREIGN KEY (`shift_id`) REFERENCES `pos_shifts`(`id`),
  INDEX `idx_transaction_no` (`transaction_no`),
  INDEX `idx_cashier_id` (`cashier_id`),
  INDEX `idx_created_at` (`created_at`),
  INDEX `idx_transaction_status` (`transaction_status`)
);

-- =====================================================
-- 2. TRANSACTION ITEMS
-- =====================================================
CREATE TABLE IF NOT EXISTS `pos_transaction_items` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `transaction_id` INT NOT NULL,
  `barang_id` INT NOT NULL,
  `quantity` INT NOT NULL,
  `unit_price` DECIMAL(12, 2) NOT NULL,
  `discount_amount` DECIMAL(12, 2) DEFAULT 0,
  `subtotal` DECIMAL(12, 2) NOT NULL,
  `notes` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`transaction_id`) REFERENCES `pos_transactions`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`barang_id`) REFERENCES `barang`(`id`),
  INDEX `idx_transaction_id` (`transaction_id`)
);

-- =====================================================
-- 3. PAYMENTS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS `pos_payments` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `transaction_id` INT NOT NULL,
  `payment_method` ENUM('cash', 'qris', 'debit', 'credit', 'ewallet', 'transfer') NOT NULL,
  `amount` DECIMAL(12, 2) NOT NULL,
  `reference_no` VARCHAR(100) NULL,
  `payment_status` ENUM('pending', 'success', 'failed') DEFAULT 'pending',
  `payment_time` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `notes` TEXT NULL,
  FOREIGN KEY (`transaction_id`) REFERENCES `pos_transactions`(`id`) ON DELETE CASCADE,
  INDEX `idx_transaction_id` (`transaction_id`),
  INDEX `idx_payment_method` (`payment_method`)
);

-- =====================================================
-- 4. CUSTOMERS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS `pos_customers` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `outlet_id` INT NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `phone` VARCHAR(20) UNIQUE NOT NULL,
  `email` VARCHAR(255) NULL,
  `address` TEXT NULL,
  `member_type` ENUM('regular', 'member', 'vip') DEFAULT 'regular',
  `loyalty_points` INT DEFAULT 0,
  `total_purchases` DECIMAL(12, 2) DEFAULT 0,
  `last_purchase_date` DATETIME NULL,
  `is_active` BOOLEAN DEFAULT TRUE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`outlet_id`) REFERENCES `outlets`(`id`),
  INDEX `idx_phone` (`phone`),
  INDEX `idx_member_type` (`member_type`)
);

-- =====================================================
-- 5. LOYALTY POINTS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS `pos_loyalty_points` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `customer_id` INT NOT NULL,
  `transaction_id` INT NULL,
  `points` INT NOT NULL,
  `point_type` ENUM('earn', 'redeem', 'bonus', 'expire') DEFAULT 'earn',
  `notes` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`customer_id`) REFERENCES `pos_customers`(`id`),
  FOREIGN KEY (`transaction_id`) REFERENCES `pos_transactions`(`id`) ON DELETE SET NULL,
  INDEX `idx_customer_id` (`customer_id`)
);

-- =====================================================
-- 6. VOUCHERS & PROMOTIONS
-- =====================================================
CREATE TABLE IF NOT EXISTS `pos_vouchers` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `outlet_id` INT NOT NULL,
  `voucher_code` VARCHAR(50) UNIQUE NOT NULL,
  `discount_type` ENUM('percentage', 'fixed', 'bogo') DEFAULT 'fixed',
  `discount_value` DECIMAL(12, 2) NOT NULL,
  `max_discount` DECIMAL(12, 2) NULL,
  `min_purchase` DECIMAL(12, 2) DEFAULT 0,
  `start_date` DATE NOT NULL,
  `end_date` DATE NOT NULL,
  `quota` INT DEFAULT NULL,
  `used_count` INT DEFAULT 0,
  `product_id` INT NULL,
  `is_active` BOOLEAN DEFAULT TRUE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`outlet_id`) REFERENCES `outlets`(`id`),
  FOREIGN KEY (`product_id`) REFERENCES `barang`(`id`),
  INDEX `idx_voucher_code` (`voucher_code`),
  INDEX `idx_is_active` (`is_active`)
);

-- =====================================================
-- 7. INVENTORY & STOCK RESERVATION
-- =====================================================
CREATE TABLE IF NOT EXISTS `pos_stock_reservation` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `barang_id` INT NOT NULL,
  `outlet_id` INT NOT NULL,
  `quantity_reserved` INT NOT NULL,
  `reason` VARCHAR(255) NOT NULL,
  `reservation_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `release_date` DATETIME NULL,
  `notes` TEXT NULL,
  FOREIGN KEY (`barang_id`) REFERENCES `barang`(`id`),
  FOREIGN KEY (`outlet_id`) REFERENCES `outlets`(`id`),
  INDEX `idx_barang_id` (`barang_id`)
);

-- =====================================================
-- 8. PRODUCT RECIPE / BOM (Bill of Materials)
-- =====================================================
CREATE TABLE IF NOT EXISTS `pos_recipes` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `finished_product_id` INT NOT NULL,
  `ingredient_id` INT NOT NULL,
  `quantity_needed` DECIMAL(10, 2) NOT NULL,
  `unit_id` INT NOT NULL,
  `notes` TEXT NULL,
  `is_active` BOOLEAN DEFAULT TRUE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`finished_product_id`) REFERENCES `barang`(`id`),
  FOREIGN KEY (`ingredient_id`) REFERENCES `barang`(`id`),
  FOREIGN KEY (`unit_id`) REFERENCES `satuan`(`id`),
  UNIQUE KEY `unique_recipe` (`finished_product_id`, `ingredient_id`)
);

-- =====================================================
-- 9. ORDERS (For Dine-in/Takeaway/Delivery)
-- =====================================================
CREATE TABLE IF NOT EXISTS `pos_orders` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `order_no` VARCHAR(50) UNIQUE NOT NULL,
  `outlet_id` INT NOT NULL,
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
);

-- =====================================================
-- 10. ORDER ITEMS
-- =====================================================
CREATE TABLE IF NOT EXISTS `pos_order_items` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `order_id` INT NOT NULL,
  `barang_id` INT NOT NULL,
  `quantity` INT NOT NULL,
  `notes` TEXT NULL,
  `item_status` ENUM('pending', 'preparing', 'ready', 'served') DEFAULT 'pending',
  `prepared_at` DATETIME NULL,
  FOREIGN KEY (`order_id`) REFERENCES `pos_orders`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`barang_id`) REFERENCES `barang`(`id`)
);

-- =====================================================
-- 11. RETURNS & REFUNDS
-- =====================================================
CREATE TABLE IF NOT EXISTS `pos_returns` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `return_no` VARCHAR(50) UNIQUE NOT NULL,
  `transaction_id` INT NOT NULL,
  `outlet_id` INT NOT NULL,
  `return_reason` VARCHAR(255) NOT NULL,
  `return_status` ENUM('pending', 'approved', 'rejected', 'completed') DEFAULT 'pending',
  `total_refund` DECIMAL(12, 2) NOT NULL,
  `refund_method` ENUM('cash', 'original_payment', 'credit') DEFAULT 'cash',
  `processed_by` INT NOT NULL,
  `notes` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `completed_at` DATETIME NULL,
  FOREIGN KEY (`transaction_id`) REFERENCES `pos_transactions`(`id`),
  FOREIGN KEY (`outlet_id`) REFERENCES `outlets`(`id`),
  FOREIGN KEY (`processed_by`) REFERENCES `users`(`id`),
  INDEX `idx_return_no` (`return_no`)
);

-- =====================================================
-- 12. RETURN ITEMS
-- =====================================================
CREATE TABLE IF NOT EXISTS `pos_return_items` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `return_id` INT NOT NULL,
  `barang_id` INT NOT NULL,
  `quantity` INT NOT NULL,
  `unit_price` DECIMAL(12, 2) NOT NULL,
  `subtotal` DECIMAL(12, 2) NOT NULL,
  FOREIGN KEY (`return_id`) REFERENCES `pos_returns`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`barang_id`) REFERENCES `barang`(`id`)
);

-- =====================================================
-- 13. SHIFT MANAGEMENT
-- =====================================================
CREATE TABLE IF NOT EXISTS `pos_shifts` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `outlet_id` INT NOT NULL,
  `cashier_id` INT NOT NULL,
  `shift_date` DATE NOT NULL,
  `opening_time` DATETIME NOT NULL,
  `closing_time` DATETIME NULL,
  `opening_cash` DECIMAL(12, 2) NOT NULL,
  `closing_cash` DECIMAL(12, 2) NULL,
  `expected_cash` DECIMAL(12, 2) NULL,
  `cash_difference` DECIMAL(12, 2) NULL,
  `total_transactions` INT DEFAULT 0,
  `total_sales` DECIMAL(12, 2) DEFAULT 0,
  `notes` TEXT NULL,
  `shift_status` ENUM('open', 'closed') DEFAULT 'open',
  FOREIGN KEY (`outlet_id`) REFERENCES `outlets`(`id`),
  FOREIGN KEY (`cashier_id`) REFERENCES `users`(`id`),
  INDEX `idx_shift_date` (`shift_date`),
  INDEX `idx_cashier_id` (`cashier_id`)
);

-- =====================================================
-- 14. CASH DRAWER MANAGEMENT
-- =====================================================
CREATE TABLE IF NOT EXISTS `pos_cash_drawer` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `shift_id` INT NOT NULL,
  `transaction_type` ENUM('cash_in', 'cash_out', 'payment_received') DEFAULT 'payment_received',
  `amount` DECIMAL(12, 2) NOT NULL,
  `description` TEXT NOT NULL,
  `recorded_by` INT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`shift_id`) REFERENCES `pos_shifts`(`id`),
  FOREIGN KEY (`recorded_by`) REFERENCES `users`(`id`),
  INDEX `idx_shift_id` (`shift_id`)
);

-- =====================================================
-- 15. AUDIT LOG
-- =====================================================
CREATE TABLE IF NOT EXISTS `pos_audit_log` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
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
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`),
  INDEX `idx_user_id` (`user_id`),
  INDEX `idx_created_at` (`created_at`),
  INDEX `idx_action_type` (`action_type`)
);

-- =====================================================
-- 16. OFFLINE TRANSACTIONS QUEUE
-- =====================================================
CREATE TABLE IF NOT EXISTS `pos_offline_queue` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `outlet_id` INT NOT NULL,
  `transaction_data` JSON NOT NULL,
  `sync_status` ENUM('pending', 'synced', 'failed') DEFAULT 'pending',
  `sync_attempt` INT DEFAULT 0,
  `sync_error` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `synced_at` DATETIME NULL,
  FOREIGN KEY (`outlet_id`) REFERENCES `outlets`(`id`),
  INDEX `idx_sync_status` (`sync_status`)
);

-- =====================================================
-- 17. OUTLETS (Multi-outlet Support)
-- =====================================================
CREATE TABLE IF NOT EXISTS `outlets` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `outlet_name` VARCHAR(255) NOT NULL,
  `location` TEXT NOT NULL,
  `contact_person` VARCHAR(255) NULL,
  `phone` VARCHAR(20) NULL,
  `email` VARCHAR(255) NULL,
  `is_active` BOOLEAN DEFAULT TRUE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_outlet_name` (`outlet_name`)
);
