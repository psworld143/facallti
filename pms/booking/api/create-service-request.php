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
    if (empty($input['room_id'])) {
        throw new Exception('Room is required');
    }
    
    if (empty($input['request_type'])) {
        throw new Exception('Request type is required');
    }
    
    if (empty($input['priority'])) {
        throw new Exception('Priority is required');
    }
    
    if (empty($input['description'])) {
        throw new Exception('Description is required');
    }
    
    // Create service request
    $result = createServiceRequest($input);
    
    echo json_encode($result);
    
} catch (Exception $e) {
    error_log("Error creating service request: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/**
 * Create service request
 */
function createServiceRequest($data) {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        // Insert service request
        $stmt = $pdo->prepare("
            INSERT INTO maintenance_requests (
                room_id, request_type, priority, description, 
                special_instructions, status, created_by, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, 'pending', ?, NOW(), NOW())
        ");
        
        $stmt->execute([
            $data['room_id'],
            $data['request_type'],
            $data['priority'],
            $data['description'],
            $data['special_instructions'] ?? '',
            $_SESSION['user_id']
        ]);
        
        $request_id = $pdo->lastInsertId();
        
        // Log activity
        logActivity($_SESSION['user_id'], 'service_request_created', "Created service request #{$request_id}");
        
        $pdo->commit();
        
        return [
            'success' => true,
            'message' => 'Service request created successfully',
            'request_id' => $request_id
        ];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error creating service request: " . $e->getMessage());
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}
?>
