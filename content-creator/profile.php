<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/id_encryption.php';

// Check if user is logged in and is a content creator
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'content_creator') {
    header('Location: ../login.php');
    exit();
}

$message = '';
$error = '';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'update_profile') {
        $first_name = mysqli_real_escape_string($conn, $_POST['first_name']);
        $last_name = mysqli_real_escape_string($conn, $_POST['last_name']);
        $email = mysqli_real_escape_string($conn, $_POST['email']);

        // Validate email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            // Check if email is already taken by another user
            $email_check_query = "SELECT id FROM users WHERE email = ? AND id != ?";
            $stmt = mysqli_prepare($conn, $email_check_query);
            mysqli_stmt_bind_param($stmt, "si", $email, $_SESSION['user_id']);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);

            if (mysqli_num_rows($result) > 0) {
                $error = 'Email address is already taken by another user.';
            } else {
                $update_query = "UPDATE users SET first_name = ?, last_name = ?, email = ? WHERE id = ?";
                $stmt = mysqli_prepare($conn, $update_query);
                mysqli_stmt_bind_param($stmt, "sssi", $first_name, $last_name, $email, $_SESSION['user_id']);

                if (mysqli_stmt_execute($stmt)) {
                    // Update session data
                    $_SESSION['first_name'] = $first_name;
                    $_SESSION['last_name'] = $last_name;
                    $_SESSION['email'] = $email;
                    $message = 'Profile updated successfully!';
                } else {
                    $error = 'Failed to update profile.';
                }
            }
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'change_password') {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];

        // Verify current password
        $verify_query = "SELECT password FROM users WHERE id = ?";
        $stmt = mysqli_prepare($conn, $verify_query);
        mysqli_stmt_bind_param($stmt, "i", $_SESSION['user_id']);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user = mysqli_fetch_assoc($result);

        if (password_verify($current_password, $user['password'])) {
            if ($new_password === $confirm_password) {
                if (strlen($new_password) >= 6) {
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $update_query = "UPDATE users SET password = ? WHERE id = ?";
                    $stmt = mysqli_prepare($conn, $update_query);
                    mysqli_stmt_bind_param($stmt, "si", $hashed_password, $_SESSION['user_id']);

                    if (mysqli_stmt_execute($stmt)) {
                        $message = 'Password changed successfully!';
                    } else {
                        $error = 'Failed to change password.';
                    }
                } else {
                    $error = 'New password must be at least 6 characters long.';
                }
            } else {
                $error = 'New passwords do not match.';
            }
        } else {
            $error = 'Current password is incorrect.';
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'update_photo') {
        // Handle profile photo upload
        if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['profile_photo'];
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            $max_size = 5 * 1024 * 1024; // 5MB

            if (!in_array($file['type'], $allowed_types)) {
                $error = 'Please upload a valid image file (JPEG, PNG, or GIF).';
            } elseif ($file['size'] > $max_size) {
                $error = 'File size must be less than 5MB.';
            } else {
                // Use absolute path for better reliability
                $upload_dir = dirname(__FILE__) . '/../uploads/profile-photos/';
                
                // Ensure directory exists with proper permissions
                if (!is_dir($upload_dir)) {
                    if (!mkdir($upload_dir, 0755, true)) {
                        $error = 'Failed to create upload directory. Please check permissions.';
                        error_log("Content Creator Profile Debug - Failed to create directory: " . $upload_dir);
                    }
                }
                
                // Check if directory is writable
                if (!is_writable($upload_dir)) {
                    $error = 'Upload directory is not writable. Please check permissions.';
                    error_log("Content Creator Profile Debug - Directory not writable: " . $upload_dir);
                }
                
                // Additional debug: Check if we can create a test file
                $test_file = $upload_dir . 'test_' . time() . '.txt';
                if (file_put_contents($test_file, 'test') === false) {
                    error_log("Content Creator Profile Debug - Cannot create test file in upload directory");
                    $error = 'Cannot write to upload directory. Please check permissions.';
                } else {
                    unlink($test_file); // Remove test file
                    error_log("Content Creator Profile Debug - Upload directory is writable");
                }

                $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                $filename = 'profile_' . $_SESSION['user_id'] . '_' . time() . '.' . $file_extension;
                $filepath = $upload_dir . $filename;

                if (move_uploaded_file($file['tmp_name'], $filepath)) {
                    // Update database with new photo path
                    $photo_path = 'uploads/profile-photos/' . $filename;
                    $update_query = "UPDATE users SET profile_photo = ? WHERE id = ?";
                    $stmt = mysqli_prepare($conn, $update_query);
                    mysqli_stmt_bind_param($stmt, "si", $photo_path, $_SESSION['user_id']);

                    if (mysqli_stmt_execute($stmt)) {
                        $_SESSION['profile_photo'] = $photo_path;
                        $message = 'Profile photo updated successfully!';
                        error_log("Content Creator Profile Debug - Photo uploaded and database updated successfully");
                    } else {
                        $error = 'Failed to update profile photo in database.';
                        error_log("Content Creator Profile Debug - Database update failed: " . mysqli_error($conn));
                    }
                } else {
                    $error = 'Failed to upload profile photo.';
                    error_log("Content Creator Profile Debug - File upload failed. Upload error: " . $file['error']);
                    error_log("Content Creator Profile Debug - Source: " . $file['tmp_name'] . " -> Destination: " . $filepath);
                    
                    // Check if destination directory exists and is writable
                    if (!is_dir($upload_dir)) {
                        error_log("Content Creator Profile Debug - Upload directory does not exist: " . $upload_dir);
                    } elseif (!is_writable($upload_dir)) {
                        error_log("Content Creator Profile Debug - Upload directory not writable: " . $upload_dir);
                    }
                }
            }
        } else {
            $error = 'Please select a valid image file.';
        }
    }
}

