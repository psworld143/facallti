<?php
require_once 'config.php';

/**
 * Get dashboard statistics
 */
function getDashboardStats() {
    global $pdo;
    
    try {
        // Total rooms
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM rooms");
        $total_rooms = $stmt->fetch()['total'];
        
        // Occupied rooms
        $stmt = $pdo->query("SELECT COUNT(*) as occupied FROM rooms WHERE status = 'occupied'");
        $occupied_rooms = $stmt->fetch()['occupied'];
        
        // Occupancy rate
        $occupancy_rate = $total_rooms > 0 ? round(($occupied_rooms / $total_rooms) * 100, 1) : 0;
        
        // Today's revenue
        $stmt = $pdo->query("SELECT COALESCE(SUM(total_amount), 0) as revenue FROM billing WHERE DATE(created_at) = CURDATE()");
        $today_revenue = $stmt->fetch()['revenue'];
        
        return [
            'total_rooms' => $total_rooms,
            'occupied_rooms' => $occupied_rooms,
            'occupancy_rate' => $occupancy_rate,
            'today_revenue' => $today_revenue
        ];
    } catch (PDOException $e) {
        error_log("Error getting dashboard stats: " . $e->getMessage());
        return [
            'total_rooms' => 0,
            'occupied_rooms' => 0,
            'occupancy_rate' => 0,
            'today_revenue' => 0
        ];
    }
}

/**
 * Log user activity
 */
function logActivity($user_id, $action, $details = '') {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, details, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$user_id, $action, $details]);
    } catch (PDOException $e) {
        error_log("Error logging activity: " . $e->getMessage());
    }
}

/**
 * Get recent activities
 */
function getRecentActivities($limit = 10) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT al.*, u.name as user_name 
            FROM activity_logs al 
            JOIN users u ON al.user_id = u.id 
            ORDER BY al.created_at DESC 
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error getting recent activities: " . $e->getMessage());
        return [];
    }
}

/**
 * Check user permissions
 */
function hasPermission($user_role, $required_role) {
    $role_hierarchy = [
        'front_desk' => 1,
        'housekeeping' => 1,
        'manager' => 3
    ];
    
    return isset($role_hierarchy[$user_role]) && 
           isset($role_hierarchy[$required_role]) && 
           $role_hierarchy[$user_role] >= $role_hierarchy[$required_role];
}

/**
 * Sanitize input
 */
function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Validate email
 */
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

/**
 * Generate random string
 */
function generateRandomString($length = 10) {
    return bin2hex(random_bytes($length));
}

/**
 * Format currency
 */
function formatCurrency($amount) {
    return '$' . number_format($amount, 2);
}

/**
 * Format date
 */
function formatDate($date, $format = 'Y-m-d H:i:s') {
    return date($format, strtotime($date));
}

/**
 * Get room status options
 */
function getRoomStatusOptions() {
    return [
        'available' => 'Available',
        'occupied' => 'Occupied',
        'reserved' => 'Reserved',
        'maintenance' => 'Maintenance',
        'out_of_service' => 'Out of Service'
    ];
}

/**
 * Get housekeeping status options
 */
function getHousekeepingStatusOptions() {
    return [
        'clean' => 'Clean',
        'dirty' => 'Dirty',
        'cleaning' => 'Cleaning',
        'maintenance' => 'Maintenance'
    ];
}

/**
 * Send notification
 */
function sendNotification($user_id, $message, $type = 'info') {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("INSERT INTO notifications (user_id, message, type, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$user_id, $message, $type]);
    } catch (PDOException $e) {
        error_log("Error sending notification: " . $e->getMessage());
    }
}

/**
 * Get unread notifications count
 */
function getUnreadNotificationsCount($user_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$user_id]);
        return $stmt->fetch()['count'];
    } catch (PDOException $e) {
        error_log("Error getting unread notifications count: " . $e->getMessage());
        return 0;
    }
}

/**
 * Get front desk statistics
 */
