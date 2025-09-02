<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and has head role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'head') {
    header('Location: ../index.php');
    exit();
}

// Set page title
$page_title = 'Profile Settings';

$message = '';
$message_type = '';

// Get current user data
$user_id = $_SESSION['user_id'];
$user_query = "SELECT u.*, h.department, h.position, h.phone as head_phone 
               FROM users u 
               LEFT JOIN heads h ON u.id = h.user_id 
               WHERE u.id = ?";
$user_stmt = mysqli_prepare($conn, $user_query);
mysqli_stmt_bind_param($user_stmt, "i", $user_id);
mysqli_stmt_execute($user_stmt);
$user_result = mysqli_stmt_get_result($user_stmt);
$user_data = mysqli_fetch_assoc($user_result);

if (!$user_data) {
    header('Location: logout.php');
    exit();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_profile':
                $first_name = sanitize_input($_POST['first_name']);
                $last_name = sanitize_input($_POST['last_name']);
                $email = sanitize_input($_POST['email']);
                $phone = sanitize_input($_POST['phone']);
                $department = sanitize_input($_POST['department']);
                $position = sanitize_input($_POST['position']);

                // Validate email format
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $message = "Invalid email format!";
                    $message_type = "error";
                } else {
                    // Check if email already exists for another user
                    $email_check_query = "SELECT id FROM users WHERE email = ? AND id != ?";
                    $email_check_stmt = mysqli_prepare($conn, $email_check_query);
                    mysqli_stmt_bind_param($email_check_stmt, "si", $email, $user_id);
                    mysqli_stmt_execute($email_check_stmt);
                    $email_check_result = mysqli_stmt_get_result($email_check_stmt);

                    if (mysqli_num_rows($email_check_result) > 0) {
                        $message = "Email already exists!";
                        $message_type = "error";
                    } else {
                        // Update users table
                        $update_user_query = "UPDATE users SET first_name = ?, last_name = ?, email = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
                        $update_user_stmt = mysqli_prepare($conn, $update_user_query);
                        mysqli_stmt_bind_param($update_user_stmt, "sssi", $first_name, $last_name, $email, $user_id);

                        if (mysqli_stmt_execute($update_user_stmt)) {
                            // Update heads table
                            $update_head_query = "UPDATE heads SET department = ?, position = ?, phone = ?, updated_at = CURRENT_TIMESTAMP WHERE user_id = ?";
                            $update_head_stmt = mysqli_prepare($conn, $update_head_query);
                            mysqli_stmt_bind_param($update_head_stmt, "sssi", $department, $position, $phone, $user_id);

                            if (mysqli_stmt_execute($update_head_stmt)) {
                                // Update session data
                                $_SESSION['first_name'] = $first_name;
                                $_SESSION['last_name'] = $last_name;
                                $_SESSION['email'] = $email;

                                $message = "Profile updated successfully!";
                                $message_type = "success";

                                // Refresh user data
                                $user_data['first_name'] = $first_name;
                                $user_data['last_name'] = $last_name;
                                $user_data['email'] = $email;
                                $user_data['department'] = $department;
                                $user_data['position'] = $position;
                                $user_data['head_phone'] = $phone;
                            } else {
                                $message = "Error updating head information: " . mysqli_error($conn);
                                $message_type = "error";
                            }
                        } else {
                            $message = "Error updating user information: " . mysqli_error($conn);
                            $message_type = "error";
                        }
                    }
                }
                break;

            case 'change_password':
                $current_password = $_POST['current_password'];
                $new_password = $_POST['new_password'];
                $confirm_password = $_POST['confirm_password'];

                // Validate current password
                if (!password_verify($current_password, $user_data['password'])) {
                    $message = "Current password is incorrect!";
                    $message_type = "error";
                } elseif (strlen($new_password) < 6) {
                    $message = "New password must be at least 6 characters long!";
                    $message_type = "error";
                } elseif ($new_password !== $confirm_password) {
                    $message = "New passwords do not match!";
                    $message_type = "error";
                } else {
                    // Hash new password
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

                    // Update password
                    $update_password_query = "UPDATE users SET password = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
                    $update_password_stmt = mysqli_prepare($conn, $update_password_query);
                    mysqli_stmt_bind_param($update_password_stmt, "si", $hashed_password, $user_id);

                    if (mysqli_stmt_execute($update_password_stmt)) {
                        $message = "Password changed successfully!";
                        $message_type = "success";
                    } else {
                        $message = "Error changing password: " . mysqli_error($conn);
                        $message_type = "error";
                    }
                }
                break;
        }
    }
}

