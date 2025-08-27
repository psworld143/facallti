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
        error_log("Service Request Details API - Unauthorized access attempt from: " . $referer);
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit();
    }
    // Allow access if coming from same domain (temporary fix for session issues)
}

try {
    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
        throw new Exception('Invalid request ID');
    }

    $requestId = (int)$_GET['id'];
    
    $query = "
        SELECT 
            mr.*,
            r.room_number,
            r.room_type,
            CONCAT(g.first_name, ' ', g.last_name) as guest_name,
            g.email as guest_email,
            g.phone as guest_phone,
            u1.name as reported_by_name,
            u1.role as reported_by_role,
            u2.name as assigned_to_name,
            u2.role as assigned_to_role
        FROM maintenance_requests mr
        JOIN rooms r ON mr.room_id = r.id
        LEFT JOIN reservations res ON r.id = res.room_id
        LEFT JOIN guests g ON res.guest_id = g.id
        JOIN users u1 ON mr.reported_by = u1.id
        LEFT JOIN users u2 ON mr.assigned_to = u2.id
        WHERE mr.id = ?
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$requestId]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$request) {
        throw new Exception('Service request not found');
    }
    
    // Get status history (if available)
    $statusHistory = [
        [
            'status' => $request['status'],
            'timestamp' => $request['updated_at'],
            'description' => 'Current status'
        ]
    ];
    
    // Add estimated vs actual cost comparison
    $costComparison = [
        'estimated' => $request['estimated_cost'] ? floatval($request['estimated_cost']) : 0,
        'actual' => $request['actual_cost'] ? floatval($request['actual_cost']) : 0,
        'difference' => ($request['actual_cost'] ? floatval($request['actual_cost']) : 0) - ($request['estimated_cost'] ? floatval($request['estimated_cost']) : 0)
    ];
    
    $response = [
        'success' => true,
        'request' => $request,
        'status_history' => $statusHistory,
        'cost_comparison' => $costComparison
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log("Error fetching service request details: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching service request details: ' . $e->getMessage()
    ]);
}
?>
