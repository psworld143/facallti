<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and has teacher role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header('Location: ../login.php');
    exit();
}

// Set page title
$page_title = 'Conduct Peer Evaluation';

$message = '';
$message_type = '';

// Check for session messages
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $message_type = $_SESSION['message_type'];
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
}

// Get evaluation session ID from URL
$session_id = isset($_GET['session_id']) ? (int)$_GET['session_id'] : 0;

if (!$session_id) {
    $_SESSION['message'] = 'Invalid evaluation session ID provided.';
    $_SESSION['message_type'] = 'error';
    header('Location: peer-evaluations.php');
    exit();
}

// Get evaluation session details and verify ownership
$session_query = "SELECT es.*, mec.name as category_name, mec.evaluation_type,
                         COALESCE(evaluatee_f.first_name, evaluatee_u.first_name) as evaluatee_first_name,
                         COALESCE(evaluatee_f.last_name, evaluatee_u.last_name) as evaluatee_last_name
                  FROM evaluation_sessions es
                  JOIN main_evaluation_categories mec ON es.main_category_id = mec.id
                  LEFT JOIN faculty evaluatee_f ON es.evaluatee_id = evaluatee_f.id
                  LEFT JOIN users evaluatee_u ON es.evaluatee_id = evaluatee_u.id
                  WHERE es.id = ? AND es.evaluator_id = ? AND mec.evaluation_type = 'peer_to_peer'";
$session_stmt = mysqli_prepare($conn, $session_query);
mysqli_stmt_bind_param($session_stmt, "ii", $session_id, $_SESSION['user_id']);
mysqli_stmt_execute($session_stmt);
$session_result = mysqli_stmt_get_result($session_stmt);
$evaluation_session = mysqli_fetch_assoc($session_result);

if (!$evaluation_session) {
    $_SESSION['message'] = 'Evaluation session not found or you do not have permission to edit it.';
    $_SESSION['message_type'] = 'error';
    header('Location: peer-evaluations.php');
    exit();
}

// Check if evaluation is already completed
if ($evaluation_session['status'] === 'completed') {
    $_SESSION['message'] = 'This evaluation has already been completed and cannot be edited.';
    $_SESSION['message_type'] = 'error';
    header('Location: view-peer-evaluation.php?session_id=' . $session_id);
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'save_evaluation') {
        $success = true;
        $errors = [];

        // Process form responses
        foreach ($_POST as $key => $value) {
            if (strpos($key, 'question_') === 0) {
                $questionnaire_id = (int)substr($key, 9); // Remove 'question_' prefix

                // Validate response
                if (empty($value) && $value !== '0') {
                    $errors[] = "Question ID $questionnaire_id requires a response.";
                    $success = false;
                    continue;
                }

                // Get question type to determine which column to use
                $question_type_query = "SELECT question_type FROM evaluation_questionnaires WHERE id = ?";
                $question_type_stmt = mysqli_prepare($conn, $question_type_query);
                mysqli_stmt_bind_param($question_type_stmt, "i", $questionnaire_id);
                mysqli_stmt_execute($question_type_stmt);
                $question_type_result = mysqli_stmt_get_result($question_type_stmt);
                $question_type_row = mysqli_fetch_assoc($question_type_result);

                if (!$question_type_row) {
                    $errors[] = "Question ID $questionnaire_id not found.";
                    $success = false;
                    continue;
                }

                $question_type = $question_type_row['question_type'];

                    // Check if response already exists
                    $check_existing_query = "SELECT id FROM evaluation_responses WHERE evaluation_session_id = ? AND questionnaire_id = ?";
                    $check_existing_stmt = mysqli_prepare($conn, $check_existing_query);
                    mysqli_stmt_bind_param($check_existing_stmt, "ii", $session_id, $questionnaire_id);
                    mysqli_stmt_execute($check_existing_stmt);
                    $existing_result = mysqli_stmt_get_result($check_existing_stmt);

                    if (mysqli_num_rows($existing_result) > 0) {
                        // Update existing response
                        $rating_value = null;
                        $text_response = null;
                        $multiple_choice_response = null;
                        $yes_no_response = null;

                        switch ($question_type) {
                            case 'rating_1_5':
                                $rating_value = (int)$value;
                                break;
                            case 'text':
                                $text_response = $value;
                                break;
                            case 'multiple_choice':
                                $multiple_choice_response = $value;
                                break;
                            case 'yes_no':
                                $yes_no_response = $value;
                                break;
                        }

                        $update_query = "UPDATE evaluation_responses SET
                                        rating_value = ?,
                                        text_response = ?,
                                        multiple_choice_response = ?,
                                        yes_no_response = ?
                                        WHERE evaluation_session_id = ? AND questionnaire_id = ?";
                        $update_stmt = mysqli_prepare($conn, $update_query);
                        mysqli_stmt_bind_param($update_stmt, "isssii", $rating_value, $text_response, $multiple_choice_response, $yes_no_response, $session_id, $questionnaire_id);

                        if (!mysqli_stmt_execute($update_stmt)) {
                            $errors[] = "Error updating response for question ID $questionnaire_id: " . mysqli_error($conn);
                            $success = false;
                        }
                    } else {
                        // Insert new response
                $insert_query = "INSERT INTO evaluation_responses (evaluation_session_id, questionnaire_id, rating_value, text_response, multiple_choice_response, yes_no_response, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())";
                $insert_stmt = mysqli_prepare($conn, $insert_query);

                if ($insert_stmt) {
                    $rating_value = null;
                    $text_response = null;
                    $multiple_choice_response = null;
                    $yes_no_response = null;

                    switch ($question_type) {
                        case 'rating_1_5':
                            $rating_value = (int)$value;
                            break;
                        case 'text':
                            $text_response = $value;
                            break;
                        case 'multiple_choice':
                            $multiple_choice_response = $value;
                            break;
                        case 'yes_no':
                            $yes_no_response = $value;
                            break;
                    }

                    mysqli_stmt_bind_param($insert_stmt, "iiisss", $session_id, $questionnaire_id, $rating_value, $text_response, $multiple_choice_response, $yes_no_response);

                    if (!mysqli_stmt_execute($insert_stmt)) {
                        $errors[] = "Error saving response for question ID $questionnaire_id: " . mysqli_error($conn);
                        $success = false;
                    }
                } else {
                    $errors[] = "Database error for question ID $questionnaire_id: " . mysqli_error($conn);
                    $success = false;
                        }
                }
            }
        }

        // Update session status if completing
        if (isset($_POST['complete_evaluation']) && $_POST['complete_evaluation'] === '1') {
                $update_query = "UPDATE evaluation_sessions SET status = 'completed' WHERE id = ?";
            $update_stmt = mysqli_prepare($conn, $update_query);
            mysqli_stmt_bind_param($update_stmt, "i", $session_id);

            if (!mysqli_stmt_execute($update_stmt)) {
                $errors[] = "Error updating evaluation status: " . mysqli_error($conn);
                $success = false;
            }
        }

        if ($success) {
            if (isset($_POST['complete_evaluation']) && $_POST['complete_evaluation'] === '1') {
                $_SESSION['message'] = 'Peer evaluation completed successfully!';
                $_SESSION['message_type'] = 'success';
                header('Location: view-peer-evaluation.php?session_id=' . $session_id);
                exit();
            } else {
                $_SESSION['message'] = 'Evaluation responses saved successfully.';
                $_SESSION['message_type'] = 'success';
                header('Location: edit-peer-evaluation.php?session_id=' . $session_id);
                exit();
            }
        } else {
            $message = 'Errors occurred while saving evaluation: ' . implode(', ', $errors);
            $message_type = 'error';
            }
        } elseif ($_POST['action'] === 'clear_category_responses') {
            // Handle clearing category responses
            $questionnaire_ids = json_decode($_POST['questionnaire_ids'], true);
            $success = true;

            if (is_array($questionnaire_ids) && !empty($questionnaire_ids)) {
                // Delete responses for the specified questionnaire IDs
                $placeholders = str_repeat('?,', count($questionnaire_ids) - 1) . '?';
                $delete_query = "DELETE FROM evaluation_responses WHERE evaluation_session_id = ? AND questionnaire_id IN ($placeholders)";
                $delete_stmt = mysqli_prepare($conn, $delete_query);

                $params = array_merge([$session_id], $questionnaire_ids);
                $types = str_repeat('i', count($params));
                mysqli_stmt_bind_param($delete_stmt, $types, ...$params);

                if (!mysqli_stmt_execute($delete_stmt)) {
                    $success = false;
                }
            }

            // Return JSON response for AJAX request
            header('Content-Type: application/json');
            echo json_encode(['success' => $success]);
            exit();
        }
    }
}

