<?php
require_once '../includes/session-config.php';
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // Check if request is from same domain (basic security)
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    if (strpos($referer, 'localhost/seait/pms/booking') === false) {
        error_log("Additional Services API - Unauthorized access attempt from: " . $referer);
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit();
    }
    // Allow access if coming from same domain (temporary fix for session issues)
}

header('Content-Type: application/json');

try {
    // Get filter parameters
    $category_filter = $_GET['category'] ?? '';
    
    $services = getAdditionalServices($category_filter);
    
    echo json_encode([
        'success' => true,
        'services' => $services
    ]);
    
} catch (Exception $e) {
    error_log("Error getting additional services: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
