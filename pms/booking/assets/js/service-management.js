// Service Management JavaScript
document.addEventListener('DOMContentLoaded', function() {
    initializeServiceManagement();
    loadServiceRequests();
});

function initializeServiceManagement() {
    // Initialize tab functionality
    switchTab('service-requests');
    
    // Initialize filter change listeners
    document.getElementById('request-status-filter').addEventListener('change', loadServiceRequests);
    document.getElementById('request-type-filter').addEventListener('change', loadServiceRequests);
    document.getElementById('service-category-filter').addEventListener('change', loadAdditionalServices);
    document.getElementById('charges-date-filter').addEventListener('change', loadServiceCharges);
    document.getElementById('charges-status-filter').addEventListener('change', loadServiceCharges);
    
    // Initialize form handlers
    document.getElementById('service-request-form').addEventListener('submit', handleServiceRequestSubmit);
    document.getElementById('additional-service-form').addEventListener('submit', handleAdditionalServiceSubmit);
    document.getElementById('minibar-form').addEventListener('submit', handleMinibarSubmit);
    document.getElementById('laundry-form').addEventListener('submit', handleLaundrySubmit);
    
    // Initialize calculation listeners
    document.getElementById('service_quantity').addEventListener('input', calculateServiceTotal);
    document.getElementById('service_unit_price').addEventListener('input', calculateServiceTotal);
}

// Tab switching functionality
function switchTab(tabName) {
    // Hide all tab contents
    document.querySelectorAll('.tab-content').forEach(content => {
        content.classList.add('hidden');
        content.classList.remove('active');
    });
    
    // Remove active class from all tab buttons
    document.querySelectorAll('.tab-button').forEach(button => {
        button.classList.remove('active');
        button.classList.remove('border-primary', 'text-primary');
        button.classList.add('border-transparent', 'text-gray-500');
    });
    
    // Show selected tab content
    const selectedContent = document.getElementById(`tab-content-${tabName}`);
    if (selectedContent) {
        selectedContent.classList.remove('hidden');
        selectedContent.classList.add('active');
    }
    
    // Activate selected tab button
    const selectedButton = document.getElementById(`tab-${tabName}`);
    if (selectedButton) {
        selectedButton.classList.add('active', 'border-primary', 'text-primary');
        selectedButton.classList.remove('border-transparent', 'text-gray-500');
    }
    
    // Load appropriate data
    switch(tabName) {
        case 'service-requests':
            loadServiceRequests();
            break;
        case 'additional-services':
            loadAdditionalServices();
            break;
        case 'service-charges':
            loadServiceCharges();
            break;
    }
}

// Load service requests
function loadServiceRequests() {
    const statusFilter = document.getElementById('request-status-filter').value;
    const typeFilter = document.getElementById('request-type-filter').value;
    
    const params = new URLSearchParams({
        status: statusFilter,
        type: typeFilter
    });
    
    fetch(`../../api/get-service-requests.php?${params}`, {
        credentials: 'same-origin'
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayServiceRequests(data.requests);
            } else {
                Utils.showNotification(data.message || 'Error loading service requests', 'error');
            }
        })
        .catch(error => {
            console.error('Error loading service requests:', error);
            Utils.showNotification('Error loading service requests', 'error');
        });
}

