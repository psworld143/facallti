<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/sentiment_analysis.php';

// Check if user is logged in and has teacher role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header('Location: ../login.php');
    exit();
}

// Initialize sentiment analysis
$sentimentAnalyzer = new SentimentAnalysis();

// Set page title
$page_title = 'My Evaluation Results';

$message = '';
$message_type = '';

// Check for session messages
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $message_type = $_SESSION['message_type'];
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
}

// Function to get training suggestions based on subcategory and score
function getTrainingSuggestions($subcategory_name, $average_score) {
    global $conn;
    $suggestions = [];

    if ($average_score < 4.0) {
        // Get subcategory ID from name
        $subcategory_query = "SELECT id FROM evaluation_sub_categories WHERE name = ?";
        $subcategory_stmt = mysqli_prepare($conn, $subcategory_query);
        mysqli_stmt_bind_param($subcategory_stmt, "s", $subcategory_name);
        mysqli_stmt_execute($subcategory_stmt);
        $subcategory_result = mysqli_stmt_get_result($subcategory_stmt);
        $subcategory = mysqli_fetch_assoc($subcategory_result);

        if ($subcategory) {
            // Get available trainings for this subcategory
            $trainings_query = "SELECT ts.id, ts.title, ts.description, ts.type, ts.duration_hours, ts.venue, ts.start_date, ts.end_date, ts.cost
                               FROM trainings_seminars ts
                               WHERE ts.sub_category_id = ?
                               AND ts.status = 'published'
                               AND ts.start_date > NOW()
                               ORDER BY ts.start_date ASC
                               LIMIT 5";
            $trainings_stmt = mysqli_prepare($conn, $trainings_query);
            mysqli_stmt_bind_param($trainings_stmt, "i", $subcategory['id']);
            mysqli_stmt_execute($trainings_stmt);
            $trainings_result = mysqli_stmt_get_result($trainings_stmt);

            while ($training = mysqli_fetch_assoc($trainings_result)) {
                $suggestions[] = $training['title'];
            }
        }

        // If no specific trainings found, get general trainings
        if (empty($suggestions)) {
            $general_trainings_query = "SELECT ts.title
                                       FROM trainings_seminars ts
                                       WHERE ts.status = 'published'
                                       AND ts.start_date > NOW()
                                       ORDER BY ts.start_date ASC
                                       LIMIT 3";
            $general_trainings_stmt = mysqli_prepare($conn, $general_trainings_query);
            mysqli_stmt_execute($general_trainings_stmt);
            $general_trainings_result = mysqli_stmt_get_result($general_trainings_stmt);

            while ($training = mysqli_fetch_assoc($general_trainings_result)) {
                $suggestions[] = $training['title'];
            }
        }
    }

    return $suggestions;
}

// Function to get detailed training suggestions with full information
function getDetailedTrainingSuggestions($subcategory_name, $average_score) {
    global $conn;
    $suggestions = [];

    if ($average_score < 4.0) {
        // Get subcategory ID from name
        $subcategory_query = "SELECT id FROM evaluation_sub_categories WHERE name = ?";
        $subcategory_stmt = mysqli_prepare($conn, $subcategory_query);
        mysqli_stmt_bind_param($subcategory_stmt, "s", $subcategory_name);
        mysqli_stmt_execute($subcategory_stmt);
        $subcategory_result = mysqli_stmt_get_result($subcategory_stmt);
        $subcategory = mysqli_fetch_assoc($subcategory_result);

        if ($subcategory) {
            // Get available trainings for this subcategory with full details
            $trainings_query = "SELECT ts.id, ts.title, ts.description, ts.type, ts.duration_hours, ts.venue, ts.start_date, ts.end_date, ts.cost, ts.registration_deadline
                               FROM trainings_seminars ts
                               WHERE ts.sub_category_id = ?
                               AND ts.status = 'published'
                               AND ts.start_date > NOW()
                               ORDER BY ts.start_date ASC
                               LIMIT 5";
            $trainings_stmt = mysqli_prepare($conn, $trainings_query);
            mysqli_stmt_bind_param($trainings_stmt, "i", $subcategory['id']);
            mysqli_stmt_execute($trainings_stmt);
            $trainings_result = mysqli_stmt_get_result($trainings_stmt);

            while ($training = mysqli_fetch_assoc($trainings_result)) {
                $suggestions[] = $training;
            }
        }

        // If no specific trainings found, get general trainings
        if (empty($suggestions)) {
            $general_trainings_query = "SELECT ts.id, ts.title, ts.description, ts.type, ts.duration_hours, ts.venue, ts.start_date, ts.end_date, ts.cost, ts.registration_deadline
                                       FROM trainings_seminars ts
                                       WHERE ts.status = 'published'
                                       AND ts.start_date > NOW()
                                       ORDER BY ts.start_date ASC
                                       LIMIT 3";
            $general_trainings_stmt = mysqli_prepare($conn, $general_trainings_query);
            mysqli_stmt_execute($general_trainings_stmt);
            $general_trainings_result = mysqli_stmt_get_result($general_trainings_stmt);

            while ($training = mysqli_fetch_assoc($general_trainings_result)) {
                $suggestions[] = $training;
            }
        }
    }

    return $suggestions;
}

