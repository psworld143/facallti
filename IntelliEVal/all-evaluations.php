<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and has guidance_officer role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'guidance_officer') {
    header('Location: ../login.php');
    exit();
}

// Set page title - this is important for the sidebar to work correctly
$page_title = 'All Evaluations';

$message = '';
$message_type = '';

// Check for session messages
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $message_type = $_SESSION['message_type'];
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
}

// Get search and filter parameters
$search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? sanitize_input($_GET['status']) : '';
$type_filter = isset($_GET['type']) ? sanitize_input($_GET['type']) : '';
$category_filter = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$semester_filter = isset($_GET['semester']) ? (int)$_GET['semester'] : 0;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Build query conditions
$where_conditions = ["1=1"]; // Always true condition to start
$params = [];
$param_types = '';

if ($search) {
    $where_conditions[] = "(mec.name LIKE ? OR evaluator.first_name LIKE ? OR evaluator.last_name LIKE ? OR evaluatee.first_name LIKE ? OR evaluatee.last_name LIKE ? OR evaluator.email LIKE ? OR evaluatee.email LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param, $search_param, $search_param, $search_param]);
    $param_types .= 'sssssss';
}

if ($status_filter) {
    $where_conditions[] = "es.status = ?";
    $params[] = $status_filter;
    $param_types .= 's';
}

if ($type_filter) {
    $where_conditions[] = "mec.evaluation_type = ?";
    $params[] = $type_filter;
    $param_types .= 's';
}

if ($category_filter) {
    $where_conditions[] = "es.main_category_id = ?";
    $params[] = $category_filter;
    $param_types .= 'i';
}

