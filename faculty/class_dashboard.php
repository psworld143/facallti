<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and has teacher role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header('Location: ../login.php');
    exit();
}

// Get class_id from URL
$class_id = isset($_GET['class_id']) ? (int)$_GET['class_id'] : null;

if (!$class_id) {
    header('Location: class-management.php');
    exit();
}

// Verify the class belongs to the logged-in teacher
$class_query = "SELECT tc.*, cc.subject_title, cc.subject_code, cc.units, cc.description as subject_description
                FROM teacher_classes tc
                JOIN course_curriculum cc ON tc.subject_id = cc.id
                JOIN faculty f ON tc.teacher_id = f.id
                WHERE tc.id = ? AND f.email = ? AND f.is_active = 1";
$class_stmt = mysqli_prepare($conn, $class_query);
mysqli_stmt_bind_param($class_stmt, "is", $class_id, $_SESSION['username']);
mysqli_stmt_execute($class_stmt);
$class_result = mysqli_stmt_get_result($class_stmt);
$class_data = mysqli_fetch_assoc($class_result);

if (!$class_data) {
    header('Location: class-management.php');
    exit();
}

// Set page title
$page_title = $class_data['subject_title'] . ' - Class Dashboard';

// Get class statistics
$stats_query = "SELECT
                COUNT(DISTINCT ce.student_id) as total_students,
                COUNT(DISTINCT CASE WHEN ce.status = 'active' THEN ce.student_id END) as active_students,
                COUNT(DISTINCT CASE WHEN ce.status = 'dropped' THEN ce.student_id END) as dropped_students,
                COUNT(DISTINCT es.id) as total_evaluations,
                COUNT(DISTINCT CASE WHEN es.status = 'completed' THEN es.id END) as completed_evaluations
                FROM class_enrollments ce
                JOIN teacher_classes tc ON ce.class_id = tc.id
                JOIN faculty f ON tc.teacher_id = f.id
                LEFT JOIN evaluation_sessions es ON es.evaluatee_id = f.id AND es.evaluator_id = ce.student_id
                WHERE ce.class_id = ? AND f.email = ?";
$stats_stmt = mysqli_prepare($conn, $stats_query);
mysqli_stmt_bind_param($stats_stmt, "is", $class_id, $_SESSION['username']);
mysqli_stmt_execute($stats_stmt);
$stats_result = mysqli_stmt_get_result($stats_stmt);
$class_stats = mysqli_fetch_assoc($stats_result);

// Get recent announcements
$announcements_query = "SELECT * FROM class_announcements
                       WHERE class_id = ?
                       ORDER BY created_at DESC
                       LIMIT 5";
$announcements_stmt = mysqli_prepare($conn, $announcements_query);
mysqli_stmt_bind_param($announcements_stmt, "i", $class_id);
mysqli_stmt_execute($announcements_stmt);
$announcements_result = mysqli_stmt_get_result($announcements_stmt);

// Get recent events
$events_query = "SELECT * FROM faculty_events
                WHERE class_id = ?
                ORDER BY event_date ASC
                LIMIT 5";
$events_stmt = mysqli_prepare($conn, $events_query);
mysqli_stmt_bind_param($events_stmt, "i", $class_id);
mysqli_stmt_execute($events_stmt);
$events_result = mysqli_stmt_get_result($events_stmt);

// Get recent student activities (placeholder for future features)
$recent_activities = [];

// Get evaluation statistics for this class
$evaluation_stats_query = "SELECT
                          COUNT(*) as total_evaluations,
                          COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_evaluations,
                          COUNT(CASE WHEN status = 'in_progress' THEN 1 END) as in_progress_evaluations
                          FROM evaluation_sessions
                          WHERE evaluatee_id = ? AND evaluator_id IN
                          (SELECT student_id FROM class_enrollments WHERE class_id = ? AND status = 'active')";
$evaluation_stats_stmt = mysqli_prepare($conn, $evaluation_stats_query);
mysqli_stmt_bind_param($evaluation_stats_stmt, "ii", $_SESSION['user_id'], $class_id);
mysqli_stmt_execute($evaluation_stats_stmt);
$evaluation_stats = mysqli_fetch_assoc(mysqli_stmt_get_result($evaluation_stats_stmt));

