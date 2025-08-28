<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once 'includes/employee_id_generator.php';

// Check if user is logged in and has human_resource role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'human_resource') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Check if it's a GET request
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

try {
    $next_employee_id = generateEmployeeID($conn);
    
    if ($next_employee_id) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true, 
            'employee_id' => $next_employee_id,
            'message' => 'Employee ID generated successfully'
        ]);
    } else {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false, 
            'message' => 'Error generating employee ID'
        ]);
    }
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false, 
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>
