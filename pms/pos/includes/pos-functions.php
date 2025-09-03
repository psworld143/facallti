<?php
/**
 * POS System Functions
 * Handles all Point of Sale operations and statistics
 * 
 * Note: This file requires the unified database configuration
 * from pms/includes/database.php to be included before this file
 */

/**
 * Get POS statistics for dashboard
 */
function getPOSStats() {
    global $pdo;
    
    try {
        // Initialize default values
        $stats = [
            'today_sales' => 0,
            'today_transactions' => 0,
            'active_orders' => 0,
            'monthly_revenue' => 0,
            'recent_transactions' => []
        ];
        
        // Get today's sales
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(total_amount), 0) as today_sales,
                   COUNT(*) as today_transactions
            FROM pos_transactions 
            WHERE DATE(created_at) = CURDATE()
            AND status = 'completed'
        ");
        $stmt->execute();
        $today_data = $stmt->fetch();
        
        $stats['today_sales'] = $today_data['today_sales'];
        $stats['today_transactions'] = $today_data['today_transactions'];
        
        // Get active orders
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as active_orders
            FROM pos_transactions 
            WHERE status IN ('pending', 'preparing', 'ready')
        ");
        $stmt->execute();
        $active_data = $stmt->fetch();
        $stats['active_orders'] = $active_data['active_orders'];
        
        // Get monthly revenue
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(total_amount), 0) as monthly_revenue
            FROM pos_transactions 
            WHERE YEAR(created_at) = YEAR(CURDATE())
            AND MONTH(created_at) = MONTH(CURDATE())
            AND status = 'completed'
        ");
        $stmt->execute();
        $monthly_data = $stmt->fetch();
        $stats['monthly_revenue'] = $monthly_data['monthly_revenue'];
        
        // Get recent transactions
        $stmt = $pdo->prepare("
            SELECT id, service_type, total_amount, status, created_at
            FROM pos_transactions 
            ORDER BY created_at DESC 
            LIMIT 10
        ");
        $stmt->execute();
        $stats['recent_transactions'] = $stmt->fetchAll();
        
        return $stats;
        
    } catch (PDOException $e) {
        error_log("Error getting POS stats: " . $e->getMessage());
        return $stats; // Return default values on error
    }
}

/**
 * Create a new POS transaction
 */
function createPOSTransaction($data) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO pos_transactions (
                service_type, guest_id, room_number, items, 
                subtotal, tax_amount, discount_amount, total_amount,
                payment_method, status, notes, created_by
            ) VALUES (
                :service_type, :guest_id, :room_number, :items,
                :subtotal, :tax_amount, :discount_amount, :total_amount,
                :payment_method, :status, :notes, :created_by
            )
        ");
        
        $stmt->execute([
            'service_type' => $data['service_type'],
            'guest_id' => $data['guest_id'] ?? null,
            'room_number' => $data['room_number'] ?? null,
            'items' => json_encode($data['items']),
            'subtotal' => $data['subtotal'],
            'tax_amount' => $data['tax_amount'],
            'discount_amount' => $data['discount_amount'],
            'total_amount' => $data['total_amount'],
            'payment_method' => $data['payment_method'],
            'status' => $data['status'] ?? 'pending',
            'notes' => $data['notes'] ?? '',
            'created_by' => $data['created_by']
        ]);
        
        return $pdo->lastInsertId();
        
    } catch (PDOException $e) {
        error_log("Error creating POS transaction: " . $e->getMessage());
        return false;
    }
}

/**
 * Get POS transaction by ID
 */
function getPOSTransaction($transaction_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM pos_transactions WHERE id = :id
        ");
        $stmt->execute(['id' => $transaction_id]);
        return $stmt->fetch();
        
    } catch (PDOException $e) {
        error_log("Error getting POS transaction: " . $e->getMessage());
        return false;
    }
}

/**
 * Update POS transaction status
 */
