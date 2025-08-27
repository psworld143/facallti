// Modify Reservation JavaScript
document.addEventListener('DOMContentLoaded', function() {
    initializeModifyReservation();
    loadOverbookingStatus();
    loadGroupBookingInfo();
});

function initializeModifyReservation() {
    // Initialize form handlers
    document.getElementById('basic-info-form').addEventListener('submit', handleBasicInfoUpdate);
    document.getElementById('room-transfer-form').addEventListener('submit', handleRoomTransfer);
    document.getElementById('room-upgrade-form').addEventListener('submit', handleRoomUpgrade);
    document.getElementById('group-booking-form').addEventListener('submit', handleGroupBooking);
    
    // Initialize room type change listener
    document.getElementById('new-room-type').addEventListener('change', loadAvailableRooms);
    document.getElementById('upgrade-room-type').addEventListener('change', updateUpgradePricing);
    
    // Initialize date change listeners for pricing updates
    document.querySelectorAll('input[name="check_in_date"], input[name="check_out_date"]').forEach(input => {
        input.addEventListener('change', updatePricingCalculation);
    });
}

// Handle basic information update
function handleBasicInfoUpdate(e) {
    e.preventDefault();
    
    if (!validateBasicInfoForm()) {
        HotelPMS.Utils.showNotification('Please fill in all required fields', 'warning');
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
            HotelPMS.Utils.showNotification('Reservation updated successfully!', 'success');
            // Reload page to show updated information
            setTimeout(() => window.location.reload(), 1500);
        } else {
            HotelPMS.Utils.showNotification(result.message || 'Error updating reservation', 'error');
        }
    })
    .catch(error => {
        console.error('Error updating reservation:', error);
        HotelPMS.Utils.showNotification('Error updating reservation', 'error');
    })
    .finally(() => {
        // Reset button state
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    });
}

// Handle room transfer
function handleRoomTransfer(e) {
    e.preventDefault();
    
    if (!validateRoomTransferForm()) {
        HotelPMS.Utils.showNotification('Please fill in all required fields', 'warning');
        return;
    }
    
    const formData = new FormData(e.target);
    const data = Object.fromEntries(formData.entries());
    
    // Show loading state
    const submitBtn = e.target.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Transferring...';
    
    // Submit transfer
    fetch('../../api/transfer-room.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            HotelPMS.Utils.showNotification('Room transferred successfully!', 'success');
            setTimeout(() => window.location.reload(), 1500);
        } else {
            HotelPMS.Utils.showNotification(result.message || 'Error transferring room', 'error');
        }
    })
    .catch(error => {
        console.error('Error transferring room:', error);
        HotelPMS.Utils.showNotification('Error transferring room', 'error');
    })
    .finally(() => {
        // Reset button state
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    });
}

// Handle room upgrade
function handleRoomUpgrade(e) {
    e.preventDefault();
    
    if (!validateRoomUpgradeForm()) {
        HotelPMS.Utils.showNotification('Please fill in all required fields', 'warning');
        return;
    }
    
    const formData = new FormData(e.target);
    const data = Object.fromEntries(formData.entries());
    
    // Show loading state
    const submitBtn = e.target.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Upgrading...';
    
    // Submit upgrade
    fetch('../../api/upgrade-room.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            HotelPMS.Utils.showNotification('Room upgraded successfully!', 'success');
            setTimeout(() => window.location.reload(), 1500);
        } else {
            HotelPMS.Utils.showNotification(result.message || 'Error upgrading room', 'error');
        }
    })
    .catch(error => {
        console.error('Error upgrading room:', error);
        HotelPMS.Utils.showNotification('Error upgrading room', 'error');
    })
    .finally(() => {
        // Reset button state
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    });
}

// Handle group booking
function handleGroupBooking(e) {
    e.preventDefault();
    
    if (!validateGroupBookingForm()) {
        HotelPMS.Utils.showNotification('Please fill in all required fields', 'warning');
        return;
    }
    
    const formData = new FormData(e.target);
    const data = Object.fromEntries(formData.entries());
    
    // Show loading state
    const submitBtn = e.target.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Adding to Group...';
    
    // Submit group booking
    fetch('../../api/add-to-group-booking.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            HotelPMS.Utils.showNotification('Added to group booking successfully!', 'success');
            closeGroupBookingModal();
            loadGroupBookingInfo();
        } else {
            HotelPMS.Utils.showNotification(result.message || 'Error adding to group booking', 'error');
        }
    })
    .catch(error => {
        console.error('Error adding to group booking:', error);
        HotelPMS.Utils.showNotification('Error adding to group booking', 'error');
    })
    .finally(() => {
        // Reset button state
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    });
}

