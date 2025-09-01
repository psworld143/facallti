<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/id_encryption.php';

// For testing, let's bypass session check temporarily
// if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'human_resource') {
//     header('Location: ../index.php');
//     exit();
// }

echo "<h1>Debug Faculty Form</h1>";

// Test with faculty ID 8
$faculty_id = 8;

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
    echo "❌ Faculty not found<br>";
    exit();
}

echo "<h2>Retrieved Faculty Data:</h2>";
echo "<pre>";
print_r($faculty);
echo "</pre>";

echo "<h2>Form Test:</h2>";
?>

<form method="POST">
    <h3>Basic Information</h3>
    <p>First Name: <input type="text" name="first_name" value="<?php echo htmlspecialchars($faculty['first_name']); ?>"></p>
    <p>Last Name: <input type="text" name="last_name" value="<?php echo htmlspecialchars($faculty['last_name']); ?>"></p>
    <p>Email: <input type="email" name="email" value="<?php echo htmlspecialchars($faculty['email']); ?>"></p>
    <p>Position: <input type="text" name="position" value="<?php echo htmlspecialchars($faculty['position']); ?>"></p>
    <p>Department: <input type="text" name="department" value="<?php echo htmlspecialchars($faculty['department']); ?>"></p>
    
    <h3>Personal Information</h3>
    <p>Middle Name: <input type="text" name="middle_name" value="<?php echo htmlspecialchars($faculty['middle_name'] ?? ''); ?>"></p>
    <p>Phone: <input type="tel" name="phone" value="<?php echo htmlspecialchars($faculty['phone'] ?? ''); ?>"></p>
    <p>Date of Birth: <input type="date" name="date_of_birth" value="<?php echo htmlspecialchars($faculty['date_of_birth'] ?? ''); ?>"></p>
    <p>Gender: 
        <select name="gender">
            <option value="">Select Gender</option>
            <option value="Male" <?php echo ($faculty['gender'] ?? '') === 'Male' ? 'selected' : ''; ?>>Male</option>
            <option value="Female" <?php echo ($faculty['gender'] ?? '') === 'Female' ? 'selected' : ''; ?>>Female</option>
            <option value="Other" <?php echo ($faculty['gender'] ?? '') === 'Other' ? 'selected' : ''; ?>>Other</option>
        </select>
    </p>
    
    <input type="submit" value="Test Submit">
</form>

<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "<h2>POST Data Received:</h2>";
    echo "<pre>";
    print_r($_POST);
    echo "</pre>";
}
?>
