<?php
// Notification Component for Leave Management System
// This component can be included in navigation bars to show notifications

// Get unread notifications for the current user
function getUnreadNotifications($user_id, $user_type, $conn) {
    $query = "SELECT COUNT(*) as count FROM leave_notifications 
              WHERE recipient_id = ? AND recipient_type = ? AND is_read = 0";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, 'is', $user_id, $user_type);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    return $row['count'];
}

// Get recent notifications for the current user
function getRecentNotifications($user_id, $user_type, $conn, $limit = 5) {
    $query = "SELECT * FROM leave_notifications 
              WHERE recipient_id = ? AND recipient_type = ? 
              ORDER BY created_at DESC LIMIT ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, 'isi', $user_id, $user_type, $limit);
    mysqli_stmt_execute($stmt);
    return mysqli_stmt_get_result($stmt);
}

// Mark notification as read
function markNotificationAsRead($notification_id, $conn) {
    $query = "UPDATE leave_notifications SET is_read = 1, read_at = NOW() WHERE id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, 'i', $notification_id);
    return mysqli_stmt_execute($stmt);
}

// Get current user type based on session
$current_user_type = '';
$current_user_id = 0;

if (isset($_SESSION['user_id'])) {
    $current_user_id = $_SESSION['user_id'];
    
    if (isset($_SESSION['role'])) {
        switch ($_SESSION['role']) {
            case 'faculty':
                $current_user_type = 'employee';
                break;
            case 'head':
                $current_user_type = 'department_head';
                break;
            case 'hr':
                $current_user_type = 'hr';
                break;
        }
    }
}

// Get notification count
$notification_count = 0;
if ($current_user_type && $current_user_id) {
    $notification_count = getUnreadNotifications($current_user_id, $current_user_type, $conn);
}
?>

<!-- Notification Bell Icon -->
<div class="relative inline-block">
    <button id="notificationBell" class="relative p-2 text-gray-600 hover:text-gray-900 transition-colors duration-200">
        <i class="fas fa-bell text-xl"></i>
        <?php if ($notification_count > 0): ?>
            <span class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center animate-pulse">
                <?php echo $notification_count > 99 ? '99+' : $notification_count; ?>
            </span>
        <?php endif; ?>
    </button>

    <!-- Notification Dropdown -->
    <div id="notificationDropdown" class="hidden absolute right-0 mt-2 w-80 bg-white rounded-lg shadow-lg border border-gray-200 z-50">
        <div class="p-4 border-b border-gray-200">
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-900">Notifications</h3>
                <?php if ($notification_count > 0): ?>
                    <button onclick="markAllAsRead()" class="text-sm text-seait-orange hover:text-orange-600">
                        Mark all as read
                    </button>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="max-h-96 overflow-y-auto">
            <div id="notificationList">
                <?php if ($current_user_type && $current_user_id): ?>
                    <?php 
                    $notifications = getRecentNotifications($current_user_id, $current_user_type, $conn, 10);
                    if (mysqli_num_rows($notifications) > 0):
                    ?>
                        <?php while ($notification = mysqli_fetch_assoc($notifications)): ?>
                            <div class="p-4 border-b border-gray-100 hover:bg-gray-50 transition-colors duration-200 <?php echo $notification['is_read'] ? 'opacity-75' : 'bg-blue-50'; ?>">
                                <div class="flex items-start justify-between">
                                    <div class="flex-1">
                                        <p class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($notification['title']); ?></p>
                                        <p class="text-sm text-gray-600 mt-1"><?php echo htmlspecialchars($notification['message']); ?></p>
                                        <p class="text-xs text-gray-500 mt-2">
                                            <?php echo date('M d, Y g:i A', strtotime($notification['created_at'])); ?>
                                        </p>
                                    </div>
                                    <?php if (!$notification['is_read']): ?>
                                        <div class="ml-2">
                                            <div class="w-2 h-2 bg-blue-500 rounded-full"></div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <?php if ($notification['related_leave_request_id']): ?>
                                    <div class="mt-2">
                                        <button onclick="viewLeaveRequest(<?php echo $notification['related_leave_request_id']; ?>)" class="text-xs text-seait-orange hover:text-orange-600">
                                            View Details
                                        </button>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="p-4 text-center text-gray-500">
                            <i class="fas fa-bell-slash text-2xl mb-2"></i>
                            <p>No notifications</p>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="p-4 text-center text-gray-500">
                        <p>Please log in to view notifications</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="p-4 border-t border-gray-200">
            <a href="notifications.php" class="text-sm text-seait-orange hover:text-orange-600">
                View all notifications
            </a>
        </div>
    </div>
</div>

<script>
// Toggle notification dropdown
document.getElementById('notificationBell').addEventListener('click', function(e) {
    e.stopPropagation();
    const dropdown = document.getElementById('notificationDropdown');
    dropdown.classList.toggle('hidden');
});

// Close dropdown when clicking outside
document.addEventListener('click', function(e) {
    const dropdown = document.getElementById('notificationDropdown');
    const bell = document.getElementById('notificationBell');
    
    if (!dropdown.contains(e.target) && !bell.contains(e.target)) {
        dropdown.classList.add('hidden');
    }
});

// Mark all notifications as read
function markAllAsRead() {
    fetch('mark-notifications-read.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'mark_all_read'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Remove unread indicators
            document.querySelectorAll('.bg-blue-50').forEach(el => {
                el.classList.remove('bg-blue-50');
                el.classList.add('opacity-75');
            });
            document.querySelectorAll('.bg-blue-500').forEach(el => {
                el.remove();
            });
            
            // Hide notification count
            const countBadge = document.querySelector('.bg-red-500');
            if (countBadge) {
                countBadge.remove();
            }
            
            // Update notification list
            loadNotifications();
        }
    })
    .catch(error => {
        console.error('Error:', error);
    });
}

// View leave request details
function viewLeaveRequest(leaveId) {
    // Close dropdown
    document.getElementById('notificationDropdown').classList.add('hidden');
    
    // Redirect to appropriate page based on user role
    const userRole = '<?php echo $_SESSION['role'] ?? ''; ?>';
    
    switch (userRole) {
        case 'faculty':
            window.location.href = `faculty/leave-requests.php?view=${leaveId}`;
            break;
        case 'head':
            window.location.href = `heads/leave-requests.php?view=${leaveId}`;
            break;
        case 'hr':
            window.location.href = `human-resource/leave-management.php?view=${leaveId}`;
            break;
        default:
            console.log('Unknown user role');
    }
}

// Load notifications (for real-time updates)
function loadNotifications() {
    fetch('get-notifications.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update notification count
                const countBadge = document.querySelector('.bg-red-500');
                if (data.count > 0) {
                    if (countBadge) {
                        countBadge.textContent = data.count > 99 ? '99+' : data.count;
                    } else {
                        const badge = document.createElement('span');
                        badge.className = 'absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center animate-pulse';
                        badge.textContent = data.count > 99 ? '99+' : data.count;
                        document.getElementById('notificationBell').appendChild(badge);
                    }
                } else if (countBadge) {
                    countBadge.remove();
                }
            }
        })
        .catch(error => {
            console.error('Error loading notifications:', error);
        });
}

// Auto-refresh notifications every 30 seconds
setInterval(loadNotifications, 30000);
</script>
