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
    if (empty($input['reservation_id'])) {
        throw new Exception('Reservation ID is required');
    }
    
    if (empty($input['upgrade_room_type'])) {
        throw new Exception('Upgrade room type is required');
    }
    
    // Upgrade room
    $result = upgradeRoom(
        $input['reservation_id'], 
        $input['upgrade_room_type'], 
        $input['upgrade_reason'] ?? '',
        $input['charge_upgrade'] ?? false
    );
    
    echo json_encode($result);
    
} catch (Exception $e) {
    error_log("Error upgrading room: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/**
 * Upgrade room for a reservation
 */
function upgradeRoom($reservation_id, $upgrade_room_type, $reason = '', $charge_guest = false) {
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
            throw new Exception('Reservation not found or cannot be upgraded');
        }
        
        // Validate upgrade (new room type must be higher than current)
        $room_types = getRoomTypes();
        $current_rate = $room_types[$reservation['current_room_type']]['rate'];
        $upgrade_rate = $room_types[$upgrade_room_type]['rate'];
        
        if ($upgrade_rate <= $current_rate) {
            throw new Exception('Upgrade room type must have a higher rate than current room');
        }
        
        // Find available upgrade room
        $stmt = $pdo->prepare("
            SELECT id, room_number, room_type, rate 
            FROM rooms 
            WHERE room_type = ? AND status = 'available'
            ORDER BY room_number ASC
            LIMIT 1
        ");
        $stmt->execute([$upgrade_room_type]);
        $upgrade_room = $stmt->fetch();
        
        if (!$upgrade_room) {
            throw new Exception('No available rooms for upgrade');
        }
        
        // Calculate new total amount
        $nights = (strtotime($reservation['check_out_date']) - strtotime($reservation['check_in_date'])) / (60 * 60 * 24);
        $new_total_amount = $upgrade_rate * $nights * 1.1; // 10% tax
        
        // Calculate price difference
        $price_difference = ($upgrade_rate - $current_rate) * $nights;
        
        // Update reservation with new room
        $stmt = $pdo->prepare("
            UPDATE reservations 
            SET room_id = ?, total_amount = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$upgrade_room['id'], $new_total_amount, $reservation_id]);
        
        // Update room statuses
        $stmt = $pdo->prepare("UPDATE rooms SET status = 'reserved' WHERE id = ?");
        $stmt->execute([$upgrade_room['id']]);
        
        // Free up old room if it was occupied
        if ($reservation['status'] === 'checked_in') {
            $stmt = $pdo->prepare("UPDATE rooms SET status = 'available', housekeeping_status = 'dirty' WHERE id = ?");
            $stmt->execute([$reservation['room_id']]);
        } else {
            $stmt = $pdo->prepare("UPDATE rooms SET status = 'available' WHERE id = ?");
            $stmt->execute([$reservation['room_id']]);
        }
        
        // Update billing
        $room_charges = $upgrade_rate * $nights;
        $tax_amount = $room_charges * 0.1;
        
        $stmt = $pdo->prepare("
            UPDATE billing 
            SET room_charges = ?, tax_amount = ?, total_amount = ?
            WHERE reservation_id = ?
        ");
        $stmt->execute([$room_charges, $tax_amount, $new_total_amount, $reservation_id]);
        
        // Add upgrade charge if guest should be charged
        if ($charge_guest && $price_difference > 0) {
            $stmt = $pdo->prepare("
                INSERT INTO service_charges (reservation_id, service_id, quantity, unit_price, total_price, notes, charged_by)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            // Create a service charge for the upgrade
            $stmt->execute([
                $reservation_id,
                999, // Special service ID for upgrades
                1,
                $price_difference,
                $price_difference,
                "Room upgrade from {$reservation['current_room_type']} to {$upgrade_room_type}",
                $_SESSION['user_id']
            ]);
            
            // Update billing total to include upgrade charge
            $stmt = $pdo->prepare("
                UPDATE billing 
                SET additional_charges = COALESCE(additional_charges, 0) + ?,
                    total_amount = total_amount + ?
                WHERE reservation_id = ?
            ");
            $stmt->execute([$price_difference, $price_difference, $reservation_id]);
        }
        
        // Log activity
        $log_message = "Upgraded reservation {$reservation['reservation_number']} from {$reservation['current_room_type']} to {$upgrade_room_type}";
        if ($reason) {
            $log_message .= " (Reason: {$reason})";
        }
        if ($charge_guest) {
            $log_message .= " - Guest charged additional $" . number_format($price_difference, 2);
        } else {
            $log_message .= " - Complimentary upgrade";
        }
        logActivity($_SESSION['user_id'], 'room_upgraded', $log_message);
        
        $pdo->commit();
        
        return [
            'success' => true,
            'message' => 'Room upgraded successfully',
            'new_room_id' => $upgrade_room['id'],
            'new_total_amount' => $new_total_amount,
            'price_difference' => $price_difference,
            'charged_guest' => $charge_guest
        ];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error upgrading room: " . $e->getMessage());
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}
?>