// Display service requests
function displayServiceRequests(requests) {
    const container = document.getElementById('service-requests-container');
    
    if (!requests || requests.length === 0) {
        container.innerHTML = `
            <div class="text-center py-8">
                <i class="fas fa-tools text-gray-400 text-4xl mb-4"></i>
                <h3 class="text-lg font-medium text-gray-900 mb-2">No service requests found</h3>
                <p class="text-gray-500">No service requests match your current filters.</p>
            </div>
        `;
        return;
    }
    
    const tableHtml = `
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Request</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Room</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Priority</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    ${requests.map(request => `
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium text-gray-900">${request.description.substring(0, 50)}${request.description.length > 50 ? '...' : ''}</div>
                                <div class="text-sm text-gray-500">Reported by: ${request.reported_by_name || 'Unknown'}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${request.room_number}</td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 py-1 text-xs font-medium rounded-full ${getRequestTypeClass(request.issue_type)}">
                                    ${getRequestTypeLabel(request.issue_type)}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 py-1 text-xs font-medium rounded-full ${getPriorityClass(request.priority)}">
                                    ${getPriorityLabel(request.priority)}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 py-1 text-xs font-medium rounded-full ${getStatusClass(request.status)}">
                                    ${getStatusLabel(request.status)}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${formatDate(request.created_at)}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <div class="flex space-x-2">
                                    <button onclick="viewServiceRequest(${request.id})" 
                                            class="text-blue-600 hover:text-blue-900">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    ${request.status === 'reported' ? `
                                        <button onclick="updateServiceRequestStatus(${request.id}, 'assigned')" 
                                                class="text-blue-600 hover:text-blue-900">
                                            <i class="fas fa-user-plus"></i>
                                        </button>
                                    ` : ''}
                                    ${request.status === 'assigned' ? `
                                        <button onclick="updateServiceRequestStatus(${request.id}, 'in_progress')" 
                                                class="text-yellow-600 hover:text-yellow-900">
                                            <i class="fas fa-play"></i>
                                        </button>
                                    ` : ''}
                                    ${request.status === 'in_progress' ? `
                                        <button onclick="updateServiceRequestStatus(${request.id}, 'completed')" 
                                                class="text-green-600 hover:text-green-900">
                                            <i class="fas fa-check"></i>
                                        </button>
                                    ` : ''}
                                    ${request.status === 'completed' ? `
                                        <button onclick="updateServiceRequestStatus(${request.id}, 'verified')" 
                                                class="text-purple-600 hover:text-purple-900">
                                            <i class="fas fa-check-double"></i>
                                        </button>
                                    ` : ''}
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

// Load additional services
function loadAdditionalServices() {
    const categoryFilter = document.getElementById('service-category-filter').value;
    
    const params = new URLSearchParams({
        category: categoryFilter
    });
    
    fetch(`../../api/get-additional-services.php?${params}`, {
        credentials: 'same-origin'
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayAdditionalServices(data.services);
            } else {
                Utils.showNotification(data.message || 'Error loading additional services', 'error');
            }
        })
        .catch(error => {
            console.error('Error loading additional services:', error);
            Utils.showNotification('Error loading additional services', 'error');
        });
}

// Display additional services
function displayAdditionalServices(services) {
    const container = document.getElementById('additional-services-container');
    
    if (!services || services.length === 0) {
        container.innerHTML = `
            <div class="text-center py-8">
                <i class="fas fa-concierge-bell text-gray-400 text-4xl mb-4"></i>
                <h3 class="text-lg font-medium text-gray-900 mb-2">No additional services found</h3>
                <p class="text-gray-500">No additional services match your current filters.</p>
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
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Service</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quantity</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    ${services.map(service => `
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium text-gray-900">${service.guest_name}</div>
                                <div class="text-sm text-gray-500">Room ${service.room_number}</div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium text-gray-900">${service.service_name}</div>
                                <div class="text-sm text-gray-500">${service.description || 'No description'}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 py-1 text-xs font-medium rounded-full ${getServiceCategoryClass(service.service_category)}">
                                    ${getServiceCategoryLabel(service.service_category)}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${service.quantity}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">₱${parseFloat(service.total_price).toFixed(2)}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${formatDate(service.created_at)}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <button onclick="viewServiceDetails(${service.id})" 
                                        class="text-blue-600 hover:text-blue-900">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        </div>
    `;
    
    container.innerHTML = tableHtml;
}

// Load service charges
function loadServiceCharges() {
    const dateFilter = document.getElementById('charges-date-filter').value;
    const statusFilter = document.getElementById('charges-status-filter').value;
    
    const params = new URLSearchParams({
        date: dateFilter,
        status: statusFilter
    });
    
    fetch(`../../api/get-service-charges.php?${params}`, {
        credentials: 'same-origin'
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayServiceCharges(data.charges);
            } else {
                Utils.showNotification(data.message || 'Error loading service charges', 'error');
            }
        })
        .catch(error => {
            console.error('Error loading service charges:', error);
            Utils.showNotification('Error loading service charges', 'error');
        });
}

// Display service charges
function displayServiceCharges(charges) {
    const container = document.getElementById('service-charges-container');
    
    if (!charges || charges.length === 0) {
        container.innerHTML = `
            <div class="text-center py-8">
                <i class="fas fa-dollar-sign text-gray-400 text-4xl mb-4"></i>
                <h3 class="text-lg font-medium text-gray-900 mb-2">No service charges found</h3>
                <p class="text-gray-500">No service charges match your current filters.</p>
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
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Service</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    ${charges.map(charge => `
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium text-gray-900">${charge.guest_name}</div>
                                <div class="text-sm text-gray-500">Room ${charge.room_number}</div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium text-gray-900">${charge.service_name}</div>
                                <div class="text-sm text-gray-500">${charge.service_category}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">₱${parseFloat(charge.total_price).toFixed(2)}</td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 py-1 text-xs font-medium rounded-full bg-green-100 text-green-800">
                                    Billed
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${formatDate(charge.created_at)}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <button onclick="viewChargeDetails(${charge.id})" 
                                        class="text-blue-600 hover:text-blue-900">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        </div>
    `;
    
    container.innerHTML = tableHtml;
}

// Modal functions
function openServiceRequestModal() {
    loadAvailableRooms('request_room_id');
    document.getElementById('service-request-modal').classList.remove('hidden');
}

function closeServiceRequestModal() {
    document.getElementById('service-request-modal').classList.add('hidden');
    document.getElementById('service-request-form').reset();
}

function openAdditionalServiceModal() {
    loadActiveReservations('service_reservation_id');
    document.getElementById('additional-service-modal').classList.remove('hidden');
}

function closeAdditionalServiceModal() {
    document.getElementById('additional-service-modal').classList.add('hidden');
    document.getElementById('additional-service-form').reset();
    document.getElementById('service_total_amount').value = '';
}

function openMinibarModal() {
    loadAvailableRooms('minibar_room_id');
    loadMinibarItems();
    document.getElementById('minibar-modal').classList.remove('hidden');
}

function closeMinibarModal() {
    document.getElementById('minibar-modal').classList.add('hidden');
    document.getElementById('minibar-form').reset();
    document.getElementById('minibar-total').textContent = '$0.00';
}

function openLaundryModal() {
    loadActiveReservations('laundry_reservation_id');
    document.getElementById('laundry-modal').classList.remove('hidden');
}

function closeLaundryModal() {
    document.getElementById('laundry-modal').classList.add('hidden');
    document.getElementById('laundry-form').reset();
}

// Form handlers
function handleServiceRequestSubmit(e) {
    e.preventDefault();
    
    if (!validateServiceRequestForm()) {
        Utils.showNotification('Please fill in all required fields', 'warning');
        return;
    }
    
    const formData = new FormData(e.target);
    const data = Object.fromEntries(formData.entries());
    
    // Show loading state
    const submitBtn = e.target.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Submitting...';
    
    // Submit service request
    fetch('../../api/create-service-request.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        credentials: 'same-origin',
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            Utils.showNotification('Service request created successfully!', 'success');
            closeServiceRequestModal();
            loadServiceRequests();
        } else {
            Utils.showNotification(result.message || 'Error creating service request', 'error');
        }
    })
    .catch(error => {
        console.error('Error creating service request:', error);
        Utils.showNotification('Error creating service request', 'error');
    })
    .finally(() => {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    });
}

function handleAdditionalServiceSubmit(e) {
    e.preventDefault();
    
    if (!validateAdditionalServiceForm()) {
        Utils.showNotification('Please fill in all required fields', 'warning');
        return;
    }
    
    const formData = new FormData(e.target);
    const data = Object.fromEntries(formData.entries());
    
    // Show loading state
    const submitBtn = e.target.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Adding...';
    
    // Submit additional service
    fetch('../../api/add-additional-service.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        credentials: 'same-origin',
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            Utils.showNotification('Service added successfully!', 'success');
            closeAdditionalServiceModal();
            loadAdditionalServices();
        } else {
            Utils.showNotification(result.message || 'Error adding service', 'error');
        }
    })
    .catch(error => {
        console.error('Error adding service:', error);
        Utils.showNotification('Error adding service', 'error');
    })
    .finally(() => {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    });
}

