<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

check_social_media_manager();

// ========================================
// CRUD FUNCTIONS - Separated from UI
// ========================================

/**
 * Get all pending carousel slides with creator information
 * @param mysqli $conn Database connection
 * @return mysqli_result|false Query result
 */
function get_pending_carousel_slides($conn) {
    $query = "SELECT c.*, u.first_name, u.last_name, u.email
              FROM carousel_slides c
              JOIN users u ON c.created_by = u.id
              WHERE c.status = 'pending'
              ORDER BY c.created_at DESC";
    return mysqli_query($conn, $query);
}

/**
 * Get a single pending carousel slide by ID
 * @param mysqli $conn Database connection
 * @param int $slide_id Slide ID
 * @return array|false Slide data or false
 */
function get_pending_carousel_slide_by_id($conn, $slide_id) {
    $slide_id = (int)$slide_id;
    $query = "SELECT c.*, u.first_name, u.last_name, u.email
              FROM carousel_slides c
              JOIN users u ON c.created_by = u.id
              WHERE c.id = ? AND c.status = 'pending'";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $slide_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    return mysqli_fetch_assoc($result);
}

/**
 * Approve a pending carousel slide
 * @param mysqli $conn Database connection
 * @param int $slide_id Slide ID
 * @param int $approver_id Approver user ID
 * @return bool Success status
 */
function approve_carousel_slide($conn, $slide_id, $approver_id) {
    $slide_id = (int)$slide_id;
    $approver_id = (int)$approver_id;

    $query = "UPDATE carousel_slides SET
              status = 'approved',
              approved_by = ?,
              is_active = 1
              WHERE id = ? AND status = 'pending'";

    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "ii", $approver_id, $slide_id);
    return mysqli_stmt_execute($stmt);
}

/**
 * Reject a pending carousel slide
 * @param mysqli $conn Database connection
 * @param int $slide_id Slide ID
 * @param int $rejecter_id Rejecter user ID
 * @param string $rejection_reason Reason for rejection
 * @return bool Success status
 */
function reject_carousel_slide($conn, $slide_id, $rejecter_id, $rejection_reason = '') {
    $slide_id = (int)$slide_id;
    $rejecter_id = (int)$rejecter_id;

    $query = "UPDATE carousel_slides SET
              status = 'rejected',
              rejected_by = ?,
              rejected_at = NOW(),
              rejection_reason = ?,
              is_active = 0
              WHERE id = ? AND status = 'pending'";

    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "isi", $rejecter_id, $rejection_reason, $slide_id);
    return mysqli_stmt_execute($stmt);
}

/**
 * Delete a pending carousel slide
 * @param mysqli $conn Database connection
 * @param int $slide_id Slide ID
 * @return bool Success status
 */
function delete_pending_carousel_slide($conn, $slide_id) {
    $slide_id = (int)$slide_id;

    $query = "DELETE FROM carousel_slides WHERE id = ? AND status = 'pending'";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $slide_id);
    return mysqli_stmt_execute($stmt);
}

/**
 * Get pending carousel slides count
 * @param mysqli $conn Database connection
 * @return int Count of pending slides
 */
function get_pending_carousel_slides_count($conn) {
    $query = "SELECT COUNT(*) as total FROM carousel_slides WHERE status = 'pending'";
    $result = mysqli_query($conn, $query);
    $row = mysqli_fetch_assoc($result);
    return (int)$row['total'];
}

/**
 * Get carousel slides statistics
 * @param mysqli $conn Database connection
 * @return array Statistics data
 */