// Include the unified LMS header
include 'includes/lms_header.php';
?>

<!-- Dashboard Content -->
<!-- Quick Stats -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    <div class="bg-white rounded-lg shadow-md p-6">
        <div class="flex items-center">
            <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mr-4">
                <i class="fas fa-users text-blue-600 text-xl"></i>
            </div>
            <div>
                <p class="text-sm font-medium text-gray-600">Total Students</p>
                <p class="text-2xl font-bold text-gray-900"><?php echo $class_stats['total_students']; ?></p>
            </div>
        </div>
        <div class="mt-4 flex justify-between text-sm">
            <span class="text-green-600"><?php echo $class_stats['active_students']; ?> Active</span>
            <span class="text-red-500"><?php echo $class_stats['dropped_students']; ?> Dropped</span>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow-md p-6">
        <div class="flex items-center">
            <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center mr-4">
                <i class="fas fa-clipboard-check text-green-600 text-xl"></i>
            </div>
            <div>
                <p class="text-sm font-medium text-gray-600">Evaluations</p>
                <p class="text-2xl font-bold text-gray-900"><?php echo $evaluation_stats['total_evaluations']; ?></p>
            </div>
        </div>
        <div class="mt-4 flex justify-between text-sm">
            <span class="text-green-600"><?php echo $evaluation_stats['completed_evaluations']; ?> Completed</span>
            <span class="text-yellow-600"><?php echo $evaluation_stats['in_progress_evaluations']; ?> In Progress</span>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow-md p-6">
        <div class="flex items-center">
            <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center mr-4">
                <i class="fas fa-bullhorn text-purple-600 text-xl"></i>
            </div>
            <div>
                <p class="text-sm font-medium text-gray-600">Announcements</p>
                <p class="text-2xl font-bold text-gray-900"><?php echo mysqli_num_rows($announcements_result); ?></p>
            </div>
        </div>
        <div class="mt-4">
            <span class="text-sm text-gray-500">Recent posts</span>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow-md p-6">
        <div class="flex items-center">
            <div class="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center mr-4">
                <i class="fas fa-calendar-alt text-orange-600 text-xl"></i>
            </div>
            <div>
                <p class="text-sm font-medium text-gray-600">Upcoming Events</p>
                <p class="text-2xl font-bold text-gray-900"><?php echo mysqli_num_rows($events_result); ?></p>
            </div>
        </div>
        <div class="mt-4">
            <span class="text-sm text-gray-500">Scheduled events</span>
        </div>
    </div>
</div>

