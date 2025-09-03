<?php
session_start();
require_once '../config/database.php';
require_once '../includes/unified-error-handler.php';
require_once '../includes/functions.php';

check_admin();

$message = '';
$message_type = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $username = sanitize_input($_POST['username']);
                $email = sanitize_input($_POST['email']);
                $password = $_POST['password'];
                $first_name = sanitize_input($_POST['first_name']);
                $last_name = sanitize_input($_POST['last_name']);
                $role = sanitize_input($_POST['role']);

                // Check if username or email already exists
                $check_query = "SELECT id FROM users WHERE username = ? OR email = ?";
                $check_stmt = mysqli_prepare($conn, $check_query);
                mysqli_stmt_bind_param($check_stmt, "ss", $username, $email);
                mysqli_stmt_execute($check_stmt);
                $check_result = mysqli_stmt_get_result($check_stmt);

                if (mysqli_num_rows($check_result) > 0) {
                    $message = "Username or email already exists!";
                    $message_type = "error";
                } else {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $insert_query = "INSERT INTO users (username, email, password, first_name, last_name, role) VALUES (?, ?, ?, ?, ?, ?)";
                    $insert_stmt = mysqli_prepare($conn, $insert_query);
                    mysqli_stmt_bind_param($insert_stmt, "ssssss", $username, $email, $hashed_password, $first_name, $last_name, $role);

                    if (mysqli_stmt_execute($insert_stmt)) {
                        $message = "User added successfully!";
                        $message_type = "success";
                    } else {
                        $message = "Error adding user: " . mysqli_error($conn);
                        $message_type = "error";
                    }
                }
                break;

            case 'edit':
                $user_id = (int)$_POST['user_id'];
                $email = sanitize_input($_POST['email']);
                $first_name = sanitize_input($_POST['first_name']);
                $last_name = sanitize_input($_POST['last_name']);
                $role = sanitize_input($_POST['role']);

                $update_query = "UPDATE users SET email = ?, first_name = ?, last_name = ?, role = ? WHERE id = ?";
                $update_stmt = mysqli_prepare($conn, $update_query);
                mysqli_stmt_bind_param($update_stmt, "ssssi", $email, $first_name, $last_name, $role, $user_id);

                if (mysqli_stmt_execute($update_stmt)) {
                    $message = "User updated successfully!";
                    $message_type = "success";
                } else {
                    $message = "Error updating user: " . mysqli_error($conn);
                    $message_type = "error";
                }
                break;

            case 'delete':
                $user_id = (int)$_POST['user_id'];

                // Prevent deleting own account
                if ($user_id == $_SESSION['user_id']) {
                    $message = "You cannot delete your own account!";
                    $message_type = "error";
                } else {
                    $delete_query = "DELETE FROM users WHERE id = ?";
                    $delete_stmt = mysqli_prepare($conn, $delete_query);
                    mysqli_stmt_bind_param($delete_stmt, "i", $user_id);

                    if (mysqli_stmt_execute($delete_stmt)) {
                        $message = "User deleted successfully!";
                        $message_type = "success";
                    } else {
                        $message = "Error deleting user: " . mysqli_error($conn);
                        $message_type = "error";
                    }
                }
                break;

            case 'change_password':
                $user_id = (int)$_POST['user_id'];
                $new_password = $_POST['new_password'];

                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $password_query = "UPDATE users SET password = ? WHERE id = ?";
                $password_stmt = mysqli_prepare($conn, $password_query);
                mysqli_stmt_bind_param($password_stmt, "si", $hashed_password, $user_id);

                if (mysqli_stmt_execute($password_stmt)) {
                    $message = "Password changed successfully!";
                    $message_type = "success";
                } else {
                    $message = "Error changing password: " . mysqli_error($conn);
                    $message_type = "error";
                }
                break;
        }
    }
}

// Get users with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
$role_filter = isset($_GET['role']) ? sanitize_input($_GET['role']) : '';

// Build query with filters
$where_conditions = [];
$params = [];
$param_types = "";

if (!empty($search)) {
    $where_conditions[] = "(username LIKE ? OR email LIKE ? OR first_name LIKE ? OR last_name LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
    $param_types .= "ssss";
}

