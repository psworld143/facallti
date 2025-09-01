<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once 'includes/employee_id_generator.php';

// Check if user is logged in and has human_resource role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'human_resource') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// Enable error reporting for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Get form data for main faculty table
$first_name = isset($_POST['first_name']) ? sanitize_input($_POST['first_name']) : '';
$last_name = isset($_POST['last_name']) ? sanitize_input($_POST['last_name']) : '';
$email = isset($_POST['email']) ? sanitize_input($_POST['email']) : '';
$position = isset($_POST['position']) ? sanitize_input($_POST['position']) : '';
$department = isset($_POST['department']) ? sanitize_input($_POST['department']) : '';
$is_active = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1;

// Get form data for faculty_details table
$middle_name = isset($_POST['middle_name']) ? sanitize_input($_POST['middle_name']) : '';
$date_of_birth = isset($_POST['date_of_birth']) ? sanitize_input($_POST['date_of_birth']) : '';
$gender = isset($_POST['gender']) ? trim($_POST['gender']) : '';
$civil_status = isset($_POST['civil_status']) ? trim($_POST['civil_status']) : '';
$nationality = isset($_POST['nationality']) ? sanitize_input($_POST['nationality']) : '';
$religion = isset($_POST['religion']) ? sanitize_input($_POST['religion']) : '';
$phone = isset($_POST['phone']) ? sanitize_input($_POST['phone']) : '';
$emergency_contact_name = isset($_POST['emergency_contact_name']) ? sanitize_input($_POST['emergency_contact_name']) : '';
$emergency_contact_number = isset($_POST['emergency_contact_number']) ? sanitize_input($_POST['emergency_contact_number']) : '';
$address = isset($_POST['address']) ? sanitize_input($_POST['address']) : '';
// Handle employee ID - auto-generate if empty, validate if provided
$employee_id = isset($_POST['employee_id']) ? sanitize_input($_POST['employee_id']) : '';

// If employee ID is empty, auto-generate one
if (empty($employee_id)) {
    $employee_id = generateEmployeeID($conn);
    if (!$employee_id) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Error generating employee ID']);
        exit();
    }
} else {
    // Validate provided employee ID format
    if (!validateEmployeeID($employee_id)) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Invalid employee ID format. Use format: YYYY-XXXX (e.g., 2025-0001)']);
        exit();
    }
    
    // Check if employee ID is unique
    if (!isEmployeeIDUnique($conn, $employee_id)) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Employee ID already exists']);
        exit();
    }
}
$date_of_hire = isset($_POST['date_of_hire']) ? sanitize_input($_POST['date_of_hire']) : '';
$employment_type = isset($_POST['employment_type']) ? trim($_POST['employment_type']) : '';
$basic_salary = isset($_POST['basic_salary']) ? (float)$_POST['basic_salary'] : 0;
$salary_grade = isset($_POST['salary_grade']) ? sanitize_input($_POST['salary_grade']) : '';
$allowances = isset($_POST['allowances']) ? (float)$_POST['allowances'] : 0;
$pay_schedule = isset($_POST['pay_schedule']) && !empty($_POST['pay_schedule']) ? trim($_POST['pay_schedule']) : null;
$highest_education = isset($_POST['highest_education']) ? trim($_POST['highest_education']) : '';
$field_of_study = isset($_POST['field_of_study']) ? sanitize_input($_POST['field_of_study']) : '';
$school_university = isset($_POST['school_university']) ? sanitize_input($_POST['school_university']) : '';
$year_graduated = isset($_POST['year_graduated']) ? (int)$_POST['year_graduated'] : null;
$tin_number = isset($_POST['tin_number']) ? sanitize_input($_POST['tin_number']) : '';
$sss_number = isset($_POST['sss_number']) ? sanitize_input($_POST['sss_number']) : '';
$philhealth_number = isset($_POST['philhealth_number']) ? sanitize_input($_POST['philhealth_number']) : '';
$pagibig_number = isset($_POST['pagibig_number']) ? sanitize_input($_POST['pagibig_number']) : '';

// Validate required fields for main faculty table
$required_fields = [
    'first_name' => 'First Name',
    'last_name' => 'Last Name',
    'email' => 'Email Address',
    'position' => 'Position/Title',
    'department' => 'Department/College'
];

$missing_fields = [];
foreach ($required_fields as $field => $label) {
    if (empty($$field)) {
        $missing_fields[] = $label;
    }
}

if (!empty($missing_fields)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Please fill in all required fields: ' . implode(', ', $missing_fields)]);
    exit();
}

// Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Please enter a valid email address']);
    exit();
}

// Validate enum values
$valid_genders = ['Male', 'Female', 'Other'];
$valid_civil_statuses = ['Single', 'Married', 'Widowed', 'Divorced', 'Separated'];
$valid_employment_types = ['Full-time', 'Part-time', 'Contract', 'Temporary', 'Probationary'];
$valid_pay_schedules = ['Monthly', 'Bi-weekly', 'Weekly'];
$valid_highest_education = ['High School', 'Associate Degree', 'Bachelor\'s Degree', 'Master\'s Degree', 'Doctorate', 'Post-Doctorate'];

if (!empty($gender) && !in_array($gender, $valid_genders)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid gender value']);
    exit();
}

if (!empty($civil_status) && !in_array($civil_status, $valid_civil_statuses)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid civil status value']);
    exit();
}

if (!empty($employment_type) && !in_array($employment_type, $valid_employment_types)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid employment type value']);
    exit();
}