function handleMinibarSubmit(e) {
    e.preventDefault();
    
    if (!validateMinibarForm()) {
        Utils.showNotification('Please select at least one minibar item', 'warning');
        return;
    }
    
    const formData = new FormData(e.target);
    const data = Object.fromEntries(formData.entries());
    
    // Get minibar items
    const minibarItems = [];
    document.querySelectorAll('.minibar-item').forEach(item => {
        const quantity = parseInt(item.querySelector('.minibar-quantity').value);
        if (quantity > 0) {
            minibarItems.push({
                item_id: item.dataset.itemId,
                quantity: quantity,
                unit_price: parseFloat(item.dataset.unitPrice)
            });
        }
    });
    
    data.minibar_items = minibarItems;
    
    // Show loading state
    const submitBtn = e.target.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Saving...';
    
    // Submit minibar charges
    fetch('../../api/add-minibar-charges.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        credentials: 'same-origin',
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            Utils.showNotification('Minibar charges saved successfully!', 'success');
            closeMinibarModal();
            loadAdditionalServices();
        } else {
            Utils.showNotification(result.message || 'Error saving minibar charges', 'error');
        }
    })
    .catch(error => {
        console.error('Error saving minibar charges:', error);
        Utils.showNotification('Error saving minibar charges', 'error');
    })
    .finally(() => {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    });
}

