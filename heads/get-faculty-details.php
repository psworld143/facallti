<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is a department head
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'head') {
    http_response_code(403);
    exit('Unauthorized');
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    exit('Invalid request');
}

$faculty_id = (int)$_GET['id'];
$head_id = $_SESSION['user_id'];

// Get department head information
$head_query = "SELECT department FROM heads WHERE user_id = ? AND status = 'active'";
$head_stmt = mysqli_prepare($conn, $head_query);
mysqli_stmt_bind_param($head_stmt, 'i', $head_id);
mysqli_stmt_execute($head_stmt);
$head_result = mysqli_stmt_get_result($head_stmt);

if (mysqli_num_rows($head_result) === 0) {
    http_response_code(403);
    exit('Unauthorized');
}

$head_info = mysqli_fetch_assoc($head_result);
$head_department = $head_info['department'];

// Get faculty member details (only for the head's department)
$query = "SELECT f.*, fd.bio, fd.phone, fd.address
          FROM faculty f
          LEFT JOIN faculty_details fd ON f.id = fd.faculty_id
          WHERE f.id = ? AND f.department = ? AND f.is_active = 1";

$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, 'is', $faculty_id, $head_department);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) === 0) {
    echo '<div class="text-center text-red-600">Faculty member not found or not in your department.</div>';
    exit();
}

$faculty = mysqli_fetch_assoc($result);
?>

<div class="space-y-6">
    <!-- Faculty Information -->
    <div class="bg-gray-50 rounded-lg p-4">
        <h4 class="text-lg font-semibold text-gray-900 mb-3">Faculty Information</h4>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <p class="text-sm font-medium text-gray-600">Name</p>
                <p class="text-sm text-gray-900"><?php echo htmlspecialchars($faculty['first_name'] . ' ' . $faculty['last_name']); ?></p>
            </div>
            <div>
                <p class="text-sm font-medium text-gray-600">Email</p>
                <p class="text-sm text-gray-900"><?php echo htmlspecialchars($faculty['email']); ?></p>
            </div>
            <div>
                <p class="text-sm font-medium text-gray-600">Position</p>
                <p class="text-sm text-gray-900"><?php echo htmlspecialchars($faculty['position']); ?></p>
            </div>
            <div>
                <p class="text-sm font-medium text-gray-600">Department</p>
                <p class="text-sm text-gray-900"><?php echo htmlspecialchars($faculty['department']); ?></p>
            </div>
            <?php if (!empty($faculty['phone'])): ?>
            <div>
                <p class="text-sm font-medium text-gray-600">Phone</p>
                <p class="text-sm text-gray-900"><?php echo htmlspecialchars($faculty['phone']); ?></p>
            </div>
            <?php endif; ?>
            <div>
                <p class="text-sm font-medium text-gray-600">Status</p>
                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                    Active
                </span>
            </div>
        </div>
    </div>

    <?php if (!empty($faculty['bio'])): ?>
    <!-- Bio -->
    <div class="bg-gray-50 rounded-lg p-4">
        <h4 class="text-lg font-semibold text-gray-900 mb-3">Biography</h4>
        <p class="text-sm text-gray-700"><?php echo nl2br(htmlspecialchars($faculty['bio'])); ?></p>
    </div>
    <?php endif; ?>

    <!-- Leave Management -->
    <div class="bg-gray-50 rounded-lg p-4">
        <h4 class="text-lg font-semibold text-gray-900 mb-3">Leave Management</h4>
        <div class="text-center py-8">
            <i class="fas fa-calendar-plus text-gray-400 text-4xl mb-4"></i>
            <p class="text-gray-600 mb-4">No leave requests found for this faculty member.</p>
            <button onclick="createLeaveRequest(<?php echo $faculty['id']; ?>)" class="bg-seait-orange text-white px-4 py-2 rounded-lg hover:bg-orange-600 transition-colors duration-200">
                <i class="fas fa-plus mr-2"></i>Create Leave Request
            </button>
        </div>
    </div>

    <!-- Actions -->
    <div class="flex justify-end space-x-3">
        <button onclick="closeLeaveDetailsModal()" class="bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600 transition-colors duration-200">
            Close
        </button>
        <button onclick="createLeaveRequest(<?php echo $faculty['id']; ?>)" class="bg-seait-orange text-white px-4 py-2 rounded-lg hover:bg-orange-600 transition-colors duration-200">
            <i class="fas fa-plus mr-2"></i>Create Leave Request
        </button>
    </div>
</div>
