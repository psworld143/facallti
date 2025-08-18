<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/sentiment_analysis.php';

// Initialize sentiment analyzer
$sentimentAnalyzer = new SentimentAnalysis();

// Check if user is logged in and has appropriate role
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['guidance_officer', 'head', 'teacher'])) {
    header('Location: ../index.php');
    exit();
}

// Set page title
$page_title = $_SESSION['role'] === 'teacher' ? 'My Evaluation Results' : 'Teacher Evaluation Results';

$message = '';
$message_type = '';

// Check for session messages
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $message_type = $_SESSION['message_type'];
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
}

// Get teacher ID from URL - support both user_id and faculty_id
$teacher_id = 0;
$is_faculty_member = false;

if (isset($_GET['faculty_id'])) {
    $faculty_id = (int)$_GET['faculty_id'];
    $is_faculty_member = true;

    if (!$faculty_id) {
        $_SESSION['message'] = 'Invalid faculty ID provided.';
        $_SESSION['message_type'] = 'error';
        header('Location: ' . ($_SESSION['role'] === 'teacher' ? '../faculty/evaluation-results.php' : 'teachers.php'));
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
        header('Location: ' . ($_SESSION['role'] === 'teacher' ? '../faculty/evaluation-results.php' : 'teachers.php'));
        exit();
    }

    // For faculty members, we'll use their faculty_id for queries
    $teacher_id = $faculty_id;

} else {
    // Original logic for user_id
    $teacher_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

    if (!$teacher_id) {
        $_SESSION['message'] = 'Invalid teacher ID provided.';
        $_SESSION['message_type'] = 'error';
        header('Location: ' . ($_SESSION['role'] === 'teacher' ? '../faculty/evaluation-results.php' : 'teachers.php'));
        exit();
    }

    // Get teacher details from users table with faculty information
    $teacher_query = "SELECT u.id, u.first_name, u.last_name, u.email, u.role,
                            COALESCE(f.department, 'N/A') as department,
                            COALESCE(f.position, 'N/A') as position,
                            COALESCE(f.is_active, 1) as status,
                            f.id as faculty_id
                     FROM users u
                     LEFT JOIN faculty f ON u.email = f.email
                     WHERE u.id = ? AND u.role IN ('teacher', 'head')";
    $teacher_stmt = mysqli_prepare($conn, $teacher_query);
    mysqli_stmt_bind_param($teacher_stmt, "i", $teacher_id);
    mysqli_stmt_execute($teacher_stmt);
    $teacher_result = mysqli_stmt_get_result($teacher_stmt);
    $teacher = mysqli_fetch_assoc($teacher_result);

    if (!$teacher) {
        $_SESSION['message'] = 'Teacher not found.';
        $_SESSION['message_type'] = 'error';
        header('Location: ' . ($_SESSION['role'] === 'teacher' ? '../faculty/evaluation-results.php' : 'teachers.php'));
        exit();
    }

    // Check if this teacher has a faculty record
    if ($teacher['faculty_id']) {
        $is_faculty_member = true;
    }
}

// Security check: If user is a teacher, they can only view their own evaluations
if ($_SESSION['role'] === 'teacher') {
    $can_view = false;

    if ($is_faculty_member) {
        // For faculty members, check if they're viewing their own evaluations
        // Faculty members have their ID stored in $_SESSION['faculty_id'] or we can check by email
        if (isset($_SESSION['faculty_id']) && $_SESSION['faculty_id'] == $teacher_id) {
            $can_view = true;
        } elseif (isset($_SESSION['username']) && $_SESSION['username'] == $teacher['email']) {
            $can_view = true;
        }
    } else {
        // For regular users, check user_id
        if ($_SESSION['user_id'] == $teacher_id) {
            $can_view = true;
        }
    }

    if (!$can_view) {
        $_SESSION['message'] = 'You can only view your own evaluation results.';
        $_SESSION['message_type'] = 'error';
        header('Location: ../faculty/evaluation-results.php');
        exit();
    }
}

// Get all semesters for this teacher
if ($is_faculty_member) {
    // For faculty members, check evaluations by faculty_id (since they might not have user records)
    $semesters_query = "SELECT DISTINCT s.id, s.name, s.academic_year, s.start_date, s.end_date
                       FROM evaluation_sessions es
                       JOIN semesters s ON es.semester_id = s.id
                       WHERE es.evaluatee_id = ? AND es.status = 'completed'
                       ORDER BY s.start_date DESC";
    $semesters_stmt = mysqli_prepare($conn, $semesters_query);
    mysqli_stmt_bind_param($semesters_stmt, "i", $teacher_id);
} else {
    // Original query for users table teachers
    $semesters_query = "SELECT DISTINCT s.id, s.name, s.academic_year, s.start_date, s.end_date
                       FROM evaluation_sessions es
                       JOIN semesters s ON es.semester_id = s.id
                       WHERE es.evaluatee_id = ? AND es.status = 'completed'
                       ORDER BY s.start_date DESC";
    $semesters_stmt = mysqli_prepare($conn, $semesters_query);
    mysqli_stmt_bind_param($semesters_stmt, "i", $teacher_id);
}
mysqli_stmt_execute($semesters_stmt);
$semesters_result = mysqli_stmt_get_result($semesters_stmt);

$semesters = [];
while ($semester = mysqli_fetch_assoc($semesters_result)) {
    $semesters[] = $semester;
}

// If no semesters found, try alternative query structure
if (empty($semesters)) {
    // Try querying by evaluation_date instead of semester_id
    if ($is_faculty_member) {
        $semesters_query = "SELECT DISTINCT s.id, s.name, s.academic_year, s.start_date, s.end_date
                           FROM evaluation_sessions es
                           JOIN semesters s ON es.evaluation_date BETWEEN s.start_date AND s.end_date
                           WHERE es.evaluatee_id = ? AND es.status = 'completed'
                           ORDER BY s.start_date DESC";
        $semesters_stmt = mysqli_prepare($conn, $semesters_query);
        mysqli_stmt_bind_param($semesters_stmt, "i", $teacher_id);
    } else {
        $semesters_query = "SELECT DISTINCT s.id, s.name, s.academic_year, s.start_date, s.end_date
                           FROM evaluation_sessions es
                           JOIN semesters s ON es.evaluation_date BETWEEN s.start_date AND s.end_date
                           WHERE es.evaluatee_id = ? AND es.status = 'completed'
                           ORDER BY s.start_date DESC";
        $semesters_stmt = mysqli_prepare($conn, $semesters_query);
        mysqli_stmt_bind_param($semesters_stmt, "i", $teacher_id);
    }
    mysqli_stmt_execute($semesters_stmt);
    $semesters_result = mysqli_stmt_get_result($semesters_stmt);

    while ($semester = mysqli_fetch_assoc($semesters_result)) {
        $semesters[] = $semester;
    }
}

// Get evaluation categories and their statistics
if ($is_faculty_member) {
    // For faculty members, check evaluations by faculty_id
    $categories_query = "SELECT
        mec.id as category_id,
        mec.name as category_name,
        mec.evaluation_type,
        mec.description as category_description,
        COUNT(DISTINCT es.id) as total_evaluations,
        AVG(er.rating_value) as average_rating,
        MIN(er.rating_value) as min_rating,
        MAX(er.rating_value) as max_rating,
        STDDEV(er.rating_value) as rating_stddev
    FROM main_evaluation_categories mec
    LEFT JOIN evaluation_sessions es ON mec.id = es.main_category_id
        AND es.evaluatee_id = ?
        AND es.status = 'completed'
    LEFT JOIN evaluation_responses er ON es.id = er.evaluation_session_id AND er.rating_value IS NOT NULL
    GROUP BY mec.id, mec.name, mec.evaluation_type, mec.description
    ORDER BY mec.evaluation_type, mec.name";
    $categories_stmt = mysqli_prepare($conn, $categories_query);
    mysqli_stmt_bind_param($categories_stmt, "i", $teacher_id);
} else {
    // Original query for users table teachers
    $categories_query = "SELECT
        mec.id as category_id,
        mec.name as category_name,
        mec.evaluation_type,
        mec.description as category_description,
        COUNT(DISTINCT es.id) as total_evaluations,
        AVG(er.rating_value) as average_rating,
        MIN(er.rating_value) as min_rating,
        MAX(er.rating_value) as max_rating,
        STDDEV(er.rating_value) as rating_stddev
    FROM main_evaluation_categories mec
    LEFT JOIN evaluation_sessions es ON mec.id = es.main_category_id AND es.evaluatee_id = ? AND es.status = 'completed'
    LEFT JOIN evaluation_responses er ON es.id = er.evaluation_session_id AND er.rating_value IS NOT NULL
    GROUP BY mec.id, mec.name, mec.evaluation_type, mec.description
    ORDER BY mec.evaluation_type, mec.name";
    $categories_stmt = mysqli_prepare($conn, $categories_query);
    mysqli_stmt_bind_param($categories_stmt, "i", $teacher_id);
}
mysqli_stmt_execute($categories_stmt);
$categories_result = mysqli_stmt_get_result($categories_stmt);

$categories = [];
while ($category = mysqli_fetch_assoc($categories_result)) {
    $categories[] = $category;
}

// Get semester-wise performance data for charts
if ($is_faculty_member) {
    // For faculty members, check evaluations by faculty_id
    $semester_performance_query = "SELECT
        s.id as semester_id,
        s.name as semester_name,
        s.academic_year,
        mec.evaluation_type,
        AVG(er.rating_value) as average_rating,
        COUNT(DISTINCT es.id) as evaluation_count
    FROM semesters s
    CROSS JOIN main_evaluation_categories mec
    LEFT JOIN evaluation_sessions es ON s.id = es.semester_id
        AND mec.id = es.main_category_id
        AND es.evaluatee_id = ?
        AND es.status = 'completed'
    LEFT JOIN evaluation_responses er ON es.id = er.evaluation_session_id AND er.rating_value IS NOT NULL
    WHERE s.id IN (SELECT DISTINCT semester_id FROM evaluation_sessions es2
                   WHERE es2.evaluatee_id = ? AND es2.status = 'completed')
    GROUP BY s.id, s.name, s.academic_year, mec.evaluation_type
    ORDER BY s.start_date DESC, mec.evaluation_type";
    $semester_performance_stmt = mysqli_prepare($conn, $semester_performance_query);
    mysqli_stmt_bind_param($semester_performance_stmt, "ii", $teacher_id, $teacher_id);
} else {
    // Original query for users table teachers
    $semester_performance_query = "SELECT
        s.id as semester_id,
        s.name as semester_name,
        s.academic_year,
        mec.evaluation_type,
        AVG(er.rating_value) as average_rating,
        COUNT(DISTINCT es.id) as evaluation_count
    FROM semesters s
    CROSS JOIN main_evaluation_categories mec
    LEFT JOIN evaluation_sessions es ON s.id = es.semester_id AND mec.id = es.main_category_id AND es.evaluatee_id = ? AND es.status = 'completed'
    LEFT JOIN evaluation_responses er ON es.id = er.evaluation_session_id AND er.rating_value IS NOT NULL
    WHERE s.id IN (SELECT DISTINCT semester_id FROM evaluation_sessions WHERE evaluatee_id = ? AND status = 'completed')
    GROUP BY s.id, s.name, s.academic_year, mec.evaluation_type
    ORDER BY s.start_date DESC, mec.evaluation_type";
    $semester_performance_stmt = mysqli_prepare($conn, $semester_performance_query);
    mysqli_stmt_bind_param($semester_performance_stmt, "ii", $teacher_id, $teacher_id);
}
mysqli_stmt_execute($semester_performance_stmt);
$semester_performance_result = mysqli_stmt_get_result($semester_performance_stmt);