function handleLaundrySubmit(e) {
    e.preventDefault();
    
    if (!validateLaundryForm()) {
        Utils.showNotification('Please fill in all required fields', 'warning');
        return;
    }
    
    const formData = new FormData(e.target);
    const data = Object.fromEntries(formData.entries());
    
    // Show loading state
    const submitBtn = e.target.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Adding...';
    
    // Submit laundry service
    fetch('../../api/add-laundry-service.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        credentials: 'same-origin',
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            Utils.showNotification('Laundry service added successfully!', 'success');
            closeLaundryModal();
            loadAdditionalServices();
        } else {
            Utils.showNotification(result.message || 'Error adding laundry service', 'error');
        }
    })
    .catch(error => {
        console.error('Error adding laundry service:', error);
        Utils.showNotification('Error adding laundry service', 'error');
    })
    .finally(() => {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    });
}

// Utility functions
function loadAvailableRooms(selectId) {
    fetch('../../api/get-available-rooms.php', {
        credentials: 'same-origin'
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const select = document.getElementById(selectId);
                select.innerHTML = '<option value="">Select Room</option>';
                data.rooms.forEach(room => {
                    select.innerHTML += `<option value="${room.id}">${room.room_number} - ${room.room_type}</option>`;
                });
            }
        })
        .catch(error => {
            console.error('Error loading rooms:', error);
        });
}

function loadActiveReservations(selectId) {
    fetch('../../api/get-active-reservations.php', {
        credentials: 'same-origin'
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const select = document.getElementById(selectId);
                select.innerHTML = '<option value="">Select Reservation</option>';
                data.reservations.forEach(reservation => {
                    select.innerHTML += `<option value="${reservation.id}">${reservation.guest_name} - Room ${reservation.room_number}</option>`;
                });
            }
        })
        .catch(error => {
            console.error('Error loading reservations:', error);
        });
}

function loadMinibarItems() {
    fetch('../../api/get-minibar-items.php', {
        credentials: 'same-origin'
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const container = document.getElementById('minibar-items-container');
                container.innerHTML = data.items.map(item => `
                    <div class="minibar-item flex items-center justify-between p-3 border rounded-lg" data-item-id="${item.id}" data-unit-price="${item.unit_price}">
                        <div class="flex items-center space-x-3">
                            <input type="number" class="minibar-quantity w-16 px-2 py-1 border rounded text-center" 
                                   min="0" value="0" onchange="calculateMinibarTotal()">
                            <span class="text-sm font-medium">${item.name}</span>
                            <span class="text-sm text-gray-500">₱${parseFloat(item.unit_price).toFixed(2)} each</span>
                        </div>
                        <span class="minibar-item-total text-sm font-medium">$0.00</span>
                    </div>
                `).join('');
            }
        })
        .catch(error => {
            console.error('Error loading minibar items:', error);
        });
}

function calculateServiceTotal() {
    const quantity = parseInt(document.getElementById('service_quantity').value) || 0;
    const unitPrice = parseFloat(document.getElementById('service_unit_price').value) || 0;
    const total = quantity * unitPrice;
    document.getElementById('service_total_amount').value = `₱${total.toFixed(2)}`;
}

function calculateMinibarTotal() {
    let total = 0;
    document.querySelectorAll('.minibar-item').forEach(item => {
        const quantity = parseInt(item.querySelector('.minibar-quantity').value) || 0;
        const unitPrice = parseFloat(item.dataset.unitPrice) || 0;
        const itemTotal = quantity * unitPrice;
        item.querySelector('.minibar-item-total').textContent = `₱${itemTotal.toFixed(2)}`;
        total += itemTotal;
    });
    document.getElementById('minibar-total').textContent = `₱${total.toFixed(2)}`;
}

// Validation functions
function validateServiceRequestForm() {
    const requiredFields = ['room_id', 'issue_type', 'priority', 'description'];
    return requiredFields.every(field => {
        const input = document.getElementById(field);
        return input && input.value.trim();
    });
}

function validateAdditionalServiceForm() {
    const requiredFields = ['reservation_id', 'service_category', 'service_name', 'unit_price'];
    return requiredFields.every(field => {
        const input = document.getElementById(field);
        return input && input.value.trim();
    });
}

function validateMinibarForm() {
    let hasItems = false;
    document.querySelectorAll('.minibar-quantity').forEach(input => {
        if (parseInt(input.value) > 0) {
            hasItems = true;
        }
    });
    return hasItems;
}

function validateLaundryForm() {
    const requiredFields = ['laundry_reservation_id', 'laundry_service_type'];
    return requiredFields.every(field => {
        const input = document.getElementById(field);
        return input && input.value.trim();
    });
}

// Helper functions for styling
function getRequestTypeClass(type) {
    switch (type) {
        case 'plumbing': return 'bg-blue-100 text-blue-800';
        case 'electrical': return 'bg-yellow-100 text-yellow-800';
        case 'hvac': return 'bg-green-100 text-green-800';
        case 'furniture': return 'bg-purple-100 text-purple-800';
        case 'appliance': return 'bg-red-100 text-red-800';
        case 'other': return 'bg-gray-100 text-gray-800';
        default: return 'bg-gray-100 text-gray-800';
    }
}

