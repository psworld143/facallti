<?php
session_start();
require_once '../includes/config.php';
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
    $today = date('Y-m-d');
    
    // Get today's schedule including check-ins, check-outs, and reservations
    $stmt = $pdo->prepare("
        SELECT 
            'checkin' as type,
            CONCAT(g.first_name, ' ', g.last_name) as title,
            CONCAT('Check-in for Room ', rm.room_number) as description,
            '09:00' as time,
            rm.room_number,
            r.id as reservation_id
        FROM reservations r
        JOIN guests g ON r.guest_id = g.id
        JOIN rooms rm ON r.room_id = rm.id
        WHERE r.check_in_date = ? AND r.status = 'confirmed'
        
        UNION ALL
        
        SELECT 
            'checkout' as type,
            CONCAT(g.first_name, ' ', g.last_name) as title,
            CONCAT('Check-out from Room ', rm.room_number) as description,
            '11:00' as time,
            rm.room_number,
            r.id as reservation_id
        FROM reservations r
        JOIN guests g ON r.guest_id = g.id
        JOIN rooms rm ON r.room_id = rm.id
        WHERE r.check_out_date = ? AND r.status = 'checked_in'
        
        UNION ALL
        
        SELECT 
            'reservation' as type,
            CONCAT('New Reservation - ', g.first_name, ' ', g.last_name) as title,
            CONCAT('Room ', rm.room_number, ' (', rm.room_type, ')') as description,
            DATE_FORMAT(r.created_at, '%H:%i') as time,
            rm.room_number,
            r.id as reservation_id
        FROM reservations r
        JOIN guests g ON r.guest_id = g.id
        JOIN rooms rm ON r.room_id = rm.id
        WHERE DATE(r.created_at) = ? AND r.status = 'confirmed'
        
        ORDER BY time ASC
    ");
    $stmt->execute([$today, $today, $today]);
    $schedule = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format the data for display
    $formatted_schedule = [];
    foreach ($schedule as $item) {
        $formatted_schedule[] = [
            'type' => $item['type'],
            'title' => $item['title'],
            'description' => $item['description'],
            'time' => $item['time'],
            'room_number' => $item['room_number'],
            'reservation_id' => $item['reservation_id']
        ];
    }

    // If no schedule items, add a default message
    if (empty($formatted_schedule)) {
        $formatted_schedule[] = [
            'type' => 'info',
            'title' => 'No scheduled activities',
            'description' => 'No check-ins, check-outs, or new reservations for today',
            'time' => '--:--',
            'room_number' => 'N/A',
            'reservation_id' => null
        ];
    }

    echo json_encode([
        'success' => true,
        'schedule' => $formatted_schedule,
        'has_data' => !empty($formatted_schedule) || (count($formatted_schedule) === 1 && $formatted_schedule[0]['type'] === 'info')
    ]);

} catch (PDOException $e) {
    error_log("Error fetching today's schedule: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error loading schedule'
    ]);
}
?>
