<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/id_encryption.php';
require_once 'includes/employee_id_generator.php';

// Check if user is logged in and has human_resource role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'human_resource') {
    header('Location: ../index.php');
    exit();
}

// Set page title
$page_title = 'Edit Faculty';

// Check if faculty ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: manage-faculty.php');
    exit();
}

// Decrypt the faculty ID
$faculty_id = safe_decrypt_id($_GET['id']);
if ($faculty_id <= 0) {
    header('Location: manage-faculty.php');
    exit();
}

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
    fd.employee_id,
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
    header('Location: manage-faculty.php');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data for main faculty table
    $first_name = sanitize_input($_POST['first_name'] ?? '');
    $last_name = sanitize_input($_POST['last_name'] ?? '');
    $email = sanitize_input($_POST['email'] ?? '');
    $position = sanitize_input($_POST['position'] ?? '');
    $department = sanitize_input($_POST['department'] ?? '');
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    // Get form data for faculty_details table
    $middle_name = sanitize_input($_POST['middle_name'] ?? '');
    $date_of_birth = sanitize_input($_POST['date_of_birth'] ?? '');
    $gender = sanitize_input($_POST['gender'] ?? '');
    $civil_status = sanitize_input($_POST['civil_status'] ?? '');
    $nationality = sanitize_input($_POST['nationality'] ?? '');
    $religion = sanitize_input($_POST['religion'] ?? '');
    $phone = sanitize_input($_POST['phone'] ?? '');
    $emergency_contact_name = sanitize_input($_POST['emergency_contact_name'] ?? '');
    $emergency_contact_number = sanitize_input($_POST['emergency_contact_number'] ?? '');
    $address = sanitize_input($_POST['address'] ?? '');
    $employee_id = sanitize_input($_POST['employee_id'] ?? '');

