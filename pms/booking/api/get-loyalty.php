<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Check if user is logged in and has front desk access
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['front_desk', 'manager'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

try {
    $tier_filter = $_GET['tier'] ?? '';
    
    // Get loyalty data with filters
    $loyalty = getLoyalty($tier_filter);
    
    echo json_encode([
        'success' => true,
        'loyalty' => $loyalty
    ]);
    
} catch (Exception $e) {
    error_log("Error in get-loyalty.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error loading loyalty data: ' . $e->getMessage()
    ]);
}
?>
