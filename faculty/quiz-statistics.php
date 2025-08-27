<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/id_encryption.php';

// Check if user is logged in and has teacher role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header('Location: ../login.php');
    exit();
}

// Get quiz ID from URL
$quiz_id = safe_decrypt_id($_GET['id']);
if (!$quiz_id) {
    header('Location: quizzes.php');
    exit();
}

// Get quiz details and verify ownership
$quiz_query = "SELECT q.*, l.title as lesson_title, 
               COUNT(DISTINCT qq.id) as total_questions,
               COUNT(DISTINCT qca.id) as total_assignments,
               COUNT(DISTINCT qs.id) as total_submissions,
               COUNT(DISTINCT CASE WHEN qs.status = 'completed' THEN qs.id END) as completed_submissions,
               AVG(CASE WHEN qs.score IS NOT NULL THEN qs.score END) as average_score,
               MIN(CASE WHEN qs.score IS NOT NULL THEN qs.score END) as lowest_score,
               MAX(CASE WHEN qs.score IS NOT NULL THEN qs.score END) as highest_score
               FROM quizzes q
               LEFT JOIN lessons l ON q.lesson_id = l.id
               LEFT JOIN quiz_questions qq ON q.id = qq.quiz_id
               LEFT JOIN quiz_class_assignments qca ON q.id = qca.quiz_id
               LEFT JOIN quiz_submissions qs ON qca.id = qs.assignment_id
               WHERE q.id = ? AND q.teacher_id = ?
               GROUP BY q.id";

$quiz_stmt = mysqli_prepare($conn, $quiz_query);
mysqli_stmt_bind_param($quiz_stmt, "ii", $quiz_id, $_SESSION['user_id']);
mysqli_stmt_execute($quiz_stmt);
$quiz_result = mysqli_stmt_get_result($quiz_stmt);

if (mysqli_num_rows($quiz_result) === 0) {
    header('Location: quizzes.php');
    exit();
}

$quiz = mysqli_fetch_assoc($quiz_result);

// Get submission statistics by status
$status_stats_query = "SELECT 
                        qs.status,
                        COUNT(*) as count,
                        AVG(qs.score) as avg_score,
                        AVG(qs.time_taken) as avg_time
                       FROM quiz_submissions qs
                       JOIN quiz_class_assignments qca ON qs.assignment_id = qca.id
                       WHERE qca.quiz_id = ?
                       GROUP BY qs.status";
$status_stmt = mysqli_prepare($conn, $status_stats_query);
mysqli_stmt_bind_param($status_stmt, "i", $quiz_id);
mysqli_stmt_execute($status_stmt);
$status_stats_result = mysqli_stmt_get_result($status_stmt);

$status_stats = [];
while ($row = mysqli_fetch_assoc($status_stats_result)) {
    $status_stats[$row['status']] = $row;
}

// Get score distribution
$score_distribution_query = "SELECT 
                              CASE 
                                WHEN qs.score >= 90 THEN '90-100%'
                                WHEN qs.score >= 80 THEN '80-89%'
                                WHEN qs.score >= 70 THEN '70-79%'
                                WHEN qs.score >= 60 THEN '60-69%'
                                WHEN qs.score >= 50 THEN '50-59%'
                                ELSE 'Below 50%'
                              END as score_range,
                              COUNT(*) as count
                             FROM quiz_submissions qs
                             JOIN quiz_class_assignments qca ON qs.assignment_id = qca.id
                             WHERE qca.quiz_id = ? AND qs.status = 'completed' AND qs.score IS NOT NULL
                             GROUP BY score_range
                             ORDER BY 
                               CASE score_range
                                 WHEN '90-100%' THEN 1
                                 WHEN '80-89%' THEN 2
                                 WHEN '70-79%' THEN 3
                                 WHEN '60-69%' THEN 4
                                 WHEN '50-59%' THEN 5
                                 ELSE 6
                               END";
$score_dist_stmt = mysqli_prepare($conn, $score_distribution_query);
mysqli_stmt_bind_param($score_dist_stmt, "i", $quiz_id);
mysqli_stmt_execute($score_dist_stmt);
$score_distribution_result = mysqli_stmt_get_result($score_dist_stmt);

$score_distribution = [];
while ($row = mysqli_fetch_assoc($score_distribution_result)) {
    $score_distribution[] = $row;
}

