<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../config/database.php';

// Set timezone
date_default_timezone_set('Asia/Manila');

// Function to log errors
function logError($message) {
    error_log("Teacher Availability Handler Error: " . $message);
    console.log("Teacher Availability Handler Error: " . $message);
}

// Function to validate teacher ID format
function validateTeacherId($teacherId) {
    // Teacher ID should be numeric and exist in faculty table
    if (!is_numeric($teacherId) || $teacherId <= 0) {
        return false;
    }
    return true;
}

// Function to get teacher details by ID
function getTeacherDetails($teacherId) {
    global $conn;
    
    $query = "SELECT id, first_name, last_name, email, department, position, is_active, qrcode 
              FROM faculty 
              WHERE id = ? AND is_active = 1";
    
    $stmt = mysqli_prepare($conn, $query);
    if (!$stmt) {
        logError("Failed to prepare teacher details query: " . mysqli_error($conn));
        return null;
    }
    
    mysqli_stmt_bind_param($stmt, "i", $teacherId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($result) > 0) {
        return mysqli_fetch_assoc($result);
    }
    
    return null;
}

// Function to get teacher details by QR code
function getTeacherDetailsByQRCode($qrCode) {
    global $conn;
    
    $query = "SELECT id, first_name, last_name, email, department, position, is_active, qrcode 
              FROM faculty 
              WHERE qrcode = ? AND is_active = 1";
    
    $stmt = mysqli_prepare($conn, $query);
    if (!$stmt) {
        logError("Failed to prepare teacher QR code query: " . mysqli_error($conn));
        return null;
    }
    
    mysqli_stmt_bind_param($stmt, "s", $qrCode);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($result) > 0) {
        return mysqli_fetch_assoc($result);
    }
    
    return null;
}

// Function to mark teacher as available
function markTeacherAvailable($teacherId, $notes = null) {
    global $conn;
    
    try {
        // Use stored procedure if available, otherwise use direct query
        $query = "INSERT INTO teacher_availability (teacher_id, availability_date, status, notes, last_activity)
                  VALUES (?, CURDATE(), 'available', ?, NOW())
                  ON DUPLICATE KEY UPDATE
                      status = 'available',
                      scan_time = NOW(),
                      last_activity = NOW(),
                      notes = COALESCE(?, notes),
                      updated_at = NOW()";
        
        $stmt = mysqli_prepare($conn, $query);
        if (!$stmt) {
            logError("Failed to prepare mark available query: " . mysqli_error($conn));
            return false;
        }
        
        mysqli_stmt_bind_param($stmt, "iss", $teacherId, $notes, $notes);
        $result = mysqli_stmt_execute($stmt);
        
        if (!$result) {
            logError("Failed to mark teacher available: " . mysqli_stmt_error($stmt));
            return false;
        }
        
        return true;
    } catch (Exception $e) {
        logError("Exception in markTeacherAvailable: " . $e->getMessage());
        return false;
    }
}

// Function to mark teacher as unavailable
function markTeacherUnavailable($teacherId, $notes = null) {
    global $conn;
    
    try {
        $query = "UPDATE teacher_availability 
                  SET status = 'unavailable',
                      last_activity = NOW(),
                      notes = COALESCE(?, notes),
                      updated_at = NOW()
                  WHERE teacher_id = ? AND availability_date = CURDATE()";
        
        $stmt = mysqli_prepare($conn, $query);
        if (!$stmt) {
            logError("Failed to prepare mark unavailable query: " . mysqli_error($conn));
            return false;
        }
        
        mysqli_stmt_bind_param($stmt, "si", $notes, $teacherId);
        $result = mysqli_stmt_execute($stmt);
        
        if (!$result) {
            logError("Failed to mark teacher unavailable: " . mysqli_stmt_error($stmt));
            return false;
        }
        
        return true;
    } catch (Exception $e) {
        logError("Exception in markTeacherUnavailable: " . $e->getMessage());
        return false;
    }
}

// Function to get teacher availability status
function getTeacherAvailabilityStatus($teacherId) {
    global $conn;
    
    $query = "SELECT ta.*, f.first_name, f.last_name, f.department, f.position
              FROM teacher_availability ta
              JOIN faculty f ON ta.teacher_id = f.id
              WHERE ta.teacher_id = ? AND ta.availability_date = CURDATE()";
    
    $stmt = mysqli_prepare($conn, $query);
    if (!$stmt) {
        logError("Failed to prepare availability status query: " . mysqli_error($conn));
        return null;
    }
    
    mysqli_stmt_bind_param($stmt, "i", $teacherId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($result) > 0) {
        return mysqli_fetch_assoc($result);
    }
    
    return null;
}

// Function to get all available teachers for today
function getAvailableTeachers($department = null) {
    global $conn;
    
    $query = "SELECT ta.*, f.first_name, f.last_name, f.email, f.department, f.position, f.image_url,
                     TIMESTAMPDIFF(MINUTE, ta.last_activity, NOW()) as minutes_since_last_activity
              FROM teacher_availability ta
              JOIN faculty f ON ta.teacher_id = f.id
              WHERE ta.availability_date = CURDATE()
              AND ta.status = 'available'
              AND f.is_active = 1";
    
    if ($department) {
        $query .= " AND f.department = ?";
    }
    
    $query .= " ORDER BY ta.scan_time DESC";
    
    $stmt = mysqli_prepare($conn, $query);
    if (!$stmt) {
        logError("Failed to prepare available teachers query: " . mysqli_error($conn));
        return [];
    }
    
    if ($department) {
        mysqli_stmt_bind_param($stmt, "s", $department);
    }
    
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $teachers = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $teachers[] = $row;
    }
    
    return $teachers;
}

