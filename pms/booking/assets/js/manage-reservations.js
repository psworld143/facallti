// Manage Reservations JavaScript

// Initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeManageReservations);
} else {
    initializeManageReservations();
}

function initializeManageReservations() {
    // Load reservations on page load
    loadReservations();
    
    // Initialize form handlers
    initializeEditForm();
    
    // Debug: Check if sidebar elements exist
    const mobileToggle = document.getElementById('mobile-sidebar-toggle');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebar-overlay');
    
    console.log('Sidebar Debug:', {
        mobileToggle: mobileToggle ? 'Found' : 'Not found',
        sidebar: sidebar ? 'Found' : 'Not found',
        overlay: overlay ? 'Found' : 'Not found'
    });
    
    // Test sidebar toggle functionality
    if (mobileToggle) {
        console.log('Mobile toggle button found, testing click...');
        mobileToggle.addEventListener('click', function() {
            console.log('Mobile toggle clicked!');
        });
    }
}

// Load reservations
function loadReservations() {
    const container = document.getElementById('reservations-list');
    if (!container) return;
    
    // Show loading
    container.innerHTML = '<div class="flex items-center justify-center py-8"><div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div></div>';
    
    // Fetch reservations
    fetch('../../api/get-all-reservations.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayReservations(data.reservations);
            } else {
                container.innerHTML = `
                    <div class="px-6 py-12 text-center">
                        <i class="fas fa-exclamation-triangle text-red-400 text-4xl mb-4"></i>
                        <h3 class="text-lg font-medium text-gray-900 mb-2">Error loading reservations</h3>
                        <p class="text-gray-500">${data.message || 'Unable to load reservations.'}</p>
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Error loading reservations:', error);
            container.innerHTML = `
                <div class="px-6 py-12 text-center">
                    <i class="fas fa-exclamation-triangle text-red-400 text-4xl mb-4"></i>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">Error loading reservations</h3>
                    <p class="text-gray-500">Unable to load reservations. Please try again.</p>
                </div>
            `;
        });
}

// Search reservations
function searchReservations() {
    const reservationNumber = document.getElementById('search_reservation').value;
    const guestName = document.getElementById('search_guest').value;
    const status = document.getElementById('search_status').value;
    
    // Show loading
    const container = document.getElementById('reservations-list');
    container.innerHTML = '<div class="flex items-center justify-center py-8"><div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div></div>';
    
    // Build query parameters
    const params = new URLSearchParams();
    if (reservationNumber) params.append('reservation_number', reservationNumber);
    if (guestName) params.append('guest_name', guestName);
    if (status) params.append('status', status);
    
    // Fetch search results
    fetch(`../../api/get-all-reservations.php?${params.toString()}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayReservations(data.reservations);
            } else {
                container.innerHTML = `
                    <div class="px-6 py-12 text-center">
                        <i class="fas fa-search text-gray-400 text-4xl mb-4"></i>
                        <h3 class="text-lg font-medium text-gray-900 mb-2">No search results found</h3>
                        <p class="text-gray-500">Try adjusting your search criteria or filters.</p>
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Error searching reservations:', error);
            container.innerHTML = `
                <div class="px-6 py-12 text-center">
                    <i class="fas fa-exclamation-triangle text-red-400 text-4xl mb-4"></i>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">Error searching reservations</h3>
                    <p class="text-gray-500">Unable to search reservations. Please try again.</p>
                </div>
            `;
        });
}

// Display reservations
function displayReservations(reservations) {
    const container = document.getElementById('reservations-list');
    
    if (!reservations || reservations.length === 0) {
        container.innerHTML = `
            <div class="px-6 py-12 text-center">
                <i class="fas fa-calendar-alt text-gray-400 text-4xl mb-4"></i>
                <h3 class="text-lg font-medium text-gray-900 mb-2">No reservations found</h3>
                <p class="text-gray-500">Try adjusting your search or filters to find reservations.</p>
            </div>
        `;
        return;
    }
    
    const tableHtml = `
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Guest</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reservation</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Room</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Dates</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
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
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-900">${formatDate(reservation.check_in_date)}</div>
                            <div class="text-sm text-gray-500">to ${formatDate(reservation.check_out_date)}</div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 py-1 text-xs font-medium rounded-full ${reservation.status_class}">
                                ${reservation.status_label}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            $${parseFloat(reservation.total_amount).toFixed(2)}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            <div class="flex space-x-2">
                                <button onclick="editReservation(${reservation.id})" 
                                        class="text-blue-600 hover:text-blue-900">
                                    <i class="fas fa-edit"></i>
                                </button>
                                ${reservation.status === 'confirmed' || reservation.status === 'checked_in' ? 
                                    `<button onclick="showCancelModal(${reservation.id}, '${reservation.reservation_number}', '${reservation.first_name} ${reservation.last_name}')" 
                                             class="text-red-600 hover:text-red-900">
                                        <i class="fas fa-times"></i>
                                    </button>` : ''
                                }
                                <button onclick="viewReservationDetails(${reservation.id})" 
                                        class="text-green-600 hover:text-green-900">
                                    <i class="fas fa-eye"></i>
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

// Edit reservation
function editReservation(reservationId) {
    // Redirect to the advanced modify reservation page
    window.location.href = `modify-reservation.php?id=${reservationId}`;
}

// Populate edit form
function populateEditForm(reservation) {
    document.getElementById('edit_reservation_id').value = reservation.id;
    document.getElementById('edit_first_name').value = reservation.first_name;
    document.getElementById('edit_last_name').value = reservation.last_name;
    document.getElementById('edit_email').value = reservation.email || '';
    document.getElementById('edit_phone').value = reservation.phone;
    document.getElementById('edit_check_in_date').value = reservation.check_in_date;
    document.getElementById('edit_check_out_date').value = reservation.check_out_date;
    document.getElementById('edit_adults').value = reservation.adults;
    document.getElementById('edit_children').value = reservation.children || 0;
    document.getElementById('edit_room_type').value = reservation.room_type;
    document.getElementById('edit_special_requests').value = reservation.special_requests || '';
}

// Show edit modal
function showEditModal() {
    document.getElementById('edit-modal').classList.remove('hidden');
}

// Close edit modal
function closeEditModal() {
    document.getElementById('edit-modal').classList.add('hidden');
    document.getElementById('edit-reservation-form').reset();
}

// Initialize edit form
function initializeEditForm() {
    const form = document.getElementById('edit-reservation-form');
    if (form) {
        form.addEventListener('submit', handleEditSubmit);
    }
}

// Handle edit form submission
function handleEditSubmit(e) {
    e.preventDefault();
    
    if (!validateEditForm()) {
                    Utils.showNotification('Please fill in all required fields', 'warning');
        return;
    }
    
    const formData = new FormData(e.target);
    const data = Object.fromEntries(formData.entries());
    
    // Show loading state
    const submitBtn = e.target.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Updating...';
    
    // Submit update
    fetch('../../api/update-reservation.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            Utils.showNotification('Reservation updated successfully!', 'success');
            closeEditModal();
            loadReservations();
        } else {
            Utils.showNotification(result.message || 'Error updating reservation', 'error');
        }
    })
    .catch(error => {
        console.error('Error updating reservation:', error);
        Utils.showNotification('Error updating reservation', 'error');
    })
    .finally(() => {
        // Reset button state
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    });
}

