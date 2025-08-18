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
$page_title = 'Class Settings';

$message = '';
$message_type = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_notifications':
                $email_notifications = isset($_POST['email_notifications']) ? 1 : 0;
                $announcement_notifications = isset($_POST['announcement_notifications']) ? 1 : 0;
                $student_join_notifications = isset($_POST['student_join_notifications']) ? 1 : 0;
                $evaluation_notifications = isset($_POST['evaluation_notifications']) ? 1 : 0;

                // Update or insert notification settings
                $settings_query = "INSERT INTO teacher_settings (teacher_id, email_notifications, announcement_notifications, student_join_notifications, evaluation_notifications)
                                  VALUES (?, ?, ?, ?, ?)
                                  ON DUPLICATE KEY UPDATE
                                  email_notifications = VALUES(email_notifications),
                                  announcement_notifications = VALUES(announcement_notifications),
                                  student_join_notifications = VALUES(student_join_notifications),
                                  evaluation_notifications = VALUES(evaluation_notifications)";
                $settings_stmt = mysqli_prepare($conn, $settings_query);
                mysqli_stmt_bind_param($settings_stmt, "iiiii", $_SESSION['user_id'], $email_notifications, $announcement_notifications, $student_join_notifications, $evaluation_notifications);

                if (mysqli_stmt_execute($settings_stmt)) {
                    $message = "Notification settings updated successfully!";
                    $message_type = "success";
                } else {
                    $message = "Error updating notification settings: " . mysqli_error($conn);
                    $message_type = "error";
                }
                break;

            case 'update_defaults':
                $default_class_status = sanitize_input($_POST['default_class_status']);
                $auto_approve_students = isset($_POST['auto_approve_students']) ? 1 : 0;
                $default_announcement_priority = sanitize_input($_POST['default_announcement_priority']);

                // Update or insert default settings
                $defaults_query = "INSERT INTO teacher_settings (teacher_id, default_class_status, auto_approve_students, default_announcement_priority)
                                  VALUES (?, ?, ?, ?)
                                  ON DUPLICATE KEY UPDATE
                                  default_class_status = VALUES(default_class_status),
                                  auto_approve_students = VALUES(auto_approve_students),
                                  default_announcement_priority = VALUES(default_announcement_priority)";
                $defaults_stmt = mysqli_prepare($conn, $defaults_query);
                mysqli_stmt_bind_param($defaults_stmt, "isis", $_SESSION['user_id'], $default_class_status, $auto_approve_students, $default_announcement_priority);

                if (mysqli_stmt_execute($defaults_stmt)) {
                    $message = "Default settings updated successfully!";
                    $message_type = "success";
                } else {
                    $message = "Error updating default settings: " . mysqli_error($conn);
                    $message_type = "error";
                }
                break;
        }
    }
}

// Get current settings
$settings_query = "SELECT * FROM teacher_settings WHERE teacher_id = ?";
$settings_stmt = mysqli_prepare($conn, $settings_query);
mysqli_stmt_bind_param($settings_stmt, "i", $_SESSION['user_id']);
mysqli_stmt_execute($settings_stmt);
$settings_result = mysqli_stmt_get_result($settings_stmt);
$settings = mysqli_fetch_assoc($settings_result);

// If no settings exist, create default values
if (!$settings) {
    $settings = [
        'email_notifications' => 1,
        'announcement_notifications' => 1,
        'student_join_notifications' => 1,
        'evaluation_notifications' => 1,
        'default_class_status' => 'active',
        'auto_approve_students' => 0,
        'default_announcement_priority' => 'medium'
    ];
}

// Include the shared header
$sidebar_context = 'main';
include 'includes/unified-header.php';
?>

<div class="mb-6 sm:mb-8">
    <h1 class="text-2xl sm:text-3xl font-bold text-seait-dark mb-2">Class Settings</h1>
    <p class="text-sm sm:text-base text-gray-600">Configure your class management preferences and notifications</p>
</div>

