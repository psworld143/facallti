// Reservation JavaScript

document.addEventListener('DOMContentLoaded', function() {
    // Initialize form handlers
    initializeFormHandlers();
    
    // Load available rooms
    loadAvailableRooms();
    
    // Set up date change listeners
    setupDateListeners();
    
    // Set up room type change listener
    setupRoomTypeListener();
});

// Initialize form handlers
function initializeFormHandlers() {
    const form = document.getElementById('reservation-form');
    if (form) {
        form.addEventListener('submit', handleReservationSubmit);
    }
    
    // Add form validation
    addFormValidation();
}

// Handle reservation form submission
function handleReservationSubmit(e) {
    e.preventDefault();
    
    if (!validateForm()) {
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
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Creating Reservation...';
    
    // Submit reservation
    fetch('../../api/create-reservation.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            HotelPMS.Utils.showNotification('Reservation created successfully!', 'success');
            
            // Show success modal with reservation details
            showReservationSuccess(result.reservation_number, result.reservation_id);
        } else {
            HotelPMS.Utils.showNotification(result.message || 'Error creating reservation', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        HotelPMS.Utils.showNotification('Error creating reservation', 'error');
    })
    .finally(() => {
        // Reset button state
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    });
}

// Validate form
function validateForm() {
    const requiredFields = [
        'first_name', 'last_name', 'phone', 'id_type', 'id_number',
        'check_in_date', 'check_out_date', 'adults', 'room_type', 'booking_source'
    ];
    
    let isValid = true;
    
    requiredFields.forEach(field => {
        const element = document.getElementById(field);
        if (!element.value.trim()) {
            HotelPMS.FormValidator.showFieldError(element, 'This field is required');
            isValid = false;
        } else {
            HotelPMS.FormValidator.clearFieldError(element);
        }
    });
    
    // Validate dates
    const checkInDate = new Date(document.getElementById('check_in_date').value);
    const checkOutDate = new Date(document.getElementById('check_out_date').value);
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    
    if (checkInDate < today) {
        HotelPMS.FormValidator.showFieldError(document.getElementById('check_in_date'), 'Check-in date cannot be in the past');
        isValid = false;
    }
    
    if (checkOutDate <= checkInDate) {
        HotelPMS.FormValidator.showFieldError(document.getElementById('check_out_date'), 'Check-out date must be after check-in date');
        isValid = false;
    }
    
    // Validate email if provided
    const email = document.getElementById('email').value;
    if (email && !HotelPMS.Utils.validateEmail(email)) {
        HotelPMS.FormValidator.showFieldError(document.getElementById('email'), 'Please enter a valid email address');
        isValid = false;
    }
    
    return isValid;
}

// Add form validation
function addFormValidation() {
    const inputs = document.querySelectorAll('input, select, textarea');
    inputs.forEach(input => {
        input.addEventListener('blur', function() {
            if (this.hasAttribute('required') && !this.value.trim()) {
                HotelPMS.FormValidator.showFieldError(this, 'This field is required');
            } else {
                HotelPMS.FormValidator.clearFieldError(this);
            }
        });
        
        input.addEventListener('input', function() {
            HotelPMS.FormValidator.clearFieldError(this);
        });
    });
}

// Load available rooms
function loadAvailableRooms() {
    const container = document.getElementById('available-rooms');
    if (!container) return;
    
    // Show loading
    container.innerHTML = '<div class="flex items-center justify-center py-8"><div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div></div>';
    
    // Fetch available rooms
    fetch('../../api/get-available-rooms.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayAvailableRooms(data.rooms);
            } else {
                container.innerHTML = '<div class="text-center py-8 text-gray-500">No available rooms</div>';
            }
        })
        .catch(error => {
            console.error('Error loading available rooms:', error);
            container.innerHTML = '<div class="text-center py-8 text-red-500">Error loading rooms</div>';
        });
}

// Display available rooms
function displayAvailableRooms(rooms) {
    const container = document.getElementById('available-rooms');
    
    if (rooms.length === 0) {
        container.innerHTML = '<div class="text-center py-8 text-gray-500">No available rooms for the selected criteria</div>';
        return;
    }
    
    const roomsHtml = rooms.map(room => `
        <div class="border border-gray-200 rounded-lg p-4 hover:border-primary transition-colors cursor-pointer room-option" 
             data-room-id="${room.id}" data-room-number="${room.room_number}">
            <div class="flex items-center justify-between mb-2">
                <h4 class="font-medium text-gray-900">Room ${room.room_number}</h4>
                <span class="px-2 py-1 text-xs font-medium rounded-full bg-green-100 text-green-800">Available</span>
            </div>
            <div class="text-sm text-gray-600">
                <div>Type: ${room.room_type_name}</div>
                <div>Floor: ${room.floor}</div>
                <div>Capacity: ${room.capacity} persons</div>
                <div class="font-medium text-primary">₱${room.rate}/night</div>
            </div>
        </div>
    `).join('');
    
    container.innerHTML = roomsHtml;
    
    // Add click handlers
    document.querySelectorAll('.room-option').forEach(option => {
        option.addEventListener('click', function() {
            // Remove previous selection
            document.querySelectorAll('.room-option').forEach(opt => {
                opt.classList.remove('border-primary', 'bg-blue-50');
            });
            
            // Add selection to current option
            this.classList.add('border-primary', 'bg-blue-50');
            
            // Store selected room
            window.selectedRoomId = this.dataset.roomId;
            window.selectedRoomNumber = this.dataset.roomNumber;
        });
    });
}

