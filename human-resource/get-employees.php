<?php
session_start();
require_once '../includes/error_handler.php';
require_once '../config/database.php';

// Check if user is logged in and is HR
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'human_resource') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

// Get employees from both tables
$employees = [];

// Get employees from employees table
$employee_query = "SELECT id, first_name, last_name, employee_id, department, employee_type 
                   FROM employees 
                   WHERE is_active = 1 
                   ORDER BY first_name, last_name";
$employee_result = mysqli_query($conn, $employee_query);

if ($employee_result) {
    while ($row = mysqli_fetch_assoc($employee_result)) {
        $row['source_table'] = 'employees';
        $employees[] = $row;
    }
}

// Get faculty from faculty table
$faculty_query = "SELECT id, first_name, last_name, id as employee_id, department, 'faculty' as employee_type 
                  FROM faculty 
                  WHERE is_active = 1 
                  ORDER BY first_name, last_name";
$faculty_result = mysqli_query($conn, $faculty_query);

if ($faculty_result) {
    while ($row = mysqli_fetch_assoc($faculty_result)) {
        $row['source_table'] = 'faculty';
        $employees[] = $row;
    }
}

// Sort all employees by name
usort($employees, function($a, $b) {
    return strcmp($a['first_name'] . ' ' . $a['last_name'], $b['first_name'] . ' ' . $b['last_name']);
});

header('Content-Type: application/json');
echo json_encode($employees);
?>
