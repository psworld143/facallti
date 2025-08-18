<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and has guidance_officer role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'guidance_officer') {
    header('Location: ../login.php');
    exit();
}

$message = '';
$message_type = '';

// Get sub-category ID from URL
$sub_category_id = isset($_GET['sub_category_id']) ? (int)$_GET['sub_category_id'] : 0;

if (!$sub_category_id) {
    header('Location: evaluations.php');
    exit();
}

// Get sub-category information with main category details
$sub_category_query = "SELECT esc.*, mec.name as main_category_name, mec.evaluation_type
                      FROM evaluation_sub_categories esc
                      JOIN main_evaluation_categories mec ON esc.main_category_id = mec.id
                      WHERE esc.id = ?";
$sub_category_stmt = mysqli_prepare($conn, $sub_category_query);
mysqli_stmt_bind_param($sub_category_stmt, "i", $sub_category_id);
mysqli_stmt_execute($sub_category_stmt);
$sub_category_result = mysqli_stmt_get_result($sub_category_stmt);
$sub_category = mysqli_fetch_assoc($sub_category_result);

if (!$sub_category) {
    header('Location: evaluations.php');
    exit();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_questionnaire':
                $question_text = trim($_POST['question_text']);
                $question_type = $_POST['question_type'];
                $required = isset($_POST['required']) ? 1 : 0;

                // Calculate automatic order number
                $order_query = "SELECT MAX(order_number) as max_order FROM evaluation_questionnaires WHERE sub_category_id = ?";
                $order_stmt = mysqli_prepare($conn, $order_query);
                mysqli_stmt_bind_param($order_stmt, "i", $sub_category_id);
                mysqli_stmt_execute($order_stmt);
                $order_result = mysqli_stmt_get_result($order_stmt);
                $order_row = mysqli_fetch_assoc($order_result);
                $order_number = ($order_row['max_order'] ?? 0) + 1;

                // Set default rating labels for 1-5 scale
                $rating_labels = json_encode([
                    "1 - Poor",
                    "2 - Good",
                    "3 - Satisfactory",
                    "4 - Very Satisfactory",
                    "5 - Excellent"
                ]);

                $options = null;
                if ($question_type === 'multiple_choice' && !empty($_POST['options'])) {
                    $options = json_encode($_POST['options']);
                }

                if (empty($question_text)) {
                    $message = "Question text is required!";
                    $message_type = "error";
                } else {
                    $insert_query = "INSERT INTO evaluation_questionnaires (sub_category_id, question_text, question_type, rating_labels, options, required, order_number, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                    $insert_stmt = mysqli_prepare($conn, $insert_query);
                    mysqli_stmt_bind_param($insert_stmt, "issssiii", $sub_category_id, $question_text, $question_type, $rating_labels, $options, $required, $order_number, $_SESSION['user_id']);

                    if (mysqli_stmt_execute($insert_stmt)) {
                        $message = "Questionnaire added successfully!";
                        $message_type = "success";
                    } else {
                        $message = "Error adding questionnaire: " . mysqli_error($conn);
                        $message_type = "error";
                    }
                }
                break;

            case 'update_questionnaire':
                $questionnaire_id = (int)$_POST['questionnaire_id'];
                $question_text = trim($_POST['question_text']);
                $question_type = $_POST['question_type'];
                $required = isset($_POST['required']) ? 1 : 0;
                $order_number = (int)$_POST['order_number'];
                $status = $_POST['status'];

                $options = null;
                if ($question_type === 'multiple_choice' && !empty($_POST['options'])) {
                    $options = json_encode($_POST['options']);
                }

                if (empty($question_text)) {
                    $message = "Question text is required!";
                    $message_type = "error";
                } else {
                    $update_query = "UPDATE evaluation_questionnaires SET question_text = ?, question_type = ?, options = ?, required = ?, order_number = ?, status = ? WHERE id = ? AND sub_category_id = ?";
                    $update_stmt = mysqli_prepare($conn, $update_query);
                    mysqli_stmt_bind_param($update_stmt, "sssissii", $question_text, $question_type, $options, $required, $order_number, $status, $questionnaire_id, $sub_category_id);

                    if (mysqli_stmt_execute($update_stmt)) {
                        $message = "Questionnaire updated successfully!";
                        $message_type = "success";
                    } else {
                        $message = "Error updating questionnaire: " . mysqli_error($conn);
                        $message_type = "error";
                    }
                }
                break;

            case 'delete_questionnaire':
                $questionnaire_id = (int)$_POST['questionnaire_id'];

                // Check if questionnaire has responses
                $check_query = "SELECT COUNT(*) as count FROM evaluation_responses WHERE questionnaire_id = ?";
                $check_stmt = mysqli_prepare($conn, $check_query);
                mysqli_stmt_bind_param($check_stmt, "i", $questionnaire_id);
                mysqli_stmt_execute($check_stmt);
                $check_result = mysqli_stmt_get_result($check_stmt);
                $check_row = mysqli_fetch_assoc($check_result);

                if ($check_row['count'] > 0) {
                    $message = "Cannot delete questionnaire. It has responses. Please archive it instead.";
                    $message_type = "error";
                } else {
                    $delete_query = "DELETE FROM evaluation_questionnaires WHERE id = ? AND sub_category_id = ?";
                    $delete_stmt = mysqli_prepare($conn, $delete_query);
                    mysqli_stmt_bind_param($delete_stmt, "ii", $questionnaire_id, $sub_category_id);

                    if (mysqli_stmt_execute($delete_stmt)) {
                        $message = "Questionnaire deleted successfully!";
                        $message_type = "success";
                    } else {
                        $message = "Error deleting questionnaire: " . mysqli_error($conn);
                        $message_type = "error";
                    }
                }
                break;
        }
    }
}

