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
<div id="sidebar" class="fixed lg:fixed inset-y-0 left-0 z-40 w-72 bg-gradient-to-b from-seait-dark to-gray-800 text-white transform -translate-x-full lg:translate-x-0 transition-transform duration-300 ease-in-out lg:transition-none lg:top-16 shadow-xl">
    <div class="flex flex-col h-full">
        <!-- Mobile Close Button -->
        <div class="lg:hidden flex justify-end p-4">
            <button id="close-sidebar" class="text-white hover:text-gray-300 transition-colors duration-200">
                <i class="fas fa-times text-lg"></i>
            </button>
        </div>

        <div class="h-full overflow-y-auto">
            <div class="p-6">
                <!-- Content Creator Header -->
                <div class="mb-8 animate-fadeInUp" style="animation-delay: 0.1s;">
                    <div class="flex items-center space-x-3 mb-2 transform transition-transform duration-200 hover:scale-105">
                        <div class="w-8 h-8 bg-seait-orange rounded-lg flex items-center justify-center transition-all duration-300 hover:bg-orange-500 hover:scale-110 hover:shadow-md">
                            <i class="fas fa-edit text-white text-base transition-transform duration-200 hover:rotate-12"></i>
                        </div>
                        <h2 class="text-lg font-bold text-white">Content Creator</h2>
                    </div>
                    <p class="text-gray-300 text-sm">Create and manage content</p>
                </div>

                <!-- Navigation Menu -->
                <nav class="space-y-2">
                    <!-- Dashboard -->
                    <div class="animate-fadeInUp" style="animation-delay: 0.2s;">
                        <a href="dashboard.php" class="group flex items-center px-4 py-3 rounded-xl transition-all duration-300 ease-in-out transform hover:scale-105 hover:shadow-md <?php echo $current_page === 'dashboard.php' ? 'bg-seait-orange text-white shadow-lg' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?>">
                            <div class="w-8 h-8 rounded-lg flex items-center justify-center mr-3 transition-all duration-300 <?php echo $current_page === 'dashboard.php' ? 'bg-white bg-opacity-20' : 'bg-gray-700 group-hover:bg-seait-orange group-hover:bg-opacity-20'; ?>">
                                <i class="fas fa-tachometer-alt text-base transition-transform duration-200 hover:rotate-12"></i>
                            </div>
                            <span class="font-medium text-sm whitespace-nowrap">Dashboard</span>
                        </a>
                    </div>

                    <!-- Content Management Section -->
                    <div class="mt-6 animate-fadeInUp" style="animation-delay: 0.3s;">
                        <div class="px-4 py-2">
                            <h3 class="text-sm font-semibold text-gray-400 uppercase tracking-wider">Content Management</h3>
                        </div>

                        <!-- Create Post -->
                        <div class="animate-fadeInUp" style="animation-delay: 0.4s;">
                            <a href="create-post.php" class="group flex items-center px-4 py-3 rounded-xl transition-all duration-300 ease-in-out transform hover:scale-105 hover:shadow-md <?php echo $current_page === 'create-post.php' ? 'bg-seait-orange text-white shadow-lg' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?>">
                                <div class="w-8 h-8 rounded-lg flex items-center justify-center mr-3 transition-all duration-300 <?php echo $current_page === 'create-post.php' ? 'bg-white bg-opacity-20' : 'bg-gray-700 group-hover:bg-seait-orange group-hover:bg-opacity-20'; ?>">
                                    <i class="fas fa-plus text-base transition-transform duration-200 hover:rotate-12"></i>
                                </div>
                                <span class="font-medium text-sm whitespace-nowrap">Create Post</span>
                            </a>
                        </div>

                        <!-- My Posts -->
                        <div class="animate-fadeInUp" style="animation-delay: 0.5s;">
                            <a href="my-posts.php" class="group flex items-center px-4 py-3 rounded-xl transition-all duration-300 ease-in-out transform hover:scale-105 hover:shadow-md <?php echo $current_page === 'my-posts.php' ? 'bg-seait-orange text-white shadow-lg' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?>">
                                <div class="w-8 h-8 rounded-lg flex items-center justify-center mr-3 transition-all duration-300 <?php echo $current_page === 'my-posts.php' ? 'bg-white bg-opacity-20' : 'bg-gray-700 group-hover:bg-seait-orange group-hover:bg-opacity-20'; ?>">
                                    <i class="fas fa-newspaper text-base transition-transform duration-200 hover:rotate-12"></i>
                                </div>
                                <span class="font-medium text-sm whitespace-nowrap">My Posts</span>
                            </a>
                        </div>

                        <!-- Drafts -->
                        <div class="animate-fadeInUp" style="animation-delay: 0.6s;">
                            <a href="drafts.php" class="group flex items-center px-4 py-3 rounded-xl transition-all duration-300 ease-in-out transform hover:scale-105 hover:shadow-md <?php echo $current_page === 'drafts.php' ? 'bg-seait-orange text-white shadow-lg' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?>">
                                <div class="w-8 h-8 rounded-lg flex items-center justify-center mr-3 transition-all duration-300 <?php echo $current_page === 'drafts.php' ? 'bg-white bg-opacity-20' : 'bg-gray-700 group-hover:bg-seait-orange group-hover:bg-opacity-20'; ?>">
                                    <i class="fas fa-edit text-base transition-transform duration-200 hover:rotate-12"></i>
                                </div>
                                <span class="font-medium text-sm whitespace-nowrap">Drafts</span>
                            </a>
                        </div>
                    </div>

                    <!-- Website Management Section -->
                    <div class="mt-6 animate-fadeInUp" style="animation-delay: 0.7s;">
                        <div class="px-4 py-2">
                            <h3 class="text-sm font-semibold text-gray-400 uppercase tracking-wider">Website Management</h3>
                        </div>

                        <!-- Manage Carousel -->
                        <div class="animate-fadeInUp" style="animation-delay: 0.8s;">
                            <a href="manage-carousel.php" class="group flex items-center px-4 py-3 rounded-xl transition-all duration-300 ease-in-out transform hover:scale-105 hover:shadow-md <?php echo $current_page === 'manage-carousel.php' ? 'bg-seait-orange text-white shadow-lg' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?>">
                                <div class="w-8 h-8 rounded-lg flex items-center justify-center mr-3 transition-all duration-300 <?php echo $current_page === 'manage-carousel.php' ? 'bg-white bg-opacity-20' : 'bg-gray-700 group-hover:bg-seait-orange group-hover:bg-opacity-20'; ?>">
                                    <i class="fas fa-images text-base transition-transform duration-200 hover:rotate-12"></i>
                                </div>
                                <span class="font-medium text-sm whitespace-nowrap">Manage Carousel</span>
                            </a>
                        </div>

                        <!-- Manage Colleges -->
                        <div class="animate-fadeInUp" style="animation-delay: 0.9s;">
                            <a href="manage-colleges.php" class="group flex items-center px-4 py-3 rounded-xl transition-all duration-300 ease-in-out transform hover:scale-105 hover:shadow-md <?php echo $current_page === 'manage-colleges.php' ? 'bg-seait-orange text-white shadow-lg' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?>">
                                <div class="w-8 h-8 rounded-lg flex items-center justify-center mr-3 transition-all duration-300 <?php echo $current_page === 'manage-colleges.php' ? 'bg-white bg-opacity-20' : 'bg-gray-700 group-hover:bg-seait-orange group-hover:bg-opacity-20'; ?>">
                                    <i class="fas fa-university text-base transition-transform duration-200 hover:rotate-12"></i>
                                </div>
                                <span class="font-medium text-sm whitespace-nowrap">Manage Colleges</span>
                            </a>
                        </div>

                        <!-- Manage Course Details -->
                        <div class="animate-fadeInUp" style="animation-delay: 1.0s;">
                            <a href="manage-course-details.php" class="group flex items-center px-4 py-3 rounded-xl transition-all duration-300 ease-in-out transform hover:scale-105 hover:shadow-md <?php echo $current_page === 'manage-course-details.php' ? 'bg-seait-orange text-white shadow-lg' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?>">
                                <div class="w-8 h-8 rounded-lg flex items-center justify-center mr-3 transition-all duration-300 <?php echo $current_page === 'manage-course-details.php' ? 'bg-white bg-opacity-20' : 'bg-gray-700 group-hover:bg-seait-orange group-hover:bg-opacity-20'; ?>">
                                    <i class="fas fa-graduation-cap text-base transition-transform duration-200 hover:rotate-12"></i>
                                </div>
                                <span class="font-medium text-sm whitespace-nowrap">Manage Course Details</span>
                            </a>
                        </div>

                        <!-- Manage Board Directors -->
                        <div class="animate-fadeInUp" style="animation-delay: 1.1s;">
                            <a href="manage-board-directors.php" class="group flex items-center px-4 py-3 rounded-xl transition-all duration-300 ease-in-out transform hover:scale-105 hover:shadow-md <?php echo $current_page === 'manage-board-directors.php' ? 'bg-seait-orange text-white shadow-lg' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?>">
                                <div class="w-8 h-8 rounded-lg flex items-center justify-center mr-3 transition-all duration-300 <?php echo $current_page === 'manage-board-directors.php' ? 'bg-white bg-opacity-20' : 'bg-gray-700 group-hover:bg-seait-orange group-hover:bg-opacity-20'; ?>">
                                    <i class="fas fa-user-tie text-base transition-transform duration-200 hover:rotate-12"></i>
                                </div>
                                <span class="font-medium text-sm whitespace-nowrap">Board Directors</span>
                            </a>
                        </div>

                        <!-- Manage Admissions -->
                        <div class="animate-fadeInUp" style="animation-delay: 1.2s;">
                            <a href="manage-admissions.php" class="group flex items-center px-4 py-3 rounded-xl transition-all duration-300 ease-in-out transform hover:scale-105 hover:shadow-md <?php echo $current_page === 'manage-admissions.php' ? 'bg-seait-orange text-white shadow-lg' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?>">
                                <div class="w-8 h-8 rounded-lg flex items-center justify-center mr-3 transition-all duration-300 <?php echo $current_page === 'manage-admissions.php' ? 'bg-white bg-opacity-20' : 'bg-gray-700 group-hover:bg-seait-orange group-hover:bg-opacity-20'; ?>">
                                    <i class="fas fa-user-graduate text-base transition-transform duration-200 hover:rotate-12"></i>
                                </div>
                                <span class="font-medium text-sm whitespace-nowrap">Manage Admissions</span>
                            </a>
                        </div>

                        <!-- Manage Research -->
                        <div class="animate-fadeInUp" style="animation-delay: 1.3s;">
                            <a href="manage-research.php" class="group flex items-center px-4 py-3 rounded-xl transition-all duration-300 ease-in-out transform hover:scale-105 hover:shadow-md <?php echo $current_page === 'manage-research.php' ? 'bg-seait-orange text-white shadow-lg' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?>">
                                <div class="w-8 h-8 rounded-lg flex items-center justify-center mr-3 transition-all duration-300 <?php echo $current_page === 'manage-research.php' ? 'bg-white bg-opacity-20' : 'bg-gray-700 group-hover:bg-seait-orange group-hover:bg-opacity-20'; ?>">
                                    <i class="fas fa-microscope text-base transition-transform duration-200 hover:rotate-12"></i>
                                </div>
                                <span class="font-medium text-sm whitespace-nowrap">Manage Research</span>
                            </a>
                        </div>

                        <!-- Manage History -->
                        <div class="animate-fadeInUp" style="animation-delay: 1.4s;">
                            <a href="manage-history.php" class="group flex items-center px-4 py-3 rounded-xl transition-all duration-300 ease-in-out transform hover:scale-105 hover:shadow-md <?php echo $current_page === 'manage-history.php' ? 'bg-seait-orange text-white shadow-lg' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?>">
                                <div class="w-8 h-8 rounded-lg flex items-center justify-center mr-3 transition-all duration-300 <?php echo $current_page === 'manage-history.php' ? 'bg-white bg-opacity-20' : 'bg-gray-700 group-hover:bg-seait-orange group-hover:bg-opacity-20'; ?>">
                                    <i class="fas fa-history text-base transition-transform duration-200 hover:rotate-12"></i>
                                </div>
                                <span class="font-medium text-sm whitespace-nowrap">Manage History</span>
                            </a>
                        </div>

                        <!-- Manage Contacts -->
                        <div class="animate-fadeInUp" style="animation-delay: 1.5s;">
                            <a href="manage-contacts.php" class="group flex items-center px-4 py-3 rounded-xl transition-all duration-300 ease-in-out transform hover:scale-105 hover:shadow-md <?php echo $current_page === 'manage-contacts.php' ? 'bg-seait-orange text-white shadow-lg' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?>">
                                <div class="w-8 h-8 rounded-lg flex items-center justify-center mr-3 transition-all duration-300 <?php echo $current_page === 'manage-contacts.php' ? 'bg-white bg-opacity-20' : 'bg-gray-700 group-hover:bg-seait-orange group-hover:bg-opacity-20'; ?>">
                                    <i class="fas fa-address-book text-base transition-transform duration-200 hover:rotate-12"></i>
                                </div>
                                <span class="font-medium text-sm whitespace-nowrap">Manage Contacts</span>
                            </a>
                        </div>
                    </div>

                    <!-- Account Section -->
                    <div class="mt-6 animate-fadeInUp" style="animation-delay: 1.6s;">
                        <div class="px-4 py-2">
                            <h3 class="text-sm font-semibold text-gray-400 uppercase tracking-wider">Account</h3>
                        </div>

                        <!-- Profile -->
                        <div class="animate-fadeInUp" style="animation-delay: 1.7s;">
                            <a href="profile.php" class="group flex items-center px-4 py-3 rounded-xl transition-all duration-300 ease-in-out transform hover:scale-105 hover:shadow-md <?php echo $current_page === 'profile.php' ? 'bg-seait-orange text-white shadow-lg' : 'text-gray-300 hover:bg-gray-700 hover:text-white'; ?>">
                                <div class="w-8 h-8 rounded-lg flex items-center justify-center mr-3 transition-all duration-300 <?php echo $current_page === 'profile.php' ? 'bg-white bg-opacity-20' : 'bg-gray-700 group-hover:bg-seait-orange group-hover:bg-opacity-20'; ?>">
                                    <i class="fas fa-user text-base transition-transform duration-200 hover:rotate-12"></i>
                                </div>
                                <span class="font-medium text-sm whitespace-nowrap">Profile</span>
                            </a>
                        </div>
                    </div>
                </nav>

                <!-- Quick Stats -->
                <div class="mt-8 p-4 bg-white bg-opacity-5 rounded-xl border border-white border-opacity-10 animate-fadeInUp" style="animation-delay: 1.8s;">
                    <h3 class="text-base font-semibold text-gray-300 mb-3">My Content</h3>
                    <div class="space-y-2">
                        <?php
                        // Get user's content statistics
                        $user_id = $_SESSION['user_id'];

                        // Count drafts
                        $drafts_query = "SELECT COUNT(*) as total FROM posts WHERE author_id = ? AND status = 'draft'";
                        $stmt = mysqli_prepare($conn, $drafts_query);
                        mysqli_stmt_bind_param($stmt, "i", $user_id);
                        mysqli_stmt_execute($stmt);
                        $drafts_result = mysqli_stmt_get_result($stmt);
                        $drafts_count = mysqli_fetch_assoc($drafts_result)['total'];

                        // Count pending posts
                        $pending_query = "SELECT COUNT(*) as total FROM posts WHERE author_id = ? AND status = 'pending'";
                        $stmt = mysqli_prepare($conn, $pending_query);
                        mysqli_stmt_bind_param($stmt, "i", $user_id);
                        mysqli_stmt_execute($stmt);
                        $pending_result = mysqli_stmt_get_result($stmt);
                        $pending_count = mysqli_fetch_assoc($pending_result)['total'];

                        // Count approved posts
                        $approved_query = "SELECT COUNT(*) as total FROM posts WHERE author_id = ? AND status = 'approved'";
                        $stmt = mysqli_prepare($conn, $approved_query);
                        mysqli_stmt_bind_param($stmt, "i", $user_id);
                        mysqli_stmt_execute($stmt);
                        $approved_result = mysqli_stmt_get_result($stmt);
                        $approved_count = mysqli_fetch_assoc($approved_result)['total'];
                        ?>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-400">Drafts</span>
                            <span class="text-yellow-400 font-medium"><?php echo $drafts_count; ?></span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-400">Pending</span>
                            <span class="text-blue-400 font-medium"><?php echo $pending_count; ?></span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-400">Approved</span>
                            <span class="text-green-400 font-medium"><?php echo $approved_count; ?></span>
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
            sessionStorage.setItem('contentCreatorSidebarScrollPosition', sidebarContent.scrollTop);
        }
    }

    // Restore scroll position from sessionStorage
    function restoreScrollPosition() {
        if (sidebarContent) {
            const savedPosition = sessionStorage.getItem('contentCreatorSidebarScrollPosition');
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