function getRequestTypeLabel(type) {
    try {
        if (!type || typeof type !== 'string') {
            return 'Unknown';
        }
        return type.charAt(0).toUpperCase() + type.slice(1).replace('_', ' ');
    } catch (error) {
        console.error('Error in getRequestTypeLabel:', error);
        return 'Unknown';
    }
}

function getPriorityClass(priority) {
    switch (priority) {
        case 'urgent': return 'bg-red-100 text-red-800';
        case 'high': return 'bg-orange-100 text-orange-800';
        case 'medium': return 'bg-yellow-100 text-yellow-800';
        case 'low': return 'bg-green-100 text-green-800';
        default: return 'bg-gray-100 text-gray-800';
    }
}

function getPriorityLabel(priority) {
    if (!priority || typeof priority !== 'string') return 'Unknown';
    return priority.charAt(0).toUpperCase() + priority.slice(1);
}

function getStatusClass(status) {
    switch (status) {
        case 'reported': return 'bg-yellow-100 text-yellow-800';
        case 'assigned': return 'bg-blue-100 text-blue-800';
        case 'in_progress': return 'bg-orange-100 text-orange-800';
        case 'completed': return 'bg-green-100 text-green-800';
        case 'verified': return 'bg-purple-100 text-purple-800';
        default: return 'bg-gray-100 text-gray-800';
    }
}

function getStatusLabel(status) {
    if (!status || typeof status !== 'string') return 'Unknown';
    return status.replace('_', ' ').charAt(0).toUpperCase() + status.replace('_', ' ').slice(1);
}

function getServiceCategoryClass(category) {
    switch (category) {
        case 'minibar': return 'bg-yellow-100 text-yellow-800';
        case 'laundry': return 'bg-purple-100 text-purple-800';
        case 'spa': return 'bg-pink-100 text-pink-800';
        case 'restaurant': return 'bg-green-100 text-green-800';
        case 'transportation': return 'bg-blue-100 text-blue-800';
        default: return 'bg-gray-100 text-gray-800';
    }
}

function getServiceCategoryLabel(category) {
    if (!category || typeof category !== 'string') return 'Unknown';
    return category.charAt(0).toUpperCase() + category.slice(1);
}

function getChargeStatusClass(status) {
    switch (status) {
        case 'pending': return 'bg-yellow-100 text-yellow-800';
        case 'billed': return 'bg-blue-100 text-blue-800';
        case 'paid': return 'bg-green-100 text-green-800';
        default: return 'bg-gray-100 text-gray-800';
    }
}

function getChargeStatusLabel(status) {
    if (!status || typeof status !== 'string') return 'Unknown';
    return status.charAt(0).toUpperCase() + status.slice(1);
}

function formatDate(dateString) {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

// View functions for details
function viewServiceRequest(requestId) {
    // Show loading state
    Utils.showNotification('Loading service request details...', 'info');
    
    // Fetch service request details
    fetch(`../../api/get-service-request-details.php?id=${requestId}`, {
        credentials: 'same-origin'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            displayServiceRequestDetails(data);
        } else {
            Utils.showNotification(data.message || 'Error loading service request details', 'error');
        }
    })
    .catch(error => {
        console.error('Error loading service request details:', error);
        Utils.showNotification('Error loading service request details', 'error');
    });
}

function viewServiceDetails(serviceId) {
    // Show loading state
    Utils.showNotification('Loading service details...', 'info');
    
    // Fetch service details
    fetch(`../../api/get-service-details.php?id=${serviceId}`, {
        credentials: 'same-origin'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            displayServiceDetails(data);
        } else {
            Utils.showNotification(data.message || 'Error loading service details', 'error');
        }
    })
    .catch(error => {
        console.error('Error loading service details:', error);
        Utils.showNotification('Error loading service details', 'error');
    });
}

function viewChargeDetails(chargeId) {
    // Show loading state
    Utils.showNotification('Loading charge details...', 'info');
    
    // Fetch charge details
    fetch(`../../api/get-charge-details.php?id=${chargeId}`, {
        credentials: 'same-origin'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            displayChargeDetails(data);
        } else {
            Utils.showNotification(data.message || 'Error loading charge details', 'error');
        }
    })
    .catch(error => {
        console.error('Error loading charge details:', error);
        Utils.showNotification('Error loading charge details', 'error');
    });
}

// Status update functions
function updateServiceRequestStatus(requestId, newStatus) {
    // Show loading state
    Utils.showNotification(`Updating status to ${newStatus}...`, 'info');
    
    // Submit status update
    fetch('../../api/update-service-request-status.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        credentials: 'same-origin',
        body: JSON.stringify({
            request_id: requestId,
            status: newStatus
        })
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            Utils.showNotification(`Status updated to ${newStatus} successfully!`, 'success');
            loadServiceRequests(); // Reload the data
        } else {
            Utils.showNotification(result.message || 'Error updating status', 'error');
        }
    })
    .catch(error => {
        console.error('Error updating status:', error);
        Utils.showNotification('Error updating status', 'error');
    });
}

