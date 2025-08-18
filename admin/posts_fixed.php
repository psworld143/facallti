<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

check_admin();

$message = '';
$message_type = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_status':
                $post_id = (int)$_POST['post_id'];
                $status = sanitize_input($_POST['status']);

                $query = "UPDATE posts SET status = ? WHERE id = ?";
                $stmt = mysqli_prepare($conn, $query);
                mysqli_stmt_bind_param($stmt, "si", $status, $post_id);

                if (mysqli_stmt_execute($stmt)) {
                    $message = "Post status updated successfully!";
                    $message_type = "success";
                } else {
                    $message = "Error updating post status.";
                    $message_type = "error";
                }
                break;

            case 'delete':
                $post_id = (int)$_POST['post_id'];

                $query = "DELETE FROM posts WHERE id = ?";
                $stmt = mysqli_prepare($conn, $query);
                mysqli_stmt_bind_param($stmt, "i", $post_id);

                if (mysqli_stmt_execute($stmt)) {
                    $message = "Post deleted successfully!";
                    $message_type = "success";
                } else {
                    $message = "Error deleting post.";
                    $message_type = "error";
                }
                break;
        }
    }
}

// Get posts with filters
$status_filter = isset($_GET['status']) ? sanitize_input($_GET['status']) : '';
$type_filter = isset($_GET['type']) ? sanitize_input($_GET['type']) : '';
$search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';

$where_conditions = [];

if ($status_filter) {
    $where_conditions[] = "p.status = '$status_filter'";
}

if ($type_filter) {
    $where_conditions[] = "p.type = '$type_filter'";
}

if ($search) {
    $search_escaped = mysqli_real_escape_string($conn, $search);
    $where_conditions[] = "(p.title LIKE '%$search_escaped%' OR p.content LIKE '%$search_escaped%')";
}

$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = "WHERE " . implode(' AND ', $where_conditions);
}

$query = "SELECT p.*, u.first_name, u.last_name, u.username
          FROM posts p
          LEFT JOIN users u ON p.author_id = u.id
          $where_clause
          ORDER BY p.created_at DESC";

$posts_result = mysqli_query($conn, $query);
if (!$posts_result) {
    die("Query failed: " . mysqli_error($conn));
}

$total_posts = mysqli_num_rows($posts_result);

