<?php
/**
 * Database Initialization Script
 * Automatically creates database and tables if they don't exist
 */

// Include configuration file for database settings
include('configuration.php');

// Function to create database and tables
function initializeDatabase($host, $username, $password, $dbname) {
    try {
        // Use socket from configuration
        global $socket;
        
        // First, connect without specifying database
        if ($socket) {
            $conn = new mysqli($host, $username, $password, '', 3306, $socket);
        } else {
            $conn = new mysqli($host, $username, $password, '', 3306);
        }
        
        if ($conn->connect_error) {
            throw new Exception("Connection failed: " . $conn->connect_error);
        }
        
        // Check if database exists
        $result = $conn->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '$dbname'");
        
        if ($result->num_rows == 0) {
            // Database doesn't exist, create it
            if ($conn->query("CREATE DATABASE `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci")) {
                // Database created successfully (no output)
            } else {
                throw new Exception("Failed to create database: " . $conn->error);
            }
        } else {
            // Database already exists (no output)
        }
        
        // Now connect to the specific database
        $conn->select_db($dbname);
        
        // Create tables if they don't exist
        $tables = [
            'seens_account' => "
                CREATE TABLE `seens_account` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `username` varchar(255) DEFAULT NULL,
                    `password` varchar(255) DEFAULT NULL,
                    `role` varchar(50) DEFAULT NULL,
                    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                    PRIMARY KEY (`id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
            ",
            'seens_adviser' => "
                CREATE TABLE `seens_adviser` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `name` varchar(255) DEFAULT NULL,
                    `email` varchar(255) DEFAULT NULL,
                    `department` varchar(255) DEFAULT NULL,
                    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                    PRIMARY KEY (`id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
            ",
            'seens_logs' => "
                CREATE TABLE `seens_logs` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `student_id` varchar(255) DEFAULT NULL,
                    `action` varchar(255) DEFAULT NULL,
                    `timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
                    `details` text DEFAULT NULL,
                    PRIMARY KEY (`id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
            ",
            'seens_student' => "
                CREATE TABLE `seens_student` (
                    `ss_id` int(11) NOT NULL AUTO_INCREMENT,
                    `ss_id_no` varchar(255) NOT NULL,
                    `ss_photo_location` LONGTEXT DEFAULT NULL,
                    `ss_date_added` timestamp NOT NULL DEFAULT current_timestamp(),
                    PRIMARY KEY (`ss_id`),
                    UNIQUE KEY `ss_id_no` (`ss_id_no`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
            ",
            'seens_visitors' => "
                CREATE TABLE `seens_visitors` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `visitor_name` varchar(255) DEFAULT NULL,
                    `purpose` varchar(500) DEFAULT NULL,
                    `entry_time` timestamp NOT NULL DEFAULT current_timestamp(),
                    `exit_time` timestamp NULL DEFAULT NULL,
                    PRIMARY KEY (`id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
            ",
            'activity_logs' => "
                CREATE TABLE `activity_logs` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
                    `student_id` varchar(255) DEFAULT NULL,
                    `student_name` varchar(255) DEFAULT NULL,
                    `action` varchar(255) DEFAULT NULL,
                    `status` varchar(50) DEFAULT NULL,
                    `details` text DEFAULT NULL,
                    PRIMARY KEY (`id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
            "
        ];
        
        foreach ($tables as $table_name => $create_sql) {
            // Check if table exists
            $result = $conn->query("SHOW TABLES LIKE '$table_name'");
            
            if ($result->num_rows == 0) {
                // Table doesn't exist, create it
                if ($conn->query($create_sql)) {
                    // Table created successfully (no output)
                } else {
                    echo "❌ Failed to create table '$table_name': " . $conn->error . "\n";
                }
            } else {
                // Table already exists (no output)
            }
        }
        
        // Insert default admin account if seens_account table is empty
        $result = $conn->query("SELECT COUNT(*) as count FROM seens_account");
        $row = $result->fetch_assoc();
        
        if ($row['count'] == 0) {
            $default_username = 'root';
            $default_password = password_hash('', PASSWORD_DEFAULT); // No password
            $default_role = 'administrator';
            
            $insert_sql = "INSERT INTO seens_account (username, password, role) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($insert_sql);
            $stmt->bind_param("sss", $default_username, $default_password, $default_role);
            
            if ($stmt->execute()) {
                // Default admin account created successfully (no output)
            } else {
                echo "❌ Failed to create default admin account: " . $stmt->error . "\n";
            }
            $stmt->close();
        }
        
        $conn->close();
        return true;
        
    } catch (Exception $e) {
        echo "❌ Error: " . $e->getMessage() . "\n";
        return false;
    }
}

// Function to check if database connection is successful
function checkDatabaseConnection($host, $username, $password, $dbname) {
    try {
        // Use socket from configuration
        global $socket;
        
        if ($socket) {
            $conn = new mysqli($host, $username, $password, $dbname, 3306, $socket);
        } else {
            $conn = new mysqli($host, $username, $password, $dbname, 3306);
        }
        
        if ($conn->connect_error) {
            return false;
        }
        
        // Test if we can query the database
        $result = $conn->query("SELECT 1");
        $conn->close();
        
        return $result !== false;
        
    } catch (Exception $e) {
        return false;
    }
}

// Note: Database initialization is now handled by index.php with user permission
// This file only provides the functions, it doesn't auto-initialize

?>
