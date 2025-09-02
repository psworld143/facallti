<?php
session_start();
require_once '../includes/error_handler.php';
require_once '../config/database.php';

// Check if user is logged in and is HR
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'human_resource') {
    header('Location: ../index.php?login=required&redirect=leave-balances');
    exit();
}

// Get filter parameters
$year_filter = $_GET['year'] ?? date('Y');
$department_filter = $_GET['department'] ?? '';
$employee_type_filter = $_GET['employee_type'] ?? 'all';
$search = $_GET['search'] ?? '';
$current_tab = $_GET['tab'] ?? 'employees'; // Default to employees tab

// Get leave balances for employees
$employee_balances = [];
if (($employee_type_filter === 'all' || $employee_type_filter === 'employee') && ($current_tab === 'employees' || $current_tab === 'all')) {
    $employee_where_conditions = ["elb.year = ?"];
    $employee_params = [$year_filter];
    $employee_types = "i";
    
    if ($department_filter) {
        $employee_where_conditions[] = "e.department = ?";
        $employee_params[] = $department_filter;
        $employee_types .= "s";
    }
    
    if ($search) {
        $employee_where_conditions[] = "(e.first_name LIKE ? OR e.last_name LIKE ? OR e.employee_id LIKE ?)";
        $search_param = "%$search%";
        $employee_params[] = $search_param;
        $employee_params[] = $search_param;
        $employee_params[] = $search_param;
        $employee_types .= "sss";
    }
    
    $employee_where_clause = "WHERE " . implode(" AND ", $employee_where_conditions);
    
    $employee_query = "SELECT elb.*, 
                      e.first_name, e.last_name, e.employee_id, e.department, e.employee_type,
                      lt.name as leave_type_name, lt.description, lt.default_days_per_year,
                      'employee' as source_table
                      FROM employee_leave_balances elb
                      JOIN employees e ON elb.employee_id = e.id
                      JOIN leave_types lt ON elb.leave_type_id = lt.id
                      $employee_where_clause
                      ORDER BY e.last_name, e.first_name, lt.name";
    
    $employee_stmt = mysqli_prepare($conn, $employee_query);
    if ($employee_stmt) {
        mysqli_stmt_bind_param($employee_stmt, $employee_types, ...$employee_params);
        mysqli_stmt_execute($employee_stmt);
        $employee_result = mysqli_stmt_get_result($employee_stmt);
        
        while ($row = mysqli_fetch_assoc($employee_result)) {
            $employee_balances[] = $row;
        }
    }
}

// Get leave balances for faculty
$faculty_balances = [];
if (($employee_type_filter === 'all' || $employee_type_filter === 'faculty') && ($current_tab === 'faculty' || $current_tab === 'all')) {
    $faculty_where_conditions = ["flb.year = ?"];
    $faculty_params = [$year_filter];
    $faculty_types = "i";
    
    if ($department_filter) {
        $faculty_where_conditions[] = "f.department = ?";
        $faculty_params[] = $department_filter;
        $faculty_types .= "s";
    }
    
    if ($search) {
        $faculty_where_conditions[] = "(f.first_name LIKE ? OR f.last_name LIKE ? OR f.id LIKE ?)";
        $search_param = "%$search%";
        $faculty_params[] = $search_param;
        $faculty_params[] = $search_param;
        $faculty_params[] = $search_param;
        $faculty_types .= "sss";
    }
    
    $faculty_where_clause = "WHERE " . implode(" AND ", $faculty_where_conditions);
    
    $faculty_query = "SELECT flb.*, 
                     f.first_name, f.last_name, f.id as employee_id, f.department, 'faculty' as employee_type,
                     lt.name as leave_type_name, lt.description, lt.default_days_per_year,
                     'faculty' as source_table
                     FROM faculty_leave_balances flb
                     JOIN faculty f ON flb.faculty_id = f.id
                     JOIN leave_types lt ON flb.leave_type_id = lt.id
                     $faculty_where_clause
                     ORDER BY f.last_name, f.first_name, lt.name";
    
    $faculty_stmt = mysqli_prepare($conn, $faculty_query);
    if ($faculty_stmt) {
        mysqli_stmt_bind_param($faculty_stmt, $faculty_types, ...$faculty_params);
        mysqli_stmt_execute($faculty_stmt);
        $faculty_result = mysqli_stmt_get_result($faculty_stmt);
        
        while ($row = mysqli_fetch_assoc($faculty_result)) {
            $faculty_balances[] = $row;
        }
    }
}

// Combine all balances based on current tab
$all_balances = $current_tab === 'employees' ? $employee_balances : ($current_tab === 'faculty' ? $faculty_balances : array_merge($employee_balances, $faculty_balances));

// Set headers for Excel download
$filename = "leave_balances_" . $current_tab . "_" . $year_filter . "_" . date('Y-m-d_H-i-s') . ".csv";
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

// Create output stream
$output = fopen('php://output', 'w');

// Add UTF-8 BOM for proper Excel encoding
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Write headers
$headers = [
    'Employee Name',
    'Employee ID',
    'Employee Type',
    'Department',
    'Leave Type',
    'Year',
    'Total Days',
    'Used Days',
    'Remaining Days',
    'Usage Percentage',
    'Default Days Per Year',
    'Description'
];
fputcsv($output, $headers);

// Write data
foreach ($all_balances as $balance) {
    $usage_percentage = $balance['total_days'] > 0 ? ($balance['used_days'] / $balance['total_days']) * 100 : 0;
    
    $row = [
        $balance['first_name'] . ' ' . $balance['last_name'],
        $balance['employee_id'],
        ucfirst($balance['source_table']),
        $balance['department'],
        $balance['leave_type_name'],
        $balance['year'],
        $balance['total_days'],
        $balance['used_days'],
        $balance['total_days'] - $balance['used_days'],
        number_format($usage_percentage, 2) . '%',
        $balance['default_days_per_year'],
        $balance['description']
    ];
    
    fputcsv($output, $row);
}

// Close the file
fclose($output);
exit();
?>
