<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Debug: Log what we received
error_log("=== ADD FACULTY DEBUG START ===");
error_log("POST data received: " . print_r($_POST, true));
error_log("FILES data received: " . print_r($_FILES, true));
error_log("Request method: " . $_SERVER['REQUEST_METHOD']);
error_log("Content type: " . ($_SERVER['CONTENT_TYPE'] ?? 'Not set'));
error_log("=== END DEBUG START ===");

// Check if user is logged in and has head role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'head') {
    header('Location: ../index.php');
    exit();
}

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: teachers.php');
    exit();
}

// Get user information
$user_id = $_SESSION['user_id'];

// Get head information
$head_query = "SELECT h.* FROM heads h WHERE h.user_id = ?";
$head_stmt = mysqli_prepare($conn, $head_query);
mysqli_stmt_bind_param($head_stmt, "i", $user_id);
mysqli_stmt_execute($head_stmt);
$head_result = mysqli_stmt_get_result($head_stmt);
$head_info = mysqli_fetch_assoc($head_result);

if (!$head_info) {
    header('Location: teachers.php?error=unauthorized');
    exit();
}

// Sanitize input data
$qrcode = sanitize_input($_POST['qrcode']);
$first_name = sanitize_input($_POST['first_name']);
$last_name = sanitize_input($_POST['last_name']);
$middle_name = sanitize_input($_POST['middle_name']);
$position = sanitize_input($_POST['position']);
$department = sanitize_input($_POST['department']);

// Validate required fields
if (empty($qrcode) || empty($first_name) || empty($last_name) || empty($position)) {
    header('Location: teachers.php?error=missing_fields');
    exit();
}

// Verify department matches head's department
if ($department !== $head_info['department']) {
    header('Location: teachers.php?error=unauthorized_department');
    exit();
}

// Check if QR code already exists
$qr_check_query = "SELECT id FROM faculty WHERE qrcode = ?";
$qr_check_stmt = mysqli_prepare($conn, $qr_check_query);
mysqli_stmt_bind_param($qr_check_stmt, "s", $qrcode);
mysqli_stmt_execute($qr_check_stmt);
$qr_check_result = mysqli_stmt_get_result($qr_check_stmt);

if (mysqli_num_rows($qr_check_result) > 0) {
    header('Location: teachers.php?error=qrcode_exists');
    exit();
}

// Generate sample email based on name
$email_base = strtolower($first_name) . '.' . strtolower($last_name);
$email = $email_base . '@seait.edu.ph';

// Check if email already exists, if so, add a number
$email_check_query = "SELECT id FROM faculty WHERE email = ?";
$counter = 1;
$original_email = $email;

do {
    $email_check_query = "SELECT id FROM faculty WHERE email = ?";
    $email_check_stmt = mysqli_prepare($conn, $email_check_query);
    mysqli_stmt_bind_param($email_check_stmt, "s", $email);
    mysqli_stmt_execute($email_check_stmt);
    $email_check_result = mysqli_stmt_get_result($email_check_stmt);
    
    if (mysqli_num_rows($email_check_result) > 0) {
        $email = $email_base . $counter . '@seait.edu.ph';
        $counter++;
    } else {
        break;
    }
} while ($counter <= 100); // Safety limit

// Handle photo upload
$photo_path = null;

// Debug: Log what we received
error_log("=== PHOTO DEBUG ===");
error_log("POST captured_photo exists: " . (isset($_POST['captured_photo']) ? 'YES' : 'NO'));
if (isset($_POST['captured_photo'])) {
    error_log("captured_photo length: " . strlen($_POST['captured_photo']));
    error_log("captured_photo starts with: " . substr($_POST['captured_photo'], 0, 50));
}

