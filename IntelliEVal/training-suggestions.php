<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and has guidance_officer role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'guidance_officer') {
    header('Location: ../index.php');
    exit();
}

// Set page title
$page_title = 'Training Suggestions';

$message = '';
$message_type = '';

// Check for session messages
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $message_type = $_SESSION['message_type'];
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
}

// Handle generate suggestions action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'generate_suggestions') {
    // Get evaluated faculty members with average scores less than 4.0 in any evaluation category
    $teachers_query = "SELECT DISTINCT
                        f.id as teacher_id,
                        f.first_name,
                        f.last_name,
                        f.email,
                        'teacher' as role,
                        esc.id as sub_category_id,
                        esc.name as sub_category_name,
                        mec.name as main_category_name,
                        AVG(er.rating_value) as average_score,
                        COUNT(er.id) as total_ratings
                      FROM faculty f
                      JOIN evaluation_sessions es ON f.id = es.evaluatee_id
                      JOIN main_evaluation_categories mec ON es.main_category_id = mec.id
                      JOIN evaluation_sub_categories esc ON mec.id = esc.main_category_id
                      JOIN evaluation_questionnaires eq ON esc.id = eq.sub_category_id
                      JOIN evaluation_responses er ON eq.id = er.questionnaire_id AND es.id = er.evaluation_session_id
                      WHERE es.evaluatee_type = 'teacher'
                        AND es.status = 'completed'
                        AND er.rating_value IS NOT NULL
                        AND f.is_active = 1
                      GROUP BY f.id, esc.id
                      HAVING average_score < 4.0 AND total_ratings >= 3
                      ORDER BY f.last_name, f.first_name, average_score ASC";

    $teachers_result = mysqli_query($conn, $teachers_query);

    $suggestions_generated = 0;
    $teachers_processed = 0;

    while ($teacher = mysqli_fetch_assoc($teachers_result)) {
        // Find available trainings for this sub-category
        $trainings_query = "SELECT id, title, type, start_date
                           FROM trainings_seminars
                           WHERE sub_category_id = ?
                             AND status = 'published'
                             AND start_date > NOW()
                           ORDER BY start_date ASC";

        $stmt = mysqli_prepare($conn, $trainings_query);
        mysqli_stmt_bind_param($stmt, "i", $teacher['sub_category_id']);
        mysqli_stmt_execute($stmt);
        $trainings_result = mysqli_stmt_get_result($stmt);

        while ($training = mysqli_fetch_assoc($trainings_result)) {
            // Check if suggestion already exists
            $check_query = "SELECT id FROM training_suggestions
                           WHERE user_id = ? AND training_id = ? AND status IN ('pending', 'accepted')";
            $check_stmt = mysqli_prepare($conn, $check_query);
            mysqli_stmt_bind_param($check_stmt, "ii", $teacher['teacher_id'], $training['id']);
            mysqli_stmt_execute($check_stmt);
            $check_result = mysqli_stmt_get_result($check_stmt);

            if (mysqli_num_rows($check_result) == 0) {
                // Determine priority level based on average score
                $priority = 'medium';
                if ($teacher['average_score'] < 2.5) {
                    $priority = 'critical';
                } elseif ($teacher['average_score'] < 3.0) {
                    $priority = 'high';
                } elseif ($teacher['average_score'] < 3.5) {
                    $priority = 'medium';
                } else {
                    $priority = 'low';
                }

                // Create suggestion reason
                $suggestion_reason = "Based on your evaluation score of " . round($teacher['average_score'], 2) .
                                   " in " . $teacher['sub_category_name'] . " (" . $teacher['main_category_name'] .
                                   "), which is below the recommended threshold of 4.0. This training will help improve your performance in this area.";

                // Insert training suggestion
                $insert_query = "INSERT INTO training_suggestions
                                (user_id, training_id, suggestion_reason, evaluation_category_id,
                                 evaluation_score, priority_level, suggested_by, status)
                                VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')";

                $insert_stmt = mysqli_prepare($conn, $insert_query);
                mysqli_stmt_bind_param($insert_stmt, "iissdsi",
                    $teacher['teacher_id'],
                    $training['id'],
                    $suggestion_reason,
                    $teacher['sub_category_id'],
                    $teacher['average_score'],
                    $priority,
                    $_SESSION['user_id']
                );

                if (mysqli_stmt_execute($insert_stmt)) {
                    $suggestions_generated++;
                }
            }
        }

        $teachers_processed++;
    }

    $_SESSION['message'] = "Generated $suggestions_generated training suggestions for $teachers_processed faculty members with scores below 4.0.";
    $_SESSION['message_type'] = 'success';
    header('Location: training-suggestions.php');
    exit();
}

