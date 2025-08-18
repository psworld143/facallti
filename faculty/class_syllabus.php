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
                WHERE tc.id = ? AND tc.teacher_id = ?";
$class_stmt = mysqli_prepare($conn, $class_query);
mysqli_stmt_bind_param($class_stmt, "ii", $class_id, $_SESSION['user_id']);
mysqli_stmt_execute($class_stmt);
$class_result = mysqli_stmt_get_result($class_stmt);
$class_data = mysqli_fetch_assoc($class_result);

if (!$class_data) {
    header('Location: class-management.php');
    exit();
}

// Set page title
$page_title = 'Class Syllabus - ' . $class_data['subject_title'];

$message = '';
$message_type = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'save_syllabus':
                $title = sanitize_input($_POST['title']);
                $description = sanitize_input($_POST['description']);
                $course_objectives = sanitize_input($_POST['course_objectives']);
                $learning_outcomes = sanitize_input($_POST['learning_outcomes']);
                $prerequisites = sanitize_input($_POST['prerequisites']);
                $course_requirements = sanitize_input($_POST['course_requirements']);
                $grading_system = sanitize_input($_POST['grading_system']);
                $course_policies = sanitize_input($_POST['course_policies']);
                $academic_integrity = sanitize_input($_POST['academic_integrity']);
                $attendance_policy = sanitize_input($_POST['attendance_policy']);
                $late_submission_policy = sanitize_input($_POST['late_submission_policy']);
                $office_hours = sanitize_input($_POST['office_hours']);
                $contact_information = sanitize_input($_POST['contact_information']);
                $textbooks = sanitize_input($_POST['textbooks']);
                $references = sanitize_input($_POST['references']);
                $schedule = sanitize_input($_POST['schedule']);
                $assessment_methods = sanitize_input($_POST['assessment_methods']);

                if (empty($title)) {
                    $message = "Please provide a title for the syllabus.";
                    $message_type = "error";
                } else {
                    // Check if syllabus already exists for this class
                    $check_query = "SELECT id FROM class_syllabus WHERE class_id = ?";
                    $check_stmt = mysqli_prepare($conn, $check_query);
                    mysqli_stmt_bind_param($check_stmt, "i", $class_id);
                    mysqli_stmt_execute($check_stmt);
                    $check_result = mysqli_stmt_get_result($check_stmt);

                    if (mysqli_num_rows($check_result) > 0) {
                        // Update existing syllabus
                        $syllabus = mysqli_fetch_assoc($check_result);
                        $update_query = "UPDATE class_syllabus SET
                                        title = ?, description = ?, course_objectives = ?, learning_outcomes = ?,
                                        prerequisites = ?, course_requirements = ?, grading_system = ?, course_policies = ?,
                                        academic_integrity = ?, attendance_policy = ?, late_submission_policy = ?,
                                        office_hours = ?, contact_information = ?, textbooks = ?, references = ?,
                                        schedule = ?, assessment_methods = ?, updated_at = NOW()
                                        WHERE id = ?";
                        $update_stmt = mysqli_prepare($conn, $update_query);
                        mysqli_stmt_bind_param($update_stmt, "sssssssssssssssssi",
                            $title, $description, $course_objectives, $learning_outcomes,
                            $prerequisites, $course_requirements, $grading_system, $course_policies,
                            $academic_integrity, $attendance_policy, $late_submission_policy,
                            $office_hours, $contact_information, $textbooks, $references,
                            $schedule, $assessment_methods, $syllabus['id']);

                        if (mysqli_stmt_execute($update_stmt)) {
                            $message = "Syllabus updated successfully!";
                            $message_type = "success";
                        } else {
                            $message = "Error updating syllabus: " . mysqli_error($conn);
                            $message_type = "error";
                        }
                    } else {
                        // Insert new syllabus
                        $insert_query = "INSERT INTO class_syllabus (class_id, teacher_id, title, description,
                                        course_objectives, learning_outcomes, prerequisites, course_requirements,
                                        grading_system, course_policies, academic_integrity, attendance_policy,
                                        late_submission_policy, office_hours, contact_information, textbooks,
                                        references, schedule, assessment_methods, created_at)
                                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
                        $insert_stmt = mysqli_prepare($conn, $insert_query);
                        mysqli_stmt_bind_param($insert_stmt, "iisssssssssssssssss",
                            $class_id, $_SESSION['user_id'], $title, $description, $course_objectives,
                            $learning_outcomes, $prerequisites, $course_requirements, $grading_system,
                            $course_policies, $academic_integrity, $attendance_policy, $late_submission_policy,
                            $office_hours, $contact_information, $textbooks, $references, $schedule, $assessment_methods);

                        if (mysqli_stmt_execute($insert_stmt)) {
                            $message = "Syllabus created successfully!";
                            $message_type = "success";
                        } else {
                            $message = "Error creating syllabus: " . mysqli_error($conn);
                            $message_type = "error";
                        }
                    }
                }
                break;

            case 'publish_syllabus':
                $publish_query = "UPDATE class_syllabus SET is_published = 1, published_at = NOW() WHERE class_id = ?";
                $publish_stmt = mysqli_prepare($conn, $publish_query);
                mysqli_stmt_bind_param($publish_stmt, "i", $class_id);

                if (mysqli_stmt_execute($publish_stmt)) {
                    $message = "Syllabus published successfully! Students can now view it.";
                    $message_type = "success";
                } else {
                    $message = "Error publishing syllabus: " . mysqli_error($conn);
                    $message_type = "error";
                }
                break;

            case 'unpublish_syllabus':
                $unpublish_query = "UPDATE class_syllabus SET is_published = 0, published_at = NULL WHERE class_id = ?";
                $unpublish_stmt = mysqli_prepare($conn, $unpublish_query);
                mysqli_stmt_bind_param($unpublish_stmt, "i", $class_id);

                if (mysqli_stmt_execute($unpublish_stmt)) {
                    $message = "Syllabus unpublished successfully! Students can no longer view it.";
                    $message_type = "success";
                } else {
                    $message = "Error unpublishing syllabus: " . mysqli_error($conn);
                    $message_type = "error";
                }
                break;
        }
    }
}

