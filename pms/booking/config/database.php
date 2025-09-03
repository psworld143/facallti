<?php
/**
 * Booking Module Database Configuration
 * Extends the main PMS database connection for Booking-specific operations
 */

// Include the main PMS database configuration
require_once __DIR__ . '/../../includes/database.php';

// Booking-specific database functions
class BookingDatabase {
    private $pdo;
    
    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
    }
    
    /**
     * Get database connection
     */
    public function getConnection() {
        return $this->pdo;
    }
    
    /**
     * Check if booking tables exist
     */
    public function checkBookingTables() {
        try {
            $tables = ['reservations', 'rooms', 'guests', 'check_ins', 'billing'];
            foreach ($tables as $table) {
                $stmt = $this->pdo->query("SHOW TABLES LIKE '$table'");
                if ($stmt->rowCount() == 0) {
                    return false;
                }
            }
            return true;
        } catch (PDOException $e) {
            error_log("Booking Database check failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get room availability
     */
    public function getRoomAvailability($check_in, $check_out, $room_type = null) {
        try {
            $sql = "SELECT r.* FROM rooms r 
                    WHERE r.id NOT IN (
                        SELECT DISTINCT res.room_id 
                        FROM reservations res 
                        WHERE (res.check_in <= ? AND res.check_out >= ?) 
                        OR (res.check_in <= ? AND res.check_out >= ?)
                        OR (res.check_in >= ? AND res.check_out <= ?)
                    )";
            
            $params = [$check_out, $check_in, $check_in, $check_in, $check_in, $check_out];
            
            if ($room_type) {
                $sql .= " AND r.type = ?";
                $params[] = $room_type;
            }
            
            $sql .= " AND r.status = 'available'";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
            
        } catch (PDOException $e) {
            error_log("Error getting room availability: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Create reservation
     */
    public function createReservation($data) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO reservations (
                    guest_id, room_id, check_in, check_out, 
                    adults, children, total_amount, status, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, 'confirmed', NOW())
            ");
            
            $stmt->execute([
                $data['guest_id'],
                $data['room_id'],
                $data['check_in'],
                $data['check_out'],
                $data['adults'],
                $data['children'],
                $data['total_amount']
            ]);
            
            return $this->pdo->lastInsertId();
            
        } catch (PDOException $e) {
            error_log("Error creating reservation: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get reservation details
     */
    public function getReservation($reservation_id) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT r.*, g.name as guest_name, g.email, g.phone,
                       rm.room_number, rm.type as room_type
                FROM reservations r
                JOIN guests g ON r.guest_id = g.id
                JOIN rooms rm ON r.room_id = rm.id
                WHERE r.id = ?
            ");
            
            $stmt->execute([$reservation_id]);
            return $stmt->fetch();
            
        } catch (PDOException $e) {
            error_log("Error getting reservation: " . $e->getMessage());
            return false;
        }
    }
}

// Initialize booking database
$booking_db = new BookingDatabase();
?>