// Get sub-categories for this main category
$sub_categories_query = "SELECT * FROM evaluation_sub_categories
                        WHERE main_category_id = ? AND status = 'active'
                        ORDER BY order_number ASC";
$sub_categories_stmt = mysqli_prepare($conn, $sub_categories_query);
mysqli_stmt_bind_param($sub_categories_stmt, "i", $evaluation_session['main_category_id']);
mysqli_stmt_execute($sub_categories_stmt);
$sub_categories_result = mysqli_stmt_get_result($sub_categories_stmt);

$sub_categories = [];
while ($row = mysqli_fetch_assoc($sub_categories_result)) {
    $sub_categories[] = $row;
}

// Get questionnaires for each sub-category
$questionnaires = [];
foreach ($sub_categories as $sub_category) {
    $questionnaires_query = "SELECT * FROM evaluation_questionnaires
                            WHERE sub_category_id = ? AND status = 'active'
                            ORDER BY order_number ASC";
    $questionnaires_stmt = mysqli_prepare($conn, $questionnaires_query);
    mysqli_stmt_bind_param($questionnaires_stmt, "i", $sub_category['id']);
    mysqli_stmt_execute($questionnaires_stmt);
    $questionnaires_result = mysqli_stmt_get_result($questionnaires_stmt);

    $sub_category_questionnaires = [];
    while ($row = mysqli_fetch_assoc($questionnaires_result)) {
        $sub_category_questionnaires[] = $row;
    }
    $questionnaires[$sub_category['id']] = $sub_category_questionnaires;
}

// Get existing responses
$existing_responses_query = "SELECT questionnaire_id, rating_value, text_response, multiple_choice_response, yes_no_response FROM evaluation_responses WHERE evaluation_session_id = ?";
$existing_responses_stmt = mysqli_prepare($conn, $existing_responses_query);
mysqli_stmt_bind_param($existing_responses_stmt, "i", $session_id);
mysqli_stmt_execute($existing_responses_stmt);
$existing_responses_result = mysqli_stmt_get_result($existing_responses_stmt);

$existing_responses = [];
while ($row = mysqli_fetch_assoc($existing_responses_result)) {
    $existing_responses[$row['questionnaire_id']] = $row;
}

// Calculate progress
$total_questions = 0;
$answered_questions = 0;
$category_progress = [];

foreach ($sub_categories as $sub_category) {
    $sub_category_questions = $questionnaires[$sub_category['id']] ?? [];
    $category_total = count($sub_category_questions);
    $category_answered = 0;

    foreach ($sub_category_questions as $questionnaire) {
        if (isset($existing_responses[$questionnaire['id']])) {
            $category_answered++;
            $answered_questions++;
        }
        $total_questions++;
    }

    $category_progress[$sub_category['name']] = [
        'total' => $category_total,
        'answered' => $category_answered,
        'percentage' => $category_total > 0 ? round(($category_answered / $category_total) * 100) : 0
    ];
}

$overall_progress_percentage = $total_questions > 0 ? round(($answered_questions / $total_questions) * 100) : 0;

// Include the shared header
$sidebar_context = 'main';
include 'includes/unified-header.php';
?>

<!-- Include Peer Evaluation CSS -->
<link rel="stylesheet" href="assets/css/peer-evaluation.css">

<div class="mb-6 sm:mb-8">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-seait-dark mb-2">Conduct Peer Evaluation</h1>
            <p class="text-sm sm:text-base text-gray-600">
                Evaluating: <span class="font-medium"><?php echo htmlspecialchars($evaluation_session['evaluatee_first_name'] . ' ' . $evaluation_session['evaluatee_last_name']); ?></span>
            </p>
        </div>
        <div class="flex space-x-2">
            <a href="peer-evaluations.php" class="bg-gray-500 text-white px-4 py-2 rounded-md hover:bg-gray-600 transition flex items-center">
                <i class="fas fa-arrow-left mr-2"></i>Back
            </a>
            <a href="view-peer-evaluation.php?session_id=<?php echo $session_id; ?>" class="bg-blue-500 text-white px-4 py-2 rounded-md hover:bg-blue-600 transition flex items-center">
                <i class="fas fa-eye mr-2"></i>View
            </a>
        </div>
    </div>
</div>

<!-- Message Display -->
<?php if ($message): ?>
<div class="mb-6 p-4 rounded-lg <?php echo $message_type === 'success' ? 'bg-green-50 border border-green-200 text-green-800' : 'bg-red-50 border border-red-200 text-red-800'; ?>">
    <?php echo htmlspecialchars($message); ?>
</div>
<?php endif; ?>

