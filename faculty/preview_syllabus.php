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

if (empty($encrypted_class_id)) {
    header('Location: class-management.php?message=' . urlencode('No class ID provided.') . '&type=error');
    exit();
}

$class_id = safe_decrypt_id($encrypted_class_id);

if (!$class_id) {
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

// Get syllabus data (including unpublished for preview)
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

// Set page title
$page_title = 'Syllabus Preview';

// Include the unified LMS header
$sidebar_context = 'lms';
include 'includes/lms_header.php';
?>

<!-- Preview Header -->
<div class="mb-6 sm:mb-8">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-seait-dark mb-2">Syllabus Preview</h1>
            <p class="text-sm sm:text-base text-gray-600"><?php echo htmlspecialchars($class_data['subject_title'] . ' - Section ' . $class_data['section']); ?></p>
            <p class="text-sm text-orange-600 mt-1">
                <i class="fas fa-eye mr-1"></i>This is how students will see your syllabus
                <?php if (!$syllabus_data || !$syllabus_data['is_published']): ?>
                    <span class="font-medium">(Currently unpublished)</span>
                <?php endif; ?>
            </p>
        </div>
        <div class="mt-4 sm:mt-0 flex flex-wrap gap-3">
            <a href="class_syllabus.php?class_id=<?php echo encrypt_id($class_id); ?>" class="inline-flex items-center px-4 py-2 bg-gray-600 text-white text-sm font-medium rounded-lg hover:bg-gray-700 transition-colors">
                <i class="fas fa-edit mr-2"></i>Edit Syllabus
            </a>
            <a href="class_dashboard.php?class_id=<?php echo encrypt_id($class_id); ?>" class="inline-flex items-center px-4 py-2 bg-seait-orange text-white text-sm font-medium rounded-lg hover:bg-orange-600 transition-colors">
                <i class="fas fa-arrow-left mr-2"></i>Back to Dashboard
            </a>
        </div>
    </div>
</div>

<?php if (!$syllabus_data): ?>
    <!-- No Syllabus Available -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="p-8 text-center">
            <i class="fas fa-file-alt text-gray-300 text-6xl mb-4"></i>
            <h3 class="text-lg font-medium text-gray-900 mb-2">No Syllabus Available</h3>
            <p class="text-gray-500 mb-4">You haven't created a syllabus for this course yet.</p>
            <a href="class_syllabus.php?class_id=<?php echo encrypt_id($class_id); ?>" class="inline-flex items-center px-4 py-2 bg-seait-orange text-white text-sm font-medium rounded-lg hover:bg-orange-600 transition-colors">
                <i class="fas fa-plus mr-2"></i>Create Syllabus
            </a>
        </div>
    </div>
<?php else: ?>
    <!-- Syllabus Content -->
    <div class="space-y-6">
        <!-- Syllabus Header -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="px-6 py-4 bg-gradient-to-r from-seait-orange to-orange-600">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-xl font-bold text-white"><?php echo htmlspecialchars($syllabus_data['title']); ?></h2>
                        <p class="text-orange-100 mt-1"><?php echo htmlspecialchars($class_data['subject_title'] . ' - ' . $class_data['section']); ?></p>
                    </div>
                    <div class="text-right text-orange-100">
                        <p class="text-sm">Last updated</p>
                        <p class="font-medium"><?php echo date('M j, Y', strtotime($syllabus_data['updated_at'] ?: $syllabus_data['created_at'])); ?></p>
                        <?php if ($syllabus_data['is_published']): ?>
                            <p class="text-sm mt-1">Published on <?php echo date('M j, Y', strtotime($syllabus_data['published_at'])); ?></p>
                        <?php else: ?>
                            <p class="text-sm mt-1 text-orange-200">Draft - Not published</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="p-6">
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
        </div>

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

        <!-- Course Topics & Schedule -->
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
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-seait-orange bg-opacity-10 text-seait-orange">
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
                                <h5 class="font-medium text-gray-900 mb-1">Learning Objectives</h5>
                                <p class="text-gray-600"><?php echo htmlspecialchars($topic['learning_objectives']); ?></p>
                            </div>
                            <?php endif; ?>

                            <?php if ($topic['materials']): ?>
                            <div>
                                <h5 class="font-medium text-gray-900 mb-1">Materials</h5>
                                <p class="text-gray-600"><?php echo htmlspecialchars($topic['materials']); ?></p>
                            </div>
                            <?php endif; ?>

                            <?php if ($topic['activities']): ?>
                            <div>
                                <h5 class="font-medium text-gray-900 mb-1">Activities</h5>
                                <p class="text-gray-600"><?php echo htmlspecialchars($topic['activities']); ?></p>
                            </div>
                            <?php endif; ?>

                            <?php if ($topic['assessment']): ?>
                            <div>
                                <h5 class="font-medium text-gray-900 mb-1">Assessment</h5>
                                <p class="text-gray-600"><?php echo htmlspecialchars($topic['assessment']); ?></p>
                            </div>
                            <?php endif; ?>

                            <?php if ($topic['references_field']): ?>
                            <div>
                                <h5 class="font-medium text-gray-900 mb-1">References</h5>
                                <p class="text-gray-600"><?php echo htmlspecialchars($topic['references_field']); ?></p>
                            </div>
                            <?php endif; ?>

                            <?php if ($topic['values_integration']): ?>
                            <div>
                                <h5 class="font-medium text-gray-900 mb-1">Values Integration</h5>
                                <p class="text-gray-600"><?php echo htmlspecialchars($topic['values_integration']); ?></p>
                            </div>
                            <?php endif; ?>

                            <?php if ($topic['target']): ?>
                            <div>
                                <h5 class="font-medium text-gray-900 mb-1">Target</h5>
                                <p class="text-gray-600"><?php echo htmlspecialchars($topic['target']); ?></p>
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

        <!-- Syllabus Attachments -->
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

<?php include 'includes/unified-footer.php'; ?>