// Get training suggestions grouped by evaluated faculty member
$suggestions_query = "SELECT
                       f.id as teacher_id,
                       f.first_name,
                       f.last_name,
                       f.email,
                       'teacher' as role,
                       COUNT(ts.id) as total_suggestions,
                       COUNT(CASE WHEN ts.priority_level = 'critical' THEN 1 END) as critical_count,
                       COUNT(CASE WHEN ts.priority_level = 'high' THEN 1 END) as high_count,
                       COUNT(CASE WHEN ts.priority_level = 'medium' THEN 1 END) as medium_count,
                       COUNT(CASE WHEN ts.priority_level = 'low' THEN 1 END) as low_count,
                       COUNT(CASE WHEN ts.status = 'pending' THEN 1 END) as pending_count,
                       COUNT(CASE WHEN ts.status = 'accepted' THEN 1 END) as accepted_count,
                       MIN(ts.evaluation_score) as lowest_score,
                       MAX(ts.evaluation_score) as highest_score
                     FROM faculty f
                     JOIN training_suggestions ts ON f.id = ts.user_id
                     WHERE f.is_active = 1
                     GROUP BY f.id
                     ORDER BY critical_count DESC, high_count DESC, total_suggestions DESC, f.last_name, f.first_name";

$suggestions_result = mysqli_query($conn, $suggestions_query);

// Get suggestion statistics
$stats_query = "SELECT
                COUNT(*) as total_suggestions,
                COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_suggestions,
                COUNT(CASE WHEN priority_level = 'critical' THEN 1 END) as critical_suggestions,
                COUNT(CASE WHEN priority_level = 'high' THEN 1 END) as high_suggestions,
                COUNT(CASE WHEN priority_level = 'medium' THEN 1 END) as medium_suggestions,
                COUNT(CASE WHEN priority_level = 'low' THEN 1 END) as low_suggestions
                FROM training_suggestions";

$stats_result = mysqli_query($conn, $stats_query);
$stats = mysqli_fetch_assoc($stats_result);

// Get evaluated faculty members with overall low scores (for display)
$low_scores_query = "SELECT
                      f.id,
                      f.first_name,
                      f.last_name,
                      f.email,
                      'teacher' as role,
                      AVG(er.rating_value) as overall_average_score,
                      COUNT(er.id) as total_ratings,
                      COUNT(DISTINCT esc.id) as total_categories
                    FROM faculty f
                    JOIN evaluation_sessions es ON f.id = es.evaluatee_id
                    JOIN main_evaluation_categories mec ON es.main_category_id = mec.id
                    JOIN evaluation_sub_categories esc ON mec.id = esc.main_category_id
                    JOIN evaluation_questionnaires eq ON esc.id = eq.sub_category_id
                    JOIN evaluation_responses er ON eq.id = er.questionnaire_id AND es.id = er.evaluation_session_id
                    WHERE es.evaluatee_type = 'teacher'
                      AND es.status = 'completed'
                      AND er.rating_value IS NOT NULL
                      AND f.is_active = 1
                    GROUP BY f.id
                    HAVING overall_average_score < 4.0 AND total_ratings >= 3
                    ORDER BY overall_average_score ASC
                    LIMIT 10";

$low_scores_result = mysqli_query($conn, $low_scores_query);

// Include the shared header
include 'includes/header.php';
?>

<!-- Custom CSS for training-suggestions page -->
<link rel="stylesheet" href="assets/css/training-suggestions.css">

<div class="mb-6 sm:mb-8">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-seait-dark mb-2">Training Suggestions</h1>
            <p class="text-sm sm:text-base text-gray-600">AI-powered training recommendations for faculty members (scores below 4.0)</p>
        </div>
        <div class="flex space-x-2">
            <form method="POST" class="inline">
                <input type="hidden" name="action" value="generate_suggestions">
                <button type="submit" class="btn-success">
                    <i class="fas fa-magic mr-2"></i>Generate Suggestions
                </button>
            </form>
            <a href="trainings.php" class="btn-secondary">
                <i class="fas fa-arrow-left mr-2"></i>Back to Trainings
            </a>
        </div>
    </div>