<!-- Evaluation Session Info -->
<div class="bg-white rounded-lg shadow-md overflow-hidden mb-6 sm:mb-8">
    <div class="px-6 py-4 border-b border-gray-200">
        <h2 class="text-lg font-medium text-gray-900">Evaluation Details</h2>
    </div>
    <div class="p-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <h3 class="text-sm font-medium text-gray-500 mb-2">Evaluated Faculty</h3>
                <p class="text-lg font-semibold text-gray-900">
                    <?php echo htmlspecialchars($evaluation_session['evaluatee_first_name'] . ' ' . $evaluation_session['evaluatee_last_name']); ?>
                </p>
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
                <h3 class="text-sm font-medium text-gray-500 mb-2">Evaluation Date</h3>
                <p class="text-sm text-gray-900 font-medium"><?php echo date('M d, Y', strtotime($evaluation_session['evaluation_date'])); ?></p>
            </div>
        </div>
    </div>
</div>

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
                    <span class="text-sm font-medium"><?php echo $answered_questions; ?> of <?php echo $total_questions; ?> questions answered</span>
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
                        <span class="text-sm font-medium"><?php echo $answered_questions; ?> of <?php echo $total_questions; ?> questions answered</span>
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
                    foreach ($sub_categories as $sub_category):
                        $progress = $category_progress[$sub_category['name']];
                        $tab_id = 'tab-' . preg_replace('/[^a-zA-Z0-9]/', '-', $sub_category['name']);
                    ?>
                        <button
                            class="w-full category-tab px-4 py-3 rounded-lg text-sm font-medium transition-all duration-200 flex items-center justify-between <?php echo $first_tab ? 'active' : ''; ?>"
                            data-tab="<?php echo $tab_id; ?>"
                            onclick="switchTab('<?php echo $tab_id; ?>', function() { scrollToTop(); })"
                        >
                            <div class="flex items-center space-x-2">
                                <span class="text-left"><?php echo htmlspecialchars($sub_category['name']); ?></span>
                                <?php if ($progress['percentage'] == 100): ?>
                                    <i class="fas fa-check-circle text-xs check-icon"></i>
                                <?php endif; ?>
                            </div>
                            <div class="flex items-center space-x-2">
                                <span class="px-2 py-1 text-xs rounded-full progress-badge">
                                    <?php echo $progress['answered']; ?>/<?php echo $progress['total']; ?>
            </span>
                                <i class="fas fa-chevron-right text-xs chevron-icon"></i>
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
                    foreach ($sub_categories as $sub_category):
                        $progress = $category_progress[$sub_category['name']];
                        $tab_id = 'tab-' . preg_replace('/[^a-zA-Z0-9]/', '-', $sub_category['name']);
                    ?>
                        <button
                            class="category-tab px-4 py-2 rounded-lg text-sm font-medium transition-all duration-200 flex items-center space-x-2 <?php echo $first_tab ? 'active' : ''; ?>"
                            data-tab="<?php echo $tab_id; ?>"
                            onclick="switchTab('<?php echo $tab_id; ?>', function() { scrollToTop(); })"
                        >
                            <span><?php echo htmlspecialchars($sub_category['name']); ?></span>
                            <span class="px-2 py-1 text-xs rounded-full progress-badge">
                                <?php echo $progress['answered']; ?>/<?php echo $progress['total']; ?>
                            </span>
                            <?php if ($progress['percentage'] == 100): ?>
                                <i class="fas fa-check-circle text-xs check-icon"></i>
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
    <input type="hidden" name="action" value="save_evaluation">

            <?php
            $first_tab = true;
            foreach ($sub_categories as $sub_category):
                $tab_id = 'tab-' . preg_replace('/[^a-zA-Z0-9]/', '-', $sub_category['name']);
                $progress = $category_progress[$sub_category['name']];
            ?>
                <div class="category-content <?php echo $first_tab ? 'active' : 'hidden'; ?>" id="<?php echo $tab_id; ?>-content" data-category="<?php echo htmlspecialchars($sub_category['name']); ?>">
                    <!-- Category Header -->
                    <div class="mb-6 p-4 bg-gray-50 rounded-lg border border-gray-200">
                        <div class="flex items-center justify-between mb-3">
                            <h3 class="text-lg font-medium text-gray-900 flex items-center">
                                <i class="fas fa-list-ul mr-2 text-seait-orange"></i>
                <?php echo htmlspecialchars($sub_category['name']); ?>
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
                        if (isset($questionnaires[$sub_category['id']])):
                            foreach ($questionnaires[$sub_category['id']] as $questionnaire):
                                $question_count++;
                        ?>
                            <div class="border-l-4 border-seait-orange pl-4">
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
                                            <span class="text-red-500 ml-1">*</span>
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
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                                    <label class="rating-star <?php echo ($existing_response && $existing_response['rating_value'] == $i) ? 'selected' : ''; ?>">
                                                        <input type="radio" name="question_<?php echo $questionnaire['id']; ?>"
                                                               value="<?php echo $i; ?>"
                                               <?php echo ($existing_response && $existing_response['rating_value'] == $i) ? 'checked' : ''; ?> required
                                                               class="hidden"
                                                               onchange="updateProgress()">
                                                        <span class="text-sm font-medium"><?php echo $i; ?></span>
                                    </label>
                                    <?php endfor; ?>
                                </div>
                                            <?php break;

                                        case 'text': ?>
                                            <textarea name="question_<?php echo $questionnaire['id']; ?>"
                                                      rows="4"
                                                      required
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
                                                    <input type="radio" name="question_<?php echo $questionnaire['id']; ?>"
                                                           value="yes"
                                           <?php echo ($existing_response && $existing_response['yes_no_response'] === 'yes') ? 'checked' : ''; ?> required
                                                           class="mr-3 text-seait-orange focus:ring-seait-orange"
                                                           onchange="updateProgress()">
                                                    <span class="text-sm text-gray-700 font-medium">Yes</span>
                                </label>
                                                <label class="flex items-center p-3 border border-gray-200 rounded-lg hover:bg-gray-50 transition-all duration-200 <?php echo ($existing_response && $existing_response['yes_no_response'] === 'no') ? 'yes-no-option selected-no' : ''; ?>">
                                                    <input type="radio" name="question_<?php echo $questionnaire['id']; ?>"
                                                           value="no"
                                           <?php echo ($existing_response && $existing_response['yes_no_response'] === 'no') ? 'checked' : ''; ?> required
                                                           class="mr-3 text-seait-orange focus:ring-seait-orange"
                                                           onchange="updateProgress()">
                                                    <span class="text-sm text-gray-700 font-medium">No</span>
                                </label>
                            </div>
                                            <?php break;

                                        case 'multiple_choice':
                                            $options = $questionnaire['options'] ? json_decode($questionnaire['options'], true) : ['Option 1', 'Option 2', 'Option 3']; ?>
                                            <div class="space-y-3">
                                <?php foreach ($options as $option): ?>
                                                    <label class="flex items-center p-3 border border-gray-200 rounded-lg hover:bg-gray-50 transition-all duration-200 <?php echo ($existing_response && $existing_response['multiple_choice_response'] === $option) ? 'multiple-choice-option selected' : ''; ?>">
                                                        <input type="radio" name="question_<?php echo $questionnaire['id']; ?>"
                                                               value="<?php echo htmlspecialchars($option); ?>"
                                           <?php echo ($existing_response && $existing_response['multiple_choice_response'] === $option) ? 'checked' : ''; ?> required
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
                        <?php
                            endforeach;
                        endif;
                        ?>
                    </div>
                </div>
            <?php
            $first_tab = false;
            endforeach;
            ?>

            <!-- Submit Buttons -->
            <div class="flex flex-col sm:flex-row gap-4 mt-8">
                <button type="button" onclick="showResetOptions()" class="flex-1 bg-red-500 text-white px-4 py-2 rounded-md hover:bg-red-600 transition reset-button">
                    <i class="fas fa-undo mr-2"></i>Reset
                </button>
                <button type="button" onclick="saveDraft()" class="flex-1 bg-gray-500 text-white px-4 py-2 rounded-md hover:bg-gray-600 transition">
                    <i class="fas fa-save mr-2"></i>Save Draft
                </button>
                <button type="submit" name="complete_evaluation" value="1" class="flex-1 bg-seait-orange text-white px-4 py-2 rounded-md hover:bg-orange-600 transition">
                    <i class="fas fa-paper-plane mr-2"></i>Complete Evaluation
                </button>
                    </div>
            <div class="mt-4 text-center text-sm text-gray-600">
                <i class="fas fa-info-circle mr-1"></i>
                Your responses are automatically saved as you type. You can complete when ready.
                </div>
        </form>
                </div>
        </div>

