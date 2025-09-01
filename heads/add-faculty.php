<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

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
$department = sanitize_input($_POST['department']);

// Validate required fields
if (empty($qrcode) || empty($first_name) || empty($last_name)) {
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

// Check for captured photo (base64)
if (isset($_POST['captured_photo']) && !empty($_POST['captured_photo'])) {
    $base64_data = $_POST['captured_photo'];
    
    // Remove data URL prefix
    if (strpos($base64_data, 'data:image/') === 0) {
        $base64_data = substr($base64_data, strpos($base64_data, ',') + 1);
    }
    
    // Decode base64
    $image_data = base64_decode($base64_data);
    
    if ($image_data !== false) {
        $upload_dir = dirname(__FILE__) . '/../uploads/faculty_photos/';
        
        // Create directory if it doesn't exist
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        // Generate unique filename
        $filename = $qrcode . '_' . time() . '.jpg';
        $upload_path = $upload_dir . $filename;
        
        if (file_put_contents($upload_path, $image_data)) {
            $photo_path = 'uploads/faculty_photos/' . $filename;
        }
    } else {
        error_log("Failed to decode base64 image data");
    }
}
// Check for uploaded file
elseif (isset($_FILES['faculty_photo']) && $_FILES['faculty_photo']['error'] === UPLOAD_ERR_OK) {
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
            }
        } else {
            error_log("File too large: " . $_FILES['faculty_photo']['size'] . " bytes");
        }
    } else {
        error_log("Invalid file type: " . $file_extension);
    }
}



// Sample data for new faculty
$sample_position = 'Instructor'; // Default position
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
    

    
    mysqli_stmt_bind_param($faculty_stmt, "sssssssss", 
        $first_name, $last_name, $email, $sample_position, $department, $sample_bio, $photo_path, $qrcode, $default_password);
    
    if (!mysqli_stmt_execute($faculty_stmt)) {
        throw new Exception("Error inserting faculty: " . mysqli_stmt_error($faculty_stmt));
    }
    
    $faculty_id = mysqli_insert_id($conn);
    
    // Insert into faculty_details table with sample data
    $details_query = "INSERT INTO faculty_details (
        faculty_id, middle_name, gender, civil_status, nationality, religion, 
        phone, address, employee_id, date_of_hire, employment_type, 
        basic_salary, salary_grade, pay_schedule, highest_education, 
        field_of_study, school_university, created_at
    ) VALUES (?, ?, 'Male', 'Single', 'Filipino', 'Catholic', 
        '+63 900 000 0000', 'Sample Address, Philippines', ?, NOW(), 'Full-time', 
        25000.00, 'SG-11', 'Monthly', 'Bachelor''s Degree', 
        'Sample Field', 'Sample University', NOW())";
    
    $details_stmt = mysqli_prepare($conn, $details_query);
    
    if (!$details_stmt) {
        throw new Exception("Error preparing details statement: " . mysqli_error($conn));
    }
    
    // Generate employee ID based on QR code
    $employee_id = 'EMP-' . $qrcode;
    
    mysqli_stmt_bind_param($details_stmt, "iss", $faculty_id, $middle_name, $employee_id);
    
    if (!mysqli_stmt_execute($details_stmt)) {
        throw new Exception("Error inserting faculty details: " . mysqli_stmt_error($details_stmt));
    }
    
    // Commit transaction
    mysqli_commit($conn);
    
    // Success - redirect with success message
    header('Location: teachers.php?success=faculty_added&name=' . urlencode($first_name . ' ' . $last_name) . '&password=Seait123');
    exit();
    
} catch (Exception $e) {
    // Rollback transaction
    mysqli_rollback($conn);
    
    // Log error for debugging
    error_log("Add Faculty Error: " . $e->getMessage());
    
    // Redirect with error
    header('Location: teachers.php?error=database_error');
    exit();
}
?>