if ($pay_schedule !== null && !in_array($pay_schedule, $valid_pay_schedules)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid pay schedule value']);
    exit();
}

if (!empty($highest_education) && !in_array($highest_education, $valid_highest_education)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid highest education value: ' . $highest_education]);
    exit();
}

// Check if email already exists
$check_email_query = "SELECT id FROM faculty WHERE email = ?";
$check_stmt = mysqli_prepare($conn, $check_email_query);
mysqli_stmt_bind_param($check_stmt, "s", $email);
mysqli_stmt_execute($check_stmt);
$check_result = mysqli_stmt_get_result($check_stmt);

if (mysqli_num_rows($check_result) > 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Email address already exists']);
    exit();
}

// Start transaction
mysqli_begin_transaction($conn);

try {
    // Set default password for new faculty member
    $default_password = "Seait123";
    $hashed_password = password_hash($default_password, PASSWORD_DEFAULT);
    
    // Insert into main faculty table
    $insert_faculty_query = "INSERT INTO faculty (
        first_name, last_name, email, password, position, department, is_active, qrcode, created_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";

    $insert_faculty_stmt = mysqli_prepare($conn, $insert_faculty_query);
    if (!$insert_faculty_stmt) {
        throw new Exception('Error preparing faculty insert: ' . mysqli_error($conn));
    }

    mysqli_stmt_bind_param($insert_faculty_stmt, "ssssssis", 
        $first_name, $last_name, $email, $hashed_password, $position, $department, $is_active, $employee_id
    );
    
    if (!mysqli_stmt_execute($insert_faculty_stmt)) {
        throw new Exception('Error inserting faculty: ' . mysqli_error($conn));
    }
    
    $faculty_id = mysqli_insert_id($conn);
    
    // Insert into faculty_details table
    $insert_details_query = "INSERT INTO faculty_details (
        faculty_id, middle_name, date_of_birth, gender, civil_status, nationality, religion,
        phone, emergency_contact_name, emergency_contact_number, address,
        employee_id, date_of_hire, employment_type, basic_salary, salary_grade, allowances, pay_schedule,
        highest_education, field_of_study, school_university, year_graduated,
        tin_number, sss_number, philhealth_number, pagibig_number, created_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

    $insert_details_stmt = mysqli_prepare($conn, $insert_details_query);
    if (!$insert_details_stmt) {
        throw new Exception('Error preparing details insert: ' . mysqli_error($conn));
    }

    // Handle NULL values for optional fields
    $gender_param = !empty($gender) ? $gender : null;
    $civil_status_param = !empty($civil_status) ? $civil_status : null;
    $employment_type_param = !empty($employment_type) ? $employment_type : null;
    $pay_schedule_param = $pay_schedule !== null ? $pay_schedule : null;
    $highest_education_param = !empty($highest_education) ? $highest_education : null;
    $year_graduated_param = $year_graduated !== null ? $year_graduated : null;
    
    mysqli_stmt_bind_param($insert_details_stmt, "isssssssssssssdsdsssssssss", 
        $faculty_id, $middle_name, $date_of_birth, $gender_param, $civil_status_param, $nationality, $religion,
        $phone, $emergency_contact_name, $emergency_contact_number, $address,
        $employee_id, $date_of_hire, $employment_type_param, $basic_salary, $salary_grade, $allowances, $pay_schedule_param,
        $highest_education_param, $field_of_study, $school_university, $year_graduated_param,
        $tin_number, $sss_number, $philhealth_number, $pagibig_number
    );
    
    if (!mysqli_stmt_execute($insert_details_stmt)) {
        throw new Exception('Error inserting faculty details: ' . mysqli_error($conn));
    }
    
    // Log the action (optional - don't fail faculty addition if logging fails)
    try {
        $admin_id = $_SESSION['user_id'];
        
        // Check if the user exists in the users table
        $check_user_query = "SELECT id FROM users WHERE id = ?";
        $check_user_stmt = mysqli_prepare($conn, $check_user_query);
        mysqli_stmt_bind_param($check_user_stmt, "i", $admin_id);
        mysqli_stmt_execute($check_user_stmt);
        $check_user_result = mysqli_stmt_get_result($check_user_stmt);
        
        if (mysqli_num_rows($check_user_result) > 0) {
            // User exists, proceed with logging
            $action = "Added new faculty member: $first_name $last_name";
            $log_query = "INSERT INTO admin_activity_logs (admin_id, action, created_at) VALUES (?, ?, NOW())";
            $log_stmt = mysqli_prepare($conn, $log_query);
            if ($log_stmt) {
                mysqli_stmt_bind_param($log_stmt, "is", $admin_id, $action);
                mysqli_stmt_execute($log_stmt);
            }
        } else {
            // User doesn't exist, skip logging
            error_log("Activity logging skipped: User ID $admin_id not found in users table");
        }
    } catch (Exception $e) {
        // Log error but don't fail the faculty addition
        error_log("Activity logging failed: " . $e->getMessage());
    }
    
    // Commit transaction
    mysqli_commit($conn);
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true, 
        'message' => 'Faculty member added successfully! Default password is: Seait123',
        'faculty_id' => $faculty_id
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    mysqli_rollback($conn);
    
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Error adding faculty member: ' . $e->getMessage()]);
}

// Close statements
if (isset($insert_faculty_stmt)) mysqli_stmt_close($insert_faculty_stmt);
if (isset($insert_details_stmt)) mysqli_stmt_close($insert_details_stmt);

mysqli_close($conn);
?>
