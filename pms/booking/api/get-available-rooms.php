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

header('Content-Type: application/json');

try {
    // Get query parameters
    $room_type = $_GET['room_type'] ?? null;
    $check_in_date = $_GET['check_in_date'] ?? null;
    $check_out_date = $_GET['check_out_date'] ?? null;
    
    // Get available rooms
    $rooms = getAvailableRoomsForDates($room_type, $check_in_date, $check_out_date);
    
    echo json_encode([
        'success' => true,
        'rooms' => $rooms
    ]);
    
} catch (Exception $e) {
    error_log("Error getting available rooms: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error retrieving available rooms'
    ]);
}

/**
 * Get available rooms for specific dates and room type
 */
function getAvailableRoomsForDates($room_type = null, $check_in_date = null, $check_out_date = null) {
    global $pdo;
    
    try {
        $sql = "
            SELECT r.*, 
                   CASE r.room_type 
                       WHEN 'standard' THEN 'Standard Room'
                       WHEN 'deluxe' THEN 'Deluxe Room'
                       WHEN 'suite' THEN 'Suite'
                       WHEN 'presidential' THEN 'Presidential Suite'
                       ELSE r.room_type
                   END as room_type_name,
                   CASE r.room_type 
                       WHEN 'standard' THEN 150.00
                       WHEN 'deluxe' THEN 250.00
                       WHEN 'suite' THEN 400.00
                       WHEN 'presidential' THEN 800.00
                       ELSE 150.00
                   END as rate
            FROM rooms r
            WHERE r.status = 'available'
        ";
        
        $params = [];
        
        // Add room type filter
        if ($room_type) {
            $sql .= " AND r.room_type = ?";
            $params[] = $room_type;
        }
        
        // Add date availability filter
        if ($check_in_date && $check_out_date) {
            $sql .= " AND r.id NOT IN (
                SELECT room_id 
                FROM reservations 
                WHERE status IN ('confirmed', 'checked_in')
                AND (
                    (check_in_date <= ? AND check_out_date > ?) OR
                    (check_in_date < ? AND check_out_date >= ?) OR
                    (check_in_date >= ? AND check_out_date <= ?)
                )
            )";
            $params = array_merge($params, [
                $check_out_date, $check_in_date,
                $check_out_date, $check_in_date,
                $check_in_date, $check_out_date
            ]);
        }
        
        $sql .= " ORDER BY r.room_number";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
        
    } catch (PDOException $e) {
        error_log("Error getting available rooms for dates: " . $e->getMessage());
        return [];
    }
}
?>
