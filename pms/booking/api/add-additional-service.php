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
        throw new Exception('Reservation is required');
    }
    
    if (empty($input['service_category'])) {
        throw new Exception('Service category is required');
    }
    
    if (empty($input['service_name'])) {
        throw new Exception('Service name is required');
    }
    
    if (empty($input['unit_price'])) {
        throw new Exception('Unit price is required');
    }
    
    // Add additional service
    $result = addAdditionalService($input);
    
    echo json_encode($result);
    
} catch (Exception $e) {
    error_log("Error adding additional service: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/**
 * Add additional service
 */
function addAdditionalService($data) {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        // Calculate total amount
        $quantity = intval($data['quantity'] ?? 1);
        $unit_price = floatval($data['unit_price']);
        $total_amount = $quantity * $unit_price;
        
        // Insert service charge
        $stmt = $pdo->prepare("
            INSERT INTO service_charges (
                reservation_id, service_category, service_name, 
                quantity, unit_price, total_amount, description, 
                status, created_by, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', ?, NOW())
        ");
        
        $stmt->execute([
            $data['reservation_id'],
            $data['service_category'],
            $data['service_name'],
            $quantity,
            $unit_price,
            $total_amount,
            $data['description'] ?? '',
            $_SESSION['user_id']
        ]);
        
        $service_id = $pdo->lastInsertId();
        
        // Log activity
        logActivity($_SESSION['user_id'], 'additional_service_added', "Added {$data['service_name']} service");
        
        $pdo->commit();
        
        return [
            'success' => true,
            'message' => 'Service added successfully',
            'service_id' => $service_id,
            'total_amount' => $total_amount
        ];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error adding additional service: " . $e->getMessage());
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}
?>
