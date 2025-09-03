<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

check_admin();

// Get selected semester from form
$selected_semester = isset($_GET['semester']) ? (int)$_GET['semester'] : 0;
$selected_year = isset($_GET['year']) ? $_GET['year'] : date('Y');

// Get available semesters for dropdown
$semesters_query = "SELECT id, name, academic_year FROM semesters WHERE status = 'active' ORDER BY start_date DESC";
$semesters_result = mysqli_query($conn, $semesters_query);

// Get available academic years
$years_query = "SELECT DISTINCT academic_year FROM semesters WHERE status = 'active' ORDER BY academic_year DESC";
$years_result = mysqli_query($conn, $years_query);

// Base date filters
$current_month = date('Y-m');
$current_year = date('Y');

// Semester-specific date range
$semester_start_date = null;
$semester_end_date = null;
if ($selected_semester > 0) {
    $semester_dates_query = "SELECT start_date, end_date FROM semesters WHERE id = ?";
    $stmt = mysqli_prepare($conn, $semester_dates_query);
    mysqli_stmt_bind_param($stmt, "i", $selected_semester);
    mysqli_stmt_execute($stmt);
    $semester_dates_result = mysqli_stmt_get_result($stmt);
    if ($semester_row = mysqli_fetch_assoc($semester_dates_result)) {
        $semester_start_date = $semester_row['start_date'];
        $semester_end_date = $semester_row['end_date'];
    }
}

// User statistics
$total_users_query = "SELECT COUNT(*) as total FROM users";
$total_users_result = mysqli_query($conn, $total_users_query);
$total_users = mysqli_fetch_assoc($total_users_result)['total'];

$new_users_this_month_query = "SELECT COUNT(*) as total FROM users WHERE DATE_FORMAT(created_at, '%Y-%m') = ?";
$stmt = mysqli_prepare($conn, $new_users_this_month_query);
mysqli_stmt_bind_param($stmt, "s", $current_month);
mysqli_stmt_execute($stmt);
$new_users_result = mysqli_stmt_get_result($stmt);
$new_users_this_month = mysqli_fetch_assoc($new_users_result)['total'];

// Semester-specific user statistics
$semester_users = 0;
if ($semester_start_date && $semester_end_date) {
    $semester_users_query = "SELECT COUNT(*) as total FROM users WHERE created_at BETWEEN ? AND ?";
    $stmt = mysqli_prepare($conn, $semester_users_query);
    mysqli_stmt_bind_param($stmt, "ss", $semester_start_date, $semester_end_date);
    mysqli_stmt_execute($stmt);
    $semester_users_result = mysqli_stmt_get_result($stmt);
    $semester_users = mysqli_fetch_assoc($semester_users_result)['total'];
}

// Post statistics
$total_posts_query = "SELECT COUNT(*) as total FROM posts";
$total_posts_result = mysqli_query($conn, $total_posts_query);
$total_posts = mysqli_fetch_assoc($total_posts_result)['total'];

$approved_posts_query = "SELECT COUNT(*) as total FROM posts WHERE status = 'approved'";
$approved_posts_result = mysqli_query($conn, $approved_posts_query);
$approved_posts = mysqli_fetch_assoc($approved_posts_result)['total'];

$pending_posts_query = "SELECT COUNT(*) as total FROM posts WHERE status = 'pending'";
$pending_posts_result = mysqli_query($conn, $pending_posts_query);
$pending_posts = mysqli_fetch_assoc($pending_posts_result)['total'];

$posts_this_month_query = "SELECT COUNT(*) as total FROM posts WHERE DATE_FORMAT(created_at, '%Y-%m') = ?";
$stmt = mysqli_prepare($conn, $posts_this_month_query);
mysqli_stmt_bind_param($stmt, "s", $current_month);
mysqli_stmt_execute($stmt);
$posts_this_month_result = mysqli_stmt_get_result($stmt);
$posts_this_month = mysqli_fetch_assoc($posts_this_month_result)['total'];