// Validate edit form
function validateEditForm() {
    const requiredFields = ['first_name', 'last_name', 'phone', 'check_in_date', 'check_out_date', 'adults', 'room_type'];
    
    let isValid = true;
    
    requiredFields.forEach(field => {
        const element = document.getElementById(`edit_${field}`);
        if (!element.value.trim()) {
            FormValidator.showFieldError(element, 'This field is required');
            isValid = false;
        } else {
            FormValidator.clearFieldError(element);
        }
    });
    
    // Validate dates
    const checkInDate = new Date(document.getElementById('edit_check_in_date').value);
    const checkOutDate = new Date(document.getElementById('edit_check_out_date').value);
    
    if (checkOutDate <= checkInDate) {
        FormValidator.showFieldError(document.getElementById('edit_check_out_date'), 'Check-out date must be after check-in date');
        isValid = false;
    }
    
    return isValid;
}

// Show cancel modal
function showCancelModal(reservationId, reservationNumber, guestName) {
    document.getElementById('cancel_reservation_number').textContent = reservationNumber;
    document.getElementById('cancel_guest_name').textContent = guestName;
    document.getElementById('cancel-modal').classList.remove('hidden');
    window.cancelReservationId = reservationId;
}

// Close cancel modal
function closeCancelModal() {
    document.getElementById('cancel-modal').classList.add('hidden');
    window.cancelReservationId = null;
}

// Confirm cancel reservation
function confirmCancelReservation() {
    if (!window.cancelReservationId) return;
    
    fetch('../../api/cancel-reservation.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            reservation_id: window.cancelReservationId
        })
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            Utils.showNotification('Reservation cancelled successfully!', 'success');
            closeCancelModal();
            loadReservations();
        } else {
            Utils.showNotification(result.message || 'Error cancelling reservation', 'error');
        }
    })
    .catch(error => {
        console.error('Error cancelling reservation:', error);
        Utils.showNotification('Error cancelling reservation', 'error');
    });
}

// View reservation details
function viewReservationDetails(reservationId) {
    // Redirect to reservation details page
    window.location.href = `view-reservation.php?id=${reservationId}`;
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
window.ManageReservations = {
    loadReservations,
    searchReservations,
    editReservation,
    showCancelModal,
    confirmCancelReservation
};
