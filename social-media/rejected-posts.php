<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

check_social_media_manager();

// ========================================
// CRUD FUNCTIONS - Separated from UI
// ========================================

/**
 * Get all rejected posts with author information
 * @param mysqli $conn Database connection
 * @param int $limit Number of posts to retrieve
 * @return mysqli_result|false Query result
 */
function get_rejected_posts($conn, $limit = 50) {
    $limit = (int)$limit;
    $query = "SELECT p.*, u.first_name, u.last_name, u.email,
              r.first_name as rejecter_first_name, r.last_name as rejecter_last_name
              FROM posts p
              JOIN users u ON p.author_id = u.id
              LEFT JOIN users r ON p.rejected_by = r.id
              WHERE p.status = 'rejected'
              ORDER BY p.rejected_at DESC
              LIMIT ?";
    $stmt = mysqli_prepare($conn, $query);
    if (!$stmt) {
        error_log("Database error in get_rejected_posts (prepare): " . mysqli_error($conn));
        return false;
    }

    mysqli_stmt_bind_param($stmt, "i", $limit);
    if (!mysqli_stmt_execute($stmt)) {
        error_log("Database error in get_rejected_posts (execute): " . mysqli_stmt_error($stmt));
        mysqli_stmt_close($stmt);
        return false;
    }

    $result = mysqli_stmt_get_result($stmt);
    if (!$result) {
        error_log("Database error in get_rejected_posts (get_result): " . mysqli_stmt_error($stmt));
        mysqli_stmt_close($stmt);
        return false;
    }

    mysqli_stmt_close($stmt);
    return $result;
}

/**
 * Get a single rejected post by ID
 * @param mysqli $conn Database connection
 * @param int $post_id Post ID
 * @return array|false Post data or false
 */
function get_rejected_post_by_id($conn, $post_id) {
    $post_id = (int)$post_id;
    $query = "SELECT p.*, u.first_name, u.last_name, u.email,
              r.first_name as rejecter_first_name, r.last_name as rejecter_last_name
              FROM posts p
              JOIN users u ON p.author_id = u.id
              LEFT JOIN users r ON p.rejected_by = r.id
              WHERE p.id = ? AND p.status = 'rejected'";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $post_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    return mysqli_fetch_assoc($result);
}

/**
 * Approve a rejected post (move back to approved)
 * @param mysqli $conn Database connection
 * @param int $post_id Post ID
 * @param int $approver_id User ID who approves
 * @return bool Success status
 */
function approve_rejected_post($conn, $post_id, $approver_id) {
    $post_id = (int)$post_id;
    $approver_id = (int)$approver_id;

    $query = "UPDATE posts SET
              status = 'approved',
              approved_by = ?,
              rejected_by = NULL,
              rejected_at = NULL,
              rejection_reason = NULL
              WHERE id = ? AND status = 'rejected'";

    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "ii", $approver_id, $post_id);
    return mysqli_stmt_execute($stmt);
}

/**
 * Move rejected post back to pending
 * @param mysqli $conn Database connection
 * @param int $post_id Post ID
 * @return bool Success status
 */
function move_rejected_to_pending($conn, $post_id) {
    $post_id = (int)$post_id;

    $query = "UPDATE posts SET
              status = 'pending',
              rejected_by = NULL,
              rejected_at = NULL,
              rejection_reason = NULL
              WHERE id = ? AND status = 'rejected'";

    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $post_id);
    return mysqli_stmt_execute($stmt);
}

/**
 * Delete a rejected post (soft delete)
 * @param mysqli $conn Database connection
 * @param int $post_id Post ID
 * @return bool Success status
 */
function delete_rejected_post($conn, $post_id) {
    $post_id = (int)$post_id;

    $query = "UPDATE posts SET
              status = 'deleted',
              deleted_at = NOW()
              WHERE id = ? AND status = 'rejected'";

    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $post_id);
    return mysqli_stmt_execute($stmt);
}