// Semester-specific post statistics
$semester_posts = 0;
if ($semester_start_date && $semester_end_date) {
    $semester_posts_query = "SELECT COUNT(*) as total FROM posts WHERE created_at BETWEEN ? AND ?";
    $stmt = mysqli_prepare($conn, $semester_posts_query);
    mysqli_stmt_bind_param($stmt, "ss", $semester_start_date, $semester_end_date);
    mysqli_stmt_execute($stmt);
    $semester_posts_result = mysqli_stmt_get_result($stmt);
    $semester_posts = mysqli_fetch_assoc($semester_posts_result)['total'];
}

// Program statistics
$total_programs_query = "SELECT COUNT(*) as total FROM academic_programs WHERE is_active = 1";
$total_programs_result = mysqli_query($conn, $total_programs_query);
$total_programs = mysqli_fetch_assoc($total_programs_result)['total'];

// Faculty statistics
$total_faculty_query = "SELECT COUNT(*) as total FROM faculty WHERE is_active = 1";
$total_faculty_result = mysqli_query($conn, $total_faculty_query);
$total_faculty = mysqli_fetch_assoc($total_faculty_result)['total'];

// Inquiry statistics
$total_inquiries_query = "SELECT COUNT(*) as total FROM user_inquiries";
$total_inquiries_result = mysqli_query($conn, $total_inquiries_query);
$total_inquiries = mysqli_fetch_assoc($total_inquiries_result)['total'];

$unresolved_inquiries_query = "SELECT COUNT(*) as total FROM user_inquiries WHERE is_resolved = 0";
$unresolved_inquiries_result = mysqli_query($conn, $unresolved_inquiries_query);
$unresolved_inquiries = mysqli_fetch_assoc($unresolved_inquiries_result)['total'];

// Semester-specific inquiry statistics
$semester_inquiries = 0;
if ($semester_start_date && $semester_end_date) {
    $semester_inquiries_query = "SELECT COUNT(*) as total FROM user_inquiries WHERE created_at BETWEEN ? AND ?";
    $stmt = mysqli_prepare($conn, $semester_inquiries_query);
    mysqli_stmt_bind_param($stmt, "ss", $semester_start_date, $semester_end_date);
    mysqli_stmt_execute($stmt);
    $semester_inquiries_result = mysqli_stmt_get_result($stmt);
    $semester_inquiries = mysqli_fetch_assoc($semester_inquiries_result)['total'];
}

// Student statistics (from students table)
$total_students_query = "SELECT COUNT(*) as total FROM students WHERE status = 'active'";
$total_students_result = mysqli_query($conn, $total_students_query);
$total_students = mysqli_fetch_assoc($total_students_result)['total'];

// Semester-specific student statistics
$semester_students = 0;
if ($semester_start_date && $semester_end_date) {
    $semester_students_query = "SELECT COUNT(*) as total FROM students WHERE status = 'active' AND created_at BETWEEN ? AND ?";
    $stmt = mysqli_prepare($conn, $semester_students_query);
    mysqli_stmt_bind_param($stmt, "ss", $semester_start_date, $semester_end_date);
    mysqli_stmt_execute($stmt);
    $semester_students_result = mysqli_stmt_get_result($stmt);
    $semester_students = mysqli_fetch_assoc($semester_students_result)['total'];
}

// Evaluation statistics (from IntelliEVal system)
$total_evaluations = 0;
$semester_evaluations = 0;
if (mysqli_query($conn, "SHOW TABLES LIKE 'evaluation_sessions'")) {
    $total_evaluations_query = "SELECT COUNT(*) as total FROM evaluation_sessions";
    $total_evaluations_result = mysqli_query($conn, $total_evaluations_query);
    if ($total_evaluations_result) {
        $total_evaluations = mysqli_fetch_assoc($total_evaluations_result)['total'];
    }

    if ($semester_start_date && $semester_end_date) {
        $semester_evaluations_query = "SELECT COUNT(*) as total FROM evaluation_sessions WHERE created_at BETWEEN ? AND ?";
        $stmt = mysqli_prepare($conn, $semester_evaluations_query);
        mysqli_stmt_bind_param($stmt, "ss", $semester_start_date, $semester_end_date);
        mysqli_stmt_execute($stmt);
        $semester_evaluations_result = mysqli_stmt_get_result($stmt);
        $semester_evaluations = mysqli_fetch_assoc($semester_evaluations_result)['total'];
    }
}

