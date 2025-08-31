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
    $current_year = date('Y');
    
    // Get the highest employee ID for the current year
    $query = "SELECT employee_id FROM employees WHERE employee_id LIKE ? ORDER BY employee_id DESC LIMIT 1";
    $stmt = mysqli_prepare($conn, $query);
    
    if (!$stmt) {
        throw new Exception('Database prepare error: ' . mysqli_error($conn));
    }
    
    $year_pattern = $current_year . '-%';
    mysqli_stmt_bind_param($stmt, 's', $year_pattern);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        $last_employee_id = $row['employee_id'];
        
        // Extract the sequence number from the last employee ID
        $parts = explode('-', $last_employee_id);
        if (count($parts) == 2 && $parts[0] == $current_year) {
            $last_sequence = (int)$parts[1];
            $next_sequence = $last_sequence + 1;
        } else {
            $next_sequence = 1;
        }
    } else {
        // No employees for this year, start with 1
        $next_sequence = 1;
    }
    
    // Format the new employee ID
    $new_employee_id = $current_year . '-' . str_pad($next_sequence, 4, '0', STR_PAD_LEFT);
    
    echo json_encode([
        'success' => true,
        'employee_id' => $new_employee_id,
        'year' => $current_year,
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
