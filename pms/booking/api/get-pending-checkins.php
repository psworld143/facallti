<?php
session_start();
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
    $reservations = getPendingCheckins();
    
    echo json_encode([
        'success' => true,
        'reservations' => $reservations
    ]);
    
} catch (Exception $e) {
    error_log("Error getting pending check-ins: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error retrieving pending check-ins'
    ]);
}
?>
