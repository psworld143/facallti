<?php
/**
 * POS Module Database Configuration
 * Extends the main PMS database connection for POS-specific operations
 */

// Include the main PMS database configuration
require_once __DIR__ . '/../../includes/database.php';

// POS-specific database functions
class POSDatabase {
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
     * Check if POS tables exist
     */
    public function checkPOSTables() {
        try {
            $tables = ['pos_transactions', 'pos_menu_items', 'pos_orders', 'pos_payments'];
            foreach ($tables as $table) {
                $stmt = $this->pdo->query("SHOW TABLES LIKE '$table'");
                if ($stmt->rowCount() == 0) {
                    return false;
                }
            }
            return true;
        } catch (PDOException $e) {
            error_log("POS Database check failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Initialize POS tables if they don't exist
     */
    public function initializePOSTables() {
        try {
            // This would create POS tables if they don't exist
            // For now, just return true as tables should already exist
            return true;
        } catch (PDOException $e) {
            error_log("POS Tables initialization failed: " . $e->getMessage());
            return false;
        }
    }
}

// Initialize POS database
$pos_db = new POSDatabase();
?>