</div>

<?php if ($message): ?>
<div class="mb-6 p-4 rounded-lg <?php echo $message_type === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
    <?php echo htmlspecialchars($message); ?>
</div>
<?php endif; ?>

<!-- Statistics Cards -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    <div class="stats-card">
        <div class="flex items-center">
            <div class="stats-icon stats-icon-blue">
                <i class="fas fa-lightbulb text-blue-600 text-xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-600">Total Suggestions</p>
                <p class="text-2xl font-bold text-gray-900"><?php echo $stats['total_suggestions']; ?></p>
            </div>
        </div>
    </div>

    <div class="stats-card">
        <div class="flex items-center">
            <div class="stats-icon stats-icon-yellow">
                <i class="fas fa-clock text-yellow-600 text-xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-600">Pending</p>
                <p class="text-2xl font-bold text-gray-900"><?php echo $stats['pending_suggestions']; ?></p>
            </div>
        </div>
    </div>

    <div class="stats-card">
        <div class="flex items-center">
            <div class="stats-icon stats-icon-red">
                <i class="fas fa-exclamation-triangle text-red-600 text-xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-600">Critical</p>
                <p class="text-2xl font-bold text-gray-900"><?php echo $stats['critical_suggestions']; ?></p>
            </div>
        </div>
    </div>

    <div class="stats-card">
        <div class="flex items-center">
            <div class="stats-icon stats-icon-orange">
                <i class="fas fa-arrow-up text-orange-600 text-xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-600">High Priority</p>
                <p class="text-2xl font-bold text-gray-900"><?php echo $stats['high_suggestions']; ?></p>
            </div>
        </div>
    </div>
</div>

