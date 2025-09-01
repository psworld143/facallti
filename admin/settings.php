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
            case 'update_general':
                $site_name = sanitize_input($_POST['site_name']);
                $site_description = sanitize_input($_POST['site_description']);
                $site_keywords = sanitize_input($_POST['site_keywords']);
                $site_author = sanitize_input($_POST['site_author']);

                // Update settings in database
                $settings = [
                    'site_name' => $site_name,
                    'site_description' => $site_description,
                    'site_keywords' => $site_keywords,
                    'site_author' => $site_author
                ];

                foreach ($settings as $key => $value) {
                    $query = "INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
                             ON DUPLICATE KEY UPDATE setting_value = ?";
                    $stmt = mysqli_prepare($conn, $query);
                    mysqli_stmt_bind_param($stmt, "sss", $key, $value, $value);
                    mysqli_stmt_execute($stmt);
                }

                $message = "General settings updated successfully!";
                $message_type = "success";
                break;

            case 'update_contact':
                $contact_email = sanitize_input($_POST['contact_email']);
                $contact_phone = sanitize_input($_POST['contact_phone']);
                $contact_address = sanitize_input($_POST['contact_address']);
                $contact_facebook = sanitize_input($_POST['contact_facebook']);
                $contact_twitter = sanitize_input($_POST['contact_twitter']);
                $contact_instagram = sanitize_input($_POST['contact_instagram']);
                $contact_linkedin = sanitize_input($_POST['contact_linkedin']);

                $settings = [
                    'contact_email' => $contact_email,
                    'contact_phone' => $contact_phone,
                    'contact_address' => $contact_address,
                    'contact_facebook' => $contact_facebook,
                    'contact_twitter' => $contact_twitter,
                    'contact_instagram' => $contact_instagram,
                    'contact_linkedin' => $contact_linkedin
                ];

                foreach ($settings as $key => $value) {
                    $query = "INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
                             ON DUPLICATE KEY UPDATE setting_value = ?";
                    $stmt = mysqli_prepare($conn, $query);
                    mysqli_stmt_bind_param($stmt, "sss", $key, $value, $value);
                    mysqli_stmt_execute($stmt);
                }

                $message = "Contact settings updated successfully!";
                $message_type = "success";
                break;

            case 'update_system':
                $maintenance_mode = isset($_POST['maintenance_mode']) ? 1 : 0;
                $registration_enabled = isset($_POST['registration_enabled']) ? 1 : 0;
                $email_notifications = isset($_POST['email_notifications']) ? 1 : 0;
                $auto_approve_posts = isset($_POST['auto_approve_posts']) ? 1 : 0;

                $settings = [
                    'maintenance_mode' => $maintenance_mode,
                    'registration_enabled' => $registration_enabled,
                    'email_notifications' => $email_notifications,
                    'auto_approve_posts' => $auto_approve_posts
                ];

                foreach ($settings as $key => $value) {
                    $query = "INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
                             ON DUPLICATE KEY UPDATE setting_value = ?";
                    $stmt = mysqli_prepare($conn, $query);
                    mysqli_stmt_bind_param($stmt, "sss", $key, $value, $value);
                    mysqli_stmt_execute($stmt);
                }

                $message = "System settings updated successfully!";
                $message_type = "success";
                break;
        }
    }
}

// Get current settings
function get_setting($conn, $key, $default = '') {
    $query = "SELECT setting_value FROM settings WHERE setting_key = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "s", $key);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if ($row = mysqli_fetch_assoc($result)) {
        return $row['setting_value'];
    }
    return $default;
}

