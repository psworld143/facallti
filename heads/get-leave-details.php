<?php
session_start();
require_once '../includes/error_handler.php';
require_once '../config/database.php';

// Check if user is logged in and is a department head
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'head') {
    echo '<div class="text-center text-red-600">Unauthorized access.</div>';
    exit();
}

$leave_id = $_GET['id'] ?? '';
$table = $_GET['table'] ?? '';

// Debug information
// error_log("get-leave-details.php called with id: $leave_id, table: $table");

if (empty($leave_id) || empty($table)) {
    echo '<div class="text-center text-red-600">Invalid request parameters.</div>';
    exit();
}

// Get department head information
$head_id = $_SESSION['user_id'];
$head_query = "SELECT h.*, u.first_name, u.last_name, u.email 
               FROM heads h 
               JOIN users u ON h.user_id = u.id 
               WHERE h.user_id = ? AND h.status = 'active'";
$head_stmt = mysqli_prepare($conn, $head_query);
mysqli_stmt_bind_param($head_stmt, 'i', $head_id);
mysqli_stmt_execute($head_stmt);
$head_result = mysqli_stmt_get_result($head_stmt);

if (mysqli_num_rows($head_result) === 0) {
    echo '<div class="text-center text-red-600">Department head not found or inactive.</div>';
    exit();
}

$head_info = mysqli_fetch_assoc($head_result);
$head_department = $head_info['department'];

$leave = null;

// Get leave request details based on table
if ($table === 'faculty') {
    $leave_query = "SELECT flr.*, 
                    f.first_name, f.last_name, f.id as faculty_id, f.department, f.email,
                    lt.name as leave_type_name, lt.description as leave_type_description,
                    dh.first_name as head_first_name, dh.last_name as head_last_name,
                    hr.first_name as hr_first_name, hr.last_name as hr_last_name,
                    'faculty' as source_table
                    FROM faculty_leave_requests flr
                    JOIN faculty f ON flr.faculty_id = f.id
                    JOIN leave_types lt ON flr.leave_type_id = lt.id
                    LEFT JOIN faculty dh ON flr.department_head_id = dh.id
                    LEFT JOIN faculty hr ON flr.hr_approver_id = hr.id
                    WHERE flr.id = ? AND f.department = ?";
    
    $leave_stmt = mysqli_prepare($conn, $leave_query);
    if (!$leave_stmt) {
        error_log("Failed to prepare statement: " . mysqli_error($conn));
        echo '<div class="text-center text-red-600">Database error: Failed to prepare statement.</div>';
        exit();
    }
    
    mysqli_stmt_bind_param($leave_stmt, 'is', $leave_id, $head_department);
    if (!mysqli_stmt_execute($leave_stmt)) {
        error_log("Failed to execute statement: " . mysqli_stmt_error($leave_stmt));
        echo '<div class="text-center text-red-600">Database error: Failed to execute statement.</div>';
        exit();
    }
    
    $leave_result = mysqli_stmt_get_result($leave_stmt);
    if (!$leave_result) {
        error_log("Failed to get result: " . mysqli_error($conn));
        echo '<div class="text-center text-red-600">Database error: Failed to get result.</div>';
        exit();
    }
    
    if (mysqli_num_rows($leave_result) > 0) {
        $leave = mysqli_fetch_assoc($leave_result);
        // error_log("Found leave request: " . json_encode($leave));
    } else {
        // error_log("No leave request found for id: $leave_id, department: $head_department");
    }
}

