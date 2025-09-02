<?php
// Get current page name for active state
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!-- Mobile Menu Button -->
<div class="lg:hidden">
    <button id="mobile-menu-button" class="fixed top-4 left-4 z-50 bg-seait-dark text-white p-3 rounded-md shadow-lg">
        <i class="fas fa-bars"></i>
    </button>
</div>

<!-- Mobile Overlay -->
<div id="mobile-overlay" class="fixed inset-0 bg-black bg-opacity-50 z-40 lg:hidden hidden transition-opacity duration-300 ease-in-out"></div>

<!-- Sidebar -->
<div id="sidebar" class="w-64 bg-gradient-to-b from-seait-dark to-gray-800 text-white fixed left-0 top-16 h-screen shadow-xl transform -translate-x-full lg:translate-x-0 transition-transform duration-300 ease-in-out lg:transition-none">
    <div class="flex flex-col h-full">
        <!-- Mobile Close Button -->
        <div class="lg:hidden flex justify-end p-4">
            <button id="close-sidebar" class="text-white hover:text-gray-300 transition-colors duration-200">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>

        <div class="h-full overflow-y-auto">
            <div class="p-6">
                <!-- Admin Panel Header -->
                <div class="mb-8 animate-fadeInUp" style="animation-delay: 0.1s;">
                    <div class="flex items-center space-x-3 mb-2 transform transition-transform duration-200 hover:scale-105">
                        <div class="w-8 h-8 bg-seait-orange rounded-lg flex items-center justify-center transition-all duration-300 hover:bg-orange-500 hover:scale-110 hover:shadow-md">
                            <i class="fas fa-shield-alt text-white text-sm transition-transform duration-200 hover:rotate-12"></i>
                        </div>
                        <h2 class="text-lg font-bold text-white">Admin Panel</h2>
                    </div>
                    <p class="text-gray-300 text-xs">Manage your website</p>
                </div>

                <!-- Navigation Menu -->
                <nav class="space-y-6">
                    <!-- Dashboard Section -->
                    <div class="animate-fadeInUp" style="animation-delay: 0.1s;">
                        <a href="dashboard.php" class="group flex items-center px-4 py-3 rounded-xl transition-all duration-300 ease-in-out transform hover:scale-105 hover:shadow-md <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'bg-seait-orange text-white shadow-lg' : 'hover:bg-gray-700 rounded transition'; ?>">
                            <div class="w-8 h-8 rounded-lg flex items-center justify-center mr-3 transition-all duration-300 <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'bg-white bg-opacity-20' : 'bg-gray-700 group-hover:bg-seait-orange group-hover:bg-opacity-20'; ?>">
                                <i class="fas fa-tachometer-alt text-sm transition-transform duration-200 hover:rotate-12"></i>
                            </div>
                            <span class="font-medium">Dashboard</span>
                        </a>
                    </div>

                    <!-- User Management Section -->
                    <div class="animate-fadeInUp" style="animation-delay: 0.2s;">
                        <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3 px-4">User Management</h3>
                        <div class="space-y-1">
                            <a href="users.php" class="group flex items-center px-4 py-3 rounded-xl transition-all duration-300 ease-in-out transform hover:scale-105 hover:shadow-md <?php echo basename($_SERVER['PHP_SELF']) == 'users.php' ? 'bg-seait-orange text-white shadow-lg' : 'hover:bg-gray-700 rounded transition'; ?>">
                                <div class="w-8 h-8 rounded-lg flex items-center justify-center mr-3 transition-all duration-300 <?php echo basename($_SERVER['PHP_SELF']) == 'users.php' ? 'bg-white bg-opacity-20' : 'bg-gray-700 group-hover:bg-seait-orange group-hover:bg-opacity-20'; ?>">
                                    <i class="fas fa-users text-sm transition-transform duration-200 hover:rotate-12"></i>
                                </div>
                                <span class="font-medium">Users</span>
                            </a>
                            
                            <a href="faculty.php" class="group flex items-center px-4 py-3 rounded-xl transition-all duration-300 ease-in-out transform hover:scale-105 hover:shadow-md <?php echo basename($_SERVER['PHP_SELF']) == 'faculty.php' ? 'bg-seait-orange text-white shadow-lg' : 'hover:bg-gray-700 rounded transition'; ?>">
                                <div class="w-8 h-8 rounded-lg flex items-center justify-center mr-3 transition-all duration-300 <?php echo basename($_SERVER['PHP_SELF']) == 'faculty.php' ? 'bg-white bg-opacity-20' : 'bg-gray-700 group-hover:bg-seait-orange group-hover:bg-opacity-20'; ?>">
                                    <i class="fas fa-chalkboard-teacher text-sm transition-transform duration-200 hover:rotate-12"></i>
                                </div>
                                <span class="font-medium">Faculty</span>
                            </a>
                            
                            <a href="students.php" class="group flex items-center px-4 py-3 rounded-xl transition-all duration-300 ease-in-out transform hover:scale-105 hover:shadow-md <?php echo basename($_SERVER['PHP_SELF']) == 'students.php' ? 'bg-seait-orange text-white shadow-lg' : 'hover:bg-gray-700 rounded transition'; ?>">
                                <div class="w-8 h-8 rounded-lg flex items-center justify-center mr-3 transition-all duration-300 <?php echo basename($_SERVER['PHP_SELF']) == 'students.php' ? 'bg-white bg-opacity-20' : 'bg-gray-700 group-hover:bg-seait-orange group-hover:bg-opacity-20'; ?>">
                                    <i class="fas fa-user-graduate text-sm transition-transform duration-200 hover:rotate-12"></i>
                                </div>
                                <span class="font-medium">Student Registration</span>
                            </a>
                        </div>
                    </div>

                    <!-- Content Management Section -->
                    <div class="animate-fadeInUp" style="animation-delay: 0.3s;">
                        <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3 px-4">Content Management</h3>
                        <div class="space-y-1">
                            <a href="posts.php" class="group flex items-center px-4 py-3 rounded-xl transition-all duration-300 ease-in-out transform hover:scale-105 hover:shadow-md <?php echo basename($_SERVER['PHP_SELF']) == 'posts.php' ? 'bg-seait-orange text-white shadow-lg' : 'hover:bg-gray-700 rounded transition'; ?>">
                                <div class="w-8 h-8 rounded-lg flex items-center justify-center mr-3 transition-all duration-300 <?php echo basename($_SERVER['PHP_SELF']) == 'posts.php' ? 'bg-white bg-opacity-20' : 'bg-gray-700 group-hover:bg-seait-orange group-hover:bg-opacity-20'; ?>">
                                    <i class="fas fa-newspaper text-sm transition-transform duration-200 hover:rotate-12"></i>
                                </div>
                                <span class="font-medium">Posts</span>
                            </a>
                            
                            <a href="programs.php" class="group flex items-center px-4 py-3 rounded-xl transition-all duration-300 ease-in-out transform hover:scale-105 hover:shadow-md <?php echo basename($_SERVER['PHP_SELF']) == 'programs.php' ? 'bg-seait-orange text-white shadow-lg' : 'hover:bg-gray-700 rounded transition'; ?>">
                                <div class="w-8 h-8 rounded-lg flex items-center justify-center mr-3 transition-all duration-300 <?php echo basename($_SERVER['PHP_SELF']) == 'programs.php' ? 'bg-white bg-opacity-20' : 'bg-gray-700 group-hover:bg-seait-orange group-hover:bg-opacity-20'; ?>">
                                    <i class="fas fa-graduation-cap text-sm transition-transform duration-200 hover:rotate-12"></i>
                                </div>
                                <span class="font-medium">Academic Programs</span>
                            </a>
                            
                            <a href="manage-faqs.php" class="group flex items-center px-4 py-3 rounded-xl transition-all duration-300 ease-in-out transform hover:scale-105 hover:shadow-md <?php echo basename($_SERVER['PHP_SELF']) == 'manage-faqs.php' ? 'bg-seait-orange text-white shadow-lg' : 'hover:bg-gray-700 rounded transition'; ?>">
                                <div class="w-8 h-8 rounded-lg flex items-center justify-center mr-3 transition-all duration-300 <?php echo basename($_SERVER['PHP_SELF']) == 'manage-faqs.php' ? 'bg-white bg-opacity-20' : 'bg-gray-700 group-hover:bg-seait-orange group-hover:bg-opacity-20'; ?>">
                                    <i class="fas fa-question-circle text-sm transition-transform duration-200 hover:rotate-12"></i>
                                </div>
                                <span class="font-medium">FAQ Management</span>
                            </a>
                        </div>
                    </div>

                    <!-- Communication Section -->
                    <div class="animate-fadeInUp" style="animation-delay: 0.4s;">
                        <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3 px-4">Communication</h3>
                        <div class="space-y-1">
                            <a href="inquiries.php" class="group flex items-center px-4 py-3 rounded-xl transition-all duration-300 ease-in-out transform hover:scale-105 hover:shadow-md <?php echo basename($_SERVER['PHP_SELF']) == 'inquiries.php' ? 'bg-seait-orange text-white shadow-lg' : 'hover:bg-gray-700 rounded transition'; ?>">
                                <div class="w-8 h-8 rounded-lg flex items-center justify-center mr-3 transition-all duration-300 <?php echo basename($_SERVER['PHP_SELF']) == 'inquiries.php' ? 'bg-white bg-opacity-20' : 'bg-gray-700 group-hover:bg-seait-orange group-hover:bg-opacity-20'; ?>">
                                    <i class="fas fa-comments text-sm transition-transform duration-200 hover:rotate-12"></i>
                                </div>
                                <span class="font-medium">View Inquiries</span>
                            </a>
                        </div>
                    </div>

                    <!-- Reports & Analytics Section -->
                    <div class="animate-fadeInUp" style="animation-delay: 0.5s;">
                        <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3 px-4">Reports & Analytics</h3>
                        <div class="space-y-1">
                            <a href="reports.php" class="group flex items-center px-4 py-3 rounded-xl transition-all duration-300 ease-in-out transform hover:scale-105 hover:shadow-md <?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'bg-seait-orange text-white shadow-lg' : 'hover:bg-gray-700 rounded transition'; ?>">
                                <div class="w-8 h-8 rounded-lg flex items-center justify-center mr-3 transition-all duration-300 <?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'bg-white bg-opacity-20' : 'bg-gray-700 group-hover:bg-seait-orange group-hover:bg-opacity-20'; ?>">
                                    <i class="fas fa-chart-bar text-sm transition-transform duration-200 hover:rotate-12"></i>
                                </div>
                                <span class="font-medium">Reports</span>
                            </a>
                        </div>
                    </div>

                    <!-- System Management Section -->
                    <div class="animate-fadeInUp" style="animation-delay: 0.6s;">
                        <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3 px-4">System</h3>
                        <div class="space-y-1">
                            <a href="database-sync.php" class="group flex items-center px-4 py-3 rounded-xl transition-all duration-300 ease-in-out transform hover:scale-105 hover:shadow-md <?php echo basename($_SERVER['PHP_SELF']) == 'database-sync.php' ? 'bg-seait-orange text-white shadow-lg' : 'hover:bg-gray-700 rounded transition'; ?>">
                                <div class="w-8 h-8 rounded-lg flex items-center justify-center mr-3 transition-all duration-300 <?php echo basename($_SERVER['PHP_SELF']) == 'database-sync.php' ? 'bg-white bg-opacity-20' : 'bg-gray-700 group-hover:bg-seait-orange group-hover:bg-opacity-20'; ?>">
                                    <i class="fas fa-sync-alt text-sm transition-transform duration-200 hover:rotate-12"></i>
                                </div>
                                <span class="font-medium">Database Sync</span>
                            </a>
                            
                            <a href="ftp-manager.php" class="group flex items-center px-4 py-3 rounded-xl transition-all duration-300 ease-in-out transform hover:scale-105 hover:shadow-md <?php echo basename($_SERVER['PHP_SELF']) == 'ftp-manager.php' ? 'bg-seait-orange text-white shadow-lg' : 'hover:bg-gray-700 rounded transition'; ?>">
                                <div class="w-8 h-8 rounded-lg flex items-center justify-center mr-3 transition-all duration-300 <?php echo basename($_SERVER['PHP_SELF']) == 'ftp-manager.php' ? 'bg-white bg-opacity-20' : 'bg-gray-700 group-hover:bg-seait-orange group-hover:bg-opacity-20'; ?>">
                                    <i class="fas fa-server text-sm transition-transform duration-200 hover:scale-110"></i>
                                </div>
                                <span class="font-medium">FTP Manager</span>
                            </a>
                            
                            <a href="settings.php" class="group flex items-center px-4 py-3 rounded-xl transition-all duration-300 ease-in-out transform hover:scale-105 hover:shadow-md <?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'bg-seait-orange text-white shadow-lg' : 'hover:bg-gray-700 rounded transition'; ?>">
                                <div class="w-8 h-8 rounded-lg flex items-center justify-center mr-3 transition-all duration-300 <?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'bg-white bg-opacity-20' : 'bg-gray-700 group-hover:bg-seait-orange group-hover:bg-opacity-20'; ?>">
                                    <i class="fas fa-cog text-sm transition-transform duration-200 hover:rotate-12"></i>
                                </div>
                                <span class="font-medium">Settings</span>
                            </a>
                        </div>
                    </div>
                </nav>

                <!-- Quick Stats -->
                <div class="mt-8 p-4 bg-white bg-opacity-5 rounded-xl border border-white border-opacity-10 animate-fadeInUp" style="animation-delay: 1.2s;">
                    <h3 class="text-sm font-semibold text-gray-300 mb-3">Quick Stats</h3>
                    <div class="space-y-2">
                        <?php
                        // Get real statistics
                        $users_count = 0;
                        $posts_count = 0;
                        $inquiries_count = 0;
                        $students_count = 0;

                        // Count users
                        $users_query = "SELECT COUNT(*) as total FROM users";
                        $users_result = mysqli_query($conn, $users_query);
                        if ($users_result) {
                            $users_count = mysqli_fetch_assoc($users_result)['total'];
                        }

                        // Count posts
                        $posts_query = "SELECT COUNT(*) as total FROM posts";
                        $posts_result = mysqli_query($conn, $posts_query);
                        if ($posts_result) {
                            $posts_count = mysqli_fetch_assoc($posts_result)['total'];
                        }

                        // Count unresolved inquiries
                        $inquiries_query = "SELECT COUNT(*) as total FROM user_inquiries WHERE is_resolved = 0";
                        $inquiries_result = mysqli_query($conn, $inquiries_query);
                        if ($inquiries_result) {
                            $inquiries_count = mysqli_fetch_assoc($inquiries_result)['total'];
                        }

                        // Count active students
                        $students_count_query = "SELECT COUNT(*) as total FROM students WHERE status = 'active'";
                        $students_count_result = mysqli_query($conn, $students_count_query);
                        if ($students_count_result) {
                            $students_count = mysqli_fetch_assoc($students_count_result)['total'];
                        }
                        ?>
                        <div class="flex justify-between text-xs">
                            <span class="text-gray-400">Active Users</span>
                            <span class="text-seait-orange font-medium"><?php echo $users_count; ?></span>
                        </div>
                        <div class="flex justify-between text-xs">
                            <span class="text-gray-400">Total Posts</span>
                            <span class="text-seait-orange font-medium"><?php echo $posts_count; ?></span>
                        </div>
                        <div class="flex justify-between text-xs">
                            <span class="text-gray-400">Active Students</span>
                            <span class="text-seait-orange font-medium"><?php echo $students_count; ?></span>
                        </div>
                        <div class="flex justify-between text-xs">
                            <span class="text-gray-400">New Inquiries</span>
                            <span class="text-seait-orange font-medium"><?php echo $inquiries_count; ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Custom animations */
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.animate-fadeInUp {
    animation: fadeInUp 0.6s ease-out forwards;
    opacity: 0;
}

