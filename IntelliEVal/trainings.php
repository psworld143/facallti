<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and has guidance_officer role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'guidance_officer') {
    header('Location: ../index.php');
    exit();
}

// Set page title
$page_title = 'Training & Seminar Management';

$message = '';
$message_type = '';

// Check for session messages
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $message_type = $_SESSION['message_type'];
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
}

// Get filter parameters
$selected_category = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$selected_type = isset($_GET['type']) ? $_GET['type'] : '';
$selected_status = isset($_GET['status']) ? $_GET['status'] : '';
$selected_main_category = isset($_GET['main_category']) ? (int)$_GET['main_category'] : 0;

// Get available categories for filter
$categories_query = "SELECT id, name FROM training_categories WHERE status = 'active' ORDER BY name";
$categories_result = mysqli_query($conn, $categories_query);

// Get available main categories for filter
$main_categories_query = "SELECT id, name FROM main_evaluation_categories WHERE status = 'active' ORDER BY name";
$main_categories_result = mysqli_query($conn, $main_categories_query);

// Build the main query with filters
$where_conditions = ["1=1"];
$params = [];
$param_types = "";

if ($selected_category > 0) {
    $where_conditions[] = "ts.category_id = ?";
    $params[] = $selected_category;
    $param_types .= "i";
}

if ($selected_type !== '') {
    $where_conditions[] = "ts.type = ?";
    $params[] = $selected_type;
    $param_types .= "s";
}

if ($selected_status !== '') {
    $where_conditions[] = "ts.status = ?";
    $params[] = $selected_status;
    $param_types .= "s";
}

if ($selected_main_category > 0) {
    $where_conditions[] = "ts.main_category_id = ?";
    $params[] = $selected_main_category;
    $param_types .= "i";
}

$where_clause = implode(" AND ", $where_conditions);

// Get trainings/seminars with filters
$trainings_query = "SELECT ts.*,
                   tc.name as category_name,
                   mec.name as main_category_name,
                   esc.name as sub_category_name,
                   u.first_name, u.last_name,
                   COUNT(tr.id) as registered_count,
                   COUNT(CASE WHEN tr.status = 'completed' THEN 1 END) as completed_count
                   FROM trainings_seminars ts
                   LEFT JOIN training_categories tc ON ts.category_id = tc.id
                   LEFT JOIN main_evaluation_categories mec ON ts.main_category_id = mec.id
                   LEFT JOIN evaluation_sub_categories esc ON ts.sub_category_id = esc.id
                   LEFT JOIN users u ON ts.created_by = u.id
                   LEFT JOIN training_registrations tr ON ts.id = tr.training_id
                   WHERE $where_clause
                   GROUP BY ts.id
                   ORDER BY ts.start_date DESC, ts.created_at DESC";

if (!empty($params)) {
    $stmt = mysqli_prepare($conn, $trainings_query);
    mysqli_stmt_bind_param($stmt, $param_types, ...$params);
    mysqli_stmt_execute($stmt);
    $trainings_result = mysqli_stmt_get_result($stmt);
} else {
    $trainings_result = mysqli_query($conn, $trainings_query);
}

// Get training statistics
$stats_query = "SELECT
                COUNT(*) as total_trainings,
                COUNT(CASE WHEN status = 'published' THEN 1 END) as published_trainings,
                COUNT(CASE WHEN status = 'ongoing' THEN 1 END) as ongoing_trainings,
                COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_trainings,
                COUNT(CASE WHEN type = 'training' THEN 1 END) as training_count,
                COUNT(CASE WHEN type = 'seminar' THEN 1 END) as seminar_count,
                COUNT(CASE WHEN type = 'workshop' THEN 1 END) as workshop_count,
                COUNT(CASE WHEN type = 'conference' THEN 1 END) as conference_count
                FROM trainings_seminars";

$stats_result = mysqli_query($conn, $stats_query);
$stats = mysqli_fetch_assoc($stats_result);

// Get upcoming trainings
$upcoming_query = "SELECT ts.*, tc.name as category_name, COUNT(tr.id) as registered_count
                   FROM trainings_seminars ts
                   LEFT JOIN training_categories tc ON ts.category_id = tc.id
                   LEFT JOIN training_registrations tr ON ts.id = tr.training_id
                   WHERE ts.status = 'published' AND ts.start_date > NOW()
                   GROUP BY ts.id
                   ORDER BY ts.start_date ASC
                   LIMIT 5";

$upcoming_result = mysqli_query($conn, $upcoming_query);