<!-- Quick Overview -->
<div class="mb-8">
    <!-- Faculty Members with Low Overall Scores -->
    <div class="training-card w-full">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Faculty Members with Overall Scores Below 4.0</h3>
        <div class="space-y-3">
            <?php if (mysqli_num_rows($low_scores_result) > 0): ?>
                <?php $accordion_id = 0; ?>
                <?php while($teacher = mysqli_fetch_assoc($low_scores_result)): ?>
                <?php $accordion_id++; ?>
                <div class="border border-gray-200 rounded-lg overflow-hidden">
                    <!-- Faculty Member Header (Accordion Trigger) -->
                    <button class="accordion-trigger w-full p-4 text-left bg-gray-50 hover:bg-gray-100 transition-colors duration-200" 
                            data-accordion-id="<?php echo $accordion_id; ?>">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <div class="mr-3">
                                    <i class="fas fa-chevron-down accordion-icon text-gray-500 transition-transform duration-200" id="icon-<?php echo $accordion_id; ?>"></i>
                                </div>
                                <div>
                                    <p class="font-medium text-sm">
                                        <?php echo htmlspecialchars($teacher['last_name'] . ', ' . $teacher['first_name']); ?>
                                    </p>
                                    <p class="text-xs text-gray-600"><?php echo htmlspecialchars($teacher['email']); ?></p>
                                </div>
                            </div>
                            <div class="text-right">
                                <p class="font-medium text-sm <?php echo $teacher['overall_average_score'] < 2.5 ? 'text-red-600' : ($teacher['overall_average_score'] < 3.0 ? 'text-orange-600' : 'text-yellow-600'); ?>">
                                    <?php echo round($teacher['overall_average_score'], 2); ?>
                                </p>
                                <p class="text-xs text-gray-500"><?php echo $teacher['total_ratings']; ?> ratings across <?php echo $teacher['total_categories']; ?> categories</p>
                            </div>
                        </div>
                    </button>
                    
                    <!-- Accordion Content -->
                    <div class="accordion-content bg-white" id="content-<?php echo $accordion_id; ?>">
                        <div class="p-4 border-t border-gray-200">
                            <!-- Individual Categories -->
                            <?php
                            // Get individual category scores for this faculty member
                            $categories_query = "SELECT
                                                  esc.name as sub_category_name,
                                                  AVG(er.rating_value) as category_score,
                                                  COUNT(er.id) as category_ratings
                                                FROM faculty f
                                                JOIN evaluation_sessions es ON f.id = es.evaluatee_id
                                                JOIN main_evaluation_categories mec ON es.main_category_id = mec.id
                                                JOIN evaluation_sub_categories esc ON mec.id = esc.main_category_id
                                                JOIN evaluation_questionnaires eq ON esc.id = eq.sub_category_id
                                                JOIN evaluation_responses er ON eq.id = er.questionnaire_id AND es.id = er.evaluation_session_id
                                                WHERE f.id = ?
                                                  AND es.evaluatee_type = 'teacher'
                                                  AND es.status = 'completed'
                                                  AND er.rating_value IS NOT NULL
                                                GROUP BY esc.id
                                                ORDER BY category_score ASC";
                            
                            $categories_stmt = mysqli_prepare($conn, $categories_query);
                            mysqli_stmt_bind_param($categories_stmt, "i", $teacher['id']);
                            mysqli_stmt_execute($categories_stmt);
                            $categories_result = mysqli_stmt_get_result($categories_stmt);
                            
                            if (mysqli_num_rows($categories_result) > 0):
                            ?>
                            <div class="space-y-3">
                                <p class="text-sm font-medium text-gray-700 mb-3">Category Breakdown:</p>
                                <?php while($category = mysqli_fetch_assoc($categories_result)): ?>
                                <div class="flex items-center justify-between p-2 bg-gray-50 rounded">
                                    <span class="text-sm text-gray-700"><?php echo htmlspecialchars($category['sub_category_name']); ?></span>
                                    <div class="flex items-center space-x-3">
                                        <span class="text-sm font-medium <?php echo $category['category_score'] < 2.5 ? 'text-red-600' : ($category['category_score'] < 3.0 ? 'text-orange-600' : 'text-yellow-600'); ?>">
                                            <?php echo round($category['category_score'], 2); ?>
                                        </span>
                                        <span class="text-xs text-gray-500 bg-white px-2 py-1 rounded"><?php echo $category['category_ratings']; ?> ratings</span>
                                    </div>
                                </div>
                                <?php endwhile; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="text-center py-4">
                    <i class="fas fa-check-circle text-green-300 text-2xl mb-2"></i>
                    <p class="text-gray-500 text-sm">All faculty members have scores above 4.0!</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<!-- Teachers with Suggestions -->
