<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/id_encryption.php';

// Check if user is logged in and has head role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'head') {
    header('Location: ../index.php');
    exit();
}

$message = '';
$error = '';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_profile':
                $first_name = mysqli_real_escape_string($conn, $_POST['first_name']);
                $last_name = mysqli_real_escape_string($conn, $_POST['last_name']);
                $email = mysqli_real_escape_string($conn, $_POST['email']);
                $phone = mysqli_real_escape_string($conn, $_POST['phone']);
                $department = mysqli_real_escape_string($conn, $_POST['department']);
                $position = mysqli_real_escape_string($conn, $_POST['position']);

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
                        // Update users table
                        $update_user_query = "UPDATE users SET first_name = ?, last_name = ?, email = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
                        $stmt = mysqli_prepare($conn, $update_user_query);
                        mysqli_stmt_bind_param($stmt, "sssi", $first_name, $last_name, $email, $_SESSION['user_id']);

                        if (mysqli_stmt_execute($stmt)) {
                            // Update heads table
                            $update_head_query = "UPDATE heads SET department = ?, position = ?, phone = ?, updated_at = CURRENT_TIMESTAMP WHERE user_id = ?";
                            $stmt = mysqli_prepare($conn, $update_head_query);
                            mysqli_stmt_bind_param($stmt, "sssi", $department, $position, $phone, $_SESSION['user_id']);

                            if (mysqli_stmt_execute($stmt)) {
                                // Update session data
                                $_SESSION['first_name'] = $first_name;
                                $_SESSION['last_name'] = $last_name;
                                $_SESSION['email'] = $email;

                                $message = 'Profile updated successfully!';
                            } else {
                                $error = 'Error updating head information: ' . mysqli_error($conn);
                            }
                        } else {
                            $error = 'Error updating user information: ' . mysqli_error($conn);
                        }
                    }
                }
                break;

            case 'change_password':
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
                            $update_query = "UPDATE users SET password = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
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
                break;

            case 'update_photo':
                // Handle profile photo upload
                error_log("Heads Profile Debug - Photo upload started");
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
                        $upload_dir = realpath(dirname(__FILE__) . '/../uploads/profile-photos/') . '/';
                        error_log("Heads Profile Debug - Upload directory: " . $upload_dir);
                        
                        // Ensure directory exists with proper permissions
                        if (!is_dir($upload_dir)) {
                            if (!mkdir($upload_dir, 0755, true)) {
                                $error = 'Failed to create upload directory. Please check permissions.';
                                error_log("Heads Profile Debug - Failed to create directory: " . $upload_dir);
                            }
                        }
                        
                        // Check if directory is writable
                        if (!is_writable($upload_dir)) {
                            $error = 'Upload directory is not writable. Please check permissions.';
                            error_log("Heads Profile Debug - Directory not writable: " . $upload_dir);
                        }
                        
                        // Check if directory is writable
                        if (!is_writable($upload_dir)) {
                            $error = 'Upload directory is not writable. Please check permissions.';
                            error_log("Heads Profile Debug - Directory not writable: " . $upload_dir);
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
                                error_log("Heads Profile Debug - Photo uploaded and database updated successfully");
                            } else {
                                $error = 'Failed to update profile photo in database.';
                                error_log("Heads Profile Debug - Database update failed: " . mysqli_error($conn));
                            }
                        } else {
                            $error = 'Failed to upload profile photo.';
                            error_log("Heads Profile Debug - File upload failed. Upload error: " . $file['error']);
                            error_log("Heads Profile Debug - Source: " . $file['tmp_name'] . " -> Destination: " . $filepath);
                        }
                    }
                } else {
                    $error = 'Please select a valid image file.';
                }
                break;
        }
    }
}

// Get user data
$user_query = "SELECT u.*, h.department, h.position, h.phone as head_phone 
               FROM users u 
               LEFT JOIN heads h ON u.id = h.user_id 
               WHERE u.id = ?";
$stmt = mysqli_prepare($conn, $user_query);
mysqli_stmt_bind_param($stmt, "i", $_SESSION['user_id']);
mysqli_stmt_execute($stmt);
$user_result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($user_result);

