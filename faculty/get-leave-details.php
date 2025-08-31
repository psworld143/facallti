<?php
session_start();
require_once '../includes/error_handler.php';
require_once '../config/database.php';

// Check if user is logged in and is faculty
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    http_response_code(403);
    exit('Unauthorized');
}

if (!isset($_GET['leave_id']) || !is_numeric($_GET['leave_id'])) {
    http_response_code(400);
    exit('Invalid request');
}

$leave_id = (int)$_GET['leave_id'];
$faculty_id = $_SESSION['user_id'];

// Get faculty information
$faculty_query = "SELECT id, first_name, last_name, email, department FROM faculty WHERE id = ? AND is_active = 1";
$faculty_stmt = mysqli_prepare($conn, $faculty_query);
mysqli_stmt_bind_param($faculty_stmt, 'i', $faculty_id);
mysqli_stmt_execute($faculty_stmt);
$faculty_result = mysqli_stmt_get_result($faculty_stmt);

if (mysqli_num_rows($faculty_result) === 0) {
    http_response_code(403);
    exit('Unauthorized');
}

$faculty_info = mysqli_fetch_assoc($faculty_result);

// Get leave request details from faculty_leave_requests table (only for the faculty's own requests)
$query = "SELECT flr.*, 
          f.first_name, f.last_name, f.id as faculty_id, f.department, f.email,
          lt.name as leave_type_name, lt.description as leave_type_description,
          dh.first_name as head_first_name, dh.last_name as head_last_name,
          hr.first_name as hr_first_name, hr.last_name as hr_last_name
          FROM faculty_leave_requests flr
          JOIN faculty f ON flr.faculty_id = f.id
          JOIN leave_types lt ON flr.leave_type_id = lt.id
          LEFT JOIN faculty dh ON flr.department_head_id = dh.id
          LEFT JOIN faculty hr ON flr.hr_approver_id = hr.id
          WHERE flr.id = ? AND flr.faculty_id = ?";

$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, 'ii', $leave_id, $faculty_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) === 0) {
    echo '<div class="text-center text-red-600">Leave request not found or not authorized to view.</div>';
    exit();
}

$leave = mysqli_fetch_assoc($result);

// Calculate working days (excluding weekends)
function calculateWorkingDays($start_date, $end_date) {
    $begin = strtotime($start_date);
    $end = strtotime($end_date);
    
    if ($begin > $end) {
        return 0;
    }
    
    $working_days = 0;
    $days_in_seconds = 86400;
    
    for($i = $begin; $i <= $end; $i += $days_in_seconds) {
        $day_of_week = date('N', $i);
        if ($day_of_week < 6) { // Monday = 1, Friday = 5
            $working_days++;
        }
    }
    
    return $working_days;
}

$working_days = calculateWorkingDays($leave['start_date'], $leave['end_date']);
?>

