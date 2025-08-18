<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

// Check if user is logged in and has appropriate role
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'teacher'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if (!isset($_GET['quiz_id'])) {
    echo json_encode(['success' => false, 'message' => 'Quiz ID is required']);
    exit;
}

$quiz_id = (int)$_GET['quiz_id'];

// Verify quiz belongs to the teacher
$verify_query = "SELECT q.id FROM quizzes q
                JOIN teacher_classes tc ON q.class_id = tc.id
                WHERE q.id = ? AND tc.teacher_id = ?";
$verify_stmt = mysqli_prepare($conn, $verify_query);
mysqli_stmt_bind_param($verify_stmt, "ii", $quiz_id, $_SESSION['user_id']);
mysqli_stmt_execute($verify_stmt);
$verify_result = mysqli_stmt_get_result($verify_stmt);

if (mysqli_num_rows($verify_result) === 0) {
    echo json_encode(['success' => false, 'message' => 'Quiz not found or access denied']);
    exit;
}

// Get leaderboard data
$leaderboard_query = "SELECT
                        s.first_name, s.last_name, s.student_id,
                        qs.score, qs.total_questions, qs.correct_answers,
                        qs.time_taken, qs.completed_at,
                        ROUND((qs.correct_answers / qs.total_questions) * 100, 2) as percentage
                      FROM quiz_submissions qs
                      JOIN students s ON qs.student_id = s.id
                      WHERE qs.quiz_id = ? AND qs.status = 'completed'
                      ORDER BY qs.score DESC, qs.time_taken ASC, qs.completed_at ASC";
$leaderboard_stmt = mysqli_prepare($conn, $leaderboard_query);
mysqli_stmt_bind_param($leaderboard_stmt, "i", $quiz_id);
mysqli_stmt_execute($leaderboard_stmt);
$leaderboard_result = mysqli_stmt_get_result($leaderboard_stmt);

$leaderboard = [];
$rank = 1;
while ($row = mysqli_fetch_assoc($leaderboard_result)) {
    $row['rank'] = $rank;
    $leaderboard[] = $row;
    $rank++;
}

// Get quiz statistics
$stats_query = "SELECT
                  COUNT(*) as total_submissions,
                  COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_submissions,
                  AVG(CASE WHEN status = 'completed' THEN score END) as average_score,
                  MAX(CASE WHEN status = 'completed' THEN score END) as highest_score,
                  MIN(CASE WHEN status = 'completed' THEN time_taken END) as fastest_time
                FROM quiz_submissions
                WHERE quiz_id = ?";
$stats_stmt = mysqli_prepare($conn, $stats_query);
mysqli_stmt_bind_param($stats_stmt, "i", $quiz_id);
mysqli_stmt_execute($stats_stmt);
$stats_result = mysqli_stmt_get_result($stats_stmt);
$statistics = mysqli_fetch_assoc($stats_result);

// Get recent activity (last 10 activities)
$activity_query = "SELECT
                    s.first_name, s.last_name,
                    CASE
                        WHEN qs.status = 'started' THEN 'started the quiz'
                        WHEN qs.status = 'completed' THEN 'completed the quiz with ' || qs.score || ' points'
                        WHEN qs.status = 'abandoned' THEN 'abandoned the quiz'
                        ELSE 'updated their submission'
                    END as action,
                    qs.updated_at as timestamp
                  FROM quiz_submissions qs
                  JOIN students s ON qs.student_id = s.id
                  WHERE qs.quiz_id = ?
                  ORDER BY qs.updated_at DESC
                  LIMIT 10";
$activity_stmt = mysqli_prepare($conn, $activity_query);
mysqli_stmt_bind_param($activity_stmt, "i", $quiz_id);
mysqli_stmt_execute($activity_stmt);
$activity_result = mysqli_stmt_get_result($activity_stmt);

$recent_activity = [];
while ($activity = mysqli_fetch_assoc($activity_result)) {
    $recent_activity[] = [
        'student_name' => $activity['first_name'] . ' ' . $activity['last_name'],
        'action' => $activity['action'],
        'timestamp' => $activity['timestamp']
    ];
}

// Get real-time updates (submissions in the last 30 seconds)
$recent_submissions_query = "SELECT
                              s.first_name, s.last_name,
                              qs.score, qs.status,
                              qs.updated_at
                            FROM quiz_submissions qs
                            JOIN students s ON qs.student_id = s.id
                            WHERE qs.quiz_id = ?
                            AND qs.updated_at >= DATE_SUB(NOW(), INTERVAL 30 SECOND)
                            ORDER BY qs.updated_at DESC";
$recent_stmt = mysqli_prepare($conn, $recent_submissions_query);
mysqli_stmt_bind_param($recent_stmt, "i", $quiz_id);
mysqli_stmt_execute($recent_stmt);
$recent_result = mysqli_stmt_get_result($recent_stmt);

$recent_updates = [];
while ($update = mysqli_fetch_assoc($recent_result)) {
    $recent_updates[] = $update;
}

$response = [
    'success' => true,
    'leaderboard' => $leaderboard,
    'statistics' => $statistics,
    'recent_activity' => $recent_activity,
    'recent_updates' => $recent_updates,
    'timestamp' => date('Y-m-d H:i:s')
];

echo json_encode($response);
?>