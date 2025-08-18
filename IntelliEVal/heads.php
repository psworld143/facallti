<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and has guidance_officer role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'guidance_officer') {
    header('Location: ../login.php');
    exit();
}

// Set page title
$page_title = 'Heads Management';

$message = '';
$message_type = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_head':
                $first_name = sanitize_input($_POST['first_name']);
                $last_name = sanitize_input($_POST['last_name']);
                $email = sanitize_input($_POST['email']);
                $phone = sanitize_input($_POST['phone']);
                $department = sanitize_input($_POST['department']);
                $position = sanitize_input($_POST['position']);
                $username = sanitize_input($_POST['username']);
                $password = $_POST['password'];

                // Check if username already exists
                $check_query = "SELECT id FROM users WHERE username = ?";
                $check_stmt = mysqli_prepare($conn, $check_query);
                mysqli_stmt_bind_param($check_stmt, "s", $username);
                mysqli_stmt_execute($check_stmt);
                $check_result = mysqli_stmt_get_result($check_stmt);

                if (mysqli_num_rows($check_result) > 0) {
                    $message = "Username already exists. Please choose a different username.";
                    $message_type = "error";
                } else {
                    // Hash password
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                    // Insert into users table
                    $insert_user_query = "INSERT INTO users (username, password, first_name, last_name, email, role, status) VALUES (?, ?, ?, ?, ?, 'head', 'active')";
                    $insert_user_stmt = mysqli_prepare($conn, $insert_user_query);
                    mysqli_stmt_bind_param($insert_user_stmt, "sssss", $username, $hashed_password, $first_name, $last_name, $email);

                    if (mysqli_stmt_execute($insert_user_stmt)) {
                        $head_id = mysqli_insert_id($conn);

                        // Insert into heads table (if it exists) or create additional head info
                        $insert_head_query = "INSERT INTO heads (user_id, department, position, phone, status) VALUES (?, ?, ?, ?, 'active')";
                        $insert_head_stmt = mysqli_prepare($conn, $insert_head_query);
                        mysqli_stmt_bind_param($insert_head_stmt, "isss", $head_id, $department, $position, $phone);

                        if (mysqli_stmt_execute($insert_head_stmt)) {
                            $message = "Head added successfully!";
                            $message_type = "success";
                        } else {
                            // If heads table doesn't exist, just log the user creation
                            $message = "Head account created successfully!";
                            $message_type = "success";
                        }
                    } else {
                        $message = "Error adding head: " . mysqli_error($conn);
                        $message_type = "error";
                    }
                }
                break;

            case 'update_head':
                $head_id = (int)$_POST['head_id'];
                $first_name = sanitize_input($_POST['first_name']);
                $last_name = sanitize_input($_POST['last_name']);
                $email = sanitize_input($_POST['email']);
                $phone = sanitize_input($_POST['phone']);
                $department = sanitize_input($_POST['department']);
                $position = sanitize_input($_POST['position']);
                $status = sanitize_input($_POST['status']);

                $update_query = "UPDATE users SET first_name = ?, last_name = ?, email = ? WHERE id = ? AND role = 'head'";
                $update_stmt = mysqli_prepare($conn, $update_query);
                mysqli_stmt_bind_param($update_stmt, "sssi", $first_name, $last_name, $email, $head_id);

                if (mysqli_stmt_execute($update_stmt)) {
                    // Update head-specific info if heads table exists
                    $update_head_query = "UPDATE heads SET department = ?, position = ?, phone = ?, status = ? WHERE user_id = ?";
                    $update_head_stmt = mysqli_prepare($conn, $update_head_query);
                    mysqli_stmt_bind_param($update_head_stmt, "ssssi", $department, $position, $phone, $status, $head_id);
                    mysqli_stmt_execute($update_head_stmt);

                    $message = "Head updated successfully!";
                    $message_type = "success";
                } else {
                    $message = "Error updating head: " . mysqli_error($conn);
                    $message_type = "error";
                }
                break;
        }
    }
}

// Get search and filter parameters
$search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
$department_filter = isset($_GET['department']) ? sanitize_input($_GET['department']) : '';
$status_filter = isset($_GET['status']) ? sanitize_input($_GET['status']) : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Build query
$where_conditions = ["u.role = 'head'"];
$params = [];
$param_types = '';

