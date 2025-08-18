<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

check_social_media_manager();

// Get post ID from URL
$post_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$post_id) {
    echo '<div class="text-center py-8 text-red-600"><i class="fas fa-exclamation-triangle text-2xl"></i><p class="mt-2">Post not found</p></div>';
    exit();
}

// Get post data with author and approver information
$query = "SELECT p.*,
          u.first_name, u.last_name, u.email as author_email,
          a.first_name as approver_first_name, a.last_name as approver_last_name,
          r.first_name as rejecter_first_name, r.last_name as rejecter_last_name
          FROM posts p
          JOIN users u ON p.author_id = u.id
          LEFT JOIN users a ON p.approved_by = a.id
          LEFT JOIN users r ON p.rejected_by = r.id
          WHERE p.id = ?";

$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $post_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) === 0) {
    echo '<div class="text-center py-8 text-red-600"><i class="fas fa-exclamation-triangle text-2xl"></i><p class="mt-2">Post not found</p></div>';
    exit();
}

$post = mysqli_fetch_assoc($result);

// Determine status color and icon
$status_colors = [
    'draft' => ['bg-gray-100', 'text-gray-800', 'fas fa-edit'],
    'pending' => ['bg-yellow-100', 'text-yellow-800', 'fas fa-clock'],
    'approved' => ['bg-green-100', 'text-green-800', 'fas fa-check-circle'],
    'rejected' => ['bg-red-100', 'text-red-800', 'fas fa-times-circle'],
    'deleted' => ['bg-gray-100', 'text-gray-800', 'fas fa-trash']
];

$status_color = $status_colors[$post['status']][0];
$status_text_color = $status_colors[$post['status']][1];
$status_icon = $status_colors[$post['status']][2];
?>

<!-- Back to List Button -->
<div class="mb-6">
    <button onclick="goBackToList()" class="inline-flex items-center text-sm text-gray-600 hover:text-seait-orange transition">
        <i class="fas fa-arrow-left mr-2"></i>
        Back to List
    </button>
</div>

<!-- Post Header -->
<div class="bg-white rounded-lg shadow-md mb-6">
    <div class="p-6 border-b border-gray-200">
        <div class="flex justify-between items-start">
            <div class="flex-1">
                <h1 class="text-3xl font-bold text-seait-dark mb-2"><?php echo htmlspecialchars($post['title']); ?></h1>
                <div class="flex items-center space-x-4 text-sm text-gray-600 mb-4">
                    <span>
                        <i class="fas fa-user mr-1"></i>
                        <?php echo htmlspecialchars($post['first_name'] . ' ' . $post['last_name']); ?>
                    </span>
                    <span>
                        <i class="fas fa-envelope mr-1"></i>
                        <?php echo htmlspecialchars($post['author_email']); ?>
                    </span>
                    <span>
                        <i class="fas fa-calendar mr-1"></i>
                        Created: <?php echo date('M d, Y H:i', strtotime($post['created_at'])); ?>
                    </span>
                    <span>
                        <i class="fas fa-clock mr-1"></i>
                        Updated: <?php echo date('M d, Y H:i', strtotime($post['updated_at'])); ?>
                    </span>
                </div>
                <div class="flex space-x-2">
                    <span class="inline-block px-3 py-1 text-xs bg-blue-100 text-blue-800 rounded">
                        <?php echo ucfirst(htmlspecialchars($post['type'])); ?>
                    </span>
                    <span class="inline-block px-3 py-1 text-xs <?php echo $status_color; ?> <?php echo $status_text_color; ?> rounded">
                        <i class="<?php echo $status_icon; ?> mr-1"></i>
                        <?php echo ucfirst($post['status']); ?>
                    </span>
                    <?php if ($post['status'] === 'approved' && $post['approver_first_name']): ?>
                    <span class="inline-block px-3 py-1 text-xs bg-purple-100 text-purple-800 rounded">
                        Approved by <?php echo htmlspecialchars($post['approver_first_name'] . ' ' . $post['approver_last_name']); ?>
                    </span>
                    <?php endif; ?>
                    <?php if ($post['status'] === 'rejected' && $post['rejecter_first_name']): ?>
                    <span class="inline-block px-3 py-1 text-xs bg-red-100 text-red-800 rounded">
                        Rejected by <?php echo htmlspecialchars($post['rejecter_first_name'] . ' ' . $post['rejecter_last_name']); ?>
                    </span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="flex space-x-2">
                <button onclick="copyToClipboard()" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600 transition">
                    <i class="fas fa-copy mr-2"></i>Copy
                </button>
                <button onclick="sharePost()" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600 transition">
                    <i class="fas fa-share mr-2"></i>Share
                </button>
            </div>
        </div>
    </div>

    <!-- Post Content -->
    <div class="p-6">
        <div class="prose max-w-none">
            <div class="text-gray-700 leading-relaxed">
                <?php echo nl2br(htmlspecialchars($post['content'])); ?>
            </div>
        </div>

        <?php if (!empty($post['image_url'])): ?>
        <div class="mt-6">
            <h3 class="text-lg font-semibold text-seait-dark mb-3">Attached Image</h3>
            <img src="<?php echo htmlspecialchars($post['image_url']); ?>"
                 alt="Post image"
                 class="max-w-full rounded-lg shadow-md">
        </div>
        <?php endif; ?>

        <?php if ($post['status'] === 'rejected' && !empty($post['rejection_reason'])): ?>
        <div class="mt-6">
            <h3 class="text-lg font-semibold text-red-600 mb-3">Rejection Reason</h3>
            <div class="bg-red-50 p-4 rounded-lg border-l-4 border-red-500">
                <i class="fas fa-exclamation-triangle text-red-500 mr-2"></i>
                <?php echo nl2br(htmlspecialchars($post['rejection_reason'])); ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Action Buttons -->