// Function to calculate sentiment statistics for a subcategory
function getSentimentStatistics($responses, $sentimentAnalyzer) {
    $sentiment_stats = [
        'positive' => 0,
        'negative' => 0,
        'neutral' => 0,
        'total_comments' => 0,
        'positive_percentage' => 0,
        'negative_percentage' => 0,
        'neutral_percentage' => 0,
        'average_sentiment_score' => 0,
        'total_sentiment_score' => 0
    ];

    foreach ($responses as $response) {
        if ($response['question_type'] === 'text' && !empty($response['text_response'])) {
            $sentiment_stats['total_comments']++;

            // Analyze sentiment
            $sentiment = $sentimentAnalyzer->analyzeSentiment($response['text_response']);
            $sentiment_stats['total_sentiment_score'] += $sentiment['score'];

            // Count by sentiment (not emotion)
            switch ($sentiment['sentiment']) {
                case 'positive':
                    $sentiment_stats['positive']++;
                    break;
                case 'negative':
                    $sentiment_stats['negative']++;
                    break;
                case 'neutral':
                    $sentiment_stats['neutral']++;
                    break;
            }
        }
    }

    // Calculate percentages and averages
    if ($sentiment_stats['total_comments'] > 0) {
        $sentiment_stats['positive_percentage'] = round(($sentiment_stats['positive'] / $sentiment_stats['total_comments']) * 100, 1);
        $sentiment_stats['negative_percentage'] = round(($sentiment_stats['negative'] / $sentiment_stats['total_comments']) * 100, 1);
        $sentiment_stats['neutral_percentage'] = round(($sentiment_stats['neutral'] / $sentiment_stats['total_comments']) * 100, 1);
        $sentiment_stats['average_sentiment_score'] = round($sentiment_stats['total_sentiment_score'] / $sentiment_stats['total_comments'], 3);
    }

    return $sentiment_stats;
}

// Get evaluation session ID from URL
$evaluation_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$evaluation_id) {
    $_SESSION['message'] = 'Invalid evaluation ID provided.';
    $_SESSION['message_type'] = 'error';
    header('Location: evaluation-results.php');
    exit();
}

// Get evaluation session details and verify it belongs to the current teacher
$evaluation_query = "SELECT es.*, mec.name as category_name, mec.evaluation_type, mec.description as category_description,
                           s.name as semester_name, s.academic_year
                    FROM evaluation_sessions es
                    JOIN main_evaluation_categories mec ON es.main_category_id = mec.id
                    LEFT JOIN semesters s ON es.semester_id = s.id
                    WHERE es.id = ?";
$evaluation_stmt = mysqli_prepare($conn, $evaluation_query);
mysqli_stmt_bind_param($evaluation_stmt, "i", $evaluation_id);
mysqli_stmt_execute($evaluation_stmt);
$evaluation_result = mysqli_stmt_get_result($evaluation_stmt);
$evaluation = mysqli_fetch_assoc($evaluation_result);

if (!$evaluation) {
    $_SESSION['message'] = 'Evaluation not found.';
    $_SESSION['message_type'] = 'error';
    header('Location: evaluation-results.php');
    exit();
}

// Check if the current user is the evaluatee
if ($evaluation['evaluatee_id'] != $_SESSION['user_id']) {
    $_SESSION['message'] = 'You do not have permission to view this evaluation.';
    $_SESSION['message_type'] = 'error';
    header('Location: evaluation-results.php');
    exit();
}

// Check if the evaluation is completed
if ($evaluation['status'] !== 'completed') {
    $_SESSION['message'] = 'This evaluation is not yet completed.';
    $_SESSION['message_type'] = 'warning';
    header('Location: evaluation-results.php');
    exit();
}

// Get evaluation responses with questions
$responses_query = "SELECT er.*, eq.question_text, eq.question_type, eq.order_number,
                          esc.name as subcategory_name, esc.description as subcategory_description
                   FROM evaluation_responses er
                   JOIN evaluation_questionnaires eq ON er.questionnaire_id = eq.id
                   JOIN evaluation_sub_categories esc ON eq.sub_category_id = esc.id
                   WHERE er.evaluation_session_id = ?
                   ORDER BY esc.order_number, eq.order_number";
$responses_stmt = mysqli_prepare($conn, $responses_query);
mysqli_stmt_bind_param($responses_stmt, "i", $evaluation_id);
mysqli_stmt_execute($responses_stmt);
$responses_result = mysqli_stmt_get_result($responses_stmt);

$responses = [];
$subcategories = [];
while ($response = mysqli_fetch_assoc($responses_result)) {
    $responses[] = $response;

    // Group by subcategory
    $subcategory_id = $response['subcategory_name'];
    if (!isset($subcategories[$subcategory_id])) {
        $subcategories[$subcategory_id] = [
            'name' => $response['subcategory_name'],
            'description' => $response['subcategory_description'],
            'responses' => [],
            'total_rating' => 0,
            'rating_count' => 0,
            'average_score' => 0.0
        ];
    }
    $subcategories[$subcategory_id]['responses'][] = $response;

    // Calculate subcategory statistics
    if ($response['question_type'] === 'rating_1_5' && $response['rating_value'] !== null) {
        $subcategories[$subcategory_id]['total_rating'] = (int)$subcategories[$subcategory_id]['total_rating'] + (int)$response['rating_value'];
        $subcategories[$subcategory_id]['rating_count']++;
    }
}

