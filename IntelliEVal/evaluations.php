<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and has guidance_officer role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'guidance_officer') {
    header('Location: ../login.php');
    exit();
}

// Set page title
$page_title = 'Evaluations';

$message = '';
$message_type = '';

// Check for session messages
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $message_type = $_SESSION['message_type'];
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
}

// Get main evaluation categories with sub-category counts
$main_categories_query = "SELECT mec.*,
                         COUNT(DISTINCT esc.id) as sub_category_count,
                         COUNT(DISTINCT eq.id) as questionnaire_count
                         FROM main_evaluation_categories mec
                         LEFT JOIN evaluation_sub_categories esc ON mec.id = esc.main_category_id AND esc.status = 'active'
                         LEFT JOIN evaluation_questionnaires eq ON esc.id = eq.sub_category_id AND eq.status = 'active'
                         WHERE mec.status = 'active'
                         GROUP BY mec.id
                         ORDER BY mec.name ASC";
$main_categories_result = mysqli_query($conn, $main_categories_query);
$main_categories = [];
while ($row = mysqli_fetch_assoc($main_categories_result)) {
    // Check if there's an active evaluation schedule for this category
    $schedule_check = "SELECT es.*, s.name as semester_name
                       FROM evaluation_schedules es
                       JOIN semesters s ON es.semester_id = s.id
                       WHERE es.evaluation_type = ?
                       AND es.status = 'active'
                       AND NOW() BETWEEN es.start_date AND es.end_date";
    $schedule_stmt = mysqli_prepare($conn, $schedule_check);
    mysqli_stmt_bind_param($schedule_stmt, "s", $row['evaluation_type']);
    mysqli_stmt_execute($schedule_stmt);
    $schedule_result = mysqli_stmt_get_result($schedule_stmt);
    $active_schedule = mysqli_fetch_assoc($schedule_result);

    // Check if there are any ongoing evaluation sessions for this category
    $ongoing_sessions_check = "SELECT COUNT(*) as ongoing_count
                               FROM evaluation_sessions
                               WHERE main_category_id = ?
                               AND status IN ('in_progress', 'draft')";
    $ongoing_stmt = mysqli_prepare($conn, $ongoing_sessions_check);
    mysqli_stmt_bind_param($ongoing_stmt, "i", $row['id']);
    mysqli_stmt_execute($ongoing_stmt);
    $ongoing_result = mysqli_stmt_get_result($ongoing_stmt);
    $ongoing_sessions = mysqli_fetch_assoc($ongoing_result);

    $row['active_schedule'] = $active_schedule;
    $row['ongoing_sessions'] = $ongoing_sessions['ongoing_count'] > 0;
    $row['ongoing_sessions_count'] = $ongoing_sessions['ongoing_count'];
    $main_categories[] = $row;
}

// Get recent evaluations
$recent_evaluations_query = "SELECT es.*,
                            mec.name as main_category_name,
                            mec.evaluation_type,
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
                                WHEN es.evaluatee_type = 'teacher' THEN evaluatee_f.first_name
                                ELSE evaluatee_u.first_name
                            END as evaluatee_first_name,
                            CASE
                                WHEN es.evaluatee_type = 'teacher' THEN evaluatee_f.last_name
                                ELSE evaluatee_u.last_name
                            END as evaluatee_last_name
                            FROM evaluation_sessions es
                            JOIN main_evaluation_categories mec ON es.main_category_id = mec.id
                            LEFT JOIN students evaluator_s ON es.evaluator_id = evaluator_s.id AND es.evaluator_type = 'student'
                            LEFT JOIN faculty evaluator_f ON es.evaluator_id = evaluator_f.id AND es.evaluator_type = 'teacher'
                            LEFT JOIN users evaluator_u ON es.evaluator_id = evaluator_u.id AND es.evaluator_type = 'head'
                            LEFT JOIN faculty evaluatee_f ON es.evaluatee_id = evaluatee_f.id AND es.evaluatee_type = 'teacher'
                            LEFT JOIN users evaluatee_u ON es.evaluatee_id = evaluatee_u.id AND es.evaluatee_type != 'teacher'
                            ORDER BY es.created_at DESC
                            LIMIT 5";
