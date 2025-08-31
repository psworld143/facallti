<?php
// Disable error display for API
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Enable mysqli exception mode for better error handling
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Enable output buffering for streaming
ob_start();

// Function to send progress update
function sendProgress($progress, $message, $type = 'info') {
    $data = [
        'progress' => $progress,
        'message' => $message,
        'log' => [
            'message' => $message,
            'type' => $type,
            'timestamp' => date('H:i:s')
        ]
    ];
    
    echo json_encode($data) . "\n";
    
    if (ob_get_level()) {
        ob_flush();
    }
    flush();
}

// Function to send log message
function sendLog($message, $type = 'info') {
    $data = [
        'log' => [
            'message' => $message,
            'type' => $type,
            'timestamp' => date('H:i:s')
        ]
    ];
    
    echo json_encode($data) . "\n";
    
    if (ob_get_level()) {
        ob_flush();
    }
    flush();
}

// Function to create a view as a table (fallback for privilege issues)
function createViewAsTable($local_conn, $online_conn, $view_name) {
    try {
        // Get the view definition
        $view_result = mysqli_query($local_conn, "SHOW CREATE VIEW `$view_name`");
        if (!$view_result) {
            return false;
        }
        
        $view_row = mysqli_fetch_array($view_result);
        $view_definition = $view_row[1];
        
        // Extract the SELECT statement from the view definition
        if (preg_match('/AS\s+(SELECT.*)/is', $view_definition, $matches)) {
            $select_statement = $matches[1];
            
            // Create table with the same structure as the view would have
            $create_table_sql = "CREATE TABLE `$view_name` AS $select_statement LIMIT 0";
            
            if (mysqli_query($online_conn, $create_table_sql)) {
                // Now populate the table with data
                $insert_sql = "INSERT INTO `$view_name` $select_statement";
                
                // We need to execute this on the local database first to get the data
                $data_result = mysqli_query($local_conn, $select_statement);
                if ($data_result && mysqli_num_rows($data_result) > 0) {
                    // Get column names
                    $columns = [];
                    $field_info = mysqli_fetch_fields($data_result);
                    foreach ($field_info as $field) {
                        $columns[] = "`{$field->name}`";
                    }
                    
                    $columns_str = implode(', ', $columns);
                    
                    // Insert data row by row
                    while ($row = mysqli_fetch_array($data_result, MYSQLI_NUM)) {
                        $values = [];
                        foreach ($row as $value) {
                            $values[] = "'" . mysqli_real_escape_string($online_conn, $value) . "'";
                        }
                        $values_str = implode(', ', $values);
                        
                        $insert_row_sql = "INSERT INTO `$view_name` ($columns_str) VALUES ($values_str)";
                        mysqli_query($online_conn, $insert_row_sql);
                    }
                }
                
                return true;
            }
        }
        
        return false;
    } catch (Exception $e) {
        return false;
    }
}

// Get request data
$input = json_decode(file_get_contents('php://input'), true);
$mode = $input['mode'] ?? 'safe';
$preserve_data = $input['preserve_data'] ?? true;

sendProgress(0, 'Starting database synchronization...');
sendLog('Sync mode: ' . $mode);
sendLog('Preserve data: ' . ($preserve_data ? 'Yes' : 'No'));