<div class="bg-white rounded-lg shadow-md p-6">
    <h3 class="text-lg font-semibold text-seait-dark mb-4">Actions</h3>
    <div class="flex flex-wrap gap-3">
        <?php if ($post['status'] === 'pending'): ?>
            <button onclick="approvePost(<?php echo $post['id']; ?>)"
                    class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600 transition">
                <i class="fas fa-check mr-2"></i>Approve
            </button>
            <button onclick="rejectPost(<?php echo $post['id']; ?>)"
                    class="bg-red-500 text-white px-4 py-2 rounded hover:bg-red-600 transition">
                <i class="fas fa-times mr-2"></i>Reject
            </button>
        <?php elseif ($post['status'] === 'approved'): ?>
            <button onclick="unapprovePost(<?php echo $post['id']; ?>)"
                    class="bg-yellow-500 text-white px-4 py-2 rounded hover:bg-yellow-600 transition">
                <i class="fas fa-undo mr-2"></i>Move to Pending
            </button>
        <?php elseif ($post['status'] === 'rejected'): ?>
            <button onclick="approvePost(<?php echo $post['id']; ?>)"
                    class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600 transition">
                <i class="fas fa-check mr-2"></i>Approve
            </button>
            <button onclick="moveToPending(<?php echo $post['id']; ?>)"
                    class="bg-yellow-500 text-white px-4 py-2 rounded hover:bg-yellow-600 transition">
                <i class="fas fa-clock mr-2"></i>Move to Pending
            </button>
        <?php endif; ?>

        <button onclick="deletePost(<?php echo $post['id']; ?>)"
                class="bg-red-500 text-white px-4 py-2 rounded hover:bg-red-600 transition">
            <i class="fas fa-trash mr-2"></i>Delete
        </button>
    </div>
</div>

<script>
function approvePost(postId) {
    if (confirm('Are you sure you want to approve this post?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'pending-post.php';
        form.innerHTML = `
            <input type="hidden" name="action" value="approve">
            <input type="hidden" name="post_id" value="${postId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function rejectPost(postId) {
    const rejectionModal = document.getElementById('rejectionModal');
    if (rejectionModal) {
        rejectionModal.classList.remove('hidden');
    }
}

function unapprovePost(postId) {
    if (confirm('Are you sure you want to move this post back to pending? This will require re-approval.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'approved-posts.php';
        form.innerHTML = `
            <input type="hidden" name="action" value="unapprove">
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
        form.action = 'rejected-posts.php';
        form.innerHTML = `
            <input type="hidden" name="action" value="move_to_pending">
            <input type="hidden" name="post_id" value="${postId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function deletePost(postId) {
    if (confirm('Are you sure you want to delete this post? This action cannot be undone.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '<?php echo $post['status']; ?>-posts.php';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="post_id" value="${postId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function copyToClipboard() {
    const content = `<?php echo addslashes($post['title']); ?>\n\n<?php echo addslashes($post['content']); ?>`;
    navigator.clipboard.writeText(content).then(() => {
        alert('Content copied to clipboard!');
    }).catch(() => {
        alert('Failed to copy content. Please select and copy manually.');
    });
}

function sharePost() {
    const url = window.location.href;
    const title = '<?php echo addslashes($post['title']); ?>';

    if (navigator.share) {
        navigator.share({
            title: title,
            url: url
        });
    } else {
        // Fallback for browsers that don't support Web Share API
        const shareUrl = `mailto:?subject=${encodeURIComponent(title)}&body=${encodeURIComponent(url)}`;
        window.open(shareUrl);
    }
}

function goBackToList() {
    const currentUrl = window.location.href;
    if (currentUrl.includes('dashboard.php')) {
        window.location.href = 'dashboard.php';
    } else if (currentUrl.includes('pending-post.php')) {
        window.location.href = 'pending-post.php';
    } else if (currentUrl.includes('approved-posts.php')) {
        window.location.href = 'approved-posts.php';
    } else if (currentUrl.includes('rejected-posts.php')) {
        window.location.href = 'rejected-posts.php';
    } else {
        // Default fallback
        window.history.back();
    }
}
</script>