// Check if there are any responses
if (empty($responses)) {
    $_SESSION['message'] = 'This evaluation has no responses yet. Please contact the administrator.';
    $_SESSION['message_type'] = 'warning';
    header('Location: evaluation-results.php');
    exit();
}

// Calculate average scores for each subcategory
foreach ($subcategories as $key => $subcategory) {
    if ($subcategory['rating_count'] > 0) {
        $subcategories[$key]['average_score'] = (float)$subcategory['total_rating'] / (int)$subcategory['rating_count'];
    }
}

// Calculate overall statistics
$total_questions = count($responses);
$total_rating_questions = 0;
$total_rating = 0;
$min_rating = 5;
$max_rating = 1;

foreach ($responses as $response) {
    if ($response['question_type'] === 'rating_1_5' && $response['rating_value'] !== null) {
        $total_rating_questions++;
        $total_rating += (int)$response['rating_value'];
        $min_rating = min($min_rating, (int)$response['rating_value']);
        $max_rating = max($max_rating, (int)$response['rating_value']);
    }
}

$average_rating = $total_rating_questions > 0 ? $total_rating / $total_rating_questions : 0;

// Calculate overall sentiment statistics
$overall_sentiment_stats = [
    'positive' => 0,
    'negative' => 0,
    'neutral' => 0,
    'total_comments' => 0,
    'positive_percentage' => 0,
    'negative_percentage' => 0,
    'neutral_percentage' => 0,
    'average_sentiment_score' => 0,
    'total_sentiment_score' => 0
];

foreach ($responses as $response) {
    if ($response['question_type'] === 'text' && !empty($response['text_response'])) {
        $overall_sentiment_stats['total_comments']++;

        // Analyze sentiment
        $sentiment = $sentimentAnalyzer->analyzeSentiment($response['text_response']);
        $overall_sentiment_stats['total_sentiment_score'] += $sentiment['score'];

        // Count by sentiment
        switch ($sentiment['sentiment']) {
            case 'positive':
                $overall_sentiment_stats['positive']++;
                break;
            case 'negative':
                $overall_sentiment_stats['negative']++;
                break;
            case 'neutral':
                $overall_sentiment_stats['neutral']++;
                break;
        }
    }
}

// Calculate overall sentiment percentages and averages
if ($overall_sentiment_stats['total_comments'] > 0) {
    $overall_sentiment_stats['positive_percentage'] = round(($overall_sentiment_stats['positive'] / $overall_sentiment_stats['total_comments']) * 100, 1);
    $overall_sentiment_stats['negative_percentage'] = round(($overall_sentiment_stats['negative'] / $overall_sentiment_stats['total_comments']) * 100, 1);
    $overall_sentiment_stats['neutral_percentage'] = round(($overall_sentiment_stats['neutral'] / $overall_sentiment_stats['total_comments']) * 100, 1);
    $overall_sentiment_stats['average_sentiment_score'] = round($overall_sentiment_stats['total_sentiment_score'] / $overall_sentiment_stats['total_comments'], 3);
}

// Include the shared header
$sidebar_context = 'main';
include 'includes/unified-header.php';
?>

<div class="mb-6 sm:mb-8">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-seait-dark mb-2">My Evaluation Results</h1>
            <p class="text-sm sm:text-base text-gray-600">
                Detailed evaluation results for <?php echo htmlspecialchars($evaluation['category_name']); ?>
            </p>
        </div>
        <div class="flex space-x-2">
            <a href="evaluation-results.php" class="bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600 transition">
                <i class="fas fa-arrow-left mr-2"></i>Back to My Results
            </a>
            <button onclick="window.print()" class="bg-seait-orange text-white px-4 py-2 rounded-lg hover:bg-orange-600 transition">
                <i class="fas fa-print mr-2"></i>Print Report
            </button>
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
<div class="bg-white rounded-lg shadow-md overflow-hidden mb-8">
    <div class="px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-seait-orange to-orange-600">
        <h2 class="text-lg font-semibold text-white flex items-center">
            <i class="fas fa-clipboard-check mr-3"></i>Evaluation Overview
        </h2>
    </div>
    <div class="p-6">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                <p class="text-gray-900 font-medium"><?php echo htmlspecialchars($evaluation['category_name']); ?></p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Evaluation Type</label>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
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
                <label class="block text-sm font-medium text-gray-700 mb-1">Semester</label>
                <p class="text-gray-900"><?php echo htmlspecialchars($evaluation['semester_name'] . ' - ' . $evaluation['academic_year']); ?></p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Evaluation Date</label>
                <p class="text-gray-900"><?php echo date('M d, Y', strtotime($evaluation['evaluation_date'])); ?></p>
            </div>
        </div>

        <?php if ($evaluation['category_description']): ?>
        <div class="mt-6">
            <label class="block text-sm font-medium text-gray-700 mb-2">Category Description</label>
            <p class="text-gray-700 bg-gray-50 p-4 rounded-lg"><?php echo htmlspecialchars($evaluation['category_description']); ?></p>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Overall Statistics -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    <div class="bg-white rounded-lg shadow-md p-6">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                <i class="fas fa-chart-line text-xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-600">Average Rating</p>
                <p class="text-2xl font-bold text-gray-900">
                    <?php echo number_format($average_rating, 2); ?>/5.00
                </p>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow-md p-6">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-green-100 text-green-600">
                <i class="fas fa-question-circle text-xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-600">Total Questions</p>
                <p class="text-2xl font-bold text-gray-900"><?php echo $total_questions; ?></p>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow-md p-6">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-purple-100 text-purple-600">
                <i class="fas fa-star text-xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-600">Rating Questions</p>
                <p class="text-2xl font-bold text-gray-900"><?php echo $total_rating_questions; ?></p>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow-md p-6">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-orange-100 text-orange-600">
                <i class="fas fa-chart-bar text-xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-600">Rating Range</p>
                <p class="text-2xl font-bold text-gray-900">
                    <?php echo $min_rating; ?> - <?php echo $max_rating; ?>
                </p>
            </div>
        </div>
    </div>
