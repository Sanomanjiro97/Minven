-- Add payment_method to direct_purchase table
ALTER TABLE `direct_purchase` 
ADD COLUMN `payment_method` ENUM('cash', 'saldo') DEFAULT 'cash' AFTER `keterangan`;