<!-- Main Content Grid -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
    <!-- Recent Announcements -->
    <div class="bg-white rounded-lg shadow-md">
        <div class="px-6 py-4 border-b border-gray-200">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-medium text-gray-900">Recent Announcements</h2>
                <a href="class_announcements.php?class_id=<?php echo $class_id; ?>" class="text-seait-orange hover:text-orange-600 text-sm font-medium">
                    View all <i class="fas fa-arrow-right ml-1"></i>
                </a>
            </div>
        </div>

        <div class="p-6">
            <?php if (mysqli_num_rows($announcements_result) == 0): ?>
                <p class="text-gray-500 text-center py-4">No announcements yet.</p>
            <?php else: ?>
                <div class="space-y-4">
                    <?php while ($announcement = mysqli_fetch_assoc($announcements_result)): ?>
                        <div class="border-l-4 border-seait-orange pl-4">
                            <h3 class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($announcement['title']); ?></h3>
                            <p class="text-sm text-gray-600 mt-1"><?php echo htmlspecialchars(substr($announcement['content'], 0, 100)) . (strlen($announcement['content']) > 100 ? '...' : ''); ?></p>
                            <p class="text-xs text-gray-400 mt-2"><?php echo date('M d, Y', strtotime($announcement['created_at'])); ?></p>
                        </div>
                    <?php endwhile; ?>
                </div>
                <div class="mt-4">
                    <a href="class_announcements.php?class_id=<?php echo $class_id; ?>" class="mt-4 inline-block bg-seait-orange text-white px-4 py-2 rounded-lg hover:bg-orange-600 transition">
                        Post New Announcement
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Upcoming Events -->
    <div class="bg-white rounded-lg shadow-md">
        <div class="px-6 py-4 border-b border-gray-200">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-medium text-gray-900">Upcoming Events</h2>
                <a href="class_calendar.php?class_id=<?php echo $class_id; ?>" class="text-seait-orange hover:text-orange-600 text-sm font-medium">
                    View calendar <i class="fas fa-arrow-right ml-1"></i>
                </a>
            </div>
        </div>

        <div class="p-6">
            <?php if (mysqli_num_rows($events_result) == 0): ?>
                <p class="text-gray-500 text-center py-4">No upcoming events.</p>
            <?php else: ?>
                <div class="space-y-4">
                    <?php while ($event = mysqli_fetch_assoc($events_result)): ?>
                        <div class="flex items-center">
                            <div class="w-10 h-10 bg-seait-orange rounded-lg flex items-center justify-center mr-3">
                                <i class="fas fa-calendar text-white text-sm"></i>
                            </div>
                            <div class="flex-1">
                                <h3 class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($event['title']); ?></h3>
                                <p class="text-sm text-gray-600"><?php echo htmlspecialchars($event['description']); ?></p>
                                <p class="text-xs text-gray-400"><?php echo date('M d, Y', strtotime($event['event_date'])); ?></p>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
                <div class="mt-4">
                    <a href="class_calendar.php?class_id=<?php echo $class_id; ?>" class="mt-4 inline-block bg-seait-orange text-white px-4 py-2 rounded-lg hover:bg-orange-600 transition">
                        Schedule New Event
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="mt-8 bg-white rounded-lg shadow-md p-6">
    <h2 class="text-lg font-semibold text-gray-900 mb-4">Quick Actions</h2>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <a href="class_announcements.php?class_id=<?php echo $class_id; ?>&action=create" class="bg-blue-50 p-4 rounded-lg hover:bg-blue-100 transition">
            <div class="flex items-center">
                <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center mr-3">
                    <i class="fas fa-bullhorn text-blue-600"></i>
                </div>
                <div>
                    <h3 class="text-sm font-medium text-gray-900">Post Announcement</h3>
                    <p class="text-xs text-gray-600">Share updates with students</p>
                </div>
            </div>
        </a>

        <a href="class_materials.php?class_id=<?php echo $class_id; ?>&action=create" class="bg-green-50 p-4 rounded-lg hover:bg-green-100 transition">
            <div class="flex items-center">
                <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center mr-3">
                    <i class="fas fa-book text-green-600"></i>
                </div>
                <div>
                    <h3 class="text-sm font-medium text-gray-900">Add Material</h3>
                    <p class="text-xs text-gray-600">Upload learning resources</p>
                </div>
            </div>
        </a>

        <a href="class_assignments.php?class_id=<?php echo $class_id; ?>&action=create" class="bg-purple-50 p-4 rounded-lg hover:bg-purple-100 transition">
            <div class="flex items-center">
                <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center mr-3">
                    <i class="fas fa-tasks text-purple-600"></i>
                </div>
                <div>
                    <h3 class="text-sm font-medium text-gray-900">Create Assignment</h3>
                    <p class="text-xs text-gray-600">Assign tasks to students</p>
                </div>
            </div>
        </a>

        <a href="class_calendar.php?class_id=<?php echo $class_id; ?>&action=create" class="bg-orange-50 p-4 rounded-lg hover:bg-orange-100 transition">
            <div class="flex items-center">
                <div class="w-10 h-10 bg-orange-100 rounded-lg flex items-center justify-center mr-3">
                    <i class="fas fa-calendar-plus text-orange-600"></i>
                </div>
                <div>
                    <h3 class="text-sm font-medium text-gray-900">Schedule Event</h3>
                    <p class="text-xs text-gray-600">Add to class calendar</p>
                </div>
            </div>
        </a>
    </div>
</div>

<?php
// Include the unified footer
include 'includes/footer.php';
?>