// Display functions for detailed modals
function displayServiceRequestDetails(data) {
    const request = data.request;
    const costComparison = data.cost_comparison;
    
    const content = `
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Basic Information -->
            <div class="space-y-4">
                <div class="bg-gray-50 p-4 rounded-lg">
                    <h4 class="text-lg font-semibold text-gray-900 mb-3">Request Information</h4>
                    <div class="space-y-2">
                        <div class="flex justify-between">
                            <span class="text-sm font-medium text-gray-600">Request ID:</span>
                            <span class="text-sm text-gray-900">#${request.id}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm font-medium text-gray-600">Issue Type:</span>
                            <span class="px-2 py-1 text-xs font-medium rounded-full ${getRequestTypeClass(request.issue_type)}">
                                ${getRequestTypeLabel(request.issue_type)}
                            </span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm font-medium text-gray-600">Priority:</span>
                            <span class="px-2 py-1 text-xs font-medium rounded-full ${getPriorityClass(request.priority)}">
                                ${getPriorityLabel(request.priority)}
                            </span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm font-medium text-gray-600">Status:</span>
                            <span class="px-2 py-1 text-xs font-medium rounded-full ${getStatusClass(request.status)}">
                                ${getStatusLabel(request.status)}
                            </span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm font-medium text-gray-600">Created:</span>
                            <span class="text-sm text-gray-900">${formatDate(request.created_at)}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm font-medium text-gray-600">Last Updated:</span>
                            <span class="text-sm text-gray-900">${formatDate(request.updated_at)}</span>
                        </div>
                    </div>
                </div>

                <div class="bg-gray-50 p-4 rounded-lg">
                    <h4 class="text-lg font-semibold text-gray-900 mb-3">Room Information</h4>
                    <div class="space-y-2">
                        <div class="flex justify-between">
                            <span class="text-sm font-medium text-gray-600">Room Number:</span>
                            <span class="text-sm text-gray-900">${request.room_number}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm font-medium text-gray-600">Room Type:</span>
                            <span class="text-sm text-gray-900">${request.room_type}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Guest and Staff Information -->
            <div class="space-y-4">
                <div class="bg-gray-50 p-4 rounded-lg">
                    <h4 class="text-lg font-semibold text-gray-900 mb-3">Guest Information</h4>
                    <div class="space-y-2">
                        <div class="flex justify-between">
                            <span class="text-sm font-medium text-gray-600">Guest Name:</span>
                            <span class="text-sm text-gray-900">${request.guest_name}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm font-medium text-gray-600">Email:</span>
                            <span class="text-sm text-gray-900">${request.guest_email}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm font-medium text-gray-600">Phone:</span>
                            <span class="text-sm text-gray-900">${request.guest_phone}</span>
                        </div>
                    </div>
                </div>

                <div class="bg-gray-50 p-4 rounded-lg">
                    <h4 class="text-lg font-semibold text-gray-900 mb-3">Staff Information</h4>
                    <div class="space-y-2">
                        <div class="flex justify-between">
                            <span class="text-sm font-medium text-gray-600">Reported By:</span>
                            <span class="text-sm text-gray-900">${request.reported_by_name} (${request.reported_by_role})</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm font-medium text-gray-600">Assigned To:</span>
                            <span class="text-sm text-gray-900">${request.assigned_to_name || 'Not assigned'} ${request.assigned_to_name ? `(${request.assigned_to_role})` : ''}</span>
                        </div>
                    </div>
                </div>

                <div class="bg-gray-50 p-4 rounded-lg">
                    <h4 class="text-lg font-semibold text-gray-900 mb-3">Cost Information</h4>
                    <div class="space-y-2">
                        <div class="flex justify-between">
                            <span class="text-sm font-medium text-gray-600">Estimated Cost:</span>
                            <span class="text-sm text-gray-900">₱${costComparison.estimated.toFixed(2)}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm font-medium text-gray-600">Actual Cost:</span>
                            <span class="text-sm text-gray-900">₱${costComparison.actual.toFixed(2)}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm font-medium text-gray-600">Difference:</span>
                            <span class="text-sm ${costComparison.difference >= 0 ? 'text-green-600' : 'text-red-600'}">
                                ${costComparison.difference >= 0 ? '+' : ''}₱${costComparison.difference.toFixed(2)}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Description -->
        <div class="bg-gray-50 p-4 rounded-lg">
            <h4 class="text-lg font-semibold text-gray-900 mb-3">Description</h4>
            <p class="text-sm text-gray-700">${request.description}</p>
        </div>
    `;
    
    document.getElementById('service-request-details-content').innerHTML = content;
    document.getElementById('service-request-details-modal').classList.remove('hidden');
}

