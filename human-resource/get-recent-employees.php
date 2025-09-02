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
    // Get recent employees (last 10 added)
    $recent_query = "SELECT id, employee_id, first_name, last_name, email, position, department, employee_type, is_active, created_at 
                     FROM employees 
                     ORDER BY created_at DESC 
                     LIMIT 10";
    
    $recent_result = mysqli_query($conn, $recent_query);
    
    if (!$recent_result) {
        throw new Exception('Database query error: ' . mysqli_error($conn));
    }
    
    $employees = [];
    while ($row = mysqli_fetch_assoc($recent_result)) {
        $employees[] = [
            'id' => $row['id'],
            'employee_id' => $row['employee_id'],
            'first_name' => htmlspecialchars($row['first_name']),
            'last_name' => htmlspecialchars($row['last_name']),
            'email' => htmlspecialchars($row['email']),
            'position' => htmlspecialchars($row['position']),
            'department' => htmlspecialchars($row['department']),
            'employee_type' => $row['employee_type'],
            'is_active' => (bool)$row['is_active'],
            'created_at' => $row['created_at']
        ];
    }

    // Return recent employees
    echo json_encode([
        'success' => true,
        'employees' => $employees,
        'count' => count($employees)
    ]);

} catch (Exception $e) {
    // Log error
    error_log("Error getting recent employees: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while fetching recent employees.',
        'employees' => [],
        'count' => 0
    ]);
}

mysqli_close($conn);
?>