function getFrontDeskStats() {
    global $pdo;
    
    try {
        // Today's check-ins (from check_ins table)
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM check_ins WHERE DATE(checked_in_at) = CURDATE()");
        $today_checkins = $stmt->fetch()['count'];
        
        // Today's check-outs (from reservations table where status is checked_out)
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM reservations WHERE status = 'checked_out' AND DATE(checked_out_at) = CURDATE()");
        $today_checkouts = $stmt->fetch()['count'];
        
        // Pending reservations
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM reservations WHERE status = 'confirmed' AND check_in_date >= CURDATE()");
        $pending_reservations = $stmt->fetch()['count'];
        
        // Overbookings (simplified - rooms with multiple reservations on same date)
        $stmt = $pdo->query("
            SELECT COUNT(DISTINCT r1.room_id) as count 
            FROM reservations r1 
            JOIN reservations r2 ON r1.room_id = r2.room_id AND r1.id != r2.id 
            WHERE r1.check_in_date <= r2.check_out_date 
            AND r1.check_out_date >= r2.check_in_date 
            AND r1.status IN ('confirmed', 'checked_in')
            AND r2.status IN ('confirmed', 'checked_in')
        ");
        $overbookings = $stmt->fetch()['count'];
        
        return [
            'today_checkins' => $today_checkins,
            'today_checkouts' => $today_checkouts,
            'pending_reservations' => $pending_reservations,
            'overbookings' => $overbookings
        ];
    } catch (PDOException $e) {
        error_log("Error getting front desk stats: " . $e->getMessage());
        return [
            'today_checkins' => 0,
            'today_checkouts' => 0,
            'pending_reservations' => 0,
            'overbookings' => 0
        ];
    }
}

/**
 * Get available rooms
 */
function getAvailableRooms() {
    global $pdo;
    
    try {
        $stmt = $pdo->query("
            SELECT id, room_number, room_type, rate, status, housekeeping_status
            FROM rooms 
            WHERE status = 'available'
            ORDER BY room_number ASC
        ");
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error getting available rooms: " . $e->getMessage());
        return [];
    }
}

/**
 * Get room types
 */
function getRoomTypes() {
    return [
        'standard' => [
            'name' => 'Standard Room',
            'rate' => 150.00,
            'description' => 'Comfortable standard room with basic amenities'
        ],
        'deluxe' => [
            'name' => 'Deluxe Room',
            'rate' => 250.00,
            'description' => 'Spacious deluxe room with premium amenities'
        ],
        'suite' => [
            'name' => 'Suite',
            'rate' => 400.00,
            'description' => 'Luxury suite with separate living area'
        ],
        'presidential' => [
            'name' => 'Presidential Suite',
            'rate' => 800.00,
            'description' => 'Ultimate luxury with premium services'
        ]
    ];
}

/**
 * Generate reservation number
 */
function generateReservationNumber() {
    return 'RES' . date('Ymd') . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
}

/**
 * Create new reservation
 */
function createReservation($data) {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        // Create or find guest
        $guest_id = createOrFindGuest($data);
        
        // Find available room
        $room_id = findAvailableRoom($data['room_type'], $data['check_in_date'], $data['check_out_date']);
        
        if (!$room_id) {
            throw new Exception('No available rooms for the selected dates');
        }
        
        // Calculate total amount
        $nights = (strtotime($data['check_out_date']) - strtotime($data['check_in_date'])) / (60 * 60 * 24);
        $room_rate = getRoomTypes()[$data['room_type']]['rate'];
        $total_amount = $room_rate * $nights * 1.1; // 10% tax
        
        // Create reservation
        $stmt = $pdo->prepare("
            INSERT INTO reservations (
                reservation_number, guest_id, room_id, check_in_date, check_out_date,
                adults, children, total_amount, special_requests, booking_source, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $reservation_number = generateReservationNumber();
        $stmt->execute([
            $reservation_number,
            $guest_id,
            $room_id,
            $data['check_in_date'],
            $data['check_out_date'],
            $data['adults'],
            $data['children'],
            $total_amount,
            $data['special_requests'] ?? '',
            $data['booking_source'],
            $_SESSION['user_id']
        ]);
        
        $reservation_id = $pdo->lastInsertId();
        
        // Create billing record
        $stmt = $pdo->prepare("
            INSERT INTO billing (reservation_id, guest_id, room_charges, tax_amount, total_amount)
            VALUES (?, ?, ?, ?, ?)
        ");
        
        $room_charges = $room_rate * $nights;
        $tax_amount = $room_charges * 0.1;
        
        $stmt->execute([
            $reservation_id,
            $guest_id,
            $room_charges,
            $tax_amount,
            $total_amount
        ]);
        
        // Update room status
        $stmt = $pdo->prepare("UPDATE rooms SET status = 'reserved' WHERE id = ?");
        $stmt->execute([$room_id]);
        
        // Log activity
        logActivity($_SESSION['user_id'], 'reservation_created', "Created reservation {$reservation_number}");
        
        $pdo->commit();
        
        return [
            'success' => true,
            'reservation_id' => $reservation_id,
            'reservation_number' => $reservation_number,
            'message' => 'Reservation created successfully'
        ];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error creating reservation: " . $e->getMessage());
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}

/**
 * Create or find guest
 */
function createOrFindGuest($data) {
    global $pdo;
    
    try {
        // Check if guest already exists
        $stmt = $pdo->prepare("
            SELECT id FROM guests 
            WHERE email = ? OR (first_name = ? AND last_name = ? AND phone = ?)
        ");
        $stmt->execute([
            $data['email'] ?? '',
            $data['first_name'],
            $data['last_name'],
            $data['phone']
        ]);
        $existing_guest = $stmt->fetch();
        
        if ($existing_guest) {
            // Update guest information
            $stmt = $pdo->prepare("
                UPDATE guests SET 
                    email = ?, phone = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                $data['email'] ?? '',
                $data['phone'],
                $existing_guest['id']
            ]);
            return $existing_guest['id'];
        }
        
        // Create new guest
        $stmt = $pdo->prepare("
            INSERT INTO guests (
                first_name, last_name, email, phone, id_type, id_number, is_vip
            ) VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $data['first_name'],
            $data['last_name'],
            $data['email'] ?? '',
            $data['phone'],
            $data['id_type'] ?? 'other',
            $data['id_number'] ?? 'N/A',
            $data['is_vip'] ?? false
        ]);
        
        return $pdo->lastInsertId();
        
    } catch (PDOException $e) {
        error_log("Error creating/finding guest: " . $e->getMessage());
        throw new Exception('Error processing guest information');
    }
}

/**
 * Find available room
 */
function findAvailableRoom($room_type, $check_in_date, $check_out_date) {
    global $pdo;
    
    try {
        // Find rooms that are available and not conflicting with existing reservations
        $stmt = $pdo->prepare("
            SELECT r.id 
            FROM rooms r
            WHERE r.room_type = ? 
            AND r.status = 'available'
            AND r.id NOT IN (
                SELECT room_id 
                FROM reservations 
                WHERE status IN ('confirmed', 'checked_in')
                AND (
                    (check_in_date <= ? AND check_out_date > ?) OR
                    (check_in_date < ? AND check_out_date >= ?) OR
                    (check_in_date >= ? AND check_out_date <= ?)
                )
            )
            ORDER BY r.room_number ASC
            LIMIT 1
        ");
        $stmt->execute([
            $room_type,
            $check_in_date,
            $check_in_date,
            $check_out_date,
            $check_out_date,
            $check_in_date,
            $check_out_date
        ]);
        
        $room = $stmt->fetch();
        return $room ? $room['id'] : null;
        
    } catch (PDOException $e) {
        error_log("Error finding available room: " . $e->getMessage());
        return null;
    }
}

/**
 * Get pending check-ins
 */
function getPendingCheckins() {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT r.*, g.first_name, g.last_name, g.phone, rm.room_number, rm.room_type
            FROM reservations r
            JOIN guests g ON r.guest_id = g.id
            JOIN rooms rm ON r.room_id = rm.id
            WHERE r.status = 'confirmed' 
            AND r.check_in_date = CURDATE()
            ORDER BY r.check_in_date ASC
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error getting pending check-ins: " . $e->getMessage());
        return [];
    }
}

/**
 * Get checked in guests
 */
function getCheckedInGuests() {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT r.*, g.first_name, g.last_name, g.phone, rm.room_number, rm.room_type
            FROM reservations r
            JOIN guests g ON r.guest_id = g.id
            JOIN rooms rm ON r.room_id = rm.id
            WHERE r.status = 'checked_in'
            ORDER BY r.check_out_date ASC
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error getting checked in guests: " . $e->getMessage());
        return [];
    }
}

/**
 * Check in guest
 */
function checkInGuest($reservation_id, $data) {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        // Get reservation details
        $stmt = $pdo->prepare("
            SELECT r.*, rm.room_number, rm.room_type
            FROM reservations r
            JOIN rooms rm ON r.room_id = rm.id
            WHERE r.id = ? AND r.status = 'confirmed'
        ");
        $stmt->execute([$reservation_id]);
        $reservation = $stmt->fetch();
        
        if (!$reservation) {
            throw new Exception('Reservation not found or already checked in');
        }
        
        // Update reservation status
        $stmt = $pdo->prepare("
            UPDATE reservations 
            SET status = 'checked_in', checked_in_at = NOW(), checked_in_by = ?
            WHERE id = ?
        ");
        $stmt->execute([$_SESSION['user_id'], $reservation_id]);
        
        // Update room status
        $stmt = $pdo->prepare("UPDATE rooms SET status = 'occupied' WHERE id = ?");
        $stmt->execute([$reservation['room_id']]);
        
        // Create check-in record
        $stmt = $pdo->prepare("
            INSERT INTO check_ins (
                reservation_id, room_key_issued, welcome_amenities, checked_in_by, checked_in_at
            ) VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $reservation_id,
            $data['room_key_issued'] ?? false,
            $data['welcome_amenities'] ?? false,
            $_SESSION['user_id']
        ]);
        
        // Log activity
        logActivity($_SESSION['user_id'], 'guest_checked_in', "Checked in guest for reservation {$reservation['reservation_number']}");
        
        $pdo->commit();
        
        return [
            'success' => true,
            'message' => 'Guest checked in successfully'
        ];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error checking in guest: " . $e->getMessage());
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}

/**
 * Check out guest
 */
function checkOutGuest($reservation_id, $data) {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        // Get reservation details
        $stmt = $pdo->prepare("
            SELECT r.*, rm.room_number, rm.room_type
            FROM reservations r
            JOIN rooms rm ON r.room_id = rm.id
            WHERE r.id = ? AND r.status = 'checked_in'
        ");
        $stmt->execute([$reservation_id]);
        $reservation = $stmt->fetch();
        
        if (!$reservation) {
            throw new Exception('Reservation not found or not checked in');
        }
        
        // Update reservation status
        $stmt = $pdo->prepare("
            UPDATE reservations 
            SET status = 'checked_out', checked_out_at = NOW(), checked_out_by = ?
            WHERE id = ?
        ");
        $stmt->execute([$_SESSION['user_id'], $reservation_id]);
        
        // Update room status
        $stmt = $pdo->prepare("UPDATE rooms SET status = 'available', housekeeping_status = 'dirty' WHERE id = ?");
        $stmt->execute([$reservation['room_id']]);
        
        // Update billing payment status
        $stmt = $pdo->prepare("
            UPDATE billing 
            SET payment_status = ?, payment_method = ?
            WHERE reservation_id = ?
        ");
        $stmt->execute([
            $data['payment_status'],
            $data['payment_method'] ?? null,
            $reservation_id
        ]);
        
        // Log activity
        logActivity($_SESSION['user_id'], 'guest_checked_out', "Checked out guest for reservation {$reservation['reservation_number']}");
        
        $pdo->commit();
        
        return [
            'success' => true,
            'message' => 'Guest checked out successfully'
        ];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error checking out guest: " . $e->getMessage());
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}

/**
 * Get reservation details
 */
function getReservationDetails($reservation_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT r.*, g.first_name, g.last_name, g.email, g.phone, g.is_vip,
                   rm.room_number, rm.room_type, rm.status as room_status, rm.housekeeping_status
            FROM reservations r
            JOIN guests g ON r.guest_id = g.id
            JOIN rooms rm ON r.room_id = rm.id
            WHERE r.id = ?
        ");
        $stmt->execute([$reservation_id]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Error getting reservation details: " . $e->getMessage());
        return null;
    }
}

/**
 * Get housekeeping statistics
 */
function getHousekeepingStats() {
    global $pdo;
    
    try {
        // Clean rooms
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM rooms WHERE housekeeping_status = 'clean'");
        $clean_rooms = $stmt->fetch()['count'];
        
        // Dirty rooms
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM rooms WHERE housekeeping_status = 'dirty'");
        $dirty_rooms = $stmt->fetch()['count'];
        
        // Rooms being cleaned
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM rooms WHERE housekeeping_status = 'cleaning'");
        $cleaning_rooms = $stmt->fetch()['count'];
        
        // Maintenance rooms
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM rooms WHERE housekeeping_status = 'maintenance'");
        $maintenance_rooms = $stmt->fetch()['count'];
        
        return [
            'clean_rooms' => $clean_rooms,
            'dirty_rooms' => $dirty_rooms,
            'cleaning_rooms' => $cleaning_rooms,
            'maintenance_rooms' => $maintenance_rooms
        ];
        
    } catch (PDOException $e) {
        error_log("Error getting housekeeping stats: " . $e->getMessage());
        return [
            'clean_rooms' => 0,
            'dirty_rooms' => 0,
            'cleaning_rooms' => 0,
            'maintenance_rooms' => 0
        ];
    }
}

/**
 * Get room status overview
 */
function getRoomStatusOverview() {
    global $pdo;
    
    try {
        $stmt = $pdo->query("
            SELECT 
                room_type,
                COUNT(*) as total,
                SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) as available,
                SUM(CASE WHEN status = 'occupied' THEN 1 ELSE 0 END) as occupied,
                SUM(CASE WHEN status = 'reserved' THEN 1 ELSE 0 END) as reserved,
                SUM(CASE WHEN status = 'maintenance' THEN 1 ELSE 0 END) as maintenance
            FROM rooms
            GROUP BY room_type
            ORDER BY room_type
        ");
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error getting room status overview: " . $e->getMessage());
        return [];
    }
}

/**
 * Get recent housekeeping tasks
 */
function getRecentHousekeepingTasks() {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT ht.*, r.room_number, r.room_type, u.name as assigned_to_name
            FROM housekeeping_tasks ht
            JOIN rooms r ON ht.room_id = r.id
            LEFT JOIN users u ON ht.assigned_to = u.id
            ORDER BY ht.created_at DESC
            LIMIT 10
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error getting recent housekeeping tasks: " . $e->getMessage());
        return [];
    }
}

/**
 * Update room housekeeping status
 */
function updateRoomHousekeepingStatus($room_id, $status) {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        // Update room status
        $stmt = $pdo->prepare("UPDATE rooms SET housekeeping_status = ? WHERE id = ?");
        $stmt->execute([$status, $room_id]);
        
        // Create housekeeping task
        $stmt = $pdo->prepare("
            INSERT INTO housekeeping_tasks (
                room_id, task_type, status, assigned_to, created_by, created_at
            ) VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $room_id,
            $status === 'clean' ? 'daily_cleaning' : 'maintenance',
            'completed',
            $_SESSION['user_id'],
            $_SESSION['user_id']
        ]);
        
        // Log activity
        logActivity($_SESSION['user_id'], 'housekeeping_updated', "Updated room {$room_id} housekeeping status to {$status}");
        
        $pdo->commit();
        
        return [
            'success' => true,
            'message' => 'Room status updated successfully'
        ];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error updating room housekeeping status: " . $e->getMessage());
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}

/**
 * Create maintenance request
 */
function createMaintenanceRequest($room_id, $issue_type, $description, $priority = 'medium') {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO maintenance_requests (
                room_id, issue_type, description, priority, status, reported_by, created_at
            ) VALUES (?, ?, ?, ?, 'reported', ?, NOW())
        ");
        $stmt->execute([
            $room_id,
            $issue_type,
            $description,
            $priority,
            $_SESSION['user_id']
        ]);
        
        $request_id = $pdo->lastInsertId();
        
        // Log activity
        logActivity($_SESSION['user_id'], 'maintenance_request', "Created maintenance request {$request_id} for room {$room_id}");
        
        return [
            'success' => true,
            'request_id' => $request_id,
            'message' => 'Maintenance request created successfully'
        ];
        
    } catch (Exception $e) {
        error_log("Error creating maintenance request: " . $e->getMessage());
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}

/**
 * Get all rooms with housekeeping status
 */
function getAllRoomsWithStatus() {
    global $pdo;
    
    try {
        $stmt = $pdo->query("
            SELECT id, room_number, room_type, status, housekeeping_status, rate
            FROM rooms
            ORDER BY room_number ASC
        ");
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error getting all rooms: " . $e->getMessage());
        return [];
    }
}

/**
 * Get all reservations
 */
function getAllReservations($filters = []) {
    global $pdo;
    
    try {
        $where_conditions = ["1=1"];
        $params = [];
        
        if (!empty($filters['status'])) {
            $where_conditions[] = "r.status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['guest_name'])) {
            $where_conditions[] = "(g.first_name LIKE ? OR g.last_name LIKE ?)";
            $search_term = "%{$filters['guest_name']}%";
            $params[] = $search_term;
            $params[] = $search_term;
        }
        
        if (!empty($filters['date_from'])) {
            $where_conditions[] = "r.check_in_date >= ?";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $where_conditions[] = "r.check_out_date <= ?";
            $params[] = $filters['date_to'];
        }
        
        $where_clause = implode(" AND ", $where_conditions);
        
        $stmt = $pdo->prepare("
            SELECT r.*, g.first_name, g.last_name, g.email, g.phone, g.is_vip,
                   rm.room_number, rm.room_type
            FROM reservations r
            JOIN guests g ON r.guest_id = g.id
            JOIN rooms rm ON r.room_id = rm.id
            WHERE {$where_clause}
            ORDER BY r.created_at DESC
        ");
        $stmt->execute($params);
        return $stmt->fetchAll();
        
    } catch (PDOException $e) {
        error_log("Error getting reservations: " . $e->getMessage());
        return [];
    }
}

/**
 * Update reservation
 */
function updateReservation($reservation_id, $data) {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        // Get current reservation
        $stmt = $pdo->prepare("SELECT * FROM reservations WHERE id = ?");
        $stmt->execute([$reservation_id]);
        $current_reservation = $stmt->fetch();
        
        if (!$current_reservation) {
            throw new Exception('Reservation not found');
        }
        
        // Update guest information
        $stmt = $pdo->prepare("
            UPDATE guests SET 
                first_name = ?, last_name = ?, email = ?, phone = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([
            $data['first_name'],
            $data['last_name'],
            $data['email'] ?? '',
            $data['phone'],
            $current_reservation['guest_id']
        ]);
        
        // Calculate new total amount if dates or room type changed
        $nights = (strtotime($data['check_out_date']) - strtotime($data['check_in_date'])) / (60 * 60 * 24);
        $room_types = getRoomTypes();
        $room_rate = $room_types[$data['room_type']]['rate'];
        $total_amount = $room_rate * $nights * 1.1; // 10% tax
        
        // Update reservation
        $stmt = $pdo->prepare("
            UPDATE reservations SET 
                check_in_date = ?, check_out_date = ?, adults = ?, children = ?,
                special_requests = ?, total_amount = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([
            $data['check_in_date'],
            $data['check_out_date'],
            $data['adults'],
            $data['children'] ?? 0,
            $data['special_requests'] ?? '',
            $total_amount,
            $reservation_id
        ]);
        
        // Update billing
        $room_charges = $room_rate * $nights;
        $tax_amount = $room_charges * 0.1;
        
        $stmt = $pdo->prepare("
            UPDATE billing SET 
                room_charges = ?, tax_amount = ?, total_amount = ?
            WHERE reservation_id = ?
        ");
        $stmt->execute([
            $room_charges,
            $tax_amount,
            $total_amount,
            $reservation_id
        ]);
        
        // Log activity
        logActivity($_SESSION['user_id'], 'reservation_updated', "Updated reservation {$current_reservation['reservation_number']}");
        
        $pdo->commit();
        
        return [
            'success' => true,
            'message' => 'Reservation updated successfully'
        ];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error updating reservation: " . $e->getMessage());
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}

/**
 * Cancel reservation
 */
function cancelReservation($reservation_id, $reason = '') {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        // Get reservation details
        $stmt = $pdo->prepare("
            SELECT r.*, g.first_name, g.last_name, rm.room_number
            FROM reservations r
            JOIN guests g ON r.guest_id = g.id
            JOIN rooms rm ON r.room_id = rm.id
            WHERE r.id = ? AND r.status IN ('confirmed', 'checked_in')
        ");
        $stmt->execute([$reservation_id]);
        $reservation = $stmt->fetch();
        
        if (!$reservation) {
            throw new Exception('Reservation not found or cannot be cancelled');
        }
        
        // Update reservation status
        $stmt = $pdo->prepare("UPDATE reservations SET status = 'cancelled', updated_at = NOW() WHERE id = ?");
        $stmt->execute([$reservation_id]);
        
        // Free up the room if it was occupied
        if ($reservation['status'] === 'checked_in') {
            $stmt = $pdo->prepare("UPDATE rooms SET status = 'available', housekeeping_status = 'dirty' WHERE id = ?");
            $stmt->execute([$reservation['room_id']]);
        } else {
            $stmt = $pdo->prepare("UPDATE rooms SET status = 'available' WHERE id = ?");
            $stmt->execute([$reservation['room_id']]);
        }
        
        // Log activity
        logActivity($_SESSION['user_id'], 'reservation_cancelled', "Cancelled reservation {$reservation['reservation_number']} for {$reservation['first_name']} {$reservation['last_name']}");
        
        $pdo->commit();
        
        return [
            'success' => true,
            'message' => 'Reservation cancelled successfully'
        ];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error cancelling reservation: " . $e->getMessage());
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}

/**
 * Add service charge to reservation
 */
function addServiceCharge($reservation_id, $service_type, $description, $amount) {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        // Add service charge
        $stmt = $pdo->prepare("
            INSERT INTO service_charges (reservation_id, service_type, description, amount, added_by)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $reservation_id,
            $service_type,
            $description,
            $amount,
            $_SESSION['user_id']
        ]);
        
        // Update billing total
        $stmt = $pdo->prepare("
            UPDATE billing 
            SET additional_charges = COALESCE(additional_charges, 0) + ?,
                total_amount = COALESCE(room_charges, 0) + COALESCE(additional_charges, 0) + ? + COALESCE(tax_amount, 0)
            WHERE reservation_id = ?
        ");
        $stmt->execute([$amount, $amount, $reservation_id]);
        
        // Log activity
        logActivity($_SESSION['user_id'], 'service_charge_added', "Added {$service_type} charge of ${$amount} to reservation {$reservation_id}");
        
        $pdo->commit();
        
        return [
            'success' => true,
            'message' => 'Service charge added successfully'
        ];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error adding service charge: " . $e->getMessage());
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}

/**
 * Get management statistics
 */
function getManagementStatistics() {
    global $pdo;
    
    try {
        // Occupancy rate
        $stmt = $pdo->query("
            SELECT 
                ROUND((COUNT(CASE WHEN status IN ('reserved', 'checked_in') THEN 1 END) * 100.0 / COUNT(*)), 1) as occupancy_rate
            FROM rooms
        ");
        $occupancy_rate = $stmt->fetch()['occupancy_rate'] ?? 0;
        
        // Monthly revenue
        $stmt = $pdo->query("
            SELECT COALESCE(SUM(total_amount), 0) as total 
            FROM bills 
            WHERE MONTH(bill_date) = MONTH(CURDATE()) 
            AND YEAR(bill_date) = YEAR(CURDATE()) 
            AND status = 'paid'
        ");
        $monthly_revenue = $stmt->fetch()['total'];
        
        // Total guests
        $stmt = $pdo->query("
            SELECT COUNT(DISTINCT guest_id) as total 
            FROM reservations 
            WHERE check_in_date <= CURDATE() 
            AND check_out_date >= CURDATE()
        ");
        $total_guests = $stmt->fetch()['total'];
        
        // Low stock items
        $stmt = $pdo->query("
            SELECT COUNT(*) as total 
            FROM inventory_items 
            WHERE current_stock <= minimum_stock
        ");
        $low_stock_items = $stmt->fetch()['total'];
        
        return [
            'occupancy_rate' => $occupancy_rate,
            'monthly_revenue' => $monthly_revenue,
            'total_guests' => $total_guests,
            'low_stock_items' => $low_stock_items
        ];
        
    } catch (PDOException $e) {
        error_log("Error getting management statistics: " . $e->getMessage());
        return [
            'occupancy_rate' => 0,
            'monthly_revenue' => 0,
            'total_guests' => 0,
            'low_stock_items' => 0
        ];
    }
}

/**
 * Get occupancy data for charts
 */
function getOccupancyData() {
    global $pdo;
    
    try {
        $stmt = $pdo->query("
            SELECT 
                DATE(check_in_date) as date,
                ROUND((COUNT(CASE WHEN status IN ('reserved', 'checked_in') THEN 1 END) * 100.0 / COUNT(*)), 1) as occupancy_rate
            FROM reservations 
            WHERE check_in_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            GROUP BY DATE(check_in_date)
            ORDER BY date
        ");
        
        $data = $stmt->fetchAll();
        $labels = [];
        $values = [];
        
        foreach ($data as $row) {
            $labels[] = date('M d', strtotime($row['date']));
            $values[] = $row['occupancy_rate'];
        }
        
        return [
            'labels' => $labels,
            'values' => $values
        ];
        
    } catch (PDOException $e) {
        error_log("Error getting occupancy data: " . $e->getMessage());
        return [
            'labels' => [],
            'values' => []
        ];
    }
}

/**
 * Get revenue data for charts
 */
function getRevenueData() {
    global $pdo;
    
    try {
        $stmt = $pdo->query("
            SELECT 
                DATE(bill_date) as date,
                SUM(total_amount) as revenue
            FROM bills 
            WHERE bill_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            AND status = 'paid'
            GROUP BY DATE(bill_date)
            ORDER BY date
        ");
        
        $data = $stmt->fetchAll();
        $labels = [];
        $values = [];
        
        foreach ($data as $row) {
            $labels[] = date('M d', strtotime($row['date']));
            $values[] = $row['revenue'];
        }
        
        return [
            'labels' => $labels,
            'values' => $values
        ];
        
    } catch (PDOException $e) {
        error_log("Error getting revenue data: " . $e->getMessage());
        return [
            'labels' => [],
            'values' => []
        ];
    }
}

/**
 * Get daily reports
 */
function getDailyReports($date_filter = '') {
    global $pdo;
    
    try {
        $where_condition = "1=1";
        $params = [];
        
        if (!empty($date_filter)) {
            $where_condition = "DATE(r.check_in_date) = ?";
            $params[] = $date_filter;
        }
        
        $query = "
            SELECT 
                DATE(r.check_in_date) as date,
                ROUND((COUNT(CASE WHEN r.status IN ('reserved', 'checked_in') THEN 1 END) * 100.0 / COUNT(*)), 1) as occupancy_rate,
                COALESCE(SUM(b.total_amount), 0) as revenue,
                COUNT(CASE WHEN r.status = 'checked_in' THEN 1 END) as check_ins,
                COUNT(CASE WHEN r.status = 'checked_out' THEN 1 END) as check_outs,
                COALESCE(AVG(b.total_amount), 0) as avg_room_rate
            FROM reservations r
            LEFT JOIN bills b ON r.id = b.reservation_id
            WHERE {$where_condition}
            GROUP BY DATE(r.check_in_date)
            ORDER BY date DESC
            LIMIT 30
        ";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
        
    } catch (PDOException $e) {
        error_log("Error getting daily reports: " . $e->getMessage());
        return [];
    }
}

/**
 * Get weekly reports
 */
function getWeeklyReports($week_filter = '') {
    global $pdo;
    
    try {
        $where_condition = "1=1";
        $params = [];
        
        if (!empty($week_filter)) {
            $where_condition = "YEARWEEK(r.check_in_date) = ?";
            $params[] = $week_filter;
        }
        
        $query = "
            SELECT 
                CONCAT(YEAR(r.check_in_date), '-W', WEEK(r.check_in_date)) as week,
                ROUND(AVG(
                    (COUNT(CASE WHEN r.status IN ('reserved', 'checked_in') THEN 1 END) * 100.0 / COUNT(*))
                ), 1) as avg_occupancy,
                COALESCE(SUM(b.total_amount), 0) as total_revenue,
                COUNT(DISTINCT r.guest_id) as total_guests,
                COALESCE(AVG(b.total_amount), 0) as avg_room_rate,
                COALESCE(SUM(b.total_amount) / COUNT(*), 0) as revpar
            FROM reservations r
            LEFT JOIN bills b ON r.id = b.reservation_id
            WHERE {$where_condition}
            GROUP BY YEARWEEK(r.check_in_date)
            ORDER BY week DESC
            LIMIT 12
        ";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
        
    } catch (PDOException $e) {
        error_log("Error getting weekly reports: " . $e->getMessage());
        return [];
    }
}

/**
 * Get monthly reports
 */
function getMonthlyReports($month_filter = '') {
    global $pdo;
    
    try {
        $where_condition = "1=1";
        $params = [];
        
        if (!empty($month_filter)) {
            $where_condition = "DATE_FORMAT(r.check_in_date, '%Y-%m') = ?";
            $params[] = $month_filter;
        }
        
        $query = "
            SELECT 
                DATE_FORMAT(r.check_in_date, '%Y-%m') as month,
                ROUND(AVG(
                    (COUNT(CASE WHEN r.status IN ('reserved', 'checked_in') THEN 1 END) * 100.0 / COUNT(*))
                ), 1) as avg_occupancy,
                COALESCE(SUM(b.total_amount), 0) as total_revenue,
                COUNT(DISTINCT r.guest_id) as total_guests,
                COALESCE(AVG(b.total_amount), 0) as avg_room_rate,
                COALESCE(SUM(b.total_amount) / COUNT(*), 0) as revpar,
                COALESCE(SUM(b.total_amount) / COUNT(DISTINCT r.id), 0) as adr
            FROM reservations r
            LEFT JOIN bills b ON r.id = b.reservation_id
            WHERE {$where_condition}
            GROUP BY DATE_FORMAT(r.check_in_date, '%Y-%m')
            ORDER BY month DESC
            LIMIT 12
        ";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
        
    } catch (PDOException $e) {
        error_log("Error getting monthly reports: " . $e->getMessage());
        return [];
    }
}

/**
 * Get inventory reports
 */
function getInventoryReports($category_filter = '') {
    global $pdo;
    
    try {
        $where_condition = "1=1";
        $params = [];
        
        if (!empty($category_filter)) {
            $where_condition = "ic.name = ?";
            $params[] = $category_filter;
        }
        
        $query = "
            SELECT 
                ii.item_name,
                ic.name as category_name,
                ii.current_stock,
                ii.minimum_stock,
                ii.unit_price,
                ii.last_updated
            FROM inventory_items ii
            JOIN inventory_categories ic ON ii.category_id = ic.id
            WHERE {$where_condition}
            ORDER BY ii.current_stock ASC
        ";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
        
    } catch (PDOException $e) {
        error_log("Error getting inventory reports: " . $e->getMessage());
        return [];
    }
}

/**
 * Get inventory items
 */
function getInventoryItems() {
    global $pdo;
    
    try {
        $query = "
            SELECT 
                ii.*,
                ic.name as category_name
            FROM inventory_items ii
            JOIN inventory_categories ic ON ii.category_id = ic.id
            ORDER BY ii.item_name
        ";
        
        $stmt = $pdo->query($query);
        return $stmt->fetchAll();
        
    } catch (PDOException $e) {
        error_log("Error getting inventory items: " . $e->getMessage());
        return [];
    }
}

/**
 * Get inventory categories
 */
function getInventoryCategories() {
    global $pdo;
    
    try {
        $query = "
            SELECT 
                ic.*,
                COUNT(ii.id) as items_count
            FROM inventory_categories ic
            LEFT JOIN inventory_items ii ON ic.id = ii.category_id
            GROUP BY ic.id
            ORDER BY ic.name
        ";
        
        $stmt = $pdo->query($query);
        return $stmt->fetchAll();
        
    } catch (PDOException $e) {
        error_log("Error getting inventory categories: " . $e->getMessage());
        return [];
    }
}

/**
 * Get inventory transactions
 */
function getInventoryTransactions() {
    global $pdo;
    
    try {
        $query = "
            SELECT 
                it.*,
                ii.item_name,
                CONCAT(u.first_name, ' ', u.last_name) as user_name
            FROM inventory_transactions it
            JOIN inventory_items ii ON it.item_id = ii.id
            JOIN users u ON it.user_id = u.id
            ORDER BY it.transaction_date DESC
            LIMIT 100
        ";
        
        $stmt = $pdo->query($query);
        return $stmt->fetchAll();
        
    } catch (PDOException $e) {
        error_log("Error getting inventory transactions: " . $e->getMessage());
        return [];
    }
}

/**
 * Get guest demographics
 */
function getGuestDemographics() {
    global $pdo;
    
    try {
        // Age groups
        $stmt = $pdo->query("
            SELECT 
                CASE 
                    WHEN TIMESTAMPDIFF(YEAR, g.date_of_birth, CURDATE()) < 25 THEN '18-24'
                    WHEN TIMESTAMPDIFF(YEAR, g.date_of_birth, CURDATE()) < 35 THEN '25-34'
                    WHEN TIMESTAMPDIFF(YEAR, g.date_of_birth, CURDATE()) < 45 THEN '35-44'
                    WHEN TIMESTAMPDIFF(YEAR, g.date_of_birth, CURDATE()) < 55 THEN '45-54'
                    WHEN TIMESTAMPDIFF(YEAR, g.date_of_birth, CURDATE()) < 65 THEN '55-64'
                    ELSE '65+'
                END as age_group,
                COUNT(*) as count
            FROM guests g
            WHERE g.date_of_birth IS NOT NULL
            GROUP BY age_group
            ORDER BY age_group
        ");
        $age_groups = $stmt->fetchAll();
        
        // Gender distribution
        $stmt = $pdo->query("
            SELECT 
                gender,
                COUNT(*) as count
            FROM guests g
            WHERE g.gender IS NOT NULL
            GROUP BY gender
        ");
        $gender_distribution = $stmt->fetchAll();
        
        // Country distribution
        $stmt = $pdo->query("
            SELECT 
                country,
                COUNT(*) as count
            FROM guests g
            WHERE g.country IS NOT NULL
            GROUP BY country
            ORDER BY count DESC
            LIMIT 10
        ");
        $country_distribution = $stmt->fetchAll();
        
        // Loyalty tier distribution
        $stmt = $pdo->query("
            SELECT 
                loyalty_tier,
                COUNT(*) as count
            FROM guests g
            WHERE g.loyalty_tier IS NOT NULL
            GROUP BY loyalty_tier
            ORDER BY loyalty_tier
        ");
        $loyalty_distribution = $stmt->fetchAll();
        
        return [
            'age_groups' => $age_groups,
            'gender_distribution' => $gender_distribution,
            'country_distribution' => $country_distribution,
            'loyalty_distribution' => $loyalty_distribution
        ];
        
    } catch (PDOException $e) {
        error_log("Error getting guest demographics: " . $e->getMessage());
        return [
            'age_groups' => [],
            'gender_distribution' => [],
            'country_distribution' => [],
            'loyalty_distribution' => []
        ];
    }
}

/**
 * Get service statistics
 */
function getServiceStatistics() {
    global $pdo;
    
    try {
        // Active service requests (using correct status values from maintenance_requests table)
        $stmt = $pdo->query("
            SELECT COUNT(*) as count 
            FROM maintenance_requests 
            WHERE status IN ('reported', 'assigned', 'in_progress')
        ");
        $active_requests = $stmt->fetch()['count'];
        
        // Today's service revenue (using total_price instead of total_amount)
        $stmt = $pdo->query("
            SELECT COALESCE(SUM(total_price), 0) as total 
            FROM service_charges 
            WHERE DATE(created_at) = CURDATE()
        ");
        $today_revenue = $stmt->fetch()['total'];
        
        // Pending services (service_charges doesn't have status, so we'll count all today's charges)
        $stmt = $pdo->query("
            SELECT COUNT(*) as count 
            FROM service_charges 
            WHERE DATE(created_at) = CURDATE()
        ");
        $pending_services = $stmt->fetch()['count'];
        
        // Completed today (using correct status value)
        $stmt = $pdo->query("
            SELECT COUNT(*) as count 
            FROM maintenance_requests 
            WHERE status = 'completed' AND DATE(updated_at) = CURDATE()
        ");
        $completed_today = $stmt->fetch()['count'];
        
        return [
            'active_requests' => $active_requests,
            'today_revenue' => $today_revenue,
            'pending_services' => $pending_services,
            'completed_today' => $completed_today
        ];
        
    } catch (PDOException $e) {
        error_log("Error getting service statistics: " . $e->getMessage());
        return [
            'active_requests' => 0,
            'today_revenue' => 0,
            'pending_services' => 0,
            'completed_today' => 0
        ];
    }
}

/**
 * Get service requests with filters
 */
function getServiceRequests($status_filter = '', $type_filter = '') {
    global $pdo;
    
    try {
        $where_conditions = ["1=1"];
        $params = [];
        
        if (!empty($status_filter)) {
            $where_conditions[] = "mr.status = ?";
            $params[] = $status_filter;
        }
        
        if (!empty($type_filter)) {
            $where_conditions[] = "mr.issue_type = ?";
            $params[] = $type_filter;
        }
        
        $where_clause = implode(" AND ", $where_conditions);
        
        $query = "
            SELECT mr.*, 
                   r.room_number,
                   CONCAT(u1.name, ' (', u1.role, ')') as reported_by_name,
                   u2.name as assigned_to_name,
                   CONCAT(g.first_name, ' ', g.last_name) as guest_name,
                   g.phone as guest_phone
            FROM maintenance_requests mr
            LEFT JOIN rooms r ON mr.room_id = r.id
            LEFT JOIN users u1 ON mr.reported_by = u1.id
            LEFT JOIN users u2 ON mr.assigned_to = u2.id
            LEFT JOIN reservations res ON r.id = res.room_id AND res.status IN ('checked_in', 'confirmed')
            LEFT JOIN guests g ON res.guest_id = g.id
            WHERE {$where_clause}
            ORDER BY mr.created_at DESC
        ";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
        
    } catch (PDOException $e) {
        error_log("Error getting service requests: " . $e->getMessage());
        return [];
    }
}

/**
 * Get additional services with filters
 */
function getAdditionalServices($category_filter = '') {
    global $pdo;
    
    try {
        $where_conditions = ["1=1"];
        $params = [];
        
        if (!empty($category_filter)) {
            $where_conditions[] = "additional_services.category = ?";
            $params[] = $category_filter;
        }
        
        $where_clause = implode(" AND ", $where_conditions);
        
        $query = "
            SELECT sc.*, 
                   additional_services.name as service_name,
                   additional_services.category as service_category,
                   CONCAT(g.first_name, ' ', g.last_name) as guest_name,
                   r.room_number
            FROM service_charges sc
            JOIN additional_services ON sc.service_id = additional_services.id
            JOIN reservations res ON sc.reservation_id = res.id
            JOIN guests g ON res.guest_id = g.id
            JOIN rooms r ON res.room_id = r.id
            WHERE {$where_clause}
            ORDER BY sc.created_at DESC
        ";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
        
    } catch (PDOException $e) {
        error_log("Error getting additional services: " . $e->getMessage());
        return [];
    }
}

/**
 * Get service charges with filters
 */
function getServiceCharges($date_filter = '', $status_filter = '') {
    global $pdo;
    
    try {
        $where_conditions = ["1=1"];
        $params = [];
        
        if (!empty($date_filter)) {
            $where_conditions[] = "DATE(sc.created_at) = ?";
            $params[] = $date_filter;
        }
        
        // Note: service_charges table doesn't have status field, so status_filter is ignored
        
        $where_clause = implode(" AND ", $where_conditions);
        
        $query = "
            SELECT sc.*, 
                   additional_services.name as service_name,
                   additional_services.category as service_category,
                   CONCAT(g.first_name, ' ', g.last_name) as guest_name,
                   r.room_number
            FROM service_charges sc
            JOIN additional_services ON sc.service_id = additional_services.id
            JOIN reservations res ON sc.reservation_id = res.id
            JOIN guests g ON res.guest_id = g.id
            JOIN rooms r ON res.room_id = r.id
            WHERE {$where_clause}
            ORDER BY sc.created_at DESC
        ";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
        
    } catch (PDOException $e) {
        error_log("Error getting service charges: " . $e->getMessage());
        return [];
    }
}

/**
 * Get active reservations for service management
 */
function getActiveReservations() {
    global $pdo;
    
    try {
        $query = "
            SELECT r.id, r.reservation_number,
                   CONCAT(g.first_name, ' ', g.last_name) as guest_name,
                   rm.room_number
            FROM reservations r
            JOIN guests g ON r.guest_id = g.id
            JOIN rooms rm ON r.room_id = rm.id
            WHERE r.status = 'checked_in'
            ORDER BY r.check_in_date DESC
        ";
        
        $stmt = $pdo->query($query);
        return $stmt->fetchAll();
        
    } catch (PDOException $e) {
        error_log("Error getting active reservations: " . $e->getMessage());
        return [];
    }
}

/**
 * Get minibar items
 */
function getMinibarItems() {
    global $pdo;
    
    try {
        $query = "
            SELECT id, name, unit_price, category
            FROM inventory
            WHERE category = 'minibar' AND quantity > 0
            ORDER BY name
        ";
        
        $stmt = $pdo->query($query);
        return $stmt->fetchAll();
        
    } catch (PDOException $e) {
        error_log("Error getting minibar items: " . $e->getMessage());
        return [];
    }
}

/**
 * Get billing statistics
 */
function getBillingStatistics() {
    global $pdo;
    
    try {
        // Today's revenue
        $stmt = $pdo->query("
            SELECT COALESCE(SUM(total_amount), 0) as total 
            FROM bills 
            WHERE DATE(bill_date) = CURDATE() AND status = 'paid'
        ");
        $today_revenue = $stmt->fetch()['total'];
        
        // Pending bills
        $stmt = $pdo->query("
            SELECT COUNT(*) as count 
            FROM bills 
            WHERE status = 'pending'
        ");
        $pending_bills = $stmt->fetch()['count'];
        
        // Total discounts
        $stmt = $pdo->query("
            SELECT COALESCE(SUM(discount_amount), 0) as total 
            FROM discounts 
            WHERE DATE(created_at) = CURDATE()
        ");
        $total_discounts = $stmt->fetch()['total'];
        
        // Total loyalty points
        $stmt = $pdo->query("
            SELECT COALESCE(SUM(points), 0) as total 
            FROM loyalty_points 
            WHERE action = 'earn'
        ");
        $total_loyalty_points = $stmt->fetch()['total'];
        
        return [
            'today_revenue' => $today_revenue,
            'pending_bills' => $pending_bills,
            'total_discounts' => $total_discounts,
            'total_loyalty_points' => $total_loyalty_points
        ];
        
    } catch (PDOException $e) {
        error_log("Error getting billing statistics: " . $e->getMessage());
        return [
            'today_revenue' => 0,
            'pending_bills' => 0,
            'total_discounts' => 0,
            'total_loyalty_points' => 0
        ];
    }
}

/**
 * Get bills with filters
 */
function getBills($status_filter = '', $date_filter = '') {
    global $pdo;
    
    try {
        $where_conditions = ["1=1"];
        $params = [];
        
        if (!empty($status_filter)) {
            $where_conditions[] = "b.status = ?";
            $params[] = $status_filter;
        }
        
        if (!empty($date_filter)) {
            $where_conditions[] = "DATE(b.bill_date) = ?";
            $params[] = $date_filter;
        }
        
        $where_clause = implode(" AND ", $where_conditions);
        
        $query = "
            SELECT b.*, 
                   CONCAT(g.first_name, ' ', g.last_name) as guest_name,
                   r.room_number,
                   COALESCE(d.discount_amount, 0) as discount_amount
            FROM bills b
            JOIN reservations res ON b.reservation_id = res.id
            JOIN guests g ON res.guest_id = g.id
            JOIN rooms r ON res.room_id = r.id
            LEFT JOIN discounts d ON b.id = d.bill_id
            WHERE {$where_clause}
            ORDER BY b.bill_date DESC
        ";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
        
    } catch (PDOException $e) {
        error_log("Error getting bills: " . $e->getMessage());
        return [];
    }
}

/**
 * Get payments with filters
 */
function getPayments($method_filter = '', $date_filter = '') {
    global $pdo;
    
    try {
        $where_conditions = ["1=1"];
        $params = [];
        
        if (!empty($method_filter)) {
            $where_conditions[] = "p.payment_method = ?";
            $params[] = $method_filter;
        }
        
        if (!empty($date_filter)) {
            $where_conditions[] = "DATE(p.payment_date) = ?";
            $params[] = $date_filter;
        }
        
        $where_clause = implode(" AND ", $where_conditions);
        
        $query = "
            SELECT p.*, 
                   CONCAT(g.first_name, ' ', g.last_name) as guest_name,
                   r.room_number,
                   b.bill_number
            FROM payments p
            JOIN bills b ON p.bill_id = b.id
            JOIN reservations res ON b.reservation_id = res.id
            JOIN guests g ON res.guest_id = g.id
            JOIN rooms r ON res.room_id = r.id
            WHERE {$where_clause}
            ORDER BY p.payment_date DESC
        ";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
        
    } catch (PDOException $e) {
        error_log("Error getting payments: " . $e->getMessage());
        return [];
    }
}

/**
 * Get discounts with filters
 */
function getDiscounts($type_filter = '') {
    global $pdo;
    
    try {
        $where_conditions = ["1=1"];
        $params = [];
        
        if (!empty($type_filter)) {
            $where_conditions[] = "d.discount_type = ?";
            $params[] = $type_filter;
        }
        
        $where_clause = implode(" AND ", $where_conditions);
        
        $query = "
            SELECT d.*, 
                   CONCAT(g.first_name, ' ', g.last_name) as guest_name,
                   r.room_number,
                   b.bill_number
            FROM discounts d
            JOIN bills b ON d.bill_id = b.id
            JOIN reservations res ON b.reservation_id = res.id
            JOIN guests g ON res.guest_id = g.id
            JOIN rooms r ON res.room_id = r.id
            WHERE {$where_clause}
            ORDER BY d.created_at DESC
        ";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
        
    } catch (PDOException $e) {
        error_log("Error getting discounts: " . $e->getMessage());
        return [];
    }
}

/**
 * Get vouchers with filters
 */
function getVouchers($status_filter = '') {
    global $pdo;
    
    try {
        $where_conditions = ["1=1"];
        $params = [];
        
        if (!empty($status_filter)) {
            $where_conditions[] = "v.status = ?";
            $params[] = $status_filter;
        }
        
        $where_clause = implode(" AND ", $where_conditions);
        
        $query = "
            SELECT v.*, 
                   COALESCE(vu.used_count, 0) as used_count
            FROM vouchers v
            LEFT JOIN (
                SELECT voucher_id, COUNT(*) as used_count 
                FROM voucher_usage 
                GROUP BY voucher_id
            ) vu ON v.id = vu.voucher_id
            WHERE {$where_clause}
            ORDER BY v.created_at DESC
        ";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
        
    } catch (PDOException $e) {
        error_log("Error getting vouchers: " . $e->getMessage());
        return [];
    }
}

/**
 * Get loyalty data with filters
 */
function getLoyalty($tier_filter = '') {
    global $pdo;
    
    try {
        $where_conditions = ["1=1"];
        $params = [];
        
        if (!empty($tier_filter)) {
            $where_conditions[] = "g.loyalty_tier = ?";
            $params[] = $tier_filter;
        }
        
        $where_clause = implode(" AND ", $where_conditions);
        
        $query = "
            SELECT g.id as guest_id,
                   CONCAT(g.first_name, ' ', g.last_name) as guest_name,
                   g.email,
                   g.loyalty_tier as tier,
                   COALESCE(lp.total_points, 0) as points,
                   COALESCE(sp.total_spent, 0) as total_spent,
                   COALESCE(la.last_activity, g.created_at) as last_activity
            FROM guests g
            LEFT JOIN (
                SELECT guest_id, SUM(CASE WHEN action = 'earn' THEN points ELSE -points END) as total_points
                FROM loyalty_points 
                GROUP BY guest_id
            ) lp ON g.id = lp.guest_id
            LEFT JOIN (
                SELECT res.guest_id, SUM(b.total_amount) as total_spent
                FROM bills b
                JOIN reservations res ON b.reservation_id = res.id
                WHERE b.status = 'paid'
                GROUP BY res.guest_id
            ) sp ON g.id = sp.guest_id
            LEFT JOIN (
                SELECT guest_id, MAX(created_at) as last_activity
                FROM loyalty_points
                GROUP BY guest_id
            ) la ON g.id = la.guest_id
            WHERE {$where_clause}
            ORDER BY lp.total_points DESC
        ";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
        
    } catch (PDOException $e) {
        error_log("Error getting loyalty data: " . $e->getMessage());
        return [];
    }
}

/**
 * Get pending bills for discount application
 */
function getPendingBills() {
    global $pdo;
    
    try {
        $query = "
            SELECT b.id, b.bill_number, b.total_amount,
                   CONCAT(g.first_name, ' ', g.last_name) as guest_name,
                   r.room_number
            FROM bills b
            JOIN reservations res ON b.reservation_id = res.id
            JOIN guests g ON res.guest_id = g.id
            JOIN rooms r ON res.room_id = r.id
            WHERE b.status = 'pending'
            ORDER BY b.bill_date DESC
        ";
        
        $stmt = $pdo->query($query);
        return $stmt->fetchAll();
        
    } catch (PDOException $e) {
        error_log("Error getting pending bills: " . $e->getMessage());
        return [];
    }
}

/**
 * Get all guests for loyalty management
 */
function getAllGuests() {
    global $pdo;
    
    try {
        $query = "
            SELECT id, first_name, last_name, email
            FROM guests
            ORDER BY first_name, last_name
        ";
        
        $stmt = $pdo->query($query);
        return $stmt->fetchAll();
        
    } catch (PDOException $e) {
        error_log("Error getting all guests: " . $e->getMessage());
        return [];
    }
}

/**
 * Get training statistics
 */
function getTrainingStatistics() {
    global $pdo;
    
    try {
        // Completed scenarios
        $stmt = $pdo->query("
            SELECT COUNT(*) as total 
            FROM training_attempts 
            WHERE status = 'completed'
        ");
        $completed_scenarios = $stmt->fetch()['total'];
        
        // Average score
        $stmt = $pdo->query("
            SELECT COALESCE(AVG(score), 0) as avg_score 
            FROM training_attempts 
            WHERE status = 'completed'
        ");
        $average_score = round($stmt->fetch()['avg_score'], 1);
        
        // Training hours
        $stmt = $pdo->query("
            SELECT COALESCE(SUM(duration_minutes), 0) / 60 as hours 
            FROM training_attempts 
            WHERE status = 'completed'
        ");
        $training_hours = round($stmt->fetch()['hours'], 1);
        
        // Certificates earned
        $stmt = $pdo->query("
            SELECT COUNT(*) as total 
            FROM training_certificates 
            WHERE status = 'earned'
        ");
        $certificates_earned = $stmt->fetch()['total'];
        
        return [
            'completed_scenarios' => $completed_scenarios,
            'average_score' => $average_score,
            'training_hours' => $training_hours,
            'certificates_earned' => $certificates_earned
        ];
        
    } catch (PDOException $e) {
        error_log("Error getting training statistics: " . $e->getMessage());
        // Return sample data if database is not available
        return [
            'completed_scenarios' => 5,
            'average_score' => 85.5,
            'training_hours' => 12.5,
            'certificates_earned' => 2
        ];
    }
}

/**
 * Get training scenarios
 */
function getTrainingScenarios($difficulty_filter = '', $category_filter = '') {
    global $pdo;
    
    try {
        $where_conditions = ["status = 'active'"];
        $params = [];
        
        if (!empty($difficulty_filter)) {
            $where_conditions[] = "difficulty = ?";
            $params[] = $difficulty_filter;
        }
        
        if (!empty($category_filter)) {
            $where_conditions[] = "category = ?";
            $params[] = $category_filter;
        }
        
        $where_clause = implode(" AND ", $where_conditions);
        
        $query = "
            SELECT * FROM training_scenarios 
            WHERE {$where_clause}
            ORDER BY difficulty, title
        ";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
        
    } catch (PDOException $e) {
        error_log("Error getting training scenarios: " . $e->getMessage());
        // Return empty array if database is not available
        // The API will fall back to sample data
        return [];
    }
}

/**
 * Get customer service scenarios
 */
function getCustomerServiceScenarios($type_filter = '') {
    global $pdo;
    
    try {
        $where_conditions = ["status = 'active'"];
        $params = [];
        
        if (!empty($type_filter)) {
            $where_conditions[] = "type = ?";
            $params[] = $type_filter;
        }
        
        $where_clause = implode(" AND ", $where_conditions);
        
        $query = "
            SELECT * FROM customer_service_scenarios 
            WHERE {$where_clause}
            ORDER BY difficulty, title
        ";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
        
    } catch (PDOException $e) {
        error_log("Error getting customer service scenarios: " . $e->getMessage());
        return [];
    }
}

/**
 * Get problem scenarios
 */
function getProblemScenarios($severity_filter = '') {
    global $pdo;
    
    try {
        $where_conditions = ["status = 'active'"];
        $params = [];
        
        if (!empty($severity_filter)) {
            $where_conditions[] = "severity = ?";
            $params[] = $severity_filter;
        }
        
        $where_clause = implode(" AND ", $where_conditions);
        
        $query = "
            SELECT * FROM problem_scenarios 
            WHERE {$where_clause}
            ORDER BY severity, difficulty, title
        ";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
        
    } catch (PDOException $e) {
        error_log("Error getting problem scenarios: " . $e->getMessage());
        return [];
    }
}

/**
 * Get scenario details
 */
function getScenarioDetails($scenario_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM training_scenarios 
            WHERE id = ?
        ");
        $stmt->execute([$scenario_id]);
        $scenario = $stmt->fetch();
        
        if (!$scenario) {
            return null;
        }
        
        // Get questions for this scenario
        $stmt = $pdo->prepare("
            SELECT * FROM scenario_questions 
            WHERE scenario_id = ?
            ORDER BY question_order
        ");
        $stmt->execute([$scenario_id]);
        $questions = $stmt->fetchAll();
        
        // Get options for each question
        foreach ($questions as &$question) {
            $stmt = $pdo->prepare("
                SELECT * FROM question_options 
                WHERE question_id = ?
                ORDER BY option_order
            ");
            $stmt->execute([$question['id']]);
            $question['options'] = $stmt->fetchAll();
        }
        
        $scenario['questions'] = $questions;
        
        return $scenario;
        
    } catch (PDOException $e) {
        error_log("Error getting scenario details: " . $e->getMessage());
        return null;
    }
}

/**
 * Get customer service scenario details
 */
function getCustomerServiceDetails($scenario_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM customer_service_scenarios 
            WHERE id = ?
        ");
        $stmt->execute([$scenario_id]);
        
        return $stmt->fetch();
        
    } catch (PDOException $e) {
        error_log("Error getting customer service details: " . $e->getMessage());
        return null;
    }
}

/**
 * Get problem scenario details
 */
function getProblemDetails($scenario_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM problem_scenarios 
            WHERE id = ?
        ");
        $stmt->execute([$scenario_id]);
        
        return $stmt->fetch();
        
    } catch (PDOException $e) {
        error_log("Error getting problem details: " . $e->getMessage());
        return null;
    }
}

/**
 * Submit scenario attempt
 */
function submitScenarioAttempt($scenario_id, $answers, $user_id) {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        // Calculate score
        $score = calculateScenarioScore($scenario_id, $answers);
        
        // Record attempt
        $stmt = $pdo->prepare("
            INSERT INTO training_attempts (
                user_id, scenario_id, scenario_type, answers, score, 
                duration_minutes, status, created_at
            ) VALUES (?, ?, 'scenario', ?, ?, ?, 'completed', NOW())
        ");
        $stmt->execute([
            $user_id, 
            $scenario_id, 
            json_encode($answers), 
            $score, 
            0 // Duration will be updated by JavaScript
        ]);
        
        // Check for certificate eligibility
        checkCertificateEligibility($user_id, 'scenario');
        
        $pdo->commit();
        
        return [
            'success' => true,
            'score' => $score,
            'message' => 'Scenario completed successfully'
        ];
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Error submitting scenario attempt: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Error submitting scenario attempt'
        ];
    }
}

/**
 * Submit customer service attempt
 */
function submitCustomerServiceAttempt($scenario_id, $response, $user_id) {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        // Calculate score based on response quality
        $score = evaluateCustomerServiceResponse($scenario_id, $response);
        
        // Record attempt
        $stmt = $pdo->prepare("
            INSERT INTO training_attempts (
                user_id, scenario_id, scenario_type, answers, score, 
                duration_minutes, status, created_at
            ) VALUES (?, ?, 'customer_service', ?, ?, ?, 'completed', NOW())
        ");
        $stmt->execute([
            $user_id, 
            $scenario_id, 
            json_encode(['response' => $response]), 
            $score, 
            0
        ]);
        
        // Check for certificate eligibility
        checkCertificateEligibility($user_id, 'customer_service');
        
        $pdo->commit();
        
        return [
            'success' => true,
            'score' => $score,
            'message' => 'Customer service response submitted successfully'
        ];
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Error submitting customer service attempt: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Error submitting customer service attempt'
        ];
    }
}

/**
 * Submit problem solving attempt
 */
function submitProblemAttempt($scenario_id, $solution, $user_id) {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        // Calculate score based on solution quality
        $score = evaluateProblemSolution($scenario_id, $solution);
        
        // Record attempt
        $stmt = $pdo->prepare("
            INSERT INTO training_attempts (
                user_id, scenario_id, scenario_type, answers, score, 
                duration_minutes, status, created_at
            ) VALUES (?, ?, 'problem_solving', ?, ?, ?, 'completed', NOW())
        ");
        $stmt->execute([
            $user_id, 
            $scenario_id, 
            json_encode(['solution' => $solution]), 
            $score, 
            0
        ]);
        
        // Check for certificate eligibility
        checkCertificateEligibility($user_id, 'problem_solving');
        
        $pdo->commit();
        
        return [
            'success' => true,
            'score' => $score,
            'message' => 'Problem solution submitted successfully'
        ];
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Error submitting problem attempt: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Error submitting problem attempt'
        ];
    }
}

/**
 * Get training progress
 */
function getTrainingProgress($user_id) {
    global $pdo;
    
    try {
        // Overall statistics
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_attempts,
                COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_attempts,
                COALESCE(AVG(CASE WHEN status = 'completed' THEN score END), 0) as average_score,
                COALESCE(SUM(CASE WHEN status = 'completed' THEN duration_minutes END), 0) as total_minutes
            FROM training_attempts 
            WHERE user_id = ?
        ");
        $stmt->execute([$user_id]);
        $stats = $stmt->fetch();
        
        $completion_rate = $stats['total_attempts'] > 0 ? 
            round(($stats['completed_attempts'] / $stats['total_attempts']) * 100, 1) : 0;
        
        // Recent activity
        $stmt = $pdo->prepare("
            SELECT 
                ta.*,
                ts.title as scenario_title
            FROM training_attempts ta
            LEFT JOIN training_scenarios ts ON ta.scenario_id = ts.id
            WHERE ta.user_id = ? AND ta.status = 'completed'
            ORDER BY ta.created_at DESC
            LIMIT 10
        ");
        $stmt->execute([$user_id]);
        $recent_activity = $stmt->fetchAll();
        
        // Certificates earned
        $stmt = $pdo->prepare("
            SELECT * FROM training_certificates 
            WHERE user_id = ? AND status = 'earned'
            ORDER BY earned_at DESC
        ");
        $stmt->execute([$user_id]);
        $certificates = $stmt->fetchAll();
        
        return [
            'completion_rate' => $completion_rate,
            'average_score' => round($stats['average_score'], 1),
            'total_points' => calculateTotalPoints($user_id),
            'training_hours' => round($stats['total_minutes'] / 60, 1),
            'recent_activity' => $recent_activity,
            'certificates' => $certificates
        ];
        
    } catch (PDOException $e) {
        error_log("Error getting training progress: " . $e->getMessage());
        return [
            'completion_rate' => 0,
            'average_score' => 0,
            'total_points' => 0,
            'training_hours' => 0,
            'recent_activity' => [],
            'certificates' => []
        ];
    }
}

// Helper functions
function calculateScenarioScore($scenario_id, $answers) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total_questions 
            FROM scenario_questions 
            WHERE scenario_id = ?
        ");
        $stmt->execute([$scenario_id]);
        $total_questions = $stmt->fetch()['total_questions'];
        
        if ($total_questions == 0) return 0;
        
        $correct_answers = 0;
        
        foreach ($answers as $question_key => $answer) {
            $question_number = intval(substr($question_key, 1)); // Remove 'q' prefix
            
            $stmt = $pdo->prepare("
                SELECT correct_answer 
                FROM scenario_questions 
                WHERE scenario_id = ? AND question_order = ?
            ");
            $stmt->execute([$scenario_id, $question_number]);
            $correct_answer = $stmt->fetch()['correct_answer'];
            
            if ($answer == $correct_answer) {
                $correct_answers++;
            }
        }
        
        return round(($correct_answers / $total_questions) * 100, 1);
        
    } catch (PDOException $e) {
        error_log("Error calculating scenario score: " . $e->getMessage());
        return 0;
    }
}

function evaluateCustomerServiceResponse($scenario_id, $response) {
    // Simple evaluation based on response length and keywords
    $score = 50; // Base score
    
    // Length bonus
    if (strlen($response) > 100) $score += 20;
    if (strlen($response) > 200) $score += 10;
    
    // Keyword bonus
    $positive_keywords = ['apologize', 'sorry', 'understand', 'help', 'assist', 'resolve', 'solution'];
    $keyword_count = 0;
    
    foreach ($positive_keywords as $keyword) {
        if (stripos($response, $keyword) !== false) {
            $keyword_count++;
        }
    }
    
    $score += ($keyword_count * 5);
    
    return min(100, $score);
}

function evaluateProblemSolution($scenario_id, $solution) {
    // Simple evaluation based on solution length and structure
    $score = 50; // Base score
    
    // Length bonus
    if (strlen($solution) > 150) $score += 20;
    if (strlen($solution) > 300) $score += 10;
    
    // Structure bonus (check for numbered steps or bullet points)
    if (preg_match('/\d+\./', $solution) || preg_match('/•/', $solution)) {
        $score += 15;
    }
    
    // Action words bonus
    $action_words = ['implement', 'resolve', 'fix', 'address', 'handle', 'manage', 'coordinate'];
    $action_count = 0;
    
    foreach ($action_words as $word) {
        if (stripos($solution, $word) !== false) {
            $action_count++;
        }
    }
    
    $score += ($action_count * 3);
    
    return min(100, $score);
}

function checkCertificateEligibility($user_id, $type) {
    global $pdo;
    
    try {
        // Check if user has completed enough scenarios of this type
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as completed_count, AVG(score) as avg_score
            FROM training_attempts 
            WHERE user_id = ? AND scenario_type = ? AND status = 'completed'
        ");
        $stmt->execute([$user_id, $type]);
        $stats = $stmt->fetch();
        
        // Award certificate if criteria met
        if ($stats['completed_count'] >= 5 && $stats['avg_score'] >= 80) {
            $certificate_name = ucfirst($type) . " Excellence Certificate";
            
            $stmt = $pdo->prepare("
                INSERT INTO training_certificates (
                    user_id, name, type, status, earned_at
                ) VALUES (?, ?, ?, 'earned', NOW())
                ON DUPLICATE KEY UPDATE earned_at = NOW()
            ");
            $stmt->execute([$user_id, $certificate_name, $type]);
        }
        
    } catch (PDOException $e) {
        error_log("Error checking certificate eligibility: " . $e->getMessage());
    }
}

function calculateTotalPoints($user_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(score), 0) as total_points
            FROM training_attempts 
            WHERE user_id = ? AND status = 'completed'
        ");
        $stmt->execute([$user_id]);
        
        return $stmt->fetch()['total_points'];
        
    } catch (PDOException $e) {
        error_log("Error calculating total points: " . $e->getMessage());
        return 0;
    }
}

/**
 * Get guest statistics
 */
function getGuestStatistics() {
    global $pdo;
    
    try {
        // Total guests
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM guests");
        $total_guests = $stmt->fetch()['total'];
        
        // VIP guests
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM guests WHERE is_vip = 1");
        $vip_guests = $stmt->fetch()['total'];
        
        // Active guests (currently checked in)
        $stmt = $pdo->query("
            SELECT COUNT(DISTINCT g.id) as total 
            FROM guests g 
            JOIN reservations r ON g.id = r.guest_id 
            WHERE r.status = 'checked_in'
        ");
        $active_guests = $stmt->fetch()['total'];
        
        // New guests this month
        $stmt = $pdo->query("
            SELECT COUNT(*) as total 
            FROM guests 
            WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) 
            AND YEAR(created_at) = YEAR(CURRENT_DATE())
        ");
        $new_guests = $stmt->fetch()['total'];
        
        // Pending feedback (if feedback table exists)
        $pending_feedback = 0;
        try {
            $stmt = $pdo->query("SELECT COUNT(*) as total FROM feedback WHERE status = 'pending'");
            $result = $stmt->fetch();
            $pending_feedback = $result ? $result['total'] : 0;
        } catch (PDOException $e) {
            // Feedback table might not exist, so we'll use 0
            $pending_feedback = 0;
        }
        
        return [
            'total_guests' => $total_guests,
            'vip_guests' => $vip_guests,
            'active_guests' => $active_guests,
            'new_guests' => $new_guests,
            'pending_feedback' => $pending_feedback
        ];
        
    } catch (PDOException $e) {
        error_log("Error getting guest statistics: " . $e->getMessage());
        return [
            'total_guests' => 0,
            'vip_guests' => 0,
            'active_guests' => 0,
            'new_guests' => 0,
            'pending_feedback' => 0
        ];
    }
}

/**
 * Get service request statistics
 */
function getServiceRequestStats() {
    global $pdo;
    
    try {
        // Pending requests
        $stmt = $pdo->query("SELECT COUNT(*) as pending FROM maintenance_requests WHERE status = 'pending'");
        $pending = $stmt->fetch()['pending'];
        
        // Completed today
        $stmt = $pdo->query("SELECT COUNT(*) as completed FROM maintenance_requests WHERE status = 'completed' AND DATE(updated_at) = CURDATE()");
        $completed = $stmt->fetch()['completed'];
        
        // Urgent requests
        $stmt = $pdo->query("SELECT COUNT(*) as urgent FROM maintenance_requests WHERE priority = 'urgent' AND status != 'completed'");
        $urgent = $stmt->fetch()['urgent'];
        
        // Average response time (in minutes)
        $stmt = $pdo->query("
            SELECT AVG(TIMESTAMPDIFF(MINUTE, created_at, updated_at)) as avg_time 
            FROM maintenance_requests 
            WHERE status = 'completed' AND updated_at IS NOT NULL
        ");
        $avg_response_time = round($stmt->fetch()['avg_time'] ?? 0);
        
        return [
            'pending' => $pending,
            'completed' => $completed,
            'urgent' => $urgent,
            'avg_response_time' => $avg_response_time
        ];
        
    } catch (PDOException $e) {
        error_log("Error getting service request stats: " . $e->getMessage());
        return [
            'pending' => 0,
            'completed' => 0,
            'urgent' => 0,
            'avg_response_time' => 0
        ];
    }
}

/**
 * Get all guests
 */
function getGuests() {
    global $pdo;
    
    try {
        $stmt = $pdo->query("
            SELECT g.id, 
                   CONCAT(g.first_name, ' ', g.last_name) as name,
                   r.room_number 
            FROM guests g 
            LEFT JOIN reservations res ON g.id = res.guest_id 
            LEFT JOIN rooms r ON res.room_id = r.id 
            WHERE res.status = 'checked_in' OR res.status IS NULL
            ORDER BY g.first_name, g.last_name
        ");
        return $stmt->fetchAll();
        
    } catch (PDOException $e) {
        error_log("Error getting guests: " . $e->getMessage());
        return [];
    }
}

/**
 * Get all staff members
 */
function getStaff() {
    global $pdo;
    
    try {
        $stmt = $pdo->query("
            SELECT id, name, role 
            FROM users 
            WHERE role IN ('housekeeping', 'maintenance', 'concierge', 'manager')
            ORDER BY name
        ");
        return $stmt->fetchAll();
        
    } catch (PDOException $e) {
        error_log("Error getting staff: " . $e->getMessage());
        return [];
    }
}

/**
 * Get request type badge class
 */
function getRequestTypeBadgeClass($type) {
    $classes = [
        'room_service' => 'bg-blue-100 text-blue-800',
        'housekeeping' => 'bg-green-100 text-green-800',
        'maintenance' => 'bg-yellow-100 text-yellow-800',
        'concierge' => 'bg-purple-100 text-purple-800',
        'other' => 'bg-gray-100 text-gray-800'
    ];
    
    return $classes[$type] ?? 'bg-gray-100 text-gray-800';
}

/**
 * Get request type label
 */
function getRequestTypeLabel($type) {
    $labels = [
        'room_service' => 'Room Service',
        'housekeeping' => 'Housekeeping',
        'maintenance' => 'Maintenance',
        'concierge' => 'Concierge',
        'other' => 'Other'
    ];
    
    return $labels[$type] ?? 'Unknown';
}

/**
 * Get priority badge class
 */
function getPriorityBadgeClass($priority) {
    $classes = [
        'low' => 'bg-gray-100 text-gray-800',
        'medium' => 'bg-blue-100 text-blue-800',
        'high' => 'bg-yellow-100 text-yellow-800',
        'urgent' => 'bg-red-100 text-red-800'
    ];
    
    return $classes[$priority] ?? 'bg-gray-100 text-gray-800';
}

/**
 * Get status badge class
 */
function getStatusBadgeClass($status) {
    switch ($status) {
        case 'available': return 'bg-green-100 text-green-800';
        case 'occupied': return 'bg-red-100 text-red-800';
        case 'reserved': return 'bg-yellow-100 text-yellow-800';
        case 'maintenance': return 'bg-blue-100 text-blue-800';
        case 'out_of_service': return 'bg-gray-100 text-gray-800';
        case 'pending': return 'bg-yellow-100 text-yellow-800';
        case 'in_progress': return 'bg-blue-100 text-blue-800';
        case 'completed': return 'bg-green-100 text-green-800';
        case 'cancelled': return 'bg-red-100 text-red-800';
        default: return 'bg-gray-100 text-gray-800';
    }
}

/**
 * Get status label
 */
function getStatusLabel($status) {
    switch ($status) {
        case 'available': return 'Available';
        case 'occupied': return 'Occupied';
        case 'reserved': return 'Reserved';
        case 'maintenance': return 'Maintenance';
        case 'out_of_service': return 'Out of Service';
        case 'pending': return 'Pending';
        case 'in_progress': return 'In Progress';
        case 'completed': return 'Completed';
        case 'cancelled': return 'Cancelled';
        default: return ucfirst($status);
    }
}

/**
 * Get guest details by guest ID
 */
function getGuestDetails($guest_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM guests 
            WHERE id = ?
        ");
        $stmt->execute([$guest_id]);
        return $stmt->fetch();
        
    } catch (PDOException $e) {
        error_log("Error getting guest details: " . $e->getMessage());
        return null;
    }
}

/**
 * Get room details by room ID
 */
function getRoomDetails($room_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM rooms 
            WHERE id = ?
        ");
        $stmt->execute([$room_id]);
        return $stmt->fetch();
        
    } catch (PDOException $e) {
        error_log("Error getting room details: " . $e->getMessage());
        return null;
    }
}

/**
 * Get billing details for a reservation
 */
function getBillingDetails($reservation_id) {
    global $pdo;
    
    try {
        // Get services total
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(amount), 0) as services_total
            FROM service_charges 
            WHERE reservation_id = ?
        ");
        $stmt->execute([$reservation_id]);
        $services_total = $stmt->fetchColumn();
        
        // Calculate taxes (assuming 10% tax rate)
        $stmt = $pdo->prepare("
            SELECT total_amount FROM reservations WHERE id = ?
        ");
        $stmt->execute([$reservation_id]);
        $total_amount = $stmt->fetchColumn();
        
        $taxes = $total_amount * 0.10; // 10% tax
        
        return [
            'services_total' => $services_total,
            'taxes' => $taxes
        ];
        
    } catch (PDOException $e) {
        error_log("Error getting billing details: " . $e->getMessage());
        return [
            'services_total' => 0,
            'taxes' => 0
        ];
    }
}

/**
 * Get check-in details for a reservation
 */
function getCheckInDetails($reservation_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT 
                ci.checked_in_at,
                ci.checked_out_at,
                ci.checked_in_by,
                ci.checked_out_by,
                u1.name as checked_in_by_name,
                u2.name as checked_out_by_name
            FROM check_ins ci
            LEFT JOIN users u1 ON ci.checked_in_by = u1.id
            LEFT JOIN users u2 ON ci.checked_out_by = u2.id
            WHERE ci.reservation_id = ?
        ");
        $stmt->execute([$reservation_id]);
        $result = $stmt->fetch();
        
        if ($result) {
            return [
                'checked_in_at' => $result['checked_in_at'],
                'checked_out_at' => $result['checked_out_at'],
                'checked_in_by' => $result['checked_in_by_name'] ?? $result['checked_in_by'],
                'checked_out_by' => $result['checked_out_by_name'] ?? $result['checked_out_by']
            ];
        }
        
        return null;
        
    } catch (PDOException $e) {
        error_log("Error getting check-in details: " . $e->getMessage());
        return null;
    }
}

/**
 * Get additional services for a reservation
 */
function getAdditionalServicesForReservation($reservation_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT 
                sc.service_name,
                sc.amount,
                sc.created_at
            FROM service_charges sc
            WHERE sc.reservation_id = ?
            ORDER BY sc.created_at DESC
        ");
        $stmt->execute([$reservation_id]);
        return $stmt->fetchAll();
        
    } catch (PDOException $e) {
        error_log("Error getting additional services: " . $e->getMessage());
        return [];
    }
}

/**
 * Get current guest for a room
 */
function getCurrentGuest($room_id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            SELECT CONCAT(g.first_name, ' ', g.last_name) as guest_name
            FROM reservations r
            JOIN guests g ON r.guest_id = g.id
            WHERE r.room_id = ? AND r.status = 'checked_in'
            LIMIT 1
        ");
        $stmt->execute([$room_id]);
        $result = $stmt->fetch();
        return $result ? $result['guest_name'] : '-';
    } catch (PDOException $e) {
        return '-';
    }
}

/**
 * Get check-in date for a room
 */
function getCheckInDate($room_id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            SELECT check_in_date
            FROM reservations
            WHERE room_id = ? AND status = 'checked_in'
            LIMIT 1
        ");
        $stmt->execute([$room_id]);
        $result = $stmt->fetch();
        return $result ? date('M d, Y', strtotime($result['check_in_date'])) : '-';
    } catch (PDOException $e) {
        return '-';
    }
}

/**
 * Get check-out date for a room
 */
function getCheckOutDate($room_id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            SELECT check_out_date
            FROM reservations
            WHERE room_id = ? AND status = 'checked_in'
            LIMIT 1
        ");
        $stmt->execute([$room_id]);
        $result = $stmt->fetch();
        return $result ? date('M d, Y', strtotime($result['check_out_date'])) : '-';
    } catch (PDOException $e) {
        return '-';
    }
}

?>