if ($semester_filter) {
    $where_conditions[] = "es.semester_id = ?";
    $params[] = $semester_filter;
    $param_types .= 'i';
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

// Get total count for pagination
$count_query = "SELECT COUNT(*) as total FROM evaluation_sessions es
                JOIN main_evaluation_categories mec ON es.main_category_id = mec.id
                LEFT JOIN faculty evaluator_f ON es.evaluator_id = evaluator_f.id
                LEFT JOIN users evaluator_u ON es.evaluator_id = evaluator_u.id
                LEFT JOIN faculty evaluatee_f ON es.evaluatee_id = evaluatee_f.id
                LEFT JOIN users evaluatee_u ON es.evaluatee_id = evaluatee_u.id
                LEFT JOIN semesters s ON es.semester_id = s.id
                $where_clause";
$count_stmt = mysqli_prepare($conn, $count_query);
if (!empty($params)) {
    mysqli_stmt_bind_param($count_stmt, $param_types, ...$params);
}
mysqli_stmt_execute($count_stmt);
$count_result = mysqli_stmt_get_result($count_stmt);
$total_records = mysqli_fetch_assoc($count_result)['total'];
$total_pages = ceil($total_records / $per_page);

// Get evaluations
$evaluations_query = "SELECT es.*, mec.name as category_name, mec.evaluation_type,
                      CASE
                          WHEN es.evaluator_type = 'student' THEN evaluator_s.first_name
                          WHEN es.evaluator_type = 'teacher' THEN evaluator_f.first_name
                          ELSE evaluator_u.first_name
                      END as evaluator_first_name,
                      CASE
                          WHEN es.evaluator_type = 'student' THEN evaluator_s.last_name
                          WHEN es.evaluator_type = 'teacher' THEN evaluator_f.last_name
                          ELSE evaluator_u.last_name
                      END as evaluator_last_name,
                      CASE
                          WHEN es.evaluator_type = 'student' THEN evaluator_s.email
                          WHEN es.evaluator_type = 'teacher' THEN evaluator_f.email
                          ELSE evaluator_u.email
                      END as evaluator_email,
                      es.evaluator_type as evaluator_role,
                      CASE
                          WHEN es.evaluatee_type = 'teacher' THEN evaluatee_f.first_name
                          ELSE evaluatee_u.first_name
                      END as evaluatee_first_name,
                      CASE
                          WHEN es.evaluatee_type = 'teacher' THEN evaluatee_f.last_name
                          ELSE evaluatee_u.last_name
                      END as evaluatee_last_name,
                      CASE
                          WHEN es.evaluatee_type = 'teacher' THEN evaluatee_f.email
                          ELSE evaluatee_u.email
                      END as evaluatee_email,
                      es.evaluatee_type as evaluatee_role,
                      s.name as semester_name,
                      (SELECT COUNT(*) FROM evaluation_responses WHERE evaluation_session_id = es.id) as response_count
                      FROM evaluation_sessions es
                      JOIN main_evaluation_categories mec ON es.main_category_id = mec.id
                      LEFT JOIN students evaluator_s ON es.evaluator_id = evaluator_s.id AND es.evaluator_type = 'student'
                      LEFT JOIN faculty evaluator_f ON es.evaluator_id = evaluator_f.id AND es.evaluator_type = 'teacher'
                      LEFT JOIN users evaluator_u ON es.evaluator_id = evaluator_u.id AND es.evaluator_type = 'head'
                      LEFT JOIN faculty evaluatee_f ON es.evaluatee_id = evaluatee_f.id AND es.evaluatee_type = 'teacher'
                      LEFT JOIN users evaluatee_u ON es.evaluatee_id = evaluatee_u.id AND es.evaluatee_type != 'teacher'
                      LEFT JOIN semesters s ON es.semester_id = s.id
                      $where_clause
                      ORDER BY es.created_at DESC
                      LIMIT ? OFFSET ?";
$params[] = $per_page;
$params[] = $offset;
$param_types .= 'ii';

$evaluations_stmt = mysqli_prepare($conn, $evaluations_query);
if (!empty($params)) {
    mysqli_stmt_bind_param($evaluations_stmt, $param_types, ...$params);
}
mysqli_stmt_execute($evaluations_stmt);
$evaluations_result = mysqli_stmt_get_result($evaluations_stmt);

// Get evaluation statistics
$stats_query = "SELECT
                COUNT(*) as total_evaluations,
                COALESCE(SUM(CASE WHEN es.status = 'completed' THEN 1 ELSE 0 END), 0) as completed_evaluations,
                COALESCE(SUM(CASE WHEN es.status = 'draft' THEN 1 ELSE 0 END), 0) as draft_evaluations,
                COALESCE(SUM(CASE WHEN es.status = 'cancelled' THEN 1 ELSE 0 END), 0) as cancelled_evaluations,
                COALESCE(SUM(CASE WHEN mec.evaluation_type = 'student_to_teacher' THEN 1 ELSE 0 END), 0) as student_evaluations,
                COALESCE(SUM(CASE WHEN mec.evaluation_type = 'peer_to_peer' THEN 1 ELSE 0 END), 0) as peer_evaluations,
                COALESCE(SUM(CASE WHEN mec.evaluation_type = 'head_to_teacher' THEN 1 ELSE 0 END), 0) as head_evaluations
                FROM evaluation_sessions es
                JOIN main_evaluation_categories mec ON es.main_category_id = mec.id";
$stats_result = mysqli_query($conn, $stats_query);
$stats = mysqli_fetch_assoc($stats_result);

// Get filter options
$categories_query = "SELECT id, name FROM main_evaluation_categories WHERE status = 'active' ORDER BY name";
$categories_result = mysqli_query($conn, $categories_query);

$semesters_query = "SELECT id, name, academic_year FROM semesters WHERE status = 'active' ORDER BY start_date DESC";
$semesters_result = mysqli_query($conn, $semesters_query);

// Include the shared header - this ensures the sidebar is uniform across all pages
include 'includes/header.php';
?>

<!-- Ensure sidebar is properly loaded and uniform with evaluations.php -->
<div class="mb-6 sm:mb-8">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-seait-dark mb-2">All Evaluations</h1>
            <p class="text-sm sm:text-base text-gray-600">
                View and manage all evaluations across the system
            </p>
        </div>
        <a href="evaluations.php" class="bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600 transition">
            <i class="fas fa-arrow-left mr-2"></i>Back to Evaluations
        </a>
    </div>
</div>

<!-- Message Display -->
<?php if ($message): ?>
<div class="mb-6 p-4 rounded-lg <?php echo $message_type === 'success' ? 'bg-green-50 border border-green-200 text-green-800' : 'bg-red-50 border border-red-200 text-red-800'; ?>">
    <?php echo htmlspecialchars($message); ?>
</div>
<?php endif; ?>

<!-- Statistics Cards -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6 mb-6 sm:mb-8">
    <div class="bg-white overflow-hidden shadow rounded-lg">
        <div class="p-4 sm:p-5">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-blue-500 rounded-md flex items-center justify-center">
                        <i class="fas fa-clipboard-check text-white"></i>
                    </div>
                </div>
                <div class="ml-3 sm:ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-xs sm:text-sm font-medium text-gray-500 truncate">Total Evaluations</dt>
                        <dd class="text-base sm:text-lg font-medium text-gray-900"><?php echo number_format($stats['total_evaluations'] ?? 0); ?></dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white overflow-hidden shadow rounded-lg">
        <div class="p-4 sm:p-5">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-green-500 rounded-md flex items-center justify-center">
                        <i class="fas fa-check text-white"></i>
                    </div>
                </div>
                <div class="ml-3 sm:ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-xs sm:text-sm font-medium text-gray-500 truncate">Completed</dt>
                        <dd class="text-base sm:text-lg font-medium text-gray-900"><?php echo number_format($stats['completed_evaluations'] ?? 0); ?></dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white overflow-hidden shadow rounded-lg">
        <div class="p-4 sm:p-5">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-yellow-500 rounded-md flex items-center justify-center">
                        <i class="fas fa-edit text-white"></i>
                    </div>
                </div>
                <div class="ml-3 sm:ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-xs sm:text-sm font-medium text-gray-500 truncate">Draft</dt>
                        <dd class="text-base sm:text-lg font-medium text-gray-900"><?php echo number_format($stats['draft_evaluations'] ?? 0); ?></dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white overflow-hidden shadow rounded-lg">
        <div class="p-4 sm:p-5">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-purple-500 rounded-md flex items-center justify-center">
                        <i class="fas fa-users text-white"></i>
                    </div>
                </div>
                <div class="ml-3 sm:ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-xs sm:text-sm font-medium text-gray-500 truncate">Student Evaluations</dt>
                        <dd class="text-base sm:text-lg font-medium text-gray-900"><?php echo number_format($stats['student_evaluations'] ?? 0); ?></dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Search and Filter Form -->
<div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
    <div class="px-4 sm:px-6 py-4 border-b border-gray-200">
        <h2 class="text-lg font-medium text-gray-900">Search & Filter</h2>
    </div>
    <div class="p-4 sm:p-6">
        <form method="GET" action="" class="space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <!-- Search -->
                <div class="sm:col-span-2 lg:col-span-1">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Search</label>
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                           placeholder="Search by name, email, or category..."
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                </div>

                <!-- Status Filter -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                    <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                        <option value="">All Statuses</option>
                        <option value="draft" <?php echo $status_filter === 'draft' ? 'selected' : ''; ?>>Draft</option>
                        <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                </div>

                <!-- Type Filter -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Evaluation Type</label>
                    <select name="type" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                        <option value="">All Types</option>
                        <option value="student_to_teacher" <?php echo $type_filter === 'student_to_teacher' ? 'selected' : ''; ?>>Student to Teacher</option>
                        <option value="peer_to_peer" <?php echo $type_filter === 'peer_to_peer' ? 'selected' : ''; ?>>Peer to Peer</option>
                        <option value="head_to_teacher" <?php echo $type_filter === 'head_to_teacher' ? 'selected' : ''; ?>>Head to Teacher</option>
                    </select>
                </div>

                <!-- Category Filter -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Category</label>
                    <select name="category" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                        <option value="">All Categories</option>
                        <?php while ($category = mysqli_fetch_assoc($categories_result)): ?>
                            <option value="<?php echo $category['id']; ?>" <?php echo $category_filter == $category['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($category['name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <!-- Semester Filter -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Semester</label>
                    <select name="semester" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                        <option value="">All Semesters</option>
                        <?php while ($semester = mysqli_fetch_assoc($semesters_result)): ?>
                            <option value="<?php echo $semester['id']; ?>" <?php echo $semester_filter == $semester['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($semester['name'] . ' (' . $semester['academic_year'] . ')'); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </div>

            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                <div class="text-sm text-gray-600">
                    Showing <?php echo number_format($total_records); ?> evaluation(s)
                </div>
                <div class="flex flex-col sm:flex-row space-y-2 sm:space-y-0 sm:space-x-2">
                    <button type="submit" class="bg-seait-orange text-white px-4 py-2 rounded-md hover:bg-orange-600 transition">
                        <i class="fas fa-search mr-2"></i>Search
                    </button>
                    <?php if ($search || $status_filter || $type_filter || $category_filter || $semester_filter): ?>
                    <a href="all-evaluations.php" class="bg-gray-500 text-white px-4 py-2 rounded-md hover:bg-gray-600 transition text-center">
                        <i class="fas fa-times mr-2"></i>Clear
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Evaluations Table -->
<div class="bg-white rounded-lg shadow-md overflow-hidden">
    <div class="px-4 sm:px-6 py-4 border-b border-gray-200">
        <h2 class="text-lg font-medium text-gray-900">All Evaluations (<?php echo number_format($total_records); ?>)</h2>
    </div>

    <?php if (mysqli_num_rows($evaluations_result) == 0): ?>
        <div class="p-6 text-center">
            <i class="fas fa-clipboard-list text-gray-300 text-4xl mb-4"></i>
            <p class="text-gray-500">No evaluations found matching your criteria.</p>
            <?php if ($search || $status_filter || $type_filter || $category_filter || $semester_filter): ?>
                <a href="all-evaluations.php" class="mt-4 inline-block bg-seait-orange text-white px-4 py-2 rounded-lg hover:bg-orange-600 transition">
                    Clear Filters
                </a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <!-- Mobile Card View -->
        <div class="block md:hidden">
            <div class="p-4 space-y-4">
                <?php
                // Reset the result pointer
                mysqli_data_seek($evaluations_result, 0);
                while ($evaluation = mysqli_fetch_assoc($evaluations_result)):
                ?>
                    <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                        <div class="flex items-center justify-between mb-3">
                            <div class="flex items-center">
                                <div class="w-8 h-8 bg-seait-orange rounded-full flex items-center justify-center mr-3">
                                    <i class="fas
                                        <?php
                                        switch($evaluation['evaluator_role']) {
                                            case 'student':
                                                echo 'fa-user-graduate';
                                                break;
                                            case 'teacher':
                                                echo 'fa-chalkboard-teacher';
                                                break;
                                            case 'head':
                                                echo 'fa-user-tie';
                                                break;
                                            default:
                                                echo 'fa-user';
                                        }
                                        ?> text-white text-xs"></i>
                                </div>
                                <div>
                                    <h3 class="font-medium text-gray-900">#<?php echo $evaluation['id']; ?></h3>
                                    <p class="text-sm text-gray-600"><?php echo htmlspecialchars($evaluation['category_name']); ?></p>
                                </div>
                            </div>
                            <span class="px-2 py-1 text-xs rounded-full <?php
                                echo $evaluation['status'] === 'completed' ? 'bg-green-100 text-green-800' :
                                    ($evaluation['status'] === 'draft' ? 'bg-yellow-100 text-yellow-800' :
                                    ($evaluation['status'] === 'cancelled' ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-800'));
                            ?>">
                                <?php echo ucfirst($evaluation['status']); ?>
                            </span>
                        </div>

                        <div class="space-y-2 text-sm">
                            <div class="flex justify-between">
                                <span class="text-gray-600">Evaluator:</span>
                                <span class="font-medium"><?php echo htmlspecialchars($evaluation['evaluator_first_name'] . ' ' . $evaluation['evaluator_last_name']); ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Evaluatee:</span>
                                <span class="font-medium"><?php echo htmlspecialchars($evaluation['evaluatee_first_name'] . ' ' . $evaluation['evaluatee_last_name']); ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Type:</span>
                                <span class="px-2 py-1 text-xs rounded-full
                                    <?php
                                    switch($evaluation['evaluation_type']) {
                                        case 'student_to_teacher':
                                            echo 'bg-orange-100 text-orange-800';
                                            break;
                                        case 'peer_to_peer':
                                            echo 'bg-purple-100 text-purple-800';
                                            break;
                                        case 'head_to_teacher':
                                            echo 'bg-indigo-100 text-indigo-800';
                                            break;
                                    }
                                    ?>">
                                    <?php echo ucwords(str_replace('_', ' ', $evaluation['evaluation_type'])); ?>
                                </span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Responses:</span>
                                <span><?php echo $evaluation['response_count']; ?> response(s)</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Date:</span>
                                <span><?php echo date('M d, Y', strtotime($evaluation['evaluation_date'])); ?></span>
                            </div>
                        </div>

                        <div class="flex justify-end space-x-2 mt-4 pt-3 border-t border-gray-200">
                            <a href="view-evaluation.php?id=<?php echo $evaluation['evaluatee_id']; ?>"
                               class="text-blue-600 hover:text-blue-900 text-sm">
                                <i class="fas fa-eye mr-1"></i>View
                            </a>
                            <?php if ($evaluation['status'] === 'draft'): ?>
                            <a href="conduct-evaluation.php?session_id=<?php echo $evaluation['id']; ?>"
                               class="text-green-600 hover:text-green-900 text-sm">
                                <i class="fas fa-edit mr-1"></i>Edit
                            </a>
                            <?php endif; ?>
                            <button onclick="deleteEvaluation(<?php echo $evaluation['id']; ?>, '<?php echo htmlspecialchars($evaluation['evaluator_first_name'] . ' ' . $evaluation['evaluator_last_name']); ?>')"
                                    class="text-red-600 hover:text-red-900 text-sm">
                                <i class="fas fa-trash mr-1"></i>Delete
                            </button>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>

        <!-- Desktop/Tablet Table View -->
        <div class="hidden md:block table-responsive">
            <table class="all-evaluations-table min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-2 sm:px-3 lg:px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                        <th class="px-2 sm:px-3 lg:px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                        <th class="px-2 sm:px-3 lg:px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Evaluator</th>
                        <th class="px-2 sm:px-3 lg:px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Evaluatee</th>
                        <th class="px-2 sm:px-3 lg:px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                        <th class="px-2 sm:px-3 lg:px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-2 sm:px-3 lg:px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Responses</th>
                        <th class="px-2 sm:px-3 lg:px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                        <th class="px-2 sm:px-3 lg:px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php
                    // Reset the result pointer for desktop view
                    mysqli_data_seek($evaluations_result, 0);
                    while ($evaluation = mysqli_fetch_assoc($evaluations_result)):
                    ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-2 sm:px-3 lg:px-4 py-4 text-sm font-medium text-gray-900 table-cell-content">
                                #<?php echo $evaluation['id']; ?>
                            </td>
                            <td class="px-2 sm:px-3 lg:px-4 py-4 text-sm text-gray-900 table-cell-content">
                                <span class="hidden sm:inline"><?php echo htmlspecialchars($evaluation['category_name']); ?></span>
                                <span class="sm:hidden responsive-text"><?php echo htmlspecialchars($evaluation['category_name']); ?></span>
                            </td>
                            <td class="px-2 sm:px-3 lg:px-4 py-4 table-cell-content">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 h-6 w-6 sm:h-8 sm:w-8">
                                        <div class="h-6 w-6 sm:h-8 sm:w-8 rounded-full bg-seait-orange flex items-center justify-center">
                                            <i class="fas
                                                <?php
                                                switch($evaluation['evaluator_role']) {
                                                    case 'student':
                                                        echo 'fa-user-graduate';
                                                        break;
                                                    case 'teacher':
                                                        echo 'fa-chalkboard-teacher';
                                                        break;
                                                    case 'head':
                                                        echo 'fa-user-tie';
                                                        break;
                                                    default:
                                                        echo 'fa-user';
                                                }
                                                ?> text-white text-xs"></i>
                                        </div>
                                    </div>
                                    <div class="ml-2 sm:ml-3">
                                        <div class="text-xs sm:text-sm font-medium text-gray-900">
                                            <span class="hidden sm:inline"><?php echo htmlspecialchars($evaluation['evaluator_first_name'] . ' ' . $evaluation['evaluator_last_name']); ?></span>
                                            <span class="sm:hidden responsive-text"><?php echo htmlspecialchars($evaluation['evaluator_first_name'] . ' ' . $evaluation['evaluator_last_name']); ?></span>
                                        </div>
                                        <div class="text-xs sm:text-sm text-gray-500 hidden lg:block">
                                            <?php echo htmlspecialchars($evaluation['evaluator_email']); ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-2 sm:px-3 lg:px-4 py-4 table-cell-content">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 h-6 w-6 sm:h-8 sm:w-8">
                                        <div class="h-6 w-6 sm:h-8 sm:w-8 rounded-full bg-green-500 flex items-center justify-center">
                                            <i class="fas fa-chalkboard-teacher text-white text-xs"></i>
                                        </div>
                                    </div>
                                    <div class="ml-2 sm:ml-3">
                                        <div class="text-xs sm:text-sm font-medium text-gray-900">
                                            <span class="hidden sm:inline"><?php echo htmlspecialchars($evaluation['evaluatee_first_name'] . ' ' . $evaluation['evaluatee_last_name']); ?></span>
                                            <span class="sm:hidden responsive-text"><?php echo htmlspecialchars($evaluation['evaluatee_first_name'] . ' ' . $evaluation['evaluatee_last_name']); ?></span>
                                        </div>
                                        <div class="text-xs sm:text-sm text-gray-500 hidden lg:block">
                                            <?php echo htmlspecialchars($evaluation['evaluatee_email']); ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-2 sm:px-3 lg:px-4 py-4 table-cell-content">
                                <span class="px-1 sm:px-2 py-1 text-xs rounded-full
                                    <?php
                                    switch($evaluation['evaluation_type']) {
                                        case 'student_to_teacher':
                                            echo 'bg-orange-100 text-orange-800';
                                            break;
                                        case 'peer_to_peer':
                                            echo 'bg-purple-100 text-purple-800';
                                            break;
                                        case 'head_to_teacher':
                                            echo 'bg-indigo-100 text-indigo-800';
                                            break;
                                    }
                                    ?>">
                                    <i class="fas
                                        <?php
                                        switch($evaluation['evaluation_type']) {
                                            case 'student_to_teacher':
                                                echo 'fa-user-graduate';
                                                break;
                                            case 'peer_to_peer':
                                                echo 'fa-users';
                                                break;
                                            case 'head_to_teacher':
                                                echo 'fa-user-tie';
                                                break;
                                        }
                                        ?> mr-1"></i>
                                    <span class="hidden sm:inline"><?php echo ucwords(str_replace('_', ' ', $evaluation['evaluation_type'])); ?></span>
                                    <span class="sm:hidden"><?php echo ucwords(str_replace('_', ' ', substr($evaluation['evaluation_type'], 0, 8))); ?></span>
                                </span>
                            </td>
                            <td class="px-2 sm:px-3 lg:px-4 py-4 table-cell-content">
                                <span class="px-1 sm:px-2 py-1 text-xs rounded-full <?php
                                    echo $evaluation['status'] === 'completed' ? 'bg-green-100 text-green-800' :
                                        ($evaluation['status'] === 'draft' ? 'bg-yellow-100 text-yellow-800' :
                                        ($evaluation['status'] === 'cancelled' ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-800'));
                                ?>">
                                    <?php echo ucfirst($evaluation['status']); ?>
                                </span>
                            </td>
                            <td class="px-2 sm:px-3 lg:px-4 py-4 text-xs sm:text-sm text-gray-900 table-cell-content">
                                <?php echo $evaluation['response_count']; ?> response(s)
                            </td>
                            <td class="px-2 sm:px-3 lg:px-4 py-4 text-xs sm:text-sm text-gray-500 table-cell-content">
                                <?php echo date('M d, Y', strtotime($evaluation['evaluation_date'])); ?>
                                <?php if ($evaluation['semester_name']): ?>
                                    <br><span class="text-xs text-gray-400 hidden lg:inline"><?php echo htmlspecialchars($evaluation['semester_name']); ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="px-2 sm:px-3 lg:px-4 py-4 text-sm font-medium table-cell-content">
                                <div class="flex space-x-1 sm:space-x-2">
                                    <a href="view-evaluation.php?id=<?php echo $evaluation['evaluatee_id']; ?>"
                                       class="text-blue-600 hover:text-blue-900" title="View Evaluation">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <?php if ($evaluation['status'] === 'draft'): ?>
                                    <a href="conduct-evaluation.php?session_id=<?php echo $evaluation['id']; ?>"
                                       class="text-green-600 hover:text-green-900" title="Continue Evaluation">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <?php endif; ?>
                                    <button onclick="deleteEvaluation(<?php echo $evaluation['id']; ?>, '<?php echo htmlspecialchars($evaluation['evaluator_first_name'] . ' ' . $evaluation['evaluator_last_name']); ?>')"
                                            class="text-red-600 hover:text-red-900" title="Delete Evaluation">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="px-4 sm:px-6 py-4 border-t border-gray-200">
            <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
                <div class="text-sm text-gray-700 text-center sm:text-left">
                    Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $per_page, $total_records); ?> of <?php echo number_format($total_records); ?> results
                </div>
                <div class="flex flex-wrap justify-center gap-2">
                    <?php if ($page > 1): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>"
                           class="px-3 py-2 text-sm border border-gray-300 rounded-md hover:bg-gray-50">
                            Previous
                        </a>
                    <?php endif; ?>

                    <?php
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);

                    for ($i = $start_page; $i <= $end_page; $i++):
                    ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"
                           class="px-3 py-2 text-sm border border-gray-300 rounded-md <?php echo $i == $page ? 'bg-seait-orange text-white border-seait-orange' : 'hover:bg-gray-50'; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>

                    <?php if ($page < $total_pages): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>"
                           class="px-3 py-2 text-sm border border-gray-300 rounded-md hover:bg-gray-50">
                            Next
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-lg shadow-2xl max-w-md w-full transform transition-all duration-300 scale-95 opacity-0" id="modalContent">
        <!-- Modal Header -->
        <div class="px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-red-50 to-red-100">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-10 h-10 bg-red-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-exclamation-triangle text-red-600"></i>
                    </div>
                </div>
                <div class="ml-4">
                    <h3 class="text-lg font-semibold text-gray-900">Confirm Deletion</h3>
                    <p class="text-sm text-gray-600">This action cannot be undone</p>
                </div>
            </div>
        </div>

        <!-- Modal Body -->
        <div class="px-6 py-6">
            <div class="mb-4">
                <p class="text-gray-700 mb-3">Are you sure you want to delete this evaluation?</p>
                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                    <div class="flex items-center">
                        <div class="w-8 h-8 bg-red-100 rounded-full flex items-center justify-center mr-3">
                            <i class="fas fa-trash text-red-600 text-sm"></i>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-900" id="deleteEvaluationText"></p>
                            <p class="text-xs text-gray-500">This will permanently remove the evaluation and all associated responses.</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Warning Message -->
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3 mb-4">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <i class="fas fa-exclamation-circle text-yellow-400"></i>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-yellow-800">
                            <strong>Warning:</strong> This action will permanently delete the evaluation and cannot be recovered.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal Footer -->
        <div class="px-6 py-4 border-t border-gray-200 bg-gray-50 rounded-b-lg">
            <form id="deleteForm" method="POST" action="delete-evaluation.php" class="flex justify-end space-x-3">
                <input type="hidden" name="action" value="delete_evaluation">
                <input type="hidden" id="delete_evaluation_id" name="evaluation_id">

                <button type="button" onclick="closeDeleteModal()"
                        class="px-4 py-2 text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 hover:border-gray-400 transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:border-transparent">
                    <i class="fas fa-times mr-2"></i>Cancel
                </button>
                <button type="submit"
                        class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition-all duration-200 transform hover:scale-105">
                    <i class="fas fa-trash mr-2"></i>Delete Evaluation
                </button>
            </form>
        </div>
    </div>
</div>

<script>
function deleteEvaluation(id, evaluatorName) {
    // Set the evaluation ID
    document.getElementById('delete_evaluation_id').value = id;

    // Set the evaluation text with better formatting
    document.getElementById('deleteEvaluationText').textContent = `Evaluator: ${evaluatorName}`;

    // Show the modal with animation
    const modal = document.getElementById('deleteModal');
    const modalContent = document.getElementById('modalContent');

    modal.classList.remove('hidden');

    // Trigger animation after a brief delay
    setTimeout(() => {
        modalContent.classList.remove('scale-95', 'opacity-0');
        modalContent.classList.add('scale-100', 'opacity-100');
    }, 10);

    // Prevent body scroll
    document.body.style.overflow = 'hidden';
}

function closeDeleteModal() {
    const modal = document.getElementById('deleteModal');
    const modalContent = document.getElementById('modalContent');

    // Start closing animation
    modalContent.classList.remove('scale-100', 'opacity-100');
    modalContent.classList.add('scale-95', 'opacity-0');

    // Hide modal after animation completes
    setTimeout(() => {
        modal.classList.add('hidden');
        // Restore body scroll
        document.body.style.overflow = '';
    }, 300);
}

// Close modal when clicking outside
document.getElementById('deleteModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeDeleteModal();
    }
});

// Close modal with Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeDeleteModal();
    }
});

// Prevent form submission if modal is not visible
document.getElementById('deleteForm').addEventListener('submit', function(e) {
    const modal = document.getElementById('deleteModal');
    if (modal.classList.contains('hidden')) {
        e.preventDefault();
        return false;
    }
});

// Add loading state to delete button
document.getElementById('deleteForm').addEventListener('submit', function(e) {
    const submitBtn = this.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;

    // Show loading state
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Deleting...';
    submitBtn.disabled = true;
    submitBtn.classList.add('opacity-75', 'cursor-not-allowed');

    // Reset after a timeout (in case of error)
    setTimeout(() => {
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
        submitBtn.classList.remove('opacity-75', 'cursor-not-allowed');
    }, 10000);
});
</script>

<?php
// Include the shared footer
include 'includes/footer.php';
?>