// Front Desk Room Status JavaScript
document.addEventListener('DOMContentLoaded', function() {
    initializeFrontDeskRoomStatus();
    updateDateTime();
    setInterval(updateDateTime, 1000);
});

function initializeFrontDeskRoomStatus() {
    // Initialize filters
    document.getElementById('status-filter').addEventListener('change', filterRooms);
    document.getElementById('search-room').addEventListener('input', filterRooms);
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
    const searchTerm = document.getElementById('search-room').value.toLowerCase();
    
    const rows = document.querySelectorAll('#rooms-table-body tr');
    
    rows.forEach(row => {
        const roomNumber = row.querySelector('td:first-child').textContent.toLowerCase();
        const status = row.querySelector('td:nth-child(3) span').textContent.toLowerCase();
        
        const matchesStatus = !statusFilter || status.includes(statusFilter);
        const matchesSearch = !searchTerm || roomNumber.includes(searchTerm);
        
        if (matchesStatus && matchesSearch) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

function viewRoomDetails(roomId) {
    // Open room details in a new window or modal
    window.open(`../../api/get-room-details.php?id=${roomId}`, '_blank');
}

function assignRoom(roomId) {
    // Redirect to the reservation page with the room pre-selected
    window.location.href = `new-reservation.php?room_id=${roomId}`;
}

function createMaintenanceRequest(roomId) {
    // Get room details from the table row
    const row = document.querySelector(`tr[data-room-id="${roomId}"]`);
    const roomNumber = row.querySelector('td:first-child div').textContent;
    
    // Show a simple notification for now
    Utils.showNotification(`Maintenance request feature for room ${roomNumber} coming soon!`, 'info');
}

// Export functions for global access
window.viewRoomDetails = viewRoomDetails;
window.assignRoom = assignRoom;
window.createMaintenanceRequest = createMaintenanceRequest;
