<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and has guidance_officer role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'guidance_officer') {
    header('Location: ../login.php');
    exit();
}

// Get teacher ID
$teacher_id = isset($_GET['teacher_id']) ? (int)$_GET['teacher_id'] : 0;

if (!$teacher_id) {
    header('Location: training-suggestions.php');
    exit();
}

// Get teacher details
$teacher_query = "SELECT id, first_name, last_name, email FROM users WHERE id = ? AND role = 'teacher'";
$stmt = mysqli_prepare($conn, $teacher_query);
mysqli_stmt_bind_param($stmt, "i", $teacher_id);
mysqli_stmt_execute($stmt);
$teacher_result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($teacher_result) == 0) {
    header('Location: training-suggestions.php');
    exit();
}

$teacher = mysqli_fetch_assoc($teacher_result);

// Get teacher's evaluation scores by category
$evaluation_scores_query = "SELECT
                             esc.id as sub_category_id,
                             esc.name as sub_category_name,
                             mec.name as main_category_name,
                             AVG(er.rating_value) as average_score,
                             COUNT(er.id) as total_ratings,
                             CASE
                                 WHEN AVG(er.rating_value) < 2.5 THEN 'critical'
                                 WHEN AVG(er.rating_value) < 3.0 THEN 'high'
                                 WHEN AVG(er.rating_value) < 3.5 THEN 'medium'
                                 WHEN AVG(er.rating_value) < 4.0 THEN 'low'
                                 ELSE 'good'
                             END as performance_level
                           FROM evaluation_sessions es
                           JOIN main_evaluation_categories mec ON es.main_category_id = mec.id
                           JOIN evaluation_sub_categories esc ON mec.id = esc.main_category_id
                           JOIN evaluation_questionnaires eq ON esc.id = eq.sub_category_id
                           JOIN evaluation_responses er ON eq.id = er.questionnaire_id AND es.id = er.evaluation_session_id
                           WHERE es.evaluatee_id = ?
                             AND es.evaluatee_type = 'teacher'
                             AND es.status = 'completed'
                             AND er.rating_value IS NOT NULL
                           GROUP BY esc.id
                           HAVING total_ratings >= 3
                           ORDER BY average_score ASC";

$stmt = mysqli_prepare($conn, $evaluation_scores_query);
mysqli_stmt_bind_param($stmt, "i", $teacher_id);
mysqli_stmt_execute($stmt);
$evaluation_scores_result = mysqli_stmt_get_result($stmt);

// Get teacher's training suggestions
$suggestions_query = "SELECT ts.*,
                      ts2.title as training_title, ts2.type as training_type, ts2.start_date, ts2.end_date,
                      esc.name as category_name
                      FROM training_suggestions ts
                      JOIN trainings_seminars ts2 ON ts.training_id = ts2.id
                      LEFT JOIN evaluation_sub_categories esc ON ts.evaluation_category_id = esc.id
                      WHERE ts.user_id = ?
                      ORDER BY ts.priority_level DESC, ts.suggestion_date DESC";

$stmt = mysqli_prepare($conn, $suggestions_query);
mysqli_stmt_bind_param($stmt, "i", $teacher_id);
mysqli_stmt_execute($stmt);
$suggestions_result = mysqli_stmt_get_result($stmt);

// Get suggestion statistics
$stats_query = "SELECT
                COUNT(*) as total_suggestions,
                COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_suggestions,
                COUNT(CASE WHEN status = 'accepted' THEN 1 END) as accepted_suggestions,
                COUNT(CASE WHEN status = 'declined' THEN 1 END) as declined_suggestions,
                COUNT(CASE WHEN priority_level = 'critical' THEN 1 END) as critical_suggestions,
                COUNT(CASE WHEN priority_level = 'high' THEN 1 END) as high_suggestions,
                COUNT(CASE WHEN priority_level = 'medium' THEN 1 END) as medium_suggestions,
                COUNT(CASE WHEN priority_level = 'low' THEN 1 END) as low_suggestions
                FROM training_suggestions
                WHERE user_id = ?";

