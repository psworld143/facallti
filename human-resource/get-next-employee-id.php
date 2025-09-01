<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and has human_resource role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'human_resource') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

try {
    // Get the highest employee ID from employees table
    $query = "SELECT employee_id FROM employees WHERE employee_id LIKE 'EMP%' ORDER BY CAST(SUBSTRING(employee_id, 4) AS UNSIGNED) DESC LIMIT 1";
    $stmt = mysqli_prepare($conn, $query);
    
    if (!$stmt) {
        throw new Exception('Database prepare error: ' . mysqli_error($conn));
    }
    
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        $last_employee_id = $row['employee_id'];
        
        // Extract the sequence number from the last employee ID (EMP001 -> 1)
        $sequence_part = substr($last_employee_id, 3); // Remove "EMP" prefix
        $last_sequence = (int)$sequence_part;
        $next_sequence = $last_sequence + 1;
    } else {
        // No employees yet, start with 1
        $next_sequence = 1;
    }
    
    // Format the new employee ID (EMP001, EMP002, etc.)
    $new_employee_id = 'EMP' . str_pad($next_sequence, 3, '0', STR_PAD_LEFT);
    
    echo json_encode([
        'success' => true,
        'employee_id' => $new_employee_id,
        'sequence' => $next_sequence
    ]);

} catch (Exception $e) {
    // Log error
    error_log("Error generating employee ID: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while generating employee ID.'
    ]);
}

mysqli_close($conn);
?>