<!-- Auto-save Indicator -->
<div id="autoSaveIndicator" class="auto-save-indicator bg-green-500 text-white px-4 py-2 rounded-lg">
    <i class="fas fa-save mr-2"></i>
    <span>Auto-saved</span>
    </div>

<!-- Reset Confirmation Modal -->
<div id="resetModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full animate-bounce-in">
            <div class="p-6 text-center">
                <div class="mb-4">
                    <div class="p-4 rounded-full bg-orange-100 text-orange-600 inline-block mb-4">
                        <i class="fas fa-undo text-3xl"></i>
            </div>
                    <h3 class="text-xl font-semibold text-gray-900 mb-2">Reset Category</h3>
                    <p class="text-gray-600 mb-4">Are you sure you want to reset this category? This will clear all your responses.</p>
                    <div class="bg-orange-50 border border-orange-200 rounded-lg p-3 mb-4">
                        <div class="flex items-center text-orange-800">
                            <i class="fas fa-exclamation-triangle mr-2"></i>
                            <span class="text-sm font-medium">Warning:</span>
                        </div>
                        <ul class="text-sm text-orange-700 mt-2 text-left space-y-1">
                            <li class="flex items-center">
                                <i class="fas fa-eraser mr-2 text-orange-500"></i>
                                All responses will be cleared
                            </li>
                            <li class="flex items-center">
                                <i class="fas fa-clock mr-2 text-orange-500"></i>
                                Progress will be reset to 0%
                            </li>
                            <li class="flex items-center">
                                <i class="fas fa-undo mr-2 text-orange-500"></i>
                                Cannot be undone
                            </li>
                        </ul>
                    </div>
                </div>
                <div class="flex justify-center space-x-3">
                    <button type="button" onclick="closeResetModal()"
                            class="px-4 py-2 text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 transition-all duration-200 text-sm">
                        <i class="fas fa-times mr-1"></i>Cancel
                </button>
                    <button type="button" onclick="confirmReset()"
                            class="px-4 py-2 bg-gradient-to-r from-orange-500 to-orange-600 text-white rounded-lg hover:from-orange-600 hover:to-orange-700 transition-all duration-200 font-medium text-sm">
                        <i class="fas fa-undo mr-1"></i>Reset Category
                    </button>
                    <button type="button" onclick="clearAllResponses()"
                            class="px-4 py-2 bg-gradient-to-r from-red-500 to-red-600 text-white rounded-lg hover:from-red-600 hover:to-red-700 transition-all duration-200 font-medium text-sm">
                        <i class="fas fa-trash mr-1"></i>Clear All
                </button>
            </div>
        </div>
    </div>
    </div>
</div>

<style>
    @keyframes bounceIn {
        0% {
            opacity: 0;
            transform: scale(0.3);
        }
        50% {
            opacity: 1;
            transform: scale(1.05);
        }
        70% {
            transform: scale(0.9);
        }
        100% {
            opacity: 1;
            transform: scale(1);
        }
    }

    .animate-bounce-in {
        animation: bounceIn 0.6s ease-out;
    }
</style>

<script>
// Tab switching functionality
function switchTab(tabId, callback) {
    // Hide all category contents
    document.querySelectorAll('.category-content').forEach(content => {
        content.classList.add('hidden');
        content.classList.remove('active');
    });

    // Remove active class from all tabs
    document.querySelectorAll('.category-tab').forEach(tab => {
        tab.classList.remove('active');
    });

    // Show selected category content
    const selectedContent = document.getElementById(tabId + '-content');
    if (selectedContent) {
        selectedContent.classList.remove('hidden');
        selectedContent.classList.add('active');
    }

    // Add active class to selected tab
    const selectedTab = document.querySelector(`[data-tab="${tabId}"]`);
    if (selectedTab) {
        selectedTab.classList.add('active');
    }

    // Execute callback if provided
    if (callback) callback();
}

// Scroll to top function
function scrollToTop() {
    window.scrollTo({
        top: 0,
        behavior: 'smooth'
    });
}

// Auto-save functionality
let autoSaveTimeout;
const form = document.getElementById('evaluationForm');
const autoSaveIndicator = document.getElementById('autoSaveIndicator');

// Auto-save on form changes
form.addEventListener('change', function() {
    clearTimeout(autoSaveTimeout);
    autoSaveTimeout = setTimeout(() => {
        // Create a temporary form for auto-save
        const tempForm = new FormData(form);
        tempForm.set('action', 'save_evaluation');

        fetch(window.location.href, {
            method: 'POST',
            body: tempForm
        }).then(response => {
            if (response.ok) {
                showAutoSaveIndicator();
            }
        }).catch(error => {
            console.error('Auto-save failed:', error);
        });
    }, 2000); // Auto-save after 2 seconds of inactivity
});

