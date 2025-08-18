<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and has student role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../login.php');
    exit();
}

// Get class_id from URL
$class_id = isset($_GET['class_id']) ? (int)$_GET['class_id'] : null;

if (!$class_id) {
    header('Location: my-classes.php');
    exit();
}

// Get student_id for verification
$student_id = get_student_id($conn, $_SESSION['email']);

// Verify student is enrolled in this class
$class_query = "SELECT ce.*, tc.section, tc.join_code, tc.status as class_status,
                cc.subject_title, cc.subject_code, cc.units, cc.description as subject_description,
                u.id as teacher_id, u.first_name as teacher_first_name, u.last_name as teacher_last_name,
                u.email as teacher_email
                FROM class_enrollments ce
                JOIN teacher_classes tc ON ce.class_id = tc.id
                JOIN course_curriculum cc ON tc.subject_id = cc.id
                JOIN users u ON tc.teacher_id = u.id
                WHERE ce.class_id = ? AND ce.student_id = ? AND ce.status = 'enrolled'";
$class_stmt = mysqli_prepare($conn, $class_query);
mysqli_stmt_bind_param($class_stmt, "ii", $class_id, $student_id);
mysqli_stmt_execute($class_stmt);
$class_result = mysqli_stmt_get_result($class_stmt);
$class_data = mysqli_fetch_assoc($class_result);

if (!$class_data) {
    header('Location: my-classes.php');
    exit();
}

// Get published syllabus data
$syllabus_query = "SELECT * FROM class_syllabus WHERE class_id = ? AND is_published = 1";
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

// Set page title
$page_title = 'Course Syllabus - ' . $class_data['subject_title'];

// Include the shared LMS header
include 'includes/lms_header.php';
?>

<div class="mb-6 sm:mb-8">
    <h1 class="text-2xl sm:text-3xl font-bold text-seait-dark mb-2">Course Syllabus</h1>
    <p class="text-sm sm:text-base text-gray-600">Course information and policies for <?php echo htmlspecialchars($class_data['subject_title']); ?></p>
</div>

<?php if (!$syllabus_data): ?>
    <!-- No Syllabus Available -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="p-8 text-center">
            <i class="fas fa-file-alt text-gray-300 text-6xl mb-4"></i>
            <h3 class="text-lg font-medium text-gray-900 mb-2">No Syllabus Available</h3>
            <p class="text-gray-500 mb-4">Your teacher hasn't published a syllabus for this course yet.</p>
            <p class="text-sm text-gray-400">Check back later or contact your instructor for course information.</p>
        </div>
    </div>
