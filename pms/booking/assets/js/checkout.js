// Check-out JavaScript

document.addEventListener('DOMContentLoaded', function() {
    // Load checked-in guests
    loadCheckedInGuests();
    
    // Initialize form handlers
    initializeCheckoutForm();
});

// Load checked-in guests
function loadCheckedInGuests() {
    const container = document.getElementById('checked-in-guests');
    if (!container) return;
    
    // Show loading
    container.innerHTML = '<div class="flex items-center justify-center py-8"><div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div></div>';
    
    // Fetch checked-in guests
    fetch('../../api/get-checked-in-guests.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayCheckedInGuests(data.guests);
            } else {
                container.innerHTML = '<div class="text-center py-8 text-gray-500">No checked-in guests</div>';
            }
        })
        .catch(error => {
            console.error('Error loading checked-in guests:', error);
            container.innerHTML = '<div class="text-center py-8 text-red-500">Error loading guests</div>';
        });
}

// Display checked-in guests
function displayCheckedInGuests(guests) {
    const container = document.getElementById('checked-in-guests');
    
    if (guests.length === 0) {
        container.innerHTML = '<div class="text-center py-8 text-gray-500">No guests currently checked in</div>';
        return;
    }
    
    const tableHtml = `
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Guest</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reservation</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Room</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Check-in Time</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                ${guests.map(guest => `
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 h-10 w-10">
                                    <div class="h-10 w-10 rounded-full bg-gray-300 flex items-center justify-center">
                                        <span class="text-sm font-medium text-gray-700">
                                            ${guest.first_name.charAt(0)}${guest.last_name.charAt(0)}
                                        </span>
                                    </div>
                                </div>
                                <div class="ml-4">
                                    <div class="text-sm font-medium text-gray-900">
                                        ${guest.first_name} ${guest.last_name}
                                        ${guest.is_vip ? '<span class="ml-2 px-2 py-1 text-xs bg-yellow-100 text-yellow-800 rounded-full">VIP</span>' : ''}
                                    </div>
                                    <div class="text-sm text-gray-500">${guest.adults} adults, ${guest.children} children</div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-900">${guest.reservation_number}</div>
                            <div class="text-sm text-gray-500">Check-out: ${formatDate(guest.check_out_date)}</div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-900">${guest.room_number}</div>
                            <div class="text-sm text-gray-500">${guest.room_type}</div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            ${formatDateTime(guest.check_in_time)}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 py-1 text-xs font-medium rounded-full bg-green-100 text-green-800">
                                Checked In
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            <button onclick="startCheckout(${guest.id})" 
                                    class="text-red-600 hover:text-red-900">
                                <i class="fas fa-sign-out-alt mr-1"></i>Check Out
                            </button>
                        </td>
                    </tr>
                `).join('')}
            </tbody>
        </table>
    `;
    
    container.innerHTML = tableHtml;
}

// Search checked-in guests
function searchCheckedInGuests() {
    const reservationNumber = document.getElementById('search_reservation').value;
    const guestName = document.getElementById('search_guest').value;
    const roomNumber = document.getElementById('search_room').value;
    
    // Show loading
    const container = document.getElementById('checked-in-guests');
    container.innerHTML = '<div class="flex items-center justify-center py-8"><div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div></div>';
    
    // Build query parameters
    const params = new URLSearchParams();
    if (reservationNumber) params.append('reservation_number', reservationNumber);
    if (guestName) params.append('guest_name', guestName);
    if (roomNumber) params.append('room_number', roomNumber);
    
    // Fetch search results
    fetch(`../../api/search-checked-in-guests.php?${params.toString()}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayCheckedInGuests(data.guests);
            } else {
                container.innerHTML = '<div class="text-center py-8 text-gray-500">No guests found</div>';
            }
        })
        .catch(error => {
            console.error('Error searching guests:', error);
            container.innerHTML = '<div class="text-center py-8 text-red-500">Error searching guests</div>';
        });
}

// Start check-out process
function startCheckout(reservationId) {
    // Fetch reservation details
    fetch(`../../api/get-reservation-details.php?id=${reservationId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                populateCheckoutForm(data.reservation);
                loadBillingSummary(reservationId);
                showCheckoutForm();
            } else {
                HotelPMS.Utils.showNotification(data.message || 'Error loading reservation details', 'error');
            }
        })
        .catch(error => {
            console.error('Error loading reservation details:', error);
            HotelPMS.Utils.showNotification('Error loading reservation details', 'error');
        });
}