$recent_evaluations_result = mysqli_query($conn, $recent_evaluations_query);
$recent_evaluations = [];
while ($row = mysqli_fetch_assoc($recent_evaluations_result)) {
    $recent_evaluations[] = $row;
}

// Get evaluation statistics
$stats = [];
$result = mysqli_query($conn, "SELECT COUNT(*) as total FROM evaluation_sessions");
$stats['total_evaluations'] = mysqli_fetch_assoc($result)['total'];

$result = mysqli_query($conn, "SELECT COUNT(*) as total FROM evaluation_sessions WHERE status = 'completed'");
$stats['completed_evaluations'] = mysqli_fetch_assoc($result)['total'];

$result = mysqli_query($conn, "SELECT COUNT(*) as total FROM evaluation_sessions WHERE status = 'draft'");
$stats['draft_evaluations'] = mysqli_fetch_assoc($result)['total'];

$result = mysqli_query($conn, "SELECT COUNT(*) as total FROM evaluation_sessions WHERE evaluation_date = CURDATE()");
$stats['today_evaluations'] = mysqli_fetch_assoc($result)['total'];

// Get average ratings by main category
$avg_ratings_query = "SELECT mec.name, mec.evaluation_type, AVG(er.rating_value) as avg_rating
                      FROM main_evaluation_categories mec
                      LEFT JOIN evaluation_sessions es ON mec.id = es.main_category_id
                      LEFT JOIN evaluation_responses er ON es.id = er.evaluation_session_id
                      WHERE mec.status = 'active' AND er.rating_value IS NOT NULL
                      GROUP BY mec.id
                      ORDER BY avg_rating DESC";
$avg_ratings_result = mysqli_query($conn, $avg_ratings_query);
$avg_ratings = [];
while ($row = mysqli_fetch_assoc($avg_ratings_result)) {
    $avg_ratings[] = $row;
}

// Include the shared header
include 'includes/header.php';
?>

<div class="mb-6 sm:mb-8">
    <h1 class="text-2xl sm:text-3xl font-bold text-seait-dark mb-2">Evaluation Management</h1>
    <p class="text-sm sm:text-base text-gray-600">Manage and conduct hierarchical evaluations with main categories and sub-categories</p>
</div>

<?php if ($message): ?>
    <div class="mb-6 p-4 rounded-lg <?php echo $message_type === 'success' ? 'bg-green-100 text-green-700 border border-green-200' : 'bg-red-100 text-red-700 border border-red-200'; ?> message-slide-in">
        <div class="flex items-center">
            <div class="flex-shrink-0">
                <i class="fas <?php echo $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle'; ?> text-lg"></i>
            </div>
            <div class="ml-3">
                <p class="text-sm font-medium"><?php echo htmlspecialchars($message); ?></p>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if (isset($_SESSION['auto_disable_output'])): ?>
    <div class="mb-6 bg-gray-50 border border-gray-200 rounded-lg p-4">
        <h3 class="text-lg font-medium text-gray-900 mb-3">Auto-Disable Script Output</h3>
        <pre class="bg-white border border-gray-300 rounded p-3 text-sm text-gray-800 overflow-x-auto"><?php echo htmlspecialchars($_SESSION['auto_disable_output']); ?></pre>
        <button onclick="clearAutoDisableOutput()" class="mt-3 text-sm text-gray-600 hover:text-gray-800">
            <i class="fas fa-times mr-1"></i>Clear Output
        </button>
    </div>
    <?php unset($_SESSION['auto_disable_output']); ?>
<?php endif; ?>

<!-- Action Buttons -->
<div class="mb-6 flex flex-col sm:flex-row gap-3">
    <a href="manage-main-categories.php" class="bg-blue-600 text-white px-3 py-1.5 rounded-lg hover:bg-blue-700 transition text-center text-sm">
        <i class="fas fa-cog mr-1.5"></i>Manage Main Categories
    </a>
    <a href="all-evaluations.php" class="bg-green-600 text-white px-3 py-1.5 rounded-lg hover:bg-green-700 transition text-center text-sm">
        <i class="fas fa-list mr-1.5"></i>View All Evaluations
    </a>
    <button onclick="runAutoDisableScript()" class="bg-purple-600 text-white px-3 py-1.5 rounded-lg hover:bg-purple-700 transition text-center text-sm">
        <i class="fas fa-clock mr-1.5"></i>Check Expired Evaluations
    </button>
