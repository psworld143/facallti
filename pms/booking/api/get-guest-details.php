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
    $guest_id = $_GET['id'] ?? null;
    
    if (!$guest_id) {
        throw new Exception('Guest ID is required');
    }
    
    $guest_details = getGuestDetails($guest_id);
    
    if (!$guest_details) {
        throw new Exception('Guest not found');
    }
    
    echo json_encode([
        'success' => true,
        'guest' => $guest_details
    ]);
    
} catch (Exception $e) {
    error_log("Error getting guest details: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/**
 * Get detailed guest information
 */
function getGuestDetails($guest_id) {
    global $pdo;
    
    try {
        // Get basic guest information with stay statistics
        $stmt = $pdo->prepare("
            SELECT g.*, 
                   COUNT(DISTINCT r.id) as total_stays,
                   MAX(r.check_out_date) as last_stay,
                   SUM(r.total_amount) as total_spent,
                   AVG(r.total_amount) as avg_stay_amount
            FROM guests g
            LEFT JOIN reservations r ON g.id = r.guest_id
            WHERE g.id = ?
            GROUP BY g.id
        ");
        $stmt->execute([$guest_id]);
        $guest = $stmt->fetch();
        
        if (!$guest) {
            return null;
        }
        
        // Get recent reservations
        $stmt = $pdo->prepare("
            SELECT r.*, rm.room_number, rm.room_type
            FROM reservations r
            JOIN rooms rm ON r.room_id = rm.id
            WHERE r.guest_id = ?
            ORDER BY r.created_at DESC
            LIMIT 10
        ");
        $stmt->execute([$guest_id]);
        $guest['recent_reservations'] = $stmt->fetchAll();
        
        // Get feedback history
        $stmt = $pdo->prepare("
            SELECT gf.*, r.reservation_number
            FROM guest_feedback gf
            LEFT JOIN reservations r ON gf.reservation_id = r.id
            WHERE gf.guest_id = ?
            ORDER BY gf.created_at DESC
            LIMIT 10
        ");
        $stmt->execute([$guest_id]);
        $guest['feedback_history'] = $stmt->fetchAll();
        
        // Get service notes history (from special_requests in reservations)
        $stmt = $pdo->prepare("
            SELECT r.reservation_number, r.special_requests, r.created_at
            FROM reservations r
            WHERE r.guest_id = ? AND r.special_requests IS NOT NULL AND r.special_requests != ''
            ORDER BY r.created_at DESC
            LIMIT 10
        ");
        $stmt->execute([$guest_id]);
        $guest['service_notes_history'] = $stmt->fetchAll();
        
        return $guest;
        
    } catch (PDOException $e) {
        error_log("Error getting guest details: " . $e->getMessage());
        return null;
    }
}
?>