// Populate check-out form
function populateCheckoutForm(reservation) {
    document.getElementById('reservation_id').value = reservation.id;
    document.getElementById('guest_name').value = `${reservation.first_name} ${reservation.last_name}`;
    document.getElementById('reservation_number').value = reservation.reservation_number;
    document.getElementById('room_number').value = reservation.room_number;
    document.getElementById('checkout_date').value = formatDate(reservation.check_out_date);
}

// Load billing summary
function loadBillingSummary(reservationId) {
    const container = document.getElementById('billing-summary');
    
    fetch(`../../api/get-billing-summary.php?reservation_id=${reservationId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayBillingSummary(data.billing);
            } else {
                container.innerHTML = '<div class="text-red-500">Error loading billing information</div>';
            }
        })
        .catch(error => {
            console.error('Error loading billing summary:', error);
            container.innerHTML = '<div class="text-red-500">Error loading billing information</div>';
        });
}

// Display billing summary
function displayBillingSummary(billing) {
    const container = document.getElementById('billing-summary');
    
    const html = `
        <div class="space-y-2">
            <div class="flex justify-between">
                <span class="text-gray-600">Room Charges:</span>
                <span class="font-medium">${HotelPMS.Utils.formatCurrency(billing.room_charges)}</span>
            </div>
            <div class="flex justify-between">
                <span class="text-gray-600">Additional Charges:</span>
                <span class="font-medium">${HotelPMS.Utils.formatCurrency(billing.additional_charges)}</span>
            </div>
            <div class="flex justify-between">
                <span class="text-gray-600">Tax:</span>
                <span class="font-medium">${HotelPMS.Utils.formatCurrency(billing.tax_amount)}</span>
            </div>
            <div class="border-t border-gray-300 pt-2">
                <div class="flex justify-between">
                    <span class="text-lg font-semibold text-gray-900">Total Amount:</span>
                    <span class="text-lg font-semibold text-primary">${HotelPMS.Utils.formatCurrency(billing.total_amount)}</span>
                </div>
            </div>
            <div class="flex justify-between">
                <span class="text-gray-600">Payment Status:</span>
                <span class="px-2 py-1 text-xs font-medium rounded-full ${getPaymentStatusClass(billing.payment_status)}">
                    ${billing.payment_status.toUpperCase()}
                </span>
            </div>
        </div>
    `;
    
    container.innerHTML = html;
}

// Get payment status class
function getPaymentStatusClass(status) {
    switch (status) {
        case 'paid': return 'bg-green-100 text-green-800';
        case 'pending': return 'bg-yellow-100 text-yellow-800';
        case 'partial': return 'bg-blue-100 text-blue-800';
        default: return 'bg-gray-100 text-gray-800';
    }
}

// Show check-out form
function showCheckoutForm() {
    document.getElementById('checkout-form-container').classList.remove('hidden');
    document.getElementById('checkout-form-container').scrollIntoView({ behavior: 'smooth' });
}

// Hide check-out form
function hideCheckoutForm() {
    document.getElementById('checkout-form-container').classList.add('hidden');
    document.getElementById('checkout-form').reset();
}

// Initialize check-out form
function initializeCheckoutForm() {
    const form = document.getElementById('checkout-form');
    if (form) {
        form.addEventListener('submit', handleCheckoutSubmit);
    }
}

// Handle check-out form submission
function handleCheckoutSubmit(e) {
    e.preventDefault();
    
    if (!validateCheckoutForm()) {
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
    
    // Submit check-out
    fetch('../../api/check-out-guest.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            HotelPMS.Utils.showNotification('Guest checked out successfully!', 'success');
            hideCheckoutForm();
            loadCheckedInGuests();
        } else {
            HotelPMS.Utils.showNotification(result.message || 'Error checking out guest', 'error');
        }
    })
    .catch(error => {
        console.error('Error checking out guest:', error);
        HotelPMS.Utils.showNotification('Error checking out guest', 'error');
    })
    .finally(() => {
        // Reset button state
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    });
}

// Validate check-out form
function validateCheckoutForm() {
    const requiredFields = ['room_key_returned', 'payment_status'];
    
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

// Cancel check-out
function cancelCheckout() {
    hideCheckoutForm();
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
window.Checkout = {
    loadCheckedInGuests,
    searchCheckedInGuests,
    startCheckout,
    handleCheckoutSubmit,
    cancelCheckout
};
