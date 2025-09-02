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
    // Get total employees count
    $total_query = "SELECT COUNT(*) as total FROM employees";
    $total_result = mysqli_query($conn, $total_query);
    $total_employees = mysqli_fetch_assoc($total_result)['total'];

    // Get active employees count
    $active_query = "SELECT COUNT(*) as active FROM employees WHERE is_active = 1";
    $active_result = mysqli_query($conn, $active_query);
    $active_employees = mysqli_fetch_assoc($active_result)['active'];

    // Get new employees this month
    $new_this_month_query = "SELECT COUNT(*) as new_this_month FROM employees WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())";
    $new_this_month_result = mysqli_query($conn, $new_this_month_query);
    $new_this_month = mysqli_fetch_assoc($new_this_month_result)['new_this_month'];

    // Get employees by type
    $by_type_query = "SELECT employee_type, COUNT(*) as count FROM employees WHERE is_active = 1 GROUP BY employee_type";
    $by_type_result = mysqli_query($conn, $by_type_query);
    $employees_by_type = [];
    while ($row = mysqli_fetch_assoc($by_type_result)) {
        $employees_by_type[$row['employee_type']] = $row['count'];
    }

    // Get employees by department
    $by_department_query = "SELECT department, COUNT(*) as count FROM employees WHERE is_active = 1 GROUP BY department ORDER BY count DESC LIMIT 5";
    $by_department_result = mysqli_query($conn, $by_department_query);
    $employees_by_department = [];
    while ($row = mysqli_fetch_assoc($by_department_result)) {
        $employees_by_department[] = [
            'department' => $row['department'],
            'count' => $row['count']
        ];
    }

    // Return statistics
    echo json_encode([
        'success' => true,
        'stats' => [
            'total' => (int)$total_employees,
            'active' => (int)$active_employees,
            'inactive' => (int)($total_employees - $active_employees),
            'new_this_month' => (int)$new_this_month,
            'by_type' => $employees_by_type,
            'by_department' => $employees_by_department
        ]
    ]);

} catch (Exception $e) {
    // Log error
    error_log("Error getting employee stats: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while fetching employee statistics.',
        'stats' => [
            'total' => 0,
            'active' => 0,
            'inactive' => 0,
            'new_this_month' => 0,
            'by_type' => [],
            'by_department' => []
        ]
    ]);
}

mysqli_close($conn);
?>
