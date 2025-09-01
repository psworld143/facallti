<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

check_social_media_manager();

// Get pending posts for approval
$pending_query = "SELECT p.*, u.first_name, u.last_name FROM posts p
                  JOIN users u ON p.author_id = u.id
                  WHERE p.status = 'pending'
                  ORDER BY p.created_at DESC";
$pending_result = mysqli_query($conn, $pending_query);

// Get approved posts
$approved_query = "SELECT p.*, u.first_name, u.last_name FROM posts p
                   JOIN users u ON p.author_id = u.id
                   WHERE p.status = 'approved'
                   ORDER BY p.created_at DESC LIMIT 10";
$approved_result = mysqli_query($conn, $approved_query);

// Get statistics
$pending_count_query = "SELECT COUNT(*) as total FROM posts WHERE status = 'pending'";
$pending_count_result = mysqli_query($conn, $pending_count_query);
$pending_count = mysqli_fetch_assoc($pending_count_result)['total'];

$approved_count_query = "SELECT COUNT(*) as total FROM posts WHERE status = 'approved'";
$approved_count_result = mysqli_query($conn, $approved_count_query);
$approved_count = mysqli_fetch_assoc($approved_count_result)['total'];

// Get carousel statistics
$pending_carousel_query = "SELECT COUNT(*) as total FROM carousel_slides WHERE status = 'pending'";
$pending_carousel_result = mysqli_query($conn, $pending_carousel_query);
$pending_carousel_count = mysqli_fetch_assoc($pending_carousel_result)['total'];

