-- Warehouse Management System - Database Schema
-- Compatible with MySQL 8.0+

CREATE DATABASE IF NOT EXISTS `warehouse_management` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `warehouse_management`;

-- --------------------------------------------------------
-- Table: users
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `full_name` VARCHAR(100) NOT NULL,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `role` ENUM('admin', 'user') NOT NULL DEFAULT 'user',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_username` (`username`),
  INDEX `idx_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: items
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `items` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `item_code` VARCHAR(50) NOT NULL UNIQUE,
  `item_name` VARCHAR(150) NOT NULL,
  `category` VARCHAR(100) NOT NULL,
  `location` VARCHAR(100) NOT NULL,
  `quantity` INT NOT NULL DEFAULT 0,
  `unit` VARCHAR(20) NOT NULL DEFAULT 'pcs',
  `reorder_level` INT NOT NULL DEFAULT 5,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `chk_quantity_nonnegative` CHECK (`quantity` >= 0),
  CONSTRAINT `chk_reorder_nonnegative` CHECK (`reorder_level` >= 0),
  INDEX `idx_item_code` (`item_code`),
  INDEX `idx_item_name` (`item_name`),
  INDEX `idx_category` (`category`),
  INDEX `idx_location` (`location`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: stock_movements
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `stock_movements` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `item_id` INT UNSIGNED NOT NULL,
  `movement_type` ENUM('IN', 'OUT') NOT NULL,
  `quantity` INT NOT NULL,
  `movement_date` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `reference_note` VARCHAR(255) DEFAULT NULL,
  `created_by` INT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_movements_item` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_movements_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `chk_movement_quantity_positive` CHECK (`quantity` > 0),
  INDEX `idx_item_id` (`item_id`),
  INDEX `idx_movement_type` (`movement_type`),
  INDEX `idx_movement_date` (`movement_date`),
  INDEX `idx_created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Initial Demo Data
-- Password for admin: admin123
-- Password for demo user: user123
-- Hash generated using PASSWORD_DEFAULT (bcrypt)
-- --------------------------------------------------------

INSERT INTO `users` (`id`, `full_name`, `username`, `password_hash`, `role`, `is_active`) 
VALUES 
(1, 'System Administrator', 'admin', '$2y$10$.MkCtAl4MMJDOMX4U5nae..0B56vfpkWfN1AMKjhBL7.73ULqBcOO', 'admin', 1),
(2, 'Warehouse User', 'demo', '$2y$10$obOO5e66OpjgG.DLSjh3XeX/X8tMTqf6SFsiuMOrvn0705A46Hilm', 'user', 1)
ON DUPLICATE KEY UPDATE
  `full_name` = VALUES(`full_name`),
  `password_hash` = VALUES(`password_hash`),
  `role` = VALUES(`role`),
  `is_active` = VALUES(`is_active`);

-- Inserting initial sample items
INSERT INTO `items` (`id`, `item_code`, `item_name`, `category`, `location`, `quantity`, `unit`, `reorder_level`) VALUES
(1, 'ITEM-001', 'Wireless Ergonomic Mouse', 'Electronics', 'Rack A-1', 45, 'pcs', 10),
(2, 'ITEM-002', 'Mechanical Keyboard RGB', 'Electronics', 'Rack A-2', 12, 'pcs', 15),
(3, 'ITEM-003', '27-inch 4K Monitor', 'Electronics', 'Rack B-1', 4, 'pcs', 5),
(4, 'ITEM-004', 'Heavy Duty Storage Box', 'Packaging', 'Aisle C-3', 150, 'units', 30),
(5, 'ITEM-005', 'Barcode Scanner Handheld', 'Hardware', 'Rack D-2', 8, 'pcs', 10)
ON DUPLICATE KEY UPDATE `id`=`id`;

-- Inserting sample stock movements
INSERT INTO `stock_movements` (`item_id`, `movement_type`, `quantity`, `movement_date`, `reference_note`, `created_by`) VALUES
(1, 'IN', 50, NOW(), 'Initial Stock Intake', 1),
(1, 'OUT', 5, NOW(), 'Dispatch to Branch A', 1),
(2, 'IN', 15, NOW(), 'Initial Stock Intake', 1),
(2, 'OUT', 3, NOW(), 'Internal Use', 1),
(3, 'IN', 5, NOW(), 'Supplier Delivery #1042', 1),
(3, 'OUT', 1, NOW(), 'Customer Order #882', 1),
(4, 'IN', 150, NOW(), 'Bulk Packaging Purchase', 1),
(5, 'IN', 10, NOW(), 'Initial Stock Intake', 1),
(5, 'OUT', 2, NOW(), 'Assigned to Shipping Team', 1);
