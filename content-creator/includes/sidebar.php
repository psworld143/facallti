<?php
// Unified Sidebar for Content Creator Portal
// This file provides a consistent sidebar across all content creator pages
// Usage: include this file and set $sidebar_context before including

// Default context if not set
if (!isset($sidebar_context)) {
    $sidebar_context = 'main'; // 'main' for regular content creator pages
}

// Get current page name for active state
if (!isset($current_page)) {
$current_page = basename($_SERVER['PHP_SELF']);
}

// Debug: Log current page for troubleshooting
error_log("Sidebar Debug - Current Page: " . $current_page);
?>

<!-- Sidebar Overlay -->
<div id="sidebarOverlay" class="sidebar-overlay fixed inset-0 bg-black bg-opacity-50 z-40 lg:hidden transition-opacity duration-300 ease-in-out opacity-0 pointer-events-none" onclick="toggleSidebar()"></div>

<!-- Sidebar -->
<div id="sidebar" class="sidebar fixed top-0 left-0 h-full w-64 bg-seait-dark z-50 lg:relative lg:translate-x-0 lg:z-auto transform transition-transform duration-300 ease-in-out -translate-x-full lg:translate-x-0">
    <!-- Sidebar Header -->
    <div class="sidebar-header flex items-center justify-center p-4 border-b border-gray-700 bg-gradient-to-r from-gray-800 to-gray-700">
        <div class="flex items-center transform transition-transform duration-200 hover:scale-105">
            <img src="../assets/images/seait-logo.png" alt="SEAIT Logo" class="h-8 w-auto mr-2 transition-all duration-200 hover:rotate-12">
            <span class="text-white font-semibold">Content Creator Portal</span>
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
                    error_log("Content Creator Sidebar Debug - Session profile_photo: " . ($_SESSION['profile_photo'] ?? 'NOT SET'));
                    error_log("Content Creator Sidebar Debug - Session user_id: " . ($_SESSION['user_id'] ?? 'NOT SET'));
                    
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
                    <p class="text-gray-400 text-xs">Content Creator</p>
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

                    <!-- Content Management Section -->
            <div class="animate-fadeInUp" style="animation-delay: 0.2s;">
                <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3 px-3">Content Management</h3>
                <div class="space-y-1">
                    <a href="create-post.php" class="flex items-center <?php echo $current_page === 'create-post.php' ? 'bg-seait-orange text-white shadow-lg' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> px-3 py-2 rounded-lg transition-all duration-300 ease-in-out transform hover:scale-105 hover:shadow-md">
                        <i class="fas fa-plus mr-3 w-5 text-center transition-transform duration-200 hover:rotate-12"></i>Create New Post
                    </a>

                    <a href="my-posts.php" class="flex items-center <?php echo $current_page === 'my-posts.php' ? 'bg-seait-orange text-white shadow-lg' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> px-3 py-2 rounded-lg transition-all duration-300 ease-in-out transform hover:scale-105 hover:shadow-md">
                        <i class="fas fa-newspaper mr-3 w-5 text-center transition-transform duration-200 hover:rotate-12"></i>My Posts
                    </a>

                    <a href="drafts.php" class="flex items-center <?php echo $current_page === 'drafts.php' ? 'bg-seait-orange text-white shadow-lg' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> px-3 py-2 rounded-lg transition-all duration-300 ease-in-out transform hover:scale-105 hover:shadow-md">
                        <i class="fas fa-edit mr-3 w-5 text-center transition-transform duration-200 hover:rotate-12"></i>Drafts
                            </a>
                        </div>
                    </div>

                    <!-- Website Management Section -->
            <div class="animate-fadeInUp" style="animation-delay: 0.3s;">
                <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3 px-3">Website Management</h3>
                <div class="space-y-1">
                    <a href="manage-carousel.php" class="flex items-center <?php echo $current_page === 'manage-carousel.php' ? 'bg-seait-orange text-white shadow-lg' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> px-3 py-2 rounded-lg transition-all duration-300 ease-in-out transform hover:scale-105 hover:shadow-md">
                        <i class="fas fa-images mr-3 w-5 text-center transition-transform duration-200 hover:rotate-12"></i>Manage Carousel
                    </a>

                    <a href="manage-colleges.php" class="flex items-center <?php echo $current_page === 'manage-colleges.php' ? 'bg-seait-orange text-white shadow-lg' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> px-3 py-2 rounded-lg transition-all duration-300 ease-in-out transform hover:scale-105 hover:shadow-md">
                        <i class="fas fa-university mr-3 w-5 text-center transition-transform duration-200 hover:rotate-12"></i>Manage Colleges
                    </a>

                    <a href="manage-course-details.php" class="flex items-center <?php echo $current_page === 'manage-course-details.php' ? 'bg-seait-orange text-white shadow-lg' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> px-3 py-2 rounded-lg transition-all duration-300 ease-in-out transform hover:scale-105 hover:shadow-md">
                        <i class="fas fa-graduation-cap mr-3 w-5 text-center transition-transform duration-200 hover:rotate-12"></i>Manage Course Details
                    </a>

                    <a href="manage-board-directors.php" class="flex items-center <?php echo $current_page === 'manage-board-directors.php' ? 'bg-seait-orange text-white shadow-lg' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> px-3 py-2 rounded-lg transition-all duration-300 ease-in-out transform hover:scale-105 hover:shadow-md">
                        <i class="fas fa-user-tie mr-3 w-5 text-center transition-transform duration-200 hover:rotate-12"></i>Board Directors
                    </a>

                    <a href="manage-admissions.php" class="flex items-center <?php echo $current_page === 'manage-admissions.php' ? 'bg-seait-orange text-white shadow-lg' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> px-3 py-2 rounded-lg transition-all duration-300 ease-in-out transform hover:scale-105 hover:shadow-md">
                        <i class="fas fa-user-graduate mr-3 w-5 text-center transition-transform duration-200 hover:rotate-12"></i>Manage Admissions
                    </a>

                    <a href="manage-research.php" class="flex items-center <?php echo $current_page === 'manage-research.php' ? 'bg-seait-orange text-white shadow-lg' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> px-3 py-2 rounded-lg transition-all duration-300 ease-in-out transform hover:scale-105 hover:shadow-md">
                        <i class="fas fa-microscope mr-3 w-5 text-center transition-transform duration-200 hover:rotate-12"></i>Manage Research
                    </a>

                    <a href="manage-history.php" class="flex items-center <?php echo $current_page === 'manage-history.php' ? 'bg-seait-orange text-white shadow-lg' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> px-3 py-2 rounded-lg transition-all duration-300 ease-in-out transform hover:scale-105 hover:shadow-md">
                        <i class="fas fa-history mr-3 w-5 text-center transition-transform duration-200 hover:rotate-12"></i>Manage History
                    </a>

                    <a href="manage-contacts.php" class="flex items-center <?php echo $current_page === 'manage-contacts.php' ? 'bg-seait-orange text-white shadow-lg' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> px-3 py-2 rounded-lg transition-all duration-300 ease-in-out transform hover:scale-105 hover:shadow-md">
                        <i class="fas fa-address-book mr-3 w-5 text-center transition-transform duration-200 hover:rotate-12"></i>Manage Contacts
                            </a>
                        </div>
                    </div>

                    <!-- Account Section -->
            <div class="animate-fadeInUp" style="animation-delay: 0.4s;">
                <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3 px-3">Account</h3>
                <div class="space-y-1">
                    <a href="profile.php" class="flex items-center <?php echo $current_page === 'profile.php' ? 'bg-seait-orange text-white shadow-lg' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> px-3 py-2 rounded-lg transition-all duration-300 ease-in-out transform hover:scale-105 hover:shadow-md">
                        <i class="fas fa-user mr-3 w-5 text-center transition-transform duration-200 hover:rotate-12"></i>Profile
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