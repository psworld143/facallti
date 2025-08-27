// Housekeeping JavaScript

document.addEventListener('DOMContentLoaded', function() {
    // Update date and time
    updateDateTime();
    setInterval(updateDateTime, 1000);
    
    // Load room status overview
    loadRoomStatusOverview();
    
    // Load recent tasks
    loadRecentTasks();
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

// Load room status overview
function loadRoomStatusOverview() {
    const container = document.getElementById('room-status-overview');
    if (!container) return;
    
    // Show loading
    container.innerHTML = '<div class="flex items-center justify-center py-8"><div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div></div>';
    
    // Fetch room status overview
    fetch('../../api/get-room-status-overview.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayRoomStatusOverview(data.statuses);
            } else {
                container.innerHTML = '<div class="text-center py-8 text-gray-500">No room status data available</div>';
            }
        })
        .catch(error => {
            console.error('Error loading room status overview:', error);
            container.innerHTML = '<div class="text-center py-8 text-red-500">Error loading room status</div>';
        });
}

// Display room status overview
function displayRoomStatusOverview(statuses) {
    const container = document.getElementById('room-status-overview');
    
    if (statuses.length === 0) {
        container.innerHTML = '<div class="text-center py-8 text-gray-500">No room status data available</div>';
        return;
    }
    
    const statusCards = statuses.map(status => {
        const statusConfig = getStatusConfig(status.housekeeping_status);
        return `
            <div class="bg-white rounded-lg p-6 shadow-md border-l-4 ${statusConfig.borderColor}">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center">
                        <div class="w-10 h-10 ${statusConfig.bgColor} rounded-full flex items-center justify-center mr-3">
                            <i class="${statusConfig.icon} text-white"></i>
                        </div>
                        <div>
                            <h4 class="text-lg font-semibold text-gray-800">${statusConfig.label}</h4>
                            <p class="text-sm text-gray-600">${status.count} rooms</p>
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="text-2xl font-bold ${statusConfig.textColor}">${status.count}</div>
                    </div>
                </div>
                <div class="mt-4">
                    <div class="w-full bg-gray-200 rounded-full h-2">
                        <div class="${statusConfig.progressColor} h-2 rounded-full" style="width: ${calculatePercentage(status.count, statuses)}%"></div>
                    </div>
                </div>
            </div>
        `;
    }).join('');
    
    container.innerHTML = statusCards;
}

// Get status configuration
function getStatusConfig(status) {
    const configs = {
        'clean': {
            label: 'Clean Rooms',
            icon: 'fas fa-check-circle',
            bgColor: 'bg-green-500',
            borderColor: 'border-green-500',
            textColor: 'text-green-600',
            progressColor: 'bg-green-500'
        },
        'dirty': {
            label: 'Dirty Rooms',
            icon: 'fas fa-times-circle',
            bgColor: 'bg-red-500',
            borderColor: 'border-red-500',
            textColor: 'text-red-600',
            progressColor: 'bg-red-500'
        },
        'maintenance': {
            label: 'Maintenance',
            icon: 'fas fa-tools',
            bgColor: 'bg-yellow-500',
            borderColor: 'border-yellow-500',
            textColor: 'text-yellow-600',
            progressColor: 'bg-yellow-500'
        }
    };
    
    return configs[status] || configs['clean'];
}

// Calculate percentage
function calculatePercentage(count, allStatuses) {
    const total = allStatuses.reduce((sum, status) => sum + status.count, 0);
    return total > 0 ? Math.round((count / total) * 100) : 0;
}

// Load recent tasks
function loadRecentTasks() {
    const container = document.getElementById('recent-tasks');
    if (!container) return;
    
    // Show loading
    container.innerHTML = '<div class="flex items-center justify-center py-8"><div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div></div>';
    
    // Fetch recent tasks
    fetch('../../api/get-recent-housekeeping-tasks.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayRecentTasks(data.tasks);
            } else {
                container.innerHTML = '<div class="text-center py-8 text-gray-500">No recent tasks</div>';
            }
        })
        .catch(error => {
            console.error('Error loading recent tasks:', error);
            container.innerHTML = '<div class="text-center py-8 text-red-500">Error loading tasks</div>';
        });
}

// Display recent tasks
function displayRecentTasks(tasks) {
    const container = document.getElementById('recent-tasks');
    
    if (tasks.length === 0) {
        container.innerHTML = '<div class="text-center py-8 text-gray-500">No recent tasks</div>';
        return;
    }
    
    const tableHtml = `
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Room</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Task Type</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Assigned To</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                ${tasks.map(task => `
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm font-medium text-gray-900">Room ${task.room_number}</div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-900">${formatTaskType(task.task_type)}</div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 py-1 text-xs font-medium rounded-full ${getTaskStatusClass(task.status)}">
                                ${task.status.charAt(0).toUpperCase() + task.status.slice(1)}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            ${task.assigned_to_name || 'Unassigned'}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            ${formatDateTime(task.created_at)}
                        </td>
                    </tr>
                `).join('')}
            </tbody>
        </table>
    `;
    
    container.innerHTML = tableHtml;
}

// Format task type
function formatTaskType(taskType) {
    const types = {
        'cleaning_completed': 'Cleaning Completed',
        'cleaning_required': 'Cleaning Required',
        'maintenance_request': 'Maintenance Request',
        'inspection': 'Room Inspection',
        'deep_cleaning': 'Deep Cleaning'
    };
    
    return types[taskType] || taskType.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase());
}

// Get task status class
function getTaskStatusClass(status) {
    const classes = {
        'pending': 'bg-yellow-100 text-yellow-800',
        'in_progress': 'bg-blue-100 text-blue-800',
        'completed': 'bg-green-100 text-green-800',
        'cancelled': 'bg-red-100 text-red-800'
    };
    
    return classes[status] || 'bg-gray-100 text-gray-800';
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

// Update room status
function updateRoomStatus(roomId, status, notes = '') {
    const data = {
        room_id: roomId,
        status: status,
        notes: notes
    };
    
    fetch('../../api/update-room-status.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            HotelPMS.Utils.showNotification('Room status updated successfully!', 'success');
            // Reload data
            loadRoomStatusOverview();
            loadRecentTasks();
        } else {
            HotelPMS.Utils.showNotification(result.message || 'Error updating room status', 'error');
        }
    })
    .catch(error => {
        console.error('Error updating room status:', error);
        HotelPMS.Utils.showNotification('Error updating room status', 'error');
    });
}

// Create maintenance request
function createMaintenanceRequest(roomId, issueType, description, priority = 'medium') {
    const data = {
        room_id: roomId,
        issue_type: issueType,
        description: description,
        priority: priority
    };
    
    fetch('../../api/create-maintenance-request.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            HotelPMS.Utils.showNotification('Maintenance request created successfully!', 'success');
            // Reload data
            loadRoomStatusOverview();
            loadRecentTasks();
        } else {
            HotelPMS.Utils.showNotification(result.message || 'Error creating maintenance request', 'error');
        }
    })
    .catch(error => {
        console.error('Error creating maintenance request:', error);
        HotelPMS.Utils.showNotification('Error creating maintenance request', 'error');
    });
}

// Export functions for use in other modules
window.Housekeeping = {
    updateRoomStatus,
    createMaintenanceRequest,
    loadRoomStatusOverview,
    loadRecentTasks
};
