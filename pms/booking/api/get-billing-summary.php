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
    $reservation_id = $_GET['reservation_id'] ?? null;
    
    if (!$reservation_id) {
        throw new Exception('Reservation ID is required');
    }
    
    $billing = getBillingSummary($reservation_id);
    
    if (!$billing) {
        throw new Exception('Billing information not found');
    }
    
    echo json_encode([
        'success' => true,
        'billing' => $billing
    ]);
    
} catch (Exception $e) {
    error_log("Error getting billing summary: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/**
 * Get billing summary for a reservation
 */
function getBillingSummary($reservation_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT b.*, 
                   COALESCE(b.additional_charges, 0) as additional_charges,
                   COALESCE(b.room_charges, 0) as room_charges,
                   COALESCE(b.tax_amount, 0) as tax_amount,
                   COALESCE(b.total_amount, 0) as total_amount,
                   COALESCE(b.payment_status, 'pending') as payment_status
            FROM billing b
            WHERE b.reservation_id = ?
        ");
        $stmt->execute([$reservation_id]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Error getting billing summary: " . $e->getMessage());
        return null;
    }
}
?>
