<?php
session_start();
require_once '../../includes/error_handler.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json');

try {
    $limit = $_GET['limit'] ?? 10;
    $tasks = getRecentHousekeepingTasks($limit);
    
    echo json_encode([
        'success' => true,
        'tasks' => $tasks
    ]);
    
} catch (Exception $e) {
    error_log("Error getting recent housekeeping tasks: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error retrieving recent tasks'
    ]);
}
?>
