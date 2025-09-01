<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/id_encryption.php';

// Check if user is logged in and has human_resource role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'human_resource') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Get faculty ID from request
$faculty_id = isset($_GET['id']) ? $_GET['id'] : '';

if (empty($faculty_id)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Faculty ID is required']);
    exit();
}

// Decrypt faculty ID
$decrypted_id = decrypt_id($faculty_id);
if (!$decrypted_id) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid faculty ID']);
    exit();
}

// Get faculty data with details using LEFT JOIN
$query = "SELECT 
    f.*,
    fd.middle_name,
    fd.date_of_birth,
    fd.gender,
    fd.civil_status,
    fd.nationality,
    fd.religion,
    fd.phone,
    fd.emergency_contact_name,
    fd.emergency_contact_number,
    fd.address,
            f.qrcode as employee_id,
    fd.date_of_hire,
    fd.employment_type,
    fd.basic_salary,
    fd.salary_grade,
    fd.allowances,
    fd.pay_schedule,
    fd.highest_education,
    fd.field_of_study,
    fd.school_university,
    fd.year_graduated,
    fd.tin_number,
    fd.sss_number,
    fd.philhealth_number,
    fd.pagibig_number,
    fd.created_at as details_created_at,
    fd.updated_at as details_updated_at
FROM faculty f
LEFT JOIN faculty_details fd ON f.id = fd.faculty_id
WHERE f.id = ?";

$stmt = mysqli_prepare($conn, $query);
if (!$stmt) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database error: ' . mysqli_error($conn)]);
    exit();
}

mysqli_stmt_bind_param($stmt, "i", $decrypted_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) === 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Faculty member not found']);
    exit();
}

$faculty_data = mysqli_fetch_assoc($result);

// Format the data for response
$response_data = [
    'success' => true,
    'faculty' => [
        // Basic faculty information
        'id' => $faculty_data['id'],
        'first_name' => $faculty_data['first_name'],
        'last_name' => $faculty_data['last_name'],
        'email' => $faculty_data['email'],
        'position' => $faculty_data['position'],
        'department' => $faculty_data['department'],
        'bio' => $faculty_data['bio'],
        'image_url' => $faculty_data['image_url'],
        'is_active' => $faculty_data['is_active'],
        'created_at' => $faculty_data['created_at'],
        
        // Personal Information
        'middle_name' => $faculty_data['middle_name'],
        'date_of_birth' => $faculty_data['date_of_birth'],
        'gender' => $faculty_data['gender'],
        'civil_status' => $faculty_data['civil_status'],
        'nationality' => $faculty_data['nationality'],
        'religion' => $faculty_data['religion'],
        
        // Contact Information
        'phone' => $faculty_data['phone'],
        'emergency_contact_name' => $faculty_data['emergency_contact_name'],
        'emergency_contact_number' => $faculty_data['emergency_contact_number'],
        'address' => $faculty_data['address'],
        
        // Employment Information
        'employee_id' => $faculty_data['employee_id'],
        'date_of_hire' => $faculty_data['date_of_hire'],
        'employment_type' => $faculty_data['employment_type'],
        
        // Salary Information
        'basic_salary' => $faculty_data['basic_salary'],
        'salary_grade' => $faculty_data['salary_grade'],
        'allowances' => $faculty_data['allowances'],
        'pay_schedule' => $faculty_data['pay_schedule'],
        
        // Educational Background
        'highest_education' => $faculty_data['highest_education'],
        'field_of_study' => $faculty_data['field_of_study'],
        'school_university' => $faculty_data['school_university'],
        'year_graduated' => $faculty_data['year_graduated'],
        
        // Government Information
        'tin_number' => $faculty_data['tin_number'],
        'sss_number' => $faculty_data['sss_number'],
        'philhealth_number' => $faculty_data['philhealth_number'],
        'pagibig_number' => $faculty_data['pagibig_number'],
        
        // Details timestamps
        'details_created_at' => $faculty_data['details_created_at'],
        'details_updated_at' => $faculty_data['details_updated_at']
    ]
];

header('Content-Type: application/json');
echo json_encode($response_data);

mysqli_stmt_close($stmt);
mysqli_close($conn);
?>
