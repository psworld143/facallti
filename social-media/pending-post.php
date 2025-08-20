<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

check_social_media_manager();

// ========================================
// CRUD FUNCTIONS - Separated from UI
// ========================================

/**
 * Get all pending posts with author information
 * @param mysqli $conn Database connection
 * @return mysqli_result|false Query result
 */
function get_pending_posts($conn) {
    $query = "SELECT p.*, u.first_name, u.last_name, u.email
              FROM posts p
              JOIN users u ON p.author_id = u.id
              WHERE p.status = 'pending'
              ORDER BY p.created_at DESC";
    $result = mysqli_query($conn, $query);

    // Check for query errors
    if (!$result) {
        error_log("Database error in get_pending_posts: " . mysqli_error($conn));
        return false;
    }

    return $result;
}

/**
 * Get a single pending post by ID
 * @param mysqli $conn Database connection
 * @param int $post_id Post ID
 * @return array|false Post data or false
 */
function get_pending_post_by_id($conn, $post_id) {
    $post_id = (int)$post_id;
    $query = "SELECT p.*, u.first_name, u.last_name, u.email
              FROM posts p
              JOIN users u ON p.author_id = u.id
              WHERE p.id = ? AND p.status = 'pending'";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $post_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    return mysqli_fetch_assoc($result);
}

/**
 * Approve a pending post
 * @param mysqli $conn Database connection
 * @param int $post_id Post ID
 * @param int $approver_id Approver user ID
 * @return bool Success status
 */
function approve_post($conn, $post_id, $approver_id) {
    $post_id = (int)$post_id;
    $approver_id = (int)$approver_id;

    $query = "UPDATE posts SET
              status = 'approved',
              approved_by = ?
              WHERE id = ? AND status = 'pending'";

    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "ii", $approver_id, $post_id);
    return mysqli_stmt_execute($stmt);
}

/**
 * Reject a pending post
 * @param mysqli $conn Database connection
 * @param int $post_id Post ID
 * @param int $rejecter_id Rejecter user ID
 * @param string $rejection_reason Reason for rejection
 * @return bool Success status
 */
function reject_post($conn, $post_id, $rejecter_id, $rejection_reason = '') {
    $post_id = (int)$post_id;
    $rejecter_id = (int)$rejecter_id;
    $rejection_reason = sanitize_input($rejection_reason);

    $query = "UPDATE posts SET
              status = 'rejected',
              rejected_by = ?,
              rejected_at = NOW(),
              rejection_reason = ?
              WHERE id = ? AND status = 'pending'";

    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "isi", $rejecter_id, $rejection_reason, $post_id);
    return mysqli_stmt_execute($stmt);
}

/**
 * Get pending posts count
 * @param mysqli $conn Database connection
 * @return int Count of pending posts
 */
function get_pending_posts_count($conn) {
    $query = "SELECT COUNT(*) as total FROM posts WHERE status = 'pending'";
    $result = mysqli_query($conn, $query);

    if (!$result) {
        error_log("Database error in get_pending_posts_count: " . mysqli_error($conn));
        return 0;
    }

    $row = mysqli_fetch_assoc($result);
    return (int)$row['total'];
}

/**
 * Get posts statistics
 * @param mysqli $conn Database connection
 * @return array Statistics data
 */
function get_posts_statistics($conn) {
    $stats = [];

    // Pending count
    $pending_query = "SELECT COUNT(*) as total FROM posts WHERE status = 'pending'";
    $pending_result = mysqli_query($conn, $pending_query);
    if ($pending_result) {
        $stats['pending'] = mysqli_fetch_assoc($pending_result)['total'];
    } else {
        $stats['pending'] = 0;
        error_log("Database error in get_posts_statistics (pending): " . mysqli_error($conn));
    }

    // Approved count
    $approved_query = "SELECT COUNT(*) as total FROM posts WHERE status = 'approved'";
    $approved_result = mysqli_query($conn, $approved_query);
    if ($approved_result) {
        $stats['approved'] = mysqli_fetch_assoc($approved_result)['total'];
    } else {
        $stats['approved'] = 0;
        error_log("Database error in get_posts_statistics (approved): " . mysqli_error($conn));
    }

    // Rejected count
    $rejected_query = "SELECT COUNT(*) as total FROM posts WHERE status = 'rejected'";
    $rejected_result = mysqli_query($conn, $rejected_query);
    if ($rejected_result) {
        $stats['rejected'] = mysqli_fetch_assoc($rejected_result)['total'];
    } else {
        $stats['rejected'] = 0;
        error_log("Database error in get_posts_statistics (rejected): " . mysqli_error($conn));
    }

    // Total count
    $total_query = "SELECT COUNT(*) as total FROM posts";
    $total_result = mysqli_query($conn, $total_query);
    if ($total_result) {
        $stats['total'] = mysqli_fetch_assoc($total_result)['total'];
    } else {
        $stats['total'] = 0;
        error_log("Database error in get_posts_statistics (total): " . mysqli_error($conn));
    }

    return $stats;
}

