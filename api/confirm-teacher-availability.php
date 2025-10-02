<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit();
}

require_once '../config/database.php';

// Get POST data
$teacher_id = $_POST['teacher_id'] ?? null;
$confirmed = $_POST['confirmed'] ?? null;
$action_type = $_POST['action_type'] ?? null;

// Validate required parameters
if (!$teacher_id || $confirmed === null || !$action_type) {
    echo json_encode([
        'success' => false, 
        'error' => 'Missing required parameters: teacher_id, confirmed, and action_type'
    ]);
    exit();
}

// Convert confirmed to boolean
$confirmed = filter_var($confirmed, FILTER_VALIDATE_BOOLEAN);

try {
    $conn->autocommit(FALSE); // Start transaction
    
    $today = date('Y-m-d');
    
    // Check if teacher exists and is active
    $teacher_check_query = "SELECT id, first_name, last_name FROM faculty WHERE id = ? AND is_active = 1";
    $teacher_stmt = mysqli_prepare($conn, $teacher_check_query);
    mysqli_stmt_bind_param($teacher_stmt, "i", $teacher_id);
    mysqli_stmt_execute($teacher_stmt);
    $teacher_result = mysqli_stmt_get_result($teacher_stmt);
    
    if (mysqli_num_rows($teacher_result) === 0) {
        throw new Exception('Teacher not found or inactive');
    }
    
    $teacher = mysqli_fetch_assoc($teacher_result);
    
    if ($confirmed) {
        // Teacher confirmed the action - update availability
        $status = ($action_type === 'mark_available') ? 'available' : 'unavailable';
        $notes = "Teacher confirmed via QR scan - {$action_type}";
        
        // Check if availability record exists for this teacher
        $check_query = "SELECT id, is_available FROM teacher_availability 
                       WHERE teacher_id = ?";
        $check_stmt = mysqli_prepare($conn, $check_query);
        mysqli_stmt_bind_param($check_stmt, "i", $teacher_id);
        mysqli_stmt_execute($check_stmt);
        $check_result = mysqli_stmt_get_result($check_stmt);
        
        $is_available = ($status === 'available') ? 1 : 0;
        
        if (mysqli_num_rows($check_result) > 0) {
            // Update existing record
            $existing_record = mysqli_fetch_assoc($check_result);
            
            $update_query = "UPDATE teacher_availability 
                            SET is_available = ?, last_updated = NOW()
                            WHERE id = ?";
            $update_stmt = mysqli_prepare($conn, $update_query);
            mysqli_stmt_bind_param($update_stmt, "ii", $is_available, $existing_record['id']);
            
            if (!mysqli_stmt_execute($update_stmt)) {
                throw new Exception('Failed to update teacher availability');
            }
            
            $action = 'updated';
        } else {
            // Insert new record
            $insert_query = "INSERT INTO teacher_availability 
                            (teacher_id, is_available, last_updated) 
                            VALUES (?, ?, NOW())";
            $insert_stmt = mysqli_prepare($conn, $insert_query);
            mysqli_stmt_bind_param($insert_stmt, "ii", $teacher_id, $is_available);
            
            if (!mysqli_stmt_execute($insert_stmt)) {
                throw new Exception('Failed to insert teacher availability');
            }
            
            $action = 'created';
        }
        
        $message = "Teacher availability {$action} successfully";
    } else {
        // Teacher cancelled - no action taken
        $action = 'cancelled';
        $message = "Teacher availability update cancelled";
    }
    
    // Commit transaction
    $conn->commit();
    
    // Log the action
    error_log("Teacher availability confirmation: Teacher ID {$teacher_id} ({$teacher['first_name']} {$teacher['last_name']}) - Action: {$action_type}, Confirmed: " . ($confirmed ? 'Yes' : 'No') . " on {$today}");
    
    echo json_encode([
        'success' => true,
        'message' => $message,
        'data' => [
            'teacher_id' => $teacher_id,
            'teacher_name' => $teacher['first_name'] . ' ' . $teacher['last_name'],
            'action_type' => $action_type,
            'confirmed' => $confirmed,
            'action' => $action,
            'date' => $today
        ]
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    
    error_log("Teacher availability confirmation error: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} finally {
    $conn->autocommit(TRUE); // Restore autocommit
}
?>