$semester_performance = [];
while ($performance = mysqli_fetch_assoc($semester_performance_result)) {
    $semester_performance[] = $performance;
}

// If no semester performance data found, try alternative query structure
if (empty($semester_performance)) {
    // Try querying by evaluation_date instead of semester_id
    if ($is_faculty_member) {
        $semester_performance_query = "SELECT
            s.id as semester_id,
            s.name as semester_name,
            s.academic_year,
            mec.evaluation_type,
            AVG(er.rating_value) as average_rating,
            COUNT(DISTINCT es.id) as evaluation_count
        FROM semesters s
        CROSS JOIN main_evaluation_categories mec
        LEFT JOIN evaluation_sessions es ON es.evaluation_date BETWEEN s.start_date AND s.end_date
            AND mec.id = es.main_category_id
            AND es.evaluatee_id = ?
            AND es.status = 'completed'
        LEFT JOIN evaluation_responses er ON es.id = er.evaluation_session_id AND er.rating_value IS NOT NULL
        WHERE s.id IN (SELECT DISTINCT s2.id FROM semesters s2
                       JOIN evaluation_sessions es2 ON es2.evaluation_date BETWEEN s2.start_date AND s2.end_date
                       WHERE es2.evaluatee_id = ? AND es2.status = 'completed')
        GROUP BY s.id, s.name, s.academic_year, mec.evaluation_type
        ORDER BY s.start_date DESC, mec.evaluation_type";
        $semester_performance_stmt = mysqli_prepare($conn, $semester_performance_query);
        mysqli_stmt_bind_param($semester_performance_stmt, "ii", $teacher_id, $teacher_id);
    } else {
        $semester_performance_query = "SELECT
            s.id as semester_id,
            s.name as semester_name,
            s.academic_year,
            mec.evaluation_type,
            AVG(er.rating_value) as average_rating,
            COUNT(DISTINCT es.id) as evaluation_count
        FROM semesters s
        CROSS JOIN main_evaluation_categories mec
        LEFT JOIN evaluation_sessions es ON es.evaluation_date BETWEEN s.start_date AND s.end_date
            AND mec.id = es.main_category_id
            AND es.evaluatee_id = ?
            AND es.status = 'completed'
        LEFT JOIN evaluation_responses er ON es.id = er.evaluation_session_id AND er.rating_value IS NOT NULL
        WHERE s.id IN (SELECT DISTINCT s2.id FROM semesters s2
                       JOIN evaluation_sessions es2 ON es2.evaluation_date BETWEEN s2.start_date AND s2.end_date
                       WHERE es2.evaluatee_id = ? AND es2.status = 'completed')
        GROUP BY s.id, s.name, s.academic_year, mec.evaluation_type
        ORDER BY s.start_date DESC, mec.evaluation_type";
        $semester_performance_stmt = mysqli_prepare($conn, $semester_performance_query);
        mysqli_stmt_bind_param($semester_performance_stmt, "ii", $teacher_id, $teacher_id);
    }
    mysqli_stmt_execute($semester_performance_stmt);
    $semester_performance_result = mysqli_stmt_get_result($semester_performance_stmt);

    while ($performance = mysqli_fetch_assoc($semester_performance_result)) {
        $semester_performance[] = $performance;
    }
}

// Get sub-category performance
if ($is_faculty_member) {
    // For faculty members, check evaluations by faculty_id
    $subcategory_performance_query = "SELECT
        esc.id as subcategory_id,
        esc.name as subcategory_name,
        mec.name as category_name,
        mec.evaluation_type,
        AVG(er.rating_value) as average_rating,
        COUNT(DISTINCT es.id) as evaluation_count,
        COUNT(er.id) as response_count
    FROM evaluation_sub_categories esc
    JOIN main_evaluation_categories mec ON esc.main_category_id = mec.id
    LEFT JOIN evaluation_sessions es ON mec.id = es.main_category_id
        AND es.evaluatee_id = ?
        AND es.status = 'completed'
    LEFT JOIN evaluation_responses er ON es.id = er.evaluation_session_id
        AND er.questionnaire_id IN (SELECT id FROM evaluation_questionnaires WHERE sub_category_id = esc.id)
        AND er.rating_value IS NOT NULL
    GROUP BY esc.id, esc.name, mec.name, mec.evaluation_type
    HAVING evaluation_count > 0
    ORDER BY mec.evaluation_type, esc.order_number";
    $subcategory_performance_stmt = mysqli_prepare($conn, $subcategory_performance_query);
    mysqli_stmt_bind_param($subcategory_performance_stmt, "i", $teacher_id);
} else {
    // Original query for users table teachers
    $subcategory_performance_query = "SELECT
        esc.id as subcategory_id,
        esc.name as subcategory_name,
        mec.name as category_name,
        mec.evaluation_type,
        AVG(er.rating_value) as average_rating,
        COUNT(DISTINCT es.id) as evaluation_count,
        COUNT(er.id) as response_count
    FROM evaluation_sub_categories esc
    JOIN main_evaluation_categories mec ON esc.main_category_id = mec.id
    LEFT JOIN evaluation_sessions es ON mec.id = es.main_category_id AND es.evaluatee_id = ? AND es.status = 'completed'
    LEFT JOIN evaluation_responses er ON es.id = er.evaluation_session_id
        AND er.questionnaire_id IN (SELECT id FROM evaluation_questionnaires WHERE sub_category_id = esc.id)
        AND er.rating_value IS NOT NULL
    GROUP BY esc.id, esc.name, mec.name, mec.evaluation_type
    HAVING evaluation_count > 0
    ORDER BY mec.evaluation_type, esc.order_number";
    $subcategory_performance_stmt = mysqli_prepare($conn, $subcategory_performance_query);
    mysqli_stmt_bind_param($subcategory_performance_stmt, "i", $teacher_id);
}
mysqli_stmt_execute($subcategory_performance_stmt);
$subcategory_performance_result = mysqli_stmt_get_result($subcategory_performance_stmt);

$subcategory_performance = [];
while ($subcategory = mysqli_fetch_assoc($subcategory_performance_result)) {
    $subcategory_performance[] = $subcategory;
}

// Calculate overall statistics
if ($is_faculty_member) {
    // For faculty members, check evaluations by faculty_id
    $overall_stats_query = "SELECT
        COUNT(DISTINCT es.id) as total_evaluations,
        AVG(er.rating_value) as overall_average,
        MIN(er.rating_value) as overall_min,
        MAX(er.rating_value) as overall_max,
        STDDEV(er.rating_value) as overall_stddev,
        COUNT(DISTINCT es.semester_id) as total_semesters,
        COUNT(DISTINCT es.main_category_id) as total_categories
    FROM evaluation_sessions es
    LEFT JOIN evaluation_responses er ON es.id = er.evaluation_session_id AND er.rating_value IS NOT NULL
    WHERE es.evaluatee_id = ? AND es.status = 'completed'";
    $overall_stats_stmt = mysqli_prepare($conn, $overall_stats_query);
    mysqli_stmt_bind_param($overall_stats_stmt, "i", $teacher_id);
} else {
    // Original query for users table teachers
    $overall_stats_query = "SELECT
        COUNT(DISTINCT es.id) as total_evaluations,
        AVG(er.rating_value) as overall_average,
        MIN(er.rating_value) as overall_min,
        MAX(er.rating_value) as overall_max,
        STDDEV(er.rating_value) as overall_stddev,
        COUNT(DISTINCT es.semester_id) as total_semesters,
        COUNT(DISTINCT es.main_category_id) as total_categories
    FROM evaluation_sessions es
    LEFT JOIN evaluation_responses er ON es.id = er.evaluation_session_id AND er.rating_value IS NOT NULL
    WHERE es.evaluatee_id = ? AND es.status = 'completed'";
    $overall_stats_stmt = mysqli_prepare($conn, $overall_stats_query);
    mysqli_stmt_bind_param($overall_stats_stmt, "i", $teacher_id);
}
mysqli_stmt_execute($overall_stats_stmt);
$overall_stats_result = mysqli_stmt_get_result($overall_stats_stmt);
$overall_stats = mysqli_fetch_assoc($overall_stats_result);

// If no evaluation data exists, set default values
if (!$overall_stats || $overall_stats['total_evaluations'] == 0) {
    $overall_stats = [
        'total_evaluations' => 0,
        'overall_average' => 0,
        'overall_min' => 0,
        'overall_max' => 0,
        'overall_stddev' => 0,
        'total_semesters' => 0,
        'total_categories' => 0
    ];
}