// Get user data
$user_query = "SELECT * FROM users WHERE id = ?";
$stmt = mysqli_prepare($conn, $user_query);
mysqli_stmt_bind_param($stmt, "i", $_SESSION['user_id']);
mysqli_stmt_execute($stmt);
$user_result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($user_result);

// Update session with latest profile photo if it exists
if ($user && isset($user['profile_photo']) && !empty($user['profile_photo'])) {
    $_SESSION['profile_photo'] = $user['profile_photo'];
}

// Get content creator statistics
$stats_query = "SELECT
    COUNT(*) as total_posts,
    SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as drafts,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
FROM posts WHERE author_id = ?";
$stmt = mysqli_prepare($conn, $stats_query);
mysqli_stmt_bind_param($stmt, "i", $_SESSION['user_id']);
mysqli_stmt_execute($stmt);
$stats_result = mysqli_stmt_get_result($stmt);
$stats = mysqli_fetch_assoc($stats_result);

// Get recent activity
$activity_query = "SELECT * FROM posts WHERE author_id = ? ORDER BY created_at DESC LIMIT 5";
$stmt = mysqli_prepare($conn, $activity_query);
mysqli_stmt_bind_param($stmt, "i", $_SESSION['user_id']);
mysqli_stmt_execute($stmt);
$activity_result = mysqli_stmt_get_result($stmt);
?>

