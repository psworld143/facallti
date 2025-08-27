// Room Status JavaScript for Housekeeping Module
document.addEventListener('DOMContentLoaded', function() {
    initializeRoomStatus();
    updateDateTime();
    setInterval(updateDateTime, 1000);
});

function initializeRoomStatus() {
    // Initialize filters
    document.getElementById('status-filter').addEventListener('change', filterRooms);
    document.getElementById('housekeeping-filter').addEventListener('change', filterRooms);
    document.getElementById('search-room').addEventListener('input', filterRooms);
    
    // Initialize form handlers
    document.getElementById('update-status-form').addEventListener('submit', handleUpdateStatus);
    document.getElementById('maintenance-form').addEventListener('submit', handleMaintenanceRequest);
}

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

function filterRooms() {
    const statusFilter = document.getElementById('status-filter').value;
    const housekeepingFilter = document.getElementById('housekeeping-filter').value;
    const searchTerm = document.getElementById('search-room').value.toLowerCase();
    
    const rows = document.querySelectorAll('#rooms-table-body tr');
    
    rows.forEach(row => {
        const roomNumber = row.querySelector('td:first-child').textContent.toLowerCase();
        const status = row.querySelector('td:nth-child(3) span').textContent.toLowerCase();
        const housekeepingStatus = row.querySelector('td:nth-child(4) span').textContent.toLowerCase();
        
        const matchesStatus = !statusFilter || status.includes(statusFilter);
        const matchesHousekeeping = !housekeepingFilter || housekeepingStatus.includes(housekeepingFilter);
        const matchesSearch = !searchTerm || roomNumber.includes(searchTerm);
        
        if (matchesStatus && matchesHousekeeping && matchesSearch) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

function updateHousekeepingStatus(roomId) {
    // Get room details from the table row
    const row = document.querySelector(`tr[data-room-id="${roomId}"]`);
    const roomNumber = row.querySelector('td:first-child div').textContent;
    
    // Populate modal
    document.getElementById('room_id').value = roomId;
    document.getElementById('room_number_display').value = roomNumber;
    
    // Show modal
    document.getElementById('update-status-modal').classList.remove('hidden');
}

function closeUpdateStatusModal() {
    document.getElementById('update-status-modal').classList.add('hidden');
    document.getElementById('update-status-form').reset();
}

function handleUpdateStatus(e) {
    e.preventDefault();
    
    const formData = new FormData(e.target);
    const roomId = formData.get('room_id');
    const housekeepingStatus = formData.get('housekeeping_status');
    const notes = formData.get('notes');
    
    // Show loading state
    const submitBtn = e.target.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Updating...';
    submitBtn.disabled = true;
    
    fetch('../../api/update-room-housekeeping-status.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            HotelPMS.Utils.showNotification('Room status updated successfully!', 'success');
            closeUpdateStatusModal();
            // Reload the page to reflect changes
            setTimeout(() => location.reload(), 1000);
        } else {
            HotelPMS.Utils.showNotification(data.message || 'Error updating room status', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        HotelPMS.Utils.showNotification('Error updating room status', 'error');
    })
    .finally(() => {
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    });
}

function createMaintenanceRequest(roomId) {
    // Get room details from the table row
    const row = document.querySelector(`tr[data-room-id="${roomId}"]`);
    const roomNumber = row.querySelector('td:first-child div').textContent;
    
    // Populate modal
    document.getElementById('maintenance_room_id').value = roomId;
    document.getElementById('maintenance_room_number').value = roomNumber;
    
    // Show modal
    document.getElementById('maintenance-modal').classList.remove('hidden');
}

function closeMaintenanceModal() {
    document.getElementById('maintenance-modal').classList.add('hidden');
    document.getElementById('maintenance-form').reset();
}

function handleMaintenanceRequest(e) {
    e.preventDefault();
    
    const formData = new FormData(e.target);
    
    // Show loading state
    const submitBtn = e.target.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Creating...';
    submitBtn.disabled = true;
    
    fetch('../../api/create-maintenance-request.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            HotelPMS.Utils.showNotification('Maintenance request created successfully!', 'success');
            closeMaintenanceModal();
        } else {
            HotelPMS.Utils.showNotification(data.message || 'Error creating maintenance request', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        HotelPMS.Utils.showNotification('Error creating maintenance request', 'error');
    })
    .finally(() => {
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    });
}

function viewRoomDetails(roomId) {
    // Open room details in a new window or modal
    window.open(`../../api/get-room-details.php?id=${roomId}`, '_blank');
}

// Export functions for global access
window.updateHousekeepingStatus = updateHousekeepingStatus;
window.closeUpdateStatusModal = closeUpdateStatusModal;
window.createMaintenanceRequest = createMaintenanceRequest;
window.closeMaintenanceModal = closeMaintenanceModal;
window.viewRoomDetails = viewRoomDetails;
