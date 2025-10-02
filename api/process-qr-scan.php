<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Set timezone
date_default_timezone_set('Asia/Manila');

require_once '../config/database.php';

// Function to sanitize input
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Function to log QR processing
function log_qr_processing($qr_code, $type, $result) {
    $log_message = date('Y-m-d H:i:s') . " - QR: $qr_code, Type: $type, Result: " . ($result ? 'SUCCESS' : 'FAILED') . PHP_EOL;
    error_log($log_message, 3, '../logs/qr_processing.log');
}

try {
    // Check if request method is POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Only POST method is allowed');
    }

    // Get QR code from POST data
    $qr_code = $_POST['qr_code'] ?? '';
    
    if (empty($qr_code)) {
        throw new Exception('QR code is required');
    }

    // Sanitize the QR code
    $qr_code = sanitize_input($qr_code);
    
    // Log the QR code processing attempt
    error_log("Processing QR code: " . $qr_code);

    // First, check if QR code exists in faculty table (teacher)
    $teacher_query = "SELECT id, first_name, last_name, department, position, qrcode, is_active 
                     FROM faculty 
                     WHERE qrcode = ? AND is_active = 1";
    $teacher_stmt = mysqli_prepare($conn, $teacher_query);
    mysqli_stmt_bind_param($teacher_stmt, "s", $qr_code);
    mysqli_stmt_execute($teacher_stmt);
    $teacher_result = mysqli_stmt_get_result($teacher_stmt);
    
    if (mysqli_num_rows($teacher_result) > 0) {
        // QR code found in faculty table - handle as teacher
        $teacher = mysqli_fetch_assoc($teacher_result);
        
        // Check current availability status from teacher_availability table
        $availability_check_query = "SELECT status FROM teacher_availability 
                                   WHERE teacher_id = ? AND availability_date = CURDATE()";
        $availability_stmt = mysqli_prepare($conn, $availability_check_query);
        mysqli_stmt_bind_param($availability_stmt, "i", $teacher['id']);
        mysqli_stmt_execute($availability_stmt);
        $availability_result = mysqli_stmt_get_result($availability_stmt);
        
        $current_status = null;
        if (mysqli_num_rows($availability_result) > 0) {
            $availability_row = mysqli_fetch_assoc($availability_result);
            $current_status = $availability_row['status'];
        }
        
        // Check if teacher has active consultation today
        $consultation_check_query = "SELECT id, status FROM consultation_requests 
                                   WHERE teacher_id = ? AND DATE(created_at) = CURDATE() 
                                   AND status IN ('pending', 'accepted', 'in_progress')";
        $consultation_stmt = mysqli_prepare($conn, $consultation_check_query);
        mysqli_stmt_bind_param($consultation_stmt, "i", $teacher['id']);
        mysqli_stmt_execute($consultation_stmt);
        $consultation_result = mysqli_stmt_get_result($consultation_stmt);
        
        $has_active_consultation = mysqli_num_rows($consultation_result) > 0;
        
        log_qr_processing($qr_code, 'teacher', true);
        
        // Determine the appropriate action based on current status
        $action_type = 'mark_available';
        $message = 'Confirm teacher availability?';
        
        if ($current_status === 'available') {
            $action_type = 'mark_unavailable';
            $message = 'Teacher is currently available. Mark as unavailable?';
        } else if ($current_status === 'unavailable') {
            $action_type = 'mark_available';
            $message = 'Teacher is currently unavailable. Mark as available?';
        } else if ($has_active_consultation) {
            $action_type = 'mark_unavailable';
            $message = 'Teacher has active consultation. Mark as unavailable?';
        }
        
        echo json_encode([
            'success' => true,
            'type' => 'teacher',
            'teacher' => [
                'id' => $teacher['id'],
                'name' => $teacher['first_name'] . ' ' . $teacher['last_name'],
                'department' => $teacher['department'],
                'position' => $teacher['position'],
                'qrcode' => $teacher['qrcode']
            ],
            'current_status' => $current_status,
            'has_active_consultation' => $has_active_consultation,
            'action_type' => $action_type,
            'message' => $message
        ]);
        exit();
    }
    
    // QR code not found in faculty table - treat as student (no validation needed)
    log_qr_processing($qr_code, 'student', true);
    
    // Format student information properly
    $student_id = $qr_code; // Store the full QR code as student ID
    $student_name = 'Student ' . $qr_code; // Format as "Student 2017-00202"
    
    // Log what we're sending back for student
    error_log("Sending student response:");
    error_log("- student_id: " . var_export($student_id, true));
    error_log("- student_name: " . var_export($student_name, true));
    error_log("- student_id length: " . strlen($student_id));
    
    echo json_encode([
        'success' => true,
        'type' => 'student',
        'student' => [
            'id' => 0, // No validation, so no real ID
            'name' => $student_name, // Formatted as "Student 2017-00202"
            'student_id' => $student_id, // Full QR code like "2017-00202"
            'department' => 'General',
            'course' => 'General',
            'year_level' => 'General'
        ],
        'message' => 'Student identified. Displaying available teachers for consultation.'
    ]);

} catch (Exception $e) {
    error_log("QR Processing Error: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
