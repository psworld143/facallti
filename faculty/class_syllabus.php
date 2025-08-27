<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/id_encryption.php';

// Check if user is logged in and has appropriate role (teacher, head, or admin)
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['teacher', 'head', 'admin'])) {
    header('Location: ../index.php');
    exit();
}

// Get class_id from URL
$encrypted_class_id = $_GET['class_id'] ?? '';

// Debug logging (commented out for production)
// error_log("Class Syllabus - Encrypted class_id received: " . $encrypted_class_id);

// Validate the encrypted string format (allow = for base64 padding)
if (empty($encrypted_class_id)) {
    error_log("Class Syllabus - Empty encrypted class_id");
    header('Location: class-management.php?message=' . urlencode('No class ID provided.') . '&type=error');
    exit();
}

$class_id = safe_decrypt_id($encrypted_class_id);

// Debug logging (commented out for production)
// error_log("Class Syllabus - Decrypted class_id: " . $class_id);

if (!$class_id) {
    // Log the issue for debugging
    error_log("Class Syllabus - Failed to decrypt class_id: " . $encrypted_class_id);
    header('Location: class-management.php?message=' . urlencode('Invalid class ID provided.') . '&type=error');
    exit();
}

// Verify the class belongs to the logged-in teacher or user has admin/head role
$class_query = "SELECT tc.*, cc.subject_title, cc.subject_code, cc.units, cc.description as subject_description
                FROM teacher_classes tc
                JOIN course_curriculum cc ON tc.subject_id = cc.id
                WHERE tc.id = ? AND (tc.teacher_id = ? OR ? IN ('admin', 'head'))";

$class_stmt = mysqli_prepare($conn, $class_query);
mysqli_stmt_bind_param($class_stmt, "iis", $class_id, $_SESSION['user_id'], $_SESSION['role']);
mysqli_stmt_execute($class_stmt);
$class_result = mysqli_stmt_get_result($class_stmt);
$class_data = mysqli_fetch_assoc($class_result);

if (!$class_data) {
    header('Location: class-management.php');
    exit();
}

// Set page title
$page_title = 'Syllabus';

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
                $course_references = sanitize_input($_POST['references']);
                $schedule = sanitize_input($_POST['schedule']);
                $assessment_methods = sanitize_input($_POST['assessment_methods']);
                $course_units = sanitize_input($_POST['course_units']);
                $course_credits = sanitize_input($_POST['course_credits']);
                $semester = sanitize_input($_POST['semester']);
                $academic_year = sanitize_input($_POST['academic_year']);
                $class_schedule = sanitize_input($_POST['class_schedule']);
                $classroom_location = sanitize_input($_POST['classroom_location']);
                $course_website = sanitize_input($_POST['course_website']);
                $emergency_contact = sanitize_input($_POST['emergency_contact']);
                $disability_accommodations = sanitize_input($_POST['disability_accommodations']);

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
                                        office_hours = ?, contact_information = ?, textbooks = ?, course_references = ?,
                                        schedule = ?, assessment_methods = ?, course_units = ?, course_credits = ?,
                                        semester = ?, academic_year = ?, class_schedule = ?, classroom_location = ?,
                                        course_website = ?, emergency_contact = ?, disability_accommodations = ?, updated_at = NOW()
                                        WHERE id = ?";
                        $update_stmt = mysqli_prepare($conn, $update_query);
                        mysqli_stmt_bind_param($update_stmt, "sssssssssssssssssssssssssssi",
                            $title, $description, $course_objectives, $learning_outcomes,
                            $prerequisites, $course_requirements, $grading_system, $course_policies,
                            $academic_integrity, $attendance_policy, $late_submission_policy,
                            $office_hours, $contact_information, $textbooks, $course_references,
                            $schedule, $assessment_methods, $course_units, $course_credits,
                            $semester, $academic_year, $class_schedule, $classroom_location,
                            $course_website, $emergency_contact, $disability_accommodations, $syllabus['id']);

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
                                        course_references, schedule, assessment_methods, course_units, course_credits,
                                        semester, academic_year, class_schedule, classroom_location, course_website,
                                        emergency_contact, disability_accommodations, created_at)
                                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
                        $insert_stmt = mysqli_prepare($conn, $insert_query);
                        // Use the original teacher_id from the class data, not the current user
                        $original_teacher_id = $class_data['teacher_id'];
                        mysqli_stmt_bind_param($insert_stmt, "iisssssssssssssssssssssssssss",
                            $class_id, $original_teacher_id, $title, $description, $course_objectives,
                            $learning_outcomes, $prerequisites, $course_requirements, $grading_system,
                            $course_policies, $academic_integrity, $attendance_policy, $late_submission_policy,
                            $office_hours, $contact_information, $textbooks, $course_references, $schedule, $assessment_methods,
                            $course_units, $course_credits, $semester, $academic_year, $class_schedule, $classroom_location,
                            $course_website, $emergency_contact, $disability_accommodations);

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

