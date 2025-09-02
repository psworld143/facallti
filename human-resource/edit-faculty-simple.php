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
$page_title = 'Edit Faculty (Simple)';

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

// Get faculty details
$query = "SELECT f.*, fd.middle_name, fd.phone FROM faculty f LEFT JOIN faculty_details fd ON f.id = fd.faculty_id WHERE f.id = ?";
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
    $first_name = sanitize_input($_POST['first_name'] ?? '');
    $last_name = sanitize_input($_POST['last_name'] ?? '');
    $email = sanitize_input($_POST['email'] ?? '');
    $position = sanitize_input($_POST['position'] ?? '');
    $department = sanitize_input($_POST['department'] ?? '');
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    // Simple validation
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
            // Update faculty
            $update_query = "UPDATE faculty SET first_name = ?, last_name = ?, email = ?, position = ?, department = ?, is_active = ? WHERE id = ?";
            $update_stmt = mysqli_prepare($conn, $update_query);
            mysqli_stmt_bind_param($update_stmt, "sssssii", $first_name, $last_name, $email, $position, $department, $is_active, $faculty_id);

            if (mysqli_stmt_execute($update_stmt)) {
                $success = 'Faculty member updated successfully';
                
                // Refresh faculty data
                $faculty['first_name'] = $first_name;
                $faculty['last_name'] = $last_name;
                $faculty['email'] = $email;
                $faculty['position'] = $position;
                $faculty['department'] = $department;
                $faculty['is_active'] = $is_active;
            } else {
                $error = 'Error updating faculty member: ' . mysqli_error($conn);
            }
        }
    }
}

// Include the header
include 'includes/header.php';
?>

<!-- Page Header -->
<div class="mb-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Edit Faculty Member (Simple)</h1>
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
    <form method="POST">
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
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">First Name <span class="text-red-500">*</span></label>
                    <input type="text" name="first_name" value="<?php echo htmlspecialchars($faculty['first_name']); ?>" required
                           class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all bg-white shadow-sm"
                           placeholder="Enter first name">
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
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Position/Title <span class="text-red-500">*</span></label>
                    <input type="text" name="position" value="<?php echo htmlspecialchars($faculty['position']); ?>" required
                           class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all bg-white shadow-sm"
                           placeholder="Enter position/title">
                </div>
            </div>

            <div class="mt-6">
                <label class="block text-sm font-semibold text-gray-700 mb-2">Department/College <span class="text-red-500">*</span></label>
                <input type="text" name="department" value="<?php echo htmlspecialchars($faculty['department']); ?>" required
                       class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all bg-white shadow-sm"
                       placeholder="Enter department/college">
            </div>
        </div>

        <!-- Status Section -->
        <div class="bg-gray-50 rounded-xl p-6 border border-gray-200 mt-6">
            <div class="flex items-center">
                <input type="checkbox" name="is_active" id="is_active" value="1" <?php echo $faculty['is_active'] ? 'checked' : ''; ?>
                       class="h-4 w-4 text-seait-orange focus:ring-seait-orange border-gray-300 rounded">
                <label for="is_active" class="ml-2 block text-sm text-gray-900">
                    Active Faculty Member
                </label>
            </div>
        </div>

        <!-- Form Actions -->
        <div class="bg-white border-t border-gray-200 pt-6 mt-6">
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

<?php include 'includes/footer.php'; ?>
