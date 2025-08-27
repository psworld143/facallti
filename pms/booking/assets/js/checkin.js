// Check-in JavaScript

document.addEventListener('DOMContentLoaded', function() {
    // Load pending check-ins
    loadPendingCheckins();
    
    // Initialize form handlers
    initializeCheckinForm();
});

// Load pending check-ins
function loadPendingCheckins() {
    const container = document.getElementById('pending-checkins');
    if (!container) return;
    
    // Show loading
    container.innerHTML = '<div class="flex items-center justify-center py-8"><div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div></div>';
    
    // Fetch pending check-ins
    fetch('../../api/get-pending-checkins.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayPendingCheckins(data.reservations);
            } else {
                container.innerHTML = '<div class="text-center py-8 text-gray-500">No pending check-ins</div>';
            }
        })
        .catch(error => {
            console.error('Error loading pending check-ins:', error);
            container.innerHTML = '<div class="text-center py-8 text-red-500">Error loading check-ins</div>';
        });
}

// Display pending check-ins
function displayPendingCheckins(reservations) {
    const container = document.getElementById('pending-checkins');
    
    if (reservations.length === 0) {
        container.innerHTML = '<div class="text-center py-8 text-gray-500">No pending check-ins for today</div>';
        return;
    }
    
    const tableHtml = `
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Guest</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reservation</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Room</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Check-in Date</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                ${reservations.map(reservation => `
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 h-10 w-10">
                                    <div class="h-10 w-10 rounded-full bg-gray-300 flex items-center justify-center">
                                        <span class="text-sm font-medium text-gray-700">
                                            ${reservation.first_name.charAt(0)}${reservation.last_name.charAt(0)}
                                        </span>
                                    </div>
                                </div>
                                <div class="ml-4">
                                    <div class="text-sm font-medium text-gray-900">
                                        ${reservation.first_name} ${reservation.last_name}
                                        ${reservation.is_vip ? '<span class="ml-2 px-2 py-1 text-xs bg-yellow-100 text-yellow-800 rounded-full">VIP</span>' : ''}
                                    </div>
                                    <div class="text-sm text-gray-500">${reservation.adults} adults, ${reservation.children} children</div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-900">${reservation.reservation_number}</div>
                            <div class="text-sm text-gray-500">${formatDate(reservation.created_at)}</div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-900">${reservation.room_number}</div>
                            <div class="text-sm text-gray-500">${reservation.room_type}</div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            ${formatDate(reservation.check_in_date)}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 py-1 text-xs font-medium rounded-full bg-yellow-100 text-yellow-800">
                                Pending Check-in
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            <button onclick="startCheckin(${reservation.id})" 
                                    class="text-green-600 hover:text-green-900">
                                <i class="fas fa-sign-in-alt mr-1"></i>Check In
                            </button>
                        </td>
                    </tr>
                `).join('')}
            </tbody>
        </table>
    `;
    
    container.innerHTML = tableHtml;
}

// Search reservations
function searchReservations() {
    const reservationNumber = document.getElementById('search_reservation').value;
    const guestName = document.getElementById('search_guest').value;
    const checkInDate = document.getElementById('search_date').value;
    
    // Show loading
    const container = document.getElementById('pending-checkins');
    container.innerHTML = '<div class="flex items-center justify-center py-8"><div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div></div>';
    
    // Build query parameters
    const params = new URLSearchParams();
    if (reservationNumber) params.append('reservation_number', reservationNumber);
    if (guestName) params.append('guest_name', guestName);
    if (checkInDate) params.append('check_in_date', checkInDate);
    
    // Fetch search results
    fetch(`../../api/search-reservations.php?${params.toString()}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayPendingCheckins(data.reservations);
            } else {
                container.innerHTML = '<div class="text-center py-8 text-gray-500">No reservations found</div>';
            }
        })
        .catch(error => {
            console.error('Error searching reservations:', error);
            container.innerHTML = '<div class="text-center py-8 text-red-500">Error searching reservations</div>';
        });
}

// Start check-in process
function startCheckin(reservationId) {
    // Fetch reservation details
    fetch(`../../api/get-reservation-details.php?id=${reservationId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                populateCheckinForm(data.reservation);
                showCheckinForm();
            } else {
                HotelPMS.Utils.showNotification(data.message || 'Error loading reservation details', 'error');
            }
        })
        .catch(error => {
            console.error('Error loading reservation details:', error);
            HotelPMS.Utils.showNotification('Error loading reservation details', 'error');
        });
}

// Populate check-in form
function populateCheckinForm(reservation) {
    document.getElementById('reservation_id').value = reservation.id;
    document.getElementById('guest_name').value = `${reservation.first_name} ${reservation.last_name}`;
    document.getElementById('reservation_number').value = reservation.reservation_number;
    document.getElementById('room_number').value = reservation.room_number;
    document.getElementById('checkin_date').value = formatDate(reservation.check_in_date);
}

// Show check-in form
function showCheckinForm() {
    document.getElementById('checkin-form-container').classList.remove('hidden');
    document.getElementById('checkin-form-container').scrollIntoView({ behavior: 'smooth' });
}

// Hide check-in form
function hideCheckinForm() {
    document.getElementById('checkin-form-container').classList.add('hidden');
    document.getElementById('checkin-form').reset();
}

// Initialize check-in form
function initializeCheckinForm() {
    const form = document.getElementById('checkin-form');
    if (form) {
        form.addEventListener('submit', handleCheckinSubmit);
    }
}

// Handle check-in form submission
function handleCheckinSubmit(e) {
    e.preventDefault();
    
    if (!validateCheckinForm()) {
        HotelPMS.Utils.showNotification('Please fill in all required fields', 'warning');
        return;
    }
    
    const formData = new FormData(e.target);
    const data = Object.fromEntries(formData.entries());
    
    // Show loading state
    const submitBtn = e.target.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Processing...';
    
    // Submit check-in
    fetch('../../api/check-in-guest.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            HotelPMS.Utils.showNotification('Guest checked in successfully!', 'success');
            hideCheckinForm();
            loadPendingCheckins();
        } else {
            HotelPMS.Utils.showNotification(result.message || 'Error checking in guest', 'error');
        }
    })
    .catch(error => {
        console.error('Error checking in guest:', error);
        HotelPMS.Utils.showNotification('Error checking in guest', 'error');
    })
    .finally(() => {
        // Reset button state
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    });
}

// Validate check-in form
function validateCheckinForm() {
    const requiredFields = ['room_key_issued', 'welcome_amenities'];
    
    let isValid = true;
    
    requiredFields.forEach(field => {
        const element = document.getElementById(field);
        if (!element.value) {
            HotelPMS.FormValidator.showFieldError(element, 'This field is required');
            isValid = false;
        } else {
            HotelPMS.FormValidator.clearFieldError(element);
        }
    });
    
    return isValid;
}

// Cancel check-in
function cancelCheckin() {
    hideCheckinForm();
}

// Format date
function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
}

// Export functions for use in other modules
window.Checkin = {
    loadPendingCheckins,
    searchReservations,
    startCheckin,
    handleCheckinSubmit,
    cancelCheckin
};
