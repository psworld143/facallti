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
    if (empty($input['guest_id'])) {
        throw new Exception('Guest ID is required');
    }
    
    if (empty($input['feedback_type'])) {
        throw new Exception('Feedback type is required');
    }
    
    if (empty($input['category'])) {
        throw new Exception('Category is required');
    }
    
    if (empty($input['comments'])) {
        throw new Exception('Comments are required');
    }
    
    // Save feedback
    $result = saveFeedback($input);
    
    echo json_encode($result);
    
} catch (Exception $e) {
    error_log("Error saving feedback: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/**
 * Save guest feedback
 */
function saveFeedback($data) {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        // Get guest information
        $stmt = $pdo->prepare("SELECT first_name, last_name FROM guests WHERE id = ?");
        $stmt->execute([$data['guest_id']]);
        $guest = $stmt->fetch();
        
        if (!$guest) {
            throw new Exception('Guest not found');
        }
        
        // Insert feedback
        $stmt = $pdo->prepare("
            INSERT INTO guest_feedback (
                guest_id, reservation_id, feedback_type, category, 
                rating, comments, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $data['guest_id'],
            $data['reservation_id'] ?? null,
            $data['feedback_type'],
            $data['category'],
            $data['rating'] ?? null,
            $data['comments']
        ]);
        
        $feedback_id = $pdo->lastInsertId();
        
        // Create notification for managers if it's a complaint
        if ($data['feedback_type'] === 'complaint') {
            $stmt = $pdo->prepare("
                INSERT INTO notifications (user_id, title, message, type, created_at)
                SELECT id, ?, ?, 'warning', NOW()
                FROM users 
                WHERE role = 'manager' AND is_active = 1
            ");
            
            $notification_title = 'New Guest Complaint';
            $notification_message = "Complaint from {$guest['first_name']} {$guest['last_name']}: " . substr($data['comments'], 0, 100) . "...";
            
            $stmt->execute([$notification_title, $notification_message]);
        }
        
        // Log activity
        logActivity($_SESSION['user_id'], 'feedback_added', "Added {$data['feedback_type']} for guest {$guest['first_name']} {$guest['last_name']}");
        
        $pdo->commit();
        
        return [
            'success' => true,
            'message' => 'Feedback submitted successfully',
            'feedback_id' => $feedback_id
        ];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error saving feedback: " . $e->getMessage());
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}
?>
