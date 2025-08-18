<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and has student role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../login.php');
    exit();
}

// Set page title
$page_title = 'Conduct Evaluation';

$message = '';
$message_type = '';

// Get session_id from URL
$session_id = isset($_GET['session_id']) ? (int)$_GET['session_id'] : null;

if (!$session_id) {
    header('Location: evaluate-teacher.php');
    exit();
}

// Get student_id for verification
$student_id = get_student_id($conn, $_SESSION['email']);

// Verify the evaluation session belongs to this student and check if it's started by guidance officer
$session_query = "SELECT es.*, mec.name as category_name, mec.evaluation_type,
                  COALESCE(f.first_name, u.first_name) as teacher_first_name,
                  COALESCE(f.last_name, u.last_name) as teacher_last_name,
                  COALESCE(f.email, u.email) as teacher_email
                  FROM evaluation_sessions es
                  JOIN main_evaluation_categories mec ON es.main_category_id = mec.id
                  LEFT JOIN faculty f ON es.evaluatee_id = f.id
                  LEFT JOIN users u ON es.evaluatee_id = u.id
                  WHERE es.id = ? AND es.evaluator_id = ? AND es.evaluator_type = 'student'";
$session_stmt = mysqli_prepare($conn, $session_query);
mysqli_stmt_bind_param($session_stmt, "ii", $session_id, $_SESSION['user_id']);
mysqli_stmt_execute($session_stmt);
$session_result = mysqli_stmt_get_result($session_stmt);
$evaluation_session = mysqli_fetch_assoc($session_result);

if (!$evaluation_session) {
    $message = "Evaluation session not found or you don't have permission to access it.";
    $message_type = "error";
} else {
    // Check if there's an active evaluation schedule started by guidance officer
    $schedule_check = "SELECT es.*, s.name as semester_name
                       FROM evaluation_schedules es
                       JOIN semesters s ON es.semester_id = s.id
                       WHERE es.evaluation_type = ?
                       AND es.status = 'active'
                       AND NOW() BETWEEN es.start_date AND es.end_date";
    $schedule_stmt = mysqli_prepare($conn, $schedule_check);
    mysqli_stmt_bind_param($schedule_stmt, "s", $evaluation_session['evaluation_type']);
    mysqli_stmt_execute($schedule_stmt);
    $schedule_result = mysqli_stmt_get_result($schedule_stmt);
    $active_schedule = mysqli_fetch_assoc($schedule_result);

    if (!$active_schedule) {
        $message = "No active evaluation period found. Please wait for the guidance officer to start the evaluation period.";
        $message_type = "error";
    }
}

// Handle form submission for evaluation responses
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $evaluation_session && $active_schedule) {
    if (isset($_POST['action']) && $_POST['action'] === 'submit_evaluation') {
        $responses = $_POST['responses'] ?? [];
        $success = true;

        // Start transaction
        mysqli_begin_transaction($conn);

        try {
            // Save each response
            foreach ($responses as $questionnaire_id => $response) {
                $questionnaire_id = (int)$questionnaire_id;
                $rating_value = isset($response['rating']) ? (int)$response['rating'] : null;
                $text_response = isset($response['text']) ? sanitize_input($response['text']) : null;
                $multiple_choice = isset($response['multiple_choice']) ? sanitize_input($response['multiple_choice']) : null;
                $yes_no = isset($response['yes_no']) ? sanitize_input($response['yes_no']) : null;

                // Check if response already exists
                $existing_query = "SELECT id FROM evaluation_responses
                                  WHERE evaluation_session_id = ? AND questionnaire_id = ?";
                $existing_stmt = mysqli_prepare($conn, $existing_query);
                mysqli_stmt_bind_param($existing_stmt, "ii", $session_id, $questionnaire_id);
                mysqli_stmt_execute($existing_stmt);
                $existing_result = mysqli_stmt_get_result($existing_stmt);

                if (mysqli_num_rows($existing_result) > 0) {
                    // Update existing response
                    $update_query = "UPDATE evaluation_responses
                                    SET rating_value = ?, text_response = ?, multiple_choice_response = ?,
                                        yes_no_response = ?, updated_at = NOW()
                                    WHERE evaluation_session_id = ? AND questionnaire_id = ?";
                    $update_stmt = mysqli_prepare($conn, $update_query);
                    mysqli_stmt_bind_param($update_stmt, "isssii", $rating_value, $text_response, $multiple_choice, $yes_no, $session_id, $questionnaire_id);
                    $success = mysqli_stmt_execute($update_stmt) && $success;
                } else {
                    // Insert new response
                    $insert_query = "INSERT INTO evaluation_responses
                                    (evaluation_session_id, questionnaire_id, rating_value, text_response,
                                     multiple_choice_response, yes_no_response, created_at)
                                    VALUES (?, ?, ?, ?, ?, ?, NOW())";
                    $insert_stmt = mysqli_prepare($conn, $insert_query);
                    mysqli_stmt_bind_param($insert_stmt, "iiisss", $session_id, $questionnaire_id, $rating_value, $text_response, $multiple_choice, $yes_no);
                    $success = mysqli_stmt_execute($insert_stmt) && $success;
                }
            }

            // Update evaluation session status to completed
            if ($success) {
                $update_session = "UPDATE evaluation_sessions
                                  SET status = 'completed', updated_at = NOW()
                                  WHERE id = ?";
                $update_session_stmt = mysqli_prepare($conn, $update_session);
                mysqli_stmt_bind_param($update_session_stmt, "i", $session_id);
                $success = mysqli_stmt_execute($update_session_stmt);
            }

            if ($success) {
                mysqli_commit($conn);
                $message = "Evaluation submitted successfully!";
                $message_type = "success";
                // Refresh the session data
                $session_stmt = mysqli_prepare($conn, $session_query);
                mysqli_stmt_bind_param($session_stmt, "ii", $session_id, $_SESSION['user_id']);
                mysqli_stmt_execute($session_stmt);
                $session_result = mysqli_stmt_get_result($session_stmt);
                $evaluation_session = mysqli_fetch_assoc($session_result);
            } else {
                throw new Exception("Error saving evaluation responses");
            }
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $message = "Error submitting evaluation: " . $e->getMessage();
            $message_type = "error";
        }
    }
}

// Get evaluation questionnaires for this category
$questionnaires = [];
$sub_categories = [];
if ($evaluation_session) {
    $questionnaires_query = "SELECT eq.*, esc.name as sub_category_name, esc.order_number as sub_category_order
                            FROM evaluation_questionnaires eq
                            JOIN evaluation_sub_categories esc ON eq.sub_category_id = esc.id
                            WHERE esc.main_category_id = ? AND eq.status = 'active'
                            ORDER BY esc.order_number, eq.order_number";
    $questionnaires_stmt = mysqli_prepare($conn, $questionnaires_query);
    mysqli_stmt_bind_param($questionnaires_stmt, "i", $evaluation_session['main_category_id']);
    mysqli_stmt_execute($questionnaires_stmt);
    $questionnaires_result = mysqli_stmt_get_result($questionnaires_stmt);

    while ($questionnaire = mysqli_fetch_assoc($questionnaires_result)) {
        $questionnaires[] = $questionnaire;

        // Group by sub-categories
        $sub_category_name = $questionnaire['sub_category_name'];
        if (!isset($sub_categories[$sub_category_name])) {
            $sub_categories[$sub_category_name] = [
                'name' => $sub_category_name,
                'order' => $questionnaire['sub_category_order'],
                'questions' => []
            ];
        }
        $sub_categories[$sub_category_name]['questions'][] = $questionnaire;
    }

    // Sort sub-categories by order
    uasort($sub_categories, function($a, $b) {
        return $a['order'] - $b['order'];
    });
}