// Get questionnaires for this sub-category
$questionnaires_query = "SELECT * FROM evaluation_questionnaires WHERE sub_category_id = ? ORDER BY order_number ASC, question_text ASC";
$questionnaires_stmt = mysqli_prepare($conn, $questionnaires_query);
mysqli_stmt_bind_param($questionnaires_stmt, "i", $sub_category_id);
mysqli_stmt_execute($questionnaires_stmt);
$questionnaires_result = mysqli_stmt_get_result($questionnaires_stmt);
$questionnaires = [];
while ($row = mysqli_fetch_assoc($questionnaires_result)) {
    $questionnaires[] = $row;
}

// Calculate the next automatic order number
$next_order_number = 1;
if (!empty($questionnaires)) {
    $max_order = max(array_column($questionnaires, 'order_number'));
    $next_order_number = $max_order + 1;
}

// Set page title
$page_title = 'Questionnaires - ' . $sub_category['name'];

// Include the shared header
include 'includes/header.php';
?>

<div class="mb-6 sm:mb-8">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-seait-dark mb-2">Questionnaires</h1>
            <p class="text-sm sm:text-base text-gray-600">
                Manage questionnaires for: <strong><?php echo htmlspecialchars($sub_category['name']); ?></strong>
            </p>
        </div>
        <a href="sub-categories.php?main_category_id=<?php echo $sub_category['main_category_id']; ?>"
           class="bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600 transition">
            <i class="fas fa-arrow-left mr-2"></i>Back to Sub-Categories
        </a>
    </div>
</div>

<!-- Sub-Category Info -->
<div class="mb-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
    <div class="flex items-center">
        <div class="flex-shrink-0">
            <i class="fas fa-info-circle text-blue-400"></i>
        </div>
        <div class="ml-3">
            <h3 class="text-sm font-medium text-blue-800"><?php echo htmlspecialchars($sub_category['name']); ?></h3>
            <div class="mt-1 text-sm text-blue-700">
                <p><?php echo htmlspecialchars($sub_category['description']); ?></p>
                <p class="mt-1">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                        <?php
                        switch($sub_category['evaluation_type']) {
                            case 'student_to_teacher':
                                echo 'bg-orange-100 text-orange-800';
                                break;
                            case 'peer_to_peer':
                                echo 'bg-purple-100 text-purple-800';
                                break;
                            case 'head_to_teacher':
                                echo 'bg-indigo-100 text-indigo-800';
                                break;
                        }
                        ?>">
                        <i class="fas
                            <?php
                            switch($sub_category['evaluation_type']) {
                                case 'student_to_teacher':
                                    echo 'fa-user-graduate';
                                    break;
                                case 'peer_to_peer':
                                    echo 'fa-users';
                                    break;
                                case 'head_to_teacher':
                                    echo 'fa-user-tie';
                                    break;
                            }
                            ?> mr-1"></i>
                        <?php echo htmlspecialchars($sub_category['main_category_name']); ?>
                    </span>
                </p>
            </div>
        </div>
    </div>
</div>

<?php if ($message): ?>
    <div class="mb-6 p-4 rounded-lg border-l-4 <?php echo $message_type === 'success' ? 'bg-green-50 text-green-700 border-green-400' : ($message_type === 'warning' ? 'bg-yellow-50 text-yellow-700 border-yellow-400' : 'bg-red-50 text-red-700 border-red-400'); ?>">
        <div class="flex items-center">
            <div class="flex-shrink-0">
                <i class="fas <?php echo $message_type === 'success' ? 'fa-check-circle' : ($message_type === 'warning' ? 'fa-exclamation-triangle' : 'fa-times-circle'); ?> text-lg"></i>
            </div>
            <div class="ml-3">
                <p class="text-sm font-medium"><?php echo htmlspecialchars($message); ?></p>
            </div>
            <div class="ml-auto pl-3">
                <button onclick="this.parentElement.parentElement.parentElement.remove()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Rating Scale Information -->
<div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-lg">
    <div class="flex items-center">
        <div class="flex-shrink-0">
            <i class="fas fa-star text-green-400"></i>
        </div>
        <div class="ml-3">
            <h3 class="text-sm font-medium text-green-800">Standardized Rating Scale</h3>
            <div class="mt-1 text-sm text-green-700">
                <p>All rating questions use a standardized 1-5 scale:</p>
                <div class="mt-2 flex flex-wrap gap-2">
                    <span class="px-2 py-1 text-xs rounded-full bg-red-100 text-red-800">1 - Poor</span>
                    <span class="px-2 py-1 text-xs rounded-full bg-yellow-100 text-yellow-800">2 - Good</span>
                    <span class="px-2 py-1 text-xs rounded-full bg-blue-100 text-blue-800">3 - Satisfactory</span>
                    <span class="px-2 py-1 text-xs rounded-full bg-green-100 text-green-800">4 - Very Satisfactory</span>
                    <span class="px-2 py-1 text-xs rounded-full bg-purple-100 text-purple-800">5 - Excellent</span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add New Questionnaire Form -->