// Get recent activity with semester filter
$recent_posts_where = "";
$recent_posts_params = [];
if ($semester_start_date && $semester_end_date) {
    $recent_posts_where = "WHERE p.created_at BETWEEN ? AND ?";
    $recent_posts_params = [$semester_start_date, $semester_end_date];
}

$recent_posts_query = "SELECT p.*, u.first_name, u.last_name FROM posts p
                       JOIN users u ON p.author_id = u.id
                       $recent_posts_where
                       ORDER BY p.created_at DESC LIMIT 10";
if (!empty($recent_posts_params)) {
    $stmt = mysqli_prepare($conn, $recent_posts_query);
    mysqli_stmt_bind_param($stmt, "ss", $recent_posts_params[0], $recent_posts_params[1]);
    mysqli_stmt_execute($stmt);
    $recent_posts_result = mysqli_stmt_get_result($stmt);
} else {
    $recent_posts_result = mysqli_query($conn, $recent_posts_query);
}

// Get recent users with semester filter
$recent_users_where = "";
$recent_users_params = [];
if ($semester_start_date && $semester_end_date) {
    $recent_users_where = "WHERE created_at BETWEEN ? AND ?";
    $recent_users_params = [$semester_start_date, $semester_end_date];
}

$recent_users_query = "SELECT * FROM users $recent_users_where ORDER BY created_at DESC LIMIT 10";
if (!empty($recent_users_params)) {
    $stmt = mysqli_prepare($conn, $recent_users_query);
    mysqli_stmt_bind_param($stmt, "ss", $recent_users_params[0], $recent_users_params[1]);
    mysqli_stmt_execute($stmt);
    $recent_users_result = mysqli_stmt_get_result($stmt);
} else {
    $recent_users_result = mysqli_query($conn, $recent_users_query);
}

// Get post statistics by type with semester filter
$posts_by_type_where = "";
$posts_by_type_params = [];
if ($semester_start_date && $semester_end_date) {
    $posts_by_type_where = "WHERE p.created_at BETWEEN ? AND ?";
    $posts_by_type_params = [$semester_start_date, $semester_end_date];
}

$posts_by_type_query = "SELECT p.type, COUNT(*) as count FROM posts p $posts_by_type_where GROUP BY p.type";
if (!empty($posts_by_type_params)) {
    $stmt = mysqli_prepare($conn, $posts_by_type_query);
    mysqli_stmt_bind_param($stmt, "ss", $posts_by_type_params[0], $posts_by_type_params[1]);
    mysqli_stmt_execute($stmt);
    $posts_by_type_result = mysqli_stmt_get_result($stmt);
} else {
    $posts_by_type_result = mysqli_query($conn, $posts_by_type_query);
}

// Prepare posts by type data for charts
$posts_labels = [];
$posts_data = [];
if ($posts_by_type_result) {
    while($row = mysqli_fetch_assoc($posts_by_type_result)) {
        if (!empty($row['type']) && $row['count'] > 0) {
            $posts_labels[] = ucfirst($row['type']);
            $posts_data[] = $row['count'];
        }
    }
}

// Get user statistics by role with semester filter
$users_by_role_where = "";
$users_by_role_params = [];
if ($semester_start_date && $semester_end_date) {
    $users_by_role_where = "WHERE created_at BETWEEN ? AND ?";
    $users_by_role_params = [$semester_start_date, $semester_end_date];
}

$users_by_role_query = "SELECT role, COUNT(*) as count FROM users $users_by_role_where GROUP BY role";
if (!empty($users_by_role_params)) {
    $stmt = mysqli_prepare($conn, $users_by_role_query);
    mysqli_stmt_bind_param($stmt, "ss", $users_by_role_params[0], $users_by_role_params[1]);
    mysqli_stmt_execute($stmt);
    $users_by_role_result = mysqli_stmt_get_result($stmt);
} else {
    $users_by_role_result = mysqli_query($conn, $users_by_role_query);
}

