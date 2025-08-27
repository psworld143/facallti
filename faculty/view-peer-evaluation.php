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

// Set page title
$page_title = 'View Peer Evaluation';

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
$session_id = safe_decrypt_id($_GET['session_id']);

if (!$session_id) {
    $_SESSION['message'] = 'Invalid evaluation session ID provided.';
    $_SESSION['message_type'] = 'error';
    header('Location: peer-evaluations.php');
    exit();
}

// Get evaluation session details and verify ownership
$session_query = "SELECT es.*, mec.name as category_name, mec.evaluation_type,
                         COALESCE(f.first_name, u.first_name) as evaluatee_first_name,
                         COALESCE(f.last_name, u.last_name) as evaluatee_last_name,
                         CASE
                             WHEN es.evaluator_type = 'student' THEN evaluator_s.first_name
                             WHEN es.evaluator_type = 'teacher' THEN evaluator_f.first_name
                             WHEN es.evaluator_type = 'head' THEN evaluator_u.first_name
                             ELSE 'Unknown'
                         END as evaluator_first_name,
                         CASE
                             WHEN es.evaluator_type = 'student' THEN evaluator_s.last_name
                             WHEN es.evaluator_type = 'teacher' THEN evaluator_f.last_name
                             WHEN es.evaluator_type = 'head' THEN evaluator_u.last_name
                             ELSE 'Unknown'
                         END as evaluator_last_name
                  FROM evaluation_sessions es
                  JOIN main_evaluation_categories mec ON es.main_category_id = mec.id
                  LEFT JOIN faculty f ON es.evaluatee_id = f.id AND es.evaluatee_type = 'teacher'
                  LEFT JOIN users u ON es.evaluatee_id = u.id AND es.evaluatee_type != 'teacher'
                  LEFT JOIN students evaluator_s ON es.evaluator_id = evaluator_s.id AND es.evaluator_type = 'student'
                  LEFT JOIN faculty evaluator_f ON es.evaluator_id = evaluator_f.id AND es.evaluator_type = 'teacher'
                  LEFT JOIN users evaluator_u ON es.evaluator_id = evaluator_u.id AND es.evaluator_type = 'head'
                  WHERE es.id = ? AND (evaluator_f.email = ? OR evaluator_u.email = ?) AND mec.evaluation_type = 'peer_to_peer'";
$session_stmt = mysqli_prepare($conn, $session_query);
mysqli_stmt_bind_param($session_stmt, "iss", $session_id, $_SESSION['username'], $_SESSION['username']);
mysqli_stmt_execute($session_stmt);
$session_result = mysqli_stmt_get_result($session_stmt);
$evaluation_session = mysqli_fetch_assoc($session_result);

if (!$evaluation_session) {
    $_SESSION['message'] = 'Evaluation session not found or you do not have permission to view it.';
    $_SESSION['message_type'] = 'error';
    header('Location: peer-evaluations.php');
    exit();
}

// Get evaluation responses
$responses_query = "SELECT er.*, eq.question_text, eq.question_type, eq.order_number,
                           esc.name as sub_category_name, esc.order_number as sub_category_order
                    FROM evaluation_responses er
                    JOIN evaluation_questionnaires eq ON er.questionnaire_id = eq.id
                    JOIN evaluation_sub_categories esc ON eq.sub_category_id = esc.id
                    WHERE er.evaluation_session_id = ?
                    ORDER BY esc.order_number ASC, eq.order_number ASC";
$responses_stmt = mysqli_prepare($conn, $responses_query);
mysqli_stmt_bind_param($responses_stmt, "i", $session_id);
mysqli_stmt_execute($responses_stmt);
$responses_result = mysqli_stmt_get_result($responses_stmt);

$responses = [];
while ($response = mysqli_fetch_assoc($responses_result)) {
    $responses[] = $response;
}

// Group responses by sub-category
$grouped_responses = [];
foreach ($responses as $response) {
    $sub_category_name = $response['sub_category_name'];
    if (!isset($grouped_responses[$sub_category_name])) {
        $grouped_responses[$sub_category_name] = [];
    }
    $grouped_responses[$sub_category_name][] = $response;
}

// Include the shared header
$sidebar_context = 'main';
include 'includes/unified-header.php';
?>

<!-- Include Peer Evaluation CSS -->
<link rel="stylesheet" href="assets/css/peer-evaluation.css">

