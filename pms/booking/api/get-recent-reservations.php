<?php
session_start();
require_once '../../includes/error_handler.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Check if user has front desk access
if (!in_array($_SESSION['user_role'], ['front_desk', 'manager'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit();
}

try {
    // Get recent reservations (last 10)
    $stmt = $pdo->prepare("
        SELECT 
            r.id,
            r.reservation_number,
            r.check_in_date,
            r.check_out_date,
            r.status,
            r.created_at,
            g.first_name,
            g.last_name,
            g.email,
            g.phone,
            rm.room_number,
            rm.room_type,
            rm.rate
        FROM reservations r
        JOIN guests g ON r.guest_id = g.id
        JOIN rooms rm ON r.room_id = rm.id
        ORDER BY r.created_at DESC
        LIMIT 10
    ");
    $stmt->execute();
    $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format the data for display
    $formatted_reservations = [];
    foreach ($reservations as $reservation) {
        $formatted_reservations[] = [
            'id' => $reservation['id'],
            'reservation_number' => $reservation['reservation_number'],
            'guest_name' => $reservation['first_name'] . ' ' . $reservation['last_name'],
            'guest_email' => $reservation['email'],
            'guest_phone' => $reservation['phone'],
            'room_number' => $reservation['room_number'],
            'room_type' => ucfirst(str_replace('_', ' ', $reservation['room_type'])),
            'rate' => number_format($reservation['rate'], 2),
            'check_in_date' => $reservation['check_in_date'],
            'check_out_date' => $reservation['check_out_date'],
            'status' => $reservation['status'],
            'created_at' => $reservation['created_at']
        ];
    }

    echo json_encode([
        'success' => true,
        'reservations' => $formatted_reservations,
        'has_data' => !empty($formatted_reservations)
    ]);

} catch (PDOException $e) {
    error_log("Error fetching recent reservations: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error loading reservations'
    ]);
}
?>
