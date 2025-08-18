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
$page_title = 'Profile';

$message = '';
$message_type = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_profile':
                $first_name = sanitize_input($_POST['first_name']);
                $last_name = sanitize_input($_POST['last_name']);
                $email = sanitize_input($_POST['email']);

                if (empty($first_name) || empty($last_name) || empty($email)) {
                    $message = "All fields are required!";
                    $message_type = "error";
                } else {
                    $update_query = "UPDATE users SET first_name = ?, last_name = ?, email = ? WHERE id = ?";
                    $update_stmt = mysqli_prepare($conn, $update_query);
                    mysqli_stmt_bind_param($update_stmt, "sssi", $first_name, $last_name, $email, $_SESSION['user_id']);

                    if (mysqli_stmt_execute($update_stmt)) {
                        // Update session data
                        $_SESSION['first_name'] = $first_name;
                        $_SESSION['last_name'] = $last_name;
                        $_SESSION['email'] = $email;

                        $message = "Profile updated successfully!";
                        $message_type = "success";
                    } else {
                        $message = "Error updating profile: " . mysqli_error($conn);
                        $message_type = "error";
                    }
                }
                break;

            case 'change_password':
                $current_password = $_POST['current_password'];
                $new_password = $_POST['new_password'];
                $confirm_password = $_POST['confirm_password'];

                if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
                    $message = "All password fields are required!";
                    $message_type = "error";
                } elseif ($new_password !== $confirm_password) {
                    $message = "New passwords do not match!";
                    $message_type = "error";
                } elseif (strlen($new_password) < 6) {
                    $message = "Password must be at least 6 characters long!";
                    $message_type = "error";
                } else {
                    // Verify current password
                    $verify_query = "SELECT password FROM users WHERE id = ?";
                    $verify_stmt = mysqli_prepare($conn, $verify_query);
                    mysqli_stmt_bind_param($verify_stmt, "i", $_SESSION['user_id']);
                    mysqli_stmt_execute($verify_stmt);
                    $verify_result = mysqli_stmt_get_result($verify_stmt);
                    $user = mysqli_fetch_assoc($verify_result);

                    if (!password_verify($current_password, $user['password'])) {
                        $message = "Current password is incorrect!";
                        $message_type = "error";
                    } else {
                        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                        $password_query = "UPDATE users SET password = ? WHERE id = ?";
                        $password_stmt = mysqli_prepare($conn, $password_query);
                        mysqli_stmt_bind_param($password_stmt, "si", $hashed_password, $_SESSION['user_id']);

                        if (mysqli_stmt_execute($password_stmt)) {
                            $message = "Password changed successfully!";
                            $message_type = "success";
                        } else {
                            $message = "Error changing password: " . mysqli_error($conn);
                            $message_type = "error";
                        }
                    }
                }
                break;
        }
    }
}

// Get teacher information from faculty table
$faculty_query = "SELECT f.*, u.username, u.email as user_email
                  FROM faculty f
                  LEFT JOIN users u ON f.email = u.email
                  WHERE f.email = ? AND f.is_active = 1";
$faculty_stmt = mysqli_prepare($conn, $faculty_query);
mysqli_stmt_bind_param($faculty_stmt, "s", $_SESSION['username']);
mysqli_stmt_execute($faculty_stmt);
$faculty_result = mysqli_stmt_get_result($faculty_stmt);
$faculty_info = mysqli_fetch_assoc($faculty_result);

// Get account statistics
$classes_count_query = "SELECT COUNT(*) as total FROM teacher_classes WHERE teacher_id = ? AND status = 'active'";
$classes_count_stmt = mysqli_prepare($conn, $classes_count_query);
mysqli_stmt_bind_param($classes_count_stmt, "i", $_SESSION['user_id']);
mysqli_stmt_execute($classes_count_stmt);
$classes_count_result = mysqli_stmt_get_result($classes_count_stmt);
$classes_count = mysqli_fetch_assoc($classes_count_result)['total'];

$evaluations_count_query = "SELECT COUNT(*) as total FROM evaluation_sessions WHERE evaluator_id = ?";
$evaluations_count_stmt = mysqli_prepare($conn, $evaluations_count_query);
mysqli_stmt_bind_param($evaluations_count_stmt, "i", $_SESSION['user_id']);
mysqli_stmt_execute($evaluations_count_stmt);
$evaluations_count_result = mysqli_stmt_get_result($evaluations_count_stmt);
$evaluations_count = mysqli_fetch_assoc($evaluations_count_result)['total'];

$completed_evaluations_query = "SELECT COUNT(*) as total FROM evaluation_sessions WHERE evaluator_id = ? AND status = 'completed'";
$completed_evaluations_stmt = mysqli_prepare($conn, $completed_evaluations_query);
mysqli_stmt_bind_param($completed_evaluations_stmt, "i", $_SESSION['user_id']);
mysqli_stmt_execute($completed_evaluations_stmt);
$completed_evaluations_result = mysqli_stmt_get_result($completed_evaluations_stmt);
$completed_evaluations_count = mysqli_fetch_assoc($completed_evaluations_result)['total'];

