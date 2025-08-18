<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Try to include sentiment analysis, but don't fail if it's not available
$sentiment_available = false;
$sentimentAnalyzer = null;

try {
    if (file_exists('../includes/sentiment_analysis.php')) {
        require_once '../includes/sentiment_analysis.php';
        $sentiment_available = true;
        $sentimentAnalyzer = new SentimentAnalysis();
    }
} catch (Exception $e) {
    // Sentiment analysis not available, continue without it
    $sentiment_available = false;
}

// Check if user is logged in and has head role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'head') {
    header('Location: ../index.php');
    exit();
}

// Set page title
$page_title = 'Teacher Evaluation Details';

$message = '';
$message_type = '';

// Check for session messages
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $message_type = $_SESSION['message_type'];
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
}

// Get faculty ID and semester ID from URL
$faculty_id = isset($_GET['faculty_id']) ? (int)$_GET['faculty_id'] : 0;
$semester_id = isset($_GET['semester_id']) ? (int)$_GET['semester_id'] : 0;

if (!$faculty_id) {
    $_SESSION['message'] = 'Invalid faculty ID provided.';
    $_SESSION['message_type'] = 'error';
    header('Location: teachers.php');
    exit();
}

// Get faculty member details
$teacher_query = "SELECT f.id as faculty_id, f.first_name, f.last_name, f.email, 'teacher' as role,
                        f.department, f.position, f.is_active as status
                 FROM faculty f
                 WHERE f.id = ? AND f.is_active = 1";
$teacher_stmt = mysqli_prepare($conn, $teacher_query);
mysqli_stmt_bind_param($teacher_stmt, "i", $faculty_id);
mysqli_stmt_execute($teacher_stmt);
$teacher_result = mysqli_stmt_get_result($teacher_stmt);
$teacher = mysqli_fetch_assoc($teacher_result);

if (!$teacher) {
    $_SESSION['message'] = 'Faculty member not found.';
    $_SESSION['message_type'] = 'error';
    header('Location: teachers.php');
    exit();
}

// Get head information to verify department access
$user_id = $_SESSION['user_id'];
$head_query = "SELECT h.* FROM heads h WHERE h.user_id = ?";
$head_stmt = mysqli_prepare($conn, $head_query);
mysqli_stmt_bind_param($head_stmt, "i", $user_id);
mysqli_stmt_execute($head_stmt);
$head_result = mysqli_stmt_get_result($head_stmt);
$head_info = mysqli_fetch_assoc($head_result);

// Check if head has access to this teacher's department
$head_department = $head_info['department'];
$teacher_department = $teacher['department'];

// Simple department matching - can be enhanced later
$has_access = false;
if ($head_department === $teacher_department) {
    $has_access = true;
} elseif (str_contains($head_department, 'Department of ') && str_contains($teacher_department, 'Department of ')) {
    $has_access = true;
} elseif (str_contains($head_department, 'College of ') && str_contains($teacher_department, 'College of ')) {
    $has_access = true;
}

if (!$has_access) {
    $_SESSION['message'] = 'You do not have access to view this teacher\'s evaluations.';
    $_SESSION['message_type'] = 'error';
    header('Location: teachers.php');
    exit();
}

// Get semester information if provided
$semester_info = null;
if ($semester_id) {
    $semester_query = "SELECT * FROM semesters WHERE id = ?";
    $semester_stmt = mysqli_prepare($conn, $semester_query);
    mysqli_stmt_bind_param($semester_stmt, "i", $semester_id);
    mysqli_stmt_execute($semester_stmt);
    $semester_result = mysqli_stmt_get_result($semester_stmt);
    $semester_info = mysqli_fetch_assoc($semester_result);
}

// Get evaluation sessions for this teacher
$sessions_query = "SELECT es.*, s.name as semester_name, s.academic_year, 
                          mec.name as category_name, mec.evaluation_type
                   FROM evaluation_sessions es
                   JOIN semesters s ON es.semester_id = s.id
                   JOIN main_evaluation_categories mec ON es.main_category_id = mec.id
                   WHERE es.evaluatee_id = ? AND es.evaluatee_type = 'teacher' AND es.status = 'completed'";

if ($semester_id) {
    $sessions_query .= " AND es.semester_id = ?";
}