// Get existing syllabus data
$syllabus_query = "SELECT * FROM class_syllabus WHERE class_id = ?";
$syllabus_stmt = mysqli_prepare($conn, $syllabus_query);
mysqli_stmt_bind_param($syllabus_stmt, "i", $class_id);
mysqli_stmt_execute($syllabus_stmt);
$syllabus_result = mysqli_stmt_get_result($syllabus_stmt);
$syllabus_data = mysqli_fetch_assoc($syllabus_result);

// Get syllabus topics
$topics_query = "SELECT * FROM syllabus_topics WHERE syllabus_id = ? ORDER BY order_number, week_number";
$topics_stmt = mysqli_prepare($conn, $topics_query);
if ($syllabus_data) {
    mysqli_stmt_bind_param($topics_stmt, "i", $syllabus_data['id']);
    mysqli_stmt_execute($topics_stmt);
    $topics_result = mysqli_stmt_get_result($topics_stmt);
} else {
    $topics_result = null;
}

// Get syllabus files
$files_query = "SELECT * FROM syllabus_files WHERE syllabus_id = ? ORDER BY uploaded_at DESC";
$files_stmt = mysqli_prepare($conn, $files_query);
if ($syllabus_data) {
    mysqli_stmt_bind_param($files_stmt, "i", $syllabus_data['id']);
    mysqli_stmt_execute($files_stmt);
    $files_result = mysqli_stmt_get_result($files_stmt);
} else {
    $files_result = null;
}