<div class="mb-8 bg-white rounded-lg shadow-md overflow-hidden">
    <div class="px-6 py-4 bg-gradient-to-r from-seait-orange to-orange-600">
        <h2 class="text-lg font-semibold text-white flex items-center">
            <i class="fas fa-plus-circle mr-3"></i>Add New Questionnaire
        </h2>
    </div>

    <div class="p-6">
        <form method="POST" class="space-y-6">
            <input type="hidden" name="action" value="add_questionnaire">

            <!-- Question Details Section -->
            <div class="bg-gray-50 rounded-lg p-4">
                <h3 class="text-md font-medium text-gray-800 mb-4 flex items-center">
                    <i class="fas fa-question-circle mr-2 text-seait-orange"></i>Question Details
                </h3>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- Question Text -->
                    <div class="lg:col-span-2">
                        <label for="question_text" class="block text-sm font-medium text-gray-700 mb-2">
                            Question Text <span class="text-red-500">*</span>
                        </label>
                        <textarea id="question_text" name="question_text" required rows="4"
                                  class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-seait-orange focus:border-transparent resize-none"
                                  placeholder="Enter your question here... (e.g., How would you rate the teacher's communication skills?)"></textarea>
                        <p class="mt-1 text-xs text-gray-500">Be clear and specific in your question to get better responses.</p>
                    </div>

                    <!-- Question Type -->
                    <div>
                        <label for="question_type" class="block text-sm font-medium text-gray-700 mb-2">
                            Question Type <span class="text-red-500">*</span>
                        </label>
                        <select id="question_type" name="question_type" required
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-seait-orange focus:border-transparent">
                            <option value="">Select question type...</option>
                            <option value="rating_1_5">Rating (1-5 Scale)</option>
                            <option value="text">Text Response</option>
                            <option value="yes_no">Yes/No</option>
                            <option value="multiple_choice">Multiple Choice</option>
                        </select>
                        <div class="mt-2 text-xs text-gray-500">
                            <p><strong>Rating:</strong> 1-5 scale with labels</p>
                            <p><strong>Text:</strong> Free-form text response</p>
                            <p><strong>Yes/No:</strong> Simple binary choice</p>
                            <p><strong>Multiple Choice:</strong> Custom options</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Question Settings Section -->
            <div class="bg-gray-50 rounded-lg p-4">
                <h3 class="text-md font-medium text-gray-800 mb-4 flex items-center">
                    <i class="fas fa-cog mr-2 text-seait-orange"></i>Question Settings
                </h3>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Required Field -->
                    <div class="flex items-center">
                        <div class="flex items-center h-5">
                            <input type="checkbox" id="required" name="required" checked
                                   class="h-5 w-5 text-seait-orange focus:ring-seait-orange border-gray-300 rounded">
                        </div>
                        <div class="ml-3">
                            <label for="required" class="text-sm font-medium text-gray-700">Required Question</label>
                            <p class="text-xs text-gray-500">Respondents must answer this question</p>
                        </div>
                    </div>

                    <!-- Question Preview -->
                    <div class="bg-white rounded-lg p-4 border border-gray-200 shadow-sm">
                        <p class="text-sm font-medium text-gray-700 mb-3 flex items-center">
                            <i class="fas fa-eye mr-2 text-seait-orange"></i>Question Preview
                        </p>
                        <div id="question_preview" class="text-sm text-gray-600 bg-gray-50 p-3 rounded-lg border-l-4 border-gray-300 min-h-[60px]">
                            <span class="text-gray-500 italic">Select question type to see preview...</span>
                        </div>
                    </div>
                </div>

                <!-- Automatic Order Number Info -->
                <div class="mt-4 p-3 bg-blue-50 border border-blue-200 rounded-lg">
                    <div class="flex items-center">
                        <i class="fas fa-sort-numeric-up text-blue-500 mr-2"></i>
                        <div>
                            <p class="text-sm font-medium text-blue-800">Automatic Order Number</p>
                            <p class="text-xs text-blue-600">This question will be assigned order number: <strong><?php echo $next_order_number; ?></strong></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Multiple Choice Options Section -->
            <div id="multiple_choice_options" class="hidden bg-gray-50 rounded-lg p-4">
                <h3 class="text-md font-medium text-gray-800 mb-4 flex items-center">
                    <i class="fas fa-list mr-2 text-seait-orange"></i>Multiple Choice Options
                </h3>

                <div class="space-y-3">
                    <p class="text-sm text-gray-600 mb-3">Add the options that respondents can choose from:</p>

                    <div id="options_container" class="space-y-3">
                        <div class="flex items-center space-x-3">
                            <div class="flex-shrink-0 w-6 h-6 bg-seait-orange rounded-full flex items-center justify-center">
                                <span class="text-white text-xs font-medium">1</span>
                            </div>
                            <input type="text" name="options[]" placeholder="Enter option 1" required
                                   class="flex-1 px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-seait-orange focus:border-transparent">
                            <button type="button" onclick="removeOption(this)" class="text-red-600 hover:text-red-800 p-2">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <div class="flex items-center space-x-3">
                            <div class="flex-shrink-0 w-6 h-6 bg-seait-orange rounded-full flex items-center justify-center">
                                <span class="text-white text-xs font-medium">2</span>
                            </div>
                            <input type="text" name="options[]" placeholder="Enter option 2" required
                                   class="flex-1 px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-seait-orange focus:border-transparent">
                            <button type="button" onclick="removeOption(this)" class="text-red-600 hover:text-red-800 p-2">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>

                    <button type="button" onclick="addOption()"
                            class="inline-flex items-center px-4 py-2 text-sm font-medium text-seait-orange bg-white border border-seait-orange rounded-lg hover:bg-seait-orange hover:text-white transition-colors">
                        <i class="fas fa-plus mr-2"></i>Add Another Option
                    </button>

                    <p class="text-xs text-gray-500 mt-2">
                        <i class="fas fa-info-circle mr-1"></i>
                        You can add up to 10 options. At least 2 options are required.
                    </p>
                </div>
            </div>

            <!-- Form Actions -->
            <div class="flex flex-col sm:flex-row justify-between items-center pt-6 border-t border-gray-200">
                <div class="text-sm text-gray-500 mb-4 sm:mb-0">
                    <i class="fas fa-info-circle mr-1"></i>
                    All questions will use the standardized rating scale when applicable.
                </div>

                <div class="flex space-x-3">
                    <button type="button" onclick="resetForm()"
                            class="px-6 py-3 text-gray-700 bg-gray-200 rounded-lg hover:bg-gray-300 transition-colors">
                        <i class="fas fa-undo mr-2"></i>Reset Form
                    </button>
                    <button type="submit"
                            class="px-6 py-3 bg-seait-orange text-white rounded-lg hover:bg-orange-600 transition-colors flex items-center">
                        <i class="fas fa-plus mr-2"></i>Add Questionnaire
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Questionnaires List -->
<div class="bg-white rounded-lg shadow-md overflow-hidden">
    <div class="px-4 sm:px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-gray-50 to-white">
        <div class="flex items-center justify-between">
            <h2 class="text-lg font-semibold text-gray-900 flex items-center">
                <i class="fas fa-list-alt mr-3 text-seait-orange"></i>Questionnaires (<?php echo count($questionnaires); ?>)
            </h2>
            <div class="flex items-center space-x-2">
                <span class="text-sm text-gray-500">Order by:</span>
                <select id="sortOrder" onchange="sortQuestions()" class="text-sm border border-gray-300 rounded px-2 py-1">
                    <option value="order">Question Order</option>
                    <option value="type">Question Type</option>
                    <option value="status">Status</option>
                </select>
            </div>
        </div>
    </div>

    <div class="p-4 sm:p-6">
        <?php if (empty($questionnaires)): ?>
            <div class="text-center py-12">
                <div class="mx-auto w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                    <i class="fas fa-question-circle text-gray-400 text-2xl"></i>
                </div>
                <h3 class="text-lg font-medium text-gray-900 mb-2">No questionnaires found</h3>
                <p class="text-gray-500 mb-4">Get started by adding your first questionnaire above.</p>
                <button onclick="document.getElementById('question_text').focus()"
                        class="inline-flex items-center px-4 py-2 bg-seait-orange text-white rounded-lg hover:bg-orange-600 transition">
                    <i class="fas fa-plus mr-2"></i>Add First Question
                </button>
            </div>
        <?php else: ?>
            <div class="space-y-6">
                <?php foreach ($questionnaires as $questionnaire): ?>
                    <div class="border border-gray-200 rounded-lg overflow-hidden hover:shadow-md transition-shadow duration-200">
                        <!-- Question Header -->
                        <div class="bg-gray-50 px-4 py-3 border-b border-gray-200">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center space-x-3">
                                    <div class="w-8 h-8 bg-seait-orange rounded-full flex items-center justify-center">
                                        <span class="text-white text-sm font-semibold"><?php echo $questionnaire['order_number']; ?></span>
                                    </div>
                                    <div>
                                        <h3 class="text-base font-semibold text-gray-900"><?php echo htmlspecialchars($questionnaire['question_text']); ?></h3>
                                        <div class="flex items-center space-x-2 mt-1">
                                            <span class="px-2 py-1 text-xs rounded-full
                                                <?php
                                                switch($questionnaire['question_type']) {
                                                    case 'rating_1_5':
                                                        echo 'bg-purple-100 text-purple-800';
                                                        break;
                                                    case 'text':
                                                        echo 'bg-blue-100 text-blue-800';
                                                        break;
                                                    case 'yes_no':
                                                        echo 'bg-green-100 text-green-800';
                                                        break;
                                                    case 'multiple_choice':
                                                        echo 'bg-orange-100 text-orange-800';
                                                        break;
                                                }
                                                ?>">
                                                <i class="fas
                                                    <?php
                                                    switch($questionnaire['question_type']) {
                                                        case 'rating_1_5':
                                                            echo 'fa-star';
                                                            break;
                                                        case 'text':
                                                            echo 'fa-align-left';
                                                            break;
                                                        case 'yes_no':
                                                            echo 'fa-check-circle';
                                                            break;
                                                        case 'multiple_choice':
                                                            echo 'fa-list';
                                                            break;
                                                    }
                                                    ?> mr-1"></i>
                                                <?php echo ucwords(str_replace('_', ' ', $questionnaire['question_type'])); ?>
                                            </span>

                                            <?php if ($questionnaire['required']): ?>
                                                <span class="px-2 py-1 text-xs rounded-full bg-red-100 text-red-800">
                                                    <i class="fas fa-asterisk mr-1"></i>Required
                                                </span>
                                            <?php else: ?>
                                                <span class="px-2 py-1 text-xs rounded-full bg-gray-100 text-gray-800">
                                                    <i class="fas fa-circle mr-1"></i>Optional
                                                </span>
                                            <?php endif; ?>

                                            <span class="px-2 py-1 text-xs rounded-full <?php echo $questionnaire['status'] === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'; ?>">
                                                <i class="fas fa-<?php echo $questionnaire['status'] === 'active' ? 'check' : 'pause'; ?> mr-1"></i>
                                                <?php echo ucfirst($questionnaire['status']); ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <div class="flex items-center space-x-2">
                                    <button onclick="editQuestionnaire(<?php echo htmlspecialchars(json_encode($questionnaire)); ?>)"
                                            class="text-yellow-600 hover:text-yellow-900 p-2 rounded-lg hover:bg-yellow-50 transition-colors"
                                            title="Edit Question">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button onclick="deleteQuestionnaire(<?php echo $questionnaire['id']; ?>, '<?php echo htmlspecialchars($questionnaire['question_text']); ?>')"
                                            class="text-red-600 hover:text-red-900 p-2 rounded-lg hover:bg-red-50 transition-colors"
                                            title="Delete Question">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Question Details -->
                        <div class="p-4">
                            <?php if ($questionnaire['question_type'] === 'multiple_choice' && $questionnaire['options']): ?>
                                <div class="mb-4">
                                    <h4 class="text-sm font-medium text-gray-700 mb-2 flex items-center">
                                        <i class="fas fa-list-ul mr-2 text-gray-500"></i>Available Options:
                                    </h4>
                                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
                                        <?php
                                        $options = json_decode($questionnaire['options'], true);
                                        if ($options):
                                            foreach ($options as $index => $option):
                                        ?>
                                            <div class="flex items-center space-x-2 p-2 bg-gray-50 rounded-lg">
                                                <div class="w-5 h-5 bg-seait-orange rounded-full flex items-center justify-center">
                                                    <span class="text-white text-xs font-medium"><?php echo $index + 1; ?></span>
                                                </div>
                                                <span class="text-sm text-gray-700"><?php echo htmlspecialchars($option); ?></span>
                                            </div>
                                        <?php
                                            endforeach;
                                        endif;
                                        ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if ($questionnaire['question_type'] === 'rating_1_5'): ?>
                                <div class="mb-4">
                                    <h4 class="text-sm font-medium text-gray-700 mb-2 flex items-center">
                                        <i class="fas fa-star mr-2 text-gray-500"></i>Rating Scale:
                                    </h4>
                                    <div class="flex flex-wrap gap-2">
                                        <span class="px-3 py-1 text-xs rounded-full bg-red-100 text-red-800">1 - Poor</span>
                                        <span class="px-3 py-1 text-xs rounded-full bg-yellow-100 text-yellow-800">2 - Good</span>
                                        <span class="px-3 py-1 text-xs rounded-full bg-blue-100 text-blue-800">3 - Satisfactory</span>
                                        <span class="px-3 py-1 text-xs rounded-full bg-green-100 text-green-800">4 - Very Satisfactory</span>
                                        <span class="px-3 py-1 text-xs rounded-full bg-purple-100 text-purple-800">5 - Excellent</span>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- Question Metadata -->
                            <div class="flex items-center justify-between pt-3 border-t border-gray-100">
                                <div class="flex items-center space-x-4 text-xs text-gray-500">
                                    <span class="flex items-center">
                                        <i class="fas fa-calendar-alt mr-1"></i>
                                        Created: <?php echo date('M j, Y', strtotime($questionnaire['created_at'])); ?>
                                    </span>
                                    <?php if ($questionnaire['updated_at'] && $questionnaire['updated_at'] !== $questionnaire['created_at']): ?>
                                        <span class="flex items-center">
                                            <i class="fas fa-edit mr-1"></i>
                                            Updated: <?php echo date('M j, Y', strtotime($questionnaire['updated_at'])); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <div class="flex items-center space-x-2">
                                    <span class="text-xs text-gray-400">ID: <?php echo $questionnaire['id']; ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Edit Questionnaire Modal -->
