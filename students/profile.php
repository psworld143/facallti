<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and has student role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../login.php');
    exit();
}

// Set page title
$page_title = 'Profile';

$message = '';
$message_type = '';

// Get student information
$student_query = "SELECT u.id as user_id, u.username, u.email, u.first_name, u.last_name,
                  s.id as student_id, s.student_id as student_number, s.status as student_status,
                  sp.phone, sp.address, sp.city, sp.state, sp.zip_code, sp.country,
                  sp.date_of_birth, sp.gender, sp.emergency_contact_name, sp.emergency_contact_phone,
                  sp.emergency_contact_relationship, sai.program_id, sai.year_level, sai.section,
                  sai.enrollment_date, sai.expected_graduation, sai.gpa, sai.units_completed,
                  sai.units_remaining, sai.academic_status
                  FROM users u
                  LEFT JOIN students s ON u.email COLLATE utf8mb4_general_ci = s.email COLLATE utf8mb4_general_ci
                  LEFT JOIN student_profiles sp ON s.id = sp.student_id
                  LEFT JOIN student_academic_info sai ON s.id = sai.student_id
                  WHERE u.id = ?";
$student_stmt = mysqli_prepare($conn, $student_query);
mysqli_stmt_bind_param($student_stmt, "i", $_SESSION['user_id']);
mysqli_stmt_execute($student_stmt);
$student_result = mysqli_stmt_get_result($student_stmt);
$student = mysqli_fetch_assoc($student_result);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_profile':
                $first_name = sanitize_input($_POST['first_name']);
                $last_name = sanitize_input($_POST['last_name']);
                $email = sanitize_input($_POST['email']);
                $student_number = sanitize_input($_POST['student_number']);
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

                // Validate student number uniqueness if provided
                if (!empty($student_number)) {
                    $check_student_number = "SELECT id FROM students WHERE student_id = ? AND email != ?";
                    $check_stmt = mysqli_prepare($conn, $check_student_number);
                    mysqli_stmt_bind_param($check_stmt, "ss", $student_number, $email);
                    mysqli_stmt_execute($check_stmt);
                    $check_result = mysqli_stmt_get_result($check_stmt);

                    if (mysqli_num_rows($check_result) > 0) {
                        $message = "Student ID number already exists. Please use a different ID number.";
                        $message_type = "error";
                        break;
                    }
                }

                // Update student basic info
                $update_student = "UPDATE users SET first_name = ?, last_name = ?, email = ? WHERE id = ?";
                $update_student_stmt = mysqli_prepare($conn, $update_student);
                mysqli_stmt_bind_param($update_student_stmt, "sssi", $first_name, $last_name, $email, $_SESSION['user_id']);

                if (mysqli_stmt_execute($update_student_stmt)) {
                    // Get the student ID from the students table
                    $get_student_id = "SELECT id FROM students WHERE email COLLATE utf8mb4_general_ci = ? COLLATE utf8mb4_general_ci";
                    $get_student_stmt = mysqli_prepare($conn, $get_student_id);
                    mysqli_stmt_bind_param($get_student_stmt, "s", $email);
                    mysqli_stmt_execute($get_student_stmt);
                    $student_id_result = mysqli_stmt_get_result($get_student_stmt);
                    $student_id_row = mysqli_fetch_assoc($student_id_result);

                    if ($student_id_row) {
                        $student_id = $student_id_row['id'];

                        // Update student number if provided
                        if (!empty($student_number)) {
                            $update_student_number = "UPDATE students SET student_id = ? WHERE id = ?";
                            $update_number_stmt = mysqli_prepare($conn, $update_student_number);
                            mysqli_stmt_bind_param($update_number_stmt, "si", $student_number, $student_id);
                            mysqli_stmt_execute($update_number_stmt);
                        }

                        // Update or insert student profile
                        $profile_check = "SELECT id FROM student_profiles WHERE student_id = ?";
                        $profile_check_stmt = mysqli_prepare($conn, $profile_check);
                        mysqli_stmt_bind_param($profile_check_stmt, "i", $student_id);
                        mysqli_stmt_execute($profile_check_stmt);
                        $profile_result = mysqli_stmt_get_result($profile_check_stmt);

                        if (mysqli_num_rows($profile_result) > 0) {
                            // Update existing profile
                            $update_profile = "UPDATE student_profiles SET phone = ?, address = ?, city = ?, state = ?,
                                             zip_code = ?, country = ?, date_of_birth = ?, gender = ?,
                                             emergency_contact_name = ?, emergency_contact_phone = ?,
                                             emergency_contact_relationship = ? WHERE student_id = ?";
                            $update_profile_stmt = mysqli_prepare($conn, $update_profile);
                            mysqli_stmt_bind_param($update_profile_stmt, "sssssssssssi",
                                $phone, $address, $city, $state, $zip_code, $country, $date_of_birth, $gender,
                                $emergency_contact_name, $emergency_contact_phone, $emergency_contact_relationship, $student_id);
                            mysqli_stmt_execute($update_profile_stmt);
                        } else {
                            // Insert new profile
                            $insert_profile = "INSERT INTO student_profiles (student_id, phone, address, city, state,
                                             zip_code, country, date_of_birth, gender, emergency_contact_name,
                                             emergency_contact_phone, emergency_contact_relationship)
                                             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                            $insert_profile_stmt = mysqli_prepare($conn, $insert_profile);
                            mysqli_stmt_bind_param($insert_profile_stmt, "isssssssssss",
                                $student_id, $phone, $address, $city, $state, $zip_code, $country,
                                $date_of_birth, $gender, $emergency_contact_name, $emergency_contact_phone,
                                $emergency_contact_relationship);
                            mysqli_stmt_execute($insert_profile_stmt);
                        }
                    }

                    $message = "Profile updated successfully!";
                    $message_type = "success";

                    // Refresh student data
                    mysqli_stmt_execute($student_stmt);
                    $student = mysqli_fetch_assoc(mysqli_stmt_get_result($student_stmt));
                } else {
                    $message = "Error updating profile: " . mysqli_error($conn);
                    $message_type = "error";
                }
                break;

            case 'change_password':
                $current_password = $_POST['current_password'];
                $new_password = $_POST['new_password'];
                $confirm_password = $_POST['confirm_password'];

                if ($new_password !== $confirm_password) {
                    $message = "New passwords do not match!";
                    $message_type = "error";
                } elseif (strlen($new_password) < 6) {
                    $message = "Password must be at least 6 characters long!";
                    $message_type = "error";
                } else {
                    // Verify current password
                    $verify_password = "SELECT password FROM users WHERE id = ?";
                    $verify_stmt = mysqli_prepare($conn, $verify_password);
                    mysqli_stmt_bind_param($verify_stmt, "i", $_SESSION['user_id']);
                    mysqli_stmt_execute($verify_stmt);
                    $verify_result = mysqli_stmt_get_result($verify_stmt);
                    $current_hash = mysqli_fetch_assoc($verify_result)['password'];

                    if (password_verify($current_password, $current_hash)) {
                        // Update password
                        $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
                        $update_password = "UPDATE users SET password = ? WHERE id = ?";
                        $update_password_stmt = mysqli_prepare($conn, $update_password);
                        mysqli_stmt_bind_param($update_password_stmt, "si", $new_hash, $_SESSION['user_id']);

                        if (mysqli_stmt_execute($update_password_stmt)) {
                            $message = "Password changed successfully!";
                            $message_type = "success";
                        } else {
                            $message = "Error changing password: " . mysqli_error($conn);
                            $message_type = "error";
                        }
                    } else {
                        $message = "Current password is incorrect!";
                        $message_type = "error";
                    }
                }
                break;
        }
    }
}

