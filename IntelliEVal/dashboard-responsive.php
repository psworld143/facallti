<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and has guidance_officer role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'guidance_officer') {
    header('Location: ../index.php');
    exit();
}

// Set page title
$page_title = 'Dashboard';

// Get user information
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$first_name = $_SESSION['first_name'];
$last_name = $_SESSION['last_name'];
$role = $_SESSION['role'];

// Get statistics for dashboard
$stats = [];

// Total students
$result = mysqli_query($conn, "SELECT COUNT(*) as total FROM students WHERE status = 'active'");
$stats['total_students'] = mysqli_fetch_assoc($result)['total'];

// Recent registrations (last 7 days)
$result = mysqli_query($conn, "SELECT COUNT(*) as total FROM students WHERE status = 'active' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
$stats['recent_registrations'] = mysqli_fetch_assoc($result)['total'];

// Pending students
$result = mysqli_query($conn, "SELECT COUNT(*) as total FROM students WHERE status = 'pending'");
$stats['pending_students'] = mysqli_fetch_assoc($result)['total'];

// Get recent student registrations
$recent_students_query = "SELECT * FROM students WHERE status = 'active' ORDER BY created_at DESC LIMIT 5";
$recent_students_result = mysqli_query($conn, $recent_students_query);
$recent_students = [];
while ($row = mysqli_fetch_assoc($recent_students_result)) {
    $recent_students[] = $row;
}

// Include the shared header
include 'includes/header.php';
?>

<!-- Welcome Section -->
<div class="mb-6 sm:mb-8">
    <h2 class="text-2xl sm:text-3xl font-bold text-seait-dark mb-2">Welcome back, <?php echo htmlspecialchars($first_name); ?>!</h2>
    <p class="text-sm sm:text-base text-gray-600">Here's what's happening with student evaluations and guidance today.</p>
</div>

<!-- Statistics Cards -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6 mb-6 sm:mb-8">
    <div class="bg-white overflow-hidden shadow rounded-lg">
        <div class="p-4 sm:p-5">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-blue-500 rounded-md flex items-center justify-center">
                        <i class="fas fa-users text-white"></i>
                    </div>
                </div>
                <div class="ml-3 sm:ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-xs sm:text-sm font-medium text-gray-500 truncate">Total Students</dt>
                        <dd class="text-base sm:text-lg font-medium text-gray-900"><?php echo number_format($stats['total_students']); ?></dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white overflow-hidden shadow rounded-lg">
        <div class="p-4 sm:p-5">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-green-500 rounded-md flex items-center justify-center">
                        <i class="fas fa-user-plus text-white"></i>
                    </div>
                </div>
                <div class="ml-3 sm:ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-xs sm:text-sm font-medium text-gray-500 truncate">Recent Registrations</dt>
                        <dd class="text-base sm:text-lg font-medium text-gray-900"><?php echo number_format($stats['recent_registrations']); ?></dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white overflow-hidden shadow rounded-lg">
        <div class="p-4 sm:p-5">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-yellow-500 rounded-md flex items-center justify-center">
                        <i class="fas fa-clock text-white"></i>
                    </div>
                </div>
                <div class="ml-3 sm:ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-xs sm:text-sm font-medium text-gray-500 truncate">Pending Students</dt>
                        <dd class="text-base sm:text-lg font-medium text-gray-900"><?php echo number_format($stats['pending_students']); ?></dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white overflow-hidden shadow rounded-lg">
        <div class="p-4 sm:p-5">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-purple-500 rounded-md flex items-center justify-center">
                        <i class="fas fa-clipboard-check text-white"></i>
                    </div>
                </div>
                <div class="ml-3 sm:ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-xs sm:text-sm font-medium text-gray-500 truncate">Evaluations</dt>
                        <dd class="text-base sm:text-lg font-medium text-gray-900">0</dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 sm:gap-8 mb-6 sm:mb-8">
    <!-- Recent Students -->
    <div class="bg-white shadow rounded-lg">
        <div class="px-4 sm:px-6 py-4 border-b border-gray-200">
            <h3 class="text-base sm:text-lg font-medium text-gray-900">Recent Student Registrations</h3>
        </div>
        <div class="p-4 sm:p-6">
            <?php if (empty($recent_students)): ?>
                <p class="text-gray-500 text-center py-4">No recent student registrations</p>
            <?php else: ?>
                <div class="space-y-3 sm:space-y-4">
                    <?php foreach ($recent_students as $student): ?>
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                            <div class="flex items-center">
                                <div class="h-8 w-8 sm:h-10 sm:w-10 rounded-full bg-seait-orange flex items-center justify-center mr-3">
                                    <span class="text-white text-sm sm:text-base font-medium"><?php echo strtoupper(substr($student['first_name'], 0, 1) . substr($student['last_name'], 0, 1)); ?></span>
                                </div>
                                <div>
                                    <p class="text-xs sm:text-sm font-medium text-gray-900"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></p>
                                    <p class="text-xs sm:text-sm text-gray-500"><?php echo htmlspecialchars($student['student_id']); ?></p>
                                </div>
                            </div>
                            <span class="text-xs text-gray-400"><?php echo date('M d, Y', strtotime($student['created_at'])); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <div class="mt-4">
                <a href="students.php" class="text-seait-orange hover:text-orange-600 text-xs sm:text-sm font-medium">
                    View all students <i class="fas fa-arrow-right ml-1"></i>
                </a>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="bg-white shadow rounded-lg">
        <div class="px-4 sm:px-6 py-4 border-b border-gray-200">
            <h3 class="text-base sm:text-lg font-medium text-gray-900">Quick Actions</h3>
        </div>
        <div class="p-4 sm:p-6">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
                <a href="students.php?action=add" class="flex items-center p-3 sm:p-4 bg-blue-50 rounded-lg hover:bg-blue-100 transition">
                    <div class="w-6 h-6 sm:w-8 sm:h-8 bg-blue-500 rounded-md flex items-center justify-center mr-3">
                        <i class="fas fa-user-plus text-white text-xs sm:text-sm"></i>
                    </div>
                    <div>
                        <p class="text-xs sm:text-sm font-medium text-gray-900">Add Student</p>
                        <p class="text-xs text-gray-500">Register new student</p>
                    </div>
                </a>

                <a href="evaluations.php?action=create" class="flex items-center p-3 sm:p-4 bg-green-50 rounded-lg hover:bg-green-100 transition">
                    <div class="w-6 h-6 sm:w-8 sm:h-8 bg-green-500 rounded-md flex items-center justify-center mr-3">
                        <i class="fas fa-clipboard-check text-white text-xs sm:text-sm"></i>
                    </div>
                    <div>
                        <p class="text-xs sm:text-sm font-medium text-gray-900">Create Evaluation</p>
                        <p class="text-xs text-gray-500">New student evaluation</p>
                    </div>
                </a>

                <a href="reports.php" class="flex items-center p-3 sm:p-4 bg-purple-50 rounded-lg hover:bg-purple-100 transition">
                    <div class="w-6 h-6 sm:w-8 sm:h-8 bg-purple-500 rounded-md flex items-center justify-center mr-3">
                        <i class="fas fa-chart-bar text-white text-xs sm:text-sm"></i>
                    </div>
                    <div>
                        <p class="text-xs sm:text-sm font-medium text-gray-900">View Reports</p>
                        <p class="text-xs text-gray-500">Analytics & insights</p>
                    </div>
                </a>

                <a href="settings.php" class="flex items-center p-3 sm:p-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition">
                    <div class="w-6 h-6 sm:w-8 sm:h-8 bg-gray-500 rounded-md flex items-center justify-center mr-3">
                        <i class="fas fa-cog text-white text-xs sm:text-sm"></i>
                    </div>
                    <div>
                        <p class="text-xs sm:text-sm font-medium text-gray-900">Settings</p>
                        <p class="text-xs text-gray-500">System configuration</p>
                    </div>
                </a>
            </div>
        </div>
    </div>
</div>

<!-- System Status -->
<div class="bg-white shadow rounded-lg">
    <div class="px-4 sm:px-6 py-4 border-b border-gray-200">
        <h3 class="text-base sm:text-lg font-medium text-gray-900">System Status</h3>
    </div>
    <div class="p-4 sm:p-6">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 sm:gap-6">
            <div class="text-center">
                <div class="w-10 h-10 sm:w-12 sm:h-12 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-3">
                    <i class="fas fa-check text-green-600 text-sm sm:text-base"></i>
                </div>
                <h4 class="text-xs sm:text-sm font-medium text-gray-900">Database</h4>
                <p class="text-xs text-gray-500">Connected</p>
            </div>
            <div class="text-center">
                <div class="w-10 h-10 sm:w-12 sm:h-12 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-3">
                    <i class="fas fa-check text-green-600 text-sm sm:text-base"></i>
                </div>
                <h4 class="text-xs sm:text-sm font-medium text-gray-900">Student Module</h4>
                <p class="text-xs text-gray-500">Active</p>
            </div>
            <div class="text-center">
                <div class="w-10 h-10 sm:w-12 sm:h-12 bg-yellow-100 rounded-full flex items-center justify-center mx-auto mb-3">
                    <i class="fas fa-clock text-yellow-600 text-sm sm:text-base"></i>
                </div>
                <h4 class="text-xs sm:text-sm font-medium text-gray-900">Evaluation Module</h4>
                <p class="text-xs text-gray-500">In Development</p>
            </div>
        </div>
    </div>
</div>

<?php
// Include the shared footer
include 'includes/footer.php';
?>