$approved_carousel_query = "SELECT COUNT(*) as total FROM carousel_slides WHERE status = 'approved'";
$approved_carousel_result = mysqli_query($conn, $approved_carousel_query);
$approved_carousel_count = mysqli_fetch_assoc($approved_carousel_result)['total'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Social Media Manager Dashboard - SEAIT</title>
    <link rel="icon" type="image/png" href="../assets/images/seait-logo.png">
    <link rel="shortcut icon" type="image/png" href="../assets/images/seait-logo.png">
    <link rel="apple-touch-icon" type="image/png" href="../assets/images/seait-logo.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'seait-orange': '#FF6B35',
                        'seait-dark': '#2C3E50',
                        'seait-light': '#FFF8F0'
                    }
                }
            }
        }
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    <!-- Fixed Navigation -->
    <nav class="fixed top-0 left-0 right-0 bg-white shadow-lg z-50">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between items-center py-4">
                <div class="flex items-center space-x-4">
                    <img src="../assets/images/seait-logo.png" alt="SEAIT Logo" class="h-10 w-auto">
                    <div class="hidden sm:block">
                        <h1 class="text-xl font-bold text-seait-dark">SEAIT Social Media</h1>
                        <p class="text-sm text-gray-600">Welcome, <?php echo $_SESSION['first_name']; ?></p>
                    </div>
                    <div class="sm:hidden">
                        <h1 class="text-lg font-bold text-seait-dark">SEAIT</h1>
                        <p class="text-xs text-gray-600"><?php echo $_SESSION['first_name']; ?></p>
                    </div>
                </div>

                <div class="flex items-center space-x-2 sm:space-x-4">
                    <!-- Mobile menu button -->
                    <button id="mobile-menu-button" class="lg:hidden bg-seait-orange text-white p-2 rounded-md hover:bg-orange-600 transition">
                        <i class="fas fa-bars"></i>
                    </button>

                    <!-- Desktop links -->
                    <div class="hidden sm:flex items-center space-x-4">
                        <a href="../index.php" class="text-seait-dark hover:text-seait-orange transition">
                            <i class="fas fa-home mr-2"></i>View Site
                        </a>
                        <a href="logout.php" class="bg-red-500 text-white px-4 py-2 rounded hover:bg-red-600 transition">
                            <i class="fas fa-sign-out-alt mr-2"></i>Logout
                        </a>
                    </div>

                    <!-- Mobile links -->
                    <div class="sm:hidden flex items-center space-x-2">
                        <a href="../index.php" class="text-seait-dark hover:text-seait-orange transition p-2">
                            <i class="fas fa-home"></i>
                        </a>
                        <a href="logout.php" class="bg-red-500 text-white p-2 rounded hover:bg-red-600 transition">
                            <i class="fas fa-sign-out-alt"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Mobile Sidebar Overlay -->
    <div id="mobile-overlay" class="fixed inset-0 bg-black bg-opacity-50 z-40 lg:hidden hidden"></div>

    <!-- Sidebar -->
    <?php include 'includes/sidebar.php'; ?>

    <!-- Scrollable Main Content -->
    <div id="main-content" class="lg:ml-64 pt-20 min-h-screen transition-all duration-300 ease-in-out">
        <div class="p-3 sm:p-4 lg:p-8">
            <div class="mb-6 sm:mb-8">
                <h1 class="text-2xl sm:text-3xl font-bold text-seait-dark mb-2">Content Approval Dashboard</h1>
                <p class="text-gray-600">Review and approve content for the SEAIT website</p>
            </div>

            <!-- Information Section -->
            <?php include 'includes/info-section.php'; ?>

            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6 mb-8">
                <div class="bg-white p-4 sm:p-6 rounded-lg shadow-md">
                    <div class="flex items-center">
                        <div class="p-3 bg-yellow-100 rounded-full">
                            <i class="fas fa-clock text-yellow-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Pending Posts</p>
                            <p class="text-2xl font-bold text-gray-900"><?php echo $pending_count; ?></p>
                        </div>
                    </div>
                </div>

                <div class="bg-white p-4 sm:p-6 rounded-lg shadow-md">
                    <div class="flex items-center">
                        <div class="p-3 bg-green-100 rounded-full">
                            <i class="fas fa-check-circle text-green-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Approved Posts</p>
                            <p class="text-2xl font-bold text-gray-900"><?php echo $approved_count; ?></p>
                        </div>
                    </div>
                </div>

                <div class="bg-white p-4 sm:p-6 rounded-lg shadow-md">
                    <div class="flex items-center">
                        <div class="p-3 bg-purple-100 rounded-full">
                            <i class="fas fa-images text-purple-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Pending Carousel</p>
                            <p class="text-2xl font-bold text-gray-900"><?php echo $pending_carousel_count; ?></p>
                        </div>
                    </div>
                </div>

                <div class="bg-white p-4 sm:p-6 rounded-lg shadow-md">
                    <div class="flex items-center">
                        <div class="p-3 bg-blue-100 rounded-full">
                            <i class="fas fa-eye text-blue-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Total Views</p>
                            <p class="text-2xl font-bold text-gray-900">1,234</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pending Posts -->
            <div class="bg-white rounded-lg shadow-md">
                <div class="p-4 sm:p-6 border-b border-gray-200">
                    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center space-y-4 sm:space-y-0">
                        <div>
                            <h3 class="text-lg font-semibold text-seait-dark">Pending for Approval</h3>
                            <p class="text-gray-600">Review and approve content before it goes live</p>
                        </div>
                    </div>
                </div>

                <div class="p-3 sm:p-6">
                    <?php if (mysqli_num_rows($pending_result) > 0): ?>
                        <div class="space-y-4 sm:space-y-6">
                            <?php while($post = mysqli_fetch_assoc($pending_result)): ?>
                            <div class="border border-gray-200 rounded-lg p-4 sm:p-6 hover:shadow-md transition">
                                <div class="flex flex-col lg:flex-row lg:justify-between lg:items-start mb-4">
                                    <div class="flex-1 mb-4 lg:mb-0">
                                        <h4 class="font-semibold text-lg sm:text-xl text-seait-dark mb-2 break-words"><?php echo htmlspecialchars($post['title']); ?></h4>
                                        <div class="flex flex-col sm:flex-row sm:items-center space-y-2 sm:space-y-0 sm:space-x-4 text-sm text-gray-600 mb-3">
                                            <span class="flex items-center">
                                                <i class="fas fa-user mr-1"></i>
                                                <span class="truncate">by <?php echo htmlspecialchars($post['first_name'] . ' ' . $post['last_name']); ?></span>
                                            </span>
                                            <span class="flex items-center">
                                                <i class="fas fa-calendar mr-1"></i>
                                                <span class="truncate"><?php echo date('M d, Y H:i', strtotime($post['created_at'])); ?></span>
                                            </span>
                                        </div>
                                        <div class="flex flex-wrap gap-2">
                                            <span class="inline-block px-3 py-1 text-xs bg-blue-100 text-blue-800 rounded">
                                                <?php echo ucfirst(htmlspecialchars($post['type'])); ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="flex flex-wrap gap-2 lg:flex-col lg:gap-2 lg:ml-4">
                                        <button onclick="approvePost(<?php echo $post['id']; ?>)"
                                                class="bg-green-500 text-white px-3 py-2 rounded text-sm hover:bg-green-600 transition">
                                            <i class="fas fa-check mr-1"></i><span class="hidden sm:inline">Approve</span>
                                        </button>
                                        <button onclick="rejectPost(<?php echo $post['id']; ?>)"
                                                class="bg-red-500 text-white px-3 py-2 rounded text-sm hover:bg-red-600 transition">
                                            <i class="fas fa-times mr-1"></i><span class="hidden sm:inline">Reject</span>
                                        </button>
                                        <button onclick="viewPost(<?php echo $post['id']; ?>)"
                                                class="bg-blue-500 text-white px-3 py-2 rounded text-sm hover:bg-blue-600 transition">
                                            <i class="fas fa-eye mr-1"></i><span class="hidden sm:inline">View</span>
                                        </button>
                                    </div>
                                </div>
                                <div class="text-gray-700">
                                    <div class="break-words">
                                        <?php echo nl2br(htmlspecialchars(substr($post['content'], 0, 200))); ?>...
                                    </div>
                                </div>
                            </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-8">
                            <i class="fas fa-check-circle text-green-500 text-4xl mb-4"></i>
                            <p class="text-gray-600">No pending posts to review</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Mobile menu functionality
        const mobileMenuButton = document.getElementById('mobile-menu-button');
        const sidebar = document.getElementById('sidebar');
        const mobileOverlay = document.getElementById('mobile-overlay');
        const closeSidebarButton = document.getElementById('close-sidebar');
        const mainContent = document.getElementById('main-content');

        function openSidebar() {
            sidebar.classList.remove('-translate-x-full');
            mobileOverlay.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }

        function closeSidebar() {
            sidebar.classList.add('-translate-x-full');
            mobileOverlay.classList.add('hidden');
            document.body.style.overflow = 'auto';
        }

        // Event listeners
        mobileMenuButton.addEventListener('click', openSidebar);
        closeSidebarButton.addEventListener('click', closeSidebar);
        mobileOverlay.addEventListener('click', closeSidebar);

        // Close sidebar when clicking on navigation links (mobile)
        const navLinks = sidebar.querySelectorAll('a');
        navLinks.forEach(link => {
            link.addEventListener('click', () => {
                if (window.innerWidth < 1024) { // lg breakpoint
                    closeSidebar();
                }
            });
        });

        // Handle window resize
        window.addEventListener('resize', () => {
            if (window.innerWidth >= 1024) {
                closeSidebar();
            }
        });

        // View post functionality
        function viewPost(postId) {
            // Navigate to the dedicated view post page
            window.location.href = `view-post.php?id=${postId}`;
        }

        // Original functions
        function approvePost(postId) {
            if (confirm('Are you sure you want to approve this post?')) {
                window.location.href = `approve-post.php?id=${postId}`;
            }
        }

        function rejectPost(postId) {
            if (confirm('Are you sure you want to reject this post?')) {
                window.location.href = `reject-post.php?id=${postId}`;
            }
        }
    </script>
</body>
</html>