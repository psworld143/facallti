<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is faculty (teacher role)
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    echo '<!DOCTYPE html><html><head><title>Faculty Login Required</title></head><body>';
    echo '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">';
    echo '<strong>Login Required:</strong><br>';
    echo 'Session user_id: ' . ($_SESSION['user_id'] ?? 'NOT SET') . '<br>';
    echo 'Session role: ' . ($_SESSION['role'] ?? 'NOT SET') . '<br>';
    echo '<a href="../index.php?login=required&redirect=faculty-leave" class="text-blue-600 underline">Click here to login</a>';
    echo '</div>';
    echo '</body></html>';
    exit();
}

$page_title = 'My Leave Requests';

// Get faculty information
$faculty_id = $_SESSION['user_id'];

// Check database connection
if (!$conn) {
    echo '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">Database connection failed. Please try again later.</div>';
    exit();
}

// Get faculty information
$faculty_query = "SELECT id, first_name, last_name, email, department FROM faculty WHERE id = ? AND is_active = 1";
$faculty_stmt = mysqli_prepare($conn, $faculty_query);

if (!$faculty_stmt) {
    echo '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">Database query preparation failed.</div>';
    exit();
}

mysqli_stmt_bind_param($faculty_stmt, 'i', $faculty_id);
mysqli_stmt_execute($faculty_stmt);
$faculty_result = mysqli_stmt_get_result($faculty_stmt);
$faculty_info = mysqli_fetch_assoc($faculty_result);

if (!$faculty_info) {
    echo '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">Faculty information not found. Please contact administrator.</div>';
    exit();
}

// Get leave requests for this faculty
$leave_query = "SELECT flr.*, lt.name as leave_type_name
                FROM faculty_leave_requests flr
                JOIN leave_types lt ON flr.leave_type_id = lt.id
                WHERE flr.faculty_id = ?
                ORDER BY flr.created_at DESC";

$leave_stmt = mysqli_prepare($conn, $leave_query);
if ($leave_stmt) {
    mysqli_stmt_bind_param($leave_stmt, 'i', $faculty_id);
    mysqli_stmt_execute($leave_stmt);
    $leave_result = mysqli_stmt_get_result($leave_stmt);
} else {
    $leave_result = false;
}

// Get leave types for the form
$leave_types_query = "SELECT id, name FROM leave_types WHERE is_active = 1 ORDER BY name";
$leave_types_result = mysqli_query($conn, $leave_types_query);

// Get leave balance
$current_year = date('Y');
$balance_query = "SELECT flb.*, lt.name as leave_type_name 
                  FROM faculty_leave_balances flb
                  JOIN leave_types lt ON flb.leave_type_id = lt.id
                  WHERE flb.faculty_id = ? AND flb.year = ?
                  ORDER BY lt.name";

