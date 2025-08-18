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
<div id="sidebar" class="fixed lg:fixed inset-y-0 left-0 z-40 w-64 bg-gradient-to-b from-seait-dark to-gray-800 text-white transform -translate-x-full lg:translate-x-0 transition-transform duration-300 ease-in-out lg:transition-none lg:top-16 shadow-xl">
    <div class="flex flex-col h-full">
        <!-- Mobile Close Button -->
        <div class="lg:hidden flex justify-end p-4">
            <button id="close-sidebar" class="text-white hover:text-gray-300 transition-colors duration-200">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>

        <div class="h-full overflow-y-auto">
            <div class="p-6">
                <!-- Social Media Header -->
                <div class="mb-8 animate-fadeInUp" style="animation-delay: 0.1s;">
                    <div class="flex items-center space-x-3 mb-2 transform transition-transform duration-200 hover:scale-105">
                        <div class="w-8 h-8 bg-seait-orange rounded-lg flex items-center justify-center transition-all duration-300 hover:bg-orange-500 hover:scale-110 hover:shadow-md">
                            <i class="fas fa-share-alt text-white text-sm transition-transform duration-200 hover:rotate-12"></i>
                        </div>
                        <h2 class="text-lg font-bold text-white">Social Media</h2>
                    </div>
                    <p class="text-gray-300 text-xs">Manage content and engagement</p>
                </div>

                <!-- Navigation Menu -->
                <nav class="space-y-2">
                    <!-- Dashboard -->
                    <div class="animate-fadeInUp" style="animation-delay: 0.2s;">
                        <a href="dashboard.php" class="group flex items-center px-4 py-3 rounded-xl transition-all duration-300 ease-in-out transform hover:scale-105 hover:shadow-md <?php echo $current_page === 'dashboard.php' ? 'bg-seait-orange text-white shadow-lg' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?>">
                            <div class="w-8 h-8 rounded-lg flex items-center justify-center mr-3 transition-all duration-300 <?php echo $current_page === 'dashboard.php' ? 'bg-white bg-opacity-20' : 'bg-gray-700 group-hover:bg-seait-orange group-hover:bg-opacity-20'; ?>">
                                <i class="fas fa-tachometer-alt text-sm transition-transform duration-200 hover:rotate-12"></i>
                            </div>
                            <span class="font-medium">Dashboard</span>
                        </a>
                    </div>

                    <!-- Pending Posts -->
                    <div class="animate-fadeInUp" style="animation-delay: 0.3s;">
                        <a href="pending-post.php" class="group flex items-center px-4 py-3 rounded-xl transition-all duration-300 ease-in-out transform hover:scale-105 hover:shadow-md <?php echo $current_page === 'pending-post.php' ? 'bg-seait-orange text-white shadow-lg' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?>">
                            <div class="w-8 h-8 rounded-lg flex items-center justify-center mr-3 transition-all duration-300 <?php echo $current_page === 'pending-post.php' ? 'bg-white bg-opacity-20' : 'bg-gray-700 group-hover:bg-seait-orange group-hover:bg-opacity-20'; ?>">
                                <i class="fas fa-clock text-sm transition-transform duration-200 hover:rotate-12"></i>
                            </div>
                            <span class="font-medium">Pending Posts</span>
                        </a>
                    </div>

                    <!-- Approved Posts -->
                    <div class="animate-fadeInUp" style="animation-delay: 0.4s;">
                        <a href="approved-posts.php" class="group flex items-center px-4 py-3 rounded-xl transition-all duration-300 ease-in-out transform hover:scale-105 hover:shadow-md <?php echo $current_page === 'approved-posts.php' ? 'bg-seait-orange text-white shadow-lg' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?>">
                            <div class="w-8 h-8 rounded-lg flex items-center justify-center mr-3 transition-all duration-300 <?php echo $current_page === 'approved-posts.php' ? 'bg-white bg-opacity-20' : 'bg-gray-700 group-hover:bg-seait-orange group-hover:bg-opacity-20'; ?>">
                                <i class="fas fa-check-circle text-sm transition-transform duration-200 hover:rotate-12"></i>
                            </div>
                            <span class="font-medium">Approved Posts</span>
                        </a>
                    </div>

                    <!-- Rejected Posts -->
                    <div class="animate-fadeInUp" style="animation-delay: 0.5s;">
                        <a href="rejected-posts.php" class="group flex items-center px-4 py-3 rounded-xl transition-all duration-300 ease-in-out transform hover:scale-105 hover:shadow-md <?php echo $current_page === 'rejected-posts.php' ? 'bg-seait-orange text-white shadow-lg' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?>">
                            <div class="w-8 h-8 rounded-lg flex items-center justify-center mr-3 transition-all duration-300 <?php echo $current_page === 'rejected-posts.php' ? 'bg-white bg-opacity-20' : 'bg-gray-700 group-hover:bg-seait-orange group-hover:bg-opacity-20'; ?>">
                                <i class="fas fa-times-circle text-sm transition-transform duration-200 hover:rotate-12"></i>
                            </div>
                            <span class="font-medium">Rejected Posts</span>
                        </a>
                    </div>

                    <!-- Divider -->
                    <div class="border-t border-gray-600 my-4 animate-fadeInUp" style="animation-delay: 0.6s;"></div>

                    <!-- Pending Carousel -->
                    <div class="animate-fadeInUp" style="animation-delay: 0.7s;">
                        <a href="pending-carousel.php" class="group flex items-center px-4 py-3 rounded-xl transition-all duration-300 ease-in-out transform hover:scale-105 hover:shadow-md <?php echo $current_page === 'pending-carousel.php' ? 'bg-seait-orange text-white shadow-lg' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?>">
                            <div class="w-8 h-8 rounded-lg flex items-center justify-center mr-3 transition-all duration-300 <?php echo $current_page === 'pending-carousel.php' ? 'bg-white bg-opacity-20' : 'bg-gray-700 group-hover:bg-seait-orange group-hover:bg-opacity-20'; ?>">
                                <i class="fas fa-images text-sm transition-transform duration-200 hover:rotate-12"></i>
                            </div>
                            <span class="font-medium">Pending Carousel</span>
                        </a>
                    </div>

                    <!-- Analytics -->
                    <div class="animate-fadeInUp" style="animation-delay: 0.8s;">
                        <a href="analytics.php" class="group flex items-center px-4 py-3 rounded-xl transition-all duration-300 ease-in-out transform hover:scale-105 hover:shadow-md <?php echo $current_page === 'analytics.php' ? 'bg-seait-orange text-white shadow-lg' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?>">
                            <div class="w-8 h-8 rounded-lg flex items-center justify-center mr-3 transition-all duration-300 <?php echo $current_page === 'analytics.php' ? 'bg-white bg-opacity-20' : 'bg-gray-700 group-hover:bg-seait-orange group-hover:bg-opacity-20'; ?>">
                                <i class="fas fa-chart-line text-sm transition-transform duration-200 hover:rotate-12"></i>
                            </div>
                            <span class="font-medium">Analytics</span>
                        </a>
                    </div>
                </nav>

                <!-- Quick Stats -->
                <div class="mt-8 p-4 bg-white bg-opacity-5 rounded-xl border border-white border-opacity-10 animate-fadeInUp" style="animation-delay: 0.9s;">
                    <h3 class="text-sm font-semibold text-gray-300 mb-3">Content Overview</h3>
                    <div class="space-y-2">
                        <?php
                        // Get content statistics
                        $sidebar_pending_posts = 0;
                        $sidebar_approved_posts = 0;
                        $sidebar_rejected_posts = 0;

                        // Count pending posts
                        $pending_query = "SELECT COUNT(*) as total FROM posts WHERE status = 'pending'";
                        $pending_result = mysqli_query($conn, $pending_query);
                        if ($pending_result) {
                            $sidebar_pending_posts = mysqli_fetch_assoc($pending_result)['total'];
                        }

                        // Count approved posts
                        $approved_query = "SELECT COUNT(*) as total FROM posts WHERE status = 'approved'";
                        $approved_result = mysqli_query($conn, $approved_query);
                        if ($approved_result) {
                            $sidebar_approved_posts = mysqli_fetch_assoc($approved_result)['total'];
                        }

                        // Count rejected posts
                        $rejected_query = "SELECT COUNT(*) as total FROM posts WHERE status = 'rejected'";
                        $rejected_result = mysqli_query($conn, $rejected_query);
                        if ($rejected_result) {
                            $sidebar_rejected_posts = mysqli_fetch_assoc($rejected_result)['total'];
                        }
                        ?>
                        <div class="flex justify-between text-xs">
                            <span class="text-gray-400">Pending</span>
                            <span class="text-yellow-400 font-medium"><?php echo $sidebar_pending_posts; ?></span>
                        </div>
                        <div class="flex justify-between text-xs">
                            <span class="text-gray-400">Approved</span>
                            <span class="text-green-400 font-medium"><?php echo $sidebar_approved_posts; ?></span>
                        </div>
                        <div class="flex justify-between text-xs">
                            <span class="text-gray-400">Rejected</span>
                            <span class="text-red-400 font-medium"><?php echo $sidebar_rejected_posts; ?></span>
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
            sessionStorage.setItem('socialMediaSidebarScrollPosition', sidebarContent.scrollTop);
        }
    }

    // Restore scroll position from sessionStorage
    function restoreScrollPosition() {
        if (sidebarContent) {
            const savedPosition = sessionStorage.getItem('socialMediaSidebarScrollPosition');
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