function updatePOSTransactionStatus($transaction_id, $status, $notes = '') {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            UPDATE pos_transactions 
            SET status = :status, notes = :notes, updated_at = NOW()
            WHERE id = :id
        ");
        
        return $stmt->execute([
            'status' => $status,
            'notes' => $notes,
            'id' => $transaction_id
        ]);
        
    } catch (PDOException $e) {
        error_log("Error updating POS transaction status: " . $e->getMessage());
        return false;
    }
}

/**
 * Get menu items by category
 */
function getMenuItems($category = null) {
    global $pdo;
    
    try {
        $sql = "SELECT * FROM pos_menu_items WHERE active = 1";
        $params = [];
        
        if ($category) {
            $sql .= " AND category = :category";
            $params['category'] = $category;
        }
        
        $sql .= " ORDER BY category, name";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
        
    } catch (PDOException $e) {
        error_log("Error getting menu items: " . $e->getMessage());
        return [];
    }
}

/**
 * Get table status for restaurant
 */
function getTableStatus() {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT t.*, 
                   COALESCE(o.guest_count, 0) as guest_count,
                   COALESCE(o.status, 'available') as order_status
            FROM pos_tables t
            LEFT JOIN pos_orders o ON t.id = o.table_id AND o.status IN ('active', 'pending')
            ORDER BY t.table_number
        ");
        $stmt->execute();
        return $stmt->fetchAll();
        
    } catch (PDOException $e) {
        error_log("Error getting table status: " . $e->getMessage());
        return [];
    }
}

/**
 * Create new order
 */
function createOrder($data) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO pos_orders (
                table_id, guest_count, items, total_amount, 
                status, special_requests, created_by
            ) VALUES (
                :table_id, :guest_count, :items, :total_amount,
                :status, :special_requests, :created_by
            )
        ");
        
        $stmt->execute([
            'table_id' => $data['table_id'],
            'guest_count' => $data['guest_count'],
            'items' => json_encode($data['items']),
            'total_amount' => $data['total_amount'],
            'status' => $data['status'] ?? 'pending',
            'special_requests' => $data['special_requests'] ?? '',
            'created_by' => $data['created_by']
        ]);
        
        return $pdo->lastInsertId();
        
    } catch (PDOException $e) {
        error_log("Error creating order: " . $e->getMessage());
        return false;
    }
}

/**
 * Get active orders
 */
function getActiveOrders($service_type = null) {
    global $pdo;
    
    try {
        $sql = "SELECT * FROM pos_transactions WHERE status IN ('pending', 'preparing', 'ready')";
        $params = [];
        
        if ($service_type) {
            $sql .= " AND service_type = :service_type";
            $params['service_type'] = $service_type;
        }
        
        $sql .= " ORDER BY created_at ASC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
        
    } catch (PDOException $e) {
        error_log("Error getting active orders: " . $e->getMessage());
        return [];
    }
}

/**
 * Process payment
 */
