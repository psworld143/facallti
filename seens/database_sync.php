<?php
// Enable error reporting for debugging but suppress output
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Include configuration files
include('configuration.php');

// Establish local connection
$conn = establishConnection();

// Check if online configuration exists and handle it gracefully
$online_conn = null;
if (file_exists('configuration_online.php')) {
    // Capture any output to prevent HTML errors from being sent
    ob_start();
    include('configuration_online.php');
    ob_end_clean();
}

class DatabaseSync {
    private $local_conn;
    private $online_conn;
    private static $progress = array(
        'overall_percent' => 0,
        'current_table' => '',
        'current_record' => 0,
        'total_records' => 0,
        'status' => 'Ready'
    );
    
    public function __construct($local_conn, $online_conn = null) {
        $this->local_conn = $local_conn;
        $this->online_conn = $online_conn;
    }
    
    // Check if online connection is available
    public function isOnlineAvailable() {
        return $this->online_conn && !$this->online_conn->connect_error;
    }
    
    // Update progress
    private function updateProgress($overallPercent, $currentTable, $currentRecord, $totalRecords, $status) {
        self::$progress = array(
            'overall_percent' => $overallPercent,
            'current_table' => $currentTable,
            'current_record' => $currentRecord,
            'total_records' => $totalRecords,
            'status' => $status
        );
    }
    
    // Get progress
    public static function getProgress() {
        return self::$progress;
    }
    
    // Reset progress
    private function resetProgress() {
        self::$progress = array(
            'overall_percent' => 0,
            'current_table' => '',
            'current_record' => 0,
            'total_records' => 0,
            'status' => 'Ready'
        );
    }
    
    // Get SEENS tables from a database
    private function getTables($connection) {
        $seensTables = array('seens_account', 'seens_adviser', 'seens_logs', 'seens_student', 'seens_visitors');
        $tables = array();
        if (!$connection) return $tables;
        
        // Only return SEENS tables that exist
        foreach ($seensTables as $table) {
            $result = $connection->query("SHOW TABLES LIKE '$table'");
            if ($result && $result->num_rows > 0) {
                $tables[] = $table;
            }
        }
        return $tables;
    }
    
