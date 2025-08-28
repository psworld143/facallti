<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/id_encryption.php';

// Check if user is logged in and has human_resource role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'human_resource') {
    header('Location: ../index.php');
    exit();
}

// Set page title
$page_title = 'Edit Department';

// Check if department ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: manage-departments.php');
    exit();
}

// Decrypt the department ID
$department_id = safe_decrypt_id($_GET['id']);
if ($department_id <= 0) {
    header('Location: manage-departments.php');
    exit();
}

// Get department details
$query = "SELECT * FROM departments WHERE id = ?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $department_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (!$department = mysqli_fetch_assoc($result)) {
    header('Location: manage-departments.php');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $department_name = sanitize_input($_POST['department_name'] ?? '');
    $description = sanitize_input($_POST['description'] ?? '');
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    // Validate required fields
    if (empty($department_name)) {
        $error = 'Department name is required';
    } else {
        // Check if department name already exists (excluding current department)
        $check_query = "SELECT id FROM departments WHERE name = ? AND id != ?";
        $check_stmt = mysqli_prepare($conn, $check_query);
        mysqli_stmt_bind_param($check_stmt, "si", $department_name, $department_id);
        mysqli_stmt_execute($check_stmt);
        $check_result = mysqli_stmt_get_result($check_stmt);

        if (mysqli_num_rows($check_result) > 0) {
            $error = 'Department name already exists';
        } else {
            // Update department
            $update_query = "UPDATE departments SET name = ?, description = ?, is_active = ?, updated_at = NOW() WHERE id = ?";
            $update_stmt = mysqli_prepare($conn, $update_query);
            mysqli_stmt_bind_param($update_stmt, "ssii", $department_name, $description, $is_active, $department_id);

            if (mysqli_stmt_execute($update_stmt)) {
                $success = 'Department updated successfully';
                // Refresh department data
                $department['name'] = $department_name;
                $department['description'] = $description;
                $department['is_active'] = $is_active;
            } else {
                $error = 'Error updating department: ' . mysqli_error($conn);
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
            <h1 class="text-2xl font-bold text-gray-900">Edit Department</h1>
            <p class="text-gray-600">Update department information</p>
        </div>
        <a href="manage-departments.php" class="bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600 transform transition-all hover:scale-105 font-medium">
            <i class="fas fa-arrow-left mr-2"></i>Back to Departments
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

<!-- Edit Department Form -->
<div class="bg-white rounded-xl shadow-lg p-6">
    <form method="POST" class="space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Department Name *</label>
                <input type="text" name="department_name" value="<?php echo htmlspecialchars($department['name']); ?>" required
                       class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all"
                       placeholder="Enter department name">
            </div>
            

        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
            <textarea name="description" rows="4"
                      class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-seait-orange focus:ring-2 focus:ring-seait-orange/20 transition-all"
                      placeholder="Enter department description"><?php echo htmlspecialchars($department['description'] ?? ''); ?></textarea>
        </div>

        <div class="flex items-center">
            <input type="checkbox" name="is_active" id="is_active" value="1" <?php echo $department['is_active'] ? 'checked' : ''; ?>
                   class="h-4 w-4 text-seait-orange focus:ring-seait-orange border-gray-300 rounded">
            <label for="is_active" class="ml-2 block text-sm text-gray-900">
                Active Department
            </label>
        </div>

        <div class="flex justify-end space-x-3">
            <a href="manage-departments.php" 
               class="px-6 py-2 text-gray-600 bg-gray-200 rounded-lg hover:bg-gray-300 transition-colors">
                Cancel
            </a>
            <button type="submit" 
                    class="px-6 py-2 bg-gradient-to-r from-seait-orange to-orange-500 text-white rounded-lg hover:from-orange-500 hover:to-seait-orange transform transition-all hover:scale-105">
                <i class="fas fa-save mr-2"></i>Update Department
            </button>
        </div>
    </form>
</div>

<!-- Department Information -->
<div class="mt-6 bg-gray-50 rounded-xl p-6">
    <h3 class="text-lg font-medium text-gray-900 mb-4">Department Information</h3>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
        <div>
            <span class="font-medium text-gray-700">Created:</span>
            <span class="text-gray-900"><?php echo date('F j, Y g:i A', strtotime($department['created_at'])); ?></span>
        </div>
        <?php if ($department['updated_at']): ?>
        <div>
            <span class="font-medium text-gray-700">Last Updated:</span>
            <span class="text-gray-900"><?php echo date('F j, Y g:i A', strtotime($department['updated_at'])); ?></span>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