// Check for captured photo (base64) first
if (isset($_POST['captured_photo']) && !empty($_POST['captured_photo'])) {
    error_log("Processing captured photo...");
    $base64_data = $_POST['captured_photo'];
    
    // Remove data URL prefix if present
    if (strpos($base64_data, 'data:image/') === 0) {
        error_log("Removing data URL prefix...");
        $base64_data = substr($base64_data, strpos($base64_data, ',') + 1);
        error_log("Base64 data after prefix removal length: " . strlen($base64_data));
    }
    
    // Decode base64
    error_log("Attempting to decode base64...");
    $image_data = base64_decode($base64_data);
    
    if ($image_data !== false) {
        error_log("Base64 decode successful, image data length: " . strlen($image_data));
        $upload_dir = dirname(__FILE__) . '/../uploads/faculty_photos/';
        
        // Create directory if it doesn't exist
        if (!is_dir($upload_dir)) {
            error_log("Creating upload directory: " . $upload_dir);
            mkdir($upload_dir, 0755, true);
        }
        
        // Generate unique filename
        $filename = $qrcode . '_' . time() . '.jpg';
        $upload_path = $upload_dir . $filename;
        error_log("Attempting to save file to: " . $upload_path);
        
        if (file_put_contents($upload_path, $image_data)) {
            $photo_path = 'uploads/faculty_photos/' . $filename;
            error_log("File saved successfully! Photo path: " . $photo_path);
        } else {
            error_log("Failed to save file using file_put_contents");
        }
    } else {
        error_log("Failed to decode base64 image data");
    }
}
// Check for uploaded file if no captured photo
elseif (isset($_FILES['faculty_photo']) && $_FILES['faculty_photo']['error'] === UPLOAD_ERR_OK) {
    error_log("Processing uploaded file photo...");
    $upload_dir = dirname(__FILE__) . '/../uploads/faculty_photos/';
    
    // Create directory if it doesn't exist
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $file_info = pathinfo($_FILES['faculty_photo']['name']);
    $file_extension = strtolower($file_info['extension']);
    
    // Validate file type
    $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
    if (in_array($file_extension, $allowed_types)) {
        // Validate file size (2MB max)
        if ($_FILES['faculty_photo']['size'] <= 2 * 1024 * 1024) {
            // Generate unique filename
            $filename = $qrcode . '_' . time() . '.' . $file_extension;
            $upload_path = $upload_dir . $filename;
            
            if (move_uploaded_file($_FILES['faculty_photo']['tmp_name'], $upload_path)) {
                $photo_path = 'uploads/faculty_photos/' . $filename;
                error_log("Uploaded file saved successfully! Photo path: " . $photo_path);
            }
        }
    }
} else {
    error_log("No photo to process - neither captured photo nor uploaded file");
}

error_log("Final photo_path value: " . ($photo_path ?: 'NULL'));
error_log("=== END PHOTO DEBUG ===");

// Sample data for new faculty
$sample_bio = 'Faculty member in the ' . $department . ' department. Profile to be updated by HR.';

// Default password for new faculty
$default_password = password_hash('Seait123', PASSWORD_DEFAULT);

try {
    // Start transaction
    mysqli_begin_transaction($conn);
    
    // Insert into faculty table
    $faculty_query = "INSERT INTO faculty (first_name, last_name, email, position, department, bio, image_url, qrcode, password, is_active, created_at) 
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())";
    $faculty_stmt = mysqli_prepare($conn, $faculty_query);
    
    if (!$faculty_stmt) {
        throw new Exception("Error preparing faculty statement: " . mysqli_error($conn));
    }
    
    error_log("About to save faculty with photo_path: " . ($photo_path ?: 'NULL'));
    
    mysqli_stmt_bind_param($faculty_stmt, "sssssssss", 
        $first_name, $last_name, $email, $position, $department, $sample_bio, $photo_path, $qrcode, $default_password);
    
    if (!mysqli_stmt_execute($faculty_stmt)) {
        throw new Exception("Error inserting faculty: " . mysqli_stmt_error($faculty_stmt));
    }
    
    $faculty_id = mysqli_insert_id($conn);
    
    // Note: faculty_details table was removed during FaCallTi cleanup
    // Only basic faculty information is stored in the faculty table
    
    // Commit transaction
    mysqli_commit($conn);
    
    // Redirect with success message
    header('Location: teachers.php?success=faculty_added&qrcode=' . urlencode($qrcode));
    exit();
    
} catch (Exception $e) {
    // Rollback transaction on error
    mysqli_rollback($conn);
    
    // Log error
    error_log("Error adding faculty: " . $e->getMessage());
    
    // Redirect with error message
    header('Location: teachers.php?error=add_failed&message=' . urlencode($e->getMessage()));
    exit();
}
?>