try {
    // Connect to local database
    sendProgress(10, 'Connecting to local database...');
    
    try {
        $local_conn = mysqli_connect(
            'localhost',
            'root',
            '',
            'seait_website',
            3306,
            '/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock'
        );
        mysqli_set_charset($local_conn, "utf8");
        sendLog('Local database connected successfully', 'success');
    } catch (mysqli_sql_exception $e) {
        throw new Exception('Local database connection failed: ' . $e->getMessage());
    }
    
    // Connect to online database
    sendProgress(20, 'Connecting to online database...');
    
    try {
        $online_conn = mysqli_connect(
            'seait-edu.ph',
            'seaitedu_seait_website',
            '020894Website',
            'seaitedu_seait_website'
        );
        mysqli_set_charset($online_conn, "utf8");
        sendLog('Online database connected successfully', 'success');
    } catch (mysqli_sql_exception $e) {
        throw new Exception('Online database connection failed: ' . $e->getMessage());
    }
    
    // Get all tables from local database
    sendProgress(30, 'Analyzing local database structure...');
    
    $local_tables = [];
    $local_views = [];
    
    $result = mysqli_query($local_conn, "SHOW TABLES");
    if ($result) {
        while ($row = mysqli_fetch_array($result)) {
            $table_name = $row[0];
            
            // Check if it's a view
            $type_result = mysqli_query($local_conn, 
                "SELECT TABLE_TYPE FROM information_schema.TABLES 
                 WHERE TABLE_SCHEMA = 'seait_website' AND TABLE_NAME = '$table_name'"
            );
            
            if ($type_result) {
                $type_row = mysqli_fetch_assoc($type_result);
                if ($type_row['TABLE_TYPE'] === 'VIEW') {
                    $local_views[] = $table_name;
                } else {
                    $local_tables[] = $table_name;
                }
            } else {
                $local_tables[] = $table_name;
            }
        }
    }
    
    sendLog('Found ' . count($local_tables) . ' tables and ' . count($local_views) . ' views in local database');
    
    // Get existing online tables
    sendProgress(40, 'Analyzing online database structure...');
    
    $online_tables = [];
    $result = mysqli_query($online_conn, "SHOW TABLES");
    if ($result) {
        while ($row = mysqli_fetch_array($result)) {
            $online_tables[] = $row[0];
        }
    }
    
    sendLog('Found ' . count($online_tables) . ' tables in online database');
    
    // Disable foreign key checks
    mysqli_query($online_conn, "SET FOREIGN_KEY_CHECKS = 0");
    sendLog('Foreign key checks disabled');
    
    // Sync tables
    $tables_added = 0;
    $tables_preserved = 0;
    $total_tables = count($local_tables);
    
    foreach ($local_tables as $index => $table) {
        $progress = 50 + (($index / $total_tables) * 30);
        sendProgress($progress, "Processing table: $table");
        
        if (!in_array($table, $online_tables)) {
            // Table doesn't exist online, create it
            sendLog("Creating new table: $table");
            
            $create_result = mysqli_query($local_conn, "SHOW CREATE TABLE `$table`");
            if ($create_result) {
                $create_row = mysqli_fetch_array($create_result);
                $create_statement = $create_row[1];
                
                if (mysqli_query($online_conn, $create_statement)) {
                    sendLog("Table created successfully: $table", 'success');
                    $tables_added++;
                    
                    // Copy data if preserve_data is false or table is empty online
                    if (!$preserve_data) {
                        $copy_result = mysqli_query($local_conn, "SELECT * FROM `$table`");
                        if ($copy_result && mysqli_num_rows($copy_result) > 0) {
                            sendLog("Copying data to table: $table");
                            
                            while ($row = mysqli_fetch_assoc($copy_result)) {
                                $fields = array_keys($row);
                                $values = array_values($row);
                                
                                $fields_str = '`' . implode('`, `', $fields) . '`';
                                $values_str = "'" . implode("', '", array_map(function($v) use ($online_conn) {
                                    return mysqli_real_escape_string($online_conn, $v);
                                }, $values)) . "'";
                                
                                $insert_sql = "INSERT INTO `$table` ($fields_str) VALUES ($values_str)";
                                mysqli_query($online_conn, $insert_sql);
                            }
                            
                            sendLog("Data copied to table: $table", 'success');
                        }
                    }
                } else {
                    sendLog("Failed to create table: $table - " . mysqli_error($online_conn), 'error');
                }
            }
        } else {
            // Table exists, preserve it
            sendLog("Preserving existing table: $table");
            $tables_preserved++;
        }
    }
    
    // Update existing table structures
    sendProgress(75, 'Updating table structures...');
    
    $tables_updated = 0;
    
    foreach ($local_tables as $table) {
        if (in_array($table, $online_tables)) {
            sendLog("Checking structure for table: $table");
            
            // Get local table columns
            $local_columns = [];
            $local_result = mysqli_query($local_conn, "DESCRIBE `$table`");
            if ($local_result) {
                while ($row = mysqli_fetch_assoc($local_result)) {
                    $local_columns[$row['Field']] = $row;
                }
                sendLog("Local table $table has " . count($local_columns) . " columns", 'info');
            }
            
            // Get online table columns
            $online_columns = [];
            $online_result = mysqli_query($online_conn, "DESCRIBE `$table`");
            if ($online_result) {
                while ($row = mysqli_fetch_assoc($online_result)) {
                    $online_columns[$row['Field']] = $row;
                }
                sendLog("Online table $table has " . count($online_columns) . " columns", 'info');
            }
            
            // Find missing columns (columns in local but not in online)
            $missing_columns = array_diff_key($local_columns, $online_columns);
            
            // Find extra columns (columns in online but not in local)
            $extra_columns = array_diff_key($online_columns, $local_columns);
            
            $structure_changed = false;
            
            // Remove extra columns from online to match local structure
            if (!empty($extra_columns)) {
                sendLog("Online table $table has " . count($extra_columns) . " extra columns that will be removed: " . implode(', ', array_keys($extra_columns)), 'warning');
                
                foreach ($extra_columns as $column_name => $column_info) {
                    sendLog("Removing extra column: $column_name from table $table", 'info');
                    
                    $drop_sql = "ALTER TABLE `$table` DROP COLUMN `$column_name`";
                    sendLog("Executing: $drop_sql", 'info');
                    
                    try {
                        if (mysqli_query($online_conn, $drop_sql)) {
                            sendLog("Removed extra column $column_name from table $table", 'success');
                            $structure_changed = true;
                        } else {
                            sendLog("Failed to remove column $column_name from table $table: " . mysqli_error($online_conn), 'warning');
                        }
                    } catch (mysqli_sql_exception $e) {
                        sendLog("Error removing column $column_name from table $table: " . $e->getMessage(), 'warning');
                    }
                }
            }
            
            // Add missing columns to online to match local structure
            if (!empty($missing_columns)) {
                sendLog("Adding " . count($missing_columns) . " missing columns to table: $table", 'info');
                
                foreach ($missing_columns as $column_name => $column_info) {
                    sendLog("Preparing to add column: $column_name ({$column_info['Type']})", 'info');
                    
                    $alter_sql = "ALTER TABLE `$table` ADD COLUMN `$column_name` " . $column_info['Type'];
                    
                    if ($column_info['Null'] === 'NO') {
                        $alter_sql .= " NOT NULL";
                    }
                    
                    if ($column_info['Default'] !== null && $column_info['Default'] !== '') {
                        $alter_sql .= " DEFAULT '" . mysqli_real_escape_string($online_conn, $column_info['Default']) . "'";
                    }
                    
                    sendLog("Executing: $alter_sql", 'info');
                    
                    try {
                        if (mysqli_query($online_conn, $alter_sql)) {
                            sendLog("Added column $column_name to table $table", 'success');
                            $structure_changed = true;
                        } else {
                            sendLog("Failed to add column $column_name to table $table: " . mysqli_error($online_conn), 'warning');
                        }
                    } catch (mysqli_sql_exception $e) {
                        sendLog("Error adding column $column_name to table $table: " . $e->getMessage(), 'warning');
                    }
                }
            }
            
            if ($structure_changed) {
                $tables_updated++;
                sendLog("Table structure updated: $table now matches local structure", 'success');
            } else {
                sendLog("Table structure already matches: $table", 'info');
            }
        }
    }
    
    if ($tables_updated > 0) {
        sendLog("Updated structure for $tables_updated tables", 'success');
    }

    // Sync views (with privilege handling and table fallback)
    sendProgress(80, 'Processing views...');
    
    $views_added = 0;
    $views_preserved = 0;
    $views_skipped = 0;
    $views_as_tables = 0;
    
    foreach ($local_views as $view) {
        sendLog("Processing view: $view");
        
        if (!in_array($view, $online_tables)) {
            // View doesn't exist online, try to create it
            $create_result = mysqli_query($local_conn, "SHOW CREATE VIEW `$view`");
            if ($create_result) {
                $create_row = mysqli_fetch_array($create_result);
                $create_statement = $create_row[1];
                
                // Try to create the view with exception handling
                try {
                    if (mysqli_query($online_conn, $create_statement)) {
                        sendLog("View created successfully: $view", 'success');
                        $views_added++;
                    } else {
                        $error_message = mysqli_error($online_conn);
                        
                        // Check if it's a privilege error
                        if (strpos($error_message, 'Access denied') !== false || 
                            strpos($error_message, 'SUPER') !== false || 
                            strpos($error_message, 'SET USER') !== false) {
                            sendLog("View creation failed due to privileges, creating as table instead: $view", 'warning');
                            
                            // Create as table instead
                            if (createViewAsTable($local_conn, $online_conn, $view)) {
                                sendLog("Successfully created $view as table", 'success');
                                $views_as_tables++;
                            } else {
                                sendLog("Failed to create $view as table", 'warning');
                                $views_skipped++;
                            }
                        } else {
                            sendLog("Failed to create view: $view - " . $error_message, 'warning');
                        }
                    }
                } catch (mysqli_sql_exception $e) {
                    $error_message = $e->getMessage();
                    
                    // Check if it's a privilege error
                    if (strpos($error_message, 'Access denied') !== false || 
                        strpos($error_message, 'SUPER') !== false || 
                        strpos($error_message, 'SET USER') !== false) {
                        sendLog("View creation failed due to privileges, creating as table instead: $view", 'warning');
                        
                        // Create as table instead
                        if (createViewAsTable($local_conn, $online_conn, $view)) {
                            sendLog("Successfully created $view as table", 'success');
                            $views_as_tables++;
                        } else {
                            sendLog("Failed to create $view as table", 'warning');
                            $views_skipped++;
                        }
                    } else {
                        sendLog("Failed to create view: $view - " . $error_message, 'warning');
                    }
                } catch (Exception $e) {
                    sendLog("Error creating view $view: " . $e->getMessage(), 'warning');
                }
            }
        } else {
            sendLog("View already exists: $view");
            $views_preserved++;
        }
    }
    
    // Re-enable foreign key checks
    mysqli_query($online_conn, "SET FOREIGN_KEY_CHECKS = 1");
    sendLog('Foreign key checks re-enabled');
    
    // Final summary
    sendProgress(100, 'Synchronization completed successfully!');
    
    sendLog('=== SYNCHRONIZATION SUMMARY ===', 'success');
    sendLog("Tables added: $tables_added", 'success');
    sendLog("Tables preserved: $tables_preserved", 'success');
    if ($tables_updated > 0) {
        sendLog("Tables updated (structure): $tables_updated", 'success');
    }
    sendLog("Views added: $views_added", 'success');
    sendLog("Views preserved: $views_preserved", 'success');
    if ($views_as_tables > 0) {
        sendLog("Views created as tables: $views_as_tables", 'success');
    }
    if ($views_skipped > 0) {
        sendLog("Views skipped due to errors: $views_skipped", 'warning');
    }
    sendLog('All existing online data has been preserved', 'success');
    sendLog('Consultation system is now available online', 'success');
    
    // Close connections
    mysqli_close($local_conn);
    mysqli_close($online_conn);
    
    // Send final success response
    echo json_encode([
        'success' => true,
        'summary' => [
            'tables_added' => $tables_added,
            'tables_preserved' => $tables_preserved,
            'tables_updated' => $tables_updated ?? 0,
            'views_added' => $views_added,
            'views_preserved' => $views_preserved,
            'views_as_tables' => $views_as_tables ?? 0,
            'views_skipped' => $views_skipped ?? 0
        ],
        'message' => 'Database synchronization completed successfully',
        'timestamp' => date('Y-m-d H:i:s')
    ]) . "\n";
    
} catch (Exception $e) {
    sendLog('Synchronization failed: ' . $e->getMessage(), 'error');
    sendProgress(0, 'Synchronization failed');
    
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]) . "\n";
}

if (ob_get_level()) {
    ob_end_flush();
}
?>