$peer_evaluations_query = "SELECT COUNT(*) as total FROM evaluation_sessions
                          WHERE evaluator_id = ? AND main_category_id IN
                          (SELECT id FROM main_evaluation_categories WHERE evaluation_type = 'peer_to_peer')";
$peer_evaluations_stmt = mysqli_prepare($conn, $peer_evaluations_query);
mysqli_stmt_bind_param($peer_evaluations_stmt, "i", $_SESSION['user_id']);
mysqli_stmt_execute($peer_evaluations_stmt);
$peer_evaluations_result = mysqli_stmt_get_result($peer_evaluations_stmt);
$peer_evaluations_count = mysqli_fetch_assoc($peer_evaluations_result)['total'];

// Include the shared header
$sidebar_context = 'main';
include 'includes/unified-header.php';
?>

<div class="mb-6 sm:mb-8">
    <h1 class="text-2xl sm:text-3xl font-bold text-seait-dark mb-2">Profile Settings</h1>
    <p class="text-sm sm:text-base text-gray-600">Manage your account information and settings</p>
</div>

<!-- Message Display -->
<?php if ($message): ?>
<div class="mb-6 p-4 rounded-lg <?php echo $message_type === 'success' ? 'bg-green-50 border border-green-200 text-green-800' : 'bg-red-50 border border-red-200 text-red-800'; ?>">
    <?php echo htmlspecialchars($message); ?>
</div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 sm:gap-8">
    <!-- Profile Information -->
    <div class="lg:col-span-2">
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-medium text-gray-900">Profile Information</h2>
            </div>

            <div class="p-6">
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="update_profile">

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">First Name <span class="text-red-500">*</span></label>
                            <input type="text" name="first_name" value="<?php echo htmlspecialchars($_SESSION['first_name']); ?>" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Last Name <span class="text-red-500">*</span></label>
                            <input type="text" name="last_name" value="<?php echo htmlspecialchars($_SESSION['last_name']); ?>" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Email <span class="text-red-500">*</span></label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($_SESSION['username']); ?>" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                    </div>

                    <div class="flex justify-end">
                        <button type="submit" class="bg-seait-orange text-white px-6 py-2 rounded-lg hover:bg-orange-600 transition">
                            <i class="fas fa-save mr-2"></i>Update Profile
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Change Password -->
        <div class="mt-6 bg-white rounded-lg shadow-md overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-medium text-gray-900">Change Password</h2>
            </div>

            <div class="p-6">
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="change_password">

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Current Password <span class="text-red-500">*</span></label>
                        <input type="password" name="current_password" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">New Password <span class="text-red-500">*</span></label>
                            <input type="password" name="new_password" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Confirm New Password <span class="text-red-500">*</span></label>
                            <input type="password" name="confirm_password" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition">
                            <i class="fas fa-key mr-2"></i>Change Password
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Account Information -->
    <div class="lg:col-span-1">
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-medium text-gray-900">Account Information</h2>
            </div>

            <div class="p-6">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-500">Username</label>
                        <p class="text-sm text-gray-900"><?php echo htmlspecialchars($_SESSION['username']); ?></p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-500">Department</label>
                        <p class="text-sm text-gray-900"><?php echo htmlspecialchars($faculty_info['department'] ?? 'Not specified'); ?></p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-500">Position</label>
                        <p class="text-sm text-gray-900"><?php echo htmlspecialchars($faculty_info['position'] ?? 'Not specified'); ?></p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-500">Phone</label>
                        <p class="text-sm text-gray-900"><?php echo htmlspecialchars($faculty_info['phone'] ?? 'Not specified'); ?></p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-500">Account Created</label>
                        <p class="text-sm text-gray-900"><?php echo date('M d, Y', strtotime($faculty_info['created_at'] ?? 'now')); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Account Statistics -->
        <div class="mt-6 bg-white rounded-lg shadow-md overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-medium text-gray-900">Account Statistics</h2>
            </div>

            <div class="p-6">
                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-600">Active Classes</span>
                        <span class="text-sm font-medium text-gray-900"><?php echo number_format($classes_count); ?></span>
                    </div>

                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-600">Total Evaluations</span>
                        <span class="text-sm font-medium text-gray-900"><?php echo number_format($evaluations_count); ?></span>
                    </div>

                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-600">Completed Evaluations</span>
                        <span class="text-sm font-medium text-gray-900"><?php echo number_format($completed_evaluations_count); ?></span>
                    </div>

                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-600">Peer Evaluations</span>
                        <span class="text-sm font-medium text-gray-900"><?php echo number_format($peer_evaluations_count); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Include the shared footer
include 'includes/footer.php';
?>