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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $assignment_id = (int)$_POST['assignment_id'];
    $teacher_id = (int)$_POST['teacher_id'];
    $subject_id = (int)$_POST['subject_id'];
    $semester_id = (int)$_POST['semester_id'];
    $academic_year = sanitize_input($_POST['academic_year']);
    $section = sanitize_input($_POST['section']);
    $schedule = sanitize_input($_POST['schedule']);

    // Validate input
    if (empty($teacher_id) || empty($subject_id) || empty($semester_id) || empty($academic_year)) {
        $error_message = "All required fields must be filled.";
    } else {
        // Update the assignment
        $update_query = "UPDATE teacher_subjects SET
                        teacher_id = ?,
                        subject_id = ?,
                        semester_id = ?,
                        academic_year = ?,
                        section = ?,
                        schedule = ?,
                        updated_at = CURRENT_TIMESTAMP
                        WHERE id = ?";

        $stmt = mysqli_prepare($conn, $update_query);
        mysqli_stmt_bind_param($stmt, "iiisssi", $teacher_id, $subject_id, $semester_id, $academic_year, $section, $schedule, $assignment_id);

        if (mysqli_stmt_execute($stmt)) {
            $success_message = "Teacher assignment updated successfully.";
        } else {
            $error_message = "Error updating assignment: " . mysqli_error($conn);
        }
    }
}

// Get assignment details
$assignment_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$assignment_id) {
    header("Location: teacher-subjects.php");
    exit();
}

$assignment_query = "SELECT ts.*, t.first_name, t.last_name, t.email, s.name as subject_name, s.code as subject_code, sem.name as semester_name
                    FROM teacher_subjects ts
                    JOIN teachers t ON ts.teacher_id = t.id
                    JOIN subjects s ON ts.subject_id = s.id
                    JOIN semesters sem ON ts.semester_id = sem.id
                    WHERE ts.id = ?";
$stmt = mysqli_prepare($conn, $assignment_query);
mysqli_stmt_bind_param($stmt, "i", $assignment_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) === 0) {
    header("Location: teacher-subjects.php");
    exit();
}

$assignment = mysqli_fetch_assoc($result);

// Get all teachers
$teachers_query = "SELECT id, first_name, last_name, email FROM teachers WHERE is_active = 1 ORDER BY last_name, first_name";
$teachers_result = mysqli_query($conn, $teachers_query);

// Get all subjects
$subjects_query = "SELECT id, name, code FROM subjects WHERE is_active = 1 ORDER BY name";
$subjects_result = mysqli_query($conn, $subjects_query);

// Get all semesters
$semesters_query = "SELECT id, name, academic_year FROM semesters WHERE is_active = 1 ORDER BY academic_year DESC, name";
$semesters_result = mysqli_query($conn, $semesters_query);

include 'includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="max-w-4xl mx-auto">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-3xl font-bold text-gray-900">Edit Teacher Assignment</h1>
            <a href="teacher-subjects.php" class="bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600 transition">
                <i class="fas fa-arrow-left mr-2"></i>Back to Assignments
            </a>
        </div>

        <?php if ($success_message): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                <?php echo $success_message; ?>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <?php echo $error_message; ?>
            </div>
        <?php endif; ?>

        <div class="bg-white rounded-lg shadow-md p-6">
            <form method="POST" class="space-y-6">
                <input type="hidden" name="assignment_id" value="<?php echo $assignment_id; ?>">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="teacher_id" class="block text-sm font-medium text-gray-700 mb-2">Teacher *</label>
                        <select id="teacher_id" name="teacher_id" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="">Select Teacher</option>
                            <?php while ($teacher = mysqli_fetch_assoc($teachers_result)): ?>
                                <option value="<?php echo $teacher['id']; ?>" <?php echo $teacher['id'] == $assignment['teacher_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($teacher['last_name'] . ', ' . $teacher['first_name'] . ' (' . $teacher['email'] . ')'); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div>
                        <label for="subject_id" class="block text-sm font-medium text-gray-700 mb-2">Subject *</label>
                        <select id="subject_id" name="subject_id" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="">Select Subject</option>
                            <?php while ($subject = mysqli_fetch_assoc($subjects_result)): ?>
                                <option value="<?php echo $subject['id']; ?>" <?php echo $subject['id'] == $assignment['subject_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($subject['name'] . ' (' . $subject['code'] . ')'); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div>
                        <label for="semester_id" class="block text-sm font-medium text-gray-700 mb-2">Semester *</label>
                        <select id="semester_id" name="semester_id" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="">Select Semester</option>
                            <?php while ($semester = mysqli_fetch_assoc($semesters_result)): ?>
                                <option value="<?php echo $semester['id']; ?>" <?php echo $semester['id'] == $assignment['semester_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($semester['name'] . ' - ' . $semester['academic_year']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div>
                        <label for="academic_year" class="block text-sm font-medium text-gray-700 mb-2">Academic Year *</label>
                        <input type="text" id="academic_year" name="academic_year" value="<?php echo htmlspecialchars($assignment['academic_year']); ?>" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               placeholder="e.g., 2023-2024">
                    </div>

                    <div>
                        <label for="section" class="block text-sm font-medium text-gray-700 mb-2">Section</label>
                        <input type="text" id="section" name="section" value="<?php echo htmlspecialchars($assignment['section'] ?? ''); ?>"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               placeholder="e.g., A, B, C">
                    </div>

                    <div>
                        <label for="schedule" class="block text-sm font-medium text-gray-700 mb-2">Schedule</label>
                        <input type="text" id="schedule" name="schedule" value="<?php echo htmlspecialchars($assignment['schedule'] ?? ''); ?>"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               placeholder="e.g., MWF 9:00-10:30 AM">
                    </div>
                </div>

                <div class="flex justify-end space-x-4">
                    <a href="teacher-subjects.php" class="px-6 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition">
                        Cancel
                    </a>
                    <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                        Update Assignment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>