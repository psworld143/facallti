<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

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
    }
}

// Get user data
$user_query = "SELECT * FROM users WHERE id = ?";
$stmt = mysqli_prepare($conn, $user_query);
mysqli_stmt_bind_param($stmt, "i", $_SESSION['user_id']);
mysqli_stmt_execute($stmt);
$user_result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($user_result);

// Get user statistics
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

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - SEAIT Content Creator</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'seait-orange': '#FF6B35',
                        'seait-dark': '#2C3E50'
                    },
                    fontFamily: {
                        'poppins': ['Poppins', 'sans-serif']
                    }
                }
            }
        }
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Navigation -->
    <nav class="bg-white fixed top-0 left-0 right-0 z-50 shadow-sm">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between items-center py-4">
                <div class="flex items-center space-x-4">
                    <img src="../assets/images/seait-logo.png" alt="SEAIT Logo" class="h-10 w-auto">
                    <div>
                        <h1 class="text-xl font-bold text-seait-dark">SEAIT Content Creator</h1>
                        <p class="text-sm text-gray-600">Welcome, <?php echo $_SESSION['first_name']; ?></p>
                    </div>
                </div>

                <div class="flex items-center space-x-4">
                    <a href="../index.php" class="text-seait-dark hover:text-seait-orange transition">
                        <i class="fas fa-home mr-2"></i><span class="hidden sm:inline">View Site</span>
                    </a>
                    <a href="logout.php" class="bg-red-500 text-white px-4 py-2 rounded hover:bg-red-600 transition">
                        <i class="fas fa-sign-out-alt mr-2"></i><span class="hidden sm:inline">Logout</span>
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="flex min-h-screen pt-16">
        <!-- Sidebar -->
        <?php include 'includes/sidebar.php'; ?>

        <!-- Main Content -->
        <div class="flex-1 lg:ml-72 overflow-y-auto h-screen">
            <div class="p-4 lg:p-8">
                <div class="mb-8">
                    <h1 class="text-2xl lg:text-3xl font-bold text-seait-dark mb-2">Profile</h1>
                    <p class="text-gray-600">Manage your account information and settings</p>
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
                                <p><strong>Account Information:</strong> View and update your personal information including name, email, and contact details.</p>
                                <p><strong>Content Statistics:</strong> Track your content creation activity with detailed statistics and recent activity.</p>
                                <p><strong>Security:</strong> Update your password and manage account security settings.</p>
                                <p><strong>Activity:</strong> View your recent content creation activity and track your contributions to the website.</p>
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
                        <h3 class="text-lg font-semibold text-seait-dark mb-4">Account Statistics</h3>
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
    </div>
</body>
</html>