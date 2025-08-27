// Guest Management JavaScript
document.addEventListener('DOMContentLoaded', function() {
    initializeGuestManagement();
    loadGuests();
});

function initializeGuestManagement() {
    // Initialize search functionality
    const searchInput = document.getElementById('search-input');
    let searchTimeout;
    
    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            loadGuests();
        }, 500);
    });
    
    // Initialize filter change listeners
    document.getElementById('vip-filter').addEventListener('change', loadGuests);
    document.getElementById('status-filter').addEventListener('change', loadGuests);
    
    // Initialize form handlers
    document.getElementById('guest-form').addEventListener('submit', handleGuestSubmit);
    document.getElementById('feedback-form').addEventListener('submit', handleFeedbackSubmit);
}

// Load guests with filters
function loadGuests(page = 1) {
    const search = document.getElementById('search-input').value;
    const vipFilter = document.getElementById('vip-filter').value;
    const statusFilter = document.getElementById('status-filter').value;
    
    const params = new URLSearchParams({
        search: search,
        vip: vipFilter,
        status: statusFilter,
        page: page
    });
    
    fetch(`../../api/get-guests.php?${params}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayGuests(data.guests);
                displayPagination(data.pagination);
            } else {
                HotelPMS.Utils.showNotification(data.message || 'Error loading guests', 'error');
            }
        })
        .catch(error => {
            console.error('Error loading guests:', error);
            HotelPMS.Utils.showNotification('Error loading guests', 'error');
        });
}

// Display guests in table
function displayGuests(guests) {
    const container = document.getElementById('guests-table-container');
    
    if (!guests || guests.length === 0) {
        container.innerHTML = `
            <div class="px-6 py-12 text-center">
                <i class="fas fa-users text-gray-400 text-4xl mb-4"></i>
                <h3 class="text-lg font-medium text-gray-900 mb-2">No guests found</h3>
                <p class="text-gray-500">Try adjusting your search or filters.</p>
            </div>
        `;
        return;
    }
    
    const tableHtml = `
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Guest</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contact</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Last Stay</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Stays</th>
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
                                        <div class="text-sm text-gray-500">ID: ${guest.id_number}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900">${guest.email || 'N/A'}</div>
                                <div class="text-sm text-gray-500">${guest.phone}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 py-1 text-xs font-medium rounded-full ${getGuestStatusClass(guest.status)}">
                                    ${getGuestStatusLabel(guest.status)}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                ${guest.last_stay ? formatDate(guest.last_stay) : 'Never'}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                ${guest.total_stays || 0}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <div class="flex space-x-2">
                                    <button onclick="viewGuestDetails(${guest.id})" 
                                            class="text-blue-600 hover:text-blue-900">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button onclick="editGuest(${guest.id})" 
                                            class="text-green-600 hover:text-green-900">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button onclick="addFeedback(${guest.id})" 
                                            class="text-purple-600 hover:text-purple-900">
                                        <i class="fas fa-comment"></i>
                                    </button>
                                    ${guest.is_vip ? 
                                        `<button onclick="toggleVIPStatus(${guest.id}, false)" 
                                                 class="text-yellow-600 hover:text-yellow-900" title="Remove VIP">
                                            <i class="fas fa-crown"></i>
                                        </button>` :
                                        `<button onclick="toggleVIPStatus(${guest.id}, true)" 
                                                 class="text-gray-400 hover:text-yellow-600" title="Make VIP">
                                            <i class="fas fa-crown"></i>
                                        </button>`
                                    }
                                </div>
                            </td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        </div>
    `;
    
    container.innerHTML = tableHtml;
}

// Display pagination
function displayPagination(pagination) {
    const container = document.getElementById('pagination-container');
    
    if (!pagination || pagination.total_pages <= 1) {
        container.innerHTML = '';
        return;
    }
    
    const currentPage = pagination.current_page;
    const totalPages = pagination.total_pages;
    
    let paginationHtml = `
        <div class="flex items-center justify-between">
            <div class="text-sm text-gray-700">
                Showing ${pagination.from} to ${pagination.to} of ${pagination.total} results
            </div>
            <div class="flex space-x-2">
    `;
    
    // Previous button
    if (currentPage > 1) {
        paginationHtml += `
            <button onclick="loadGuests(${currentPage - 1})" 
                    class="px-3 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50">
                Previous
            </button>
        `;
    }
    
    // Page numbers
    const startPage = Math.max(1, currentPage - 2);
    const endPage = Math.min(totalPages, currentPage + 2);
    
    for (let i = startPage; i <= endPage; i++) {
        if (i === currentPage) {
            paginationHtml += `
                <span class="px-3 py-2 border border-primary bg-primary text-white rounded-md text-sm font-medium">
                    ${i}
                </span>
            `;
        } else {
            paginationHtml += `
                <button onclick="loadGuests(${i})" 
                        class="px-3 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50">
                    ${i}
                </button>
            `;
        }
    }
    
    // Next button
    if (currentPage < totalPages) {
        paginationHtml += `
            <button onclick="loadGuests(${currentPage + 1})" 
                    class="px-3 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50">
                Next
            </button>
        `;
    }
    
    paginationHtml += `
            </div>
        </div>
    `;
    
    container.innerHTML = paginationHtml;
}

// Add new guest
function addNewGuest() {
    document.getElementById('modal-title').textContent = 'Add New Guest';
    document.getElementById('guest-form').reset();
    document.getElementById('guest_id').value = '';
    document.getElementById('guest-modal').classList.remove('hidden');
}

// Edit guest
function editGuest(guestId) {
    fetch(`../../api/get-guest-details.php?id=${guestId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                populateGuestForm(data.guest);
                document.getElementById('modal-title').textContent = 'Edit Guest';
                document.getElementById('guest-modal').classList.remove('hidden');
            } else {
                HotelPMS.Utils.showNotification(data.message || 'Error loading guest details', 'error');
            }
        })
        .catch(error => {
            console.error('Error loading guest details:', error);
            HotelPMS.Utils.showNotification('Error loading guest details', 'error');
        });
}

