<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

// Get request data
$teacher_id = $_POST['teacher_id'] ?? null;
$student_name = $_POST['student_name'] ?? 'Student';
$student_dept = $_POST['student_dept'] ?? '';
$student_id = $_POST['student_id'] ?? null;

// Debug logging for received data (can be removed in production)
error_log('=== CONSULTATION REQUEST DEBUG ===');
error_log('Consultation request received:');
error_log('- teacher_id: ' . var_export($teacher_id, true));
error_log('- student_name: ' . var_export($student_name, true));
error_log('- student_dept: ' . var_export($student_dept, true));
error_log('- student_id: ' . var_export($student_id, true));

// Validate and clean department name
function cleanDepartmentName($dept) {
    $dept = trim($dept);
    if (empty($dept)) {
        return 'General';
    }
    
    // List of valid departments
    $valid_departments = [
        'College of Business',
        'College of Engineering',
        'College of Information and Communication Technology',
        'College of Information Technology',
        'Department of Computer Science',
        'Department of Electronics Engineering',
        'Department of Information Systems',
        'Computer Science',
        'Mathematics',
        'English',
        'History',
        'General'
    ];
    
    // Check if the department is in our valid list
    foreach ($valid_departments as $valid_dept) {
        if (stripos($dept, $valid_dept) !== false) {
            return $valid_dept;
        }
    }
    
    // If not found, return a default based on common patterns
    if (stripos($dept, 'computer') !== false || stripos($dept, 'cs') !== false) {
        return 'Department of Computer Science';
    } elseif (stripos($dept, 'business') !== false) {
        return 'College of Business';
    } elseif (stripos($dept, 'engineering') !== false) {
        return 'College of Engineering';
    } elseif (stripos($dept, 'information') !== false || stripos($dept, 'ict') !== false) {
        return 'College of Information and Communication Technology';
    }
    
    return 'General';
}

$student_dept = cleanDepartmentName($student_dept);

if (!$teacher_id) {
    echo json_encode(['error' => 'Teacher ID required']);
    exit;
}

// Validate student ID is provided
if (empty($student_id) || $student_id === 'null' || $student_id === '') {
    echo json_encode(['error' => 'Student ID is required. Please scan your student ID first.']);
    exit;
}

// Clean and validate student ID format
$student_id = trim($student_id);
if (strlen($student_id) < 3) {
    echo json_encode(['error' => 'Invalid student ID format. Please scan a valid student ID.']);
    exit;
}

try {
    // Generate unique session ID for this consultation
    $session_id = uniqid('consultation_', true);
    
    // Store consultation request in database
    $query = "INSERT INTO consultation_requests (teacher_id, student_name, student_dept, student_id, status, session_id, request_time) VALUES (?, ?, ?, ?, 'pending', ?, NOW())";
    $stmt = mysqli_prepare($conn, $query);
    
    if (!$stmt) {
        throw new Exception('Failed to prepare statement: ' . mysqli_error($conn));
    }
    
    // Student ID is already validated above, no need to handle null
    
    // Debug logging before database insertion
    error_log('About to insert into database:');
    error_log('- teacher_id: ' . var_export($teacher_id, true));
    error_log('- student_name: ' . var_export($student_name, true));
    error_log('- student_dept: ' . var_export($student_dept, true));
    error_log('- student_id: ' . var_export($student_id, true));
    error_log('- session_id: ' . var_export($session_id, true));
    
    mysqli_stmt_bind_param($stmt, "issis", $teacher_id, $student_name, $student_dept, $student_id, $session_id);
    $result = mysqli_stmt_execute($stmt);
    
    if (!$result) {
        throw new Exception('Failed to insert consultation request: ' . mysqli_stmt_error($stmt));
    }
    
    // Debug logging after successful insertion
    $inserted_id = mysqli_insert_id($conn);
    error_log('Successfully inserted consultation request with ID: ' . $inserted_id);
    
    // Verify what was actually inserted
    $verify_query = "SELECT student_id, student_name, student_dept FROM consultation_requests WHERE id = ?";
    $verify_stmt = mysqli_prepare($conn, $verify_query);
    if ($verify_stmt) {
        mysqli_stmt_bind_param($verify_stmt, "i", $inserted_id);
        mysqli_stmt_execute($verify_stmt);
        $verify_result = mysqli_stmt_get_result($verify_stmt);
        if ($verify_row = mysqli_fetch_assoc($verify_result)) {
            error_log('Verification - What was actually inserted:');
            error_log('- student_id in DB: ' . var_export($verify_row['student_id'], true));
            error_log('- student_name in DB: ' . var_export($verify_row['student_name'], true));
            error_log('- student_dept in DB: ' . var_export($verify_row['student_dept'], true));
        }
        mysqli_stmt_close($verify_stmt);
    }
    
    $request_id = mysqli_insert_id($conn);
    
    $response = [
        'success' => true,
        'message' => 'Consultation request sent successfully',
        'request_id' => $request_id,
        'session_id' => $session_id,
        'teacher_id' => $teacher_id,
        'student_name' => $student_name,
        'student_dept' => $student_dept,
        'student_id' => $student_id,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    // Log error to console instead of showing alert
    error_log('Consultation request error: ' . $e->getMessage());
    echo json_encode(['error' => 'Failed to send consultation request. Please try again.']);
}
?>
