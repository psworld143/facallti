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

if (!isset($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'Event ID is required']);
    exit;
}

$event_id = (int)$_GET['id'];
$faculty_id = $_SESSION['user_id'];

// Get event details with class information
$query = "SELECT fe.*, tc.subject_title, tc.section
          FROM faculty_events fe
          LEFT JOIN teacher_classes tc ON fe.class_id = tc.id
          WHERE fe.id = ? AND fe.faculty_id = ?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "ii", $event_id, $faculty_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) === 0) {
    echo json_encode(['success' => false, 'message' => 'Event not found or access denied']);
    exit;
}

$event = mysqli_fetch_assoc($result);

// Format the response
$response = [
    'success' => true,
    'event' => [
        'id' => $event['id'],
        'title' => htmlspecialchars($event['title']),
        'description' => nl2br(htmlspecialchars($event['description'])),
        'event_date' => date('M j, Y', strtotime($event['event_date'])),
        'start_time' => $event['start_time'] ? date('g:i A', strtotime($event['start_time'])) : 'N/A',
        'end_time' => $event['end_time'] ? date('g:i A', strtotime($event['end_time'])) : 'N/A',
        'location' => htmlspecialchars($event['location'] ?? 'N/A'),
        'event_type' => htmlspecialchars($event['event_type']),
        'created_at' => date('M j, Y g:i A', strtotime($event['created_at'])),
        'class_name' => $event['subject_title'] ?
            htmlspecialchars($event['subject_title'] . ' - ' . $event['section']) :
            'Personal Event'
    ]
];

echo json_encode($response);
?>