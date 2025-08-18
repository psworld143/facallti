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
$page_title = 'View Evaluation';

$message = '';
$message_type = '';

// Check for session messages
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $message_type = $_SESSION['message_type'];
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
}

// Get evaluation ID from URL
$evaluation_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$evaluation_id) {
    $_SESSION['message'] = 'Invalid evaluation ID provided.';
    $_SESSION['message_type'] = 'error';
    header('Location: evaluations.php');
    exit();
}

// Get evaluation details - ensure student can only view their own evaluations
$evaluation_query = "SELECT es.*, mec.name as category_name, mec.evaluation_type, mec.description as category_description,
                    CASE
                        WHEN es.evaluator_type = 'student' THEN evaluator_s.first_name
                        WHEN es.evaluator_type = 'teacher' THEN evaluator_f.first_name
                        ELSE evaluator_u.first_name
                    END as evaluator_first_name,
                    CASE
                        WHEN es.evaluator_type = 'student' THEN evaluator_s.last_name
                        WHEN es.evaluator_type = 'teacher' THEN evaluator_f.last_name
                        ELSE evaluator_u.last_name
                    END as evaluator_last_name,
                    CASE
                        WHEN es.evaluator_type = 'student' THEN evaluator_s.email
                        WHEN es.evaluator_type = 'teacher' THEN evaluator_f.email
                        ELSE evaluator_u.email
                    END as evaluator_email,
                    es.evaluator_type as evaluator_role,
                    CASE
                        WHEN es.evaluatee_type = 'teacher' THEN evaluatee_f.first_name
                        ELSE evaluatee_u.first_name
                    END as evaluatee_first_name,
                    CASE
                        WHEN es.evaluatee_type = 'teacher' THEN evaluatee_f.last_name
                        ELSE evaluatee_u.last_name
                    END as evaluatee_last_name,
                    CASE
                        WHEN es.evaluatee_type = 'teacher' THEN evaluatee_f.email
                        ELSE evaluatee_u.email
                    END as evaluatee_email,
                    es.evaluatee_type as evaluatee_role,
                    s.name as semester_name, s.academic_year,
                    (SELECT COUNT(*) FROM evaluation_responses WHERE evaluation_session_id = es.id) as response_count
                    FROM evaluation_sessions es
                    JOIN main_evaluation_categories mec ON es.main_category_id = mec.id
                    LEFT JOIN students evaluator_s ON es.evaluator_id = evaluator_s.id AND es.evaluator_type = 'student'
                    LEFT JOIN faculty evaluator_f ON es.evaluator_id = evaluator_f.id AND es.evaluator_type = 'teacher'
                    LEFT JOIN users evaluator_u ON es.evaluator_id = evaluator_u.id AND es.evaluator_type = 'head'
                    LEFT JOIN faculty evaluatee_f ON es.evaluatee_id = evaluatee_f.id AND es.evaluatee_type = 'teacher'
                    LEFT JOIN users evaluatee_u ON es.evaluatee_id = evaluatee_u.id AND es.evaluatee_type != 'teacher'
                    LEFT JOIN semesters s ON es.semester_id = s.id
                    WHERE es.id = ? AND es.evaluator_id = ? AND es.evaluator_type = 'student'";

$evaluation_stmt = mysqli_prepare($conn, $evaluation_query);
mysqli_stmt_bind_param($evaluation_stmt, "ii", $evaluation_id, $_SESSION['user_id']);
mysqli_stmt_execute($evaluation_stmt);
$evaluation_result = mysqli_stmt_get_result($evaluation_stmt);
$evaluation = mysqli_fetch_assoc($evaluation_result);

if (!$evaluation) {
    $_SESSION['message'] = 'Evaluation not found or you do not have permission to view it.';
    $_SESSION['message_type'] = 'error';
    header('Location: evaluations.php');
    exit();
}

// Only allow viewing completed evaluations
if ($evaluation['status'] !== 'completed') {
    $_SESSION['message'] = 'This evaluation is not yet completed.';
    $_SESSION['message_type'] = 'error';
    header('Location: evaluations.php');
    exit();
}