<div class="mb-6 sm:mb-8">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-seait-dark mb-2">Peer Evaluation Details</h1>
            <p class="text-sm sm:text-base text-gray-600">
                Evaluation of <span class="font-medium"><?php echo htmlspecialchars($evaluation_session['evaluatee_first_name'] . ' ' . $evaluation_session['evaluatee_last_name']); ?></span>
            </p>
        </div>
        <div class="flex flex-col sm:flex-row gap-2">
            <a href="peer-evaluations.php" class="action-btn btn-secondary px-4 py-2 rounded-lg transition flex items-center justify-center">
                <i class="fas fa-arrow-left mr-2"></i>Back
            </a>
            <?php if ($evaluation_session['status'] === 'draft'): ?>
            <a href="edit-peer-evaluation.php?session_id=<?php echo encrypt_id($session_id); ?>" class="action-btn btn-primary px-4 py-2 rounded-lg transition flex items-center justify-center">
                <i class="fas fa-edit mr-2"></i>Continue Evaluation
            </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Message Display -->
<?php if ($message): ?>
<div class="message <?php echo $message_type === 'success' ? 'message-success' : 'message-error'; ?>">
    <div class="flex items-center">
        <i class="fas <?php echo $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?> mr-2"></i>
        <?php echo htmlspecialchars($message); ?>
    </div>
</div>
<?php endif; ?>

<!-- Evaluation Summary -->
<div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
    <div class="px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-gray-50 to-gray-100">
        <h2 class="text-xl font-bold text-seait-dark flex items-center">
            <i class="fas fa-chart-bar mr-2 text-seait-orange"></i>
            Evaluation Summary
        </h2>
    </div>

    <div class="p-6">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 evaluation-info-grid">
            <div class="evaluation-info-card p-4 rounded-lg">
                <h3 class="font-medium text-gray-900 mb-2 flex items-center">
                    <i class="fas fa-user mr-2 text-seait-orange"></i>
                    Evaluated Faculty
                </h3>
                <p class="text-gray-600 font-medium"><?php echo htmlspecialchars($evaluation_session['evaluatee_first_name'] . ' ' . $evaluation_session['evaluatee_last_name']); ?></p>
            </div>

            <div class="evaluation-info-card p-4 rounded-lg">
                <h3 class="font-medium text-gray-900 mb-2 flex items-center">
                    <i class="fas fa-tag mr-2 text-seait-orange"></i>
                    Evaluation Category
                </h3>
                <p class="text-gray-600 font-medium"><?php echo htmlspecialchars($evaluation_session['category_name']); ?></p>
            </div>

            <div class="evaluation-info-card p-4 rounded-lg">
                <h3 class="font-medium text-gray-900 mb-2 flex items-center">
                    <i class="fas fa-calendar mr-2 text-seait-orange"></i>
                    Evaluation Date
                </h3>
                <p class="text-gray-600 font-medium"><?php echo date('M d, Y', strtotime($evaluation_session['evaluation_date'])); ?></p>
            </div>

            <div class="evaluation-info-card p-4 rounded-lg">
                <h3 class="font-medium text-gray-900 mb-2 flex items-center">
                    <i class="fas fa-clock mr-2 text-seait-orange"></i>
                    Status
                </h3>
                <span class="status-badge <?php
                    echo $evaluation_session['status'] === 'completed' ? 'status-completed' :
                        ($evaluation_session['status'] === 'draft' ? 'status-pending' : 'status-pending');
                ?>">
                    <i class="fas <?php echo $evaluation_session['status'] === 'completed' ? 'fa-check' : 'fa-clock'; ?> mr-1"></i>
                    <?php echo ucfirst(str_replace('_', ' ', $evaluation_session['status'])); ?>
                </span>
            </div>

            <div class="evaluation-info-card p-4 rounded-lg">
                <h3 class="font-medium text-gray-900 mb-2 flex items-center">
                    <i class="fas fa-user-edit mr-2 text-seait-orange"></i>
                    Evaluator
                </h3>
                <p class="text-gray-600 font-medium"><?php echo htmlspecialchars($evaluation_session['evaluator_first_name'] . ' ' . $evaluation_session['evaluator_last_name']); ?></p>
            </div>

            <div class="evaluation-info-card p-4 rounded-lg">
                <h3 class="font-medium text-gray-900 mb-2 flex items-center">
                    <i class="fas fa-list-check mr-2 text-seait-orange"></i>
                    Total Responses
                </h3>
                <p class="text-gray-600 font-medium"><?php echo count($responses); ?> questions answered</p>
            </div>
        </div>
    </div>
</div>

