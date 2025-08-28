-- Error Logs Table for tracking 404 errors and other issues
CREATE TABLE IF NOT EXISTS `error_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `error_type` varchar(50) NOT NULL COMMENT 'Type of error (404, 500, etc.)',
  `requested_url` text NOT NULL COMMENT 'The URL that caused the error',
  `referrer` text DEFAULT NULL COMMENT 'The referring page',
  `user_agent` text DEFAULT NULL COMMENT 'User agent string',
  `ip_address` varchar(45) DEFAULT NULL COMMENT 'IP address of the user',
  `user_id` int(11) DEFAULT NULL COMMENT 'User ID if logged in',
  `session_id` varchar(255) DEFAULT NULL COMMENT 'Session ID',
  `error_message` text DEFAULT NULL COMMENT 'Additional error details',
  `stack_trace` text DEFAULT NULL COMMENT 'Stack trace for debugging',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_error_type` (`error_type`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_ip_address` (`ip_address`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Logs for tracking 404 errors and other issues';

-- Insert sample data for testing
INSERT INTO `error_logs` (`error_type`, `requested_url`, `referrer`, `user_agent`, `ip_address`, `created_at`) VALUES
('404', '/seait/nonexistent-page', 'https://example.com', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36', '127.0.0.1', NOW()),
('404', '/seait/old-page', 'https://google.com', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36', '192.168.1.1', NOW());
