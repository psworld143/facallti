<?php
// Unified Sidebar for Student Portal
// This file handles both main and LMS contexts based on $sidebar_context variable
?>

<!-- Sidebar Overlay -->
<div id="sidebarOverlay" class="sidebar-overlay fixed inset-0 bg-black bg-opacity-50 z-40 lg:hidden transition-opacity duration-300 ease-in-out opacity-0 pointer-events-none" onclick="toggleSidebar()"></div>

<!-- Sidebar -->
<div id="sidebar" class="sidebar fixed top-0 left-0 h-full w-64 bg-seait-dark z-50 lg:relative lg:translate-x-0 lg:z-auto flex flex-col transform transition-transform duration-300 ease-in-out -translate-x-full lg:translate-x-0">
    <!-- Sidebar Header -->
    <div class="sidebar-header flex items-center justify-center p-4 border-b border-gray-700 bg-gradient-to-r from-gray-800 to-gray-700 flex-shrink-0">
        <div class="flex items-center transform transition-transform duration-200 hover:scale-105">
            <img src="../assets/images/seait-logo.png" alt="SEAIT Logo" class="h-8 w-auto mr-2 transition-all duration-200 hover:rotate-12">
            <span class="text-white font-semibold">
                <?php echo $sidebar_context === 'lms' ? 'SEAIT LMS' : 'Student Portal'; ?>
            </span>
        </div>
    </div>

    <!-- Navigation Menu - Scrollable Content -->
    <div class="sidebar-content flex-1 overflow-y-auto">
        <!-- User Profile Section -->
        <div class="mb-6 p-4 bg-gray-800 rounded-lg mx-3 transform transition-all duration-300 hover:bg-gray-700 hover:scale-105 hover:shadow-lg">
            <div class="flex items-center">
                <div class="h-12 w-12 rounded-full bg-seait-orange flex items-center justify-center mr-3 transition-all duration-300 hover:bg-orange-500 hover:scale-110 hover:shadow-md">
                    <span class="text-white font-semibold text-lg"><?php echo strtoupper(substr($_SESSION['first_name'], 0, 1) . substr($_SESSION['last_name'], 0, 1)); ?></span>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-white font-semibold text-sm truncate"><?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?></p>
                    <p class="text-gray-400 text-xs">Student</p>
                    <div class="flex items-center mt-1">
                        <div class="w-2 h-2 bg-green-400 rounded-full mr-2 animate-pulse"></div>
                        <span class="text-green-400 text-xs">Online</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="px-3">
            <?php if ($sidebar_context === 'main'): ?>
                <!-- Main Student Portal Navigation -->
                <div class="space-y-6">
                    <!-- Dashboard Section -->
                    <div class="animate-fadeInUp" style="animation-delay: 0.1s;">
                        <a href="dashboard.php" class="flex items-center <?php echo basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'bg-seait-orange text-white shadow-lg' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> px-3 py-2 rounded-lg transition-all duration-300 ease-in-out transform hover:scale-105 hover:shadow-md">
                            <i class="fas fa-tachometer-alt mr-3 w-5 text-center transition-transform duration-200 hover:rotate-12"></i>
                            <span class="truncate">Dashboard</span>
                        </a>
                    </div>

                    <!-- Academic Section -->
                    <div class="animate-fadeInUp" style="animation-delay: 0.2s;">
                        <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">Academic</h3>
                        <div class="space-y-1">
                            <a href="my-classes.php" class="flex items-center <?php echo basename($_SERVER['PHP_SELF']) === 'my-classes.php' ? 'bg-seait-orange text-white shadow-lg' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> px-3 py-2 rounded-lg transition-all duration-300 ease-in-out transform hover:scale-105 hover:shadow-md">
                                <i class="fas fa-graduation-cap mr-3 w-5 text-center transition-transform duration-200 hover:rotate-12"></i>
                                <span class="truncate">My Classes</span>
                            </a>
                        </div>
                    </div>

                    <!-- Evaluations Section -->
                    <div class="animate-fadeInUp" style="animation-delay: 0.3s;">
                        <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">Evaluations</h3>
                        <div class="space-y-1">
                            <a href="evaluate-teacher.php" class="flex items-center <?php echo basename($_SERVER['PHP_SELF']) === 'evaluate-teacher.php' ? 'bg-seait-orange text-white shadow-lg' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> px-3 py-2 rounded-lg transition-all duration-300 ease-in-out transform hover:scale-105 hover:shadow-md">
                                <i class="fas fa-clipboard-check mr-3 w-5 text-center transition-transform duration-200 hover:rotate-12"></i>
                                <span class="truncate">Teacher Evaluations</span>
                            </a>
                        </div>
                    </div>

                    <!-- Account Section -->
                    <div class="animate-fadeInUp" style="animation-delay: 0.4s;">
                        <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">Account</h3>
                        <div class="space-y-1">
                            <a href="profile.php" class="flex items-center <?php echo basename($_SERVER['PHP_SELF']) === 'profile.php' ? 'bg-seait-orange text-white shadow-lg' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> px-3 py-2 rounded-lg transition-all duration-300 ease-in-out transform hover:scale-105 hover:shadow-md">
                                <i class="fas fa-user mr-3 w-5 text-center transition-transform duration-200 hover:rotate-12"></i>
                                <span class="truncate">Profile</span>
                            </a>
                        </div>
                    </div>
                </div>

            <?php else: ?>
                <!-- LMS Navigation -->
                <!-- Class Info Section -->
                <div class="mb-6 p-4 bg-gray-800 rounded-lg transform transition-all duration-300 hover:bg-gray-700 hover:scale-105 hover:shadow-lg">
                    <h3 class="text-white font-semibold text-sm mb-2">Current Class</h3>
                    <p class="text-gray-300 text-xs font-medium truncate"><?php echo htmlspecialchars($class_data['subject_code'] ?? 'N/A'); ?></p>
                    <p class="text-white text-sm truncate"><?php echo htmlspecialchars($class_data['subject_title'] ?? 'Class Title'); ?></p>
                    <p class="text-gray-300 text-xs mt-1">Section <?php echo htmlspecialchars($class_data['section'] ?? 'N/A'); ?></p>
                    <p class="text-gray-300 text-xs">Join Code: <?php echo htmlspecialchars($class_data['join_code'] ?? 'N/A'); ?></p>
                </div>

                <div class="space-y-6">
                    <!-- Class Navigation Section -->
                    <div class="animate-fadeInUp" style="animation-delay: 0.1s;">
                        <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">Class Navigation</h3>
                        <div class="space-y-1">
                            <a href="class_dashboard.php?class_id=<?php echo $class_id; ?>" class="flex items-center <?php echo basename($_SERVER['PHP_SELF']) === 'class_dashboard.php' ? 'bg-seait-orange text-white shadow-lg' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> px-3 py-2 rounded-lg transition-all duration-300 ease-in-out transform hover:scale-105 hover:shadow-md">
                                <i class="fas fa-tachometer-alt mr-3 w-5 text-center transition-transform duration-200 hover:rotate-12"></i>
                                <span class="truncate">Dashboard</span>
                            </a>

                            <a href="lms_materials.php?class_id=<?php echo $class_id; ?>" class="flex items-center <?php echo basename($_SERVER['PHP_SELF']) === 'lms_materials.php' ? 'bg-seait-orange text-white shadow-lg' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> px-3 py-2 rounded-lg transition-all duration-300 ease-in-out transform hover:scale-105 hover:shadow-md">
                                <i class="fas fa-book mr-3 w-5 text-center transition-transform duration-200 hover:rotate-12"></i>
                                <span class="truncate">Learning Materials</span>
                            </a>

                            <a href="lms_assignments.php?class_id=<?php echo $class_id; ?>" class="flex items-center <?php echo basename($_SERVER['PHP_SELF']) === 'lms_assignments.php' ? 'bg-seait-orange text-white shadow-lg' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> px-3 py-2 rounded-lg transition-all duration-300 ease-in-out transform hover:scale-105 hover:shadow-md">
                                <i class="fas fa-tasks mr-3 w-5 text-center transition-transform duration-200 hover:rotate-12"></i>
                                <span class="truncate">Assignments</span>
                            </a>

                            <a href="lms_discussions.php?class_id=<?php echo $class_id; ?>" class="flex items-center <?php echo basename($_SERVER['PHP_SELF']) === 'lms_discussions.php' ? 'bg-seait-orange text-white shadow-lg' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> px-3 py-2 rounded-lg transition-all duration-300 ease-in-out transform hover:scale-105 hover:shadow-md">
                                <i class="fas fa-comments mr-3 w-5 text-center transition-transform duration-200 hover:rotate-12"></i>
                                <span class="truncate">Discussions</span>
                            </a>
                        </div>
                    </div>

                    <!-- Additional Features Section -->
                    <div class="animate-fadeInUp" style="animation-delay: 0.2s;">
                        <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">Additional Features</h3>
                        <div class="space-y-1">
                            <a href="lms_grades.php?class_id=<?php echo $class_id; ?>" class="flex items-center <?php echo basename($_SERVER['PHP_SELF']) === 'lms_grades.php' ? 'bg-seait-orange text-white shadow-lg' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> px-3 py-2 rounded-lg transition-all duration-300 ease-in-out transform hover:scale-105 hover:shadow-md">
                                <i class="fas fa-chart-line mr-3 w-5 text-center transition-transform duration-200 hover:rotate-12"></i>
                                <span class="truncate">Grades & Progress</span>
                            </a>

                            <a href="lms_resources.php?class_id=<?php echo $class_id; ?>" class="flex items-center <?php echo basename($_SERVER['PHP_SELF']) === 'lms_resources.php' ? 'bg-seait-orange text-white shadow-lg' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> px-3 py-2 rounded-lg transition-all duration-300 ease-in-out transform hover:scale-105 hover:shadow-md">
                                <i class="fas fa-link mr-3 w-5 text-center transition-transform duration-200 hover:rotate-12"></i>
                                <span class="truncate">Resources</span>
                            </a>
                        </div>
                    </div>

                    <!-- Evaluations Section -->
                    <div class="animate-fadeInUp" style="animation-delay: 0.3s;">
                        <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">Evaluations</h3>
                        <div class="space-y-1">
                            <a href="evaluate-teacher.php?class_id=<?php echo $class_id; ?>" class="flex items-center <?php echo basename($_SERVER['PHP_SELF']) === 'evaluate-teacher.php' ? 'bg-seait-orange text-white shadow-lg' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?> px-3 py-2 rounded-lg transition-all duration-300 ease-in-out transform hover:scale-105 hover:shadow-md">
                                <i class="fas fa-clipboard-check mr-3 w-5 text-center transition-transform duration-200 hover:rotate-12"></i>
                                <span class="truncate">Teacher Evaluations</span>
                            </a>
                        </div>
                    </div>

                    <!-- Navigation Section -->
                    <div class="animate-fadeInUp" style="animation-delay: 0.4s;">
                        <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">Navigation</h3>
                        <div class="space-y-1">
                            <a href="my-classes.php" class="flex items-center text-gray-300 hover:bg-gray-700 hover:text-white px-3 py-2 rounded-lg transition-all duration-300 ease-in-out transform hover:scale-105 hover:shadow-md">
                                <i class="fas fa-arrow-left mr-3 w-5 text-center transition-transform duration-200 hover:rotate-12"></i>
                                <span class="truncate">Back to Classes</span>
                            </a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Sidebar Footer -->
    <div class="sidebar-footer p-4 border-t border-gray-700 bg-gradient-to-r from-gray-800 to-gray-700 flex-shrink-0">
        <a href="logout.php" class="flex items-center bg-red-600 text-white hover:bg-red-700 px-3 py-2 rounded-lg transition-all duration-300 ease-in-out transform hover:scale-105 hover:shadow-lg">
            <i class="fas fa-sign-out-alt mr-3 w-5 text-center transition-transform duration-200 hover:rotate-12"></i>
            <span class="truncate">Logout</span>
        </a>
    </div>