</div>

<!-- Overall Sentiment Statistics -->
<?php if ($overall_sentiment_stats['total_comments'] > 0): ?>
<div class="bg-white rounded-lg shadow-md overflow-hidden mb-8">
    <div class="px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-blue-500 to-blue-600">
        <h2 class="text-lg font-semibold text-white flex items-center">
            <i class="fas fa-chart-pie mr-3"></i>Overall Student Feedback Sentiment Analysis
        </h2>
    </div>
    <div class="p-6">
        <!-- Sentiment Overview Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
            <div class="bg-green-50 border border-green-200 rounded-lg p-4 text-center">
                <div class="flex items-center justify-center mb-2">
                    <i class="fas fa-smile text-green-600 text-3xl"></i>
                </div>
                <div class="text-3xl font-bold text-green-600"><?php echo $overall_sentiment_stats['positive_percentage']; ?>%</div>
                <div class="text-sm font-medium text-green-800">Positive</div>
                <div class="text-xs text-green-600"><?php echo $overall_sentiment_stats['positive']; ?> of <?php echo $overall_sentiment_stats['total_comments']; ?> comments</div>
            </div>

            <div class="bg-red-50 border border-red-200 rounded-lg p-4 text-center">
                <div class="flex items-center justify-center mb-2">
                    <i class="fas fa-frown text-red-600 text-3xl"></i>
                </div>
                <div class="text-3xl font-bold text-red-600"><?php echo $overall_sentiment_stats['negative_percentage']; ?>%</div>
                <div class="text-sm font-medium text-red-800">Negative</div>
                <div class="text-xs text-red-600"><?php echo $overall_sentiment_stats['negative']; ?> of <?php echo $overall_sentiment_stats['total_comments']; ?> comments</div>
            </div>

            <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 text-center">
                <div class="flex items-center justify-center mb-2">
                    <i class="fas fa-meh text-gray-600 text-3xl"></i>
                </div>
                <div class="text-3xl font-bold text-gray-600"><?php echo $overall_sentiment_stats['neutral_percentage']; ?>%</div>
                <div class="text-sm font-medium text-gray-800">Neutral</div>
                <div class="text-xs text-gray-600"><?php echo $overall_sentiment_stats['neutral']; ?> of <?php echo $overall_sentiment_stats['total_comments']; ?> comments</div>
            </div>

            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 text-center">
                <div class="flex items-center justify-center mb-2">
                    <i class="fas fa-chart-line text-blue-600 text-3xl"></i>
                </div>
                <div class="text-3xl font-bold text-blue-600"><?php echo $overall_sentiment_stats['average_sentiment_score']; ?></div>
                <div class="text-sm font-medium text-blue-800">Avg. Sentiment</div>
                <div class="text-xs text-blue-600">Compound Score</div>
            </div>
        </div>

        <!-- Sentiment Progress Bars -->
        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <i class="fas fa-smile text-green-600 mr-2"></i>
                    <span class="text-sm font-medium text-gray-700">Positive Sentiment</span>
                </div>
                <span class="text-sm font-medium text-gray-900"><?php echo $overall_sentiment_stats['positive_percentage']; ?>%</span>
            </div>
            <div class="w-full bg-gray-200 rounded-full h-3">
                <div class="bg-green-600 h-3 rounded-full" style="width: <?php echo $overall_sentiment_stats['positive_percentage']; ?>%"></div>
            </div>

            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <i class="fas fa-meh text-gray-600 mr-2"></i>
                    <span class="text-sm font-medium text-gray-700">Neutral Sentiment</span>
                </div>
                <span class="text-sm font-medium text-gray-900"><?php echo $overall_sentiment_stats['neutral_percentage']; ?>%</span>
            </div>
            <div class="w-full bg-gray-200 rounded-full h-3">
                <div class="bg-gray-600 h-3 rounded-full" style="width: <?php echo $overall_sentiment_stats['neutral_percentage']; ?>%"></div>
            </div>

            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <i class="fas fa-frown text-red-600 mr-2"></i>
                    <span class="text-sm font-medium text-gray-700">Negative Sentiment</span>
                </div>
                <span class="text-sm font-medium text-gray-900"><?php echo $overall_sentiment_stats['negative_percentage']; ?>%</span>
            </div>
            <div class="w-full bg-gray-200 rounded-full h-3">
                <div class="bg-red-600 h-3 rounded-full" style="width: <?php echo $overall_sentiment_stats['negative_percentage']; ?>%"></div>
            </div>
        </div>

        <!-- Overall Sentiment Insights -->
        <div class="mt-6 p-4 bg-blue-100 rounded-lg">
            <h5 class="text-sm font-medium text-blue-800 mb-2">Overall Sentiment Insights:</h5>
            <ul class="text-sm text-blue-700 space-y-1">
                <li>• Total student comments analyzed: <strong><?php echo $overall_sentiment_stats['total_comments']; ?></strong></li>
                <li>• Overall sentiment trend:
                    <strong>
                        <?php
                        if ($overall_sentiment_stats['positive_percentage'] > 50) {
                            echo 'Positive';
                        } elseif ($overall_sentiment_stats['negative_percentage'] > 50) {
                            echo 'Negative';
                        } else {
                            echo 'Mixed/Neutral';
                        }
                        ?>
                    </strong>
                </li>
                <li>• Average sentiment score: <strong><?php echo $overall_sentiment_stats['average_sentiment_score']; ?></strong>
                    (Range: -1.0 to +1.0, where +1.0 is very positive and -1.0 is very negative)
                </li>
                <li>• Sentiment distribution: <strong><?php echo $overall_sentiment_stats['positive_percentage']; ?>% positive</strong>,
                    <strong><?php echo $overall_sentiment_stats['neutral_percentage']; ?>% neutral</strong>,
                    <strong><?php echo $overall_sentiment_stats['negative_percentage']; ?>% negative</strong>
                </li>
            </ul>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Evaluation Results by Subcategory -->