// Populate guest form
function populateGuestForm(guest) {
    document.getElementById('guest_id').value = guest.id;
    document.getElementById('first_name').value = guest.first_name;
    document.getElementById('last_name').value = guest.last_name;
    document.getElementById('email').value = guest.email || '';
    document.getElementById('phone').value = guest.phone;
    document.getElementById('date_of_birth').value = guest.date_of_birth || '';
    document.getElementById('nationality').value = guest.nationality || '';
    document.getElementById('address').value = guest.address || '';
    document.getElementById('id_type').value = guest.id_type;
    document.getElementById('id_number').value = guest.id_number;
    document.getElementById('is_vip').checked = guest.is_vip;
    document.getElementById('preferences').value = guest.preferences || '';
    document.getElementById('service_notes').value = guest.service_notes || '';
}

// Handle guest form submission
function handleGuestSubmit(e) {
    e.preventDefault();
    
    if (!validateGuestForm()) {
        HotelPMS.Utils.showNotification('Please fill in all required fields', 'warning');
        return;
    }
    
    const formData = new FormData(e.target);
    const data = Object.fromEntries(formData.entries());
    
    // Add checkbox values
    data.is_vip = formData.has('is_vip');
    
    // Show loading state
    const submitBtn = e.target.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Saving...';
    
    // Submit guest data
    fetch('../../api/save-guest.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            HotelPMS.Utils.showNotification('Guest saved successfully!', 'success');
            closeGuestModal();
            loadGuests();
        } else {
            HotelPMS.Utils.showNotification(result.message || 'Error saving guest', 'error');
        }
    })
    .catch(error => {
        console.error('Error saving guest:', error);
        HotelPMS.Utils.showNotification('Error saving guest', 'error');
    })
    .finally(() => {
        // Reset button state
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    });
}

