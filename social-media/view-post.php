<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

check_social_media_manager();

// Get post ID from URL
$post_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$post_id) {
    header('Location: dashboard.php');
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
    header('Location: dashboard.php');
    exit();
}

$post = mysqli_fetch_assoc($result);

// Get post statistics
$view_count = 0; // Placeholder for view count functionality
$engagement_rate = 0; // Placeholder for engagement rate

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

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Post - <?php echo htmlspecialchars($post['title']); ?> - SEAIT</title>
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
    <div id="mobile-overlay" class="fixed inset-0 bg-black bg-opacity-50 z-30 lg:hidden hidden"></div>

    <!-- Sidebar -->
    <?php include 'includes/sidebar.php'; ?>

    <!-- Scrollable Main Content -->
    <div id="main-content" class="lg:ml-64 pt-20 min-h-screen transition-all duration-300 ease-in-out">
        <div class="p-4 lg:p-8">
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
                        <div class="flex space-x-2 ml-4">
                            <?php if ($post['status'] === 'pending'): ?>
                            <button onclick="openApproveModal(<?php echo $post['id']; ?>)"
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
                                <i class="fas fa-undo mr-2"></i>Unapprove
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
                            <button onclick="goBack()" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600 transition">
                                <i class="fas fa-arrow-left mr-2"></i>Back
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Information Section -->
            <?php include 'includes/info-section.php'; ?>

            <!-- Breadcrumb Navigation -->
            <div class="mb-4">
                <button onclick="goBack()" class="inline-flex items-center text-sm text-gray-600 hover:text-seait-orange transition">
                    <i class="fas fa-arrow-left mr-2"></i>
                    Back to Previous Page
                </button>
            </div>

            <!-- Post Content and Statistics -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Main Content -->
                <div class="lg:col-span-2">
                    <!-- Post Content -->
                    <div class="bg-white rounded-lg shadow-md mb-6">
                        <div class="p-4 lg:p-6">
                            <h2 class="text-xl font-semibold text-seait-dark mb-4">Content</h2>
                            <div class="prose max-w-none">
                                <div class="bg-gray-50 p-4 lg:p-6 rounded-lg border-l-4 border-seait-orange">
                                    <?php echo strip_tags($post['content'], '<b><i><strong><em><ul><ol><li><p><br><a>'); ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Attached Image -->
                    <?php if (!empty($post['image_url'])): ?>
                    <div class="bg-white rounded-lg shadow-md mb-6">
                        <div class="p-4 lg:p-6">
                            <h2 class="text-xl font-semibold text-seait-dark mb-4">Attached Image</h2>
                            <div class="flex justify-center">
                                <img src="../<?php echo htmlspecialchars($post['image_url']); ?>"
                                     alt="Post image"
                                     class="max-w-full h-auto rounded-lg shadow-md">
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Rejection Reason (if applicable) -->
                    <?php if ($post['status'] === 'rejected' && !empty($post['rejection_reason'])): ?>
                    <div class="bg-white rounded-lg shadow-md mb-6">
                        <div class="p-4 lg:p-6">
                            <h2 class="text-xl font-semibold text-red-600 mb-4">
                                <i class="fas fa-exclamation-triangle mr-2"></i>Rejection Reason
                            </h2>
                            <div class="bg-red-50 p-4 rounded-lg border-l-4 border-red-500">
                                <p class="text-red-800"><?php echo nl2br(htmlspecialchars($post['rejection_reason'])); ?></p>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Sidebar -->
                <div class="lg:col-span-1">
                    <!-- Post Statistics -->
                    <div class="bg-white rounded-lg shadow-md mb-6">
                        <div class="p-4 lg:p-6">
                            <h3 class="text-lg font-semibold text-seait-dark mb-4">Post Statistics</h3>
                            <div class="space-y-4">
                                <div class="flex justify-between items-center">
                                    <span class="text-gray-600">Views</span>
                                    <span class="font-semibold"><?php echo number_format($view_count); ?></span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-gray-600">Engagement Rate</span>
                                    <span class="font-semibold"><?php echo $engagement_rate; ?>%</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-gray-600">Post ID</span>
                                    <span class="font-mono text-sm">#<?php echo $post['id']; ?></span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-gray-600">Status</span>
                                    <span class="px-2 py-1 text-xs rounded <?php echo $status_color; ?> <?php echo $status_text_color; ?>">
                                        <?php echo ucfirst($post['status']); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="bg-white rounded-lg shadow-md mb-6">
                        <div class="p-4 lg:p-6">
                            <h3 class="text-lg font-semibold text-seait-dark mb-4">Quick Actions</h3>
                            <div class="space-y-3">
                                <button onclick="window.print()"
                                        class="w-full bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600 transition">
                                    <i class="fas fa-print mr-2"></i>Print Post
                                </button>
                                <button onclick="copyToClipboard()"
                                        class="w-full bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600 transition">
                                    <i class="fas fa-copy mr-2"></i>Copy Content
                                </button>
                                <button onclick="sharePost()"
                                        class="w-full bg-purple-500 text-white px-4 py-2 rounded hover:bg-purple-600 transition">
                                    <i class="fas fa-share mr-2"></i>Share Post
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Post History -->
                    <div class="bg-white rounded-lg shadow-md">
                        <div class="p-4 lg:p-6">
                            <h3 class="text-lg font-semibold text-seait-dark mb-4">Post History</h3>
                            <div class="space-y-3">
                                <div class="flex items-center text-sm">
                                    <div class="w-2 h-2 bg-green-500 rounded-full mr-3"></div>
                                    <div>
                                        <p class="font-medium">Created</p>
                                        <p class="text-gray-600"><?php echo date('M d, Y H:i', strtotime($post['created_at'])); ?></p>
                                    </div>
                                </div>
                                <?php if ($post['status'] === 'approved'): ?>
                                <div class="flex items-center text-sm">
                                    <div class="w-2 h-2 bg-blue-500 rounded-full mr-3"></div>
                                    <div>
                                        <p class="font-medium">Approved</p>
                                        <p class="text-gray-600"><?php echo date('M d, Y H:i', strtotime($post['updated_at'])); ?></p>
                                        <?php if ($post['approver_first_name']): ?>
                                        <p class="text-gray-500">by <?php echo htmlspecialchars($post['approver_first_name'] . ' ' . $post['approver_last_name']); ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                                <?php if ($post['status'] === 'rejected'): ?>
                                <div class="flex items-center text-sm">
                                    <div class="w-2 h-2 bg-red-500 rounded-full mr-3"></div>
                                    <div>
                                        <p class="font-medium">Rejected</p>
                                        <p class="text-gray-600"><?php echo date('M d, Y H:i', strtotime($post['rejected_at'])); ?></p>
                                        <?php if ($post['rejecter_first_name']): ?>
                                        <p class="text-gray-500">by <?php echo htmlspecialchars($post['rejecter_first_name'] . ' ' . $post['rejecter_last_name']); ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                                <div class="flex items-center text-sm">
                                    <div class="w-2 h-2 bg-gray-500 rounded-full mr-3"></div>
                                    <div>
                                        <p class="font-medium">Last Updated</p>
                                        <p class="text-gray-600"><?php echo date('M d, Y H:i', strtotime($post['updated_at'])); ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
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
            if (sidebar && mobileOverlay) {
                sidebar.classList.remove('-translate-x-full');
                mobileOverlay.classList.remove('hidden');
                document.body.style.overflow = 'hidden';
            }
        }

        function closeSidebar() {
            if (sidebar && mobileOverlay) {
                sidebar.classList.add('-translate-x-full');
                mobileOverlay.classList.add('hidden');
                document.body.style.overflow = 'auto';
            }
        }

        // Event listeners with error handling
        if (mobileMenuButton) {
            mobileMenuButton.addEventListener('click', openSidebar);
        }
        if (closeSidebarButton) {
            closeSidebarButton.addEventListener('click', closeSidebar);
        }
        if (mobileOverlay) {
            mobileOverlay.addEventListener('click', closeSidebar);
        }

        // Close sidebar when clicking on navigation links (mobile)
        if (sidebar) {
            const navLinks = sidebar.querySelectorAll('a');
            navLinks.forEach(link => {
                link.addEventListener('click', () => {
                    if (window.innerWidth < 1024) { // lg breakpoint
                        closeSidebar();
                    }
                });
            });
        }

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

        function closeRejectionModal() {
            const rejectionModal = document.getElementById('rejectionModal');
            const rejectionReason = document.getElementById('rejection_reason');
            if (rejectionModal) {
                rejectionModal.classList.add('hidden');
            }
            if (rejectionReason) {
                rejectionReason.value = '';
            }
        }

        function unapprovePost(postId) {
            const unapproveModal = document.getElementById('unapproveModal');
            if (unapproveModal) {
                unapproveModal.classList.remove('hidden');
            }
        }

        function closeUnapproveModal() {
            const unapproveModal = document.getElementById('unapproveModal');
            if (unapproveModal) {
                unapproveModal.classList.add('hidden');
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
            const deleteModal = document.getElementById('deleteModal');
            if (deleteModal) {
                deleteModal.classList.remove('hidden');
            }
        }

        function closeDeleteModal() {
            const deleteModal = document.getElementById('deleteModal');
            if (deleteModal) {
                deleteModal.classList.add('hidden');
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

        function goBack() {
            // Check if we have a valid referrer from the same domain
            if (document.referrer && document.referrer.includes(window.location.hostname)) {
                // Check if the referrer is from one of our social media pages
                const referrerUrl = new URL(document.referrer);
                const currentPath = referrerUrl.pathname;

                // If referrer is from a valid social media page, go back
                if (currentPath.includes('/social-media/')) {
                    window.history.back();
                    return;
                }
            }

            // Fallback: Determine the appropriate page based on post status
            const postStatus = '<?php echo $post['status']; ?>';
            let fallbackUrl = 'dashboard.php';

            switch (postStatus) {
                case 'pending':
                    fallbackUrl = 'pending-post.php';
                    break;
                case 'approved':
                    fallbackUrl = 'approved-posts.php';
                    break;
                case 'rejected':
                    fallbackUrl = 'rejected-posts.php';
                    break;
                default:
                    fallbackUrl = 'dashboard.php';
            }

            window.location.href = fallbackUrl;
        }

        // Close modal when clicking outside
        const rejectionModal = document.getElementById('rejectionModal');
        if (rejectionModal) {
            rejectionModal.addEventListener('click', function(e) {
                if (e.target === this) {
                    closeRejectionModal();
                }
            });
        }

        const unapproveModal = document.getElementById('unapproveModal');
        if (unapproveModal) {
            unapproveModal.addEventListener('click', function(e) {
                if (e.target === this) {
                    closeUnapproveModal();
                }
            });
        }

        const deleteModal = document.getElementById('deleteModal');
        if (deleteModal) {
            deleteModal.addEventListener('click', function(e) {
                if (e.target === this) {
                    closeDeleteModal();
                }
            });
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
            form.action = 'pending-post.php';
            form.innerHTML = `
                <input type="hidden" name="action" value="approve">
                <input type="hidden" name="post_id" value="${postId}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
        // Close modal when clicking outside
        document.getElementById('approveModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeApproveModal();
            }
        });
    </script>

    <!-- Rejection Modal -->
    <div id="rejectionModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-md w-full">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Reject Post</h3>
                    <form method="POST" action="pending-post.php">
                        <input type="hidden" name="action" value="reject">
                        <input type="hidden" name="post_id" value="<?php echo $post['id']; ?>">
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

    <!-- Unapprove Confirmation Modal -->
    <div id="unapproveModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full animate-bounce-in">
                <div class="p-6 text-center">
                    <div class="mb-4">
                        <div class="p-4 rounded-full bg-yellow-100 text-yellow-600 inline-block mb-4">
                            <i class="fas fa-undo text-3xl"></i>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-900 mb-2">Unapprove Post</h3>
                        <p class="text-gray-600 mb-4">Are you sure you want to move this post back to pending? This will require re-approval.</p>
                        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3 mb-4">
                            <div class="flex items-center text-yellow-800">
                                <i class="fas fa-info-circle mr-2"></i>
                                <span class="text-sm font-medium">This action will:</span>
                            </div>
                            <ul class="text-sm text-yellow-700 mt-2 text-left space-y-1">
                                <li class="flex items-center">
                                    <i class="fas fa-arrow-down mr-2 text-yellow-500"></i>
                                    Change status from "Approved" to "Pending"
                                </li>
                                <li class="flex items-center">
                                    <i class="fas fa-clock mr-2 text-yellow-500"></i>
                                    Require re-approval before publication
                                </li>
                                <li class="flex items-center">
                                    <i class="fas fa-eye mr-2 text-yellow-500"></i>
                                    Remove from public view temporarily
                                </li>
                            </ul>
                        </div>
                    </div>
                    <form id="unapproveForm" method="POST" action="approved-posts.php" class="space-y-3">
                        <input type="hidden" name="action" value="unapprove">
                        <input type="hidden" name="post_id" value="<?php echo $post['id']; ?>">
                        <div class="flex justify-center space-x-3">
                            <button type="button" onclick="closeUnapproveModal()"
                                    class="px-6 py-3 text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 transition-all duration-200">
                                <i class="fas fa-times mr-2"></i>Cancel
                            </button>
                            <button type="submit"
                                    class="px-6 py-3 bg-gradient-to-r from-yellow-500 to-yellow-600 text-white rounded-lg hover:from-yellow-600 hover:to-yellow-700 transition-all duration-200 font-semibold">
                                <i class="fas fa-undo mr-2"></i>Unapprove Post
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
                    <form id="deleteForm" method="POST" action="<?php echo $post['status']; ?>-posts.php" class="space-y-3">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="post_id" value="<?php echo $post['id']; ?>">
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
                        <p class="text-gray-600 mb-4">Are you sure you want to approve this post? It will be published on the website.</p>
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
</body>
</html>