$stmt = mysqli_prepare($conn, $stats_query);
mysqli_stmt_bind_param($stmt, "i", $teacher_id);
mysqli_stmt_execute($stmt);
$stats_result = mysqli_stmt_get_result($stmt);
$stats = mysqli_fetch_assoc($stats_result);

// Set page title
$page_title = 'Teacher Suggestions: ' . $teacher['first_name'] . ' ' . $teacher['last_name'];

// Include the shared header
include 'includes/header.php';
?>

<div class="mb-6 sm:mb-8">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-seait-dark mb-2">
                <?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?>
            </h1>
            <p class="text-sm sm:text-base text-gray-600">Training Suggestions & Evaluation Analysis</p>
        </div>
        <div class="flex space-x-2">
            <a href="training-suggestions.php" class="btn-secondary">
                <i class="fas fa-arrow-left mr-2"></i>Back to Suggestions
            </a>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    <!-- Teacher Profile -->
    <div class="lg:col-span-1">
        <div class="training-card">
            <div class="flex items-center mb-6">
                <div class="h-16 w-16 rounded-full bg-seait-orange flex items-center justify-center mr-4">
                    <span class="text-white font-medium text-xl">
                        <?php echo strtoupper(substr($teacher['first_name'], 0, 1) . substr($teacher['last_name'], 0, 1)); ?>
                    </span>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-gray-900">
                        <?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?>
                    </h3>
                    <p class="text-sm text-gray-600"><?php echo htmlspecialchars($teacher['email']); ?></p>
                    <p class="text-xs text-gray-500">Teacher</p>
                </div>
            </div>

            <!-- Quick Stats -->
            <div class="space-y-4">
                <div class="flex justify-between">
                    <span class="text-sm text-gray-600">Total Suggestions</span>
                    <span class="text-sm font-medium text-gray-900"><?php echo $stats['total_suggestions']; ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-sm text-gray-600">Pending</span>
                    <span class="text-sm font-medium text-gray-900"><?php echo $stats['pending_suggestions']; ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-sm text-gray-600">Accepted</span>
                    <span class="text-sm font-medium text-gray-900"><?php echo $stats['accepted_suggestions']; ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-sm text-gray-600">Declined</span>
                    <span class="text-sm font-medium text-gray-900"><?php echo $stats['declined_suggestions']; ?></span>
                </div>
            </div>
        </div>

        <!-- Priority Breakdown -->
        <div class="training-card mt-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Priority Breakdown</h3>
            <div class="space-y-3">
                <?php if ($stats['critical_suggestions'] > 0): ?>
                <div class="flex justify-between items-center p-2 bg-red-50 rounded">
                    <span class="text-sm text-red-700">Critical</span>
                    <span class="text-sm font-medium text-red-700"><?php echo $stats['critical_suggestions']; ?></span>
                </div>
                <?php endif; ?>

                <?php if ($stats['high_suggestions'] > 0): ?>
                <div class="flex justify-between items-center p-2 bg-orange-50 rounded">
                    <span class="text-sm text-orange-700">High</span>
                    <span class="text-sm font-medium text-orange-700"><?php echo $stats['high_suggestions']; ?></span>
                </div>
                <?php endif; ?>

                <?php if ($stats['medium_suggestions'] > 0): ?>
                <div class="flex justify-between items-center p-2 bg-yellow-50 rounded">
                    <span class="text-sm text-yellow-700">Medium</span>
                    <span class="text-sm font-medium text-yellow-700"><?php echo $stats['medium_suggestions']; ?></span>
                </div>
                <?php endif; ?>

                <?php if ($stats['low_suggestions'] > 0): ?>
                <div class="flex justify-between items-center p-2 bg-green-50 rounded">
                    <span class="text-sm text-green-700">Low</span>
                    <span class="text-sm font-medium text-green-700"><?php echo $stats['low_suggestions']; ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="lg:col-span-2 space-y-6">
        <!-- Evaluation Scores -->
        <div class="training-card">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Evaluation Performance by Category</h3>
            <div class="space-y-3">
                <?php if (mysqli_num_rows($evaluation_scores_result) > 0): ?>
                    <?php while($score = mysqli_fetch_assoc($evaluation_scores_result)): ?>
                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded">
                        <div class="flex-1">
                            <p class="font-medium text-sm"><?php echo htmlspecialchars($score['sub_category_name']); ?></p>
                            <p class="text-xs text-gray-600"><?php echo htmlspecialchars($score['main_category_name']); ?></p>
                        </div>
                        <div class="text-right">
                            <p class="font-medium text-sm <?php
                                echo $score['performance_level'] === 'critical' ? 'text-red-600' :
                                    ($score['performance_level'] === 'high' ? 'text-orange-600' :
                                    ($score['performance_level'] === 'medium' ? 'text-yellow-600' :
                                    ($score['performance_level'] === 'low' ? 'text-green-600' : 'text-blue-600')));
                            ?>">
                                <?php echo round($score['average_score'], 2); ?>
                            </p>
                            <p class="text-xs text-gray-500"><?php echo $score['total_ratings']; ?> ratings</p>
                            <?php if ($score['average_score'] < 4.0): ?>
                            <p class="text-xs text-red-600">Below threshold (4.0)</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-chart-line text-gray-300 text-2xl mb-2"></i>
                        <p class="text-gray-500 text-sm">No evaluation data available.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Training Suggestions -->
        <div class="training-card">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Training Suggestions</h3>
            <div class="space-y-4">
                <?php if (mysqli_num_rows($suggestions_result) > 0): ?>
                    <?php while($suggestion = mysqli_fetch_assoc($suggestions_result)): ?>
                    <div class="border border-gray-200 rounded-lg p-4">
                        <div class="flex items-start justify-between mb-3">
                            <div class="flex-1">
                                <h4 class="font-medium text-gray-900"><?php echo htmlspecialchars($suggestion['training_title']); ?></h4>
                                <p class="text-sm text-gray-600"><?php echo ucfirst($suggestion['training_type']); ?></p>
                                <p class="text-xs text-gray-500">
                                    <?php echo date('M d, Y', strtotime($suggestion['start_date'])); ?> -
                                    <?php echo date('M d, Y', strtotime($suggestion['end_date'])); ?>
                                </p>
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

                        <div class="mb-3">
                            <p class="text-sm text-gray-700"><?php echo htmlspecialchars($suggestion['suggestion_reason']); ?></p>
                        </div>

                        <div class="flex items-center justify-between text-xs text-gray-500">
                            <span>Category: <?php echo htmlspecialchars($suggestion['category_name'] ?? 'N/A'); ?></span>
                            <span>Score: <?php echo round($suggestion['evaluation_score'], 2); ?></span>
                        </div>

                        <div class="flex items-center space-x-2 mt-3">
                            <a href="view-training.php?id=<?php echo $suggestion['training_id']; ?>"
                               class="text-seait-orange hover:text-orange-600 text-sm">
                                <i class="fas fa-eye mr-1"></i>View Training
                            </a>
                            <?php if ($suggestion['status'] === 'pending'): ?>
                            <a href="edit-suggestion.php?id=<?php echo $suggestion['id']; ?>"
                               class="text-blue-600 hover:text-blue-800 text-sm">
                                <i class="fas fa-edit mr-1"></i>Edit
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="text-center py-8">
                        <i class="fas fa-lightbulb text-gray-300 text-2xl mb-2"></i>
                        <p class="text-gray-500 text-sm">No training suggestions found for this teacher.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
// Include the shared footer
include 'includes/footer.php';
?>