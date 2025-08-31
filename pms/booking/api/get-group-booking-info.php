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
    
    $group_info = getGroupBookingInfo($reservation_id);
    
    echo json_encode([
        'success' => true,
        'is_group_booking' => $group_info['is_group_booking'],
        'group_name' => $group_info['group_name'] ?? '',
        'group_size' => $group_info['group_size'] ?? 0,
        'group_discount' => $group_info['group_discount'] ?? 0,
        'details' => $group_info['details'] ?? []
    ]);
    
} catch (Exception $e) {
    error_log("Error getting group booking info: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/**
 * Get group booking information for a reservation
 */
function getGroupBookingInfo($reservation_id) {
    global $pdo;
    
    try {
        // Check if reservation is part of a group booking
        $stmt = $pdo->prepare("
            SELECT special_requests 
            FROM reservations 
            WHERE id = ?
        ");
        $stmt->execute([$reservation_id]);
        $reservation = $stmt->fetch();
        
        if (!$reservation) {
            return [
                'is_group_booking' => false,
                'group_name' => '',
                'group_size' => 0,
                'group_discount' => 0,
                'details' => []
            ];
        }
        
        // Check if reservation has group information
        if (strpos($reservation['special_requests'], 'GROUP:') === false) {
            return [
                'is_group_booking' => false,
                'group_name' => '',
                'group_size' => 0,
                'group_discount' => 0,
                'details' => []
            ];
        }
        
        // Extract group information from special requests
        preg_match('/GROUP: ([^(]+) \(Size: (\d+), Discount: (\d+)%\)/', $reservation['special_requests'], $matches);
        
        if (count($matches) >= 4) {
            $group_name = trim($matches[1]);
            $group_size = (int)$matches[2];
            $group_discount = (int)$matches[3];
            
            // Get additional group details
            $stmt = $pdo->prepare("
                SELECT gb.*, COUNT(gb2.id) as current_members
                FROM group_bookings gb
                LEFT JOIN group_bookings gb2 ON gb.group_name = gb2.group_name
                WHERE gb.reservation_id = ?
                GROUP BY gb.group_name
            ");
            $stmt->execute([$reservation_id]);
            $group_details = $stmt->fetch();
            
            return [
                'is_group_booking' => true,
                'group_name' => $group_name,
                'group_size' => $group_size,
                'group_discount' => $group_discount,
                'details' => $group_details ?: []
            ];
        }
        
        return [
            'is_group_booking' => false,
            'group_name' => '',
            'group_size' => 0,
            'group_discount' => 0,
            'details' => []
        ];
        
    } catch (Exception $e) {
        error_log("Error getting group booking info: " . $e->getMessage());
        return [
            'is_group_booking' => false,
            'group_name' => '',
            'group_size' => 0,
            'group_discount' => 0,
            'details' => []
        ];
    }
}
?>