// Main request handler
try {
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? '';
    
    switch ($method) {
        case 'POST':
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!$input) {
                $input = $_POST;
            }
            
            switch ($action) {
                case 'mark_available':
                    $teacherId = $input['teacher_id'] ?? null;
                    $notes = $input['notes'] ?? null;
                    
                    if (!$teacherId) {
                        echo json_encode(['success' => false, 'error' => 'Teacher ID is required']);
                        exit();
                    }
                    
                    if (!validateTeacherId($teacherId)) {
                        echo json_encode(['success' => false, 'error' => 'Invalid teacher ID format']);
                        exit();
                    }
                    
                    $teacher = getTeacherDetails($teacherId);
                    if (!$teacher) {
                        echo json_encode(['success' => false, 'error' => 'Teacher not found or inactive']);
                        exit();
                    }
                    
                    if (markTeacherAvailable($teacherId, $notes)) {
                        $availability = getTeacherAvailabilityStatus($teacherId);
                        echo json_encode([
                            'success' => true,
                            'message' => 'Teacher marked as available successfully',
                            'teacher' => $teacher,
                            'availability' => $availability
                        ]);
                    } else {
                        echo json_encode(['success' => false, 'error' => 'Failed to mark teacher as available']);
                    }
                    break;
                    
                case 'mark_unavailable':
                    $teacherId = $input['teacher_id'] ?? null;
                    $notes = $input['notes'] ?? null;
                    
                    if (!$teacherId) {
                        echo json_encode(['success' => false, 'error' => 'Teacher ID is required']);
                        exit();
                    }
                    
                    if (!validateTeacherId($teacherId)) {
                        echo json_encode(['success' => false, 'error' => 'Invalid teacher ID format']);
                        exit();
                    }
                    
                    if (markTeacherUnavailable($teacherId, $notes)) {
                        echo json_encode([
                            'success' => true,
                            'message' => 'Teacher marked as unavailable successfully'
                        ]);
                    } else {
                        echo json_encode(['success' => false, 'error' => 'Failed to mark teacher as unavailable']);
                    }
                    break;
                    
                default:
                    echo json_encode(['success' => false, 'error' => 'Invalid action']);
                    break;
            }
            break;
            
        case 'GET':
            switch ($action) {
                case 'verify_teacher':
                    $teacherId = $_GET['teacher_id'] ?? null;
                    $qrCode = $_GET['qr_code'] ?? null;
                    
                    if (!$teacherId && !$qrCode) {
                        echo json_encode(['success' => false, 'error' => 'Teacher ID or QR Code is required']);
                        exit();
                    }
                    
                    $teacher = null;
                    
                    if ($qrCode) {
                        // Verify by QR code
                        $teacher = getTeacherDetailsByQRCode($qrCode);
                    } else if ($teacherId) {
                        // Verify by teacher ID
                        if (!validateTeacherId($teacherId)) {
                            echo json_encode(['success' => false, 'error' => 'Invalid teacher ID format']);
                            exit();
                        }
                        $teacher = getTeacherDetails($teacherId);
                    }
                    
                    if ($teacher) {
                        echo json_encode([
                            'success' => true,
                            'teacher' => [
                                'id' => $teacher['id'],
                                'name' => $teacher['first_name'] . ' ' . $teacher['last_name'],
                                'department' => $teacher['department'],
                                'position' => $teacher['position'],
                                'email' => $teacher['email'],
                                'qrcode' => $teacher['qrcode']
                            ],
                            'message' => 'Teacher verified successfully'
                        ]);
                    } else {
                        echo json_encode(['success' => false, 'error' => 'Teacher not found in database']);
                    }
                    break;
                    
                case 'get_status':
                    $teacherId = $_GET['teacher_id'] ?? null;
                    
                    if (!$teacherId) {
                        echo json_encode(['success' => false, 'error' => 'Teacher ID is required']);
                        exit();
                    }
                    
                    if (!validateTeacherId($teacherId)) {
                        echo json_encode(['success' => false, 'error' => 'Invalid teacher ID format']);
                        exit();
                    }
                    
                    $availability = getTeacherAvailabilityStatus($teacherId);
                    if ($availability) {
                        echo json_encode([
                            'success' => true,
                            'availability' => $availability
                        ]);
                    } else {
                        echo json_encode([
                            'success' => true,
                            'availability' => null,
                            'message' => 'No availability record found for today'
                        ]);
                    }
                    break;
                    
                case 'get_available_teachers':
                    $department = $_GET['department'] ?? null;
                    $teachers = getAvailableTeachers($department);
                    
                    echo json_encode([
                        'success' => true,
                        'teachers' => $teachers,
                        'count' => count($teachers)
                    ]);
                    break;
                    
                default:
                    echo json_encode(['success' => false, 'error' => 'Invalid action']);
                    break;
            }
            break;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            break;
    }
    
} catch (Exception $e) {
    logError("Unexpected error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'An unexpected error occurred']);
}
?>
