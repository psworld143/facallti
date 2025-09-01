<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

// Set timezone
date_default_timezone_set('Asia/Manila');

// Get the request data
$teacherId = $_POST['teacher_id'] ?? '';
$confirmed = $_POST['confirmed'] ?? '';
$actionType = $_POST['action_type'] ?? '';

if (empty($teacherId)) {
    echo json_encode([
        'success' => false,
        'error' => 'Teacher ID is required'
    ]);
    exit;
}

if ($confirmed !== 'true' && $confirmed !== 'false') {
    echo json_encode([
        'success' => false,
        'error' => 'Confirmation status is required'
    ]);
    exit;
}

if (empty($actionType) || !in_array($actionType, ['mark_available', 'mark_unavailable'])) {
    echo json_encode([
        'success' => false,
        'error' => 'Valid action type is required (mark_available or mark_unavailable)'
    ]);
    exit;
}

$teacherId = intval($teacherId);
$isConfirmed = ($confirmed === 'true');

try {
    // Verify teacher exists and is active
    $teacherQuery = "SELECT id, first_name, last_name, email, department, position, qrcode 
                     FROM faculty 
                     WHERE id = ? AND is_active = 1";
    
    $teacherStmt = mysqli_prepare($conn, $teacherQuery);
    mysqli_stmt_bind_param($teacherStmt, "i", $teacherId);
    mysqli_stmt_execute($teacherStmt);
    $teacherResult = mysqli_stmt_get_result($teacherStmt);
    
    if (mysqli_num_rows($teacherResult) == 0) {
        echo json_encode([
            'success' => false,
            'error' => 'Teacher not found or inactive'
        ]);
        exit;
    }
    
    $teacher = mysqli_fetch_assoc($teacherResult);
    
    if ($isConfirmed) {
        if ($actionType === 'mark_available') {
            // Mark teacher as available
            $markAvailableQuery = "INSERT INTO teacher_availability (teacher_id, availability_date, status, notes, last_activity)
                                  VALUES (?, CURDATE(), 'available', 'QR Code Scanned - Confirmed Available', NOW())
                                  ON DUPLICATE KEY UPDATE
                                      status = 'available',
                                      scan_time = NOW(),
                                      last_activity = NOW(),
                                      notes = 'QR Code Scanned - Confirmed Available',
                                      updated_at = NOW()";
            
            $markStmt = mysqli_prepare($conn, $markAvailableQuery);
            mysqli_stmt_bind_param($markStmt, "i", $teacherId);
            
            if (!mysqli_stmt_execute($markStmt)) {
                throw new Exception('Failed to mark teacher as available: ' . mysqli_stmt_error($markStmt));
            }
            
            // Check for pending consultation requests and auto-accept them
            $pendingQuery = "SELECT id, session_id, student_name, student_dept, student_id, request_time
                            FROM consultation_requests 
                            WHERE teacher_id = ? AND status = 'pending' 
                            AND request_time > DATE_SUB(NOW(), INTERVAL 30 MINUTE)
                            ORDER BY request_time ASC";
            
            $pendingStmt = mysqli_prepare($conn, $pendingQuery);
            mysqli_stmt_bind_param($pendingStmt, "i", $teacherId);
            mysqli_stmt_execute($pendingStmt);
            $pendingResult = mysqli_stmt_get_result($pendingStmt);
            
            $acceptedRequests = [];
            $autoAcceptedCount = 0;
            
            while ($pendingRequest = mysqli_fetch_assoc($pendingResult)) {
                // Auto-accept the pending request
                $acceptQuery = "UPDATE consultation_requests 
                               SET status = 'accepted', 
                                   response_time = NOW(),
                                   response_duration_seconds = TIMESTAMPDIFF(SECOND, request_time, NOW()),
                                   updated_at = NOW()
                               WHERE id = ? AND status = 'pending'";
                
                $acceptStmt = mysqli_prepare($conn, $acceptQuery);
                mysqli_stmt_bind_param($acceptStmt, "i", $pendingRequest['id']);
                
                if (mysqli_stmt_execute($acceptStmt)) {
                    $acceptedRequests[] = $pendingRequest;
                    $autoAcceptedCount++;
                }
            }
            
            // Log the successful confirmation
            error_log("Teacher marked as available: " . $teacher['first_name'] . " " . $teacher['last_name'] . " (ID: " . $teacherId . ") - " . $autoAcceptedCount . " requests auto-accepted");
            
            echo json_encode([
                'success' => true,
                'confirmed' => true,
                'action_type' => 'mark_available',
                'teacher' => [
                    'id' => $teacher['id'],
                    'name' => $teacher['first_name'] . ' ' . $teacher['last_name'],
                    'department' => $teacher['department'],
                    'position' => $teacher['position'],
                    'qrcode' => $teacher['qrcode']
                ],
                'marked_available' => true,
                'accepted_requests' => $acceptedRequests,
                'auto_accepted_count' => $autoAcceptedCount,
                'message' => $autoAcceptedCount > 0 ? 
                    "Teacher marked as available! {$autoAcceptedCount} pending consultation request(s) auto-accepted." : 
                    'Teacher marked as available for consultation!'
            ]);
            
        } else if ($actionType === 'mark_unavailable') {
            // Mark teacher as unavailable by deleting the availability record
            $deleteAvailabilityQuery = "DELETE FROM teacher_availability 
                                        WHERE teacher_id = ? AND availability_date = CURDATE()";
            
            $deleteStmt = mysqli_prepare($conn, $deleteAvailabilityQuery);
            mysqli_stmt_bind_param($deleteStmt, "i", $teacherId);
            
            if (!mysqli_stmt_execute($deleteStmt)) {
                throw new Exception('Failed to mark teacher as unavailable: ' . mysqli_stmt_error($deleteStmt));
            }
            
            $deletedRecords = mysqli_affected_rows($conn);
            
            // Log the successful unavailability
            error_log("Teacher marked as unavailable: " . $teacher['first_name'] . " " . $teacher['last_name'] . " (ID: " . $teacherId . ") - availability record deleted");
            
            echo json_encode([
                'success' => true,
                'confirmed' => true,
                'action_type' => 'mark_unavailable',
                'teacher' => [
                    'id' => $teacher['id'],
                    'name' => $teacher['first_name'] . ' ' . $teacher['last_name'],
                    'department' => $teacher['department'],
                    'position' => $teacher['position'],
                    'qrcode' => $teacher['qrcode']
                ],
                'marked_available' => false,
                'records_deleted' => $deletedRecords,
                'message' => $deletedRecords > 0 ? 
                    'Teacher marked as unavailable! Availability record removed.' : 
                    'Teacher was already unavailable.'
            ]);
        }
        
    } else {
        // Teacher declined the action
        $actionText = $actionType === 'mark_available' ? 'become available' : 'become unavailable';
        error_log("Teacher declined action: " . $teacher['first_name'] . " " . $teacher['last_name'] . " (ID: " . $teacherId . ") - declined to " . $actionText);
        
        echo json_encode([
            'success' => true,
            'confirmed' => false,
            'action_type' => $actionType,
            'teacher' => [
                'id' => $teacher['id'],
                'name' => $teacher['first_name'] . ' ' . $teacher['last_name'],
                'department' => $teacher['department'],
                'position' => $teacher['position'],
                'qrcode' => $teacher['qrcode']
            ],
            'marked_available' => null,
            'message' => "Teacher chose not to {$actionText} at this time."
        ]);
    }
    
} catch (Exception $e) {
    error_log("Teacher Availability Confirmation Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Failed to process teacher availability confirmation: ' . $e->getMessage()
    ]);
}
?>
