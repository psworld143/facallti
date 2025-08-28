<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/id_encryption.php';

// Check if user is logged in and has human_resource role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'human_resource') {
    header('Location: ../index.php');
    exit();
}

// Set page title
$page_title = 'Manage Regularization';

// Get filters
$status_filter = $_GET['status'] ?? '';
$category_filter = $_GET['category'] ?? '';
$search = $_GET['search'] ?? '';
$review_due_filter = $_GET['review_due'] ?? '';

// Build query for faculty regularization
$where_conditions = ["fr.is_active = 1"];
$params = [];
$param_types = "";

if (!empty($status_filter)) {
    $where_conditions[] = "rs.name = ?";
    $params[] = $status_filter;
    $param_types .= "s";
}

if (!empty($category_filter)) {
    $where_conditions[] = "sc.name = ?";
    $params[] = $category_filter;
    $param_types .= "s";
}

if (!empty($search)) {
    $where_conditions[] = "(f.first_name LIKE ? OR f.last_name LIKE ? OR f.email LIKE ? OR fd.employee_id LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $param_types .= "ssss";
}

if (!empty($review_due_filter)) {
    switch ($review_due_filter) {
        case 'due_this_week':
            $where_conditions[] = "fr.regularization_review_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)";
            break;
        case 'overdue':
            $where_conditions[] = "fr.regularization_review_date < CURDATE()";
            break;
        case 'due_this_month':
            $where_conditions[] = "fr.regularization_review_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)";
            break;
    }
}

$where_clause = implode(" AND ", $where_conditions);

// Get total count for pagination
$count_query = "SELECT COUNT(*) as total FROM faculty_regularization fr 
                LEFT JOIN faculty f ON fr.faculty_id = f.id 
                LEFT JOIN faculty_details fd ON f.id = fd.faculty_id 
                LEFT JOIN staff_categories sc ON fr.staff_category_id = sc.id 
                LEFT JOIN regularization_status rs ON fr.current_status_id = rs.id 
                WHERE $where_clause";

$count_stmt = mysqli_prepare($conn, $count_query);
if (!empty($params)) {
    mysqli_stmt_bind_param($count_stmt, $param_types, ...$params);
}
mysqli_stmt_execute($count_stmt);
$count_result = mysqli_stmt_get_result($count_stmt);
$total_records = mysqli_fetch_assoc($count_result)['total'];

// Pagination
$records_per_page = 10;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $records_per_page;
$total_pages = ceil($total_records / $records_per_page);

// Main query with pagination
$query = "SELECT 
    fr.*,
    f.first_name,
    f.last_name,
    f.email,
    f.position,
    f.department,
    fd.employee_id,
    sc.name as category_name,
    sc.regularization_period_months,
    rs.name as status_name,
    rs.color as status_color,
    DATEDIFF(fr.regularization_review_date, CURDATE()) as days_until_review,
    DATEDIFF(CURDATE(), fr.regularization_review_date) as days_overdue
FROM faculty_regularization fr
LEFT JOIN faculty f ON fr.faculty_id = f.id
LEFT JOIN faculty_details fd ON f.id = fd.faculty_id
LEFT JOIN staff_categories sc ON fr.staff_category_id = sc.id
LEFT JOIN regularization_status rs ON fr.current_status_id = rs.id
WHERE $where_clause
ORDER BY 
    CASE 
        WHEN fr.regularization_review_date < CURDATE() THEN 1
        WHEN fr.regularization_review_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 2
        ELSE 3
    END,
    fr.regularization_review_date ASC,
    f.last_name ASC, f.first_name ASC
LIMIT ? OFFSET ?";

$params[] = $records_per_page;
$params[] = $offset;
$param_types .= "ii";

$stmt = mysqli_prepare($conn, $query);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $param_types, ...$params);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

// Get filter options
$statuses_query = "SELECT name FROM regularization_status WHERE is_active = 1 ORDER BY name";
$statuses_result = mysqli_query($conn, $statuses_query);
$statuses = [];
while ($row = mysqli_fetch_assoc($statuses_result)) {
    $statuses[] = $row['name'];
}

$categories_query = "SELECT name FROM staff_categories WHERE is_active = 1 ORDER BY name";
$categories_result = mysqli_query($conn, $categories_query);
$categories = [];
while ($row = mysqli_fetch_assoc($categories_result)) {
    $categories[] = $row['name'];
}