function showAutoSaveIndicator() {
    autoSaveIndicator.classList.add('show');
    setTimeout(() => {
        autoSaveIndicator.classList.remove('show');
    }, 3000);
}

// Save draft function
function saveDraft() {
    const formData = new FormData(form);
    formData.set('action', 'save_evaluation');

    fetch(window.location.href, {
        method: 'POST',
        body: formData
    }).then(response => {
        if (response.ok) {
            showAutoSaveIndicator();
        }
    }).catch(error => {
        console.error('Save draft failed:', error);
    });
}

// Update progress function
function updateProgress() {
    // Update answer status visually
    const form = document.getElementById('evaluationForm');
    const formData = new FormData(form);

    // Find all question cards and update their status
    document.querySelectorAll('.question-card').forEach(card => {
        const questionId = card.querySelector('input, textarea')?.name;
        if (questionId) {
            const questionNumber = questionId.replace('question_', '');
            const value = formData.get(questionId);

            // Check if question has a value
            const hasValue = value && value.trim() !== '';
            const answerStatus = card.querySelector('.answer-status');

            if (hasValue) {
                card.classList.add('answered');
                card.classList.remove('pending');
                if (answerStatus) {
                    answerStatus.className = 'answer-status answered';
                    answerStatus.innerHTML = '<i class="fas fa-check-circle mr-1"></i>Answered';
                }
            } else {
                card.classList.remove('answered');
                card.classList.add('pending');
                if (answerStatus) {
                    answerStatus.className = 'answer-status pending';
                    answerStatus.innerHTML = '<i class="fas fa-clock mr-1"></i>Pending';
                }
            }
        }
    });

    // Update rating stars visual state
    document.querySelectorAll('.rating-star input[type="radio"]').forEach(radio => {
        const label = radio.closest('.rating-star');
        if (radio.checked) {
            label.classList.add('selected');
        } else {
            label.classList.remove('selected');
        }
    });

    // Update yes/no options visual state
    document.querySelectorAll('.yes-no-option input[type="radio"]').forEach(radio => {
        const label = radio.closest('.yes-no-option');
        if (radio.checked) {
            if (radio.value === 'yes') {
                label.classList.add('selected-yes');
                label.classList.remove('selected-no');
            } else {
                label.classList.add('selected-no');
                label.classList.remove('selected-yes');
            }
        } else {
            label.classList.remove('selected-yes', 'selected-no');
        }
    });

    // Update multiple choice options visual state
    document.querySelectorAll('.multiple-choice-option input[type="radio"]').forEach(radio => {
        const label = radio.closest('.multiple-choice-option');
        if (radio.checked) {
            label.classList.add('selected');
        } else {
            label.classList.remove('selected');
        }
    });

    // Update text area visual state
    document.querySelectorAll('textarea').forEach(textarea => {
        if (textarea.value.trim() !== '') {
            textarea.classList.add('text-answer-filled');
        } else {
            textarea.classList.remove('text-answer-filled');
        }
    });

    // Check for auto-progress
    if (autoProgressEnabled) {
        checkAndAutoProgress();
    }
}

// Auto-progress functionality
let autoProgressInProgress = false;

function checkAndAutoProgress() {
    // Prevent multiple auto-progress calls
    if (autoProgressInProgress) {
        console.log('Auto-progress: Already in progress, skipping check');
        return;
    }

    const activeCategory = document.querySelector('.category-content.active');
    if (!activeCategory) return;

    const categoryName = activeCategory.getAttribute('data-category');
    const questionsInCategory = activeCategory.querySelectorAll('.question-card');
    let answeredQuestions = 0;
    let totalQuestions = questionsInCategory.length;

    console.log(`Auto-progress check: Category "${categoryName}" - ${totalQuestions} total questions`);

    // Count answered questions in current category
    questionsInCategory.forEach(card => {
        const questionId = card.querySelector('input, textarea')?.name;
        if (questionId) {
            const form = document.getElementById('evaluationForm');
            const formData = new FormData(form);
            const value = formData.get(questionId);
            if (value && value.trim() !== '') {
                answeredQuestions++;
            }
        }
    });

    console.log(`Auto-progress check: ${answeredQuestions}/${totalQuestions} questions answered`);

    // Only auto-progress if ALL questions in current category are answered AND we have questions
    if (answeredQuestions === totalQuestions && totalQuestions > 0 && answeredQuestions > 0) {
        console.log(`Auto-progress: Category "${categoryName}" completed (${answeredQuestions}/${totalQuestions}), triggering auto-progress`);

        // Set flag to prevent multiple calls
        autoProgressInProgress = true;

        setTimeout(() => {
            autoProgressToNextCategory();
            // Reset flag after auto-progress completes
            setTimeout(() => {
                autoProgressInProgress = false;
            }, 2000); // 2 second cooldown
        }, 1000); // 1 second delay to show completion
    } else {
        console.log(`Auto-progress: Category "${categoryName}" not completed yet (${answeredQuestions}/${totalQuestions}) - no auto-progress`);
    }
}

function autoProgressToNextCategory() {
    // Find the currently active tab by looking for the active category content
    const activeCategory = document.querySelector('.category-content.active');
    if (!activeCategory) {
        console.log('Auto-progress: No active category found');
        return;
    }

    const activeCategoryName = activeCategory.getAttribute('data-category');
    const activeTabId = `tab-${activeCategoryName.replace(/[^a-zA-Z0-9]/g, '-')}`;

    console.log('Auto-progress: Active category:', activeCategoryName);
    console.log('Auto-progress: Active tab ID:', activeTabId);

    // Determine if we're on mobile or desktop view
    const isMobile = window.innerWidth < 640; // sm breakpoint
    console.log('Auto-progress: View type:', isMobile ? 'Mobile' : 'Desktop');

    // Get tabs from the appropriate container (mobile or desktop)
    let tabContainer;
    if (isMobile) {
        tabContainer = document.querySelector('.block.sm\\:hidden .space-y-2');
    } else {
        tabContainer = document.querySelector('.hidden.sm\\:block .flex.flex-wrap');
    }

    if (!tabContainer) {
        console.log('Auto-progress: Tab container not found');
        return;
    }

    // Get only the tabs from the current view
    const allTabs = Array.from(tabContainer.querySelectorAll('.category-tab'));
    console.log('Auto-progress: Total tabs found:', allTabs.length);

    // Log all tab IDs for debugging
    allTabs.forEach((tab, index) => {
        const tabId = tab.getAttribute('data-tab');
        const tabText = tab.textContent.trim();
        console.log(`Auto-progress: Tab ${index}: ${tabId} - "${tabText}"`);
    });

    // Find the current active tab in this view
    const currentActiveTab = allTabs.find(tab => tab.getAttribute('data-tab') === activeTabId);
    if (!currentActiveTab) {
        console.log('Auto-progress: Current active tab not found in view');
        return;
    }

    const currentIndex = allTabs.indexOf(currentActiveTab);
    console.log('Auto-progress: Current tab index:', currentIndex);

    const nextTab = allTabs[currentIndex + 1];

    if (nextTab) {
        // Auto-progress to next tab
        const nextTabId = nextTab.getAttribute('data-tab');
        const nextTabText = nextTab.textContent.trim();
        console.log(`Auto-progress: Moving to next tab ${currentIndex + 1}: ${nextTabId} - "${nextTabText}"`);

        // Temporarily disable auto-progress to prevent immediate triggering
        const wasAutoProgressEnabled = autoProgressEnabled;
        autoProgressEnabled = false;

        switchTab(nextTabId, function() {
            // Scroll to top of new category
            scrollToTop();

            // Re-enable auto-progress after a short delay
            setTimeout(() => {
                autoProgressEnabled = wasAutoProgressEnabled;
                console.log('Auto-progress: Re-enabled after tab switch');
            }, 1500); // 1.5 second delay
        });
    } else {
        // We're on the last tab - show completion message
        console.log('Auto-progress: Reached last tab, showing completion message');
        showCompletionMessage();
    }
}