// Get existing responses
$existing_responses = [];
if ($evaluation_session) {
    $responses_query = "SELECT questionnaire_id, rating_value, text_response, multiple_choice_response, yes_no_response
                        FROM evaluation_responses
                        WHERE evaluation_session_id = ?";
    $responses_stmt = mysqli_prepare($conn, $responses_query);
    mysqli_stmt_bind_param($responses_stmt, "i", $session_id);
    mysqli_stmt_execute($responses_stmt);
    $responses_result = mysqli_stmt_get_result($responses_stmt);

    while ($response = mysqli_fetch_assoc($responses_result)) {
        $existing_responses[$response['questionnaire_id']] = $response;
    }
}

// Calculate progress for each sub-category and overall
$category_progress = [];
$total_questions = 0;
$total_answered = 0;

foreach ($sub_categories as $sub_category_name => $sub_category) {
    $category_questions = count($sub_category['questions']);
    $category_answered = 0;
    $total_questions += $category_questions;

    foreach ($sub_category['questions'] as $questionnaire) {
        $existing_response = $existing_responses[$questionnaire['id']] ?? null;
        if ($existing_response) {
            if ($questionnaire['question_type'] === 'rating_1_5' && $existing_response['rating_value'] !== null) {
                $category_answered++;
                $total_answered++;
            } elseif ($questionnaire['question_type'] === 'text' && !empty($existing_response['text_response'])) {
                $category_answered++;
                $total_answered++;
            } elseif ($questionnaire['question_type'] === 'yes_no' && $existing_response['yes_no_response'] !== null) {
                $category_answered++;
                $total_answered++;
            } elseif ($questionnaire['question_type'] === 'multiple_choice' && $existing_response['multiple_choice_response'] !== null) {
                $category_answered++;
                $total_answered++;
            }
        }
    }

    $category_progress[$sub_category_name] = [
        'total' => $category_questions,
        'answered' => $category_answered,
        'percentage' => $category_questions > 0 ? round(($category_answered / $category_questions) * 100) : 0
    ];
}

$overall_progress_percentage = $total_questions > 0 ? round(($total_answered / $total_questions) * 100) : 0;

// Include the shared header
include 'includes/header.php';
?>

<style>
/* Floating Tabs Styles */
.sticky {
    position: sticky;
    top: 0;
    z-index: 1000;
    background: white;
    border-bottom: 1px solid #e5e7eb;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    margin-top: 0;
    padding-top: 0;
}

.category-tab {
    transition: all 0.2s ease-in-out;
    border: 1px solid transparent;
}