// Get statistics
$stats_query = "SELECT 
    COALESCE(COUNT(*), 0) as total_regularization,
    COALESCE(SUM(CASE WHEN rs.name = 'Probationary' THEN 1 ELSE 0 END), 0) as probationary,
    COALESCE(SUM(CASE WHEN rs.name = 'Under Review' THEN 1 ELSE 0 END), 0) as under_review,
    COALESCE(SUM(CASE WHEN rs.name = 'Regular' THEN 1 ELSE 0 END), 0) as regular,
    COALESCE(SUM(CASE WHEN rs.name = 'Extended Probation' THEN 1 ELSE 0 END), 0) as extended_probation,
    COALESCE(SUM(CASE WHEN fr.regularization_review_date < CURDATE() THEN 1 ELSE 0 END), 0) as overdue_reviews,
    COALESCE(SUM(CASE WHEN fr.regularization_review_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 1 ELSE 0 END), 0) as due_this_week
FROM faculty_regularization fr
LEFT JOIN regularization_status rs ON fr.current_status_id = rs.id
WHERE fr.is_active = 1";

$stats_result = mysqli_query($conn, $stats_query);
$stats = mysqli_fetch_assoc($stats_result);

// Ensure stats are always set to 0 if no records exist
if (!$stats) {
    $stats = [
        'total_regularization' => 0,
        'probationary' => 0,
        'under_review' => 0,
        'regular' => 0,
        'extended_probation' => 0,
        'overdue_reviews' => 0,
        'due_this_week' => 0
    ];
}

// Include the header
include 'includes/header.php';
?>

<!-- Page Header -->
<div class="mb-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Regularization Management</h1>
            <p class="text-gray-600">Manage faculty regularization process for Teaching and Non-Teaching staff</p>
        </div>
        <div class="flex space-x-3">
            <a href="add-regularization.php" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transform transition-all hover:scale-105 font-medium">
                <i class="fas fa-plus mr-2"></i>Add Regularization
            </a>
            <a href="regularization-reports.php" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transform transition-all hover:scale-105 font-medium">
                <i class="fas fa-chart-bar mr-2"></i>Reports
            </a>
        </div>
    </div>
</div>

<!-- Statistics Cards -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-blue-500">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                <i class="fas fa-users text-xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-600">Total Regularization</p>
                <p class="text-2xl font-bold text-gray-900"><?php echo $stats['total_regularization']; ?></p>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-yellow-500">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-yellow-100 text-yellow-600">
                <i class="fas fa-clock text-xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-600">Probationary</p>
                <p class="text-2xl font-bold text-gray-900"><?php echo $stats['probationary']; ?></p>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-red-500">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-red-100 text-red-600">
                <i class="fas fa-exclamation-triangle text-xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-600">Overdue Reviews</p>
                <p class="text-2xl font-bold text-gray-900"><?php echo $stats['overdue_reviews']; ?></p>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-green-500">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-green-100 text-green-600">
                <i class="fas fa-check-circle text-xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-600">Regular</p>
                <p class="text-2xl font-bold text-gray-900"><?php echo $stats['regular']; ?></p>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="bg-white rounded-xl shadow-lg p-6 mb-6">
    <form method="GET" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Search</label>
            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20"
                   placeholder="Name, email, or employee ID">
        </div>
        
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
            <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20">
                <option value="">All Statuses</option>
                <?php foreach ($statuses as $status): ?>
                    <option value="<?php echo htmlspecialchars($status); ?>" <?php echo $status_filter === $status ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($status); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Category</label>
            <select name="category" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20">
                <option value="">All Categories</option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?php echo htmlspecialchars($category); ?>" <?php echo $category_filter === $category ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($category); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Review Due</label>
            <select name="review_due" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20">
                <option value="">All</option>
                <option value="due_this_week" <?php echo $review_due_filter === 'due_this_week' ? 'selected' : ''; ?>>Due This Week</option>
                <option value="overdue" <?php echo $review_due_filter === 'overdue' ? 'selected' : ''; ?>>Overdue</option>
                <option value="due_this_month" <?php echo $review_due_filter === 'due_this_month' ? 'selected' : ''; ?>>Due This Month</option>
            </select>
        </div>
        
        <div class="flex items-end">
            <button type="submit" class="w-full bg-seait-orange text-white px-4 py-2 rounded-lg hover:bg-orange-600 transition-colors">
                <i class="fas fa-search mr-2"></i>Filter
            </button>
        </div>
    </form>
</div>