// Get recent submissions
$recent_submissions_query = "SELECT 
  u.first_name, u.last_name, s.student_id,
  qs.score, qs.status, qs.time_taken, qs.start_time, qs.end_time,
  tc.section, cc.subject_title
 FROM quiz_submissions qs
 JOIN users u ON qs.student_id = u.id
 LEFT JOIN students s ON qs.student_id = s.id
 JOIN quiz_class_assignments qca ON qs.assignment_id = qca.id
 JOIN teacher_classes tc ON qca.class_id = tc.id
 JOIN course_curriculum cc ON tc.subject_id = cc.id
 WHERE qca.quiz_id = ?
 ORDER BY qs.created_at DESC
 LIMIT 10";
$recent_stmt = mysqli_prepare($conn, $recent_submissions_query);
mysqli_stmt_bind_param($recent_stmt, "i", $quiz_id);
mysqli_stmt_execute($recent_stmt);
$recent_submissions_result = mysqli_stmt_get_result($recent_stmt);

// Get question performance analysis
$question_performance_query = "SELECT 
                                qq.id, qq.question_text, qq.question_type, qq.points,
                                COUNT(qsa.id) as total_attempts,
                                COUNT(CASE WHEN qsa.is_correct = 1 THEN 1 END) as correct_attempts,
                                ROUND((COUNT(CASE WHEN qsa.is_correct = 1 THEN 1 END) / COUNT(qsa.id)) * 100, 2) as success_rate
                               FROM quiz_questions qq
                               LEFT JOIN quiz_submission_answers qsa ON qq.id = qsa.question_id
                               LEFT JOIN quiz_submissions qs ON qsa.submission_id = qs.id
                               LEFT JOIN quiz_class_assignments qca ON qs.assignment_id = qca.id
                               WHERE qq.quiz_id = ? AND (qca.quiz_id = ? OR qca.quiz_id IS NULL)
                               GROUP BY qq.id
                               ORDER BY qq.order_number";
$question_stmt = mysqli_prepare($conn, $question_performance_query);
mysqli_stmt_bind_param($question_stmt, "ii", $quiz_id, $quiz_id);
mysqli_stmt_execute($question_stmt);
$question_performance_result = mysqli_stmt_get_result($question_stmt);

// Get class-wise performance
$class_performance_query = "SELECT 
                             tc.section, cc.subject_title, cc.subject_code,
                             COUNT(DISTINCT qs.student_id) as total_students,
                             COUNT(DISTINCT CASE WHEN qs.status = 'completed' THEN qs.student_id END) as completed_students,
                             AVG(CASE WHEN qs.status = 'completed' THEN qs.score END) as avg_score,
                             MIN(CASE WHEN qs.status = 'completed' THEN qs.score END) as min_score,
                             MAX(CASE WHEN qs.status = 'completed' THEN qs.score END) as max_score
                            FROM quiz_class_assignments qca
                            JOIN teacher_classes tc ON qca.class_id = tc.id
                            JOIN course_curriculum cc ON tc.subject_id = cc.id
                            LEFT JOIN quiz_submissions qs ON qca.id = qs.assignment_id
                            WHERE qca.quiz_id = ?
                            GROUP BY tc.id
                            ORDER BY avg_score DESC";
$class_stmt = mysqli_prepare($conn, $class_performance_query);
mysqli_stmt_bind_param($class_stmt, "i", $quiz_id);
mysqli_stmt_execute($class_stmt);
$class_performance_result = mysqli_stmt_get_result($class_stmt);

// Get time-based analytics (last 30 days)
$time_analytics_query = "SELECT 
                          DATE(qs.created_at) as date,
                          COUNT(*) as submissions,
                          COUNT(CASE WHEN qs.status = 'completed' THEN 1 END) as completed,
                          AVG(CASE WHEN qs.status = 'completed' THEN qs.score END) as avg_score
                         FROM quiz_submissions qs
                         JOIN quiz_class_assignments qca ON qs.assignment_id = qca.id
                         WHERE qca.quiz_id = ? AND qs.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                         GROUP BY DATE(qs.created_at)
                         ORDER BY date DESC";
$time_stmt = mysqli_prepare($conn, $time_analytics_query);
mysqli_stmt_bind_param($time_stmt, "i", $quiz_id);
mysqli_stmt_execute($time_stmt);
$time_analytics_result = mysqli_stmt_get_result($time_stmt);

$time_analytics = [];
while ($row = mysqli_fetch_assoc($time_analytics_result)) {
    $time_analytics[] = $row;
}

// Set page title
$page_title = 'Quiz Statistics: ' . $quiz['title'];

// Include the unified header
$sidebar_context = 'main';
include 'includes/unified-header.php';
?>

