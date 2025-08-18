<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

// Check if user is logged in and has faculty role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

$faculty_id = $_SESSION['user_id'];
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-01');
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-t');

// Get updated statistics
$stats_query = "SELECT
    COUNT(DISTINCT tc.id) as total_classes,
    COUNT(DISTINCT ce.student_id) as total_students,
    COUNT(DISTINCT cm.id) as total_materials,
    COUNT(DISTINCT ca.id) as total_announcements,
    COUNT(DISTINCT fe.id) as total_events
FROM teacher_classes tc
LEFT JOIN class_enrollments ce ON tc.id = ce.class_id
LEFT JOIN class_materials cm ON tc.id = cm.class_id
LEFT JOIN class_announcements ca ON tc.id = ca.class_id
LEFT JOIN faculty_events fe ON tc.id = fe.class_id
WHERE tc.faculty_id = ?
AND tc.created_at BETWEEN ? AND ?";

$stmt = mysqli_prepare($conn, $stats_query);
mysqli_stmt_bind_param($stmt, "iss", $faculty_id, $date_from, $date_to);
mysqli_stmt_execute($stmt);
$stats_result = mysqli_stmt_get_result($stmt);
$stats = mysqli_fetch_assoc($stats_result);

// Get evaluation statistics
$eval_query = "SELECT
    COUNT(DISTINCT es.id) as total_evaluations,
    AVG(er.rating) as average_rating,
    COUNT(DISTINCT es.evaluator_id) as unique_evaluators
FROM evaluation_sessions es
LEFT JOIN evaluation_responses er ON es.id = er.session_id
WHERE es.evaluatee_id = ?
AND es.created_at BETWEEN ? AND ?";

$stmt = mysqli_prepare($conn, $eval_query);
mysqli_stmt_bind_param($stmt, "iss", $faculty_id, $date_from, $date_to);
mysqli_stmt_execute($stmt);
$eval_result = mysqli_stmt_get_result($stmt);
$eval_stats = mysqli_fetch_assoc($eval_result);

// Get monthly trends
$trends_query = "SELECT
    DATE_FORMAT(created_at, '%Y-%m') as month,
    COUNT(*) as class_count
FROM teacher_classes
WHERE faculty_id = ?
AND created_at BETWEEN ? AND ?
GROUP BY DATE_FORMAT(created_at, '%Y-%m')
ORDER BY month DESC
LIMIT 6";

$stmt = mysqli_prepare($conn, $trends_query);
mysqli_stmt_bind_param($stmt, "iss", $faculty_id, $date_from, $date_to);
mysqli_stmt_execute($stmt);
$trends_result = mysqli_stmt_get_result($stmt);

$trends = [];
while ($trend = mysqli_fetch_assoc($trends_result)) {
    $trends[] = $trend;
}

// Get recent activities
$activities_query = "SELECT
    'class' as type,
    tc.subject_title as title,
    tc.created_at as date,
    'New class created' as description
FROM teacher_classes tc
WHERE tc.faculty_id = ? AND tc.created_at BETWEEN ? AND ?
UNION ALL
SELECT
    'material' as type,
    cm.title,
    cm.created_at as date,
    'New material uploaded' as description
FROM class_materials cm
JOIN teacher_classes tc ON cm.class_id = tc.id
WHERE tc.faculty_id = ? AND cm.created_at BETWEEN ? AND ?
UNION ALL
SELECT
    'announcement' as type,
    ca.title,
    ca.created_at as date,
    'New announcement posted' as description
FROM class_announcements ca
JOIN teacher_classes tc ON ca.class_id = tc.id
WHERE tc.faculty_id = ? AND ca.created_at BETWEEN ? AND ?
ORDER BY date DESC
LIMIT 10";

$stmt = mysqli_prepare($conn, $activities_query);
mysqli_stmt_bind_param($stmt, "isssssss", $faculty_id, $date_from, $date_to, $faculty_id, $date_from, $date_to, $faculty_id, $date_from, $date_to);
mysqli_stmt_execute($stmt);
$activities_result = mysqli_stmt_get_result($stmt);

$activities = [];
while ($activity = mysqli_fetch_assoc($activities_result)) {
    $activity['date'] = date('M j, Y g:i A', strtotime($activity['date']));
    $activities[] = $activity;
}

$response = [
    'success' => true,
    'data' => [
        'stats' => $stats,
        'evaluation_stats' => $eval_stats,
        'trends' => $trends,
        'activities' => $activities,
        'last_updated' => date('Y-m-d H:i:s')
    ]
];

echo json_encode($response);
?>