// Set up date change listeners
function setupDateListeners() {
    const checkInDate = document.getElementById('check_in_date');
    const checkOutDate = document.getElementById('check_out_date');
    
    if (checkInDate && checkOutDate) {
        checkInDate.addEventListener('change', function() {
            // Set minimum check-out date
            const minCheckOut = new Date(this.value);
            minCheckOut.setDate(minCheckOut.getDate() + 1);
            checkOutDate.min = minCheckOut.toISOString().split('T')[0];
            
            // Update pricing
            updatePricing();
            
            // Reload available rooms
            loadAvailableRooms();
        });
        
        checkOutDate.addEventListener('change', function() {
            // Update pricing
            updatePricing();
            
            // Reload available rooms
            loadAvailableRooms();
        });
    }
}

// Set up room type change listener
function setupRoomTypeListener() {
    const roomTypeSelect = document.getElementById('room_type');
    if (roomTypeSelect) {
        roomTypeSelect.addEventListener('change', function() {
            updatePricing();
            loadAvailableRooms();
        });
    }
}

// Update pricing calculation
function updatePricing() {
    const checkInDate = document.getElementById('check_in_date').value;
    const checkOutDate = document.getElementById('check_out_date').value;
    const roomType = document.getElementById('room_type').value;
    
    if (!checkInDate || !checkOutDate || !roomType) {
        return;
    }
    
    // Calculate nights
    const nights = Math.ceil((new Date(checkOutDate) - new Date(checkInDate)) / (1000 * 60 * 60 * 24));
    
    // Get room rate
    const roomTypes = {
        'standard': 150.00,
        'deluxe': 250.00,
        'suite': 400.00,
        'presidential': 800.00
    };
    
    const roomRate = roomTypes[roomType] || 0;
    const subtotal = roomRate * nights;
    const tax = subtotal * 0.1; // 10% tax
    const total = subtotal + tax;
    
    // Update display
    document.getElementById('room-rate').textContent = HotelPMS.Utils.formatCurrency(roomRate);
    document.getElementById('nights').textContent = nights;
    document.getElementById('subtotal').textContent = HotelPMS.Utils.formatCurrency(subtotal);
    document.getElementById('tax').textContent = HotelPMS.Utils.formatCurrency(tax);
    document.getElementById('total-amount').textContent = HotelPMS.Utils.formatCurrency(total);
}

// Show reservation success modal
function showReservationSuccess(reservationNumber, reservationId) {
    const modal = document.createElement('div');
    modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
    modal.innerHTML = `
        <div class="bg-white rounded-lg p-8 max-w-md w-full mx-4">
            <div class="text-center">
                <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-check text-green-600 text-2xl"></i>
                </div>
                <h3 class="text-lg font-semibold text-gray-900 mb-2">Reservation Created Successfully!</h3>
                <p class="text-gray-600 mb-4">Reservation number: <strong>${reservationNumber}</strong></p>
                <div class="space-y-2 text-sm text-gray-600">
                    <p>• Guest has been registered in the system</p>
                    <p>• Room has been assigned and reserved</p>
                    <p>• Billing record has been created</p>
                </div>
                <div class="mt-6 space-x-3">
                    <button onclick="window.location.href='view-reservation.php?id=${reservationId}'" 
                            class="px-4 py-2 bg-primary text-white rounded-md hover:bg-primary-dark transition-colors">
                        View Details
                    </button>
                    <button onclick="window.location.href='index.php'" 
                            class="px-4 py-2 border border-gray-300 text-gray-700 rounded-md hover:bg-gray-50 transition-colors">
                        Back to Dashboard
                    </button>
                </div>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    
    // Close modal when clicking outside
    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            modal.remove();
        }
    });
}

// Export functions for use in other modules
window.Reservation = {
    handleReservationSubmit,
    validateForm,
    loadAvailableRooms,
    updatePricing,
    showReservationSuccess
};
