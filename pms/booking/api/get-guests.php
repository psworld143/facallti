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
    // Get filter parameters
    $search = $_GET['search'] ?? '';
    $vip_filter = $_GET['vip'] ?? '';
    $status_filter = $_GET['status'] ?? '';
    $page = max(1, intval($_GET['page'] ?? 1));
    $per_page = 20;
    $offset = ($page - 1) * $per_page;
    
    $guests = getGuestsWithFilters($search, $vip_filter, $status_filter, $per_page, $offset);
    $pagination = getGuestsPagination($search, $vip_filter, $status_filter, $per_page, $page);
    
    echo json_encode([
        'success' => true,
        'guests' => $guests,
        'pagination' => $pagination
    ]);
    
} catch (Exception $e) {
    error_log("Error getting guests: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/**
 * Get guests with filters
 */
function getGuestsWithFilters($search, $vip_filter, $status_filter, $per_page, $offset) {
    global $pdo;
    
    try {
        $where_conditions = ["1=1"];
        $params = [];
        
        // Search filter
        if (!empty($search)) {
            $where_conditions[] = "(g.first_name LIKE ? OR g.last_name LIKE ? OR g.email LIKE ? OR g.phone LIKE ?)";
            $search_param = "%{$search}%";
            $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
        }
        
        // VIP filter
        if ($vip_filter !== '') {
            $where_conditions[] = "g.is_vip = ?";
            $params[] = $vip_filter;
        }
        
        $where_clause = implode(" AND ", $where_conditions);
        
        // Base query
        $query = "
            SELECT g.*, 
                   COUNT(DISTINCT r.id) as total_stays,
                   MAX(r.check_out_date) as last_stay,
                   CASE 
                       WHEN EXISTS (SELECT 1 FROM reservations r2 WHERE r2.guest_id = g.id AND r2.status = 'checked_in') THEN 'active'
                       WHEN MAX(r.check_out_date) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN 'recent'
                       WHEN COUNT(DISTINCT r.id) >= 3 THEN 'frequent'
                       ELSE 'guest'
                   END as status
            FROM guests g
            LEFT JOIN reservations r ON g.id = r.guest_id
            WHERE {$where_clause}
            GROUP BY g.id
        ";
        
        // Apply status filter
        if (!empty($status_filter)) {
            switch ($status_filter) {
                case 'active':
                    $query .= " HAVING status = 'active'";
                    break;
                case 'recent':
                    $query .= " HAVING status = 'recent'";
                    break;
                case 'frequent':
                    $query .= " HAVING status = 'frequent'";
                    break;
            }
        }
        
        $query .= " ORDER BY g.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $per_page;
        $params[] = $offset;
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
        
    } catch (PDOException $e) {
        error_log("Error getting guests with filters: " . $e->getMessage());
        return [];
    }
}

/**
 * Get pagination information
 */
function getGuestsPagination($search, $vip_filter, $status_filter, $per_page, $current_page) {
    global $pdo;
    
    try {
        $where_conditions = ["1=1"];
        $params = [];
        
        // Search filter
        if (!empty($search)) {
            $where_conditions[] = "(g.first_name LIKE ? OR g.last_name LIKE ? OR g.email LIKE ? OR g.phone LIKE ?)";
            $search_param = "%{$search}%";
            $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
        }
        
        // VIP filter
        if ($vip_filter !== '') {
            $where_conditions[] = "g.is_vip = ?";
            $params[] = $vip_filter;
        }
        
        $where_clause = implode(" AND ", $where_conditions);
        
        // Count total records
        $count_query = "
            SELECT COUNT(DISTINCT g.id) as total
            FROM guests g
            LEFT JOIN reservations r ON g.id = r.guest_id
            WHERE {$where_clause}
        ";
        
        // Apply status filter to count
        if (!empty($status_filter)) {
            $count_query .= " GROUP BY g.id";
            
            switch ($status_filter) {
                case 'active':
                    $count_query .= " HAVING EXISTS (SELECT 1 FROM reservations r2 WHERE r2.guest_id = g.id AND r2.status = 'checked_in')";
                    break;
                case 'recent':
                    $count_query .= " HAVING MAX(r.check_out_date) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
                    break;
                case 'frequent':
                    $count_query .= " HAVING COUNT(DISTINCT r.id) >= 3";
                    break;
            }
            
            // Count the filtered results
            $count_query = "SELECT COUNT(*) as total FROM ({$count_query}) as filtered";
        }
        
        $stmt = $pdo->prepare($count_query);
        $stmt->execute($params);
        $total = $stmt->fetch()['total'];
        
        $total_pages = ceil($total / $per_page);
        $from = ($current_page - 1) * $per_page + 1;
        $to = min($current_page * $per_page, $total);
        
        return [
            'current_page' => $current_page,
            'total_pages' => $total_pages,
            'total' => $total,
            'from' => $from,
            'to' => $to,
            'per_page' => $per_page
        ];
        
    } catch (PDOException $e) {
        error_log("Error getting pagination: " . $e->getMessage());
        return [
            'current_page' => 1,
            'total_pages' => 1,
            'total' => 0,
            'from' => 0,
            'to' => 0,
            'per_page' => $per_page
        ];
    }
}
?>
