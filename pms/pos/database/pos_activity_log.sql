-- POS Activity Log Table
-- This table tracks all activities in the POS system for learning and audit purposes

CREATE TABLE IF NOT EXISTS `pos_activity_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` varchar(100) NOT NULL,
  `action` varchar(50) NOT NULL,
  `description` text,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `action` (`action`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert sample activity log entries
INSERT INTO `pos_activity_log` (`user_id`, `action`, `description`, `ip_address`, `created_at`) VALUES
('student1', 'login', 'Student logged into POS simulation', '127.0.0.1', NOW() - INTERVAL 1 HOUR),
('student2', 'login', 'Student logged into POS simulation', '127.0.0.1', NOW() - INTERVAL 30 MINUTE),
('demo_user', 'login', 'Demo user logged into POS simulation', '127.0.0.1', NOW() - INTERVAL 15 MINUTE),
('student1', 'transaction', 'Created new restaurant order', '127.0.0.1', NOW() - INTERVAL 45 MINUTE),
('student2', 'transaction', 'Processed gift shop sale', '127.0.0.1', NOW() - INTERVAL 20 MINUTE),
('demo_user', 'transaction', 'Completed spa service booking', '127.0.0.1', NOW() - INTERVAL 10 MINUTE);