function get_carousel_slides_statistics($conn) {
    $stats = [];

    // Total pending slides
    $pending_query = "SELECT COUNT(*) as total FROM carousel_slides WHERE status = 'pending'";
    $pending_result = mysqli_query($conn, $pending_query);
    $stats['pending'] = mysqli_fetch_assoc($pending_result)['total'];

    // Total approved slides
    $approved_query = "SELECT COUNT(*) as total FROM carousel_slides WHERE status = 'approved'";
    $approved_result = mysqli_query($conn, $approved_query);
    $stats['approved'] = mysqli_fetch_assoc($approved_result)['total'];

    // Total rejected slides
    $rejected_query = "SELECT COUNT(*) as total FROM carousel_slides WHERE status = 'rejected'";
    $rejected_result = mysqli_query($conn, $rejected_query);
    $stats['rejected'] = mysqli_fetch_assoc($rejected_result)['total'];

    // Total active slides
    $active_query = "SELECT COUNT(*) as total FROM carousel_slides WHERE is_active = 1";
    $active_result = mysqli_query($conn, $active_query);
    $stats['active'] = mysqli_fetch_assoc($active_result)['total'];

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
        $slide_id = (int)$_POST['slide_id'];

        switch ($_POST['action']) {
            case 'approve':
                if (approve_carousel_slide($conn, $slide_id, $_SESSION['user_id'])) {
                    $message = "Carousel slide approved successfully!";
                    $message_type = 'success';
                } else {
                    $message = "Error approving carousel slide.";
                    $message_type = 'error';
                }
                break;

            case 'reject':
                $rejection_reason = isset($_POST['rejection_reason']) ? $_POST['rejection_reason'] : '';
                if (reject_carousel_slide($conn, $slide_id, $_SESSION['user_id'], $rejection_reason)) {
                    $message = "Carousel slide rejected successfully!";
                    $message_type = 'success';
                } else {
                    $message = "Error rejecting carousel slide.";
                    $message_type = 'error';
                }
                break;

            case 'delete':
                if (delete_pending_carousel_slide($conn, $slide_id)) {
                    $message = "Carousel slide deleted successfully!";
                    $message_type = 'success';
                } else {
                    $message = "Error deleting carousel slide.";
                    $message_type = 'error';
                }
                break;
        }
    }
}

// Get data for display
$pending_slides = get_pending_carousel_slides($conn);
$statistics = get_carousel_slides_statistics($conn);
$pending_count = get_pending_carousel_slides_count($conn);

// ========================================
// UI RENDERING
// ========================================
?>