<!-- Evaluation Responses -->
<?php if (empty($responses)): ?>
<div class="bg-white rounded-lg shadow-md overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-gray-50 to-gray-100">
        <h2 class="text-lg font-medium text-seait-dark flex items-center">
            <i class="fas fa-clipboard-list mr-2 text-seait-orange"></i>
            Evaluation Responses
        </h2>
    </div>

    <div class="p-6">
        <div class="text-center py-12">
            <i class="fas fa-clipboard-list text-gray-300 text-6xl mb-6"></i>
            <h3 class="text-xl font-medium text-gray-700 mb-2">No Responses Found</h3>
            <p class="text-gray-500 mb-6">No evaluation responses have been submitted for this session yet.</p>
            <?php if ($evaluation_session['status'] === 'draft'): ?>
            <a href="edit-peer-evaluation.php?session_id=<?php echo encrypt_id($session_id); ?>" class="action-btn btn-primary px-6 py-3 rounded-lg transition inline-flex items-center">
                <i class="fas fa-edit mr-2"></i>Start Evaluation
            </a>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php else: ?>
<div class="bg-white rounded-lg shadow-md overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-gray-50 to-gray-100">
        <h2 class="text-lg font-medium text-seait-dark flex items-center">
            <i class="fas fa-clipboard-list mr-2 text-seait-orange"></i>
            Evaluation Responses
        </h2>
        <p class="text-sm text-gray-600 mt-1">Detailed responses for each evaluation category</p>
    </div>

    <div class="p-6">
        <?php foreach ($grouped_responses as $sub_category_name => $sub_category_responses): ?>
        <div class="mb-8 last:mb-0 question-card">
            <div class="px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-gray-50 to-gray-100 mb-6">
                <h3 class="text-lg font-medium text-seait-dark flex items-center">
                    <i class="fas fa-list-alt mr-2 text-seait-orange"></i>
                    <?php echo htmlspecialchars($sub_category_name); ?>
                </h3>
                <p class="text-sm text-gray-600 mt-1"><?php echo count($sub_category_responses); ?> questions in this category</p>
            </div>

            <div class="space-y-6">
                <?php foreach ($sub_category_responses as $response): ?>
                <div class="p-6 bg-gray-50 rounded-lg border border-gray-200 hover:shadow-md transition-shadow">
                    <div class="mb-4">
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <h4 class="font-medium text-gray-900 mb-2 flex items-center">
                                    <span class="bg-seait-orange text-white text-xs font-bold px-2 py-1 rounded-full mr-2">
                                        Q<?php echo $response['order_number']; ?>
                                    </span>
                                    Question <?php echo $response['order_number']; ?>
                                </h4>
                                <p class="text-gray-700 leading-relaxed"><?php echo htmlspecialchars($response['question_text']); ?></p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white p-4 rounded-lg border border-gray-200 shadow-sm">
                        <?php if ($response['question_type'] === 'rating_1_5'): ?>
                            <div class="flex flex-col sm:flex-row sm:items-center gap-3">
                                <div class="flex items-center">
                                    <span class="text-2xl font-bold text-seait-orange mr-3">
                                        <?php echo $response['rating_value']; ?>/5
                                    </span>
                                    <div class="flex space-x-1">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <div class="w-5 h-5 rounded-full <?php echo $i <= $response['rating_value'] ? 'bg-seait-orange' : 'bg-gray-300'; ?> flex items-center justify-center">
                                            <?php if ($i <= $response['rating_value']): ?>
                                            <i class="fas fa-star text-white text-xs"></i>
                                            <?php endif; ?>
                                        </div>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                                <div class="text-sm text-gray-600">
                                    <?php
                                    $rating_text = '';
                                    switch($response['rating_value']) {
                                        case 1: $rating_text = 'Poor'; break;
                                        case 2: $rating_text = 'Fair'; break;
                                        case 3: $rating_text = 'Good'; break;
                                        case 4: $rating_text = 'Very Good'; break;
                                        case 5: $rating_text = 'Excellent'; break;
                                    }
                                    echo $rating_text;
                                    ?>
                                </div>
                            </div>
                        <?php elseif ($response['question_type'] === 'text'): ?>
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <p class="text-gray-700 leading-relaxed"><?php echo nl2br(htmlspecialchars($response['text_response'])); ?></p>
                            </div>
                        <?php elseif ($response['question_type'] === 'yes_no'): ?>
                            <div class="flex items-center">
                                <span class="px-3 py-1 text-sm rounded-full font-medium <?php echo $response['yes_no_response'] === 'yes' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                    <i class="fas <?php echo $response['yes_no_response'] === 'yes' ? 'fa-check' : 'fa-times'; ?> mr-1"></i>
                                    <?php echo ucfirst($response['yes_no_response']); ?>
                                </span>
                            </div>
                        <?php elseif ($response['question_type'] === 'multiple_choice'): ?>
                            <div class="bg-blue-50 p-4 rounded-lg border border-blue-200">
                                <p class="text-gray-700 font-medium"><?php echo htmlspecialchars($response['multiple_choice_response']); ?></p>
                            </div>
                        <?php else: ?>
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <p class="text-gray-700"><?php echo htmlspecialchars($response['rating_value']); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<?php
// Include the shared footer
include 'includes/footer.php';
?>