// Update session with latest profile photo if it exists
if ($user && isset($user['profile_photo']) && !empty($user['profile_photo'])) {
    $_SESSION['profile_photo'] = $user['profile_photo'];
}

// Get head statistics (placeholder for now)
$stats = [
    'total_teachers' => 0,
    'evaluations_pending' => 0,
    'evaluations_completed' => 0,
    'leave_requests' => 0,
    'leave_approved' => 0
];

// Get recent activity (placeholder for now)
$activity_result = null;
?>

<?php
$page_title = 'Profile';
include 'includes/header.php';
?>

<script>
    // Log current profile state on page load
    console.log('Profile page loaded');
    console.log('Current user ID:', <?php echo json_encode($_SESSION['user_id'] ?? 'Not set'); ?>);
    console.log('Current profile photo:', <?php echo json_encode($_SESSION['profile_photo'] ?? 'Not set'); ?>);
    console.log('PHP Message:', <?php echo json_encode($message ?? ''); ?>);
    console.log('PHP Error:', <?php echo json_encode($error ?? ''); ?>);
    
    // Test upload directory accessibility
    console.log('Upload directory test:', '<?php echo realpath(dirname(__FILE__) . '/../uploads/profile-photos/'); ?>');
    console.log('Directory exists:', <?php echo is_dir(realpath(dirname(__FILE__) . '/../uploads/profile-photos/')) ? 'true' : 'false'; ?>);
    console.log('Directory writable:', <?php echo is_writable(realpath(dirname(__FILE__) . '/../uploads/profile-photos/')) ? 'true' : 'false'; ?>);