$sessions_query .= " ORDER BY s.start_date DESC, es.created_at DESC";

$sessions_stmt = mysqli_prepare($conn, $sessions_query);
if ($semester_id) {
    mysqli_stmt_bind_param($sessions_stmt, "ii", $faculty_id, $semester_id);
} else {
    mysqli_stmt_bind_param($sessions_stmt, "i", $faculty_id);
}
mysqli_stmt_execute($sessions_stmt);
$sessions_result = mysqli_stmt_get_result($sessions_stmt);

$evaluation_sessions = [];
while ($session = mysqli_fetch_assoc($sessions_result)) {
    $evaluation_sessions[] = $session;
}

// Get detailed statistics
$stats = [];
if (!empty($evaluation_sessions)) {
    // Overall statistics
    $overall_stats_query = "SELECT 
        COUNT(DISTINCT es.id) as total_sessions,
        COUNT(er.id) as total_responses,
        AVG(er.rating_value) as average_rating,
        MIN(er.rating_value) as min_rating,
        MAX(er.rating_value) as max_rating,
        STDDEV(er.rating_value) as rating_stddev
        FROM evaluation_sessions es
        LEFT JOIN evaluation_responses er ON es.id = er.evaluation_session_id AND er.rating_value IS NOT NULL
        WHERE es.evaluatee_id = ? AND es.evaluatee_type = 'teacher' AND es.status = 'completed'";
    
    if ($semester_id) {
        $overall_stats_query .= " AND es.semester_id = ?";
    }
    
    $overall_stats_stmt = mysqli_prepare($conn, $overall_stats_query);
    if ($semester_id) {
        mysqli_stmt_bind_param($overall_stats_stmt, "ii", $faculty_id, $semester_id);
    } else {
        mysqli_stmt_bind_param($overall_stats_stmt, "i", $faculty_id);
    }
    mysqli_stmt_execute($overall_stats_stmt);
    $overall_stats_result = mysqli_stmt_get_result($overall_stats_stmt);
    $stats = mysqli_fetch_assoc($overall_stats_result);
}

// Get category-wise statistics
$category_stats_query = "SELECT 
    mec.id as category_id,
    mec.name as category_name,
    mec.evaluation_type,
    COUNT(DISTINCT es.id) as session_count,
    COUNT(er.id) as response_count,
    AVG(er.rating_value) as average_rating,
    MIN(er.rating_value) as min_rating,
    MAX(er.rating_value) as max_rating,
    STDDEV(er.rating_value) as rating_stddev
    FROM main_evaluation_categories mec
    LEFT JOIN evaluation_sessions es ON es.main_category_id = mec.id 
        AND es.evaluatee_id = ? AND es.evaluatee_type = 'teacher' AND es.status = 'completed'
    LEFT JOIN evaluation_responses er ON es.id = er.evaluation_session_id AND er.rating_value IS NOT NULL";

if ($semester_id) {
    $category_stats_query .= " AND es.semester_id = ?";
}

$category_stats_query .= " GROUP BY mec.id, mec.name, mec.evaluation_type
    HAVING session_count > 0
    ORDER BY average_rating DESC";

$category_stats_stmt = mysqli_prepare($conn, $category_stats_query);
if ($semester_id) {
    mysqli_stmt_bind_param($category_stats_stmt, "ii", $faculty_id, $semester_id);
} else {
    mysqli_stmt_bind_param($category_stats_stmt, "i", $faculty_id);
}
mysqli_stmt_execute($category_stats_stmt);
$category_stats_result = mysqli_stmt_get_result($category_stats_stmt);

$category_stats = [];
while ($category = mysqli_fetch_assoc($category_stats_result)) {
    $category_stats[] = $category;
}

// Get text responses for sentiment analysis
$text_responses_query = "SELECT 
    er.id as response_id,
    er.text_response,
    er.created_at,
    es.semester_id,
    s.name as semester_name,
    s.academic_year,
    mec.name as category_name
    FROM evaluation_responses er
    JOIN evaluation_sessions es ON er.evaluation_session_id = es.id
    JOIN semesters s ON es.semester_id = s.id
    JOIN main_evaluation_categories mec ON es.main_category_id = mec.id
    WHERE es.evaluatee_id = ? AND es.evaluatee_type = 'teacher' 
    AND es.status = 'completed' AND er.text_response IS NOT NULL 
    AND er.text_response != ''";

