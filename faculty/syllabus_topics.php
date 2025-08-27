<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/id_encryption.php';

// Check if user is logged in and has teacher role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header('Location: ../login.php');
    exit();
}

// Get class_id from URL
$class_id = safe_decrypt_id($_GET['class_id']);

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

// Get syllabus data
$syllabus_query = "SELECT * FROM class_syllabus WHERE class_id = ?";
$syllabus_stmt = mysqli_prepare($conn, $syllabus_query);
mysqli_stmt_bind_param($syllabus_stmt, "i", $class_id);
mysqli_stmt_execute($syllabus_stmt);
$syllabus_result = mysqli_stmt_get_result($syllabus_stmt);
$syllabus_data = mysqli_fetch_assoc($syllabus_result);

if (!$syllabus_data) {
            header('Location: class_syllabus.php?class_id=' . encrypt_id($class_id) . '&error=no_syllabus');
    exit();
}

// Set page title
$page_title = 'Syllabus Topics - ' . $class_data['subject_title'];

$message = '';
$message_type = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_topic':
                $title = sanitize_input($_POST['title']);
                $description = sanitize_input($_POST['description']);
                $week_number = isset($_POST['week_number']) ? (int)$_POST['week_number'] : null;
                $duration_hours = isset($_POST['duration_hours']) ? (int)$_POST['duration_hours'] : null;
                $learning_objectives = sanitize_input($_POST['learning_objectives']);
                $activities = sanitize_input($_POST['activities']);
                $assessments = sanitize_input($_POST['assessments']);
                $materials = sanitize_input($_POST['materials']);
                $references = sanitize_input($_POST['references']);
                $values_integration = sanitize_input($_POST['values_integration']);
                $target = sanitize_input($_POST['target']);
                $order_number = isset($_POST['order_number']) ? (int)$_POST['order_number'] : 0;

                if (empty($title)) {
                    $message = "Please provide a title for the topic.";
                    $message_type = "error";
                } else {
                    $insert_query = "INSERT INTO syllabus_topics (syllabus_id, topic_title, description, week_number,
                                    order_number, learning_objectives, activities, assessment, materials, 
                                    references_field, values_integration, target)
                                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $insert_stmt = mysqli_prepare($conn, $insert_query);
                    mysqli_stmt_bind_param($insert_stmt, "issiisssssss",
                        $syllabus_data['id'], $title, $description, $week_number, $order_number,
                        $learning_objectives, $activities, $assessments, $materials, $references, $values_integration, $target);

                    if (mysqli_stmt_execute($insert_stmt)) {
                        $topic_id = mysqli_insert_id($conn);
                        
                        // Handle CLO alignments
                        $clo_alignments = $_POST['clo_alignment'] ?? [];
                        foreach ($clo_alignments as $clo_id) {
                            $insert_alignment = "INSERT INTO syllabus_topic_clo_alignment (syllabus_id, topic_id, clo_id, is_aligned) VALUES (?, ?, ?, 1)";
                            $alignment_stmt = mysqli_prepare($conn, $insert_alignment);
                            mysqli_stmt_bind_param($alignment_stmt, "iii", $syllabus_data['id'], $topic_id, $clo_id);
                            mysqli_stmt_execute($alignment_stmt);
                        }
                        
                        $message = "Topic added successfully!";
                        $message_type = "success";
                    } else {
                        $message = "Error adding topic: " . mysqli_error($conn);
                        $message_type = "error";
                    }
                }
                break;

            case 'update_topic':
                $topic_id = (int)$_POST['topic_id'];
                $title = sanitize_input($_POST['title']);
                $description = sanitize_input($_POST['description']);
                $week_number = isset($_POST['week_number']) ? (int)$_POST['week_number'] : null;
                $duration_hours = isset($_POST['duration_hours']) ? (int)$_POST['duration_hours'] : null;
                $learning_objectives = sanitize_input($_POST['learning_objectives']);
                $activities = sanitize_input($_POST['activities']);
                $assessments = sanitize_input($_POST['assessments']);
                $materials = sanitize_input($_POST['materials']);
                $references = sanitize_input($_POST['references']);
                $values_integration = sanitize_input($_POST['values_integration']);
                $target = sanitize_input($_POST['target']);
                $order_number = isset($_POST['order_number']) ? (int)$_POST['order_number'] : 0;

                if (empty($title)) {
                    $message = "Please provide a title for the topic.";
                    $message_type = "error";
                } else {
                    $update_query = "UPDATE syllabus_topics SET topic_title = ?, description = ?, week_number = ?,
                                    order_number = ?, learning_objectives = ?, activities = ?, assessment = ?,
                                    materials = ?, references_field = ?, values_integration = ?, target = ?, updated_at = NOW()
                                    WHERE id = ? AND syllabus_id = ?";
                    $update_stmt = mysqli_prepare($conn, $update_query);
                    mysqli_stmt_bind_param($update_stmt, "ssiissssssiis",
                        $title, $description, $week_number, $order_number, $learning_objectives,
                        $activities, $assessments, $materials, $references, $values_integration, $target, $topic_id, $syllabus_data['id']);

                    if (mysqli_stmt_execute($update_stmt)) {
                        // Handle CLO alignments
                        $clo_alignments = $_POST['clo_alignment'] ?? [];
                        
                        // Clear existing alignments for this topic
                        $clear_alignments = "DELETE FROM syllabus_topic_clo_alignment WHERE syllabus_id = ? AND topic_id = ?";
                        $clear_stmt = mysqli_prepare($conn, $clear_alignments);
                        mysqli_stmt_bind_param($clear_stmt, "ii", $syllabus_data['id'], $topic_id);
                        mysqli_stmt_execute($clear_stmt);
                        
                        // Insert new alignments
                        foreach ($clo_alignments as $clo_id) {
                            $insert_alignment = "INSERT INTO syllabus_topic_clo_alignment (syllabus_id, topic_id, clo_id, is_aligned) VALUES (?, ?, ?, 1)";
                            $alignment_stmt = mysqli_prepare($conn, $insert_alignment);
                            mysqli_stmt_bind_param($alignment_stmt, "iii", $syllabus_data['id'], $topic_id, $clo_id);
                            mysqli_stmt_execute($alignment_stmt);
                        }
                        
                        $message = "Topic updated successfully!";
                        $message_type = "success";
                    } else {
                        $message = "Error updating topic: " . mysqli_error($conn);
                        $message_type = "error";
                    }
                }
                break;

            case 'delete_topic':
                $topic_id = (int)$_POST['topic_id'];

                $delete_query = "DELETE FROM syllabus_topics WHERE id = ? AND syllabus_id = ?";
                $delete_stmt = mysqli_prepare($conn, $delete_query);
                mysqli_stmt_bind_param($delete_stmt, "ii", $topic_id, $syllabus_data['id']);

                if (mysqli_stmt_execute($delete_stmt)) {
                    $message = "Topic deleted successfully!";
                    $message_type = "success";
                } else {
                    $message = "Error deleting topic: " . mysqli_error($conn);
                    $message_type = "error";
                }
                break;
        }
    }
}