<div class="bg-white rounded-lg shadow-md p-6">
    <div class="flex justify-between items-center mb-6">
        <h3 class="text-lg font-semibold text-gray-900">Faculty Members with Training Suggestions</h3>
        <div class="flex space-x-2">
            <div class="dropdown relative">
                <button class="btn-success btn-sm dropdown-toggle" onclick="toggleExportDropdown()">
                    <i class="fas fa-download mr-2"></i>Export
                    <i class="fas fa-chevron-down ml-1"></i>
                </button>
                <div id="exportDropdown" class="dropdown-menu hidden absolute right-0 mt-2 w-64 bg-white border border-gray-200 rounded-lg shadow-lg z-50">
                    <div class="p-4">
                        <h4 class="text-sm font-medium text-gray-900 mb-3">Export Options</h4>
                        
                        <form action="export_suggestions.php" method="GET" class="space-y-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">Priority Level</label>
                                <select name="priority" class="w-full px-2 py-1 text-xs border border-gray-300 rounded focus:outline-none focus:ring-1 focus:ring-seait-orange">
                                    <option value="">All Priorities</option>
                                    <option value="critical">Critical</option>
                                    <option value="high">High</option>
                                    <option value="medium">Medium</option>
                                    <option value="low">Low</option>
                                </select>
                            </div>
                            
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">Status</label>
                                <select name="status" class="w-full px-2 py-1 text-xs border border-gray-300 rounded focus:outline-none focus:ring-1 focus:ring-seait-orange">
                                    <option value="">All Status</option>
                                    <option value="pending">Pending</option>
                                    <option value="accepted">Accepted</option>
                                    <option value="declined">Declined</option>
                                    <option value="completed">Completed</option>
                                </select>
                            </div>
                            
                            <div class="flex space-x-2 pt-2">
                                <button type="submit" class="flex-1 bg-seait-orange text-white text-xs px-3 py-1 rounded hover:bg-orange-600 transition-colors">
                                    Export
                                </button>
                                <button type="button" onclick="toggleExportDropdown()" class="flex-1 bg-gray-300 text-gray-700 text-xs px-3 py-1 rounded hover:bg-gray-400 transition-colors">
                                    Cancel
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="space-y-6">
        <?php if (mysqli_num_rows($suggestions_result) > 0): ?>
            <?php while($teacher = mysqli_fetch_assoc($suggestions_result)): ?>
            <div class="border border-gray-200 rounded-lg p-6">
                <!-- Faculty Member Header -->
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center">
                        <div class="h-12 w-12 rounded-full bg-seait-orange flex items-center justify-center mr-4">
                            <span class="text-white font-medium text-lg">
                                <?php echo strtoupper(substr($teacher['first_name'], 0, 1) . substr($teacher['last_name'], 0, 1)); ?>
                            </span>
                        </div>
                        <div>
                            <h4 class="text-lg font-semibold text-gray-900">
                                <?php echo htmlspecialchars($teacher['last_name'] . ', ' . $teacher['first_name']); ?>
                            </h4>
                            <p class="text-sm text-gray-600"><?php echo htmlspecialchars($teacher['email']); ?></p>
                        </div>
                    </div>
                    <div class="text-right">
                        <p class="text-sm font-medium text-gray-900"><?php echo $teacher['total_suggestions']; ?> suggestions</p>
                        <p class="text-xs text-gray-500">
                            Score range: <?php echo round($teacher['lowest_score'], 2); ?> - <?php echo round($teacher['highest_score'], 2); ?>
                        </p>
                    </div>
                </div>

                <!-- Priority Summary -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
                    <?php if ($teacher['critical_count'] > 0): ?>
                    <div class="text-center p-3 bg-red-50 rounded-lg">
                        <p class="text-lg font-bold text-red-600"><?php echo $teacher['critical_count']; ?></p>
                        <p class="text-xs text-red-600">Critical</p>
                    </div>
                    <?php endif; ?>

                    <?php if ($teacher['high_count'] > 0): ?>
                    <div class="text-center p-3 bg-orange-50 rounded-lg">
                        <p class="text-lg font-bold text-orange-600"><?php echo $teacher['high_count']; ?></p>
                        <p class="text-xs text-orange-600">High</p>
                    </div>
                    <?php endif; ?>

                    <?php if ($teacher['medium_count'] > 0): ?>
                    <div class="text-center p-3 bg-yellow-50 rounded-lg">
                        <p class="text-lg font-bold text-yellow-600"><?php echo $teacher['medium_count']; ?></p>
                        <p class="text-xs text-yellow-600">Medium</p>
                    </div>
                    <?php endif; ?>

                    <?php if ($teacher['low_count'] > 0): ?>
                    <div class="text-center p-3 bg-green-50 rounded-lg">
                        <p class="text-lg font-bold text-green-600"><?php echo $teacher['low_count']; ?></p>
                        <p class="text-xs text-green-600">Low</p>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Status Summary -->
                <div class="flex items-center justify-between mb-4">
                    <div class="flex space-x-4">
                        <span class="text-sm text-gray-600">
                            <i class="fas fa-clock mr-1"></i><?php echo $teacher['pending_count']; ?> pending
                        </span>
                        <span class="text-sm text-gray-600">
                            <i class="fas fa-check mr-1"></i><?php echo $teacher['accepted_count']; ?> accepted
                        </span>
                    </div>
                    <a href="view-teacher-suggestions.php?teacher_id=<?php echo $teacher['teacher_id']; ?>"
                       class="btn-info btn-sm">
                        <i class="fas fa-eye mr-2"></i>View Details
                    </a>
                </div>

                <!-- Individual Suggestions Preview -->
                <?php
                // Get individual suggestions for this teacher
                $teacher_suggestions_query = "SELECT ts.*, ts2.title as training_title, ts2.type as training_type, esc.name as category_name,
                                            mec.evaluation_type,
                                            CASE 
                                                WHEN mec.evaluation_type = 'student_to_teacher' THEN 'Student'
                                                WHEN mec.evaluation_type = 'peer_to_peer' THEN 'Faculty'
                                                WHEN mec.evaluation_type = 'head_to_teacher' THEN 'Head'
                                                ELSE 'Unknown'
                                            END as evaluator_type
                                            FROM training_suggestions ts
                                            JOIN trainings_seminars ts2 ON ts.training_id = ts2.id
                                            LEFT JOIN evaluation_sub_categories esc ON ts.evaluation_category_id = esc.id
                                            LEFT JOIN main_evaluation_categories mec ON esc.main_category_id = mec.id
                                            WHERE ts.user_id = ?
                                            ORDER BY ts.priority_level DESC, ts.suggestion_date DESC
                                            LIMIT 3";

                $teacher_stmt = mysqli_prepare($conn, $teacher_suggestions_query);
                mysqli_stmt_bind_param($teacher_stmt, "i", $teacher['teacher_id']);
                mysqli_stmt_execute($teacher_stmt);
                $teacher_suggestions_result = mysqli_stmt_get_result($teacher_stmt);

                if (mysqli_num_rows($teacher_suggestions_result) > 0):
                ?>
                <div class="space-y-2">
                    <h5 class="text-sm font-medium text-gray-700">Recent Suggestions:</h5>
                    <?php while($suggestion = mysqli_fetch_assoc($teacher_suggestions_result)): ?>
                    <div class="flex items-center justify-between p-2 bg-gray-50 rounded text-sm">
                        <div class="flex-1">
                            <p class="font-medium"><?php echo htmlspecialchars($suggestion['training_title']); ?></p>
                            <div class="flex items-center space-x-2 text-xs text-gray-600">
                                <span><?php echo htmlspecialchars($suggestion['category_name'] ?? 'N/A'); ?></span>
                                <span class="text-gray-400">•</span>
                                <span class="px-1 py-0.5 bg-blue-100 text-blue-700 rounded text-xs">
                                    <?php echo htmlspecialchars($suggestion['evaluator_type']); ?>
                                </span>
                            </div>
                        </div>
                        <div class="flex items-center space-x-2">
                            <span class="priority-<?php echo $suggestion['priority_level']; ?>">
                                <?php echo ucfirst($suggestion['priority_level']); ?>
                            </span>
                            <span class="status-<?php echo $suggestion['status']; ?>">
                                <?php echo ucfirst($suggestion['status']); ?>
                            </span>
                        </div>
                    </div>
                    <?php endwhile; ?>

                    <?php if ($teacher['total_suggestions'] > 3): ?>
                    <p class="text-xs text-gray-500 text-center">
                        +<?php echo $teacher['total_suggestions'] - 3; ?> more suggestions
                    </p>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-lightbulb empty-state-icon"></i>
                <p class="empty-state-text">No training suggestions found. Click "Generate Suggestions" to create recommendations for faculty members with scores below 4.0.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