<?php else: ?>
    <!-- Syllabus Content -->
    <div class="space-y-6">
        <!-- Syllabus Header -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="px-6 py-4 bg-gradient-to-r from-blue-600 to-blue-700">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-xl font-bold text-white"><?php echo htmlspecialchars($syllabus_data['title']); ?></h2>
                        <p class="text-blue-100 mt-1"><?php echo htmlspecialchars($class_data['subject_title'] . ' - ' . $class_data['section']); ?></p>
                    </div>
                    <div class="text-right text-blue-100">
                        <p class="text-sm">Published on</p>
                        <p class="font-medium"><?php echo date('M j, Y', strtotime($syllabus_data['published_at'])); ?></p>
                    </div>
                </div>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                    <div class="text-center">
                        <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mx-auto mb-2">
                            <i class="fas fa-chalkboard-teacher text-blue-600"></i>
                        </div>
                        <p class="text-sm font-medium text-gray-900">Instructor</p>
                        <p class="text-sm text-gray-600"><?php echo htmlspecialchars($class_data['teacher_first_name'] . ' ' . $class_data['teacher_last_name']); ?></p>
                    </div>
                    <div class="text-center">
                        <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center mx-auto mb-2">
                            <i class="fas fa-graduation-cap text-green-600"></i>
                        </div>
                        <p class="text-sm font-medium text-gray-900">Subject Code</p>
                        <p class="text-sm text-gray-600"><?php echo htmlspecialchars($class_data['subject_code']); ?></p>
                    </div>
                    <div class="text-center">
                        <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center mx-auto mb-2">
                            <i class="fas fa-clock text-purple-600"></i>
                        </div>
                        <p class="text-sm font-medium text-gray-900">Units</p>
                        <p class="text-sm text-gray-600"><?php echo htmlspecialchars($class_data['units']); ?> units</p>
                    </div>
                </div>

                <?php if ($syllabus_data['description']): ?>
                <div class="mb-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-3">Course Description</h3>
                    <div class="prose max-w-none text-gray-700">
                        <?php echo nl2br(htmlspecialchars($syllabus_data['description'])); ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Course Objectives and Outcomes -->
        <?php if ($syllabus_data['course_objectives'] || $syllabus_data['learning_outcomes']): ?>
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Course Objectives & Learning Outcomes</h3>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <?php if ($syllabus_data['course_objectives']): ?>
                    <div>
                        <h4 class="font-medium text-gray-900 mb-3">Course Objectives</h4>
                        <div class="prose max-w-none text-gray-700">
                            <?php echo nl2br(htmlspecialchars($syllabus_data['course_objectives'])); ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ($syllabus_data['learning_outcomes']): ?>
                    <div>
                        <h4 class="font-medium text-gray-900 mb-3">Learning Outcomes</h4>
                        <div class="prose max-w-none text-gray-700">
                            <?php echo nl2br(htmlspecialchars($syllabus_data['learning_outcomes'])); ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Prerequisites and Requirements -->
        <?php if ($syllabus_data['prerequisites'] || $syllabus_data['course_requirements']): ?>
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Prerequisites & Requirements</h3>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <?php if ($syllabus_data['prerequisites']): ?>
                    <div>
                        <h4 class="font-medium text-gray-900 mb-3">Prerequisites</h4>
                        <div class="prose max-w-none text-gray-700">
                            <?php echo nl2br(htmlspecialchars($syllabus_data['prerequisites'])); ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ($syllabus_data['course_requirements']): ?>
                    <div>
                        <h4 class="font-medium text-gray-900 mb-3">Course Requirements</h4>
                        <div class="prose max-w-none text-gray-700">
                            <?php echo nl2br(htmlspecialchars($syllabus_data['course_requirements'])); ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Course Policies -->
        <?php if ($syllabus_data['grading_system'] || $syllabus_data['course_policies'] || $syllabus_data['academic_integrity'] || $syllabus_data['attendance_policy'] || $syllabus_data['late_submission_policy']): ?>
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Course Policies</h3>
            </div>
            <div class="p-6">
                <div class="space-y-6">
                    <?php if ($syllabus_data['grading_system']): ?>
                    <div>
                        <h4 class="font-medium text-gray-900 mb-3">Grading System</h4>
                        <div class="prose max-w-none text-gray-700">
                            <?php echo nl2br(htmlspecialchars($syllabus_data['grading_system'])); ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ($syllabus_data['course_policies']): ?>
                    <div>
                        <h4 class="font-medium text-gray-900 mb-3">General Course Policies</h4>
                        <div class="prose max-w-none text-gray-700">
                            <?php echo nl2br(htmlspecialchars($syllabus_data['course_policies'])); ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ($syllabus_data['academic_integrity']): ?>
                    <div>
                        <h4 class="font-medium text-gray-900 mb-3">Academic Integrity Policy</h4>
                        <div class="prose max-w-none text-gray-700">
                            <?php echo nl2br(htmlspecialchars($syllabus_data['academic_integrity'])); ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ($syllabus_data['attendance_policy']): ?>
                    <div>
                        <h4 class="font-medium text-gray-900 mb-3">Attendance Policy</h4>
                        <div class="prose max-w-none text-gray-700">
                            <?php echo nl2br(htmlspecialchars($syllabus_data['attendance_policy'])); ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ($syllabus_data['late_submission_policy']): ?>
                    <div>
                        <h4 class="font-medium text-gray-900 mb-3">Late Submission Policy</h4>
                        <div class="prose max-w-none text-gray-700">
                            <?php echo nl2br(htmlspecialchars($syllabus_data['late_submission_policy'])); ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Assessment Methods -->
        <?php if ($syllabus_data['assessment_methods']): ?>
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Assessment Methods</h3>
            </div>
            <div class="p-6">
                <div class="prose max-w-none text-gray-700">
                    <?php echo nl2br(htmlspecialchars($syllabus_data['assessment_methods'])); ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Course Resources -->
        <?php if ($syllabus_data['textbooks'] || $syllabus_data['references']): ?>
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Course Resources</h3>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <?php if ($syllabus_data['textbooks']): ?>
                    <div>
                        <h4 class="font-medium text-gray-900 mb-3">Required Textbooks</h4>
                        <div class="prose max-w-none text-gray-700">
                            <?php echo nl2br(htmlspecialchars($syllabus_data['textbooks'])); ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ($syllabus_data['references']): ?>
                    <div>
                        <h4 class="font-medium text-gray-900 mb-3">Additional References</h4>
                        <div class="prose max-w-none text-gray-700">
                            <?php echo nl2br(htmlspecialchars($syllabus_data['references'])); ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Course Schedule -->
        <?php if ($syllabus_data['schedule']): ?>
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Course Schedule</h3>
            </div>
            <div class="p-6">
                <div class="prose max-w-none text-gray-700">
                    <?php echo nl2br(htmlspecialchars($syllabus_data['schedule'])); ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Instructor Information -->
        <?php if ($syllabus_data['office_hours'] || $syllabus_data['contact_information']): ?>
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Instructor Information</h3>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <?php if ($syllabus_data['office_hours']): ?>
                    <div>
                        <h4 class="font-medium text-gray-900 mb-3">Office Hours</h4>
                        <div class="prose max-w-none text-gray-700">
                            <?php echo nl2br(htmlspecialchars($syllabus_data['office_hours'])); ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ($syllabus_data['contact_information']): ?>
                    <div>
                        <h4 class="font-medium text-gray-900 mb-3">Contact Information</h4>
                        <div class="prose max-w-none text-gray-700">
                            <?php echo nl2br(htmlspecialchars($syllabus_data['contact_information'])); ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Syllabus Topics/Units -->
        <?php if ($topics_result && mysqli_num_rows($topics_result) > 0): ?>
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Course Topics & Schedule</h3>
            </div>
            <div class="p-6">
                <div class="space-y-4">
                    <?php while ($topic = mysqli_fetch_assoc($topics_result)): ?>
                    <div class="border border-gray-200 rounded-lg p-4">
                        <div class="flex items-start justify-between mb-3">
                            <h4 class="font-medium text-gray-900"><?php echo htmlspecialchars($topic['title']); ?></h4>
                            <?php if ($topic['week_number']): ?>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                Week <?php echo $topic['week_number']; ?>
                            </span>
                            <?php endif; ?>
                        </div>

                        <?php if ($topic['description']): ?>
                        <p class="text-gray-600 mb-3"><?php echo htmlspecialchars($topic['description']); ?></p>
                        <?php endif; ?>

                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 text-sm">
                            <?php if ($topic['duration_hours']): ?>
                            <div>
                                <span class="font-medium text-gray-700">Duration:</span>
                                <span class="text-gray-600"><?php echo $topic['duration_hours']; ?> hours</span>
                            </div>
                            <?php endif; ?>

                            <?php if ($topic['learning_objectives']): ?>
                            <div>
                                <span class="font-medium text-gray-700">Learning Objectives:</span>
                                <p class="text-gray-600 mt-1"><?php echo htmlspecialchars($topic['learning_objectives']); ?></p>
                            </div>
                            <?php endif; ?>

                            <?php if ($topic['activities']): ?>
                            <div>
                                <span class="font-medium text-gray-700">Activities:</span>
                                <p class="text-gray-600 mt-1"><?php echo htmlspecialchars($topic['activities']); ?></p>
                            </div>
                            <?php endif; ?>

                            <?php if ($topic['assessments']): ?>
                            <div>
                                <span class="font-medium text-gray-700">Assessments:</span>
                                <p class="text-gray-600 mt-1"><?php echo htmlspecialchars($topic['assessments']); ?></p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Syllabus Files -->
        <?php if ($files_result && mysqli_num_rows($files_result) > 0): ?>
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Syllabus Attachments</h3>
            </div>
            <div class="p-6">
                <div class="space-y-3">
                    <?php while ($file = mysqli_fetch_assoc($files_result)): ?>
                    <div class="flex items-center justify-between p-3 border border-gray-200 rounded-lg">
                        <div class="flex items-center">
                            <div class="w-10 h-10 bg-gray-100 rounded-lg flex items-center justify-center mr-3">
                                <i class="fas fa-file text-gray-600"></i>
                            </div>
                            <div>
                                <p class="font-medium text-gray-900"><?php echo htmlspecialchars($file['file_name']); ?></p>
                                <?php if ($file['description']): ?>
                                <p class="text-sm text-gray-600"><?php echo htmlspecialchars($file['description']); ?></p>
                                <?php endif; ?>
                                <p class="text-xs text-gray-500">
                                    <?php echo number_format($file['file_size'] / 1024, 1); ?> KB •
                                    <?php echo date('M j, Y', strtotime($file['uploaded_at'])); ?>
                                </p>
                            </div>
                        </div>
                        <a href="../uploads/syllabus/<?php echo $file['file_path']; ?>"
                           target="_blank"
                           class="inline-flex items-center px-3 py-2 bg-seait-orange text-white text-sm rounded-lg hover:bg-orange-600 transition">
                            <i class="fas fa-download mr-2"></i>Download
                        </a>
                    </div>
                    <?php endwhile; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php
// Include the shared LMS footer
include 'includes/lms_footer.php';
?>