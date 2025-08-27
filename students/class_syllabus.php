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
                f.id as teacher_id, f.first_name as teacher_first_name, f.last_name as teacher_last_name,
                f.email as teacher_email
                FROM class_enrollments ce
                JOIN teacher_classes tc ON ce.class_id = tc.id
                JOIN course_curriculum cc ON tc.subject_id = cc.id
                JOIN faculty f ON tc.teacher_id = f.id
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

<!-- Add Enhanced CSS -->
<link rel="stylesheet" href="../assets/css/syllabus-enhanced.css">

<!-- Enhanced Page Header -->
<div class="mb-8">
    <div class="bg-gradient-to-r from-indigo-600 to-indigo-700 rounded-xl p-6 text-white shadow-lg">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between">
            <div class="mb-4 lg:mb-0">
                <div class="flex items-center mb-2">
                    <div class="bg-white bg-opacity-20 rounded-lg p-2 mr-3">
                        <i class="fas fa-book-open text-xl"></i>
                    </div>
                    <h1 class="text-3xl lg:text-4xl font-bold">Course Syllabus</h1>
                </div>
                <p class="text-lg text-indigo-100"><?php echo htmlspecialchars($class_data['subject_title']); ?></p>
                <p class="text-sm text-indigo-200 mt-1">Comprehensive course information, objectives, and learning outcomes</p>
            </div>
            <div class="flex items-center">
                <div class="bg-white bg-opacity-20 rounded-lg px-4 py-2">
                    <span class="text-sm font-medium">Section <?php echo htmlspecialchars($class_data['section']); ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if (!$syllabus_data): ?>
    <!-- Enhanced No Syllabus Available -->
    <div class="bg-white rounded-xl shadow-lg border border-gray-200 overflow-hidden">
        <div class="p-12 text-center">
            <div class="bg-gray-100 rounded-full w-24 h-24 flex items-center justify-center mx-auto mb-6">
                <i class="fas fa-file-alt text-gray-400 text-3xl"></i>
            </div>
            <h3 class="text-2xl font-bold text-gray-900 mb-3">No Syllabus Available</h3>
            <p class="text-gray-600 mb-6 text-lg">Your teacher hasn't published a syllabus for this course yet.</p>
            <div class="bg-blue-50 rounded-lg p-4 max-w-md mx-auto">
                <p class="text-sm text-blue-800">
                    <i class="fas fa-info-circle mr-2"></i>
                    Check back later or contact your instructor for course information.
                </p>
            </div>
        </div>
    </div>