// Load available rooms for transfer
function loadAvailableRooms() {
    const roomType = document.getElementById('new-room-type').value;
    const roomSelect = document.getElementById('new-room-select');
    
    if (!roomType) {
        roomSelect.innerHTML = '<option value="">Auto-assign best available</option>';
        return;
    }
    
    // Show loading
    roomSelect.innerHTML = '<option value="">Loading available rooms...</option>';
    
    fetch(`../../api/get-available-rooms.php?room_type=${roomType}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                let options = '<option value="">Auto-assign best available</option>';
                data.rooms.forEach(room => {
                    options += `<option value="${room.id}">${room.room_number} - ${room.room_type} ($${room.rate}/night)</option>`;
                });
                roomSelect.innerHTML = options;
            } else {
                roomSelect.innerHTML = '<option value="">Error loading rooms</option>';
            }
        })
        .catch(error => {
            console.error('Error loading available rooms:', error);
            roomSelect.innerHTML = '<option value="">Error loading rooms</option>';
        });
}

// Update upgrade pricing display
function updateUpgradePricing() {
    const upgradeType = document.getElementById('upgrade-room-type').value;
    const chargeUpgrade = document.getElementById('charge-upgrade');
    
    if (upgradeType) {
        // Get current room rate and new room rate
        const currentRate = getCurrentRoomRate();
        const newRate = getRoomTypeRate(upgradeType);
        const priceDifference = newRate - currentRate;
        
        if (priceDifference > 0) {
            chargeUpgrade.checked = true;
            chargeUpgrade.disabled = false;
        } else {
            chargeUpgrade.checked = false;
            chargeUpgrade.disabled = true;
        }
    }
}

// Update pricing calculation when dates change
function updatePricingCalculation() {
    const checkInDate = document.querySelector('input[name="check_in_date"]').value;
    const checkOutDate = document.querySelector('input[name="check_out_date"]').value;
    
    if (checkInDate && checkOutDate) {
        const nights = Math.ceil((new Date(checkOutDate) - new Date(checkInDate)) / (1000 * 60 * 60 * 24));
        const currentRate = getCurrentRoomRate();
        const total = currentRate * nights * 1.1; // 10% tax
        
        // Update pricing display if it exists
        const pricingDisplay = document.querySelector('.pricing-display');
        if (pricingDisplay) {
            pricingDisplay.innerHTML = `
                <div class="text-sm text-gray-600">
                    <div class="flex justify-between">
                        <span>Room Rate:</span>
                        <span>$${currentRate.toFixed(2)}/night</span>
                    </div>
                    <div class="flex justify-between">
                        <span>Nights:</span>
                        <span>${nights}</span>
                    </div>
                    <div class="flex justify-between">
                        <span>Subtotal:</span>
                        <span>$${(currentRate * nights).toFixed(2)}</span>
                    </div>
                    <div class="flex justify-between">
                        <span>Tax (10%):</span>
                        <span>$${(currentRate * nights * 0.1).toFixed(2)}</span>
                    </div>
                    <div class="flex justify-between font-medium">
                        <span>Total:</span>
                        <span>$${total.toFixed(2)}</span>
                    </div>
                </div>
            `;
        }
    }
}

// Load overbooking status
function loadOverbookingStatus() {
    const reservationId = new URLSearchParams(window.location.search).get('id');
    
    fetch(`../../api/check-overbooking.php?reservation_id=${reservationId}`)
        .then(response => response.json())
        .then(data => {
            const statusContainer = document.getElementById('overbooking-status');
            const actionsContainer = document.getElementById('overbooking-actions');
            
            if (data.is_overbooked) {
                statusContainer.innerHTML = `
                    <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                        <div class="flex items-center">
                            <i class="fas fa-exclamation-triangle text-red-600 mr-3"></i>
                            <div>
                                <h4 class="font-medium text-red-900">Overbooking Detected</h4>
                                <p class="text-sm text-red-700">${data.message}</p>
                            </div>
                        </div>
                    </div>
                `;
                actionsContainer.classList.remove('hidden');
            } else {
                statusContainer.innerHTML = `
                    <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                        <div class="flex items-center">
                            <i class="fas fa-check-circle text-green-600 mr-3"></i>
                            <div>
                                <h4 class="font-medium text-green-900">No Overbooking Issues</h4>
                                <p class="text-sm text-green-700">This reservation is properly accommodated.</p>
                            </div>
                        </div>
                    </div>
                `;
                actionsContainer.classList.add('hidden');
            }
        })
        .catch(error => {
            console.error('Error checking overbooking status:', error);
        });
}

// Handle overbooking resolution
function handleOverbooking(action) {
    const reservationId = new URLSearchParams(window.location.search).get('id');
    
    let actionText = '';
    switch(action) {
        case 'walk':
            actionText = 'walk guest';
            break;
        case 'upgrade':
            actionText = 'upgrade guest';
            break;
        case 'compensation':
            actionText = 'offer compensation';
            break;
    }
    
    if (confirm(`Are you sure you want to ${actionText}?`)) {
        fetch('../../api/resolve-overbooking.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                reservation_id: reservationId,
                action: action
            })
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                HotelPMS.Utils.showNotification(`Overbooking resolved: ${actionText}`, 'success');
                loadOverbookingStatus();
            } else {
                HotelPMS.Utils.showNotification(result.message || 'Error resolving overbooking', 'error');
            }
        })
        .catch(error => {
            console.error('Error resolving overbooking:', error);
            HotelPMS.Utils.showNotification('Error resolving overbooking', 'error');
        });
    }
}

// Load group booking information
function loadGroupBookingInfo() {
    const reservationId = new URLSearchParams(window.location.search).get('id');
    
    fetch(`../../api/get-group-booking-info.php?reservation_id=${reservationId}`)
        .then(response => response.json())
        .then(data => {
            const infoContainer = document.getElementById('group-booking-info');
            
            if (data.is_group_booking) {
                document.getElementById('group-name').textContent = data.group_name;
                document.getElementById('group-size').textContent = data.group_size;
                document.getElementById('group-discount').textContent = data.group_discount + '%';
                infoContainer.classList.remove('hidden');
            } else {
                infoContainer.classList.add('hidden');
            }
        })
        .catch(error => {
            console.error('Error loading group booking info:', error);
        });
}

// Show group booking modal
function showGroupBookingModal() {
    document.getElementById('group-booking-modal').classList.remove('hidden');
}

// Close group booking modal
function closeGroupBookingModal() {
    document.getElementById('group-booking-modal').classList.add('hidden');
    document.getElementById('group-booking-form').reset();
}

// Show cancel modal
function showCancelModal() {
    document.getElementById('cancel-modal').classList.remove('hidden');
}

// Close cancel modal
function closeCancelModal() {
    document.getElementById('cancel-modal').classList.add('hidden');
}

// Confirm cancel reservation
function confirmCancelReservation() {
    const reservationId = new URLSearchParams(window.location.search).get('id');
    
    fetch('../../api/cancel-reservation.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            reservation_id: reservationId
        })
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            HotelPMS.Utils.showNotification('Reservation cancelled successfully!', 'success');
            closeCancelModal();
            setTimeout(() => window.location.href = 'manage-reservations.php', 1500);
        } else {
            HotelPMS.Utils.showNotification(result.message || 'Error cancelling reservation', 'error');
        }
    })
    .catch(error => {
        console.error('Error cancelling reservation:', error);
        HotelPMS.Utils.showNotification('Error cancelling reservation', 'error');
    });
}

// Print reservation
function printReservation() {
    const reservationId = new URLSearchParams(window.location.search).get('id');
    window.open(`print-reservation.php?id=${reservationId}`, '_blank');
}

// Validation functions
function validateBasicInfoForm() {
    const requiredFields = ['first_name', 'last_name', 'phone', 'check_in_date', 'check_out_date', 'adults'];
    
    for (const field of requiredFields) {
        const input = document.querySelector(`[name="${field}"]`);
        if (!input || !input.value.trim()) {
            return false;
        }
    }
    
    // Validate dates
    const checkInDate = new Date(document.querySelector('[name="check_in_date"]').value);
    const checkOutDate = new Date(document.querySelector('[name="check_out_date"]').value);
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    
    if (checkInDate < today) {
        HotelPMS.Utils.showNotification('Check-in date cannot be in the past', 'error');
        return false;
    }
    
    if (checkOutDate <= checkInDate) {
        HotelPMS.Utils.showNotification('Check-out date must be after check-in date', 'error');
        return false;
    }
    
    return true;
}

function validateRoomTransferForm() {
    const roomType = document.getElementById('new-room-type').value;
    if (!roomType) {
        HotelPMS.Utils.showNotification('Please select a room type', 'error');
        return false;
    }
    return true;
}

function validateRoomUpgradeForm() {
    const upgradeType = document.getElementById('upgrade-room-type').value;
    if (!upgradeType) {
        HotelPMS.Utils.showNotification('Please select an upgrade option', 'error');
        return false;
    }
    return true;
}

function validateGroupBookingForm() {
    const groupName = document.querySelector('[name="group_name"]').value;
    const groupSize = document.querySelector('[name="group_size"]').value;
    
    if (!groupName.trim()) {
        HotelPMS.Utils.showNotification('Please enter a group name', 'error');
        return false;
    }
    
    if (!groupSize || groupSize < 2) {
        HotelPMS.Utils.showNotification('Group size must be at least 2', 'error');
        return false;
    }
    
    return true;
}

// Helper functions
function getCurrentRoomRate() {
    // This would typically come from the current reservation data
    // For now, we'll use a default value
    return 150.00;
}

function getRoomTypeRate(roomType) {
    const rates = {
        'standard': 150.00,
        'deluxe': 250.00,
        'suite': 400.00,
        'presidential': 800.00
    };
    return rates[roomType] || 150.00;
}
