<?php
// Turn on error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/id_encryption.php';
require_once 'includes/employee_id_generator.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is logged in and has human_resource role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'human_resource') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// Get the faculty ID from POST data
$encrypted_id = $_POST['faculty_id'] ?? '';
if (empty($encrypted_id)) {
    echo json_encode(['success' => false, 'message' => 'Faculty ID is required']);
    exit();
}

// Decrypt the faculty ID
$faculty_id = safe_decrypt_id($encrypted_id);
if ($faculty_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid faculty ID: ' . $encrypted_id]);
    exit();
}

try {
    // Get form data for main faculty table
    $first_name = mysqli_real_escape_string($conn, $_POST['first_name'] ?? '');
    $last_name = mysqli_real_escape_string($conn, $_POST['last_name'] ?? '');
    $email = mysqli_real_escape_string($conn, $_POST['email'] ?? '');
    $position = mysqli_real_escape_string($conn, $_POST['position'] ?? '');
    $department = mysqli_real_escape_string($conn, $_POST['department'] ?? '');
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    // Get form data for faculty_details table
    $middle_name = mysqli_real_escape_string($conn, $_POST['middle_name'] ?? '');
    $date_of_birth = mysqli_real_escape_string($conn, $_POST['date_of_birth'] ?? '');
    $gender = mysqli_real_escape_string($conn, $_POST['gender'] ?? '');
    $civil_status = mysqli_real_escape_string($conn, $_POST['civil_status'] ?? '');
    $nationality = mysqli_real_escape_string($conn, $_POST['nationality'] ?? '');
    $religion = mysqli_real_escape_string($conn, $_POST['religion'] ?? '');
    $phone = mysqli_real_escape_string($conn, $_POST['phone'] ?? '');
    $emergency_contact_name = mysqli_real_escape_string($conn, $_POST['emergency_contact_name'] ?? '');
    $emergency_contact_number = mysqli_real_escape_string($conn, $_POST['emergency_contact_number'] ?? '');
    $address = mysqli_real_escape_string($conn, $_POST['address'] ?? '');
    $employee_id = mysqli_real_escape_string($conn, $_POST['employee_id'] ?? '');
    $date_of_hire = mysqli_real_escape_string($conn, $_POST['date_of_hire'] ?? '');
    $employment_type = mysqli_real_escape_string($conn, $_POST['employment_type'] ?? '');
    $basic_salary = isset($_POST['basic_salary']) ? (float)$_POST['basic_salary'] : 0;
    $salary_grade = mysqli_real_escape_string($conn, $_POST['salary_grade'] ?? '');
    $allowances = isset($_POST['allowances']) ? (float)$_POST['allowances'] : 0;
    $pay_schedule = mysqli_real_escape_string($conn, $_POST['pay_schedule'] ?? '');
    $highest_education = mysqli_real_escape_string($conn, $_POST['highest_education'] ?? '');
    $field_of_study = mysqli_real_escape_string($conn, $_POST['field_of_study'] ?? '');
    $school_university = mysqli_real_escape_string($conn, $_POST['school_university'] ?? '');
    $year_graduated = isset($_POST['year_graduated']) ? (int)$_POST['year_graduated'] : null;
    $tin_number = mysqli_real_escape_string($conn, $_POST['tin_number'] ?? '');
    $sss_number = mysqli_real_escape_string($conn, $_POST['sss_number'] ?? '');
    $philhealth_number = mysqli_real_escape_string($conn, $_POST['philhealth_number'] ?? '');
    $pagibig_number = mysqli_real_escape_string($conn, $_POST['pagibig_number'] ?? '');

    // Basic validation
    if (empty($first_name) || empty($last_name) || empty($email) || empty($position) || empty($department)) {
        echo json_encode(['success' => false, 'message' => 'All required fields must be filled']);
        exit();
    }

    // Check if email already exists (excluding current faculty)
    $check_query = "SELECT id FROM faculty WHERE email = '$email' AND id != $faculty_id";
    $check_result = mysqli_query($conn, $check_query);

    if (mysqli_num_rows($check_result) > 0) {
        echo json_encode(['success' => false, 'message' => 'Email address already exists']);
        exit();
    }

    // Start transaction
    mysqli_begin_transaction($conn);
    
    // Update main faculty table
    $update_faculty_query = "UPDATE faculty SET 
        first_name = '$first_name', 
        last_name = '$last_name', 
        email = '$email', 
        position = '$position', 
        department = '$department', 
        is_active = $is_active 
        WHERE id = $faculty_id";

    if (!mysqli_query($conn, $update_faculty_query)) {
        throw new Exception('Error updating faculty: ' . mysqli_error($conn));
    }

    // Check if faculty_details record exists
    $check_details_query = "SELECT id FROM faculty_details WHERE faculty_id = $faculty_id";
    $check_details_result = mysqli_query($conn, $check_details_query);
    $details_exists = mysqli_num_rows($check_details_result) > 0;

    if ($details_exists) {
        // Update existing faculty_details
        $update_details_query = "UPDATE faculty_details SET 
            middle_name = " . ($middle_name ? "'$middle_name'" : 'NULL') . ",
            date_of_birth = " . ($date_of_birth ? "'$date_of_birth'" : 'NULL') . ",
            gender = " . ($gender ? "'$gender'" : 'NULL') . ",
            civil_status = " . ($civil_status ? "'$civil_status'" : 'NULL') . ",
            nationality = " . ($nationality ? "'$nationality'" : 'NULL') . ",
            religion = " . ($religion ? "'$religion'" : 'NULL') . ",
            phone = " . ($phone ? "'$phone'" : 'NULL') . ",
            emergency_contact_name = " . ($emergency_contact_name ? "'$emergency_contact_name'" : 'NULL') . ",
            emergency_contact_number = " . ($emergency_contact_number ? "'$emergency_contact_number'" : 'NULL') . ",
            address = " . ($address ? "'$address'" : 'NULL') . ",
            employee_id = " . ($employee_id ? "'$employee_id'" : 'NULL') . ",
            date_of_hire = " . ($date_of_hire ? "'$date_of_hire'" : 'NULL') . ",
            employment_type = " . ($employment_type ? "'$employment_type'" : 'NULL') . ",
            basic_salary = $basic_salary,
            salary_grade = " . ($salary_grade ? "'$salary_grade'" : 'NULL') . ",
            allowances = $allowances,
            pay_schedule = " . ($pay_schedule ? "'$pay_schedule'" : 'NULL') . ",
            highest_education = " . ($highest_education ? "'$highest_education'" : 'NULL') . ",
            field_of_study = " . ($field_of_study ? "'$field_of_study'" : 'NULL') . ",
            school_university = " . ($school_university ? "'$school_university'" : 'NULL') . ",
            year_graduated = " . ($year_graduated ? $year_graduated : 'NULL') . ",
            tin_number = " . ($tin_number ? "'$tin_number'" : 'NULL') . ",
            sss_number = " . ($sss_number ? "'$sss_number'" : 'NULL') . ",
            philhealth_number = " . ($philhealth_number ? "'$philhealth_number'" : 'NULL') . ",
            pagibig_number = " . ($pagibig_number ? "'$pagibig_number'" : 'NULL') . ",
            updated_at = NOW()
            WHERE faculty_id = $faculty_id";
    } else {
        // Insert new faculty_details record
        $update_details_query = "INSERT INTO faculty_details (
            faculty_id, middle_name, date_of_birth, gender, civil_status, nationality, religion,
            phone, emergency_contact_name, emergency_contact_number, address,
            employee_id, date_of_hire, employment_type, basic_salary, salary_grade, allowances, pay_schedule,
            highest_education, field_of_study, school_university, year_graduated,
            tin_number, sss_number, philhealth_number, pagibig_number, created_at
        ) VALUES (
            $faculty_id, " . ($middle_name ? "'$middle_name'" : 'NULL') . ", " . ($date_of_birth ? "'$date_of_birth'" : 'NULL') . ", " . ($gender ? "'$gender'" : 'NULL') . ", " . ($civil_status ? "'$civil_status'" : 'NULL') . ", " . ($nationality ? "'$nationality'" : 'NULL') . ", " . ($religion ? "'$religion'" : 'NULL') . ",
            " . ($phone ? "'$phone'" : 'NULL') . ", " . ($emergency_contact_name ? "'$emergency_contact_name'" : 'NULL') . ", " . ($emergency_contact_number ? "'$emergency_contact_number'" : 'NULL') . ", " . ($address ? "'$address'" : 'NULL') . ",
            " . ($employee_id ? "'$employee_id'" : 'NULL') . ", " . ($date_of_hire ? "'$date_of_hire'" : 'NULL') . ", " . ($employment_type ? "'$employment_type'" : 'NULL') . ", $basic_salary, " . ($salary_grade ? "'$salary_grade'" : 'NULL') . ", $allowances, " . ($pay_schedule ? "'$pay_schedule'" : 'NULL') . ",
            " . ($highest_education ? "'$highest_education'" : 'NULL') . ", " . ($field_of_study ? "'$field_of_study'" : 'NULL') . ", " . ($school_university ? "'$school_university'" : 'NULL') . ", " . ($year_graduated ? $year_graduated : 'NULL') . ",
            " . ($tin_number ? "'$tin_number'" : 'NULL') . ", " . ($sss_number ? "'$sss_number'" : 'NULL') . ", " . ($philhealth_number ? "'$philhealth_number'" : 'NULL') . ", " . ($pagibig_number ? "'$pagibig_number'" : 'NULL') . ", NOW()
        )";
    }

    if (!mysqli_query($conn, $update_details_query)) {
        throw new Exception('Error updating faculty details: ' . mysqli_error($conn));
    }

    // Commit transaction
    mysqli_commit($conn);
    
    echo json_encode([
        'success' => true, 
        'message' => 'Faculty member updated successfully',
        'data' => [
            'faculty_id' => $faculty_id,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'email' => $email,
            'position' => $position,
            'department' => $department
        ]
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    if (mysqli_connect_errno() === 0) {
        mysqli_rollback($conn);
    }
    echo json_encode(['success' => false, 'message' => 'Error updating faculty member: ' . $e->getMessage()]);
}
?>