<?php else: ?>
    <!-- Syllabus Content -->
    <div class="space-y-6">
        <!-- Enhanced Syllabus Header -->
        <div class="bg-white rounded-xl shadow-lg border border-gray-200 overflow-hidden">
            <div class="px-8 py-6 bg-gradient-to-r from-indigo-600 to-indigo-700">
                <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between">
                    <div class="mb-4 lg:mb-0">
                        <h2 class="text-2xl lg:text-3xl font-bold text-white mb-2"><?php echo htmlspecialchars($syllabus_data['title']); ?></h2>
                        <p class="text-indigo-100 text-lg"><?php echo htmlspecialchars($class_data['subject_title'] . ' - ' . $class_data['section']); ?></p>
                    </div>
                    <div class="text-right text-indigo-100">
                        <div class="bg-white bg-opacity-20 rounded-lg px-4 py-2">
                            <p class="text-sm">Published on</p>
                            <p class="font-semibold"><?php echo date('M j, Y', strtotime($syllabus_data['published_at'])); ?></p>
                        </div>
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

        <!-- Course Objectives and Learning Outcomes -->
        <?php if ($syllabus_data['course_objectives'] || $syllabus_data['learning_outcomes']): ?>
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Course Objectives and Learning Outcomes</h3>
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
                <h3 class="text-lg font-medium text-gray-900">Prerequisites and Requirements</h3>
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

        <!-- Course Materials and Resources -->
        <?php if ($syllabus_data['textbooks'] || $syllabus_data['references']): ?>
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Course Materials and Resources</h3>
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

                                                <?php if ($syllabus_data['course_references']): ?>
                            <div>
                                <h4 class="font-medium text-gray-900 mb-3">Additional References</h4>
                                <div class="prose max-w-none text-gray-700">
                                    <?php echo nl2br(htmlspecialchars($syllabus_data['course_references'])); ?>
                                </div>
                            </div>
                            <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Assessment and Grading -->
        <?php if ($syllabus_data['assessment_methods'] || $syllabus_data['grading_system']): ?>
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Assessment and Grading</h3>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <?php if ($syllabus_data['assessment_methods']): ?>
                    <div>
                        <h4 class="font-medium text-gray-900 mb-3">Assessment Methods</h4>
                        <div class="prose max-w-none text-gray-700">
                            <?php echo nl2br(htmlspecialchars($syllabus_data['assessment_methods'])); ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ($syllabus_data['grading_system']): ?>
                    <div>
                        <h4 class="font-medium text-gray-900 mb-3">Grading System</h4>
                        <div class="prose max-w-none text-gray-700">
                            <?php echo nl2br(htmlspecialchars($syllabus_data['grading_system'])); ?>
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
                            <h4 class="font-medium text-gray-900"><?php echo htmlspecialchars($topic['topic_title']); ?></h4>
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
                            <?php if ($topic['learning_objectives']): ?>
                            <div>
                                <span class="font-medium text-gray-700">Learning Objectives:</span>
                                <p class="text-gray-600 mt-1"><?php echo htmlspecialchars($topic['learning_objectives']); ?></p>
                            </div>
                            <?php endif; ?>

                            <?php if ($topic['materials']): ?>
                            <div>
                                <span class="font-medium text-gray-700">Materials:</span>
                                <p class="text-gray-600 mt-1"><?php echo htmlspecialchars($topic['materials']); ?></p>
                            </div>
                            <?php endif; ?>

                            <?php if ($topic['activities']): ?>
                            <div>
                                <span class="font-medium text-gray-700">Activities:</span>
                                <p class="text-gray-600 mt-1"><?php echo htmlspecialchars($topic['activities']); ?></p>
                            </div>
                            <?php endif; ?>

                            <?php if ($topic['assessment']): ?>
                            <div>
                                <span class="font-medium text-gray-700">Assessment:</span>
                                <p class="text-gray-600 mt-1"><?php echo htmlspecialchars($topic['assessment']); ?></p>
                            </div>
                            <?php endif; ?>

                            <?php if ($topic['references_field']): ?>
                            <div>
                                <span class="font-medium text-gray-700">References:</span>
                                <p class="text-gray-600 mt-1"><?php echo htmlspecialchars($topic['references_field']); ?></p>
                            </div>
                            <?php endif; ?>

                            <?php if ($topic['values_integration']): ?>
                            <div>
                                <span class="font-medium text-gray-700">Values Integration:</span>
                                <p class="text-gray-600 mt-1"><?php echo htmlspecialchars($topic['values_integration']); ?></p>
                            </div>
                            <?php endif; ?>

                            <?php if ($topic['target']): ?>
                            <div>
                                <span class="font-medium text-gray-700">Target:</span>
                                <p class="text-gray-600 mt-1"><?php echo htmlspecialchars($topic['target']); ?></p>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- CLO Alignments Display -->
                        <?php
                        // Get CLO alignments for this topic
                        $topic_clo_query = "SELECT clo.clo_code, clo.clo_description 
                                           FROM syllabus_topic_clo_alignment tca 
                                           JOIN syllabus_clos clo ON tca.clo_id = clo.id 
                                           WHERE tca.syllabus_id = ? AND tca.topic_id = ? AND tca.is_aligned = 1";
                        $topic_clo_stmt = mysqli_prepare($conn, $topic_clo_query);
                        mysqli_stmt_bind_param($topic_clo_stmt, "ii", $syllabus_data['id'], $topic['id']);
                        mysqli_stmt_execute($topic_clo_stmt);
                        $topic_clo_result = mysqli_stmt_get_result($topic_clo_stmt);
                        
                        if (mysqli_num_rows($topic_clo_result) > 0):
                        ?>
                        <div class="mt-3 pt-3 border-t border-gray-200">
                            <h5 class="font-medium text-gray-900 mb-2">Aligned Course Learning Outcomes:</h5>
                            <div class="flex flex-wrap gap-2">
                                <?php while ($aligned_clo = mysqli_fetch_assoc($topic_clo_result)): ?>
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                    <strong><?php echo htmlspecialchars($aligned_clo['clo_code']); ?></strong>
                                    <span class="ml-1 text-gray-600"><?php echo htmlspecialchars(substr($aligned_clo['clo_description'], 0, 30)) . '...'; ?></span>
                                </span>
                                <?php endwhile; ?>
                            </div>
                        </div>
                        <?php endif; ?>
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