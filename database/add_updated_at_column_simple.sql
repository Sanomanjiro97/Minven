-- Simple version to add columns to surat_jalan table
-- This version is more compatible with older MySQL versions

-- Add updated_at column if not exists
ALTER TABLE `surat_jalan` 
ADD COLUMN `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() 
AFTER `created_at`;

-- Add keterangan_pembayaran column if not exists  
ALTER TABLE `surat_jalan` 
ADD COLUMN `keterangan_pembayaran` text DEFAULT NULL 
AFTER `status_pembayaran`; 