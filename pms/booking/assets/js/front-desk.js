// Front Desk JavaScript

document.addEventListener('DOMContentLoaded', function() {
    // Update date and time
    updateDateTime();
    setInterval(updateDateTime, 1000);
    
    // Load recent reservations
    loadRecentReservations();
    
    // Load today's schedule
    loadTodaySchedule();
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

// Load recent reservations
function loadRecentReservations() {
    const container = document.getElementById('recent-reservations');
    if (!container) return;
    
    // Show loading
    container.innerHTML = '<div class="flex items-center justify-center py-8"><div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div></div>';
    
    // Fetch recent reservations
    fetch('../../api/get-recent-reservations.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayRecentReservations(data.reservations);
            } else {
                container.innerHTML = `
                    <div class="px-6 py-12 text-center">
                        <i class="fas fa-exclamation-triangle text-red-400 text-4xl mb-4"></i>
                        <h3 class="text-lg font-medium text-gray-900 mb-2">Error loading reservations</h3>
                        <p class="text-gray-500">${data.message || 'Unable to load recent reservations.'}</p>
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Error loading recent reservations:', error);
            container.innerHTML = `
                <div class="px-6 py-12 text-center">
                    <i class="fas fa-exclamation-triangle text-red-400 text-4xl mb-4"></i>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">Error loading reservations</h3>
                    <p class="text-gray-500">Unable to load recent reservations. Please try again.</p>
                </div>
            `;
        });
}

// Display recent reservations
function displayRecentReservations(reservations) {
    const container = document.getElementById('recent-reservations');
    
    if (!reservations || reservations.length === 0) {
        container.innerHTML = `
            <div class="px-6 py-12 text-center">
                <i class="fas fa-calendar-alt text-gray-400 text-4xl mb-4"></i>
                <h3 class="text-lg font-medium text-gray-900 mb-2">No recent reservations</h3>
                <p class="text-gray-500">No reservations have been made recently.</p>
            </div>
        `;
        return;
    }
    
    const tableHtml = `
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Guest</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Room</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Check-in</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                ${reservations.map(reservation => `
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm font-medium text-gray-900">${reservation.guest_name}</div>
                            <div class="text-sm text-gray-500">${reservation.reservation_number}</div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-900">${reservation.room_number}</div>
                            <div class="text-sm text-gray-500">${reservation.room_type}</div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            ${formatDate(reservation.check_in_date)}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            ${getStatusBadge(reservation.status)}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            <div class="flex space-x-2">
                                ${reservation.status === 'confirmed' ? `
                                    <button onclick="checkInGuest(${reservation.id})" class="text-green-600 hover:text-green-900">
                                        <i class="fas fa-sign-in-alt"></i> Check In
                                    </button>
                                ` : ''}
                                ${reservation.status === 'checked_in' ? `
                                    <button onclick="checkOutGuest(${reservation.id})" class="text-red-600 hover:text-red-900">
                                        <i class="fas fa-sign-out-alt"></i> Check Out
                                    </button>
                                ` : ''}
                                <button onclick="viewReservation(${reservation.id})" class="text-blue-600 hover:text-blue-900">
                                    <i class="fas fa-eye"></i> View
                                </button>
                            </div>
                        </td>
                    </tr>
                `).join('')}
            </tbody>
        </table>
    `;
    
    container.innerHTML = tableHtml;
}

// Load today's schedule
function loadTodaySchedule() {
    const container = document.getElementById('today-schedule');
    if (!container) return;
    
    // Show loading
    container.innerHTML = '<div class="flex items-center justify-center py-8"><div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div></div>';
    
    // Fetch today's schedule
    fetch('../../api/get-today-schedule.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayTodaySchedule(data.schedule);
            } else {
                container.innerHTML = `
                    <div class="px-6 py-12 text-center">
                        <i class="fas fa-exclamation-triangle text-red-400 text-4xl mb-4"></i>
                        <h3 class="text-lg font-medium text-gray-900 mb-2">Error loading schedule</h3>
                        <p class="text-gray-500">${data.message || 'Unable to load today\'s schedule.'}</p>
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Error loading today\'s schedule:', error);
            container.innerHTML = `
                <div class="px-6 py-12 text-center">
                    <i class="fas fa-exclamation-triangle text-red-400 text-4xl mb-4"></i>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">Error loading schedule</h3>
                    <p class="text-gray-500">Unable to load today\'s schedule. Please try again.</p>
                </div>
            `;
        });
}