<div class="mb-6 sm:mb-8">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-seait-dark mb-2">Quiz Statistics</h1>
            <p class="text-sm sm:text-base text-gray-600">Detailed analytics and performance metrics for your quiz</p>
        </div>
        <div class="flex space-x-2">
            <a href="quizzes.php" class="bg-gray-500 text-white px-4 py-2 rounded-md hover:bg-gray-600 transition">
                <i class="fas fa-arrow-left mr-2"></i>Back to Quizzes
            </a>
            <a href="view-quiz.php?id=<?php echo encrypt_id($quiz_id); ?>" class="bg-seait-orange text-white px-4 py-2 rounded-md hover:bg-orange-600 transition">
                <i class="fas fa-eye mr-2"></i>View Quiz
            </a>
        </div>
    </div>
</div>

<!-- Quiz Overview Card -->
<div class="bg-white rounded-lg shadow-md p-6 mb-6">
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
        <div class="flex-1">
            <h2 class="text-xl font-semibold text-gray-900 mb-2"><?php echo htmlspecialchars($quiz['title']); ?></h2>
            <p class="text-gray-600 mb-3"><?php echo htmlspecialchars($quiz['description']); ?></p>
            <div class="flex flex-wrap gap-4 text-sm text-gray-500">
                <span class="flex items-center">
                    <i class="fas fa-tag mr-2"></i>
                    <?php echo ucwords(str_replace('_', ' ', $quiz['quiz_type'])); ?>
                </span>
                <?php if ($quiz['lesson_title']): ?>
                <span class="flex items-center">
                    <i class="fas fa-book mr-2"></i>
                    <?php echo htmlspecialchars($quiz['lesson_title']); ?>
                </span>
                <?php endif; ?>
                <span class="flex items-center">
                    <i class="fas fa-clock mr-2"></i>
                    <?php echo $quiz['time_limit'] ? $quiz['time_limit'] . ' minutes' : 'No time limit'; ?>
                </span>
                <span class="flex items-center">
                    <i class="fas fa-percentage mr-2"></i>
                    Passing: <?php echo $quiz['passing_score']; ?>%
                </span>
            </div>
        </div>
        <div class="text-right">
            <div class="text-2xl font-bold text-seait-orange"><?php echo $quiz['total_questions']; ?></div>
            <div class="text-sm text-gray-500">Total Questions</div>
        </div>
    </div>
</div>

<!-- Key Statistics Cards -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    <!-- Total Submissions -->
    <div class="bg-white rounded-lg shadow-md p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-600">Total Submissions</p>
                <p class="text-2xl font-bold text-gray-900"><?php echo $quiz['total_submissions'] ?: 0; ?></p>
            </div>
            <div class="p-3 bg-blue-100 rounded-full">
                <i class="fas fa-users text-blue-600 text-xl"></i>
            </div>
        </div>
    </div>

    <!-- Completed Submissions -->
    <div class="bg-white rounded-lg shadow-md p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-600">Completed</p>
                <p class="text-2xl font-bold text-gray-900"><?php echo $quiz['completed_submissions'] ?: 0; ?></p>
                <?php if ($quiz['total_submissions'] > 0): ?>
                <p class="text-sm text-green-600">
                    <?php echo round(($quiz['completed_submissions'] / $quiz['total_submissions']) * 100, 1); ?>% completion rate
                </p>
                <?php endif; ?>
            </div>
            <div class="p-3 bg-green-100 rounded-full">
                <i class="fas fa-check-circle text-green-600 text-xl"></i>
            </div>
        </div>
    </div>

    <!-- Average Score -->
    <div class="bg-white rounded-lg shadow-md p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-600">Average Score</p>
                <p class="text-2xl font-bold text-gray-900">
                    <?php echo $quiz['average_score'] ? round($quiz['average_score'], 1) . '%' : 'N/A'; ?>
                </p>
            </div>
            <div class="p-3 bg-yellow-100 rounded-full">
                <i class="fas fa-chart-line text-yellow-600 text-xl"></i>
            </div>
        </div>
    </div>

    <!-- Score Range -->
    <div class="bg-white rounded-lg shadow-md p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-600">Score Range</p>
                <p class="text-2xl font-bold text-gray-900">
                    <?php 
                    if ($quiz['lowest_score'] !== null && $quiz['highest_score'] !== null) {
                        echo round($quiz['lowest_score'], 1) . '% - ' . round($quiz['highest_score'], 1) . '%';
                    } else {
                        echo 'N/A';
                    }
                    ?>
                </p>
            </div>
            <div class="p-3 bg-purple-100 rounded-full">
                <i class="fas fa-range text-purple-600 text-xl"></i>
            </div>
        </div>
    </div>
</div>

