-- ==============================================================================
-- Carbonova World - Admin Database Schema & Image Support
-- ==============================================================================

-- 1. Plants & Products Table
-- Column `image` stores the relative path (e.g. 'uploads/products/products_1727892345_abc.webp')
-- or full URL from the server.
CREATE TABLE IF NOT EXISTS `products` (
  `rowid` INT AUTO_INCREMENT PRIMARY KEY,
  `productname` VARCHAR(255) NOT NULL,
  `scientificname` VARCHAR(255) DEFAULT '',
  `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `dp` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `active` TINYINT(1) DEFAULT 1,
  `linkwithpackage` TINYINT(1) DEFAULT 0,
  `rewardpoint` INT DEFAULT 0,
  `quantity` INT DEFAULT 0,
  `image` VARCHAR(500) DEFAULT '',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Migration script if table already exists without image column:
-- ALTER TABLE `products` ADD COLUMN IF NOT EXISTS `image` VARCHAR(500) DEFAULT '' AFTER `scientificname`;

-- 2. Packages / Plans Table (Exact Table Structure)
CREATE TABLE IF NOT EXISTS `plans` (
  `rowid` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `packagename` VARCHAR(50) DEFAULT NULL,
  `amount` DECIMAL(10,2) DEFAULT NULL,
  `active` TINYINT(1) DEFAULT NULL,
  `description` VARCHAR(255) DEFAULT NULL,
  `autopool` DECIMAL(10,2) DEFAULT NULL,
  `direct` DECIMAL(10,2) DEFAULT NULL,
  `image` VARCHAR(500) DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Migration to add image column to existing plans table:
-- ALTER TABLE `plans` ADD COLUMN IF NOT EXISTS `image` VARCHAR(500) DEFAULT '' AFTER `packagename`;

-- 3. Farmer Members / Profile Photo Table (If profile photo is uploaded)
-- ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `photo` VARCHAR(500) DEFAULT '' AFTER `mobile`;

-- 4. Payment Slips / Receipts Table (If receipt image is uploaded)
-- ALTER TABLE `payments` ADD COLUMN IF NOT EXISTS `receipt_image` VARCHAR(500) DEFAULT '' AFTER `amount`;
