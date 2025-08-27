<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and has head role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'head') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
    $consultation_id = (int)$_GET['id'];
    

    
    // Get consultation details with teacher information
    $query = "SELECT ch.*, f.first_name, f.last_name, f.email, f.department, f.position 
              FROM consultation_hours ch 
              JOIN faculty f ON ch.teacher_id = f.id 
              WHERE ch.id = ? AND ch.is_active = 1";
    
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $consultation_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    

    
    if ($consultation = mysqli_fetch_assoc($result)) {
        // Format the response
        $response = [
            'id' => $consultation['id'],
            'teacher_name' => $consultation['first_name'] . ' ' . $consultation['last_name'],
            'email' => $consultation['email'],
            'department' => $consultation['department'],
            'position' => $consultation['position'],
            'semester' => $consultation['semester'],
            'academic_year' => $consultation['academic_year'],
            'day_of_week' => $consultation['day_of_week'],
            'start_time' => date('g:i A', strtotime($consultation['start_time'])),
            'end_time' => date('g:i A', strtotime($consultation['end_time'])),
            'notes' => $consultation['notes']
        ];
        
        header('Content-Type: application/json');
        echo json_encode($response);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Consultation not found']);
    }
} else {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
}
?>
