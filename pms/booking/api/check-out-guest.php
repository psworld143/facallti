<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Check if user is logged in and has front desk access
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['front_desk', 'manager'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Invalid input data');
    }
    
    // Validate required fields
    if (empty($input['reservation_id'])) {
        throw new Exception('Reservation ID is required');
    }
    
    if (!isset($input['room_key_returned'])) {
        throw new Exception('Room key returned status is required');
    }
    
    if (empty($input['payment_status'])) {
        throw new Exception('Payment status is required');
    }
    
    // Validate payment status
    $valid_payment_statuses = ['paid', 'pending', 'partial'];
    if (!in_array($input['payment_status'], $valid_payment_statuses)) {
        throw new Exception('Invalid payment status');
    }
    
    // Check out guest
    $result = checkOutGuest($input['reservation_id'], $input);
    
    echo json_encode($result);
    
} catch (Exception $e) {
    error_log("Error checking out guest: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
