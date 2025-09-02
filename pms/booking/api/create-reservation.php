<?php
session_start();
require_once '../../includes/error_handler.php';
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
    $required_fields = [
        'first_name', 'last_name', 'phone', 'id_type', 'id_number',
        'check_in_date', 'check_out_date', 'adults', 'room_type', 'booking_source'
    ];
    
    foreach ($required_fields as $field) {
        if (empty($input[$field])) {
            throw new Exception("Missing required field: {$field}");
        }
    }
    
    // Validate dates
    $check_in_date = new DateTime($input['check_in_date']);
    $check_out_date = new DateTime($input['check_out_date']);
    $today = new DateTime();
    $today->setTime(0, 0, 0);
    
    if ($check_in_date < $today) {
        throw new Exception('Check-in date cannot be in the past');
    }
    
    if ($check_out_date <= $check_in_date) {
        throw new Exception('Check-out date must be after check-in date');
    }
    
    // Validate room type
    $room_types = getRoomTypes();
    if (!isset($room_types[$input['room_type']])) {
        throw new Exception('Invalid room type');
    }
    
    // Validate adults count
    if ($input['adults'] < 1 || $input['adults'] > 10) {
        throw new Exception('Invalid number of adults');
    }
    
    // Validate children count
    if (isset($input['children']) && ($input['children'] < 0 || $input['children'] > 10)) {
        throw new Exception('Invalid number of children');
    }
    
    // Create reservation
    $result = createReservation($input);
    
    if ($result['success']) {
        echo json_encode([
            'success' => true,
            'reservation_id' => $result['reservation_id'],
            'reservation_number' => $result['reservation_number'],
            'message' => 'Reservation created successfully'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => $result['message']
        ]);
    }
    
} catch (Exception $e) {
    error_log("Error creating reservation: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
