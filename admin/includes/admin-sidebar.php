<?php
// Unified Sidebar for Admin Portal
// This file provides a consistent sidebar across all admin pages
// Usage: include this file and set $sidebar_context before including

// Default context if not set
if (!isset($sidebar_context)) {
    $sidebar_context = 'main'; // 'main' for regular admin pages
}

// Get current page name for active state
if (!isset($current_page)) {
$current_page = basename($_SERVER['PHP_SELF']);
}

// Debug: Log current page for troubleshooting
error_log("Admin Sidebar Debug - Current Page: " . $current_page);
?>

<!-- Sidebar Overlay -->
<div id="sidebarOverlay" class="sidebar-overlay fixed inset-0 bg-black bg-opacity-50 z-40 lg:hidden transition-opacity duration-300 ease-in-out opacity-0 pointer-events-none" onclick="toggleSidebar()"></div>

<!-- Sidebar -->
<div id="sidebar" class="sidebar fixed top-0 left-0 h-full w-64 bg-seait-dark z-50 lg:relative lg:translate-x-0 lg:z-auto transform transition-transform duration-300 ease-in-out -translate-x-full lg:translate-x-0">
    <!-- Sidebar Header -->
    <div class="sidebar-header flex items-center justify-center p-4 border-b border-gray-700 bg-gradient-to-r from-gray-800 to-gray-700">
        <div class="flex items-center transform transition-transform duration-200 hover:scale-105">
            <img src="../assets/images/seait-logo.png" alt="SEAIT Logo" class="h-8 w-auto mr-2 transition-all duration-200 hover:rotate-12">
            <span class="text-white font-semibold">Admin Portal</span>
        </div>
    </div>

    <!-- Navigation Menu - Scrollable Content -->
    <div class="sidebar-content">
        <!-- User Profile Section -->
        <div class="mb-6 p-4 bg-gray-800 rounded-lg mx-3 transform transition-all duration-300 hover:bg-gray-700 hover:scale-105 hover:shadow-lg">
            <div class="flex items-center">
                <div class="h-12 w-12 rounded-full bg-seait-orange flex items-center justify-center mr-3 transition-all duration-300 hover:bg-orange-500 hover:scale-110 hover:shadow-md overflow-hidden">
                    <?php 
                    // Debug: Log session data
                    error_log("Admin Sidebar Debug - Session profile_photo: " . ($_SESSION['profile_photo'] ?? 'NOT SET'));
                    error_log("Admin Sidebar Debug - Session user_id: " . ($_SESSION['user_id'] ?? 'NOT SET'));
                    
                    if (!empty($_SESSION['profile_photo'])): ?>
                        <img src="../<?php echo htmlspecialchars($_SESSION['profile_photo']); ?>" 
                             alt="Profile Photo" 
                             class="w-full h-full rounded-full object-cover">
                    <?php else: ?>
                        <span class="text-white font-semibold text-lg"><?php echo strtoupper(substr($_SESSION['first_name'], 0, 1) . substr($_SESSION['last_name'], 0, 1)); ?></span>
                    <?php endif; ?>
                </div>
                <div class="flex-1">
                    <p class="text-white font-semibold text-sm"><?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?></p>
                    <p class="text-gray-400 text-xs">Administrator</p>
                    <div class="flex items-center mt-1">
                        <div class="w-2 h-2 bg-green-400 rounded-full mr-2 animate-pulse"></div>
                        <span class="text-green-400 text-xs">Online</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="space-y-6">
            <!-- Dashboard Section -->
            <div class="animate-fadeInUp" style="animation-delay: 0.1s;">
                <a href="dashboard.php" class="flex items-center <?php echo $current_page === 'dashboard.php' ? 'bg-seait-orange text-white shadow-lg' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> px-3 py-2 rounded-lg transition-all duration-300 ease-in-out transform hover:scale-105 hover:shadow-md">
                    <i class="fas fa-tachometer-alt mr-3 w-5 text-center transition-transform duration-200 hover:rotate-12"></i>Dashboard
                </a>
            </div>

            <!-- User Management Section -->
            <div class="animate-fadeInUp" style="animation-delay: 0.2s;">
                <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3 px-3">User Management</h3>
                <div class="space-y-1">
                    <a href="users.php" class="flex items-center <?php echo $current_page === 'users.php' ? 'bg-seait-orange text-white shadow-lg' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> px-3 py-2 rounded-lg transition-all duration-300 ease-in-out transform hover:scale-105 hover:shadow-md">
                        <i class="fas fa-users mr-3 w-5 text-center transition-transform duration-200 hover:rotate-12"></i>Users
                    </a>

                    <a href="faculty.php" class="flex items-center <?php echo $current_page === 'faculty.php' ? 'bg-seait-orange text-white shadow-lg' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> px-3 py-2 rounded-lg transition-all duration-300 ease-in-out transform hover:scale-105 hover:shadow-md">
                        <i class="fas fa-chalkboard-teacher mr-3 w-5 text-center transition-transform duration-200 hover:rotate-12"></i>Faculty
                    </a>

                    <a href="students.php" class="flex items-center <?php echo $current_page === 'students.php' ? 'bg-seait-orange text-white shadow-lg' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> px-3 py-2 rounded-lg transition-all duration-300 ease-in-out transform hover:scale-105 hover:shadow-md">
                        <i class="fas fa-user-graduate mr-3 w-5 text-center transition-transform duration-200 hover:rotate-12"></i>Student Registration
                    </a>
                </div>
            </div>

            <!-- Content Management Section -->
            <div class="animate-fadeInUp" style="animation-delay: 0.3s;">
                <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3 px-3">Content Management</h3>
                <div class="space-y-1">
                    <a href="posts.php" class="flex items-center <?php echo $current_page === 'posts.php' ? 'bg-seait-orange text-white shadow-lg' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> px-3 py-2 rounded-lg transition-all duration-300 ease-in-out transform hover:scale-105 hover:shadow-md">
                        <i class="fas fa-newspaper mr-3 w-5 text-center transition-transform duration-200 hover:rotate-12"></i>Posts
                    </a>

                    <a href="programs.php" class="flex items-center <?php echo $current_page === 'programs.php' ? 'bg-seait-orange text-white shadow-lg' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> px-3 py-2 rounded-lg transition-all duration-300 ease-in-out transform hover:scale-105 hover:shadow-md">
                        <i class="fas fa-graduation-cap mr-3 w-5 text-center transition-transform duration-200 hover:rotate-12"></i>Academic Programs
                    </a>

                    <a href="manage-faqs.php" class="flex items-center <?php echo $current_page === 'manage-faqs.php' ? 'bg-seait-orange text-white shadow-lg' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> px-3 py-2 rounded-lg transition-all duration-300 ease-in-out transform hover:scale-105 hover:shadow-md">
                        <i class="fas fa-question-circle mr-3 w-5 text-center transition-transform duration-200 hover:rotate-12"></i>FAQ Management
                    </a>

                    <a href="manage-services.php" class="flex items-center <?php echo $current_page === 'manage-services.php' ? 'bg-seait-orange text-white shadow-lg' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> px-3 py-2 rounded-lg transition-all duration-300 ease-in-out transform hover:scale-105 hover:shadow-md">
                        <i class="fas fa-cogs mr-3 w-5 text-center transition-transform duration-200 hover:rotate-12"></i>Services Management
                    </a>

                    <a href="manage-teacher-availability.php" class="flex items-center <?php echo $current_page === 'manage-teacher-availability.php' ? 'bg-seait-orange text-white shadow-lg' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> px-3 py-2 rounded-lg transition-all duration-300 ease-in-out transform hover:scale-105 hover:shadow-md">
                        <i class="fas fa-calendar-check mr-3 w-5 text-center transition-transform duration-200 hover:rotate-12"></i>Teacher Availability
                    </a>
                </div>
            </div>

            <!-- Communication Section -->
            <div class="animate-fadeInUp" style="animation-delay: 0.4s;">
                <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3 px-3">Communication</h3>
                <div class="space-y-1">
                    <a href="inquiries.php" class="flex items-center <?php echo $current_page === 'inquiries.php' ? 'bg-seait-orange text-white shadow-lg' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> px-3 py-2 rounded-lg transition-all duration-300 ease-in-out transform hover:scale-105 hover:shadow-md">
                        <i class="fas fa-comments mr-3 w-5 text-center transition-transform duration-200 hover:rotate-12"></i>View Inquiries
                    </a>
                </div>
            </div>

            <!-- Reports & Analytics Section -->
            <div class="animate-fadeInUp" style="animation-delay: 0.5s;">
                <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3 px-3">Reports & Analytics</h3>
                <div class="space-y-1">
                    <a href="reports.php" class="flex items-center <?php echo $current_page === 'reports.php' ? 'bg-seait-orange text-white shadow-lg' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> px-3 py-2 rounded-lg transition-all duration-300 ease-in-out transform hover:scale-105 hover:shadow-md">
                        <i class="fas fa-chart-bar mr-3 w-5 text-center transition-transform duration-200 hover:rotate-12"></i>Reports
                    </a>

                    <a href="reports_enhanced.php" class="flex items-center <?php echo $current_page === 'reports_enhanced.php' ? 'bg-seait-orange text-white shadow-lg' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> px-3 py-2 rounded-lg transition-all duration-300 ease-in-out transform hover:scale-105 hover:shadow-md">
                        <i class="fas fa-chart-line mr-3 w-5 text-center transition-transform duration-200 hover:rotate-12"></i>Enhanced Reports
                    </a>

                    <a href="export_reports.php" class="flex items-center <?php echo $current_page === 'export_reports.php' ? 'bg-seait-orange text-white shadow-lg' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> px-3 py-2 rounded-lg transition-all duration-300 ease-in-out transform hover:scale-105 hover:shadow-md">
                        <i class="fas fa-file-export mr-3 w-5 text-center transition-transform duration-200 hover:rotate-12"></i>Export Reports
                    </a>

                    <a href="export_excel_reports.php" class="flex items-center <?php echo $current_page === 'export_excel_reports.php' ? 'bg-seait-orange text-white shadow-lg' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> px-3 py-2 rounded-lg transition-all duration-300 ease-in-out transform hover:scale-105 hover:shadow-md">
                        <i class="fas fa-file-excel mr-3 w-5 text-center transition-transform duration-200 hover:rotate-12"></i>Excel Reports
                    </a>
                </div>
            </div>

            <!-- System Management Section -->
            <div class="animate-fadeInUp" style="animation-delay: 0.6s;">
                <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3 px-3">System</h3>
                <div class="space-y-1">
                    <a href="database-sync.php" class="flex items-center <?php echo $current_page === 'database-sync.php' ? 'bg-seait-orange text-white shadow-lg' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> px-3 py-2 rounded-lg transition-all duration-300 ease-in-out transform hover:scale-105 hover:shadow-md">
                        <i class="fas fa-sync-alt mr-3 w-5 text-center transition-transform duration-200 hover:rotate-12"></i>Database Sync
                    </a>

                    <a href="ftp-manager.php" class="flex items-center <?php echo $current_page === 'ftp-manager.php' ? 'bg-seait-orange text-white shadow-lg' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> px-3 py-2 rounded-lg transition-all duration-300 ease-in-out transform hover:scale-105 hover:shadow-md">
                        <i class="fas fa-server mr-3 w-5 text-center transition-transform duration-200 hover:rotate-12"></i>FTP Manager
                    </a>

                    <a href="error-logs.php" class="flex items-center <?php echo $current_page === 'error-logs.php' ? 'bg-seait-orange text-white shadow-lg' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> px-3 py-2 rounded-lg transition-all duration-300 ease-in-out transform hover:scale-105 hover:shadow-md">
                        <i class="fas fa-exclamation-triangle mr-3 w-5 text-center transition-transform duration-200 hover:rotate-12"></i>Error Logs
                    </a>

                    <a href="settings.php" class="flex items-center <?php echo $current_page === 'settings.php' ? 'bg-seait-orange text-white shadow-lg' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> px-3 py-2 rounded-lg transition-all duration-300 ease-in-out transform hover:scale-105 hover:shadow-md">
                        <i class="fas fa-cog mr-3 w-5 text-center transition-transform duration-200 hover:rotate-12"></i>Settings
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Sidebar Footer -->
    <div class="sidebar-footer p-4 border-t border-gray-700">
        <a href="logout.php" class="flex items-center justify-center w-full px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-all duration-300 ease-in-out transform hover:scale-105 hover:shadow-md">
            <i class="fas fa-sign-out-alt mr-2"></i>Logout
        </a>
    </div>
