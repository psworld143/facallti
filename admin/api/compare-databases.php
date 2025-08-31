<?php
// Disable error display for API
ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// Function to get all tables from a database with detailed column info
function getAllTables($host, $username, $password, $dbname, $port = 3306, $socket = null) {
    try {
        if ($socket) {
            $conn = mysqli_connect($host, $username, $password, $dbname, $port, $socket);
        } else {
            $conn = mysqli_connect($host, $username, $password, $dbname, $port);
        }
        
        if (!$conn) {
            return [];
        }
        
        mysqli_set_charset($conn, "utf8");
        
        $tables = [];
        $result = mysqli_query($conn, "SHOW TABLES");
        
        if ($result) {
            while ($row = mysqli_fetch_array($result)) {
                $table_name = $row[0];
                
                // Get table info
                $info_query = "SELECT 
                    TABLE_TYPE,
                    TABLE_ROWS,
                    DATA_LENGTH,
                    INDEX_LENGTH
                FROM information_schema.TABLES 
                WHERE TABLE_SCHEMA = '$dbname' AND TABLE_NAME = '$table_name'";
                
                $info_result = mysqli_query($conn, $info_query);
                $info = mysqli_fetch_assoc($info_result);
                
                // Get detailed column information
                $columns_result = mysqli_query($conn, "DESCRIBE `$table_name`");
                $column_count = 0;
                $column_names = [];
                
                if ($columns_result) {
                    while ($col_row = mysqli_fetch_assoc($columns_result)) {
                        $column_names[] = $col_row['Field'];
                        $column_count++;
                    }
                }
                
                $tables[$table_name] = [
                    'name' => $table_name,
                    'type' => $info['TABLE_TYPE'] ?? 'BASE TABLE',
                    'rows' => $info['TABLE_ROWS'] ?? 0,
                    'columns' => $column_count,
                    'column_names' => $column_names,
                    'data_size' => $info['DATA_LENGTH'] ?? 0,
                    'index_size' => $info['INDEX_LENGTH'] ?? 0
                ];
            }
        }
        
        mysqli_close($conn);
        return $tables;
        
    } catch (Exception $e) {
        return [];
    }
}

// Get tables from both databases
$local_tables = getAllTables(
    'localhost',
    'root',
    '',
    'seait_website',
    3306,
    '/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock'
);

$online_tables = getAllTables(
    'seait-edu.ph',
    'seaitedu_seait_website',
    '020894Website',
    'seaitedu_seait_website'
);

// Compare tables
$comparison_results = [];
$matches = 0;
$differences = 0;
$missing_online = 0;

// Get all unique table names
$all_tables = array_unique(array_merge(array_keys($local_tables), array_keys($online_tables)));

foreach ($all_tables as $table_name) {
    $local_exists = isset($local_tables[$table_name]);
    $online_exists = isset($online_tables[$table_name]);
    
    $table_comparison = [
        'name' => $table_name,
        'local' => $local_exists,
        'online' => $online_exists,
        'local_info' => $local_exists ? $local_tables[$table_name] : null,
        'online_info' => $online_exists ? $online_tables[$table_name] : null
    ];
    
    if ($local_exists && $online_exists) {
        // Both exist, check for differences
        $local_info = $local_tables[$table_name];
        $online_info = $online_tables[$table_name];
        
        // Smart comparison logic
        if ($local_info['type'] === 'VIEW' && $online_info['type'] === 'BASE TABLE') {
            // View converted to table - consider this a match
            $table_comparison['status'] = 'match';
            $table_comparison['note'] = 'View converted to table';
            $matches++;
        } else {
            // Strict comparison - online must exactly match local structure
            $local_columns = $local_info['column_names'] ?? [];
            $online_columns = $online_info['column_names'] ?? [];
            
            $missing_in_online = array_diff($local_columns, $online_columns);
            $extra_in_online = array_diff($online_columns, $local_columns);
            
            if (empty($missing_in_online) && empty($extra_in_online)) {
                // Perfect match - structures are identical
                $table_comparison['status'] = 'match';
                $matches++;
            } else {
                // Structures differ - needs synchronization
                $table_comparison['status'] = 'different';
                $differences++;
                
                $issues = [];
                if (!empty($missing_in_online)) {
                    $issues[] = count($missing_in_online) . ' missing columns';
                    $table_comparison['missing_columns'] = $missing_in_online;
                }
                if (!empty($extra_in_online)) {
                    $issues[] = count($extra_in_online) . ' extra columns';
                    $table_comparison['extra_columns'] = $extra_in_online;
                }
                
                $table_comparison['note'] = implode(', ', $issues);
            }
        }
    } elseif ($local_exists && !$online_exists) {
        $table_comparison['status'] = 'missing_online';
        $missing_online++;
    } elseif (!$local_exists && $online_exists) {
        $table_comparison['status'] = 'extra_online';
    }
    
    $comparison_results[] = $table_comparison;
}

// Sort results by status (missing first, then differences, then matches)
usort($comparison_results, function($a, $b) {
    $status_order = [
        'missing_online' => 1,
        'different' => 2,
        'extra_online' => 3,
        'match' => 4
    ];
    
    $a_order = $status_order[$a['status']] ?? 5;
    $b_order = $status_order[$b['status']] ?? 5;
    
    if ($a_order == $b_order) {
        return strcmp($a['name'], $b['name']);
    }
    
    return $a_order - $b_order;
});

// Return comparison results
echo json_encode([
    'tables' => $comparison_results,
    'summary' => [
        'total_local' => count($local_tables),
        'total_online' => count($online_tables),
        'matches' => $matches,
        'differences' => $differences,
        'missing_online' => $missing_online,
        'total_differences' => $differences + $missing_online
    ],
    'matches' => $matches,
    'differences' => $differences,
    'missing' => $missing_online,
    'total_differences' => $differences + $missing_online,
    'timestamp' => date('Y-m-d H:i:s')
]);
?>