// Get evaluation responses
$responses_query = "SELECT er.*, eq.question_text, eq.question_type, eq.order_number, eq.options
                   FROM evaluation_responses er
                   JOIN evaluation_questionnaires eq ON er.questionnaire_id = eq.id
                   WHERE er.evaluation_session_id = ?
                   ORDER BY eq.order_number ASC";

$responses_stmt = mysqli_prepare($conn, $responses_query);
mysqli_stmt_bind_param($responses_stmt, "i", $evaluation_id);
mysqli_stmt_execute($responses_stmt);
$responses_result = mysqli_stmt_get_result($responses_stmt);

// Group responses by question
$grouped_responses = [];
while ($response = mysqli_fetch_assoc($responses_result)) {
    $question_id = $response['questionnaire_id'];
    if (!isset($grouped_responses[$question_id])) {
        $grouped_responses[$question_id] = [
            'question_text' => $response['question_text'],
            'question_type' => $response['question_type'],
            'order_number' => $response['order_number'],
            'options' => $response['options'] ? json_decode($response['options'], true) : null,
            'responses' => []
        ];
    }
    $grouped_responses[$question_id]['responses'][] = $response;
}

// Include the shared header
include 'includes/header.php';
?>

<div class="mb-6 sm:mb-8">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-seait-dark mb-2">View Evaluation</h1>
            <p class="text-sm sm:text-base text-gray-600">
                Detailed view of evaluation #<?php echo $evaluation['id']; ?>
            </p>
        </div>
        <div class="flex space-x-2">
            <a href="evaluations.php" class="bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600 transition">
                <i class="fas fa-arrow-left mr-2"></i>Back to Evaluations
            </a>
            <?php if ($evaluation['status'] === 'draft'): ?>
            <a href="conduct-evaluation.php?session_id=<?php echo $evaluation['id']; ?>"
               class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition">
                <i class="fas fa-edit mr-2"></i>Continue Evaluation
            </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Message Display -->
<?php if ($message): ?>
<div class="mb-6 p-4 rounded-lg <?php echo $message_type === 'success' ? 'bg-green-50 border border-green-200 text-green-800' : 'bg-red-50 border border-red-200 text-red-800'; ?>">
    <?php echo htmlspecialchars($message); ?>
</div>
<?php endif; ?>

