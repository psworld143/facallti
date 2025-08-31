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
    
    if (empty($input['action'])) {
        throw new Exception('Resolution action is required');
    }
    
    // Resolve overbooking
    $result = resolveOverbooking($input['reservation_id'], $input['action'], $input['details'] ?? []);
    
    echo json_encode($result);
    
} catch (Exception $e) {
    error_log("Error resolving overbooking: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/**
 * Resolve overbooking scenarios
 */
function resolveOverbooking($reservation_id, $action, $details = []) {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        // Get reservation details
        $stmt = $pdo->prepare("
            SELECT r.*, g.first_name, g.last_name, g.phone, rm.room_number, rm.room_type
            FROM reservations r
            JOIN guests g ON r.guest_id = g.id
            JOIN rooms rm ON r.room_id = rm.id
            WHERE r.id = ?
        ");
        $stmt->execute([$reservation_id]);
        $reservation = $stmt->fetch();
        
        if (!$reservation) {
            throw new Exception('Reservation not found');
        }
        
        switch ($action) {
            case 'walk':
                $result = handleWalkGuest($reservation, $details);
                break;
                
            case 'upgrade':
                $result = handleUpgradeGuest($reservation, $details);
                break;
                
            case 'compensation':
                $result = handleCompensation($reservation, $details);
                break;
                
            default:
                throw new Exception('Invalid resolution action');
        }
        
        // Log the resolution
        logActivity($_SESSION['user_id'], 'overbooking_resolved', "Resolved overbooking for reservation {$reservation['reservation_number']} using action: {$action}");
        
        $pdo->commit();
        
        return [
            'success' => true,
            'message' => 'Overbooking resolved successfully',
            'action' => $action,
            'details' => $result
        ];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error resolving overbooking: " . $e->getMessage());
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}

/**
 * Handle walking a guest (finding alternative accommodation)
 */
function handleWalkGuest($reservation, $details) {
    global $pdo;
    
    // Update reservation status to indicate walk
    $stmt = $pdo->prepare("
        UPDATE reservations 
        SET status = 'walked', special_requests = CONCAT(COALESCE(special_requests, ''), ' | WALKED: ', ?)
        WHERE id = ?
    ");
    $walk_reason = $details['reason'] ?? 'Overbooking - alternative accommodation arranged';
    $stmt->execute([$walk_reason, $reservation['id']]);
    
    // Free up the room
    $stmt = $pdo->prepare("UPDATE rooms SET status = 'available' WHERE id = ?");
    $stmt->execute([$reservation['room_id']]);
    
    // Create notification for guest
    $stmt = $pdo->prepare("
        INSERT INTO notifications (user_id, title, message, type, created_at)
        VALUES (?, ?, ?, 'warning', NOW())
    ");
    $notification_title = 'Reservation Walked';
    $notification_message = "Reservation {$reservation['reservation_number']} for {$reservation['first_name']} {$reservation['last_name']} has been walked due to overbooking. Alternative accommodation: " . (isset($details['alternative_hotel']) ? $details['alternative_hotel'] : 'TBD');
    $stmt->execute([$_SESSION['user_id'], $notification_title, $notification_message]);
    
    return [
        'status' => 'walked',
        'alternative_hotel' => $details['alternative_hotel'] ?? 'TBD',
        'compensation' => $details['compensation'] ?? 0
    ];
}

/**
 * Handle upgrading a guest to resolve overbooking
 */
function handleUpgradeGuest($reservation, $details) {
    global $pdo;
    
    $upgrade_room_type = $details['upgrade_room_type'] ?? 'suite';
    
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
        throw new Exception("No available {$upgrade_room_type} rooms for upgrade");
    }
    
    // Calculate new total amount
    $nights = (strtotime($reservation['check_out_date']) - strtotime($reservation['check_in_date'])) / (60 * 60 * 24);
    $room_types = getRoomTypes();
    $upgrade_rate = $room_types[$upgrade_room_type]['rate'];
    $new_total_amount = $upgrade_rate * $nights * 1.1; // 10% tax
    
    // Update reservation with new room
    $stmt = $pdo->prepare("
        UPDATE reservations 
        SET room_id = ?, total_amount = ?, special_requests = CONCAT(COALESCE(special_requests, ''), ' | UPGRADED DUE TO OVERBOOKING')
        WHERE id = ?
    ");
    $stmt->execute([$upgrade_room['id'], $new_total_amount, $reservation['id']]);
    
    // Update room statuses
    $stmt = $pdo->prepare("UPDATE rooms SET status = 'reserved' WHERE id = ?");
    $stmt->execute([$upgrade_room['id']]);
    
    // Free up old room
    $stmt = $pdo->prepare("UPDATE rooms SET status = 'available' WHERE id = ?");
    $stmt->execute([$reservation['room_id']]);
    
    // Update billing
    $room_charges = $upgrade_rate * $nights;
    $tax_amount = $room_charges * 0.1;
    
    $stmt = $pdo->prepare("
        UPDATE billing 
        SET room_charges = ?, tax_amount = ?, total_amount = ?
        WHERE reservation_id = ?
    ");
    $stmt->execute([$room_charges, $tax_amount, $new_total_amount, $reservation['id']]);
    
    return [
        'status' => 'upgraded',
        'new_room_number' => $upgrade_room['room_number'],
        'new_room_type' => $upgrade_room['room_type'],
        'new_total_amount' => $new_total_amount,
        'complimentary' => $details['complimentary'] ?? true
    ];
}

/**
 * Handle compensation for overbooking
 */
function handleCompensation($reservation, $details) {
    global $pdo;
    
    $compensation_amount = $details['amount'] ?? 50.00;
    $compensation_type = $details['type'] ?? 'discount';
    
    // Add compensation to billing
    $stmt = $pdo->prepare("
        UPDATE billing 
        SET discount_amount = COALESCE(discount_amount, 0) + ?,
            total_amount = total_amount - ?
        WHERE reservation_id = ?
    ");
    $stmt->execute([$compensation_amount, $compensation_amount, $reservation['id']]);
    
    // Update reservation notes
    $stmt = $pdo->prepare("
        UPDATE reservations 
        SET special_requests = CONCAT(COALESCE(special_requests, ''), ' | COMPENSATION: $', ?, ' ', ?)
        WHERE id = ?
    ");
    $stmt->execute([$compensation_amount, $compensation_type, $reservation['id']]);
    
    // Create service charge record for tracking
    $stmt = $pdo->prepare("
        INSERT INTO service_charges (reservation_id, service_id, quantity, unit_price, total_price, notes, charged_by)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $reservation['id'],
        998, // Special service ID for compensation
        1,
        -$compensation_amount, // Negative amount for discount
        -$compensation_amount,
        "Overbooking compensation: {$compensation_type}",
        $_SESSION['user_id']
    ]);
    
    return [
        'status' => 'compensated',
        'compensation_amount' => $compensation_amount,
        'compensation_type' => $compensation_type
    ];
}
?>
