<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is faculty
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'faculty') {
    header('Location: ../login.php');
    exit();
}

$page_title = 'My Leave Requests';

// Get faculty information
$faculty_id = $_SESSION['user_id'];
$faculty_query = "SELECT f.*, e.id as employee_id, e.first_name, e.last_name, e.email, e.department 
                  FROM faculty f 
                  LEFT JOIN employees e ON f.email = e.email 
                  WHERE f.id = ?";
$faculty_stmt = mysqli_prepare($conn, $faculty_query);
mysqli_stmt_bind_param($faculty_stmt, 'i', $faculty_id);
mysqli_stmt_execute($faculty_stmt);
$faculty_result = mysqli_stmt_get_result($faculty_stmt);

if (mysqli_num_rows($faculty_result) === 0) {
    header('Location: logout.php');
    exit();
}

$faculty_info = mysqli_fetch_assoc($faculty_result);

// Get leave requests for this faculty
$leave_query = "SELECT lr.*, 
                lt.name as leave_type_name,
                dh.first_name as head_first_name, dh.last_name as head_last_name,
                hr.first_name as hr_first_name, hr.last_name as hr_last_name
                FROM leave_requests lr
                JOIN leave_types lt ON lr.leave_type_id = lt.id
                LEFT JOIN employees dh ON lr.department_head_id = dh.id
                LEFT JOIN employees hr ON lr.hr_approver_id = hr.id
                WHERE lr.employee_id = ?
                ORDER BY lr.created_at DESC";

$leave_stmt = mysqli_prepare($conn, $leave_query);
mysqli_stmt_bind_param($leave_stmt, 'i', $faculty_info['employee_id']);
mysqli_stmt_execute($leave_stmt);
$leave_result = mysqli_stmt_get_result($leave_stmt);

// Get leave types for the form
$leave_types_query = "SELECT * FROM leave_types WHERE is_active = 1 ORDER BY name";
$leave_types_result = mysqli_query($conn, $leave_types_query);

// Get leave balance
$balance_query = "SELECT lb.*, lt.name as leave_type_name 
                  FROM leave_balances lb
                  JOIN leave_types lt ON lb.leave_type_id = lt.id
                  WHERE lb.employee_id = ? AND lb.year = ?
                  ORDER BY lt.name";
$balance_stmt = mysqli_prepare($conn, $balance_query);
$current_year = date('Y');
mysqli_stmt_bind_param($balance_stmt, 'ii', $faculty_info['employee_id'], $current_year);
mysqli_stmt_execute($balance_stmt);
$balance_result = mysqli_stmt_get_result($balance_stmt);

include 'includes/header.php';
?>

<div class="space-y-6">
    <!-- Page Header -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-seait-dark">My Leave Requests</h1>
                <p class="text-gray-600 mt-1">Submit and manage your leave requests</p>
            </div>
            <div class="mt-4 sm:mt-0">
                <button onclick="openNewLeaveModal()" class="bg-seait-orange text-white px-4 py-2 rounded-lg hover:bg-orange-600 transition-colors duration-200 flex items-center">
                    <i class="fas fa-plus mr-2"></i>
                    New Leave Request
                </button>
            </div>
        </div>
    </div>

    <!-- Leave Balance Summary -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Leave Balance Summary (<?php echo $current_year; ?>)</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <?php if (mysqli_num_rows($balance_result) > 0): ?>
                <?php while ($balance = mysqli_fetch_assoc($balance_result)): ?>
                    <div class="bg-gray-50 rounded-lg p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-600"><?php echo htmlspecialchars($balance['leave_type_name']); ?></p>
                                <p class="text-lg font-semibold text-gray-900"><?php echo $balance['remaining_days']; ?> days</p>
                            </div>
                            <div class="text-right">
                                <p class="text-xs text-gray-500">Used: <?php echo $balance['used_days']; ?></p>
                                <p class="text-xs text-gray-500">Total: <?php echo $balance['total_days']; ?></p>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="col-span-full text-center text-gray-500">
                    No leave balance information available.
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Leave Requests Table -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">My Leave Requests</h2>
        </div>
        
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Leave Type</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date Range</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Days</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (mysqli_num_rows($leave_result) > 0): ?>
                        <?php while ($leave = mysqli_fetch_assoc($leave_result)): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="text-sm text-gray-900"><?php echo htmlspecialchars($leave['leave_type_name']); ?></span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">
                                        <?php echo date('M d, Y', strtotime($leave['start_date'])); ?>
                                    </div>
                                    <div class="text-sm text-gray-500">
                                        to <?php echo date('M d, Y', strtotime($leave['end_date'])); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="text-sm text-gray-900"><?php echo $leave['total_days']; ?> days</span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
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
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?php echo $status_colors[$leave['status']]; ?>">
                                        <?php echo $status_text[$leave['status']]; ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <div class="flex space-x-2">
                                        <button onclick="viewLeaveDetails(<?php echo $leave['id']; ?>)" class="text-seait-orange hover:text-orange-600">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <?php if ($leave['status'] === 'pending'): ?>
                                            <button onclick="cancelLeave(<?php echo $leave['id']; ?>)" class="text-red-600 hover:text-red-800">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="px-6 py-4 text-center text-gray-500">
                                No leave requests found. Submit your first leave request to get started.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- New Leave Request Modal -->