// Get training and seminar suggestions for categories with scores below 4.0
$training_suggestions = [];
if ($overall_stats['total_evaluations'] > 0) {
    // Get categories with scores below 4.0
    $low_performing_categories = array_filter($categories, function($cat) {
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

// Get text responses for sentiment analysis
$text_responses = [];
if ($overall_stats['total_evaluations'] > 0) {
    if ($is_faculty_member) {
        // For faculty members, get text responses by faculty_id
        $text_responses_query = "SELECT
            er.id as response_id,
            er.text_response,
            er.created_at,
            eq.question_text,
            eq.question_type,
            esc.name as subcategory_name,
            mec.name as category_name,
            mec.evaluation_type,
            s.name as semester_name,
            s.academic_year
        FROM evaluation_responses er
        JOIN evaluation_sessions es ON er.evaluation_session_id = es.id
        JOIN evaluation_questionnaires eq ON er.questionnaire_id = eq.id
        LEFT JOIN evaluation_sub_categories esc ON eq.sub_category_id = esc.id
        LEFT JOIN main_evaluation_categories mec ON esc.main_category_id = mec.id
        LEFT JOIN semesters s ON es.semester_id = s.id
        WHERE es.evaluatee_id = ?
        AND es.status = 'completed'
        AND er.text_response IS NOT NULL
        AND er.text_response != ''
        ORDER BY er.created_at DESC";
        $text_responses_stmt = mysqli_prepare($conn, $text_responses_query);
        mysqli_stmt_bind_param($text_responses_stmt, "i", $teacher_id);
    } else {
        // For regular users, get text responses by user_id
        $text_responses_query = "SELECT
            er.id as response_id,
            er.text_response,
            er.created_at,
            eq.question_text,
            eq.question_type,
            esc.name as subcategory_name,
            mec.name as category_name,
            mec.evaluation_type,
            s.name as semester_name,
            s.academic_year
        FROM evaluation_responses er
        JOIN evaluation_sessions es ON er.evaluation_session_id = es.id
        JOIN evaluation_questionnaires eq ON er.questionnaire_id = eq.id
        LEFT JOIN evaluation_sub_categories esc ON eq.sub_category_id = esc.id
        LEFT JOIN main_evaluation_categories mec ON esc.main_category_id = mec.id
        LEFT JOIN semesters s ON es.semester_id = s.id
        WHERE es.evaluatee_id = ?
        AND es.status = 'completed'
        AND er.text_response IS NOT NULL
        AND er.text_response != ''
        ORDER BY er.created_at DESC";
        $text_responses_stmt = mysqli_prepare($conn, $text_responses_query);
        mysqli_stmt_bind_param($text_responses_stmt, "i", $teacher_id);
    }

    mysqli_stmt_execute($text_responses_stmt);
    $text_responses_result = mysqli_stmt_get_result($text_responses_stmt);

    while ($response = mysqli_fetch_assoc($text_responses_result)) {
        // Analyze sentiment for each text response
        $sentiment = $sentimentAnalyzer->analyzeSentiment($response['text_response']);
        $response['sentiment'] = $sentiment;
        $text_responses[] = $response;
    }
}

// Calculate overall sentiment statistics
$overall_sentiment_stats = [
    'positive' => 0,
    'negative' => 0,
    'neutral' => 0,
    'total_comments' => count($text_responses),
    'positive_percentage' => 0,
    'negative_percentage' => 0,
    'neutral_percentage' => 0,
    'average_sentiment_score' => 0,
    'total_sentiment_score' => 0,
    'emotions' => [
        'happy' => 0,
        'sad' => 0,
        'angry' => 0,
        'neutral' => 0,
        'confused' => 0,
        'frustrated' => 0,
        'excited' => 0,
        'concerned' => 0
    ]
];

foreach ($text_responses as $response) {
    $sentiment = $response['sentiment'];
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

    // Count emotions
    if (isset($sentiment['emotion']) && isset($overall_sentiment_stats['emotions'][$sentiment['emotion']])) {
        $overall_sentiment_stats['emotions'][$sentiment['emotion']]++;
    }
}

// Calculate percentages and averages
if ($overall_sentiment_stats['total_comments'] > 0) {
    $overall_sentiment_stats['positive_percentage'] = round(($overall_sentiment_stats['positive'] / $overall_sentiment_stats['total_comments']) * 100, 1);
    $overall_sentiment_stats['negative_percentage'] = round(($overall_sentiment_stats['negative'] / $overall_sentiment_stats['total_comments']) * 100, 1);
    $overall_sentiment_stats['neutral_percentage'] = round(($overall_sentiment_stats['neutral'] / $overall_sentiment_stats['total_comments']) * 100, 1);
    $overall_sentiment_stats['average_sentiment_score'] = round($overall_sentiment_stats['total_sentiment_score'] / $overall_sentiment_stats['total_comments'], 3);
}

// Group text responses by category for detailed analysis
$category_sentiment_stats = [];
foreach ($categories as $category) {
    $category_responses = array_filter($text_responses, function($response) use ($category) {
        return $response['category_name'] === $category['category_name'];
    });

    if (!empty($category_responses)) {
        $category_stats = [
            'category_name' => $category['category_name'],
            'evaluation_type' => $category['evaluation_type'],
            'positive' => 0,
            'negative' => 0,
            'neutral' => 0,
            'total_comments' => count($category_responses),
            'positive_percentage' => 0,
            'negative_percentage' => 0,
            'neutral_percentage' => 0,
            'average_sentiment_score' => 0,
            'total_sentiment_score' => 0,
            'emotions' => [
                'happy' => 0,
                'sad' => 0,
                'angry' => 0,
                'neutral' => 0,
                'confused' => 0,
                'frustrated' => 0,
                'excited' => 0,
                'concerned' => 0
            ],
            'responses' => $category_responses
        ];

        foreach ($category_responses as $response) {
            $sentiment = $response['sentiment'];
            $category_stats['total_sentiment_score'] += $sentiment['score'];

            switch ($sentiment['sentiment']) {
                case 'positive':
                    $category_stats['positive']++;
                    break;
                case 'negative':
                    $category_stats['negative']++;
                    break;
                case 'neutral':
                    $category_stats['neutral']++;
                    break;
            }

            if (isset($sentiment['emotion']) && isset($category_stats['emotions'][$sentiment['emotion']])) {
                $category_stats['emotions'][$sentiment['emotion']]++;
            }
        }

        if ($category_stats['total_comments'] > 0) {
            $category_stats['positive_percentage'] = round(($category_stats['positive'] / $category_stats['total_comments']) * 100, 1);
            $category_stats['negative_percentage'] = round(($category_stats['negative'] / $category_stats['total_comments']) * 100, 1);
            $category_stats['neutral_percentage'] = round(($category_stats['neutral'] / $category_stats['total_comments']) * 100, 1);
            $category_stats['average_sentiment_score'] = round($category_stats['total_sentiment_score'] / $category_stats['total_comments'], 3);
        }

        $category_sentiment_stats[] = $category_stats;
    }
}

// Include the shared header
include 'includes/header.php';
?>

<!-- Custom CSS for evaluation results -->
<link rel="stylesheet" href="assets/css/evaluation-results.css">

<style>
/* Enhanced Sentiment Analysis Styles */
.sentiment-chart-container {
    position: relative;
    min-height: 350px;
    height: 350px;
    width: 100%;
}

.chart-type-selector {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 8px;
    padding: 1rem;
    margin-bottom: 1rem;
}

.chart-type-selector select {
    background: white;
    border: 2px solid #e5e7eb;
    border-radius: 6px;
    padding: 0.5rem;
    font-weight: 500;
    transition: all 0.2s ease;
    color: #374151;
}

.chart-type-selector select:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    outline: none;
}

.chart-type-selector label {
    color: white;
    font-weight: 600;
    margin-bottom: 0.5rem;
    display: block;
}

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

.emotion-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 24px;
    height: 24px;
    border-radius: 50%;
    margin-right: 0.5rem;
}

.emotion-happy { background-color: #fef3c7; color: #d97706; }
.emotion-sad { background-color: #dbeafe; color: #2563eb; }
.emotion-angry { background-color: #fee2e2; color: #dc2626; }
.emotion-neutral { background-color: #f3f4f6; color: #6b7280; }
.emotion-confused { background-color: #f3e8ff; color: #9333ea; }
.emotion-frustrated { background-color: #fed7aa; color: #ea580c; }
.emotion-excited { background-color: #dcfce7; color: #16a34a; }
.emotion-concerned { background-color: #e0e7ff; color: #6366f1; }

/* Chart container improvements */
.chart-container {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 1.5rem;
    height: 100%;
    display: flex;
    flex-direction: column;
}

.chart-container:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
}

.chart-wrapper {
    flex: 1;
    min-height: 350px;
    position: relative;
}

/* Chart grid improvements */
.charts-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
    gap: 1.5rem;
    margin-top: 1.5rem;
}

.charts-grid-full {
    display: grid;
    grid-template-columns: 1fr;
    gap: 1.5rem;
    margin-top: 1.5rem;
}

/* Responsive improvements */
@media (max-width: 768px) {
    .charts-grid {
        grid-template-columns: 1fr;
    }

    .sentiment-chart-container {
        min-height: 300px;
        height: 300px;
    }

    .chart-wrapper {
        min-height: 300px;
    }

    .chart-container {
        padding: 1rem;
    }
}

/* Responsive table improvements */
.sentiment-summary-table {
    font-size: 0.875rem;
}

.sentiment-summary-table th {
    background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.sentiment-summary-table tr:hover {
    background-color: #f8fafc;
}

/* Chart loading states */
.chart-loading {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 350px;
    background: #f9fafb;
    border-radius: 8px;
}

.chart-loading-spinner {
    border: 3px solid #e5e7eb;
    border-top: 3px solid #667eea;
    border-radius: 50%;
    width: 40px;
    height: 40px;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Print styles */
@media print {
    .chart-type-selector,
    .chart-container:hover {
        transform: none;
        box-shadow: none;
    }

    .sentiment-summary-table {
        font-size: 0.75rem;
    }

    .chart-container {
        break-inside: avoid;
        page-break-inside: avoid;
    }
}

/* Chart title improvements */
.chart-title {
    font-size: 1.125rem;
    font-weight: 600;
    color: #1f2937;
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
}

.chart-title i {
    margin-right: 0.5rem;
}

/* Chart subtitle */
.chart-subtitle {
    font-size: 0.875rem;
    color: #6b7280;
    margin-bottom: 1.5rem;
}

/* Chart legend improvements */
.chart-legend {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
    margin-top: 1rem;
    padding: 1rem;
    background: #f9fafb;
    border-radius: 6px;
}

.legend-item {
    display: flex;
    align-items: center;
    font-size: 0.875rem;
    color: #374151;
}

.legend-color {
    width: 12px;
    height: 12px;
    border-radius: 2px;
    margin-right: 0.5rem;
}
</style>

<div class="mb-6 sm:mb-8">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-seait-dark mb-2">Teacher Evaluation Results</h1>
            <p class="text-sm sm:text-base text-gray-600">
                Comprehensive evaluation analysis for <?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?>
            </p>
        </div>
        <div class="flex space-x-2">
            <a href="<?php echo $_SESSION['role'] === 'teacher' ? '../faculty/evaluation-results.php' : 'teachers.php'; ?>" class="bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600 transition">
                <i class="fas fa-arrow-left mr-2"></i><?php echo $_SESSION['role'] === 'teacher' ? 'Back to My Results' : 'Back to Teachers'; ?>
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

<!-- No Evaluation Data Alert -->
<?php if ($overall_stats['total_evaluations'] == 0): ?>
<div class="mb-6 p-6 bg-blue-50 border border-blue-200 rounded-lg">
    <div class="flex">
        <div class="flex-shrink-0">
            <i class="fas fa-info-circle text-blue-400 text-xl"></i>
        </div>
        <div class="ml-3">
            <h3 class="text-lg font-medium text-blue-800">
                <?php if ($is_faculty_member): ?>
                    No Evaluation Data Available for Faculty Member
                <?php else: ?>
                    No Evaluation Data Available
                <?php endif; ?>
            </h3>
            <div class="mt-2 text-sm text-blue-700">
                <?php if ($is_faculty_member): ?>
                    <p><strong>Faculty Member Status:</strong> This faculty member currently has no evaluation data in the system.</p>
                    <p class="mt-1"><strong>Possible Reasons:</strong></p>
                    <ul class="list-disc list-inside mt-1 ml-4">
                        <li>No evaluations have been conducted for this faculty member yet</li>
                        <li>Faculty member may need to be added to the users table for evaluation tracking</li>
                        <li>Evaluations may be in draft status and not yet completed</li>
                    </ul>
                <?php else: ?>
                    <p><strong>Evaluation Status:</strong> This teacher currently has no completed evaluations in the system.</p>
                    <p class="mt-1"><strong>Possible Reasons:</strong></p>
                    <ul class="list-disc list-inside mt-1 ml-4">
                        <li>No evaluations have been conducted yet</li>
                        <li>All evaluations are still in draft status</li>
                        <li>Evaluation sessions haven't been created for this teacher</li>
                    </ul>
                <?php endif; ?>
            </div>
            <div class="mt-3 p-3 bg-blue-100 rounded-md">
                <p class="text-sm font-medium text-blue-800">To see evaluation data:</p>
                <p class="text-sm text-blue-700 mt-1">• Conduct evaluations through the evaluation system</p>
                <p class="text-sm text-blue-700">• Ensure evaluations are completed (not just saved as drafts)</p>
                <p class="text-sm text-blue-700">• Check other evaluation management pages for pending evaluations</p>
                <?php if ($is_faculty_member): ?>
                    <p class="text-sm text-blue-700">• Consider creating a user account for this faculty member if needed</p>
                <?php endif; ?>
            </div>
            <div class="mt-4 flex flex-wrap gap-2">
                <a href="conduct-evaluation.php?evaluatee_id=<?php echo $teacher_id; ?>&evaluatee_type=teacher"
                   class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-seait-orange hover:bg-orange-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-orange-500 transition-colors duration-200">
                    <i class="fas fa-plus mr-2"></i>Conduct Evaluation
                </a>
                <a href="all-evaluations.php?evaluatee_id=<?php echo $teacher_id; ?>"
                   class="inline-flex items-center px-3 py-2 border border-gray-300 text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 transition-colors duration-200">
                    <i class="fas fa-search mr-2"></i>Check All Evaluations
                </a>
                <a href="teacher-evaluations.php"
                   class="inline-flex items-center px-3 py-2 border border-gray-300 text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 transition-colors duration-200">
                    <i class="fas fa-list mr-2"></i>View Teacher Evaluations
                </a>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Teacher Information -->
<div class="bg-white rounded-lg shadow-md overflow-hidden mb-8">
    <div class="px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-seait-orange to-orange-600">
        <h2 class="text-lg font-semibold text-white flex items-center">
            <i class="fas fa-user-tie mr-3"></i>Teacher Information
        </h2>
    </div>
    <div class="p-6">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                <p class="text-gray-900 font-medium"><?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?></p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                <p class="text-gray-900"><?php echo htmlspecialchars($teacher['email']); ?></p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Department</label>
                <p class="text-gray-900"><?php echo htmlspecialchars($teacher['department']); ?></p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Position</label>
                <p class="text-gray-900"><?php echo htmlspecialchars($teacher['position']); ?></p>
            </div>
        </div>
    </div>
</div>

<!-- Overall Statistics -->
<?php if ($overall_stats['total_evaluations'] > 0): ?>
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    <div class="bg-white rounded-lg shadow-md p-6">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                <i class="fas fa-chart-line text-xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-600">Overall Average</p>
                <p class="text-2xl font-bold text-gray-900">
                    <?php echo number_format($overall_stats['overall_average'] ?? 0, 2); ?>/5.00
                </p>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow-md p-6">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-green-100 text-green-600">
                <i class="fas fa-clipboard-check text-xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-600">Total Evaluations</p>
                <p class="text-2xl font-bold text-gray-900"><?php echo $overall_stats['total_evaluations']; ?></p>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow-md p-6">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-purple-100 text-purple-600">
                <i class="fas fa-calendar-alt text-xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-600">Semesters Evaluated</p>
                <p class="text-2xl font-bold text-gray-900"><?php echo $overall_stats['total_semesters']; ?></p>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow-md p-6">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-orange-100 text-orange-600">
                <i class="fas fa-star text-xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-600">Rating Range</p>
                <p class="text-2xl font-bold text-gray-900">
                    <?php echo $overall_stats['overall_min']; ?> - <?php echo $overall_stats['overall_max']; ?>
                </p>
            </div>
        </div>
    </div>
</div>

<!-- Sentiment Analysis Section -->
<?php if ($overall_sentiment_stats['total_comments'] > 0): ?>
<div class="bg-white rounded-lg shadow-md overflow-hidden mb-8">
    <div class="px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-indigo-500 to-indigo-600">
        <h2 class="text-lg font-semibold text-white flex items-center">
            <i class="fas fa-brain mr-3"></i>Sentiment Analysis of Feedback
        </h2>
        <p class="text-indigo-100 text-sm mt-1">Analysis of <?php echo $overall_sentiment_stats['total_comments']; ?> text responses and comments</p>
    </div>
    <div class="p-6">
        <!-- Overall Sentiment Overview -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
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
                <div class="text-xs text-blue-600">Range: -1.0 to +1.0</div>
            </div>
        </div>

        <!-- Emotion Distribution -->
        <div class="mb-8">
            <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                <i class="fas fa-heart mr-2 text-pink-500"></i>Emotion Distribution
            </h3>
            <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-8 gap-4">
                <?php
                $emotion_colors = [
                    'happy' => ['bg-yellow-50', 'border-yellow-200', 'text-yellow-600', 'text-yellow-800'],
                    'sad' => ['bg-blue-50', 'border-blue-200', 'text-blue-600', 'text-blue-800'],
                    'angry' => ['bg-red-50', 'border-red-200', 'text-red-600', 'text-red-800'],
                    'neutral' => ['bg-gray-50', 'border-gray-200', 'text-gray-600', 'text-gray-800'],
                    'confused' => ['bg-purple-50', 'border-purple-200', 'text-purple-600', 'text-purple-800'],
                    'frustrated' => ['bg-orange-50', 'border-orange-200', 'text-orange-600', 'text-orange-800'],
                    'excited' => ['bg-green-50', 'border-green-200', 'text-green-600', 'text-green-800'],
                    'concerned' => ['bg-indigo-50', 'border-indigo-200', 'text-indigo-600', 'text-indigo-800']
                ];

                $emotion_icons = [
                    'happy' => 'fas fa-smile',
                    'sad' => 'fas fa-sad-tear',
                    'angry' => 'fas fa-angry',
                    'neutral' => 'fas fa-meh',
                    'confused' => 'fas fa-question-circle',
                    'frustrated' => 'fas fa-exclamation-triangle',
                    'excited' => 'fas fa-star',
                    'concerned' => 'fas fa-exclamation-circle'
                ];

                foreach ($overall_sentiment_stats['emotions'] as $emotion => $count):
                    if ($count > 0):
                        $percentage = round(($count / $overall_sentiment_stats['total_comments']) * 100, 1);
                        $colors = $emotion_colors[$emotion];
                        $icon = $emotion_icons[$emotion];
                ?>
                    <div class="<?php echo $colors[0]; ?> border <?php echo $colors[1]; ?> rounded-lg p-3 text-center">
                        <div class="flex items-center justify-center mb-1">
                            <i class="<?php echo $icon; ?> <?php echo $colors[2]; ?> text-xl"></i>
                        </div>
                        <div class="text-lg font-bold <?php echo $colors[3]; ?>"><?php echo $count; ?></div>
                        <div class="text-xs <?php echo $colors[3]; ?>"><?php echo ucfirst($emotion); ?></div>
                        <div class="text-xs <?php echo $colors[2]; ?>"><?php echo $percentage; ?>%</div>
                    </div>
                <?php
                    endif;
                endforeach;
                ?>
            </div>
        </div>

        <!-- Category-wise Sentiment Analysis -->
        <?php if (!empty($category_sentiment_stats)): ?>
        <div class="mb-8">
            <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                <i class="fas fa-chart-pie mr-2 text-purple-500"></i>Sentiment Analysis by Category
            </h3>
            <div class="space-y-6">
                <?php foreach ($category_sentiment_stats as $category_stats): ?>
                    <div class="border border-gray-200 rounded-lg p-6">
                        <div class="flex justify-between items-center mb-4">
                            <div>
                                <h4 class="text-lg font-medium text-gray-900">
                                    <?php echo htmlspecialchars($category_stats['category_name']); ?>
                                </h4>
                                <p class="text-sm text-gray-600">
                                    <?php echo ucwords(str_replace('_', ' ', $category_stats['evaluation_type'])); ?> Evaluation
                                </p>
                            </div>
                            <div class="text-right">
                                <div class="text-2xl font-bold text-indigo-600">
                                    <?php echo $category_stats['average_sentiment_score']; ?>
                                </div>
                                <div class="text-sm text-gray-600">Sentiment Score</div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                            <div class="bg-green-50 border border-green-200 rounded-lg p-3 text-center">
                                <div class="text-lg font-bold text-green-600"><?php echo $category_stats['positive_percentage']; ?>%</div>
                                <div class="text-sm text-green-800">Positive</div>
                                <div class="text-xs text-green-600"><?php echo $category_stats['positive']; ?> comments</div>
                            </div>

                            <div class="bg-red-50 border border-red-200 rounded-lg p-3 text-center">
                                <div class="text-lg font-bold text-red-600"><?php echo $category_stats['negative_percentage']; ?>%</div>
                                <div class="text-sm text-red-800">Negative</div>
                                <div class="text-xs text-red-600"><?php echo $category_stats['negative']; ?> comments</div>
                            </div>

                            <div class="bg-gray-50 border border-gray-200 rounded-lg p-3 text-center">
                                <div class="text-lg font-bold text-gray-600"><?php echo $category_stats['neutral_percentage']; ?>%</div>
                                <div class="text-sm text-gray-800">Neutral</div>
                                <div class="text-xs text-gray-600"><?php echo $category_stats['neutral']; ?> comments</div>
                            </div>
                        </div>

                        <!-- Top Emotions for this Category -->
                        <div class="mb-4">
                            <h5 class="text-sm font-medium text-gray-700 mb-2">Top Emotions:</h5>
                            <div class="flex flex-wrap gap-2">
                                <?php
                                arsort($category_stats['emotions']);
                                $top_emotions = array_slice($category_stats['emotions'], 0, 3, true);
                                foreach ($top_emotions as $emotion => $count):
                                    if ($count > 0):
                                        $colors = $emotion_colors[$emotion];
                                        $icon = $emotion_icons[$emotion];
                                ?>
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm <?php echo $colors[0]; ?> <?php echo $colors[1]; ?>">
                                        <i class="<?php echo $icon; ?> <?php echo $colors[2]; ?> mr-2"></i>
                                        <?php echo ucfirst($emotion); ?> (<?php echo $count; ?>)
                                    </span>
                                <?php
                                    endif;
                                endforeach;
                                ?>
                            </div>
                        </div>

                        <!-- Sample Comments -->
                        <div class="border-t border-gray-200 pt-4">
                            <h5 class="text-sm font-medium text-gray-700 mb-3">Sample Comments:</h5>
                            <div class="space-y-3">
                                <?php
                                $sample_responses = array_slice($category_stats['responses'], 0, 3);
                                foreach ($sample_responses as $response):
                                    $sentiment = $response['sentiment'];
                                    $sentiment_colors = [
                                        'positive' => ['bg-green-50', 'border-green-200', 'text-green-800'],
                                        'negative' => ['bg-red-50', 'border-red-200', 'text-red-800'],
                                        'neutral' => ['bg-gray-50', 'border-gray-200', 'text-gray-800']
                                    ];
                                    $colors = $sentiment_colors[$sentiment['sentiment']];
                                ?>
                                    <div class="<?php echo $colors[0]; ?> border <?php echo $colors[1]; ?> rounded-lg p-3">
                                        <div class="flex justify-between items-start mb-2">
                                            <span class="text-xs font-medium <?php echo $colors[2]; ?>">
                                                <?php echo ucfirst($sentiment['sentiment']); ?>
                                                (<?php echo $sentiment['score']; ?>)
                                            </span>
                                            <span class="text-xs text-gray-500">
                                                <?php echo date('M d, Y', strtotime($response['created_at'])); ?>
                                            </span>
                                        </div>
                                        <p class="text-sm <?php echo $colors[2]; ?>">
                                            "<?php echo htmlspecialchars(substr($response['text_response'], 0, 150)); ?>
                                            <?php if (strlen($response['text_response']) > 150): ?>..."<?php endif; ?>"
                                        </p>
                                        <div class="mt-2 text-xs text-gray-600">
                                            <strong>Question:</strong> <?php echo htmlspecialchars(substr($response['question_text'], 0, 100)); ?>
                                            <?php if (strlen($response['question_text']) > 100): ?>...<?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Sentiment Insights -->
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-6">
            <h3 class="text-lg font-semibold text-blue-800 mb-4 flex items-center">
                <i class="fas fa-lightbulb mr-2"></i>Sentiment Insights
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <h4 class="font-medium text-blue-800 mb-2">Overall Sentiment Trend:</h4>
                    <p class="text-blue-700 text-sm">
                        <?php
                        if ($overall_sentiment_stats['positive_percentage'] > 50) {
                            echo '<span class="font-semibold text-green-600">Positive</span> - Most feedback indicates satisfaction and positive experiences.';
                        } elseif ($overall_sentiment_stats['negative_percentage'] > 50) {
                            echo '<span class="font-semibold text-red-600">Negative</span> - Feedback suggests areas that need improvement.';
                        } else {
                            echo '<span class="font-semibold text-gray-600">Mixed/Neutral</span> - Feedback is balanced with both positive and negative aspects.';
                        }
                        ?>
                    </p>
                </div>
                <div>
                    <h4 class="font-medium text-blue-800 mb-2">Key Observations:</h4>
                    <ul class="text-blue-700 text-sm space-y-1">
                        <li>• Total comments analyzed: <strong><?php echo $overall_sentiment_stats['total_comments']; ?></strong></li>
                        <li>• Average sentiment score: <strong><?php echo $overall_sentiment_stats['average_sentiment_score']; ?></strong></li>
                        <li>• Most common emotion:
                            <strong>
                                <?php
                                $max_emotion = array_keys($overall_sentiment_stats['emotions'], max($overall_sentiment_stats['emotions']));
                                echo ucfirst($max_emotion[0]);
                                ?>
                            </strong>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Sentiment Charts -->
        <div class="charts-grid">
            <!-- Category Sentiment Comparison -->
            <?php if (count($category_sentiment_stats) > 1): ?>
            <div class="chart-container">
                <h3 class="chart-title">
                    <i class="fas fa-chart-line text-green-500"></i>Category Sentiment Comparison
                </h3>
                <p class="chart-subtitle">Compare sentiment scores across different evaluation categories</p>

                <div class="chart-type-selector">
                    <label for="chartTypeSelector">Chart Type:</label>
                    <select id="chartTypeSelector" class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                        <option value="line">Line Chart</option>
                        <option value="bar">Bar Chart</option>
                        <option value="radar">Radar Chart</option>
                    </select>
                </div>

                <div class="chart-wrapper">
                    <div class="sentiment-chart-container">
                        <canvas id="categorySentimentChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Sentiment Distribution by Category -->
            <div class="chart-container">
                <h3 class="chart-title">
                    <i class="fas fa-chart-pie text-purple-500"></i>Sentiment Distribution
                </h3>
                <p class="chart-subtitle">Distribution of positive sentiment percentages by category</p>

                <div class="chart-wrapper">
                    <div class="sentiment-chart-container">
                        <canvas id="sentimentDistributionChart"></canvas>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <!-- Placeholder for single category -->
            <div class="chart-container">
                <div class="chart-loading">
                    <div class="text-center">
                        <i class="fas fa-chart-line text-gray-400 text-4xl mb-4"></i>
                        <h3 class="text-lg font-semibold text-gray-900 mb-2">Category Comparison</h3>
                        <p class="text-gray-600">Category comparison available with multiple categories</p>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Category Sentiment Comparison (Full Width) -->
        <?php if (count($category_sentiment_stats) > 1): ?>
        <div class="charts-grid-full">
            <div class="chart-container">
                <h3 class="chart-title">
                    <i class="fas fa-chart-line text-green-500"></i>Detailed Category Sentiment Analysis
                </h3>
                <p class="chart-subtitle">Comprehensive analysis of sentiment patterns and emotion distribution</p>

                <div class="charts-grid">
                    <!-- Emotion Analysis by Category -->
                    <div class="chart-container">
                        <h4 class="chart-title">
                            <i class="fas fa-heart text-pink-500"></i>Top Emotions by Category
                        </h4>
                        <div class="chart-wrapper">
                            <div class="sentiment-chart-container">
                                <canvas id="emotionAnalysisChart"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- Sentiment Trend Analysis -->
                    <div class="chart-container">
                        <h4 class="chart-title">
                            <i class="fas fa-chart-bar text-blue-500"></i>Sentiment Score Breakdown
                        </h4>
                        <div class="chart-wrapper">
                            <div class="sentiment-chart-container">
                                <canvas id="sentimentBreakdownChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Chart Legend -->
                <div class="chart-legend">
                    <div class="legend-item">
                        <div class="legend-color" style="background-color: rgba(34, 197, 94, 0.8);"></div>
                        <span>Positive</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color" style="background-color: rgba(107, 114, 128, 0.8);"></div>
                        <span>Neutral</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color" style="background-color: rgba(239, 68, 68, 0.8);"></div>
                        <span>Negative</span>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Category Performance Summary Table -->
<?php if (count($category_sentiment_stats) > 1): ?>
<div class="bg-white rounded-lg shadow-md overflow-hidden mb-8">
    <div class="px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-blue-500 to-blue-600">
        <h2 class="text-lg font-semibold text-white flex items-center">
            <i class="fas fa-table mr-3"></i>Category Sentiment Summary
        </h2>
    </div>
    <div class="p-6">
        <div class="overflow-x-auto sentiment-summary-table">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Evaluation Type</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Avg Sentiment</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Positive %</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Negative %</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Neutral %</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Top Emotion</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($category_sentiment_stats as $stats): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                <?php echo htmlspecialchars($stats['category_name']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 py-1 text-xs rounded-full
                                    <?php
                                    switch($stats['evaluation_type']) {
                                        case 'student_to_teacher':
                                            echo 'bg-blue-100 text-blue-800';
                                            break;
                                        case 'peer_to_peer':
                                            echo 'bg-purple-100 text-purple-800';
                                            break;
                                        case 'head_to_teacher':
                                            echo 'bg-indigo-100 text-indigo-800';
                                            break;
                                    }
                                    ?>">
                                    <?php echo ucwords(str_replace('_', ' ', $stats['evaluation_type'])); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <span class="font-bold <?php echo $stats['average_sentiment_score'] >= 0.3 ? 'text-green-600' : ($stats['average_sentiment_score'] <= -0.3 ? 'text-red-600' : 'text-yellow-600'); ?>">
                                    <?php echo number_format($stats['average_sentiment_score'], 3); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <span class="text-green-600 font-medium"><?php echo $stats['positive_percentage']; ?>%</span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <span class="text-red-600 font-medium"><?php echo $stats['negative_percentage']; ?>%</span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <span class="text-gray-600 font-medium"><?php echo $stats['neutral_percentage']; ?>%</span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?php
                                $max_emotion = array_keys($stats['emotions'], max($stats['emotions']));
                                $top_emotion = $max_emotion[0];
                                $emotion_icons = [
                                    'happy' => 'fas fa-smile text-yellow-500',
                                    'sad' => 'fas fa-sad-tear text-blue-500',
                                    'angry' => 'fas fa-angry text-red-500',
                                    'neutral' => 'fas fa-meh text-gray-500',
                                    'confused' => 'fas fa-question-circle text-purple-500',
                                    'frustrated' => 'fas fa-exclamation-triangle text-orange-500',
                                    'excited' => 'fas fa-star text-green-500',
                                    'concerned' => 'fas fa-exclamation-circle text-indigo-500'
                                ];
                                ?>
                                <span class="inline-flex items-center">
                                    <i class="<?php echo $emotion_icons[$top_emotion]; ?> mr-2"></i>
                                    <?php echo ucfirst($top_emotion); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php
                                $status_color = '';
                                $status_text = '';
                                if ($stats['average_sentiment_score'] >= 0.3) {
                                    $status_color = 'sentiment-status-positive';
                                    $status_text = 'Positive';
                                } elseif ($stats['average_sentiment_score'] <= -0.3) {
                                    $status_color = 'sentiment-status-negative';
                                    $status_text = 'Needs Attention';
                                } else {
                                    $status_color = 'sentiment-status-neutral';
                                    $status_text = 'Neutral';
                                }
                                ?>
                                <span class="sentiment-status-indicator <?php echo $status_color; ?>">
                                    <?php echo $status_text; ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Sentiment Analysis Charts JavaScript -->
<!-- Include Chart.js library with fallback -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js"></script>
<script>
// Fallback Chart.js loading
if (typeof Chart === 'undefined') {
    console.log('Primary Chart.js CDN failed, trying fallback...');
    const script = document.createElement('script');
    script.src = 'https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.js';
    script.onload = function() {
        console.log('Fallback Chart.js loaded successfully');
        initializeCharts();
    };
    script.onerror = function() {
        console.error('Both Chart.js CDNs failed to load');
        showChartError();
    };
    document.head.appendChild(script);
} else {
    console.log('Chart.js loaded successfully');
    initializeCharts();
}

function showChartError() {
    const chartContainers = document.querySelectorAll('.sentiment-chart-container');
    chartContainers.forEach(container => {
        container.innerHTML = `
            <div class="chart-loading">
                <div class="text-center">
                    <i class="fas fa-exclamation-triangle text-red-500 text-4xl mb-4"></i>
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">Chart Loading Error</h3>
                    <p class="text-gray-600">Unable to load chart library. Please refresh the page or check your internet connection.</p>
                </div>
            </div>
        `;
    });
}

function initializeCharts() {
    document.addEventListener('DOMContentLoaded', function() {
        <?php if (count($category_sentiment_stats) > 1): ?>

        console.log('Initializing sentiment charts...');

        // Check if Chart.js is loaded
        if (typeof Chart === 'undefined') {
            console.error('Chart.js not loaded');
            showChartError();
            return;
        }

        // Prepare data for charts
        const categoryData = {
            labels: [
                <?php
                $category_labels = [];
                $category_scores = [];
                $category_positive = [];
                $category_negative = [];
                $category_neutral = [];
                $category_emotions = [];

                foreach ($category_sentiment_stats as $stats) {
                    $category_labels[] = "'" . addslashes($stats['category_name']) . "'";
                    $category_scores[] = $stats['average_sentiment_score'];
                    $category_positive[] = $stats['positive_percentage'];
                    $category_negative[] = $stats['negative_percentage'];
                    $category_neutral[] = $stats['neutral_percentage'];

                    // Get top emotion for each category
                    $max_emotion = array_keys($stats['emotions'], max($stats['emotions']));
                    $category_emotions[] = $max_emotion[0];
                }
                echo implode(', ', $category_labels);
                ?>
            ],
            scores: [<?php echo implode(', ', $category_scores); ?>],
            positive: [<?php echo implode(', ', $category_positive); ?>],
            negative: [<?php echo implode(', ', $category_negative); ?>],
            neutral: [<?php echo implode(', ', $category_neutral); ?>],
            emotions: [<?php echo "'" . implode("', '", $category_emotions) . "'"; ?>]
        };

        console.log('Category data:', categoryData);

        // Chart instances
        let categorySentimentChart = null;
        let sentimentDistributionChart = null;
        let emotionAnalysisChart = null;
        let sentimentBreakdownChart = null;

        // Initialize Category Sentiment Comparison Chart
        function initCategorySentimentChart(type = 'line') {
            console.log('Initializing category sentiment chart with type:', type);

            const categoryCtx = document.getElementById('categorySentimentChart');
            if (!categoryCtx) {
                console.error('Category sentiment chart canvas not found');
                return;
            }

            // Clear loading state and create new canvas
            const container = categoryCtx.parentElement;
            container.innerHTML = '<canvas id="categorySentimentChart"></canvas>';

            const newCtx = document.getElementById('categorySentimentChart').getContext('2d');

            // Destroy existing chart if it exists
            if (categorySentimentChart) {
                categorySentimentChart.destroy();
            }

            const chartData = {
                labels: categoryData.labels,
                datasets: [{
                    label: 'Average Sentiment Score',
                    data: categoryData.scores,
                    backgroundColor: type === 'radar' ? 'rgba(99, 102, 241, 0.2)' : 'rgba(99, 102, 241, 0.8)',
                    borderColor: 'rgba(99, 102, 241, 1)',
                    borderWidth: 2,
                    fill: type === 'line',
                    tension: type === 'line' ? 0.4 : 0
                }]
            };

            const options = {
                responsive: true,
                maintainAspectRatio: false,
                scales: type !== 'radar' ? {
                    y: {
                        beginAtZero: false,
                        min: -1,
                        max: 1,
                        ticks: {
                            stepSize: 0.5
                        }
                    }
                } : undefined,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return `Sentiment Score: ${context.parsed.y.toFixed(3)}`;
                            }
                        }
                    }
                }
            };

            try {
                categorySentimentChart = new Chart(newCtx, {
                    type: type,
                    data: chartData,
                    options: options
                });
                console.log('Category sentiment chart created successfully');
            } catch (error) {
                console.error('Error creating category sentiment chart:', error);
            }
        }

        // Initialize Sentiment Distribution Chart
        function initSentimentDistributionChart() {
            console.log('Initializing sentiment distribution chart');

            const distributionCtx = document.getElementById('sentimentDistributionChart');
            if (!distributionCtx) {
                console.error('Sentiment distribution chart canvas not found');
                return;
            }

            // Clear loading state and create new canvas
            const container = distributionCtx.parentElement;
            container.innerHTML = '<canvas id="sentimentDistributionChart"></canvas>';

            const newCtx = document.getElementById('sentimentDistributionChart').getContext('2d');

            if (sentimentDistributionChart) {
                sentimentDistributionChart.destroy();
            }

            try {
                sentimentDistributionChart = new Chart(newCtx, {
                    type: 'doughnut',
                    data: {
                        labels: categoryData.labels,
                        datasets: [{
                            data: categoryData.positive,
                            backgroundColor: [
                                'rgba(34, 197, 94, 0.8)',
                                'rgba(59, 130, 246, 0.8)',
                                'rgba(147, 51, 234, 0.8)',
                                'rgba(245, 158, 11, 0.8)',
                                'rgba(239, 68, 68, 0.8)'
                            ],
                            borderWidth: 2,
                            borderColor: '#ffffff'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom'
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        return `${context.label}: ${context.parsed}% positive`;
                                    }
                                }
                            }
                        }
                    }
                });
                console.log('Sentiment distribution chart created successfully');
            } catch (error) {
                console.error('Error creating sentiment distribution chart:', error);
            }
        }

        // Initialize Emotion Analysis Chart
        function initEmotionAnalysisChart() {
            console.log('Initializing emotion analysis chart');

            const emotionCtx = document.getElementById('emotionAnalysisChart');
            if (!emotionCtx) {
                console.error('Emotion analysis chart canvas not found');
                return;
            }

            // Clear loading state and create new canvas
            const container = emotionCtx.parentElement;
            container.innerHTML = '<canvas id="emotionAnalysisChart"></canvas>';

            const newCtx = document.getElementById('emotionAnalysisChart').getContext('2d');

            if (emotionAnalysisChart) {
                emotionAnalysisChart.destroy();
            }

            // Count emotions across all categories
            const emotionCounts = {};
            categoryData.emotions.forEach(emotion => {
                emotionCounts[emotion] = (emotionCounts[emotion] || 0) + 1;
            });

            const emotionLabels = Object.keys(emotionCounts);
            const emotionData = Object.values(emotionCounts);

            try {
                emotionAnalysisChart = new Chart(newCtx, {
                    type: 'bar',
                    data: {
                        labels: emotionLabels.map(e => e.charAt(0).toUpperCase() + e.slice(1)),
                        datasets: [{
                            label: 'Categories with this top emotion',
                            data: emotionData,
                            backgroundColor: [
                                'rgba(34, 197, 94, 0.8)',
                                'rgba(59, 130, 246, 0.8)',
                                'rgba(239, 68, 68, 0.8)',
                                'rgba(107, 114, 128, 0.8)',
                                'rgba(147, 51, 234, 0.8)',
                                'rgba(245, 158, 11, 0.8)',
                                'rgba(16, 185, 129, 0.8)',
                                'rgba(99, 102, 241, 0.8)'
                            ],
                            borderWidth: 1,
                            borderColor: '#ffffff'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    stepSize: 1
                                }
                            }
                        },
                        plugins: {
                            legend: {
                                display: false
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        return `${context.parsed.y} categories`;
                                    }
                                }
                            }
                        }
                    }
                });
                console.log('Emotion analysis chart created successfully');
            } catch (error) {
                console.error('Error creating emotion analysis chart:', error);
            }
        }

        // Initialize Sentiment Breakdown Chart
        function initSentimentBreakdownChart() {
            console.log('Initializing sentiment breakdown chart');

            const breakdownCtx = document.getElementById('sentimentBreakdownChart');
            if (!breakdownCtx) {
                console.error('Sentiment breakdown chart canvas not found');
                return;
            }

            // Clear loading state and create new canvas
            const container = breakdownCtx.parentElement;
            container.innerHTML = '<canvas id="sentimentBreakdownChart"></canvas>';

            const newCtx = document.getElementById('sentimentBreakdownChart').getContext('2d');

            if (sentimentBreakdownChart) {
                sentimentBreakdownChart.destroy();
            }

            try {
                sentimentBreakdownChart = new Chart(newCtx, {
                    type: 'bar',
                    data: {
                        labels: categoryData.labels,
                        datasets: [
                            {
                                label: 'Positive',
                                data: categoryData.positive,
                                backgroundColor: 'rgba(34, 197, 94, 0.8)',
                                borderColor: 'rgba(34, 197, 94, 1)',
                                borderWidth: 1
                            },
                            {
                                label: 'Neutral',
                                data: categoryData.neutral,
                                backgroundColor: 'rgba(107, 114, 128, 0.8)',
                                borderColor: 'rgba(107, 114, 128, 1)',
                                borderWidth: 1
                            },
                            {
                                label: 'Negative',
                                data: categoryData.negative,
                                backgroundColor: 'rgba(239, 68, 68, 0.8)',
                                borderColor: 'rgba(239, 68, 68, 1)',
                                borderWidth: 1
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            x: {
                                stacked: true
                            },
                            y: {
                                stacked: true,
                                beginAtZero: true,
                                max: 100,
                                ticks: {
                                    callback: function(value) {
                                        return value + '%';
                                    }
                                }
                            }
                        },
                        plugins: {
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        return `${context.dataset.label}: ${context.parsed.y}%`;
                                    }
                                }
                            }
                        }
                    }
                });
                console.log('Sentiment breakdown chart created successfully');
            } catch (error) {
                console.error('Error creating sentiment breakdown chart:', error);
            }
        }

        // Initialize all charts
        try {
            console.log('Starting chart initialization...');
            initCategorySentimentChart('line');
            initSentimentDistributionChart();
            initEmotionAnalysisChart();
            initSentimentBreakdownChart();
            console.log('All charts initialized successfully');
        } catch (error) {
            console.error('Error during chart initialization:', error);
        }

        // Chart type selector functionality
        const chartTypeSelector = document.getElementById('chartTypeSelector');
        if (chartTypeSelector) {
            chartTypeSelector.addEventListener('change', function() {
                console.log('Chart type changed to:', this.value);
                initCategorySentimentChart(this.value);
            });
        }

        <?php else: ?>
        console.log('No category sentiment stats available for charts');
        <?php endif; ?>
    });
}
</script>

