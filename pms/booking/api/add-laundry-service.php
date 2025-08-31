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
    if (empty($input['laundry_reservation_id'])) {
        throw new Exception('Reservation is required');
    }
    
    if (empty($input['laundry_service_type'])) {
        throw new Exception('Service type is required');
    }
    
    // Add laundry service
    $result = addLaundryService($input);
    
    echo json_encode($result);
    
} catch (Exception $e) {
    error_log("Error adding laundry service: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/**
 * Add laundry service
 */
function addLaundryService($data) {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        // Get service pricing based on type
        $service_pricing = [
            'dry_clean' => 15.00,
            'wash_and_press' => 12.00,
            'press_only' => 8.00,
            'express' => 20.00
        ];
        
        $unit_price = $service_pricing[$data['laundry_service_type']] ?? 10.00;
        $quantity = intval($data['laundry_quantity'] ?? 1);
        $total_amount = $quantity * $unit_price;
        
        // Get service name
        $service_names = [
            'dry_clean' => 'Dry Cleaning',
            'wash_and_press' => 'Wash & Press',
            'press_only' => 'Press Only',
            'express' => 'Express Laundry'
        ];
        
        $service_name = $service_names[$data['laundry_service_type']] ?? 'Laundry Service';
        
        // Insert service charge
        $stmt = $pdo->prepare("
            INSERT INTO service_charges (
                reservation_id, service_category, service_name, 
                quantity, unit_price, total_amount, description, 
                status, created_by, created_at
            ) VALUES (?, 'laundry', ?, ?, ?, ?, ?, 'pending', ?, NOW())
        ");
        
        $description = "Laundry service: {$service_name}";
        if (!empty($data['laundry_instructions'])) {
            $description .= " - " . $data['laundry_instructions'];
        }
        
        $stmt->execute([
            $data['laundry_reservation_id'],
            $service_name,
            $quantity,
            $unit_price,
            $total_amount,
            $description,
            $_SESSION['user_id']
        ]);
        
        $service_id = $pdo->lastInsertId();
        
        // Log activity
        logActivity($_SESSION['user_id'], 'laundry_service_added', "Added {$service_name} service");
        
        $pdo->commit();
        
        return [
            'success' => true,
            'message' => 'Laundry service added successfully',
            'service_id' => $service_id,
            'total_amount' => $total_amount
        ];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error adding laundry service: " . $e->getMessage());
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}
?>
