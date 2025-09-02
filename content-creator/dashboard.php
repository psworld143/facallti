<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

check_content_creator();

// Get user's posts
$user_posts_query = "SELECT * FROM posts WHERE author_id = ? ORDER BY created_at DESC";
$stmt = mysqli_prepare($conn, $user_posts_query);
mysqli_stmt_bind_param($stmt, "i", $_SESSION['user_id']);
mysqli_stmt_execute($stmt);
$user_posts_result = mysqli_stmt_get_result($stmt);

// Get statistics
$draft_count_query = "SELECT COUNT(*) as total FROM posts WHERE author_id = ? AND status = 'draft'";
$stmt = mysqli_prepare($conn, $draft_count_query);
mysqli_stmt_bind_param($stmt, "i", $_SESSION['user_id']);
mysqli_stmt_execute($stmt);
$draft_result = mysqli_stmt_get_result($stmt);
$draft_count = mysqli_fetch_assoc($draft_result)['total'];

$pending_count_query = "SELECT COUNT(*) as total FROM posts WHERE author_id = ? AND status = 'pending'";
$stmt = mysqli_prepare($conn, $pending_count_query);
mysqli_stmt_bind_param($stmt, "i", $_SESSION['user_id']);
mysqli_stmt_execute($stmt);
$pending_result = mysqli_stmt_get_result($stmt);
$pending_count = mysqli_fetch_assoc($pending_result)['total'];

