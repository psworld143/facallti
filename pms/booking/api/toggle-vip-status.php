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
    if (empty($input['guest_id'])) {
        throw new Exception('Guest ID is required');
    }
    
    if (!isset($input['is_vip'])) {
        throw new Exception('VIP status is required');
    }
    
    // Toggle VIP status
    $result = toggleVIPStatus($input['guest_id'], $input['is_vip']);
    
    echo json_encode($result);
    
} catch (Exception $e) {
    error_log("Error toggling VIP status: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/**
 * Toggle VIP status for a guest
 */
function toggleVIPStatus($guest_id, $is_vip) {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        // Get guest information
        $stmt = $pdo->prepare("SELECT first_name, last_name, is_vip FROM guests WHERE id = ?");
        $stmt->execute([$guest_id]);
        $guest = $stmt->fetch();
        
        if (!$guest) {
            throw new Exception('Guest not found');
        }
        
        // Update VIP status
        $stmt = $pdo->prepare("UPDATE guests SET is_vip = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$is_vip, $guest_id]);
        
        // Log activity
        $action = $is_vip ? 'vip_status_granted' : 'vip_status_removed';
        $message = $is_vip ? 'VIP status granted' : 'VIP status removed';
        logActivity($_SESSION['user_id'], $action, "{$message} for guest {$guest['first_name']} {$guest['last_name']}");
        
        // Create notification for managers if VIP status is granted
        if ($is_vip) {
            $stmt = $pdo->prepare("
                INSERT INTO notifications (user_id, title, message, type, created_at)
                SELECT id, ?, ?, 'info', NOW()
                FROM users 
                WHERE role = 'manager' AND is_active = 1
            ");
            
            $notification_title = 'New VIP Guest';
            $notification_message = "VIP status granted to {$guest['first_name']} {$guest['last_name']}";
            
            $stmt->execute([$notification_title, $notification_message]);
        }
        
        $pdo->commit();
        
        return [
            'success' => true,
            'message' => $message,
            'is_vip' => $is_vip
        ];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error toggling VIP status: " . $e->getMessage());
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}
?>