// Include the shared footer
include 'includes/footer.php';
?>

<script>
function toggleAccordion(id) {
    const content = document.getElementById('content-' + id);
    const icon = document.getElementById('icon-' + id);
    if (content.classList.contains('show')) {
        content.classList.remove('show');
        icon.style.transform = 'rotate(0deg)';
    } else {
        content.classList.add('show');
        icon.style.transform = 'rotate(180deg)';
    }
}

// Optional: Add smooth animation
document.addEventListener('DOMContentLoaded', function() {
    const accordionContents = document.querySelectorAll('.accordion-content');
    accordionContents.forEach(content => {
        content.style.transition = 'all 0.3s ease-in-out';
    });
});

// Event delegation for accordion triggers
document.addEventListener('click', function(event) {
    const btn = event.target.closest('.accordion-trigger');
    if (btn) {
        const id = btn.getAttribute('data-accordion-id');
        toggleAccordion(id);
    }
});

function toggleExportDropdown() {
    const dropdown = document.getElementById('exportDropdown');
    dropdown.classList.toggle('hidden');
}

// Close dropdown when clicking outside
document.addEventListener('click', function(event) {
    const dropdown = document.getElementById('exportDropdown');
    const dropdownButton = event.target.closest('.dropdown');
    if (!dropdownButton && !dropdown.classList.contains('hidden')) {
        dropdown.classList.add('hidden');
    }
});
</script>