if ($semester_id) {
    $text_responses_query .= " AND es.semester_id = ?";
}

$text_responses_query .= " ORDER BY er.created_at DESC";

$text_responses_stmt = mysqli_prepare($conn, $text_responses_query);
if ($semester_id) {
    mysqli_stmt_bind_param($text_responses_stmt, "ii", $faculty_id, $semester_id);
} else {
    mysqli_stmt_bind_param($text_responses_stmt, "i", $faculty_id);
}
mysqli_stmt_execute($text_responses_stmt);
$text_responses_result = mysqli_stmt_get_result($text_responses_stmt);

$text_responses = [];
while ($response = mysqli_fetch_assoc($text_responses_result)) {
    $text_responses[] = $response;
}

// Perform sentiment analysis on text responses if available
$sentiment_data = [];
$emotion_data = [];
$category_sentiment = [];

if ($sentiment_available && !empty($text_responses)) {
    try {
        foreach ($text_responses as $response) {
            $sentiment_result = $sentimentAnalyzer->analyzeSentiment($response['text_response']);
            
            $sentiment_data[] = [
                'response' => $response,
                'sentiment' => $sentiment_result
            ];
            
            // Aggregate sentiment by category
            $category = $response['category_name'];
            if (!isset($category_sentiment[$category])) {
                $category_sentiment[$category] = [
                    'positive' => 0,
                    'negative' => 0,
                    'neutral' => 0,
                    'total' => 0
                ];
            }
            
            $category_sentiment[$category]['total']++;
            $category_sentiment[$category][$sentiment_result['sentiment']]++;
            
            // Aggregate emotions
            if (isset($sentiment_result['emotions'])) {
                foreach ($sentiment_result['emotions'] as $emotion => $confidence) {
                    if (!isset($emotion_data[$emotion])) {
                        $emotion_data[$emotion] = 0;
                    }
                    $emotion_data[$emotion] += $confidence;
                }
            }
        }
    } catch (Exception $e) {
        // If sentiment analysis fails, continue without it
        $sentiment_data = [];
        $emotion_data = [];
        $category_sentiment = [];
    }
}

// Get training and seminar suggestions for categories with scores below 4.0
$training_suggestions = [];
if (!empty($category_stats)) {
    $low_performing_categories = array_filter($category_stats, function($cat) {
        return ($cat['average_rating'] ?? 0) < 4.0;
    });

    if (!empty($low_performing_categories)) {
        foreach ($low_performing_categories as $category) {
            // Get trainings and seminars for this category
            $trainings_query = "SELECT ts.*,
                               tc.name as category_name,
                               mec.name as main_category_name,
                               esc.name as sub_category_name,
                               u.first_name, u.last_name
                               FROM trainings_seminars ts
                               LEFT JOIN training_categories tc ON ts.category_id = tc.id
                               LEFT JOIN main_evaluation_categories mec ON ts.main_category_id = mec.id
                               LEFT JOIN evaluation_sub_categories esc ON ts.sub_category_id = esc.id
                               LEFT JOIN users u ON ts.created_by = u.id
                               WHERE (ts.main_category_id = ? OR ts.sub_category_id IN (
                                   SELECT id FROM evaluation_sub_categories WHERE main_category_id = ?
                               ))
                               ORDER BY ts.start_date DESC, ts.created_at DESC";

            $trainings_stmt = mysqli_prepare($conn, $trainings_query);
            mysqli_stmt_bind_param($trainings_stmt, "ii", $category['category_id'], $category['category_id']);
            mysqli_stmt_execute($trainings_stmt);
            $trainings_result = mysqli_stmt_get_result($trainings_stmt);

            $category_trainings = [];
            while ($training = mysqli_fetch_assoc($trainings_result)) {
                $category_trainings[] = $training;
            }

            if (!empty($category_trainings)) {
                $training_suggestions[] = [
                    'category' => $category,
                    'trainings' => $category_trainings
                ];
            }
        }
    }
}

// Include the header
include 'includes/header.php';
?>