$balance_stmt = mysqli_prepare($conn, $balance_query);
if ($balance_stmt) {
    mysqli_stmt_bind_param($balance_stmt, 'ii', $faculty_id, $current_year);
    mysqli_stmt_execute($balance_stmt);
    $balance_result = mysqli_stmt_get_result($balance_stmt);
} else {
    $balance_result = false;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Leave Requests - Faculty Portal</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .seait-orange { background-color: #FF6B35; }
        .text-seait-orange { color: #FF6B35; }
        .seait-dark { color: #2C3E50; }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Header -->
    <div class="bg-white shadow-sm border-b border-gray-200 p-4">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-xl font-bold text-seait-dark">Faculty Portal</h1>
                <p class="text-sm text-gray-600">Welcome, <?php echo htmlspecialchars($faculty_info['first_name'] . ' ' . $faculty_info['last_name']); ?></p>
                <p class="text-xs text-gray-500">Department: <?php echo htmlspecialchars($faculty_info['department']); ?></p>
            </div>
            <a href="../index.php" class="text-seait-orange hover:text-orange-600">Logout</a>
        </div>
    </div>

    <div class="container mx-auto px-4 py-6">
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
                    <?php if ($balance_result && mysqli_num_rows($balance_result) > 0): ?>
                        <?php while ($balance = mysqli_fetch_assoc($balance_result)): ?>
                            <div class="bg-gray-50 rounded-lg p-4">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm font-medium text-gray-600"><?php echo htmlspecialchars($balance['leave_type_name']); ?></p>
                                        <p class="text-lg font-semibold text-gray-900"><?php echo ($balance['total_days'] - $balance['used_days']); ?> days</p>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-xs text-gray-500">Used: <?php echo $balance['used_days']; ?></p>
                                        <p class="text-xs text-gray-500">Total: <?php echo $balance['total_days']; ?></p>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="col-span-full text-center py-8">
                            <i class="fas fa-calendar-times text-gray-400 text-4xl mb-4"></i>
                            <p class="text-gray-500">No leave balance information available</p>
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
                            <?php if ($leave_result && mysqli_num_rows($leave_result) > 0): ?>
                                <?php while ($leave = mysqli_fetch_assoc($leave_result)): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($leave['leave_type_name']); ?></div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900"><?php echo date('M d, Y', strtotime($leave['start_date'])); ?></div>
                                            <div class="text-sm text-gray-500">to <?php echo date('M d, Y', strtotime($leave['end_date'])); ?></div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo $leave['total_days']; ?> days</td>
                                        <td class="px-6 py-4 whitespace-nowrap">
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
                                            ?>
                                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?php echo $status_colors[$leave['status']] ?? 'bg-gray-100 text-gray-800'; ?>">
                                                <?php echo $status_text[$leave['status']] ?? ucfirst($leave['status']); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <button onclick="viewLeaveDetails(<?php echo $leave['id']; ?>)" class="text-seait-orange hover:text-orange-600 mr-3">
                                                <i class="fas fa-eye"></i> View
                                            </button>
                                            <?php if ($leave['status'] === 'pending'): ?>
                                            <button onclick="cancelLeaveRequest(<?php echo $leave['id']; ?>)" class="text-red-600 hover:text-red-800">
                                                <i class="fas fa-times"></i> Cancel
                                            </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="px-6 py-4 text-center text-gray-500">No leave requests found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
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
                                <?php if ($leave_types_result): ?>
                                    <?php while ($leave_type = mysqli_fetch_assoc($leave_types_result)): ?>
                                        <option value="<?php echo $leave_type['id']; ?>">
                                            <?php echo htmlspecialchars($leave_type['name']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                            <input type="date" name="start_date" required class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-seait-orange focus:border-transparent">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">End Date</label>
                            <input type="date" name="end_date" required class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-seait-orange focus:border-transparent">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Reason</label>
                        <textarea name="reason" required rows="3" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-seait-orange focus:border-transparent" placeholder="Enter the reason for leave request"></textarea>
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
        <div class="relative top-10 mx-auto p-5 border w-11/12 max-w-4xl shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-medium text-gray-900">Leave Request Details</h3>
                    <button onclick="closeLeaveDetailsModal()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times text-xl"></i>
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
        fetch(`get-leave-details.php?id=${leaveId}&table=faculty`)
            .then(response => response.text())
            .then(html => {
                document.getElementById('leaveDetailsContent').innerHTML = html;
                document.getElementById('leaveDetailsModal').classList.remove('hidden');
            })
            .catch(error => {
                console.error('Error loading leave details:', error);
                alert('Error loading leave details.');
            });
    }

    function closeLeaveDetailsModal() {
        document.getElementById('leaveDetailsModal').classList.add('hidden');
    }

    function cancelLeaveRequest(leaveId) {
        if (confirm('Are you sure you want to cancel this leave request?')) {
            fetch('cancel-leave-request.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    leave_id: leaveId,
                    table: 'faculty'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Leave request cancelled successfully!');
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while cancelling the leave request.');
            });
        }
    }

    // Handle new leave form submission
    document.getElementById('newLeaveForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        formData.append('faculty_id', '<?php echo $faculty_id; ?>');
        
        fetch('submit-leave-request.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Leave request submitted successfully!');
                closeNewLeaveModal();
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error submitting leave request');
        });
    });
    </script>
</body>
</html>