function showCompletionMessage() {
    // Create completion notification
    const notification = document.createElement('div');
    notification.className = 'fixed top-20 right-4 bg-green-500 text-white px-4 py-2 rounded-lg shadow-lg z-50 transform transition-all duration-300 opacity-0 translate-x-full';
    notification.innerHTML = `
        <div class="flex items-center">
            <i class="fas fa-check-circle mr-2"></i>
            <span>All categories completed! You can now submit your evaluation.</span>
        </div>
    `;

    document.body.appendChild(notification);

    // Animate in
    setTimeout(() => {
        notification.classList.remove('opacity-0', 'translate-x-full');
    }, 100);

    // Animate out and remove
    setTimeout(() => {
        notification.classList.add('opacity-0', 'translate-x-full');
        setTimeout(() => {
            if (document.body.contains(notification)) {
                document.body.removeChild(notification);
            }
        }, 300);
    }, 5000);
}

// Update category progress indicators
function updateCategoryProgress() {
    document.querySelectorAll('.category-content').forEach(category => {
        const categoryName = category.getAttribute('data-category');
        const questionsInCategory = category.querySelectorAll('.question-card');
        let answeredQuestions = 0;
        let totalQuestions = questionsInCategory.length;

        questionsInCategory.forEach(card => {
            const questionId = card.querySelector('input, textarea')?.name;
            if (questionId) {
                const form = document.getElementById('evaluationForm');
                const formData = new FormData(form);
                const value = formData.get(questionId);
                if (value && value.trim() !== '') {
                    answeredQuestions++;
                }
            }
        });

        // Update the corresponding tab progress for both mobile and desktop
        const tabId = `tab-${categoryName.replace(/[^a-zA-Z0-9]/g, '-')}`;
        const tabs = document.querySelectorAll(`[data-tab="${tabId}"]`);

        tabs.forEach(tab => {
            // Find the progress span (it has specific classes)
            const progressSpan = tab.querySelector('.progress-badge');
            if (progressSpan) {
                progressSpan.textContent = `${answeredQuestions}/${totalQuestions}`;
            }

            // Add check mark if all questions answered
            const checkIcon = tab.querySelector('.check-icon');
            if (answeredQuestions === totalQuestions && totalQuestions > 0) {
                if (!checkIcon) {
                    const icon = document.createElement('i');
                    icon.className = 'fas fa-check-circle text-xs check-icon';
                    tab.appendChild(icon);
                }
            } else {
                if (checkIcon) {
                    checkIcon.remove();
                }
            }
        });

        // Also update the category header progress
        const categoryHeader = category.querySelector('.text-sm.text-gray-600');
        if (categoryHeader) {
            categoryHeader.textContent = `${answeredQuestions} of ${totalQuestions} questions answered`;
        }

        // Update the progress bar in category header
        const progressBar = category.querySelector('.bg-seait-orange.h-2');
        if (progressBar && totalQuestions > 0) {
            const percentage = Math.round((answeredQuestions / totalQuestions) * 100);
            progressBar.style.width = `${percentage}%`;
        }

        // Update the percentage text in category header
        const percentageText = category.querySelector('.text-sm.font-medium.text-seait-orange');
        if (percentageText && totalQuestions > 0) {
            const percentage = Math.round((answeredQuestions / totalQuestions) * 100);
            percentageText.textContent = `${percentage}%`;
        }
    });

    // Update overall progress
    updateOverallProgress();
}

// Update overall progress
function updateOverallProgress() {
    const form = document.getElementById('evaluationForm');
    const formData = new FormData(form);
    let totalAnswered = 0;
    let totalQuestions = 0;

    // Count all questions and answered questions
    document.querySelectorAll('.question-card').forEach(card => {
        const questionId = card.querySelector('input, textarea')?.name;
        if (questionId) {
            totalQuestions++;
            const value = formData.get(questionId);
            if (value && value.trim() !== '') {
                totalAnswered++;
            }
        }
    });

    // Update overall progress text
    const overallProgressTexts = document.querySelectorAll('.text-sm.font-medium');
    overallProgressTexts.forEach(text => {
        if (text.textContent.includes('questions answered')) {
            text.textContent = `${totalAnswered} of ${totalQuestions} questions answered`;
        }
    });

    // Update overall progress percentage
    const overallPercentage = totalQuestions > 0 ? Math.round((totalAnswered / totalQuestions) * 100) : 0;
    const percentageTexts = document.querySelectorAll('.text-lg.font-bold');
    percentageTexts.forEach(text => {
        if (text.textContent.includes('% Complete')) {
            text.textContent = `${overallPercentage}% Complete`;
        }
    });

    // Update overall progress bar
    const overallProgressBars = document.querySelectorAll('.bg-white.h-2.rounded-full');
    overallProgressBars.forEach(bar => {
        if (bar.style.width) {
            bar.style.width = `${overallPercentage}%`;
        }
    });
}

// Add event listeners for all form elements
document.addEventListener('DOMContentLoaded', function() {
    // Add change event listeners to all form elements
    const form = document.getElementById('evaluationForm');

    // Radio buttons
    form.querySelectorAll('input[type="radio"]').forEach(radio => {
        radio.addEventListener('change', function() {
            updateProgress();
            updateCategoryProgress();
        });
    });

    // Text areas
    form.querySelectorAll('textarea').forEach(textarea => {
        textarea.addEventListener('input', function() {
            updateProgress();
            updateCategoryProgress();
        });
        textarea.addEventListener('change', function() {
            updateProgress();
            updateCategoryProgress();
        });
    });

    // Text inputs
    form.querySelectorAll('input[type="text"]').forEach(input => {
        input.addEventListener('input', function() {
            updateProgress();
            updateCategoryProgress();
        });
        input.addEventListener('change', function() {
            updateProgress();
            updateCategoryProgress();
        });
    });

    // Initialize progress on page load
    updateProgress();
    updateCategoryProgress();
});