<?php
$page_title = 'Pending Carousel Slides';
include 'includes/header.php';
?>
        <div class="p-3 sm:p-4 lg:p-8">
            <div class="mb-6 sm:mb-8">
                <h1 class="text-2xl sm:text-3xl font-bold text-seait-dark mb-2">Pending Carousel Slides</h1>
                <p class="text-gray-600">Review and approve carousel slides for the homepage</p>
            </div>

            <!-- Information Section -->
            <?php include 'includes/info-section.php'; ?>

            <!-- Message Display -->
            <?php if ($message): ?>
                <div class="mb-6 p-4 rounded-lg <?php echo $message_type === 'success' ? 'bg-green-100 text-green-700 border border-green-400' : 'bg-red-100 text-red-700 border border-red-400'; ?>">
                    <div class="flex items-center">
                        <i class="fas <?php echo $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle'; ?> mr-2"></i>
                        <?php echo $message; ?>
                    </div>
                </div>
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
                            <i class="fas fa-play-circle text-blue-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Active</p>
                            <p class="text-2xl font-bold text-gray-900"><?php echo $statistics['active']; ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pending Carousel Slides List -->
            <div class="bg-white rounded-lg shadow-md">
                <div class="p-4 sm:p-6 border-b border-gray-200">
                    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center space-y-4 sm:space-y-0">
                        <div>
                            <h3 class="text-lg font-semibold text-seait-dark">Pending for Approval</h3>
                            <p class="text-gray-600">Review and approve carousel slides before they go live</p>
                        </div>
                        <div class="flex justify-center sm:justify-end">
                            <button onclick="refreshPage()" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600 transition">
                                <i class="fas fa-refresh mr-2"></i>Refresh
                            </button>
                        </div>
                    </div>
                </div>

                <div class="p-3 sm:p-6">
                    <?php if (mysqli_num_rows($pending_slides) > 0): ?>
                        <div class="space-y-4 sm:space-y-6">
                            <?php while($slide = mysqli_fetch_assoc($pending_slides)): ?>
                            <div class="border border-gray-200 rounded-lg p-4 sm:p-6 hover:shadow-md transition">
                                <div class="flex flex-col lg:flex-row lg:justify-between lg:items-start mb-4">
                                    <div class="flex-1 mb-4 lg:mb-0">
                                        <div class="flex items-start space-x-4">
                                            <img src="../<?php echo htmlspecialchars($slide['image_url']); ?>"
                                                 alt="<?php echo nl2br(htmlspecialchars($slide['title'])); ?>"
                                                 class="w-24 h-16 object-cover rounded-lg flex-shrink-0">
                                            <div class="flex-1">
                                                <h4 class="font-semibold text-lg sm:text-xl text-seait-dark mb-2 break-words"><?php echo nl2br(htmlspecialchars($slide['title'])); ?></h4>
                                                <div class="flex flex-col sm:flex-row sm:items-center space-y-2 sm:space-y-0 sm:space-x-4 text-sm text-gray-600 mb-3">
                                                    <span class="flex items-center">
                                                        <i class="fas fa-user mr-1"></i>
                                                        <span class="truncate">by <?php echo htmlspecialchars($slide['first_name'] . ' ' . $slide['last_name']); ?></span>
                                                    </span>
                                                    <span class="flex items-center">
                                                        <i class="fas fa-calendar mr-1"></i>
                                                        <span class="truncate"><?php echo date('M d, Y H:i', strtotime($slide['created_at'])); ?></span>
                                                    </span>
                                                    <span class="flex items-center">
                                                        <i class="fas fa-sort-numeric-down mr-1"></i>
                                                        <span class="truncate">Sort: <?php echo $slide['sort_order']; ?></span>
                                                    </span>
                                                </div>
                                                <div class="flex flex-wrap gap-2">
                                                    <span class="inline-block px-3 py-1 text-xs bg-yellow-100 text-yellow-800 rounded">
                                                        Carousel Slide
                                                    </span>
                                                    <span class="inline-block px-3 py-1 text-xs bg-blue-100 text-blue-800 rounded">
                                                        Pending
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="flex flex-wrap gap-2 lg:flex-col lg:gap-2 lg:ml-4">
                                        <button onclick="approveSlide(<?php echo $slide['id']; ?>)"
                                                class="bg-green-500 text-white px-3 py-2 rounded text-sm hover:bg-green-600 transition">
                                            <i class="fas fa-check mr-1"></i><span class="hidden sm:inline">Approve</span>
                                        </button>
                                        <button onclick="rejectSlide(<?php echo $slide['id']; ?>)"
                                                class="bg-red-500 text-white px-3 py-2 rounded text-sm hover:bg-red-600 transition">
                                            <i class="fas fa-times mr-1"></i><span class="hidden sm:inline">Reject</span>
                                        </button>
                                        <button onclick="viewSlide(<?php echo $slide['id']; ?>)"
                                                class="bg-blue-500 text-white px-3 py-2 rounded text-sm hover:bg-blue-600 transition">
                                            <i class="fas fa-eye mr-1"></i><span class="hidden sm:inline">Preview</span>
                                        </button>
                                        <button onclick="deleteSlide(<?php echo $slide['id']; ?>)"
                                                class="bg-gray-500 text-white px-3 py-2 rounded text-sm hover:bg-gray-600 transition">
                                            <i class="fas fa-trash mr-1"></i><span class="hidden sm:inline">Delete</span>
                                        </button>
                                    </div>
                                </div>

                                <div class="text-gray-700">
                                    <?php if (!empty($slide['subtitle'])): ?>
                                    <div class="mb-2">
                                        <h5 class="font-medium text-gray-900">Subtitle:</h5>
                                        <p class="text-gray-600"><?php echo nl2br(htmlspecialchars($slide['subtitle'])); ?></p>
                                    </div>
                                    <?php endif; ?>

                                    <?php if (!empty($slide['description'])): ?>
                                    <div class="mb-2">
                                        <h5 class="font-medium text-gray-900">Description:</h5>
                                        <p class="text-gray-600"><?php echo nl2br(htmlspecialchars($slide['description'])); ?></p>
                                    </div>
                                    <?php endif; ?>

                                    <?php if (!empty($slide['button_text'])): ?>
                                    <div class="mb-2">
                                        <h5 class="font-medium text-gray-900">Button:</h5>
                                        <p class="text-gray-600"><?php echo nl2br(htmlspecialchars($slide['button_text'])); ?> → <?php echo htmlspecialchars($slide['button_link']); ?></p>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-8">
                            <i class="fas fa-check-circle text-green-500 text-4xl mb-4"></i>
                            <p class="text-gray-600">No pending carousel slides to review</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Enhanced Reject Modal -->
    <div id="rejectModal" class="fixed inset-0 bg-black bg-opacity-50 overflow-y-auto h-full w-full hidden z-50 modal-backdrop">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full animate-bounce-in">
                <div class="p-6">
                    <div class="flex items-center mb-4">
                        <div class="p-3 rounded-full bg-red-100 text-red-600 mr-4">
                            <i class="fas fa-times text-xl"></i>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-900">Reject Carousel Slide</h3>
                    </div>
                    <form method="POST" class="space-y-4">
                        <input type="hidden" name="action" value="reject">
                        <input type="hidden" name="slide_id" id="rejectSlideId">

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-comment mr-2 text-seait-orange"></i>
                                Rejection Reason (Optional)
                            </label>
                            <textarea name="rejection_reason" rows="4"
                                      class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-seait-orange focus:border-transparent resize-none"
                                      placeholder="Please provide a reason for rejection to help the content creator improve..."></textarea>
                        </div>

                        <div class="flex justify-end space-x-3 pt-4">
                            <button type="button" onclick="closeRejectModal()"
                                    class="px-6 py-3 text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 transition-all duration-200">
                                <i class="fas fa-times mr-2"></i>Cancel
                            </button>
                            <button type="submit"
                                    class="px-6 py-3 bg-gradient-to-r from-red-500 to-red-600 text-white rounded-lg hover:from-red-600 hover:to-red-700 transition-all duration-200 font-semibold">
                                <i class="fas fa-times mr-2"></i>Reject Slide
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Enhanced Delete Confirmation Modal -->
    <div id="deleteModal" class="fixed inset-0 bg-black bg-opacity-50 overflow-y-auto h-full w-full hidden z-50 modal-backdrop">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full animate-bounce-in">
                <div class="p-6 text-center">
                    <div class="mb-4">
                        <div class="p-4 rounded-full bg-red-100 text-red-600 inline-block mb-4">
                            <i class="fas fa-exclamation-triangle text-3xl"></i>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-900 mb-2">Confirm Delete</h3>
                        <p class="text-gray-600">Are you sure you want to delete this carousel slide? This action cannot be undone.</p>
                    </div>
                    <form method="POST">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="slide_id" id="deleteSlideId">
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

    <!-- Success Notification -->
    <div id="successNotification" class="fixed top-4 right-4 bg-green-500 text-white px-6 py-4 rounded-lg shadow-lg transform translate-x-full transition-transform duration-300 z-50">
        <div class="flex items-center">
            <i class="fas fa-check-circle mr-3"></i>
            <span id="successMessage">Action completed successfully!</span>
        </div>
    </div>

    <!-- Enhanced Approval Confirmation Modal -->
    <div id="approvalModal" class="fixed inset-0 bg-black bg-opacity-50 overflow-y-auto h-full w-full hidden z-50 modal-backdrop">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full animate-bounce-in">
                <div class="p-6 text-center">
                    <div class="mb-4">
                        <div class="p-4 rounded-full bg-green-100 text-green-600 inline-block mb-4">
                            <i class="fas fa-check-circle text-3xl"></i>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-900 mb-2">Approve Carousel Slide</h3>
                        <p class="text-gray-600 mb-4">Are you sure you want to approve this carousel slide? It will be displayed on the homepage.</p>
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 mb-4">
                            <div class="flex items-center text-blue-800">
                                <i class="fas fa-info-circle mr-2"></i>
                                <span class="text-sm font-medium">This action will:</span>
                            </div>
                            <ul class="text-sm text-blue-700 mt-2 text-left space-y-1">
                                <li class="flex items-center">
                                    <i class="fas fa-check mr-2 text-green-500"></i>
                                    Set slide status to "Approved"
                                </li>
                                <li class="flex items-center">
                                    <i class="fas fa-check mr-2 text-green-500"></i>
                                    Display on the homepage carousel
                                </li>
                            </ul>
                        </div>
                    </div>
                    <form id="approvalForm" method="POST" class="space-y-3">
                        <input type="hidden" name="action" value="approve">
                        <input type="hidden" name="slide_id" id="approvalSlideId">
                        <div class="flex justify-center space-x-3">
                            <button type="button" onclick="closeApprovalModal()"
                                    class="px-6 py-3 text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 transition-all duration-200">
                                <i class="fas fa-times mr-2"></i>Cancel
                            </button>
                            <button type="submit"
                                    class="px-6 py-3 bg-gradient-to-r from-green-500 to-green-600 text-white rounded-lg hover:from-green-600 hover:to-green-700 transition-all duration-200 font-semibold">
                                <i class="fas fa-check mr-2"></i>Approve Slide
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

                </div>
            </div>
        </div>
    </main>