<!-- Message Display -->
<?php if ($message): ?>
<div class="mb-6 p-4 rounded-lg <?php echo $message_type === 'success' ? 'bg-green-50 border border-green-200 text-green-800' : 'bg-red-50 border border-red-200 text-red-800'; ?>">
    <?php echo htmlspecialchars($message); ?>
</div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
    <!-- Notification Settings -->
    <div class="bg-white rounded-lg shadow-md">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-medium text-gray-900">Notification Settings</h2>
            <p class="text-sm text-gray-600">Configure how you receive notifications</p>
        </div>
        <div class="p-6">
            <form method="POST">
                <input type="hidden" name="action" value="update_notifications">

                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-sm font-medium text-gray-900">Email Notifications</h3>
                            <p class="text-sm text-gray-500">Receive notifications via email</p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" name="email_notifications" class="sr-only peer" <?php echo $settings['email_notifications'] ? 'checked' : ''; ?>>
                            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-seait-orange rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-seait-orange"></div>
                        </label>
                    </div>

                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-sm font-medium text-gray-900">Announcement Notifications</h3>
                            <p class="text-sm text-gray-500">Get notified when students view announcements</p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" name="announcement_notifications" class="sr-only peer" <?php echo $settings['announcement_notifications'] ? 'checked' : ''; ?>>
                            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-seait-orange rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-seait-orange"></div>
                        </label>
                    </div>

                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-sm font-medium text-gray-900">Student Join Notifications</h3>
                            <p class="text-sm text-gray-500">Notify when students join your classes</p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" name="student_join_notifications" class="sr-only peer" <?php echo $settings['student_join_notifications'] ? 'checked' : ''; ?>>
                            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-seait-orange rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-seait-orange"></div>
                        </label>
                    </div>

                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-sm font-medium text-gray-900">Evaluation Notifications</h3>
                            <p class="text-sm text-gray-500">Get notified about evaluation activities</p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" name="evaluation_notifications" class="sr-only peer" <?php echo $settings['evaluation_notifications'] ? 'checked' : ''; ?>>
                            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-seait-orange rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-seait-orange"></div>
                        </label>
                    </div>
                </div>

                <div class="mt-6">
                    <button type="submit" class="w-full bg-seait-orange text-white px-4 py-2 rounded-md hover:bg-orange-600 transition">
                        <i class="fas fa-save mr-2"></i>Save Notification Settings
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Default Settings -->
    <div class="bg-white rounded-lg shadow-md">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-medium text-gray-900">Default Settings</h2>
            <p class="text-sm text-gray-600">Set default values for new classes</p>
        </div>
        <div class="p-6">
            <form method="POST">
                <input type="hidden" name="action" value="update_defaults">

                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Default Class Status</label>
                        <select name="default_class_status" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                            <option value="active" <?php echo $settings['default_class_status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo $settings['default_class_status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                        <p class="text-sm text-gray-500 mt-1">Status for newly created classes</p>
                    </div>

                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-sm font-medium text-gray-900">Auto-approve Students</h3>
                            <p class="text-sm text-gray-500">Automatically approve students who join with join code</p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" name="auto_approve_students" class="sr-only peer" <?php echo $settings['auto_approve_students'] ? 'checked' : ''; ?>>
                            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-seait-orange rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-seait-orange"></div>
                        </label>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Default Announcement Priority</label>
                        <select name="default_announcement_priority" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                            <option value="low" <?php echo $settings['default_announcement_priority'] === 'low' ? 'selected' : ''; ?>>Low</option>
                            <option value="medium" <?php echo $settings['default_announcement_priority'] === 'medium' ? 'selected' : ''; ?>>Medium</option>
                            <option value="high" <?php echo $settings['default_announcement_priority'] === 'high' ? 'selected' : ''; ?>>High</option>
                            <option value="urgent" <?php echo $settings['default_announcement_priority'] === 'urgent' ? 'selected' : ''; ?>>Urgent</option>
                        </select>
                        <p class="text-sm text-gray-500 mt-1">Default priority for new announcements</p>
                    </div>
                </div>

                <div class="mt-6">
                    <button type="submit" class="w-full bg-seait-orange text-white px-4 py-2 rounded-md hover:bg-orange-600 transition">
                        <i class="fas fa-save mr-2"></i>Save Default Settings
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Additional Settings -->
<div class="mt-8 bg-white rounded-lg shadow-md">
    <div class="px-6 py-4 border-b border-gray-200">
        <h2 class="text-lg font-medium text-gray-900">Data Management</h2>
        <p class="text-sm text-gray-600">Manage your class data and exports</p>
    </div>
    <div class="p-6">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="border border-gray-200 rounded-lg p-4">
                <div class="flex items-center">
                    <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center mr-3">
                        <i class="fas fa-download text-blue-600"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-medium text-gray-900">Export Class Data</h3>
                        <p class="text-xs text-gray-500">Download class information</p>
                    </div>
                </div>
                <button class="mt-3 w-full bg-blue-600 text-white px-3 py-2 rounded-md hover:bg-blue-700 transition text-sm">
                    <i class="fas fa-file-export mr-1"></i>Export
                </button>
            </div>

            <div class="border border-gray-200 rounded-lg p-4">
                <div class="flex items-center">
                    <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center mr-3">
                        <i class="fas fa-users text-green-600"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-medium text-gray-900">Export Student List</h3>
                        <p class="text-xs text-gray-500">Download student information</p>
                    </div>
                </div>
                <button class="mt-3 w-full bg-green-600 text-white px-3 py-2 rounded-md hover:bg-green-700 transition text-sm">
                    <i class="fas fa-file-export mr-1"></i>Export
                </button>
            </div>

            <div class="border border-gray-200 rounded-lg p-4">
                <div class="flex items-center">
                    <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center mr-3">
                        <i class="fas fa-chart-bar text-purple-600"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-medium text-gray-900">Export Analytics</h3>
                        <p class="text-xs text-gray-500">Download analytics reports</p>
                    </div>
                </div>
                <button class="mt-3 w-full bg-purple-600 text-white px-3 py-2 rounded-md hover:bg-purple-700 transition text-sm">
                    <i class="fas fa-file-export mr-1"></i>Export
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Danger Zone -->
<div class="mt-8 bg-white rounded-lg shadow-md border border-red-200">
    <div class="px-6 py-4 border-b border-red-200 bg-red-50">
        <h2 class="text-lg font-medium text-red-900">Danger Zone</h2>
        <p class="text-sm text-red-600">Irreversible actions</p>
    </div>
    <div class="p-6">
        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-medium text-gray-900">Delete All Classes</h3>
                    <p class="text-sm text-gray-500">Permanently delete all your classes and data</p>
                </div>
                <button class="bg-red-600 text-white px-4 py-2 rounded-md hover:bg-red-700 transition text-sm">
                    <i class="fas fa-trash mr-1"></i>Delete All
                </button>
            </div>

            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-medium text-gray-900">Reset Settings</h3>
                    <p class="text-sm text-gray-500">Reset all settings to default values</p>
                </div>
                <button class="bg-yellow-600 text-white px-4 py-2 rounded-md hover:bg-yellow-700 transition text-sm">
                    <i class="fas fa-undo mr-1"></i>Reset
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Add confirmation dialogs for dangerous actions
document.addEventListener('DOMContentLoaded', function() {
    // Delete All Classes confirmation
    const deleteAllBtn = document.querySelector('button:contains("Delete All")');
    if (deleteAllBtn) {
        deleteAllBtn.addEventListener('click', function(e) {
            e.preventDefault();
            if (confirm('Are you sure you want to delete ALL your classes? This action cannot be undone and will permanently remove all class data, students, announcements, and events.')) {
                alert('Delete functionality will be implemented here.');
            }
        });
    }

    // Reset Settings confirmation
    const resetBtn = document.querySelector('button:contains("Reset")');
    if (resetBtn) {
        resetBtn.addEventListener('click', function(e) {
            e.preventDefault();
            if (confirm('Are you sure you want to reset all settings to default values? This will clear all your custom preferences.')) {
                alert('Reset functionality will be implemented here.');
            }
        });
    }
});
</script>

<?php
// Include the shared footer
include 'includes/footer.php';
?>