<!-- Evaluation Overview -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
    <!-- Main Information -->
    <div class="lg:col-span-2">
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-seait-orange to-orange-600">
                <h2 class="text-lg font-semibold text-white flex items-center">
                    <i class="fas fa-clipboard-check mr-3"></i>Evaluation Details
                </h2>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Basic Information -->
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                            <p class="text-gray-900"><?php echo htmlspecialchars($evaluation['category_name']); ?></p>
                            <?php if ($evaluation['category_description']): ?>
                                <p class="text-sm text-gray-600 mt-1"><?php echo htmlspecialchars($evaluation['category_description']); ?></p>
                            <?php endif; ?>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Evaluation Type</label>
                            <span class="px-3 py-1 text-sm rounded-full
                                <?php
                                switch($evaluation['evaluation_type']) {
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
                                    switch($evaluation['evaluation_type']) {
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
                                <?php echo ucwords(str_replace('_', ' ', $evaluation['evaluation_type'])); ?>
                            </span>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                            <span class="px-3 py-1 text-sm rounded-full <?php
                                echo $evaluation['status'] === 'completed' ? 'bg-green-100 text-green-800' :
                                    ($evaluation['status'] === 'draft' ? 'bg-yellow-100 text-yellow-800' :
                                    ($evaluation['status'] === 'cancelled' ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-800'));
                            ?>">
                                <i class="fas
                                    <?php
                                    echo $evaluation['status'] === 'completed' ? 'fa-check-circle' :
                                        ($evaluation['status'] === 'draft' ? 'fa-edit' :
                                        ($evaluation['status'] === 'cancelled' ? 'fa-times-circle' : 'fa-clock'));
                                    ?> mr-1"></i>
                                <?php echo ucfirst($evaluation['status']); ?>
                            </span>
                        </div>
                    </div>

                    <!-- Dates and Semester -->
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Evaluation Date</label>
                            <p class="text-gray-900"><?php echo date('F d, Y', strtotime($evaluation['evaluation_date'])); ?></p>
                        </div>

                        <?php if ($evaluation['semester_name']): ?>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Semester</label>
                            <p class="text-gray-900"><?php echo htmlspecialchars($evaluation['semester_name']); ?></p>
                            <p class="text-sm text-gray-600"><?php echo htmlspecialchars($evaluation['academic_year']); ?></p>
                        </div>
                        <?php endif; ?>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Created</label>
                            <p class="text-gray-900"><?php echo date('F d, Y g:i A', strtotime($evaluation['created_at'])); ?></p>
                        </div>

                        <?php if ($evaluation['updated_at'] && $evaluation['updated_at'] !== $evaluation['created_at']): ?>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Last Updated</label>
                            <p class="text-gray-900"><?php echo date('F d, Y g:i A', strtotime($evaluation['updated_at'])); ?></p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Participants Information -->
    <div class="lg:col-span-1">
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-blue-500 to-blue-600">
                <h2 class="text-lg font-semibold text-white flex items-center">
                    <i class="fas fa-users mr-3"></i>Participants
                </h2>
            </div>
            <div class="p-6 space-y-6">
                <!-- Evaluator -->
                <div>
                    <h3 class="text-sm font-medium text-gray-700 mb-3 flex items-center">
                        <i class="fas fa-user-check mr-2 text-green-600"></i>Evaluator
                    </h3>
                    <div class="bg-gray-50 rounded-lg p-4">
                        <div class="flex items-center mb-3">
                            <div class="w-10 h-10 bg-seait-orange rounded-full flex items-center justify-center mr-3"
                                 title="<?php echo htmlspecialchars($evaluation['evaluator_first_name'] . ' ' . $evaluation['evaluator_last_name'] . ' (' . $evaluation['evaluator_email'] . ')'); ?>">
                                <i class="fas
                                    <?php
                                    switch($evaluation['evaluator_role']) {
                                        case 'student':
                                            echo 'fa-user-graduate';
                                            break;
                                        case 'teacher':
                                            echo 'fa-chalkboard-teacher';
                                            break;
                                        case 'head':
                                            echo 'fa-user-tie';
                                            break;
                                        default:
                                            echo 'fa-user';
                                    }
                                    ?> text-white"></i>
                            </div>
                        </div>
                        <span class="px-2 py-1 text-xs rounded-full bg-blue-100 text-blue-800">
                            <?php echo ucfirst($evaluation['evaluator_role']); ?>
                        </span>
                    </div>
                </div>

                <!-- Evaluatee -->
                <div>
                    <h3 class="text-sm font-medium text-gray-700 mb-3 flex items-center">
                        <i class="fas fa-user-edit mr-2 text-purple-600"></i>Evaluatee
                    </h3>
                    <div class="bg-gray-50 rounded-lg p-4">
                        <div class="flex items-center mb-3">
                            <div class="w-10 h-10 bg-green-500 rounded-full flex items-center justify-center mr-3">
                                <i class="fas fa-chalkboard-teacher text-white"></i>
                            </div>
                            <div>
                                <p class="font-medium text-gray-900">
                                    <?php echo htmlspecialchars($evaluation['evaluatee_first_name'] . ' ' . $evaluation['evaluatee_last_name']); ?>
                                </p>
                                <p class="text-sm text-gray-600"><?php echo htmlspecialchars($evaluation['evaluatee_email']); ?></p>
                            </div>
                        </div>
                        <span class="px-2 py-1 text-xs rounded-full bg-green-100 text-green-800">
                            <?php echo ucfirst($evaluation['evaluatee_role']); ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Evaluation Responses -->
<div class="bg-white rounded-lg shadow-md overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-green-500 to-green-600">
        <h2 class="text-lg font-semibold text-white flex items-center">
            <i class="fas fa-list-alt mr-3"></i>Evaluation Responses
            <span class="ml-3 bg-white bg-opacity-20 px-2 py-1 rounded-full text-sm">
                <?php echo count($grouped_responses); ?> Questions
            </span>
        </h2>
    </div>

    <?php if (empty($grouped_responses)): ?>
        <div class="p-8 text-center">
            <i class="fas fa-clipboard-list text-gray-300 text-4xl mb-4"></i>
            <p class="text-gray-500 mb-2">No responses recorded for this evaluation.</p>
            <?php if ($evaluation['status'] === 'draft'): ?>
                <a href="conduct-evaluation.php?session_id=<?php echo $evaluation['id']; ?>"
                   class="inline-block bg-seait-orange text-white px-4 py-2 rounded-lg hover:bg-orange-600 transition">
                    <i class="fas fa-edit mr-2"></i>Start Evaluation
                </a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="p-6 space-y-6">
            <?php foreach ($grouped_responses as $question_id => $question_data): ?>
                <div class="border border-gray-200 rounded-lg p-6">
                    <div class="flex items-start justify-between mb-4">
                        <div class="flex items-center">
                            <div class="w-8 h-8 bg-seait-orange rounded-full flex items-center justify-center mr-3">
                                <span class="text-white text-sm font-medium"><?php echo $question_data['order_number']; ?></span>
                            </div>
                            <div>
                                <h3 class="text-lg font-medium text-gray-900"><?php echo htmlspecialchars($question_data['question_text']); ?></h3>
                                <div class="flex items-center mt-1 space-x-3">
                                    <span class="px-2 py-1 text-xs rounded-full
                                        <?php
                                        switch($question_data['question_type']) {
                                            case 'rating_1_5':
                                                echo 'bg-yellow-100 text-yellow-800';
                                                break;
                                            case 'text':
                                                echo 'bg-green-100 text-green-800';
                                                break;
                                            case 'yes_no':
                                                echo 'bg-blue-100 text-blue-800';
                                                break;
                                            case 'multiple_choice':
                                                echo 'bg-purple-100 text-purple-800';
                                                break;
                                            default:
                                                echo 'bg-gray-100 text-gray-800';
                                        }
                                        ?>">
                                        <i class="fas
                                            <?php
                                            switch($question_data['question_type']) {
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
                                                    echo 'fa-list-ul';
                                                    break;
                                                default:
                                                    echo 'fa-question';
                                            }
                                            ?> mr-1"></i>
                                        <?php echo ucwords(str_replace('_', ' ', $question_data['question_type'])); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-3">
                        <?php foreach ($question_data['responses'] as $response): ?>
                            <div class="bg-gray-50 rounded-lg p-4">
                                <?php if ($question_data['question_type'] === 'rating_1_5' && $response['rating_value']): ?>
                                    <div class="flex items-center">
                                        <div class="flex items-center mr-3">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <i class="fas fa-star <?php echo $i <= $response['rating_value'] ? 'text-yellow-400' : 'text-gray-300'; ?> mr-1"></i>
                                            <?php endfor; ?>
                                        </div>
                                        <span class="text-sm font-medium text-gray-900"><?php echo $response['rating_value']; ?>/5</span>
                                        <?php if ($response['text_response']): ?>
                                            <p class="text-sm text-gray-600 mt-2"><?php echo htmlspecialchars($response['text_response']); ?></p>
                                        <?php endif; ?>
                                    </div>
                                <?php elseif ($question_data['question_type'] === 'multiple_choice' && $response['multiple_choice_response']): ?>
                                    <div class="flex items-center">
                                        <div class="w-4 h-4 bg-seait-orange rounded-full mr-3"></div>
                                        <div>
                                            <p class="font-medium text-gray-900"><?php echo htmlspecialchars($response['multiple_choice_response']); ?></p>
                                            <?php if ($response['text_response']): ?>
                                                <p class="text-sm text-gray-600 mt-1"><?php echo htmlspecialchars($response['text_response']); ?></p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php elseif ($question_data['question_type'] === 'yes_no' && $response['yes_no_response']): ?>
                                    <div class="flex items-center">
                                        <div class="w-4 h-4 bg-seait-orange rounded-full mr-3"></div>
                                        <div>
                                            <p class="font-medium text-gray-900"><?php echo ucfirst($response['yes_no_response']); ?></p>
                                            <?php if ($response['text_response']): ?>
                                                <p class="text-sm text-gray-600 mt-1"><?php echo htmlspecialchars($response['text_response']); ?></p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php elseif ($response['text_response']): ?>
                                    <p class="text-gray-900"><?php echo htmlspecialchars($response['text_response']); ?></p>
                                <?php else: ?>
                                    <p class="text-gray-500 italic">No response provided</p>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php
// Include the shared footer
include 'includes/footer.php';
?>