// Get recent suggestions
$suggestions_query = "SELECT tsg.*, ts.title as training_title, u.first_name, u.last_name, esc.name as category_name
                      FROM training_suggestions tsg
                      JOIN trainings_seminars ts ON tsg.training_id = ts.id
                      JOIN users u ON tsg.user_id = u.id
                      LEFT JOIN evaluation_sub_categories esc ON tsg.evaluation_category_id = esc.id
                      WHERE tsg.status = 'pending'
                      ORDER BY tsg.suggestion_date DESC
                      LIMIT 10";

$suggestions_result = mysqli_query($conn, $suggestions_query);

// Include the shared header
include 'includes/header.php';
?>

<!-- Custom CSS for trainings page -->
<link rel="stylesheet" href="assets/css/trainings.css">

<div class="mb-6 sm:mb-8">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-seait-dark mb-2">Training & Seminar Management</h1>
            <p class="text-sm sm:text-base text-gray-600">Manage trainings, seminars, and professional development programs</p>
        </div>
        <div class="flex space-x-2">
            <a href="add-training.php" class="bg-seait-orange text-white px-4 py-2 rounded-lg hover:bg-orange-600 transition text-center">
                <i class="fas fa-plus mr-2"></i>Add Training
            </a>
            <a href="training-suggestions.php" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition text-center">
                <i class="fas fa-lightbulb mr-2"></i>View Suggestions
            </a>
        </div>
    </div>
</div>

<?php if ($message): ?>
<div class="mb-6 p-4 rounded-lg <?php echo $message_type === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
    <?php echo htmlspecialchars($message); ?>
</div>
<?php endif; ?>