</div>

<script>
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');

    if (sidebar && overlay) {
        if (sidebar.classList.contains('open')) {
            sidebar.classList.remove('open');
            overlay.classList.remove('open');
        } else {
            sidebar.classList.add('open');
            overlay.classList.add('open');
        }
    }
}

// Initialize sidebar behavior
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    const sidebarLinks = document.querySelectorAll('#sidebar a:not([href^="#"])'); // Exclude anchor links
    const sidebarContent = document.querySelector('.sidebar-content');

    // Store scroll position in sessionStorage
    function saveScrollPosition() {
        if (sidebarContent) {
            const storageKey = '<?php echo $sidebar_context; ?>SidebarScrollPosition';
            sessionStorage.setItem(storageKey, sidebarContent.scrollTop);
        }
    }

    // Restore scroll position from sessionStorage
    function restoreScrollPosition() {
        if (sidebarContent) {
            const storageKey = '<?php echo $sidebar_context; ?>SidebarScrollPosition';
            const savedPosition = sessionStorage.getItem(storageKey);
            if (savedPosition !== null) {
                setTimeout(() => {
                    sidebarContent.scrollTop = parseInt(savedPosition);
                }, 100);
            }
        }
    }

    // Save scroll position when scrolling
    if (sidebarContent) {
        sidebarContent.addEventListener('scroll', function() {
            saveScrollPosition();
        });
    }

    // Restore scroll position on page load
    restoreScrollPosition();

    // Ensure sidebar is in correct state on load
    if (sidebar) {
        if (window.innerWidth >= 1024) {
            sidebar.classList.remove('open');
            if (overlay) overlay.classList.remove('open');
        }
    }

    // Handle link clicks - NO MOVEMENT
    sidebarLinks.forEach(link => {
        link.addEventListener('click', (e) => {
            // Save current scroll position before navigation
            saveScrollPosition();

            // Prevent any default behavior that might cause movement
            e.preventDefault();

            // Get the href and navigate immediately without any delays or animations
            const href = link.getAttribute('href');
            if (href) {
                // Navigate immediately - no delays, no animations, no sidebar movement
                window.location.href = href;
            }
        });
    });

    // Handle overlay clicks
    if (overlay) {
        overlay.addEventListener('click', (e) => {
            e.preventDefault();
            if (window.innerWidth < 1024) {
                toggleSidebar();
            }
        });
    }
});

// Handle window resize
window.addEventListener('resize', () => {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');

    if (window.innerWidth >= 1024) {
        if (sidebar) sidebar.classList.remove('open');
        if (overlay) overlay.classList.remove('open');
    }
});

// Prevent unwanted sidebar interactions on desktop
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar');
    if (sidebar) {
        sidebar.addEventListener('click', (e) => {
            // Prevent event bubbling on desktop
            if (window.innerWidth >= 1024) {
                e.stopPropagation();
            }
        });
    }
});

// Prevent any zooming or scaling issues
document.addEventListener('DOMContentLoaded', function() {
    // Ensure viewport meta tag is properly set
    const viewport = document.querySelector('meta[name="viewport"]');
    if (!viewport) {
        const meta = document.createElement('meta');
        meta.name = 'viewport';
        meta.content = 'width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no';
        document.head.appendChild(meta);
    } else {
        viewport.content = 'width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no';
    }
});
</script>