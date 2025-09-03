<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/id_encryption.php';

check_admin();

// Set page title for the header
$page_title = 'Edit Post';

$message = '';
$message_type = '';

if (!isset($_GET['id'])) {
    header('Location: posts.php');
    exit;
}

$post_id = safe_decrypt_id($_GET['id']);

// Get post data
$query = "SELECT * FROM posts WHERE id = ?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $post_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$post = mysqli_fetch_assoc($result);

if (!$post) {
    header('Location: posts.php');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = sanitize_input($_POST['title']);
    $content = $_POST['content']; // Don't sanitize HTML content
    $type = sanitize_input($_POST['type']);
    $status = sanitize_input($_POST['status']);

    if (!empty($title) && !empty($content)) {
        $query = "UPDATE posts SET title = ?, content = ?, type = ?, status = ? WHERE id = ?";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "ssssi", $title, $content, $type, $status, $post_id);

        if (mysqli_stmt_execute($stmt)) {
            $message = "Post updated successfully!";
            $message_type = "success";
            $post['title'] = $title;
            $post['content'] = $content;
            $post['type'] = $type;
            $post['status'] = $status;
        } else {
            $message = "Error updating post.";
            $message_type = "error";
        }
    } else {
        $message = "Title and content are required.";
        $message_type = "error";
    }
}

// Include the new admin header
include 'includes/admin-header.php';
?>

<script src="https://cdn.ckeditor.com/ckeditor5/27.1.0/classic/ckeditor.js"></script>

            <div class="mb-8">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-3xl font-bold text-seait-dark mb-2">Edit Post</h1>
                        <p class="text-gray-600">Modify post content and settings</p>
                    </div>
                    <a href="posts.php" class="bg-gray-500 text-white px-4 py-2 rounded-md hover:bg-gray-600 transition">
                        <i class="fas fa-arrow-left mr-2"></i>Back to Posts
                    </a>
                </div>
            </div>

            <?php if ($message): ?>
            <div class="mb-6 p-4 rounded-lg <?php echo $message_type === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                <?php echo $message; ?>
            </div>
            <?php endif; ?>

            <!-- Edit Form -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <form method="POST">
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        <div class="lg:col-span-2">
                            <div class="mb-6">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Title</label>
                                <input type="text" name="title" value="<?php echo htmlspecialchars($post['title']); ?>"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange"
                                       required>
                            </div>

                            <div class="mb-6">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Content</label>
                                <textarea name="content" id="editor" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange"
                                          rows="15" required><?php echo htmlspecialchars($post['content']); ?></textarea>
                            </div>
                        </div>

                        <div class="space-y-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Post Type</label>
                                <select name="type" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                                    <option value="news" <?php echo $post['type'] === 'news' ? 'selected' : ''; ?>>News</option>
                                    <option value="announcement" <?php echo $post['type'] === 'announcement' ? 'selected' : ''; ?>>Announcement</option>
                                    <option value="event" <?php echo $post['type'] === 'event' ? 'selected' : ''; ?>>Event</option>
                                    <option value="article" <?php echo $post['type'] === 'article' ? 'selected' : ''; ?>>Article</option>
                                    <option value="hiring" <?php echo $post['type'] === 'hiring' ? 'selected' : ''; ?>>Hiring</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                                <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                                    <option value="draft" <?php echo $post['status'] === 'draft' ? 'selected' : ''; ?>>Draft</option>
                                    <option value="pending" <?php echo $post['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="approved" <?php echo $post['status'] === 'approved' ? 'selected' : ''; ?>>Approved</option>
                                    <option value="rejected" <?php echo $post['status'] === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                </select>
                            </div>

                            <div class="bg-gray-50 p-4 rounded-lg">
                                <h3 class="text-sm font-medium text-gray-700 mb-3">Post Information</h3>
                                <div class="space-y-2 text-sm">
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Created:</span>
                                        <span class="text-gray-900"><?php echo date('M d, Y', strtotime($post['created_at'])); ?></span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Last Updated:</span>
                                        <span class="text-gray-900"><?php echo date('M d, Y', strtotime($post['updated_at'])); ?></span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Post ID:</span>
                                        <span class="text-gray-900">#<?php echo $post['id']; ?></span>
                                    </div>
                                </div>
                            </div>

                            <div class="flex space-x-3">
                                <button type="submit" class="flex-1 bg-seait-orange text-white px-4 py-2 rounded-md hover:bg-orange-600 transition">
                                    <i class="fas fa-save mr-2"></i>Update Post
                                </button>
                                <a href="posts.php" class="flex-1 bg-gray-500 text-white px-4 py-2 rounded-md hover:bg-gray-600 transition text-center">
                                    Cancel
                                </a>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </main>
</div>

<script>
    ClassicEditor
        .create(document.querySelector('#editor'), {
            toolbar: ['heading', '|', 'bold', 'italic', 'link', 'bulletedList', 'numberedList', '|', 'outdent', 'indent', '|', 'blockQuote', 'insertTable', 'undo', 'redo'],
            heading: {
                options: [
                    { model: 'paragraph', title: 'Paragraph', class: 'ck-heading_paragraph' },
                    { model: 'heading1', view: 'h1', title: 'Heading 1', class: 'ck-heading_heading1' },
                    { model: 'heading2', view: 'h2', title: 'Heading 2', class: 'ck-heading_heading2' },
                    { model: 'heading3', view: 'h3', title: 'Heading 3', class: 'ck-heading_heading3' }
                ]
            }
        })
        .catch(error => {
            console.error(error);
        });
</script>
</body>
</html>