<div class="space-y-6">
    <!-- Leave Request Details -->
    <div class="bg-gray-50 rounded-lg p-4">
        <h4 class="text-lg font-semibold text-gray-900 mb-3">Leave Request Details</h4>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <p class="text-sm font-medium text-gray-600">Leave Type</p>
                <p class="text-sm text-gray-900"><?php echo htmlspecialchars($leave['leave_type_name'] ?? ''); ?></p>
                <p class="text-xs text-gray-500 mt-1"><?php echo htmlspecialchars($leave['leave_type_description'] ?? ''); ?></p>
            </div>
            <div>
                <p class="text-sm font-medium text-gray-600">Status</p>
                <?php
                $status_colors = [
                    'pending' => 'bg-yellow-100 text-yellow-800',
                    'approved_by_head' => 'bg-orange-100 text-orange-800',
                    'approved_by_hr' => 'bg-green-100 text-green-800',
                    'rejected' => 'bg-red-100 text-red-800',
                    'cancelled' => 'bg-gray-100 text-gray-800'
                ];
                $status_text = [
                    'pending' => 'Pending',
                    'approved_by_head' => 'Approved by Head',
                    'approved_by_hr' => 'Approved by HR',
                    'rejected' => 'Rejected',
                    'cancelled' => 'Cancelled'
                ];
                ?>
                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?php echo $status_colors[$leave['status']] ?? 'bg-gray-100 text-gray-800'; ?>">
                    <?php echo $status_text[$leave['status']] ?? ucfirst($leave['status']); ?>
                </span>
            </div>
            <div>
                <p class="text-sm font-medium text-gray-600">Start Date</p>
                <p class="text-sm text-gray-900"><?php echo date('F d, Y (l)', strtotime($leave['start_date'])); ?></p>
            </div>
            <div>
                <p class="text-sm font-medium text-gray-600">End Date</p>
                <p class="text-sm text-gray-900"><?php echo date('F d, Y (l)', strtotime($leave['end_date'])); ?></p>
            </div>
            <div>
                <p class="text-sm font-medium text-gray-600">Total Days</p>
                <p class="text-sm text-gray-900"><?php echo $leave['total_days']; ?> days</p>
            </div>
            <div>
                <p class="text-sm font-medium text-gray-600">Working Days</p>
                <p class="text-sm text-gray-900"><?php echo $working_days; ?> days</p>
            </div>
        </div>
        <div class="mt-4">
            <p class="text-sm font-medium text-gray-600">Reason</p>
            <p class="text-sm text-gray-900 mt-1"><?php echo nl2br(htmlspecialchars($leave['reason'] ?? '')); ?></p>
        </div>
    </div>

    <!-- Approval Information -->
    <div class="bg-gray-50 rounded-lg p-4">
        <h4 class="text-lg font-semibold text-gray-900 mb-3">Approval Information</h4>
        
        <!-- Department Head Approval -->
        <div class="mb-4">
            <h5 class="text-md font-medium text-gray-700 mb-2">Department Head Approval</h5>
            <?php if ($leave['head_first_name']): ?>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <p class="text-sm font-medium text-gray-600">Department Head</p>
                        <p class="text-sm text-gray-900"><?php echo htmlspecialchars($leave['head_first_name'] . ' ' . $leave['head_last_name']); ?></p>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-600">Status</p>
                        <?php
                        $head_status_colors = [
                            'pending' => 'bg-yellow-100 text-yellow-800',
                            'approved' => 'bg-green-100 text-green-800',
                            'rejected' => 'bg-red-100 text-red-800'
                        ];
                        $head_status_text = [
                            'pending' => 'Pending',
                            'approved' => 'Approved',
                            'rejected' => 'Rejected'
                        ];
                        ?>
                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?php echo $head_status_colors[$leave['department_head_approval']] ?? 'bg-gray-100 text-gray-800'; ?>">
                            <?php echo $head_status_text[$leave['department_head_approval']] ?? ucfirst($leave['department_head_approval']); ?>
                        </span>
                    </div>
                    <?php if ($leave['department_head_comment']): ?>
                        <div class="md:col-span-2">
                            <p class="text-sm font-medium text-gray-600">Comment</p>
                            <p class="text-sm text-gray-900"><?php echo nl2br(htmlspecialchars($leave['department_head_comment'])); ?></p>
                        </div>
                    <?php endif; ?>
                    <?php if ($leave['department_head_approved_at']): ?>
                        <div>
                            <p class="text-sm font-medium text-gray-600">Approved At</p>
                            <p class="text-sm text-gray-900"><?php echo date('F d, Y g:i A', strtotime($leave['department_head_approved_at'])); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <p class="text-sm text-gray-500">No department head assigned</p>
            <?php endif; ?>
        </div>

        <!-- HR Approval -->
        <div>
            <h5 class="text-md font-medium text-gray-700 mb-2">HR Approval</h5>
            <?php if ($leave['hr_first_name']): ?>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <p class="text-sm font-medium text-gray-600">HR Approver</p>
                        <p class="text-sm text-gray-900"><?php echo htmlspecialchars($leave['hr_first_name'] . ' ' . $leave['hr_last_name']); ?></p>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-600">Status</p>
                        <?php
                        $hr_status_colors = [
                            'pending' => 'bg-yellow-100 text-yellow-800',
                            'approved' => 'bg-green-100 text-green-800',
                            'rejected' => 'bg-red-100 text-red-800'
                        ];
                        $hr_status_text = [
                            'pending' => 'Pending',
                            'approved' => 'Approved',
                            'rejected' => 'Rejected'
                        ];
                        ?>
                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?php echo $hr_status_colors[$leave['hr_approval']] ?? 'bg-gray-100 text-gray-800'; ?>">
                            <?php echo $hr_status_text[$leave['hr_approval']] ?? ucfirst($leave['hr_approval']); ?>
                        </span>
                    </div>
                    <?php if ($leave['hr_comment']): ?>
                        <div class="md:col-span-2">
                            <p class="text-sm font-medium text-gray-600">Comment</p>
                            <p class="text-sm text-gray-900"><?php echo nl2br(htmlspecialchars($leave['hr_comment'])); ?></p>
                        </div>
                    <?php endif; ?>
                    <?php if ($leave['hr_approved_at']): ?>
                        <div>
                            <p class="text-sm font-medium text-gray-600">Approved At</p>
                            <p class="text-sm text-gray-900"><?php echo date('F d, Y g:i A', strtotime($leave['hr_approved_at'])); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <p class="text-sm text-gray-500">No HR approval yet</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Request Information -->
    <div class="bg-gray-50 rounded-lg p-4">
        <h4 class="text-lg font-semibold text-gray-900 mb-3">Request Information</h4>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <p class="text-sm font-medium text-gray-600">Request Date</p>
                <p class="text-sm text-gray-900"><?php echo date('F d, Y g:i A', strtotime($leave['created_at'])); ?></p>
            </div>
            <div>
                <p class="text-sm font-medium text-gray-600">Last Updated</p>
                <p class="text-sm text-gray-900"><?php echo $leave['updated_at'] ? date('F d, Y g:i A', strtotime($leave['updated_at'])) : 'Not updated'; ?></p>
            </div>
        </div>
    </div>

    <!-- Action Buttons (if pending) -->
    <?php if ($leave['status'] === 'pending'): ?>
        <div class="bg-white rounded-lg p-4 border border-gray-200">
            <h4 class="text-lg font-semibold text-gray-900 mb-3">Actions</h4>
            <div class="flex space-x-3">
                <button onclick="cancelLeave(<?php echo $leave['id']; ?>)" class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 transition-colors duration-200">
                    <i class="fas fa-times mr-2"></i>Cancel Request
                </button>
            </div>
        </div>
    <?php endif; ?>
</div>