<div id="editModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Edit Questionnaire</h3>
            </div>

            <form id="editForm" method="POST" class="p-6 space-y-4">
                <input type="hidden" name="action" value="update_questionnaire">
                <input type="hidden" id="edit_questionnaire_id" name="questionnaire_id">

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="edit_question_text" class="block text-sm font-medium text-gray-700 mb-1">Question Text *</label>
                        <textarea id="edit_question_text" name="question_text" required rows="3"
                                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-seait-orange focus:border-transparent"></textarea>
                    </div>

                    <div class="space-y-4">
                        <div>
                            <label for="edit_question_type" class="block text-sm font-medium text-gray-700 mb-1">Question Type *</label>
                            <select id="edit_question_type" name="question_type" required
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-seait-orange focus:border-transparent">
                                <option value="rating_1_5">Rating (1-5 Scale)</option>
                                <option value="text">Text Response</option>
                                <option value="yes_no">Yes/No</option>
                                <option value="multiple_choice">Multiple Choice</option>
                            </select>
                        </div>

                        <div>
                            <label for="edit_order_number" class="block text-sm font-medium text-gray-700 mb-1">Order Number</label>
                            <input type="number" id="edit_order_number" name="order_number" min="1"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-seait-orange focus:border-transparent">
                        </div>

                        <div>
                            <label for="edit_status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                            <select id="edit_status" name="status"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-seait-orange focus:border-transparent">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>

                        <div class="flex items-center">
                            <input type="checkbox" id="edit_required" name="required"
                                   class="h-4 w-4 text-seait-orange focus:ring-seait-orange border-gray-300 rounded">
                            <label for="edit_required" class="ml-2 block text-sm text-gray-700">Required question</label>
                        </div>
                    </div>
                </div>

                <!-- Edit Multiple Choice Options -->
                <div id="edit_multiple_choice_options" class="hidden">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Multiple Choice Options</label>
                    <div id="edit_options_container" class="space-y-2">
                        <!-- Options will be populated by JavaScript -->
                    </div>
                    <button type="button" onclick="addEditOption()" class="mt-2 text-seait-orange hover:text-orange-600 text-sm">
                        <i class="fas fa-plus mr-1"></i>Add Option
                    </button>
                </div>

                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeEditModal()"
                            class="px-4 py-2 text-gray-700 bg-gray-200 rounded-lg hover:bg-gray-300 transition">
                        Cancel
                    </button>
                    <button type="submit"
                            class="px-4 py-2 bg-seait-orange text-white rounded-lg hover:bg-orange-600 transition">
                        Update Questionnaire
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-lg shadow-xl max-w-md w-full">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Confirm Delete</h3>
            </div>

            <div class="p-6">
                <p class="text-gray-700 mb-4">Are you sure you want to delete this questionnaire?</p>
                <p class="text-sm text-gray-500 mb-4" id="deleteQuestionText"></p>
                <p class="text-sm text-gray-500 mb-4">This action cannot be undone.</p>

                <form id="deleteForm" method="POST" class="flex justify-end space-x-3">
                    <input type="hidden" name="action" value="delete_questionnaire">
                    <input type="hidden" id="delete_questionnaire_id" name="questionnaire_id">

                    <button type="button" onclick="closeDeleteModal()"
                            class="px-4 py-2 text-gray-700 bg-gray-200 rounded-lg hover:bg-gray-300 transition">
                        Cancel
                    </button>
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
// Show/hide multiple choice options based on question type
document.getElementById('question_type').addEventListener('change', function() {
    const optionsDiv = document.getElementById('multiple_choice_options');
    const previewDiv = document.getElementById('question_preview');
    const questionText = document.getElementById('question_text').value.trim();

    if (this.value === 'multiple_choice') {
        optionsDiv.classList.remove('hidden');
        if (questionText) {
            previewDiv.innerHTML = `<strong>Question:</strong> ${questionText}<br><span class="text-orange-600">Type: Multiple choice with custom options</span>`;
        } else {
            previewDiv.innerHTML = '<span class="text-orange-600">Multiple choice question with custom options</span>';
        }
    } else {
        optionsDiv.classList.add('hidden');

        // Update preview based on question type
        let previewText = '';
        if (questionText) {
            previewText = `<strong>Question:</strong> ${questionText}<br>`;
        }

        switch(this.value) {
            case 'rating_1_5':
                previewText += '<span class="text-purple-600">Type: Rating scale (1-5)</span>';
                break;
            case 'text':
                previewText += '<span class="text-blue-600">Type: Text response</span>';
                break;
            case 'yes_no':
                previewText += '<span class="text-green-600">Type: Yes/No choice</span>';
                break;
            default:
                previewText = '<span class="text-gray-500 italic">Select question type to see preview...</span>';
        }

        previewDiv.innerHTML = previewText;
    }
});