// Prepare users by role data for charts
$users_labels = [];
$users_data = [];
if ($users_by_role_result) {
    while($row = mysqli_fetch_assoc($users_by_role_result)) {
        if (!empty($row['role']) && $row['count'] > 0) {
            $users_labels[] = ucfirst(str_replace('_', ' ', $row['role']));
            $users_data[] = $row['count'];
        }
    }
}

// Get monthly activity data for the selected semester
$monthly_activity_data = [];
$monthly_activity_labels = [];
if ($semester_start_date && $semester_end_date) {
    $monthly_activity_query = "SELECT
        DATE_FORMAT(created_at, '%Y-%m') as month,
        COUNT(*) as count
        FROM posts
        WHERE created_at BETWEEN ? AND ?
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY month";
    $stmt = mysqli_prepare($conn, $monthly_activity_query);
    mysqli_stmt_bind_param($stmt, "ss", $semester_start_date, $semester_end_date);
    mysqli_stmt_execute($stmt);
    $monthly_activity_result = mysqli_stmt_get_result($stmt);

    while($row = mysqli_fetch_assoc($monthly_activity_result)) {
        $monthly_activity_labels[] = date('M Y', strtotime($row['month'] . '-01'));
        $monthly_activity_data[] = $row['count'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports & Analytics - Admin Dashboard</title>
    <link rel="icon" type="image/png" href="../assets/images/seait-logo.png">
    <link rel="shortcut icon" type="image/png" href="../assets/images/seait-logo.png">
    <link rel="apple-touch-icon" type="image/png" href="../assets/images/seait-logo.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
    <style>
        /* Prevent layout shifts - only for chart containers in reports page */
        .reports-page .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
        }
        /* Only affect canvas elements within chart containers */
        .reports-page .chart-container canvas {
            max-width: 100% !important;
            height: auto !important;
        }
        /* Only affect white background cards that contain charts */
        .reports-page .bg-white .chart-container {
            min-height: 350px;
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-50 reports-page">
    <?php include 'includes/admin-header.php'; ?>

    
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-seait-dark mb-2">Reports & Analytics</h1>
                <p class="text-gray-600">Website statistics and performance insights</p>
            </div>

            <!-- Semester Filter Form -->
            <div class="bg-white p-6 rounded-lg shadow-md mb-8">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Filter by Semester</h3>
                <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Semester</label>
                        <select name="semester" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                            <option value="">All Semesters</option>
                            <?php while ($semester = mysqli_fetch_assoc($semesters_result)): ?>
                            <option value="<?php echo $semester['id']; ?>" <?php echo $selected_semester == $semester['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($semester['name'] . ' (' . $semester['academic_year'] . ')'); ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Academic Year</label>
                        <select name="year" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                            <?php while ($year = mysqli_fetch_assoc($years_result)): ?>
                            <option value="<?php echo $year['academic_year']; ?>" <?php echo $selected_year == $year['academic_year'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($year['academic_year']); ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="flex items-end">
                        <button type="submit" class="w-full bg-seait-orange text-white px-4 py-2 rounded-md hover:bg-orange-600 transition-colors">
                            <i class="fas fa-filter mr-2"></i>Apply Filter
                        </button>
                    </div>
                </form>

                <?php if ($selected_semester > 0): ?>
                <div class="mt-4 p-3 bg-blue-50 rounded-md">
                    <p class="text-sm text-blue-800">
                        <i class="fas fa-info-circle mr-2"></i>
                        Showing data for selected semester period.
                        <?php if ($semester_start_date && $semester_end_date): ?>
                        Period: <?php echo date('M d, Y', strtotime($semester_start_date)); ?> - <?php echo date('M d, Y', strtotime($semester_end_date)); ?>
                        <?php endif; ?>
                    </p>
                </div>
                <?php endif; ?>
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
                            <?php if ($selected_semester > 0): ?>
                            <p class="text-xs text-blue-600"><?php echo $semester_users; ?> in selected semester</p>
                            <?php else: ?>
                            <p class="text-xs text-green-600">+<?php echo $new_users_this_month; ?> this month</p>
                            <?php endif; ?>
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
                            <?php if ($selected_semester > 0): ?>
                            <p class="text-xs text-green-600"><?php echo $semester_posts; ?> in selected semester</p>
                            <?php else: ?>
                            <p class="text-xs text-green-600">+<?php echo $posts_this_month; ?> this month</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-lg shadow-md">
                    <div class="flex items-center">
                        <div class="p-3 bg-purple-100 rounded-full">
                            <i class="fas fa-graduation-cap text-purple-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Active Students</p>
                            <p class="text-2xl font-bold text-gray-900"><?php echo $total_students; ?></p>
                            <?php if ($selected_semester > 0): ?>
                            <p class="text-xs text-purple-600"><?php echo $semester_students; ?> in selected semester</p>
                            <?php else: ?>
                            <p class="text-xs text-purple-600">Active Programs: <?php echo $total_programs; ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-lg shadow-md">
                    <div class="flex items-center">
                        <div class="p-3 bg-orange-100 rounded-full">
                            <i class="fas fa-comments text-orange-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Total Inquiries</p>
                            <p class="text-2xl font-bold text-gray-900"><?php echo $total_inquiries; ?></p>
                            <?php if ($selected_semester > 0): ?>
                            <p class="text-xs text-orange-600"><?php echo $semester_inquiries; ?> in selected semester</p>
                            <?php else: ?>
                            <p class="text-xs text-red-600"><?php echo $unresolved_inquiries; ?> unresolved</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Additional Semester Statistics -->
            <?php if ($selected_semester > 0): ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
                <div class="bg-white p-6 rounded-lg shadow-md">
                    <div class="flex items-center">
                        <div class="p-3 bg-indigo-100 rounded-full">
                            <i class="fas fa-chart-line text-indigo-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Evaluations</p>
                            <p class="text-2xl font-bold text-gray-900"><?php echo $semester_evaluations; ?></p>
                            <p class="text-xs text-indigo-600">This semester</p>
                        </div>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-lg shadow-md">
                    <div class="flex items-center">
                        <div class="p-3 bg-teal-100 rounded-full">
                            <i class="fas fa-user-tie text-teal-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Active Faculty</p>
                            <p class="text-2xl font-bold text-gray-900"><?php echo $total_faculty; ?></p>
                            <p class="text-xs text-teal-600">Total active</p>
                        </div>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-lg shadow-md">
                    <div class="flex items-center">
                        <div class="p-3 bg-pink-100 rounded-full">
                            <i class="fas fa-book text-pink-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Active Programs</p>
                            <p class="text-2xl font-bold text-gray-900"><?php echo $total_programs; ?></p>
                            <p class="text-xs text-pink-600">Academic programs</p>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <!-- Charts -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Posts by Type</h3>
                    <div class="chart-container">
                        <canvas id="postsChart"></canvas>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Users by Role</h3>
                    <div class="chart-container">
                        <canvas id="usersChart"></canvas>
                    </div>
                </div>

                <!-- Monthly Activity Chart (for semester view) -->
                <?php if ($selected_semester > 0 && !empty($monthly_activity_data)): ?>
                <div class="bg-white rounded-lg shadow-md p-6 lg:col-span-2">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Monthly Activity</h3>
                    <div class="chart-container">
                        <canvas id="monthlyActivityChart"></canvas>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Recent Activity -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">
                        Recent Posts
                        <?php if ($selected_semester > 0): ?>
                        <span class="text-sm font-normal text-gray-500">(Semester Filtered)</span>
                        <?php endif; ?>
                    </h3>
                    <div class="space-y-3">
                        <?php while($post = mysqli_fetch_assoc($recent_posts_result)): ?>
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded">
                            <div>
                                <p class="font-medium text-sm"><?php echo htmlspecialchars($post['title']); ?></p>
                                <p class="text-xs text-gray-600">
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

                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">
                        Recent Users
                        <?php if ($selected_semester > 0): ?>
                        <span class="text-sm font-normal text-gray-500">(Semester Filtered)</span>
                        <?php endif; ?>
                    </h3>
                    <div class="space-y-3">
                        <?php while($user = mysqli_fetch_assoc($recent_users_result)): ?>
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded">
                            <div>
                                <p class="font-medium text-sm"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></p>
                                <p class="text-xs text-gray-600">
                                    @<?php echo htmlspecialchars($user['username']); ?> •
                                    <?php echo date('M d, Y', strtotime($user['created_at'])); ?>
                                </p>
                            </div>
                            <span class="px-2 py-1 text-xs rounded bg-blue-100 text-blue-800">
                                <?php echo ucfirst($user['role']); ?>
                            </span>
                        </div>
                        <?php endwhile; ?>
                    </div>
                </div>
            </div>

            <!-- Detailed Statistics -->
            <div class="mt-8 grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Post Statistics</h3>
                    <div class="space-y-3">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Total Posts:</span>
                            <span class="font-medium"><?php echo $total_posts; ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Approved:</span>
                            <span class="font-medium text-green-600"><?php echo $approved_posts; ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Pending:</span>
                            <span class="font-medium text-yellow-600"><?php echo $pending_posts; ?></span>
                        </div>
                        <?php if ($selected_semester > 0): ?>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Semester Posts:</span>
                            <span class="font-medium text-blue-600"><?php echo $semester_posts; ?></span>
                        </div>
                        <?php else: ?>
                        <div class="flex justify-between">
                            <span class="text-gray-600">This Month:</span>
                            <span class="font-medium"><?php echo $posts_this_month; ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">User Statistics</h3>
                    <div class="space-y-3">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Total Users:</span>
                            <span class="font-medium"><?php echo $total_users; ?></span>
                        </div>
                        <?php if ($selected_semester > 0): ?>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Semester Users:</span>
                            <span class="font-medium text-blue-600"><?php echo $semester_users; ?></span>
                        </div>
                        <?php else: ?>
                        <div class="flex justify-between">
                            <span class="text-gray-600">New This Month:</span>
                            <span class="font-medium text-green-600"><?php echo $new_users_this_month; ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Active Students:</span>
                            <span class="font-medium"><?php echo $total_students; ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Active Faculty:</span>
                            <span class="font-medium"><?php echo $total_faculty; ?></span>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Inquiry Statistics</h3>
                    <div class="space-y-3">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Total Inquiries:</span>
                            <span class="font-medium"><?php echo $total_inquiries; ?></span>
                        </div>
                        <?php if ($selected_semester > 0): ?>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Semester Inquiries:</span>
                            <span class="font-medium text-blue-600"><?php echo $semester_inquiries; ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Unresolved:</span>
                            <span class="font-medium text-red-600"><?php echo $unresolved_inquiries; ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Resolved:</span>
                            <span class="font-medium text-green-600"><?php echo $total_inquiries - $unresolved_inquiries; ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Resolution Rate:</span>
                            <span class="font-medium"><?php echo $total_inquiries > 0 ? round((($total_inquiries - $unresolved_inquiries) / $total_inquiries) * 100, 1) : 0; ?>%</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Export Options -->
            <div class="mt-8 bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Export Reports</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <!-- User Reports -->
                    <div class="border border-gray-300 rounded-lg p-4">
                        <div class="flex items-center mb-3">
                            <i class="fas fa-users text-blue-600 mr-3"></i>
                            <span class="font-semibold">User Report</span>
                        </div>
                        <div class="flex gap-2">
                            <a href="export_excel_reports.php?type=users&semester=<?php echo $selected_semester; ?>&year=<?php echo $selected_year; ?>"
                               class="w-full bg-green-600 text-white px-3 py-2 rounded text-sm hover:bg-green-700 transition text-center">
                                <i class="fas fa-file-excel mr-2"></i>Export Excel
                            </a>
                        </div>
                    </div>

                    <!-- Post Reports -->
                    <div class="border border-gray-300 rounded-lg p-4">
                        <div class="flex items-center mb-3">
                            <i class="fas fa-newspaper text-green-600 mr-3"></i>
                            <span class="font-semibold">Post Report</span>
                        </div>
                        <div class="flex gap-2">
                            <a href="export_excel_reports.php?type=posts&semester=<?php echo $selected_semester; ?>&year=<?php echo $selected_year; ?>"
                               class="w-full bg-green-600 text-white px-3 py-2 rounded text-sm hover:bg-green-700 transition text-center">
                                <i class="fas fa-file-excel mr-2"></i>Export Excel
                            </a>
                        </div>
                    </div>

                    <!-- Inquiry Reports -->
                    <div class="border border-gray-300 rounded-lg p-4">
                        <div class="flex items-center mb-3">
                            <i class="fas fa-question-circle text-purple-600 mr-3"></i>
                            <span class="font-semibold">Inquiry Report</span>
                        </div>
                        <div class="flex gap-2">
                            <a href="export_excel_reports.php?type=inquiries&semester=<?php echo $selected_semester; ?>&year=<?php echo $selected_year; ?>"
                               class="w-full bg-green-600 text-white px-3 py-2 rounded text-sm hover:bg-green-700 transition text-center">
                                <i class="fas fa-file-excel mr-2"></i>Export Excel
                            </a>
                        </div>
                    </div>

                    <!-- Student Reports -->
                    <div class="border border-gray-300 rounded-lg p-4">
                        <div class="flex items-center mb-3">
                            <i class="fas fa-user-graduate text-purple-600 mr-3"></i>
                            <span class="font-semibold">Student Report</span>
                        </div>
                        <div class="flex gap-2">
                            <a href="export_reports.php?type=students&semester=<?php echo $selected_semester; ?>&year=<?php echo $selected_year; ?>"
                               class="flex-1 bg-blue-600 text-white px-3 py-2 rounded text-sm hover:bg-blue-700 transition text-center">
                                CSV
                            </a>
                            <a href="export_excel_reports.php?type=students&semester=<?php echo $selected_semester; ?>&year=<?php echo $selected_year; ?>"
                               class="flex-1 bg-green-600 text-white px-3 py-2 rounded text-sm hover:bg-green-700 transition text-center">
                                Excel
                            </a>
                        </div>
                    </div>

                    <!-- Evaluation Reports -->
                    <div class="border border-gray-300 rounded-lg p-4">
                        <div class="flex items-center mb-3">
                            <i class="fas fa-chart-line text-red-600 mr-3"></i>
                            <span class="font-semibold">Evaluation Report</span>
                        </div>
                        <div class="flex gap-2">
                            <a href="export_reports.php?type=evaluations&semester=<?php echo $selected_semester; ?>&year=<?php echo $selected_year; ?>"
                               class="flex-1 bg-blue-600 text-white px-3 py-2 rounded text-sm hover:bg-blue-700 transition text-center">
                                CSV
                            </a>
                            <a href="export_excel_reports.php?type=evaluations&semester=<?php echo $selected_semester; ?>&year=<?php echo $selected_year; ?>"
                               class="flex-1 bg-green-600 text-white px-3 py-2 rounded text-sm hover:bg-green-700 transition text-center">
                                Excel
                            </a>
                        </div>
                    </div>
                </div>
            </div>
    
    <script>
        // Prevent multiple chart creation
        if (typeof window.chartsInitialized === 'undefined') {
            window.chartsInitialized = false;
        }

        // Wait for DOM to be fully loaded
        document.addEventListener('DOMContentLoaded', function() {
            if (!window.chartsInitialized) {
                // Posts by Type Chart
                const postsCtx = document.getElementById('postsChart');
                if (postsCtx) {
                    <?php if (!empty($posts_data)): ?>
                    const postsChart = new Chart(postsCtx.getContext('2d'), {
                        type: 'doughnut',
                        data: {
                            labels: [
                                <?php
                                $quoted_labels = array_map(function($label) {
                                    return "'" . addslashes($label) . "'";
                                }, $posts_labels);
                                echo implode(', ', $quoted_labels);
                                ?>
                            ],
                            datasets: [{
                                data: [<?php echo implode(', ', $posts_data); ?>],
                                backgroundColor: [
                                    '#FF6B35',
                                    '#4CAF50',
                                    '#2196F3',
                                    '#9C27B0',
                                    '#FF9800'
                                ]
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            animation: {
                                duration: 1000,
                                easing: 'easeInOutQuart'
                            },
                            plugins: {
                                legend: {
                                    position: 'bottom'
                                }
                            },
                            layout: {
                                padding: {
                                    top: 10,
                                    bottom: 10
                                }
                            }
                        }
                    });
                    <?php else: ?>
                    // Show fallback chart with no data message
                    const postsChart = new Chart(postsCtx.getContext('2d'), {
                        type: 'doughnut',
                        data: {
                            labels: ['No Posts'],
                            datasets: [{
                                data: [1],
                                backgroundColor: ['#E5E7EB'],
                                borderWidth: 0
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            animation: {
                                duration: 1000,
                                easing: 'easeInOutQuart'
                            },
                            plugins: {
                                legend: {
                                    display: false
                                },
                                tooltip: {
                                    callbacks: {
                                        label: function() {
                                            return 'No posts data available';
                                        }
                                    }
                                }
                            }
                        }
                    });
                    <?php endif; ?>
                }

                // Users by Role Chart
                const usersCtx = document.getElementById('usersChart');
                if (usersCtx) {
                    <?php if (!empty($users_data)): ?>
                    const usersChart = new Chart(usersCtx.getContext('2d'), {
                        type: 'bar',
                        data: {
                            labels: [
                                <?php
                                $quoted_user_labels = array_map(function($label) {
                                    return "'" . addslashes($label) . "'";
                                }, $users_labels);
                                echo implode(', ', $quoted_user_labels);
                                ?>
                            ],
                            datasets: [{
                                label: 'Users',
                                data: [<?php echo implode(', ', $users_data); ?>],
                                backgroundColor: '#FF6B35',
                                borderColor: '#FF6B35',
                                borderWidth: 1
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            animation: {
                                duration: 1000,
                                easing: 'easeInOutQuart'
                            },
                            scales: {
                                y: {
                                    beginAtZero: true
                                }
                            },
                            plugins: {
                                legend: {
                                    display: false
                                }
                            },
                            layout: {
                                padding: {
                                    top: 10,
                                    bottom: 10
                                }
                            }
                        }
                    });
                    <?php else: ?>
                    // Show fallback chart with no data message
                    const usersChart = new Chart(usersCtx.getContext('2d'), {
                        type: 'bar',
                        data: {
                            labels: ['No Users'],
                            datasets: [{
                                label: 'Users',
                                data: [0],
                                backgroundColor: '#E5E7EB',
                                borderColor: '#E5E7EB',
                                borderWidth: 1
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            animation: {
                                duration: 1000,
                                easing: 'easeInOutQuart'
                            },
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    max: 1
                                }
                            },
                            plugins: {
                                legend: {
                                    display: false
                                },
                                tooltip: {
                                    callbacks: {
                                        label: function() {
                                            return 'No users data available';
                                        }
                                    }
                                }
                            }
                        }
                    });
                    <?php endif; ?>
                }

                // Monthly Activity Chart (for semester view)
                <?php if ($selected_semester > 0 && !empty($monthly_activity_data)): ?>
                const monthlyActivityCtx = document.getElementById('monthlyActivityChart');
                if (monthlyActivityCtx) {
                    const monthlyActivityChart = new Chart(monthlyActivityCtx.getContext('2d'), {
                        type: 'line',
                        data: {
                            labels: [
                                <?php
                                $quoted_monthly_labels = array_map(function($label) {
                                    return "'" . addslashes($label) . "'";
                                }, $monthly_activity_labels);
                                echo implode(', ', $quoted_monthly_labels);
                                ?>
                            ],
                            datasets: [{
                                label: 'Posts',
                                data: [<?php echo implode(', ', $monthly_activity_data); ?>],
                                borderColor: '#FF6B35',
                                backgroundColor: 'rgba(255, 107, 53, 0.1)',
                                borderWidth: 3,
                                fill: true,
                                tension: 0.4
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            animation: {
                                duration: 1000,
                                easing: 'easeInOutQuart'
                            },
                            scales: {
                                y: {
                                    beginAtZero: true
                                }
                            },
                            plugins: {
                                legend: {
                                    display: false
                                }
                            },
                            layout: {
                                padding: {
                                    top: 10,
                                    bottom: 10
                                }
                            }
                        }
                    });
                }
                <?php endif; ?>

                // Mark charts as initialized
                window.chartsInitialized = true;
            }
        });
    </script>
</body>
</html>