.category-tab:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.category-tab.active {
    background: linear-gradient(135deg, #f97316, #ea580c);
    color: white;
    box-shadow: 0 4px 8px rgba(249, 115, 22, 0.3);
}

.category-content {
    transition: opacity 0.3s ease-in-out;
}

.category-content.hidden {
    display: none;
}

.category-content.active {
    display: block;
    animation: fadeIn 0.3s ease-in-out;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Progress bar animations */
.bg-seait-orange {
    transition: width 0.3s ease-in-out;
}

/* Selected Answer Highlighting */
.rating-star.selected {
    border-color: #f97316;
    background-color: #fff7ed;
    color: #f97316;
    box-shadow: 0 2px 4px rgba(249, 115, 22, 0.2);
    transform: scale(1.05);
}

.rating-star.selected span {
    font-weight: 600;
}

/* Yes/No Answer Highlighting */
.yes-no-option.selected {
    background-color: #f0fdf4 !important;
    border-color: #10b981 !important;
    box-shadow: 0 2px 4px rgba(16, 185, 129, 0.2);
}

.yes-no-option.selected-yes {
    background-color: #f0fdf4 !important;
    border-color: #10b981 !important;
}

.yes-no-option.selected-no {
    background-color: #fef2f2 !important;
    border-color: #ef4444 !important;
}

.yes-no-option.selected span {
    font-weight: 600;
}

/* Multiple Choice Answer Highlighting */
.multiple-choice-option.selected {
    background-color: #eff6ff !important;
    border-color: #3b82f6 !important;
    box-shadow: 0 2px 4px rgba(59, 130, 246, 0.2);
}

.multiple-choice-option.selected span {
    font-weight: 600;
    color: #1e40af;
}

/* Text Answer Highlighting */
.text-answer-filled {
    border-color: #10b981 !important;
    background-color: #f0fdf4 !important;
    box-shadow: 0 2px 4px rgba(16, 185, 129, 0.1);
}

.text-answer-filled:focus {
    border-color: #10b981 !important;
    box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1) !important;
}

/* Question Card Enhancement */
.question-card.answered {
    border-color: #10b981;
    background-color: #f0fdf4;
    box-shadow: 0 2px 8px rgba(16, 185, 129, 0.1);
}

.question-card.answered .border-l-4 {
    border-color: #10b981;
}

/* Completed Category Tab Styling */
.category-tab.completed {
    background-color: #f0fdf4 !important;
    border-color: #10b981 !important;
    color: #065f46 !important;
}

.category-tab.completed:hover {
    background-color: #dcfce7 !important;
}

.category-tab.completed .rounded-full {
    background-color: #10b981 !important;
    color: white !important;
}

.category-tab.completed .fa-chevron-right {
    color: #10b981 !important;
}

/* Active completed tab */
.category-tab.completed.active {
    background: linear-gradient(135deg, #10b981, #059669) !important;
    color: white !important;
    box-shadow: 0 4px 8px rgba(16, 185, 129, 0.3);
}

.category-tab.completed.active .rounded-full {
    background-color: rgba(255, 255, 255, 0.2) !important;
}

.category-tab.completed.active .fa-chevron-right {
    color: white !important;
}

/* Answer Status Indicator */
.answer-status {
    display: inline-flex;
    align-items: center;
    padding: 0.25rem 0.5rem;
    border-radius: 0.375rem;
    font-size: 0.75rem;
    font-weight: 500;
    margin-left: 0.5rem;
}

.answer-status.answered {
    background-color: #10b981;
    color: white;
}

.answer-status.pending {
    background-color: #f59e0b;
    color: white;
}

.rating-stars {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.rating-star {
    display: inline-flex;
    align-items: center;
    padding: 0.5rem 1rem;
    border: 1px solid #e5e7eb;
    border-radius: 0.5rem;
    cursor: pointer;
    transition: all 0.2s ease-in-out;
    background: white;
}

.rating-star:hover {
    border-color: #f97316;
    background-color: #fff7ed;
}

.rating-star.selected {
    border-color: #f97316;
    background-color: #fff7ed;
    color: #f97316;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .sticky {
        position: sticky;
        top: 0;
        z-index: 1000;
        background: white;
        border-bottom: 1px solid #e5e7eb;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        margin-top: 0;
        padding-top: 0;
    }

    .category-tab {
        transition: all 0.2s ease-in-out;
        border: 1px solid transparent;
    }

    .category-tab:hover {
        transform: translateY(-1px);
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }

    .category-tab.active {
        background: linear-gradient(135deg, #f97316, #ea580c);
        color: white;
        box-shadow: 0 4px 8px rgba(249, 115, 22, 0.3);
    }

    .rating-stars {
        flex-direction: column;
        gap: 0.25rem;
    }

    .rating-star {
        justify-content: center;
    }

    /* Mobile category tabs container */
    .px-6.py-4.bg-gray-50 {
        padding: 0.75rem 1rem;
    }

    .px-6.py-4.bg-gray-50 .space-y-2 {
        gap: 0.5rem;
    }

    /* Ensure proper spacing on mobile */
    .sticky .bg-white {
        margin: 0;
        border-radius: 0;
    }

    /* Mobile tab improvements */
    .category-tab {
        min-height: 48px;
        font-size: 0.875rem;
        line-height: 1.25rem;
    }

    /* Mobile tab text truncation */
    .category-tab span {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
}
</style>

<div class="mb-6 sm:mb-8">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-seait-dark mb-2">Conduct Evaluation</h1>
            <p class="text-sm sm:text-base text-gray-600">Provide comprehensive feedback for your teacher</p>
        </div>
        <a href="evaluate-teacher.php" class="bg-gray-500 text-white px-4 py-2 rounded-md hover:bg-gray-600 transition flex items-center">
            <i class="fas fa-arrow-left mr-2"></i>Back to Evaluations
        </a>
    </div>
</div>

<!-- Message Display -->
<?php if ($message && $evaluation_session['status'] !== 'completed'): ?>
<div class="mb-6 p-4 rounded-lg <?php echo $message_type === 'success' ? 'bg-green-50 border border-green-200 text-green-800' : 'bg-red-50 border border-red-200 text-red-800'; ?>">
    <?php echo htmlspecialchars($message); ?>
</div>
<?php endif; ?>

<?php if ($evaluation_session && $active_schedule): ?>
    <?php if ($evaluation_session['status'] !== 'completed'): ?>
    <!-- Evaluation Session Info -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6 sm:mb-8">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-medium text-gray-900">Evaluation Details</h2>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <h3 class="text-sm font-medium text-gray-500 mb-2">Teacher</h3>
                    <p class="text-lg font-semibold text-gray-900">
                        <?php echo htmlspecialchars($evaluation_session['teacher_first_name'] . ' ' . $evaluation_session['teacher_last_name']); ?>
                    </p>
                    <p class="text-sm text-gray-600"><?php echo htmlspecialchars($evaluation_session['teacher_email']); ?></p>
                </div>
                <div>
                    <h3 class="text-sm font-medium text-gray-500 mb-2">Evaluation Category</h3>
                    <p class="text-lg font-semibold text-gray-900"><?php echo htmlspecialchars($evaluation_session['category_name']); ?></p>
                    <p class="text-sm text-gray-600"><?php echo ucfirst(str_replace('_', ' ', $evaluation_session['evaluation_type'])); ?></p>
                </div>
                <div>
                    <h3 class="text-sm font-medium text-gray-500 mb-2">Status</h3>
                    <span class="px-3 py-1 text-sm rounded-full <?php
                        echo $evaluation_session['status'] === 'completed' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800';
                    ?> font-medium">
                        <i class="fas <?php echo $evaluation_session['status'] === 'completed' ? 'fa-check' : 'fa-clock'; ?> mr-1"></i>
                        <?php echo ucfirst($evaluation_session['status']); ?>
                    </span>
                </div>
                <div>
                    <h3 class="text-sm font-medium text-gray-500 mb-2">Evaluation Period</h3>
                    <p class="text-sm text-gray-900 font-medium"><?php echo htmlspecialchars($active_schedule['semester_name']); ?></p>
                    <p class="text-xs text-gray-600">
                        <?php echo date('M d, Y', strtotime($active_schedule['start_date'])); ?> -
                        <?php echo date('M d, Y', strtotime($active_schedule['end_date'])); ?>
                    </p>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($evaluation_session['status'] !== 'completed'): ?>
        <!-- Floating Category Tabs -->
        <div class="sticky z-10 mb-6 sm:mb-8">
            <div class="bg-white rounded-lg shadow-lg border border-gray-200 overflow-hidden">
                <!-- Overall Progress Header -->
                <div class="px-6 py-4 bg-gradient-to-r from-seait-orange to-orange-500 text-white">
                    <!-- Mobile Layout -->
                    <div class="block sm:hidden">
                        <div class="flex items-center justify-between mb-3">
                            <h3 class="text-lg font-medium">Overall Progress</h3>
                            <button type="button" id="autoProgressToggleMobile" class="flex items-center space-x-1 hover:bg-white hover:bg-opacity-20 px-2 py-1 rounded transition-all duration-200">
                                <i class="fas fa-arrow-right text-xs"></i>
                                <span id="autoProgressTextMobile" class="text-xs">Auto</span>
                            </button>
                        </div>
                        <div class="text-center mb-3">
                            <span class="text-sm font-medium"><?php echo $total_answered; ?> of <?php echo $total_questions; ?> questions answered</span>
                        </div>
                        <div class="w-full bg-white bg-opacity-30 rounded-full h-2 mb-2">
                            <div class="bg-white h-2 rounded-full transition-all duration-300" style="width: <?php echo $overall_progress_percentage; ?>%"></div>
                        </div>
                        <div class="text-center">
                            <span class="text-lg font-bold"><?php echo $overall_progress_percentage; ?>% Complete</span>
                        </div>
                    </div>

                    <!-- Desktop Layout -->
                    <div class="hidden sm:block">
                        <div class="flex items-center justify-between">
                            <h3 class="text-lg font-medium">Overall Progress</h3>
                            <div class="flex items-center space-x-4">
                                <div class="flex items-center space-x-2 text-sm">
                                    <button type="button" id="autoProgressToggle" class="flex items-center space-x-2 hover:bg-white hover:bg-opacity-20 px-2 py-1 rounded transition-all duration-200">
                                        <i class="fas fa-arrow-right text-xs"></i>
                                        <span id="autoProgressText">Auto-progress enabled</span>
                                    </button>
                                </div>
                                <span class="text-sm font-medium"><?php echo $total_answered; ?> of <?php echo $total_questions; ?> questions answered</span>
                            </div>
                        </div>
                        <div class="mt-3">
                            <div class="w-full bg-white bg-opacity-30 rounded-full h-2">
                                <div class="bg-white h-2 rounded-full transition-all duration-300" style="width: <?php echo $overall_progress_percentage; ?>%"></div>
                            </div>
                            <div class="mt-2 text-center">
                                <span class="text-lg font-bold"><?php echo $overall_progress_percentage; ?>% Complete</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Category Tabs -->
                <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
                    <!-- Mobile Tabs Layout -->
                    <div class="block sm:hidden">
                        <div class="space-y-2">
                            <?php
                            $first_tab = true;
                            foreach ($sub_categories as $sub_category_name => $sub_category):
                                $progress = $category_progress[$sub_category_name];
                                $tab_id = 'tab-' . preg_replace('/[^a-zA-Z0-9]/', '-', $sub_category_name);
                            ?>
                                <button
                                    class="w-full category-tab px-4 py-3 rounded-lg text-sm font-medium transition-all duration-200 flex items-center justify-between <?php echo $first_tab ? 'bg-seait-orange text-white shadow-md' : 'bg-white text-gray-700 hover:bg-gray-100 border border-gray-200'; ?>"
                                    data-tab="<?php echo $tab_id; ?>"
                                    onclick="switchTab('<?php echo $tab_id; ?>', function() { scrollToTop(); })"
                                >
                                    <div class="flex items-center space-x-2">
                                        <span class="text-left"><?php echo htmlspecialchars($sub_category_name); ?></span>
                                        <?php if ($progress['percentage'] == 100): ?>
                                            <i class="fas fa-check-circle text-xs <?php echo $first_tab ? 'text-white' : 'text-green-500'; ?>"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex items-center space-x-2">
                                        <span class="px-2 py-1 text-xs rounded-full <?php echo $first_tab ? 'bg-white bg-opacity-20' : 'bg-seait-orange text-white'; ?>">
                                            <?php echo $progress['answered']; ?>/<?php echo $progress['total']; ?>
                                        </span>
                                        <i class="fas fa-chevron-right text-xs <?php echo $first_tab ? 'text-white' : 'text-gray-400'; ?>"></i>
                                    </div>
                                </button>
                            <?php
                            $first_tab = false;
                            endforeach;
                            ?>
                        </div>
                    </div>

                    <!-- Desktop Tabs Layout -->
                    <div class="hidden sm:block">
                        <div class="flex flex-wrap gap-2">
                            <?php
                            $first_tab = true;
                            foreach ($sub_categories as $sub_category_name => $sub_category):
                                $progress = $category_progress[$sub_category_name];
                                $tab_id = 'tab-' . preg_replace('/[^a-zA-Z0-9]/', '-', $sub_category_name);
                            ?>
                                <button
                                    class="category-tab px-4 py-2 rounded-lg text-sm font-medium transition-all duration-200 flex items-center space-x-2 <?php echo $first_tab ? 'bg-seait-orange text-white shadow-md' : 'bg-white text-gray-700 hover:bg-gray-100 border border-gray-200'; ?>"
                                    data-tab="<?php echo $tab_id; ?>"
                                    onclick="switchTab('<?php echo $tab_id; ?>', function() { scrollToTop(); })"
                                >
                                    <span><?php echo htmlspecialchars($sub_category_name); ?></span>
                                    <span class="px-2 py-1 text-xs rounded-full <?php echo $first_tab ? 'bg-white bg-opacity-20' : 'bg-seait-orange text-white'; ?>">
                                        <?php echo $progress['answered']; ?>/<?php echo $progress['total']; ?>
                                    </span>
                                    <?php if ($progress['percentage'] == 100): ?>
                                        <i class="fas fa-check-circle text-xs <?php echo $first_tab ? 'text-white' : 'text-green-500'; ?>"></i>
                                    <?php endif; ?>
                                </button>
                            <?php
                            $first_tab = false;
                            endforeach;
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Evaluation Form -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6 sm:mb-8">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-medium text-gray-900">Evaluation Questions</h2>
            </div>

            <div class="p-6">
                <form method="POST" id="evaluationForm" class="space-y-6">
                    <input type="hidden" name="action" value="submit_evaluation">

                    <?php
                    $first_tab = true;
                    foreach ($sub_categories as $sub_category_name => $sub_category):
                        $tab_id = 'tab-' . preg_replace('/[^a-zA-Z0-9]/', '-', $sub_category_name);
                        $progress = $category_progress[$sub_category_name];
                    ?>
                        <div class="category-content <?php echo $first_tab ? 'active' : 'hidden'; ?>" id="<?php echo $tab_id; ?>-content" data-category="<?php echo htmlspecialchars($sub_category_name); ?>">
                            <!-- Category Header -->
                            <div class="mb-6 p-4 bg-gray-50 rounded-lg border border-gray-200">
                                <div class="flex items-center justify-between mb-3">
                                    <h3 class="text-lg font-medium text-gray-900 flex items-center">
                                        <i class="fas fa-list-ul mr-2 text-seait-orange"></i>
                                        <?php echo htmlspecialchars($sub_category_name); ?>
                                    </h3>
                                    <div class="flex items-center space-x-3">
                                        <span class="text-sm text-gray-600">
                                            <?php echo $progress['answered']; ?> of <?php echo $progress['total']; ?> questions answered
                                        </span>
                                        <div class="w-24 bg-gray-200 rounded-full h-2">
                                            <div class="bg-seait-orange h-2 rounded-full transition-all duration-300" style="width: <?php echo $progress['percentage']; ?>%"></div>
                                        </div>
                                        <span class="text-sm font-medium text-seait-orange"><?php echo $progress['percentage']; ?>%</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Questions in this category -->
                            <div class="space-y-6">
                                <?php
                                $question_count = 0;
                                foreach ($sub_category['questions'] as $questionnaire):
                                    $question_count++;
                                ?>
                                    <div class="border-l-4 border-seait-orange pl-4">
                                        <div class="mb-4">
                                            <?php
                                            $existing_response = $existing_responses[$questionnaire['id']] ?? null;
                                            $is_answered = false;
                                            if ($existing_response) {
                                                if ($questionnaire['question_type'] === 'rating_1_5' && $existing_response['rating_value'] !== null) {
                                                    $is_answered = true;
                                                } elseif ($questionnaire['question_type'] === 'text' && !empty($existing_response['text_response'])) {
                                                    $is_answered = true;
                                                } elseif ($questionnaire['question_type'] === 'yes_no' && $existing_response['yes_no_response'] !== null) {
                                                    $is_answered = true;
                                                } elseif ($questionnaire['question_type'] === 'multiple_choice' && $existing_response['multiple_choice_response'] !== null) {
                                                    $is_answered = true;
                                                }
                                            }
                                            ?>

                                            <div class="question-card <?php echo $is_answered ? 'answered' : ''; ?> p-4 border border-gray-200 rounded-lg">
                                                <div class="flex items-start justify-between mb-3">
                                                    <div class="flex-1">
                                                        <span class="inline-block bg-seait-orange text-white text-xs px-2 py-1 rounded-full mr-2">Q<?php echo $question_count; ?></span>
                                                        <?php echo htmlspecialchars($questionnaire['question_text']); ?>
                                                        <?php if ($questionnaire['required']): ?>
                                                            <span class="text-red-500 ml-1">*</span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <?php if ($is_answered): ?>
                                                        <span class="answer-status answered">
                                                            <i class="fas fa-check-circle mr-1"></i>Answered
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="answer-status pending">
                                                            <i class="fas fa-clock mr-1"></i>Pending
                                                        </span>
                                                    <?php endif; ?>
                                                </div>

                                                <?php
                                                switch ($questionnaire['question_type']):
                                                    case 'rating_1_5': ?>
                                                        <div class="rating-stars">
                                                            <?php
                                                            $rating_labels = json_decode($questionnaire['rating_labels'], true) ?:
                                                                ['1 - Poor', '2 - Good', '3 - Satisfactory', '4 - Very Satisfactory', '5 - Excellent'];
                                                            for ($i = 1; $i <= 5; $i++): ?>
                                                                <label class="rating-star <?php echo ($existing_response && $existing_response['rating_value'] == $i) ? 'selected' : ''; ?>">
                                                                    <input type="radio" name="responses[<?php echo $questionnaire['id']; ?>][rating]"
                                                                           value="<?php echo $i; ?>"
                                                                           <?php echo ($existing_response && $existing_response['rating_value'] == $i) ? 'checked' : ''; ?>
                                                                           <?php echo $questionnaire['required'] ? 'required' : ''; ?>
                                                                           class="hidden"
                                                                           onchange="updateProgress()">
                                                                    <span class="text-sm font-medium"><?php echo $rating_labels[$i-1] ?? $i; ?></span>
                                                                </label>
                                                            <?php endfor; ?>
                                                        </div>
                                                        <?php break;

                                                    case 'text': ?>
                                                        <textarea name="responses[<?php echo $questionnaire['id']; ?>][text]"
                                                                  rows="4"
                                                                  <?php echo $questionnaire['required'] ? 'required' : ''; ?>
                                                                  class="w-full px-4 py-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange <?php echo $existing_response && !empty($existing_response['text_response']) ? 'text-answer-filled' : ''; ?>"
                                                                  placeholder="Enter your detailed response here..."
                                                                  onchange="updateProgress()"><?php echo $existing_response ? htmlspecialchars($existing_response['text_response']) : ''; ?></textarea>
                                                        <div class="mt-2 text-xs text-gray-500">
                                                            <i class="fas fa-info-circle mr-1"></i>Provide specific examples and constructive feedback
                                                        </div>
                                                        <?php break;

                                                    case 'yes_no': ?>
                                                        <div class="space-y-3">
                                                            <label class="flex items-center p-3 border border-gray-200 rounded-lg hover:bg-gray-50 transition-all duration-200 <?php echo ($existing_response && $existing_response['yes_no_response'] === 'yes') ? 'yes-no-option selected-yes' : ''; ?>">
                                                                <input type="radio" name="responses[<?php echo $questionnaire['id']; ?>][yes_no]"
                                                                       value="yes"
                                                                       <?php echo ($existing_response && $existing_response['yes_no_response'] === 'yes') ? 'checked' : ''; ?>
                                                                       <?php echo $questionnaire['required'] ? 'required' : ''; ?>
                                                                       class="mr-3 text-seait-orange focus:ring-seait-orange"
                                                                       onchange="updateProgress()">
                                                                <span class="text-sm text-gray-700 font-medium">Yes</span>
                                                            </label>
                                                            <label class="flex items-center p-3 border border-gray-200 rounded-lg hover:bg-gray-50 transition-all duration-200 <?php echo ($existing_response && $existing_response['yes_no_response'] === 'no') ? 'yes-no-option selected-no' : ''; ?>">
                                                                <input type="radio" name="responses[<?php echo $questionnaire['id']; ?>][yes_no]"
                                                                       value="no"
                                                                       <?php echo ($existing_response && $existing_response['yes_no_response'] === 'no') ? 'checked' : ''; ?>
                                                                       <?php echo $questionnaire['required'] ? 'required' : ''; ?>
                                                                       class="mr-3 text-seait-orange focus:ring-seait-orange"
                                                                       onchange="updateProgress()">
                                                                <span class="text-sm text-gray-700 font-medium">No</span>
                                                            </label>
                                                        </div>
                                                        <?php break;

                                                    case 'multiple_choice':
                                                        $options = json_decode($questionnaire['options'], true) ?: ['Option 1', 'Option 2', 'Option 3']; ?>
                                                        <div class="space-y-3">
                                                            <?php foreach ($options as $option): ?>
                                                                <label class="flex items-center p-3 border border-gray-200 rounded-lg hover:bg-gray-50 transition-all duration-200 <?php echo ($existing_response && $existing_response['multiple_choice_response'] === $option) ? 'multiple-choice-option selected' : ''; ?>">
                                                                    <input type="radio" name="responses[<?php echo $questionnaire['id']; ?>][multiple_choice]"
                                                                           value="<?php echo htmlspecialchars($option); ?>"
                                                                           <?php echo ($existing_response && $existing_response['multiple_choice_response'] === $option) ? 'checked' : ''; ?>
                                                                           <?php echo $questionnaire['required'] ? 'required' : ''; ?>
                                                                           class="mr-3 text-seait-orange focus:ring-seait-orange"
                                                                           onchange="updateProgress()">
                                                                    <span class="text-sm text-gray-700 font-medium"><?php echo htmlspecialchars($option); ?></span>
                                                                </label>
                                                            <?php endforeach; ?>
                                                        </div>
                                                        <?php break;
                                                endswitch; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php
                    $first_tab = false;
                    endforeach;
                    ?>

                    <!-- Submit Buttons -->
                    <div class="flex flex-col sm:flex-row gap-4 mt-8">
                        <button type="button" onclick="saveDraft()" class="flex-1 bg-gray-500 text-white px-4 py-2 rounded-md hover:bg-gray-600 transition">
                            <i class="fas fa-save mr-2"></i>Save Draft
                        </button>
                        <button type="submit" class="flex-1 bg-seait-orange text-white px-4 py-2 rounded-md hover:bg-orange-600 transition">
                            <i class="fas fa-paper-plane mr-2"></i>
                            <?php echo $evaluation_session['status'] === 'draft' ? 'Submit Evaluation' : 'Update Evaluation'; ?>
                        </button>
                    </div>
                    <div class="mt-4 text-center text-sm text-gray-600">
                        <i class="fas fa-info-circle mr-1"></i>
                        Your responses are automatically saved as you type. You can submit when ready.
                    </div>
                </form>
            </div>
        </div>
    <?php else: ?>
        <!-- Completed Evaluation Display -->
        <div class="bg-green-50 rounded-lg p-6 mb-6 sm:mb-8">
            <div class="text-center">
                <i class="fas fa-check-circle text-green-500 text-4xl mb-4"></i>
                <h3 class="text-xl font-bold text-green-900 mb-2">Evaluation Completed!</h3>
                <p class="text-green-700 mb-4">Thank you for providing your valuable feedback. Your evaluation has been submitted successfully.</p>
                <p class="text-sm text-green-600 mb-6">You will be redirected to the evaluations page in <span id="countdown">3</span> seconds.</p>

                <div class="mt-6">
                    <a href="evaluate-teacher.php" class="bg-green-500 text-white px-6 py-3 rounded-md hover:bg-green-600 transition font-medium">
                        <i class="fas fa-arrow-left mr-2"></i>Back to Evaluations
                    </a>
                </div>
            </div>
        </div>
    <?php endif; ?>

<?php elseif ($evaluation_session && !$active_schedule): ?>
    <!-- No Active Schedule -->
    <div class="bg-yellow-50 rounded-lg p-6 mb-6 sm:mb-8">
        <div class="text-center">
            <i class="fas fa-clock text-yellow-500 text-4xl mb-4"></i>
            <h3 class="text-xl font-bold text-yellow-900 mb-2">Evaluation Period Not Active</h3>
            <p class="text-yellow-700">The evaluation period for this category has not been started by the guidance officer yet. Please wait for the evaluation period to begin.</p>
        </div>
        <div class="mt-6 text-center">
            <a href="evaluate-teacher.php" class="bg-yellow-500 text-white px-6 py-3 rounded-md hover:bg-yellow-600 transition font-medium">
                <i class="fas fa-arrow-left mr-2"></i>Back to Evaluations
            </a>
        </div>
    </div>
<?php endif; ?>

<script>
let progressData = {
    total: <?php echo $total_questions; ?>,
    answered: <?php echo $total_answered; ?>
};

let categoryProgress = <?php echo json_encode($category_progress); ?>;

// Auto-progression settings
let autoProgressEnabled = localStorage.getItem('autoProgressEnabled') !== 'false'; // Default to true

function updateProgress() {
    const form = document.getElementById('evaluationForm');
    const inputs = form.querySelectorAll('input[type="radio"]:checked, textarea');
    let answered = 0;

    // Reset category progress
    Object.keys(categoryProgress).forEach(category => {
        categoryProgress[category].answered = 0;
    });

    // Track answered questions by category more directly
    const categoryContents = document.querySelectorAll('.category-content');

    categoryContents.forEach(content => {
        const categoryName = getCategoryNameFromContent(content);

        if (categoryName && categoryProgress[categoryName]) {
            const categoryInputs = content.querySelectorAll('input[type="radio"]:checked, textarea');
            let categoryAnswered = 0;

            categoryInputs.forEach(input => {
                if (input.type === 'radio' || (input.type === 'textarea' && input.value.trim() !== '')) {
                    categoryAnswered++;
                    answered++;
                }
            });

            categoryProgress[categoryName].answered = categoryAnswered;
        }
    });

    progressData.answered = answered;
    const percentage = progressData.total > 0 ? Math.round((answered / progressData.total) * 100) : 0;

    // Update Overall Progress - Mobile Layout
    const mobileProgressBar = document.querySelector('.block.sm\\:hidden .bg-white.h-2');
    if (mobileProgressBar) {
        mobileProgressBar.style.width = percentage + '%';
    }

    const mobileProgressText = document.querySelector('.block.sm\\:hidden .text-lg.font-bold');
    if (mobileProgressText) {
        mobileProgressText.textContent = percentage + '% Complete';
    }

    const mobileProgressCount = document.querySelector('.block.sm\\:hidden .text-sm.font-medium');
    if (mobileProgressCount) {
        mobileProgressCount.textContent = answered + ' of ' + progressData.total + ' questions answered';
    }

    // Update Overall Progress - Desktop Layout
    const desktopProgressBar = document.querySelector('.hidden.sm\\:block .bg-white.h-2');
    if (desktopProgressBar) {
        desktopProgressBar.style.width = percentage + '%';
    }

    const desktopProgressText = document.querySelector('.hidden.sm\\:block .text-lg.font-bold');
    if (desktopProgressText) {
        desktopProgressText.textContent = percentage + '% Complete';
    }

    const desktopProgressCount = document.querySelector('.hidden.sm\\:block .text-sm.font-medium');
    if (desktopProgressCount) {
        desktopProgressCount.textContent = answered + ' of ' + progressData.total + ' questions answered';
    }

    // Update category tabs
    updateCategoryTabs();

    // Update question cards
    updateQuestionCards();

    // Check if current category is complete and auto-proceed to next tab
    checkAndProceedToNextTab();
}

function toggleAutoProgress() {
    autoProgressEnabled = !autoProgressEnabled;
    localStorage.setItem('autoProgressEnabled', autoProgressEnabled);

    // Update desktop toggle
    const toggleButton = document.getElementById('autoProgressToggle');
    const textElement = document.getElementById('autoProgressText');
    const iconElement = toggleButton?.querySelector('i');

    // Update mobile toggle
    const toggleButtonMobile = document.getElementById('autoProgressToggleMobile');
    const textElementMobile = document.getElementById('autoProgressTextMobile');
    const iconElementMobile = toggleButtonMobile?.querySelector('i');

    if (autoProgressEnabled) {
        if (textElement) textElement.textContent = 'Auto-progress enabled';
        if (iconElement) iconElement.className = 'fas fa-arrow-right text-xs';
        if (toggleButton) toggleButton.classList.remove('opacity-50');

        if (textElementMobile) textElementMobile.textContent = 'Auto';
        if (iconElementMobile) iconElementMobile.className = 'fas fa-arrow-right text-xs';
        if (toggleButtonMobile) toggleButtonMobile.classList.remove('opacity-50');
    } else {
        if (textElement) textElement.textContent = 'Auto-progress disabled';
        if (iconElement) iconElement.className = 'fas fa-pause text-xs';
        if (toggleButton) toggleButton.classList.add('opacity-50');

        if (textElementMobile) textElementMobile.textContent = 'Off';
        if (iconElementMobile) iconElementMobile.className = 'fas fa-pause text-xs';
        if (toggleButtonMobile) toggleButtonMobile.classList.add('opacity-50');
    }
}

function checkAndProceedToNextTab() {
    // Check if auto-progression is enabled
    if (!autoProgressEnabled) return;

    // Get the currently active category content
    const activeContent = document.querySelector('.category-content.active');
    if (!activeContent) return;

    const categoryName = getCategoryNameFromContent(activeContent);
    if (!categoryName || !categoryProgress[categoryName]) return;

    const progress = categoryProgress[categoryName];

    // Check if current category is 100% complete
    if (progress.answered === progress.total && progress.total > 0) {
        // Find the next tab to switch to
        const currentTabId = activeContent.id.replace('-content', '');
        const allTabs = Array.from(document.querySelectorAll('.category-tab'));
        const currentTabIndex = allTabs.findIndex(tab => tab.dataset.tab === currentTabId);

        if (currentTabIndex >= 0 && currentTabIndex < allTabs.length - 1) {
            // There's a next tab available
            const nextTab = allTabs[currentTabIndex + 1];
            const nextTabId = nextTab.dataset.tab;

            // Auto-switch to next tab after a short delay
            setTimeout(() => {
                // Switch to the next tab with scroll callback
                switchTab(nextTabId, function() {
                    // This callback runs after the tab content is visible
                    scrollToTop();
                });

                // Show a notification that we moved to the next category
                showAutoProceedNotification(categoryName, nextTab.querySelector('span:first-child').textContent.trim());
            }, 1000);
        }
    }
}

function scrollToTop() {
    // Check if we're already at the top
    if (window.pageYOffset === 0) {
        return; // Already at top, no need to scroll
    }

    // Multiple scroll methods for better compatibility
    try {
        // Method 1: Smooth scroll to top
        window.scrollTo({
            top: 0,
            behavior: 'smooth'
        });

        // Method 2: Also scroll the sticky header into view
        const stickyHeader = document.querySelector('.sticky');
        if (stickyHeader) {
            stickyHeader.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }

        // Method 3: Fallback for older browsers or if smooth scroll fails
        setTimeout(() => {
            if (window.pageYOffset > 50) {
                window.scrollTo(0, 0);
            }
        }, 150);

    } catch (e) {
        // Fallback for browsers that don't support smooth scrolling
        window.scrollTo(0, 0);
    }
}

function showAutoProceedNotification(completedCategory, nextCategory) {
    // Create notification element
    const notification = document.createElement('div');
    notification.className = 'fixed top-4 right-4 bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg z-50 transform transition-all duration-300 translate-x-full';
    notification.innerHTML = `
        <div class="flex items-center">
            <i class="fas fa-arrow-up mr-2"></i>
            <div>
                <div class="font-medium">Category Complete!</div>
                <div class="text-sm opacity-90">Moving to: ${nextCategory}</div>
                <div class="text-xs opacity-75 mt-1">Scrolling to top...</div>
            </div>
        </div>
    `;

    // Add to page
    document.body.appendChild(notification);

    // Animate in
    setTimeout(() => {
        notification.classList.remove('translate-x-full');
    }, 100);

    // Remove after 4 seconds (increased from 3)
    setTimeout(() => {
        notification.classList.add('translate-x-full');
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 300);
    }, 4000);
}

function getCategoryNameFromContent(contentElement) {
    // First try to get from data-category attribute
    if (contentElement.dataset.category) {
        return contentElement.dataset.category;
    }

    // Get the content ID and extract category name from it
    const contentId = contentElement.id;
    if (contentId && contentId.includes('-content')) {
        const tabId = contentId.replace('-content', '');
        const tab = document.querySelector(`[data-tab="${tabId}"]`);
        if (tab) {
            // Get the category name from the first span in the tab
            const categorySpan = tab.querySelector('span:first-child');
            if (categorySpan) {
                return categorySpan.textContent.trim();
            }
        }
    }

    // Fallback: try to get from h3 header
    const categoryHeader = contentElement.querySelector('h3');
    if (categoryHeader) {
        return categoryHeader.textContent.trim();
    }

    return null;
}

function updateCategoryTabs() {
    Object.keys(categoryProgress).forEach(categoryName => {
        const progress = categoryProgress[categoryName];
        const percentage = progress.total > 0 ? Math.round((progress.answered / progress.total) * 100) : 0;

        // Update tab progress - find all tabs with this category name
        const tabId = 'tab-' + categoryName.replace(/[^a-zA-Z0-9]/g, '-');
        const tabs = document.querySelectorAll(`[data-tab="${tabId}"]`);

        tabs.forEach(tab => {
            // Find the progress span - it has the rounded-full class
            const progressSpan = tab.querySelector('span.rounded-full');

            if (progressSpan) {
                const newText = progress.answered + '/' + progress.total;
                progressSpan.textContent = newText;
            }

            // Update completion styling
            if (percentage === 100) {
                tab.classList.add('completed');
            } else {
                tab.classList.remove('completed');
            }

            // Update check icon for both mobile and desktop
            const checkIcon = tab.querySelector('.fa-check-circle');
            if (percentage === 100) {
                if (!checkIcon) {
                    const icon = document.createElement('i');
                    icon.className = 'fas fa-check-circle text-xs text-green-500';
                    // Add the icon in the appropriate location based on layout
                    const categoryNameSpan = tab.querySelector('span:first-child');
                    if (categoryNameSpan) {
                        categoryNameSpan.parentNode.insertBefore(icon, categoryNameSpan.nextSibling);
                    } else {
                        tab.appendChild(icon);
                    }
                }
            } else {
                if (checkIcon) {
                    checkIcon.remove();
                }
            }
        });

        // Update category content progress (the header inside each category)
        const contentId = tabId + '-content';
        const content = document.getElementById(contentId);
        if (content) {
            const progressBar = content.querySelector('.w-24 .bg-seait-orange');
            const progressText = content.querySelector('.text-seait-orange');
            const progressCount = content.querySelector('.text-sm.text-gray-600');

            if (progressBar) {
                progressBar.style.width = percentage + '%';
            }
            if (progressText) {
                progressText.textContent = percentage + '%';
            }
            if (progressCount) {
                progressCount.textContent = progress.answered + ' of ' + progress.total + ' questions answered';
            }
        }
    });
}

function updateQuestionCards() {
    const questionCards = document.querySelectorAll('.question-card');
    questionCards.forEach(card => {
        const inputs = card.querySelectorAll('input[type="radio"]:checked, textarea');
        let isAnswered = false;

        inputs.forEach(input => {
            if (input.type === 'radio' || (input.type === 'textarea' && input.value.trim() !== '')) {
                isAnswered = true;
            }
        });

        if (isAnswered) {
            card.classList.add('answered');
        } else {
            card.classList.remove('answered');
        }

        // Update answer highlighting
        updateAnswerHighlighting(card);
    });
}

function updateAnswerHighlighting(questionCard) {
    // Handle rating stars
    const ratingStars = questionCard.querySelectorAll('.rating-star');
    ratingStars.forEach(star => {
        const radio = star.querySelector('input[type="radio"]');
        if (radio && radio.checked) {
            star.classList.add('selected');
        } else {
            star.classList.remove('selected');
        }
    });

    // Handle yes/no options
    const yesNoOptions = questionCard.querySelectorAll('input[type="radio"][name*="[yes_no]"]');
    const yesNoLabels = questionCard.querySelectorAll('label:has(input[type="radio"][name*="[yes_no]"])');

    yesNoLabels.forEach(label => {
        label.classList.remove('yes-no-option', 'selected-yes', 'selected-no');
    });

    yesNoOptions.forEach(radio => {
        if (radio.checked) {
            const label = radio.closest('label');
            if (label) {
                label.classList.add('yes-no-option');
                if (radio.value === 'yes') {
                    label.classList.add('selected-yes');
                } else if (radio.value === 'no') {
                    label.classList.add('selected-no');
                }
            }
        }
    });

    // Handle multiple choice options
    const multipleChoiceOptions = questionCard.querySelectorAll('input[type="radio"][name*="[multiple_choice]"]');
    const multipleChoiceLabels = questionCard.querySelectorAll('label:has(input[type="radio"][name*="[multiple_choice]"])');

    multipleChoiceLabels.forEach(label => {
        label.classList.remove('multiple-choice-option', 'selected');
    });

    multipleChoiceOptions.forEach(radio => {
        if (radio.checked) {
            const label = radio.closest('label');
            if (label) {
                label.classList.add('multiple-choice-option', 'selected');
            }
        }
    });

    // Handle text answers
    const textAreas = questionCard.querySelectorAll('textarea');
    textAreas.forEach(textarea => {
        if (textarea.value.trim() !== '') {
            textarea.classList.add('text-answer-filled');
        } else {
            textarea.classList.remove('text-answer-filled');
        }
    });

    // Update answer status indicator
    updateAnswerStatus(questionCard);
}

function updateAnswerStatus(questionCard) {
    const inputs = questionCard.querySelectorAll('input[type="radio"]:checked, textarea');
    let isAnswered = false;

    inputs.forEach(input => {
        if (input.type === 'radio' || (input.type === 'textarea' && input.value.trim() !== '')) {
            isAnswered = true;
        }
    });

    const statusContainer = questionCard.querySelector('.answer-status');
    if (statusContainer) {
        if (isAnswered) {
            statusContainer.className = 'answer-status answered';
            statusContainer.innerHTML = '<i class="fas fa-check-circle mr-1"></i>Answered';
        } else {
            statusContainer.className = 'answer-status pending';
            statusContainer.innerHTML = '<i class="fas fa-clock mr-1"></i>Pending';
        }
    }
}

function switchTab(tabId, callback = null) {
    // Remove active state from all tabs
    const categoryTabs = document.querySelectorAll('.category-tab');
    categoryTabs.forEach(tab => {
        tab.classList.remove('bg-seait-orange', 'text-white', 'shadow-md');

        // Check if tab is completed
        const isCompleted = tab.classList.contains('completed');

        if (isCompleted) {
            // Keep completed styling but remove active
            tab.classList.remove('active');
            tab.classList.add('bg-green-50', 'text-green-800', 'border-green-200');
        } else {
            // Regular inactive styling
            tab.classList.add('bg-white', 'text-gray-700', 'hover:bg-gray-100', 'border', 'border-gray-200');
        }

        // Update tab content styling - use rounded-full selector
        const progressSpan = tab.querySelector('span.rounded-full');
        if (progressSpan) {
            if (isCompleted) {
                progressSpan.className = 'px-2 py-1 text-xs rounded-full bg-green-600 text-white';
            } else {
                progressSpan.className = 'px-2 py-1 text-xs rounded-full bg-seait-orange text-white';
            }
        }

        // Update chevron color for mobile
        const chevronIcon = tab.querySelector('.fa-chevron-right');
        if (chevronIcon) {
            if (isCompleted) {
                chevronIcon.className = 'fas fa-chevron-right text-xs text-green-600';
            } else {
                chevronIcon.className = 'fas fa-chevron-right text-xs text-gray-400';
            }
        }
    });

    // Hide all content
    const categoryContents = document.querySelectorAll('.category-content');
    categoryContents.forEach(content => {
        content.classList.add('hidden');
        content.classList.remove('active');
    });

    // Activate selected tab
    const selectedTab = document.querySelector(`[data-tab="${tabId}"]`);
    if (selectedTab) {
        const isCompleted = selectedTab.classList.contains('completed');

        if (isCompleted) {
            // Completed tab active styling
            selectedTab.classList.remove('bg-green-50', 'text-green-800', 'border-green-200');
            selectedTab.classList.add('bg-gradient-to-r', 'from-green-500', 'to-green-600', 'text-white', 'shadow-md', 'active');

            // Update progress span for completed active tab
            const progressSpan = selectedTab.querySelector('span.rounded-full');
            if (progressSpan) {
                progressSpan.className = 'px-2 py-1 text-xs rounded-full bg-white bg-opacity-20';
            }

            // Update chevron for completed active tab
            const chevronIcon = selectedTab.querySelector('.fa-chevron-right');
            if (chevronIcon) {
                chevronIcon.className = 'fas fa-chevron-right text-xs text-white';
            }
        } else {
            // Regular tab active styling
            selectedTab.classList.remove('bg-white', 'text-gray-700', 'hover:bg-gray-100', 'border', 'border-gray-200');
            selectedTab.classList.add('bg-seait-orange', 'text-white', 'shadow-md', 'active');

            // Update tab content styling - use rounded-full selector
            const progressSpan = selectedTab.querySelector('span.rounded-full');
            if (progressSpan) {
                progressSpan.className = 'px-2 py-1 text-xs rounded-full bg-white bg-opacity-20';
            }

            // Update chevron color for mobile
            const chevronIcon = selectedTab.querySelector('.fa-chevron-right');
            if (chevronIcon) {
                chevronIcon.className = 'fas fa-chevron-right text-xs text-white';
            }
        }
    }

    // Show selected content
    const selectedContent = document.getElementById(tabId + '-content');
    if (selectedContent) {
        selectedContent.classList.remove('hidden');
        selectedContent.classList.add('active');

        // Force a reflow to ensure the content is visible before any scroll operations
        selectedContent.offsetHeight;

        // Execute callback if provided (for auto-progression scroll)
        if (callback && typeof callback === 'function') {
            setTimeout(callback, 10);
        }
    }
}

function saveDraft() {
    const form = document.getElementById('evaluationForm');
    const actionInput = form.querySelector('input[name="action"]');
    actionInput.value = 'save_draft';
    form.submit();
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    // Initialize auto-progression toggle
    const toggleButton = document.getElementById('autoProgressToggle');
    const toggleButtonMobile = document.getElementById('autoProgressToggleMobile');

    if (toggleButton) {
        toggleButton.addEventListener('click', toggleAutoProgress);
    }

    if (toggleButtonMobile) {
        toggleButtonMobile.addEventListener('click', toggleAutoProgress);
    }

    // Set initial state for both buttons
    const textElement = document.getElementById('autoProgressText');
    const iconElement = toggleButton?.querySelector('i');
    const textElementMobile = document.getElementById('autoProgressTextMobile');
    const iconElementMobile = toggleButtonMobile?.querySelector('i');

    if (autoProgressEnabled) {
        if (textElement) textElement.textContent = 'Auto-progress enabled';
        if (iconElement) iconElement.className = 'fas fa-arrow-right text-xs';
        if (toggleButton) toggleButton.classList.remove('opacity-50');

        if (textElementMobile) textElementMobile.textContent = 'Auto';
        if (iconElementMobile) iconElementMobile.className = 'fas fa-arrow-right text-xs';
        if (toggleButtonMobile) toggleButtonMobile.classList.remove('opacity-50');
    } else {
        if (textElement) textElement.textContent = 'Auto-progress disabled';
        if (iconElement) iconElement.className = 'fas fa-pause text-xs';
        if (toggleButton) toggleButton.classList.add('opacity-50');

        if (textElementMobile) textElementMobile.textContent = 'Off';
        if (iconElementMobile) iconElementMobile.className = 'fas fa-pause text-xs';
        if (toggleButtonMobile) toggleButtonMobile.classList.add('opacity-50');
    }

    updateProgress();

    // Add event listeners for all form inputs
    const form = document.getElementById('evaluationForm');
    if (form) {
        const inputs = form.querySelectorAll('input, textarea');
        inputs.forEach(input => {
            input.addEventListener('change', function() {
                updateProgress();
                // Update highlighting for the specific question card
                const questionCard = this.closest('.question-card');
                if (questionCard) {
                    updateAnswerHighlighting(questionCard);
                }
            });

            // For textareas, also listen to input events for real-time highlighting
            if (input.type === 'textarea') {
                input.addEventListener('input', function() {
                    const questionCard = this.closest('.question-card');
                    if (questionCard) {
                        updateAnswerHighlighting(questionCard);
                    }
                });
            }
        });
    }

    // Rating star click handlers
    document.querySelectorAll('.rating-star').forEach(star => {
        star.addEventListener('click', function() {
            const radio = this.querySelector('input[type="radio"]');
            if (radio) {
                radio.checked = true;
                updateProgress();
                updateAnswerHighlighting(this.closest('.question-card'));
            }
        });
    });

    // Yes/No option click handlers
    document.querySelectorAll('label:has(input[type="radio"][name*="[yes_no]"])').forEach(label => {
        label.addEventListener('click', function() {
            setTimeout(() => {
                updateAnswerHighlighting(this.closest('.question-card'));
            }, 10);
        });
    });

    // Multiple choice option click handlers
    document.querySelectorAll('label:has(input[type="radio"][name*="[multiple_choice]"])').forEach(label => {
        label.addEventListener('click', function() {
            setTimeout(() => {
                updateAnswerHighlighting(this.closest('.question-card'));
            }, 10);
        });
    });

    // Initialize first tab as active
    const firstTab = document.querySelector('.category-tab');
    if (firstTab) {
        switchTab(firstTab.dataset.tab);
    }

    // Initialize highlighting for all question cards
    document.querySelectorAll('.question-card').forEach(card => {
        updateAnswerHighlighting(card);
    });

    // Auto-redirect for completed evaluations
    <?php if ($evaluation_session && $evaluation_session['status'] === 'completed'): ?>
    // Check if this is a completed evaluation and auto-redirect after 3 seconds
    let countdown = 3;
    const countdownElement = document.getElementById('countdown');

    const countdownInterval = setInterval(() => {
        countdown--;
        if (countdownElement) {
            countdownElement.textContent = countdown;
        }

        if (countdown <= 0) {
            clearInterval(countdownInterval);
            window.location.href = 'evaluate-teacher.php';
        }
    }, 1000);
    <?php endif; ?>
});
</script>

<?php
// Include the shared footer
include 'includes/footer.php';
?>