// Include the unified LMS header
$sidebar_context = 'lms';
include 'includes/lms_header.php';
?>

<!-- Add Enhanced CSS and CKEditor script -->
<link rel="stylesheet" href="../assets/css/syllabus-enhanced.css">
<script src="https://cdn.ckeditor.com/ckeditor5/27.1.0/classic/ckeditor.js"></script>


        <!-- Enhanced Page Header -->
        <div class="mb-8">
            <div class="bg-gradient-to-r from-seait-orange to-orange-600 rounded-xl p-6 text-white shadow-lg">
                <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between">
                    <div class="mb-4 lg:mb-0">
                        <div class="flex items-center mb-2">
                            <div class="bg-white bg-opacity-20 rounded-lg p-2 mr-3">
                                <i class="fas fa-book-open text-xl"></i>
                            </div>
                            <h1 class="text-3xl lg:text-4xl font-bold">Course Syllabus</h1>
                        </div>
                        <p class="text-lg text-orange-100"><?php echo htmlspecialchars($class_data['subject_title'] . ' - Section ' . $class_data['section']); ?></p>
                        <p class="text-sm text-orange-200 mt-1">Create and manage your course syllabus with comprehensive academic standards</p>
                    </div>
                    <div class="flex flex-wrap gap-3">
                        <?php if ($syllabus_data): ?>
                            <a href="syllabus_alignment.php?class_id=<?php echo encrypt_id($class_id); ?>" class="inline-flex items-center px-4 py-3 bg-white bg-opacity-20 text-white text-sm font-medium rounded-lg hover:bg-white hover:text-seait-orange transition-all duration-200 backdrop-blur-sm">
                                <i class="fas fa-link mr-2"></i>Manage Alignments
                            </a>
                            <a href="syllabus_topics.php?class_id=<?php echo encrypt_id($class_id); ?>" class="inline-flex items-center px-4 py-3 bg-white bg-opacity-20 text-white text-sm font-medium rounded-lg hover:bg-white hover:text-seait-orange transition-all duration-200 backdrop-blur-sm">
                                <i class="fas fa-list mr-2"></i>Manage Topics
                            </a>
                            <?php if ($syllabus_data['is_published']): ?>
                                <a href="preview_syllabus.php?class_id=<?php echo encrypt_id($class_id); ?>" target="_blank" class="inline-flex items-center px-4 py-3 bg-white bg-opacity-20 text-white text-sm font-medium rounded-lg hover:bg-white hover:text-seait-orange transition-all duration-200 backdrop-blur-sm">
                                    <i class="fas fa-eye mr-2"></i>View as Student
                                </a>
                                <form method="POST" class="inline">
                                    <input type="hidden" name="action" value="unpublish_syllabus">
                                    <button type="submit" class="inline-flex items-center px-4 py-3 bg-red-500 text-white text-sm font-medium rounded-lg hover:bg-red-600 transition-all duration-200">
                                        <i class="fas fa-eye-slash mr-2"></i>Unpublish
                                    </button>
                                </form>
                            <?php else: ?>
                                <a href="preview_syllabus.php?class_id=<?php echo encrypt_id($class_id); ?>" target="_blank" class="inline-flex items-center px-4 py-3 bg-white bg-opacity-20 text-white text-sm font-medium rounded-lg hover:bg-white hover:text-seait-orange transition-all duration-200 backdrop-blur-sm">
                                    <i class="fas fa-eye mr-2"></i>Preview
                                </a>
                                <form method="POST" class="inline">
                                    <input type="hidden" name="action" value="publish_syllabus">
                                    <button type="submit" class="inline-flex items-center px-4 py-3 bg-green-500 text-white text-sm font-medium rounded-lg hover:bg-green-600 transition-all duration-200">
                                        <i class="fas fa-check mr-2"></i>Publish
                                    </button>
                                </form>
                            <?php endif; ?>
                        <?php endif; ?>
                        <a href="class_dashboard.php?class_id=<?php echo encrypt_id($class_id); ?>" class="inline-flex items-center px-4 py-3 bg-white bg-opacity-20 text-white text-sm font-medium rounded-lg hover:bg-white hover:text-seait-orange transition-all duration-200 backdrop-blur-sm">
                            <i class="fas fa-arrow-left mr-2"></i>Back to Dashboard
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($message): ?>
        <div class="mb-6 animate-fade-in">
            <div class="p-4 rounded-xl border-2 <?php echo $message_type === 'success' ? 'bg-green-50 border-green-200 text-green-800' : 'bg-red-50 border-red-200 text-red-800'; ?> shadow-sm">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 rounded-full <?php echo $message_type === 'success' ? 'bg-green-100' : 'bg-red-100'; ?> flex items-center justify-center">
                            <i class="fas <?php echo $message_type === 'success' ? 'fa-check text-green-600' : 'fa-exclamation-triangle text-red-600'; ?> text-sm"></i>
                        </div>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-semibold"><?php echo $message; ?></p>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Enhanced Syllabus Status -->
        <?php if ($syllabus_data): ?>
        <div class="mb-8">
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="p-6">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <div class="w-12 h-12 rounded-full <?php echo $syllabus_data['is_published'] ? 'bg-green-100' : 'bg-yellow-100'; ?> flex items-center justify-center mr-4">
                                <i class="fas <?php echo $syllabus_data['is_published'] ? 'fa-check-circle text-green-600' : 'fa-clock text-yellow-600'; ?> text-xl"></i>
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold <?php echo $syllabus_data['is_published'] ? 'text-green-800' : 'text-yellow-800'; ?>">
                                    Syllabus <?php echo $syllabus_data['is_published'] ? 'Published' : 'Draft'; ?>
                                </h3>
                                <p class="text-sm <?php echo $syllabus_data['is_published'] ? 'text-green-600' : 'text-yellow-600'; ?>">
                                    <?php if ($syllabus_data['is_published']): ?>
                                        <i class="fas fa-calendar-check mr-1"></i>
                                        Published on <?php echo date('M j, Y g:i A', strtotime($syllabus_data['published_at'])); ?>
                                    <?php else: ?>
                                        <i class="fas fa-edit mr-1"></i>
                                        Last updated on <?php echo date('M j, Y g:i A', strtotime($syllabus_data['updated_at'] ?: $syllabus_data['created_at'])); ?>
                                    <?php endif; ?>
                                </p>
                            </div>
                        </div>
                        <div class="text-right">
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium <?php echo $syllabus_data['is_published'] ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'; ?>">
                                <span class="w-2 h-2 rounded-full <?php echo $syllabus_data['is_published'] ? 'bg-green-400' : 'bg-yellow-400'; ?> mr-2"></span>
                                <?php echo $syllabus_data['is_published'] ? 'Active' : 'Draft'; ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Enhanced Syllabus Form -->
        <div class="bg-white rounded-xl shadow-lg border border-gray-200 overflow-hidden">
            <div class="px-8 py-6 border-b border-gray-200 bg-gradient-to-r from-gray-50 to-gray-100">
                <div class="flex items-center">
                    <div class="bg-seait-orange bg-opacity-10 rounded-lg p-3 mr-4">
                        <i class="fas fa-edit text-seait-orange text-xl"></i>
                    </div>
                    <div>
                        <h2 class="text-2xl font-bold text-seait-dark">Syllabus Information</h2>
                        <p class="text-gray-600 mt-1">Follow the standard academic syllabus outline below to create a comprehensive course syllabus</p>
                    </div>
                </div>
            </div>
            <form method="POST" class="p-6">
                <input type="hidden" name="action" value="save_syllabus">

                <!-- Section 1: Course Information -->
                <div class="mb-10">
                    <div class="flex items-center mb-6">
                        <div class="bg-blue-100 rounded-lg p-2 mr-3">
                            <i class="fas fa-info-circle text-blue-600"></i>
                        </div>
                        <h3 class="text-xl font-bold text-seait-dark">1. Course Information</h3>
                    </div>
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                            <label for="title" class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-book mr-1 text-seait-orange"></i>Course Title *
                            </label>
                            <input type="text" id="title" name="title" required
                                   value="<?php echo htmlspecialchars($syllabus_data['title'] ?? ''); ?>"
                                   placeholder="e.g., Introduction to Computer Science"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-seait-orange focus:border-seait-orange transition-all duration-200 bg-white shadow-sm">
                        </div>

                        <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                            <label for="description" class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-align-left mr-1 text-seait-orange"></i>Course Description
                            </label>
                            <textarea id="description" name="description" rows="4"
                                      placeholder="Provide a comprehensive overview of the course content, scope, and approach..."
                                      class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-seait-orange focus:border-seait-orange transition-all duration-200 bg-white shadow-sm resize-none"><?php echo htmlspecialchars($syllabus_data['description'] ?? ''); ?></textarea>
                        </div>

                        <div>
                            <label for="course_units" class="block text-sm font-medium text-gray-700 mb-2">Course Units</label>
                            <input type="text" id="course_units" name="course_units"
                                   value="<?php echo htmlspecialchars($syllabus_data['course_units'] ?? ''); ?>"
                                   placeholder="e.g., 3 units"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange focus:border-seait-orange transition-colors">
                        </div>

                        <div>
                            <label for="course_credits" class="block text-sm font-medium text-gray-700 mb-2">Course Credits</label>
                            <input type="text" id="course_credits" name="course_credits"
                                   value="<?php echo htmlspecialchars($syllabus_data['course_credits'] ?? ''); ?>"
                                   placeholder="e.g., 3 credits"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange focus:border-seait-orange transition-colors">
                        </div>

                        <div>
                            <label for="semester" class="block text-sm font-medium text-gray-700 mb-2">Semester</label>
                            <input type="text" id="semester" name="semester"
                                   value="<?php echo htmlspecialchars($syllabus_data['semester'] ?? ''); ?>"
                                   placeholder="e.g., First Semester, Second Semester, Summer"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange focus:border-seait-orange transition-colors">
                        </div>

                        <div>
                            <label for="academic_year" class="block text-sm font-medium text-gray-700 mb-2">Academic Year</label>
                            <input type="text" id="academic_year" name="academic_year"
                                   value="<?php echo htmlspecialchars($syllabus_data['academic_year'] ?? ''); ?>"
                                   placeholder="e.g., 2024-2025"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange focus:border-seait-orange transition-colors">
                        </div>
                    </div>
                </div>

                <!-- Section 2: Course Objectives and Learning Outcomes -->
                <div class="mb-8">
                    <h3 class="text-lg font-semibold text-seait-dark mb-4 border-b border-gray-200 pb-2">2. Course Objectives and Learning Outcomes</h3>
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <div>
                            <label for="course_objectives" class="block text-sm font-medium text-gray-700 mb-2">Course Objectives</label>
                            <textarea id="course_objectives" name="course_objectives" rows="6"
                                      placeholder="List the main goals and objectives of this course. What should students understand by the end of the semester?..."
                                      class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange focus:border-seait-orange transition-colors"><?php echo htmlspecialchars($syllabus_data['course_objectives'] ?? ''); ?></textarea>
                        </div>

                        <div>
                            <label for="learning_outcomes" class="block text-sm font-medium text-gray-700 mb-2">Learning Outcomes</label>
                            <textarea id="learning_outcomes" name="learning_outcomes" rows="6"
                                      placeholder="Specify what students will be able to do upon completion of this course. Use action verbs like 'analyze', 'evaluate', 'create'..."
                                      class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange focus:border-seait-orange transition-colors"><?php echo htmlspecialchars($syllabus_data['learning_outcomes'] ?? ''); ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- Section 3: Prerequisites and Requirements -->
                <div class="mb-8">
                    <h3 class="text-lg font-semibold text-seait-dark mb-4 border-b border-gray-200 pb-2">3. Prerequisites and Requirements</h3>
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <div>
                            <label for="prerequisites" class="block text-sm font-medium text-gray-700 mb-2">Prerequisites</label>
                            <textarea id="prerequisites" name="prerequisites" rows="4"
                                      placeholder="List any courses, skills, or knowledge that students should have before taking this course..."
                                      class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange focus:border-seait-orange transition-colors"><?php echo htmlspecialchars($syllabus_data['prerequisites'] ?? ''); ?></textarea>
                        </div>

                        <div>
                            <label for="course_requirements" class="block text-sm font-medium text-gray-700 mb-2">Course Requirements</label>
                            <textarea id="course_requirements" name="course_requirements" rows="4"
                                      placeholder="Specify what students need to bring, prepare, or have access to for this course..."
                                      class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange focus:border-seait-orange transition-colors"><?php echo htmlspecialchars($syllabus_data['course_requirements'] ?? ''); ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- Section 4: Course Materials and Resources -->
                <div class="mb-8">
                    <h3 class="text-lg font-semibold text-seait-dark mb-4 border-b border-gray-200 pb-2">4. Course Materials and Resources</h3>
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <div>
                            <label for="textbooks" class="block text-sm font-medium text-gray-700 mb-2">Required Textbooks</label>
                            <textarea id="textbooks" name="textbooks" rows="4"
                                      placeholder="List required textbooks with author, title, edition, and ISBN if applicable..."
                                      class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange focus:border-seait-orange transition-colors"><?php echo htmlspecialchars($syllabus_data['textbooks'] ?? ''); ?></textarea>
                        </div>

                        <div>
                            <label for="references" class="block text-sm font-medium text-gray-700 mb-2">Additional References</label>
                            <textarea id="references" name="references" rows="4"
                                      placeholder="List recommended readings, online resources, software, or other materials..."
                                      class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange focus:border-seait-orange transition-colors"><?php echo htmlspecialchars($syllabus_data['course_references'] ?? ''); ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- Section 5: Assessment and Grading -->
                <div class="mb-8">
                    <h3 class="text-lg font-semibold text-seait-dark mb-4 border-b border-gray-200 pb-2">5. Assessment and Grading</h3>
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <div>
                            <label for="assessment_methods" class="block text-sm font-medium text-gray-700 mb-2">Assessment Methods</label>
                            <textarea id="assessment_methods" name="assessment_methods" rows="4"
                                      placeholder="Describe the types of assessments (exams, projects, presentations, etc.) and their purposes..."
                                      class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange focus:border-seait-orange transition-colors"><?php echo htmlspecialchars($syllabus_data['assessment_methods'] ?? ''); ?></textarea>
                        </div>

                        <div>
                            <label for="grading_system" class="block text-sm font-medium text-gray-700 mb-2">Grading System</label>
                            <textarea id="grading_system" name="grading_system" rows="4"
                                      placeholder="Explain how grades are calculated, including percentages for different components..."
                                      class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange focus:border-seait-orange transition-colors"><?php echo htmlspecialchars($syllabus_data['grading_system'] ?? ''); ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- Section 6: Course Policies -->
                <div class="mb-8">
                    <h3 class="text-lg font-semibold text-seait-dark mb-4 border-b border-gray-200 pb-2">6. Course Policies</h3>
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <div>
                            <label for="attendance_policy" class="block text-sm font-medium text-gray-700 mb-2">Attendance Policy</label>
                            <textarea id="attendance_policy" name="attendance_policy" rows="3"
                                      placeholder="Specify attendance requirements, consequences of absences, and make-up policies..."
                                      class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange focus:border-seait-orange transition-colors"><?php echo htmlspecialchars($syllabus_data['attendance_policy'] ?? ''); ?></textarea>
                        </div>

                        <div>
                            <label for="late_submission_policy" class="block text-sm font-medium text-gray-700 mb-2">Late Submission Policy</label>
                            <textarea id="late_submission_policy" name="late_submission_policy" rows="3"
                                      placeholder="Explain policies for late assignments, extensions, and penalties..."
                                      class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange focus:border-seait-orange transition-colors"><?php echo htmlspecialchars($syllabus_data['late_submission_policy'] ?? ''); ?></textarea>
                        </div>

                        <div>
                            <label for="academic_integrity" class="block text-sm font-medium text-gray-700 mb-2">Academic Integrity Policy</label>
                            <textarea id="academic_integrity" name="academic_integrity" rows="3"
                                      placeholder="Define plagiarism, cheating, and consequences for academic dishonesty..."
                                      class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange focus:border-seait-orange transition-colors"><?php echo htmlspecialchars($syllabus_data['academic_integrity'] ?? ''); ?></textarea>
                        </div>

                        <div>
                            <label for="course_policies" class="block text-sm font-medium text-gray-700 mb-2">General Course Policies</label>
                            <textarea id="course_policies" name="course_policies" rows="3"
                                      placeholder="Include any other course-specific policies (participation, technology use, etc.)..."
                                      class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange focus:border-seait-orange transition-colors"><?php echo htmlspecialchars($syllabus_data['course_policies'] ?? ''); ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- Section 7: Course Schedule -->
                <div class="mb-8">
                    <h3 class="text-lg font-semibold text-seait-dark mb-4 border-b border-gray-200 pb-2">7. Course Schedule</h3>
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <div>
                            <label for="class_schedule" class="block text-sm font-medium text-gray-700 mb-2">Class Schedule</label>
                            <textarea id="class_schedule" name="class_schedule" rows="3"
                                      placeholder="e.g., Monday and Wednesday 9:00 AM - 10:30 AM"
                                      class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange focus:border-seait-orange transition-colors"><?php echo htmlspecialchars($syllabus_data['class_schedule'] ?? ''); ?></textarea>
                        </div>

                        <div>
                            <label for="classroom_location" class="block text-sm font-medium text-gray-700 mb-2">Classroom Location</label>
                            <input type="text" id="classroom_location" name="classroom_location"
                                   value="<?php echo htmlspecialchars($syllabus_data['classroom_location'] ?? ''); ?>"
                                   placeholder="e.g., Room 101, Building A"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange focus:border-seait-orange transition-colors">
                        </div>
                    </div>
                    <div class="mt-6">
                        <label for="schedule" class="block text-sm font-medium text-gray-700 mb-2">Weekly Schedule</label>
                        <textarea id="schedule" name="schedule" rows="8"
                                  placeholder="Provide a week-by-week breakdown of topics, readings, assignments, and important dates..."
                                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange focus:border-seait-orange transition-colors"><?php echo htmlspecialchars($syllabus_data['schedule'] ?? ''); ?></textarea>
                    </div>
                </div>

                <!-- Section 8: Instructor Information -->
                <div class="mb-8">
                    <h3 class="text-lg font-semibold text-seait-dark mb-4 border-b border-gray-200 pb-2">8. Instructor Information</h3>
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <div>
                            <label for="office_hours" class="block text-sm font-medium text-gray-700 mb-2">Office Hours</label>
                            <textarea id="office_hours" name="office_hours" rows="3"
                                      placeholder="Specify your office hours, location, and appointment scheduling process..."
                                      class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange focus:border-seait-orange transition-colors"><?php echo htmlspecialchars($syllabus_data['office_hours'] ?? ''); ?></textarea>
                        </div>

                        <div>
                            <label for="contact_information" class="block text-sm font-medium text-gray-700 mb-2">Contact Information</label>
                            <textarea id="contact_information" name="contact_information" rows="3"
                                      placeholder="Provide your email, phone, preferred contact method, and response time expectations..."
                                      class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange focus:border-seait-orange transition-colors"><?php echo htmlspecialchars($syllabus_data['contact_information'] ?? ''); ?></textarea>
                        </div>

                        <div>
                            <label for="course_website" class="block text-sm font-medium text-gray-700 mb-2">Course Website</label>
                            <input type="url" id="course_website" name="course_website"
                                   value="<?php echo htmlspecialchars($syllabus_data['course_website'] ?? ''); ?>"
                                   placeholder="https://example.com/course"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange focus:border-seait-orange transition-colors">
                        </div>

                        <div>
                            <label for="emergency_contact" class="block text-sm font-medium text-gray-700 mb-2">Emergency Contact</label>
                            <textarea id="emergency_contact" name="emergency_contact" rows="3"
                                      placeholder="Emergency contact information and procedures..."
                                      class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange focus:border-seait-orange transition-colors"><?php echo htmlspecialchars($syllabus_data['emergency_contact'] ?? ''); ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- Section 9: Additional Information -->
                <div class="mb-8">
                    <h3 class="text-lg font-semibold text-seait-dark mb-4 border-b border-gray-200 pb-2">9. Additional Information</h3>
                    <div>
                        <label for="disability_accommodations" class="block text-sm font-medium text-gray-700 mb-2">Disability Accommodations</label>
                        <textarea id="disability_accommodations" name="disability_accommodations" rows="4"
                                  placeholder="Information about disability accommodations and support services..."
                                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange focus:border-seait-orange transition-colors"><?php echo htmlspecialchars($syllabus_data['disability_accommodations'] ?? ''); ?></textarea>
                    </div>
                </div>

                <!-- Enhanced Submit Button -->
                <div class="mt-10 flex justify-end">
                    <div class="bg-gradient-to-r from-seait-orange to-orange-600 rounded-xl p-1 shadow-lg">
                        <button type="submit" class="inline-flex items-center px-8 py-4 bg-white text-seait-orange text-base font-semibold rounded-lg hover:bg-gray-50 transition-all duration-200 transform hover:scale-105">
                            <i class="fas fa-save mr-3 text-lg"></i>Save Syllabus
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <style>
            .animate-fade-in {
                animation: fadeIn 0.5s ease-in-out;
            }
            
            @keyframes fadeIn {
                from { opacity: 0; transform: translateY(-10px); }
                to { opacity: 1; transform: translateY(0); }
            }
            
            .form-section {
                transition: all 0.3s ease;
            }
            
            .form-section:hover {
                transform: translateY(-2px);
            }
            
            .enhanced-input {
                transition: all 0.2s ease;
            }
            
            .enhanced-input:focus {
                transform: scale(1.02);
                box-shadow: 0 4px 12px rgba(249, 115, 22, 0.15);
            }
        </style>

        <script>
            // Initialize CKEditor for rich text areas
            document.addEventListener('DOMContentLoaded', function() {
                const textareas = [
                    'description', 'course_objectives', 'learning_outcomes', 'prerequisites', 
                    'course_requirements', 'textbooks', 'references', 'assessment_methods',
                    'grading_system', 'attendance_policy', 'late_submission_policy', 
                    'academic_integrity', 'course_policies', 'schedule', 'class_schedule',
                    'office_hours', 'contact_information', 'emergency_contact', 
                    'disability_accommodations'
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

<?php include 'includes/unified-footer.php'; ?>