<!-- Submission Status Overview -->
<?php if (!empty($status_stats)): ?>
<div class="bg-white rounded-lg shadow-md p-6 mb-8">
    <h3 class="text-lg font-semibold text-gray-900 mb-4">Submission Status Overview</h3>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <?php foreach ($status_stats as $status => $stats): ?>
        <div class="border rounded-lg p-4">
            <div class="flex items-center justify-between mb-2">
                <span class="text-sm font-medium text-gray-600">
                    <?php echo ucfirst($status); ?>
                </span>
                <span class="text-lg font-bold text-gray-900"><?php echo $stats['count']; ?></span>
            </div>
            <?php if ($stats['avg_score'] !== null): ?>
            <div class="text-sm text-gray-500">
                Avg Score: <?php echo round($stats['avg_score'], 1); ?>%
            </div>
            <?php endif; ?>
            <?php if ($stats['avg_time'] !== null): ?>
            <div class="text-sm text-gray-500">
                Avg Time: <?php echo round($stats['avg_time'] / 60, 1); ?> min
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- Score Distribution Chart -->
<?php if (!empty($score_distribution)): ?>
<div class="bg-white rounded-lg shadow-md p-6 mb-8">
    <h3 class="text-lg font-semibold text-gray-900 mb-4">Score Distribution</h3>
    <div class="space-y-3">
        <?php foreach ($score_distribution as $range): ?>
        <div class="flex items-center">
            <div class="w-24 text-sm font-medium text-gray-600"><?php echo $range['score_range']; ?></div>
            <div class="flex-1 mx-4">
                <div class="w-full bg-gray-200 rounded-full h-2">
                    <?php 
                    $percentage = $quiz['completed_submissions'] > 0 ? ($range['count'] / $quiz['completed_submissions']) * 100 : 0;
                    $color_class = '';
                    if (strpos($range['score_range'], '90-100') !== false || strpos($range['score_range'], '80-89') !== false) {
                        $color_class = 'bg-green-500';
                    } elseif (strpos($range['score_range'], '70-79') !== false || strpos($range['score_range'], '60-69') !== false) {
                        $color_class = 'bg-yellow-500';
                    } else {
                        $color_class = 'bg-red-500';
                    }
                    ?>
                    <div class="<?php echo $color_class; ?> h-2 rounded-full" style="width: <?php echo $percentage; ?>%"></div>
                </div>
            </div>
            <div class="w-16 text-sm text-gray-900 text-right"><?php echo $range['count']; ?></div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- Class Performance -->
<?php if (mysqli_num_rows($class_performance_result) > 0): ?>
<div class="bg-white rounded-lg shadow-md p-6 mb-8">
    <h3 class="text-lg font-semibold text-gray-900 mb-4">Class Performance</h3>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Class</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subject</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Students</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Completed</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Avg Score</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Range</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php while ($class = mysqli_fetch_assoc($class_performance_result)): ?>
                <tr>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                        <?php echo htmlspecialchars($class['section']); ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        <?php echo htmlspecialchars($class['subject_title']); ?>
                        <br><span class="text-xs text-gray-400"><?php echo htmlspecialchars($class['subject_code']); ?></span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        <?php echo $class['total_students']; ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        <?php echo $class['completed_students']; ?>
                        <?php if ($class['total_students'] > 0): ?>
                        <span class="text-xs text-gray-500">
                            (<?php echo round(($class['completed_students'] / $class['total_students']) * 100, 1); ?>%)
                        </span>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        <?php echo $class['avg_score'] ? round($class['avg_score'], 1) . '%' : 'N/A'; ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        <?php 
                        if ($class['min_score'] !== null && $class['max_score'] !== null) {
                            echo round($class['min_score'], 1) . '% - ' . round($class['max_score'], 1) . '%';
                        } else {
                            echo 'N/A';
                        }
                        ?>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Question Performance Analysis -->
<?php if (mysqli_num_rows($question_performance_result) > 0): ?>
<div class="bg-white rounded-lg shadow-md p-6 mb-8">
    <h3 class="text-lg font-semibold text-gray-900 mb-4">Question Performance Analysis</h3>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">#</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Question</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Points</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Attempts</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Correct</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Success Rate</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php $qnum = 1; while ($question = mysqli_fetch_assoc($question_performance_result)): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo $qnum++; ?></td>
                            <td class="px-6 py-4 whitespace-normal text-sm text-gray-900"><?php echo htmlspecialchars($question['question_text']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $question['question_type']))); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo $question['points']; ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo $question['total_attempts']; ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo $question['correct_attempts']; ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo $question['success_rate'] !== null ? $question['success_rate'] . '%' : 'N/A'; ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>