</div>

<!-- Information Alert -->
<div class="mb-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
    <div class="flex">
        <div class="flex-shrink-0">
            <i class="fas fa-info-circle text-blue-400"></i>
        </div>
        <div class="ml-3">
            <h3 class="text-sm font-medium text-blue-800">Evaluation Structure</h3>
            <div class="mt-2 text-sm text-blue-700">
                <p>The evaluation system is organized into main categories that you can customize. Each main category contains multiple sub-categories with specific questionnaires using a standardized 1-5 rating scale. <strong>Click "Manage Main Categories" to add, edit, or remove evaluation categories.</strong></p>
            </div>
        </div>
    </div>
</div>

<!-- Statistics Cards -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6 mb-6 sm:mb-8">
    <div class="bg-white overflow-hidden shadow rounded-lg">
        <div class="p-4 sm:p-5">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-blue-500 rounded-md flex items-center justify-center">
                        <i class="fas fa-clipboard-check text-white"></i>
                    </div>
                </div>
                <div class="ml-3 sm:ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-xs sm:text-sm font-medium text-gray-500 truncate">Total Evaluations</dt>
                        <dd class="text-base sm:text-lg font-medium text-gray-900"><?php echo number_format($stats['total_evaluations']); ?></dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white overflow-hidden shadow rounded-lg">
        <div class="p-4 sm:p-5">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-green-500 rounded-md flex items-center justify-center">
                        <i class="fas fa-check text-white"></i>
                    </div>
                </div>
                <div class="ml-3 sm:ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-xs sm:text-sm font-medium text-gray-500 truncate">Completed</dt>
                        <dd class="text-base sm:text-lg font-medium text-gray-900"><?php echo number_format($stats['completed_evaluations']); ?></dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white overflow-hidden shadow rounded-lg">
        <div class="p-4 sm:p-5">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-yellow-500 rounded-md flex items-center justify-center">
                        <i class="fas fa-edit text-white"></i>
                    </div>
                </div>
                <div class="ml-3 sm:ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-xs sm:text-sm font-medium text-gray-500 truncate">Drafts</dt>
                        <dd class="text-base sm:text-lg font-medium text-gray-900"><?php echo number_format($stats['draft_evaluations']); ?></dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white overflow-hidden shadow rounded-lg">
        <div class="p-4 sm:p-5">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-purple-500 rounded-md flex items-center justify-center">
                        <i class="fas fa-calendar-day text-white"></i>
                    </div>
                </div>
                <div class="ml-3 sm:ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-xs sm:text-sm font-medium text-gray-500 truncate">Today</dt>
                        <dd class="text-base sm:text-lg font-medium text-gray-900"><?php echo number_format($stats['today_evaluations']); ?></dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Main Evaluation Categories -->