// Update preview when question text changes
document.getElementById('question_text').addEventListener('input', function() {
    updateQuestionPreview();
});

// Update preview when required checkbox changes
document.getElementById('required').addEventListener('change', function() {
    updateQuestionPreview();
});

// Function to update the question preview
function updateQuestionPreview() {
    const questionType = document.getElementById('question_type').value;
    const questionText = document.getElementById('question_text').value.trim();
    const isRequired = document.getElementById('required').checked;
    const previewDiv = document.getElementById('question_preview');

    if (!questionType) {
        previewDiv.innerHTML = '<span class="text-gray-500 italic">Select question type to see preview...</span>';
        return;
    }

    let previewText = '';

    // Add question text if available
    if (questionText) {
        previewText = `<strong>Question:</strong> ${questionText}<br>`;
    } else {
        previewText = '<strong>Question:</strong> <span class="text-gray-400 italic">Enter your question...</span><br>';
    }

    // Add question type
    switch(questionType) {
        case 'rating_1_5':
            previewText += '<span class="text-purple-600">Type: Rating scale (1-5)</span>';
            break;
        case 'text':
            previewText += '<span class="text-blue-600">Type: Text response</span>';
            break;
        case 'yes_no':
            previewText += '<span class="text-green-600">Type: Yes/No choice</span>';
            break;
        case 'multiple_choice':
            previewText += '<span class="text-orange-600">Type: Multiple choice</span>';
            break;
    }

    // Add required/optional status
    previewText += `<br><span class="text-sm ${isRequired ? 'text-red-600' : 'text-gray-600'}">${isRequired ? 'Required' : 'Optional'}</span>`;

    previewDiv.innerHTML = previewText;
}

