<?php
session_start();
require_once '../../includes/error_handler.php';
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json');

try {
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
    $activities = getRecentActivities($limit);
    
    echo json_encode([
        'success' => true,
        'activities' => $activities
    ]);
} catch (Exception $e) {
    error_log("Error getting recent activities: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error retrieving activities'
    ]);
}
?>
