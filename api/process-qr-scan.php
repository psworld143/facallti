<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

// Set timezone
date_default_timezone_set('Asia/Manila');

// Get the scanned QR code
$qrCode = $_POST['qr_code'] ?? '';

if (empty($qrCode)) {
    echo json_encode([
        'success' => false,
        'error' => 'QR code is required'
    ]);
    exit;
}

$qrCode = trim($qrCode);

try {
    // First, check if this QR code belongs to a teacher
    $teacherQuery = "SELECT id, first_name, last_name, email, department, position, qrcode 
                     FROM faculty 
                     WHERE qrcode = ? AND is_active = 1";
    
    $teacherStmt = mysqli_prepare($conn, $teacherQuery);
    mysqli_stmt_bind_param($teacherStmt, "s", $qrCode);
    mysqli_stmt_execute($teacherStmt);
    $teacherResult = mysqli_stmt_get_result($teacherStmt);
    
    if (mysqli_num_rows($teacherResult) > 0) {
        // This is a teacher QR code
        $teacher = mysqli_fetch_assoc($teacherResult);
        
        // Check if teacher already has availability record for today
        $availabilityQuery = "SELECT id, status, scan_time, notes 
                             FROM teacher_availability 
                             WHERE teacher_id = ? AND availability_date = CURDATE()";
        
        $availabilityStmt = mysqli_prepare($conn, $availabilityQuery);
        mysqli_stmt_bind_param($availabilityStmt, "i", $teacher['id']);
        mysqli_stmt_execute($availabilityStmt);
        $availabilityResult = mysqli_stmt_get_result($availabilityStmt);
        
        $existingAvailability = null;
        if (mysqli_num_rows($availabilityResult) > 0) {
            $existingAvailability = mysqli_fetch_assoc($availabilityResult);
        }
        
        // Check for pending consultation requests for this teacher
        $pendingQuery = "SELECT id, session_id, student_name, student_dept, student_id, request_time
                        FROM consultation_requests 
                        WHERE teacher_id = ? AND status = 'pending' 
                        AND request_time > DATE_SUB(NOW(), INTERVAL 30 MINUTE)
                        ORDER BY request_time ASC";
        
        $pendingStmt = mysqli_prepare($conn, $pendingQuery);
        mysqli_stmt_bind_param($pendingStmt, "i", $teacher['id']);
        mysqli_stmt_execute($pendingStmt);
        $pendingResult = mysqli_stmt_get_result($pendingStmt);
        
        $pendingRequests = [];
        while ($row = mysqli_fetch_assoc($pendingResult)) {
            $pendingRequests[] = $row;
        }
        
        // Determine the action based on existing availability
        if ($existingAvailability) {
            // Teacher already has availability record - ask if they want to become unavailable
            $action = 'mark_unavailable';
            $message = 'You are currently marked as available. Do you want to mark yourself as unavailable?';
            error_log("Teacher QR scanned: " . $teacher['first_name'] . " " . $teacher['last_name'] . " (ID: " . $teacher['id'] . ") - currently available, asking to mark unavailable");
        } else {
            // Teacher has no availability record - ask if they want to become available
            $action = 'mark_available';
            $message = 'You are currently not available. Do you want to mark yourself as available for consultation?';
            error_log("Teacher QR scanned: " . $teacher['first_name'] . " " . $teacher['last_name'] . " (ID: " . $teacher['id'] . ") - not available, asking to mark available");
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
            'requires_confirmation' => true,
            'current_status' => $existingAvailability ? $existingAvailability['status'] : 'unavailable',
            'action_type' => $action,
            'existing_availability' => $existingAvailability,
            'pending_requests' => $pendingRequests,
            'pending_count' => count($pendingRequests),
            'message' => $message
        ]);
        
    } else {
        // This is not a teacher QR code, treat as student ID
        // Validate student ID format (basic validation)
        if (strlen($qrCode) < 3) {
            echo json_encode([
                'success' => false,
                'error' => 'Invalid student ID format. Please scan a valid student ID.'
            ]);
            exit;
        }
        
        // Log the student ID scan
        error_log("Student ID scanned: " . $qrCode);
        
        echo json_encode([
            'success' => true,
            'type' => 'student',
            'student' => [
                'id' => $qrCode,
                'name' => 'Student ' . $qrCode,
                'department' => 'General'
            ],
            'message' => 'Student ID scanned successfully!'
        ]);
    }
    
} catch (Exception $e) {
    error_log("QR Processing Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Failed to process QR code: ' . $e->getMessage()
    ]);
}
?>