<?php
$page_title = 'Profile';
include 'includes/header.php';
?>
        <div class="p-3 sm:p-4 lg:p-8">
            <div class="mb-8">
                <h1 class="text-2xl lg:text-3xl font-bold text-seait-dark mb-2">Profile Management</h1>
                <p class="text-gray-600">Manage your account information, photo, and content creation settings</p>
            </div>

            <!-- Information Section -->
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 lg:p-6 mb-6 lg:mb-8">
                <div class="flex items-start">
                    <div class="flex-shrink-0">
                        <i class="fas fa-info-circle text-blue-400 text-lg"></i>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-blue-800 mb-2">Profile Management</h3>
                        <div class="text-sm text-blue-700 space-y-1">
                            <p><strong>Account Information:</strong> Update your personal information including name and email.</p>
                            <p><strong>Profile Photo:</strong> Upload and manage your profile picture with real-time preview.</p>
                            <p><strong>Content Creation Statistics:</strong> Track your content creation activity and performance metrics.</p>
                            <p><strong>Security:</strong> Update your password and manage account security settings.</p>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Profile Information -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- Profile Photo Section -->
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <h3 class="text-lg font-semibold text-seait-dark mb-4">Profile Photo</h3>
                        <div class="flex flex-col md:flex-row items-center space-y-4 md:space-y-0 md:space-x-6">
                            <!-- Current Photo Display -->
                            <div class="flex-shrink-0">
                                <div class="relative">
                                    <div id="profilePhotoPreview" class="w-32 h-32 rounded-full border-4 border-seait-orange shadow-lg bg-gray-200 flex items-center justify-center">
                                        <?php if (!empty($user['profile_photo'])): ?>
                                            <img src="../<?php echo $user['profile_photo']; ?>" 
                                                 alt="Profile Photo" 
                                                 class="w-full h-full rounded-full object-cover">
                                        <?php else: ?>
                                            <i class="fas fa-user text-4xl text-gray-400"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div class="absolute -bottom-2 -right-2 bg-seait-orange text-white rounded-full p-2 cursor-pointer hover:bg-orange-600 transition-colors" 
                                         onclick="document.getElementById('photoInput').click()">
                                        <i class="fas fa-camera text-sm"></i>
                                    </div>
                                </div>
                            </div>

                            <!-- Photo Upload Form -->
                            <div class="flex-1">
                                <form method="POST" enctype="multipart/form-data" class="space-y-4">
                                    <input type="hidden" name="action" value="update_photo">
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Upload New Photo</label>
                                        <input type="file" id="photoInput" name="profile_photo" accept="image/*" 
                                               class="hidden" onchange="previewProfilePhoto(this)">
                                        <button type="button" onclick="document.getElementById('photoInput').click()" 
                                                class="w-full px-4 py-2 border-2 border-dashed border-gray-300 rounded-lg hover:border-seait-orange hover:bg-orange-50 transition-colors text-center">
                                            <i class="fas fa-cloud-upload-alt mr-2 text-gray-400"></i>
                                            <span class="text-gray-600">Click to select image</span>
                                        </button>
                                        <p class="text-xs text-gray-500 mt-1">Supported formats: JPEG, PNG, GIF. Max size: 5MB</p>
                                    </div>

                                    <button type="submit" class="bg-seait-orange text-white px-6 py-2 rounded-md hover:bg-orange-600 transition">
                                        <i class="fas fa-upload mr-2"></i>Update Photo
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Basic Information -->
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <h3 class="text-lg font-semibold text-seait-dark mb-4">Basic Information</h3>
                        <form method="POST" class="space-y-4">
                            <input type="hidden" name="action" value="update_profile">

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">First Name</label>
                                    <input type="text" name="first_name" value="<?php echo htmlspecialchars($user['first_name']); ?>" required
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Last Name</label>
                                    <input type="text" name="last_name" value="<?php echo htmlspecialchars($user['last_name']); ?>" required
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Email Address</label>
                                <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Username</label>
                                <input type="text" value="<?php echo htmlspecialchars($user['username']); ?>" disabled
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-100 text-gray-500">
                                <p class="text-xs text-gray-500 mt-1">Username cannot be changed</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Role</label>
                                <input type="text" value="<?php echo ucfirst(str_replace('_', ' ', $user['role'])); ?>" disabled
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-100 text-gray-500">
                            </div>

                            <div class="flex justify-end">
                                <button type="submit" class="bg-seait-orange text-white px-6 py-2 rounded-md hover:bg-orange-600 transition">
                                    <i class="fas fa-save mr-2"></i>Update Profile
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Change Password -->
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <h3 class="text-lg font-semibold text-seait-dark mb-4">Change Password</h3>
                        <form method="POST" class="space-y-4">
                            <input type="hidden" name="action" value="change_password">

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Current Password</label>
                                <input type="password" name="current_password" required
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">New Password</label>
                                <input type="password" name="new_password" required
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                                <p class="text-xs text-gray-500 mt-1">Minimum 6 characters</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Confirm New Password</label>
                                <input type="password" name="confirm_password" required
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                            </div>

                            <div class="flex justify-end">
                                <button type="submit" class="bg-blue-500 text-white px-6 py-2 rounded-md hover:bg-blue-600 transition">
                                    <i class="fas fa-key mr-2"></i>Change Password
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Sidebar -->
                <div class="space-y-6">
                    <!-- Account Statistics -->
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <h3 class="text-lg font-semibold text-seait-dark mb-4">Content Creation Statistics</h3>
                        <div class="space-y-3">
                            <div class="flex justify-between">
                                <span class="text-gray-600">Total Posts</span>
                                <span class="font-semibold"><?php echo $stats['total_posts']; ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Drafts</span>
                                <span class="font-semibold text-gray-600"><?php echo $stats['drafts']; ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Pending Review</span>
                                <span class="font-semibold text-yellow-600"><?php echo $stats['pending']; ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Approved</span>
                                <span class="font-semibold text-green-600"><?php echo $stats['approved']; ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Rejected</span>
                                <span class="font-semibold text-red-600"><?php echo $stats['rejected']; ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Account Information -->
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <h3 class="text-lg font-semibold text-seait-dark mb-4">Account Information</h3>
                        <div class="space-y-3">
                            <div>
                                <span class="text-sm text-gray-600">Member Since</span>
                                <p class="font-semibold"><?php echo date('M d, Y', strtotime($user['created_at'])); ?></p>
                            </div>
                            <div>
                                <span class="text-sm text-gray-600">Last Updated</span>
                                <p class="font-semibold"><?php echo date('M d, Y', strtotime($user['updated_at'])); ?></p>
                            </div>
                            <div>
                                <span class="text-sm text-gray-600">Account Status</span>
                                <p class="font-semibold text-green-600">Active</p>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Activity -->
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <h3 class="text-lg font-semibold text-seait-dark mb-4">Recent Activity</h3>
                        <?php if (mysqli_num_rows($activity_result) > 0): ?>
                            <div class="space-y-3">
                                <?php while($activity = mysqli_fetch_assoc($activity_result)): ?>
                                <div class="border-l-2 border-seait-orange pl-3">
                                    <p class="text-sm font-medium"><?php echo htmlspecialchars($activity['title']); ?></p>
                                    <p class="text-xs text-gray-500">
                                        <?php echo ucfirst($activity['type']); ?> •
                                        <?php echo date('M d, Y', strtotime($activity['created_at'])); ?>
                                    </p>
                                    <span class="inline-block px-2 py-1 text-xs rounded-full mt-1 <?php
                                        echo $activity['status'] == 'approved' ? 'bg-green-100 text-green-800' :
                                            ($activity['status'] == 'pending' ? 'bg-yellow-100 text-yellow-800' :
                                            ($activity['status'] == 'rejected' ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-800'));
                                    ?>">
                                        <?php echo ucfirst($activity['status']); ?>
                                    </span>
                                </div>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-gray-500 text-sm">No recent activity</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>
</div>

<script>
    // Profile photo preview functionality
    function previewProfilePhoto(input) {
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const previewDiv = document.getElementById('profilePhotoPreview');
                // Clear the div and add the new image
                previewDiv.innerHTML = `<img src="${e.target.result}" alt="Profile Photo Preview" class="w-full h-full rounded-full object-cover">`;
            };
            reader.readAsDataURL(input.files[0]);
        }
    }

    // Add click event to the camera icon for easier photo selection
    document.addEventListener('DOMContentLoaded', function() {
        const cameraIcon = document.querySelector('.fa-camera').parentElement;
        cameraIcon.addEventListener('click', function() {
            document.getElementById('photoInput').click();
        });
    });
</script>

</body>
</html>
