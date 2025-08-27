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
    // Get filters from query parameters
    $filters = [];
    if (isset($_GET['reservation_number'])) $filters['reservation_number'] = $_GET['reservation_number'];
    if (isset($_GET['guest_name'])) $filters['guest_name'] = $_GET['guest_name'];
    if (isset($_GET['status'])) $filters['status'] = $_GET['status'];
    
    $reservations = getAllReservations($filters);
    
    echo json_encode([
        'success' => true,
        'reservations' => $reservations,
        'has_data' => !empty($reservations)
    ]);
    
} catch (Exception $e) {
    error_log("Error getting reservations: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error retrieving reservations'
    ]);
}
?>