<!-- Regularization Table -->
<div class="bg-white rounded-xl shadow-lg overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-200">
        <h3 class="text-lg font-semibold text-gray-900">Regularization Records</h3>
        <p class="text-sm text-gray-600">Showing <?php echo $total_records; ?> records</p>
    </div>

    <!-- Desktop Table View -->
    <div class="hidden lg:block overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Employee</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Hire Date</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Review Due</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Progress</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php while ($row = mysqli_fetch_assoc($result)): ?>
                    <?php
                    $days_until_review = $row['days_until_review'];
                    $days_overdue = $row['days_overdue'];
                    $is_overdue = $days_overdue > 0;
                    $is_due_soon = $days_until_review >= 0 && $days_until_review <= 7;
                    
                    // Calculate progress percentage
                    $hire_date = new DateTime($row['date_of_hire']);
                    $review_date = new DateTime($row['regularization_review_date']);
                    $today = new DateTime();
                    $total_days = $hire_date->diff($review_date)->days;
                    $elapsed_days = $hire_date->diff($today)->days;
                    $progress_percentage = min(100, max(0, ($elapsed_days / $total_days) * 100));
                    ?>
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="w-10 h-10 bg-gradient-to-r from-blue-500 to-blue-600 rounded-full flex items-center justify-center text-white font-bold text-sm mr-3">
                                    <?php echo strtoupper(substr($row['first_name'], 0, 1) . substr($row['last_name'], 0, 1)); ?>
                                </div>
                                <div>
                                    <div class="text-sm font-medium text-gray-900">
                                        <?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?>
                                    </div>
                                    <div class="text-sm text-gray-500"><?php echo htmlspecialchars($row['position']); ?></div>
                                    <div class="text-xs text-gray-400"><?php echo htmlspecialchars($row['employee_id'] ?? 'No ID'); ?></div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $row['category_name'] === 'Teaching' ? 'bg-purple-100 text-purple-800' : 'bg-green-100 text-green-800'; ?>">
                                <?php echo htmlspecialchars($row['category_name']); ?>
                            </span>
                            <div class="text-xs text-gray-500 mt-1">
                                <?php echo $row['regularization_period_months']; ?> months
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium" style="background-color: <?php echo $row['status_color']; ?>20; color: <?php echo $row['status_color']; ?>;">
                                <?php echo htmlspecialchars($row['status_name']); ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            <?php echo date('M j, Y', strtotime($row['date_of_hire'])); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <?php if ($is_overdue): ?>
                                <div class="text-sm font-medium text-red-600">
                                    <i class="fas fa-exclamation-triangle mr-1"></i>
                                    <?php echo $days_overdue; ?> days overdue
                                </div>
                            <?php elseif ($is_due_soon): ?>
                                <div class="text-sm font-medium text-yellow-600">
                                    <i class="fas fa-clock mr-1"></i>
                                    Due in <?php echo $days_until_review; ?> days
                                </div>
                            <?php else: ?>
                                <div class="text-sm text-gray-900">
                                    <?php echo date('M j, Y', strtotime($row['regularization_review_date'])); ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div class="bg-blue-600 h-2 rounded-full transition-all duration-300" style="width: <?php echo $progress_percentage; ?>%"></div>
                            </div>
                            <div class="text-xs text-gray-500 mt-1">
                                <?php echo round($progress_percentage); ?>% complete
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            <div class="flex space-x-2">
                                <a href="view-regularization.php?id=<?php echo safe_encrypt_id($row['faculty_id']); ?>" 
                                   class="text-blue-600 hover:text-blue-900 transition-colors">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="conduct-review.php?id=<?php echo safe_encrypt_id($row['faculty_id']); ?>" 
                                   class="text-green-600 hover:text-green-900 transition-colors">
                                    <i class="fas fa-clipboard-check"></i>
                                </a>
                                <a href="edit-regularization.php?id=<?php echo safe_encrypt_id($row['faculty_id']); ?>" 
                                   class="text-yellow-600 hover:text-yellow-900 transition-colors">
                                    <i class="fas fa-edit"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <!-- Mobile Card View -->
    <div class="lg:hidden">
        <?php 
        mysqli_data_seek($result, 0); // Reset result pointer
        while ($row = mysqli_fetch_assoc($result)): 
            $days_until_review = $row['days_until_review'];
            $days_overdue = $row['days_overdue'];
            $is_overdue = $days_overdue > 0;
            $is_due_soon = $days_until_review >= 0 && $days_until_review <= 7;
            
            $hire_date = new DateTime($row['date_of_hire']);
            $review_date = new DateTime($row['regularization_review_date']);
            $today = new DateTime();
            $total_days = $hire_date->diff($review_date)->days;
            $elapsed_days = $hire_date->diff($today)->days;
            $progress_percentage = min(100, max(0, ($elapsed_days / $total_days) * 100));
        ?>
            <div class="p-4 border-b border-gray-200">
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-gradient-to-r from-blue-500 to-blue-600 rounded-full flex items-center justify-center text-white font-bold text-lg mr-3">
                            <?php echo strtoupper(substr($row['first_name'], 0, 1) . substr($row['last_name'], 0, 1)); ?>
                        </div>
                        <div>
                            <div class="font-medium text-gray-900">
                                <?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?>
                            </div>
                            <div class="text-sm text-gray-500"><?php echo htmlspecialchars($row['position']); ?></div>
                        </div>
                    </div>
                    <div class="text-right">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $row['category_name'] === 'Teaching' ? 'bg-purple-100 text-purple-800' : 'bg-green-100 text-green-800'; ?>">
                            <?php echo htmlspecialchars($row['category_name']); ?>
                        </span>
                    </div>
                </div>
                
                <div class="grid grid-cols-2 gap-4 mb-3">
                    <div>
                        <div class="text-xs text-gray-500">Status</div>
                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium" style="background-color: <?php echo $row['status_color']; ?>20; color: <?php echo $row['status_color']; ?>;">
                            <?php echo htmlspecialchars($row['status_name']); ?>
                        </span>
                    </div>
                    <div>
                        <div class="text-xs text-gray-500">Review Due</div>
                        <div class="text-sm font-medium">
                            <?php if ($is_overdue): ?>
                                <span class="text-red-600">
                                    <i class="fas fa-exclamation-triangle mr-1"></i>
                                    <?php echo $days_overdue; ?> days overdue
                                </span>
                            <?php elseif ($is_due_soon): ?>
                                <span class="text-yellow-600">
                                    <i class="fas fa-clock mr-1"></i>
                                    Due in <?php echo $days_until_review; ?> days
                                </span>
                            <?php else: ?>
                                <?php echo date('M j, Y', strtotime($row['regularization_review_date'])); ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="mb-3">
                    <div class="flex justify-between text-xs text-gray-500 mb-1">
                        <span>Progress</span>
                        <span><?php echo round($progress_percentage); ?>%</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2">
                        <div class="bg-blue-600 h-2 rounded-full transition-all duration-300" style="width: <?php echo $progress_percentage; ?>%"></div>
                    </div>
                </div>
                
                <div class="flex justify-end space-x-2">
                    <a href="view-regularization.php?id=<?php echo safe_encrypt_id($row['faculty_id']); ?>" 
                       class="text-blue-600 hover:text-blue-900 transition-colors">
                        <i class="fas fa-eye"></i>
                    </a>
                    <a href="conduct-review.php?id=<?php echo safe_encrypt_id($row['faculty_id']); ?>" 
                       class="text-green-600 hover:text-green-900 transition-colors">
                        <i class="fas fa-clipboard-check"></i>
                    </a>
                    <a href="edit-regularization.php?id=<?php echo safe_encrypt_id($row['faculty_id']); ?>" 
                       class="text-yellow-600 hover:text-yellow-900 transition-colors">
                        <i class="fas fa-edit"></i>
                    </a>
                </div>
            </div>
        <?php endwhile; ?>
    </div>
</div>

<!-- Pagination -->
<?php if ($total_pages > 1): ?>
    <div class="mt-6 flex items-center justify-between">
        <div class="text-sm text-gray-700">
            Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $records_per_page, $total_records); ?> of <?php echo $total_records; ?> results
        </div>
        
        <div class="flex space-x-2">
            <?php if ($current_page > 1): ?>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $current_page - 1])); ?>" 
                   class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                    Previous
                </a>
            <?php endif; ?>
            
            <?php for ($i = max(1, $current_page - 2); $i <= min($total_pages, $current_page + 2); $i++): ?>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" 
                   class="px-3 py-2 text-sm font-medium <?php echo $i === $current_page ? 'text-white bg-seait-orange border border-seait-orange' : 'text-gray-500 bg-white border border-gray-300 hover:bg-gray-50'; ?> rounded-md">
                    <?php echo $i; ?>
                </a>
            <?php endfor; ?>
            
            <?php if ($current_page < $total_pages): ?>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $current_page + 1])); ?>" 
                   class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                    Next
                </a>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
