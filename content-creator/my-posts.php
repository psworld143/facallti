<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and is a content creator
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'content_creator') {
    header('Location: ../login.php');
    exit();
}

$message = '';
$error = '';

// Handle post deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $post_id = (int)$_POST['post_id'];

    // Verify the post belongs to the current user
    $verify_query = "SELECT id FROM posts WHERE id = ? AND author_id = ?";
    $stmt = mysqli_prepare($conn, $verify_query);
    mysqli_stmt_bind_param($stmt, "ii", $post_id, $_SESSION['user_id']);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if (mysqli_num_rows($result) > 0) {
        $delete_query = "DELETE FROM posts WHERE id = ? AND author_id = ?";
        $stmt = mysqli_prepare($conn, $delete_query);
        mysqli_stmt_bind_param($stmt, "ii", $post_id, $_SESSION['user_id']);

        if (mysqli_stmt_execute($stmt)) {
            $message = 'Post deleted successfully!';
        } else {
            $error = 'Failed to delete post.';
        }
    } else {
        $error = 'Post not found or you do not have permission to delete it.';
    }
}

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$type_filter = isset($_GET['type']) ? $_GET['type'] : '';

// Build query with filters
$where_conditions = ["author_id = ?"];
$params = [$_SESSION['user_id']];
$param_types = "i";

if ($status_filter) {
    $where_conditions[] = "status = ?";
    $params[] = $status_filter;
    $param_types .= "s";
}

if ($type_filter) {
    $where_conditions[] = "type = ?";
    $params[] = $type_filter;
    $param_types .= "s";
}

$where_clause = implode(" AND ", $where_conditions);
$query = "SELECT * FROM posts WHERE $where_clause ORDER BY created_at DESC";

$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, $param_types, ...$params);
mysqli_stmt_execute($stmt);
$posts_result = mysqli_stmt_get_result($stmt);

// Get statistics
$stats_query = "SELECT
    COUNT(*) as total,
    SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as drafts,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