function processPayment($transaction_id, $payment_data) {
    global $pdo;
    
    try {
        // Update transaction status
        $stmt = $pdo->prepare("
            UPDATE pos_transactions 
            SET status = 'completed', 
                payment_method = :payment_method,
                payment_reference = :payment_reference,
                completed_at = NOW()
            WHERE id = :id
        ");
        
        $stmt->execute([
            'payment_method' => $payment_data['payment_method'],
            'payment_reference' => $payment_data['payment_reference'] ?? null,
            'id' => $transaction_id
        ]);
        
        // Create payment record
        $stmt = $pdo->prepare("
            INSERT INTO pos_payments (
                transaction_id, amount, payment_method, 
                payment_reference, processed_by
            ) VALUES (
                :transaction_id, :amount, :payment_method,
                :payment_reference, :processed_by
            )
        ");
        
        return $stmt->execute([
            'transaction_id' => $transaction_id,
            'amount' => $payment_data['amount'],
            'payment_method' => $payment_data['payment_method'],
            'payment_reference' => $payment_data['payment_reference'] ?? null,
            'processed_by' => $payment_data['processed_by']
        ]);
        
    } catch (PDOException $e) {
        error_log("Error processing payment: " . $e->getMessage());
        return false;
    }
}

/**
 * Get sales report
 */
function getSalesReport($start_date, $end_date, $service_type = null) {
    global $pdo;
    
    try {
        $sql = "
            SELECT 
                DATE(created_at) as date,
                service_type,
                COUNT(*) as transaction_count,
                SUM(total_amount) as total_sales,
                AVG(total_amount) as average_sale
            FROM pos_transactions 
            WHERE created_at BETWEEN :start_date AND :end_date
            AND status = 'completed'
        ";
        
        $params = [
            'start_date' => $start_date . ' 00:00:00',
            'end_date' => $end_date . ' 23:59:59'
        ];
        
        if ($service_type) {
            $sql .= " AND service_type = :service_type";
            $params['service_type'] = $service_type;
        }
        
        $sql .= " GROUP BY DATE(created_at), service_type ORDER BY date DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
        
    } catch (PDOException $e) {
        error_log("Error getting sales report: " . $e->getMessage());
        return [];
    }
}

/**
 * Search guests for POS transactions
 */
function searchGuests($search_term) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT id, first_name, last_name, room_number, check_in_date, check_out_date
            FROM guests 
            WHERE (first_name LIKE :search OR last_name LIKE :search OR room_number LIKE :search)
            AND check_out_date >= CURDATE()
            ORDER BY check_in_date DESC
            LIMIT 20
        ");
        
        $stmt->execute(['search' => '%' . $search_term . '%']);
        return $stmt->fetchAll();
        
    } catch (PDOException $e) {
        error_log("Error searching guests: " . $e->getMessage());
        return [];
    }
}

/**
 * Calculate tax and totals
 */
function calculateTotals($items, $discount_percent = 0) {
    $subtotal = 0;
    
    foreach ($items as $item) {
        $subtotal += $item['price'] * $item['quantity'];
    }
    
    $discount_amount = $subtotal * ($discount_percent / 100);
    $tax_amount = ($subtotal - $discount_amount) * 0.12; // 12% VAT
    $total_amount = $subtotal - $discount_amount + $tax_amount;
    
    return [
        'subtotal' => $subtotal,
        'discount_amount' => $discount_amount,
        'tax_amount' => $tax_amount,
        'total_amount' => $total_amount
    ];
}

/**
 * Get spa services
 */
function getSpaServices() {
    global $pdo;
    
    try {
        $sql = "SELECT * FROM pos_menu_items WHERE category IN ('spa', 'wellness', 'massage', 'facial', 'body-treatment') AND active = 1 ORDER BY name";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
        
    } catch (PDOException $e) {
        error_log("Error getting spa services: " . $e->getMessage());
        return [];
    }
}

/**
 * Get active appointments for spa
 */
function getActiveAppointments($service_type) {
    global $pdo;
    
    try {
        $sql = "SELECT * FROM pos_orders WHERE service_type = :service_type AND status IN ('confirmed', 'in-progress') ORDER BY created_at DESC LIMIT 10";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['service_type' => $service_type]);
        return $stmt->fetchAll();
        
    } catch (PDOException $e) {
        error_log("Error getting active appointments: " . $e->getMessage());
        return [];
    }
}

/**
 * Get spa statistics
 */
