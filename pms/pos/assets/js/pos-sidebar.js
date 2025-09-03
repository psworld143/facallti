/**
 * POS Sidebar JavaScript Functions
 * Handles sidebar interactions, dropdowns, and mobile responsiveness
 */

// Sidebar functionality - matching booking system
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebar-overlay');
    
    if (sidebar.classList.contains('-translate-x-full')) {
        sidebar.classList.remove('-translate-x-full');
        overlay.classList.remove('hidden');
    } else {
        sidebar.classList.add('-translate-x-full');
        overlay.classList.add('hidden');
    }
}

function closeSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebar-overlay');
    
    sidebar.classList.add('-translate-x-full');
    overlay.classList.add('hidden');
}

// Submenu functionality
function toggleSubmenu(menuKey) {
    const submenu = document.getElementById(`submenu-${menuKey}`);
    const chevron = document.getElementById(`chevron-${menuKey}`);
    
    if (submenu.classList.contains('hidden')) {
        submenu.classList.remove('hidden');
        chevron.style.transform = 'rotate(180deg)';
    } else {
        submenu.classList.add('hidden');
        chevron.style.transform = 'rotate(0deg)';
    }
}

// User dropdown functionality
function toggleUserDropdown() {
    const dropdown = document.getElementById('user-dropdown');
    dropdown.classList.toggle('hidden');
}

// Notifications functionality
function toggleNotifications() {
    const dropdown = document.getElementById('notifications-dropdown');
    dropdown.classList.toggle('hidden');
}

// Close dropdowns when clicking outside
function setupDropdownHandlers() {
    document.addEventListener('click', function(event) {
        const userDropdown = document.getElementById('user-dropdown');
        const notificationsDropdown = document.getElementById('notifications-dropdown');
        
        if (!event.target.closest('#user-menu-toggle')) {
            if (userDropdown) userDropdown.classList.add('hidden');
        }
        
        if (!event.target.closest('#notifications-toggle')) {
            if (notificationsDropdown) notificationsDropdown.classList.add('hidden');
        }
    });
}

// Initialize sidebar functionality
function initializePOSSidebar() {
    // Sidebar toggle event listener
    const sidebarToggle = document.getElementById('sidebar-toggle');
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', toggleSidebar);
    }
    
    // Setup dropdown handlers
    setupDropdownHandlers();
    
    // Initialize any other sidebar functionality
    console.log('POS Sidebar initialized successfully');
}

// Auto-initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    initializePOSSidebar();
});

// Export functions for global use
window.toggleSidebar = toggleSidebar;
window.closeSidebar = closeSidebar;
window.toggleSubmenu = toggleSubmenu;
window.toggleUserDropdown = toggleUserDropdown;
window.toggleNotifications = toggleNotifications;
