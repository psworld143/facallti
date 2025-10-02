<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and has head role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'head') {
    header('Location: ../index.php');
    exit();
}

$page_title = 'Schedule Management';
$user_id = $_SESSION['user_id'];
$message = '';
$message_type = '';

// Get head information using email
$user_email = $_SESSION['email'];
$head_query = "SELECT h.* FROM heads h WHERE h.email = ?";
$head_stmt = mysqli_prepare($conn, $head_query);
mysqli_stmt_bind_param($head_stmt, "s", $user_email);
mysqli_stmt_execute($head_stmt);
$head_result = mysqli_stmt_get_result($head_stmt);
$head_info = mysqli_fetch_assoc($head_result);

if (!$head_info) {
    $message = "Head information not found. Please contact administrator.";
    $message_type = "error";
}

// Get all faculty in the head's department
$faculty_query = "SELECT * FROM faculty WHERE department = ? AND is_active = 1 ORDER BY last_name, first_name";
$faculty_stmt = mysqli_prepare($conn, $faculty_query);
mysqli_stmt_bind_param($faculty_stmt, "s", $head_info['department']);
mysqli_stmt_execute($faculty_stmt);
$faculty_result = mysqli_stmt_get_result($faculty_stmt);

// Get consultation hours for all faculty
$consultation_query = "SELECT ch.*, f.first_name, f.last_name, f.email 
                      FROM consultation_hours ch 
                      JOIN faculty f ON ch.teacher_id = f.id 
                      WHERE f.department = ? AND ch.is_active = 1
                      ORDER BY f.last_name, f.first_name, ch.day_of_week, ch.start_time";
$consultation_stmt = mysqli_prepare($conn, $consultation_query);
mysqli_stmt_bind_param($consultation_stmt, "s", $head_info['department']);
mysqli_stmt_execute($consultation_stmt);
$consultation_result = mysqli_stmt_get_result($consultation_stmt);

// Get consultation requests for monitoring
$requests_query = "SELECT cr.*, f.first_name, f.last_name 
                  FROM consultation_requests cr 
                  JOIN faculty f ON cr.teacher_id = f.id 
                  WHERE f.department = ? 
                  ORDER BY cr.request_time DESC 
                  LIMIT 20";
$requests_stmt = mysqli_prepare($conn, $requests_query);
mysqli_stmt_bind_param($requests_stmt, "s", $head_info['department']);
mysqli_stmt_execute($requests_stmt);
$requests_result = mysqli_stmt_get_result($requests_stmt);

include 'includes/header.php';
?>

<div class="min-h-screen bg-gray-50">
    <!-- Header -->
    <div class="bg-white shadow-sm border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="py-6">
                <div class="flex items-center justify-between">
        <div>
                        <h1 class="text-3xl font-bold text-gray-900">Schedule Management</h1>
                        <p class="mt-2 text-gray-600">Manage faculty consultation schedules and monitor requests</p>
        </div>
        <div class="flex space-x-3">
                        <a href="consultation-hours.php" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-seait-orange hover:bg-orange-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-seait-orange">
                            <i class="fas fa-calendar-plus mr-2"></i>
                            Add Consultation Hours
                        </a>
                        <a href="dashboard.php" class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-seait-orange">
                            <i class="fas fa-arrow-left mr-2"></i>
                            Back to Dashboard
            </a>
        </div>
    </div>
</div>
        </div>
        </div>
        
    <!-- Main Content -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <?php if ($message): ?>
            <div class="mb-6 p-4 rounded-md <?php echo $message_type === 'success' ? 'bg-green-50 text-green-800 border border-green-200' : 'bg-red-50 text-red-800 border border-red-200'; ?>">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <i class="fas <?php echo $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium"><?php echo htmlspecialchars($message); ?></p>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-users text-2xl text-blue-600"></i>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Total Faculty</dt>
                                <dd class="text-lg font-medium text-gray-900"><?php echo mysqli_num_rows($faculty_result); ?></dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-calendar-alt text-2xl text-green-600"></i>
        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Consultation Hours</dt>
                                <dd class="text-lg font-medium text-gray-900"><?php echo mysqli_num_rows($consultation_result); ?></dd>
                            </dl>
            </div>
        </div>
    </div>
