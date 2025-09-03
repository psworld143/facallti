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
    $reservation_id = $_GET['id'] ?? null;
    
    if (!$reservation_id) {
        throw new Exception('Reservation ID is required');
    }
    
    $reservation = getReservationDetails($reservation_id);
    
    if (!$reservation) {
        throw new Exception('Reservation not found');
    }
    
    echo json_encode([
        'success' => true,
        'reservation' => $reservation
    ]);
    
} catch (Exception $e) {
    error_log("Error getting reservation details: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
