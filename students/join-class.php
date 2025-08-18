<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and has student role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../login.php');
    exit();
}

// Set page title
$page_title = 'Join Class';

$message = '';
$message_type = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $join_code = sanitize_input($_POST['join_code']);

    if (empty($join_code)) {
        $message = "Please enter a join code!";
        $message_type = "error";
    } else {
        // Check if join code exists and class is active
        $class_query = "SELECT tc.*, cc.subject_title, cc.subject_code, cc.units,
                       u.first_name as teacher_first_name, u.last_name as teacher_last_name
                       FROM teacher_classes tc
                       JOIN course_curriculum cc ON tc.subject_id = cc.id
                       JOIN users u ON tc.teacher_id = u.id
                       WHERE tc.join_code = ? AND tc.status = 'active'";
        $class_stmt = mysqli_prepare($conn, $class_query);
        mysqli_stmt_bind_param($class_stmt, "s", $join_code);
        mysqli_stmt_execute($class_stmt);
        $class_result = mysqli_stmt_get_result($class_stmt);
        $class = mysqli_fetch_assoc($class_result);

        if (!$class) {
            $message = "Invalid join code or class is not active!";
            $message_type = "error";
        } else {
            // Check if student is already enrolled
            $enrollment_check = "SELECT id FROM class_enrollments
                               WHERE class_id = ? AND student_id = ?";
            $enrollment_stmt = mysqli_prepare($conn, $enrollment_check);
            mysqli_stmt_bind_param($enrollment_stmt, "ii", $class['id'], $_SESSION['user_id']);
            mysqli_stmt_execute($enrollment_stmt);
            $enrollment_result = mysqli_stmt_get_result($enrollment_stmt);

            if (mysqli_num_rows($enrollment_result) > 0) {
                $message = "You are already enrolled in this class!";
                $message_type = "error";
            } else {
                // Get the student_id from students table
                $student_id = get_student_id($conn, $_SESSION['email']);

                if (!$student_id) {
                    $message = "Student profile not found. Please contact administrator.";
                    $message_type = "error";
                } else {
                    // Enroll student in the class using the correct student_id
                    $enroll_query = "INSERT INTO class_enrollments (class_id, student_id, status) VALUES (?, ?, 'enrolled')";
                    $enroll_stmt = mysqli_prepare($conn, $enroll_query);
                    mysqli_stmt_bind_param($enroll_stmt, "ii", $class['id'], $student_id);

                    if (mysqli_stmt_execute($enroll_stmt)) {
                        $message = "Successfully joined " . htmlspecialchars($class['subject_title']) . " - Section " . htmlspecialchars($class['section']) . "!";
                        $message_type = "success";
                    } else {
                        $message = "Error joining class: " . mysqli_error($conn);
                        $message_type = "error";
                    }
                }
            }
        }
    }
}

// Get student's current enrollments for reference
$student_id = get_student_id($conn, $_SESSION['email']);

$current_enrollments_query = "SELECT ce.*, tc.section, tc.join_code,
                             cc.subject_title, cc.subject_code,
                             u.first_name as teacher_first_name, u.last_name as teacher_last_name
                             FROM class_enrollments ce
                             JOIN teacher_classes tc ON ce.class_id = tc.id
                             JOIN course_curriculum cc ON tc.subject_id = cc.id
                             JOIN users u ON tc.teacher_id = u.id
                             WHERE ce.student_id = ? AND ce.status = 'enrolled'
                             ORDER BY ce.join_date DESC
                             LIMIT 5";
$current_enrollments_stmt = mysqli_prepare($conn, $current_enrollments_query);
mysqli_stmt_bind_param($current_enrollments_stmt, "i", $student_id);
mysqli_stmt_execute($current_enrollments_stmt);
$current_enrollments_result = mysqli_stmt_get_result($current_enrollments_stmt);

// Include the shared header
include 'includes/header.php';
?>

<div class="mb-6 sm:mb-8">
    <h1 class="text-2xl sm:text-3xl font-bold text-seait-dark mb-2">Join Class</h1>
    <p class="text-sm sm:text-base text-gray-600">Enter a join code to enroll in a teacher's class</p>
</div>

<!-- Message Display -->
<?php if ($message): ?>
<div class="mb-6 p-4 rounded-lg <?php echo $message_type === 'success' ? 'bg-green-50 border border-green-200 text-green-800' : 'bg-red-50 border border-red-200 text-red-800'; ?>">
    <?php echo htmlspecialchars($message); ?>
</div>
<?php endif; ?>

