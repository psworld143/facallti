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
    
    if (empty($input['group_name'])) {
        throw new Exception('Group name is required');
    }
    
    if (empty($input['group_size']) || $input['group_size'] < 2) {
        throw new Exception('Group size must be at least 2');
    }
    
    // Add to group booking
    $result = addToGroupBooking(
        $input['reservation_id'],
        $input['group_name'],
        $input['group_size'],
        $input['group_discount'] ?? 10
    );
    
    echo json_encode($result);
    
} catch (Exception $e) {
    error_log("Error adding to group booking: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/**
 * Add reservation to group booking
 */
function addToGroupBooking($reservation_id, $group_name, $group_size, $group_discount = 10) {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        // Get reservation details
        $stmt = $pdo->prepare("
            SELECT r.*, g.first_name, g.last_name, rm.room_number, rm.room_type
            FROM reservations r
            JOIN guests g ON r.guest_id = g.id
            JOIN rooms rm ON r.room_id = rm.id
            WHERE r.id = ? AND r.status IN ('confirmed', 'checked_in')
        ");
        $stmt->execute([$reservation_id]);
        $reservation = $stmt->fetch();
        
        if (!$reservation) {
            throw new Exception('Reservation not found or cannot be added to group');
        }
        
        // Check if reservation is already part of a group
        if (strpos($reservation['special_requests'], 'GROUP:') !== false) {
            throw new Exception('Reservation is already part of a group booking');
        }
        
        // Create or find group booking
        $group_id = createOrFindGroupBooking($group_name, $group_size, $group_discount);
        
        // Calculate discount amount
        $discount_amount = ($reservation['total_amount'] * $group_discount) / 100;
        $new_total_amount = $reservation['total_amount'] - $discount_amount;
        
        // Update reservation with group information
        $stmt = $pdo->prepare("
            UPDATE reservations 
            SET total_amount = ?, special_requests = CONCAT(COALESCE(special_requests, ''), ' | GROUP: ', ?)
            WHERE id = ?
        ");
        $group_info = "{$group_name} (Size: {$group_size}, Discount: {$group_discount}%)";
        $stmt->execute([$new_total_amount, $group_info, $reservation_id]);
        
        // Update billing with discount
        $stmt = $pdo->prepare("
            UPDATE billing 
            SET discount_amount = COALESCE(discount_amount, 0) + ?,
                total_amount = total_amount - ?
            WHERE reservation_id = ?
        ");
        $stmt->execute([$discount_amount, $discount_amount, $reservation_id]);
        
        // Add group booking record
        $stmt = $pdo->prepare("
            INSERT INTO group_bookings (group_id, reservation_id, guest_name, room_number, discount_amount, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $guest_name = $reservation['first_name'] . ' ' . $reservation['last_name'];
        $stmt->execute([$group_id, $reservation_id, $guest_name, $reservation['room_number'], $discount_amount]);
        
        // Log activity
        logActivity($_SESSION['user_id'], 'group_booking_added', "Added reservation {$reservation['reservation_number']} to group booking: {$group_name}");
        
        $pdo->commit();
        
        return [
            'success' => true,
            'message' => 'Added to group booking successfully',
            'group_id' => $group_id,
            'group_name' => $group_name,
            'discount_amount' => $discount_amount,
            'new_total_amount' => $new_total_amount
        ];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error adding to group booking: " . $e->getMessage());
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}

/**
 * Create or find group booking
 */
function createOrFindGroupBooking($group_name, $group_size, $group_discount) {
    global $pdo;
    
    // Check if group already exists
    $stmt = $pdo->prepare("
        SELECT id FROM group_bookings 
        WHERE group_name = ? 
        LIMIT 1
    ");
    $stmt->execute([$group_name]);
    $existing_group = $stmt->fetch();
    
    if ($existing_group) {
        return $existing_group['id'];
    }
    
    // Create new group booking
    $stmt = $pdo->prepare("
        INSERT INTO group_bookings (group_name, group_size, group_discount, created_by, created_at)
        VALUES (?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$group_name, $group_size, $group_discount, $_SESSION['user_id']]);
    
    return $pdo->lastInsertId();
}
?>