// Auto-progress toggle functionality
let autoProgressEnabled = true;

document.getElementById('autoProgressToggle')?.addEventListener('click', function() {
    autoProgressEnabled = !autoProgressEnabled;
    const textElement = document.getElementById('autoProgressText');
    if (textElement) {
        textElement.textContent = autoProgressEnabled ? 'Auto-progress enabled' : 'Auto-progress disabled';
    }
});

document.getElementById('autoProgressToggleMobile')?.addEventListener('click', function() {
    autoProgressEnabled = !autoProgressEnabled;
    const textElement = document.getElementById('autoProgressTextMobile');
    if (textElement) {
        textElement.textContent = autoProgressEnabled ? 'Auto' : 'Manual';
    }
});

// Close reset modal when clicking outside
const resetModal = document.getElementById('resetModal');
if (resetModal) {
    resetModal.addEventListener('click', function(e) {
        if (e.target === this) {
            closeResetModal();
        }
    });
}

// Reset functionality
function showResetOptions() {
    openResetModal();
}

function resetCurrentCategory() {
    const activeCategory = document.querySelector('.category-content.active');
    if (!activeCategory) return;

    const categoryName = activeCategory.getAttribute('data-category');

    // Get all questionnaire IDs in the current category
    const questionCards = activeCategory.querySelectorAll('.question-card');
    const questionnaireIds = [];

    questionCards.forEach(card => {
        const questionId = card.querySelector('input, textarea')?.name;
        if (questionId) {
            const id = questionId.replace('question_', '');
            questionnaireIds.push(id);
        }
    });

    // Clear form elements visually first
    const formElements = activeCategory.querySelectorAll('input[type="radio"], textarea, input[type="text"]');
    formElements.forEach(element => {
        if (element.type === 'radio') {
            element.checked = false;
        } else {
            element.value = '';
        }
    });

    // Clear responses from database for this category
    if (questionnaireIds.length > 0) {
        const clearData = new FormData();
        clearData.append('action', 'clear_category_responses');
        clearData.append('session_id', <?php echo $session_id; ?>);
        clearData.append('questionnaire_ids', JSON.stringify(questionnaireIds));

        fetch(window.location.href, {
            method: 'POST',
            body: clearData
        }).then(response => {
            if (response.ok) {
                // Update visual states after successful database clear
                updateProgress();
                updateCategoryProgress();
                showResetConfirmation();
            } else {
                console.error('Failed to clear category responses from database');
                alert('Failed to clear category responses. Please try again.');
            }
        }).catch(error => {
            console.error('Error clearing category responses:', error);
            alert('Error clearing category responses. Please try again.');
        });
    } else {
        // Fallback: just update visual states if no questionnaire IDs found
        updateProgress();
        updateCategoryProgress();
        showResetConfirmation();
    }
}

function showResetConfirmation() {
    // Create reset confirmation notification
    const notification = document.createElement('div');
    notification.className = 'fixed top-20 right-4 bg-green-500 text-white px-4 py-2 rounded-lg shadow-lg z-50 transform transition-all duration-300 opacity-0 translate-x-full';
    notification.innerHTML = `
        <div class="flex items-center">
            <i class="fas fa-check-circle mr-2"></i>
            <span>Category reset successfully!</span>
        </div>
    `;

    document.body.appendChild(notification);

    // Animate in
    setTimeout(() => {
        notification.classList.remove('opacity-0', 'translate-x-full');
    }, 100);

    // Animate out and remove
    setTimeout(() => {
        notification.classList.add('opacity-0', 'translate-x-full');
        setTimeout(() => {
            if (document.body.contains(notification)) {
                document.body.removeChild(notification);
            }
        }, 300);
    }, 3000);
}

// Clear all responses for the current evaluation session
function clearAllResponses() {
    // Close the current reset modal
    closeResetModal();

    // Show the clear all confirmation modal
    showClearAllConfirmationModal();
}

