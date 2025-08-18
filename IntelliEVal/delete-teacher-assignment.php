<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and has appropriate role
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'guidance_officer', 'head'])) {
    header("Location: " . get_login_path());
    exit();
}

$success_message = '';
$error_message = '';

// Handle deletion
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['assignment_id'])) {
    $assignment_id = (int)$_POST['assignment_id'];

    // Check if assignment exists and user has permission
    $check_query = "SELECT ts.*, t.first_name, t.last_name, s.name as subject_name
                   FROM teacher_subjects ts
                   JOIN teachers t ON ts.teacher_id = t.id
                   JOIN subjects s ON ts.subject_id = s.id
                   WHERE ts.id = ?";
    $stmt = mysqli_prepare($conn, $check_query);
    mysqli_stmt_bind_param($stmt, "i", $assignment_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if (mysqli_num_rows($result) === 0) {
        $error_message = "Assignment not found.";
    } else {
        $assignment = mysqli_fetch_assoc($result);

        // Delete the assignment
        $delete_query = "DELETE FROM teacher_subjects WHERE id = ?";
        $stmt = mysqli_prepare($conn, $delete_query);
        mysqli_stmt_bind_param($stmt, "i", $assignment_id);

        if (mysqli_stmt_execute($stmt)) {
            $success_message = "Teacher assignment for " . htmlspecialchars($assignment['first_name'] . ' ' . $assignment['last_name']) .
                              " in " . htmlspecialchars($assignment['subject_name']) . " has been deleted successfully.";
        } else {
            $error_message = "Error deleting assignment: " . mysqli_error($conn);
        }
    }
} else {
    // Redirect if accessed directly without POST data
    header("Location: teacher-subjects.php");
    exit();
}

include 'includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="max-w-2xl mx-auto">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-3xl font-bold text-gray-900">Delete Teacher Assignment</h1>
            <a href="teacher-subjects.php" class="bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600 transition">
                <i class="fas fa-arrow-left mr-2"></i>Back to Assignments
            </a>
        </div>

        <?php if ($success_message): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                <?php echo $success_message; ?>
            </div>
            <div class="text-center">
                <a href="teacher-subjects.php" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition">
                    Return to Assignments
                </a>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <?php echo $error_message; ?>
            </div>
            <div class="text-center">
                <a href="teacher-subjects.php" class="bg-gray-600 text-white px-6 py-2 rounded-lg hover:bg-gray-700 transition">
                    Return to Assignments
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>