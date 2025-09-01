<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once 'includes/employee_id_generator.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set content type to JSON
header('Content-Type: application/json');

// Test database connection
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

// Test employee ID generation
try {
    $test_employee_id = generateEmployeeID($conn);
    echo json_encode([
        'success' => true, 
        'message' => 'Database connection and employee ID generation working',
        'test_employee_id' => $test_employee_id,
        'database_error' => mysqli_error($conn)
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Error: ' . $e->getMessage(),
        'database_error' => mysqli_error($conn)
    ]);
}

mysqli_close($conn);
?>