<!-- Performance Trends Chart -->
<div class="bg-white rounded-lg shadow-md overflow-hidden mb-8">
    <div class="px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-green-500 to-green-600">
        <h2 class="text-lg font-semibold text-white flex items-center">
            <i class="fas fa-chart-area mr-3"></i>Performance Trends by Semester
        </h2>
    </div>
    <div class="p-6">
        <div class="chart-wrapper">
            <div class="sentiment-chart-container">
                <canvas id="performanceChart"></canvas>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Evaluation Categories Performance -->
<?php if ($overall_stats['total_evaluations'] > 0): ?>
<div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
    <!-- Student to Teacher Evaluation -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-blue-500 to-blue-600">
            <h2 class="text-lg font-semibold text-white flex items-center">
                <i class="fas fa-user-graduate mr-3"></i>Student to Teacher Evaluation
            </h2>
        </div>
        <div class="p-6">
            <?php
            $student_evaluations = array_filter($categories, function($cat) { return $cat['evaluation_type'] === 'student_to_teacher'; });
            if (!empty($student_evaluations)):
            ?>
                <div class="space-y-4">
                    <?php foreach ($student_evaluations as $category): ?>
                        <div class="border border-gray-200 rounded-lg p-4">
                            <div class="flex justify-between items-center mb-2">
                                <h3 class="font-medium text-gray-900"><?php echo htmlspecialchars($category['category_name']); ?></h3>
                                <span class="text-lg font-bold text-seait-orange">
                                    <?php echo number_format($category['average_rating'] ?? 0, 2); ?>/5.00
                                </span>
                            </div>
                            <div class="flex items-center justify-between text-sm text-gray-600">
                                <span><?php echo $category['total_evaluations']; ?> evaluations</span>
                                <div class="flex items-center">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="fas fa-star <?php echo $i <= ($category['average_rating'] ?? 0) ? 'text-yellow-400' : 'text-gray-300'; ?> mr-1"></i>
                                    <?php endfor; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="text-gray-500 text-center py-4">No student evaluations available</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Peer to Peer Evaluation -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-purple-500 to-purple-600">
            <h2 class="text-lg font-semibold text-white flex items-center">
                <i class="fas fa-users mr-3"></i>Peer to Peer Evaluation
            </h2>
        </div>
        <div class="p-6">
            <?php
            $peer_evaluations = array_filter($categories, function($cat) { return $cat['evaluation_type'] === 'peer_to_peer'; });
            if (!empty($peer_evaluations)):
            ?>
                <div class="space-y-4">
                    <?php foreach ($peer_evaluations as $category): ?>
                        <div class="border border-gray-200 rounded-lg p-4">
                            <div class="flex justify-between items-center mb-2">
                                <h3 class="font-medium text-gray-900"><?php echo htmlspecialchars($category['category_name']); ?></h3>
                                <span class="text-lg font-bold text-seait-orange">
                                    <?php echo number_format($category['average_rating'] ?? 0, 2); ?>/5.00
                                </span>
                            </div>
                            <div class="flex items-center justify-between text-sm text-gray-600">
                                <span><?php echo $category['total_evaluations']; ?> evaluations</span>
                                <div class="flex items-center">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="fas fa-star <?php echo $i <= ($category['average_rating'] ?? 0) ? 'text-yellow-400' : 'text-gray-300'; ?> mr-1"></i>
                                    <?php endfor; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="text-gray-500 text-center py-4">No peer evaluations available</p>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Head to Teacher Evaluation -->
