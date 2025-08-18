<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and has teacher role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header('Location: ../login.php');
    exit();
}

// Set page title
$page_title = 'All Evaluations';

$message = '';
$message_type = '';

// Get search and filter parameters
$search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? sanitize_input($_GET['status']) : '';
$type_filter = isset($_GET['type']) ? sanitize_input($_GET['type']) : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Build query
$where_conditions = ["(f.email = ? OR u.email = ?)"];
$params = [$_SESSION['username'], $_SESSION['username']];
$param_types = 'ss';

if ($search) {
    $where_conditions[] = "(mec.name LIKE ? OR COALESCE(f.first_name, u.first_name) LIKE ? OR COALESCE(f.last_name, u.last_name) LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param]);
    $param_types .= 'sss';
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

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

// Get total count for pagination
$count_query = "SELECT COUNT(*) as total FROM evaluation_sessions es
                JOIN main_evaluation_categories mec ON es.main_category_id = mec.id
                LEFT JOIN faculty f ON es.evaluator_id = f.id
                LEFT JOIN users u ON es.evaluator_id = u.id
                $where_clause";
$count_stmt = mysqli_prepare($conn, $count_query);
mysqli_stmt_bind_param($count_stmt, $param_types, ...$params);
mysqli_stmt_execute($count_stmt);
$count_result = mysqli_stmt_get_result($count_stmt);
$total_records = mysqli_fetch_assoc($count_result)['total'];
$total_pages = ceil($total_records / $per_page);

// Get evaluations
$evaluations_query = "SELECT es.*, mec.name as category_name, mec.evaluation_type,
                      COALESCE(f.first_name, u.first_name) as evaluatee_first_name,
                      COALESCE(f.last_name, u.last_name) as evaluatee_last_name,
                      COALESCE(f.email, u.email) as evaluatee_email
                      FROM evaluation_sessions es
                      JOIN main_evaluation_categories mec ON es.main_category_id = mec.id
                      LEFT JOIN faculty f ON es.evaluatee_id = f.id
                      LEFT JOIN users u ON es.evaluatee_id = u.id
                      LEFT JOIN faculty evaluator_f ON es.evaluator_id = evaluator_f.id
                      LEFT JOIN users evaluator_u ON es.evaluator_id = evaluator_u.id
                      WHERE (evaluator_f.email = ? OR evaluator_u.email = ?)
                      ORDER BY es.created_at DESC
                      LIMIT ? OFFSET ?";
$params = [$_SESSION['username'], $_SESSION['username'], $per_page, $offset];
$param_types = 'ssii';

$evaluations_stmt = mysqli_prepare($conn, $evaluations_query);
mysqli_stmt_bind_param($evaluations_stmt, $param_types, ...$params);
mysqli_stmt_execute($evaluations_stmt);
$evaluations_result = mysqli_stmt_get_result($evaluations_stmt);

// Get evaluation statistics
$stats_query = "SELECT
                COUNT(*) as total_evaluations,
                COALESCE(SUM(CASE WHEN es.status = 'completed' THEN 1 ELSE 0 END), 0) as completed_evaluations,
                COALESCE(SUM(CASE WHEN es.status = 'draft' THEN 1 ELSE 0 END), 0) as draft_evaluations,
                COALESCE(SUM(CASE WHEN mec.evaluation_type = 'peer_to_peer' THEN 1 ELSE 0 END), 0) as peer_evaluations
                FROM evaluation_sessions es
                JOIN main_evaluation_categories mec ON es.main_category_id = mec.id
                LEFT JOIN faculty f ON es.evaluator_id = f.id
                LEFT JOIN users u ON es.evaluator_id = u.id
                WHERE (f.email = ? OR u.email = ?)";
$stats_stmt = mysqli_prepare($conn, $stats_query);
mysqli_stmt_bind_param($stats_stmt, "ss", $_SESSION['username'], $_SESSION['username']);
mysqli_stmt_execute($stats_stmt);
$stats_result = mysqli_stmt_get_result($stats_stmt);
$stats = mysqli_fetch_assoc($stats_result);

// Include the shared header
$sidebar_context = 'main';
include 'includes/unified-header.php';
?>

<div class="mb-6 sm:mb-8">
    <h1 class="text-2xl sm:text-3xl font-bold text-seait-dark mb-2">All Evaluations</h1>
    <p class="text-sm sm:text-base text-gray-600">View and manage all your evaluations</p>
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
                    <div class="w-8 h-8 bg-purple-500 rounded-md flex items-center justify-center">
                        <i class="fas fa-users text-white"></i>
                    </div>
                </div>
                <div class="ml-3 sm:ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-xs sm:text-sm font-medium text-gray-500 truncate">Peer Evaluations</dt>
                        <dd class="text-base sm:text-lg font-medium text-gray-900"><?php echo number_format($stats['peer_evaluations'] ?? 0); ?></dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Search and Filter -->
<div class="mb-6 bg-white p-4 rounded-lg shadow-md">
    <form method="GET" class="grid grid-cols-1 sm:grid-cols-4 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                   placeholder="Search by category or evaluatee"
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
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Type</label>
            <select name="type" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                <option value="">All Types</option>
                <option value="student_to_teacher" <?php echo $type_filter === 'student_to_teacher' ? 'selected' : ''; ?>>Student to Teacher</option>
                <option value="peer_to_peer" <?php echo $type_filter === 'peer_to_peer' ? 'selected' : ''; ?>>Peer to Peer</option>
                <option value="head_to_teacher" <?php echo $type_filter === 'head_to_teacher' ? 'selected' : ''; ?>>Head to Teacher</option>
            </select>
        </div>
        <div class="flex items-end">
            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition">
                <i class="fas fa-search mr-2"></i>Search
            </button>
            <?php if ($search || $status_filter || $type_filter): ?>
            <a href="evaluations.php" class="ml-2 bg-gray-500 text-white px-4 py-2 rounded-md hover:bg-gray-600 transition">
                <i class="fas fa-times mr-2"></i>Clear
            </a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Evaluations Table -->
<div class="bg-white rounded-lg shadow-md overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-200">
        <h2 class="text-lg font-medium text-gray-900">Your Evaluations (<?php echo $total_records; ?>)</h2>
    </div>

    <?php if (mysqli_num_rows($evaluations_result) == 0): ?>
        <div class="p-6 text-center">
            <i class="fas fa-clipboard-list text-gray-300 text-4xl mb-4"></i>
            <p class="text-gray-500">No evaluations found. Start by conducting an evaluation.</p>
            <a href="peer-evaluations.php" class="mt-4 inline-block bg-seait-orange text-white px-4 py-2 rounded-lg hover:bg-orange-600 transition">
                Start Evaluation
            </a>
        </div>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Evaluatee</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php while ($evaluation = mysqli_fetch_assoc($evaluations_result)): ?>
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="h-10 w-10 rounded-full bg-seait-orange flex items-center justify-center mr-3">
                                    <span class="text-white font-medium"><?php echo strtoupper(substr($evaluation['evaluatee_first_name'], 0, 1) . substr($evaluation['evaluatee_last_name'], 0, 1)); ?></span>
                                </div>
                                <div>
                                    <div class="text-sm font-medium text-gray-900">
                                        <?php echo htmlspecialchars($evaluation['evaluatee_first_name'] . ' ' . $evaluation['evaluatee_last_name']); ?>
                                    </div>
                                    <div class="text-sm text-gray-500"><?php echo htmlspecialchars($evaluation['evaluatee_email']); ?></div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-900"><?php echo htmlspecialchars($evaluation['category_name']); ?></div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
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
                                <?php echo ucwords(str_replace('_', ' ', $evaluation['evaluation_type'])); ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 py-1 text-xs rounded-full <?php
                                echo $evaluation['status'] === 'completed' ? 'bg-green-100 text-green-800' :
                                    ($evaluation['status'] === 'draft' ? 'bg-yellow-100 text-yellow-800' : 'bg-blue-100 text-blue-800');
                            ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $evaluation['status'])); ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <?php echo date('M d, Y', strtotime($evaluation['evaluation_date'])); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            <div class="flex space-x-2">
                                <a href="../IntelliEVal/view-evaluation.php?id=<?php echo $evaluation['evaluatee_id']; ?>"
                                   class="text-blue-600 hover:text-blue-900" title="View Evaluation">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <?php if ($evaluation['status'] === 'draft'): ?>
                                <a href="../IntelliEVal/conduct-evaluation.php?session_id=<?php echo $evaluation['id']; ?>"
                                   class="text-green-600 hover:text-green-900" title="Continue Evaluation">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="px-6 py-4 border-t border-gray-200">
            <div class="flex items-center justify-between">
                <div class="text-sm text-gray-700">
                    Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $per_page, $total_records); ?> of <?php echo $total_records; ?> results
                </div>
                <div class="flex space-x-2">
                    <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&type=<?php echo urlencode($type_filter); ?>"
                       class="px-3 py-2 text-sm bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                        Previous
                    </a>
                    <?php endif; ?>

                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                    <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&type=<?php echo urlencode($type_filter); ?>"
                       class="px-3 py-2 text-sm <?php echo $i === $page ? 'bg-seait-orange text-white' : 'bg-white border border-gray-300 hover:bg-gray-50'; ?> rounded-md">
                        <?php echo $i; ?>
                    </a>
                    <?php endfor; ?>

                    <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&type=<?php echo urlencode($type_filter); ?>"
                       class="px-3 py-2 text-sm bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                        Next
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