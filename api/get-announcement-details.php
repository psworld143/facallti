<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/id_encryption.php';

header('Content-Type: application/json');

// Check if user is logged in and has faculty role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if (!isset($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'Announcement ID is required']);
    exit;
}

$announcement_id = safe_decrypt_id($_GET['id']);
$faculty_id = $_SESSION['user_id'];

// Get announcement details with class information
$query = "SELECT ca.*, tc.subject_title, tc.section
          FROM class_announcements ca
          LEFT JOIN teacher_classes tc ON ca.class_id = tc.id
          WHERE ca.id = ? AND ca.faculty_id = ?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "ii", $announcement_id, $faculty_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) === 0) {
    echo json_encode(['success' => false, 'message' => 'Announcement not found or access denied']);
    exit;
}

$announcement = mysqli_fetch_assoc($result);

// Format the response
$response = [
    'success' => true,
    'announcement' => [
        'id' => $announcement['id'],
        'title' => htmlspecialchars($announcement['title']),
        'content' => nl2br(htmlspecialchars($announcement['content'])),
        'priority' => $announcement['priority'],
        'is_pinned' => (bool)$announcement['is_pinned'],
        'created_at' => date('M j, Y g:i A', strtotime($announcement['created_at'])),
        'class_name' => $announcement['subject_title'] ?
            htmlspecialchars($announcement['subject_title'] . ' - ' . $announcement['section']) :
            'All Classes'
    ]
];

echo json_encode($response);
?>