<?php
/**
 * Export Local Database Schema (Structure Only) - PHP Version
 * This script exports the database structure without any data using PHP mysqli
 */

// Include configuration
include('configuration.php');

// Output file name
$output_file = 'seens_schema_php_' . date('Y-m-d_H-i-s') . '.sql';

try {
    // Connect to database using configuration
    if ($socket) {
        $conn = new mysqli($host, $username, $password, $dbname, 3306, $socket);
    } else {
        $conn = new mysqli($host, $username, $password, $dbname, 3306);
    }
    
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    
    echo "✅ Connected to database: {$dbname}\n";
    echo "📁 Output file: {$output_file}\n\n";
    
    // Start building the SQL file
    $sql_content = "-- SEENS Database Schema Export\n";
    $sql_content .= "-- Generated on: " . date('Y-m-d H:i:s') . "\n";
    $sql_content .= "-- Database: {$dbname}\n\n";
    $sql_content .= "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n";
    $sql_content .= "SET AUTOCOMMIT = 0;\n";
    $sql_content .= "START TRANSACTION;\n";
    $sql_content .= "SET time_zone = \"+00:00\";\n\n";
    
    // Get all tables
    $tables_result = $conn->query("SHOW TABLES");
    $tables = [];
    
    while ($row = $tables_result->fetch_array()) {
        $tables[] = $row[0];
    }
    
    echo "📋 Found " . count($tables) . " tables\n";
    
    // Export each table structure
    foreach ($tables as $table) {
        echo "📝 Exporting table: {$table}\n";
        
        // Get CREATE TABLE statement
        $create_result = $conn->query("SHOW CREATE TABLE `{$table}`");
        $create_row = $create_result->fetch_assoc();
        $create_statement = $create_row['Create Table'];
        
        $sql_content .= "-- Table structure for table `{$table}`\n";
        $sql_content .= "DROP TABLE IF EXISTS `{$table}`;\n";
        $sql_content .= $create_statement . ";\n\n";
    }
    
    // Get stored procedures
    $procedures_result = $conn->query("SHOW PROCEDURE STATUS WHERE Db = '{$dbname}'");
    $procedures = [];
    
    while ($row = $procedures_result->fetch_assoc()) {
        $procedures[] = $row['Name'];
    }
    
    if (!empty($procedures)) {
        echo "📋 Found " . count($procedures) . " stored procedures\n";
        
        foreach ($procedures as $procedure) {
            echo "📝 Exporting procedure: {$procedure}\n";
            
            // Get procedure definition
            $proc_result = $conn->query("SHOW CREATE PROCEDURE `{$procedure}`");
            $proc_row = $proc_result->fetch_assoc();
            $proc_definition = $proc_row['Create Procedure'];
            
            $sql_content .= "-- Procedure structure for procedure `{$procedure}`\n";
            $sql_content .= "DROP PROCEDURE IF EXISTS `{$procedure}`;\n";
            $sql_content .= "DELIMITER ;;\n";
            $sql_content .= $proc_definition . " ;;\n";
            $sql_content .= "DELIMITER ;\n\n";
        }
    }
    
    // Get functions
    $functions_result = $conn->query("SHOW FUNCTION STATUS WHERE Db = '{$dbname}'");
    $functions = [];
    
    while ($row = $functions_result->fetch_assoc()) {
        $functions[] = $row['Name'];
    }
    
    if (!empty($functions)) {
        echo "📋 Found " . count($functions) . " functions\n";
        
        foreach ($functions as $function) {
            echo "📝 Exporting function: {$function}\n";
            
            // Get function definition
            $func_result = $conn->query("SHOW CREATE FUNCTION `{$function}`");
            $func_row = $func_result->fetch_assoc();
            $func_definition = $func_row['Create Function'];
            
            $sql_content .= "-- Function structure for function `{$function}`\n";
            $sql_content .= "DROP FUNCTION IF EXISTS `{$function}`;\n";
            $sql_content .= "DELIMITER ;;\n";
            $sql_content .= $func_definition . " ;;\n";
            $sql_content .= "DELIMITER ;\n\n";
        }
    }
    
    // Get triggers
    $triggers_result = $conn->query("SHOW TRIGGERS");
    $triggers = [];
    
    while ($row = $triggers_result->fetch_assoc()) {
        $triggers[] = $row['Trigger'];
    }
    
    if (!empty($triggers)) {
        echo "📋 Found " . count($triggers) . " triggers\n";
        
        foreach ($triggers as $trigger) {
            echo "📝 Exporting trigger: {$trigger}\n";
            
            // Get trigger definition
            $trigger_result = $conn->query("SHOW CREATE TRIGGER `{$trigger}`");
            $trigger_row = $trigger_result->fetch_assoc();
            $trigger_definition = $trigger_row['SQL Original Statement'];
            
            $sql_content .= "-- Trigger structure for trigger `{$trigger}`\n";
            $sql_content .= "DROP TRIGGER IF EXISTS `{$trigger}`;\n";
            $sql_content .= "DELIMITER ;;\n";
            $sql_content .= $trigger_definition . " ;;\n";
            $sql_content .= "DELIMITER ;\n\n";
        }
    }
    
    $sql_content .= "COMMIT;\n";
    
    // Write to file
    if (file_put_contents($output_file, $sql_content)) {
        $file_size = filesize($output_file);
        echo "\n✅ Database schema exported successfully!\n";
        echo "📁 File saved as: {$output_file}\n";
        echo "📊 File size: " . number_format($file_size) . " bytes (" . round($file_size / 1024, 2) . " KB)\n";
    } else {
        throw new Exception("Failed to write to file: {$output_file}");
    }
    
    $conn->close();
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n--- Export Complete ---\n";
?>
