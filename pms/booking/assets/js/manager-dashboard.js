// Manager Dashboard JavaScript

document.addEventListener('DOMContentLoaded', function() {
    // Update date and time
    updateDateTime();
    setInterval(updateDateTime, 1000);
    
    // Load recent activity
    loadRecentActivity();
});

// Update date and time
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
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit'
        });
    }
}

// Load recent activity
function loadRecentActivity() {
    const container = document.getElementById('recent-activity');
    if (!container) return;
    
    // Show loading
    container.innerHTML = '<div class="flex items-center justify-center py-8"><div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div></div>';
    
    // Fetch recent activity
    fetch('../../api/get-recent-activities.php?limit=10')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayRecentActivity(data.activities);
            } else {
                container.innerHTML = '<div class="text-center py-8 text-gray-500">No recent activity</div>';
            }
        })
        .catch(error => {
            console.error('Error loading recent activity:', error);
            container.innerHTML = '<div class="text-center py-8 text-red-500">Error loading activity</div>';
        });
}

// Display recent activity
function displayRecentActivity(activities) {
    const container = document.getElementById('recent-activity');
    
    if (activities.length === 0) {
        container.innerHTML = '<div class="text-center py-8 text-gray-500">No recent activity</div>';
        return;
    }
    
    const activityHtml = activities.map(activity => `
        <div class="flex items-start space-x-3 py-3 border-b border-gray-200 last:border-b-0">
            <div class="flex-shrink-0">
                <div class="w-8 h-8 rounded-full bg-gray-300 flex items-center justify-center">
                    <i class="fas ${getActivityIcon(activity.action_type)} text-gray-600 text-sm"></i>
                </div>
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-sm text-gray-900">${activity.description}</p>
                <p class="text-xs text-gray-500">by ${activity.user_name} • ${formatDateTime(activity.created_at)}</p>
            </div>
        </div>
    `).join('');
    
    container.innerHTML = activityHtml;
}

// Get activity icon
function getActivityIcon(actionType) {
    const icons = {
        'reservation_created': 'fa-calendar-plus',
        'reservation_updated': 'fa-calendar-check',
        'reservation_cancelled': 'fa-calendar-times',
        'checkin': 'fa-sign-in-alt',
        'checkout': 'fa-sign-out-alt',
        'housekeeping_update': 'fa-broom',
        'maintenance_request': 'fa-tools',
        'service_charge_added': 'fa-dollar-sign',
        'login': 'fa-sign-in-alt',
        'logout': 'fa-sign-out-alt'
    };
    
    return icons[actionType] || 'fa-info-circle';
}

// Format date and time
function formatDateTime(dateString) {
    const date = new Date(dateString);
    return date.toLocaleString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

// Export functions for use in other modules
window.ManagerDashboard = {
    loadRecentActivity,
    updateDateTime
};
