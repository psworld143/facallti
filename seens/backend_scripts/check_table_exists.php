<?php
include('../configuration.php');

// Set response header
header('Content-Type: application/json');

// Error handling
try {
    // Check database connection
    if (!$conn) {
        throw new Exception('Database connection failed');
    }
    
    $tables = array('student', 'logs', 'account', 'adviser', 'visitors');
    $existing_tables = array();

    foreach ($tables as $table) {
        $result = mysqli_query($conn, "SHOW TABLES LIKE '$table'");
        if ($result === false) {
            throw new Exception('Query failed: ' . mysqli_error($conn));
        }
        if (mysqli_num_rows($result) > 0) {
            $existing_tables[] = $table;
        }
    }

    echo json_encode(array(
        'success' => true,
        'existing_tables' => $existing_tables,
        'total_tables' => count($tables),
        'existing_count' => count($existing_tables)
    ));
    
} catch (Exception $e) {
    echo json_encode(array(
        'success' => false,
        'error' => $e->getMessage()
    ));
}
?>