</div>
</div>

                <script>
                    function approveSlide(slideId) {
                        document.getElementById('approvalSlideId').value = slideId;
                        document.getElementById('approvalModal').classList.remove('hidden');
                    }

                    function rejectSlide(slideId) {
                        document.getElementById('rejectSlideId').value = slideId;
                        document.getElementById('rejectModal').classList.remove('hidden');
                    }

                    function closeRejectModal() {
                        document.getElementById('rejectModal').classList.add('hidden');
                        document.querySelector('#rejectModal textarea').value = '';
                    }

                    function deleteSlide(slideId) {
                        document.getElementById('deleteSlideId').value = slideId;
                        document.getElementById('deleteModal').classList.remove('hidden');
                    }

                    function closeDeleteModal() {
                        document.getElementById('deleteModal').classList.add('hidden');
                    }

                    function viewSlide(slideId) {
                        // Open in new window/tab for preview
                        window.open(`../index.php#home`, '_blank');
                    }

                    function showSuccessNotification(message) {
                        const notification = document.getElementById('successNotification');
                        const messageElement = document.getElementById('successMessage');
                        messageElement.textContent = message;

                        notification.classList.remove('translate-x-full');
                        notification.classList.add('translate-x-0');

                        setTimeout(() => {
                            notification.classList.remove('translate-x-0');
                            notification.classList.add('translate-x-full');
                        }, 3000);
                    }

                    function closeApprovalModal() {
                        document.getElementById('approvalModal').classList.add('hidden');
                    }

                    // Close modals when clicking outside
                    window.onclick = function(event) {
                        const rejectModal = document.getElementById('rejectModal');
                        const deleteModal = document.getElementById('deleteModal');
                        const approvalModal = document.getElementById('approvalModal');

                        if (event.target === rejectModal) {
                            closeRejectModal();
                        }
                        if (event.target === deleteModal) {
                            closeDeleteModal();
                        }
                        if (event.target === approvalModal) {
                            closeApprovalModal();
                        }
                    }

                    // Show success notification if there's a success message
                    <?php if ($message && $message_type === 'success'): ?>
                    document.addEventListener('DOMContentLoaded', function() {
                        showSuccessNotification('<?php echo addslashes($message); ?>');
                    });
                    <?php endif; ?>

                    // Add hover effects to action buttons
                    document.addEventListener('DOMContentLoaded', function() {
                        const actionButtons = document.querySelectorAll('.action-button');
                        actionButtons.forEach(button => {
                            button.addEventListener('mouseenter', function() {
                                this.style.transform = 'scale(1.05)';
                            });
                            button.addEventListener('mouseleave', function() {
                                this.style.transform = 'scale(1)';
                            });
                        });
                    });

                    // Refresh page on button click
                    function refreshPage() {
                        window.location.reload();
                    }
                </script>
</body>
</html>