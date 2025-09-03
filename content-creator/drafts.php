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

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $post_id = (int)$_POST['post_id'];

        // Verify the post belongs to the current user and is a draft
        $verify_query = "SELECT id FROM posts WHERE id = ? AND author_id = ? AND status = 'draft'";
        $stmt = mysqli_prepare($conn, $verify_query);
        mysqli_stmt_bind_param($stmt, "ii", $post_id, $_SESSION['user_id']);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if (mysqli_num_rows($result) > 0) {
            if ($_POST['action'] === 'delete') {
                $delete_query = "DELETE FROM posts WHERE id = ? AND author_id = ? AND status = 'draft'";
                $stmt = mysqli_prepare($conn, $delete_query);
                mysqli_stmt_bind_param($stmt, "ii", $post_id, $_SESSION['user_id']);

                if (mysqli_stmt_execute($stmt)) {
                    $message = 'Draft deleted successfully!';
                } else {
                    $error = 'Failed to delete draft.';
                }
            } elseif ($_POST['action'] === 'publish') {
                $publish_query = "UPDATE posts SET status = 'pending' WHERE id = ? AND author_id = ? AND status = 'draft'";
                $stmt = mysqli_prepare($conn, $publish_query);
                mysqli_stmt_bind_param($stmt, "ii", $post_id, $_SESSION['user_id']);

                if (mysqli_stmt_execute($stmt)) {
                    $message = 'Draft submitted for review successfully!';
                } else {
                    $error = 'Failed to submit draft for review.';
                }
            }
        } else {
            $error = 'Draft not found or you do not have permission to modify it.';
        }
    }
}

// Get filter parameters
$type_filter = isset($_GET['type']) ? $_GET['type'] : '';

// Build query for drafts only
$where_conditions = ["author_id = ?", "status = 'draft'"];
$params = [$_SESSION['user_id']];
$param_types = "i";

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
$drafts_result = mysqli_stmt_get_result($stmt);

// Get draft statistics
$stats_query = "SELECT
    COUNT(*) as total_drafts,
    SUM(CASE WHEN type = 'news' THEN 1 ELSE 0 END) as news_drafts,
    SUM(CASE WHEN type = 'announcement' THEN 1 ELSE 0 END) as announcement_drafts,
    SUM(CASE WHEN type = 'hiring' THEN 1 ELSE 0 END) as hiring_drafts,
    SUM(CASE WHEN type = 'event' THEN 1 ELSE 0 END) as event_drafts,
    SUM(CASE WHEN type = 'article' THEN 1 ELSE 0 END) as article_drafts
FROM posts WHERE author_id = ? AND status = 'draft'";
$stmt = mysqli_prepare($conn, $stats_query);
mysqli_stmt_bind_param($stmt, "i", $_SESSION['user_id']);
mysqli_stmt_execute($stmt);
$stats_result = mysqli_stmt_get_result($stmt);
$stats = mysqli_fetch_assoc($stats_result);
?>