// Store posts in array to avoid result set issues
$posts = [];
while ($post = mysqli_fetch_assoc($posts_result)) {
    $posts[] = $post;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Posts - Admin Dashboard</title>
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
        @keyframes bounce-in {
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
            animation: bounce-in 0.6s ease-out;
        }
    </style>
</head>
<body class="bg-gray-50">
    <?php include 'includes/admin-header.php'; ?>

    <div class="flex pt-16">
        <?php include 'includes/admin-sidebar.php'; ?>

        <!-- Main Content -->
        <div class="flex-1 ml-64 p-8 overflow-y-auto h-screen">
            <div class="mb-8">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-3xl font-bold text-seait-dark mb-2">Manage Posts</h1>
                        <p class="text-gray-600">Review and manage all website posts</p>
                    </div>
                    <a href="create_post.php" class="bg-seait-orange text-white px-4 py-2 rounded-md hover:bg-orange-600 transition">
                        <i class="fas fa-plus mr-2"></i>Create New Post
                    </a>
                </div>
            </div>

            <?php if ($message): ?>
            <div class="mb-6 p-4 rounded-lg <?php echo $message_type === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                <?php echo $message; ?>
            </div>
            <?php endif; ?>

            <!-- Filters -->
            <div class="bg-white p-6 rounded-lg shadow-md mb-6">
                <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Search</label>
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange"
                               placeholder="Search posts...">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                        <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                            <option value="">All Status</option>
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
                            <option value="event" <?php echo $type_filter === 'event' ? 'selected' : ''; ?>>Event</option>
                            <option value="article" <?php echo $type_filter === 'article' ? 'selected' : ''; ?>>Article</option>
                            <option value="hiring" <?php echo $type_filter === 'hiring' ? 'selected' : ''; ?>>Hiring</option>
                        </select>
                    </div>
                    <div class="flex items-end">
                        <button type="submit" class="w-full bg-seait-orange text-white px-4 py-2 rounded-md hover:bg-orange-600 transition">
                            <i class="fas fa-search mr-2"></i>Filter
                        </button>
                    </div>
                </form>
            </div>

            <!-- Posts Table -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900">Posts (<?php echo count($posts); ?> total)</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Title</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Author</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if (count($posts) > 0): ?>
                                <?php foreach ($posts as $post): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4">
                                        <div>
                                            <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($post['title']); ?></div>
                                            <div class="text-sm text-gray-500"><?php echo substr(strip_tags($post['content']), 0, 100) . '...'; ?></div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-900">
                                        <?php echo htmlspecialchars($post['first_name'] . ' ' . $post['last_name']); ?>
                                        <div class="text-xs text-gray-500">@<?php echo htmlspecialchars($post['username']); ?></div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full
                                            <?php
                                            switch($post['type']) {
                                                case 'news': echo 'bg-blue-100 text-blue-800'; break;
                                                case 'announcement': echo 'bg-yellow-100 text-yellow-800'; break;
                                                case 'event': echo 'bg-green-100 text-green-800'; break;
                                                case 'article': echo 'bg-purple-100 text-purple-800'; break;
                                                case 'hiring': echo 'bg-orange-100 text-orange-800'; break;
                                                default: echo 'bg-gray-100 text-gray-800';
                                            }
                                            ?>">
                                            <?php echo ucfirst($post['type']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full
                                            <?php
                                            switch($post['status']) {
                                                case 'approved': echo 'bg-green-100 text-green-800'; break;
                                                case 'pending': echo 'bg-yellow-100 text-yellow-800'; break;
                                                case 'rejected': echo 'bg-red-100 text-red-800'; break;
                                                case 'draft': echo 'bg-gray-100 text-gray-800'; break;
                                                default: echo 'bg-gray-100 text-gray-800';
                                            }
                                            ?>">
                                            <?php echo ucfirst($post['status']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-500">
                                        <?php echo date('M d, Y', strtotime($post['created_at'])); ?>
                                    </td>
                                    <td class="px-6 py-4 text-sm font-medium">
                                        <div class="flex space-x-2">
                                            <button onclick="viewPost(<?php echo $post['id']; ?>)"
                                                    class="text-blue-600 hover:text-blue-900">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button onclick="editPost(<?php echo $post['id']; ?>)"
                                                    class="text-green-600 hover:text-green-900">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <?php if ($post['status'] === 'pending'): ?>
                                            <button onclick="updateStatus(<?php echo $post['id']; ?>, 'approved')"
                                                    class="text-green-600 hover:text-green-900">
                                                <i class="fas fa-check"></i>
                                            </button>
                                            <button onclick="updateStatus(<?php echo $post['id']; ?>, 'rejected')"
                                                    class="text-red-600 hover:text-red-900">
                                                <i class="fas fa-times"></i>
                                            </button>
                                            <?php endif; ?>
                                            <button onclick="deletePost(<?php echo $post['id']; ?>)"
                                                    class="text-red-600 hover:text-red-900">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="px-6 py-4 text-center text-gray-500">
                                        No posts found matching your criteria.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- View Post Modal -->
    <div id="viewModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-medium text-gray-900">View Post</h3>
                    <button onclick="closeModal('viewModal')" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div id="postContent"></div>
            </div>
        </div>
    </div>

    <!-- Delete Post Confirmation Modal -->
    <div id="deleteModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full animate-bounce-in">
                <div class="p-6 text-center">
                    <div class="mb-4">
                        <div class="p-4 rounded-full bg-red-100 text-red-600 inline-block mb-4">
                            <i class="fas fa-exclamation-triangle text-3xl"></i>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-900 mb-2">Delete Post</h3>
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
        function viewPost(postId) {
            // Fetch post content via AJAX and display in modal
            fetch(`get_post.php?id=${postId}`)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('postContent').innerHTML = `
                        <h4 class="text-xl font-bold mb-2">${data.title}</h4>
                        <div class="mb-4">
                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">${data.type}</span>
                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800 ml-2">${data.status}</span>
                        </div>
                        <div class="prose max-w-none">${data.content}</div>
                    `;
                    document.getElementById('viewModal').classList.remove('hidden');
                });
        }

        function editPost(postId) {
            window.location.href = `edit_post.php?id=${postId}`;
        }

        function updateStatus(postId, status) {
            if (confirm('Are you sure you want to ' + status + ' this post?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="post_id" value="${postId}">
                    <input type="hidden" name="status" value="${status}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function deletePost(postId) {
            document.getElementById('deletePostId').value = postId;
            document.getElementById('deleteModal').classList.remove('hidden');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.add('hidden');
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.add('hidden');
        }

        // Close delete modal when clicking outside
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