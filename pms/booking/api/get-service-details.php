<?php
require_once '../includes/session-config.php';
session_start();
require_once '../includes/config.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // Check if request is from same domain (basic security)
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    if (strpos($referer, 'localhost/seait/pms/booking') === false) {
        error_log("Service Details API - Unauthorized access attempt from: " . $referer);
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit();
    }
    // Allow access if coming from same domain (temporary fix for session issues)
}

try {
    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
        throw new Exception('Invalid service ID');
    }

    $serviceId = (int)$_GET['id'];
    
    $query = "
        SELECT 
            sc.*,
            additional_services.name as service_name,
            additional_services.description as service_description,
            additional_services.category as service_category,
            additional_services.price as service_price,
            CONCAT(g.first_name, ' ', g.last_name) as guest_name,
            g.email as guest_email,
            g.phone as guest_phone,
            r.room_number,
            r.room_type,
            res.check_in_date,
            res.check_out_date,
            u.name as charged_by_name,
            u.role as charged_by_role
        FROM service_charges sc
        JOIN additional_services ON sc.service_id = additional_services.id
        JOIN reservations res ON sc.reservation_id = res.id
        JOIN guests g ON res.guest_id = g.id
        JOIN rooms r ON res.room_id = r.id
        JOIN users u ON sc.charged_by = u.id
        WHERE sc.id = ?
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$serviceId]);
    $service = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$service) {
        throw new Exception('Service not found');
    }
    
    // Calculate service metrics
    $metrics = [
        'total_amount' => floatval($service['total_price']),
        'unit_price' => floatval($service['unit_price']),
        'quantity' => intval($service['quantity']),
        'calculated_total' => floatval($service['unit_price']) * intval($service['quantity'])
    ];
    
    // Get similar services for this guest
    $similarQuery = "
        SELECT 
            sc.id,
            additional_services.name as service_name,
            sc.quantity,
            sc.total_price,
            sc.created_at
        FROM service_charges sc
        JOIN additional_services ON sc.service_id = additional_services.id
        WHERE sc.reservation_id = ? AND sc.id != ?
        ORDER BY sc.created_at DESC
        LIMIT 5
    ";
    
    $stmt = $pdo->prepare($similarQuery);
    $stmt->execute([$service['reservation_id'], $serviceId]);
    $similarServices = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $response = [
        'success' => true,
        'service' => $service,
        'metrics' => $metrics,
        'similar_services' => $similarServices
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log("Error fetching service details: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching service details: ' . $e->getMessage()
    ]);
}
?>