<?php if ($overall_stats['total_evaluations'] > 0): ?>
<div class="bg-white rounded-lg shadow-md overflow-hidden mb-8">
    <div class="px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-indigo-500 to-indigo-600">
        <h2 class="text-lg font-semibold text-white flex items-center">
            <i class="fas fa-user-tie mr-3"></i>Head to Teacher Evaluation
        </h2>
    </div>
    <div class="p-6">
        <?php
        $head_evaluations = array_filter($categories, function($cat) { return $cat['evaluation_type'] === 'head_to_teacher'; });
        if (!empty($head_evaluations)):
        ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <?php foreach ($head_evaluations as $category): ?>
                    <div class="border border-gray-200 rounded-lg p-4">
                        <div class="flex justify-between items-center mb-2">
                            <h3 class="font-medium text-gray-900"><?php echo htmlspecialchars($category['category_name']); ?></h3>
                            <span class="text-lg font-bold text-seait-orange">
                                <?php echo number_format($category['average_rating'] ?? 0, 2); ?>/5.00
                            </span>
                        </div>
                        <div class="flex items-center justify-between text-sm text-gray-600">
                            <span><?php echo $category['total_evaluations']; ?> evaluations</span>
                            <div class="flex items-center">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="fas fa-star <?php echo $i <= ($category['average_rating'] ?? 0) ? 'text-yellow-400' : 'text-gray-300'; ?> mr-1"></i>
                                <?php endfor; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="text-gray-500 text-center py-4">No head evaluations available</p>
        <?php endif; ?>
    </div>
