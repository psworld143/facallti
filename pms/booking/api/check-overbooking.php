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
    $reservation_id = $_GET['reservation_id'] ?? null;
    
    if (!$reservation_id) {
        throw new Exception('Reservation ID is required');
    }
    
    $overbooking_info = checkOverbookingStatus($reservation_id);
    
    echo json_encode([
        'success' => true,
        'is_overbooked' => $overbooking_info['is_overbooked'],
        'message' => $overbooking_info['message'],
        'details' => $overbooking_info['details']
    ]);
    
} catch (Exception $e) {
    error_log("Error checking overbooking: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/**
 * Check overbooking status for a reservation
 */
function checkOverbookingStatus($reservation_id) {
    global $pdo;
    
    try {
        // Get reservation details
        $stmt = $pdo->prepare("
            SELECT r.*, rm.room_number, rm.room_type, rm.status as room_status
            FROM reservations r
            JOIN rooms rm ON r.room_id = rm.id
            WHERE r.id = ?
        ");
        $stmt->execute([$reservation_id]);
        $reservation = $stmt->fetch();
        
        if (!$reservation) {
            return [
                'is_overbooked' => false,
                'message' => 'Reservation not found',
                'details' => []
            ];
        }
        
        $overbooking_issues = [];
        
        // Check if room is actually available
        if ($reservation['room_status'] !== 'available' && $reservation['room_status'] !== 'reserved') {
            $overbooking_issues[] = "Room {$reservation['room_number']} is currently {$reservation['room_status']}";
        }
        
        // Check for double booking (multiple reservations for same room on same dates)
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count, GROUP_CONCAT(r.reservation_number) as reservation_numbers
            FROM reservations r
            WHERE r.room_id = ? 
            AND r.id != ?
            AND r.status IN ('confirmed', 'checked_in')
            AND (
                (r.check_in_date <= ? AND r.check_out_date > ?) OR
                (r.check_in_date < ? AND r.check_out_date >= ?) OR
                (r.check_in_date >= ? AND r.check_out_date <= ?)
            )
        ");
        $stmt->execute([
            $reservation['room_id'],
            $reservation_id,
            $reservation['check_in_date'],
            $reservation['check_in_date'],
            $reservation['check_out_date'],
            $reservation['check_out_date'],
            $reservation['check_in_date'],
            $reservation['check_out_date']
        ]);
        $double_booking = $stmt->fetch();
        
        if ($double_booking['count'] > 0) {
            $overbooking_issues[] = "Double booking detected with reservations: {$double_booking['reservation_numbers']}";
        }
        
        // Check if room type availability matches reservation
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as available_count
            FROM rooms
            WHERE room_type = ? AND status = 'available'
        ");
        $stmt->execute([$reservation['room_type']]);
        $available_rooms = $stmt->fetch()['available_count'];
        
        if ($available_rooms == 0) {
            $overbooking_issues[] = "No available {$reservation['room_type']} rooms";
        }
        
        // Check for maintenance issues
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as maintenance_count
            FROM maintenance_requests
            WHERE room_id = ? AND status IN ('reported', 'assigned', 'in_progress')
        ");
        $stmt->execute([$reservation['room_id']]);
        $maintenance_issues = $stmt->fetch()['maintenance_count'];
        
        if ($maintenance_issues > 0) {
            $overbooking_issues[] = "Room has pending maintenance requests";
        }
        
        // Check housekeeping status
        if ($reservation['room_status'] === 'available' && $reservation['housekeeping_status'] === 'dirty') {
            $overbooking_issues[] = "Room needs housekeeping before guest arrival";
        }
        
        $is_overbooked = count($overbooking_issues) > 0;
        $message = $is_overbooked ? 
            'Overbooking issues detected: ' . implode(', ', $overbooking_issues) :
            'No overbooking issues detected';
        
        return [
            'is_overbooked' => $is_overbooked,
            'message' => $message,
            'details' => $overbooking_issues
        ];
        
    } catch (Exception $e) {
        error_log("Error checking overbooking status: " . $e->getMessage());
        return [
            'is_overbooked' => false,
            'message' => 'Error checking overbooking status',
            'details' => []
        ];
    }
}
?>