FROM posts WHERE author_id = ?";
$stmt = mysqli_prepare($conn, $stats_query);
mysqli_stmt_bind_param($stmt, "i", $_SESSION['user_id']);
mysqli_stmt_execute($stmt);
$stats_result = mysqli_stmt_get_result($stmt);
$stats = mysqli_fetch_assoc($stats_result);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Posts - SEAIT Content Creator</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'seait-orange': '#FF6B35',
                        'seait-dark': '#2C3E50'
                    },
                    fontFamily: {
                        'poppins': ['Poppins', 'sans-serif']
                    }
                }
            }
        }
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
        }
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
    <!-- Navigation -->
    <nav class="bg-white fixed top-0 left-0 right-0 z-50 shadow-sm">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between items-center py-4">
                <div class="flex items-center space-x-4">
                    <img src="../assets/images/seait-logo.png" alt="SEAIT Logo" class="h-10 w-auto">
                    <div>
                        <h1 class="text-xl font-bold text-seait-dark">SEAIT Content Creator</h1>
                        <p class="text-sm text-gray-600">Welcome, <?php echo $_SESSION['first_name']; ?></p>
                    </div>
                </div>

                <div class="flex items-center space-x-4">
                    <a href="../index.php" class="text-seait-dark hover:text-seait-orange transition">
                        <i class="fas fa-home mr-2"></i><span class="hidden sm:inline">View Site</span>
                    </a>
                    <a href="logout.php" class="bg-red-500 text-white px-4 py-2 rounded hover:bg-red-600 transition">
                        <i class="fas fa-sign-out-alt mr-2"></i><span class="hidden sm:inline">Logout</span>
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="flex min-h-screen pt-16">
        <!-- Sidebar -->
        <?php include 'includes/sidebar.php'; ?>

        <!-- Main Content -->
        <div class="flex-1 lg:ml-72 overflow-y-auto h-screen">
            <div class="p-4 lg:p-8">
                <div class="mb-8">
                    <h1 class="text-2xl lg:text-3xl font-bold text-seait-dark mb-2">My Posts</h1>
                    <p class="text-gray-600">Manage all your published and draft posts</p>
                </div>

                <!-- Information Section -->
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 lg:p-6 mb-6 lg:mb-8">
                    <div class="flex items-start">
                        <div class="flex-shrink-0">
                            <i class="fas fa-info-circle text-blue-400 text-lg"></i>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-blue-800 mb-2">Post Management</h3>
                            <div class="text-sm text-blue-700 space-y-1">
                                <p><strong>Overview:</strong> View and manage all your posts including drafts, pending reviews, and published content.</p>
                                <p><strong>Statistics:</strong> Track your content creation progress with real-time statistics.</p>
                                <p><strong>Actions:</strong> Edit, delete, or view your posts. Published posts can be viewed on the website.</p>
                                <p><strong>Organization:</strong> Posts are organized by status and type for easy management and tracking.</p>
                            </div>
                        </div>
                    </div>
                </div>

            <?php if ($message): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-8">
                <div class="bg-white p-4 rounded-lg shadow-md">
                    <div class="flex items-center">
                        <div class="p-2 bg-blue-100 rounded-full">
                            <i class="fas fa-newspaper text-blue-600"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-gray-600">Total</p>
                            <p class="text-xl font-bold text-gray-900"><?php echo $stats['total']; ?></p>
                        </div>
                    </div>
                </div>
                <div class="bg-white p-4 rounded-lg shadow-md">
                    <div class="flex items-center">
                        <div class="p-2 bg-gray-100 rounded-full">
                            <i class="fas fa-edit text-gray-600"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-gray-600">Drafts</p>
                            <p class="text-xl font-bold text-gray-900"><?php echo $stats['drafts']; ?></p>
                        </div>
                    </div>
                </div>
                <div class="bg-white p-4 rounded-lg shadow-md">
                    <div class="flex items-center">
                        <div class="p-2 bg-yellow-100 rounded-full">
                            <i class="fas fa-clock text-yellow-600"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-gray-600">Pending</p>
                            <p class="text-xl font-bold text-gray-900"><?php echo $stats['pending']; ?></p>
                        </div>
                    </div>
                </div>
                <div class="bg-white p-4 rounded-lg shadow-md">
                    <div class="flex items-center">
                        <div class="p-2 bg-green-100 rounded-full">
                            <i class="fas fa-check text-green-600"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-gray-600">Approved</p>
                            <p class="text-xl font-bold text-gray-900"><?php echo $stats['approved']; ?></p>
                        </div>
                    </div>
                </div>
                <div class="bg-white p-4 rounded-lg shadow-md">
                    <div class="flex items-center">
                        <div class="p-2 bg-red-100 rounded-full">
                            <i class="fas fa-times text-red-600"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-gray-600">Rejected</p>
                            <p class="text-xl font-bold text-gray-900"><?php echo $stats['rejected']; ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <h3 class="text-lg font-semibold text-seait-dark mb-4">Filters</h3>
                <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                        <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                            <option value="">All Statuses</option>
                            <option value="draft" <?php echo $status_filter === 'draft' ? 'selected' : ''; ?>>Draft</option>
                            <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                            <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Type</label>
                        <select name="type" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                            <option value="">All Types</option>
                            <option value="news" <?php echo $type_filter === 'news' ? 'selected' : ''; ?>>News</option>
                            <option value="announcement" <?php echo $type_filter === 'announcement' ? 'selected' : ''; ?>>Announcement</option>
                            <option value="hiring" <?php echo $type_filter === 'hiring' ? 'selected' : ''; ?>>Hiring</option>
                            <option value="event" <?php echo $type_filter === 'event' ? 'selected' : ''; ?>>Event</option>
                            <option value="article" <?php echo $type_filter === 'article' ? 'selected' : ''; ?>>Article</option>
                        </select>
                    </div>
                    <div class="flex items-end">
                        <button type="submit" class="bg-seait-orange text-white px-6 py-2 rounded-md hover:bg-orange-600 transition">
                            <i class="fas fa-filter mr-2"></i>Filter
                        </button>
                        <a href="my-posts.php" class="ml-2 bg-gray-300 text-gray-700 px-6 py-2 rounded-md hover:bg-gray-400 transition">
                            <i class="fas fa-times mr-2"></i>Clear
                        </a>
                    </div>
                </form>
            </div>

            <!-- Posts List -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="p-6 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-seait-dark">Posts (<?php echo mysqli_num_rows($posts_result); ?>)</h3>
                </div>

                <!-- Edit Functionality Information -->
                <div class="p-4 bg-blue-50 border-b border-blue-200">
                    <div class="flex items-start">
                        <div class="flex-shrink-0">
                            <i class="fas fa-info-circle text-blue-400 text-lg"></i>
                        </div>
                        <div class="ml-3">
                            <h4 class="text-sm font-medium text-blue-800 mb-1">Edit Functionality</h4>
                            <div class="text-xs text-blue-700 space-y-1">
                                <p><strong>Draft Posts:</strong> Can be edited and saved as drafts or submitted for review.</p>
                                <p><strong>Pending Posts:</strong> Can be edited and will remain in pending status.</p>
                                <p><strong>Approved Posts:</strong> Can be edited but will be marked for re-approval.</p>
                                <p><strong>Rejected Posts:</strong> Can be edited and will be submitted for review again.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="p-6">
                    <?php if (mysqli_num_rows($posts_result) > 0): ?>
                        <div class="space-y-4">
                            <?php while($post = mysqli_fetch_assoc($posts_result)): ?>
                            <div class="border border-gray-200 rounded-lg p-6 hover:shadow-md transition">
                                <div class="flex justify-between items-start mb-4">
                                    <div class="flex-1">
                                        <div class="flex items-center space-x-3 mb-2">
                                            <h4 class="text-lg font-semibold text-gray-900"><?php echo htmlspecialchars($post['title']); ?></h4>
                                            <span class="px-2 py-1 text-xs rounded-full <?php
                                                echo $post['status'] == 'approved' ? 'bg-green-100 text-green-800' :
                                                    ($post['status'] == 'pending' ? 'bg-yellow-100 text-yellow-800' :
                                                    ($post['status'] == 'rejected' ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-800'));
                                            ?>">
                                                <?php echo ucfirst($post['status']); ?>
                                            </span>
                                            <span class="px-2 py-1 text-xs bg-blue-100 text-blue-800 rounded-full">
                                                <?php echo ucfirst($post['type']); ?>
                                            </span>
                                        </div>
                                        <p class="text-sm text-gray-600 mb-2">
                                            <?php echo htmlspecialchars($post['author'] ?? ($_SESSION['first_name'] . ' ' . $_SESSION['last_name'])); ?> |
                                            Created: <?php echo date('M d, Y H:i', strtotime($post['created_at'])); ?>
                                            <?php if ($post['updated_at'] != $post['created_at']): ?>
                                                | Updated: <?php echo date('M d, Y H:i', strtotime($post['updated_at'])); ?>
                                            <?php endif; ?>
                                        </p>
                                                                                 <div class="text-gray-700 prose prose-sm max-w-none">
                                             <?php
                                             // Display HTML content safely, but limit length for preview
                                             $content = strip_tags($post['content']);
                                             echo htmlspecialchars(substr($content, 0, 200)) . (strlen($content) > 200 ? '...' : '');
                                             ?>
                                         </div>
                                         
                                                                                   <?php 
                                          $additional_images = [];
                                          if (!empty($post['additional_image_url'])) {
                                              $decoded = json_decode($post['additional_image_url'], true);
                                              if (json_last_error() === JSON_ERROR_NONE) {
                                                  $additional_images = $decoded;
                                              } else {
                                                  $additional_images = [$post['additional_image_url']];
                                              }
                                          }
                                          ?>
                                          <?php if (!empty($additional_images)): ?>
                                          <div class="mt-3">
                                              <div class="text-xs text-gray-500 mb-2">Additional Images (<?php echo count($additional_images); ?>):</div>
                                              <div class="grid grid-cols-3 gap-1">
                                                  <?php foreach (array_slice($additional_images, 0, 3) as $index => $image_url): ?>
                                                      <img src="../<?php echo htmlspecialchars($image_url); ?>" 
                                                           alt="Additional Image <?php echo $index + 1; ?>" 
                                                           class="w-full h-16 object-cover rounded border">
                                                  <?php endforeach; ?>
                                                  <?php if (count($additional_images) > 3): ?>
                                                      <div class="w-full h-16 bg-gray-100 rounded border flex items-center justify-center">
                                                          <span class="text-xs text-gray-500">+<?php echo count($additional_images) - 3; ?> more</span>
                                                      </div>
                                                  <?php endif; ?>
                                              </div>
                                          </div>
                                          <?php endif; ?>

                                        <!-- Edit Status Information -->
                                        <?php if ($post['status'] === 'approved'): ?>
                                        <div class="mt-3 p-2 bg-yellow-50 border border-yellow-200 rounded text-xs text-yellow-800">
                                            <i class="fas fa-info-circle mr-1"></i>
                                            <strong>Note:</strong> Editing this approved post will mark it for re-approval.
                                        </div>
                                        <?php elseif ($post['status'] === 'pending'): ?>
                                        <div class="mt-3 p-2 bg-blue-50 border border-blue-200 rounded text-xs text-blue-800">
                                            <i class="fas fa-info-circle mr-1"></i>
                                            <strong>Note:</strong> Editing this pending post will keep it in pending status.
                                        </div>
                                        <?php elseif ($post['status'] === 'rejected'): ?>
                                        <div class="mt-3 p-2 bg-orange-50 border border-orange-200 rounded text-xs text-orange-800">
                                            <i class="fas fa-info-circle mr-1"></i>
                                            <strong>Note:</strong> Editing this rejected post will submit it for review again.
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex space-x-2 ml-4">
                                        <a href="edit-post.php?id=<?php echo $post['id']; ?>"
                                           class="bg-blue-500 text-white px-3 py-1 rounded text-sm hover:bg-blue-600 transition">
                                            <i class="fas fa-edit mr-1"></i>Edit
                                        </a>
                                        <button onclick="deletePost(<?php echo $post['id']; ?>)"
                                                class="bg-red-500 text-white px-3 py-1 rounded text-sm hover:bg-red-600 transition">
                                            <i class="fas fa-trash mr-1"></i>Delete
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-12">
                            <i class="fas fa-newspaper text-gray-400 text-4xl mb-4"></i>
                            <p class="text-gray-600 mb-4">No posts found matching your filters.</p>
                            <a href="create-post.php" class="inline-block bg-seait-orange text-white px-6 py-2 rounded hover:bg-orange-600 transition">
                                <i class="fas fa-plus mr-2"></i>Create Your First Post
                            </a>
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
                        <h3 class="text-xl font-semibold text-gray-900 mb-2">Delete Your Post</h3>
                        <p class="text-gray-600 mb-4">Are you sure you want to delete this post? This action cannot be undone.</p>
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
                    <form method="POST" class="space-y-3">
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