// Get syllabus topics
$topics_query = "SELECT * FROM syllabus_topics WHERE syllabus_id = ? ORDER BY order_number, week_number";
$topics_stmt = mysqli_prepare($conn, $topics_query);
mysqli_stmt_bind_param($topics_stmt, "i", $syllabus_data['id']);
mysqli_stmt_execute($topics_stmt);
$topics_result = mysqli_stmt_get_result($topics_stmt);

// Get CLOs for alignment
$clos_query = "SELECT * FROM syllabus_clos WHERE syllabus_id = ? ORDER BY order_number";
$clos_stmt = mysqli_prepare($conn, $clos_query);
mysqli_stmt_bind_param($clos_stmt, "i", $syllabus_data['id']);
mysqli_stmt_execute($clos_stmt);
$clos_result = mysqli_stmt_get_result($clos_stmt);

// Get topic-CLO alignments
$topic_clo_alignments = [];
$topic_clo_query = "SELECT topic_id, clo_id FROM syllabus_topic_clo_alignment WHERE syllabus_id = ? AND is_aligned = 1";
$topic_clo_stmt = mysqli_prepare($conn, $topic_clo_query);
mysqli_stmt_bind_param($topic_clo_stmt, "i", $syllabus_data['id']);
mysqli_stmt_execute($topic_clo_stmt);
$topic_clo_result = mysqli_stmt_get_result($topic_clo_stmt);
while ($row = mysqli_fetch_assoc($topic_clo_result)) {
    $topic_clo_alignments[] = $row['topic_id'] . '_' . $row['clo_id'];
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
                                <i class="fas fa-list-alt text-xl"></i>
                            </div>
                            <h1 class="text-3xl lg:text-4xl font-bold">Syllabus Topics</h1>
                        </div>
                        <p class="text-lg text-orange-100"><?php echo htmlspecialchars($class_data['subject_title'] . ' - Section ' . $class_data['section']); ?></p>
                        <p class="text-sm text-orange-200 mt-1">Manage weekly topics and learning objectives for your course</p>
                    </div>
                    <div class="flex flex-wrap gap-3">
                        <button onclick="openAddModal()" class="inline-flex items-center px-4 py-3 bg-white bg-opacity-20 text-white text-sm font-medium rounded-lg hover:bg-white hover:text-seait-orange transition-all duration-200 backdrop-blur-sm">
                            <i class="fas fa-plus mr-2"></i>Add Topic
                        </button>
                        <a href="syllabus_alignment.php?class_id=<?php echo encrypt_id($class_id); ?>" class="inline-flex items-center px-4 py-3 bg-white bg-opacity-20 text-white text-sm font-medium rounded-lg hover:bg-white hover:text-seait-orange transition-all duration-200 backdrop-blur-sm">
                            <i class="fas fa-link mr-2"></i>Manage Alignments
                        </a>
                        <a href="class_syllabus.php?class_id=<?php echo encrypt_id($class_id); ?>" class="inline-flex items-center px-4 py-3 bg-white bg-opacity-20 text-white text-sm font-medium rounded-lg hover:bg-white hover:text-seait-orange transition-all duration-200 backdrop-blur-sm">
                            <i class="fas fa-arrow-left mr-2"></i>Back to Syllabus
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

        <!-- Enhanced Topics List -->
        <div class="bg-white rounded-xl shadow-lg border border-gray-200 overflow-hidden">
            <div class="px-8 py-6 border-b border-gray-200 bg-gradient-to-r from-gray-50 to-gray-100">
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <div class="bg-blue-100 rounded-lg p-3 mr-4">
                            <i class="fas fa-list text-blue-600 text-xl"></i>
                        </div>
                        <div>
                            <h3 class="text-2xl font-bold text-seait-dark">Course Topics</h3>
                            <p class="text-gray-600 mt-1"><?php echo mysqli_num_rows($topics_result); ?> topics organized for your course</p>
                        </div>
                    </div>
                    <div class="text-right">
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800">
                            <i class="fas fa-check-circle mr-2"></i>
                            <?php echo mysqli_num_rows($topics_result); ?> Topics
                        </span>
                    </div>
                </div>
            </div>

            <?php if (mysqli_num_rows($topics_result) == 0): ?>
                <div class="p-8 text-center">
                    <i class="fas fa-list text-gray-300 text-4xl mb-4"></i>
                    <p class="text-gray-500">No topics have been added yet.</p>
                    <p class="text-sm text-gray-400 mt-2">Click "Add Topic" to start building your course outline.</p>
                </div>
            <?php else: ?>
                <div class="divide-y divide-gray-200">
                    <?php while ($topic = mysqli_fetch_assoc($topics_result)): ?>
                    <div class="p-6 hover:bg-gray-50 transition-colors">
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <div class="flex items-center mb-3">
                                    <h4 class="text-lg font-medium text-gray-900"><?php echo htmlspecialchars($topic['topic_title']); ?></h4>
                                    <?php if ($topic['week_number']): ?>
                                    <span class="ml-3 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-seait-orange bg-opacity-10 text-seait-orange">
                                        Week <?php echo $topic['week_number']; ?>
                                    </span>
                                    <?php endif; ?>
                                    <?php if ($topic['duration_hours']): ?>
                                    <span class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-700">
                                        <?php echo $topic['duration_hours']; ?> hours
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

                                    <?php if ($topic['activities']): ?>
                                    <div>
                                        <span class="font-medium text-gray-700">Activities:</span>
                                        <p class="text-gray-600 mt-1"><?php echo htmlspecialchars($topic['activities']); ?></p>
                                    </div>
                                    <?php endif; ?>

                                    <?php if ($topic['assessment']): ?>
                                    <div>
                                        <span class="font-medium text-gray-700">Assessments:</span>
                                        <p class="text-gray-600 mt-1"><?php echo htmlspecialchars($topic['assessment']); ?></p>
                                    </div>
                                    <?php endif; ?>

                                    <?php if ($topic['materials']): ?>
                                    <div>
                                        <span class="font-medium text-gray-700">Materials:</span>
                                        <p class="text-gray-600 mt-1"><?php echo htmlspecialchars($topic['materials']); ?></p>
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
                                <div class="mt-3">
                                    <span class="font-medium text-gray-700 text-sm">Aligned CLOs:</span>
                                    <div class="flex flex-wrap gap-2 mt-1">
                                        <?php while ($aligned_clo = mysqli_fetch_assoc($topic_clo_result)): ?>
                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                            <?php echo htmlspecialchars($aligned_clo['clo_code']); ?>
                                        </span>
                                        <?php endwhile; ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="ml-4 flex space-x-2">
                                <button onclick="openEditModal(<?php echo htmlspecialchars(json_encode($topic)); ?>)"
                                        class="inline-flex items-center px-3 py-2 bg-seait-orange text-white text-sm rounded-lg hover:bg-orange-600 transition-colors">
                                    <i class="fas fa-edit mr-2"></i>Edit
                                </button>
                                <button onclick="deleteTopic(<?php echo $topic['id']; ?>)"
                                        class="inline-flex items-center px-3 py-2 bg-gray-600 text-white text-sm rounded-lg hover:bg-gray-700 transition-colors">
                                    <i class="fas fa-trash mr-2"></i>Delete
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Add Topic Modal -->
    <div id="addModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900">Add Topic</h3>
                    <button onclick="closeAddModal()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <form method="POST" id="addForm">
                    <input type="hidden" name="action" value="add_topic">

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label for="title" class="block text-sm font-medium text-gray-700 mb-2">Title *</label>
                            <input type="text" id="title" name="title" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-seait-orange">
                        </div>
                        <div>
                            <label for="order_number" class="block text-sm font-medium text-gray-700 mb-2">Order Number</label>
                            <input type="number" id="order_number" name="order_number" min="0" value="0"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-seait-orange">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label for="week_number" class="block text-sm font-medium text-gray-700 mb-2">Week Number</label>
                            <input type="number" id="week_number" name="week_number" min="1"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-seait-orange">
                        </div>
                        <div>
                            <label for="duration_hours" class="block text-sm font-medium text-gray-700 mb-2">Duration (Hours)</label>
                            <input type="number" id="duration_hours" name="duration_hours" min="1"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-seait-orange">
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="description" class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                        <textarea id="description" name="description" rows="3"
                                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-seait-orange"></textarea>
                    </div>

                    <div class="mb-4">
                        <label for="learning_objectives" class="block text-sm font-medium text-gray-700 mb-2">Learning Objectives</label>
                        <textarea id="learning_objectives" name="learning_objectives" rows="3"
                                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-seait-orange"></textarea>
                    </div>

                    <div class="mb-4">
                        <label for="activities" class="block text-sm font-medium text-gray-700 mb-2">Activities</label>
                        <textarea id="activities" name="activities" rows="3"
                                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-seait-orange"></textarea>
                    </div>

                    <div class="mb-4">
                        <label for="assessments" class="block text-sm font-medium text-gray-700 mb-2">Assessments</label>
                        <textarea id="assessments" name="assessments" rows="3"
                                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-seait-orange"></textarea>
                    </div>

                    <div class="mb-4">
                        <label for="materials" class="block text-sm font-medium text-gray-700 mb-2">Materials</label>
                        <textarea id="materials" name="materials" rows="3"
                                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-seait-orange"></textarea>
                    </div>

                    <div class="mb-4">
                        <label for="references" class="block text-sm font-medium text-gray-700 mb-2">References</label>
                        <textarea id="references" name="references" rows="3"
                                  placeholder="List relevant references, readings, or resources for this topic..."
                                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-seait-orange"></textarea>
                    </div>

                    <div class="mb-4">
                        <label for="values_integration" class="block text-sm font-medium text-gray-700 mb-2">Values Integration</label>
                        <textarea id="values_integration" name="values_integration" rows="3"
                                  placeholder="Describe how values, ethics, or character development are integrated into this topic..."
                                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-seait-orange"></textarea>
                    </div>

                    <div class="mb-4">
                        <label for="target" class="block text-sm font-medium text-gray-700 mb-2">Target</label>
                        <textarea id="target" name="target" rows="3"
                                  placeholder="Specify the target competencies, skills, or outcomes for this topic..."
                                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-seait-orange"></textarea>
                    </div>

                    <!-- CLO Alignment Section -->
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">CLO Alignment</label>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 p-3 border border-gray-200 rounded-lg">
                            <?php 
                            mysqli_data_seek($clos_result, 0);
                            while ($clo = mysqli_fetch_assoc($clos_result)): 
                            ?>
                            <label class="flex items-center">
                                <input type="checkbox" name="clo_alignment[]" value="<?php echo $clo['id']; ?>" 
                                       class="h-4 w-4 text-seait-orange focus:ring-seait-orange border-gray-300 rounded">
                                <span class="ml-2 text-sm text-gray-700">
                                    <strong><?php echo htmlspecialchars($clo['clo_code']); ?></strong>: 
                                    <?php echo htmlspecialchars(substr($clo['clo_description'], 0, 50)) . '...'; ?>
                                </span>
                            </label>
                            <?php endwhile; ?>
                        </div>
                    </div>

                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeAddModal()"
                                class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition">
                            Cancel
                        </button>
                        <button type="submit"
                                class="px-4 py-2 bg-seait-orange text-white rounded-lg hover:bg-orange-600 transition">
                            Add Topic
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Topic Modal -->
    <div id="editModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900">Edit Topic</h3>
                    <button onclick="closeEditModal()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <form method="POST" id="editForm">
                    <input type="hidden" name="action" value="update_topic">
                    <input type="hidden" id="edit_topic_id" name="topic_id">

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label for="edit_title" class="block text-sm font-medium text-gray-700 mb-2">Title *</label>
                            <input type="text" id="edit_title" name="title" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-seait-orange">
                        </div>
                        <div>
                            <label for="edit_order_number" class="block text-sm font-medium text-gray-700 mb-2">Order Number</label>
                            <input type="number" id="edit_order_number" name="order_number" min="0"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-seait-orange">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label for="edit_week_number" class="block text-sm font-medium text-gray-700 mb-2">Week Number</label>
                            <input type="number" id="edit_week_number" name="week_number" min="1"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-seait-orange">
                        </div>
                        <div>
                            <label for="edit_duration_hours" class="block text-sm font-medium text-gray-700 mb-2">Duration (Hours)</label>
                            <input type="number" id="edit_duration_hours" name="duration_hours" min="1"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-seait-orange">
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="edit_description" class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                        <textarea id="edit_description" name="description" rows="3"
                                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-seait-orange"></textarea>
                    </div>

                    <div class="mb-4">
                        <label for="edit_learning_objectives" class="block text-sm font-medium text-gray-700 mb-2">Learning Objectives</label>
                        <textarea id="edit_learning_objectives" name="learning_objectives" rows="3"
                                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-seait-orange"></textarea>
                    </div>

                    <div class="mb-4">
                        <label for="edit_activities" class="block text-sm font-medium text-gray-700 mb-2">Activities</label>
                        <textarea id="edit_activities" name="activities" rows="3"
                                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-seait-orange"></textarea>
                    </div>

                    <div class="mb-4">
                        <label for="edit_assessments" class="block text-sm font-medium text-gray-700 mb-2">Assessments</label>
                        <textarea id="edit_assessments" name="assessments" rows="3"
                                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-seait-orange"></textarea>
                    </div>

                    <div class="mb-4">
                        <label for="edit_materials" class="block text-sm font-medium text-gray-700 mb-2">Materials</label>
                        <textarea id="edit_materials" name="materials" rows="3"
                                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-seait-orange"></textarea>
                    </div>

                    <div class="mb-4">
                        <label for="edit_references" class="block text-sm font-medium text-gray-700 mb-2">References</label>
                        <textarea id="edit_references" name="references" rows="3"
                                  placeholder="List relevant references, readings, or resources for this topic..."
                                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-seait-orange"></textarea>
                    </div>

                    <div class="mb-4">
                        <label for="edit_values_integration" class="block text-sm font-medium text-gray-700 mb-2">Values Integration</label>
                        <textarea id="edit_values_integration" name="values_integration" rows="3"
                                  placeholder="Describe how values, ethics, or character development are integrated into this topic..."
                                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-seait-orange"></textarea>
                    </div>

                    <div class="mb-4">
                        <label for="edit_target" class="block text-sm font-medium text-gray-700 mb-2">Target</label>
                        <textarea id="edit_target" name="target" rows="3"
                                  placeholder="Specify the target competencies, skills, or outcomes for this topic..."
                                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-seait-orange"></textarea>
                    </div>

                    <!-- CLO Alignment Section -->
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">CLO Alignment</label>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 p-3 border border-gray-200 rounded-lg">
                            <?php 
                            mysqli_data_seek($clos_result, 0);
                            while ($clo = mysqli_fetch_assoc($clos_result)): 
                            ?>
                            <label class="flex items-center">
                                <input type="checkbox" name="clo_alignment[]" value="<?php echo $clo['id']; ?>" 
                                       class="h-4 w-4 text-seait-orange focus:ring-seait-orange border-gray-300 rounded">
                                <span class="ml-2 text-sm text-gray-700">
                                    <strong><?php echo htmlspecialchars($clo['clo_code']); ?></strong>: 
                                    <?php echo htmlspecialchars(substr($clo['clo_description'], 0, 50)) . '...'; ?>
                                </span>
                            </label>
                            <?php endwhile; ?>
                        </div>
                    </div>

                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeEditModal()"
                                class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition">
                            Cancel
                        </button>
                        <button type="submit"
                                class="px-4 py-2 bg-seait-orange text-white rounded-lg hover:bg-orange-600 transition">
                            Update Topic
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3 text-center">
                <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-gray-100">
                    <i class="fas fa-exclamation-triangle text-gray-600"></i>
                </div>
                <h3 class="text-lg font-medium text-gray-900 mt-4">Delete Topic</h3>
                <div class="mt-2 px-7 py-3">
                    <p class="text-sm text-gray-500">Are you sure you want to delete this topic? This action cannot be undone.</p>
                </div>
                <div class="flex justify-center space-x-3 mt-4">
                    <button onclick="closeDeleteModal()"
                            class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition">
                        Cancel
                    </button>
                    <form method="POST" id="deleteForm" class="inline">
                        <input type="hidden" name="action" value="delete_topic">
                        <input type="hidden" id="delete_topic_id" name="topic_id">
                        <button type="submit"
                                class="px-4 py-2 bg-seait-orange text-white rounded-lg hover:bg-orange-600 transition-colors">
                            Delete
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        function openAddModal() {
            document.getElementById('addModal').classList.remove('hidden');
        }

        function closeAddModal() {
            document.getElementById('addModal').classList.add('hidden');
            document.getElementById('addForm').reset();
        }

        function openEditModal(topic) {
            document.getElementById('edit_topic_id').value = topic.id;
            document.getElementById('edit_title').value = topic.topic_title || topic.title || '';
            document.getElementById('edit_description').value = topic.description || '';
            document.getElementById('edit_week_number').value = topic.week_number || '';
            document.getElementById('edit_duration_hours').value = topic.duration_hours || '';
            document.getElementById('edit_learning_objectives').value = topic.learning_objectives || '';
            document.getElementById('edit_activities').value = topic.activities || '';
            document.getElementById('edit_assessments').value = topic.assessment || topic.assessments || '';
            document.getElementById('edit_materials').value = topic.materials || '';
            document.getElementById('edit_references').value = topic.references_field || topic.references || '';
            document.getElementById('edit_values_integration').value = topic.values_integration || '';
            document.getElementById('edit_target').value = topic.target || '';
            document.getElementById('edit_order_number').value = topic.order_number || 0;

            // Clear all CLO checkboxes first
            const cloCheckboxes = document.querySelectorAll('input[name="clo_alignment[]"]');
            cloCheckboxes.forEach(checkbox => checkbox.checked = false);

            // Check the aligned CLOs if they exist
            if (topic.clo_alignments) {
                topic.clo_alignments.forEach(cloId => {
                    const checkbox = document.querySelector(`input[name="clo_alignment[]"][value="${cloId}"]`);
                    if (checkbox) checkbox.checked = true;
                });
            }

            document.getElementById('editModal').classList.remove('hidden');
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.add('hidden');
        }

        function deleteTopic(topicId) {
            document.getElementById('delete_topic_id').value = topicId;
            document.getElementById('deleteModal').classList.remove('hidden');
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.add('hidden');
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            const addModal = document.getElementById('addModal');
            const editModal = document.getElementById('editModal');
            const deleteModal = document.getElementById('deleteModal');

            if (event.target === addModal) {
                closeAddModal();
            }
            if (event.target === editModal) {
                closeEditModal();
            }
            if (event.target === deleteModal) {
                closeDeleteModal();
            }
        }
    </script>

<?php include 'includes/unified-footer.php'; ?>