function getSpaStats() {
    global $pdo;
    
    try {
        $today = date('Y-m-d');
        
        // Today's revenue
        $sql = "SELECT COALESCE(SUM(total_amount), 0) as today_revenue FROM pos_transactions WHERE service_type = 'spa' AND DATE(created_at) = :today";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['today' => $today]);
        $today_revenue = $stmt->fetchColumn();
        
        // Today's appointments
        $sql = "SELECT COUNT(*) as today_appointments FROM pos_orders WHERE service_type = 'spa' AND DATE(created_at) = :today";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['today' => $today]);
        $today_appointments = $stmt->fetchColumn();
        
        // Active appointments
        $sql = "SELECT COUNT(*) as active_appointments FROM pos_orders WHERE service_type = 'spa' AND status IN ('confirmed', 'in-progress')";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $active_appointments = $stmt->fetchColumn();
        
        // Monthly revenue
        $sql = "SELECT COALESCE(SUM(total_amount), 0) as monthly_revenue FROM pos_transactions WHERE service_type = 'spa' AND MONTH(created_at) = MONTH(:today) AND YEAR(created_at) = YEAR(:today)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['today' => $today]);
        $monthly_revenue = $stmt->fetchColumn();
        
        return [
            'today_revenue' => $today_revenue,
            'today_appointments' => $today_revenue,
            'active_appointments' => $active_appointments,
            'monthly_revenue' => $monthly_revenue
        ];
        
    } catch (PDOException $e) {
        error_log("Error getting spa stats: " . $e->getMessage());
        return [
            'today_revenue' => 0,
            'today_appointments' => 0,
            'active_appointments' => 0,
            'monthly_revenue' => 0
        ];
    }
}

/**
 * Get gift shop items
 */
function getGiftShopItems() {
    global $pdo;
    
    try {
        $sql = "SELECT * FROM pos_menu_items WHERE category IN ('gift', 'souvenir', 'retail', 'local-product') AND active = 1 ORDER BY name";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchColumn();
        
    } catch (PDOException $e) {
        error_log("Error getting gift shop items: " . $e->getMessage());
        return [];
    }
}

/**
 * Get inventory status for gift shop
 */
function getInventoryStatus($service_type) {
    global $pdo;
    
    try {
        $sql = "SELECT * FROM pos_inventory WHERE service_type = :service_type ORDER BY stock_quantity ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['service_type' => $service_type]);
        return $stmt->fetchAll();
        
    } catch (PDOException $e) {
        error_log("Error getting inventory status: " . $e->getMessage());
        return [];
    }
}

/**
 * Get gift shop statistics
 */
function getGiftShopStats() {
    global $pdo;
    
    try {
        $today = date('Y-m-d');
        
        // Today's sales
        $sql = "SELECT COALESCE(SUM(total_amount), 0) as today_sales FROM pos_transactions WHERE service_type = 'gift-shop' AND DATE(created_at) = :today";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['today' => $today]);
        $today_sales = $stmt->fetchColumn();
        
        // Today's transactions
        $sql = "SELECT COUNT(*) as today_transactions FROM pos_transactions WHERE service_type = 'gift-shop' AND DATE(created_at) = :today";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['today' => $today]);
        $today_transactions = $stmt->fetchColumn();
        
        // Total items
        $sql = "SELECT COUNT(*) as total_items FROM pos_menu_items WHERE category IN ('gift', 'souvenir', 'retail', 'local-product') AND active = 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $total_items = $stmt->fetchColumn();
        
        // Low stock items
        $sql = "SELECT COUNT(*) as low_stock_items FROM pos_inventory WHERE stock_quantity <= 10";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $low_stock_items = $stmt->fetchColumn();
        
        return [
            'today_sales' => $today_sales,
            'today_transactions' => $today_transactions,
            'total_items' => $total_items,
            'low_stock_items' => $low_stock_items
        ];
        
    } catch (PDOException $e) {
        error_log("Error getting gift shop stats: " . $e->getMessage());
        return [
            'today_sales' => 0,
            'today_transactions' => 0,
            'total_items' => 0,
            'low_stock_items' => 0
        ];
    }
}

/**
 * Get event services
 */
function getEventServices() {
    global $pdo;
    
    try {
        $sql = "SELECT * FROM pos_menu_items WHERE category IN ('catering', 'av-services', 'decoration', 'event-planning') AND active = 1 ORDER BY name";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
        
    } catch (PDOException $e) {
        error_log("Error getting event services: " . $e->getMessage());
        return [];
    }
}

/**
 * Get active events
 */