<!-- Join Class Form -->
<div class="bg-white rounded-lg shadow-md overflow-hidden mb-6 sm:mb-8">
    <div class="px-6 py-4 border-b border-gray-200">
        <h2 class="text-lg font-medium text-gray-900">Enter Join Code</h2>
    </div>

    <div class="p-6">
        <form method="POST" class="max-w-md">
            <div class="mb-4">
                <label for="join_code" class="block text-sm font-medium text-gray-700 mb-2">Join Code</label>
                <input type="text" id="join_code" name="join_code" required
                       placeholder="Enter the 8-character join code"
                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange"
                       maxlength="8" style="text-transform: uppercase;">
                <p class="text-xs text-gray-500 mt-1">Ask your teacher for the join code</p>
            </div>

            <button type="submit" class="w-full bg-seait-orange text-white px-4 py-2 rounded-md hover:bg-orange-600 transition">
                <i class="fas fa-plus-circle mr-2"></i>Join Class
            </button>
        </form>
    </div>
</div>

<!-- How to Join Instructions -->
<div class="bg-blue-50 rounded-lg p-6 mb-6 sm:mb-8">
    <h3 class="text-lg font-medium text-blue-900 mb-4">
        <i class="fas fa-info-circle mr-2"></i>How to Join a Class
    </h3>
    <div class="space-y-3 text-sm text-blue-800">
        <div class="flex items-start">
            <span class="bg-blue-200 text-blue-800 rounded-full w-6 h-6 flex items-center justify-center text-xs font-medium mr-3 mt-0.5">1</span>
            <p>Ask your teacher for the join code for their class</p>
        </div>
        <div class="flex items-start">
            <span class="bg-blue-200 text-blue-800 rounded-full w-6 h-6 flex items-center justify-center text-xs font-medium mr-3 mt-0.5">2</span>
            <p>Enter the 8-character join code in the field above</p>
        </div>
        <div class="flex items-start">
            <span class="bg-blue-200 text-blue-800 rounded-full w-6 h-6 flex items-center justify-center text-xs font-medium mr-3 mt-0.5">3</span>
            <p>Click "Join Class" to enroll in the class</p>
        </div>
        <div class="flex items-start">
            <span class="bg-blue-200 text-blue-800 rounded-full w-6 h-6 flex items-center justify-center text-xs font-medium mr-3 mt-0.5">4</span>
            <p>Once enrolled, you can evaluate your teacher and access class materials</p>
        </div>
    </div>
</div>

<!-- Current Enrollments -->
<div class="bg-white rounded-lg shadow-md overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-200">
        <div class="flex items-center justify-between">
            <h3 class="text-lg font-medium text-gray-900">Your Enrolled Classes</h3>
            <a href="my-classes.php" class="text-seait-orange hover:text-orange-600 text-sm font-medium">
                View all classes <i class="fas fa-arrow-right ml-1"></i>
            </a>
        </div>
    </div>

    <?php if (mysqli_num_rows($current_enrollments_result) == 0): ?>
        <div class="p-6 text-center">
            <i class="fas fa-chalkboard text-gray-300 text-4xl mb-4"></i>
            <p class="text-gray-500">You haven't joined any classes yet.</p>
            <p class="text-sm text-gray-400 mt-2">Use a join code above to enroll in your first class.</p>
        </div>
    <?php else: ?>
        <div class="divide-y divide-gray-200">
            <?php while ($enrollment = mysqli_fetch_assoc($current_enrollments_result)): ?>
            <div class="p-6">
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <div class="h-10 w-10 rounded-full bg-seait-orange flex items-center justify-center mr-4">
                            <span class="text-white font-medium"><?php echo strtoupper(substr($enrollment['teacher_first_name'], 0, 1) . substr($enrollment['teacher_last_name'], 0, 1)); ?></span>
                        </div>
                        <div>
                            <h4 class="text-sm sm:text-base font-medium text-gray-900"><?php echo htmlspecialchars($enrollment['subject_title']); ?></h4>
                            <p class="text-sm text-gray-500"><?php echo htmlspecialchars($enrollment['subject_code']); ?> - Section <?php echo htmlspecialchars($enrollment['section']); ?></p>
                            <p class="text-xs text-gray-400">Teacher: <?php echo htmlspecialchars($enrollment['teacher_first_name'] . ' ' . $enrollment['teacher_last_name']); ?></p>
                        </div>
                    </div>
                    <div class="text-right">
                        <span class="px-2 py-1 text-xs rounded-full bg-green-100 text-green-800">
                            Enrolled
                        </span>
                        <p class="text-xs text-gray-500 mt-1">Joined <?php echo date('M d, Y', strtotime($enrollment['join_date'])); ?></p>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
    <?php endif; ?>
</div>

<script>
// Auto-uppercase join code input
document.getElementById('join_code').addEventListener('input', function() {
    this.value = this.value.toUpperCase();
});

// Auto-focus on join code input
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('join_code').focus();
});
</script>

<?php
// Include the shared footer
include 'includes/footer.php';
?>