</div>

            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-clock text-2xl text-yellow-600"></i>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Pending Requests</dt>
                                <dd class="text-lg font-medium text-gray-900">
                                    <?php 
                                    $pending_count = 0;
                                    while ($request = mysqli_fetch_assoc($requests_result)) {
                                        if ($request['status'] === 'pending') {
                                            $pending_count++;
                                        }
                                    }
                                    echo $pending_count;
                                    ?>
                                </dd>
                            </dl>
                        </div>
                </div>
                </div>
            </div>
            
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-check-circle text-2xl text-purple-600"></i>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Completed Today</dt>
                                <dd class="text-lg font-medium text-gray-900">
                                    <?php 
                                    $today = date('Y-m-d');
                                    $completed_today = 0;
                                    mysqli_data_seek($requests_result, 0); // Reset result pointer
                                    while ($request = mysqli_fetch_assoc($requests_result)) {
                                        if ($request['status'] === 'completed' && date('Y-m-d', strtotime($request['end_time'])) === $today) {
                                            $completed_today++;
                                        }
                                    }
                                    echo $completed_today;
                                    ?>
                                </dd>
                            </dl>
                        </div>
                </div>
                </div>
                </div>
            </div>
            
        <!-- Faculty Schedule Overview -->
        <div class="bg-white shadow rounded-lg mb-8">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Faculty Schedule Overview</h3>
                <p class="mt-1 text-sm text-gray-500">View all consultation schedules for your department</p>
            </div>
            <div class="p-6">
                <?php if (mysqli_num_rows($consultation_result) > 0): ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Faculty</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Day</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Time</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Room</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php 
                                mysqli_data_seek($consultation_result, 0); // Reset result pointer
                                while ($consultation = mysqli_fetch_assoc($consultation_result)): 
                                ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div class="flex-shrink-0 h-10 w-10">
                                                    <div class="h-10 w-10 rounded-full bg-seait-orange flex items-center justify-center">
                                                        <span class="text-sm font-medium text-white">
                                                            <?php echo strtoupper(substr($consultation['first_name'], 0, 1) . substr($consultation['last_name'], 0, 1)); ?>
                                                        </span>
                                                    </div>
                                                </div>
                                                <div class="ml-4">
                                                    <div class="text-sm font-medium text-gray-900">
                                                        <?php echo htmlspecialchars($consultation['first_name'] . ' ' . $consultation['last_name']); ?>
                                                    </div>
                                                    <div class="text-sm text-gray-500"><?php echo htmlspecialchars($consultation['email']); ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?php echo ucfirst($consultation['day_of_week']); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?php echo date('g:i A', strtotime($consultation['start_time'])) . ' - ' . date('g:i A', strtotime($consultation['end_time'])); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?php echo $consultation['room'] ? htmlspecialchars($consultation['room']) : '-'; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                                                Active
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <a href="edit-consultation.php?id=<?php echo $consultation['id']; ?>" class="text-seait-orange hover:text-orange-600 mr-3">
                                                <i class="fas fa-edit"></i> Edit
                                            </a>
                                            <button onclick="deleteConsultation(<?php echo $consultation['id']; ?>)" class="text-red-600 hover:text-red-900">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                </div>
                <?php else: ?>
                    <div class="text-center py-12">
                        <i class="fas fa-calendar-times text-4xl text-gray-400 mb-4"></i>
                        <h3 class="text-lg font-medium text-gray-900 mb-2">No consultation schedules found</h3>
                        <p class="text-gray-500 mb-4">Start by adding consultation hours for your faculty members.</p>
                        <a href="consultation-hours.php" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-seait-orange hover:bg-orange-600">
                            <i class="fas fa-plus mr-2"></i>
                            Add Consultation Hours
                        </a>
                    </div>
                <?php endif; ?>
                </div>
            </div>
            
        <!-- Recent Consultation Requests -->
        <div class="bg-white shadow rounded-lg">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Recent Consultation Requests</h3>
                <p class="mt-1 text-sm text-gray-500">Monitor recent student consultation requests</p>
            </div>
            <div class="p-6">
                <?php if (mysqli_num_rows($requests_result) > 0): ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Faculty</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Request Time</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Duration</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php 
                                mysqli_data_seek($requests_result, 0); // Reset result pointer
                                $count = 0;
                                while ($request = mysqli_fetch_assoc($requests_result) && $count < 10): 
                                    $count++;
                                ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?php echo htmlspecialchars($request['student_name']); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?php echo htmlspecialchars($request['first_name'] . ' ' . $request['last_name']); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo date('M j, Y g:i A', strtotime($request['request_time'])); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php
                                            $status_colors = [
                                                'pending' => 'bg-yellow-100 text-yellow-800',
                                                'accepted' => 'bg-blue-100 text-blue-800',
                                                'declined' => 'bg-red-100 text-red-800',
                                                'completed' => 'bg-green-100 text-green-800',
                                                'cancelled' => 'bg-gray-100 text-gray-800'
                                            ];
                                            $color_class = $status_colors[$request['status']] ?? 'bg-gray-100 text-gray-800';
                                            ?>
                                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?php echo $color_class; ?>">
                                                <?php echo ucfirst($request['status']); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo $request['duration_minutes'] ? $request['duration_minutes'] . ' min' : '-'; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                </div>
                <?php else: ?>
                    <div class="text-center py-12">
                        <i class="fas fa-inbox text-4xl text-gray-400 mb-4"></i>
                        <h3 class="text-lg font-medium text-gray-900 mb-2">No consultation requests found</h3>
                        <p class="text-gray-500">Consultation requests will appear here when students submit them.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function deleteConsultation(id) {
    if (confirm('Are you sure you want to delete this consultation hour? This action cannot be undone.')) {
        // Create a form and submit it
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'consultation-hours.php';
        
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'delete';
        
        const idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.name = 'consultation_id';
        idInput.value = id;
        
        form.appendChild(actionInput);
        form.appendChild(idInput);
        document.body.appendChild(form);
        form.submit();
    }
}
</script>

<?php include 'includes/footer.php'; ?>