</script>
        <div class="p-3 sm:p-4 lg:p-8">
            <div class="mb-8">
                <h1 class="text-2xl lg:text-3xl font-bold text-seait-dark mb-2">Profile Management</h1>
                <p class="text-gray-600">Manage your account information, photo, and department head settings</p>
            </div>



            <?php if ($message): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                    <?php echo $message; ?>
                </div>
                <script>console.log('Profile Update Success:', <?php echo json_encode($message); ?>);</script>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                    <?php echo $error; ?>
                </div>
                <script>
                    console.log('Profile Update Error:', <?php echo json_encode($error); ?>);
                    console.log('Error occurred in:', '<?php echo basename($_SERVER['PHP_SELF']); ?>');
                    console.log('Current timestamp:', new Date().toISOString());
                </script>
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
                                <form method="POST" enctype="multipart/form-data" class="space-y-4" id="photoForm">
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
                                <label class="block text-sm font-medium text-gray-700 mb-2">Phone Number</label>
                                <input type="tel" name="phone" value="<?php echo htmlspecialchars($user['head_phone'] ?? ''); ?>"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Department</label>
                                    <input type="text" name="department" value="<?php echo htmlspecialchars($user['department'] ?? ''); ?>" required
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Position</label>
                                    <input type="text" name="position" value="<?php echo htmlspecialchars($user['position'] ?? ''); ?>" required
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                                </div>
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
                        
                        <script>
                            // Add form submission logging for profile update
                            const profileForm = document.querySelector('form input[name="action"][value="update_profile"]').closest('form');
                            if (profileForm) {
                                profileForm.addEventListener('submit', function(e) {
                                    console.log('Profile update form submission started');
                                    const formData = new FormData(this);
                                    console.log('Form data:', Object.fromEntries(formData));
                                });
                            }
                        </script>
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
                                <button type="submit" class="bg-seait-orange text-white px-6 py-2 rounded-md hover:bg-orange-600 transition">
                                    <i class="fas fa-key mr-2"></i>Change Password
                                </button>
                            </div>
                        </form>
                        
                        <script>
                            // Add form submission logging for password change
                            const passwordForm = document.querySelector('form input[name="action"][value="change_password"]').closest('form');
                            if (passwordForm) {
                                passwordForm.addEventListener('submit', function(e) {
                                    console.log('Password change form submission started');
                                    const formData = new FormData(this);
                                    console.log('Form data keys:', Array.from(formData.keys()));
                                });
                            }
                        </script>
                        </form>
                    </div>
                </div>

                <!-- Sidebar -->
                <div class="space-y-6">
                    <!-- Account Statistics -->
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <h3 class="text-lg font-semibold text-seait-dark mb-4">Department Head Statistics</h3>
                        <div class="space-y-3">
                            <div class="flex justify-between">
                                <span class="text-gray-600 text-base">Total Teachers</span>
                                <span class="font-semibold text-base"><?php echo $stats['total_teachers']; ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600 text-base">Evaluations Pending</span>
                                <span class="font-semibold text-base text-yellow-600"><?php echo $stats['evaluations_pending']; ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600 text-base">Evaluations Completed</span>
                                <span class="font-semibold text-base text-green-600"><?php echo $stats['evaluations_completed']; ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600 text-base">Leave Requests</span>
                                <span class="font-semibold text-base text-blue-600"><?php echo $stats['leave_requests']; ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600 text-base">Leave Approved</span>
                                <span class="font-semibold text-base text-green-600"><?php echo $stats['leave_approved']; ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Account Information -->
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <h3 class="text-lg font-semibold text-seait-dark mb-4">Account Information</h3>
                        <div class="space-y-3">
                            <div>
                                <span class="text-base text-gray-600">Member Since</span>
                                <p class="font-semibold text-base"><?php echo date('M d, Y', strtotime($user['created_at'])); ?></p>
                            </div>
                            <div>
                                <span class="text-base text-gray-600">Last Updated</span>
                                <p class="font-semibold text-base"><?php echo date('M d, Y', strtotime($user['updated_at'])); ?></p>
                            </div>
                            <div>
                                <span class="text-base text-gray-600">Account Status</span>
                                <p class="font-semibold text-base text-green-600">Active</p>
                            </div>
                            <div>
                                <span class="text-base text-gray-600">Department</span>
                                <p class="font-semibold text-base"><?php echo htmlspecialchars($user['department'] ?? 'Not set'); ?></p>
                            </div>
                            <div>
                                <span class="text-base text-gray-600">Position</span>
                                <p class="font-semibold text-base"><?php echo htmlspecialchars($user['position'] ?? 'Not set'); ?></p>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Activity -->
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <h3 class="text-lg font-semibold text-seait-dark mb-4">Recent Activity</h3>
                        <?php if ($activity_result && mysqli_num_rows($activity_result) > 0): ?>
                            <div class="space-y-3">
                                <?php while($activity = mysqli_fetch_assoc($activity_result)): ?>
                                <div class="border-l-2 border-seait-orange pl-3">
                                    <p class="text-base font-medium"><?php echo htmlspecialchars($activity['title']); ?></p>
                                    <p class="text-sm text-gray-500">
                                        <?php echo ucfirst($activity['type']); ?> •
                                        <?php echo date('M d, Y', strtotime($activity['created_at'])); ?>
                                    </p>
                                    <span class="inline-block px-2 py-1 text-sm rounded-full mt-1 <?php
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
                            <p class="text-gray-500 text-base">No recent activity</p>
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
        console.log('Profile photo preview function called');
        if (input.files && input.files[0]) {
            console.log('File selected:', input.files[0].name, 'Size:', input.files[0].size, 'Type:', input.files[0].type);
            const reader = new FileReader();
            reader.onload = function(e) {
                console.log('File preview loaded successfully');
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
        if (cameraIcon) {
            cameraIcon.addEventListener('click', function() {
                document.getElementById('photoInput').click();
            });
        }
        
            // Add form validation for photo upload
        const photoForm = document.getElementById('photoForm');
        if (photoForm) {
            photoForm.addEventListener('submit', function(e) {
                console.log('Photo form submission started');
                const fileInput = document.getElementById('photoInput');
                if (fileInput.files.length === 0) {
                    e.preventDefault();
                    console.log('Photo form submission blocked: No file selected');
                    alert('Please select a file before submitting');
                } else {
                    console.log('Photo form submission proceeding with file:', fileInput.files[0].name);
                }
            });
        }
    });
</script>

</body>
</html>

