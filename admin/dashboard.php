<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

check_admin();

// Get statistics
$users_query = "SELECT COUNT(*) as total FROM users";
$users_result = mysqli_query($conn, $users_query);
$total_users = mysqli_fetch_assoc($users_result)['total'];

$posts_query = "SELECT COUNT(*) as total FROM posts";
$posts_result = mysqli_query($conn, $posts_query);
$total_posts = mysqli_fetch_assoc($posts_result)['total'];

$pending_query = "SELECT COUNT(*) as total FROM posts WHERE status = 'pending'";
$pending_result = mysqli_query($conn, $pending_query);
$pending_posts = mysqli_fetch_assoc($pending_result)['total'];

$programs_query = "SELECT COUNT(*) as total FROM academic_programs";
$programs_result = mysqli_query($conn, $programs_query);
$total_programs = mysqli_fetch_assoc($programs_result)['total'];

// Get recent posts
$recent_posts_query = "SELECT p.*, u.first_name, u.last_name FROM posts p
                       JOIN users u ON p.author_id = u.id
                       ORDER BY p.created_at DESC LIMIT 5";
$recent_posts_result = mysqli_query($conn, $recent_posts_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - SEAIT</title>
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
                <h1 class="text-3xl font-bold text-seait-dark mb-2">Dashboard</h1>
                <p class="text-gray-600">Overview of SEAIT website management</p>
            </div>

            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="bg-white p-6 rounded-lg shadow-md">
                    <div class="flex items-center">
                        <div class="p-3 bg-blue-100 rounded-full">
                            <i class="fas fa-users text-blue-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Total Users</p>
                            <p class="text-2xl font-bold text-gray-900"><?php echo $total_users; ?></p>
                        </div>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-lg shadow-md">
                    <div class="flex items-center">
                        <div class="p-3 bg-green-100 rounded-full">
                            <i class="fas fa-newspaper text-green-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Total Posts</p>
                            <p class="text-2xl font-bold text-gray-900"><?php echo $total_posts; ?></p>
                        </div>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-lg shadow-md">
                    <div class="flex items-center">
                        <div class="p-3 bg-yellow-100 rounded-full">
                            <i class="fas fa-clock text-yellow-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Pending Posts</p>
                            <p class="text-2xl font-bold text-gray-900"><?php echo $pending_posts; ?></p>
                        </div>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-lg shadow-md">
                    <div class="flex items-center">
                        <div class="p-3 bg-purple-100 rounded-full">
                            <i class="fas fa-graduation-cap text-purple-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Academic Programs</p>
                            <p class="text-2xl font-bold text-gray-900"><?php echo $total_programs; ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <div class="bg-white p-6 rounded-lg shadow-md">
                    <h3 class="text-lg font-semibold mb-4">Quick Actions</h3>
                    <div class="space-y-3">
                        <a href="users.php?action=add" class="flex items-center p-3 bg-gray-50 rounded hover:bg-gray-100 transition">
                            <i class="fas fa-user-plus text-seait-orange mr-3"></i>
                            <span>Add New User</span>
                        </a>
                        <a href="posts.php?action=add" class="flex items-center p-3 bg-gray-50 rounded hover:bg-gray-100 transition">
                            <i class="fas fa-plus text-seait-orange mr-3"></i>
                            <span>Create New Post</span>
                        </a>
                        <a href="programs.php?action=add" class="flex items-center p-3 bg-gray-50 rounded hover:bg-gray-100 transition">
                            <i class="fas fa-graduation-cap text-seait-orange mr-3"></i>
                            <span>Add Academic Program</span>
                        </a>
                        <a href="settings.php" class="flex items-center p-3 bg-gray-50 rounded hover:bg-gray-100 transition">
                            <i class="fas fa-cog text-seait-orange mr-3"></i>
                            <span>Website Settings</span>
                        </a>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-lg shadow-md">
                    <h3 class="text-lg font-semibold mb-4">Recent Posts</h3>
                    <div class="space-y-3">
                        <?php while($post = mysqli_fetch_assoc($recent_posts_result)): ?>
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded">
                            <div>
                                <p class="font-medium"><?php echo $post['title']; ?></p>
                                <p class="text-sm text-gray-600">
                                    by <?php echo $post['first_name'] . ' ' . $post['last_name']; ?> •
                                    <?php echo date('M d, Y', strtotime($post['created_at'])); ?>
                                </p>
                            </div>
                            <span class="px-2 py-1 text-xs rounded <?php
                                echo $post['status'] == 'approved' ? 'bg-green-100 text-green-800' :
                                    ($post['status'] == 'pending' ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-800');
                            ?>">
                                <?php echo ucfirst($post['status']); ?>
                            </span>
                        </div>
                        <?php endwhile; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>