// Validate employee ID if provided
if (!empty($employee_id)) {
    if (!validateEmployeeID($employee_id)) {
        $error = 'Invalid employee ID format. Use format: YYYY-XXXX (e.g., 2024-0001)';
    } elseif (!isEmployeeIDUnique($conn, $employee_id, $faculty_id)) {
        $error = 'Employee ID already exists';
    }
}
    $date_of_hire = sanitize_input($_POST['date_of_hire'] ?? '');
    $employment_type = sanitize_input($_POST['employment_type'] ?? '');
    $basic_salary = isset($_POST['basic_salary']) ? (float)$_POST['basic_salary'] : 0;
    $salary_grade = sanitize_input($_POST['salary_grade'] ?? '');
    $allowances = isset($_POST['allowances']) ? (float)$_POST['allowances'] : 0;
    $pay_schedule = sanitize_input($_POST['pay_schedule'] ?? '');
    $highest_education = sanitize_input($_POST['highest_education'] ?? '');
    $field_of_study = sanitize_input($_POST['field_of_study'] ?? '');
    $school_university = sanitize_input($_POST['school_university'] ?? '');
    $year_graduated = isset($_POST['year_graduated']) ? (int)$_POST['year_graduated'] : null;
    $tin_number = sanitize_input($_POST['tin_number'] ?? '');
    $sss_number = sanitize_input($_POST['sss_number'] ?? '');
    $philhealth_number = sanitize_input($_POST['philhealth_number'] ?? '');
    $pagibig_number = sanitize_input($_POST['pagibig_number'] ?? '');

    // Validate required fields
    if (empty($first_name) || empty($last_name) || empty($email)) {
        $error = 'First name, last name, and email are required';
    } else {
        // Check if email already exists (excluding current faculty)
        $check_query = "SELECT id FROM faculty WHERE email = ? AND id != ?";
        $check_stmt = mysqli_prepare($conn, $check_query);
        mysqli_stmt_bind_param($check_stmt, "si", $email, $faculty_id);
        mysqli_stmt_execute($check_stmt);
        $check_result = mysqli_stmt_get_result($check_stmt);

        if (mysqli_num_rows($check_result) > 0) {
            $error = 'Email address already exists';
        } else {
            // Start transaction
            mysqli_begin_transaction($conn);
            
            try {
                // Update main faculty table
                $update_faculty_query = "UPDATE faculty SET first_name = ?, last_name = ?, email = ?, position = ?, department = ?, is_active = ? WHERE id = ?";
                $update_faculty_stmt = mysqli_prepare($conn, $update_faculty_query);
                mysqli_stmt_bind_param($update_faculty_stmt, "sssssii", $first_name, $last_name, $email, $position, $department, $is_active, $faculty_id);

                if (!mysqli_stmt_execute($update_faculty_stmt)) {
                    throw new Exception('Error updating faculty: ' . mysqli_error($conn));
                }

                // Check if faculty_details record exists
                $check_details_query = "SELECT id FROM faculty_details WHERE faculty_id = ?";
                $check_details_stmt = mysqli_prepare($conn, $check_details_query);
                mysqli_stmt_bind_param($check_details_stmt, "i", $faculty_id);
                mysqli_stmt_execute($check_details_stmt);
                $details_exists = mysqli_num_rows(mysqli_stmt_get_result($check_details_stmt)) > 0;

                if ($details_exists) {
                    // Update existing faculty_details
                    $update_details_query = "UPDATE faculty_details SET 
                        middle_name = ?, date_of_birth = ?, gender = ?, civil_status = ?, nationality = ?, religion = ?,
                        phone = ?, emergency_contact_name = ?, emergency_contact_number = ?, address = ?,
                        employee_id = ?, date_of_hire = ?, employment_type = ?, basic_salary = ?, salary_grade = ?, allowances = ?, pay_schedule = ?,
                        highest_education = ?, field_of_study = ?, school_university = ?, year_graduated = ?,
                        tin_number = ?, sss_number = ?, philhealth_number = ?, pagibig_number = ?, updated_at = NOW()
                        WHERE faculty_id = ?";
                    
                    $update_details_stmt = mysqli_prepare($conn, $update_details_query);
                    mysqli_stmt_bind_param($update_details_stmt, "ssssssssssssdsisssssssssssssssssi", 
                        $middle_name, $date_of_birth, $gender, $civil_status, $nationality, $religion,
                        $phone, $emergency_contact_name, $emergency_contact_number, $address,
                        $employee_id, $date_of_hire, $employment_type, $basic_salary, $salary_grade, $allowances, $pay_schedule,
                        $highest_education, $field_of_study, $school_university, $year_graduated,
                        $tin_number, $sss_number, $philhealth_number, $pagibig_number, $faculty_id
                    );
                } else {
                    // Insert new faculty_details record
                    $insert_details_query = "INSERT INTO faculty_details (
                        faculty_id, middle_name, date_of_birth, gender, civil_status, nationality, religion,
                        phone, emergency_contact_name, emergency_contact_number, address,
                        employee_id, date_of_hire, employment_type, basic_salary, salary_grade, allowances, pay_schedule,
                        highest_education, field_of_study, school_university, year_graduated,
                        tin_number, sss_number, philhealth_number, pagibig_number, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
                    
                    $insert_details_stmt = mysqli_prepare($conn, $insert_details_query);
                    mysqli_stmt_bind_param($insert_details_stmt, "isssssssssssssdsisssssssssssssssss", 
                        $faculty_id, $middle_name, $date_of_birth, $gender, $civil_status, $nationality, $religion,
                        $phone, $emergency_contact_name, $emergency_contact_number, $address,
                        $employee_id, $date_of_hire, $employment_type, $basic_salary, $salary_grade, $allowances, $pay_schedule,
                        $highest_education, $field_of_study, $school_university, $year_graduated,
                        $tin_number, $sss_number, $philhealth_number, $pagibig_number
                    );
                }

                if (!mysqli_stmt_execute($details_exists ? $update_details_stmt : $insert_details_stmt)) {
                    throw new Exception('Error updating faculty details: ' . mysqli_error($conn));
                }

                // Commit transaction
                mysqli_commit($conn);
                
                $success = 'Faculty member updated successfully';
                
                // Refresh faculty data
                $faculty['first_name'] = $first_name;
                $faculty['last_name'] = $last_name;
                $faculty['email'] = $email;
                $faculty['position'] = $position;
                $faculty['department'] = $department;
                $faculty['is_active'] = $is_active;
                $faculty['middle_name'] = $middle_name;
                $faculty['date_of_birth'] = $date_of_birth;
                $faculty['gender'] = $gender;
                $faculty['civil_status'] = $civil_status;
                $faculty['nationality'] = $nationality;
                $faculty['religion'] = $religion;
                $faculty['phone'] = $phone;
                $faculty['emergency_contact_name'] = $emergency_contact_name;
                $faculty['emergency_contact_number'] = $emergency_contact_number;
                $faculty['address'] = $address;
                $faculty['employee_id'] = $employee_id;
                $faculty['date_of_hire'] = $date_of_hire;
                $faculty['employment_type'] = $employment_type;
                $faculty['basic_salary'] = $basic_salary;
                $faculty['salary_grade'] = $salary_grade;
                $faculty['allowances'] = $allowances;
                $faculty['pay_schedule'] = $pay_schedule;
                $faculty['highest_education'] = $highest_education;
                $faculty['field_of_study'] = $field_of_study;
                $faculty['school_university'] = $school_university;
                $faculty['year_graduated'] = $year_graduated;
                $faculty['tin_number'] = $tin_number;
                $faculty['sss_number'] = $sss_number;
                $faculty['philhealth_number'] = $philhealth_number;
                $faculty['pagibig_number'] = $pagibig_number;
                
            } catch (Exception $e) {
                // Rollback transaction on error
                mysqli_rollback($conn);
                $error = 'Error updating faculty member: ' . $e->getMessage();
            }
        }
    }
}