$approved_count_query = "SELECT COUNT(*) as total FROM posts WHERE author_id = ? AND status = 'approved'";
$stmt = mysqli_prepare($conn, $approved_count_query);
mysqli_stmt_bind_param($stmt, "i", $_SESSION['user_id']);
mysqli_stmt_execute($stmt);
$approved_result = mysqli_stmt_get_result($stmt);
$approved_count = mysqli_fetch_assoc($approved_result)['total'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - SEAIT Content Creator</title>
    <link rel="icon" type="image/png" href="../assets/images/seait-logo.png">
    <link rel="shortcut icon" type="image/png" href="../assets/images/seait-logo.png">
    <link rel="apple-touch-icon" type="image/png" href="../assets/images/seait-logo.png">
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
                    <h1 class="text-2xl lg:text-3xl font-bold text-seait-dark mb-2">Content Creator Dashboard</h1>
                    <p class="text-gray-600">Create and manage your content for the SEAIT website</p>
                </div>

                <!-- Information Section -->
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 lg:p-6 mb-6 lg:mb-8">
                    <div class="flex items-start">
                        <div class="flex-shrink-0">
                            <i class="fas fa-info-circle text-blue-400 text-lg"></i>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-blue-800 mb-2">Content Creator Dashboard</h3>
                            <div class="text-sm text-blue-700 space-y-1">
                                <p><strong>Overview:</strong> Welcome to your content management dashboard. Here you can create, edit, and manage all website content.</p>
                                <p><strong>Quick Actions:</strong> Use the quick action buttons to create new posts, manage colleges and courses, or access your drafts.</p>
                                <p><strong>Statistics:</strong> Monitor your content status with real-time statistics showing drafts, pending reviews, and published posts.</p>
                                <p><strong>Guidelines:</strong> Follow the content guidelines to ensure your posts meet SEAIT's quality standards and branding requirements.</p>
                            </div>
                        </div>
                    </div>
                </div>

            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 lg:gap-6 mb-8">
                <div class="bg-white p-4 lg:p-6 rounded-lg shadow-md">
                    <div class="flex items-center">
                        <div class="p-2 lg:p-3 bg-blue-100 rounded-full">
                            <i class="fas fa-edit text-blue-600 text-lg lg:text-xl"></i>
                        </div>
                        <div class="ml-3 lg:ml-4">
                            <p class="text-xs lg:text-sm font-medium text-gray-600">Drafts</p>
                            <p class="text-xl lg:text-2xl font-bold text-gray-900"><?php echo $draft_count; ?></p>
                        </div>
                    </div>
                </div>

                <div class="bg-white p-4 lg:p-6 rounded-lg shadow-md">
                    <div class="flex items-center">
                        <div class="p-2 lg:p-3 bg-yellow-100 rounded-full">
                            <i class="fas fa-clock text-yellow-600 text-lg lg:text-xl"></i>
                        </div>
                        <div class="ml-3 lg:ml-4">
                            <p class="text-xs lg:text-sm font-medium text-gray-600">Pending Review</p>
                            <p class="text-xl lg:text-2xl font-bold text-gray-900"><?php echo $pending_count; ?></p>
                        </div>
                    </div>
                </div>

                <div class="bg-white p-4 lg:p-6 rounded-lg shadow-md sm:col-span-2 lg:col-span-1">
                    <div class="flex items-center">
                        <div class="p-2 lg:p-3 bg-green-100 rounded-full">
                            <i class="fas fa-check-circle text-green-600 text-lg lg:text-xl"></i>
                        </div>
                        <div class="ml-3 lg:ml-4">
                            <p class="text-xs lg:text-sm font-medium text-gray-600">Published</p>
                            <p class="text-xl lg:text-2xl font-bold text-gray-900"><?php echo $approved_count; ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 lg:gap-8 mb-8">
                <div class="bg-white p-4 lg:p-6 rounded-lg shadow-md">
                    <h3 class="text-base lg:text-lg font-semibold mb-4">Quick Actions</h3>
                    <div class="space-y-3">
                        <a href="create-post.php" class="flex items-center p-3 bg-seait-light rounded hover:bg-orange-100 transition">
                            <i class="fas fa-plus text-seait-orange mr-3"></i>
                            <span class="text-sm lg:text-base">Create New Post</span>
                        </a>
                        <a href="manage-colleges.php" class="flex items-center p-3 bg-seait-light rounded hover:bg-orange-100 transition">
                            <i class="fas fa-university text-seait-orange mr-3"></i>
                            <span class="text-sm lg:text-base">Manage Colleges</span>
                        </a>
                        <a href="manage-course-details.php" class="flex items-center p-3 bg-seait-light rounded hover:bg-orange-100 transition">
                            <i class="fas fa-graduation-cap text-seait-orange mr-3"></i>
                            <span class="text-sm lg:text-base">Manage Course</span>
                        </a>
                        <a href="drafts.php" class="flex items-center p-3 bg-gray-50 rounded hover:bg-gray-100 transition">
                            <i class="fas fa-edit text-seait-orange mr-3"></i>
                            <span class="text-sm lg:text-base">Continue Draft</span>
                        </a>
                        <a href="my-posts.php" class="flex items-center p-3 bg-gray-50 rounded hover:bg-gray-100 transition">
                            <i class="fas fa-newspaper text-seait-orange mr-3"></i>
                            <span class="text-sm lg:text-base">View My Posts</span>
                        </a>
                    </div>
                </div>

                <div class="bg-white p-4 lg:p-6 rounded-lg shadow-md">
                    <h3 class="text-base lg:text-lg font-semibold mb-4">Content Guidelines</h3>
                    <div class="space-y-3 text-xs lg:text-sm text-gray-600">
                        <div class="flex items-start">
                            <i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i>
                            <span>All content must be reviewed by Social Media Manager</span>
                        </div>
                        <div class="flex items-start">
                            <i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i>
                            <span>Use clear, professional language</span>
                        </div>
                        <div class="flex items-start">
                            <i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i>
                            <span>Include relevant images when possible</span>
                        </div>
                        <div class="flex items-start">
                            <i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i>
                            <span>Follow SEAIT branding guidelines</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Posts -->
            <div class="bg-white rounded-lg shadow-md">
                <div class="p-6 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-seait-dark">My Recent Posts</h3>
                    <p class="text-gray-600">Your latest content submissions</p>
                </div>

                <div class="p-6">
                    <?php if (mysqli_num_rows($user_posts_result) > 0): ?>
                        <div class="space-y-4">
                            <?php while($post = mysqli_fetch_assoc($user_posts_result)): ?>
                            <div class="border border-gray-200 rounded-lg p-4">
                                <div class="flex justify-between items-start mb-3">
                                    <div>
                                        <h4 class="font-semibold text-lg"><?php echo $post['title']; ?></h4>
                                        <p class="text-sm text-gray-600">
                                            <?php echo date('M d, Y H:i', strtotime($post['created_at'])); ?>
                                        </p>
                                        <span class="inline-block px-2 py-1 text-xs rounded mt-1 <?php
                                            echo $post['status'] == 'approved' ? 'bg-green-100 text-green-800' :
                                                ($post['status'] == 'pending' ? 'bg-yellow-100 text-yellow-800' :
                                                ($post['status'] == 'rejected' ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-800'));
                                        ?>">
                                            <?php echo ucfirst($post['status']); ?>
                                        </span>
                                    </div>
                                    <div class="flex space-x-2">
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
                                <div class="text-gray-700">
                                    <?php echo substr($post['content'], 0, 150); ?>...
                                </div>
                            </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-8">
                            <i class="fas fa-newspaper text-gray-400 text-4xl mb-4"></i>
                            <p class="text-gray-600">No posts yet. Create your first post!</p>
                            <a href="create-post.php" class="inline-block mt-4 bg-seait-orange text-white px-6 py-2 rounded hover:bg-orange-600 transition">
                                Create Post
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

        function confirmDelete() {
            const postId = document.getElementById('deletePostId').value;
            window.location.href = `delete-post.php?id=${postId}`;
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