include 'includes/header.php';
?>

<div class="animate-fadeInUp">
    <!-- Page Header -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-seait-dark">Profile Settings</h1>
                <p class="text-gray-600 mt-1">Manage your account information and security settings</p>
            </div>
            <div class="flex items-center space-x-3">
                <div class="h-16 w-16 rounded-full bg-seait-orange flex items-center justify-center">
                    <span class="text-white font-semibold text-xl">
                        <?php echo strtoupper(substr($user_data['first_name'], 0, 1) . substr($user_data['last_name'], 0, 1)); ?>
                    </span>
                </div>
            </div>
        </div>
    </div>

        <?php if ($message): ?>
            <div class="mb-6 p-4 rounded-lg <?php echo $message_type === 'success' ? 'bg-green-100 border border-green-400 text-green-700' : 'bg-red-100 border border-red-400 text-red-700'; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Profile Information -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                <div class="p-6 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-seait-dark flex items-center">
                        <i class="fas fa-user-edit mr-3 text-seait-orange"></i>
                        Profile Information
                    </h2>
                    <p class="text-gray-600 text-sm mt-1">Update your personal and professional information</p>
                </div>
                <div class="p-6">
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="update_profile">
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="first_name" class="block text-sm font-medium text-gray-700 mb-2">First Name</label>
                                <input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($user_data['first_name']); ?>" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-seait-orange focus:border-transparent transition-all duration-200" required>
                            </div>
                            <div>
                                <label for="last_name" class="block text-sm font-medium text-gray-700 mb-2">Last Name</label>
                                <input type="text" id="last_name" name="last_name" value="<?php echo htmlspecialchars($user_data['last_name']); ?>" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-seait-orange focus:border-transparent transition-all duration-200" required>
                            </div>
                        </div>

                        <div class="mt-4">
                            <label for="email" class="block text-sm font-medium text-gray-700 mb-2">Email Address</label>
                            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user_data['email']); ?>" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-seait-orange focus:border-transparent transition-all duration-200" required>
                        </div>

                        <div class="mt-4">
                            <label for="phone" class="block text-sm font-medium text-gray-700 mb-2">Phone Number</label>
                            <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($user_data['head_phone'] ?? ''); ?>" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-seait-orange focus:border-transparent transition-all duration-200">
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                            <div>
                                <label for="department" class="block text-sm font-medium text-gray-700 mb-2">Department</label>
                                <input type="text" id="department" name="department" value="<?php echo htmlspecialchars($user_data['department'] ?? ''); ?>" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-seait-orange focus:border-transparent transition-all duration-200" required>
                            </div>
                            <div>
                                <label for="position" class="block text-sm font-medium text-gray-700 mb-2">Position</label>
                                <input type="text" id="position" name="position" value="<?php echo htmlspecialchars($user_data['position'] ?? ''); ?>" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-seait-orange focus:border-transparent transition-all duration-200" required>
                            </div>
                        </div>

                        <div class="mt-6">
                            <button type="submit" class="w-full bg-seait-orange text-white py-2 px-4 rounded-lg hover:bg-orange-600 transition-all duration-200 transform hover:scale-105 hover:shadow-lg">
                                <i class="fas fa-save mr-2"></i>Update Profile
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Change Password -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                <div class="p-6 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-seait-dark flex items-center">
                        <i class="fas fa-lock mr-3 text-seait-orange"></i>
                        Change Password
                    </h2>
                    <p class="text-gray-600 text-sm mt-1">Update your account password for enhanced security</p>
                </div>
                <div class="p-6">
                    <form method="POST" action="" id="passwordForm">
                        <input type="hidden" name="action" value="change_password">
                        
                        <div class="mb-4">
                            <label for="current_password" class="block text-sm font-medium text-gray-700 mb-2">Current Password</label>
                            <div class="relative">
                                <input type="password" id="current_password" name="current_password" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-seait-orange focus:border-transparent transition-all duration-200 pr-10" required>
                                <button type="button" class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600" onclick="togglePassword('current_password')">
                                    <i class="fas fa-eye" id="current_password_icon"></i>
                                </button>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="new_password" class="block text-sm font-medium text-gray-700 mb-2">New Password</label>
                            <div class="relative">
                                <input type="password" id="new_password" name="new_password" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-seait-orange focus:border-transparent transition-all duration-200 pr-10" required>
                                <button type="button" class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600" onclick="togglePassword('new_password')">
                                    <i class="fas fa-eye" id="new_password_icon"></i>
                                </button>
                            </div>
                            <div class="mt-1 text-xs text-gray-500">
                                Password must be at least 6 characters long
                            </div>
                        </div>

                        <div class="mb-6">
                            <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-2">Confirm New Password</label>
                            <div class="relative">
                                <input type="password" id="confirm_password" name="confirm_password" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-seait-orange focus:border-transparent transition-all duration-200 pr-10" required>
                                <button type="button" class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600" onclick="togglePassword('confirm_password')">
                                    <i class="fas fa-eye" id="confirm_password_icon"></i>
                                </button>
                            </div>
                        </div>

                        <button type="submit" class="w-full bg-red-600 text-white py-2 px-4 rounded-lg hover:bg-red-700 transition-all duration-200 transform hover:scale-105 hover:shadow-lg">
                            <i class="fas fa-key mr-2"></i>Change Password
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Account Information -->
        <div class="mt-6 bg-white rounded-lg shadow-sm border border-gray-200">
            <div class="p-6 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-seait-dark flex items-center">
                    <i class="fas fa-info-circle mr-3 text-seait-orange"></i>
                    Account Information
                </h2>
                <p class="text-gray-600 text-sm mt-1">Your account details and system information</p>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <div class="bg-gray-50 rounded-lg p-4">
                        <div class="flex items-center">
                            <i class="fas fa-user-tag text-seait-orange mr-3"></i>
                            <div>
                                <p class="text-sm text-gray-600">Username</p>
                                <p class="font-semibold text-seait-dark"><?php echo htmlspecialchars($user_data['username']); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 rounded-lg p-4">
                        <div class="flex items-center">
                            <i class="fas fa-shield-alt text-seait-orange mr-3"></i>
                            <div>
                                <p class="text-sm text-gray-600">Role</p>
                                <p class="font-semibold text-seait-dark"><?php echo ucfirst(htmlspecialchars($user_data['role'])); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 rounded-lg p-4">
                        <div class="flex items-center">
                            <i class="fas fa-calendar-alt text-seait-orange mr-3"></i>
                            <div>
                                <p class="text-sm text-gray-600">Member Since</p>
                                <p class="font-semibold text-seait-dark"><?php echo date('M d, Y', strtotime($user_data['created_at'])); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 rounded-lg p-4">
                        <div class="flex items-center">
                            <i class="fas fa-clock text-seait-orange mr-3"></i>
                            <div>
                                <p class="text-sm text-gray-600">Last Updated</p>
                                <p class="font-semibold text-seait-dark"><?php echo date('M d, Y H:i', strtotime($user_data['updated_at'])); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 rounded-lg p-4">
                        <div class="flex items-center">
                            <i class="fas fa-check-circle text-green-500 mr-3"></i>
                            <div>
                                <p class="text-sm text-gray-600">Status</p>
                                <p class="font-semibold text-green-600"><?php echo ucfirst(htmlspecialchars($user_data['status'])); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 rounded-lg p-4">
                        <div class="flex items-center">
                            <i class="fas fa-building text-seait-orange mr-3"></i>
                            <div>
                                <p class="text-sm text-gray-600">Department</p>
                                <p class="font-semibold text-seait-dark"><?php echo htmlspecialchars($user_data['department'] ?? 'Not set'); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

<script>
function togglePassword(fieldId) {
    const field = document.getElementById(fieldId);
    const icon = document.getElementById(fieldId + '_icon');
    
    if (field.type === 'password') {
        field.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        field.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}

// Password confirmation validation
document.getElementById('passwordForm').addEventListener('submit', function(e) {
    const newPassword = document.getElementById('new_password').value;
    const confirmPassword = document.getElementById('confirm_password').value;
    
    if (newPassword !== confirmPassword) {
        e.preventDefault();
        alert('New passwords do not match!');
        return false;
    }
    
    if (newPassword.length < 6) {
        e.preventDefault();
        alert('New password must be at least 6 characters long!');
        return false;
    }
});

// Auto-hide success messages after 5 seconds
document.addEventListener('DOMContentLoaded', function() {
    const successMessages = document.querySelectorAll('.bg-green-100');
    successMessages.forEach(function(message) {
        setTimeout(function() {
            message.style.transition = 'opacity 0.5s ease-out';
            message.style.opacity = '0';
            setTimeout(function() {
                message.remove();
            }, 500);
        }, 5000);
    });
});
</script>