<!-- Page Header -->
<div class="mb-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Teacher Evaluation Details</h1>
            <p class="text-gray-600">
                Detailed analysis for <?php echo $teacher['first_name'] . ' ' . $teacher['last_name']; ?>
                <?php if ($semester_info): ?>
                    - <?php echo $semester_info['name'] . ' (' . $semester_info['academic_year'] . ')'; ?>
                <?php endif; ?>
            </p>
        </div>
        <div class="flex space-x-3">
            <a href="view-evaluation.php?faculty_id=<?php echo $faculty_id; ?>" 
               class="bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600 transition">
                <i class="fas fa-arrow-left mr-2"></i>Back to Overview
            </a>
        </div>
    </div>
</div>

<?php if ($message): ?>
    <div class="mb-6 p-4 rounded-lg <?php echo $message_type === 'error' ? 'bg-red-100 text-red-700 border border-red-300' : 'bg-green-100 text-green-700 border border-green-300'; ?>">
        <?php echo htmlspecialchars($message); ?>
    </div>
<?php endif; ?>

<?php if (!empty($evaluation_sessions)): ?>
    <!-- Overall Statistics -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <div class="bg-white rounded-lg shadow-sm p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-clipboard-check text-blue-600"></i>
                    </div>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500">Total Sessions</p>
                    <p class="text-2xl font-bold text-gray-900"><?php echo $stats['total_sessions']; ?></p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-sm p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-green-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-star text-green-600"></i>
                    </div>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500">Average Rating</p>
                    <p class="text-2xl font-bold text-gray-900"><?php echo number_format($stats['average_rating'], 2); ?></p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-sm p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-purple-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-comments text-purple-600"></i>
                    </div>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500">Total Responses</p>
                    <p class="text-2xl font-bold text-gray-900"><?php echo $stats['total_responses']; ?></p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-sm p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-orange-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-chart-line text-orange-600"></i>
                    </div>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500">Rating Range</p>
                    <p class="text-2xl font-bold text-gray-900"><?php echo $stats['min_rating']; ?> - <?php echo $stats['max_rating']; ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Category Performance -->
    <div class="bg-white rounded-lg shadow-sm overflow-hidden mb-8">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-medium text-gray-900">Category Performance Analysis</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Average Rating</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sessions</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Responses</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Performance</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($category_stats as $category): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($category['category_name']); ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 py-1 text-xs rounded-full 
                                    <?php echo $category['evaluation_type'] === 'student_to_teacher' ? 'bg-blue-100 text-blue-800' : 
                                        ($category['evaluation_type'] === 'peer_to_peer' ? 'bg-purple-100 text-purple-800' : 'bg-green-100 text-green-800'); ?>">
                                    <?php echo ucwords(str_replace('_', ' ', $category['evaluation_type'])); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <span class="text-sm font-medium text-gray-900">
                                        <?php echo number_format($category['average_rating'], 2); ?>
                                    </span>
                                    <div class="ml-2 flex-1 bg-gray-200 rounded-full h-2 w-16">
                                        <div class="bg-seait-orange h-2 rounded-full" 
                                             style="width: <?php echo ($category['average_rating'] / 5) * 100; ?>%"></div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?php echo $category['session_count']; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?php echo $category['response_count']; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 py-1 text-xs rounded-full 
                                    <?php echo $category['average_rating'] >= 4.0 ? 'bg-green-100 text-green-800' : 
                                        ($category['average_rating'] >= 3.0 ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800'); ?>">
                                    <?php echo $category['average_rating'] >= 4.0 ? 'Excellent' : 
                                        ($category['average_rating'] >= 3.0 ? 'Good' : 'Needs Improvement'); ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if (!empty($sentiment_data)): ?>
        <!-- Sentiment Analysis -->
        <div class="bg-white rounded-lg shadow-sm overflow-hidden mb-8">
            <div class="px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-indigo-500 to-indigo-600">
                <h3 class="text-lg font-semibold text-white flex items-center">
                    <i class="fas fa-brain mr-3"></i>Sentiment Analysis of Feedback
                </h3>
            </div>
            <div class="p-6">
                <!-- Overall Sentiment Summary -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                    <?php
                    $total_sentiments = count($sentiment_data);
                    $positive_count = 0;
                    $negative_count = 0;
                    $neutral_count = 0;
                    
                    foreach ($sentiment_data as $data) {
                        switch ($data['sentiment']['sentiment']) {
                            case 'positive':
                                $positive_count++;
                                break;
                            case 'negative':
                                $negative_count++;
                                break;
                            case 'neutral':
                                $neutral_count++;
                                break;
                        }
                    }
                    ?>
                    
                    <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <i class="fas fa-smile text-green-600 text-2xl"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-green-800">Positive Feedback</p>
                                <p class="text-2xl font-bold text-green-900"><?php echo $positive_count; ?></p>
                                <p class="text-sm text-green-600"><?php echo round(($positive_count / $total_sentiments) * 100, 1); ?>%</p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <i class="fas fa-frown text-red-600 text-2xl"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-red-800">Negative Feedback</p>
                                <p class="text-2xl font-bold text-red-900"><?php echo $negative_count; ?></p>
                                <p class="text-sm text-red-600"><?php echo round(($negative_count / $total_sentiments) * 100, 1); ?>%</p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <i class="fas fa-meh text-gray-600 text-2xl"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-800">Neutral Feedback</p>
                                <p class="text-2xl font-bold text-gray-900"><?php echo $neutral_count; ?></p>
                                <p class="text-sm text-gray-600"><?php echo round(($neutral_count / $total_sentiments) * 100, 1); ?>%</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Category-wise Sentiment -->
                <?php if (!empty($category_sentiment)): ?>
                    <div class="mb-8">
                        <h4 class="text-lg font-medium text-gray-900 mb-4">Sentiment by Category</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            <?php foreach ($category_sentiment as $category => $sentiment): ?>
                                <div class="bg-gray-50 rounded-lg p-4">
                                    <h5 class="font-medium text-gray-900 mb-2"><?php echo htmlspecialchars($category); ?></h5>
                                    <div class="space-y-2">
                                        <div class="flex justify-between text-sm">
                                            <span class="text-green-600">Positive</span>
                                            <span class="font-medium"><?php echo round(($sentiment['positive'] / $sentiment['total']) * 100, 1); ?>%</span>
                                        </div>
                                        <div class="flex justify-between text-sm">
                                            <span class="text-red-600">Negative</span>
                                            <span class="font-medium"><?php echo round(($sentiment['negative'] / $sentiment['total']) * 100, 1); ?>%</span>
                                        </div>
                                        <div class="flex justify-between text-sm">
                                            <span class="text-gray-600">Neutral</span>
                                            <span class="font-medium"><?php echo round(($sentiment['neutral'] / $sentiment['total']) * 100, 1); ?>%</span>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Sample Text Responses -->
                <div>
                    <h4 class="text-lg font-medium text-gray-900 mb-4">Sample Text Responses</h4>
                    <div class="space-y-4">
                        <?php 
                        $sample_count = 0;
                        foreach ($sentiment_data as $data): 
                            if ($sample_count >= 5) break;
                        ?>
                            <div class="border border-gray-200 rounded-lg p-4">
                                <div class="flex items-start justify-between mb-2">
                                    <div class="flex items-center">
                                        <span class="sentiment-status-indicator sentiment-status-<?php echo $data['sentiment']['sentiment']; ?>">
                                            <?php echo ucfirst($data['sentiment']['sentiment']); ?>
                                        </span>
                                        <span class="ml-2 text-sm text-gray-500">
                                            <?php echo $data['response']['category_name']; ?> - 
                                            <?php echo $data['response']['semester_name'] . ' (' . $data['response']['academic_year'] . ')'; ?>
                                        </span>
                                    </div>
                                    <span class="text-sm text-gray-500">
                                        <?php echo date('M d, Y', strtotime($data['response']['created_at'])); ?>
                                    </span>
                                </div>
                                <p class="text-gray-700"><?php echo htmlspecialchars($data['response']['text_response']); ?></p>
                            </div>
                        <?php 
                            $sample_count++;
                        endforeach; 
                        ?>
                    </div>
                </div>
            </div>
        </div>
    <?php elseif (!empty($text_responses)): ?>
        <!-- Text Responses (without sentiment analysis) -->
        <div class="bg-white rounded-lg shadow-sm overflow-hidden mb-8">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Recent Text Feedback</h3>
            </div>
            <div class="p-6">
                <div class="space-y-4">
                    <?php 
                    $sample_count = 0;
                    foreach ($text_responses as $response): 
                        if ($sample_count >= 5) break;
                    ?>
                        <div class="border border-gray-200 rounded-lg p-4">
                            <div class="flex items-start justify-between mb-2">
                                <div class="flex items-center">
                                    <span class="text-sm text-gray-500">
                                        <?php echo $response['category_name']; ?> - 
                                        <?php echo $response['semester_name'] . ' (' . $response['academic_year'] . ')'; ?>
                                    </span>
                                </div>
                                <span class="text-sm text-gray-500">
                                    <?php echo date('M d, Y', strtotime($response['created_at'])); ?>
                                </span>
                            </div>
                            <p class="text-gray-700"><?php echo htmlspecialchars($response['text_response']); ?></p>
                        </div>
                    <?php 
                        $sample_count++;
                    endforeach; 
                    ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Training and Seminar Suggestions -->
    <?php if (!empty($training_suggestions)): ?>
        <div class="bg-white rounded-lg shadow-sm overflow-hidden mb-8">
            <div class="px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-yellow-500 to-yellow-600">
                <h3 class="text-lg font-semibold text-white flex items-center">
                    <i class="fas fa-graduation-cap mr-3"></i>Training & Development Suggestions
                </h3>
            </div>
            <div class="p-6">
                <p class="text-gray-600 mb-6">
                    Based on evaluation results, the following training programs are recommended for areas that need improvement:
                </p>
                
                <?php foreach ($training_suggestions as $suggestion): ?>
                    <div class="border border-gray-200 rounded-lg p-4 mb-4">
                        <h4 class="font-medium text-gray-900 mb-2">
                            <?php echo htmlspecialchars($suggestion['category']['category_name']); ?>
                            <span class="text-sm text-gray-500">
                                (Average Rating: <?php echo number_format($suggestion['category']['average_rating'], 2); ?>)
                            </span>
                        </h4>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mt-4">
                            <?php foreach ($suggestion['trainings'] as $training): ?>
                                <div class="bg-gray-50 rounded-lg p-3">
                                    <h5 class="font-medium text-gray-900 text-sm"><?php echo htmlspecialchars($training['title']); ?></h5>
                                    <p class="text-xs text-gray-600 mt-1"><?php echo htmlspecialchars($training['description']); ?></p>
                                    <div class="flex items-center mt-2 text-xs text-gray-500">
                                        <i class="fas fa-calendar mr-1"></i>
                                        <?php echo date('M d, Y', strtotime($training['start_date'])); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

<?php else: ?>
    <!-- No Evaluation Data -->
    <div class="bg-white rounded-lg shadow-sm p-8 text-center">
        <div class="flex items-center justify-center mb-4">
            <i class="fas fa-chart-bar text-gray-400 text-6xl"></i>
        </div>
        <h3 class="text-xl font-semibold text-gray-900 mb-2">No Evaluation Data Available</h3>
        <p class="text-gray-600 mb-6">
            There are currently no completed evaluations for this teacher
            <?php if ($semester_info): ?>
                in <?php echo $semester_info['name'] . ' (' . $semester_info['academic_year'] . ')'; ?>
            <?php endif; ?>.
        </p>
        <a href="view-evaluation.php?faculty_id=<?php echo $faculty_id; ?>" 
           class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-seait-orange hover:bg-orange-600 transition">
            <i class="fas fa-arrow-left mr-2"></i>Back to Overview
        </a>
    </div>
<?php endif; ?>

<style>
.sentiment-status-indicator {
    display: inline-flex;
    align-items: center;
    padding: 0.25rem 0.75rem;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.sentiment-status-positive {
    background-color: #dcfce7;
    color: #166534;
    border: 1px solid #bbf7d0;
}

.sentiment-status-negative {
    background-color: #fee2e2;
    color: #991b1b;
    border: 1px solid #fecaca;
}

.sentiment-status-neutral {
    background-color: #fef3c7;
    color: #92400e;
    border: 1px solid #fde68a;
}
</style> 