// Include the shared header
include 'includes/header.php';
?>

<div class="mb-6 sm:mb-8">
    <h1 class="text-2xl sm:text-3xl font-bold text-seait-dark mb-2">Student Profile</h1>
    <p class="text-sm sm:text-base text-gray-600">Manage your personal information and account settings</p>
</div>

<!-- Message Display -->
<?php if ($message): ?>
<div class="mb-6 p-4 rounded-lg <?php echo $message_type === 'success' ? 'bg-green-50 border border-green-200 text-green-800' : 'bg-red-50 border border-red-200 text-red-800'; ?>">
    <?php echo htmlspecialchars($message); ?>
</div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 sm:gap-8">
    <!-- Personal Information -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-medium text-gray-900">Personal Information</h2>
        </div>

        <div class="p-6">
            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="update_profile">

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">First Name <span class="text-red-500">*</span></label>
                        <input type="text" name="first_name" value="<?php echo htmlspecialchars($student['first_name'] ?? ''); ?>" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Last Name <span class="text-red-500">*</span></label>
                        <input type="text" name="last_name" value="<?php echo htmlspecialchars($student['last_name'] ?? ''); ?>" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Email <span class="text-red-500">*</span></label>
                    <input type="email" name="email" value="<?php echo htmlspecialchars($student['email'] ?? ''); ?>" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Student ID Number</label>
                    <input type="text" name="student_number" value="<?php echo htmlspecialchars($student['student_number'] ?? ''); ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange"
                           placeholder="e.g., 2024-0001">
                    <p class="text-xs text-gray-500 mt-1">Your unique student identification number</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                    <input type="tel" name="phone" value="<?php echo htmlspecialchars($student['phone'] ?? ''); ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                    <textarea name="address" rows="3"
                              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange"><?php echo htmlspecialchars($student['address'] ?? ''); ?></textarea>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">City</label>
                        <input type="text" name="city" value="<?php echo htmlspecialchars($student['city'] ?? ''); ?>"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">State</label>
                        <input type="text" name="state" value="<?php echo htmlspecialchars($student['state'] ?? ''); ?>"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">ZIP Code</label>
                        <input type="text" name="zip_code" value="<?php echo htmlspecialchars($student['zip_code'] ?? ''); ?>"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Country</label>
                        <input type="text" name="country" value="<?php echo htmlspecialchars($student['country'] ?? 'Philippines'); ?>"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Gender</label>
                        <select name="gender" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                            <option value="">Select Gender</option>
                            <option value="male" <?php echo ($student['gender'] ?? '') === 'male' ? 'selected' : ''; ?>>Male</option>
                            <option value="female" <?php echo ($student['gender'] ?? '') === 'female' ? 'selected' : ''; ?>>Female</option>
                            <option value="other" <?php echo ($student['gender'] ?? '') === 'other' ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Date of Birth</label>
                    <input type="date" name="date_of_birth" value="<?php echo htmlspecialchars($student['date_of_birth'] ?? ''); ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                </div>

                <button type="submit" class="w-full bg-seait-orange text-white px-4 py-2 rounded-md hover:bg-orange-600 transition">
                    <i class="fas fa-save mr-2"></i>Update Profile
                </button>
            </form>
        </div>
    </div>

    <!-- Emergency Contact & Password Change -->
    <div class="space-y-6 sm:space-y-8">
        <!-- Emergency Contact -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-medium text-gray-900">Emergency Contact</h2>
            </div>

            <div class="p-6">
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="update_profile">

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Contact Name</label>
                        <input type="text" name="emergency_contact_name" value="<?php echo htmlspecialchars($student['emergency_contact_name'] ?? ''); ?>"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Contact Phone</label>
                        <input type="tel" name="emergency_contact_phone" value="<?php echo htmlspecialchars($student['emergency_contact_phone'] ?? ''); ?>"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Relationship</label>
                        <input type="text" name="emergency_contact_relationship" value="<?php echo htmlspecialchars($student['emergency_contact_relationship'] ?? ''); ?>"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                    </div>

                    <button type="submit" class="w-full bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition">
                        <i class="fas fa-save mr-2"></i>Update Emergency Contact
                    </button>
                </form>
            </div>
        </div>

        <!-- Change Password -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-medium text-gray-900">Change Password</h2>
            </div>

            <div class="p-6">
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="change_password">

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Current Password <span class="text-red-500">*</span></label>
                        <input type="password" name="current_password" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">New Password <span class="text-red-500">*</span></label>
                        <input type="password" name="new_password" required minlength="6"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                        <p class="text-xs text-gray-500 mt-1">Password must be at least 6 characters long</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Confirm New Password <span class="text-red-500">*</span></label>
                        <input type="password" name="confirm_password" required minlength="6"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-seait-orange">
                    </div>

                    <button type="submit" class="w-full bg-red-600 text-white px-4 py-2 rounded-md hover:bg-red-700 transition">
                        <i class="fas fa-key mr-2"></i>Change Password
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
// Include the shared footer
include 'includes/footer.php';
?>