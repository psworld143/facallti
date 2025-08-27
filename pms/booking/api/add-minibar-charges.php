<?php
session_start();
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
    if (empty($input['minibar_room_id'])) {
        throw new Exception('Room is required');
    }
    
    if (empty($input['minibar_items']) || !is_array($input['minibar_items'])) {
        throw new Exception('Minibar items are required');
    }
    
    // Add minibar charges
    $result = addMinibarCharges($input);
    
    echo json_encode($result);
    
} catch (Exception $e) {
    error_log("Error adding minibar charges: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/**
 * Add minibar charges
 */
function addMinibarCharges($data) {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        // Get room's current reservation
        $stmt = $pdo->prepare("
            SELECT r.id, r.reservation_number
            FROM reservations r
            WHERE r.room_id = ? AND r.status = 'checked_in'
            ORDER BY r.check_in_date DESC
            LIMIT 1
        ");
        $stmt->execute([$data['minibar_room_id']]);
        $reservation = $stmt->fetch();
        
        if (!$reservation) {
            throw new Exception('No active reservation found for this room');
        }
        
        $total_amount = 0;
        $items_added = 0;
        
        // Process each minibar item
        foreach ($data['minibar_items'] as $item) {
            if ($item['quantity'] > 0) {
                $item_total = $item['quantity'] * $item['unit_price'];
                $total_amount += $item_total;
                
                // Insert service charge for this item
                $stmt = $pdo->prepare("
                    INSERT INTO service_charges (
                        reservation_id, service_category, service_name, 
                        quantity, unit_price, total_amount, description, 
                        status, created_by, created_at
                    ) VALUES (?, 'minibar', ?, ?, ?, ?, ?, 'pending', ?, NOW())
                ");
                
                $stmt->execute([
                    $reservation['id'],
                    $item['name'] ?? 'Minibar Item',
                    $item['quantity'],
                    $item['unit_price'],
                    $item_total,
                    "Minibar charge for room",
                    $_SESSION['user_id']
                ]);
                
                $items_added++;
            }
        }
        
        if ($items_added === 0) {
            throw new Exception('No valid minibar items to add');
        }
        
        // Log activity
        logActivity($_SESSION['user_id'], 'minibar_charges_added', "Added minibar charges for room {$data['minibar_room_id']}");
        
        $pdo->commit();
        
        return [
            'success' => true,
            'message' => 'Minibar charges added successfully',
            'total_amount' => $total_amount,
            'items_added' => $items_added
        ];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error adding minibar charges: " . $e->getMessage());
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}
?>
