<?php
session_start();
require_once '../../includes/error_handler.php';
require_once '../includes/database.php';
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
    
    if (empty($input['new_room_type'])) {
        throw new Exception('New room type is required');
    }
    
    // Transfer room
    $result = transferRoom($input['reservation_id'], $input['new_room_type'], $input['new_room_id'] ?? null, $input['transfer_reason'] ?? '');
    
    echo json_encode($result);
    
} catch (Exception $e) {
    error_log("Error transferring room: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/**
 * Transfer room for a reservation
 */
function transferRoom($reservation_id, $new_room_type, $specific_room_id = null, $reason = '') {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        // Get current reservation details
        $stmt = $pdo->prepare("
            SELECT r.*, g.first_name, g.last_name, rm.room_number as current_room_number, rm.room_type as current_room_type
            FROM reservations r
            JOIN guests g ON r.guest_id = g.id
            JOIN rooms rm ON r.room_id = rm.id
            WHERE r.id = ? AND r.status IN ('confirmed', 'checked_in')
        ");
        $stmt->execute([$reservation_id]);
        $reservation = $stmt->fetch();
        
        if (!$reservation) {
            throw new Exception('Reservation not found or cannot be transferred');
        }
        
        // Find new room
        $new_room_id = null;
        if ($specific_room_id) {
            // Use specific room if provided
            $stmt = $pdo->prepare("
                SELECT id, room_number, room_type, rate, status 
                FROM rooms 
                WHERE id = ? AND room_type = ? AND status = 'available'
            ");
            $stmt->execute([$specific_room_id, $new_room_type]);
            $new_room = $stmt->fetch();
            
            if (!$new_room) {
                throw new Exception('Selected room is not available');
            }
            $new_room_id = $new_room['id'];
        } else {
            // Find best available room
            $stmt = $pdo->prepare("
                SELECT id, room_number, room_type, rate 
                FROM rooms 
                WHERE room_type = ? AND status = 'available'
                ORDER BY room_number ASC
                LIMIT 1
            ");
            $stmt->execute([$new_room_type]);
            $new_room = $stmt->fetch();
            
            if (!$new_room) {
                throw new Exception('No available rooms of the selected type');
            }
            $new_room_id = $new_room['id'];
        }
        
        // Calculate new total amount
        $nights = (strtotime($reservation['check_out_date']) - strtotime($reservation['check_in_date'])) / (60 * 60 * 24);
        $room_types = getRoomTypes();
        $new_room_rate = $room_types[$new_room_type]['rate'];
        $new_total_amount = $new_room_rate * $nights * 1.1; // 10% tax
        
        // Update reservation with new room
        $stmt = $pdo->prepare("
            UPDATE reservations 
            SET room_id = ?, total_amount = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$new_room_id, $new_total_amount, $reservation_id]);
        
        // Update room statuses
        $stmt = $pdo->prepare("UPDATE rooms SET status = 'reserved' WHERE id = ?");
        $stmt->execute([$new_room_id]);
        
        // Free up old room if it was occupied
        if ($reservation['status'] === 'checked_in') {
            $stmt = $pdo->prepare("UPDATE rooms SET status = 'available', housekeeping_status = 'dirty' WHERE id = ?");
            $stmt->execute([$reservation['room_id']]);
        } else {
            $stmt = $pdo->prepare("UPDATE rooms SET status = 'available' WHERE id = ?");
            $stmt->execute([$reservation['room_id']]);
        }
        
        // Update billing
        $room_charges = $new_room_rate * $nights;
        $tax_amount = $room_charges * 0.1;
        
        $stmt = $pdo->prepare("
            UPDATE billing 
            SET room_charges = ?, tax_amount = ?, total_amount = ?
            WHERE reservation_id = ?
        ");
        $stmt->execute([$room_charges, $tax_amount, $new_total_amount, $reservation_id]);
        
        // Log activity
        $log_message = "Transferred reservation {$reservation['reservation_number']} from room {$reservation['current_room_number']} to new room";
        if ($reason) {
            $log_message .= " (Reason: {$reason})";
        }
        logActivity($_SESSION['user_id'], 'room_transferred', $log_message);
        
        $pdo->commit();
        
        return [
            'success' => true,
            'message' => 'Room transferred successfully',
            'new_room_id' => $new_room_id,
            'new_total_amount' => $new_total_amount
        ];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error transferring room: " . $e->getMessage());
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}
?>
