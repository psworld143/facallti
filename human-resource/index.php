<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/id_encryption.php';

// Check if user is logged in and has human_resource role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'human_resource') {
    header('Location: ../index.php');
    exit();
}

// Set page title
$page_title = 'HR Dashboard';

// Get user information
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$first_name = $_SESSION['first_name'];
$last_name = $_SESSION['last_name'];

// Get HR statistics
$stats = [];

// Get total faculty count
$faculty_query = "SELECT COUNT(*) as total FROM faculty WHERE is_active = 1";
$faculty_result = mysqli_query($conn, $faculty_query);
$stats['total_faculty'] = mysqli_fetch_assoc($faculty_result)['total'];



// Get total users count
$users_query = "SELECT COUNT(*) as total FROM users WHERE status = 'active'";
$users_result = mysqli_query($conn, $users_query);
$stats['total_users'] = mysqli_fetch_assoc($users_result)['total'];

// Get recent faculty members
$recent_faculty_query = "SELECT id, first_name, last_name, email, department, position, created_at 
                        FROM faculty 
                        WHERE is_active = 1 
                        ORDER BY created_at DESC 
                        LIMIT 5";
$recent_faculty_result = mysqli_query($conn, $recent_faculty_query);
$recent_faculty = [];
while ($row = mysqli_fetch_assoc($recent_faculty_result)) {
    $recent_faculty[] = $row;
}

// Include the header
include 'includes/header.php';
?>

<!-- Welcome Section -->
<div class="mb-6">
    <div class="bg-gradient-to-r from-seait-orange to-orange-500 text-white rounded-xl shadow-lg p-6 transform transition-transform hover:scale-105">
        <h2 class="text-2xl font-bold mb-2">Welcome, <?php echo $first_name; ?>!</h2>
        <p class="opacity-90">You are logged in as the Human Resource Manager.</p>
    </div>
</div>

<!-- Statistics Cards -->
<div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-blue-500 transform transition-all hover:scale-105 hover:shadow-xl">
        <div class="flex items-center">
            <div class="flex-shrink-0">
                <div class="w-16 h-16 bg-gradient-to-r from-blue-500 to-blue-600 rounded-full flex items-center justify-center text-white text-2xl">
                    <i class="fas fa-chalkboard-teacher"></i>
                </div>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-500">Total Faculty</p>
                <p class="text-2xl font-bold text-gray-900" id="total-faculty"><?php echo $stats['total_faculty']; ?></p>
            </div>
        </div>
    </div>



    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-yellow-500 transform transition-all hover:scale-105 hover:shadow-xl">
        <div class="flex items-center">
            <div class="flex-shrink-0">
                <div class="w-16 h-16 bg-gradient-to-r from-yellow-500 to-yellow-600 rounded-full flex items-center justify-center text-white text-2xl">
                    <i class="fas fa-users"></i>
                </div>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-500">Total Users</p>
                <p class="text-2xl font-bold text-gray-900" id="total-users"><?php echo $stats['total_users']; ?></p>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
    <div class="bg-white rounded-lg shadow-sm p-6">
        <h3 class="text-lg font-medium text-gray-900 mb-4">Quick Actions</h3>
        <div class="space-y-3">
            <a href="manage-faculty.php" class="flex items-center p-4 bg-gray-50 rounded-lg hover:bg-white hover:border-2 hover:border-seait-orange transform transition-all hover:translate-x-2 hover:shadow-lg" data-action="manage-faculty">
                <i class="fas fa-user-plus mr-3 text-seait-orange text-xl transition-transform group-hover:scale-110"></i>
                <span class="text-gray-700 font-medium">Manage Faculty</span>
            </a>
            <a href="manage-users.php" class="flex items-center p-4 bg-gray-50 rounded-lg hover:bg-white hover:border-2 hover:border-seait-orange transform transition-all hover:translate-x-2 hover:shadow-lg" data-action="manage-users">
                <i class="fas fa-user-cog mr-3 text-seait-orange text-xl transition-transform group-hover:scale-110"></i>
                <span class="text-gray-700 font-medium">Manage Users</span>
            </a>
            <a href="reports.php" class="flex items-center p-4 bg-gray-50 rounded-lg hover:bg-white hover:border-2 hover:border-seait-orange transform transition-all hover:translate-x-2 hover:shadow-lg" data-action="reports">
                <i class="fas fa-chart-bar mr-3 text-seait-orange text-xl transition-transform group-hover:scale-110"></i>
                <span class="text-gray-700 font-medium">HR Reports</span>
            </a>
            <a href="settings.php" class="flex items-center p-4 bg-gray-50 rounded-lg hover:bg-white hover:border-2 hover:border-seait-orange transform transition-all hover:translate-x-2 hover:shadow-lg" data-action="settings">
                <i class="fas fa-cog mr-3 text-seait-orange text-xl transition-transform group-hover:scale-110"></i>
                <span class="text-gray-700 font-medium">Settings</span>
            </a>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow-sm p-6">
        <h3 class="text-lg font-medium text-gray-900 mb-4">Recent Faculty Members</h3>
        <?php if (empty($recent_faculty)): ?>
            <p class="text-gray-500">No recent faculty members found.</p>
        <?php else: ?>
            <div class="space-y-3">
                <?php foreach ($recent_faculty as $faculty): ?>
                    <div class="bg-white rounded-lg p-4 mb-3 border-l-4 border-blue-500 transform transition-all hover:translate-x-2 hover:shadow-lg cursor-pointer" data-faculty-id="<?php echo encrypt_id($faculty['id']); ?>">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <div class="w-10 h-10 bg-gradient-to-r from-blue-500 to-blue-600 rounded-full flex items-center justify-center text-white font-bold text-sm">
                                    <?php echo strtoupper(substr($faculty['first_name'], 0, 1) . substr($faculty['last_name'], 0, 1)); ?>
                                </div>
                                <div class="ml-3">
                                    <p class="font-medium text-gray-900"><?php echo $faculty['first_name'] . ' ' . $faculty['last_name']; ?></p>
                                    <p class="text-sm text-gray-500"><?php echo $faculty['position']; ?> - <?php echo $faculty['department']; ?></p>
                                </div>
                            </div>
                            <span class="text-xs text-gray-400"><?php echo date('M j, Y', strtotime($faculty['created_at'])); ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- College Overview -->
<div class="bg-white rounded-xl shadow-lg p-6">
    <h3 class="text-lg font-medium text-gray-900 mb-4">College Overview</h3>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-hr-secondary">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">College</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Short Name</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Faculty Count</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php
                $college_query = "SELECT c.name, c.short_name, COUNT(f.id) as faculty_count 
                                  FROM colleges c 
                                  LEFT JOIN faculty f ON f.department = c.name AND f.is_active = 1 
                                  WHERE c.is_active = 1 
                                  GROUP BY c.id, c.name, c.short_name 
                                  ORDER BY faculty_count DESC";
                $college_result = mysqli_query($conn, $college_query);
                while ($college = mysqli_fetch_assoc($college_result)):
                ?>
                <tr class="hover:bg-gray-50 transition-colors cursor-pointer" data-college="<?php echo htmlspecialchars($college['name']); ?>">
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                        <?php echo $college['name']; ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        <?php echo $college['short_name']; ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        <?php echo $college['faculty_count']; ?> faculty members
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                        <a href="manage-faculty.php?department=<?php echo urlencode($college['name']); ?>" 
                           class="text-seait-orange hover:text-seait-dark font-medium transition-colors">
                            View Faculty
                        </a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<!-- Custom JavaScript for HR Dashboard -->
<script src="assets/js/hr-dashboard.js"></script>
