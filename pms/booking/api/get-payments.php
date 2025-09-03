<?php
session_start();
require_once '../../includes/error_handler.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and has front desk access
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['front_desk', 'manager'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

try {
    $method_filter = $_GET['method'] ?? '';
    $date_filter = $_GET['date'] ?? '';
    
    // Get payments with filters
    $payments = getPayments($method_filter, $date_filter);
    
    echo json_encode([
        'success' => true,
        'payments' => $payments
    ]);
    
} catch (Exception $e) {
    error_log("Error in get-payments.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error loading payments: ' . $e->getMessage()
    ]);
}
?>
