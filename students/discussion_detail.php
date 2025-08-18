<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and has student role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../login.php');
    exit();
}

// Get parameters from URL
$discussion_id = isset($_GET['discussion_id']) ? (int)$_GET['discussion_id'] : null;
$class_id = isset($_GET['class_id']) ? (int)$_GET['class_id'] : null;

if (!$discussion_id || !$class_id) {
    header('Location: my-classes.php');
    exit();
}

// Get student_id for verification
$student_id = get_student_id($conn, $_SESSION['email']);

// Verify student is enrolled in this class
$class_query = "SELECT ce.*, tc.section, tc.join_code, tc.status as class_status,
                cc.subject_title, cc.subject_code, cc.units, cc.description as subject_description,
                u.id as teacher_id, u.first_name as teacher_first_name, u.last_name as teacher_last_name,
                u.email as teacher_email
                FROM class_enrollments ce
                JOIN teacher_classes tc ON ce.class_id = tc.id
                JOIN course_curriculum cc ON tc.subject_id = cc.id
                JOIN users u ON tc.teacher_id = u.id
                WHERE ce.class_id = ? AND ce.student_id = ? AND ce.status = 'enrolled'";
$class_stmt = mysqli_prepare($conn, $class_query);
mysqli_stmt_bind_param($class_stmt, "ii", $class_id, $student_id);
mysqli_stmt_execute($class_stmt);
$class_result = mysqli_stmt_get_result($class_stmt);
$class_data = mysqli_fetch_assoc($class_result);

if (!$class_data) {
    header('Location: my-classes.php');
    exit();
}

// Get discussion details
$discussion_query = "SELECT d.*, u.first_name as created_by_name, u.last_name as created_by_last_name
                     FROM lms_discussions d
                     JOIN users u ON d.created_by = u.id
                     WHERE d.id = ? AND d.class_id = ? AND d.status = 'active'";
$discussion_stmt = mysqli_prepare($conn, $discussion_query);
mysqli_stmt_bind_param($discussion_stmt, "ii", $discussion_id, $class_id);
mysqli_stmt_execute($discussion_stmt);
$discussion_result = mysqli_stmt_get_result($discussion_stmt);
$discussion_data = mysqli_fetch_assoc($discussion_result);

if (!$discussion_data) {
    header('Location: lms_discussions.php?class_id=' . $class_id);
    exit();
}

// Handle form submissions
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_post':
                $content = sanitize_input($_POST['content']);
                $parent_id = isset($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;

                if (empty($content)) {
                    $message = "Please enter a message.";
                    $message_type = "error";
                } else {
                    $insert_query = "INSERT INTO lms_discussion_posts (discussion_id, parent_id, author_id, author_type, content, created_at)
                                    VALUES (?, ?, ?, 'student', ?, NOW())";
                    $insert_stmt = mysqli_prepare($conn, $insert_query);
                    mysqli_stmt_bind_param($insert_stmt, "iiis", $discussion_id, $parent_id, $student_id, $content);

                    if (mysqli_stmt_execute($insert_stmt)) {
                        $message = "Post added successfully!";
                        $message_type = "success";
                    } else {
                        $message = "Error adding post: " . mysqli_error($conn);
                        $message_type = "error";
                    }
                }
                break;
        }
    }
}

// Set page title
$page_title = 'Discussion - ' . $discussion_data['title'];

// Get posts for this discussion
$posts_query = "SELECT p.*,
                u.first_name as author_name, u.last_name as author_last_name,
                COUNT(r.id) as reaction_count,
                COUNT(CASE WHEN r.reaction_type = 'like' THEN 1 END) as likes,
                COUNT(CASE WHEN r.reaction_type = 'love' THEN 1 END) as loves,
                COUNT(CASE WHEN r.reaction_type = 'helpful' THEN 1 END) as helpful,
                COUNT(CASE WHEN r.reaction_type = 'insightful' THEN 1 END) as insightful
                FROM lms_discussion_posts p
                JOIN users u ON p.author_id = u.id
                LEFT JOIN lms_post_reactions r ON p.id = r.post_id
                WHERE p.discussion_id = ? AND p.status = 'active'
                GROUP BY p.id
                ORDER BY p.is_pinned DESC, p.created_at ASC";
$posts_stmt = mysqli_prepare($conn, $posts_query);
mysqli_stmt_bind_param($posts_stmt, "i", $discussion_id);
mysqli_stmt_execute($posts_stmt);
$posts_result = mysqli_stmt_get_result($posts_stmt);

// Get post statistics
$stats_query = "SELECT
                COUNT(*) as total_posts,
                COUNT(DISTINCT author_id) as total_participants,
                MAX(created_at) as last_activity
                FROM lms_discussion_posts
                WHERE discussion_id = ? AND status = 'active'";