function getActiveEvents() {
    global $pdo;
    
    try {
        $sql = "SELECT * FROM pos_orders WHERE service_type = 'events' AND status IN ('confirmed', 'in-progress', 'setup') ORDER BY created_at DESC LIMIT 10";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
        
    } catch (PDOException $e) {
        error_log("Error getting active events: " . $e->getMessage());
        return [];
    }
}

/**
 * Get event statistics
 */
function getEventStats() {
    global $pdo;
    
    try {
        $today = date('Y-m-d');
        
        // Today's revenue
        $sql = "SELECT COALESCE(SUM(total_amount), 0) as today_revenue FROM pos_transactions WHERE service_type = 'events' AND DATE(created_at) = :today";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['today' => $today]);
        $today_revenue = $stmt->fetchColumn();
        
        // Today's guests
        $sql = "SELECT COALESCE(SUM(guest_count), 0) as today_guests FROM pos_orders WHERE service_type = 'events' AND DATE(created_at) = :today";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['today' => $today]);
        $today_guests = $stmt->fetchColumn();
        
        // Active events
        $sql = "SELECT COUNT(*) as active_events FROM pos_orders WHERE service_type = 'events' AND status IN ('confirmed', 'in-progress', 'setup')";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $active_events = $stmt->fetchColumn();
        
        // Monthly revenue
        $sql = "SELECT COALESCE(SUM(total_amount), 0) as monthly_revenue FROM pos_transactions WHERE service_type = 'events' AND MONTH(created_at) = MONTH(:today) AND YEAR(created_at) = YEAR(:today)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['today' => $today]);
        $monthly_revenue = $stmt->fetchColumn();
        
        return [
            'today_revenue' => $today_revenue,
            'today_guests' => $today_guests,
            'active_events' => $active_events,
            'monthly_revenue' => $monthly_revenue
        ];
        
    } catch (PDOException $e) {
        error_log("Error getting event stats: " . $e->getMessage());
        return [
            'today_revenue' => 0,
            'today_guests' => 0,
            'active_events' => 0,
            'monthly_revenue' => 0
        ];
    }
}

/**
 * Get quick sale items
 */
function getQuickSaleItems() {
    global $pdo;
    
    try {
        $sql = "SELECT * FROM pos_menu_items WHERE active = 1 ORDER BY name LIMIT 20";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
        
    } catch (PDOException $e) {
        error_log("Error getting quick sale items: " . $e->getMessage());
        return [];
    }
}

/**
 * Get quick sales statistics
 */
function getQuickSalesStats() {
    global $pdo;
    
    try {
        $today = date('Y-m-d');
        
        // Today's sales
        $sql = "SELECT COALESCE(SUM(total_amount), 0) as today_sales FROM pos_transactions WHERE DATE(created_at) = :today";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['today' => $today]);
        $today_sales = $stmt->fetchColumn();
        
        // Today's transactions
        $sql = "SELECT COUNT(*) as today_transactions FROM pos_transactions WHERE DATE(created_at) = :today";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['today' => $today]);
        $today_transactions = $stmt->fetchColumn();
        
        // Average transaction time (placeholder)
        $avg_transaction_time = 45;
        
        // Monthly revenue
        $sql = "SELECT COALESCE(SUM(total_amount), 0) as monthly_revenue FROM pos_transactions WHERE MONTH(created_at) = MONTH(:today) AND YEAR(created_at) = YEAR(:today)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['today' => $today]);
        $monthly_revenue = $stmt->fetchColumn();
        
        return [
            'today_sales' => $today_sales,
            'today_transactions' => $today_transactions,
            'avg_transaction_time' => $avg_transaction_time,
            'monthly_revenue' => $monthly_revenue
        ];
        
    } catch (PDOException $e) {
        error_log("Error getting quick sales stats: " . $e->getMessage());
        return [
            'today_sales' => 0,
            'today_transactions' => 0,
            'avg_transaction_time' => 0,
            'monthly_revenue' => 0
        ];
    }
}
?>