function showClearAllConfirmationModal() {
    // Create clear all confirmation modal
    const modal = document.createElement('div');
    modal.id = 'clearAllConfirmationModal';
    modal.className = 'fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50';
    modal.innerHTML = `
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full animate-bounce-in">
                <div class="p-6 text-center">
                    <div class="mb-4">
                        <div class="p-4 rounded-full bg-red-100 text-red-600 inline-block mb-4">
                            <i class="fas fa-trash text-3xl"></i>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-900 mb-2">Clear All Responses</h3>
                        <p class="text-gray-600 mb-4">Are you sure you want to clear ALL responses for this evaluation session? This action cannot be undone.</p>
                        <div class="bg-red-50 border border-red-200 rounded-lg p-3 mb-4">
                            <div class="flex items-center text-red-800">
                                <i class="fas fa-exclamation-triangle mr-2"></i>
                                <span class="text-sm font-medium">Warning:</span>
                            </div>
                            <ul class="text-sm text-red-700 mt-2 text-left space-y-1">
                                <li class="flex items-center">
                                    <i class="fas fa-eraser mr-2 text-red-500"></i>
                                    All responses will be permanently deleted
                                </li>
                                <li class="flex items-center">
                                    <i class="fas fa-clock mr-2 text-red-500"></i>
                                    Progress will be reset to 0%
                                </li>
                                <li class="flex items-center">
                                    <i class="fas fa-undo mr-2 text-red-500"></i>
                                    Cannot be recovered
                                </li>
                                <li class="flex items-center">
                                    <i class="fas fa-refresh mr-2 text-red-500"></i>
                                    Page will refresh automatically
                                </li>
                            </ul>
                        </div>
                    </div>
                    <div class="flex justify-center space-x-3">
                        <button type="button" onclick="closeClearAllConfirmationModal()"
                                class="px-4 py-2 text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 transition-all duration-200 text-sm">
                            <i class="fas fa-times mr-1"></i>Cancel
                        </button>
                        <button type="button" onclick="confirmClearAll()"
                                class="px-4 py-2 bg-gradient-to-r from-red-500 to-red-600 text-white rounded-lg hover:from-red-600 hover:to-red-700 transition-all duration-200 font-medium text-sm">
                            <i class="fas fa-trash mr-1"></i>Clear All Responses
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;

    document.body.appendChild(modal);

    // Show the modal
    setTimeout(() => {
        modal.classList.remove('hidden');
    }, 100);

    // Close modal when clicking outside
    modal.addEventListener('click', function(e) {
        if (e.target === this) {
            closeClearAllConfirmationModal();
        }
    });
}

function closeClearAllConfirmationModal() {
    const modal = document.getElementById('clearAllConfirmationModal');
    if (modal) {
        modal.classList.add('hidden');
        setTimeout(() => {
            if (document.body.contains(modal)) {
                document.body.removeChild(modal);
            }
        }, 300);
    }
}

function confirmClearAll() {
    // Show loading state
    showClearAllLoading();

    fetch(`clear-responses.php?session_id=${<?php echo $session_id; ?>}`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            closeClearAllConfirmationModal();
            showClearAllSuccess();
        } else {
            showClearAllError(data.message || 'Failed to clear responses');
        }
    })
    .catch(error => {
        console.error('Error clearing responses:', error);
        showClearAllError('Network error occurred while clearing responses');
    });
}

function showClearAllLoading() {
    // Remove any existing notifications
    removeExistingNotifications();

    const notification = document.createElement('div');
    notification.id = 'clearAllNotification';
    notification.className = 'fixed top-20 right-4 bg-blue-500 text-white px-6 py-4 rounded-xl shadow-2xl z-50 transform transition-all duration-500 opacity-0 translate-x-full max-w-sm clear-all-notification';
    notification.innerHTML = `
        <div class="flex items-center space-x-3">
            <div class="flex-shrink-0">
                <div class="animate-spin rounded-full h-6 w-6 border-2 border-white border-t-transparent"></div>
            </div>
            <div class="flex-1">
                <h4 class="text-sm font-semibold">Clearing All Responses</h4>
                <p class="text-xs opacity-90 mt-1">Please wait while we clear your evaluation data...</p>
            </div>
        </div>
    `;

    document.body.appendChild(notification);

    // Animate in
    setTimeout(() => {
        notification.classList.remove('opacity-0', 'translate-x-full');
        notification.classList.add('notification-enter');
    }, 100);
}

function showClearAllSuccess() {
    // Remove loading notification
    removeExistingNotifications();

    const notification = document.createElement('div');
    notification.id = 'clearAllNotification';
    notification.className = 'fixed top-20 right-4 bg-gradient-to-r from-green-500 to-green-600 text-white px-6 py-4 rounded-xl shadow-2xl z-50 transform transition-all duration-500 opacity-0 translate-x-full max-w-sm border-l-4 border-green-400 clear-all-notification';
    notification.innerHTML = `
        <div class="flex items-start space-x-3">
            <div class="flex-shrink-0 mt-0.5">
                <div class="w-6 h-6 bg-white bg-opacity-20 rounded-full flex items-center justify-center success-icon">
                    <i class="fas fa-check text-sm"></i>
                </div>
            </div>
            <div class="flex-1">
                <h4 class="text-sm font-semibold">All Responses Cleared!</h4>
                <p class="text-xs opacity-90 mt-1">Your evaluation has been reset to a clean state. You can now start fresh.</p>
                <div class="mt-3 flex items-center space-x-2">
                    <div class="flex-1 bg-white bg-opacity-20 rounded-full h-1">
                        <div class="bg-white h-1 rounded-full transition-all duration-1000 progress-fill" style="width: 0%"></div>
                    </div>
                    <span class="text-xs opacity-75">Refreshing...</span>
                </div>
            </div>
        </div>
    `;

    document.body.appendChild(notification);

    // Animate in
    setTimeout(() => {
        notification.classList.remove('opacity-0', 'translate-x-full');
        notification.classList.add('notification-enter');
    }, 100);

    // Animate progress bar
    setTimeout(() => {
        const progressBar = notification.querySelector('.bg-white.h-1');
        if (progressBar) {
            progressBar.style.width = '100%';
        }
    }, 200);

    // Auto-refresh page after delay
    setTimeout(() => {
        notification.classList.add('notification-exit');
        setTimeout(() => {
            if (document.body.contains(notification)) {
                document.body.removeChild(notification);
            }
            // Refresh the page to show cleared state
            window.location.reload();
        }, 500);
    }, 3000);
}

function showClearAllError(message) {
    // Remove loading notification
    removeExistingNotifications();

    const notification = document.createElement('div');
    notification.id = 'clearAllNotification';
    notification.className = 'fixed top-20 right-4 bg-gradient-to-r from-red-500 to-red-600 text-white px-6 py-4 rounded-xl shadow-2xl z-50 transform transition-all duration-500 opacity-0 translate-x-full max-w-sm border-l-4 border-red-400 clear-all-notification';
    notification.innerHTML = `
        <div class="flex items-start space-x-3">
            <div class="flex-shrink-0 mt-0.5">
                <div class="w-6 h-6 bg-white bg-opacity-20 rounded-full flex items-center justify-center error-icon">
                    <i class="fas fa-exclamation-triangle text-sm"></i>
                </div>
            </div>
            <div class="flex-1">
                <h4 class="text-sm font-semibold">Clear Failed</h4>
                <p class="text-xs opacity-90 mt-1">${message}</p>
                <div class="mt-3 flex space-x-2">
                    <button onclick="this.closest('#clearAllNotification').remove()" class="text-xs bg-white bg-opacity-20 px-3 py-1 rounded-full hover:bg-opacity-30 transition-all duration-200 notification-btn">
                        Dismiss
                    </button>
                    <button onclick="clearAllResponses()" class="text-xs bg-white bg-opacity-20 px-3 py-1 rounded-full hover:bg-opacity-30 transition-all duration-200 notification-btn">
                        Try Again
                    </button>
                </div>
            </div>
        </div>
    `;

    document.body.appendChild(notification);

    // Animate in
    setTimeout(() => {
        notification.classList.remove('opacity-0', 'translate-x-full');
        notification.classList.add('notification-enter');
    }, 100);

    // Auto-remove after 8 seconds
    setTimeout(() => {
        if (document.body.contains(notification)) {
            notification.classList.add('notification-exit');
            setTimeout(() => {
                if (document.body.contains(notification)) {
                    document.body.removeChild(notification);
                }
            }, 500);
        }
    }, 8000);
}

function removeExistingNotifications() {
    const existingNotification = document.getElementById('clearAllNotification');
    if (existingNotification) {
        existingNotification.remove();
    }
}

// Reset Confirmation Modal Functions
function openResetModal() {
    document.getElementById('resetModal').classList.remove('hidden');
}

function closeResetModal() {
    document.getElementById('resetModal').classList.add('hidden');
}

function confirmReset() {
    resetCurrentCategory();
    closeResetModal();
}
</script>

<?php
// Include the shared footer
include 'includes/footer.php';
?>