<div id="newLeaveModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
    <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-medium text-gray-900">Submit New Leave Request</h3>
                <button onclick="closeNewLeaveModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form id="newLeaveForm" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Leave Type</label>
                        <select name="leave_type_id" required class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-seait-orange focus:border-transparent">
                            <option value="">Select Leave Type</option>
                            <?php 
                            mysqli_data_seek($leave_types_result, 0);
                            while ($leave_type = mysqli_fetch_assoc($leave_types_result)): 
                            ?>
                                <option value="<?php echo $leave_type['id']; ?>">
                                    <?php echo htmlspecialchars($leave_type['name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                        <input type="date" name="start_date" required class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-seait-orange focus:border-transparent">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">End Date</label>
                        <input type="date" name="end_date" required class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-seait-orange focus:border-transparent">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Total Days</label>
                        <input type="number" name="total_days" step="0.5" min="0.5" required class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-seait-orange focus:border-transparent" readonly>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Reason</label>
                    <textarea name="reason" required rows="3" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-seait-orange focus:border-transparent" placeholder="Please provide a detailed reason for your leave request"></textarea>
                </div>

                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeNewLeaveModal()" class="bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600 transition-colors duration-200">
                        Cancel
                    </button>
                    <button type="submit" class="bg-seait-orange text-white px-4 py-2 rounded-lg hover:bg-orange-600 transition-colors duration-200">
                        Submit Request
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Leave Details Modal -->
<div id="leaveDetailsModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
    <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-medium text-gray-900">Leave Request Details</h3>
                <button onclick="closeLeaveDetailsModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div id="leaveDetailsContent">
                <!-- Content will be loaded here -->
            </div>
        </div>
    </div>
</div>

<script>
function openNewLeaveModal() {
    document.getElementById('newLeaveModal').classList.remove('hidden');
}

function closeNewLeaveModal() {
    document.getElementById('newLeaveModal').classList.add('hidden');
    document.getElementById('newLeaveForm').reset();
}

function viewLeaveDetails(leaveId) {
    // Load leave details via AJAX
    fetch(`get-leave-details.php?id=${leaveId}`)
        .then(response => response.text())
        .then(data => {
            document.getElementById('leaveDetailsContent').innerHTML = data;
            document.getElementById('leaveDetailsModal').classList.remove('hidden');
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error loading leave details');
        });
}

function closeLeaveDetailsModal() {
    document.getElementById('leaveDetailsModal').classList.add('hidden');
}

function cancelLeave(leaveId) {
    if (confirm('Are you sure you want to cancel this leave request?')) {
        fetch('cancel-leave-request.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                leave_id: leaveId
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Leave request cancelled successfully');
                location.reload();
            } else {
                alert(data.message || 'Error cancelling leave request');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error cancelling leave request');
        });
    }
}

// Calculate total days when dates change
document.addEventListener('DOMContentLoaded', function() {
    const startDateInput = document.querySelector('input[name="start_date"]');
    const endDateInput = document.querySelector('input[name="end_date"]');
    const totalDaysInput = document.querySelector('input[name="total_days"]');

    function calculateDays() {
        const startDate = new Date(startDateInput.value);
        const endDate = new Date(endDateInput.value);
        
        if (startDate && endDate && startDate <= endDate) {
            const timeDiff = endDate.getTime() - startDate.getTime();
            const daysDiff = Math.ceil(timeDiff / (1000 * 3600 * 24)) + 1; // Include both start and end dates
            totalDaysInput.value = daysDiff;
        } else {
            totalDaysInput.value = '';
        }
    }

    startDateInput.addEventListener('change', calculateDays);
    endDateInput.addEventListener('change', calculateDays);
});

// Handle new leave form submission
document.getElementById('newLeaveForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    fetch('submit-leave-request.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Leave request submitted successfully');
            closeNewLeaveModal();
            location.reload();
        } else {
            alert(data.message || 'Error submitting leave request');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error submitting leave request');
    });
});
</script>

<?php include 'includes/footer.php'; ?>
