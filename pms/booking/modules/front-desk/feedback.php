<?php
session_start();
require_once '../../includes/config.php';

// Check if user is logged in and has front desk role
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'front_desk') {
    header('Location: ../../login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
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

// Set page title for unified header
$page_title = 'Guest Feedback Management';

// Include unified navigation (automatically selects based on user role)
include '../../includes/header-unified.php';
include '../../includes/sidebar-unified.php';
?>

        <!-- Main Content -->
        <main class="lg:ml-64 mt-16 p-4 lg:p-6 flex-1 transition-all duration-300">
            <div class="flex justify-between items-center mb-8">
                <h2 class="text-3xl font-semibold text-gray-800">Guest Feedback Management</h2>
                <div class="text-right">
                    <div id="current-date" class="text-sm text-gray-600"></div>
                    <div id="current-time" class="text-sm text-gray-600"></div>
                </div>
            </div>

            <!-- Page Header -->
            <div class="mb-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-2xl font-bold text-gray-800">Guest Feedback</h2>
                        <p class="text-gray-600 mt-1">Monitor and respond to guest feedback and reviews</p>
                    </div>
                    <div class="flex space-x-3">
                        <button class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors">
                            <i class="fas fa-download mr-2"></i>Export Report
                        </button>
                        <button class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition-colors">
                            <i class="fas fa-chart-bar mr-2"></i>Analytics
                        </button>
                    </div>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
                <div class="bg-white rounded-lg shadow-sm p-6 border border-gray-200">
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
                <div class="bg-white rounded-lg shadow-sm p-6 border border-gray-200">
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
                <div class="bg-white rounded-lg shadow-sm p-6 border border-gray-200">
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
                <div class="bg-white rounded-lg shadow-sm p-6 border border-gray-200">
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

            <!-- Feedback Overview -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
                <!-- Rating Distribution -->
                <div class="bg-white rounded-lg shadow-sm p-6 border border-gray-200">
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
                <div class="bg-white rounded-lg shadow-sm p-6 border border-gray-200">
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
                <div class="bg-white rounded-lg shadow-sm p-6 border border-gray-200">
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

            <!-- Feedback Table -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                <div class="p-6 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-gray-800">Feedback List</h3>
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
        </main>
    </div>

    <?php include '../../includes/footer.php'; ?>

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
                        location.reload();
                    } else {
                        alert('Error flagging feedback: ' + response.message);
                    }
                });
            }
        }
    </script>
</body>
</html>