// Get all settings
$settings = [
    'site_name' => get_setting($conn, 'site_name', 'SEAIT - South East Asian Institute of Technology'),
    'site_description' => get_setting($conn, 'site_description', 'Official website of South East Asian Institute of Technology'),
    'site_keywords' => get_setting($conn, 'site_keywords', 'SEAIT, education, technology, institute'),
    'site_author' => get_setting($conn, 'site_author', 'SEAIT'),
    'contact_email' => get_setting($conn, 'contact_email', 'info@seait.edu.ph'),
    'contact_phone' => get_setting($conn, 'contact_phone', '+63 123 456 7890'),
    'contact_address' => get_setting($conn, 'contact_address', '123 Main Street, City, Philippines'),
    'contact_facebook' => get_setting($conn, 'contact_facebook', ''),
    'contact_twitter' => get_setting($conn, 'contact_twitter', ''),
    'contact_instagram' => get_setting($conn, 'contact_instagram', ''),
    'contact_linkedin' => get_setting($conn, 'contact_linkedin', ''),
    'maintenance_mode' => get_setting($conn, 'maintenance_mode', '0'),
    'registration_enabled' => get_setting($conn, 'registration_enabled', '1'),
    'email_notifications' => get_setting($conn, 'email_notifications', '1'),
    'auto_approve_posts' => get_setting($conn, 'auto_approve_posts', '0')
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Admin Dashboard</title>
    <link rel="icon" type="image/png" href="../assets/images/seait-logo.png">
    <link rel="shortcut icon" type="image/png" href="../assets/images/seait-logo.png">
    <link rel="apple-touch-icon" type="image/png" href="../assets/images/seait-logo.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'seait-orange': '#FF6B35',
                        'seait-dark': '#2C3E50',
                        'seait-light': '#FFF8F0'
                    }
                }
            }
        }
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    <?php include 'includes/admin-header.php'; ?>

    <div class="flex pt-16">
        <?php include 'includes/admin-sidebar.php'; ?>

        <!-- Main Content -->
        <div class="flex-1 ml-64 p-8 overflow-y-auto h-screen">
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-seait-dark mb-2">Website Settings</h1>
                <p class="text-gray-600">Configure website settings and preferences</p>
            </div>

            <?php if ($message): ?>
            <div class="mb-6 p-4 rounded-lg <?php echo $message_type === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                <?php echo $message; ?>
            </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <!-- General Settings -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">
                        <i class="fas fa-cog text-seait-orange mr-2"></i>General Settings
                    </h3>

                    <form method="POST">
                        <input type="hidden" name="action" value="update_general">

                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Site Name</label>
                                <input type="text" name="site_name" value="<?php echo htmlspecialchars($settings['site_name']); ?>"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange"
                                       required>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Site Description</label>
                                <textarea name="site_description" rows="3"
                                          class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange"><?php echo htmlspecialchars($settings['site_description']); ?></textarea>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Site Keywords</label>
                                <input type="text" name="site_keywords" value="<?php echo htmlspecialchars($settings['site_keywords']); ?>"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                                <p class="text-xs text-gray-500 mt-1">Separate keywords with commas</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Site Author</label>
                                <input type="text" name="site_author" value="<?php echo htmlspecialchars($settings['site_author']); ?>"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                            </div>
                        </div>

                        <button type="submit" class="mt-6 bg-seait-orange text-white px-4 py-2 rounded-md hover:bg-orange-600 transition">
                            <i class="fas fa-save mr-2"></i>Save General Settings
                        </button>
                    </form>
                </div>

                <!-- Contact Settings -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">
                        <i class="fas fa-address-book text-seait-orange mr-2"></i>Contact Information
                    </h3>

                    <form method="POST">
                        <input type="hidden" name="action" value="update_contact">

                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Email Address</label>
                                <input type="email" name="contact_email" value="<?php echo htmlspecialchars($settings['contact_email']); ?>"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Phone Number</label>
                                <input type="text" name="contact_phone" value="<?php echo htmlspecialchars($settings['contact_phone']); ?>"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Address</label>
                                <textarea name="contact_address" rows="3"
                                          class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange"><?php echo htmlspecialchars($settings['contact_address']); ?></textarea>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Facebook</label>
                                    <input type="url" name="contact_facebook" value="<?php echo htmlspecialchars($settings['contact_facebook']); ?>"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Twitter</label>
                                    <input type="url" name="contact_twitter" value="<?php echo htmlspecialchars($settings['contact_twitter']); ?>"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Instagram</label>
                                    <input type="url" name="contact_instagram" value="<?php echo htmlspecialchars($settings['contact_instagram']); ?>"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">LinkedIn</label>
                                    <input type="url" name="contact_linkedin" value="<?php echo htmlspecialchars($settings['contact_linkedin']); ?>"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                                </div>
                            </div>
                        </div>

                        <button type="submit" class="mt-6 bg-seait-orange text-white px-4 py-2 rounded-md hover:bg-orange-600 transition">
                            <i class="fas fa-save mr-2"></i>Save Contact Settings
                        </button>
                    </form>
                </div>

                <!-- System Settings -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">
                        <i class="fas fa-server text-seait-orange mr-2"></i>System Settings
                    </h3>

                    <form method="POST">
                        <input type="hidden" name="action" value="update_system">

                        <div class="space-y-4">
                            <div class="flex items-center justify-between">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Maintenance Mode</label>
                                    <p class="text-xs text-gray-500">Enable maintenance mode to restrict access</p>
                                </div>
                                <input type="checkbox" name="maintenance_mode" <?php echo $settings['maintenance_mode'] == '1' ? 'checked' : ''; ?>
                                       class="w-4 h-4 text-seait-orange bg-gray-100 border-gray-300 rounded focus:ring-seait-orange">
                            </div>

                            <div class="flex items-center justify-between">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">User Registration</label>
                                    <p class="text-xs text-gray-500">Allow new users to register</p>
                                </div>
                                <input type="checkbox" name="registration_enabled" <?php echo $settings['registration_enabled'] == '1' ? 'checked' : ''; ?>
                                       class="w-4 h-4 text-seait-orange bg-gray-100 border-gray-300 rounded focus:ring-seait-orange">
                            </div>

                            <div class="flex items-center justify-between">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Email Notifications</label>
                                    <p class="text-xs text-gray-500">Send email notifications for new content</p>
                                </div>
                                <input type="checkbox" name="email_notifications" <?php echo $settings['email_notifications'] == '1' ? 'checked' : ''; ?>
                                       class="w-4 h-4 text-seait-orange bg-gray-100 border-gray-300 rounded focus:ring-seait-orange">
                            </div>

                            <div class="flex items-center justify-between">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Auto-approve Posts</label>
                                    <p class="text-xs text-gray-500">Automatically approve new posts</p>
                                </div>
                                <input type="checkbox" name="auto_approve_posts" <?php echo $settings['auto_approve_posts'] == '1' ? 'checked' : ''; ?>
                                       class="w-4 h-4 text-seait-orange bg-gray-100 border-gray-300 rounded focus:ring-seait-orange">
                            </div>
                        </div>

                        <button type="submit" class="mt-6 bg-seait-orange text-white px-4 py-2 rounded-md hover:bg-orange-600 transition">
                            <i class="fas fa-save mr-2"></i>Save System Settings
                        </button>
                    </form>
                </div>

                <!-- System Information -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">
                        <i class="fas fa-info-circle text-seait-orange mr-2"></i>System Information
                    </h3>

                    <div class="space-y-3 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-600">PHP Version:</span>
                            <span class="font-medium"><?php echo phpversion(); ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">MySQL Version:</span>
                            <span class="font-medium"><?php echo mysqli_get_server_info($conn); ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Server Software:</span>
                            <span class="font-medium"><?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'; ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Upload Max Size:</span>
                            <span class="font-medium"><?php echo ini_get('upload_max_filesize'); ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Memory Limit:</span>
                            <span class="font-medium"><?php echo ini_get('memory_limit'); ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Max Execution Time:</span>
                            <span class="font-medium"><?php echo ini_get('max_execution_time'); ?>s</span>
                        </div>
                    </div>

                    <div class="mt-6 pt-4 border-t border-gray-200">
                        <h4 class="text-sm font-medium text-gray-700 mb-2">Quick Actions</h4>
                        <div class="space-y-2">
                            <button onclick="clearCache()" class="w-full text-left px-3 py-2 bg-gray-100 hover:bg-gray-200 rounded-md text-sm transition">
                                <i class="fas fa-broom mr-2"></i>Clear Cache
                            </button>
                            <button onclick="backupDatabase()" class="w-full text-left px-3 py-2 bg-gray-100 hover:bg-gray-200 rounded-md text-sm transition">
                                <i class="fas fa-download mr-2"></i>Backup Database
                            </button>
                            <button onclick="checkUpdates()" class="w-full text-left px-3 py-2 bg-gray-100 hover:bg-gray-200 rounded-md text-sm transition">
                                <i class="fas fa-sync mr-2"></i>Check for Updates
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Confirmation Modal -->
    <div id="confirmModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-2xl max-w-md w-full p-6">
            <div class="text-center mb-6">
                <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-question-circle text-blue-600 text-2xl"></i>
                </div>
                <h3 class="text-lg font-semibold text-gray-900 mb-2">Confirm Action</h3>
                <p class="text-gray-600 text-sm" id="confirmMessage">Are you sure you want to proceed?</p>
            </div>
            
            <div class="flex justify-center space-x-3">
                <button onclick="closeConfirmModal()"
                        class="px-6 py-3 text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 transition-all duration-200">
                    <i class="fas fa-times mr-2"></i>Cancel
                </button>
                <button id="confirmActionBtn"
                        class="px-6 py-3 bg-gradient-to-r from-blue-500 to-blue-600 text-white rounded-lg hover:from-blue-600 hover:to-blue-700 transition-all duration-200 font-semibold">
                    <i class="fas fa-check mr-2"></i>Confirm
                </button>
            </div>
        </div>
    </div>

    <!-- Success Modal -->
    <div id="successModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-2xl max-w-md w-full p-6">
            <div class="text-center mb-6">
                <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-check-circle text-green-600 text-2xl"></i>
                </div>
                <h3 class="text-lg font-semibold text-gray-900 mb-2">Success!</h3>
                <p class="text-gray-600 text-sm" id="successMessage">Operation completed successfully.</p>
            </div>
            
            <div class="flex justify-center">
                <button onclick="closeSuccessModal()" class="bg-green-500 hover:bg-green-600 text-white px-6 py-2 rounded-lg font-medium">
                    OK
                </button>
            </div>
        </div>
    </div>

    <script>
        function clearCache() {
            showConfirmModal('Are you sure you want to clear the cache?', function() {
                // Implement cache clearing functionality
                showSuccessModal('Cache cleared successfully!');
            });
        }

        function backupDatabase() {
            showConfirmModal('Do you want to create a database backup?', function() {
                // Implement database backup functionality
                showSuccessModal('Database backup created successfully!');
            });
        }

        function checkUpdates() {
            // Implement update check functionality
            showSuccessModal('No updates available at this time.');
        }

        function showConfirmModal(message, onConfirm) {
            document.getElementById('confirmMessage').textContent = message;
            document.getElementById('confirmModal').classList.remove('hidden');
            
            const confirmBtn = document.getElementById('confirmActionBtn');
            confirmBtn.onclick = function() {
                closeConfirmModal();
                onConfirm();
            };
        }

        function closeConfirmModal() {
            document.getElementById('confirmModal').classList.add('hidden');
        }

        function showSuccessModal(message) {
            document.getElementById('successMessage').textContent = message;
            document.getElementById('successModal').classList.remove('hidden');
        }

        function closeSuccessModal() {
            document.getElementById('successModal').classList.add('hidden');
        }

        // Close modals when clicking outside
        document.addEventListener('DOMContentLoaded', function() {
            const confirmModal = document.getElementById('confirmModal');
            const successModal = document.getElementById('successModal');
            
            if (confirmModal) {
                confirmModal.addEventListener('click', function(e) {
                    if (e.target === this) {
                        closeConfirmModal();
                    }
                });
            }
            
            if (successModal) {
                successModal.addEventListener('click', function(e) {
                    if (e.target === this) {
                        closeSuccessModal();
                    }
                });
            }
        });
    </script>
</body>
</html>