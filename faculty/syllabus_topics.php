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

// Get syllabus data
$syllabus_query = "SELECT * FROM class_syllabus WHERE class_id = ?";
$syllabus_stmt = mysqli_prepare($conn, $syllabus_query);
mysqli_stmt_bind_param($syllabus_stmt, "i", $class_id);
mysqli_stmt_execute($syllabus_stmt);
$syllabus_result = mysqli_stmt_get_result($syllabus_stmt);
$syllabus_data = mysqli_fetch_assoc($syllabus_result);

if (!$syllabus_data) {
    header('Location: class_syllabus.php?class_id=' . $class_id . '&error=no_syllabus');
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
                $order_number = isset($_POST['order_number']) ? (int)$_POST['order_number'] : 0;

                if (empty($title)) {
                    $message = "Please provide a title for the topic.";
                    $message_type = "error";
                } else {
                    $insert_query = "INSERT INTO syllabus_topics (syllabus_id, title, description, week_number,
                                    duration_hours, learning_objectives, activities, assessments, materials, order_number)
                                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $insert_stmt = mysqli_prepare($conn, $insert_query);
                    mysqli_stmt_bind_param($insert_stmt, "issiissssi",
                        $syllabus_data['id'], $title, $description, $week_number, $duration_hours,
                        $learning_objectives, $activities, $assessments, $materials, $order_number);

                    if (mysqli_stmt_execute($insert_stmt)) {
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
                $order_number = isset($_POST['order_number']) ? (int)$_POST['order_number'] : 0;

                if (empty($title)) {
                    $message = "Please provide a title for the topic.";
                    $message_type = "error";
                } else {
                    $update_query = "UPDATE syllabus_topics SET title = ?, description = ?, week_number = ?,
                                    duration_hours = ?, learning_objectives = ?, activities = ?, assessments = ?,
                                    materials = ?, order_number = ?, updated_at = NOW()
                                    WHERE id = ? AND syllabus_id = ?";
                    $update_stmt = mysqli_prepare($conn, $update_query);
                    mysqli_stmt_bind_param($update_stmt, "ssiissssiis",
                        $title, $description, $week_number, $duration_hours, $learning_objectives,
                        $activities, $assessments, $materials, $order_number, $topic_id, $syllabus_data['id']);

                    if (mysqli_stmt_execute($update_stmt)) {
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

// Include the shared header
$sidebar_context = 'main';
include 'includes/unified-header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - Faculty Portal</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
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
                            <a href="class_syllabus.php?class_id=<?php echo $class_id; ?>" class="text-gray-500 hover:text-gray-700">
                                Syllabus
                            </a>
                        </div>
                    </li>
                    <li>
                        <div class="flex items-center">
                            <i class="fas fa-chevron-right text-gray-400 mx-2"></i>
                            <span class="text-gray-900">Topics</span>
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
                    <h1 class="text-3xl font-bold text-gray-900">Syllabus Topics</h1>
                    <p class="text-gray-600 mt-2"><?php echo htmlspecialchars($class_data['subject_title'] . ' - ' . $class_data['section']); ?></p>
                </div>
                <div class="mt-4 sm:mt-0 flex space-x-3">
                    <button onclick="openAddModal()" class="bg-seait-orange text-white px-4 py-2 rounded-lg hover:bg-orange-600 transition">
                        <i class="fas fa-plus mr-2"></i>Add Topic
                    </button>
                    <a href="class_syllabus.php?class_id=<?php echo $class_id; ?>" class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700 transition">
                        <i class="fas fa-arrow-left mr-2"></i>Back to Syllabus
                    </a>
                </div>
            </div>
        </div>

        <?php if ($message): ?>
        <div class="mb-6 p-4 rounded-lg <?php echo $message_type === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
            <?php echo $message; ?>
        </div>
        <?php endif; ?>

        <!-- Topics List -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Course Topics (<?php echo mysqli_num_rows($topics_result); ?>)</h3>
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
                                    <h4 class="text-lg font-medium text-gray-900"><?php echo htmlspecialchars($topic['title']); ?></h4>
                                    <?php if ($topic['week_number']): ?>
                                    <span class="ml-3 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                        Week <?php echo $topic['week_number']; ?>
                                    </span>
                                    <?php endif; ?>
                                    <?php if ($topic['duration_hours']): ?>
                                    <span class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
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

                                    <?php if ($topic['assessments']): ?>
                                    <div>
                                        <span class="font-medium text-gray-700">Assessments:</span>
                                        <p class="text-gray-600 mt-1"><?php echo htmlspecialchars($topic['assessments']); ?></p>
                                    </div>
                                    <?php endif; ?>

                                    <?php if ($topic['materials']): ?>
                                    <div>
                                        <span class="font-medium text-gray-700">Materials:</span>
                                        <p class="text-gray-600 mt-1"><?php echo htmlspecialchars($topic['materials']); ?></p>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="ml-4 flex space-x-2">
                                <button onclick="openEditModal(<?php echo htmlspecialchars(json_encode($topic)); ?>)"
                                        class="inline-flex items-center px-3 py-2 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700 transition">
                                    <i class="fas fa-edit mr-2"></i>Edit
                                </button>
                                <button onclick="deleteTopic(<?php echo $topic['id']; ?>)"
                                        class="inline-flex items-center px-3 py-2 bg-red-600 text-white text-sm rounded-lg hover:bg-red-700 transition">
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
                <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100">
                    <i class="fas fa-exclamation-triangle text-red-600"></i>
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
                                class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition">
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
            document.getElementById('edit_title').value = topic.title;
            document.getElementById('edit_description').value = topic.description || '';
            document.getElementById('edit_week_number').value = topic.week_number || '';
            document.getElementById('edit_duration_hours').value = topic.duration_hours || '';
            document.getElementById('edit_learning_objectives').value = topic.learning_objectives || '';
            document.getElementById('edit_activities').value = topic.activities || '';
            document.getElementById('edit_assessments').value = topic.assessments || '';
            document.getElementById('edit_materials').value = topic.materials || '';
            document.getElementById('edit_order_number').value = topic.order_number || 0;

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
</body>
</html>