</div>

<!-- Sub-Category Performance Analysis -->
<div class="bg-white rounded-lg shadow-md overflow-hidden mb-8">
    <div class="px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-teal-500 to-teal-600">
        <h2 class="text-lg font-semibold text-white flex items-center">
            <i class="fas fa-chart-bar mr-3"></i>Sub-Category Performance Analysis
        </h2>
    </div>
    <div class="p-6">
        <?php if (!empty($subcategory_performance)): ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sub-Category</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Average Rating</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Evaluations</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Performance</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($subcategory_performance as $subcategory): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 py-1 text-xs rounded-full
                                        <?php
                                        switch($subcategory['evaluation_type']) {
                                            case 'student_to_teacher':
                                                echo 'bg-blue-100 text-blue-800';
                                                break;
                                            case 'peer_to_peer':
                                                echo 'bg-purple-100 text-purple-800';
                                                break;
                                            case 'head_to_teacher':
                                                echo 'bg-indigo-100 text-indigo-800';
                                                break;
                                        }
                                        ?>">
                                        <?php echo ucwords(str_replace('_', ' ', $subcategory['evaluation_type'])); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    <?php echo htmlspecialchars($subcategory['subcategory_name']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <span class="font-bold text-seait-orange">
                                        <?php echo number_format($subcategory['average_rating'] ?? 0, 2); ?>/5.00
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo $subcategory['evaluation_count']; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="fas fa-star <?php echo $i <= ($subcategory['average_rating'] ?? 0) ? 'text-yellow-400' : 'text-gray-300'; ?> mr-1"></i>
                                        <?php endfor; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="text-gray-500 text-center py-4">No sub-category performance data available</p>
        <?php endif; ?>
    </div>
</div>

<!-- Training and Seminar Suggestions -->
<?php if (!empty($training_suggestions)): ?>
<div class="bg-white rounded-lg shadow-md overflow-hidden mb-8">
    <div class="px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-yellow-500 to-yellow-600">
        <h2 class="text-lg font-semibold text-white flex items-center">
            <i class="fas fa-lightbulb mr-3"></i>Training and Seminar Suggestions
        </h2>
        <p class="text-yellow-100 text-sm mt-1">Based on evaluation scores below 4.0</p>
    </div>
    <div class="p-6">
        <!-- Tab Navigation -->
        <div class="border-b border-gray-200 mb-6">
            <nav class="-mb-px flex space-x-8 overflow-x-auto" aria-label="Tabs">
                <?php foreach ($training_suggestions as $index => $suggestion): ?>
                    <button onclick="showTrainingTab(<?php echo $index; ?>)"
                            class="training-tab-btn whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm transition-colors duration-200 <?php echo $index === 0 ? 'border-yellow-500 text-yellow-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?>"
                            id="tab-btn-<?php echo $index; ?>">
                        <i class="fas fa-chalkboard-teacher mr-2"></i>
                        <?php echo htmlspecialchars($suggestion['category']['category_name']); ?>
                        <span class="ml-2 px-2 py-1 text-xs rounded-full bg-red-100 text-red-800">
                            <?php echo number_format($suggestion['category']['average_rating'] ?? 0, 2); ?>
                        </span>
                    </button>
                <?php endforeach; ?>
            </nav>
        </div>

        <!-- Tab Content -->
        <div class="space-y-6">
            <?php foreach ($training_suggestions as $index => $suggestion): ?>
                <div class="training-tab-content <?php echo $index === 0 ? 'block' : 'hidden'; ?>"
                     id="tab-content-<?php echo $index; ?>">

                    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-semibold text-yellow-800">
                                <?php echo htmlspecialchars($suggestion['category']['category_name']); ?>
                            </h3>
                            <span class="px-3 py-1 bg-red-100 text-red-800 text-sm font-medium rounded-full">
                                Score: <?php echo number_format($suggestion['category']['average_rating'] ?? 0, 2); ?>/5.00
                            </span>
                        </div>

                        <div class="mb-4">
                            <p class="text-sm text-yellow-700 mb-2">
                                <strong>Evaluation Type:</strong>
                                <span class="capitalize"><?php echo str_replace('_', ' ', $suggestion['category']['evaluation_type']); ?></span>
                            </p>
                            <p class="text-sm text-yellow-700">
                                <strong>Total Evaluations:</strong> <?php echo $suggestion['category']['total_evaluations']; ?>
                            </p>
                        </div>

                        <div class="border-t border-yellow-200 pt-4">
                            <h4 class="font-medium text-yellow-800 mb-3">Available Training Programs & Seminars:</h4>
                            <?php if (!empty($suggestion['trainings'])): ?>
                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                    <?php foreach ($suggestion['trainings'] as $training): ?>
                                        <div class="bg-white border border-yellow-300 rounded-lg p-4 hover:shadow-md transition-shadow">
                                            <div class="flex items-start justify-between mb-2">
                                                <span class="px-2 py-1 text-xs rounded-full
                                                    <?php
                                                    switch($training['type']) {
                                                        case 'training':
                                                            echo 'bg-blue-100 text-blue-800';
                                                            break;
                                                        case 'seminar':
                                                            echo 'bg-green-100 text-green-800';
                                                            break;
                                                        case 'workshop':
                                                            echo 'bg-purple-100 text-purple-800';
                                                            break;
                                                        case 'conference':
                                                            echo 'bg-orange-100 text-orange-800';
                                                            break;
                                                    }
                                                    ?>">
                                                    <?php echo ucfirst($training['type']); ?>
                                                </span>
                                                <span class="px-2 py-1 text-xs rounded-full
                                                    <?php
                                                    switch($training['status']) {
                                                        case 'published':
                                                            echo 'bg-green-100 text-green-800';
                                                            break;
                                                        case 'draft':
                                                            echo 'bg-gray-100 text-gray-800';
                                                            break;
                                                        case 'ongoing':
                                                            echo 'bg-blue-100 text-blue-800';
                                                            break;
                                                        case 'completed':
                                                            echo 'bg-purple-100 text-purple-800';
                                                            break;
                                                        case 'cancelled':
                                                            echo 'bg-red-100 text-red-800';
                                                            break;
                                                    }
                                                    ?>">
                                                    <?php echo ucfirst($training['status']); ?>
                                                </span>
                                            </div>

                                            <h5 class="font-medium text-gray-900 mb-2">
                                                <?php echo htmlspecialchars($training['title']); ?>
                                            </h5>

                                            <?php if ($training['description']): ?>
                                                <p class="text-sm text-gray-600 mb-3">
                                                    <?php echo htmlspecialchars(substr($training['description'], 0, 100)); ?>
                                                    <?php if (strlen($training['description']) > 100): ?>...<?php endif; ?>
                                                </p>
                                            <?php endif; ?>

                                            <div class="space-y-1 text-xs text-gray-600">
                                                <?php if ($training['start_date']): ?>
                                                    <div class="flex items-center">
                                                        <i class="fas fa-calendar-alt mr-2 text-yellow-600"></i>
                                                        <span><?php echo date('M d, Y', strtotime($training['start_date'])); ?></span>
                                                    </div>
                                                <?php endif; ?>

                                                <?php if ($training['venue']): ?>
                                                    <div class="flex items-center">
                                                        <i class="fas fa-map-marker-alt mr-2 text-yellow-600"></i>
                                                        <span><?php echo htmlspecialchars($training['venue']); ?></span>
                                                    </div>
                                                <?php endif; ?>

                                                <?php if ($training['duration_hours']): ?>
                                                    <div class="flex items-center">
                                                        <i class="fas fa-clock mr-2 text-yellow-600"></i>
                                                        <span><?php echo $training['duration_hours']; ?> hours</span>
                                                    </div>
                                                <?php endif; ?>
                                            </div>

                                            <div class="mt-4 flex space-x-2">
                                                <a href="view-training.php?id=<?php echo $training['id']; ?>"
                                                   class="flex-1 bg-yellow-600 text-white text-center py-2 px-3 rounded text-sm hover:bg-yellow-700 transition-colors">
                                                    <i class="fas fa-eye mr-1"></i>View Details
                                                </a>
                                                <?php if ($training['status'] === 'published'): ?>
                                                    <a href="trainings.php?category=<?php echo $training['category_id']; ?>"
                                                       class="flex-1 bg-blue-600 text-white text-center py-2 px-3 rounded text-sm hover:bg-blue-700 transition-colors">
                                                        <i class="fas fa-plus mr-1"></i>Register
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 text-center">
                                    <i class="fas fa-info-circle text-gray-400 text-2xl mb-2"></i>
                                    <p class="text-gray-600">No specific training programs or seminars are currently available for this category.</p>
                                    <p class="text-sm text-gray-500 mt-1">Check back later for new opportunities or contact the guidance office.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="mt-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
            <div class="flex items-start">
                <i class="fas fa-info-circle text-blue-500 mt-1 mr-3"></i>
                <div>
                    <h4 class="font-medium text-blue-800 mb-2">About Training Suggestions</h4>
                    <p class="text-sm text-blue-700 mb-2">
                        These suggestions are based on evaluation scores below 4.0 in specific categories.
                        Training programs and seminars are recommended to help improve performance in these areas.
                    </p>
                    <p class="text-sm text-blue-700">
                        <strong>Note:</strong> All available training programs and seminars are shown regardless of their scheduled status.
                        Contact the guidance office for registration and scheduling information.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript for Tab Functionality -->
<script>
function showTrainingTab(tabIndex) {
    // Hide all tab contents
    const tabContents = document.querySelectorAll('.training-tab-content');
    tabContents.forEach(content => {
        content.classList.add('hidden');
    });

    // Remove active state from all tab buttons
    const tabButtons = document.querySelectorAll('.training-tab-btn');
    tabButtons.forEach(btn => {
        btn.classList.remove('border-yellow-500', 'text-yellow-600');
        btn.classList.add('border-transparent', 'text-gray-500');
    });

    // Show selected tab content
    const selectedContent = document.getElementById('tab-content-' + tabIndex);
    if (selectedContent) {
        selectedContent.classList.remove('hidden');
        selectedContent.classList.add('block');
    }

    // Add active state to selected tab button
    const selectedButton = document.getElementById('tab-btn-' + tabIndex);
    if (selectedButton) {
        selectedButton.classList.remove('border-transparent', 'text-gray-500');
        selectedButton.classList.add('border-yellow-500', 'text-yellow-600');
    }
}
</script>
<?php endif; ?>

<!-- Semester-wise Performance -->
<div class="bg-white rounded-lg shadow-md overflow-hidden mb-8">
    <div class="px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-pink-500 to-pink-600">
        <h2 class="text-lg font-semibold text-white flex items-center">
            <i class="fas fa-calendar-week mr-3"></i>Semester-wise Performance
        </h2>
    </div>
    <div class="p-6">
        <?php if (!empty($semesters)): ?>
            <div class="space-y-6">
                <?php foreach ($semesters as $semester): ?>
                    <div class="border border-gray-200 rounded-lg p-6">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-medium text-gray-900">
                                <?php echo htmlspecialchars($semester['name']); ?> - <?php echo htmlspecialchars($semester['academic_year']); ?>
                            </h3>
                            <span class="text-sm text-gray-600">
                                <?php echo date('M d, Y', strtotime($semester['start_date'])); ?> -
                                <?php echo date('M d, Y', strtotime($semester['end_date'])); ?>
                            </span>
                        </div>

                        <?php
                        $semester_data = array_filter($semester_performance, function($perf) use ($semester) {
                            return $perf['semester_id'] == $semester['id'];
                        });
                        ?>

                        <?php if (!empty($semester_data)): ?>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <?php foreach ($semester_data as $performance): ?>
                                    <div class="bg-gray-50 rounded-lg p-4">
                                        <div class="flex justify-between items-center mb-2">
                                            <span class="text-sm font-medium text-gray-700">
                                                <?php echo ucwords(str_replace('_', ' ', $performance['evaluation_type'])); ?>
                                            </span>
                                            <span class="text-lg font-bold text-seait-orange">
                                                <?php echo number_format($performance['average_rating'] ?? 0, 2); ?>/5.00
                                            </span>
                                        </div>
                                        <div class="flex items-center justify-between text-xs text-gray-600">
                                            <span><?php echo $performance['evaluation_count']; ?> evaluations</span>
                                            <div class="flex items-center">
                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                    <i class="fas fa-star <?php echo $i <= ($performance['average_rating'] ?? 0) ? 'text-yellow-400' : 'text-gray-300'; ?> mr-1"></i>
                                                <?php endfor; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-gray-500 text-center py-4">No evaluation data for this semester</p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="text-gray-500 text-center py-4">No semester data available</p>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- Chart.js for Performance Trends -->
<?php if ($overall_stats['total_evaluations'] > 0 && !empty($semester_performance)): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    console.log('Initializing performance trends chart...');

    // Check if Chart.js is loaded
    if (typeof Chart === 'undefined') {
        console.error('Chart.js not loaded for performance chart');
        return;
    }

    // Prepare data for the performance chart
    const semesterData = <?php echo json_encode($semester_performance); ?>;
    const semesters = <?php echo json_encode($semesters); ?>;

    console.log('Semester data:', semesterData);
    console.log('Semesters:', semesters);

    // Group data by semester and evaluation type
    const chartData = {};
    semesters.forEach(semester => {
        chartData[semester.name + ' ' + semester.academic_year] = {
            student_to_teacher: null,
            peer_to_peer: null,
            head_to_teacher: null
        };
    });

    semesterData.forEach(data => {
        const semesterKey = data.semester_name + ' ' + data.academic_year;
        if (chartData[semesterKey]) {
            chartData[semesterKey][data.evaluation_type] = parseFloat(data.average_rating);
        }
    });

    console.log('Processed chart data:', chartData);

    // Create chart
    const performanceCtx = document.getElementById('performanceChart');
    if (!performanceCtx) {
        console.error('Performance chart canvas not found');
        return;
    }

    try {
        const ctx = performanceCtx.getContext('2d');
        const performanceChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: Object.keys(chartData),
                datasets: [
                    {
                        label: 'Student to Teacher',
                        data: Object.values(chartData).map(d => d.student_to_teacher),
                        borderColor: 'rgb(59, 130, 246)',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        tension: 0.1,
                        fill: false
                    },
                    {
                        label: 'Peer to Peer',
                        data: Object.values(chartData).map(d => d.peer_to_peer),
                        borderColor: 'rgb(147, 51, 234)',
                        backgroundColor: 'rgba(147, 51, 234, 0.1)',
                        tension: 0.1,
                        fill: false
                    },
                    {
                        label: 'Head to Teacher',
                        data: Object.values(chartData).map(d => d.head_to_teacher),
                        borderColor: 'rgb(99, 102, 241)',
                        backgroundColor: 'rgba(99, 102, 241, 0.1)',
                        tension: 0.1,
                        fill: false
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 5,
                        ticks: {
                            stepSize: 1
                        }
                    }
                },
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    title: {
                        display: true,
                        text: 'Performance Trends Over Semesters'
                    }
                }
            }
        });
        console.log('Performance trends chart created successfully');
    } catch (error) {
        console.error('Error creating performance trends chart:', error);
    }
});
</script>
<?php endif; ?>
<?php else: ?>
<!-- No Sentiment Data Available -->
<div class="bg-white rounded-lg shadow-md overflow-hidden mb-8">
    <div class="px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-indigo-500 to-indigo-600">
        <h2 class="text-lg font-semibold text-white flex items-center">
            <i class="fas fa-brain mr-3"></i>Sentiment Analysis of Feedback
        </h2>
    </div>
    <div class="p-6">
        <div class="text-center py-8">
            <div class="flex items-center justify-center mb-4">
                <i class="fas fa-comment-slash text-indigo-400 text-6xl"></i>
            </div>
            <h3 class="text-xl font-semibold text-gray-900 mb-2">No Text Feedback Available</h3>
            <p class="text-gray-600 mb-4">
                There are currently no text responses or comments available for sentiment analysis.
            </p>
            <div class="bg-indigo-50 border border-indigo-200 rounded-lg p-4 max-w-md mx-auto">
                <h4 class="font-medium text-indigo-800 mb-2">About Sentiment Analysis:</h4>
                <ul class="text-sm text-indigo-700 space-y-1">
                    <li>• Analyzes text responses from evaluation forms</li>
                    <li>• Identifies positive, negative, and neutral sentiments</li>
                    <li>• Detects emotions like happy, sad, angry, confused, etc.</li>
                    <li>• Provides insights into student/peer feedback quality</li>
                </ul>
            </div>
            <div class="mt-6 flex justify-center space-x-4">
                <a href="conduct-evaluation.php?evaluatee_id=<?php echo $teacher_id; ?>&evaluatee_type=teacher"
                   class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors duration-200">
                    <i class="fas fa-plus mr-2"></i>Conduct Evaluation
                </a>
                <a href="all-evaluations.php?evaluatee_id=<?php echo $teacher_id; ?>"
                   class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 transition-colors duration-200">
                    <i class="fas fa-search mr-2"></i>Check Evaluations
                </a>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php
// Include the shared footer
include 'includes/footer.php';
?>