    // Get table structure
    private function getTableStructure($connection, $table) {
        if (!$connection) return null;
        
        $structure = array();
        $result = $connection->query("DESCRIBE `$table`");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $structure[] = $row;
            }
        }
        return $structure;
    }
    
    // Create table if it doesn't exist
    private function createTableIfNotExists($connection, $table, $structure) {
        if (!$connection || !$structure) return false;
        
        $create_sql = "CREATE TABLE IF NOT EXISTS `$table` (";
        $columns = array();
        
        foreach ($structure as $column) {
            $col_def = "`{$column['Field']}` {$column['Type']}";
            if ($column['Null'] == 'NO') $col_def .= " NOT NULL";
            if ($column['Default'] !== null) $col_def .= " DEFAULT '{$column['Default']}'";
            if ($column['Extra']) $col_def .= " {$column['Extra']}";
            $columns[] = $col_def;
        }
        
        $create_sql .= implode(', ', $columns) . ")";
        
        return $connection->query($create_sql);
    }
    
    // Sync all tables from local to online
    public function syncLocalToOnline() {
        if (!$this->isOnlineAvailable()) {
            return array('success' => false, 'message' => 'Online database connection not available');
        }
        
        $this->resetProgress();
        $this->updateProgress(0, 'Initializing...', 0, 0, 'Starting sync...');
        
        $results = array();
        $local_tables = $this->getTables($this->local_conn);
        $total_synced = 0;
        $errors = array();
        
        // Calculate total records for progress
        $total_records = 0;
        foreach ($local_tables as $table) {
            $count_result = $this->local_conn->query("SELECT COUNT(*) as count FROM `$table`");
            $total_records += $count_result ? $count_result->fetch_assoc()['count'] : 0;
        }
        
        $this->updateProgress(0, 'Preparing...', 0, $total_records, 'Calculated total records: ' . $total_records);
        
        $current_record = 0;
        foreach ($local_tables as $table) {
            try {
                $overall_percent = $total_records > 0 ? ($current_record / $total_records) * 100 : 0;
                $this->updateProgress(
                    $overall_percent,
                    $table,
                    $current_record,
                    $total_records,
                    "Syncing table: $table"
                );
                
                $result = $this->syncTableWithProgress($table, 'local_to_online', $current_record, $total_records);
                if ($result['success']) {
                    $total_synced += $result['count'];
                    $results[$table] = $result;
                    $current_record += $result['count'];
                } else {
                    $errors[] = "$table: " . $result['message'];
                }
            } catch (Exception $e) {
                $errors[] = "$table: " . $e->getMessage();
            }
        }
        
        $this->updateProgress(100, 'Complete', $total_records, $total_records, 'Sync completed');
        
        $message = "Successfully synced $total_synced records across " . count($local_tables) . " tables";
        if (!empty($errors)) {
            $message .= ". Errors: " . implode(', ', $errors);
        }
        
        return array(
            'success' => empty($errors),
            'message' => $message,
            'count' => $total_synced,
            'tables' => $results,
            'errors' => $errors
        );
    }
    
    // Sync all tables from online to local
    public function syncOnlineToLocal() {
        if (!$this->isOnlineAvailable()) {
            return array('success' => false, 'message' => 'Online database connection not available');
        }
        
        $this->resetProgress();
        $this->updateProgress(0, 'Initializing...', 0, 0, 'Starting sync...');
        
        $results = array();
        $online_tables = $this->getTables($this->online_conn);
        $total_synced = 0;
        $errors = array();
        
        // Calculate total records for progress
        $total_records = 0;
        foreach ($online_tables as $table) {
            $count_result = $this->online_conn->query("SELECT COUNT(*) as count FROM `$table`");
            $total_records += $count_result ? $count_result->fetch_assoc()['count'] : 0;
        }
        
        // If no records to sync, return early
        if ($total_records == 0) {
            $this->updateProgress(100, 'Complete', 0, 0, 'No records to sync');
            return array(
                'success' => true,
                'message' => 'No records to sync from online database',
                'count' => 0,
                'tables' => array(),
                'errors' => array()
            );
        }
        
        $this->updateProgress(0, 'Preparing...', 0, $total_records, 'Calculated total records: ' . $total_records);
        
        $current_record = 0;
        foreach ($online_tables as $table) {
            try {
                $overall_percent = $total_records > 0 ? ($current_record / $total_records) * 100 : 0;
                $this->updateProgress(
                    $overall_percent,
                    $table,
                    $current_record,
                    $total_records,
                    "Syncing table: $table"
                );
                
                $result = $this->syncTableWithProgress($table, 'online_to_local', $current_record, $total_records);
                if ($result['success']) {
                    $total_synced += $result['count'];
                    $results[$table] = $result;
                    $current_record += $result['count'];
                } else {
                    $errors[] = "$table: " . $result['message'];
                }
            } catch (Exception $e) {
                $errors[] = "$table: " . $e->getMessage();
            }
        }
        
        $this->updateProgress(100, 'Complete', $total_records, $total_records, 'Sync completed');
        
        $message = "Successfully synced $total_synced records across " . count($online_tables) . " tables";
        if (!empty($errors)) {
            $message .= ". Errors: " . implode(', ', $errors);
        }
        
        return array(
            'success' => empty($errors),
            'message' => $message,
            'count' => $total_synced,
            'tables' => $results,
            'errors' => $errors
        );
    }
    
    // Sync a single table with progress tracking
    private function syncTableWithProgress($table, $direction, $current_record, $total_records) {
        $result = array('success' => false, 'message' => '', 'count' => 0);
        
        try {
            if ($direction === 'local_to_online') {
                $source_conn = $this->local_conn;
                $target_conn = $this->online_conn;
            } else {
                $source_conn = $this->online_conn;
                $target_conn = $this->local_conn;
            }
            
            if (!$source_conn || !$target_conn) {
                throw new Exception("Database connection not available");
            }
            
            // Get table structure and create table if needed
            $structure = $this->getTableStructure($source_conn, $table);
            if ($structure) {
                $this->createTableIfNotExists($target_conn, $table, $structure);
            }
            
            // Get total count for progress tracking
            $count_result = $source_conn->query("SELECT COUNT(*) as count FROM `$table`");
            $table_total = $count_result ? $count_result->fetch_assoc()['count'] : 0;
            
            if ($table_total == 0) {
                $result['success'] = true;
                $result['message'] = "No records to sync from $table";
                $result['count'] = 0;
                return $result;
            }
            
            $synced_count = 0;
            $primary_key = $this->getPrimaryKey($source_conn, $table);
            $table_current = 0;
            $batch_size = 100; // Process in batches of 100
            $offset = 0;
            
            while ($offset < $table_total) {
                // Get batch of records
                $source_data = $source_conn->query("SELECT * FROM `$table` LIMIT $batch_size OFFSET $offset");
                if (!$source_data) {
                    throw new Exception("Error fetching data from $table: " . $source_conn->error);
                }
                
                while ($row = $source_data->fetch_assoc()) {
                    if ($this->syncRow($target_conn, $table, $row, $primary_key)) {
                        $synced_count++;
                    }
                    $table_current++;
                    $current_record++;
                    
                    // Update progress every 10 records or at the end
                    if ($table_current % 10 == 0 || $table_current == $table_total) {
                        $overall_percent = $total_records > 0 ? ($current_record / $total_records) * 100 : 0;
                        $this->updateProgress(
                            $overall_percent,
                            $table,
                            $current_record,
                            $total_records,
                            "Syncing $table: $table_current/$table_total records"
                        );
                    }
                }
                
                $offset += $batch_size;
                
                // Free memory
                $source_data->free();
            }
            
            $result['success'] = true;
            $result['message'] = "Synced $synced_count records from $table";
            $result['count'] = $synced_count;
            
        } catch (Exception $e) {
            $result['message'] = "Error syncing $table: " . $e->getMessage();
        }
        
        return $result;
    }
    
    // Sync a single table (legacy method)
    private function syncTable($table, $direction) {
        return $this->syncTableWithProgress($table, $direction, 0, 0);
    }
    
    // Get primary key for a table
    private function getPrimaryKey($connection, $table) {
        if (!$connection) return null;
        
        $result = $connection->query("SHOW KEYS FROM `$table` WHERE Key_name = 'PRIMARY'");
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            return $row['Column_name'];
        }
        return null;
    }
    
    // Sync a single row
    private function syncRow($target_conn, $table, $row, $primary_key) {
        if (!$target_conn) return false;
        
        // Build WHERE clause for checking existing records
        $where_clause = "";
        if ($primary_key && isset($row[$primary_key])) {
            $where_clause = "WHERE `$primary_key` = '" . $target_conn->real_escape_string($row[$primary_key]) . "'";
        } else {
            // If no primary key, use all columns for comparison
            $conditions = array();
            foreach ($row as $key => $value) {
                $conditions[] = "`$key` = '" . $target_conn->real_escape_string($value) . "'";
            }
            $where_clause = "WHERE " . implode(' AND ', $conditions);
        }
        
        // Check if record exists
        $check_query = "SELECT COUNT(*) as count FROM `$table` $where_clause";
        $check_result = $target_conn->query($check_query);
        
        if ($check_result && $check_result->fetch_assoc()['count'] > 0) {
            // Update existing record
            $set_clause = array();
            foreach ($row as $key => $value) {
                $set_clause[] = "`$key` = '" . $target_conn->real_escape_string($value) . "'";
            }
            $update_query = "UPDATE `$table` SET " . implode(', ', $set_clause) . " $where_clause";
            return $target_conn->query($update_query);
        } else {
            // Insert new record
            $columns = array_keys($row);
            $values = array_values($row);
            $escaped_values = array();
            foreach ($values as $value) {
                $escaped_values[] = "'" . $target_conn->real_escape_string($value) . "'";
            }
            $insert_query = "INSERT INTO `$table` (`" . implode('`, `', $columns) . "`) VALUES (" . implode(', ', $escaped_values) . ")";
            return $target_conn->query($insert_query);
        }
    }
    
    // Get sync status for SEENS tables only
    public function getSyncStatus() {
        $status = array();
        $seensTables = array('seens_account', 'seens_adviser', 'seens_logs', 'seens_student', 'seens_visitors');
        
        // Get local SEENS tables and counts
        $status['local'] = array();
        foreach ($seensTables as $table) {
            $count_result = $this->local_conn->query("SELECT COUNT(*) as count FROM `$table`");
            $status['local'][$table] = $count_result ? $count_result->fetch_assoc()['count'] : 0;
        }
        
        // Get online SEENS tables and counts
        $status['online'] = array();
        if ($this->isOnlineAvailable()) {
            foreach ($seensTables as $table) {
                $count_result = $this->online_conn->query("SELECT COUNT(*) as count FROM `$table`");
                $status['online'][$table] = $count_result ? $count_result->fetch_assoc()['count'] : 0;
            }
        }
        
        return $status;
    }
    
    // Fix database tables by adding seens_ prefix
    public function fixDatabaseTables() {
        $results = array();
        $errors = array();
        $renamed_tables = array();
        
        // Define the expected SEENS table names
        $expectedTables = array(
            'account' => 'seens_account',
            'adviser' => 'seens_adviser', 
            'logs' => 'seens_logs',
            'student' => 'seens_student',
            'visitors' => 'seens_visitors'
        );
        
        // Fix local database tables
        if ($this->local_conn) {
            $results['local'] = $this->fixTablesInDatabase($this->local_conn, $expectedTables, $renamed_tables, $errors);
        }
        
        // Fix online database tables
        if ($this->isOnlineAvailable()) {
            $results['online'] = $this->fixTablesInDatabase($this->online_conn, $expectedTables, $renamed_tables, $errors);
        }
        
        $message = "Database tables fixed successfully.";
        if (!empty($renamed_tables)) {
            $message .= " Renamed tables: " . implode(', ', $renamed_tables);
        }
        if (!empty($errors)) {
            $message .= " Errors: " . implode(', ', $errors);
        }
        
        return array(
            'success' => empty($errors),
            'message' => $message,
            'renamed_tables' => $renamed_tables,
            'errors' => $errors,
            'results' => $results
        );
    }
    
    // Fix tables in a specific database
    private function fixTablesInDatabase($connection, $expectedTables, &$renamed_tables, &$errors) {
        $results = array();
        
        try {
            // Get all tables in the database
            $tables_result = $connection->query("SHOW TABLES");
            if (!$tables_result) {
                throw new Exception("Failed to get tables list");
            }
            
            $existing_tables = array();
            while ($row = $tables_result->fetch_array()) {
                $existing_tables[] = $row[0];
            }
            
            // Check each expected table
            foreach ($expectedTables as $old_name => $new_name) {
                // If the old name exists but new name doesn't, rename it
                if (in_array($old_name, $existing_tables) && !in_array($new_name, $existing_tables)) {
                    $rename_sql = "RENAME TABLE `$old_name` TO `$new_name`";
                    if ($connection->query($rename_sql)) {
                        $renamed_tables[] = "$old_name → $new_name";
                        $results[$old_name] = "Renamed to $new_name";
                    } else {
                        $errors[] = "Failed to rename $old_name to $new_name: " . $connection->error;
                        $results[$old_name] = "Error: " . $connection->error;
                    }
                } elseif (in_array($new_name, $existing_tables)) {
                    // Table already has correct name
                    $results[$new_name] = "Already correct";
                } else {
                    // Table doesn't exist
                    $results[$old_name] = "Not found";
                }
            }
            
        } catch (Exception $e) {
            $errors[] = "Database error: " . $e->getMessage();
        }
        
        return $results;
    }
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    try {
        $sync = new DatabaseSync($conn, $online_conn ?? null);
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'sync_local_to_online':
                $result = $sync->syncLocalToOnline();
                echo json_encode($result);
                break;
                
            case 'sync_online_to_local':
                $result = $sync->syncOnlineToLocal();
                echo json_encode($result);
                break;
                
            case 'get_status':
                $result = $sync->getSyncStatus();
                echo json_encode($result);
                break;
                
            case 'get_progress':
                $result = array('progress' => DatabaseSync::getProgress());
                echo json_encode($result);
                break;
                
            case 'fix_database_tables':
                $result = $sync->fixDatabaseTables();
                echo json_encode($result);
                break;
                
            default:
                echo json_encode(array('error' => 'Invalid action'));
        }
    } catch (Exception $e) {
        echo json_encode(array('error' => 'Database error: ' . $e->getMessage()));
    }
    exit;
}
?>