// Include the header
include 'includes/header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - Faculty Portal</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.ckeditor.com/ckeditor5/27.1.0/classic/ckeditor.js"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'seait-dark': '#1e3a8a',
                        'seait-orange': '#f97316'
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-50">
    <!-- Navigation -->
    <nav class="bg-seait-dark text-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <a href="dashboard.php" class="flex items-center space-x-3">
                        <i class="fas fa-graduation-cap text-2xl"></i>
                        <span class="text-xl font-bold">Faculty Portal</span>
                    </a>
                </div>
                <div class="flex items-center space-x-4">
                    <span class="text-sm"><?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?></span>
                    <a href="logout.php" class="text-white hover:text-gray-300">
                        <i class="fas fa-sign-out-alt"></i>
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Breadcrumb -->
    <div class="bg-white shadow-sm border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3">
            <nav class="flex" aria-label="Breadcrumb">
                <ol class="flex items-center space-x-4">
                    <li>
                        <a href="class-management.php" class="text-gray-500 hover:text-gray-700">
                            <i class="fas fa-home"></i>
                        </a>
                    </li>
                    <li>
                        <div class="flex items-center">
                            <i class="fas fa-chevron-right text-gray-400 mx-2"></i>
                            <a href="class_dashboard.php?class_id=<?php echo $class_id; ?>" class="text-gray-500 hover:text-gray-700">
                                <?php echo htmlspecialchars($class_data['subject_title']); ?>
                            </a>
                        </div>
                    </li>
                    <li>
                        <div class="flex items-center">
                            <i class="fas fa-chevron-right text-gray-400 mx-2"></i>
                            <span class="text-gray-900">Syllabus</span>
                        </div>
                    </li>
                </ol>
            </nav>
        </div>
    </div>

    <!-- Main Content -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Header -->
        <div class="mb-8">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Course Syllabus</h1>
                    <p class="text-gray-600 mt-2"><?php echo htmlspecialchars($class_data['subject_title'] . ' - ' . $class_data['section']); ?></p>
                </div>
                <div class="mt-4 sm:mt-0 flex space-x-3">
                    <?php if ($syllabus_data): ?>
                        <a href="syllabus_topics.php?class_id=<?php echo $class_id; ?>" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition">
                            <i class="fas fa-list mr-2"></i>Manage Topics
                        </a>
                        <?php if ($syllabus_data['is_published']): ?>
                            <form method="POST" class="inline">
                                <input type="hidden" name="action" value="unpublish_syllabus">
                                <button type="submit" class="bg-yellow-600 text-white px-4 py-2 rounded-lg hover:bg-yellow-700 transition">
                                    <i class="fas fa-eye-slash mr-2"></i>Unpublish
                                </button>
                            </form>
                        <?php else: ?>
                            <form method="POST" class="inline">
                                <input type="hidden" name="action" value="publish_syllabus">
                                <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition">
                                    <i class="fas fa-eye mr-2"></i>Publish
                                </button>
                            </form>
                        <?php endif; ?>
                    <?php endif; ?>
                    <a href="class_dashboard.php?class_id=<?php echo $class_id; ?>" class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700 transition">
                        <i class="fas fa-arrow-left mr-2"></i>Back to Dashboard
                    </a>
                </div>
            </div>
        </div>

        <?php if ($message): ?>
        <div class="mb-6 p-4 rounded-lg <?php echo $message_type === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
            <?php echo $message; ?>
        </div>
        <?php endif; ?>

        <!-- Syllabus Status -->
        <?php if ($syllabus_data): ?>
        <div class="mb-6 p-4 rounded-lg <?php echo $syllabus_data['is_published'] ? 'bg-green-50 border border-green-200' : 'bg-yellow-50 border border-yellow-200'; ?>">
            <div class="flex items-center">
                <i class="fas <?php echo $syllabus_data['is_published'] ? 'fa-check-circle text-green-600' : 'fa-clock text-yellow-600'; ?> mr-3"></i>
                <div>
                    <h3 class="font-medium <?php echo $syllabus_data['is_published'] ? 'text-green-900' : 'text-yellow-900'; ?>">
                        Syllabus <?php echo $syllabus_data['is_published'] ? 'Published' : 'Draft'; ?>
                    </h3>
                    <p class="text-sm <?php echo $syllabus_data['is_published'] ? 'text-green-700' : 'text-yellow-700'; ?>">
                        <?php if ($syllabus_data['is_published']): ?>
                            Published on <?php echo date('M j, Y g:i A', strtotime($syllabus_data['published_at'])); ?>
                        <?php else: ?>
                            Last updated on <?php echo date('M j, Y g:i A', strtotime($syllabus_data['updated_at'] ?: $syllabus_data['created_at'])); ?>
                        <?php endif; ?>
                    </p>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Syllabus Form -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-medium text-gray-900">Syllabus Information</h2>
            </div>
            <form method="POST" class="p-6">
                <input type="hidden" name="action" value="save_syllabus">

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Basic Information -->
                    <div class="space-y-6">
                        <div>
                            <label for="title" class="block text-sm font-medium text-gray-700 mb-2">Syllabus Title *</label>
                            <input type="text" id="title" name="title" required
                                   value="<?php echo htmlspecialchars($syllabus_data['title'] ?? ''); ?>"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-seait-orange">
                        </div>

                        <div>
                            <label for="description" class="block text-sm font-medium text-gray-700 mb-2">Course Description</label>
                            <textarea id="description" name="description" rows="4"
                                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-seait-orange"><?php echo htmlspecialchars($syllabus_data['description'] ?? ''); ?></textarea>
                        </div>

                        <div>
                            <label for="prerequisites" class="block text-sm font-medium text-gray-700 mb-2">Prerequisites</label>
                            <textarea id="prerequisites" name="prerequisites" rows="3"
                                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-seait-orange"><?php echo htmlspecialchars($syllabus_data['prerequisites'] ?? ''); ?></textarea>
                        </div>

                        <div>
                            <label for="office_hours" class="block text-sm font-medium text-gray-700 mb-2">Office Hours</label>
                            <textarea id="office_hours" name="office_hours" rows="3"
                                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-seait-orange"><?php echo htmlspecialchars($syllabus_data['office_hours'] ?? ''); ?></textarea>
                        </div>

                        <div>
                            <label for="contact_information" class="block text-sm font-medium text-gray-700 mb-2">Contact Information</label>
                            <textarea id="contact_information" name="contact_information" rows="3"
                                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-seait-orange"><?php echo htmlspecialchars($syllabus_data['contact_information'] ?? ''); ?></textarea>
                        </div>
                    </div>

                    <!-- Course Details -->
                    <div class="space-y-6">
                        <div>
                            <label for="course_objectives" class="block text-sm font-medium text-gray-700 mb-2">Course Objectives</label>
                            <textarea id="course_objectives" name="course_objectives" rows="4"
                                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-seait-orange"><?php echo htmlspecialchars($syllabus_data['course_objectives'] ?? ''); ?></textarea>
                        </div>

                        <div>
                            <label for="learning_outcomes" class="block text-sm font-medium text-gray-700 mb-2">Learning Outcomes</label>
                            <textarea id="learning_outcomes" name="learning_outcomes" rows="4"
                                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-seait-orange"><?php echo htmlspecialchars($syllabus_data['learning_outcomes'] ?? ''); ?></textarea>
                        </div>

                        <div>
                            <label for="course_requirements" class="block text-sm font-medium text-gray-700 mb-2">Course Requirements</label>
                            <textarea id="course_requirements" name="course_requirements" rows="4"
                                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-seait-orange"><?php echo htmlspecialchars($syllabus_data['course_requirements'] ?? ''); ?></textarea>
                        </div>

                        <div>
                            <label for="assessment_methods" class="block text-sm font-medium text-gray-700 mb-2">Assessment Methods</label>
                            <textarea id="assessment_methods" name="assessment_methods" rows="3"
                                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-seait-orange"><?php echo htmlspecialchars($syllabus_data['assessment_methods'] ?? ''); ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- Policies Section -->
                <div class="mt-8 border-t border-gray-200 pt-8">
                    <h3 class="text-lg font-medium text-gray-900 mb-6">Course Policies</h3>
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <div>
                            <label for="grading_system" class="block text-sm font-medium text-gray-700 mb-2">Grading System</label>
                            <textarea id="grading_system" name="grading_system" rows="4"
                                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-seait-orange"><?php echo htmlspecialchars($syllabus_data['grading_system'] ?? ''); ?></textarea>
                        </div>

                        <div>
                            <label for="course_policies" class="block text-sm font-medium text-gray-700 mb-2">General Course Policies</label>
                            <textarea id="course_policies" name="course_policies" rows="4"
                                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-seait-orange"><?php echo htmlspecialchars($syllabus_data['course_policies'] ?? ''); ?></textarea>
                        </div>

                        <div>
                            <label for="academic_integrity" class="block text-sm font-medium text-gray-700 mb-2">Academic Integrity Policy</label>
                            <textarea id="academic_integrity" name="academic_integrity" rows="3"
                                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-seait-orange"><?php echo htmlspecialchars($syllabus_data['academic_integrity'] ?? ''); ?></textarea>
                        </div>

                        <div>
                            <label for="attendance_policy" class="block text-sm font-medium text-gray-700 mb-2">Attendance Policy</label>
                            <textarea id="attendance_policy" name="attendance_policy" rows="3"
                                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-seait-orange"><?php echo htmlspecialchars($syllabus_data['attendance_policy'] ?? ''); ?></textarea>
                        </div>

                        <div>
                            <label for="late_submission_policy" class="block text-sm font-medium text-gray-700 mb-2">Late Submission Policy</label>
                            <textarea id="late_submission_policy" name="late_submission_policy" rows="3"
                                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-seait-orange"><?php echo htmlspecialchars($syllabus_data['late_submission_policy'] ?? ''); ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- Resources Section -->
                <div class="mt-8 border-t border-gray-200 pt-8">
                    <h3 class="text-lg font-medium text-gray-900 mb-6">Course Resources</h3>
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <div>
                            <label for="textbooks" class="block text-sm font-medium text-gray-700 mb-2">Required Textbooks</label>
                            <textarea id="textbooks" name="textbooks" rows="4"
                                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-seait-orange"><?php echo htmlspecialchars($syllabus_data['textbooks'] ?? ''); ?></textarea>
                        </div>

                        <div>
                            <label for="references" class="block text-sm font-medium text-gray-700 mb-2">Additional References</label>
                            <textarea id="references" name="references" rows="4"
                                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-seait-orange"><?php echo htmlspecialchars($syllabus_data['references'] ?? ''); ?></textarea>
                        </div>

                        <div class="lg:col-span-2">
                            <label for="schedule" class="block text-sm font-medium text-gray-700 mb-2">Course Schedule</label>
                            <textarea id="schedule" name="schedule" rows="6"
                                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-seait-orange"><?php echo htmlspecialchars($syllabus_data['schedule'] ?? ''); ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="mt-8 flex justify-end">
                    <button type="submit" class="bg-seait-orange text-white px-6 py-3 rounded-lg hover:bg-orange-600 transition">
                        <i class="fas fa-save mr-2"></i>Save Syllabus
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Initialize CKEditor for rich text areas
        document.addEventListener('DOMContentLoaded', function() {
            const textareas = [
                'course_objectives', 'learning_outcomes', 'course_requirements',
                'grading_system', 'course_policies', 'textbooks', 'references', 'schedule'
            ];

            textareas.forEach(function(textareaId) {
                const textarea = document.getElementById(textareaId);
                if (textarea) {
                    ClassicEditor
                        .create(textarea, {
                            toolbar: ['heading', '|', 'bold', 'italic', 'link', 'bulletedList', 'numberedList', '|', 'undo', 'redo'],
                            placeholder: 'Enter content here...'
                        })
                        .catch(error => {
                            console.error(error);
                        });
                }
            });
        });
    </script>
</body>
</html>