function displayServiceDetails(data) {
    const service = data.service;
    const metrics = data.metrics;
    const similarServices = data.similar_services;
    
    const content = `
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Service Information -->
            <div class="space-y-4">
                <div class="bg-gray-50 p-4 rounded-lg">
                    <h4 class="text-lg font-semibold text-gray-900 mb-3">Service Information</h4>
                    <div class="space-y-2">
                        <div class="flex justify-between">
                            <span class="text-sm font-medium text-gray-600">Service ID:</span>
                            <span class="text-sm text-gray-900">#${service.id}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm font-medium text-gray-600">Service Name:</span>
                            <span class="text-sm text-gray-900">${service.service_name}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm font-medium text-gray-600">Category:</span>
                            <span class="px-2 py-1 text-xs font-medium rounded-full ${getServiceCategoryClass(service.service_category)}">
                                ${getServiceCategoryLabel(service.service_category)}
                            </span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm font-medium text-gray-600">Quantity:</span>
                            <span class="text-sm text-gray-900">${service.quantity}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm font-medium text-gray-600">Unit Price:</span>
                            <span class="text-sm text-gray-900">₱${parseFloat(service.unit_price).toFixed(2)}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm font-medium text-gray-600">Total Amount:</span>
                            <span class="text-sm font-semibold text-gray-900">₱${parseFloat(service.total_price).toFixed(2)}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm font-medium text-gray-600">Charged By:</span>
                            <span class="text-sm text-gray-900">${service.charged_by_name} (${service.charged_by_role})</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm font-medium text-gray-600">Created:</span>
                            <span class="text-sm text-gray-900">${formatDate(service.created_at)}</span>
                        </div>
                    </div>
                </div>

                <div class="bg-gray-50 p-4 rounded-lg">
                    <h4 class="text-lg font-semibold text-gray-900 mb-3">Guest Information</h4>
                    <div class="space-y-2">
                        <div class="flex justify-between">
                            <span class="text-sm font-medium text-gray-600">Guest Name:</span>
                            <span class="text-sm text-gray-900">${service.guest_name}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm font-medium text-gray-600">Email:</span>
                            <span class="text-sm text-gray-900">${service.guest_email}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm font-medium text-gray-600">Phone:</span>
                            <span class="text-sm text-gray-900">${service.guest_phone}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm font-medium text-gray-600">Room:</span>
                            <span class="text-sm text-gray-900">${service.room_number} (${service.room_type})</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Similar Services -->
            <div class="space-y-4">
                <div class="bg-gray-50 p-4 rounded-lg">
                    <h4 class="text-lg font-semibold text-gray-900 mb-3">Similar Services for This Guest</h4>
                    ${similarServices.length > 0 ? `
                        <div class="space-y-2">
                            ${similarServices.map(similar => `
                                <div class="flex justify-between items-center p-2 bg-white rounded border">
                                    <div>
                                        <div class="text-sm font-medium text-gray-900">${similar.service_name}</div>
                                        <div class="text-xs text-gray-500">${formatDate(similar.created_at)}</div>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-sm font-medium text-gray-900">₱${parseFloat(similar.total_price).toFixed(2)}</div>
                                        <div class="text-xs text-gray-500">Qty: ${similar.quantity}</div>
                                    </div>
                                </div>
                            `).join('')}
                        </div>
                    ` : `
                        <p class="text-sm text-gray-500">No other services found for this guest.</p>
                    `}
                </div>
            </div>
        </div>

        <!-- Notes -->
        ${service.notes ? `
            <div class="bg-gray-50 p-4 rounded-lg">
                <h4 class="text-lg font-semibold text-gray-900 mb-3">Notes</h4>
                <p class="text-sm text-gray-700">${service.notes}</p>
            </div>
        ` : ''}
    `;
    
    document.getElementById('service-details-content').innerHTML = content;
    document.getElementById('service-details-modal').classList.remove('hidden');
}