if (!$leave) {
    echo '<div class="text-center text-red-600">Leave request not found or not accessible.</div>';
    exit();
}

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
    <!-- Faculty Information -->
    <div class="bg-gray-50 rounded-lg p-4">
        <h4 class="text-lg font-semibold text-gray-900 mb-3">Faculty Information</h4>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <p class="text-sm font-medium text-gray-600">Name</p>
                <p class="text-sm text-gray-900"><?php echo htmlspecialchars(($leave['first_name'] ?? '') . ' ' . ($leave['last_name'] ?? '')); ?></p>
            </div>
            <div>
                <p class="text-sm font-medium text-gray-600">Faculty ID</p>
                <p class="text-sm text-gray-900"><?php echo htmlspecialchars($leave['faculty_id'] ?? ''); ?></p>
            </div>
            <div>
                <p class="text-sm font-medium text-gray-600">Department</p>
                <p class="text-sm text-gray-900"><?php echo htmlspecialchars($leave['department'] ?? ''); ?></p>
            </div>
            <div>
                <p class="text-sm font-medium text-gray-600">Email</p>
                <p class="text-sm text-gray-900"><?php echo htmlspecialchars($leave['email'] ?? ''); ?></p>
            </div>
            <div>
                <p class="text-sm font-medium text-gray-600">Type</p>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800">
                    Faculty
                </span>
            </div>
        </div>
    </div>

    <!-- Leave Request Information -->
    <div class="bg-gray-50 rounded-lg p-4">
        <h4 class="text-lg font-semibold text-gray-900 mb-3">Leave Request Information</h4>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <p class="text-sm font-medium text-gray-600">Leave Type</p>
                <p class="text-sm text-gray-900"><?php echo htmlspecialchars($leave['leave_type_name'] ?? ''); ?></p>
                <?php if (!empty($leave['leave_type_description'])): ?>
                <p class="text-xs text-gray-500 mt-1"><?php echo htmlspecialchars($leave['leave_type_description'] ?? ''); ?></p>
                <?php endif; ?>
            </div>
            <div>
                <p class="text-sm font-medium text-gray-600">Status</p>
                <?php
                $status_colors = [
                    'pending' => 'bg-yellow-100 text-yellow-800',
                    'approved_by_head' => 'bg-blue-100 text-blue-800',
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
                $status = $leave['status'] ?? 'pending';
                ?>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $status_colors[$status]; ?>">
                    <?php echo $status_text[$status]; ?>
                </span>
            </div>
            <div>
                <p class="text-sm font-medium text-gray-600">Start Date</p>
                <p class="text-sm text-gray-900"><?php echo date('F j, Y', strtotime($leave['start_date'])); ?></p>
            </div>
            <div>
                <p class="text-sm font-medium text-gray-600">End Date</p>
                <p class="text-sm text-gray-900"><?php echo date('F j, Y', strtotime($leave['end_date'])); ?></p>
            </div>
            <div>
                <p class="text-sm font-medium text-gray-600">Total Days</p>
                <p class="text-sm text-gray-900"><?php echo htmlspecialchars($leave['total_days'] ?? ''); ?> days</p>
            </div>
            <div>
                <p class="text-sm font-medium text-gray-600">Working Days</p>
                <p class="text-sm text-gray-900"><?php echo $working_days; ?> days</p>
            </div>
        </div>
    </div>

    <!-- Reason -->
    <div class="bg-gray-50 rounded-lg p-4">
        <h4 class="text-lg font-semibold text-gray-900 mb-3">Reason for Leave</h4>
        <p class="text-sm text-gray-900"><?php echo nl2br(htmlspecialchars($leave['reason'] ?? '')); ?></p>
    </div>

    <!-- Approval Information -->
    <div class="bg-gray-50 rounded-lg p-4">
        <h4 class="text-lg font-semibold text-gray-900 mb-3">Approval Information</h4>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Department Head Approval -->
            <div>
                <h5 class="text-md font-medium text-gray-900 mb-2">Department Head Approval</h5>
                <?php if ($leave['head_first_name']): ?>
                <div class="space-y-2">
                    <div>
                        <p class="text-sm font-medium text-gray-600">Department Head</p>
                        <p class="text-sm text-gray-900"><?php echo htmlspecialchars(($leave['head_first_name'] ?? '') . ' ' . ($leave['head_last_name'] ?? '')); ?></p>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-600">Status</p>
                        <?php
                        $head_status = $leave['department_head_approval'] ?? 'pending';
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
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $head_status_colors[$head_status]; ?>">
                            <?php echo $head_status_text[$head_status]; ?>
                        </span>
                    </div>
                    <?php if (!empty($leave['department_head_comment'])): ?>
                    <div>
                        <p class="text-sm font-medium text-gray-600">Comment</p>
                        <p class="text-sm text-gray-900"><?php echo nl2br(htmlspecialchars($leave['department_head_comment'] ?? '')); ?></p>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($leave['department_head_approved_at'])): ?>
                    <div>
                        <p class="text-sm font-medium text-gray-600">Date</p>
                        <p class="text-sm text-gray-900"><?php echo date('F j, Y g:i A', strtotime($leave['department_head_approved_at'])); ?></p>
                    </div>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                <p class="text-sm text-gray-500">No department head assigned</p>
                <?php endif; ?>
            </div>

            <!-- HR Approval -->
            <div>
                <h5 class="text-md font-medium text-gray-900 mb-2">HR Approval</h5>
                <?php if ($leave['hr_first_name']): ?>
                <div class="space-y-2">
                    <div>
                        <p class="text-sm font-medium text-gray-600">HR Approver</p>
                        <p class="text-sm text-gray-900"><?php echo htmlspecialchars(($leave['hr_first_name'] ?? '') . ' ' . ($leave['hr_last_name'] ?? '')); ?></p>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-600">Status</p>
                        <?php
                        $hr_status = $leave['hr_approval'] ?? 'pending';
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
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $hr_status_colors[$hr_status]; ?>">
                            <?php echo $hr_status_text[$hr_status]; ?>
                        </span>
                    </div>
                    <?php if (!empty($leave['hr_comment'])): ?>
                    <div>
                        <p class="text-sm font-medium text-gray-600">Comment</p>
                        <p class="text-sm text-gray-900"><?php echo nl2br(htmlspecialchars($leave['hr_comment'] ?? '')); ?></p>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($leave['hr_approved_at'])): ?>
                    <div>
                        <p class="text-sm font-medium text-gray-600">Date</p>
                        <p class="text-sm text-gray-900"><?php echo date('F j, Y g:i A', strtotime($leave['hr_approved_at'])); ?></p>
                    </div>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                <p class="text-sm text-gray-500">No HR approval yet</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Request Timeline -->
    <div class="bg-gray-50 rounded-lg p-4">
        <h4 class="text-lg font-semibold text-gray-900 mb-3">Request Timeline</h4>
        <div class="space-y-3">
            <div class="flex items-center">
                <div class="w-3 h-3 bg-green-500 rounded-full mr-3"></div>
                <div>
                    <p class="text-sm font-medium text-gray-900">Request Submitted</p>
                    <p class="text-xs text-gray-500"><?php echo date('F j, Y g:i A', strtotime($leave['created_at'])); ?></p>
                </div>
            </div>
            <?php if (!empty($leave['department_head_approved_at'])): ?>
            <div class="flex items-center">
                <div class="w-3 h-3 bg-blue-500 rounded-full mr-3"></div>
                <div>
                    <p class="text-sm font-medium text-gray-900">Department Head <?php echo $leave['department_head_approval'] === 'approved' ? 'Approved' : 'Rejected'; ?></p>
                    <p class="text-xs text-gray-500"><?php echo date('F j, Y g:i A', strtotime($leave['department_head_approved_at'])); ?></p>
                </div>
            </div>
            <?php endif; ?>
            <?php if (!empty($leave['hr_approved_at'])): ?>
            <div class="flex items-center">
                <div class="w-3 h-3 bg-green-500 rounded-full mr-3"></div>
                <div>
                    <p class="text-sm font-medium text-gray-900">HR <?php echo $leave['hr_approval'] === 'approved' ? 'Approved' : 'Rejected'; ?></p>
                    <p class="text-xs text-gray-500"><?php echo date('F j, Y g:i A', strtotime($leave['hr_approved_at'])); ?></p>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