<div class="mb-6 sm:mb-8">
    <h2 class="text-xl sm:text-2xl font-bold text-seait-dark mb-4">Main Evaluation Categories</h2>
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 sm:gap-6">
        <?php foreach ($main_categories as $category): ?>
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="p-4 sm:p-6">
                <div class="flex items-center justify-between mb-3 sm:mb-4">
                    <h3 class="text-base sm:text-lg font-semibold text-gray-900"><?php echo htmlspecialchars($category['name']); ?></h3>
                    <div class="flex flex-col items-end space-y-1">
                        <span class="px-2 py-1 text-xs rounded-full bg-blue-100 text-blue-800">
                            <?php echo $category['sub_category_count']; ?> sub-categories
                        </span>
                        <span class="px-2 py-1 text-xs rounded-full bg-green-100 text-green-800">
                            <?php echo $category['questionnaire_count']; ?> questions
                        </span>
                    </div>
                </div>

                <p class="text-xs sm:text-sm text-gray-600 mb-3 sm:mb-4"><?php echo htmlspecialchars($category['description']); ?></p>

                <div class="mb-3 sm:mb-4">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                        <?php
                        switch($category['evaluation_type']) {
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
                            switch($category['evaluation_type']) {
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
                        <?php echo ucwords(str_replace('_', ' ', $category['evaluation_type'])); ?>
                    </span>

                    <?php if ($category['active_schedule'] || $category['ongoing_sessions']): ?>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 ml-2 icon-pulse">
                            <i class="fas fa-play-circle mr-1"></i>Active
                        </span>
                    <?php else: ?>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 ml-2">
                            <i class="fas fa-pause-circle mr-1"></i>Inactive
                        </span>
                    <?php endif; ?>
                </div>

                <div class="flex flex-col sm:flex-row space-y-2 sm:space-y-0 sm:space-x-2">
                    <?php if ($category['active_schedule'] || $category['ongoing_sessions']): ?>
                        <!-- Evaluation is Ongoing -->
                        <div class="flex-1 flex flex-col sm:flex-row items-center justify-center px-2 py-1.5 bg-green-100 text-green-800 rounded-lg text-xs">
                            <div class="flex items-center">
                                <i class="fas fa-clock mr-1"></i>
                                <span class="font-medium">
                                    <?php if ($category['active_schedule']): ?>
                                        Scheduled Evaluation
                                    <?php else: ?>
                                        Ongoing Sessions
                                    <?php endif; ?>
                                </span>
                            </div>
                            <?php if ($category['active_schedule']): ?>
                                <div class="text-xs text-green-700 mt-1 sm:mt-0 sm:ml-2">
                                    <?php echo date('M d, H:i', strtotime($category['active_schedule']['start_date'])); ?> -
                                    <?php echo date('M d, H:i', strtotime($category['active_schedule']['end_date'])); ?>
                                </div>
                            <?php elseif ($category['ongoing_sessions']): ?>
                                <div class="text-xs text-green-700 mt-1 sm:mt-0 sm:ml-2">
                                    <?php echo $category['ongoing_sessions_count']; ?> active session<?php echo $category['ongoing_sessions_count'] > 1 ? 's' : ''; ?> in progress
                                </div>
                            <?php endif; ?>
                        </div>
                        <button onclick="startEvaluation(<?php echo $category['id']; ?>, '<?php echo htmlspecialchars($category['name']); ?>', '<?php echo $category['evaluation_type']; ?>')"
                               class="px-2 py-1.5 bg-blue-600 text-white border border-blue-300 rounded-lg hover:bg-blue-700 transition text-xs btn-hover-scale"
                               title="<?php echo $category['ongoing_sessions'] ? 'View ongoing evaluation sessions' : 'View evaluation progress'; ?>">
                            <i class="fas fa-chart-line mr-1"></i>View Progress
                        </button>
                        <?php if ($category['active_schedule']): ?>
                        <button onclick="stopEvaluation(<?php echo $category['id']; ?>, '<?php echo htmlspecialchars($category['name']); ?>', '<?php echo $category['evaluation_type']; ?>')"
                               class="px-2 py-1.5 bg-red-600 text-white border border-red-300 rounded-lg hover:bg-red-700 transition text-xs btn-hover-scale">
                            <i class="fas fa-stop"></i>
                        </button>
                        <?php endif; ?>
                    <?php else: ?>
                        <!-- Evaluation not started -->
                        <button onclick="startEvaluation(<?php echo $category['id']; ?>, '<?php echo htmlspecialchars($category['name']); ?>', '<?php echo $category['evaluation_type']; ?>')"
                               class="flex-1 bg-seait-orange text-white text-center px-2 py-1.5 rounded-lg hover:bg-orange-600 transition text-xs btn-hover-scale">
                            <i class="fas fa-play mr-1"></i>Start Evaluation
                        </button>
                    <?php endif; ?>
                    <a href="sub-categories.php?main_category_id=<?php echo $category['id']; ?>"
                       class="px-2 py-1.5 text-blue-600 border border-blue-300 rounded-lg hover:bg-blue-50 transition text-xs btn-hover-scale">
                        <i class="fas fa-eye"></i>
                    </a>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Average Ratings by Category -->
<?php if (!empty($avg_ratings)): ?>
<div class="mb-6 sm:mb-8">
    <h2 class="text-xl sm:text-2xl font-bold text-seait-dark mb-4">Average Ratings by Category</h2>
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="p-4 sm:p-6">
            <div class="space-y-4">
                <?php foreach ($avg_ratings as $rating): ?>
                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                    <div class="flex items-center">
                        <div class="w-10 h-10 rounded-full bg-seait-orange flex items-center justify-center mr-3">
                            <i class="fas
                                <?php
                                switch($rating['evaluation_type']) {
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
                                ?> text-white"></i>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($rating['name']); ?></p>
                            <p class="text-xs text-gray-500"><?php echo ucwords(str_replace('_', ' ', $rating['evaluation_type'])); ?></p>
                        </div>
                    </div>
                    <div class="flex items-center">
                        <div class="flex items-center mr-2">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="fas fa-star <?php echo $i <= round($rating['avg_rating']) ? 'text-yellow-400' : 'text-gray-300'; ?> text-sm"></i>
                            <?php endfor; ?>
                        </div>
                        <span class="text-sm font-medium text-gray-900"><?php echo number_format($rating['avg_rating'], 1); ?>/5.0</span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Recent Evaluations -->
<div class="bg-white rounded-lg shadow-md overflow-hidden">
    <div class="px-4 sm:px-6 py-4 border-b border-gray-200">
        <h2 class="text-base sm:text-lg font-medium text-gray-900">Recent Evaluations</h2>
    </div>

    <div class="p-4 sm:p-6">
        <?php if (empty($recent_evaluations)): ?>
            <p class="text-gray-500 text-center py-4">No evaluations conducted yet. Start by selecting a main evaluation category above.</p>
        <?php else: ?>
            <div class="space-y-3 sm:space-y-4">
                <?php foreach ($recent_evaluations as $evaluation): ?>
                    <div class="flex items-center justify-between p-3 sm:p-4 bg-gray-50 rounded-lg">
                        <div class="flex items-center">
                            <div class="h-8 w-8 sm:h-10 sm:w-10 rounded-full bg-seait-orange flex items-center justify-center mr-3">
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
                                    ?> text-white text-sm"></i>
                            </div>
                            <div>
                                <p class="text-xs sm:text-sm font-medium text-gray-900">
                                    <?php echo htmlspecialchars($evaluation['evaluator_first_name'] . ' ' . $evaluation['evaluator_last_name']); ?>
                                    →
                                    <?php echo htmlspecialchars($evaluation['evaluatee_first_name'] . ' ' . $evaluation['evaluatee_last_name']); ?>
                                </p>
                                <p class="text-xs sm:text-sm text-gray-500">
                                    <?php echo htmlspecialchars($evaluation['main_category_name']); ?> •
                                    <?php echo ucwords(str_replace('_', ' ', $evaluation['evaluation_type'])); ?>
                                </p>
                            </div>
                        </div>
                        <div class="flex items-center space-x-2 sm:space-x-4">
                            <span class="px-2 py-1 text-xs rounded-full <?php
                                echo $evaluation['status'] === 'completed' ? 'bg-green-100 text-green-800' :
                                    ($evaluation['status'] === 'draft' ? 'bg-yellow-100 text-yellow-800' :
                                    ($evaluation['status'] === 'cancelled' ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-800'));
                            ?>">
                                <?php echo ucfirst($evaluation['status']); ?>
                            </span>
                            <span class="text-xs text-gray-400">
                                <?php echo date('M d, Y', strtotime($evaluation['evaluation_date'])); ?>
                            </span>
                            <a href="view-evaluation.php?id=<?php echo $evaluation['evaluatee_id']; ?>"
                               class="text-blue-600 hover:text-blue-900">
                                <i class="fas fa-eye"></i>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="mt-4">
                <a href="all-evaluations.php" class="text-seait-orange hover:text-orange-600 text-xs sm:text-sm font-medium">
                    View all evaluations <i class="fas fa-arrow-right ml-1"></i>
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Stop Evaluation Confirmation Modal -->
<div id="stopEvaluationModal" class="fixed inset-0 bg-black bg-opacity-50 overflow-y-auto h-full w-full hidden z-50 modal-backdrop">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full transform transition-all duration-300 scale-95 opacity-0 modal-content-stop" id="stopModalContent">
            <div class="p-6 text-center">
                <div class="mb-4">
                    <div class="p-4 rounded-full bg-red-100 text-red-600 inline-block mb-4">
                        <i class="fas fa-stop text-3xl icon-pulse"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-900 mb-2">Stop Evaluation</h3>
                    <p class="text-gray-600 mb-4">Are you sure you want to stop the evaluation for "<span id="stopEvaluationCategoryName" class="font-semibold"></span>"?</p>
                    <div class="bg-red-50 border border-red-200 rounded-lg p-3 mb-4">
                        <div class="flex items-center text-red-800">
                            <i class="fas fa-exclamation-triangle mr-2"></i>
                            <span class="text-sm font-medium">What will happen:</span>
                        </div>
                        <ul class="text-sm text-red-700 mt-2 text-left space-y-1">
                            <li class="flex items-center">
                                <i class="fas fa-users mr-2 text-red-500"></i>
                                Evaluation sessions with responses will be marked as "completed", others as "cancelled"
                            </li>
                            <li class="flex items-center">
                                <i class="fas fa-calendar mr-2 text-red-500"></i>
                                The evaluation period will be closed
                            </li>
                            <li class="flex items-center">
                                <i class="fas fa-clipboard-check mr-2 text-red-500"></i>
                                Participants will no longer be able to submit evaluations
                            </li>
                        </ul>
                    </div>
                </div>
                <div class="flex justify-center space-x-3">
                    <button type="button" onclick="closeStopEvaluationModal()"
                            class="px-6 py-3 text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 transition-all duration-200 btn-hover-scale">
                        <i class="fas fa-times mr-2"></i>Cancel
                    </button>
                    <button type="button" onclick="confirmStopEvaluation()"
                            class="px-6 py-3 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-all duration-200 font-semibold btn-hover-scale">
                        <i class="fas fa-stop mr-2"></i>Stop Evaluation
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .modal-show {
        transform: scale(1);
        opacity: 1;
    }

    /* Enhanced modal animations */
    @keyframes modalFadeIn {
        from {
            opacity: 0;
        }
        to {
            opacity: 1;
        }
    }

    @keyframes modalSlideUp {
        from {
            transform: translateY(20px) scale(0.95);
            opacity: 0;
        }
        to {
            transform: translateY(0) scale(1);
            opacity: 1;
        }
    }

    @keyframes modalBounceIn {
        0% {
            transform: scale(0.3) translateY(-50px);
            opacity: 0;
        }
        50% {
            transform: scale(1.05) translateY(0);
            opacity: 1;
        }
        70% {
            transform: scale(0.9) translateY(0);
        }
        100% {
            transform: scale(1) translateY(0);
            opacity: 1;
        }
    }

    @keyframes modalShake {
        0%, 100% {
            transform: translateX(0);
        }
        10%, 30%, 50%, 70%, 90% {
            transform: translateX(-2px);
        }
        20%, 40%, 60%, 80% {
            transform: translateX(2px);
        }
    }

    /* Modal backdrop animation */
    .modal-backdrop {
        animation: modalFadeIn 0.3s ease-out;
    }

    /* Modal content animations */
    .modal-content-start {
        animation: modalBounceIn 0.5s ease-out;
    }

    .modal-content-stop {
        animation: modalSlideUp 0.4s ease-out;
    }

    /* Button hover animations */
    .btn-hover-scale {
        transition: all 0.2s ease-in-out;
    }

    .btn-hover-scale:hover {
        transform: scale(1.05);
    }

    .btn-hover-scale:active {
        transform: scale(0.95);
    }

    /* Icon animations */
    .icon-pulse {
        animation: pulse 2s infinite;
    }

    @keyframes pulse {
        0% {
            transform: scale(1);
        }
        50% {
            transform: scale(1.1);
        }
        100% {
            transform: scale(1);
        }
    }

    /* Success/Error message animations */
    .message-slide-in {
        animation: slideInFromTop 0.4s ease-out;
    }

    @keyframes slideInFromTop {
        from {
            transform: translateY(-20px);
            opacity: 0;
        }
        to {
            transform: translateY(0);
            opacity: 1;
        }
    }
</style>

<script>
    let currentEvaluationData = {};

    function startEvaluation(categoryId, categoryName, evaluationType) {
        // Direct redirect to conduct-evaluation.php
        window.location.href = `conduct-evaluation.php?main_category_id=${categoryId}`;
    }

    function stopEvaluation(categoryId, categoryName, evaluationType) {
        currentEvaluationData = {
            categoryId: categoryId,
            categoryName: categoryName,
            evaluationType: evaluationType,
            action: 'stop'
        };

        document.getElementById('stopEvaluationCategoryName').textContent = categoryName;
        const modal = document.getElementById('stopEvaluationModal');
        const modalContent = document.getElementById('stopModalContent');

        modal.classList.remove('hidden');
        // Trigger animation after a small delay
        setTimeout(() => {
            modalContent.classList.add('modal-show');
            // Add slide-up effect
            modalContent.style.animation = 'modalSlideUp 0.4s ease-out';
        }, 10);
    }

    function closeStopEvaluationModal() {
        const modal = document.getElementById('stopEvaluationModal');
        const modalContent = document.getElementById('stopModalContent');

        // Add exit animation
        modalContent.style.animation = 'modalSlideUp 0.3s ease-in reverse';
        modalContent.classList.remove('modal-show');

        // Wait for animation to complete before hiding
        setTimeout(() => {
            modal.classList.add('hidden');
            modalContent.style.animation = '';
        }, 300);

        currentEvaluationData = {};
    }

    function confirmStopEvaluation() {
        if (currentEvaluationData.categoryId) {
            // Add button click animation
            const button = event.target.closest('button');
            if (button) {
                button.style.transform = 'scale(0.95)';
                setTimeout(() => {
                    button.style.transform = '';
                }, 150);
            }

            // Create a form to submit the stop evaluation request
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'stop-evaluation.php';
            form.innerHTML = `
                <input type="hidden" name="action" value="stop_evaluation">
                <input type="hidden" name="category_id" value="${currentEvaluationData.categoryId}">
                <input type="hidden" name="evaluation_type" value="${currentEvaluationData.evaluationType}">
            `;
            document.body.appendChild(form);

            // Small delay for animation before submit
            setTimeout(() => {
                form.submit();
            }, 200);
        }
    }

    // Close modals when clicking outside
    const stopEvaluationModal = document.getElementById('stopEvaluationModal');

    if (stopEvaluationModal) {
        stopEvaluationModal.addEventListener('click', function(e) {
            if (e.target === this) {
                closeStopEvaluationModal();
            }
        });
    }

    // Close modals with Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeStopEvaluationModal();
        }
    });

    // Add hover effects to action buttons
    document.addEventListener('DOMContentLoaded', function() {
        const actionButtons = document.querySelectorAll('.btn-hover-scale');
        actionButtons.forEach(button => {
            button.addEventListener('mouseenter', function() {
                this.style.transform = 'scale(1.05)';
            });

            button.addEventListener('mouseleave', function() {
                this.style.transform = 'scale(1)';
            });

            button.addEventListener('mousedown', function() {
                this.style.transform = 'scale(0.95)';
            });

            button.addEventListener('mouseup', function() {
                this.style.transform = 'scale(1.05)';
            });
        });
    });

    function runAutoDisableScript() {
        if (confirm('This will check for and disable any expired evaluations. Continue?')) {
            // Create a form to submit the request
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'run-auto-disable.php';
            document.body.appendChild(form);
            form.submit();
        }
    }

    function clearAutoDisableOutput() {
        if (confirm('Are you sure you want to clear the auto-disable script output?')) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'clear-auto-disable-output.php';
            document.body.appendChild(form);
            form.submit();
        }
    }
</script>

<?php
// Include the shared footer
include 'includes/footer.php';
?>