/**
 * Delete a pending post (soft delete)
 * @param mysqli $conn Database connection
 * @param int $post_id Post ID
 * @return bool Success status
 */
function delete_pending_post($conn, $post_id) {
    $post_id = (int)$post_id;

    $query = "UPDATE posts SET
              status = 'deleted',
              deleted_at = NOW()
              WHERE id = ? AND status = 'pending'";

    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $post_id);
    return mysqli_stmt_execute($stmt);
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
                if (approve_post($conn, $post_id, $_SESSION['user_id'])) {
                    $message = "Post approved successfully!";
                    $message_type = 'success';
                } else {
                    $message = "Error approving post.";
                    $message_type = 'error';
                }
                break;

            case 'reject':
                $rejection_reason = isset($_POST['rejection_reason']) ? $_POST['rejection_reason'] : '';
                if (reject_post($conn, $post_id, $_SESSION['user_id'], $rejection_reason)) {
                    $message = "Post rejected successfully!";
                    $message_type = 'success';
                } else {
                    $message = "Error rejecting post.";
                    $message_type = 'error';
                }
                break;

            case 'delete':
                if (delete_pending_post($conn, $post_id)) {
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
$pending_posts = get_pending_posts($conn);
$statistics = get_posts_statistics($conn);
$pending_count = get_pending_posts_count($conn);

// ========================================
// UI RENDERING
// ========================================
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pending Posts - Social Media Manager - SEAIT</title>
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
                <h1 class="text-2xl sm:text-3xl font-bold text-seait-dark mb-2">Pending Posts</h1>
                <p class="text-gray-600">Review and manage content awaiting approval</p>
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
                        <div class="p-3 bg-yellow-100 rounded-full">
                            <i class="fas fa-clock text-yellow-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Pending</p>
                            <p class="text-2xl font-bold text-gray-900"><?php echo $statistics['pending']; ?></p>
                        </div>
                    </div>
                </div>

                <div class="bg-white p-4 sm:p-6 rounded-lg shadow-md">
                    <div class="flex items-center">
                        <div class="p-3 bg-green-100 rounded-full">
                            <i class="fas fa-check-circle text-green-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Approved</p>
                            <p class="text-2xl font-bold text-gray-900"><?php echo $statistics['approved']; ?></p>
                        </div>
                    </div>
                </div>

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
                            <i class="fas fa-file-alt text-blue-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Total</p>
                            <p class="text-2xl font-bold text-gray-900"><?php echo $statistics['total']; ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pending Posts List -->
            <div class="bg-white rounded-lg shadow-md">
                <div class="p-4 sm:p-6 border-b border-gray-200">
                    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center space-y-4 sm:space-y-0">
                        <div>
                            <h3 class="text-lg font-semibold text-seait-dark">Pending for Approval</h3>
                            <p class="text-gray-600">Review and approve content before it goes live</p>
                        </div>
                        <div class="flex justify-center sm:justify-end">
                            <button onclick="refreshPage()" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600 transition">
                                <i class="fas fa-refresh mr-2"></i>Refresh
                            </button>
                        </div>
                    </div>
                </div>

                <div class="p-3 sm:p-6">
                    <?php if ($pending_posts && mysqli_num_rows($pending_posts) > 0): ?>
                        <div class="space-y-4 sm:space-y-6">
                            <?php while($post = mysqli_fetch_assoc($pending_posts)): ?>
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
                                        </div>
                                        <div class="flex flex-wrap gap-2">
                                            <span class="inline-block px-3 py-1 text-xs bg-blue-100 text-blue-800 rounded">
                                                <?php echo ucfirst(htmlspecialchars($post['type'])); ?>
                                            </span>
                                            <span class="inline-block px-3 py-1 text-xs bg-yellow-100 text-yellow-800 rounded">
                                                Pending
                                            </span>
                                        </div>
                                    </div>
                                    <div class="flex flex-wrap gap-2 lg:flex-col lg:gap-2 lg:ml-4">
                                        <button onclick="openApproveModal(<?php echo $post['id']; ?>)"
                                                class="bg-green-500 text-white px-3 py-2 rounded hover:bg-green-600 transition text-sm">
                                            <i class="fas fa-check mr-1"></i><span class="hidden sm:inline">Approve</span>
                                        </button>
                                        <button onclick="rejectPost(<?php echo $post['id']; ?>)"
                                                class="bg-red-500 text-white px-3 py-2 rounded hover:bg-red-600 transition text-sm">
                                            <i class="fas fa-times mr-1"></i><span class="hidden sm:inline">Reject</span>
                                        </button>
                                        <button onclick="viewPost(<?php echo $post['id']; ?>)"
                                                class="bg-blue-500 text-white px-3 py-2 rounded hover:bg-blue-600 transition text-sm">
                                            <i class="fas fa-eye mr-1"></i><span class="hidden sm:inline">View</span>
                                        </button>
                                        <button onclick="deletePost(<?php echo $post['id']; ?>)"
                                                class="bg-gray-500 text-white px-3 py-2 rounded hover:bg-gray-600 transition text-sm">
                                            <i class="fas fa-trash mr-1"></i><span class="hidden sm:inline">Delete</span>
                                        </button>
                                    </div>
                                </div>

                                <div class="text-gray-700 mb-4">
                                    <h5 class="font-medium mb-2">Content Preview:</h5>
                                    <div class="bg-gray-50 p-4 rounded border-l-4 border-blue-500 overflow-hidden">
                                        <div class="break-words">
                                            <?php
                                                $allowed_tags = '<b><i><strong><em><ul><ol><li><p><br><a>'; // allow basic formatting
                                                $preview = strip_tags($post['content'], $allowed_tags);
                                                if (mb_strlen($preview) > 300) {
                                                    echo mb_substr($preview, 0, 300) . '<span class="text-blue-600">...</span>';
                                                } else {
                                                    echo $preview;
                                                }
                                            ?>
                                        </div>
                                    </div>
                                </div>

                                <?php if (!empty($post['image_url'])): ?>
                                <div class="mb-4">
                                    <h5 class="font-medium mb-2">Attached Image:</h5>
                                    <img src="../<?php echo htmlspecialchars($post['image_url']); ?>"
                                         alt="Post image"
                                         class="max-w-full sm:max-w-xs rounded border">
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php endwhile; ?>
                        </div>
                    <?php elseif ($pending_posts === false): ?>
                        <div class="text-center py-12">
                            <i class="fas fa-exclamation-triangle text-red-500 text-6xl mb-4"></i>
                            <h3 class="text-xl font-semibold text-gray-700 mb-2">Database Error</h3>
                            <p class="text-gray-600">Unable to load pending posts. Please try again later.</p>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-12">
                            <i class="fas fa-check-circle text-green-500 text-6xl mb-4"></i>
                            <h3 class="text-xl font-semibold text-gray-700 mb-2">No Pending Posts</h3>
                            <p class="text-gray-600">All posts have been reviewed and processed</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Rejection Modal -->
    <div id="rejectionModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-md w-full">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Reject Post</h3>
                    <form id="rejectionForm" method="POST">
                        <input type="hidden" name="action" value="reject">
                        <input type="hidden" name="post_id" id="rejectPostId">
                        <div class="mb-4">
                            <label for="rejection_reason" class="block text-sm font-medium text-gray-700 mb-2">
                                Reason for Rejection (Optional)
                            </label>
                            <textarea id="rejection_reason" name="rejection_reason" rows="4"
                                      class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                      placeholder="Please provide a reason for rejection..."></textarea>
                        </div>
                        <div class="flex justify-end space-x-3">
                            <button type="button" onclick="closeRejectionModal()"
                                    class="px-4 py-2 text-gray-600 border border-gray-300 rounded hover:bg-gray-50">
                                Cancel
                            </button>
                            <button type="submit"
                                    class="px-4 py-2 bg-red-500 text-white rounded hover:bg-red-600">
                                Reject Post
                            </button>
                        </div>
                    </form>
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
                        <h3 class="text-xl font-semibold text-gray-900 mb-2">Delete Pending Post</h3>
                        <p class="text-gray-600 mb-4">Are you sure you want to delete this pending post? This action cannot be undone.</p>
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
                    <div class="flex justify-center space-x-3">
                        <input type="hidden" id="deletePostId" value="">
                        <button type="button" onclick="closeDeleteModal()"
                                class="px-6 py-3 text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 transition-all duration-200">
                            <i class="fas fa-times mr-2"></i>Cancel
                        </button>
                        <button type="button" onclick="confirmDelete()"
                                class="px-6 py-3 bg-gradient-to-r from-red-500 to-red-600 text-white rounded-lg hover:from-red-600 hover:to-red-700 transition-all duration-200 font-semibold">
                            <i class="fas fa-trash mr-2"></i>Delete Permanently
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Approve Confirmation Modal -->
    <div id="approveModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full animate-bounce-in">
                <div class="p-6 text-center">
                    <div class="mb-4">
                        <div class="p-4 rounded-full bg-green-100 text-green-600 inline-block mb-4">
                            <i class="fas fa-check-circle text-3xl"></i>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-900 mb-2">Approve Post</h3>
                        <p class="text-gray-600 mb-4">Are you sure you want to approve this pending post? This action will publish the post on the website.</p>
                        <div class="bg-green-50 border border-green-200 rounded-lg p-3 mb-4">
                            <div class="flex items-center text-green-800">
                                <i class="fas fa-check mr-2"></i>
                                <span class="text-sm font-medium">Confirmation:</span>
                            </div>
                            <ul class="text-sm text-green-700 mt-2 text-left space-y-1">
                                <li class="flex items-center">
                                    <i class="fas fa-globe mr-2 text-green-500"></i>
                                    Post will be visible to users
                                </li>
                                <li class="flex items-center">
                                    <i class="fas fa-check-circle mr-2 text-green-500"></i>
                                    Marked as approved
                                </li>
                            </ul>
                        </div>
                    </div>
                    <div class="flex justify-center space-x-3">
                        <input type="hidden" id="approvePostId" value="">
                        <button type="button" onclick="closeApproveModal()"
                                class="px-6 py-3 text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 transition-all duration-200">
                            <i class="fas fa-times mr-2"></i>Cancel
                        </button>
                        <button type="button" onclick="confirmApprove()"
                                class="px-6 py-3 bg-gradient-to-r from-green-500 to-green-600 text-white rounded-lg hover:from-green-600 hover:to-green-700 transition-all duration-200 font-semibold">
                            <i class="fas fa-check mr-2"></i>Approve
                        </button>
                    </div>
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
            if (confirm('Are you sure you want to approve this post?')) {
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

        function rejectPost(postId) {
            document.getElementById('rejectPostId').value = postId;
            document.getElementById('rejectionModal').classList.remove('hidden');
        }

        function closeRejectionModal() {
            document.getElementById('rejectionModal').classList.add('hidden');
            document.getElementById('rejection_reason').value = '';
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

        function confirmDelete() {
            const postId = document.getElementById('deletePostId').value;
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="post_id" value="${postId}">
            `;
            document.body.appendChild(form);
            form.submit();
        }

        function viewPost(postId) {
            // Navigate to the dedicated view post page
            window.location.href = `view-post.php?id=${postId}`;
        }

        function refreshPage() {
            window.location.reload();
        }

        function openApproveModal(postId) {
            document.getElementById('approvePostId').value = postId;
            document.getElementById('approveModal').classList.remove('hidden');
        }
        function closeApproveModal() {
            document.getElementById('approveModal').classList.add('hidden');
            document.getElementById('approvePostId').value = '';
        }
        function confirmApprove() {
            const postId = document.getElementById('approvePostId').value;
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="approve">
                <input type="hidden" name="post_id" value="${postId}">
            `;
            document.body.appendChild(form);
            form.submit();
        }

        // Close modal when clicking outside
        document.getElementById('rejectionModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeRejectionModal();
            }
        });

        document.getElementById('deleteModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeDeleteModal();
            }
        });
        document.getElementById('approveModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeApproveModal();
            }
        });
    </script>
</body>
</html>