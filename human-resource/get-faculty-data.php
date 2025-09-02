<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/id_encryption.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is logged in and has human_resource role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'human_resource') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Check if faculty ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'Faculty ID is required']);
    exit();
}

// Decrypt the faculty ID
$faculty_id = safe_decrypt_id($_GET['id']);
if ($faculty_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid faculty ID']);
    exit();
}

try {
    // Get faculty details with comprehensive HR information using JOIN
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
    mysqli_stmt_bind_param($stmt, "i", $faculty_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if (!$faculty = mysqli_fetch_assoc($result)) {
        echo json_encode(['success' => false, 'message' => 'Faculty member not found']);
        exit();
    }

    echo json_encode([
        'success' => true,
        'faculty' => $faculty
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error loading faculty data: ' . $e->getMessage()]);
}
?>