/**
 * Get rejected posts count
 * @param mysqli $conn Database connection
 * @return int Count of rejected posts
 */
function get_rejected_posts_count($conn) {
    $query = "SELECT COUNT(*) as total FROM posts WHERE status = 'rejected'";
    $result = mysqli_query($conn, $query);
    $row = mysqli_fetch_assoc($result);
    return (int)$row['total'];
}

/**
 * Get rejected posts statistics
 * @param mysqli $conn Database connection
 * @return array Statistics data
 */
function get_rejected_posts_statistics($conn) {
    $stats = [];

    // Rejected count
    $rejected_query = "SELECT COUNT(*) as total FROM posts WHERE status = 'rejected'";
    $rejected_result = mysqli_query($conn, $rejected_query);
    $stats['rejected'] = mysqli_fetch_assoc($rejected_result)['total'];

    // Total count
    $total_query = "SELECT COUNT(*) as total FROM posts";
    $total_result = mysqli_query($conn, $total_query);
    $stats['total'] = mysqli_fetch_assoc($total_result)['total'];

    // Recent rejections (last 7 days)
    $recent_query = "SELECT COUNT(*) as total FROM posts
                     WHERE status = 'rejected'
                     AND rejected_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
    $recent_result = mysqli_query($conn, $recent_query);
    $stats['recent'] = mysqli_fetch_assoc($recent_result)['total'];

    // Average rejection time
    $avg_query = "SELECT AVG(TIMESTAMPDIFF(MINUTE, created_at, rejected_at)) as avg_time
                  FROM posts
                  WHERE status = 'rejected' AND rejected_at IS NOT NULL";
    $avg_result = mysqli_query($conn, $avg_query);
    $avg_time = mysqli_fetch_assoc($avg_result)['avg_time'];
    $stats['avg_rejection_time'] = $avg_time ? round($avg_time, 1) : 0;

    // Posts with rejection reasons
    $with_reason_query = "SELECT COUNT(*) as total FROM posts
                          WHERE status = 'rejected' AND rejection_reason IS NOT NULL AND rejection_reason != ''";
    $with_reason_result = mysqli_query($conn, $with_reason_query);
    $stats['with_reason'] = mysqli_fetch_assoc($with_reason_result)['total'];

    return $stats;
}

// ========================================
// REQUEST HANDLING
// ========================================

$message = '';
$message_type = 'info';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $post_id = (int)$_POST['post_id'];

        switch ($_POST['action']) {
            case 'approve':
                if (approve_rejected_post($conn, $post_id, $_SESSION['user_id'])) {
                    $message = "Post approved successfully!";
                    $message_type = 'success';
                } else {
                    $message = "Error approving post.";
                    $message_type = 'error';
                }
                break;

            case 'move_to_pending':
                if (move_rejected_to_pending($conn, $post_id)) {
                    $message = "Post moved to pending successfully!";
                    $message_type = 'success';
                } else {
                    $message = "Error moving post to pending.";
                    $message_type = 'error';
                }
                break;

            case 'delete':
                if (delete_rejected_post($conn, $post_id)) {
                    $message = "Post deleted successfully!";
                    $message_type = 'success';
                } else {
                    $message = "Error deleting post.";
                    $message_type = 'error';
                }
                break;
        }
    }
}

// Get data for display
$rejected_posts = get_rejected_posts($conn);
$statistics = get_rejected_posts_statistics($conn);
$rejected_count = get_rejected_posts_count($conn);