// Initialize preview on page load
document.addEventListener('DOMContentLoaded', function() {
    updateQuestionPreview();
});

document.getElementById('edit_question_type').addEventListener('change', function() {
    const optionsDiv = document.getElementById('edit_multiple_choice_options');
    if (this.value === 'multiple_choice') {
        optionsDiv.classList.remove('hidden');
    } else {
        optionsDiv.classList.add('hidden');
    }
});

function addOption() {
    const container = document.getElementById('options_container');
    const optionCount = container.children.length;

    // Limit to 10 options
    if (optionCount >= 10) {
        alert('You can only add up to 10 options.');
        return;
    }

    const optionDiv = document.createElement('div');
    optionDiv.className = 'flex items-center space-x-3';
    optionDiv.innerHTML = `
        <div class="flex-shrink-0 w-6 h-6 bg-seait-orange rounded-full flex items-center justify-center">
            <span class="text-white text-xs font-medium">${optionCount + 1}</span>
        </div>
        <input type="text" name="options[]" placeholder="Enter option ${optionCount + 1}" required
               class="flex-1 px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-seait-orange focus:border-transparent">
        <button type="button" onclick="removeOption(this)" class="text-red-600 hover:text-red-800 p-2">
            <i class="fas fa-times"></i>
        </button>
    `;
    container.appendChild(optionDiv);

    // Update option numbers
    updateOptionNumbers();
}

