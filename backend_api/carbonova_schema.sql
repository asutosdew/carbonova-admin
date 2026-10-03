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

-- 5. Admin Users & Roles Table
CREATE TABLE IF NOT EXISTS `adminusers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `email` VARCHAR(100) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `role` VARCHAR(50) NOT NULL DEFAULT 'Operations Admin',
  `status` ENUM('Active', 'Inactive') NOT NULL DEFAULT 'Active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Initial default administrator accounts
INSERT INTO `adminusers` (`name`, `username`, `email`, `password`, `role`, `status`) VALUES
('System Administrator', 'admin', 'admin@carbonovaworld.com', 'admin123', 'Super Admin', 'Active'),
('Operations Manager', 'operations', 'operations@carbonovaworld.com', 'admin123', 'Operations Admin', 'Active'),
('Finance Manager', 'finance', 'finance@carbonovaworld.com', 'admin123', 'Finance Admin', 'Active'),
('Support Executive', 'support', 'support@carbonovaworld.com', 'admin123', 'Support Admin', 'Active'),
('Reporting User', 'viewer', 'viewer@carbonovaworld.com', 'admin123', 'Viewer', 'Active')
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`);

-- 6. Dynamic Admin Session Tokens Table
CREATE TABLE IF NOT EXISTS `tokens` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `token` VARCHAR(100) NOT NULL UNIQUE,
  `username` VARCHAR(50) NOT NULL,
  `expireson` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (`token`),
  INDEX (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Migration to add token columns on existing adminusers table:
-- ALTER TABLE `adminusers` ADD COLUMN IF NOT EXISTS `token` VARCHAR(100) DEFAULT NULL AFTER `status`;
-- ALTER TABLE `adminusers` ADD COLUMN IF NOT EXISTS `token_expires` DATETIME DEFAULT NULL AFTER `token`;

-- 7. Orders Table (Customer plant purchases & package kit distributions)
CREATE TABLE IF NOT EXISTS `orders` (
  `order_id` INT AUTO_INCREMENT PRIMARY KEY,
  `order_number` VARCHAR(50) NOT NULL UNIQUE,
  `user_id` VARCHAR(50) NOT NULL,
  `order_type` VARCHAR(30) NOT NULL DEFAULT 'PACKAGE',
  `package_id` INT DEFAULT NULL,
  `total_items` INT NOT NULL DEFAULT 1,
  `total_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `total_dp` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `total_reward_points` INT NOT NULL DEFAULT 0,
  `payment_status` VARCHAR(20) NOT NULL DEFAULT 'PAID',
  `order_status` VARCHAR(20) NOT NULL DEFAULT 'PENDING',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX (`user_id`),
  INDEX (`order_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 8. Order Items Table
CREATE TABLE IF NOT EXISTS `order_items` (
  `item_id` INT AUTO_INCREMENT PRIMARY KEY,
  `order_id` INT NOT NULL,
  `product_id` INT NOT NULL,
  `product_name` VARCHAR(255) NOT NULL,
  `scientific_name` VARCHAR(255) DEFAULT '',
  `quantity` INT NOT NULL DEFAULT 1,
  `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `dp` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `reward_point` INT NOT NULL DEFAULT 0,
  `total_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `total_reward_points` INT NOT NULL DEFAULT 0,
  INDEX (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 9. Order Shipping & Tracking Details Table
CREATE TABLE IF NOT EXISTS `order_shipping` (
  `shipping_id` INT AUTO_INCREMENT PRIMARY KEY,
  `order_id` INT NOT NULL UNIQUE,
  `recipient_name` VARCHAR(100) NOT NULL DEFAULT '',
  `phone` VARCHAR(25) NOT NULL DEFAULT '',
  `address` TEXT NOT NULL,
  `city` VARCHAR(100) NOT NULL DEFAULT '',
  `state` VARCHAR(100) NOT NULL DEFAULT '',
  `pincode` VARCHAR(15) NOT NULL DEFAULT '',
  `shipping_mode` VARCHAR(50) NOT NULL DEFAULT 'Courier',
  `courier_name` VARCHAR(100) NOT NULL DEFAULT '',
  `tracking_number` VARCHAR(100) NOT NULL DEFAULT '',
  `shipping_status` VARCHAR(50) NOT NULL DEFAULT 'PENDING',
  `packed_date` DATETIME DEFAULT NULL,
  `dispatched_date` DATETIME DEFAULT NULL,
  `delivered_date` DATETIME DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Initial Real Seed Orders (if table is freshly created)
INSERT INTO `orders` (`order_id`, `order_number`, `user_id`, `order_type`, `package_id`, `total_items`, `total_amount`, `total_dp`, `total_reward_points`, `payment_status`, `order_status`, `created_at`) VALUES
(1001, 'ORD-2026-1001', '180093', 'PACKAGE', 1, 2, 10000.00, 8000.00, 50, 'PAID', 'PACKED', DATE_SUB(NOW(), INTERVAL 5 DAY)),
(1002, 'ORD-2026-1002', '157059', 'DIRECT_PRODUCT', NULL, 3, 2500.00, 2050.00, 75, 'PAID', 'SHIPPED', DATE_SUB(NOW(), INTERVAL 4 DAY)),
(1003, 'ORD-2026-1003', '125374', 'PACKAGE', 1, 1, 10000.00, 8500.00, 35, 'PAID', 'DELIVERED', DATE_SUB(NOW(), INTERVAL 3 DAY)),
(1004, 'ORD-2026-1004', '182328', 'DIRECT_PRODUCT', NULL, 2, 2000.00, 1600.00, 50, 'PAID', 'PENDING', DATE_SUB(NOW(), INTERVAL 1 DAY))
ON DUPLICATE KEY UPDATE `order_number`=VALUES(`order_number`);

INSERT INTO `order_items` (`order_id`, `product_id`, `product_name`, `scientific_name`, `quantity`, `price`, `dp`, `reward_point`, `total_price`, `total_reward_points`) VALUES
(1001, 1, 'Vietnam Super Early Jackfruit', 'Artocarpus heterophyllus', 1, 1000.00, 800.00, 25, 1000.00, 25),
(1001, 2, 'Kumbhkat Seedless Lemon', 'Citrus limon', 1, 1000.00, 800.00, 25, 1000.00, 25),
(1002, 2, 'Kumbhkat Seedless Lemon', 'Citrus limon', 2, 1000.00, 800.00, 20, 2000.00, 40),
(1002, 3, 'PKM-1 Super Moringa', 'Moringa oleifera', 1, 500.00, 400.00, 15, 500.00, 15),
(1003, 1, 'Vietnam Super Early Jackfruit', 'Artocarpus heterophyllus', 2, 1000.00, 800.00, 25, 2000.00, 50),
(1004, 1, 'Vietnam Super Early Jackfruit', 'Artocarpus heterophyllus', 2, 1000.00, 800.00, 25, 2000.00, 50);

INSERT INTO `order_shipping` (`order_id`, `recipient_name`, `phone`, `address`, `city`, `state`, `pincode`, `shipping_mode`, `courier_name`, `tracking_number`, `shipping_status`) VALUES
(1001, 'MANSAI', '9827112001', 'Near Kisan Mandi, Ward 4', 'Raipur', 'Chhattisgarh', '492001', 'Courier', 'DTDC Express', 'DTDC-89213401', 'PACKED'),
(1002, 'Sandeep Sharma', '9752344102', 'Plot 42, Green Avenue, Telibandha', 'Bilaspur', 'Chhattisgarh', '495001', 'India Post', 'Speed Post', 'SP-CG49500128', 'SHIPPED'),
(1003, 'TANIYA SANDILYA', '9179883344', 'Main Market Road, Durg', 'Durg', 'Chhattisgarh', '491001', 'Transport', 'VRL Logistics', 'VRL-9921045', 'DELIVERED'),
(1004, 'Pankaj Kumar Biswas', '9425211990', 'Village Post Raigarh, Civil Lines', 'Raigarh', 'Chhattisgarh', '496001', 'Courier', 'Delhivery', '', 'PENDING')
ON DUPLICATE KEY UPDATE `recipient_name`=VALUES(`recipient_name`);