</div>

<script>
// Sidebar functionality
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    
    if (sidebar && overlay) {
        sidebar.classList.toggle('open');
        overlay.classList.toggle('open');
        
        // Update overlay pointer events
        if (overlay.classList.contains('open')) {
            overlay.style.pointerEvents = 'auto';
        } else {
            overlay.style.pointerEvents = 'none';
        }
    }
}

// Close sidebar when clicking on a link (mobile)
document.addEventListener('DOMContentLoaded', function() {
    const sidebarLinks = document.querySelectorAll('#sidebar a');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');

    // Ensure sidebar is in correct state on load
    if (sidebar) {
        if (window.innerWidth >= 1024) {
            sidebar.classList.remove('open');
            if (overlay) overlay.classList.remove('open');
        }
    }

    // Handle link clicks
    sidebarLinks.forEach(link => {
        link.addEventListener('click', function() {
            // Close sidebar on mobile after link click
            if (window.innerWidth < 1024) {
                sidebar.classList.remove('open');
                if (overlay) overlay.classList.remove('open');
            }
        });
    });

    // Handle overlay clicks
    if (overlay) {
        overlay.addEventListener('click', function() {
            if (window.innerWidth < 1024) {
                toggleSidebar();
            }
        });
    }
});

// Handle window resize
window.addEventListener('resize', function() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    
    if (window.innerWidth >= 1024) {
        if (sidebar) sidebar.classList.remove('open');
        if (overlay) overlay.classList.remove('open');
    }
});
</script>