<div class="bg-white rounded-lg shadow-md overflow-hidden mb-8">
    <div class="px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-green-500 to-green-600">
        <h2 class="text-lg font-semibold text-white flex items-center">
            <i class="fas fa-chart-bar mr-3"></i>Detailed Evaluation Results by Category
        </h2>
    </div>
    <div class="p-6">
        <?php if (!empty($subcategories)): ?>

            <!-- Tab Navigation -->
            <div class="border-b border-gray-200 mb-6">
                <nav class="-mb-px flex space-x-8 overflow-x-auto" aria-label="Tabs">
                    <?php $first_tab = true; $tab_counter = 0; ?>
                    <?php foreach ($subcategories as $key => $subcategory): ?>
                        <button onclick="showTab(<?php echo $tab_counter; ?>)"
                                class="tab-button whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm <?php echo $first_tab ? 'border-seait-orange text-seait-orange' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?>"
                                id="tab-button-<?php echo $tab_counter; ?>">
                            <div class="flex items-center">
                                <span><?php echo htmlspecialchars($subcategory['name']); ?></span>
                                <?php if ($subcategory['average_score'] > 0): ?>
                                    <span class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                        <?php echo $subcategory['average_score'] >= 4.0 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                        <?php echo number_format($subcategory['average_score'], 1); ?>/5.0
                                    </span>
                                <?php endif; ?>
                            </div>
                        </button>
                        <?php $first_tab = false; $tab_counter++; ?>
                    <?php endforeach; ?>
                </nav>
            </div>

            <!-- Tab Content -->
            <?php $tab_counter = 0; ?>
            <?php foreach ($subcategories as $key => $subcategory): ?>
                <div class="tab-content <?php echo $tab_counter === 0 ? 'block' : 'hidden'; ?>" id="tab-content-<?php echo $tab_counter; ?>">
                    <!-- Subcategory Header -->
                    <div class="mb-6">
                        <h3 class="text-xl font-semibold text-gray-900 mb-2">
                            <?php echo htmlspecialchars($subcategory['name']); ?>
                        </h3>
                        <?php if ($subcategory['description']): ?>
                            <p class="text-gray-600 mb-4"><?php echo htmlspecialchars($subcategory['description']); ?></p>
                        <?php endif; ?>

                        <!-- Subcategory Statistics -->
                        <?php if ($subcategory['average_score'] > 0): ?>
                            <div class="bg-gray-50 rounded-lg p-4 mb-6">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <h4 class="text-lg font-medium text-gray-900">Category Performance</h4>
                                        <p class="text-sm text-gray-600">
                                            Average Score: <span class="font-semibold"><?php echo number_format($subcategory['average_score'], 2); ?>/5.00</span>
                                        </p>
                                        <p class="text-sm text-gray-600">
                                            Total Questions: <?php echo $subcategory['rating_count']; ?>
                                        </p>
                                    </div>
                                    <div class="text-right">
                                        <div class="flex items-center">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <i class="fas fa-star <?php echo $i <= $subcategory['average_score'] ? 'text-yellow-400' : 'text-gray-300'; ?> mr-1"></i>
                                            <?php endfor; ?>
                                        </div>
                                        <p class="text-sm text-gray-600 mt-1">
                                            <?php
                                            if ($subcategory['average_score'] >= 4.5) {
                                                echo 'Excellent Performance';
                                            } elseif ($subcategory['average_score'] >= 4.0) {
                                                echo 'Very Good Performance';
                                            } elseif ($subcategory['average_score'] >= 3.0) {
                                                echo 'Good Performance';
                                            } else {
                                                echo 'Needs Improvement';
                                            }
                                            ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Training Suggestions (if score < 4.0) -->
                    <?php
                    $detailed_training_suggestions = getDetailedTrainingSuggestions($subcategory['name'], $subcategory['average_score']);
                    if (!empty($detailed_training_suggestions)):
                    ?>
                        <div class="bg-orange-50 border border-orange-200 rounded-lg p-6 mb-6">
                            <div class="flex items-start">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-lightbulb text-orange-600 text-xl"></i>
                                </div>
                                <div class="ml-3 flex-1">
                                    <h4 class="text-lg font-medium text-orange-800 mb-2">
                                        Suggested Training & Development Opportunities
                                    </h4>
                                    <p class="text-orange-700 mb-4">
                                        Based on your evaluation results, we recommend the following training programs to enhance your skills in this area:
                                    </p>
                                    <div class="space-y-4">
                                        <?php foreach ($detailed_training_suggestions as $training): ?>
                                            <div class="bg-white border border-orange-200 rounded-lg p-4">
                                                <div class="flex items-start justify-between">
                                                    <div class="flex-1">
                                                        <h5 class="font-semibold text-gray-900 mb-2">
                                                            <i class="fas fa-check-circle text-orange-600 mr-2"></i>
                                                            <?php echo htmlspecialchars($training['title']); ?>
                                                        </h5>
                                                        <?php if ($training['description']): ?>
                                                            <p class="text-gray-600 text-sm mb-3"><?php echo htmlspecialchars($training['description']); ?></p>
                                                        <?php endif; ?>
                                                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3 text-sm">
                                                            <div class="flex items-center">
                                                                <i class="fas fa-calendar-alt text-gray-400 mr-2"></i>
                                                                <span class="text-gray-700">
                                                                    <?php echo date('M d, Y', strtotime($training['start_date'])); ?>
                                                                </span>
                                                            </div>
                                                            <?php if ($training['duration_hours']): ?>
                                                                <div class="flex items-center">
                                                                    <i class="fas fa-clock text-gray-400 mr-2"></i>
                                                                    <span class="text-gray-700">
                                                                        <?php echo $training['duration_hours']; ?> hours
                                                                    </span>
                                                                </div>
                                                            <?php endif; ?>
                                                            <?php if ($training['venue']): ?>
                                                                <div class="flex items-center">
                                                                    <i class="fas fa-map-marker-alt text-gray-400 mr-2"></i>
                                                                    <span class="text-gray-700"><?php echo htmlspecialchars($training['venue']); ?></span>
                                                                </div>
                                                            <?php endif; ?>
                                                            <div class="flex items-center">
                                                                <i class="fas fa-tag text-gray-400 mr-2"></i>
                                                                <span class="text-gray-700"><?php echo ucfirst($training['type']); ?></span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="ml-4 text-right">
                                                        <?php if ($training['cost'] > 0): ?>
                                                            <div class="text-sm font-medium text-gray-900">
                                                                ₱<?php echo number_format($training['cost'], 2); ?>
                                                            </div>
                                                        <?php else: ?>
                                                            <div class="text-sm font-medium text-green-600">Free</div>
                                                        <?php endif; ?>
                                                        <div class="mt-2">
                                                            <a href="view-training.php?id=<?php echo $training['id']; ?>"
                                                               class="inline-flex items-center px-3 py-1 text-xs font-medium text-orange-700 bg-orange-100 border border-orange-200 rounded hover:bg-orange-200 transition">
                                                                <i class="fas fa-external-link-alt mr-1"></i>
                                                                View Details
                                                            </a>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Sentiment Analysis Statistics -->
                    <?php
                    $sentiment_stats = getSentimentStatistics($subcategory['responses'], $sentimentAnalyzer);
                    if ($sentiment_stats['total_comments'] > 0):
                    ?>
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-6 mb-6">
                            <div class="flex items-start">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-chart-pie text-blue-600 text-xl"></i>
                                </div>
                                <div class="ml-3 flex-1">
                                    <h4 class="text-lg font-medium text-blue-800 mb-2">
                                        Student Feedback Sentiment Analysis
                                    </h4>
                                    <p class="text-blue-700 mb-4">
                                        Analysis of student comments and feedback sentiment for this category:
                                    </p>

                                    <!-- Sentiment Overview -->
                                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                                        <div class="bg-white border border-blue-200 rounded-lg p-4 text-center">
                                            <div class="flex items-center justify-center mb-2">
                                                <i class="fas fa-smile text-green-600 text-2xl"></i>
                                            </div>
                                            <div class="text-2xl font-bold text-green-600"><?php echo $sentiment_stats['positive_percentage']; ?>%</div>
                                            <div class="text-sm text-gray-600">Positive</div>
                                            <div class="text-xs text-gray-500"><?php echo $sentiment_stats['positive']; ?> of <?php echo $sentiment_stats['total_comments']; ?> comments</div>
                                        </div>

                                        <div class="bg-white border border-blue-200 rounded-lg p-4 text-center">
                                            <div class="flex items-center justify-center mb-2">
                                                <i class="fas fa-frown text-red-600 text-2xl"></i>
                                            </div>
                                            <div class="text-2xl font-bold text-red-600"><?php echo $sentiment_stats['negative_percentage']; ?>%</div>
                                            <div class="text-sm text-gray-600">Negative</div>
                                            <div class="text-xs text-gray-500"><?php echo $sentiment_stats['negative']; ?> of <?php echo $sentiment_stats['total_comments']; ?> comments</div>
                                        </div>

                                        <div class="bg-white border border-blue-200 rounded-lg p-4 text-center">
                                            <div class="flex items-center justify-center mb-2">
                                                <i class="fas fa-meh text-gray-600 text-2xl"></i>
                                            </div>
                                            <div class="text-2xl font-bold text-gray-600"><?php echo $sentiment_stats['neutral_percentage']; ?>%</div>
                                            <div class="text-sm text-gray-600">Neutral</div>
                                            <div class="text-xs text-gray-500"><?php echo $sentiment_stats['neutral']; ?> of <?php echo $sentiment_stats['total_comments']; ?> comments</div>
                                        </div>

                                        <div class="bg-white border border-blue-200 rounded-lg p-4 text-center">
                                            <div class="flex items-center justify-center mb-2">
                                                <i class="fas fa-chart-line text-blue-600 text-2xl"></i>
                                            </div>
                                            <div class="text-2xl font-bold text-blue-600"><?php echo $sentiment_stats['average_sentiment_score']; ?></div>
                                            <div class="text-sm text-gray-600">Avg. Sentiment</div>
                                            <div class="text-xs text-gray-500">Compound Score</div>
                                        </div>
                                    </div>

                                    <!-- Sentiment Progress Bars -->
                                    <div class="space-y-3">
                                        <div class="flex items-center justify-between">
                                            <div class="flex items-center">
                                                <i class="fas fa-smile text-green-600 mr-2"></i>
                                                <span class="text-sm font-medium text-gray-700">Positive Sentiment</span>
                                            </div>
                                            <span class="text-sm font-medium text-gray-900"><?php echo $sentiment_stats['positive_percentage']; ?>%</span>
                                        </div>
                                        <div class="w-full bg-gray-200 rounded-full h-2">
                                            <div class="bg-green-600 h-2 rounded-full" style="width: <?php echo $sentiment_stats['positive_percentage']; ?>%"></div>
                                        </div>

                                        <div class="flex items-center justify-between">
                                            <div class="flex items-center">
                                                <i class="fas fa-meh text-gray-600 mr-2"></i>
                                                <span class="text-sm font-medium text-gray-700">Neutral Sentiment</span>
                                            </div>
                                            <span class="text-sm font-medium text-gray-900"><?php echo $sentiment_stats['neutral_percentage']; ?>%</span>
                                        </div>
                                        <div class="w-full bg-gray-200 rounded-full h-2">
                                            <div class="bg-gray-600 h-2 rounded-full" style="width: <?php echo $sentiment_stats['neutral_percentage']; ?>%"></div>
                                        </div>

                                        <div class="flex items-center justify-between">
                                            <div class="flex items-center">
                                                <i class="fas fa-frown text-red-600 mr-2"></i>
                                                <span class="text-sm font-medium text-gray-700">Negative Sentiment</span>
                                            </div>
                                            <span class="text-sm font-medium text-gray-900"><?php echo $sentiment_stats['negative_percentage']; ?>%</span>
                                        </div>
                                        <div class="w-full bg-gray-200 rounded-full h-2">
                                            <div class="bg-red-600 h-2 rounded-full" style="width: <?php echo $sentiment_stats['negative_percentage']; ?>%"></div>
                                        </div>
                                    </div>

                                    <!-- Sentiment Insights -->
                                    <div class="mt-4 p-3 bg-blue-100 rounded-lg">
                                        <h5 class="text-sm font-medium text-blue-800 mb-2">Sentiment Insights:</h5>
                                        <ul class="text-sm text-blue-700 space-y-1">
                                            <li>• Total student comments analyzed: <strong><?php echo $sentiment_stats['total_comments']; ?></strong></li>
                                            <li>• Overall sentiment trend:
                                                <strong>
                                                    <?php
                                                    if ($sentiment_stats['positive_percentage'] > 50) {
                                                        echo 'Positive';
                                                    } elseif ($sentiment_stats['negative_percentage'] > 50) {
                                                        echo 'Negative';
                                                    } else {
                                                        echo 'Mixed/Neutral';
                                                    }
                                                    ?>
                                                </strong>
                                            </li>
                                            <li>• Average sentiment score: <strong><?php echo $sentiment_stats['average_sentiment_score']; ?></strong>
                                                (Range: -1.0 to +1.0)
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Individual Questions -->
                    <div class="space-y-4">
                        <h4 class="text-lg font-medium text-gray-900 mb-4">Individual Question Results</h4>
                        <?php foreach ($subcategory['responses'] as $response): ?>
                            <div class="border border-gray-200 rounded-lg p-4">
                                <div class="flex justify-between items-start mb-3">
                                    <div class="flex-1">
                                        <h5 class="font-medium text-gray-900 mb-2">
                                            <?php echo htmlspecialchars($response['question_text']); ?>
                                        </h5>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                            <?php echo $response['question_type'] === 'rating_1_5' ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800'; ?>">
                                            <?php echo $response['question_type'] === 'rating_1_5' ? 'Rating (1-5)' : ucfirst($response['question_type']); ?> Question
                                        </span>
                                    </div>
                                </div>

                                <div class="mt-4">
                                    <?php if ($response['question_type'] === 'rating_1_5' && $response['rating_value'] !== null): ?>
                                        <div class="flex items-center justify-between">
                                            <div class="flex items-center">
                                                <span class="text-lg font-bold text-seait-orange mr-3">
                                                    <?php echo $response['rating_value']; ?>/5
                                                </span>
                                                <div class="flex items-center">
                                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                                        <i class="fas fa-star <?php echo $i <= $response['rating_value'] ? 'text-yellow-400' : 'text-gray-300'; ?> mr-1"></i>
                                                    <?php endfor; ?>
                                                </div>
                                            </div>
                                            <div class="text-sm text-gray-600">
                                                Question #<?php echo $response['order_number']; ?>
                                            </div>
                                        </div>
                                    <?php elseif ($response['question_type'] === 'text' && $response['text_response']): ?>
                                        <?php
                                        // Analyze sentiment using VADER
                                        $sentiment = $sentimentAnalyzer->analyzeSentiment($response['text_response']);
                                        $emotion = $sentiment['emotion'];
                                        $emotion_color = $sentiment['color'];
                                        $emotion_bg = $sentiment['bg'];
                                        $emotion_border = $sentiment['border'];
                                        $emotion_icon = $sentiment['icon'];
                                        ?>
                                        <div class="<?php echo $emotion_bg; ?> border <?php echo $emotion_border; ?> p-4 rounded-lg">
                                            <div class="flex items-start">
                                                <div class="flex-shrink-0">
                                                    <i class="fas <?php echo $emotion_icon; ?> text-<?php echo $emotion_color; ?>-600 mt-1 text-lg"></i>
                                                </div>
                                                <div class="ml-3">
                                                    <h6 class="text-sm font-medium text-<?php echo $emotion_color; ?>-800 mb-2">
                                                        Student Comment
                                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-semibold bg-<?php echo $emotion_color; ?>-100 text-<?php echo $emotion_color; ?>-800 ml-3 border border-<?php echo $emotion_color; ?>-200">
                                                            <i class="fas <?php echo $emotion_icon; ?> mr-1"></i>
                                                            <?php echo ucfirst($emotion); ?>
                                                        </span>
                                                    </h6>
                                                    <p class="text-<?php echo $emotion_color; ?>-700 text-sm leading-relaxed"><?php echo nl2br(html_entity_decode($response['text_response'])); ?></p>
                                                </div>
                                            </div>
                                        </div>
                                    <?php elseif ($response['question_type'] === 'multiple_choice' && $response['multiple_choice_response']): ?>
                                        <div class="bg-gray-50 p-4 rounded-lg">
                                            <p class="text-gray-700 font-medium">Selected: <?php echo htmlspecialchars($response['multiple_choice_response']); ?></p>
                                        </div>
                                    <?php else: ?>
                                        <div class="bg-gray-50 p-4 rounded-lg">
                                            <p class="text-gray-500 italic">No response provided</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php $tab_counter++; ?>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="text-center py-8">
                <i class="fas fa-clipboard-list text-gray-300 text-4xl mb-4"></i>
                <p class="text-gray-500">No evaluation responses found.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- JavaScript for Tab Functionality -->
