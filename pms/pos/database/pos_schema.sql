-- POS System Database Schema
-- Hotel Property Management System - Point of Sale Module

-- Create POS transactions table
CREATE TABLE IF NOT EXISTS `pos_transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `transaction_number` varchar(20) NOT NULL,
  `service_type` enum('restaurant','room-service','spa','gift-shop','events','quick-sales') NOT NULL,
  `guest_id` int(11) DEFAULT NULL,
  `room_number` varchar(10) DEFAULT NULL,
  `table_id` int(11) DEFAULT NULL,
  `items` json NOT NULL,
  `subtotal` decimal(10,2) NOT NULL DEFAULT 0.00,
  `tax_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `discount_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `payment_method` enum('cash','credit-card','debit-card','mobile-payment','room-charge') DEFAULT NULL,
  `payment_reference` varchar(100) DEFAULT NULL,
  `status` enum('pending','preparing','ready','completed','cancelled') NOT NULL DEFAULT 'pending',
  `notes` text,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `completed_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `transaction_number` (`transaction_number`),
  KEY `guest_id` (`guest_id`),
  KEY `service_type` (`service_type`),
  KEY `status` (`status`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create POS menu items table
CREATE TABLE IF NOT EXISTS `pos_menu_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text,
  `category` varchar(50) NOT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `cost` decimal(10,2) DEFAULT 0.00,
  `image` varchar(255) DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `category` (`category`),
  KEY `active` (`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create POS tables table
CREATE TABLE IF NOT EXISTS `pos_tables` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `table_number` varchar(10) NOT NULL,
  `capacity` int(11) NOT NULL DEFAULT 4,
  `location` varchar(50) DEFAULT 'main-floor',
  `status` enum('available','occupied','reserved','maintenance') NOT NULL DEFAULT 'available',
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `table_number` (`table_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create POS orders table
CREATE TABLE IF NOT EXISTS `pos_orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_number` varchar(20) NOT NULL,
  `table_id` int(11) DEFAULT NULL,
  `guest_id` int(11) DEFAULT NULL,
  `room_number` varchar(10) DEFAULT NULL,
  `guest_count` int(11) DEFAULT 1,
  `items` json NOT NULL,
  `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `status` enum('pending','preparing','ready','served','cancelled') NOT NULL DEFAULT 'pending',
  `special_requests` text,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `served_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `order_number` (`order_number`),
  KEY `table_id` (`table_id`),
  KEY `guest_id` (`guest_id`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create POS payments table
CREATE TABLE IF NOT EXISTS `pos_payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `transaction_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_method` enum('cash','credit-card','debit-card','mobile-payment','room-charge') NOT NULL,
  `payment_reference` varchar(100) DEFAULT NULL,
  `processed_by` int(11) NOT NULL,
  `processed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `transaction_id` (`transaction_id`),
  KEY `payment_method` (`payment_method`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create POS inventory table
CREATE TABLE IF NOT EXISTS `pos_inventory` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `item_name` varchar(100) NOT NULL,
  `category` varchar(50) NOT NULL,
  `sku` varchar(50) DEFAULT NULL,
  `quantity` int(11) NOT NULL DEFAULT 0,
  `reorder_level` int(11) DEFAULT 10,
  `unit_cost` decimal(10,2) DEFAULT 0.00,
  `supplier` varchar(100) DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `category` (`category`),
  KEY `sku` (`sku`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create POS categories table
CREATE TABLE IF NOT EXISTS `pos_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `description` text,
  `service_type` enum('restaurant','room-service','spa','gift-shop','events','quick-sales') NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `service_type` (`service_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create POS discounts table
CREATE TABLE IF NOT EXISTS `pos_discounts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `discount_type` enum('percentage','fixed-amount') NOT NULL,
  `discount_value` decimal(10,2) NOT NULL,
  `min_amount` decimal(10,2) DEFAULT 0.00,
  `max_discount` decimal(10,2) DEFAULT NULL,
  `valid_from` date DEFAULT NULL,
  `valid_until` date DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create POS tax rates table
CREATE TABLE IF NOT EXISTS `pos_tax_rates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `rate` decimal(5,2) NOT NULL,
  `description` text,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert sample data for menu items
INSERT INTO `pos_menu_items` (`name`, `description`, `category`, `price`, `cost`) VALUES
-- Appetizers
('Spring Rolls', 'Fresh vegetables wrapped in rice paper with sweet chili sauce', 'appetizers', 180.00, 80.00),
('Chicken Satay', 'Grilled chicken skewers with peanut sauce', 'appetizers', 220.00, 100.00),
('Tom Yum Soup', 'Spicy and sour soup with shrimp and mushrooms', 'appetizers', 280.00, 120.00),

-- Main Courses
('Beef Tenderloin', 'Grilled beef tenderloin with garlic mashed potatoes', 'main-courses', 850.00, 350.00),
('Grilled Salmon', 'Fresh salmon with seasonal vegetables', 'main-courses', 720.00, 280.00),
('Chicken Adobo', 'Traditional Filipino chicken adobo with rice', 'main-courses', 380.00, 150.00),
('Pasta Carbonara', 'Creamy pasta with bacon and parmesan', 'main-courses', 420.00, 180.00),

-- Desserts
('Chocolate Lava Cake', 'Warm chocolate cake with molten center', 'desserts', 280.00, 120.00),
('Tiramisu', 'Classic Italian dessert with coffee and mascarpone', 'desserts', 320.00, 140.00),
('Ice Cream Selection', 'Vanilla, chocolate, and strawberry ice cream', 'desserts', 180.00, 80.00),

-- Beverages
('Fresh Orange Juice', 'Freshly squeezed orange juice', 'beverages', 120.00, 50.00),
('Iced Tea', 'Refreshing iced tea with lemon', 'beverages', 80.00, 30.00),
('Coffee', 'Freshly brewed coffee', 'beverages', 100.00, 40.00),
('Mineral Water', '500ml bottled water', 'beverages', 60.00, 20.00);

-- Insert sample data for tables
INSERT INTO `pos_tables` (`table_number`, `capacity`, `location`) VALUES
('1', 4, 'main-floor'),
('2', 4, 'main-floor'),
('3', 6, 'main-floor'),
('4', 2, 'window'),
('5', 8, 'private-room'),
('6', 4, 'garden-view'),
('7', 6, 'main-floor'),
('8', 4, 'window');

-- Insert sample data for categories
INSERT INTO `pos_categories` (`name`, `description`, `service_type`) VALUES
('Appetizers', 'Starters and small plates', 'restaurant'),
('Main Courses', 'Primary dishes and entrees', 'restaurant'),
('Desserts', 'Sweet treats and pastries', 'restaurant'),
('Beverages', 'Drinks and refreshments', 'restaurant'),
('Spa Treatments', 'Wellness and relaxation services', 'spa'),
('Gift Items', 'Souvenirs and retail products', 'gift-shop'),
('Event Services', 'Conference and banquet services', 'events');

-- Insert sample tax rate
INSERT INTO `pos_tax_rates` (`name`, `rate`, `description`) VALUES
('VAT', 12.00, 'Value Added Tax');

-- Insert sample discount
INSERT INTO `pos_discounts` (`name`, `discount_type`, `discount_value`, `min_amount`) VALUES
('Senior Citizen', 'percentage', 20.00, 0.00),
('PWD', 'percentage', 20.00, 0.00),
('Bulk Order', 'percentage', 10.00, 1000.00);

-- Create triggers for transaction numbers
DELIMITER $$
CREATE TRIGGER `generate_transaction_number` BEFORE INSERT ON `pos_transactions`
FOR EACH ROW
BEGIN
    IF NEW.transaction_number IS NULL OR NEW.transaction_number = '' THEN
        SET NEW.transaction_number = CONCAT('TXN', DATE_FORMAT(NOW(), '%Y%m%d'), LPAD((SELECT COUNT(*) + 1 FROM pos_transactions WHERE DATE(created_at) = CURDATE()), 4, '0'));
    END IF;
END$$

CREATE TRIGGER `generate_order_number` BEFORE INSERT ON `pos_orders`
FOR EACH ROW
BEGIN
    IF NEW.order_number IS NULL OR NEW.order_number = '' THEN
        SET NEW.order_number = CONCAT('ORD', DATE_FORMAT(NOW(), '%Y%m%d'), LPAD((SELECT COUNT(*) + 1 FROM pos_orders WHERE DATE(created_at) = CURDATE()), 4, '0'));
    END IF;
END$$
DELIMITER ;

-- Create indexes for better performance
CREATE INDEX `idx_pos_transactions_service_status` ON `pos_transactions` (`service_type`, `status`);
CREATE INDEX `idx_pos_transactions_guest_room` ON `pos_transactions` (`guest_id`, `room_number`);
CREATE INDEX `idx_pos_menu_items_category_active` ON `pos_menu_items` (`category`, `active`);
CREATE INDEX `idx_pos_orders_table_status` ON `pos_orders` (`table_id`, `status`);
CREATE INDEX `idx_pos_payments_transaction` ON `pos_payments` (`transaction_id`);
