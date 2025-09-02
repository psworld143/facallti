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
    // Get current year
    $current_year = date('Y');
    
    // Get all employee IDs from both tables for the current year
    // Fix collation issue by explicitly specifying collation
    $query = "SELECT employee_id COLLATE utf8mb4_unicode_ci as id FROM employees 
              WHERE employee_id IS NOT NULL AND employee_id LIKE ? 
              UNION ALL 
              SELECT qrcode COLLATE utf8mb4_unicode_ci as id FROM faculty 
              WHERE qrcode IS NOT NULL AND qrcode LIKE ?";
    
    $year_pattern = $current_year . '-%';
    $stmt = mysqli_prepare($conn, $query);
    
    if (!$stmt) {
        throw new Exception('Database prepare error: ' . mysqli_error($conn));
    }
    
    mysqli_stmt_bind_param($stmt, 'ss', $year_pattern, $year_pattern);
    
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception('Database execute error: ' . mysqli_stmt_error($stmt));
    }
    
    $result = mysqli_stmt_get_result($stmt);
    
    if (!$result) {
        throw new Exception('Database result error: ' . mysqli_error($conn));
    }
    
    $max_sequence = 0;
    
    if (mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            $employee_id = $row['id'];
            
            // Validate the format of the employee ID
            if (preg_match('/^\d{4}-\d{4}$/', $employee_id)) {
                // Extract the sequence number from the employee ID (2025-0001 -> 1)
                $sequence_part = substr($employee_id, 5); // Remove "YYYY-" prefix
                $sequence = (int)$sequence_part;
                
                if ($sequence > $max_sequence) {
                    $max_sequence = $sequence;
                }
            }
        }
    }
    
    $next_sequence = $max_sequence + 1;
    
    // Validate the sequence number
    if ($next_sequence < 1 || $next_sequence > 9999) {
        throw new Exception('Invalid sequence number: ' . $next_sequence);
    }
    
    // Format the new employee ID (2025-0001, 2025-0002, etc.)
    $new_employee_id = $current_year . '-' . str_pad($next_sequence, 4, '0', STR_PAD_LEFT);
    
    // Final validation of the generated ID
    if (!preg_match('/^\d{4}-\d{4}$/', $new_employee_id)) {
        throw new Exception('Generated employee ID has invalid format: ' . $new_employee_id);
    }
    
    echo json_encode([
        'success' => true,
        'employee_id' => $new_employee_id,
        'sequence' => $next_sequence,
        'year' => $current_year
    ]);

} catch (Exception $e) {
    // Log error with more details
    error_log("Error generating employee ID: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while generating employee ID: ' . $e->getMessage()
    ]);
}

mysqli_close($conn);
?>