<!-- Statistics Cards -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    <div class="bg-white p-6 rounded-lg shadow-md">
        <div class="flex items-center">
            <div class="p-3 bg-blue-100 rounded-full">
                <i class="fas fa-graduation-cap text-blue-600 text-xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-600">Total Trainings</p>
                <p class="text-2xl font-bold text-gray-900"><?php echo $stats['total_trainings']; ?></p>
            </div>
        </div>
    </div>

    <div class="bg-white p-6 rounded-lg shadow-md">
        <div class="flex items-center">
            <div class="p-3 bg-green-100 rounded-full">
                <i class="fas fa-check-circle text-green-600 text-xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-600">Published</p>
                <p class="text-2xl font-bold text-gray-900"><?php echo $stats['published_trainings']; ?></p>
                <p class="text-xs text-green-600"><?php echo $stats['ongoing_trainings']; ?> ongoing</p>
            </div>
        </div>
    </div>

    <div class="bg-white p-6 rounded-lg shadow-md">
        <div class="flex items-center">
            <div class="p-3 bg-purple-100 rounded-full">
                <i class="fas fa-users text-purple-600 text-xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-600">Completed</p>
                <p class="text-2xl font-bold text-gray-900"><?php echo $stats['completed_trainings']; ?></p>
            </div>
        </div>
    </div>

    <div class="bg-white p-6 rounded-lg shadow-md">
        <div class="flex items-center">
            <div class="p-3 bg-orange-100 rounded-full">
                <i class="fas fa-chart-pie text-orange-600 text-xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-600">Types</p>
                <p class="text-2xl font-bold text-gray-900"><?php echo $stats['training_count'] + $stats['seminar_count'] + $stats['workshop_count'] + $stats['conference_count']; ?></p>
                <p class="text-xs text-orange-600"><?php echo $stats['training_count']; ?> trainings, <?php echo $stats['seminar_count']; ?> seminars</p>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="bg-white rounded-lg shadow-md p-6 mb-8">
    <h3 class="text-lg font-semibold text-gray-900 mb-4">Filter Trainings</h3>
    <form method="GET" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Category</label>
            <select name="category" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                <option value="">All Categories</option>
                <?php while ($category = mysqli_fetch_assoc($categories_result)): ?>
                <option value="<?php echo $category['id']; ?>" <?php echo $selected_category == $category['id'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($category['name']); ?>
                </option>
                <?php endwhile; ?>
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Type</label>
            <select name="type" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                <option value="">All Types</option>
                <option value="training" <?php echo $selected_type === 'training' ? 'selected' : ''; ?>>Training</option>
                <option value="seminar" <?php echo $selected_type === 'seminar' ? 'selected' : ''; ?>>Seminar</option>
                <option value="workshop" <?php echo $selected_type === 'workshop' ? 'selected' : ''; ?>>Workshop</option>
                <option value="conference" <?php echo $selected_type === 'conference' ? 'selected' : ''; ?>>Conference</option>
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
            <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                <option value="">All Status</option>
                <option value="draft" <?php echo $selected_status === 'draft' ? 'selected' : ''; ?>>Draft</option>
                <option value="published" <?php echo $selected_status === 'published' ? 'selected' : ''; ?>>Published</option>
                <option value="ongoing" <?php echo $selected_status === 'ongoing' ? 'selected' : ''; ?>>Ongoing</option>
                <option value="completed" <?php echo $selected_status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                <option value="cancelled" <?php echo $selected_status === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Evaluation Category</label>
            <select name="main_category" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                <option value="">All Categories</option>
                <?php while ($main_category = mysqli_fetch_assoc($main_categories_result)): ?>
                <option value="<?php echo $main_category['id']; ?>" <?php echo $selected_main_category == $main_category['id'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($main_category['name']); ?>
                </option>
                <?php endwhile; ?>
            </select>
        </div>

        <div class="flex items-end">
            <button type="submit" class="w-full bg-seait-orange text-white px-4 py-2 rounded-md hover:bg-orange-600 transition-colors">
                <i class="fas fa-filter mr-2"></i>Apply Filters
            </button>
        </div>
    </form>
</div>

<!-- Quick Overview -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
    <!-- Upcoming Trainings -->
    <div class="bg-white rounded-lg shadow-md p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Upcoming Trainings</h3>
        <div class="space-y-3">
            <?php if (mysqli_num_rows($upcoming_result) > 0): ?>
                <?php while($training = mysqli_fetch_assoc($upcoming_result)): ?>
                <div class="flex items-center justify-between p-3 bg-gray-50 rounded">
                    <div>
                        <p class="font-medium text-sm"><?php echo htmlspecialchars($training['title']); ?></p>
                        <p class="text-xs text-gray-600"><?php echo date('M d, Y', strtotime($training['start_date'])); ?> • <?php echo htmlspecialchars($training['category_name']); ?></p>
                    </div>
                    <div class="text-right">
                        <p class="font-medium text-sm"><?php echo $training['registered_count']; ?>/<?php echo $training['max_participants']; ?></p>
                        <p class="text-xs text-blue-600"><?php echo $training['type']; ?></p>
                    </div>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="text-center py-4">
                    <i class="fas fa-calendar text-gray-300 text-2xl mb-2"></i>
                    <p class="text-gray-500 text-sm">No upcoming trainings</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Recent Suggestions -->
    <div class="bg-white rounded-lg shadow-md p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Recent Training Suggestions</h3>
        <div class="space-y-3">
            <?php if (mysqli_num_rows($suggestions_result) > 0): ?>
                <?php while($suggestion = mysqli_fetch_assoc($suggestions_result)): ?>
                <div class="flex items-center justify-between p-3 bg-gray-50 rounded">
                    <div>
                        <p class="font-medium text-sm"><?php echo htmlspecialchars($suggestion['first_name'] . ' ' . $suggestion['last_name']); ?></p>
                        <p class="text-xs text-gray-600"><?php echo htmlspecialchars($suggestion['training_title']); ?></p>
                    </div>
                    <div class="text-right">
                        <span class="px-2 py-1 text-xs rounded <?php
                            echo $suggestion['priority_level'] === 'critical' ? 'bg-red-100 text-red-800' :
                                ($suggestion['priority_level'] === 'high' ? 'bg-orange-100 text-orange-800' :
                                ($suggestion['priority_level'] === 'medium' ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800'));
                        ?>">
                            <?php echo ucfirst($suggestion['priority_level']); ?>
                        </span>
                    </div>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="text-center py-4">
                    <i class="fas fa-lightbulb text-gray-300 text-2xl mb-2"></i>
                    <p class="text-gray-500 text-sm">No pending suggestions</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Trainings Table -->
<div class="bg-white rounded-lg shadow-md p-6">
    <div class="flex justify-between items-center mb-6">
        <h3 class="text-lg font-semibold text-gray-900">All Trainings & Seminars</h3>
        <div class="flex space-x-2">
            <a href="export_trainings.php" class="btn-success btn-sm">
                <i class="fas fa-download mr-2"></i>Export
            </a>
        </div>
    </div>

    <!-- Desktop Table -->
    <div class="table-container">
        <table class="responsive-table">
            <thead>
                <tr>
                    <th>Training</th>
                    <th>Category</th>
                    <th>Type</th>
                    <th>Date</th>
                    <th>Status</th>
                    <th>Participants</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php
                // Reset the result pointer
                mysqli_data_seek($trainings_result, 0);
                while($training = mysqli_fetch_assoc($trainings_result)):
                ?>
                <tr>
                    <td>
                        <div>
                            <p class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($training['title']); ?></p>
                            <p class="text-xs text-gray-500"><?php echo htmlspecialchars($training['venue'] ?? 'TBD'); ?></p>
                        </div>
                    </td>
                    <td>
                        <div>
                            <p class="text-sm text-gray-900"><?php echo htmlspecialchars($training['category_name'] ?? 'N/A'); ?></p>
                            <?php if ($training['sub_category_name']): ?>
                            <p class="text-xs text-gray-500"><?php echo htmlspecialchars($training['sub_category_name']); ?></p>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td>
                        <span class="type-<?php echo $training['type']; ?>">
                            <?php echo ucfirst($training['type']); ?>
                        </span>
                    </td>
                    <td class="text-sm text-gray-900">
                        <?php echo date('M d, Y', strtotime($training['start_date'])); ?>
                        <br>
                        <span class="text-xs text-gray-500"><?php echo date('H:i', strtotime($training['start_date'])); ?> - <?php echo date('H:i', strtotime($training['end_date'])); ?></span>
                    </td>
                    <td>
                        <span class="status-<?php echo $training['status']; ?>">
                            <?php echo ucfirst($training['status']); ?>
                        </span>
                    </td>
                    <td class="text-sm text-gray-900">
                        <?php echo $training['registered_count']; ?>/<?php echo $training['max_participants'] ?? '∞'; ?>
                        <?php if ($training['completed_count'] > 0): ?>
                        <br>
                        <span class="text-xs text-green-600"><?php echo $training['completed_count']; ?> completed</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-sm font-medium">
                        <div class="flex space-x-2">
                            <a href="view-training.php?id=<?php echo $training['id']; ?>"
                               class="text-seait-orange hover:text-orange-600">
                                <i class="fas fa-eye"></i>
                            </a>
                            <a href="edit-training.php?id=<?php echo $training['id']; ?>"
                               class="text-blue-600 hover:text-blue-800">
                                <i class="fas fa-edit"></i>
                            </a>
                            <a href="training-participants.php?id=<?php echo $training['id']; ?>"
                               class="text-green-600 hover:text-green-800">
                                <i class="fas fa-users"></i>
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <!-- Mobile Cards -->
    <div class="mobile-training-cards">
        <?php
        // Reset the result pointer again for mobile cards
        mysqli_data_seek($trainings_result, 0);
        while($training = mysqli_fetch_assoc($trainings_result)):
        ?>
        <div class="mobile-training-card">
            <div class="mobile-card-header">
                <div>
                    <h4 class="mobile-card-title"><?php echo htmlspecialchars($training['title']); ?></h4>
                    <p class="mobile-card-venue"><?php echo htmlspecialchars($training['venue'] ?? 'TBD'); ?></p>
                </div>
                <div class="mobile-card-badges">
                    <span class="type-<?php echo $training['type']; ?>">
                        <?php echo ucfirst($training['type']); ?>
                    </span>
                    <span class="status-<?php echo $training['status']; ?>">
                        <?php echo ucfirst($training['status']); ?>
                    </span>
                </div>
            </div>

            <div class="mobile-card-details">
                <div class="mobile-detail-item">
                    <span class="mobile-detail-label">Category</span>
                    <span class="mobile-detail-value"><?php echo htmlspecialchars($training['category_name'] ?? 'N/A'); ?></span>
                </div>
                <div class="mobile-detail-item">
                    <span class="mobile-detail-label">Date</span>
                    <span class="mobile-detail-value"><?php echo date('M d, Y', strtotime($training['start_date'])); ?></span>
                </div>
                <div class="mobile-detail-item">
                    <span class="mobile-detail-label">Time</span>
                    <span class="mobile-detail-value"><?php echo date('H:i', strtotime($training['start_date'])); ?> - <?php echo date('H:i', strtotime($training['end_date'])); ?></span>
                </div>
                <div class="mobile-detail-item">
                    <span class="mobile-detail-label">Participants</span>
                    <span class="mobile-detail-value"><?php echo $training['registered_count']; ?>/<?php echo $training['max_participants'] ?? '∞'; ?></span>
                </div>
            </div>

            <div class="mobile-card-actions">
                <a href="view-training.php?id=<?php echo $training['id']; ?>"
                   class="mobile-action-btn view" title="View Training">
                    <i class="fas fa-eye"></i>
                </a>
                <a href="edit-training.php?id=<?php echo $training['id']; ?>"
                   class="mobile-action-btn edit" title="Edit Training">
                    <i class="fas fa-edit"></i>
                </a>
                <a href="training-participants.php?id=<?php echo $training['id']; ?>"
                   class="mobile-action-btn participants" title="View Participants">
                    <i class="fas fa-users"></i>
                </a>
            </div>
        </div>
        <?php endwhile; ?>
    </div>

    <?php if (mysqli_num_rows($trainings_result) == 0): ?>
    <div class="empty-state">
        <i class="fas fa-graduation-cap empty-state-icon"></i>
        <p class="empty-state-text">No trainings found with the selected filters.</p>
    </div>
    <?php endif; ?>
</div>

<?php
// Include the shared footer
include 'includes/footer.php';
?>