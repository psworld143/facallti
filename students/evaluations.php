<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and has student role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../login.php');
    exit();
}

// Set page title
$page_title = 'My Evaluations';

$message = '';
$message_type = '';

// Get search and filter parameters
$search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? sanitize_input($_GET['status']) : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Build query for student's evaluations
$where_conditions = ["es.evaluator_id = ? AND mec.evaluation_type = 'student_to_teacher'"];
$params = [$_SESSION['user_id']];
$param_types = 'i';

if ($search) {
    $where_conditions[] = "(mec.name LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param]);
    $param_types .= 'sss';
}

if ($status_filter) {
    $where_conditions[] = "es.status = ?";
    $params[] = $status_filter;
    $param_types .= 's';
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

// Get total count for pagination
$count_query = "SELECT COUNT(*) as total FROM evaluation_sessions es
                JOIN main_evaluation_categories mec ON es.main_category_id = mec.id
                LEFT JOIN faculty f ON es.evaluatee_id = f.id
                LEFT JOIN users u ON es.evaluatee_id = u.id
                $where_clause";
$count_stmt = mysqli_prepare($conn, $count_query);
mysqli_stmt_bind_param($count_stmt, $param_types, ...$params);
mysqli_stmt_execute($count_stmt);
$count_result = mysqli_stmt_get_result($count_stmt);
$total_records = mysqli_fetch_assoc($count_result)['total'];
$total_pages = ceil($total_records / $per_page);

// Get evaluation results
$evaluations_query = "SELECT es.*, mec.name as category_name, mec.evaluation_type,
                      CASE
                          WHEN es.evaluatee_type = 'teacher' THEN f.first_name
                          ELSE u.first_name
                      END as teacher_first_name,
                      CASE
                          WHEN es.evaluatee_type = 'teacher' THEN f.last_name
                          ELSE u.last_name
                      END as teacher_last_name,
                      CASE
                          WHEN es.evaluatee_type = 'teacher' THEN f.email
                          ELSE u.email
                      END as teacher_email
                      FROM evaluation_sessions es
                      JOIN main_evaluation_categories mec ON es.main_category_id = mec.id
                      LEFT JOIN faculty f ON es.evaluatee_id = f.id AND es.evaluatee_type = 'teacher'
                      LEFT JOIN users u ON es.evaluatee_id = u.id AND es.evaluatee_type != 'teacher'
                      $where_clause
                      ORDER BY es.created_at DESC
                      LIMIT ? OFFSET ?";
$params[] = $per_page;
$params[] = $offset;
$param_types .= 'ii';

$evaluations_stmt = mysqli_prepare($conn, $evaluations_query);
mysqli_stmt_bind_param($evaluations_stmt, $param_types, ...$params);
mysqli_stmt_execute($evaluations_stmt);
$evaluations_result = mysqli_stmt_get_result($evaluations_stmt);

// Get evaluation statistics
$stats_query = "SELECT
                COUNT(*) as total_evaluations,
                COALESCE(SUM(CASE WHEN es.status = 'completed' THEN 1 ELSE 0 END), 0) as completed_evaluations,
                COALESCE(SUM(CASE WHEN es.status = 'draft' THEN 1 ELSE 0 END), 0) as draft_evaluations,
                COALESCE(SUM(CASE WHEN es.status = 'in_progress' THEN 1 ELSE 0 END), 0) as in_progress_evaluations
                FROM evaluation_sessions es
                JOIN main_evaluation_categories mec ON es.main_category_id = mec.id
                WHERE es.evaluator_id = ? AND mec.evaluation_type = 'student_to_teacher'";
$stats_stmt = mysqli_prepare($conn, $stats_query);
mysqli_stmt_bind_param($stats_stmt, "i", $_SESSION['user_id']);
mysqli_stmt_execute($stats_stmt);
$stats_result = mysqli_stmt_get_result($stats_stmt);
$stats = mysqli_fetch_assoc($stats_result);

// Include the shared header
include 'includes/header.php';
?>

<div class="mb-6 sm:mb-8">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-seait-dark mb-2">My Evaluations</h1>
            <p class="text-sm sm:text-base text-gray-600">View your teacher evaluation history</p>
        </div>
        <a href="evaluate-teacher.php" class="bg-seait-orange text-white px-4 py-2 rounded-lg hover:bg-orange-600 transition">
            <i class="fas fa-plus-circle mr-2"></i>New Evaluation
        </a>
    </div>
</div>

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
                        <dt class="text-xs sm:text-sm font-medium text-gray-500 truncate">Drafts</dt>
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
                    <div class="w-8 h-8 bg-blue-500 rounded-md flex items-center justify-center">
                        <i class="fas fa-clock text-white"></i>
                    </div>
                </div>
                <div class="ml-3 sm:ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-xs sm:text-sm font-medium text-gray-500 truncate">In Progress</dt>
                        <dd class="text-base sm:text-lg font-medium text-gray-900"><?php echo number_format($stats['in_progress_evaluations'] ?? 0); ?></dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Search and Filter -->
<div class="mb-6 bg-white p-4 rounded-lg shadow-md">
    <form method="GET" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="sm:col-span-1 lg:col-span-2">
            <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                   placeholder="Search by category or teacher"
                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
            <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                <option value="">All Status</option>
                <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                <option value="draft" <?php echo $status_filter === 'draft' ? 'selected' : ''; ?>>Draft</option>
                <option value="in_progress" <?php echo $status_filter === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
            </select>
        </div>
        <div class="flex items-end">
            <button type="submit" class="w-full bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition">
                <i class="fas fa-search mr-2"></i>Search
            </button>
            <?php if ($search || $status_filter): ?>
            <a href="evaluations.php" class="ml-2 bg-gray-500 text-white px-4 py-2 rounded-md hover:bg-gray-600 transition">
                <i class="fas fa-times mr-2"></i>Clear
            </a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Evaluations Table -->
<div class="bg-white rounded-lg shadow-md overflow-hidden">
    <div class="px-4 sm:px-6 py-4 border-b border-gray-200">
        <h2 class="text-lg font-medium text-gray-900">Evaluation History (<?php echo $total_records; ?>)</h2>
    </div>

    <?php if (mysqli_num_rows($evaluations_result) == 0): ?>
        <div class="p-4 sm:p-6 text-center">
            <i class="fas fa-clipboard-list text-gray-300 text-4xl mb-4"></i>
            <p class="text-gray-500">No evaluations found. Start by evaluating a teacher.</p>
            <a href="evaluate-teacher.php" class="mt-4 inline-block bg-seait-orange text-white px-4 py-2 rounded-lg hover:bg-orange-600 transition">
                Start Your First Evaluation
            </a>
        </div>
    <?php else: ?>
        <!-- Responsive table container -->
        <div class="w-full overflow-x-auto">
            <div class="min-w-full inline-block align-middle">
                <div class="overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Teacher</th>
                                <th class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                                <th class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                <th class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php while ($evaluation = mysqli_fetch_assoc($evaluations_result)): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-3 sm:px-6 py-4">
                                    <div class="flex items-center">
                                        <div class="h-8 w-8 sm:h-10 sm:w-10 rounded-full bg-seait-orange flex items-center justify-center mr-2 sm:mr-3 flex-shrink-0">
                                            <span class="text-white font-medium text-xs sm:text-sm"><?php echo strtoupper(substr($evaluation['teacher_first_name'], 0, 1) . substr($evaluation['teacher_last_name'], 0, 1)); ?></span>
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <div class="text-sm font-medium text-gray-900 truncate">
                                                <?php echo htmlspecialchars($evaluation['teacher_first_name'] . ' ' . $evaluation['teacher_last_name']); ?>
                                            </div>
                                            <div class="text-xs sm:text-sm text-gray-500 truncate"><?php echo htmlspecialchars($evaluation['teacher_email']); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-3 sm:px-6 py-4">
                                    <div class="text-sm text-gray-900 truncate"><?php echo htmlspecialchars($evaluation['category_name']); ?></div>
                                </td>
                                <td class="px-3 sm:px-6 py-4">
                                    <span class="px-2 py-1 text-xs rounded-full <?php
                                        echo $evaluation['status'] === 'completed' ? 'bg-green-100 text-green-800' :
                                            ($evaluation['status'] === 'draft' ? 'bg-yellow-100 text-yellow-800' : 'bg-blue-100 text-blue-800');
                                    ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $evaluation['status'])); ?>
                                    </span>
                                </td>
                                <td class="px-3 sm:px-6 py-4 text-sm text-gray-500">
                                    <span class="hidden sm:inline"><?php echo date('M d, Y', strtotime($evaluation['evaluation_date'])); ?></span>
                                    <span class="sm:hidden"><?php echo date('m/d/y', strtotime($evaluation['evaluation_date'])); ?></span>
                                </td>
                                <td class="px-3 sm:px-6 py-4 text-sm font-medium">
                                    <div class="flex space-x-2">
                                        <?php if ($evaluation['status'] === 'completed'): ?>
                                        <a href="view-evaluation.php?id=<?php echo $evaluation['evaluatee_id']; ?>"
                                           class="text-blue-600 hover:text-blue-900 p-1 rounded" title="View Results">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <?php elseif ($evaluation['status'] === 'draft'): ?>
                                        <a href="conduct-evaluation.php?session_id=<?php echo $evaluation['id']; ?>"
                                           class="text-green-600 hover:text-green-900 p-1 rounded" title="Continue Evaluation">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <?php else: ?>
                                        <a href="conduct-evaluation.php?session_id=<?php echo $evaluation['id']; ?>"
                                           class="text-orange-600 hover:text-orange-900 p-1 rounded" title="Continue Evaluation">
                                            <i class="fas fa-clock"></i>
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="px-4 sm:px-6 py-4 border-t border-gray-200">
            <div class="flex flex-col sm:flex-row items-center justify-between space-y-2 sm:space-y-0">
                <div class="text-sm text-gray-700 text-center sm:text-left">
                    Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $per_page, $total_records); ?> of <?php echo $total_records; ?> results
                </div>
                <div class="flex flex-wrap justify-center sm:justify-end space-x-1 sm:space-x-2">
                    <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>"
                       class="px-2 sm:px-3 py-2 text-sm bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                        <span class="hidden sm:inline">Previous</span>
                        <span class="sm:hidden">←</span>
                    </a>
                    <?php endif; ?>

                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                    <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>"
                       class="px-2 sm:px-3 py-2 text-sm <?php echo $i === $page ? 'bg-seait-orange text-white' : 'bg-white border border-gray-300 hover:bg-gray-50'; ?> rounded-md">
                        <?php echo $i; ?>
                    </a>
                    <?php endfor; ?>

                    <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>"
                       class="px-2 sm:px-3 py-2 text-sm bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                        <span class="hidden sm:inline">Next</span>
                        <span class="sm:hidden">→</span>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php
// Include the shared footer
include 'includes/footer.php';
?>