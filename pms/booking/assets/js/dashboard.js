// Dashboard specific JavaScript

document.addEventListener('DOMContentLoaded', function() {
    // Update date and time
    updateDateTime();
    setInterval(updateDateTime, 1000);
    
    // Load recent activities
    loadRecentActivities();
    
    // Load notifications count
    loadNotificationsCount();
});

// Update current date and time
function updateDateTime() {
    const now = new Date();
    const dateElement = document.getElementById('current-date');
    const timeElement = document.getElementById('current-time');
    
    if (dateElement) {
        dateElement.textContent = now.toLocaleDateString('en-US', {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });
    }
    
    if (timeElement) {
        timeElement.textContent = now.toLocaleTimeString('en-US', {
            hour12: true,
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit'
        });
    }
}

// Load recent activities
function loadRecentActivities() {
    const activityList = document.getElementById('activity-list');
    if (!activityList) return;
    
    // Show loading
    activityList.innerHTML = '<div class="flex items-center justify-center py-8"><div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div></div>';
    
    // Fetch activities
    fetch('api/get-recent-activities.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayActivities(data.activities);
            } else {
                activityList.innerHTML = '<div class="text-center py-8 text-gray-500">No recent activities</div>';
            }
        })
        .catch(error => {
            console.error('Error loading activities:', error);
            activityList.innerHTML = '<div class="text-center py-8 text-red-500">Error loading activities</div>';
        });
}

// Display activities
function displayActivities(activities) {
    const activityList = document.getElementById('activity-list');
    
    if (activities.length === 0) {
        activityList.innerHTML = '<div class="text-center py-8 text-gray-500">No recent activities</div>';
        return;
    }
    
    const activitiesHtml = activities.map(activity => {
        const iconClass = getActivityIcon(activity.action);
        const timeAgo = getTimeAgo(activity.created_at);
        
        return `
            <div class="flex items-center py-4 border-b border-gray-100 last:border-b-0">
                <div class="w-10 h-10 rounded-full flex items-center justify-center mr-4 ${iconClass.bg}">
                    <i class="fas ${iconClass.icon} text-white text-sm"></i>
                </div>
                <div class="flex-1">
                    <div class="font-medium text-gray-900">${activity.user_name}</div>
                    <div class="text-sm text-gray-600">${activity.action}</div>
                    ${activity.details ? `<div class="text-xs text-gray-500">${activity.details}</div>` : ''}
                </div>
                <div class="text-xs text-gray-400">${timeAgo}</div>
            </div>
        `;
    }).join('');
    
    activityList.innerHTML = activitiesHtml;
}

// Get activity icon
function getActivityIcon(action) {
    const icons = {
        'login': { icon: 'fa-sign-in-alt', bg: 'bg-green-500' },
        'logout': { icon: 'fa-sign-out-alt', bg: 'bg-gray-500' },
        'reservation': { icon: 'fa-calendar-plus', bg: 'bg-blue-500' },
        'checkin': { icon: 'fa-user-check', bg: 'bg-green-500' },
        'checkout': { icon: 'fa-user-times', bg: 'bg-red-500' },
        'housekeeping': { icon: 'fa-broom', bg: 'bg-yellow-500' },
        'billing': { icon: 'fa-credit-card', bg: 'bg-purple-500' },
        'maintenance': { icon: 'fa-tools', bg: 'bg-orange-500' }
    };
    
    return icons[action] || { icon: 'fa-info-circle', bg: 'bg-gray-500' };
}

// Get time ago
function getTimeAgo(dateString) {
    const date = new Date(dateString);
    const now = new Date();
    const diffInSeconds = Math.floor((now - date) / 1000);
    
    if (diffInSeconds < 60) {
        return 'Just now';
    } else if (diffInSeconds < 3600) {
        const minutes = Math.floor(diffInSeconds / 60);
        return `${minutes} minute${minutes > 1 ? 's' : ''} ago`;
    } else if (diffInSeconds < 86400) {
        const hours = Math.floor(diffInSeconds / 3600);
        return `${hours} hour${hours > 1 ? 's' : ''} ago`;
    } else {
        const days = Math.floor(diffInSeconds / 86400);
        return `${days} day${days > 1 ? 's' : ''} ago`;
    }
}

// Load notifications count
function loadNotificationsCount() {
    fetch('api/get-notifications-count.php')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.count > 0) {
                showNotificationBadge(data.count);
            }
        })
        .catch(error => {
            console.error('Error loading notifications count:', error);
        });
}

// Show notification badge
function showNotificationBadge(count) {
    // Create or update notification badge in header
    let badge = document.getElementById('notification-badge');
    if (!badge) {
        badge = document.createElement('span');
        badge.id = 'notification-badge';
        badge.className = 'absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center';
        
        // Add to header user info area
        const userInfo = document.querySelector('.user-info');
        if (userInfo) {
            const notificationIcon = document.createElement('div');
            notificationIcon.className = 'relative cursor-pointer';
            notificationIcon.innerHTML = '<i class="fas fa-bell text-white"></i>';
            notificationIcon.appendChild(badge);
            userInfo.insertBefore(notificationIcon, userInfo.firstChild);
        }
    }
    
    badge.textContent = count > 99 ? '99+' : count;
}

// Real-time updates (if WebSocket is available)
function initializeRealTimeUpdates() {
    // This would be implemented with WebSocket for real-time updates
    // For now, we'll use polling
    setInterval(() => {
        loadRecentActivities();
        loadNotificationsCount();
    }, 30000); // Update every 30 seconds
}

// Initialize real-time updates
initializeRealTimeUpdates();

// Export functions for use in other modules
window.Dashboard = {
    updateDateTime,
    loadRecentActivities,
    loadNotificationsCount,
    getTimeAgo
};