/* Sidebar open/close animations */
.sidebar.open {
    transform: translateX(0);
}

/* Smooth transitions for all interactive elements */
.sidebar a {
    position: relative;
    overflow: hidden;
}

.sidebar a::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.1), transparent);
    transition: left 0.5s;
}

.sidebar a:hover::before {
    left: 100%;
}

/* Active state animations */
.sidebar a.bg-seait-orange {
    animation: activePulse 2s infinite;
}

@keyframes activePulse {
    0%, 100% {
        box-shadow: 0 0 0 0 rgba(249, 115, 22, 0.7);
    }
    50% {
        box-shadow: 0 0 0 10px rgba(249, 115, 22, 0);
    }
}
</style>

<script>
// Mobile sidebar functionality
document.addEventListener('DOMContentLoaded', function() {
    const mobileMenuButton = document.getElementById('mobile-menu-button');
    const closeSidebarButton = document.getElementById('close-sidebar');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('mobile-overlay');
    const sidebarLinks = document.querySelectorAll('#sidebar a:not([href^="#"])'); // Exclude anchor links
    const sidebarContent = document.querySelector('.h-full.overflow-y-auto');

    // Store scroll position in sessionStorage
    function saveScrollPosition() {
        if (sidebarContent) {
            sessionStorage.setItem('adminSidebarScrollPosition', sidebarContent.scrollTop);
        }
    }

    // Restore scroll position from sessionStorage
    function restoreScrollPosition() {
        if (sidebarContent) {
            const savedPosition = sessionStorage.getItem('adminSidebarScrollPosition');
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

    function openSidebar() {
        sidebar.classList.remove('-translate-x-full');
        overlay.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }

    function closeSidebar() {
        sidebar.classList.add('-translate-x-full');
        overlay.classList.add('hidden');
        document.body.style.overflow = '';
    }

    // Open sidebar
    mobileMenuButton.addEventListener('click', openSidebar);

    // Close sidebar
    closeSidebarButton.addEventListener('click', closeSidebar);
    overlay.addEventListener('click', closeSidebar);

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

    // Close sidebar on window resize if switching to desktop
    window.addEventListener('resize', function() {
        if (window.innerWidth >= 1024) { // lg breakpoint
            closeSidebar();
        }
    });

    // Prevent unwanted sidebar interactions on desktop
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