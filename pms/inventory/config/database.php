<?php
/**
 * Inventory Module Database Configuration
 * Extends the main PMS database connection for Inventory-specific operations
 */

// Include the main PMS database configuration
require_once __DIR__ . '/../../includes/database.php';

// Inventory-specific database functions
class InventoryDatabase {
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
     * Check if inventory tables exist
     */
    public function checkInventoryTables() {
        try {
            $tables = ['inventory', 'inventory_items', 'inventory_categories', 'inventory_transactions'];
            foreach ($tables as $table) {
                $stmt = $this->pdo->query("SHOW TABLES LIKE '$table'");
                if ($stmt->rowCount() == 0) {
                    return false;
                }
            }
            return true;
        } catch (PDOException $e) {
            error_log("Inventory Database check failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get inventory items
     */
    public function getInventoryItems($category = null, $search = null) {
        try {
            $sql = "SELECT i.*, c.name as category_name 
                    FROM inventory_items i
                    LEFT JOIN inventory_categories c ON i.category_id = c.id
                    WHERE 1=1";
            
            $params = [];
            
            if ($category) {
                $sql .= " AND i.category_id = ?";
                $params[] = $category;
            }
            
            if ($search) {
                $sql .= " AND (i.name LIKE ? OR i.description LIKE ? OR i.sku LIKE ?)";
                $search_term = "%$search%";
                $params = array_merge($params, [$search_term, $search_term, $search_term]);
            }
            
            $sql .= " ORDER BY i.name";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
            
        } catch (PDOException $e) {
            error_log("Error getting inventory items: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Update inventory quantity
     */
    public function updateInventoryQuantity($item_id, $quantity_change, $reason = '') {
        try {
            $this->pdo->beginTransaction();
            
            // Update item quantity
            $stmt = $this->pdo->prepare("
                UPDATE inventory_items 
                SET quantity = quantity + ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$quantity_change, $item_id]);
            
            // Log transaction
            $stmt = $this->pdo->prepare("
                INSERT INTO inventory_transactions (
                    item_id, quantity_change, reason, created_at
                ) VALUES (?, ?, ?, NOW())
            ");
            $stmt->execute([$item_id, $quantity_change, $reason]);
            
            $this->pdo->commit();
            return true;
            
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            error_log("Error updating inventory: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get low stock items
     */
    public function getLowStockItems($threshold = 10) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT i.*, c.name as category_name
                FROM inventory_items i
                LEFT JOIN inventory_categories c ON i.category_id = c.id
                WHERE i.quantity <= ?
                ORDER BY i.quantity ASC
            ");
            
            $stmt->execute([$threshold]);
            return $stmt->fetchAll();
            
        } catch (PDOException $e) {
            error_log("Error getting low stock items: " . $e->getMessage());
            return [];
        }
    }
}

// Initialize inventory database
$inventory_db = new InventoryDatabase();
?>