function displayChargeDetails(data) {
    const charge = data.charge;
    const metrics = data.metrics;
    const reservationCharges = data.reservation_charges;
    const reservationSummary = data.reservation_summary;
    
    const content = `
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Charge Information -->
            <div class="space-y-4">
                <div class="bg-gray-50 p-4 rounded-lg">
                    <h4 class="text-lg font-semibold text-gray-900 mb-3">Charge Information</h4>
                    <div class="space-y-2">
                        <div class="flex justify-between">
                            <span class="text-sm font-medium text-gray-600">Charge ID:</span>
                            <span class="text-sm text-gray-900">#${charge.id}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm font-medium text-gray-600">Service Name:</span>
                            <span class="text-sm text-gray-900">${charge.service_name}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm font-medium text-gray-600">Category:</span>
                            <span class="px-2 py-1 text-xs font-medium rounded-full ${getServiceCategoryClass(charge.service_category)}">
                                ${getServiceCategoryLabel(charge.service_category)}
                            </span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm font-medium text-gray-600">Quantity:</span>
                            <span class="text-sm text-gray-900">${charge.quantity}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm font-medium text-gray-600">Unit Price:</span>
                            <span class="text-sm text-gray-900">₱${parseFloat(charge.unit_price).toFixed(2)}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm font-medium text-gray-600">Total Amount:</span>
                            <span class="text-sm font-semibold text-gray-900">₱${parseFloat(charge.total_price).toFixed(2)}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm font-medium text-gray-600">Charged By:</span>
                            <span class="text-sm text-gray-900">${charge.charged_by_name} (${charge.charged_by_role})</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm font-medium text-gray-600">Created:</span>
                            <span class="text-sm text-gray-900">${formatDate(charge.created_at)}</span>
                        </div>
                    </div>
                </div>

                <div class="bg-gray-50 p-4 rounded-lg">
                    <h4 class="text-lg font-semibold text-gray-900 mb-3">Guest Information</h4>
                    <div class="space-y-2">
                        <div class="flex justify-between">
                            <span class="text-sm font-medium text-gray-600">Guest Name:</span>
                            <span class="text-sm text-gray-900">${charge.guest_name}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm font-medium text-gray-600">Email:</span>
                            <span class="text-sm text-gray-900">${charge.guest_email}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm font-medium text-gray-600">Phone:</span>
                            <span class="text-sm text-gray-900">${charge.guest_phone}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm font-medium text-gray-600">Room:</span>
                            <span class="text-sm text-gray-900">${charge.room_number} (${charge.room_type})</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm font-medium text-gray-600">Reservation:</span>
                            <span class="text-sm text-gray-900">#${charge.reservation_number}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Reservation Summary -->
            <div class="space-y-4">
                <div class="bg-gray-50 p-4 rounded-lg">
                    <h4 class="text-lg font-semibold text-gray-900 mb-3">Reservation Summary</h4>
                    <div class="space-y-2">
                        <div class="flex justify-between">
                            <span class="text-sm font-medium text-gray-600">Total Charges:</span>
                            <span class="text-sm font-semibold text-gray-900">₱${reservationSummary.total_charges.toFixed(2)}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm font-medium text-gray-600">Number of Charges:</span>
                            <span class="text-sm text-gray-900">${reservationSummary.charge_count}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm font-medium text-gray-600">Average Charge:</span>
                            <span class="text-sm text-gray-900">₱${reservationSummary.average_charge.toFixed(2)}</span>
                        </div>
                    </div>
                </div>

                <div class="bg-gray-50 p-4 rounded-lg">
                    <h4 class="text-lg font-semibold text-gray-900 mb-3">All Charges for This Reservation</h4>
                    ${reservationCharges.length > 0 ? `
                        <div class="space-y-2 max-h-48 overflow-y-auto">
                            ${reservationCharges.map(resCharge => `
                                <div class="flex justify-between items-center p-2 bg-white rounded border ${resCharge.id == charge.id ? 'ring-2 ring-blue-500' : ''}">
                                    <div>
                                        <div class="text-sm font-medium text-gray-900">${resCharge.service_name}</div>
                                        <div class="text-xs text-gray-500">${formatDate(resCharge.created_at)}</div>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-sm font-medium text-gray-900">₱${parseFloat(resCharge.total_price).toFixed(2)}</div>
                                        <div class="text-xs text-gray-500">Qty: ${resCharge.quantity}</div>
                                    </div>
                                </div>
                            `).join('')}
                        </div>
                    ` : `
                        <p class="text-sm text-gray-500">No other charges found for this reservation.</p>
                    `}
                </div>
            </div>
        </div>

        <!-- Notes -->
        ${charge.notes ? `
            <div class="bg-gray-50 p-4 rounded-lg">
                <h4 class="text-lg font-semibold text-gray-900 mb-3">Notes</h4>
                <p class="text-sm text-gray-700">${charge.notes}</p>
            </div>
        ` : ''}
    `;
    
    document.getElementById('charge-details-content').innerHTML = content;
    document.getElementById('charge-details-modal').classList.remove('hidden');
}

// Modal close functions
function closeServiceRequestDetailsModal() {
    document.getElementById('service-request-details-modal').classList.add('hidden');
}

function closeServiceDetailsModal() {
    document.getElementById('service-details-modal').classList.add('hidden');
}

function closeChargeDetailsModal() {
    document.getElementById('charge-details-modal').classList.add('hidden');
}
