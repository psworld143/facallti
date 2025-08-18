<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if (!isset($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'Event ID is required']);
    exit;
}

$event_id = (int)$_GET['id'];

$query = "SELECT * FROM posts WHERE id = ? AND status = 'approved' AND type = 'event'";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $event_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) === 0) {
    echo json_encode(['success' => false, 'message' => 'Event not found']);
    exit;
}

$event = mysqli_fetch_assoc($result);

echo json_encode([
    'success' => true,
    'event' => $event
]);
?>