// ========================================
// UI RENDERING
// ========================================
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rejected Posts - Social Media Manager - SEAIT</title>
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
    <style>
        @keyframes bounceIn {
            0% {
                opacity: 0;
                transform: scale(0.3);
            }
            50% {
                opacity: 1;
                transform: scale(1.05);
            }
            70% {
                transform: scale(0.9);
            }
            100% {
                opacity: 1;
                transform: scale(1);
            }
        }

        .animate-bounce-in {
            animation: bounceIn 0.6s ease-out;
        }
    </style>
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
                <h1 class="text-2xl sm:text-3xl font-bold text-seait-dark mb-2">Rejected Posts</h1>
                <p class="text-gray-600">Review and manage rejected content</p>
            </div>

            <!-- Information Section -->
            <?php include 'includes/info-section.php'; ?>

            <!-- Message Display -->
            <?php if ($message): ?>
                <?php echo display_message($message, $message_type); ?>
            <?php endif; ?>

            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6 mb-8">
                <div class="bg-white p-4 sm:p-6 rounded-lg shadow-md">
                    <div class="flex items-center">
                        <div class="p-3 bg-red-100 rounded-full">
                            <i class="fas fa-times-circle text-red-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Rejected</p>
                            <p class="text-2xl font-bold text-gray-900"><?php echo $statistics['rejected']; ?></p>
                        </div>
                    </div>
                </div>

                <div class="bg-white p-4 sm:p-6 rounded-lg shadow-md">
                    <div class="flex items-center">
                        <div class="p-3 bg-blue-100 rounded-full">
                            <i class="fas fa-calendar-week text-blue-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Recent (7 days)</p>
                            <p class="text-2xl font-bold text-gray-900"><?php echo $statistics['recent']; ?></p>
                        </div>
                    </div>
                </div>

                <div class="bg-white p-4 sm:p-6 rounded-lg shadow-md">
                    <div class="flex items-center">
                        <div class="p-3 bg-purple-100 rounded-full">
                            <i class="fas fa-clock text-purple-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Avg Rejection Time</p>
                            <p class="text-2xl font-bold text-gray-900"><?php echo $statistics['avg_rejection_time']; ?>m</p>
                        </div>
                    </div>
                </div>

                <div class="bg-white p-4 sm:p-6 rounded-lg shadow-md">
                    <div class="flex items-center">
                        <div class="p-3 bg-orange-100 rounded-full">
                            <i class="fas fa-comment text-orange-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">With Reasons</p>
                            <p class="text-2xl font-bold text-gray-900"><?php echo $statistics['with_reason']; ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Rejected Posts List -->
            <div class="bg-white rounded-lg shadow-md">
                <div class="p-4 sm:p-6 border-b border-gray-200">
                    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center space-y-4 sm:space-y-0">
                        <div>
                            <h3 class="text-lg font-semibold text-seait-dark">Rejected Content</h3>
                            <p class="text-gray-600">Review and manage rejected posts</p>
                        </div>
                        <div class="flex justify-center sm:justify-end">
                            <button onclick="refreshPage()" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600 transition">
                                <i class="fas fa-refresh mr-2"></i>Refresh
                            </button>
                        </div>
                    </div>
                </div>

                <div class="p-3 sm:p-6">
                    <?php if ($rejected_posts && mysqli_num_rows($rejected_posts) > 0): ?>
                        <div class="space-y-4 sm:space-y-6">
                            <?php while($post = mysqli_fetch_assoc($rejected_posts)): ?>
                            <div class="border border-gray-200 rounded-lg p-4 sm:p-6 hover:shadow-md transition">
                                <div class="flex flex-col lg:flex-row lg:justify-between lg:items-start mb-4">
                                    <div class="flex-1 mb-4 lg:mb-0">
                                        <h4 class="font-semibold text-lg sm:text-xl text-seait-dark mb-2 break-words"><?php echo htmlspecialchars($post['title']); ?></h4>
                                        <div class="flex flex-col sm:flex-row sm:items-center space-y-2 sm:space-y-0 sm:space-x-4 text-sm text-gray-600 mb-3">
                                            <span class="flex items-center">
                                                <i class="fas fa-user mr-1"></i>
                                                <span class="truncate"><?php echo htmlspecialchars($post['first_name'] . ' ' . $post['last_name']); ?></span>
                                            </span>
                                            <span class="flex items-center">
                                                <i class="fas fa-envelope mr-1"></i>
                                                <span class="truncate"><?php echo htmlspecialchars($post['email']); ?></span>
                                            </span>
                                            <span class="flex items-center">
                                                <i class="fas fa-calendar mr-1"></i>
                                                <span class="truncate"><?php echo date('M d, Y H:i', strtotime($post['created_at'])); ?></span>
                                            </span>
                                            <span class="flex items-center">
                                                <i class="fas fa-times-circle mr-1"></i>
                                                <span class="truncate">Rejected: <?php echo date('M d, Y H:i', strtotime($post['rejected_at'])); ?></span>
                                            </span>
                                        </div>
                                        <div class="flex flex-wrap gap-2">
                                            <span class="inline-block px-3 py-1 text-xs bg-blue-100 text-blue-800 rounded">
                                                <?php echo ucfirst(htmlspecialchars($post['type'])); ?>
                                            </span>
                                            <span class="inline-block px-3 py-1 text-xs bg-red-100 text-red-800 rounded">
                                                Rejected
                                            </span>
                                            <?php if ($post['rejecter_first_name']): ?>
                                            <span class="inline-block px-3 py-1 text-xs bg-purple-100 text-purple-800 rounded">
                                                by <?php echo htmlspecialchars($post['rejecter_first_name'] . ' ' . $post['rejecter_last_name']); ?>
                                            </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="flex flex-wrap gap-2 lg:flex-col lg:gap-2 lg:ml-4">
                                        <button onclick="approvePost(<?php echo $post['id']; ?>)"
                                                class="bg-green-500 text-white px-3 py-2 rounded hover:bg-green-600 transition text-sm">
                                            <i class="fas fa-check mr-1"></i><span class="hidden sm:inline">Approve</span>
                                        </button>
                                        <button onclick="moveToPending(<?php echo $post['id']; ?>)"
                                                class="bg-yellow-500 text-white px-3 py-2 rounded hover:bg-yellow-600 transition text-sm">
                                            <i class="fas fa-clock mr-1"></i><span class="hidden sm:inline">Move to Pending</span>
                                        </button>
                                        <button onclick="viewPost(<?php echo $post['id']; ?>)"
                                                class="bg-blue-500 text-white px-3 py-2 rounded hover:bg-blue-600 transition text-sm">
                                            <i class="fas fa-eye mr-1"></i><span class="hidden sm:inline">View</span>
                                        </button>
                                        <button onclick="deletePost(<?php echo $post['id']; ?>)"
                                                class="bg-red-500 text-white px-3 py-2 rounded hover:bg-red-600 transition text-sm">
                                            <i class="fas fa-trash mr-1"></i><span class="hidden sm:inline">Delete</span>
                                        </button>
                                    </div>
                                </div>

                                <?php if (!empty($post['rejection_reason'])): ?>
                                <div class="text-gray-700 mb-4">
                                    <h5 class="font-medium mb-2 text-red-600">Rejection Reason:</h5>
                                    <div class="bg-red-50 p-4 rounded border-l-4 border-red-500 overflow-hidden">
                                        <div class="break-words">
                                            <i class="fas fa-exclamation-triangle text-red-500 mr-2"></i>
                                            <?php echo nl2br(htmlspecialchars($post['rejection_reason'])); ?>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>

                                <div class="text-gray-700 mb-4">
                                    <h5 class="font-medium mb-2">Content Preview:</h5>
                                    <div class="bg-gray-50 p-4 rounded border-l-4 border-red-500 overflow-hidden">
                                        <div class="break-words">
                                            <?php echo nl2br(htmlspecialchars(substr($post['content'], 0, 300))); ?>
                                            <?php if (strlen($post['content']) > 300): ?>
                                                <span class="text-blue-600">...</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                                <?php if (!empty($post['image_url'])): ?>
                                <div class="mb-4">
                                    <h5 class="font-medium mb-2">Attached Image:</h5>
                                    <img src="<?php echo htmlspecialchars($post['image_url']); ?>"
                                         alt="Post image"
                                         class="max-w-full sm:max-w-xs rounded border">
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php endwhile; ?>
                        </div>
                    <?php elseif (!$rejected_posts): ?>
                        <div class="text-center py-12">
                            <i class="fas fa-exclamation-triangle text-red-500 text-6xl mb-4"></i>
                            <h3 class="text-xl font-semibold text-gray-700 mb-2">Database Error</h3>
                            <p class="text-gray-600">Unable to load rejected posts. Please try again later.</p>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-12">
                            <i class="fas fa-check-circle text-green-500 text-6xl mb-4"></i>
                            <h3 class="text-xl font-semibold text-gray-700 mb-2">No Rejected Posts</h3>
                            <p class="text-gray-600">No posts have been rejected yet</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full animate-bounce-in">
                <div class="p-6 text-center">
                    <div class="mb-4">
                        <div class="p-4 rounded-full bg-red-100 text-red-600 inline-block mb-4">
                            <i class="fas fa-exclamation-triangle text-3xl"></i>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-900 mb-2">Delete Rejected Post</h3>
                        <p class="text-gray-600 mb-4">Are you sure you want to delete this rejected post? This action cannot be undone.</p>
                        <div class="bg-red-50 border border-red-200 rounded-lg p-3 mb-4">
                            <div class="flex items-center text-red-800">
                                <i class="fas fa-exclamation-triangle mr-2"></i>
                                <span class="text-sm font-medium">Warning:</span>
                            </div>
                            <ul class="text-sm text-red-700 mt-2 text-left space-y-1">
                                <li class="flex items-center">
                                    <i class="fas fa-trash mr-2 text-red-500"></i>
                                    Post will be permanently removed
                                </li>
                                <li class="flex items-center">
                                    <i class="fas fa-eye-slash mr-2 text-red-500"></i>
                                    No longer visible to users
                                </li>
                                <li class="flex items-center">
                                    <i class="fas fa-undo mr-2 text-red-500"></i>
                                    Cannot be recovered
                                </li>
                            </ul>
                        </div>
                    </div>
                    <form id="deleteForm" method="POST" class="space-y-3">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="post_id" id="deletePostId">
                        <div class="flex justify-center space-x-3">
                            <button type="button" onclick="closeDeleteModal()"
                                    class="px-6 py-3 text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 transition-all duration-200">
                                <i class="fas fa-times mr-2"></i>Cancel
                            </button>
                            <button type="submit"
                                    class="px-6 py-3 bg-gradient-to-r from-red-500 to-red-600 text-white rounded-lg hover:from-red-600 hover:to-red-700 transition-all duration-200 font-semibold">
                                <i class="fas fa-trash mr-2"></i>Delete Permanently
                            </button>
                        </div>
                    </form>
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

        function approvePost(postId) {
            if (confirm('Are you sure you want to approve this rejected post?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="approve">
                    <input type="hidden" name="post_id" value="${postId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function moveToPending(postId) {
            if (confirm('Are you sure you want to move this post back to pending? This will clear the rejection reason.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="move_to_pending">
                    <input type="hidden" name="post_id" value="${postId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function deletePost(postId) {
            const deleteModal = document.getElementById('deleteModal');
            const postIdField = document.getElementById('deletePostId');
            if (deleteModal && postIdField) {
                postIdField.value = postId;
                deleteModal.classList.remove('hidden');
            }
        }

        function closeDeleteModal() {
            const deleteModal = document.getElementById('deleteModal');
            if (deleteModal) {
                deleteModal.classList.add('hidden');
            }
        }

        function viewPost(postId) {
            // Navigate to the dedicated view post page
            window.location.href = `view-post.php?id=${postId}`;
        }

        function refreshPage() {
            window.location.reload();
        }

        // Close modal when clicking outside
        const deleteModal = document.getElementById('deleteModal');
        if (deleteModal) {
            deleteModal.addEventListener('click', function(e) {
                if (e.target === this) {
                    closeDeleteModal();
                }
            });
        }
    </script>
</body>
</html>