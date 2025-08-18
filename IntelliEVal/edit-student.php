<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and has guidance_officer role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'guidance_officer') {
    header('Location: ../login.php');
    exit();
}

// Set page title
$page_title = 'Edit Student';

$message = '';
$message_type = '';

// Get student ID from URL
$student_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$student_id) {
    $_SESSION['message'] = 'Invalid student ID provided.';
    $_SESSION['message_type'] = 'error';
    header('Location: students.php');
    exit();
}

// Get student information
$student_query = "SELECT s.*, sp.phone, sp.address, sp.city, sp.state, sp.zip_code, sp.country,
                         sp.date_of_birth, sp.gender, sp.emergency_contact_name, sp.emergency_contact_phone,
                         sp.emergency_contact_relationship, sai.program_id, sai.year_level, sai.section,
                         sai.enrollment_date, sai.expected_graduation, sai.gpa, sai.units_completed,
                         sai.units_remaining, sai.academic_status
                  FROM students s
                  LEFT JOIN student_profiles sp ON s.id = sp.student_id
                  LEFT JOIN student_academic_info sai ON s.id = sai.student_id
                  WHERE s.id = ?";

$student_stmt = mysqli_prepare($conn, $student_query);
mysqli_stmt_bind_param($student_stmt, "i", $student_id);
mysqli_stmt_execute($student_stmt);
$student_result = mysqli_stmt_get_result($student_stmt);
$student = mysqli_fetch_assoc($student_result);