// Get colleges for dropdown
$colleges_query = "SELECT name FROM colleges WHERE is_active = 1 ORDER BY name";
$colleges_result = mysqli_query($conn, $colleges_query);
$colleges = [];
while ($row = mysqli_fetch_assoc($colleges_result)) {
    $colleges[] = $row['name'];
}

// Include the header
include 'includes/header.php';
?>

<!-- Page Header -->
<div class="mb-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Edit Faculty Member</h1>
            <p class="text-gray-600">Update faculty member information</p>
        </div>
        <a href="manage-faculty.php" class="bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600 transform transition-all hover:scale-105 font-medium">
            <i class="fas fa-arrow-left mr-2"></i>Back to Faculty
        </a>
    </div>
</div>

<!-- Success/Error Messages -->
<?php if (isset($success)): ?>
    <div class="mb-6 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg">
        <i class="fas fa-check-circle mr-2"></i><?php echo $success; ?>
    </div>
<?php endif; ?>

<?php if (isset($error)): ?>
    <div class="mb-6 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg">
        <i class="fas fa-exclamation-circle mr-2"></i><?php echo $error; ?>
    </div>
<?php endif; ?>

<!-- Edit Faculty Form -->
<div class="bg-white rounded-xl shadow-lg p-6">
    <form method="POST" class="space-y-8">
        <!-- Basic Information Section -->
        <div class="bg-gray-50 rounded-xl p-6 border border-gray-200">
            <div class="flex items-center mb-6">
                <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center mr-4">
                    <i class="fas fa-user text-blue-600 text-lg"></i>
                </div>
                <div>
                    <h4 class="text-xl font-bold text-gray-900">Basic Information</h4>
                    <p class="text-gray-600 text-sm">Essential faculty member details</p>
                </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">First Name <span class="text-red-500">*</span></label>
                    <input type="text" name="first_name" value="<?php echo htmlspecialchars($faculty['first_name']); ?>" required
                           class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all bg-white shadow-sm"
                           placeholder="Enter first name">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Middle Name</label>
                    <input type="text" name="middle_name" value="<?php echo htmlspecialchars($faculty['middle_name'] ?? ''); ?>"
                           class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all"
                           placeholder="Enter middle name">
                </div>
                
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Last Name <span class="text-red-500">*</span></label>
                    <input type="text" name="last_name" value="<?php echo htmlspecialchars($faculty['last_name']); ?>" required
                           class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all bg-white shadow-sm"
                           placeholder="Enter last name">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Email Address <span class="text-red-500">*</span></label>
                    <input type="email" name="email" value="<?php echo htmlspecialchars($faculty['email']); ?>" required
                           class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all bg-white shadow-sm"
                           placeholder="Enter email address">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Phone Number</label>
                    <input type="tel" name="phone" value="<?php echo htmlspecialchars($faculty['phone'] ?? ''); ?>"
                           class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all"
                           placeholder="Enter phone number">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Position/Title <span class="text-red-500">*</span></label>
                    <input type="text" name="position" value="<?php echo htmlspecialchars($faculty['position']); ?>" required
                           class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all bg-white shadow-sm"
                           placeholder="Enter position/title">
                </div>
                
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Department/College <span class="text-red-500">*</span></label>
                    <select name="department" required class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all bg-white shadow-sm">
                        <option value="">Select Department/College</option>
                        <?php foreach ($colleges as $college): ?>
                            <option value="<?php echo htmlspecialchars($college); ?>" <?php echo ($faculty['department'] ?? '') === $college ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($college); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <!-- Personal Information Section -->
        <div class="bg-gray-50 rounded-xl p-6 border border-gray-200">
            <div class="flex items-center mb-6">
                <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center mr-4">
                    <i class="fas fa-user text-blue-600 text-lg"></i>
                </div>
                <div>
                    <h4 class="text-xl font-bold text-gray-900">Personal Information</h4>
                    <p class="text-gray-600 text-sm">Personal details and background</p>
                </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Date of Birth</label>
                    <input type="date" name="date_of_birth" value="<?php echo htmlspecialchars($faculty['date_of_birth'] ?? ''); ?>"
                           class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Gender</label>
                    <select name="gender" class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all">
                        <option value="">Select Gender</option>
                        <option value="Male" <?php echo ($faculty['gender'] ?? '') === 'Male' ? 'selected' : ''; ?>>Male</option>
                        <option value="Female" <?php echo ($faculty['gender'] ?? '') === 'Female' ? 'selected' : ''; ?>>Female</option>
                        <option value="Other" <?php echo ($faculty['gender'] ?? '') === 'Other' ? 'selected' : ''; ?>>Other</option>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Civil Status</label>
                    <select name="civil_status" class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all">
                        <option value="">Select Civil Status</option>
                        <option value="Single" <?php echo ($faculty['civil_status'] ?? '') === 'Single' ? 'selected' : ''; ?>>Single</option>
                        <option value="Married" <?php echo ($faculty['civil_status'] ?? '') === 'Married' ? 'selected' : ''; ?>>Married</option>
                        <option value="Widowed" <?php echo ($faculty['civil_status'] ?? '') === 'Widowed' ? 'selected' : ''; ?>>Widowed</option>
                        <option value="Divorced" <?php echo ($faculty['civil_status'] ?? '') === 'Divorced' ? 'selected' : ''; ?>>Divorced</option>
                        <option value="Separated" <?php echo ($faculty['civil_status'] ?? '') === 'Separated' ? 'selected' : ''; ?>>Separated</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Nationality</label>
                    <input type="text" name="nationality" value="<?php echo htmlspecialchars($faculty['nationality'] ?? ''); ?>"
                           class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all"
                           placeholder="Enter nationality">
                </div>
            </div>

            <div class="mt-6">
                <label class="block text-sm font-medium text-gray-700 mb-2">Religion</label>
                <input type="text" name="religion" value="<?php echo htmlspecialchars($faculty['religion'] ?? ''); ?>"
                       class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all"
                       placeholder="Enter religion">
            </div>
        </div>

        <!-- Contact Information Section -->
        <div class="bg-gray-50 rounded-xl p-6 border border-gray-200">
            <div class="flex items-center mb-6">
                <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center mr-4">
                    <i class="fas fa-phone text-green-600 text-lg"></i>
                </div>
                <div>
                    <h4 class="text-xl font-bold text-gray-900">Contact Information</h4>
                    <p class="text-gray-600 text-sm">Contact details and emergency information</p>
                </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Emergency Contact Name</label>
                    <input type="text" name="emergency_contact_name" value="<?php echo htmlspecialchars($faculty['emergency_contact_name'] ?? ''); ?>"
                           class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all"
                           placeholder="Enter emergency contact name">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Emergency Contact Number</label>
                    <input type="tel" name="emergency_contact_number" value="<?php echo htmlspecialchars($faculty['emergency_contact_number'] ?? ''); ?>"
                           class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all"
                           placeholder="Enter emergency contact number">
                </div>
            </div>

            <div class="mt-6">
                <label class="block text-sm font-medium text-gray-700 mb-2">Complete Address</label>
                <textarea name="address" rows="3"
                          class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all"
                          placeholder="Enter complete address"><?php echo htmlspecialchars($faculty['address'] ?? ''); ?></textarea>
            </div>
        </div>

        <!-- Employment Information Section -->
        <div class="bg-gray-50 rounded-xl p-6 border border-gray-200">
            <div class="flex items-center mb-6">
                <div class="w-10 h-10 bg-purple-100 rounded-full flex items-center justify-center mr-4">
                    <i class="fas fa-briefcase text-purple-600 text-lg"></i>
                </div>
                <div>
                    <h4 class="text-xl font-bold text-gray-900">Employment Information</h4>
                    <p class="text-gray-600 text-sm">Job details and employment status</p>
                </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Employee ID</label>
                    <div class="flex space-x-2">
                        <input type="text" name="employee_id" id="employee_id_input" value="<?php echo htmlspecialchars($faculty['employee_id'] ?? ''); ?>"
                               class="flex-1 px-3 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all"
                               placeholder="YYYY-XXXX (e.g., 2024-0001)" pattern="\d{4}-\d{4}">
                        <button type="button" onclick="generateEmployeeID()" 
                                class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors">
                            <i class="fas fa-magic mr-1"></i>Auto
                        </button>
                    </div>
                    <p class="text-xs text-gray-500 mt-1">Format: YYYY-XXXX (Year-Series)</p>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Date of Hire</label>
                    <input type="date" name="date_of_hire" value="<?php echo htmlspecialchars($faculty['date_of_hire'] ?? ''); ?>"
                           class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Employment Type</label>
                    <select name="employment_type" class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all">
                        <option value="">Select Employment Type</option>
                        <option value="Full-time" <?php echo ($faculty['employment_type'] ?? '') === 'Full-time' ? 'selected' : ''; ?>>Full-time</option>
                        <option value="Part-time" <?php echo ($faculty['employment_type'] ?? '') === 'Part-time' ? 'selected' : ''; ?>>Part-time</option>
                        <option value="Contract" <?php echo ($faculty['employment_type'] ?? '') === 'Contract' ? 'selected' : ''; ?>>Contract</option>
                        <option value="Temporary" <?php echo ($faculty['employment_type'] ?? '') === 'Temporary' ? 'selected' : ''; ?>>Temporary</option>
                        <option value="Probationary" <?php echo ($faculty['employment_type'] ?? '') === 'Probationary' ? 'selected' : ''; ?>>Probationary</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Pay Schedule</label>
                    <select name="pay_schedule" class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all">
                        <option value="">Select Pay Schedule</option>
                        <option value="Monthly" <?php echo ($faculty['pay_schedule'] ?? '') === 'Monthly' ? 'selected' : ''; ?>>Monthly</option>
                        <option value="Bi-weekly" <?php echo ($faculty['pay_schedule'] ?? '') === 'Bi-weekly' ? 'selected' : ''; ?>>Bi-weekly</option>
                        <option value="Weekly" <?php echo ($faculty['pay_schedule'] ?? '') === 'Weekly' ? 'selected' : ''; ?>>Weekly</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Salary Information Section -->
        <div class="bg-gray-50 rounded-xl p-6 border border-gray-200">
            <div class="flex items-center mb-6">
                <div class="w-10 h-10 bg-yellow-100 rounded-full flex items-center justify-center mr-4">
                    <i class="fas fa-money-bill-wave text-yellow-600 text-lg"></i>
                </div>
                <div>
                    <h4 class="text-xl font-bold text-gray-900">Salary Information</h4>
                    <p class="text-gray-600 text-sm">Compensation and benefits details</p>
                </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Basic Salary</label>
                    <input type="number" name="basic_salary" value="<?php echo htmlspecialchars($faculty['basic_salary'] ?? ''); ?>" step="0.01" min="0"
                           class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all"
                           placeholder="Enter basic salary">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Salary Grade</label>
                    <input type="text" name="salary_grade" value="<?php echo htmlspecialchars($faculty['salary_grade'] ?? ''); ?>"
                           class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all"
                           placeholder="Enter salary grade">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Allowances</label>
                    <input type="number" name="allowances" value="<?php echo htmlspecialchars($faculty['allowances'] ?? ''); ?>" step="0.01" min="0"
                           class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all"
                           placeholder="Enter allowances">
                </div>
            </div>
        </div>

        <!-- Educational Background Section -->
        <div class="bg-gray-50 rounded-xl p-6 border border-gray-200">
            <div class="flex items-center mb-6">
                <div class="w-10 h-10 bg-indigo-100 rounded-full flex items-center justify-center mr-4">
                    <i class="fas fa-graduation-cap text-indigo-600 text-lg"></i>
                </div>
                <div>
                    <h4 class="text-xl font-bold text-gray-900">Educational Background</h4>
                    <p class="text-gray-600 text-sm">Academic qualifications and credentials</p>
                </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Highest Educational Attainment</label>
                    <select name="highest_education" class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all">
                        <option value="">Select Highest Education</option>
                        <option value="High School" <?php echo ($faculty['highest_education'] ?? '') === 'High School' ? 'selected' : ''; ?>>High School</option>
                        <option value="Associate Degree" <?php echo ($faculty['highest_education'] ?? '') === 'Associate Degree' ? 'selected' : ''; ?>>Associate Degree</option>
                        <option value="Bachelor's Degree" <?php echo ($faculty['highest_education'] ?? '') === 'Bachelor\'s Degree' ? 'selected' : ''; ?>>Bachelor's Degree</option>
                        <option value="Master's Degree" <?php echo ($faculty['highest_education'] ?? '') === 'Master\'s Degree' ? 'selected' : ''; ?>>Master's Degree</option>
                        <option value="Doctorate" <?php echo ($faculty['highest_education'] ?? '') === 'Doctorate' ? 'selected' : ''; ?>>Doctorate</option>
                        <option value="Post-Doctorate" <?php echo ($faculty['highest_education'] ?? '') === 'Post-Doctorate' ? 'selected' : ''; ?>>Post-Doctorate</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Field of Study</label>
                    <input type="text" name="field_of_study" value="<?php echo htmlspecialchars($faculty['field_of_study'] ?? ''); ?>"
                           class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all"
                           placeholder="Enter field of study">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">School/University</label>
                    <input type="text" name="school_university" value="<?php echo htmlspecialchars($faculty['school_university'] ?? ''); ?>"
                           class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all"
                           placeholder="Enter school/university">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Year Graduated</label>
                    <input type="number" name="year_graduated" value="<?php echo htmlspecialchars($faculty['year_graduated'] ?? ''); ?>" min="1950" max="2030"
                           class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all"
                           placeholder="Enter year graduated">
                </div>
            </div>
        </div>

        <!-- Government Information Section -->
        <div class="bg-gray-50 rounded-xl p-6 border border-gray-200">
            <div class="flex items-center mb-6">
                <div class="w-10 h-10 bg-red-100 rounded-full flex items-center justify-center mr-4">
                    <i class="fas fa-id-card text-red-600 text-lg"></i>
                </div>
                <div>
                    <h4 class="text-xl font-bold text-gray-900">Government Information</h4>
                    <p class="text-gray-600 text-sm">Government-issued identification numbers</p>
                </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">TIN Number</label>
                    <input type="text" name="tin_number" value="<?php echo htmlspecialchars($faculty['tin_number'] ?? ''); ?>"
                           class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all"
                           placeholder="Enter TIN number">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">SSS Number</label>
                    <input type="text" name="sss_number" value="<?php echo htmlspecialchars($faculty['sss_number'] ?? ''); ?>"
                           class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all"
                           placeholder="Enter SSS number">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">PhilHealth Number</label>
                    <input type="text" name="philhealth_number" value="<?php echo htmlspecialchars($faculty['philhealth_number'] ?? ''); ?>"
                           class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all"
                           placeholder="Enter PhilHealth number">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">PAG-IBIG Number</label>
                    <input type="text" name="pagibig_number" value="<?php echo htmlspecialchars($faculty['pagibig_number'] ?? ''); ?>"
                           class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all"
                           placeholder="Enter PAG-IBIG number">
                </div>
            </div>
        </div>

        <!-- Status Section -->
        <div class="bg-gray-50 rounded-xl p-6 border border-gray-200">
            <div class="flex items-center">
                <input type="checkbox" name="is_active" id="is_active" value="1" <?php echo $faculty['is_active'] ? 'checked' : ''; ?>
                       class="h-4 w-4 text-seait-orange focus:ring-seait-orange border-gray-300 rounded">
                <label for="is_active" class="ml-2 block text-sm text-gray-900">
                    Active Faculty Member
                </label>
            </div>
        </div>

        <!-- Form Actions -->
        <div class="bg-white border-t border-gray-200 pt-6">
            <div class="flex justify-between items-center">
                <div class="text-sm text-gray-600">
                    <i class="fas fa-info-circle mr-2"></i>
                    All fields marked with * are required
                </div>
                <div class="flex space-x-4">
                    <a href="manage-faculty.php" 
                       class="px-8 py-3 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors font-medium border border-gray-300">
                        <i class="fas fa-times mr-2"></i>Cancel
                    </a>
                    <button type="submit" 
                            class="px-8 py-3 bg-gradient-to-r from-seait-orange to-orange-500 text-white rounded-lg hover:from-orange-500 hover:to-seait-orange transform transition-all hover:scale-105 font-medium shadow-lg">
                        <i class="fas fa-save mr-2"></i>Update Faculty Member
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>

<!-- Faculty Information -->
<div class="mt-6 bg-gray-50 rounded-xl p-6">
    <h3 class="text-lg font-medium text-gray-900 mb-4">Faculty Information</h3>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
        <div>
            <span class="font-medium text-gray-700">Created:</span>
            <span class="text-gray-900"><?php echo date('F j, Y g:i A', strtotime($faculty['created_at'])); ?></span>
        </div>
        <?php if ($faculty['updated_at']): ?>
        <div>
            <span class="font-medium text-gray-700">Last Updated:</span>
            <span class="text-gray-900"><?php echo date('F j, Y g:i A', strtotime($faculty['updated_at'])); ?></span>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Employee ID generation
function generateEmployeeID() {
    fetch('get-next-employee-id.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('employee_id_input').value = data.employee_id;
                // Show success message
                alert('Employee ID generated: ' + data.employee_id);
            } else {
                alert('Error generating employee ID: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Network error. Please try again.');
        });
}
</script>

<?php include 'includes/footer.php'; ?>