// Display today's schedule
function displayTodaySchedule(schedule) {
    const container = document.getElementById('today-schedule');
    
    if (!schedule || schedule.length === 0) {
        container.innerHTML = `
            <div class="px-6 py-12 text-center">
                <i class="fas fa-clock text-gray-400 text-4xl mb-4"></i>
                <h3 class="text-lg font-medium text-gray-900 mb-2">No schedule for today</h3>
                <p class="text-gray-500">No activities are scheduled for today.</p>
            </div>
        `;
        return;
    }
    
    // Check if there's only the "no activities" message
    if (schedule.length === 1 && schedule[0].type === 'info') {
        container.innerHTML = `
            <div class="px-6 py-12 text-center">
                <i class="fas fa-clock text-gray-400 text-4xl mb-4"></i>
                <h3 class="text-lg font-medium text-gray-900 mb-2">No scheduled activities</h3>
                <p class="text-gray-500">No check-ins, check-outs, or new reservations for today.</p>
            </div>
        `;
        return;
    }
    
    const scheduleHtml = schedule.map(item => `
        <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
            <div class="flex items-center">
                <div class="w-10 h-10 rounded-full flex items-center justify-center mr-4 ${getScheduleIcon(item.type).bg}">
                    <i class="fas ${getScheduleIcon(item.type).icon} text-white text-sm"></i>
                </div>
                <div>
                    <div class="font-medium text-gray-900">${item.title}</div>
                    <div class="text-sm text-gray-600">${item.description}</div>
                </div>
            </div>
            <div class="text-right">
                <div class="text-sm font-medium text-gray-900">${item.time}</div>
                <div class="text-xs text-gray-500">${item.room_number}</div>
            </div>
        </div>
    `).join('');
    
    container.innerHTML = scheduleHtml;
}

// Get status badge
function getStatusBadge(status) {
    const badges = {
        'confirmed': 'bg-yellow-100 text-yellow-800',
        'checked_in': 'bg-green-100 text-green-800',
        'checked_out': 'bg-gray-100 text-gray-800',
        'cancelled': 'bg-red-100 text-red-800',
        'no_show': 'bg-red-100 text-red-800'
    };
    
    const statusText = {
        'confirmed': 'Confirmed',
        'checked_in': 'Checked In',
        'checked_out': 'Checked Out',
        'cancelled': 'Cancelled',
        'no_show': 'No Show'
    };
    
    return `<span class="px-2 py-1 text-xs font-medium rounded-full ${badges[status] || 'bg-gray-100 text-gray-800'}">${statusText[status] || status}</span>`;
}

// Get schedule icon
function getScheduleIcon(type) {
    const icons = {
        'checkin': { icon: 'fa-sign-in-alt', bg: 'bg-green-500' },
        'checkout': { icon: 'fa-sign-out-alt', bg: 'bg-red-500' },
        'reservation': { icon: 'fa-calendar-plus', bg: 'bg-blue-500' },
        'maintenance': { icon: 'fa-tools', bg: 'bg-orange-500' },
        'housekeeping': { icon: 'fa-broom', bg: 'bg-purple-500' }
    };
    
    return icons[type] || { icon: 'fa-info-circle', bg: 'bg-gray-500' };
}

// Format date
function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric'
    });
}

// Check in guest
function checkInGuest(reservationId) {
    if (confirm('Are you sure you want to check in this guest?')) {
        fetch('../../api/check-in-guest.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ reservation_id: reservationId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                HotelPMS.Utils.showNotification('Guest checked in successfully', 'success');
                loadRecentReservations();
                loadTodaySchedule();
            } else {
                HotelPMS.Utils.showNotification(data.message || 'Error checking in guest', 'error');
            }
        })
        .catch(error => {
            console.error('Error checking in guest:', error);
            HotelPMS.Utils.showNotification('Error checking in guest', 'error');
        });
    }
}

// Check out guest
function checkOutGuest(reservationId) {
    if (confirm('Are you sure you want to check out this guest?')) {
        fetch('../../api/check-out-guest.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ reservation_id: reservationId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                HotelPMS.Utils.showNotification('Guest checked out successfully', 'success');
                loadRecentReservations();
                loadTodaySchedule();
            } else {
                HotelPMS.Utils.showNotification(data.message || 'Error checking out guest', 'error');
            }
        })
        .catch(error => {
            console.error('Error checking out guest:', error);
            HotelPMS.Utils.showNotification('Error checking out guest', 'error');
        });
    }
}

// View reservation
function viewReservation(reservationId) {
    window.open(`view-reservation.php?id=${reservationId}`, '_blank');
}

// Export functions for use in other modules
window.FrontDesk = {
    updateDateTime,
    loadRecentReservations,
    loadTodaySchedule,
    checkInGuest,
    checkOutGuest,
    viewReservation
};
