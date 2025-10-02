<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and has head role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'head') {
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

// Get head information from heads table using email
$user_email = $_SESSION['email'];
$head_query = "SELECT h.* FROM heads h WHERE h.email = ?";
$head_stmt = mysqli_prepare($conn, $head_query);
mysqli_stmt_bind_param($head_stmt, "s", $user_email);
mysqli_stmt_execute($head_stmt);
$head_result = mysqli_stmt_get_result($head_stmt);
$head_info = mysqli_fetch_assoc($head_result);

// Get comprehensive statistics for dashboard
$stats = [];

// Get teachers under this head's department
$head_department = $head_info['department'];
$params = [$head_department];
$param_types = "s";

$teachers_query = "SELECT COUNT(*) as total FROM faculty f 
                   WHERE f.department = ? AND f.is_active = 1";
$teachers_stmt = mysqli_prepare($conn, $teachers_query);
mysqli_stmt_bind_param($teachers_stmt, $param_types, ...$params);
mysqli_stmt_execute($teachers_stmt);
$teachers_result = mysqli_stmt_get_result($teachers_stmt);
$stats['total_teachers'] = mysqli_fetch_assoc($teachers_result)['total'];

// Get total evaluations for teachers in this department
// Since evaluation_sessions table was removed during FaCallTi cleanup,
// set static value
$stats['total_evaluations'] = 0;

// Get average rating for department teachers
// Since evaluation_responses and evaluation_sessions tables were removed during FaCallTi cleanup,
// set static value
$stats['avg_rating'] = 0;

// Get pending evaluations count
// Since evaluation_sessions table was removed during FaCallTi cleanup,
// set static value
$stats['pending_evaluations'] = 0;

// Get teachers with recent evaluations
// Since evaluation_sessions and evaluation_responses tables were removed during FaCallTi cleanup,
// get basic teacher information without evaluation data
$recent_teachers_query = "SELECT DISTINCT f.id, f.first_name, f.last_name, f.email,
                          0 as evaluation_count,
                          0 as avg_rating
                          FROM faculty f
                          WHERE f.department = ? AND f.is_active = 1
                          ORDER BY f.first_name ASC
                          LIMIT 5";
$recent_teachers_stmt = mysqli_prepare($conn, $recent_teachers_query);
mysqli_stmt_bind_param($recent_teachers_stmt, $param_types, ...$params);
mysqli_stmt_execute($recent_teachers_stmt);
$recent_teachers_result = mysqli_stmt_get_result($recent_teachers_stmt);

$recent_teachers = [];
while ($row = mysqli_fetch_assoc($recent_teachers_result)) {
    $recent_teachers[] = $row;
}

// Get recent activities (last 7 days)
// Since evaluation_sessions table was removed during FaCallTi cleanup,
// set empty array for recent activities
$recent_activities = [];

// Include the header
include 'includes/header.php';
?>

<!-- Welcome Section -->
<div class="mb-8">
    <div class="bg-white rounded-xl shadow-sm p-8 border border-gray-200">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold mb-2 text-seait-dark">Welcome back, <?php echo $first_name; ?>! 👋</h1>
                <p class="text-gray-600 text-lg">Head of <?php echo $head_info['department']; ?> Department</p>
                <p class="text-gray-500 mt-2">Manage your department efficiently</p>
            </div>
            <div class="hidden md:block">
                <div class="w-20 h-20 bg-seait-orange rounded-full flex items-center justify-center">
                    <i class="fas fa-user-tie text-4xl text-white"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Main Content Section -->
<div class="mb-12">
    <div class="text-center mb-8">
        <div class="inline-flex items-center justify-center w-20 h-20 bg-seait-orange rounded-full mb-6">
            <i class="fas fa-user-tie text-white text-3xl"></i>
        </div>
        <h2 class="text-3xl font-bold text-seait-dark mb-4">Department Head Portal</h2>
        <p class="text-lg text-gray-600 max-w-2xl mx-auto">
            Manage your department efficiently with our streamlined tools. 
            Access faculty management and evaluation features.
        </p>
    </div>
</div>

<!-- Quick Actions -->
<div class="mb-8">
    <h2 class="text-xl font-bold text-seait-dark mb-6">Quick Actions</h2>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <a href="teachers.php" class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition-all duration-300 transform hover:scale-105 border-l-4 border-seait-orange">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-12 h-12 bg-seait-orange rounded-lg flex items-center justify-center">
                        <i class="fas fa-users text-white text-xl"></i>
                    </div>
                </div>
                <div class="ml-4">
                    <h3 class="text-lg font-semibold text-seait-dark">Manage Teachers</h3>
                    <p class="text-sm text-gray-600">View and manage faculty members</p>
                </div>
            </div>
        </a>

        <a href="evaluate-faculty.php" class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition-all duration-300 transform hover:scale-105 border-l-4 border-seait-orange">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-12 h-12 bg-seait-orange rounded-lg flex items-center justify-center">
                        <i class="fas fa-clipboard-check text-white text-xl"></i>
                    </div>
                </div>
                <div class="ml-4">
                    <h3 class="text-lg font-semibold text-seait-dark">Evaluate Faculty</h3>
                    <p class="text-sm text-gray-600">Conduct faculty evaluations</p>
                </div>
            </div>
        </a>
    </div>
</div>

<!-- Department Overview -->
<div class="mb-8">
    <h2 class="text-xl font-bold text-seait-dark mb-6">Department Overview</h2>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-seait-orange">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-12 h-12 bg-seait-orange rounded-lg flex items-center justify-center">
                        <i class="fas fa-chalkboard-teacher text-white text-xl"></i>
                    </div>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-600">Active Teachers</p>
                    <p class="text-2xl font-bold text-seait-dark"><?php echo $stats['total_teachers']; ?></p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-seait-orange">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-12 h-12 bg-seait-orange rounded-lg flex items-center justify-center">
                        <i class="fas fa-clipboard-check text-white text-xl"></i>
                    </div>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-600">Completed Evaluations</p>
                    <p class="text-2xl font-bold text-seait-dark"><?php echo $stats['total_evaluations']; ?></p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Features Section -->
<div class="grid md:grid-cols-2 gap-8 mb-12">
    <div class="bg-white p-6 rounded-lg shadow-md text-center">
        <div class="inline-flex items-center justify-center w-16 h-16 bg-seait-orange rounded-full mb-4">
            <i class="fas fa-users text-white text-xl"></i>
        </div>
        <h3 class="text-xl font-semibold text-seait-dark mb-2">Faculty Management</h3>
        <p class="text-gray-600">Manage and oversee faculty members in your department.</p>
    </div>
    <div class="bg-white p-6 rounded-lg shadow-md text-center">
        <div class="inline-flex items-center justify-center w-16 h-16 bg-seait-orange rounded-full mb-4">
            <i class="fas fa-clipboard-check text-white text-xl"></i>
        </div>
        <h3 class="text-xl font-semibold text-seait-dark mb-2">Faculty Evaluation</h3>
        <p class="text-gray-600">Conduct comprehensive faculty evaluations and assessments.</p>
    </div>
</div>

<!-- Footer Section -->
<div class="mt-16 bg-seait-dark text-white py-8 rounded-lg">
    <div class="text-center">
        <p class="text-lg">&copy; <?php echo date('Y'); ?> SEAIT. All rights reserved.</p>
        <p class="text-gray-400 text-sm mt-2">Department Head Management Portal</p>
    </div>
</div>

<script>
// Add interactive elements
document.addEventListener('DOMContentLoaded', function() {
    // Add hover effects to action cards
    const actionCards = document.querySelectorAll('.grid a, .grid > div');
    actionCards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-2px)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
        });
    });
    
    // Add animation to overview cards
    const overviewCards = document.querySelectorAll('.border-l-4');
    overviewCards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        
        setTimeout(() => {
            card.style.transition = 'all 0.6s ease';
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, index * 100);
    });
});
</script>