<?php
$page_title = 'Drafts';
include 'includes/header.php';
?>
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
                <div class="mb-8">
                    <h1 class="text-2xl lg:text-3xl font-bold text-seait-dark mb-2">Drafts</h1>
                    <p class="text-gray-600">Manage your saved drafts and continue writing</p>
                </div>

                <!-- Information Section -->
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 lg:p-6 mb-6 lg:mb-8">
                    <div class="flex items-start">
                        <div class="flex-shrink-0">
                            <i class="fas fa-info-circle text-blue-400 text-lg"></i>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-blue-800 mb-2">Draft Management</h3>
                            <div class="text-sm text-blue-700 space-y-1">
                                <p><strong>Saved Work:</strong> Access all your saved drafts to continue editing or submit for review.</p>
                                <p><strong>Organization:</strong> Drafts are organized by type (news, announcements, etc.) for easy management.</p>
                                <p><strong>Statistics:</strong> View statistics about your drafts to track your content creation progress.</p>
                                <p><strong>Actions:</strong> Edit, submit for review, or delete drafts as needed. All changes are saved automatically.</p>
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
            <div class="grid grid-cols-1 md:grid-cols-6 gap-4 mb-8">
                <div class="bg-white p-4 rounded-lg shadow-md">
                    <div class="flex items-center">
                        <div class="p-2 bg-gray-100 rounded-full">
                            <i class="fas fa-edit text-gray-600"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-gray-600">Total Drafts</p>
                            <p class="text-xl font-bold text-gray-900"><?php echo $stats['total_drafts']; ?></p>
                        </div>
                    </div>
                </div>
                <div class="bg-white p-4 rounded-lg shadow-md">
                    <div class="flex items-center">
                        <div class="p-2 bg-blue-100 rounded-full">
                            <i class="fas fa-newspaper text-blue-600"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-gray-600">News</p>
                            <p class="text-xl font-bold text-gray-900"><?php echo $stats['news_drafts']; ?></p>
                        </div>
                    </div>
                </div>
                <div class="bg-white p-4 rounded-lg shadow-md">
                    <div class="flex items-center">
                        <div class="p-2 bg-yellow-100 rounded-full">
                            <i class="fas fa-bullhorn text-yellow-600"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-gray-600">Announcements</p>
                            <p class="text-xl font-bold text-gray-900"><?php echo $stats['announcement_drafts']; ?></p>
                        </div>
                    </div>
                </div>
                <div class="bg-white p-4 rounded-lg shadow-md">
                    <div class="flex items-center">
                        <div class="p-2 bg-green-100 rounded-full">
                            <i class="fas fa-briefcase text-green-600"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-gray-600">Hiring</p>
                            <p class="text-xl font-bold text-gray-900"><?php echo $stats['hiring_drafts']; ?></p>
                        </div>
                    </div>
                </div>
                <div class="bg-white p-4 rounded-lg shadow-md">
                    <div class="flex items-center">
                        <div class="p-2 bg-purple-100 rounded-full">
                            <i class="fas fa-calendar text-purple-600"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-gray-600">Events</p>
                            <p class="text-xl font-bold text-gray-900"><?php echo $stats['event_drafts']; ?></p>
                        </div>
                    </div>
                </div>
                <div class="bg-white p-4 rounded-lg shadow-md">
                    <div class="flex items-center">
                        <div class="p-2 bg-indigo-100 rounded-full">
                            <i class="fas fa-file-alt text-indigo-600"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-gray-600">Articles</p>
                            <p class="text-xl font-bold text-gray-900"><?php echo $stats['article_drafts']; ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <h3 class="text-lg font-semibold text-seait-dark mb-4">Filters</h3>
                <form method="GET" class="flex items-end space-x-4">
                    <div class="flex-1">
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
                    <button type="submit" class="bg-seait-orange text-white px-6 py-2 rounded-md hover:bg-orange-600 transition">
                        <i class="fas fa-filter mr-2"></i>Filter
                    </button>
                    <a href="drafts.php" class="bg-gray-300 text-gray-700 px-6 py-2 rounded-md hover:bg-gray-400 transition">
                        <i class="fas fa-times mr-2"></i>Clear
                    </a>
                </form>
            </div>

            <!-- Drafts List -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="p-6 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-seait-dark">Drafts (<?php echo mysqli_num_rows($drafts_result); ?>)</h3>
                </div>

                <div class="p-6">
                    <?php if (mysqli_num_rows($drafts_result) > 0): ?>
                        <div class="space-y-4">
                            <?php while($draft = mysqli_fetch_assoc($drafts_result)): ?>
                            <div class="border border-gray-200 rounded-lg p-6 hover:shadow-md transition">
                                <div class="flex justify-between items-start mb-4">
                                    <div class="flex-1">
                                        <div class="flex items-center space-x-3 mb-2">
                                            <h4 class="text-lg font-semibold text-gray-900"><?php echo htmlspecialchars($draft['title']); ?></h4>
                                            <span class="px-2 py-1 text-xs bg-gray-100 text-gray-800 rounded-full">
                                                Draft
                                            </span>
                                            <span class="px-2 py-1 text-xs bg-blue-100 text-blue-800 rounded-full">
                                                <?php echo ucfirst($draft['type']); ?>
                                            </span>
                                        </div>
                                        <p class="text-sm text-gray-600 mb-2">
                                            Created: <?php echo date('M d, Y H:i', strtotime($draft['created_at'])); ?>
                                            <?php if ($draft['updated_at'] != $draft['created_at']): ?>
                                                | Updated: <?php echo date('M d, Y H:i', strtotime($draft['updated_at'])); ?>
                                            <?php endif; ?>
                                        </p>
                                        <div class="text-gray-700 prose prose-sm max-w-none">
                                            <?php
                                            // Display HTML content safely, but limit length for preview
                                            $content = strip_tags($draft['content']);
                                            echo htmlspecialchars(substr($content, 0, 200)) . (strlen($content) > 200 ? '...' : '');
                                            ?>
                                        </div>
                                    </div>
                                    <div class="flex space-x-2 ml-4">
                                        <a href="edit-post.php?id=<?php echo $draft['id']; ?>"
                                           class="bg-blue-500 text-white px-3 py-1 rounded text-sm hover:bg-blue-600 transition">
                                            <i class="fas fa-edit mr-1"></i>Edit
                                        </a>
                                        <button onclick="publishDraft(<?php echo $draft['id']; ?>)"
                                                class="bg-green-500 text-white px-3 py-1 rounded text-sm hover:bg-green-600 transition">
                                            <i class="fas fa-paper-plane mr-1"></i>Publish
                                        </button>
                                        <button onclick="deleteDraft(<?php echo $draft['id']; ?>)"
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
                            <i class="fas fa-edit text-gray-400 text-4xl mb-4"></i>
                            <p class="text-gray-600 mb-4">No drafts found matching your filters.</p>
                            <a href="create-post.php" class="inline-block bg-seait-orange text-white px-6 py-2 rounded hover:bg-orange-600 transition">
                                <i class="fas fa-plus mr-2"></i>Create New Draft
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
                        <h3 class="text-xl font-semibold text-gray-900 mb-2">Delete Draft</h3>
                        <p class="text-gray-600 mb-4">Are you sure you want to delete this draft? This action cannot be undone.</p>
                        <div class="bg-red-50 border border-red-200 rounded-lg p-3 mb-4">
                            <div class="flex items-center text-red-800">
                                <i class="fas fa-exclamation-triangle mr-2"></i>
                                <span class="text-sm font-medium">Warning:</span>
                            </div>
                            <ul class="text-sm text-red-700 mt-2 text-left space-y-1">
                                <li class="flex items-center">
                                    <i class="fas fa-trash mr-2 text-red-500"></i>
                                    Draft will be permanently removed
                                </li>
                                <li class="flex items-center">
                                    <i class="fas fa-edit mr-2 text-red-500"></i>
                                    All content will be lost
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

    <!-- Publish Confirmation Modal -->
    <div id="publishModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full animate-bounce-in">
                <div class="p-6 text-center">
                    <div class="mb-4">
                        <div class="p-4 rounded-full bg-green-100 text-green-600 inline-block mb-4">
                            <i class="fas fa-paper-plane text-3xl"></i>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-900 mb-2">Submit for Review</h3>
                        <p class="text-gray-600 mb-4">Are you sure you want to submit this draft for review? It will be sent to the Social Media Manager for approval.</p>
                        <div class="bg-green-50 border border-green-200 rounded-lg p-3 mb-4">
                            <div class="flex items-center text-green-800">
                                <i class="fas fa-info-circle mr-2"></i>
                                <span class="text-sm font-medium">What happens next:</span>
                            </div>
                            <ul class="text-sm text-green-700 mt-2 text-left space-y-1">
                                <li class="flex items-center">
                                    <i class="fas fa-clock mr-2 text-green-500"></i>
                                    Draft will be moved to "Pending" status
                                </li>
                                <li class="flex items-center">
                                    <i class="fas fa-eye mr-2 text-green-500"></i>
                                    Social Media Manager will review your content
                                </li>
                                <li class="flex items-center">
                                    <i class="fas fa-check mr-2 text-green-500"></i>
                                    You'll be notified of approval or rejection
                                </li>
                            </ul>
                        </div>
                    </div>
                    <form method="POST" class="space-y-3">
                        <input type="hidden" name="action" value="publish">
                        <input type="hidden" name="post_id" id="publishPostId">
                        <div class="flex justify-center space-x-3">
                            <button type="button" onclick="closePublishModal()"
                                    class="px-6 py-3 text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 transition-all duration-200">
                                <i class="fas fa-times mr-2"></i>Cancel
                            </button>
                            <button type="submit"
                                    class="px-6 py-3 bg-gradient-to-r from-green-500 to-green-600 text-white rounded-lg hover:from-green-600 hover:to-green-700 transition-all duration-200 font-semibold">
                                <i class="fas fa-paper-plane mr-2"></i>Submit for Review
                            </button>
                        </div>
                    </form>
                </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function deleteDraft(postId) {
            const deleteModal = document.getElementById('deleteModal');
            const postIdField = document.getElementById('deletePostId');
            if (deleteModal && postIdField) {
                postIdField.value = postId;
                deleteModal.classList.remove('hidden');
            }
        }

        function publishDraft(postId) {
            const publishModal = document.getElementById('publishModal');
            const postIdField = document.getElementById('publishPostId');
            if (publishModal && postIdField) {
                postIdField.value = postId;
                publishModal.classList.remove('hidden');
            }
        }

        function closeDeleteModal() {
            const deleteModal = document.getElementById('deleteModal');
            if (deleteModal) {
                deleteModal.classList.add('hidden');
            }
        }

        function closePublishModal() {
            const publishModal = document.getElementById('publishModal');
            if (publishModal) {
                publishModal.classList.add('hidden');
            }
        }

        // Close modals when clicking outside
        const deleteModal = document.getElementById('deleteModal');
        const publishModal = document.getElementById('publishModal');

        if (deleteModal) {
            deleteModal.addEventListener('click', function(e) {
                if (e.target === this) {
                    closeDeleteModal();
                }
            });
        }

        if (publishModal) {
            publishModal.addEventListener('click', function(e) {
                if (e.target === this) {
                    closePublishModal();
                }
            });
        }
    </script>
</body>
</html>