if (!$student) {
    $_SESSION['message'] = 'Student not found.';
    $_SESSION['message_type'] = 'error';
    header('Location: students.php');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate and sanitize input
    $first_name = sanitize_input($_POST['first_name']);
    $middle_name = sanitize_input($_POST['middle_name']);
    $last_name = sanitize_input($_POST['last_name']);
    $student_id_number = sanitize_input($_POST['student_id']);
    $email = sanitize_input($_POST['email']);
    $status = sanitize_input($_POST['status']);

    // Profile information
    $phone = sanitize_input($_POST['phone']);
    $address = sanitize_input($_POST['address']);
    $city = sanitize_input($_POST['city']);
    $state = sanitize_input($_POST['state']);
    $zip_code = sanitize_input($_POST['zip_code']);
    $country = sanitize_input($_POST['country']);
    $date_of_birth = sanitize_input($_POST['date_of_birth']);
    $gender = sanitize_input($_POST['gender']);
    $emergency_contact_name = sanitize_input($_POST['emergency_contact_name']);
    $emergency_contact_phone = sanitize_input($_POST['emergency_contact_phone']);
    $emergency_contact_relationship = sanitize_input($_POST['emergency_contact_relationship']);

    // Academic information
    $year_level = sanitize_input($_POST['year_level']);
    $section = sanitize_input($_POST['section']);
    $enrollment_date = sanitize_input($_POST['enrollment_date']);
    $expected_graduation = sanitize_input($_POST['expected_graduation']);
    $gpa = sanitize_input($_POST['gpa']);
    $units_completed = sanitize_input($_POST['units_completed']);
    $units_remaining = sanitize_input($_POST['units_remaining']);
    $academic_status = sanitize_input($_POST['academic_status']);

    // Validation
    $errors = [];

    if (empty($first_name)) $errors[] = "First name is required.";
    if (empty($last_name)) $errors[] = "Last name is required.";
    if (empty($student_id_number)) $errors[] = "Student ID is required.";
    if (empty($email)) $errors[] = "Email is required.";

    // Check if email is valid
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    }

    // Check if student ID already exists (excluding current student)
    $check_id_query = "SELECT id FROM students WHERE student_id = ? AND id != ?";
    $check_id_stmt = mysqli_prepare($conn, $check_id_query);
    mysqli_stmt_bind_param($check_id_stmt, "si", $student_id_number, $student_id);
    mysqli_stmt_execute($check_id_stmt);
    if (mysqli_stmt_get_result($check_id_stmt)->num_rows > 0) {
        $errors[] = "Student ID already exists.";
    }

    // Check if email already exists (excluding current student)
    $check_email_query = "SELECT id FROM students WHERE email = ? AND id != ?";
    $check_email_stmt = mysqli_prepare($conn, $check_email_query);
    mysqli_stmt_bind_param($check_email_stmt, "si", $email, $student_id);
    mysqli_stmt_execute($check_email_stmt);
    if (mysqli_stmt_get_result($check_email_stmt)->num_rows > 0) {
        $errors[] = "Email already exists.";
    }

    if (empty($errors)) {
        // Start transaction
        mysqli_begin_transaction($conn);

        try {
            // Update students table
            $update_student_query = "UPDATE students SET
                first_name = ?, middle_name = ?, last_name = ?, student_id = ?,
                email = ?, status = ?, updated_at = CURRENT_TIMESTAMP
                WHERE id = ?";
            $update_student_stmt = mysqli_prepare($conn, $update_student_query);
            mysqli_stmt_bind_param($update_student_stmt, "ssssssi",
                $first_name, $middle_name, $last_name, $student_id_number,
                $email, $status, $student_id);

            if (!mysqli_stmt_execute($update_student_stmt)) {
                throw new Exception("Error updating student: " . mysqli_error($conn));
            }

            // Update or insert student profile
            $profile_query = "INSERT INTO student_profiles
                (student_id, phone, address, city, state, zip_code, country, date_of_birth,
                 gender, emergency_contact_name, emergency_contact_phone, emergency_contact_relationship, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
                ON DUPLICATE KEY UPDATE
                phone = VALUES(phone), address = VALUES(address), city = VALUES(city),
                state = VALUES(state), zip_code = VALUES(zip_code), country = VALUES(country),
                date_of_birth = VALUES(date_of_birth), gender = VALUES(gender),
                emergency_contact_name = VALUES(emergency_contact_name),
                emergency_contact_phone = VALUES(emergency_contact_phone),
                emergency_contact_relationship = VALUES(emergency_contact_relationship),
                updated_at = CURRENT_TIMESTAMP";

            $profile_stmt = mysqli_prepare($conn, $profile_query);
            mysqli_stmt_bind_param($profile_stmt, "isssssssssss",
                $student_id, $phone, $address, $city, $state, $zip_code, $country,
                $date_of_birth, $gender, $emergency_contact_name,
                $emergency_contact_phone, $emergency_contact_relationship);

            if (!mysqli_stmt_execute($profile_stmt)) {
                throw new Exception("Error updating profile: " . mysqli_error($conn));
            }

            // Update or insert academic info
            $academic_query = "INSERT INTO student_academic_info
                (student_id, year_level, section, enrollment_date, expected_graduation,
                 gpa, units_completed, units_remaining, academic_status, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
                ON DUPLICATE KEY UPDATE
                year_level = VALUES(year_level), section = VALUES(section),
                enrollment_date = VALUES(enrollment_date), expected_graduation = VALUES(expected_graduation),
                gpa = VALUES(gpa), units_completed = VALUES(units_completed),
                units_remaining = VALUES(units_remaining), academic_status = VALUES(academic_status),
                updated_at = CURRENT_TIMESTAMP";

            $academic_stmt = mysqli_prepare($conn, $academic_query);
            mysqli_stmt_bind_param($academic_stmt, "issssddiis",
                $student_id, $year_level, $section, $enrollment_date, $expected_graduation,
                $gpa, $units_completed, $units_remaining, $academic_status);

            if (!mysqli_stmt_execute($academic_stmt)) {
                throw new Exception("Error updating academic info: " . mysqli_error($conn));
            }

            // Commit transaction
            mysqli_commit($conn);

            $message = "Student updated successfully!";
            $message_type = "success";

            // Refresh student data
            mysqli_stmt_execute($student_stmt);
            $student = mysqli_fetch_assoc(mysqli_stmt_get_result($student_stmt));

        } catch (Exception $e) {
            mysqli_rollback($conn);
            $message = $e->getMessage();
            $message_type = "error";
        }
    } else {
        $message = implode(" ", $errors);
        $message_type = "error";
    }
}

// Include the shared header
include 'includes/header.php';
?>

<!-- Custom CSS -->
<link rel="stylesheet" href="assets/css/edit-student.css">

<div class="mb-6 sm:mb-8">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-seait-dark mb-2">Edit Student</h1>
            <p class="text-sm sm:text-base text-gray-600">Update student information and details</p>
        </div>
        <div class="flex space-x-2">
            <a href="students.php" class="bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600 transition">
                <i class="fas fa-arrow-left mr-2"></i>Back to Students
            </a>
        </div>
    </div>
</div>

<!-- Message Display -->
<?php if ($message): ?>
<div class="message message-<?php echo $message_type; ?>">
    <?php echo htmlspecialchars($message); ?>
</div>
<?php endif; ?>

<!-- Edit Student Form -->
<div class="bg-white rounded-lg shadow-md overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-seait-orange to-orange-600">
        <h2 class="text-lg font-semibold text-white flex items-center">
            <i class="fas fa-user-edit mr-3"></i>Student Information
        </h2>
    </div>

    <form method="POST" class="p-6">
        <!-- Basic Information -->
        <div class="mb-8">
            <div class="section-header">
                <i class="fas fa-user"></i>
                <h3>Basic Information</h3>
            </div>
            <div class="form-grid">
                <div class="form-field">
                    <label class="required">First Name</label>
                    <input type="text" name="first_name" value="<?php echo htmlspecialchars($student['first_name']); ?>" required>
                </div>

                <div class="form-field">
                    <label>Middle Name</label>
                    <input type="text" name="middle_name" value="<?php echo htmlspecialchars($student['middle_name'] ?? ''); ?>">
                </div>

                <div class="form-field">
                    <label class="required">Last Name</label>
                    <input type="text" name="last_name" value="<?php echo htmlspecialchars($student['last_name']); ?>" required>
                </div>

                <div class="form-field">
                    <label class="required">Student ID</label>
                    <input type="text" name="student_id" value="<?php echo htmlspecialchars($student['student_id']); ?>" required>
                </div>

                <div class="form-field">
                    <label class="required">Email</label>
                    <input type="email" name="email" value="<?php echo htmlspecialchars($student['email']); ?>" required>
                </div>

                <div class="form-field">
                    <label>Status</label>
                    <select name="status">
                        <option value="active" <?php echo $student['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="pending" <?php echo $student['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="inactive" <?php echo $student['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Contact Information -->
        <div class="mb-8">
            <div class="section-header">
                <i class="fas fa-phone"></i>
                <h3>Contact Information</h3>
            </div>
            <div class="form-grid">
                <div class="form-field">
                    <label>Phone</label>
                    <input type="tel" name="phone" value="<?php echo htmlspecialchars($student['phone'] ?? ''); ?>">
                </div>

                <div class="form-field">
                    <label>Date of Birth</label>
                    <input type="date" name="date_of_birth" value="<?php echo htmlspecialchars($student['date_of_birth'] ?? ''); ?>">
                </div>

                <div class="form-field">
                    <label>Gender</label>
                    <select name="gender">
                        <option value="">Select Gender</option>
                        <option value="male" <?php echo ($student['gender'] ?? '') === 'male' ? 'selected' : ''; ?>>Male</option>
                        <option value="female" <?php echo ($student['gender'] ?? '') === 'female' ? 'selected' : ''; ?>>Female</option>
                        <option value="other" <?php echo ($student['gender'] ?? '') === 'other' ? 'selected' : ''; ?>>Other</option>
                    </select>
                </div>
            </div>

            <!-- Address Information -->
            <div class="form-grid">
                <div class="form-field form-grid-full">
                    <label>Address</label>
                    <textarea name="address" rows="3"><?php echo htmlspecialchars($student['address'] ?? ''); ?></textarea>
                </div>

                <div class="form-field">
                    <label>City</label>
                    <input type="text" name="city" value="<?php echo htmlspecialchars($student['city'] ?? ''); ?>">
                </div>

                <div class="form-field">
                    <label>State/Province</label>
                    <input type="text" name="state" value="<?php echo htmlspecialchars($student['state'] ?? ''); ?>">
                </div>

                <div class="form-field">
                    <label>ZIP Code</label>
                    <input type="text" name="zip_code" value="<?php echo htmlspecialchars($student['zip_code'] ?? ''); ?>">
                </div>

                <div class="form-field">
                    <label>Country</label>
                    <input type="text" name="country" value="<?php echo htmlspecialchars($student['country'] ?? 'Philippines'); ?>">
                </div>
            </div>

            <!-- Emergency Contact -->
            <div class="form-grid">
                <div class="form-field">
                    <label>Emergency Contact Name</label>
                    <input type="text" name="emergency_contact_name" value="<?php echo htmlspecialchars($student['emergency_contact_name'] ?? ''); ?>">
                </div>

                <div class="form-field">
                    <label>Emergency Contact Phone</label>
                    <input type="tel" name="emergency_contact_phone" value="<?php echo htmlspecialchars($student['emergency_contact_phone'] ?? ''); ?>">
                </div>

                <div class="form-field">
                    <label>Relationship</label>
                    <input type="text" name="emergency_contact_relationship" value="<?php echo htmlspecialchars($student['emergency_contact_relationship'] ?? ''); ?>">
                </div>
            </div>
        </div>

        <!-- Academic Information -->
        <div class="mb-8">
            <div class="section-header">
                <i class="fas fa-graduation-cap"></i>
                <h3>Academic Information</h3>
            </div>
            <div class="form-grid">
                <div class="form-field">
                    <label>Year Level</label>
                    <select name="year_level">
                        <option value="">Select Year Level</option>
                        <option value="1st Year" <?php echo ($student['year_level'] ?? '') === '1st Year' ? 'selected' : ''; ?>>1st Year</option>
                        <option value="2nd Year" <?php echo ($student['year_level'] ?? '') === '2nd Year' ? 'selected' : ''; ?>>2nd Year</option>
                        <option value="3rd Year" <?php echo ($student['year_level'] ?? '') === '3rd Year' ? 'selected' : ''; ?>>3rd Year</option>
                        <option value="4th Year" <?php echo ($student['year_level'] ?? '') === '4th Year' ? 'selected' : ''; ?>>4th Year</option>
                    </select>
                </div>

                <div class="form-field">
                    <label>Section</label>
                    <input type="text" name="section" value="<?php echo htmlspecialchars($student['section'] ?? ''); ?>">
                </div>

                <div class="form-field">
                    <label>Academic Status</label>
                    <select name="academic_status">
                        <option value="regular" <?php echo ($student['academic_status'] ?? '') === 'regular' ? 'selected' : ''; ?>>Regular</option>
                        <option value="probation" <?php echo ($student['academic_status'] ?? '') === 'probation' ? 'selected' : ''; ?>>Probation</option>
                        <option value="suspended" <?php echo ($student['academic_status'] ?? '') === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                        <option value="graduated" <?php echo ($student['academic_status'] ?? '') === 'graduated' ? 'selected' : ''; ?>>Graduated</option>
                        <option value="withdrawn" <?php echo ($student['academic_status'] ?? '') === 'withdrawn' ? 'selected' : ''; ?>>Withdrawn</option>
                    </select>
                </div>

                <div class="form-field">
                    <label>Enrollment Date</label>
                    <input type="date" name="enrollment_date" value="<?php echo htmlspecialchars($student['enrollment_date'] ?? ''); ?>">
                </div>

                <div class="form-field">
                    <label>Expected Graduation</label>
                    <input type="date" name="expected_graduation" value="<?php echo htmlspecialchars($student['expected_graduation'] ?? ''); ?>">
                </div>

                <div class="form-field">
                    <label>GPA</label>
                    <input type="number" name="gpa" step="0.01" min="0" max="4" value="<?php echo htmlspecialchars($student['gpa'] ?? ''); ?>">
                </div>

                <div class="form-field">
                    <label>Units Completed</label>
                    <input type="number" name="units_completed" min="0" value="<?php echo htmlspecialchars($student['units_completed'] ?? '0'); ?>">
                </div>

                <div class="form-field">
                    <label>Units Remaining</label>
                    <input type="number" name="units_remaining" min="0" value="<?php echo htmlspecialchars($student['units_remaining'] ?? '0'); ?>">
                </div>
            </div>
        </div>

        <!-- Form Actions -->
        <div class="form-actions">
            <a href="students.php" class="btn btn-secondary">
                Cancel
            </a>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save mr-2"></i>Update Student
            </button>
        </div>
    </form>
</div>

<?php
// Include the shared footer
include 'includes/footer.php';
?>