<script>
function showTab(tabIndex) {
    // Hide all tab contents
    const tabContents = document.querySelectorAll('.tab-content');
    tabContents.forEach(content => {
        content.classList.add('hidden');
    });

    // Remove active state from all tab buttons
    const tabButtons = document.querySelectorAll('.tab-button');
    tabButtons.forEach(button => {
        button.classList.remove('border-seait-orange', 'text-seait-orange');
        button.classList.add('border-transparent', 'text-gray-500');
    });

    // Show selected tab content
    const selectedContent = document.getElementById('tab-content-' + tabIndex);
    if (selectedContent) {
        selectedContent.classList.remove('hidden');
        selectedContent.classList.add('block');
    }

    // Add active state to selected tab button
    const selectedButton = document.getElementById('tab-button-' + tabIndex);
    if (selectedButton) {
        selectedButton.classList.remove('border-transparent', 'text-gray-500');
        selectedButton.classList.add('border-seait-orange', 'text-seait-orange');
    }
}
</script>

<style>
/* Additional styles for better tab appearance */
.tab-button {
    transition: all 0.2s ease-in-out;
}

.tab-button:hover {
    background-color: rgba(249, 115, 22, 0.05);
}

.tab-content {
    animation: fadeIn 0.3s ease-in-out;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Responsive tab navigation */
@media (max-width: 768px) {
    .tab-button {
        font-size: 0.875rem;
        padding: 0.5rem 0.75rem;
    }
}
</style>

<?php
// Include the shared footer
include 'includes/footer.php';
?>