if ($search) {
    $where_conditions[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR u.username LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
    $param_types .= 'ssss';
}

if ($department_filter) {
    $where_conditions[] = "h.department = ?";
    $params[] = $department_filter;
    $param_types .= 's';
}

if ($status_filter) {
    $where_conditions[] = "u.status = ?";
    $params[] = $status_filter;
    $param_types .= 's';
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

// Get total count for pagination
$count_query = "SELECT COUNT(*) as total FROM users u LEFT JOIN heads h ON u.id = h.user_id $where_clause";
if (!empty($params)) {
    $count_stmt = mysqli_prepare($conn, $count_query);
    mysqli_stmt_bind_param($count_stmt, $param_types, ...$params);
    mysqli_stmt_execute($count_stmt);
    $count_result = mysqli_stmt_get_result($count_stmt);
} else {
    $count_result = mysqli_query($conn, $count_query);
}
$total_records = mysqli_fetch_assoc($count_result)['total'];
$total_pages = ceil($total_records / $per_page);

// Get heads
$heads_query = "SELECT u.*, h.department, h.position, h.phone as head_phone, h.status as head_status
                FROM users u
                LEFT JOIN heads h ON u.id = h.user_id
                $where_clause
                ORDER BY u.created_at DESC
                LIMIT ? OFFSET ?";
$params[] = $per_page;
$params[] = $offset;
$param_types .= 'ii';

$heads_stmt = mysqli_prepare($conn, $heads_query);
mysqli_stmt_bind_param($heads_stmt, $param_types, ...$params);
mysqli_stmt_execute($heads_stmt);
$heads_result = mysqli_stmt_get_result($heads_stmt);

$heads = [];
while ($row = mysqli_fetch_assoc($heads_result)) {
    $heads[] = $row;
}

// Get unique departments for filter
$departments_query = "SELECT DISTINCT department FROM heads WHERE department IS NOT NULL AND department != '' ORDER BY department";
$departments_result = mysqli_query($conn, $departments_query);
$departments = [];
while ($row = mysqli_fetch_assoc($departments_result)) {
    $departments[] = $row['department'];
}

// Get colleges for department dropdown
$colleges_query = "SELECT id, name, short_name FROM colleges WHERE is_active = 1 ORDER BY sort_order, name";
$colleges_result = mysqli_query($conn, $colleges_query);
$colleges = [];
while ($row = mysqli_fetch_assoc($colleges_result)) {
    $colleges[] = $row;
}

// Include the shared header
include 'includes/header.php';
?>

<div class="mb-6 sm:mb-8">
    <h1 class="text-2xl sm:text-3xl font-bold text-seait-dark mb-2">Heads Management</h1>
    <p class="text-sm sm:text-base text-gray-600">Manage department heads who will evaluate teachers</p>
</div>

<?php if ($message): ?>
    <div class="mb-6 p-4 rounded-lg <?php echo $message_type === 'success' ? 'bg-green-100 text-green-700 border border-green-200' : 'bg-red-100 text-red-700 border border-red-200'; ?>">
        <?php echo htmlspecialchars($message); ?>
    </div>
<?php endif; ?>

<!-- Information Alert -->
<div class="mb-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
    <div class="flex">
        <div class="flex-shrink-0">
            <i class="fas fa-info-circle text-blue-400"></i>
        </div>
        <div class="ml-3">
            <h3 class="text-sm font-medium text-blue-800">Department Selection</h3>
            <div class="mt-2 text-sm text-blue-700">
                <p>Department options are now populated from the colleges database. Each option shows the full college name with its abbreviation in parentheses.</p>
            </div>
        </div>
    </div>
</div>

<!-- Search and Filter -->
<div class="bg-white rounded-lg shadow-md p-4 sm:p-6 mb-6">
    <form method="GET" class="grid grid-cols-1 sm:grid-cols-4 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Search</label>
            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                   placeholder="Search by name, email, or username..."
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-seait-orange text-sm">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Department</label>
            <select name="department" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-seait-orange text-sm">
                <option value="">All Departments</option>
                <?php foreach ($colleges as $college): ?>
                    <option value="<?php echo htmlspecialchars($college['name']); ?>" <?php echo $department_filter === $college['name'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($college['name']); ?> (<?php echo htmlspecialchars($college['short_name']); ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
            <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-seait-orange text-sm">
                <option value="">All Status</option>
                <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
            </select>
        </div>
        <div class="flex items-end space-x-2">
            <button type="submit" class="flex-1 bg-seait-orange text-white px-4 py-2 rounded-lg hover:bg-orange-600 transition text-sm">
                <i class="fas fa-search mr-2"></i>Search
            </button>
            <a href="heads.php" class="px-4 py-2 text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 transition text-sm">
                <i class="fas fa-times"></i>
            </a>
        </div>
    </form>
</div>

<!-- Add Head Button -->
<div class="mb-6">
    <button onclick="openAddHeadModal()" class="w-full sm:w-auto bg-seait-orange text-white px-4 sm:px-6 py-2 rounded-lg hover:bg-orange-600 transition text-sm sm:text-base">
        <i class="fas fa-plus mr-2"></i>Add New Head
    </button>
</div>

<!-- Heads Table -->
<div class="bg-white rounded-lg shadow-md overflow-hidden">
    <div class="px-4 sm:px-6 py-4 border-b border-gray-200">
        <h2 class="text-base sm:text-lg font-medium text-gray-900">Department Heads (<?php echo number_format($total_records); ?>)</h2>
    </div>

    <!-- Desktop Table -->
    <div class="hidden lg:block overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Head</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contact</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Department</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (empty($heads)): ?>
                <tr>
                    <td colspan="5" class="px-6 py-4 text-center text-gray-500">
                        No heads found. <?php if ($search || $department_filter || $status_filter): ?>Try adjusting your search criteria.<?php else: ?>Add your first head to get started.<?php endif; ?>
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($heads as $head): ?>
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="h-10 w-10 rounded-full bg-purple-600 flex items-center justify-center mr-3">
                                    <span class="text-white font-medium"><?php echo strtoupper(substr($head['first_name'], 0, 1) . substr($head['last_name'], 0, 1)); ?></span>
                                </div>
                                <div>
                                    <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($head['first_name'] . ' ' . $head['last_name']); ?></div>
                                    <div class="text-sm text-gray-500"><?php echo htmlspecialchars($head['username']); ?></div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-900"><?php echo htmlspecialchars($head['email']); ?></div>
                            <div class="text-sm text-gray-500"><?php echo htmlspecialchars($head['head_phone'] ?? 'N/A'); ?></div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-900"><?php echo htmlspecialchars($head['department'] ?? 'N/A'); ?></div>
                            <div class="text-sm text-gray-500"><?php echo htmlspecialchars($head['position'] ?? 'N/A'); ?></div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 py-1 text-xs rounded-full <?php
                                echo $head['status'] === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800';
                            ?>">
                                <?php echo ucfirst($head['status']); ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            <button onclick="openEditHeadModal(<?php echo $head['id']; ?>)" class="text-blue-600 hover:text-blue-900 mr-3">
                                <i class="fas fa-edit"></i>
                            </button>
                            <a href="head-evaluations.php?head_id=<?php echo $head['id']; ?>" class="text-purple-600 hover:text-purple-900 mr-3">
                                <i class="fas fa-chart-line"></i>
                            </a>
                            <a href="head-teachers.php?head_id=<?php echo $head['id']; ?>" class="text-green-600 hover:text-green-900">
                                <i class="fas fa-users"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Mobile Cards -->
    <div class="lg:hidden">
        <?php if (empty($heads)): ?>
            <div class="p-4 text-center text-gray-500">
                No heads found. <?php if ($search || $department_filter || $status_filter): ?>Try adjusting your search criteria.<?php else: ?>Add your first head to get started.<?php endif; ?>
            </div>
        <?php else: ?>
            <div class="p-4 space-y-4">
                <?php foreach ($heads as $head): ?>
                    <div class="bg-gray-50 rounded-lg p-4">
                        <div class="flex items-center justify-between mb-3">
                            <div class="flex items-center">
                                <div class="h-10 w-10 rounded-full bg-purple-600 flex items-center justify-center mr-3">
                                    <span class="text-white font-medium"><?php echo strtoupper(substr($head['first_name'], 0, 1) . substr($head['last_name'], 0, 1)); ?></span>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($head['first_name'] . ' ' . $head['last_name']); ?></p>
                                    <p class="text-sm text-gray-500"><?php echo htmlspecialchars($head['username']); ?></p>
                                </div>
                            </div>
                            <span class="px-2 py-1 text-xs rounded-full <?php
                                echo $head['status'] === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800';
                            ?>">
                                <?php echo ucfirst($head['status']); ?>
                            </span>
                        </div>

                        <div class="grid grid-cols-2 gap-4 text-sm mb-3">
                            <div>
                                <p class="text-gray-500">Email</p>
                                <p class="text-gray-900"><?php echo htmlspecialchars($head['email']); ?></p>
                            </div>
                            <div>
                                <p class="text-gray-500">Phone</p>
                                <p class="text-gray-900"><?php echo htmlspecialchars($head['head_phone'] ?? 'N/A'); ?></p>
                            </div>
                            <div>
                                <p class="text-gray-500">Department</p>
                                <p class="text-gray-900"><?php echo htmlspecialchars($head['department'] ?? 'N/A'); ?></p>
                            </div>
                            <div>
                                <p class="text-gray-500">Position</p>
                                <p class="text-gray-900"><?php echo htmlspecialchars($head['position'] ?? 'N/A'); ?></p>
                            </div>
                        </div>

                        <div class="flex space-x-2">
                            <button onclick="openEditHeadModal(<?php echo $head['id']; ?>)" class="text-blue-600 hover:text-blue-900">
                                <i class="fas fa-edit"></i>
                            </button>
                            <a href="head-evaluations.php?head_id=<?php echo $head['id']; ?>" class="text-purple-600 hover:text-purple-900">
                                <i class="fas fa-chart-line"></i>
                            </a>
                            <a href="head-teachers.php?head_id=<?php echo $head['id']; ?>" class="text-green-600 hover:text-green-900">
                                <i class="fas fa-users"></i>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Pagination -->
<?php if ($total_pages > 1): ?>
<div class="mt-6 flex items-center justify-between">
    <div class="text-sm text-gray-700">
        Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $per_page, $total_records); ?> of <?php echo number_format($total_records); ?> results
    </div>
    <div class="flex space-x-2">
        <?php if ($page > 1): ?>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>"
               class="px-3 py-2 text-sm border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                Previous
            </a>
        <?php endif; ?>

        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"
               class="px-3 py-2 text-sm border border-gray-300 rounded-lg <?php echo $i === $page ? 'bg-seait-orange text-white' : 'hover:bg-gray-50'; ?> transition">
                <?php echo $i; ?>
            </a>
        <?php endfor; ?>

        <?php if ($page < $total_pages): ?>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>"
               class="px-3 py-2 text-sm border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                Next
            </a>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- Add Head Modal -->
<div id="addHeadModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-lg mx-auto max-h-screen overflow-y-auto">
        <div class="flex items-center justify-between p-4 sm:p-6 border-b border-gray-200">
            <h3 class="text-lg sm:text-xl font-semibold text-seait-dark">Add New Head</h3>
            <button onclick="closeAddHeadModal()" class="text-gray-400 hover:text-gray-600 transition">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>

        <form method="POST" class="p-4 sm:p-6">
            <input type="hidden" name="action" value="add_head">

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">First Name</label>
                    <input type="text" name="first_name" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-seait-orange text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Last Name</label>
                    <input type="text" name="last_name" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-seait-orange text-sm">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                    <input type="email" name="email" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-seait-orange text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Phone</label>
                    <input type="tel" name="phone"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-seait-orange text-sm">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Department</label>
                    <select name="department" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-seait-orange text-sm">
                        <option value="">Select Department</option>
                        <?php foreach ($colleges as $college): ?>
                            <option value="<?php echo htmlspecialchars($college['name']); ?>">
                                <?php echo htmlspecialchars($college['name']); ?> (<?php echo htmlspecialchars($college['short_name']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Position</label>
                    <input type="text" name="position" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-seait-orange text-sm">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Username</label>
                    <input type="text" name="username" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-seait-orange text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Password</label>
                    <input type="password" name="password" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-seait-orange text-sm">
                </div>
            </div>

            <div class="flex flex-col sm:flex-row justify-end space-y-2 sm:space-y-0 sm:space-x-3">
                <button type="button" onclick="closeAddHeadModal()"
                        class="w-full sm:w-auto px-4 py-2 text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 transition text-sm">
                    Cancel
                </button>
                <button type="submit"
                        class="w-full sm:w-auto px-4 py-2 bg-seait-orange text-white rounded-lg hover:bg-orange-600 transition text-sm">
                    Add Head
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Head Modal -->
<div id="editHeadModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-lg mx-auto max-h-screen overflow-y-auto">
        <div class="flex items-center justify-between p-4 sm:p-6 border-b border-gray-200">
            <h3 class="text-lg sm:text-xl font-semibold text-seait-dark">Edit Head</h3>
            <button onclick="closeEditHeadModal()" class="text-gray-400 hover:text-gray-600 transition">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>

        <form method="POST" class="p-4 sm:p-6">
            <input type="hidden" name="action" value="update_head">
            <input type="hidden" name="head_id" id="edit_head_id">

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">First Name</label>
                    <input type="text" name="first_name" id="edit_first_name" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-seait-orange text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Last Name</label>
                    <input type="text" name="last_name" id="edit_last_name" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-seait-orange text-sm">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                    <input type="email" name="email" id="edit_email" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-seait-orange text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Phone</label>
                    <input type="tel" name="phone" id="edit_phone"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-seait-orange text-sm">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Department</label>
                    <select name="department" id="edit_department" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-seait-orange text-sm">
                        <option value="">Select Department</option>
                        <?php foreach ($colleges as $college): ?>
                            <option value="<?php echo htmlspecialchars($college['name']); ?>">
                                <?php echo htmlspecialchars($college['name']); ?> (<?php echo htmlspecialchars($college['short_name']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Position</label>
                    <input type="text" name="position" id="edit_position" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-seait-orange text-sm">
                </div>
            </div>

            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                <select name="status" id="edit_status" required
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-seait-orange text-sm">
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>

            <div class="flex flex-col sm:flex-row justify-end space-y-2 sm:space-y-0 sm:space-x-3">
                <button type="button" onclick="closeEditHeadModal()"
                        class="w-full sm:w-auto px-4 py-2 text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 transition text-sm">
                    Cancel
                </button>
                <button type="submit"
                        class="w-full sm:w-auto px-4 py-2 bg-seait-orange text-white rounded-lg hover:bg-orange-600 transition text-sm">
                    Update Head
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openAddHeadModal() {
    document.getElementById('addHeadModal').classList.remove('hidden');
}

function closeAddHeadModal() {
    document.getElementById('addHeadModal').classList.add('hidden');
}

function openEditHeadModal(headId) {
    // Fetch head data using AJAX
    fetch(`get_head_data.php?id=${headId}`, {
        method: 'GET',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Populate the edit form
                document.getElementById('edit_head_id').value = data.head.id;
                document.getElementById('edit_first_name').value = data.head.first_name;
                document.getElementById('edit_last_name').value = data.head.last_name;
                document.getElementById('edit_email').value = data.head.email;
                document.getElementById('edit_phone').value = data.head.phone || '';
                document.getElementById('edit_department').value = data.head.department || '';
                document.getElementById('edit_position').value = data.head.position || '';
                document.getElementById('edit_status').value = data.head.status;

                // Show the modal
                document.getElementById('editHeadModal').classList.remove('hidden');
            } else {
                alert('Error loading head data: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error loading head data. Please try again.');
        });
}

function closeEditHeadModal() {
    document.getElementById('editHeadModal').classList.add('hidden');
}

// Close modals when clicking outside
document.addEventListener('click', function(event) {
    const addModal = document.getElementById('addHeadModal');
    const editModal = document.getElementById('editHeadModal');

    if (event.target === addModal) {
        addModal.classList.add('hidden');
    }

    if (event.target === editModal) {
        editModal.classList.add('hidden');
    }
});
</script>

<?php
// Include the shared footer
include 'includes/footer.php';
?>