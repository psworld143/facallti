<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'head') {
    header('Location: ../index.php');
    exit();
}

if (!function_exists('is_head_evaluation_active') || !is_head_evaluation_active()) {
    $_SESSION['message'] = 'There is no ongoing faculty evaluation period.';
    $_SESSION['message_type'] = 'error';
    header('Location: dashboard.php');
    exit();
}

$page_title = 'Evaluate Faculty';
$user_id = $_SESSION['user_id'];

// Get head info
$head_query = "SELECT * FROM heads WHERE user_id = ?";
$head_stmt = mysqli_prepare($conn, $head_query);
mysqli_stmt_bind_param($head_stmt, "i", $user_id);
mysqli_stmt_execute($head_stmt);
$head_result = mysqli_stmt_get_result($head_stmt);
$head = mysqli_fetch_assoc($head_result);

if (!$head) {
    $_SESSION['message'] = 'Head information not found.';
    $_SESSION['message_type'] = 'error';
    header('Location: dashboard.php');
    exit();
}

$department = $head['department'];

// Get faculty in this department
$faculty_query = "SELECT * FROM faculty WHERE department = ? AND is_active = 1 ORDER BY last_name, first_name";
$faculty_stmt = mysqli_prepare($conn, $faculty_query);
mysqli_stmt_bind_param($faculty_stmt, "s", $department);
mysqli_stmt_execute($faculty_stmt);
$faculty_result = mysqli_stmt_get_result($faculty_stmt);
$faculty_list = [];
while ($row = mysqli_fetch_assoc($faculty_result)) {
    $faculty_list[] = $row;
}

// Get current active evaluation schedule for head_to_teacher
$sched_query = "SELECT * FROM evaluation_schedules WHERE evaluation_type = 'head_to_teacher' AND status = 'active' AND NOW() BETWEEN start_date AND end_date ORDER BY start_date DESC LIMIT 1";
$sched_result = mysqli_query($conn, $sched_query);
$schedule = mysqli_fetch_assoc($sched_result);

// Get the current semester id from the schedule
$current_semester_id = $schedule ? $schedule['semester_id'] : null;

// Prepare a map of faculty_id => evaluation session id (if exists)
$existing_evaluations = [];
if ($current_semester_id) {
    $eval_query = "SELECT es.id, es.evaluatee_id FROM evaluation_sessions es WHERE es.evaluator_id = ? AND es.evaluator_type = 'head' AND es.evaluatee_type = 'teacher' AND es.semester_id = ? AND es.status IN ('in_progress', 'draft', 'active', 'completed')";
    $eval_stmt = mysqli_prepare($conn, $eval_query);
    mysqli_stmt_bind_param($eval_stmt, "ii", $user_id, $current_semester_id);
    mysqli_stmt_execute($eval_stmt);
    $eval_result = mysqli_stmt_get_result($eval_stmt);
    while ($row = mysqli_fetch_assoc($eval_result)) {
        $existing_evaluations[$row['evaluatee_id']] = $row['id'];
    }
}

include 'includes/header.php';
?>
<div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-900">Evaluate Faculty</h1>
    <p class="text-gray-600">You may evaluate your faculty during the ongoing evaluation period.</p>
    <?php if ($schedule): ?>
        <div class="mt-2 text-sm text-green-700">
            <i class="fas fa-clock mr-1"></i>
            Evaluation Period: <?php echo date('M d, Y', strtotime($schedule['start_date'])); ?> - <?php echo date('M d, Y', strtotime($schedule['end_date'])); ?>
        </div>
    <?php endif; ?>
</div>
<div class="bg-white rounded-lg shadow-sm overflow-hidden">
    <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Faculty</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Position</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
            </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-200">
            <?php foreach ($faculty_list as $faculty): ?>
                <tr>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <?php echo htmlspecialchars($faculty['first_name'] . ' ' . $faculty['last_name']); ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        <?php echo htmlspecialchars($faculty['position']); ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        <?php echo htmlspecialchars($faculty['email']); ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                        <?php if (isset($existing_evaluations[$faculty['id']])): ?>
                            <a href="conduct-evaluation.php?evaluatee_id=<?php echo $faculty['id']; ?>" class="text-blue-600 hover:text-blue-900">
                                <i class="fas fa-edit mr-1"></i> Edit Evaluation
                            </a>
                        <?php else: ?>
                            <a href="conduct-evaluation.php?evaluatee_id=<?php echo $faculty['id']; ?>" class="text-seait-orange hover:text-orange-600">
                                <i class="fas fa-edit mr-1"></i> Evaluate
                            </a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php // No footer include as per previous instructions ?> 