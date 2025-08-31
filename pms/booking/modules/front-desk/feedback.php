<?php
session_start();
require_once '../../../includes/error_handler.php';
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

// Check if user is logged in and has front desk access
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['front_desk', 'manager'])) {
    header('Location: ../../login.php');
    exit();
}

$user_role = $_SESSION['user_role'];
$user_name = $_SESSION['user_name'];

// Fetch feedback data from database
try {
    // Get feedback with guest information
    $stmt = $pdo->prepare("
        SELECT 
            gf.id,
            gf.rating,
            gf.feedback_type,
            gf.category,
            gf.comments,
            gf.is_resolved,
            gf.created_at,
            g.first_name,
            g.last_name,
            g.email,
            r.reservation_number,
            rm.room_number,
            CASE 
                WHEN gf.is_resolved = 1 THEN 'resolved'
                WHEN gf.feedback_type = 'complaint' THEN 'in_progress'
                ELSE 'new'
            END as status
        FROM guest_feedback gf
        JOIN guests g ON gf.guest_id = g.id
        LEFT JOIN reservations r ON gf.reservation_id = r.id
        LEFT JOIN rooms rm ON r.room_id = rm.id
        ORDER BY gf.created_at DESC
        LIMIT 50
    ");
    $stmt->execute();
    $feedback_list = $stmt->fetchAll();

    // Get feedback statistics
    $stmt = $pdo->prepare("
        SELECT 
            AVG(rating) as avg_rating,
            COUNT(*) as total_reviews,
            SUM(CASE WHEN is_resolved = 0 THEN 1 ELSE 0 END) as pending_response,
            SUM(CASE WHEN rating >= 4 THEN 1 ELSE 0 END) * 100.0 / COUNT(*) as satisfaction_rate
        FROM guest_feedback
        WHERE rating IS NOT NULL
    ");
    $stmt->execute();
    $feedback_stats = $stmt->fetch();

    // Get rating distribution
    $stmt = $pdo->prepare("
        SELECT 
            rating,
            COUNT(*) as count,
            COUNT(*) * 100.0 / (SELECT COUNT(*) FROM guest_feedback WHERE rating IS NOT NULL) as percentage
        FROM guest_feedback
        WHERE rating IS NOT NULL
        GROUP BY rating
        ORDER BY rating DESC
    ");
    $stmt->execute();
    $rating_distribution = $stmt->fetchAll();

    // Get feedback categories
    $stmt = $pdo->prepare("
        SELECT 
            category,
            COUNT(*) as count
        FROM guest_feedback
        GROUP BY category
        ORDER BY count DESC
    ");
    $stmt->execute();
    $feedback_categories = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log("Error fetching feedback: " . $e->getMessage());
    $feedback_list = [];
    $feedback_stats = [
        'avg_rating' => 0,
        'total_reviews' => 0,
        'pending_response' => 0,
        'satisfaction_rate' => 0
    ];
    $rating_distribution = [];
    $feedback_categories = [];
}

// Set page title
$page_title = 'Guest Feedback Management';

// Include unified navigation (automatically selects based on user role)
include '../../includes/header-unified.php';
include '../../includes/sidebar-unified.php';
?>

        <!-- Main Content -->
        <main class="lg:ml-64 mt-16 p-4 lg:p-6 flex-1 transition-all duration-300">
            <!-- Feedback Statistics Section -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <h2 class="text-xl font-semibold text-gray-800 mb-4">Feedback Statistics</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                        <div class="flex items-center">
                            <div class="p-3 bg-blue-100 rounded-lg">
                                <i class="fas fa-star text-blue-600 text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-600">Average Rating</p>
                                <p class="text-2xl font-bold text-gray-900"><?php echo number_format($feedback_stats['avg_rating'], 1); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                        <div class="flex items-center">
                            <div class="p-3 bg-green-100 rounded-lg">
                                <i class="fas fa-comments text-green-600 text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-600">Total Reviews</p>
                                <p class="text-2xl font-bold text-gray-900"><?php echo $feedback_stats['total_reviews']; ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                        <div class="flex items-center">
                            <div class="p-3 bg-yellow-100 rounded-lg">
                                <i class="fas fa-clock text-yellow-600 text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-600">Pending Response</p>
                                <p class="text-2xl font-bold text-gray-900"><?php echo $feedback_stats['pending_response']; ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                        <div class="flex items-center">
                            <div class="p-3 bg-purple-100 rounded-lg">
                                <i class="fas fa-heart text-purple-600 text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-600">Satisfaction Rate</p>
                                <p class="text-2xl font-bold text-gray-900"><?php echo number_format($feedback_stats['satisfaction_rate'], 1); ?>%</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Feedback Overview Section -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <h2 class="text-xl font-semibold text-gray-800 mb-4">Feedback Overview</h2>
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                    <!-- Rating Distribution -->
                    <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Rating Distribution</h3>
                        <div class="space-y-3">
                            <?php foreach ($rating_distribution as $rating): ?>
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center">
                                        <span class="text-sm font-medium text-gray-600"><?php echo $rating['rating']; ?> Stars</span>
                                        <div class="ml-3 flex-1 bg-gray-200 rounded-full h-2">
                                            <div class="bg-yellow-400 h-2 rounded-full" style="width: <?php echo $rating['percentage']; ?>%"></div>
                                        </div>
                                    </div>
                                    <span class="text-sm text-gray-500"><?php echo $rating['count']; ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Feedback Categories -->
                    <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Feedback Categories</h3>
                        <div class="space-y-3">
                            <?php foreach ($feedback_categories as $category): ?>
                                <div class="flex items-center justify-between">
                                    <span class="text-sm font-medium text-gray-600"><?php echo htmlspecialchars($category['category']); ?></span>
                                    <span class="text-sm text-gray-500"><?php echo $category['count']; ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Recent Activity -->
                    <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Recent Activity</h3>
                        <div class="space-y-3">
                            <div class="flex items-start space-x-3">
                                <div class="w-2 h-2 bg-blue-500 rounded-full mt-2"></div>
                                <div>
                                    <p class="text-sm font-medium">New feedback received</p>
                                    <p class="text-xs text-gray-500">2 minutes ago</p>
                                </div>
                            </div>
                            <div class="flex items-start space-x-3">
                                <div class="w-2 h-2 bg-green-500 rounded-full mt-2"></div>
                                <div>
                                    <p class="text-sm font-medium">Feedback resolved</p>
                                    <p class="text-xs text-gray-500">15 minutes ago</p>
                                </div>
                            </div>
                            <div class="flex items-start space-x-3">
                                <div class="w-2 h-2 bg-yellow-500 rounded-full mt-2"></div>
                                <div>
                                    <p class="text-sm font-medium">Low rating alert</p>
                                    <p class="text-xs text-gray-500">1 hour ago</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Feedback List Section -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-semibold text-gray-800 mb-4">Feedback List</h2>
                <div class="bg-gray-50 rounded-lg border border-gray-200">
                    <div class="p-6 border-b border-gray-200">
                        <div class="flex items-center justify-between">
                            <div class="text-sm font-medium text-gray-700">Search and Filter</div>
                            <div class="flex space-x-2">
                                <div class="relative">
                                    <input type="text" id="search-feedback" placeholder="Search feedback..." class="pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                    <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
                                </div>
                                <select id="rating-filter" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                    <option value="">All Ratings</option>
                                    <option value="5">5 Stars</option>
                                    <option value="4">4 Stars</option>
                                    <option value="3">3 Stars</option>
                                    <option value="2">2 Stars</option>
                                    <option value="1">1 Star</option>
                                </select>
                                <select id="status-filter" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                    <option value="">All Status</option>
                                    <option value="new">New</option>
                                    <option value="in_progress">In Progress</option>
                                    <option value="resolved">Resolved</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Guest</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rating</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Comments</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if (empty($feedback_list)): ?>
                                    <tr>
                                        <td colspan="7" class="px-6 py-4 text-center text-gray-500">
                                            <i class="fas fa-comments text-2xl mb-2"></i>
                                            <p>No feedback found</p>
                                            <p class="text-sm">Guest feedback will appear here when submitted</p>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($feedback_list as $feedback): ?>
                                        <tr class="hover:bg-gray-50 feedback-row" data-rating="<?php echo $feedback['rating']; ?>" data-status="<?php echo $feedback['status']; ?>">
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="flex items-center">
                                                    <div class="w-10 h-10 bg-gradient-to-r from-blue-500 to-purple-500 rounded-full flex items-center justify-center">
                                                        <span class="text-white font-medium"><?php echo strtoupper(substr($feedback['first_name'], 0, 1) . substr($feedback['last_name'], 0, 1)); ?></span>
                                                    </div>
                                                    <div class="ml-4">
                                                        <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($feedback['first_name'] . ' ' . $feedback['last_name']); ?></div>
                                                        <div class="text-sm text-gray-500"><?php echo htmlspecialchars($feedback['email']); ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="flex items-center">
                                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                                        <i class="fas fa-star <?php echo $i <= $feedback['rating'] ? 'text-yellow-400' : 'text-gray-300'; ?> text-sm"></i>
                                                    <?php endfor; ?>
                                                    <span class="ml-2 text-sm text-gray-500">(<?php echo $feedback['rating']; ?>)</span>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">
                                                    <?php echo htmlspecialchars($feedback['category']); ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4">
                                                <span class="text-sm text-gray-900">
                                                    <?php echo htmlspecialchars(substr($feedback['comments'], 0, 100) . (strlen($feedback['comments']) > 100 ? '...' : '')); ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <?php
                                                $status_class = '';
                                                $status_text = '';
                                                switch ($feedback['status']) {
                                                    case 'new':
                                                        $status_class = 'bg-blue-100 text-blue-800';
                                                        $status_text = 'New';
                                                        break;
                                                    case 'in_progress':
                                                        $status_class = 'bg-yellow-100 text-yellow-800';
                                                        $status_text = 'In Progress';
                                                        break;
                                                    case 'resolved':
                                                        $status_class = 'bg-green-100 text-green-800';
                                                        $status_text = 'Resolved';
                                                        break;
                                                }
                                                ?>
                                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?php echo $status_class; ?>">
                                                    <?php echo $status_text; ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo date('M j, Y', strtotime($feedback['created_at'])); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                <div class="flex space-x-2">
                                                    <button class="text-blue-600 hover:text-blue-900" onclick="viewFeedback(<?php echo $feedback['id']; ?>)">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <button class="text-green-600 hover:text-green-900" onclick="replyFeedback(<?php echo $feedback['id']; ?>)">
                                                        <i class="fas fa-reply"></i>
                                                    </button>
                                                    <button class="text-red-600 hover:text-red-900" onclick="flagFeedback(<?php echo $feedback['id']; ?>)">
                                                        <i class="fas fa-flag"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="px-6 py-4 border-t border-gray-200">
                        <div class="flex items-center justify-between">
                            <div class="text-sm text-gray-700">
                                Showing <span class="font-medium"><?php echo count($feedback_list); ?></span> feedback entries
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="../../assets/js/main.js"></script>
    
    <script>
        // Search functionality
        $('#search-feedback').on('input', function() {
            const searchTerm = $(this).val().toLowerCase();
            $('.feedback-row').each(function() {
                const guestName = $(this).find('td:first').text().toLowerCase();
                const guestEmail = $(this).find('td:first .text-gray-500').text().toLowerCase();
                const comments = $(this).find('td:nth-child(4)').text().toLowerCase();
                
                if (guestName.includes(searchTerm) || guestEmail.includes(searchTerm) || comments.includes(searchTerm)) {
                    $(this).show();
                } else {
                    $(this).hide();
                }
            });
        });

        // Rating filter
        $('#rating-filter').on('change', function() {
            const selectedRating = $(this).val();
            $('.feedback-row').each(function() {
                const feedbackRating = $(this).data('rating');
                
                if (selectedRating === '' || feedbackRating == selectedRating) {
                    $(this).show();
                } else {
                    $(this).hide();
                }
            });
        });

        // Status filter
        $('#status-filter').on('change', function() {
            const selectedStatus = $(this).val();
            $('.feedback-row').each(function() {
                const feedbackStatus = $(this).data('status');
                
                if (selectedStatus === '' || feedbackStatus === selectedStatus) {
                    $(this).show();
                } else {
                    $(this).hide();
                }
            });
        });

        // Feedback action functions
        function viewFeedback(feedbackId) {
            // Redirect to feedback details page
            window.location.href = `feedback-details.php?id=${feedbackId}`;
        }

        function replyFeedback(feedbackId) {
            // Open reply modal or redirect to reply page
            window.location.href = `reply-feedback.php?id=${feedbackId}`;
        }

        function flagFeedback(feedbackId) {
            if (confirm('Are you sure you want to flag this feedback for review?')) {
                // AJAX call to flag feedback
                $.post('../../api/flag-feedback.php', {feedback_id: feedbackId}, function(response) {
                    if (response.success) {
                        // Show success notification
                        showNotification('Feedback flagged successfully', 'success');
                        // Reload the page to reflect changes
                        setTimeout(() => {
                            location.reload();
                        }, 1000);
                    } else {
                        showNotification('Error flagging feedback: ' + response.message, 'error');
                    }
                }).fail(function() {
                    showNotification('Error flagging feedback. Please try again.', 'error');
                });
            }
        }

        // Notification function
        function showNotification(message, type) {
            const notification = $(`
                <div class="fixed top-4 right-4 z-50 p-4 rounded-lg shadow-lg transition-all duration-300 transform translate-x-full">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i class="fas ${type === 'success' ? 'fa-check-circle text-green-500' : 'fa-exclamation-circle text-red-500'}"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-gray-900">${message}</p>
                        </div>
                        <div class="ml-auto pl-3">
                            <button class="text-gray-400 hover:text-gray-600" onclick="$(this).parent().parent().parent().remove()">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                </div>
            `);
            
            $('body').append(notification);
            
            // Animate in
            setTimeout(() => {
                notification.removeClass('translate-x-full');
            }, 100);
            
            // Auto remove after 5 seconds
            setTimeout(() => {
                notification.addClass('translate-x-full');
                setTimeout(() => {
                    notification.remove();
                }, 300);
            }, 5000);
        }

        // Initialize tooltips and other UI enhancements
        $(document).ready(function() {
            // Add hover effects to feedback rows
            $('.feedback-row').hover(
                function() {
                    $(this).addClass('bg-blue-50');
                },
                function() {
                    $(this).removeClass('bg-blue-50');
                }
            );

            // Add click to expand comments functionality
            $('.feedback-row td:nth-child(4)').click(function() {
                const comments = $(this).find('span').text();
                if (comments.length > 100) {
                    // Show full comments in a modal or expand the cell
                    showCommentsModal(comments);
                }
            });

            // Initialize any additional UI components
            console.log('Feedback management page loaded successfully');
        });

        // Function to show comments modal
        function showCommentsModal(comments) {
            const modal = $(`
                <div class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50" id="commentsModal">
                    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
                        <div class="mt-3">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Full Comments</h3>
                            <p class="text-sm text-gray-700 mb-4">${comments}</p>
                            <div class="flex justify-end">
                                <button class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400" onclick="$('#commentsModal').remove()">
                                    Close
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `);
            
            $('body').append(modal);
        }
    </script>
</body>
</html>