function removeOption(button) {
    const container = document.getElementById('options_container');
    const optionCount = container.children.length;

    // Ensure at least 2 options remain
    if (optionCount <= 2) {
        alert('At least 2 options are required for multiple choice questions.');
        return;
    }

    button.parentElement.remove();
    updateOptionNumbers();
}

function updateOptionNumbers() {
    const container = document.getElementById('options_container');
    const options = container.children;

    for (let i = 0; i < options.length; i++) {
        const numberSpan = options[i].querySelector('span');
        const input = options[i].querySelector('input');

        if (numberSpan) {
            numberSpan.textContent = i + 1;
        }
        if (input) {
            input.placeholder = `Enter option ${i + 1}`;
        }
    }
}

function resetForm() {
    if (confirm('Are you sure you want to reset the form? All entered data will be lost.')) {
        document.getElementById('question_text').value = '';
        document.getElementById('question_type').value = '';
        document.getElementById('required').checked = true;
        document.getElementById('multiple_choice_options').classList.add('hidden');
        document.getElementById('question_preview').innerHTML = '<span class="text-gray-500 italic">Select question type to see preview...</span>';

        // Reset multiple choice options
        const container = document.getElementById('options_container');
        container.innerHTML = `
            <div class="flex items-center space-x-3">
                <div class="flex-shrink-0 w-6 h-6 bg-seait-orange rounded-full flex items-center justify-center">
                    <span class="text-white text-xs font-medium">1</span>
                </div>
                <input type="text" name="options[]" placeholder="Enter option 1" required
                       class="flex-1 px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-seait-orange focus:border-transparent">
                <button type="button" onclick="removeOption(this)" class="text-red-600 hover:text-red-800 p-2">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="flex items-center space-x-3">
                <div class="flex-shrink-0 w-6 h-6 bg-seait-orange rounded-full flex items-center justify-center">
                    <span class="text-white text-xs font-medium">2</span>
                </div>
                <input type="text" name="options[]" placeholder="Enter option 2" required
                       class="flex-1 px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-seait-orange focus:border-transparent">
                <button type="button" onclick="removeOption(this)" class="text-red-600 hover:text-red-800 p-2">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;
    }
}

function addEditOption() {
    const container = document.getElementById('edit_options_container');
    const optionDiv = document.createElement('div');
    optionDiv.className = 'flex items-center space-x-3';
    optionDiv.innerHTML = `
        <input type="text" name="options[]" placeholder="New option"
               class="flex-1 px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-seait-orange focus:border-transparent">
        <button type="button" onclick="removeOption(this)" class="text-red-600 hover:text-red-800 p-2">
            <i class="fas fa-times"></i>
        </button>
    `;
    container.appendChild(optionDiv);
}

function editQuestionnaire(questionnaire) {
    document.getElementById('edit_questionnaire_id').value = questionnaire.id;
    document.getElementById('edit_question_text').value = questionnaire.question_text;
    document.getElementById('edit_question_type').value = questionnaire.question_type;
    document.getElementById('edit_order_number').value = questionnaire.order_number;
    document.getElementById('edit_status').value = questionnaire.status;
    document.getElementById('edit_required').checked = questionnaire.required == 1;

    // Handle multiple choice options
    const editOptionsContainer = document.getElementById('edit_options_container');
    editOptionsContainer.innerHTML = '';

    if (questionnaire.question_type === 'multiple_choice' && questionnaire.options) {
        const options = JSON.parse(questionnaire.options);
        options.forEach((option, index) => {
            const optionDiv = document.createElement('div');
            optionDiv.className = 'flex items-center space-x-3';
            optionDiv.innerHTML = `
                <div class="flex-shrink-0 w-6 h-6 bg-seait-orange rounded-full flex items-center justify-center">
                    <span class="text-white text-xs font-medium">${index + 1}</span>
                </div>
                <input type="text" name="options[]" value="${option}"
                       class="flex-1 px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-seait-orange focus:border-transparent">
                <button type="button" onclick="removeOption(this)" class="text-red-600 hover:text-red-800 p-2">
                    <i class="fas fa-times"></i>
                </button>
            `;
            editOptionsContainer.appendChild(optionDiv);
        });
        document.getElementById('edit_multiple_choice_options').classList.remove('hidden');
    } else {
        document.getElementById('edit_multiple_choice_options').classList.add('hidden');
    }

    document.getElementById('editModal').classList.remove('hidden');
}

function closeEditModal() {
    document.getElementById('editModal').classList.add('hidden');

    // Add visual feedback that the modal was closed
    const modal = document.getElementById('editModal');
    modal.style.opacity = '0';
    setTimeout(() => {
        modal.style.opacity = '';
    }, 300);
}

function deleteQuestionnaire(id, questionText) {
    document.getElementById('delete_questionnaire_id').value = id;
    document.getElementById('deleteQuestionText').textContent = questionText;
    document.getElementById('deleteModal').classList.remove('hidden');
}

function closeDeleteModal() {
    document.getElementById('deleteModal').classList.add('hidden');
}

// Close modals when clicking outside
document.getElementById('editModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeEditModal();
    }
});

document.getElementById('deleteModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeDeleteModal();
    }
});

// Add success feedback for form submissions
document.addEventListener('DOMContentLoaded', function() {
    // Check if there's a success message and add animation
    const successMessage = document.querySelector('.bg-green-50');
    if (successMessage) {
        successMessage.style.animation = 'slideInDown 0.5s ease-out';

        // Add highlight effect to question cards
        const questionCards = document.querySelectorAll('.border.border-gray-200.rounded-lg');
        questionCards.forEach(card => {
            card.classList.add('success-highlight');
            setTimeout(() => {
                card.classList.remove('success-highlight');
            }, 3000);
        });

        // Auto-hide success messages after 5 seconds
        setTimeout(() => {
            if (successMessage.parentElement) {
                successMessage.style.animation = 'slideOutUp 0.5s ease-out';
                setTimeout(() => {
                    successMessage.remove();
                }, 500);
            }
        }, 5000);
    }

    // Add form submission feedback
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function() {
            const submitBtn = this.querySelector('button[type="submit"]');
            if (submitBtn) {
                const originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Processing...';
                submitBtn.disabled = true;

                // Re-enable after a delay (in case of validation errors)
                setTimeout(() => {
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                }, 3000);
            }
        });
    });

    // Add smooth scrolling to success message
    if (successMessage) {
        successMessage.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
});

// Add CSS animations
const style = document.createElement('style');
style.textContent = `
    @keyframes slideInDown {
        from {
            transform: translateY(-100%);
            opacity: 0;
        }
        to {
            transform: translateY(0);
            opacity: 1;
        }
    }

    @keyframes slideOutUp {
        from {
            transform: translateY(0);
            opacity: 1;
        }
        to {
            transform: translateY(-100%);
            opacity: 0;
        }
    }

    @keyframes pulse {
        0%, 100% {
            opacity: 1;
        }
        50% {
            opacity: 0.5;
        }
    }

    .success-highlight {
        animation: pulse 2s ease-in-out;
        background-color: #f0fdf4;
        border-color: #22c55e;
    }
`;
document.head.appendChild(style);

// Sorting functionality
function sortQuestions() {
    const sortOrder = document.getElementById('sortOrder').value;
    const questionsContainer = document.querySelector('.space-y-6');
    const questions = Array.from(questionsContainer.children);

    questions.sort((a, b) => {
        switch(sortOrder) {
            case 'order':
                const orderA = parseInt(a.querySelector('.bg-seait-orange span').textContent);
                const orderB = parseInt(b.querySelector('.bg-seait-orange span').textContent);
                return orderA - orderB;
            case 'type':
                const typeA = a.querySelector('.bg-purple-100, .bg-blue-100, .bg-green-100, .bg-orange-100').textContent.trim();
                const typeB = b.querySelector('.bg-purple-100, .bg-blue-100, .bg-green-100, .bg-orange-100').textContent.trim();
                return typeA.localeCompare(typeB);
            case 'status':
                const statusA = a.querySelector('.bg-green-100, .bg-gray-100').textContent.trim();
                const statusB = b.querySelector('.bg-green-100, .bg-gray-100').textContent.trim();
                return statusA.localeCompare(statusB);
            default:
                return 0;
        }
    });

    // Clear and re-append sorted questions
    questionsContainer.innerHTML = '';
    questions.forEach(question => questionsContainer.appendChild(question));

    // Add visual feedback
    const sortSelect = document.getElementById('sortOrder');
    sortSelect.style.backgroundColor = '#fef3c7';
    setTimeout(() => {
        sortSelect.style.backgroundColor = '';
    }, 500);
}

// Form validation
document.querySelector('form').addEventListener('submit', function(e) {
    const questionType = document.getElementById('question_type').value;
    const questionText = document.getElementById('question_text').value.trim();

    if (!questionText) {
        e.preventDefault();
        alert('Please enter a question text.');
        document.getElementById('question_text').focus();
        return;
    }

    if (!questionType) {
        e.preventDefault();
        alert('Please select a question type.');
        document.getElementById('question_type').focus();
        return;
    }

    if (questionType === 'multiple_choice') {
        const options = document.querySelectorAll('#options_container input[name="options[]"]');
        let validOptions = 0;

        options.forEach(option => {
            if (option.value.trim()) {
                validOptions++;
            }
        });

        if (validOptions < 2) {
            e.preventDefault();
            alert('Multiple choice questions require at least 2 options.');
            return;
        }
    }
});
</script>

<?php
// Include the shared footer
include 'includes/footer.php';
?>