if (!empty($role_filter)) {
    $where_conditions[] = "role = ?";
    $params[] = $role_filter;
    $param_types .= "s";
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Get total count
$count_query = "SELECT COUNT(*) as total FROM users $where_clause";
if (!empty($params)) {
    $count_stmt = mysqli_prepare($conn, $count_query);
    mysqli_stmt_bind_param($count_stmt, $param_types, ...$params);
    mysqli_stmt_execute($count_stmt);
    $count_result = mysqli_stmt_get_result($count_stmt);
} else {
    $count_result = mysqli_query($conn, $count_query);
}
$total_users = mysqli_fetch_assoc($count_result)['total'];
$total_pages = ceil($total_users / $limit);

// Get users
$users_query = "SELECT id, username, email, first_name, last_name, role, created_at FROM users $where_clause ORDER BY created_at DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$param_types .= "ii";

$users_stmt = mysqli_prepare($conn, $users_query);
mysqli_stmt_bind_param($users_stmt, $param_types, ...$params);
mysqli_stmt_execute($users_stmt);
$users_result = mysqli_stmt_get_result($users_stmt);

// Fetch all users into an array to avoid result set pointer issues
$users_array = [];
while ($row = mysqli_fetch_assoc($users_result)) {
    $users_array[] = $row;
}
?>

<?php
// Set page title for the header
$page_title = 'User Management';

// Include the admin header
include 'includes/admin-header.php';
?>

<style>
    @keyframes bounce-in {
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
        animation: bounce-in 0.6s ease-out;
    }
</style>
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-seait-dark mb-2">User Management</h1>
                <p class="text-gray-600">Manage system users and their roles</p>
            </div>

            <?php if ($message): ?>
                <?php echo display_message($message, $message_type); ?>
            <?php endif; ?>

            <!-- Search and Filters -->
            <div class="bg-white p-6 rounded-lg shadow-md mb-6">
                <form method="GET" class="flex flex-col md:flex-row gap-4">
                    <div class="flex-1">
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                               placeholder="Search users..."
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-seait-orange">
                    </div>
                    <div class="md:w-48">
                        <select name="role" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-seait-orange">
                            <option value="">All Roles</option>
                            <option value="admin" <?php echo $role_filter === 'admin' ? 'selected' : ''; ?>>Admin</option>
                            <option value="social_media_manager" <?php echo $role_filter === 'social_media_manager' ? 'selected' : ''; ?>>Social Media Manager</option>
                            <option value="content_creator" <?php echo $role_filter === 'content_creator' ? 'selected' : ''; ?>>Content Creator</option>
                            <option value="guidance_officer" <?php echo $role_filter === 'guidance_officer' ? 'selected' : ''; ?>>Guidance Officer</option>
                        </select>
                    </div>
                    <div class="flex gap-2">
                        <button type="submit" class="bg-seait-orange text-white px-6 py-2 rounded-lg hover:bg-orange-600 transition">
                            <i class="fas fa-search mr-2"></i>Search
                        </button>
                        <a href="users.php" class="bg-gray-500 text-white px-6 py-2 rounded-lg hover:bg-gray-600 transition">
                            <i class="fas fa-times mr-2"></i>Clear
                        </a>
                    </div>
                </form>
            </div>

            <!-- Add User Button -->
            <div class="mb-6">
                <button onclick="openAddUserModal()" class="bg-green-500 text-white px-6 py-2 rounded-lg hover:bg-green-600 transition">
                    <i class="fas fa-plus mr-2"></i>Add New User
                </button>
            </div>

            <!-- Users Table -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($users_array as $user): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-10 w-10">
                                            <div class="h-10 w-10 rounded-full bg-seait-orange flex items-center justify-center">
                                                <span class="text-white font-medium"><?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?></span>
                                            </div>
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900">
                                                <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                                            </div>
                                            <div class="text-sm text-gray-500">
                                                <?php echo htmlspecialchars($user['username']); ?>
                                            </div>
                                            <div class="text-sm text-gray-500">
                                                <?php echo htmlspecialchars($user['email']); ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 py-1 text-xs rounded-full <?php
                                        echo $user['role'] === 'admin' ? 'bg-red-100 text-red-800' :
                                            ($user['role'] === 'social_media_manager' ? 'bg-blue-100 text-blue-800' :
                                            ($user['role'] === 'guidance_officer' ? 'bg-purple-100 text-purple-800' : 'bg-green-100 text-green-800'));
                                    ?>">
                                        <?php echo ucwords(str_replace('_', ' ', $user['role'])); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo date('M d, Y', strtotime($user['created_at'])); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <div class="flex space-x-2">
                                        <button onclick="openEditUserModal(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>', '<?php echo htmlspecialchars($user['email']); ?>', '<?php echo htmlspecialchars($user['first_name']); ?>', '<?php echo htmlspecialchars($user['last_name']); ?>', '<?php echo $user['role']; ?>')"
                                                class="text-blue-600 hover:text-blue-900">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button onclick="openChangePasswordModal(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')"
                                                class="text-yellow-600 hover:text-yellow-900">
                                            <i class="fas fa-key"></i>
                                        </button>
                                        <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                        <button onclick="deleteUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')"
                                                class="text-red-600 hover:text-red-900">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="mt-6 flex justify-center">
                <nav class="flex space-x-2">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($role_filter); ?>"
                           class="px-3 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50">
                            Previous
                        </a>
                    <?php endif; ?>

                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                        <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($role_filter); ?>"
                           class="px-3 py-2 border border-gray-300 rounded-md text-sm font-medium <?php echo $i === $page ? 'bg-seait-orange text-white' : 'text-gray-700 hover:bg-gray-50'; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>

                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($role_filter); ?>"
                           class="px-3 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50">
                            Next
                        </a>
                    <?php endif; ?>
                </nav>
            </div>
            <?php endif; ?>
    <!-- Add User Modal -->
    <div id="addUserModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-md mx-auto">
            <div class="flex items-center justify-between p-6 border-b border-gray-200">
                <h3 class="text-xl font-semibold text-seait-dark">Add New User</h3>
                <button onclick="closeAddUserModal()" class="text-gray-400 hover:text-gray-600 transition">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>

            <form method="POST" class="p-6">
                <input type="hidden" name="action" value="add">

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Username</label>
                    <input type="text" name="username" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-seait-orange">
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                    <input type="email" name="email" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-seait-orange">
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Password</label>
                    <input type="password" name="password" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-seait-orange">
                </div>

                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">First Name</label>
                        <input type="text" name="first_name" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-seait-orange">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Last Name</label>
                        <input type="text" name="last_name" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-seait-orange">
                    </div>
                </div>

                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Role</label>
                    <select name="role" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-seait-orange">
                        <option value="">Select Role</option>
                        <option value="admin">Admin</option>
                        <option value="social_media_manager">Social Media Manager</option>
                        <option value="content_creator">Content Creator</option>
                        <option value="guidance_officer">Guidance Officer</option>
                    </select>
                </div>

                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeAddUserModal()"
                            class="px-4 py-2 text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                        Cancel
                    </button>
                    <button type="submit"
                            class="px-4 py-2 bg-seait-orange text-white rounded-lg hover:bg-orange-600 transition">
                        Add User
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div id="editUserModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-md mx-auto">
            <div class="flex items-center justify-between p-6 border-b border-gray-200">
                <h3 class="text-xl font-semibold text-seait-dark">Edit User</h3>
                <button onclick="closeEditUserModal()" class="text-gray-400 hover:text-gray-600 transition">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>

            <form method="POST" class="p-6">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="user_id" id="edit_user_id">

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Username</label>
                    <input type="text" id="edit_username" disabled
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-100">
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                    <input type="email" name="email" id="edit_email" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-seait-orange">
                </div>

                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">First Name</label>
                        <input type="text" name="first_name" id="edit_first_name" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-seait-orange">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Last Name</label>
                        <input type="text" name="last_name" id="edit_last_name" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-seait-orange">
                    </div>
                </div>

                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Role</label>
                    <select name="role" id="edit_role" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-seait-orange">
                        <option value="admin">Admin</option>
                        <option value="social_media_manager">Social Media Manager</option>
                        <option value="content_creator">Content Creator</option>
                        <option value="guidance_officer">Guidance Officer</option>
                    </select>
                </div>

                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeEditUserModal()"
                            class="px-4 py-2 text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                        Cancel
                    </button>
                    <button type="submit"
                            class="px-4 py-2 bg-seait-orange text-white rounded-lg hover:bg-orange-600 transition">
                        Update User
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Change Password Modal -->
    <div id="changePasswordModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-md mx-auto">
            <div class="flex items-center justify-between p-6 border-b border-gray-200">
                <h3 class="text-xl font-semibold text-seait-dark">Change Password</h3>
                <button onclick="closeChangePasswordModal()" class="text-gray-400 hover:text-gray-600 transition">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>

            <form method="POST" class="p-6">
                <input type="hidden" name="action" value="change_password">
                <input type="hidden" name="user_id" id="password_user_id">

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Username</label>
                    <input type="text" id="password_username" disabled
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-100">
                </div>

                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">New Password</label>
                    <input type="password" name="new_password" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-seait-orange">
                </div>

                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeChangePasswordModal()"
                            class="px-4 py-2 text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                        Cancel
                    </button>
                    <button type="submit"
                            class="px-4 py-2 bg-seait-orange text-white rounded-lg hover:bg-orange-600 transition">
                        Change Password
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete User Confirmation Modal -->
    <div id="deleteUserModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full animate-bounce-in">
                <div class="p-6 text-center">
                    <div class="mb-4">
                        <div class="p-4 rounded-full bg-red-100 text-red-600 inline-block mb-4">
                            <i class="fas fa-exclamation-triangle text-3xl"></i>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-900 mb-2">Delete User</h3>
                        <p class="text-gray-600 mb-4">Are you sure you want to delete this user? This action cannot be undone.</p>
                        <div class="bg-red-50 border border-red-200 rounded-lg p-3 mb-4">
                            <div class="flex items-center text-red-800">
                                <i class="fas fa-exclamation-triangle mr-2"></i>
                                <span class="text-sm font-medium">Warning:</span>
                            </div>
                            <ul class="text-sm text-red-700 mt-2 text-left space-y-1">
                                <li class="flex items-center">
                                    <i class="fas fa-trash mr-2 text-red-500"></i>
                                    User account will be permanently removed
                                </li>
                                <li class="flex items-center">
                                    <i class="fas fa-eye-slash mr-2 text-red-500"></i>
                                    All user data will be deleted
                                </li>
                                <li class="flex items-center">
                                    <i class="fas fa-undo mr-2 text-red-500"></i>
                                    Cannot be recovered
                                </li>
                            </ul>
                        </div>
                    </div>
                    <form method="POST" class="space-y-3">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="user_id" id="deleteUserId">
                        <div class="flex justify-center space-x-3">
                            <button type="button" onclick="closeDeleteUserModal()"
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

    <script>
        // Modal functions
        function openAddUserModal() {
            document.getElementById('addUserModal').classList.remove('hidden');
        }

        function closeAddUserModal() {
            document.getElementById('addUserModal').classList.add('hidden');
        }

        function openEditUserModal(userId, username, email, firstName, lastName, role) {
            document.getElementById('edit_user_id').value = userId;
            document.getElementById('edit_username').value = username;
            document.getElementById('edit_email').value = email;
            document.getElementById('edit_first_name').value = firstName;
            document.getElementById('edit_last_name').value = lastName;
            document.getElementById('edit_role').value = role;
            document.getElementById('editUserModal').classList.remove('hidden');
        }

        function closeEditUserModal() {
            document.getElementById('editUserModal').classList.add('hidden');
        }

        function openChangePasswordModal(userId, username) {
            document.getElementById('password_user_id').value = userId;
            document.getElementById('password_username').value = username;
            document.getElementById('changePasswordModal').classList.remove('hidden');
        }

        function closeChangePasswordModal() {
            document.getElementById('changePasswordModal').classList.add('hidden');
        }

        function deleteUser(userId, username) {
            document.getElementById('deleteUserId').value = userId;
            document.getElementById('deleteUserModal').classList.remove('hidden');
        }

        function closeDeleteUserModal() {
            document.getElementById('deleteUserModal').classList.add('hidden');
        }

        // Close modals when clicking outside
        document.addEventListener('click', function(event) {
            const modals = ['addUserModal', 'editUserModal', 'changePasswordModal', 'deleteUserModal'];
            modals.forEach(modalId => {
                const modal = document.getElementById(modalId);
                if (event.target === modal) {
                    modal.classList.add('hidden');
                }
            });
        });
    </script>
        </div>
    </main>
</div>
</body>
</html>