// View guest details
function viewGuestDetails(guestId) {
    fetch(`../../api/get-guest-details.php?id=${guestId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayGuestDetails(data.guest);
                document.getElementById('guest-details-modal').classList.remove('hidden');
            } else {
                HotelPMS.Utils.showNotification(data.message || 'Error loading guest details', 'error');
            }
        })
        .catch(error => {
            console.error('Error loading guest details:', error);
            HotelPMS.Utils.showNotification('Error loading guest details', 'error');
        });
}

// Display guest details
function displayGuestDetails(guest) {
    const container = document.getElementById('guest-details-content');
    
    const html = `
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Basic Information -->
            <div>
                <h4 class="text-lg font-medium text-gray-900 mb-4">Basic Information</h4>
                <div class="space-y-3">
                    <div>
                        <span class="text-sm font-medium text-gray-500">Name:</span>
                        <span class="ml-2 text-sm text-gray-900">${guest.first_name} ${guest.last_name}</span>
                        ${guest.is_vip ? '<span class="ml-2 px-2 py-1 text-xs bg-yellow-100 text-yellow-800 rounded-full">VIP</span>' : ''}
                    </div>
                    <div>
                        <span class="text-sm font-medium text-gray-500">Email:</span>
                        <span class="ml-2 text-sm text-gray-900">${guest.email || 'N/A'}</span>
                    </div>
                    <div>
                        <span class="text-sm font-medium text-gray-500">Phone:</span>
                        <span class="ml-2 text-sm text-gray-900">${guest.phone}</span>
                    </div>
                    <div>
                        <span class="text-sm font-medium text-gray-500">Date of Birth:</span>
                        <span class="ml-2 text-sm text-gray-900">${guest.date_of_birth || 'N/A'}</span>
                    </div>
                    <div>
                        <span class="text-sm font-medium text-gray-500">Nationality:</span>
                        <span class="ml-2 text-sm text-gray-900">${guest.nationality || 'N/A'}</span>
                    </div>
                    <div>
                        <span class="text-sm font-medium text-gray-500">ID Type:</span>
                        <span class="ml-2 text-sm text-gray-900">${guest.id_type}</span>
                    </div>
                    <div>
                        <span class="text-sm font-medium text-gray-500">ID Number:</span>
                        <span class="ml-2 text-sm text-gray-900">${guest.id_number}</span>
                    </div>
                    <div>
                        <span class="text-sm font-medium text-gray-500">Address:</span>
                        <span class="ml-2 text-sm text-gray-900">${guest.address || 'N/A'}</span>
                    </div>
                </div>
            </div>
            
            <!-- Preferences and Notes -->
            <div>
                <h4 class="text-lg font-medium text-gray-900 mb-4">Preferences & Notes</h4>
                <div class="space-y-3">
                    <div>
                        <span class="text-sm font-medium text-gray-500">Preferences:</span>
                        <div class="mt-1 text-sm text-gray-900">${guest.preferences || 'No preferences recorded'}</div>
                    </div>
                    <div>
                        <span class="text-sm font-medium text-gray-500">Service Notes:</span>
                        <div class="mt-1 text-sm text-gray-900">${guest.service_notes || 'No service notes'}</div>
                    </div>
                </div>
                
                <h4 class="text-lg font-medium text-gray-900 mb-4 mt-6">Stay History</h4>
                <div class="space-y-3">
                    <div>
                        <span class="text-sm font-medium text-gray-500">Total Stays:</span>
                        <span class="ml-2 text-sm text-gray-900">${guest.total_stays || 0}</span>
                    </div>
                    <div>
                        <span class="text-sm font-medium text-gray-500">Last Stay:</span>
                        <span class="ml-2 text-sm text-gray-900">${guest.last_stay ? formatDate(guest.last_stay) : 'Never'}</span>
                    </div>
                    <div>
                        <span class="text-sm font-medium text-gray-500">Member Since:</span>
                        <span class="ml-2 text-sm text-gray-900">${formatDate(guest.created_at)}</span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Action Buttons -->
        <div class="flex justify-end space-x-4 mt-8 pt-6 border-t border-gray-200">
            <button onclick="editGuest(${guest.id})" 
                    class="px-4 py-2 bg-primary text-white rounded-md hover:bg-primary-dark transition-colors">
                <i class="fas fa-edit mr-2"></i>Edit Guest
            </button>
            <button onclick="addFeedback(${guest.id})" 
                    class="px-4 py-2 bg-purple-600 text-white rounded-md hover:bg-purple-700 transition-colors">
                <i class="fas fa-comment mr-2"></i>Add Feedback
            </button>
        </div>
    `;
    
    container.innerHTML = html;
}

// Add feedback
function addFeedback(guestId, reservationId = null) {
    document.getElementById('feedback_guest_id').value = guestId;
    document.getElementById('feedback_reservation_id').value = reservationId || '';
    document.getElementById('feedback-form').reset();
    document.getElementById('feedback-modal').classList.remove('hidden');
}

// Handle feedback submission
function handleFeedbackSubmit(e) {
    e.preventDefault();
    
    if (!validateFeedbackForm()) {
        HotelPMS.Utils.showNotification('Please fill in all required fields', 'warning');
        return;
    }
    
    const formData = new FormData(e.target);
    const data = Object.fromEntries(formData.entries());
    
    // Show loading state
    const submitBtn = e.target.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Submitting...';
    
    // Submit feedback
    fetch('../../api/save-feedback.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            HotelPMS.Utils.showNotification('Feedback submitted successfully!', 'success');
            closeFeedbackModal();
        } else {
            HotelPMS.Utils.showNotification(result.message || 'Error submitting feedback', 'error');
        }
    })
    .catch(error => {
        console.error('Error submitting feedback:', error);
        HotelPMS.Utils.showNotification('Error submitting feedback', 'error');
    })
    .finally(() => {
        // Reset button state
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    });
}

// Toggle VIP status
function toggleVIPStatus(guestId, makeVip) {
    const action = makeVip ? 'make VIP' : 'remove VIP status from';
    
    if (confirm(`Are you sure you want to ${action} this guest?`)) {
        fetch('../../api/toggle-vip-status.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                guest_id: guestId,
                is_vip: makeVip
            })
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                HotelPMS.Utils.showNotification(`Guest ${makeVip ? 'made VIP' : 'VIP status removed'} successfully!`, 'success');
                loadGuests();
            } else {
                HotelPMS.Utils.showNotification(result.message || 'Error updating VIP status', 'error');
            }
        })
        .catch(error => {
            console.error('Error updating VIP status:', error);
            HotelPMS.Utils.showNotification('Error updating VIP status', 'error');
        });
    }
}

// Close modals
function closeGuestModal() {
    document.getElementById('guest-modal').classList.add('hidden');
    document.getElementById('guest-form').reset();
}

function closeGuestDetailsModal() {
    document.getElementById('guest-details-modal').classList.add('hidden');
}

function closeFeedbackModal() {
    document.getElementById('feedback-modal').classList.add('hidden');
    document.getElementById('feedback-form').reset();
}

// Validation functions
function validateGuestForm() {
    const requiredFields = ['first_name', 'last_name', 'phone', 'id_type', 'id_number'];
    
    for (const field of requiredFields) {
        const input = document.getElementById(field);
        if (!input || !input.value.trim()) {
            return false;
        }
    }
    
    return true;
}

function validateFeedbackForm() {
    const requiredFields = ['feedback_type', 'category', 'comments'];
    
    for (const field of requiredFields) {
        const input = document.getElementById(field);
        if (!input || !input.value.trim()) {
            return false;
        }
    }
    
    return true;
}

// Helper functions
function getGuestStatusClass(status) {
    switch (status) {
        case 'active':
            return 'bg-green-100 text-green-800';
        case 'recent':
            return 'bg-blue-100 text-blue-800';
        case 'frequent':
            return 'bg-purple-100 text-purple-800';
        default:
            return 'bg-gray-100 text-gray-800';
    }
}

function getGuestStatusLabel(status) {
    switch (status) {
        case 'active':
            return 'Currently Staying';
        case 'recent':
            return 'Recent Guest';
        case 'frequent':
            return 'Frequent Guest';
        default:
            return 'Guest';
    }
}

function formatDate(dateString) {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
}
