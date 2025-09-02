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

// Get leave types
$leave_types_query = "SELECT id, name, description, default_days_per_year 
                      FROM leave_types 
                      WHERE is_active = 1 
                      ORDER BY name";
$leave_types_result = mysqli_query($conn, $leave_types_query);

$leave_types = [];
if ($leave_types_result) {
    while ($row = mysqli_fetch_assoc($leave_types_result)) {
        $leave_types[] = $row;
    }
}

header('Content-Type: application/json');
echo json_encode($leave_types);
?>