$stats_stmt = mysqli_prepare($conn, $stats_query);
mysqli_stmt_bind_param($stats_stmt, "i", $discussion_id);
mysqli_stmt_execute($stats_stmt);
$stats = mysqli_fetch_assoc(mysqli_stmt_get_result($stats_stmt));

// Include the shared LMS header
include 'includes/lms_header.php';
?>

<div class="mb-6 sm:mb-8">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-seait-dark mb-2"><?php echo htmlspecialchars($discussion_data['title']); ?></h1>
            <p class="text-sm sm:text-base text-gray-600">Discussion in <?php echo htmlspecialchars($class_data['subject_title']); ?></p>
        </div>
        <div class="mt-4 sm:mt-0">
            <a href="lms_discussions.php?class_id=<?php echo $class_id; ?>" class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700 transition">
                <i class="fas fa-arrow-left mr-2"></i>Back to Discussions
            </a>
        </div>
    </div>
</div>

<?php if ($message): ?>
<div class="mb-6 p-4 rounded-lg <?php echo $message_type === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
    <?php echo $message; ?>
</div>
<?php endif; ?>

<!-- Discussion Header -->
<div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
    <div class="p-6">
        <div class="flex items-start justify-between mb-4">
            <div class="flex-1">
                <div class="flex items-center mb-2">
                    <?php if ($discussion_data['is_pinned']): ?>
                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 mr-2">
                            <i class="fas fa-thumbtack mr-1"></i>Pinned
                        </span>
                    <?php endif; ?>
                    <?php if ($discussion_data['is_locked']): ?>
                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800 mr-2">
                            <i class="fas fa-lock mr-1"></i>Locked
                        </span>
                    <?php endif; ?>
                    <h2 class="text-xl font-semibold text-gray-900"><?php echo htmlspecialchars($discussion_data['title']); ?></h2>
                </div>
                <p class="text-gray-600 mb-4"><?php echo nl2br(htmlspecialchars($discussion_data['description'])); ?></p>
                <div class="flex items-center text-sm text-gray-500 space-x-4">
                    <span class="flex items-center">
                        <i class="fas fa-user mr-1"></i>
                        <?php echo htmlspecialchars($discussion_data['created_by_name'] . ' ' . $discussion_data['created_by_last_name']); ?>
                    </span>
                    <span class="flex items-center">
                        <i class="fas fa-calendar mr-1"></i>
                        <?php echo date('M d, Y g:i A', strtotime($discussion_data['created_at'])); ?>
                    </span>
                    <span class="flex items-center">
                        <i class="fas fa-comment mr-1"></i>
                        <?php echo number_format($stats['total_posts']); ?> posts
                    </span>
                    <span class="flex items-center">
                        <i class="fas fa-users mr-1"></i>
                        <?php echo number_format($stats['total_participants']); ?> participants
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Posts Section -->
<div class="bg-white rounded-lg shadow-md overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-200">
        <h3 class="text-lg font-medium text-gray-900">Posts (<?php echo number_format($stats['total_posts']); ?>)</h3>
    </div>

    <?php if (mysqli_num_rows($posts_result) == 0): ?>
        <div class="p-6 text-center">
            <i class="fas fa-comment text-gray-300 text-4xl mb-4"></i>
            <p class="text-gray-500">No posts yet.</p>
            <p class="text-sm text-gray-400 mt-2">Be the first to start the conversation!</p>
        </div>
    <?php else: ?>
        <div class="divide-y divide-gray-200">
            <?php while ($post = mysqli_fetch_assoc($posts_result)): ?>
            <div class="p-6 hover:bg-gray-50 transition-colors">
                <div class="flex items-start space-x-4">
                    <div class="flex-shrink-0">
                        <div class="w-10 h-10 bg-seait-orange rounded-full flex items-center justify-center">
                            <i class="fas fa-user text-white"></i>
                        </div>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between mb-2">
                            <div class="flex items-center space-x-2">
                                <h4 class="text-sm font-medium text-gray-900">
                                    <?php echo htmlspecialchars($post['author_name'] . ' ' . $post['author_last_name']); ?>
                                </h4>
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium <?php echo $post['author_type'] === 'teacher' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800'; ?>">
                                    <?php echo ucfirst($post['author_type']); ?>
                                </span>
                                <?php if ($post['is_pinned']): ?>
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                        <i class="fas fa-thumbtack mr-1"></i>Pinned
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div class="flex items-center text-sm text-gray-500">
                                <span><?php echo date('M d, Y g:i A', strtotime($post['created_at'])); ?></span>
                                <?php if ($post['is_edited']): ?>
                                    <span class="ml-2 text-xs">(edited)</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="prose max-w-none mb-4">
                            <?php echo nl2br(htmlspecialchars($post['content'])); ?>
                        </div>

                        <!-- Reactions -->
                        <?php if ($post['reaction_count'] > 0): ?>
                        <div class="flex items-center space-x-2 mb-3">
                            <?php if ($post['likes'] > 0): ?>
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs bg-blue-100 text-blue-800">
                                    <i class="fas fa-thumbs-up mr-1"></i><?php echo $post['likes']; ?>
                                </span>
                            <?php endif; ?>
                            <?php if ($post['loves'] > 0): ?>
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs bg-red-100 text-red-800">
                                    <i class="fas fa-heart mr-1"></i><?php echo $post['loves']; ?>
                                </span>
                            <?php endif; ?>
                            <?php if ($post['helpful'] > 0): ?>
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs bg-green-100 text-green-800">
                                    <i class="fas fa-check-circle mr-1"></i><?php echo $post['helpful']; ?>
                                </span>
                            <?php endif; ?>
                            <?php if ($post['insightful'] > 0): ?>
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs bg-purple-100 text-purple-800">
                                    <i class="fas fa-lightbulb mr-1"></i><?php echo $post['insightful']; ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>

                        <!-- Action buttons -->
                        <div class="flex items-center space-x-4 text-sm">
                            <?php if (!$discussion_data['is_locked'] && $discussion_data['allow_replies']): ?>
                            <button onclick="showReplyForm(<?php echo $post['id']; ?>)" class="text-blue-600 hover:text-blue-800">
                                <i class="fas fa-reply mr-1"></i>Reply
                            </button>
                            <?php endif; ?>
                            <button onclick="reactToPost(<?php echo $post['id']; ?>, 'like')" class="text-gray-600 hover:text-blue-600">
                                <i class="fas fa-thumbs-up mr-1"></i>Like
                            </button>
                            <button onclick="reactToPost(<?php echo $post['id']; ?>, 'love')" class="text-gray-600 hover:text-red-600">
                                <i class="fas fa-heart mr-1"></i>Love
                            </button>
                            <button onclick="reactToPost(<?php echo $post['id']; ?>, 'helpful')" class="text-gray-600 hover:text-green-600">
                                <i class="fas fa-check-circle mr-1"></i>Helpful
                            </button>
                            <button onclick="reactToPost(<?php echo $post['id']; ?>, 'insightful')" class="text-gray-600 hover:text-purple-600">
                                <i class="fas fa-lightbulb mr-1"></i>Insightful
                            </button>
                        </div>

                        <!-- Reply form (hidden by default) -->
                        <div id="reply-form-<?php echo $post['id']; ?>" class="hidden mt-4">
                            <form method="POST" class="space-y-4">
                                <input type="hidden" name="action" value="add_post">
                                <input type="hidden" name="parent_id" value="<?php echo $post['id']; ?>">
                                <div>
                                    <textarea name="content" rows="3" required
                                              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-seait-orange"
                                              placeholder="Write your reply..."></textarea>
                                </div>
                                <div class="flex justify-end space-x-2">
                                    <button type="button" onclick="hideReplyForm(<?php echo $post['id']; ?>)"
                                            class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition">
                                        Cancel
                                    </button>
                                    <button type="submit"
                                            class="px-4 py-2 bg-seait-orange text-white rounded-lg hover:bg-orange-600 transition">
                                        Post Reply
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Add New Post -->
<?php if (!$discussion_data['is_locked'] && $discussion_data['allow_replies']): ?>
<div class="bg-white rounded-lg shadow-md overflow-hidden mt-6">
    <div class="px-6 py-4 border-b border-gray-200">
        <h3 class="text-lg font-medium text-gray-900">Add Your Post</h3>
    </div>
    <div class="p-6">
        <form method="POST" class="space-y-4">
            <input type="hidden" name="action" value="add_post">
            <div>
                <textarea name="content" rows="4" required
                          class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-seait-orange"
                          placeholder="Share your thoughts..."></textarea>
            </div>
            <div class="flex justify-end">
                <button type="submit"
                        class="px-6 py-2 bg-seait-orange text-white rounded-lg hover:bg-orange-600 transition">
                    <i class="fas fa-paper-plane mr-2"></i>Post Message
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
function showReplyForm(postId) {
    document.getElementById('reply-form-' + postId).classList.remove('hidden');
}

function hideReplyForm(postId) {
    document.getElementById('reply-form-' + postId).classList.add('hidden');
}

function reactToPost(postId, reactionType) {
    // This would typically make an AJAX call to handle reactions
    alert('Reaction functionality would be implemented here. Post ID: ' + postId + ', Reaction: ' + reactionType);
}

// Auto-scroll to bottom if there's a new post
<?php if ($message_type === 'success'): ?>
window.scrollTo(0, document.body.scrollHeight);
<?php endif; ?>
</script>

<?php
// Include the shared LMS footer
include 'includes/lms_footer.php';
?>