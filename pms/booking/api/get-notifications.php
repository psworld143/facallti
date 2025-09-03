<?php
session_start();
require_once '../../includes/error_handler.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json');

try {
    // Get notifications for the current user
    $user_id = $_SESSION['user_id'];
    $user_role = $_SESSION['user_role'];
    
    // Get recent notifications (last 24 hours)
    $stmt = $pdo->prepare("
        SELECT 
            n.id,
            n.title,
            n.message,
            n.type,
            n.created_at,
            n.is_read
        FROM notifications n
        WHERE n.user_id = ? OR n.user_role = ? OR n.user_role = 'all'
        AND n.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ORDER BY n.created_at DESC
        LIMIT 10
    ");
    
    $stmt->execute([$user_id, $user_role]);
    $notifications = $stmt->fetchAll();
    
    // Format notifications for display
    $formatted_notifications = [];
    foreach ($notifications as $notification) {
        $formatted_notifications[] = [
            'id' => $notification['id'],
            'title' => $notification['title'],
            'message' => $notification['message'],
            'type' => $notification['type'],
            'created_at' => date('M j, Y g:i A', strtotime($notification['created_at'])),
            'is_read' => (bool)$notification['is_read']
        ];
    }
    
    // Get unread count
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as unread_count
        FROM notifications n
        WHERE (n.user_id = ? OR n.user_role = ? OR n.user_role = 'all')
        AND n.is_read = 0
        AND n.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ");
    
    $stmt->execute([$user_id, $user_role]);
    $unread_count = $stmt->fetch()['unread_count'];
    
    echo json_encode([
        'success' => true,
        'notifications' => $formatted_notifications,
        'unread_count' => $unread_count
    ]);
    
} catch (Exception $e) {
    // Log error but don't expose it to user
    error_log("Error fetching notifications: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'notifications' => [],
        'unread_count' => 0,
        'error' => 'Failed to load notifications'
    ]);
}
?>
